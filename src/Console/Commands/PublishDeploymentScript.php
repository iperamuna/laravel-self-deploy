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
            $serverKey = config('app.server_key');
            $limitServers = $serverKey ? [$serverKey] : [];

            foreach (array_keys($deployments) as $depName) {
                $targetDeployments[] = [
                    'name' => $depName,
                    'limit_servers' => $limitServers
                ];
            }
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
                foreach (array_keys($deployments) as $depName) {
                    $targetDeployments[] = [
                        'name' => $depName,
                        'limit_servers' => []
                    ];
                }
            } else {
                if (!isset($deployments[$deploymentName])) {
                    $this->error("Deployment [{$deploymentName}] not configured in environment [{$env}].");

                    return Command::FAILURE;
                }

                // Check if this deployment is multi-server
                $deploymentConfig = $deployments[$deploymentName];
                $first = reset($deploymentConfig);
                if (is_array($first)) {
                    // It is multi-server. Ask if user wants to deploy to specific servers.
                    $serverOptions = array_merge(['All'], array_keys($deploymentConfig));
                    $selectedServers = select(
                        label: "Select server to publish script for [{$deploymentName}]:",
                        options: $serverOptions,
                        default: 'All'
                    );

                    if ($selectedServers === 'All') {
                        $targetDeployments[] = $deploymentName;
                    } else {
                        // We need a way to pass this selection to generateScript. 
                        // Since generateScript iterates over targetDeployments, we can use a composite key or store selection map.
                        // Ideally, we can just store the fully qualified target like 'deployment:serverKey' but current structure expects keys of deployments array.
                        // Let's store it as ['name' => $deploymentName, 'servers' => [$selectedServers]] to be cleaner, 
                        // but to minimize refactoring, we can just filter logic in the loop or make $targetDeployments an assoc array.

                        // Let's change $targetDeployments to hold detailed instruction: ['name' => ..., 'config' => ...]
                        // But $targetDeployments logic loop at step 4 expects keys.

                        // Simplest Refactor: 
                        // Pass a filter to generateScript? 

                        // Let's use a temporary property or pass variable. 
                        // Actually, let's just make $targetDeployments an array of objects/arrays:
                        // [['name' => 'app', 'servers' => ['s1']]]

                        $targetDeployments[] = [
                            'name' => $deploymentName,
                            'limit_servers' => [$selectedServers]
                        ];
                    }
                } else {
                    $targetDeployments[] = [
                        'name' => $deploymentName,
                        'limit_servers' => []
                    ];
                }
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
        // 4. Generate Scripts
        foreach ($targetDeployments as $target) {
            $name = $target['name'];
            $limitServers = $target['limit_servers'];

            if (!$this->generateScript($name, $deployments[$name], $outputDir, $limitServers)) {
                $hasErrors = true;
            }
        }

        return $hasErrors ? Command::FAILURE : Command::SUCCESS;
    }

    protected function generateScript(string $name, array $configData, string $outputDir, array $limitServers = []): bool
    {
        // Check if this is a multi-server config (heuristic: first element is an array)
        $first = reset($configData);
        if (is_array($first)) {
            $hasErrors = false;
            foreach ($configData as $serverKey => $serverConfig) {
                // Check if we should skip this server based on limitServers
                if (!empty($limitServers) && !in_array($serverKey, $limitServers)) {
                    continue;
                }

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
            'self_deploy_server_key' => config('app.server_key'),
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
