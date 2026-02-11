# Changelog

All notable changes to this project will be documented in this file.

## [v1.5.9] - 2026-02-12

### Fixed
- **Exit Code Detection**: Fixed a bug where `selfdeploy:run` incorrectly reported failure for systemd units due to misinterpretation of `exec()` return values.

## [v1.5.8] - 2026-02-12

### Changed
- **Blade Template Styling**: Cleaned up indentation and code style in the `base.blade.php` deployment template.

## [v1.5.7] - 2026-02-11

### Added
- **Permission Enforcement**: `publish-deployment-scripts` now enforces `sudo` usage to ensure scripts are created with correct permissions.
- **Improved Logging**: Background execution messages in `selfdeploy:run` are now cleaner.
- **Process Robustness**: Shell execution mode now uses `/bin/bash -lc` for better environment login state compatibility.

### Fixed
- **Fluent Strings**: Resolved a "Call to a member function limit() on string" bug in the `selfdeploy:run` command when generating systemd unit names.

## [v1.5.6] - 2026-02-11

### Added
- **Systemd User Configuration**: Added support for specifying a `user` in `config/self-deploy.php` (under `systemd`) to run the transient service as a specific user (e.g., `navissadmin`).
- **Systemd Robustness**: Added default `PATH` environment and `WorkingDirectory` enforcement for systemd units to ensure deployment tools are found reliably.
- **Environment Variable Support**: default configuration now supports `SELF_DEPLOY_USER` in `.env` for easier setup.

## [v1.5.5] - 2026-02-11

### Fixed
- **Config Path Integrity**: Prevented `ConfigFormatter` from resolving absolute paths for core configuration keys (`log_dir`, `deployment_configurations_path`, `deployment_scripts_path`).
- **Path Helper Preservation**: Core path configuration keys now correctly preserve their Laravel helper functions (`storage_path`, `resource_path`, `base_path`) when the configuration file is updated via CLI.
## [v1.5.4] - 2026-02-11

### Added
- **New Logging Helper**: Added `log_cmd()` helper function to `base.blade.php` for cleaner command logging (useful for systemctl commands).
- **Improved Formatting**: enhancing readability of the base deployment script template.

## [v1.5.3] - 2026-02-11

### Added
- **Key Validation**: The `create-deployment-file` command now validates server and config keys for a minimum length of 4 characters and uniqueness.
- **Restricted Keys**: Prevented the use of reserved keyword `self_deploy_server_key` in deployment configurations.
- **Automatic Snake Case**: All server and configuration keys are automatically formatted to `snake_case` for consistency.
- **Config Injection**: Multi-server Blade templates now inject `config('app.server_key')` as the `$self_deploy_server_key` variable.
- **Selective Publishing**: The `publish-deployment-scripts` command now prompts users to select specific servers (or all) when targeting a multi-server deployment.
- **Smart Filtering**: When using `publish-deployment-scripts --all`, if `config('app.server_key')` is set, only the script matching that server key will be generated for multi-server deployments.
- **Multi-Server Publishing Coverage**: Added comprehensive tests for automated publishing of nested server-specific scripts.

## [v1.5.1] - 2026-02-11

### Added
- **Interactive Multi-Server Prompts**: The `create-deployment-file` command now interactively asks if a deployment is for multiple servers and collects a `server_key` variable.
- **Improved UX**: Added helpful hints for common server key patterns (e.g., `config(app.server_key)` or `SERVER_KEY`).

## [v1.5.0] - 2026-02-11

### Added
- **Multi-Server Orchestration**: Added `selfdeploy:remote-deploy` command to trigger deployments across multiple servers via SSH.
- **Host Configuration**: Added support for `hosts`, `ssh_user`, and `remote_path` in environment configurations.
- **Remote Script Regeneration**: Added `--publish` flag to the remote deploy command to regenerate scripts on targeted servers before execution.
- **New Test Suite**: Added `RemoteDeployTest` to verify orchestration logic.

## [v1.4.2] - 2026-02-11

### Changed
- **Nested Log Structure**: Log files are now stored in a script-specific subdirectory within the log directory (e.g., `logs/app-production/deployment-YYYY-MM-DD_HHMMSS.log`).
- **Robust Log Creation**: Improved `base.blade.php` to ensure the parent directory of the log file is created correctly before redirecting output.

## [v1.4.1] - 2026-02-11

### Changed
- **Predictable Log Naming**: Updated `base.blade.php` to use the deployment name in log files instead of the generic `deploy-blue-green-*`.
- **Improved Logging Feedback**: Updated the deployment started message to include the deployment name.

## [v1.4.0] - 2026-02-11

### Added
- **Systemd Execution Mode**: Added support for running deployments as transient systemd units via `execution_mode => 'systemd'`.
- **Resource Limiting**: Added configuration for CPU Niceness and IO scheduling to prevent deployments from starving the host system.
- **Traceable Deployment Units**: Each deployment run now generates a unique, timestamped systemd unit for easier log tracking (e.g., `journalctl -u app-prod-sh-20240211-094500 -f`).
- **New Feature Tests**: Added `RunCommandTest` to verify the execution mode logic and output messages.

### Changed
- Refactored `selfdeploy:run` command to support multiple execution strategies (Shell vs Systemd).
- Updated internal date handling to use Laravel's `now()` for better testability and mocking.
- Enhanced CLI feedback with monitoring hints for supervised deployments.


## [v1.3.0] - 2026-02-11

### Added
- **Config Formatter Utility**: Added `Iperamuna\SelfDeploy\Support\ConfigFormatter` to handle sophisticated PHP array formatting.
- **Improved Config Spacing**: Updated configuration file updates to use a "pretty" format with double-newline spacing between keys for maximum readability.

### Changed
- Refactored `CreateDeploymentFile` command to delegate array formatting to the new `ConfigFormatter` utility.
- Integrated comprehensive unit tests for the formatting logic.
- Total tests: 17 passing (63 assertions) ✅

## [v1.2.1] - 2026-02-11

### Changed
- **Housekeeping**: Updated `.gitignore` to ignore internal release note templates and cleaned up git cache.

## [v1.2.0] - 2026-02-11

### Added
- **Customizable Configuration Path**: Added `deployment_configurations_path` to config, allowing users to customize where Blade deployment templates are stored.
- Commands now respect the `deployment_configurations_path` setting when creating and publishing scripts.
- Support for generating configurations in custom directories outside the default `resources/deployments`.

### Changed
- Refactored internal path handling to consistently use the new `deployment_configurations_path` config option.
- Updated test suite to verify functionality across custom configuration paths.

## [v1.1.1] - 2026-02-11

### Fixed
- **In-Memory Config Update**: Fixed issue where bash script generation would fail when immediately generating scripts after adding new deployment configurations interactively
- Config is now refreshed in memory after updating the config file, ensuring subsequent commands have access to newly added configurations

### Changed
- Improved test coverage with new test for in-memory config updates (14 tests, 59 assertions)

## [v1.1.0] - 2026-02-11

### Added
- **Interactive Environment Creation**: Users can now add new environments on-the-fly via prompts
- **Interactive Deployment Configuration**: Add new deployment configurations interactively
- **Dynamic Config Key-Value Input**: Recursively add configuration keys with support for empty values
- **Auto Bash Script Generation**: Optionally generate deployment scripts immediately after creating deployment files
- **Config File Preservation**: Automatically preserves existing config structure when adding new environments/deployments
- **Enhanced User Experience**: Rich CLI prompts using Laravel Prompts for better UX
- **Comprehensive Test Coverage**: 13 tests covering all interactive and non-interactive workflows

### Changed
- `selfdeploy:create-deployment-file` now supports both interactive and non-interactive modes
- Default published config now starts with empty `environments` array for cleaner fresh installs
- Config file updates now preserve `log_dir` and `deployment_scripts_path` settings

## [v1.0.0] - 2024-02-11

### Added
- Initial release of the package.
- `selfdeploy:create-deployment-file` command to generate Blade deployment templates.
- `selfdeploy:publish-deployment-scripts` command to compile templates to shell scripts.
- `selfdeploy:run` command to execute deployment scripts.
- Support for `self-deploy.php` configuration.
- Support for Blue/Green deployment strategy via customizable templates.
- Automated tests with Pest.

### Roadmap (Future)
- Remote server execution (SSH).
- Rollback functionality.
- Web interface.
- Docker support.
- Notifications (Slack/Discord/Telegram).
