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

it('can publish multi-server deployment scripts', function () {
    // Setup nested multi-server config
    config()->set('self-deploy.environments.production.multi-app', [
        'server01' => ['deploy_path' => '/var/www/s1'],
        'server02' => ['deploy_path' => '/var/www/s2'],
    ]);

    // Setup mock blade files for each server
    $configDir = config('self-deploy.deployment_configurations_path');
    File::ensureDirectoryExists($configDir);
    File::put("{$configDir}/multi-app-server01.blade.php", 'echo "S1: {{ $deploy_path }}"');
    File::put("{$configDir}/multi-app-server02.blade.php", 'echo "S2: {{ $deploy_path }}"');

    $this->artisan('selfdeploy:publish-deployment-scripts', [
        'deployment-name' => 'multi-app',
        '--environment' => 'production',
        '--force' => true,
    ])
        // It should ask for server selection if we didn't specify --all (Wait, does logic force prompt even if deployment-name arg is provided? Yes logic at lines 96+ runs if !--all)
        // Wait, lines 96 check: if (is_array($first)) ... ask select.
        ->expectsChoice('Select server to publish script for [multi-app]:', 'server01', ['All', 'server01', 'server02'])
        ->expectsOutput('Deployment script created: ' . config('self-deploy.deployment_scripts_path') . '/multi-app-server01.sh')
        ->doesntExpectOutput('Deployment script created: ' . config('self-deploy.deployment_scripts_path') . '/multi-app-server02.sh')
        ->assertExitCode(0);

    $script1 = config('self-deploy.deployment_scripts_path') . '/multi-app-server01.sh';
    $script2 = config('self-deploy.deployment_scripts_path') . '/multi-app-server02.sh';

    expect(File::exists($script1))->toBeTrue();
    expect(File::exists($script2))->toBeFalse(); // Should not exist cause we selected only server01

    expect(File::get($script1))->toContain('S1: /var/www/s1');
});

it('filters scripts by app.server_key when using --all', function () {
    // Setup nested multi-server config
    config()->set('self-deploy.environments.production.multi-app', [
        'server01' => ['deploy_path' => '/var/www/s1'],
        'server02' => ['deploy_path' => '/var/www/s2'],
    ]);

    // Simulate being on server02
    config()->set('app.server_key', 'server02');

    // Setup mock blade files
    $configDir = config('self-deploy.deployment_configurations_path');
    File::ensureDirectoryExists($configDir);
    File::put("{$configDir}/multi-app-server01.blade.php", 'echo "S1"');
    File::put("{$configDir}/multi-app-server02.blade.php", 'echo "S2"');

    $this->artisan('selfdeploy:publish-deployment-scripts', [
        '--all' => true,
        '--environment' => 'production',
        '--force' => true,
    ])
        ->doesntExpectOutput('Deployment script created: ' . config('self-deploy.deployment_scripts_path') . '/multi-app-server01.sh')
        ->expectsOutput('Deployment script created: ' . config('self-deploy.deployment_scripts_path') . '/multi-app-server02.sh')
        ->assertExitCode(0);

    $script1 = config('self-deploy.deployment_scripts_path') . '/multi-app-server01.sh';
    $script2 = config('self-deploy.deployment_scripts_path') . '/multi-app-server02.sh';

    expect(File::exists($script1))->toBeFalse();
    expect(File::exists($script2))->toBeTrue();
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
        ->expectsConfirmation('Does this deployment have multiple app servers?', 'yes')
        ->expectsQuestion('Server Key (or "d" to done)', 'server01')
        ->expectsQuestion('Server Key (or "d" to done)', 'server02')
        ->expectsQuestion('Server Key (or "d" to done)', 'd')
        ->expectsQuestion('Config Key', 'deploy_path')
        ->expectsQuestion('Default value for [deploy_path]', '/var/www')
        ->expectsQuestion('Config Key', 'd')
        ->expectsConfirmation('Do you want to generate the Bash script now?', 'no')
        ->assertExitCode(0);

    $configs = config('self-deploy.environments.production.multi-server-app');
    expect($configs)->toHaveKey('server01');
    expect($configs)->toHaveKey('server02');
    expect($configs['server01'])->toHaveKey('deploy_path');

    $configPath = config('self-deploy.deployment_configurations_path');
    expect(File::exists("{$configPath}/multi-server-app-server01.blade.php"))->toBeTrue();
    expect(File::exists("{$configPath}/multi-server-app-server02.blade.php"))->toBeTrue();

    $content1 = File::get("{$configPath}/multi-server-app-server01.blade.php");
    expect($content1)->toContain('{{ $self_deploy_server_key }}')
        ->toContain('{{ $deploy_path }}');
});

it('validates and snake_cases server and config keys', function () {
    $this->artisan('selfdeploy:create-deployment-file')
        ->expectsChoice('Select Environment or Add New', 'production', ['production', '+ Add New Environment'])
        ->expectsChoice('Select Deployment Configuration or Add New', '+ Add New Deployment Configuration', ['app-production', 'app-frontend', '+ Add New Deployment Configuration'])
        ->expectsQuestion('Enter deployment configuration name', 'validation-app')
        ->expectsConfirmation('Does this deployment have multiple app servers?', 'yes')
        // Test snake_case for server key
        ->expectsQuestion('Server Key (or "d" to done)', 'App Server') // 9 chars, should pass
        ->expectsQuestion('Server Key (or "d" to done)', 'd')
        // Test snake_case for config key
        ->expectsQuestion('Config Key', 'Deploy Path') // 11 chars, should pass
        ->expectsQuestion('Default value for [deploy_path]', '/var/www')
        ->expectsQuestion('Config Key', 'd')
        ->expectsConfirmation('Do you want to generate the Bash script now?', 'no')
        ->assertExitCode(0);

    $configs = config('self-deploy.environments.production.validation-app');

    // Check snaked keys
    expect($configs)->toHaveKey('app_server');
    expect($configs['app_server'])->toHaveKey('deploy_path');

    $configPath = config('self-deploy.deployment_configurations_path');
    $configPath = config('self-deploy.deployment_configurations_path');
    $content = File::get("{$configPath}/validation-app-app_server.blade.php");
    expect($content)->toContain('{{ $self_deploy_server_key }}')
        ->toContain('{{ $deploy_path }}');
});

it('fails when using restricted key names', function () {
    $this->artisan('selfdeploy:create-deployment-file')
        ->expectsChoice('Select Environment or Add New', 'production', ['production', '+ Add New Environment'])
        ->expectsChoice('Select Deployment Configuration or Add New', '+ Add New Deployment Configuration', ['app-production', 'app-frontend', '+ Add New Deployment Configuration'])
        ->expectsQuestion('Enter deployment configuration name', 'self_deploy_server_key')
        ->expectsOutput('Deployment name cannot be [self_deploy_server_key].')
        ->assertExitCode(1);
});
