<?php

namespace Iperamuna\SelfDeploy\Tests;

use Illuminate\Support\Facades\File;
use Iperamuna\SelfDeploy\SelfDeployServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpConfig();
    }

    protected function getPackageProviders($app)
    {
        return [
            SelfDeployServiceProvider::class,
        ];
    }

    protected function setUpConfig()
    {
        config()->set('self-deploy.environments', [
            'production' => [
                'app-production' => [
                    'deploy_path' => '/var/www/test-app',
                    'blue_service' => 'test-blue.service',
                    'green_service' => 'test-green.service',
                ]
            ],
            'staging' => [
                'app-staging' => [
                    'deploy_path' => '/var/www/test-staging',
                ]
            ]
        ]);

        // Define temp paths for tests
        $deploymentsPath = __DIR__ . '/temp/deployments';
        config()->set('self-deploy.deployment_scripts_path', $deploymentsPath);

        // Ensure clean slate
        if (File::exists($deploymentsPath)) {
            File::deleteDirectory($deploymentsPath);
        }
        File::makeDirectory($deploymentsPath, 0755, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if (File::exists(__DIR__ . '/temp')) {
            File::deleteDirectory(__DIR__ . '/temp');
        }
    }
}
