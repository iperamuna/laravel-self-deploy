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
        if (! $env) {
            $env = app()->environment();
        }

        $configs = config('self-deploy.environments', []);

        // Validate environment exists in config
        if (! isset($configs[$env])) {
            $this->error("Environment [{$env}] not found in config/self-deploy.php.");
            // Allow user to select valid environment if available
            if (! empty($configs)) {
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

            if (! $deploymentName) {
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
                if (! isset($deployments[$deploymentName])) {
                    $this->error("Deployment [{$deploymentName}] not configured in environment [{$env}].");

                    return Command::FAILURE;
                }
                $targetDeployments[] = $deploymentName;
            }
        }

        // 3. Ensure Output Directory Exists
        $outputDir = config('self-deploy.deployment_scripts_path') ?? base_path();

        if (! File::exists($outputDir)) {
            if (! File::makeDirectory($outputDir, 0755, true)) {
                $this->error("Failed to create directory: {$outputDir}");

                return Command::FAILURE;
            }
            $this->info("Created directory: {$outputDir}");
        }

        // Register view location so includes work
        // IMPORTANT: In the package, we should respect the user's local deployments location
        // Defaulting to resource_path('deployments') as it's the convention established
        View::addLocation(resource_path('deployments'));

        $hasErrors = false;

        // 4. Generate Scripts
        foreach ($targetDeployments as $name) {
            if (! $this->generateScript($name, $deployments[$name], $outputDir)) {
                $hasErrors = true;
            }
        }

        return $hasErrors ? Command::FAILURE : Command::SUCCESS;
    }

    protected function generateScript(string $name, array $configData, string $outputDir): bool
    {
        $viewData = array_merge($configData, [
            'script' => $name, // Pass to @include
            'log_dir' => config('self-deploy.log_dir'),
        ]);

        try {
            // Using 'self-deploy::base' as the base template provided by the package
            // But checking if user has published/overridden it?
            // For now, let's use view('self-deploy::base') assuming the package provider registers it.
            // However, the original code used view()->file(resource_path(...)).
            // If the user hasn't published the base.blade.php, we should use the package one.

            if (view()->exists('self-deploy::base')) {
                $content = view('self-deploy::base', $viewData)->render();
            } else {
                // Fallback or explicit file checking if view namespacing fails (e.g. during dev)
                // This path assumes we are in the package structure, but this code runs in the app.
                // It's safer to rely on the ServiceProvider registration.
                $content = view()->file(resource_path('deployments/base.blade.php'), $viewData)->render();
            }
        } catch (\Exception $e) {
            // If 'self-deploy::base' fails (e.g. not registered yet or package dev mode), fallback to local path if exists
            if (File::exists(resource_path('deployments/base.blade.php'))) {
                try {
                    $content = view()->file(resource_path('deployments/base.blade.php'), $viewData)->render();
                } catch (\Exception $ex) {
                    $this->error("Error rendering template for [{$name}]: ".$ex->getMessage());

                    return false;
                }
            } else {
                $this->error("Error rendering template for [{$name}]: ".$e->getMessage());

                return false;
            }
        }

        $filename = "{$name}.sh";
        $path = $outputDir.DIRECTORY_SEPARATOR.$filename;

        if (File::put($path, $content) === false) {
            $this->error("Failed to write to {$path}");

            return false;
        }

        // Make executable
        chmod($path, 0755);

        // Run sudo chmod +x as requested - REMOVED for better portability and testability
        // exec("sudo chmod +x {$path}");

        $this->info("Deployment script created: {$path}");

        return true;
    }
}
