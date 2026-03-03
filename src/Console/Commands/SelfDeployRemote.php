<?php

namespace Iperamuna\SelfDeploy\Console\Commands;

use Illuminate\Console\Command;

class SelfDeployRemote extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'selfdeploy:remote-deploy
                            {environment? : The environment to deploy to (e.g. production)}
                            {commit-hash? : Optional Git commit hash to pass to remote servers}
                            {commit-msg? : Optional Git commit message to pass to remote servers}
                            {--publish : Automatically publish/regenerate scripts on remote servers before deploying}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Trigger self-deployment on multiple remote servers via SSH, optionally passing commit metadata';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $environment = $this->argument('environment');
        $configs = config('self-deploy.environments', []);

        if (! $environment) {
            $environment = $this->choice(
                'Select environment for remote deployment',
                array_keys($configs)
            );
        }

        if (! isset($configs[$environment])) {
            $this->error("Environment [{$environment}] not found in config.");

            return Command::FAILURE;
        }

        $envConfig = $configs[$environment];
        $hosts = $envConfig['hosts'] ?? [];
        $user = $envConfig['ssh_user'] ?? get_current_user();
        $remotePath = $envConfig['remote_path'] ?? null;

        if (empty($hosts)) {
            $this->error("No hosts configured for environment [{$environment}].");
            $this->line('Add a [hosts] array to your environment configuration.');

            return Command::FAILURE;
        }

        if (! $remotePath) {
            $this->error("No [remote_path] configured for environment [{$environment}].");
            $this->line('This is the directory on the remote server where the PHP artisan command should run.');

            return Command::FAILURE;
        }

        $this->info("Starting remote deployment for [{$environment}] across ".count($hosts).' host(s).');

        $commitHash = $this->argument('commit-hash');
        $commitMsg = $this->argument('commit-msg');

        $publishFlag = $this->option('publish') ? ' --publish' : '';

        // Build the call for the remote artisan command
        $remoteCallParts = ['php artisan selfdeploy:run'];

        if ($commitHash) {
            $remoteCallParts[] = escapeshellarg($commitHash);
        }

        if ($commitMsg) {
            if (! $commitHash) {
                $remoteCallParts[] = '""';
            }
            $remoteCallParts[] = escapeshellarg($commitMsg);
        }

        $remoteCallParts[] = "--force{$publishFlag}";

        $remoteCommand = "cd {$remotePath} && ".implode(' ', $remoteCallParts);

        $hasErrors = false;

        foreach ($hosts as $host) {
            $this->info('--------------------------------------------------');
            $this->info("🚀 Deploying to: {$user}@{$host}");

            $sshCommand = sprintf(
                'ssh -t %s@%s %s',
                escapeshellarg($user),
                escapeshellarg($host),
                escapeshellarg($remoteCommand)
            );

            $this->line("Running: <comment>{$sshCommand}</comment>");

            if (app()->runningUnitTests()) {
                $exitCode = 0;
            } else {
                passthru($sshCommand, $exitCode);
            }

            if ($exitCode === 0) {
                $this->info("✅ Successfully triggered on {$host}");
            } else {
                $this->error("❌ Failed on {$host}. Exit code: {$exitCode}");
                $hasErrors = true;
            }
        }

        if (! $hasErrors) {
            $this->info('--------------------------------------------------');
            $this->info('✨ All remote deployments triggered successfully!');

            return Command::SUCCESS;
        }

        $this->error('--------------------------------------------------');
        $this->error('⚠️ Some remote deployments failed.');

        return Command::FAILURE;
    }
}
