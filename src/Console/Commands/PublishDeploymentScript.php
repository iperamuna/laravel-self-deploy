<?php

namespace Iperamuna\SelfDeploy\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

use function Laravel\Prompts\select;

class PublishDeploymentScript extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'selfdeploy:publish-deployment-scripts
                            {deployment-name? : The name of the deployment (e.g. app-production)}
                            {--environment= : The environment (e.g. production). Defaults to app env if not provided.}
                            {--all : Create scripts for all deployments in the environment}
                            {--force : Overwrite existing file without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish deployment scripts (shell) from Blade templates';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 1. Determine Environment
        $env = $this->option('environment');
        if (!$env) {
            $env = app()->environment();
        }

        $configs = config('self-deploy.environments', []);

        // Validate environment exists in config
        if (!isset($configs[$env])) {
            $this->error("Environment [{$env}] not found in config/self-deploy.php.");
            // Allow user to select valid environment if available
            if (!empty($configs)) {
                $env = select(
                    label: 'Select valid environment from config used for deployment settings:',
                    options: array_keys($configs),
                    default: array_key_exists('production', $configs) ? 'production' : null
                );
            } else {
                return Command::FAILURE;
            }
        }

        $deployments = $configs[$env] ?? [];

        if (empty($deployments)) {
            $this->error("No deployments found for environment [{$env}].");

            return Command::FAILURE;
        }

        // 2. Identify Target Deployments
        $targetDeployments = [];

        if ($this->option('all')) {
            $targetDeployments = array_keys($deployments);
        } else {
            $deploymentName = $this->argument('deployment-name');

            if (!$deploymentName) {
                $options = array_merge(['All'], array_keys($deployments));
                $deploymentName = select(
                    label: "Select deployment configuration for [{$env}]:",
                    options: $options,
                    required: true
                );
            }

            if ($deploymentName === 'All') {
                $targetDeployments = array_keys($deployments);
            } else {
                if (!isset($deployments[$deploymentName])) {
                    $this->error("Deployment [{$deploymentName}] not configured in environment [{$env}].");

                    return Command::FAILURE;
                }
                $targetDeployments[] = $deploymentName;
            }
        }

        // 3. Ensure Output Directory Exists
        $outputDir = config('self-deploy.deployment_scripts_path') ?? base_path();

        if (!File::exists($outputDir)) {
            if (!File::makeDirectory($outputDir, 0755, true)) {
                $this->error("Failed to create directory: {$outputDir}");

                return Command::FAILURE;
            }
            $this->info("Created directory: {$outputDir}");
        }

        // Register view location so includes work
        // Use configured deployment configurations path
        View::addLocation(config('self-deploy.deployment_configurations_path', resource_path('deployments')));

        $hasErrors = false;

        // 4. Generate Scripts
        foreach ($targetDeployments as $name) {
            if (!$this->generateScript($name, $deployments[$name], $outputDir)) {
                $hasErrors = true;
            }
        }

        return $hasErrors ? Command::FAILURE : Command::SUCCESS;
    }

    protected function generateScript(string $name, array $configData, string $outputDir): bool
    {
        // Check if this is a multi-server config (heuristic: first element is an array)
        $first = reset($configData);
        if (is_array($first)) {
            $hasErrors = false;
            foreach ($configData as $serverKey => $serverConfig) {
                // For multi-server, the blade template is named {deployment}-{server}
                $templateName = "{$name}-{$serverKey}";
                // The output script should also probably be server-specific
                $scriptName = "{$name}-{$serverKey}";

                if (!$this->renderAndSave($scriptName, $templateName, $serverConfig, $outputDir)) {
                    $hasErrors = true;
                }
            }
            return !$hasErrors;
        }

        return $this->renderAndSave($name, $name, $configData, $outputDir);
    }

    protected function renderAndSave(string $scriptName, string $templateName, array $configData, string $outputDir): bool
    {
        $viewData = array_merge($configData, [
            'script' => $templateName, // Pass to @include in base.blade.php
            'log_dir' => config('self-deploy.log_dir'),
        ]);

        try {
            if (view()->exists('self-deploy::base')) {
                $content = view('self-deploy::base', $viewData)->render();
            } else {
                $content = view()->file(resource_path('deployments/base.blade.php'), $viewData)->render();
            }
        } catch (\Exception $e) {
            if (File::exists(resource_path('deployments/base.blade.php'))) {
                try {
                    $content = view()->file(resource_path('deployments/base.blade.php'), $viewData)->render();
                } catch (\Exception $ex) {
                    $this->error("Error rendering template for [{$scriptName}]: " . $ex->getMessage());
                    return false;
                }
            } else {
                $this->error("Error rendering template for [{$scriptName}]: " . $e->getMessage());
                return false;
            }
        }

        $filename = "{$scriptName}.sh";
        $path = $outputDir . DIRECTORY_SEPARATOR . $filename;

        if (File::put($path, $content) === false) {
            $this->error("Failed to write to {$path}");
            return false;
        }

        chmod($path, 0755);

        $this->info("Deployment script created: {$path}");

        return true;
    }
}
