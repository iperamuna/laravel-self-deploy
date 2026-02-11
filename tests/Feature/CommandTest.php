<?php

use Illuminate\Support\Facades\File;

it('can create deployment file', function () {
    File::delete(resource_path('deployments/app-production.blade.php'));

    $this->artisan('selfdeploy:create-deployment-file', [
        '--deployment-name' => 'app-production',
        '--environment' => 'production',
    ])
        ->expectsOutput('Deployment file created successfully at: '.resource_path('deployments/app-production.blade.php'))
        ->assertExitCode(0);

    expect(File::exists(resource_path('deployments/app-production.blade.php')))->toBeTrue();

    // Cleanup resource path created during test
    File::delete(resource_path('deployments/app-production.blade.php'));
});

it('can publish deployment scripts', function () {
    // Setup a mock blade file first
    $bladePath = resource_path('deployments/app-production.blade.php');
    if (! File::exists(dirname($bladePath))) {
        File::makeDirectory(dirname($bladePath), 0755, true);
    }
    File::put($bladePath, 'echo "Deploying {{ $deploy_path }}"');

    $this->artisan('selfdeploy:publish-deployment-scripts', [
        'deployment-name' => 'app-production',
        '--environment' => 'production',
        '--force' => true,
    ])
        ->expectsOutput('Deployment script created: '.config('self-deploy.deployment_scripts_path').'/app-production.sh')
        ->assertExitCode(0);

    $scriptPath = config('self-deploy.deployment_scripts_path').'/app-production.sh';
    expect(File::exists($scriptPath))->toBeTrue();
    expect(File::get($scriptPath))->toContain('/var/www/test-app');

    // Cleanup
    File::delete($bladePath);
});

it('can publish all deployment scripts', function () {
    // Setup mock blade files
    $bladePath1 = resource_path('deployments/app-production.blade.php');
    File::put($bladePath1, 'echo "Prod"');

    // Add another deployment to config for this test scope
    config()->set('self-deploy.environments.production.app-worker', ['deploy_path' => '/worker']);
    $bladePath2 = resource_path('deployments/app-worker.blade.php');
    File::put($bladePath2, 'echo "Worker"');

    $this->artisan('selfdeploy:publish-deployment-scripts', [
        '--all' => true,
        '--environment' => 'production',
        '--force' => true,
    ])
        ->assertExitCode(0);

    expect(File::exists(config('self-deploy.deployment_scripts_path').'/app-production.sh'))->toBeTrue();
    expect(File::exists(config('self-deploy.deployment_scripts_path').'/app-worker.sh'))->toBeTrue();

    // Cleanup
    File::delete($bladePath1);
    File::delete($bladePath2);
});
