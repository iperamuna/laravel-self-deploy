<?php

return [

    'log_dir' => storage_path('self-deployments/logs'),

    'deployment_configurations_path' => resource_path('deployments'),

    'deployment_scripts_path' => base_path('.deployments'),

    'environments' => [],

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
    ],

];
