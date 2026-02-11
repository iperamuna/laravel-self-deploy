<?php

namespace Iperamuna\SelfDeploy\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SelfDeploy extends Command
{
    protected $signature = 'selfdeploy:run
                            {--force : Run without confirmation}
                            {--publish : Publish/regenerate deployment scripts before deploying}';

    protected $description = 'Execute all deployment scripts found in the configured path';

    public function handle(): int
    {
        if ($this->option('publish') && ! $this->publishScripts()) {
            return Command::FAILURE;
        }

        $scriptsPath = config('self-deploy.deployment_scripts_path');

        if (! $this->validateScriptsPath($scriptsPath)) {
            return Command::FAILURE;
        }

        $scripts = $this->getShellScripts($scriptsPath);

        if ($scripts->isEmpty()) {
            $this->info("No .sh files found in {$scriptsPath}");

            return Command::SUCCESS;
        }

        $this->info('Found '.$scripts->count().' deployment script(s).');

        if (! $this->option('force') && ! $this->confirm('Do you wish to run all these deployment scripts?')) {
            $this->info('Operation cancelled.');

            return Command::SUCCESS;
        }

        foreach ($scripts as $script) {
            $this->runScript($script);
        }

        $this->info('All scripts triggered.');

        return Command::SUCCESS;
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

    protected function runScript(\SplFileInfo $file): void
    {
        $scriptPath = $file->getRealPath();
        $scriptName = $file->getFilename();

        $this->info("Triggering: {$scriptName}");

        $mode = config('self-deploy.execution_mode', 'shell');

        if ($mode === 'systemd') {
            $this->runViaSystemd($scriptPath, $scriptName);
        } else {
            $this->runViaShell($scriptPath);
        }
    }

    /* -----------------------------------------------------------------
     | Execution modes
     |-----------------------------------------------------------------*/

    protected function runViaSystemd(string $scriptPath, string $scriptName): void
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

        $cmd->push('/bin/bash -lc '.escapeshellarg($scriptPath));

        $command = $cmd->implode(' ');

        if ($this->getOutput()->isVerbose()) {
            $this->line("Systemd command: {$command}");
        }

        $exitCode = app()->runningUnitTests()
            ? 0
            : exec($command, $output, $code) ?? $code;

        if ($exitCode === 0) {
            $this->line("  -> <info>SUCCESS</info>: Started systemd unit <comment>{$unitName}</comment>");
            $this->line("  -> Monitor: <comment>journalctl -u {$unitName} -f</comment>");
        } else {
            $this->error("  -> <error>ERROR</error>: Failed to start systemd unit (exit {$exitCode})");
            $this->line("     Command: {$command}");
        }
    }

    protected function runViaShell(string $scriptPath): void
    {
        $workDir = base_path();

        $command = sprintf(
            '%s bash -lc %s &',
            app()->runningUnitTests() ? '' : 'sudo',
            escapeshellarg("sleep 5; cd {$workDir} && {$scriptPath}")
        );

        if (! app()->runningUnitTests()) {
            exec($command);
        }

        $this->line('  -> <info>SUCCESS</info>: Started in background.');
    }
}
