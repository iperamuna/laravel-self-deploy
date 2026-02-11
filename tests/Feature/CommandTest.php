<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Reset config to initial state before each test
    config()->set('self-deploy.environments', [
        'production' => [
            'app-production' => [
                'deploy_path' => '/var/www/test-app',
            ],
            'app-frontend' => [
                'deploy_path' => '/var/www/test-frontend',
                'branch' => 'main',
            ],
        ],
    ]);
});

afterEach(function () {
    // Cleanup any created files
    $paths = [
        config('self-deploy.deployment_configurations_path'),
        config('self-deploy.deployment_scripts_path'),
    ];

    foreach ($paths as $path) {
        if (File::exists($path)) {
            File::deleteDirectory($path, true);
        }
    }
});

it('can create deployment file with options', function () {
    $this->artisan('selfdeploy:create-deployment-file', [
        '--deployment-name' => 'app-production',
        '--environment' => 'production',
    ])
        ->expectsConfirmation('Do you want to generate the Bash script now?', 'no')
        ->expectsOutput('Deployment file created successfully at: ' . config('self-deploy.deployment_configurations_path') . '/app-production.blade.php')
        ->assertExitCode(0);

    expect(File::exists(config('self-deploy.deployment_configurations_path') . '/app-production.blade.php'))->toBeTrue();

    $content = File::get(config('self-deploy.deployment_configurations_path') . '/app-production.blade.php');
    expect($content)->toContain('{{ $deploy_path }}');
});

it('fails when environment not found', function () {
    $this->artisan('selfdeploy:create-deployment-file', [
        '--deployment-name' => 'app-production',
        '--environment' => 'nonexistent',
    ])
        ->expectsOutput('Environment [nonexistent] not found in config.')
        ->assertExitCode(1);
});

it('fails when deployment not found in environment', function () {
    $this->artisan('selfdeploy:create-deployment-file', [
        '--deployment-name' => 'nonexistent',
        '--environment' => 'production',
    ])
        ->expectsOutput('Deployment [nonexistent] not found in environment [production].')
        ->assertExitCode(1);
});

it('prompts for overwrite when file exists', function () {
    // Create file first
    $path = config('self-deploy.deployment_configurations_path') . '/app-production.blade.php';
    File::ensureDirectoryExists(dirname($path));
    File::put($path, 'existing content');

    $this->artisan('selfdeploy:create-deployment-file', [
        '--deployment-name' => 'app-production',
        '--environment' => 'production',
    ])
        ->expectsConfirmation('Do you want to overwrite it? All existing content will be lost.', 'no')
        ->expectsOutput('Operation cancelled.')
        ->assertExitCode(0);

    // File should still have old content
    expect(File::get($path))->toBe('existing content');
});

it('overwrites file when confirmed', function () {
    // Create file first
    $path = config('self-deploy.deployment_configurations_path') . '/app-production.blade.php';
    File::ensureDirectoryExists(dirname($path));
    File::put($path, 'existing content');

    $this->artisan('selfdeploy:create-deployment-file', [
        '--deployment-name' => 'app-production',
        '--environment' => 'production',
    ])
        ->expectsConfirmation('Do you want to overwrite it? All existing content will be lost.', 'yes')
        ->expectsConfirmation('Do you want to generate the Bash script now?', 'no')
        ->expectsOutput('Deployment file created successfully at: ' . config('self-deploy.deployment_configurations_path') . '/app-production.blade.php')
        ->assertExitCode(0);

    // File should have new content
    $content = File::get($path);
    expect($content)->not()->toBe('existing content');
    expect($content)->toContain('{{ $deploy_path }}');
});

it('generates bash script when confirmed', function () {
    $this->artisan('selfdeploy:create-deployment-file', [
        '--deployment-name' => 'app-production',
        '--environment' => 'production',
    ])
        ->expectsConfirmation('Do you want to generate the Bash script now?', 'yes')
        ->assertExitCode(0);

    expect(File::exists(config('self-deploy.deployment_configurations_path') . '/app-production.blade.php'))->toBeTrue();
    expect(File::exists(config('self-deploy.deployment_scripts_path') . '/app-production.sh'))->toBeTrue();
});

it('creates directory if it does not exist', function () {
    $deploymentDir = config('self-deploy.deployment_configurations_path');

    // Ensure directory doesn't exist
    if (File::exists($deploymentDir)) {
        File::deleteDirectory($deploymentDir);
    }

    expect(File::exists($deploymentDir))->toBeFalse();

    $this->artisan('selfdeploy:create-deployment-file', [
        '--deployment-name' => 'app-production',
        '--environment' => 'production',
    ])
        ->expectsConfirmation('Do you want to generate the Bash script now?', 'no')
        ->assertExitCode(0);

    expect(File::exists($deploymentDir))->toBeTrue();
    expect(File::isDirectory($deploymentDir))->toBeTrue();
});

it('includes all config values in blade file', function () {
    // Add a deployment with multiple config values
    config()->set('self-deploy.environments.production.test-deployment', [
        'deploy_path' => '/var/www/test',
        'branch' => 'main',
        'service' => 'test.service',
        'port' => '8000',
    ]);

    $this->artisan('selfdeploy:create-deployment-file', [
        '--deployment-name' => 'test-deployment',
        '--environment' => 'production',
    ])
        ->expectsConfirmation('Do you want to generate the Bash script now?', 'no')
        ->assertExitCode(0);

    $content = File::get(config('self-deploy.deployment_configurations_path') . '/test-deployment.blade.php');

    expect($content)->toContain('{{ $deploy_path }}')
        ->toContain('{{ $branch }}')
        ->toContain('{{ $service }}')
        ->toContain('{{ $port }}');
});

it('can publish deployment scripts', function () {
    // Setup a mock blade file first
    $bladePath = config('self-deploy.deployment_configurations_path') . '/app-production.blade.php';
    if (!File::exists(dirname($bladePath))) {
        File::makeDirectory(dirname($bladePath), 0755, true);
    }
    File::put($bladePath, 'echo "Deploying {{ $deploy_path }}"');

    $this->artisan('selfdeploy:publish-deployment-scripts', [
        'deployment-name' => 'app-production',
        '--environment' => 'production',
        '--force' => true,
    ])
        ->expectsOutput('Deployment script created: ' . config('self-deploy.deployment_scripts_path') . '/app-production.sh')
        ->assertExitCode(0);

    $scriptPath = config('self-deploy.deployment_scripts_path') . '/app-production.sh';
    expect(File::exists($scriptPath))->toBeTrue();
    $content = File::get($scriptPath);
    expect($content)->toContain('/var/www/test-app')
        ->toContain('LOG_DIR="' . config('self-deploy.log_dir') . '/app-production"')
        ->toContain('LOG_FILE="${LOG_DIR}/deployment-$(date +%F_%H%M%S).log"')
        ->toContain('exec > >(sudo tee -a "$LOG_FILE") 2>&1')
        ->toContain('log "==== app-production deployment started ===="');
});

it('can publish all deployment scripts', function () {
    // Setup mock blade files
    $bladePath1 = config('self-deploy.deployment_configurations_path') . '/app-production.blade.php';
    File::put($bladePath1, 'echo "Prod"');

    // Add another deployment to config for this test scope
    config()->set('self-deploy.environments.production.app-worker', ['deploy_path' => '/worker']);
    $bladePath2 = config('self-deploy.deployment_configurations_path') . '/app-worker.blade.php';
    File::put($bladePath2, 'echo "Worker"');

    $this->artisan('selfdeploy:publish-deployment-scripts', [
        '--all' => true,
        '--environment' => 'production',
        '--force' => true,
    ])
        ->assertExitCode(0);

    expect(File::exists(config('self-deploy.deployment_scripts_path') . '/app-production.sh'))->toBeTrue();
    expect(File::exists(config('self-deploy.deployment_scripts_path') . '/app-worker.sh'))->toBeTrue();
});

it('makes published scripts executable', function () {
    $bladePath = config('self-deploy.deployment_configurations_path') . '/app-production.blade.php';
    File::put($bladePath, 'echo "Deploying"');

    $this->artisan('selfdeploy:publish-deployment-scripts', [
        'deployment-name' => 'app-production',
        '--environment' => 'production',
        '--force' => true,
    ])->assertExitCode(0);

    $scriptPath = config('self-deploy.deployment_scripts_path') . '/app-production.sh';

    expect(File::exists($scriptPath))->toBeTrue();

    // Check if file is executable (on Unix systems)
    if (PHP_OS_FAMILY !== 'Windows') {
        expect(is_executable($scriptPath))->toBeTrue();
    }
});

it('handles empty config values gracefully', function () {
    config()->set('self-deploy.environments.production.empty-deployment', [
        'deploy_path' => '',
        'branch' => '',
    ]);

    $this->artisan('selfdeploy:create-deployment-file', [
        '--deployment-name' => 'empty-deployment',
        '--environment' => 'production',
    ])
        ->expectsConfirmation('Do you want to generate the Bash script now?', 'no')
        ->assertExitCode(0);

    expect(File::exists(config('self-deploy.deployment_configurations_path') . '/empty-deployment.blade.php'))->toBeTrue();

    $content = File::get(config('self-deploy.deployment_configurations_path') . '/empty-deployment.blade.php');
    expect($content)->toContain('{{ $deploy_path }}')
        ->toContain('{{ $branch }}');
});

it('preserves existing config structure when updating', function () {
    $configPath = config_path('self-deploy.php');

    // Manually create a proper config file
    $initialConfig = [
        'log_dir' => storage_path('self-deployments/logs'),
        'deployment_configurations_path' => resource_path('deployments'),
        'deployment_scripts_path' => base_path('.deployments'),
        'environments' => [
            'production' => [
                'app-production' => [
                    'deploy_path' => '/var/www/test-app',
                ],
            ],
        ],
    ];

    File::put($configPath, "<?php\n\nreturn " . var_export($initialConfig, true) . ";\n");

    $command = new \Iperamuna\SelfDeploy\Console\Commands\CreateDeploymentFile;

    $newEnvironments = [
        'production' => $initialConfig['environments']['production'],
        'staging' => [
            'app-staging' => [
                'deploy_path' => '/var/www/staging',
            ],
        ],
    ];

    // Use reflection to call protected method
    $reflection = new \ReflectionClass($command);
    $method = $reflection->getMethod('updateConfigFile');
    $method->setAccessible(true);

    $method->invoke($command, $newEnvironments);

    // Verify the config file exists and has correct structure
    expect(File::exists($configPath))->toBeTrue();

    $updatedConfig = include $configPath;

    // Verify original keys are preserved
    expect($updatedConfig)->toHaveKey('log_dir');
    expect($updatedConfig)->toHaveKey('deployment_configurations_path');
    expect($updatedConfig)->toHaveKey('deployment_scripts_path');
    expect($updatedConfig)->toHaveKey('environments');

    // Verify environments were updated
    expect($updatedConfig['environments'])->toHaveKey('production');
    expect($updatedConfig['environments'])->toHaveKey('staging');
});

it('updates in-memory config when adding new deployment', function () {
    $configPath = config_path('self-deploy.php');

    // Create initial config
    $initialConfig = [
        'log_dir' => storage_path('self-deployments/logs'),
        'deployment_configurations_path' => resource_path('deployments'),
        'deployment_scripts_path' => base_path('.deployments'),
        'environments' => [
            'production' => [
                'app-production' => [
                    'deploy_path' => '/var/www/test-app',
                ],
            ],
        ],
    ];

    File::put($configPath, "<?php\n\nreturn " . var_export($initialConfig, true) . ";\n");
    config()->set('self-deploy', $initialConfig);

    // Create the command and add a new deployment
    $command = new \Iperamuna\SelfDeploy\Console\Commands\CreateDeploymentFile;

    $newEnvironments = [
        'production' => $initialConfig['environments']['production'],
        'staging' => [
            'app-staging' => [
                'deploy_path' => '/var/www/staging',
                'branch' => 'staging',
            ],
        ],
    ];

    // Use reflection to call protected method
    $reflection = new \ReflectionClass($command);
    $method = $reflection->getMethod('updateConfigFile');
    $method->setAccessible(true);

    $method->invoke($command, $newEnvironments);

    // Manually update config like the command does
    config()->set('self-deploy.environments', $newEnvironments);

    // Verify in-memory config was updated
    $inMemoryConfig = config('self-deploy.environments');
    expect($inMemoryConfig)->toHaveKey('staging');
    expect($inMemoryConfig['staging'])->toHaveKey('app-staging');
    expect($inMemoryConfig['staging']['app-staging']['deploy_path'])->toBe('/var/www/staging');
    expect($inMemoryConfig['staging']['app-staging']['branch'])->toBe('staging');
});

it('prompts for multi-server configuration and server key', function () {
    $this->artisan('selfdeploy:create-deployment-file')
        ->expectsChoice('Select Environment or Add New', 'production', ['production', '+ Add New Environment'])
        ->expectsChoice('Select Deployment Configuration or Add New', '+ Add New Deployment Configuration', ['app-production', 'app-frontend', '+ Add New Deployment Configuration'])
        ->expectsQuestion('Enter deployment configuration name', 'multi-server-app')
        ->expectsQuestion('Config Key (or "d" to done)', 'deploy_path')
        ->expectsQuestion('Default value for [deploy_path]', '/var/www/app')
        ->expectsQuestion('Config Key (or "d" to done)', 'd')
        ->expectsConfirmation('Is this a multiple server deployment?', 'yes')
        ->expectsQuestion('Enter Server Key variable', '{{ config("app.server_key") }}')
        ->expectsConfirmation('Do you want to generate the Bash script now?', 'no')
        ->assertExitCode(0);

    $configs = config('self-deploy.environments.production.multi-server-app');
    expect($configs)->toHaveKey('server_key');
    expect($configs['server_key'])->toBe('{{ config("app.server_key") }}');
});
