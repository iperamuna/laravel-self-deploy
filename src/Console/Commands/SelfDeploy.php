<?php

namespace Iperamuna\SelfDeploy\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SelfDeploy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'selfdeploy:run
                            {--force : Run without confirmation}
                            {--publish : Automatically publish/regenerate all deployment scripts before deploying}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Execute all deployment scripts found in the configured path';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if ($this->option('publish')) {
            $this->info('Publishing deployment scripts...');
            $exitCode = $this->call('selfdeploy:publish-deployment-scripts', [
                '--all' => true,
                '--force' => true,
            ]);

            if ($exitCode !== Command::SUCCESS) {
                $this->error('Failed to publish deployment scripts. Aborting deployment.');

                return Command::FAILURE;
            }
        }

        $scriptsPath = config('self-deploy.deployment_scripts_path');

        if (! $scriptsPath || ! File::exists($scriptsPath)) {
            $this->error("Deployment scripts path not configured or does not exist: {$scriptsPath}");

            return Command::FAILURE;
        }

        $files = File::files($scriptsPath);
        $shFiles = array_filter($files, fn ($file) => $file->getExtension() === 'sh');

        if (empty($shFiles)) {
            $this->info("No .sh files found in {$scriptsPath}");

            return Command::SUCCESS;
        }

        $this->info('Found '.count($shFiles).' deployment script(s).');

        if (! $this->option('force') && ! $this->confirm('Do you wish to run all these deployment scripts?')) {
            $this->info('Operation cancelled.');

            return Command::SUCCESS;
        }

        $runPath = base_path();
        $executionMode = config('self-deploy.execution_mode', 'shell');

        foreach ($shFiles as $file) {
            $scriptPath = $file->getRealPath();
            $scriptName = $file->getFilename();

            $this->info("Triggering: {$scriptName}");

            if ($executionMode === 'systemd') {
                $unitName = str($scriptName)->slug()->limit(30)->toString().'-'.now()->format('Ymd-His');
                $systemd = config('self-deploy.systemd', []);

                $command = [
                    'sudo', // systemd-run --unit typically requires root
                    '/usr/bin/systemd-run',
                    "--unit={$unitName}",
                    '--property=Nice='.($systemd['nice'] ?? 10),
                    '--property=IOSchedulingClass='.($systemd['io_scheduling_class'] ?? 'best-effort'),
                    '--property=IOSchedulingPriority='.($systemd['io_scheduling_priority'] ?? 7),
                ];

                if (! empty($systemd['user'])) {
                    $command[] = "--property=User={$systemd['user']}";
                }

                if ($systemd['collect'] ?? true) {
                    $command[] = '--collect';
                }

                $command[] = "bash -c \"cd {$runPath} && {$scriptPath}\"";

                $cmdString = implode(' ', $command);

                if ($this->getOutput()->isVerbose() || app()->runningUnitTests()) {
                    $this->info("Systemd command: {$cmdString}");
                }

                // We use exec for the systemd-run command itself as it returns immediately
                if (app()->runningUnitTests()) {
                    $resultCode = 0;
                } else {
                    exec($cmdString, $output, $resultCode);
                }

                if ($resultCode === 0) {
                    $this->line("  -> <info>SUCCESS</info>: Started systemd unit <comment>{$unitName}</comment>");
                    $this->line("  -> Monitor: <comment>journalctl -u {$unitName} -f</comment>");
                } else {
                    $this->error("  -> <error>ERROR</error>: Failed to start systemd unit. Exit code: {$resultCode}");
                    $this->line('     Command tried: '.$cmdString);
                }
            } else {
                // Classic shell background mode
                $command = sprintf(
                    '%s bash -c "sleep 5; cd %s && %s" &',
                    app()->runningUnitTests() ? '' : 'sudo',
                    $runPath,
                    $scriptPath
                );

                if (! app()->runningUnitTests()) {
                    exec($command);
                }

                $this->line('  -> <info>SUCCESS</info>: Started in background (PID: Background).');
            }
        }

        $this->info('All scripts triggered.');

        return Command::SUCCESS;
    }
}
