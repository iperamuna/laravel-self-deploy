<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Sync current test environment with config to avoid prompts
    config()->set('self-deploy.environments.testing', [
        'app-testing' => [
            'deploy_path' => '/var/www/test',
        ]
    ]);

    $this->scriptsPath = config('self-deploy.deployment_scripts_path');
    File::ensureDirectoryExists($this->scriptsPath);
});

it('triggers shell mode by default', function () {
    // Create a mock script
    File::put($this->scriptsPath . '/deploy.sh', 'echo "test"');

    $this->artisan('selfdeploy:run', ['--force' => true])
        ->expectsOutputToContain('Found 1 deployment script(s).')
        ->expectsOutputToContain('Triggering: deploy.sh')
        ->expectsOutputToContain('SUCCESS: Started in background (PID: Background).')
        ->assertExitCode(0);
});

it('triggers systemd mode when configured', function () {
    config()->set('self-deploy.execution_mode', 'systemd');

    // Freeze time for deterministic assertions
    $now = now();
    Illuminate\Support\Carbon::setTestNow($now);
    $timestamp = $now->format('Ymd-His');

    // Create a mock script
    File::put($this->scriptsPath . '/deploy.sh', 'echo "test"');

    $this->artisan('selfdeploy:run', ['--force' => true])
        ->expectsOutputToContain('SUCCESS: Started systemd unit')
        ->expectsOutputToContain('Monitor: journalctl -u')
        ->assertExitCode(0);

    Illuminate\Support\Carbon::setTestNow();
});

it('can publish and then run', function () {
    // Setup a mock blade file first
    $bladePath = config('self-deploy.deployment_configurations_path') . '/app-testing.blade.php';
    File::put($bladePath, 'echo "Deploy"');

    $this->artisan('selfdeploy:run', [
        '--publish' => true,
        '--force' => true
    ])
        ->expectsOutput('Publishing deployment scripts...')
        ->expectsOutput('Found 1 deployment script(s).')
        ->assertExitCode(0);

    expect(File::exists($this->scriptsPath . '/app-testing.sh'))->toBeTrue();
});
