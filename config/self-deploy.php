<?php

return [

    'log_dir' => storage_path('self-deployments/logs'),

    'deployment_configurations_path' => resource_path('deployments'),

    'deployment_scripts_path' => base_path('.deployments'),

    'environments' => [
        /*
        'production' => [
            'hosts' => ['192.168.1.10', '192.168.1.11'],
            'ssh_user' => 'deploy',
            'remote_path' => '/var/www/my-app',
            'app-production' => [
                'deploy_path' => '/var/www/my-app',
                'branch' => 'main',
            ],
        ],
        */
    ],

    /*
    |--------------------------------------------------------------------------
    | Execution Mode
    |--------------------------------------------------------------------------
    |
    | Supported: "shell", "systemd"
    |
    */
    'execution_mode' => 'shell',

    'systemd' => [
        'nice' => 10,
        'io_scheduling_class' => 'best-effort', // idle, best-effort, real-time
        'io_scheduling_priority' => 7,
        'collect' => true,
        'user' => env('SELF_DEPLOY_USER'), // Optional: Run the systemd unit as a specific user
        'env' => [
            'PATH' => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
        ],
    ],

];
