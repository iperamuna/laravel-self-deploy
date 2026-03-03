<?php

namespace Iperamuna\SelfDeploy\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SelfDeploy extends Command
{
    protected $signature = 'selfdeploy:run
                            {commit-hash? : Optional Git commit hash to pass to scripts}
                            {commit-msg? : Optional Git commit message to pass to scripts}
                            {--script= : Specific script name to run (e.g., deploy.sh)}
                            {--force : Run without confirmation}
                            {--publish : Publish/regenerate deployment scripts before deploying}
                            {--tail : Tail the journals in tmux after deployment without confirmation}';

    protected $description = 'Execute all deployment scripts found in the configured path';

    /** @var array<int, string> */
    protected array $startedUnits = [];

    public function handle(): int
    {
        if ($this->option('publish') && ! $this->publishScripts()) {
            return Command::FAILURE;
        }

        $scriptsPath = config('self-deploy.deployment_scripts_path');

        if (! $this->validateScriptsPath($scriptsPath)) {
            return Command::FAILURE;
        }

        if ($specificScript = $this->option('script')) {
            if (File::isAbsolutePath($specificScript)) {
                if (File::exists($specificScript)) {
                    $scripts = collect([new \SplFileInfo($specificScript)]);
                } else {
                    $this->error("Specific script path [{$specificScript}] does not exist.");

                    return Command::FAILURE;
                }
            } else {
                $scripts = $this->getShellScripts($scriptsPath);
                $scripts = $scripts->filter(fn ($file) => $file->getFilename() === $specificScript);

                if ($scripts->isEmpty()) {
                    $this->error("Specific script [{$specificScript}] not found in {$scriptsPath}");

                    return Command::FAILURE;
                }
            }
        } else {
            $scripts = $this->getShellScripts($scriptsPath);
        }

        if ($scripts->isEmpty()) {

            $this->info("No .sh files found in {$scriptsPath}");

            return Command::SUCCESS;
        }

        $this->info('Found '.$scripts->count().' deployment script(s).');

        if (! $this->option('force') && ! $this->confirm('Do you wish to run all these deployment scripts?')) {
            $this->info('Operation cancelled.');

            return Command::SUCCESS;
        }

        $commitHash = $this->argument('commit-hash');
        $commitMsg = $this->argument('commit-msg');

        foreach ($scripts as $script) {
            $this->runScript($script, $commitHash, $commitMsg);
        }

        $this->info('All scripts triggered.');

        if (! empty($this->startedUnits) && ! app()->runningUnitTests()) {
            if ($this->option('tail')) {
                $this->tailJournals();
            } elseif (! $this->option('force')) {
                if ($this->confirm('Do you want to tail journals in tmux?', true)) {
                    $this->tailJournals();
                }
            }
        }

        return Command::SUCCESS;
    }

    protected function tailJournals(): void
    {
        $units = $this->startedUnits;
        $count = count($units);

        if ($this->isTmuxInstalled()) {
            $this->line('▶ <info>Opening live logs in tmux (vertical split)...</info>');

            // Kill any existing session with the same name to avoid duplicates
            exec('tmux kill-session -t plcargo-logs 2>/dev/null');

            if ($count === 1) {
                $command = sprintf(
                    'tmux new-session -s plcargo-logs "journalctl -u %s -f"',
                    escapeshellarg($units[0])
                );
            } else {
                // Use first two units for the split as requested, using vertical split (-h)
                $command = sprintf(
                    'tmux new-session -d -s plcargo-logs "journalctl -u %s -f" \; split-window -h "journalctl -u %s -f" \; attach',
                    escapeshellarg($units[0]),
                    escapeshellarg($units[1])
                );
            }

            passthru($command);

            return;
        }

        $this->warn('tmux is not installed. Tailing journals sequentially...');

        foreach ($units as $unit) {
            $this->line("▶ <info>Tailing journal for: {$unit}</info> (Press Ctrl+C to move to next)");
            passthru(sprintf('journalctl -u %s -f', escapeshellarg($unit)));
        }
    }

    protected function isTmuxInstalled(): bool
    {
        exec('command -v tmux', $output, $returnVar);

        return $returnVar === 0;
    }

    /* -----------------------------------------------------------------
     | Helpers
     |-----------------------------------------------------------------*/

    protected function publishScripts(): bool
    {
        $this->info('Publishing deployment scripts...');

        $exitCode = $this->call('selfdeploy:publish-deployment-scripts', [
            '--all' => true,
            '--force' => true,
        ]);

        if ($exitCode !== Command::SUCCESS) {
            $this->error('Failed to publish deployment scripts. Aborting.');

            return false;
        }

        return true;
    }

    protected function validateScriptsPath(?string $path): bool
    {
        if (! $path || ! File::exists($path)) {
            $this->error("Deployment scripts path not configured or does not exist: {$path}");

            return false;
        }

        return true;
    }

    protected function getShellScripts(string $path)
    {
        return collect(File::files($path))
            ->filter(fn ($file) => $file->getExtension() === 'sh')
            ->values();
    }

    protected function runScript(\SplFileInfo $file, ?string $commitHash = null, ?string $commitMsg = null): void
    {
        $scriptPath = $file->getRealPath();
        $scriptName = $file->getFilename();

        $this->info("Triggering: {$scriptName}");

        $mode = config('self-deploy.execution_mode', 'shell');

        if ($mode === 'systemd') {
            $this->runViaSystemd($scriptPath, $scriptName, $commitHash, $commitMsg);
        } else {
            $this->runViaShell($scriptPath, $commitHash, $commitMsg);
        }
    }

    /* -----------------------------------------------------------------
     | Execution modes
     |-----------------------------------------------------------------*/

    protected function runViaSystemd(string $scriptPath, string $scriptName, ?string $commitHash = null, ?string $commitMsg = null): void
    {
        $systemd = config('self-deploy.systemd', []);
        $workDir = base_path();

        $unitName = Str::of(pathinfo($scriptName, PATHINFO_FILENAME))
            ->slug()
            ->limit(30, '')
            ->append('-'.now()->format('Ymd-His'));

        $cmd = collect([
            'sudo',
            '/usr/bin/systemd-run',
            "--unit={$unitName}",
            '--property=Nice='.($systemd['nice'] ?? 10),
            '--property=IOSchedulingClass='.($systemd['io_scheduling_class'] ?? 'best-effort'),
            '--property=IOSchedulingPriority='.($systemd['io_scheduling_priority'] ?? 7),
            '--property=WorkingDirectory='.escapeshellarg($workDir),
        ]);

        if (! empty($systemd['user'])) {
            $cmd->push("--property=User={$systemd['user']}");
        }

        foreach ($systemd['env'] ?? [] as $key => $value) {
            $cmd->push('--property=Environment='.escapeshellarg("{$key}={$value}"));
        }

        if (($systemd['collect'] ?? true) === true) {
            $cmd->push('--collect');
        }

        // Prepare the script execution with parameters
        $execCmd = $scriptPath;
        if ($commitHash) {
            $execCmd .= ' '.escapeshellarg($commitHash);
        }
        if ($commitMsg) {
            // If message is provided but hash wasn't, we need a placeholder for $1
            if (! $commitHash) {
                $execCmd .= ' ""';
            }
            $execCmd .= ' '.escapeshellarg($commitMsg);
        }

        $cmd->push('/bin/bash -lc '.escapeshellarg($execCmd));

        $command = $cmd->implode(' ');

        if ($this->getOutput()->isVerbose() || app()->runningUnitTests()) {
            $this->line("Systemd command: {$command}");
        }

        if (app()->runningUnitTests()) {
            $exitCode = 0;
        } else {
            exec($command, $output, $exitCode);
        }

        if ($exitCode === 0) {
            $this->startedUnits[] = (string) $unitName;
            $this->line("  -> <info>SUCCESS</info>: Started systemd unit <comment>{$unitName}</comment>");
            $this->line("  -> Monitor: <comment>journalctl -u {$unitName} -f</comment>");
        } else {
            $this->error("  -> <error>ERROR</error>: Failed to start systemd unit (exit {$exitCode})");
            $this->line("     Command: {$command}");
        }
    }

    protected function runViaShell(string $scriptPath, ?string $commitHash = null, ?string $commitMsg = null): void
    {
        $workDir = base_path();

        // Prepare the script execution with parameters
        $execCmd = $scriptPath;
        if ($commitHash) {
            $execCmd .= ' '.escapeshellarg($commitHash);
        }
        if ($commitMsg) {
            if (! $commitHash) {
                $execCmd .= ' ""';
            }
            $execCmd .= ' '.escapeshellarg($commitMsg);
        }

        $command = sprintf(
            '%s bash -lc %s &',
            app()->runningUnitTests() ? '' : 'sudo',
            escapeshellarg("sleep 5; cd {$workDir} && {$execCmd}")
        );

        if (! app()->runningUnitTests()) {
            exec($command);
        }

        $this->line('  -> <info>SUCCESS</info>: Started in background.');
    }
}
