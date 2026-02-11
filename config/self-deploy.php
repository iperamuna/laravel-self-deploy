<?php

return [

    'log_dir' => storage_path('self-deployments/logs'),

    'deployment_scripts_path' => base_path('.deployments'),

    'environments' => [

        'production' => [

            'app-production' => [

                'deploy_path' => '/var/www/iperamuna-web',

                'blue_service' => 'frankenphp-iperamuna-web.service',

                'green_service' => 'frankenphp-iperamuna2-web.service',

                'blue_upstream_conf' => '/etc/nginx/snippets/frankenphp_upstream_blue.conf',

                'green_upstream_conf' => '/etc/nginx/snippets/frankenphp_upstream_green.conf',

                'active_upstream_conf' => '/etc/nginx/snippets/frankenphp_upstream_active.conf',
            ],

            'app-frontend' => [

                'deploy_path' => '/var/www/iperamuna-web',

                'branch' => 'FR-HTML',
            ]
        ]
    ],

];
