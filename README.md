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
- **Systemd Supervision**: Supports running deployments as transient systemd units for better process isolation, automatic cleanup, and live monitoring.
- **Resource Management**: Configure CPU Niceness and IO scheduling to prevent deployments from impacting web server performance.
- **Blue/Green Ready**: The default template is set up for a Blue/Green deployment strategy using Systemd and Nginx upstream switching.
- **Pretty Configuration**: Automatically formats your `config/self-deploy.php` with double-newline spacing formatted as a standard PHP array.

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
1. **Select or Add Environment**: Choose an existing environment or create a new one.
2. **Select or Add Deployment**: Choose an existing deployment configuration or create a new one.
3. **Multi-Server Setup**: Specify if this deployment spans multiple servers.
   - **Interactive Server Keys**: If multi-server, enter your server identifiers (e.g., `web-01`, `worker-01`).
   - Keys are automatically converted to `snake_case` and must be at least 4 characters.
   - **Hint**: Ensure `SELF_DEPLOY_SERVER_KEY` is defined in each server's `.env` to match these keys.
4. **Add Config Keys**: Dynamically add key-value pairs for your deployment.
   - **Validation**: Keys must be unique and at least 4 characters.
   - **Snake Case**: All keys are automatically formatted to `snake_case`.
   - **Default Values**: Enter default values for each key (blank values accepted as empty strings).
   - Press `d` to finish adding keys.
5. **Multiple Artifact Generation**: For multi-server setups, individual Blade templates are created as `{configuration}-{serverkey}.blade.php`.
6. **Generate Bash Script**: Optionally generate the deployment script(s) immediately.

**Example: Creating a Multi-Server Setup Interactively**

```
❯ php artisan selfdeploy:create-deployment-file

 Select Environment or Add New production
 Select Deployment Configuration or Add New + Add New Deployment Configuration
 Enter deployment configuration name laravel-app
 Does this deployment have multiple app servers? Yes

 INFO Enter Server Keys one by one. Press "Enter" on an empty line or "d" to finish.

 Server Key (or "d" to done) web-01
 Server Key (or "d" to done) web-02
 Server Key (or "d" to done) d

 INFO Enter configuration key names. Press "d" to finish.

 Config Key deploy_path
 Default value for [deploy_path] /var/www/app

 Config Key branch
 Default value for [branch] main

 Config Key d

 INFO Deployment configuration [laravel-app] updated in [production].
 Created: resources/deployments/laravel-app-web_01.blade.php
 Created: resources/deployments/laravel-app-web_02.blade.php
 
 # Example Content of laravel-app-web_01.blade.php
 {{ $self_deploy_server_key }}
 {{ $deploy_path }}
 {{ $branch }}

 Do you want to generate the Bash script now? Yes
```

### 2. Publish (Generate) Deployment Scripts

Compile your Blade templates into executable `.sh` scripts in your configured `deployment_scripts_path`.

```bash
# Interactive (Select deployment & optional server)
php artisan selfdeploy:publish-deployment-scripts

# All deployments in production (Smart Filtering applies)
php artisan selfdeploy:publish-deployment-scripts --all --environment=production --force
```

**Smart Filtering**: If you run with `--all` on a server where `config('app.server_key')` is set, only the script matching that server key will be generated for multi-server deployments.

### 3. Run Deployments

Trigger all `.sh` scripts found in your `deployment_scripts_path` directory.

```bash
php artisan selfdeploy:run
```

To automatically regenerate scripts before running:

```bash
php artisan selfdeploy:run --publish
```

### Execution Modes

You can control how scripts are executed in your `config/self-deploy.php`:

#### 1. Shell Mode (Default)
Runs scripts in the background using standard shell execution (`&`). Simple and cross-platform.

#### 2. Systemd Mode (Recommended for Production)
Runs each script as a transient systemd unit. This is ported from professional deployment actions and provides:
- **Isolation**: Each deployment runs in its own unit.
- **Resource Limiting**: Prevents high-CPU/IO tasks from lagging the main site.
- **Live Logs**: Watch the deployment with `journalctl -u [unit-name] -f`.

To enable Systemd mode:

```php
'execution_mode' => 'systemd',

'systemd' => [
    'nice' => 10,
    'io_scheduling_class' => 'best-effort',
    'io_scheduling_priority' => 7,
],
```

## Multi-Server Orchestration

If you have multiple app servers for a single environment, you can manage them using the `hosts` configuration and the `remote-deploy` command.

### 1. Configure Hosts

Add `hosts`, `ssh_user`, and `remote_path` to your environment configuration in `config/self-deploy.php`:

```php
'environments' => [
    'production' => [
        'hosts' => ['192.168.1.10', '192.168.1.11'],
        'ssh_user' => 'deploy',
        'remote_path' => '/var/www/my-app',
        'app-production' => [
            'deploy_path' => '/var/www/my-app',
            // ...
        ]
    ]
]
```

### 2. Trigger Remote Deployment

Run the `remote-deploy` command from your local machine (or CI/CD server) to reach out to all configured hosts via SSH and trigger their local self-deployments.

```bash
php artisan selfdeploy:remote-deploy production
```

This command will:
1. Connect to each host via SSH as the configured `ssh_user`.
2. `cd` into the `remote_path`.
3. Run `php artisan selfdeploy:run --force`.

To automatically regenerate scripts on remote servers before deploying:

```bash
php artisan selfdeploy:remote-deploy production --publish
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
