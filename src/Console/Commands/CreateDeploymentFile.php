<?php

namespace Iperamuna\SelfDeploy\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Iperamuna\SelfDeploy\Support\ConfigFormatter;

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
        if (!$environment) {
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

        if (!isset($configs[$environment])) {
            $this->error("Environment [{$environment}] not found in config.");

            return Command::FAILURE;
        }

        $deployments = $configs[$environment];

        // 2. Select or Add Deployment Name
        if (!$deploymentName) {
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
            }
        } elseif (!isset($deployments[$deploymentName])) {
            $this->error("Deployment [{$deploymentName}] not found in environment [{$environment}].");

            return Command::FAILURE;
        }

        if ($deploymentName === 'self_deploy_server_key') {
            $this->error("Deployment name cannot be [self_deploy_server_key].");
            return Command::FAILURE;
        }

        // 3. Multi-Server Logic
        $isMultiServer = false;
        $serverKeys = [];
        $configKeys = [];
        $shouldCollect = false;

        // Check if we are creating a new one or if we should re-configure
        if (!isset($deployments[$deploymentName]) || empty($deployments[$deploymentName])) {
            $shouldCollect = true;
        } elseif ($this->option('deployment-name') === null) {
            $this->line("Existing configuration found for [{$deploymentName}].");
            if (confirm('Do you want to re-configure the server/config keys?', false)) {
                $shouldCollect = true;
            }
        }

        if ($shouldCollect) {
            $isMultiServer = confirm('Does this deployment have multiple app servers?', false);
            if ($isMultiServer) {
                $serverKeys = $this->collectServerKeys();
            }
            $configKeys = $this->collectConfigKeys();
        } else {
            // Use existing config to determine structure
            $existing = $deployments[$deploymentName];
            $first = reset($existing);
            if (is_array($first)) {
                $isMultiServer = true;
                $serverKeys = array_keys($existing);
                $configKeys = array_keys($first);
            } else {
                $isMultiServer = false;
                $configKeys = array_keys($existing);
            }
        }

        // 5. Build Final Config Structure (only if newly collected)
        if ($shouldCollect) {
            $finalConfig = [];
            if ($isMultiServer) {
                foreach ($serverKeys as $serverKey) {
                    $finalConfig[$serverKey] = [];
                    foreach ($configKeys as $key => $value) {
                        $finalConfig[$serverKey][$key] = $value;
                    }
                }
            } else {
                foreach ($configKeys as $key => $value) {
                    $finalConfig[$key] = $value;
                }
            }
            $deployments[$deploymentName] = $finalConfig;
            $configs[$environment] = $deployments;

            // Save to config file
            $this->updateConfigFile($configs);
            config()->set('self-deploy.environments', $configs);

            $this->info("Deployment configuration [{$deploymentName}] updated in [{$environment}].");
        } else {
            $finalConfig = $deployments[$deploymentName];
        }

        $directory = config('self-deploy.deployment_configurations_path', resource_path('deployments'));
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        // Determine which keys to use for Blade variables
        $keysForBlade = array_is_list($configKeys) ? $configKeys : array_keys($configKeys);

        if ($isMultiServer) {
            foreach ($serverKeys as $serverKey) {
                $path = "{$directory}/{$deploymentName}-{$serverKey}.blade.php";

                $content = "{{ \$self_deploy_server_key }}\n\n";
                foreach ($keysForBlade as $key) {
                    $content .= "{{ \${$key} }}\n";
                }

                if (File::exists($path)) {
                    $this->warn("File [{$path}] already exists, overwriting...");
                }

                File::put($path, $content);
                $this->line("Created: <info>{$path}</info>");
            }
        } else {
            $content = '';
            foreach ($keysForBlade as $key) {
                $content .= "{{ \${$key} }}\n\n";
            }

            $path = "{$directory}/{$deploymentName}.blade.php";

            if (File::exists($path)) {
                warning("File [{$path}] already exists!");
                if (!$this->confirm('Do you want to overwrite it? All existing content will be lost.')) {
                    $this->info('Operation cancelled.');

                    return Command::SUCCESS;
                }
            }

            File::put($path, $content);
            $this->info("Deployment file created successfully at: {$path}");
        }

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
     * Collect server keys from user input.
     */
    protected function collectServerKeys(): array
    {
        $keys = [];
        $this->info('Enter Server Keys one by one. Press "Enter" on an empty line or "d" to finish. ');

        while (true) {
            $key = text(
                label: 'Server Key (or "d" to done)',
                placeholder: 'e.g. server01, worker01',
                required: true,
                validate: function (string $value) use ($keys) {
                    $value = trim($value);

                    if ($value === 'd' || $value === '') {
                        return null;
                    }

                    if (mb_strlen($value) < 4) {
                        return 'Server key must be at least 4 characters.';
                    }

                    if (in_array($value, $keys)) {
                        return 'Cannot use the same server key twice.';
                    }

                    if ($value === 'self_deploy_server_key') {
                        return 'Server key cannot be [self_deploy_server_key].';
                    }

                    return null;
                },
                hint: 'Hint: This should be defined in .env as SELF_DEPLOY_SERVER_KEY'
            );

            if ($key === 'd' || $key === '') {
                break;
            }

            $keys[] = Str::snake($key);
        }

        return $keys;
    }

    /**
     * Collect configuration keys from user input.
     */
    protected function collectConfigKeys(): array
    {
        $keys = [];
        $this->info('Enter configuration key names. Press "d" to finish.');

        while (true) {
            $key = text(
                label: 'Config Key',
                placeholder: 'e.g. deploy_path (or "d" to done)',
                required: true,
                validate: function (string $value) use ($keys) {
                    $value = trim($value);

                    if ($value === 'd') {
                        return null;
                    }

                    if (mb_strlen($value) < 4) {
                        return 'Config Key must be at least 4 characters.';
                    }

                    if (in_array($value, array_keys($keys))) {
                        return 'Cannot redeclare config key.';
                    }

                    if ($value === 'self_deploy_server_key') {
                        return 'Config key cannot be [self_deploy_server_key].';
                    }

                    return null;
                },
            );

            if ($key === 'd') {
                break;
            }

            $key = Str::snake($key);

            $defaultValue = text(
                label: "Default value for [{$key}]",
                placeholder: 'Leave blank for empty string',
                required: false
            );

            if ($defaultValue === 'd') {
                break;
            }

            $keys[$key] = $defaultValue;
        }

        return $keys;
    }


    /**
     * Update the config file with new values.
     */
    protected function updateConfigFile(array $configs): void
    {
        $configPath = config_path('self-deploy.php');

        // If config doesn't exist in app, publish it first
        if (!File::exists($configPath)) {
            $this->call('vendor:publish', [
                '--tag' => 'self-deploy-config',
                '--force' => true,
            ]);
        }

        // Read existing config to preserve other keys
        $existingConfig = include $configPath;

        // Update only the environments key
        $existingConfig['environments'] = $configs;

        $content = "<?php\n\nreturn " . ConfigFormatter::format($existingConfig) . ";\n";

        File::put($configPath, $content);
    }
}
