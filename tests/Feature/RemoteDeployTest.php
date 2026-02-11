<?php

beforeEach(function () {
    config()->set('self-deploy.environments.production', [
        'hosts' => ['1.2.3.4', '5.6.7.8'],
        'ssh_user' => 'deploy',
        'remote_path' => '/var/www/app',
        'app-production' => [
            'deploy_path' => '/var/www/app',
        ],
    ]);
});

it('fails when no hosts configured', function () {
    config()->set('self-deploy.environments.production.hosts', []);

    $this->artisan('selfdeploy:remote-deploy', ['environment' => 'production'])
        ->expectsOutput('No hosts configured for environment [production].')
        ->assertExitCode(1);
});

it('fails when no remote_path configured', function () {
    config()->set('self-deploy.environments.production.remote_path', null);

    $this->artisan('selfdeploy:remote-deploy', ['environment' => 'production'])
        ->expectsOutput('No [remote_path] configured for environment [production].')
        ->assertExitCode(1);
});

it('can trigger remote deployment to multiple hosts', function () {
    $this->artisan('selfdeploy:remote-deploy', ['environment' => 'production'])
        ->expectsOutputToContain('Starting remote deployment for [production] across 2 host(s).')
        ->expectsOutputToContain('🚀 Deploying to: deploy@1.2.3.4')
        ->expectsOutputToContain('🚀 Deploying to: deploy@5.6.7.8')
        ->expectsOutputToContain('Successfully triggered on 1.2.3.4')
        ->expectsOutputToContain('Successfully triggered on 5.6.7.8')
        ->assertExitCode(0);
});

it('allows environment selection', function () {
    $this->artisan('selfdeploy:remote-deploy')
        ->expectsChoice('Select environment for remote deployment', 'production', ['production', 'staging'])
        ->assertExitCode(0);
});

it('includes publish flag when requested', function () {
    // We check the output messages which contain the command being run
    $this->artisan('selfdeploy:remote-deploy', [
        'environment' => 'production',
        '--publish' => true,
    ])
        ->expectsOutputToContain('--publish')
        ->assertExitCode(0);
});
