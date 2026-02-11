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
            $this->info("Publishing deployment scripts...");
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

        if (!$scriptsPath || !File::exists($scriptsPath)) {
            $this->error("Deployment scripts path not configured or does not exist: {$scriptsPath}");
            return Command::FAILURE;
        }

        $files = File::files($scriptsPath);
        $shFiles = array_filter($files, fn($file) => $file->getExtension() === 'sh');

        if (empty($shFiles)) {
            $this->info("No .sh files found in {$scriptsPath}");
            return Command::SUCCESS;
        }

        $this->info("Found " . count($shFiles) . " deployment script(s).");

        if (!$this->option('force') && !$this->confirm('Do you wish to run all these deployment scripts?')) {
            $this->info('Operation cancelled.');
            return Command::SUCCESS;
        }

        $runPath = base_path();

        foreach ($shFiles as $file) {
            $scriptPath = $file->getRealPath();
            $scriptName = $file->getFilename();

            $this->info("Triggering: {$scriptName}");

            $command = sprintf(
                'sudo bash -c "sleep 5; cd %s && %s" &',
                $runPath,
                $scriptPath
            );

            exec($command);

            $this->line("  -> Started in background.");
        }

        $this->info("All scripts triggered.");

        return Command::SUCCESS;
    }
}
