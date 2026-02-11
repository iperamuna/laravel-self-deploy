<?php

namespace Iperamuna\SelfDeploy\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
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

        // 1. Select or Add Environment
        if (! $environment) {
            $environmentOptions = array_keys($configs);
            $environmentOptions[] = '+ Add New Environment';

            $environment = select(
                label: 'Select Environment or Add New',
                options: $environmentOptions,
                required: true
            );

            if ($environment === '+ Add New Environment') {
                $environment = text(
                    label: 'Enter new environment name',
                    placeholder: 'e.g., staging, production',
                    required: true
                );

                $configs[$environment] = [];
            }
        }

        if (! isset($configs[$environment])) {
            $this->error("Environment [{$environment}] not found in config.");

            return Command::FAILURE;
        }

        $deployments = $configs[$environment];

        // 2. Select or Add Deployment
        if (! $deploymentName) {
            $deploymentOptions = array_keys($deployments);
            $deploymentOptions[] = '+ Add New Deployment Configuration';

            $deploymentName = select(
                label: 'Select Deployment Configuration or Add New',
                options: $deploymentOptions,
                required: true
            );

            if ($deploymentName === '+ Add New Deployment Configuration') {
                $deploymentName = text(
                    label: 'Enter deployment configuration name',
                    placeholder: 'e.g., app-production, web-staging',
                    required: true
                );

                // Dynamic Config Input
                $configValues = $this->collectConfigValues();
                $deployments[$deploymentName] = $configValues;
                $configs[$environment] = $deployments;

                // Save to config file
                $this->updateConfigFile($configs);

                $this->info("Deployment configuration [{$deploymentName}] added to [{$environment}].");
            }
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

        $directory = config('self-deploy.deployment_configurations_path', resource_path('deployments'));
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

        // Ask to generate bash file
        if (confirm('Do you want to generate the Bash script now?', true)) {
            $this->call('selfdeploy:publish-deployment-scripts', [
                'deployment-name' => $deploymentName,
                '--environment' => $environment,
                '--force' => true,
            ]);
        }

        return Command::SUCCESS;
    }

    /**
     * Collect config key-value pairs from user input.
     */
    protected function collectConfigValues(): array
    {
        $configValues = [];

        $this->info('Enter configuration key-value pairs. Press "d" to finish.');

        while (true) {
            $key = text(
                label: 'Config Key (or "d" to done)',
                placeholder: 'e.g., deploy_path, branch',
                required: false
            );

            if ($key === 'd' || $key === '') {
                break;
            }

            $value = text(
                label: "Default value for [{$key}]",
                placeholder: 'Leave blank for empty string',
                required: false,
                default: ''
            );

            $configValues[$key] = $value;
        }

        return $configValues;
    }

    /**
     * Update the config file with new values.
     */
    protected function updateConfigFile(array $configs): void
    {
        $configPath = config_path('self-deploy.php');

        // If config doesn't exist in app, publish it first
        if (! File::exists($configPath)) {
            $this->call('vendor:publish', [
                '--tag' => 'self-deploy-config',
                '--force' => true,
            ]);
        }

        // Read existing config to preserve other keys
        $existingConfig = include $configPath;

        // Update only the environments key
        $existingConfig['environments'] = $configs;

        $content = "<?php\n\nreturn ".$this->varExport($existingConfig).";\n";

        File::put($configPath, $content);
    }

    /**
     * Custom var_export that formats arrays nicely.
     */
    protected function varExport(array $array, int $level = 0): string
    {
        $indent = str_repeat('    ', $level);
        $result = "[\n";

        foreach ($array as $key => $value) {
            $result .= $indent.'    '.var_export($key, true).' => ';

            if (is_array($value)) {
                $result .= $this->varExport($value, $level + 1);
            } else {
                $result .= var_export($value, true);
            }

            $result .= ",\n";
        }

        if ($level > 0) {
            $result .= $indent.']';
        } else {
            $result .= ']';
        }

        return $result;
    }
}
