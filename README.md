# Laravel Self Deploy

[![Latest Version on Packagist](https://img.shields.io/packagist/v/iperamuna/laravel-self-deploy.svg?style=flat-square)](https://packagist.org/packages/iperamuna/laravel-self-deploy)
[![Total Downloads](https://img.shields.io/packagist/dt/iperamuna/laravel-self-deploy.svg?style=flat-square)](https://packagist.org/packages/iperamuna/laravel-self-deploy)
[![License](https://img.shields.io/packagist/l/iperamuna/laravel-self-deploy.svg?style=flat-square)](https://packagist.org/packages/iperamuna/laravel-self-deploy)

A simple, opinionated Laravel package for managing self-hosted "Blue/Green" style deployments using Artisan commands and shell scripts. It allows you to define deployment configurations in a config file, generate deployment artifacts (Blade templates -> Shell scripts), and trigger them locally or on a server.

## Features

- **Configuration-driven**: Define your environments (e.g., `production`, `staging`) and deployments in `config/self-deploy.php`.
- **Blade-powered Scripts**: Uses Blade templates for your shell scripts, allowing you to inject config variables (like paths, service names) dynamically.
- **Artifact Generation**: Compiles Blade templates into executable `.sh` files.
- **Deployment Trigger**: Run all generated deployment scripts in the background via a single Artisan command.
- **Blue/Green Ready**: The default template is set up for a Blue/Green deployment strategy using Systemd and Nginx upstream switching.

## Installation

You can install the package via composer:

```bash
composer require iperamuna/laravel-self-deploy
```

Publish the configuration and base view:

```bash
php artisan vendor:publish --tag=self-deploy-config
php artisan vendor:publish --tag=self-deploy-views
```

## Configuration

Edit `config/self-deploy.php` to define your environments and deployments.

```php
return [
    'deployment_configurations_path' => resource_path('deployments'),
    'deployment_scripts_path' => base_path('.deployments'),
    'environments' => [
        'production' => [
             'app-production' => [
                 'deploy_path' => '/var/www/my-app',
                 'blue_service' => 'my-app-blue.service',
                 'green_service' => 'my-app-green.service',
                 // ... other custom variables for your script
             ]
        ]
    ]
];
```

## Usage

### 1. Create a Deployment Configuration File

Create a Blade template for a specific deployment configuration. This command now supports **interactive environment and deployment creation**.

#### Interactive Mode (Recommended)

```bash
php artisan selfdeploy:create-deployment-file
```

The command will guide you through:
1. **Select or Add Environment**: Choose an existing environment or create a new one
2. **Select or Add Deployment**: Choose an existing deployment configuration or create a new one
3. **Add Config Keys** (if creating new): Dynamically add key-value pairs for your deployment
   - Enter config key name (e.g., `deploy_path`, `branch`, `service`)
   - Enter default value (blank values accepted as empty strings)
   - Press `d` to finish adding keys
4. **Generate Bash Script**: Optionally generate the deployment script immediately

#### Non-Interactive Mode

You can also pass options directly:

```bash
php artisan selfdeploy:create-deployment-file --environment=production --deployment-name=app-production
```

**Example: Creating a New Environment and Deployment Interactively**

```
❯ php artisan selfdeploy:create-deployment-file

 ┌ Select Environment or Add New ──────────────────────────┐
 │ › + Add New Environment                                  │
 │   production                                             │
 └──────────────────────────────────────────────────────────┘

 ┌ Enter new environment name ─────────────────────────────┐
 │ staging                                                  │
 └──────────────────────────────────────────────────────────┘

 ┌ Enter deployment configuration name ────────────────────┐
 │ app-staging                                              │
 └──────────────────────────────────────────────────────────┘

   INFO  Enter configuration key-value pairs. Press "d" to finish.

 ┌ Config Key (or "d" to done) ────────────────────────────┐
 │ deploy_path                                              │
 └──────────────────────────────────────────────────────────┘

 ┌ Default value for [deploy_path] ────────────────────────┐
 │ /var/www/staging                                         │
 └──────────────────────────────────────────────────────────┘

 ┌ Config Key (or "d" to done) ────────────────────────────┐
 │ branch                                                   │
 └──────────────────────────────────────────────────────────┘

 ┌ Default value for [branch] ─────────────────────────────┐
 │ staging                                                  │
 └──────────────────────────────────────────────────────────┘

 ┌ Config Key (or "d" to done) ────────────────────────────┐
 │ d                                                        │
 └──────────────────────────────────────────────────────────┘

   INFO  Deployment configuration [app-staging] added to [staging].
   INFO  Deployment file created successfully at: resources/deployments/app-staging.blade.php

 ┌ Do you want to generate the Bash script now? ───────────┐
 │ Yes                                                      │
 └──────────────────────────────────────────────────────────┘
```

### 2. Publish (Generate) Deployment Scripts

Compile your Blade templates into executable `.sh` scripts in your configured `deployment_scripts_path`.

```bash
# Interactive
php artisan selfdeploy:publish-deployment-scripts

# All deployments in production
php artisan selfdeploy:publish-deployment-scripts --all --environment=production --force
```

### 3. Run Deployments

Trigger all `.sh` scripts found in your `deployment_scripts_path` directory.

```bash
php artisan selfdeploy:run
```

To automatically regenerate scripts before running:

```bash
php artisan selfdeploy:run --publish
```

## Roadmap

- [ ] Add support for remote server execution (SSH).
- [ ] Implement rollback functionality.
- [ ] Add web interface for managing deployments.
- [ ] Support for Docker-based deployments.
- [ ] Slack/Discord/Telegram notifications integration.

## Development

To run the package tests:

```bash
composer test
```
Or run Pest manually:

```bash
vendor/bin/pest
```

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
