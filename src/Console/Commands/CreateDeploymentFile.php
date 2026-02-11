<?php

namespace Iperamuna\SelfDeploy\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\select;
use function Laravel\Prompts\warning;

class CreateDeploymentFile extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'selfdeploy:create-deployment-file
                            {--deployment-name= : The name of the deployment}
                            {--environment= : The environment (e.g. production)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a Blade template for a deployment configuration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $environment = $this->option('environment');
        $deploymentName = $this->option('deployment-name');

        $configs = config('self-deploy.environments', []);

        if (empty($configs)) {
            $this->error('No environments configured in config/self-deploy.php');

            return Command::FAILURE;
        }

        // 1. Select Environment if missing
        if (! $environment) {
            $environment = select(
                label: 'Select Environment',
                options: array_keys($configs),
                required: true
            );
        }

        if (! isset($configs[$environment])) {
            $this->error("Environment [{$environment}] not found in config.");

            return Command::FAILURE;
        }

        $deployments = $configs[$environment];

        if (empty($deployments)) {
            $this->error("No deployments found for environment [{$environment}].");

            return Command::FAILURE;
        }

        // 2. Select Deployment if missing
        if (! $deploymentName) {
            $deploymentName = select(
                label: 'Select Deployment configuration',
                options: array_keys($deployments),
                required: true
            );
        }

        if (! isset($deployments[$deploymentName])) {
            $this->error("Deployment [{$deploymentName}] not found in environment [{$environment}].");

            return Command::FAILURE;
        }

        $configValues = $deployments[$deploymentName];
        $content = '';

        foreach ($configValues as $key => $value) {
            $content .= "{{ \${$key} }}\n\n";
        }

        $directory = resource_path('deployments'); // Keep as resource path for project customization
        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $path = "{$directory}/{$deploymentName}.blade.php";

        if (File::exists($path)) {
            warning("File [{$path}] already exists!");
            if (! $this->confirm('Do you want to overwrite it? All existing content will be lost.')) {
                $this->info('Operation cancelled.');

                return Command::SUCCESS;
            }
        }

        File::put($path, $content);

        $this->info("Deployment file created successfully at: {$path}");

        return Command::SUCCESS;
    }
}
