# Changelog

All notable changes to this project will be documented in this file.
## [v1.4.1] - 2026-02-11

### Changed
- **Predictable Log Naming**: Updated `base.blade.php` to use the deployment name in log files (e.g., `app-production-deployment-YYYY-MM-DD_HHMMSS.log`) instead of the generic `deploy-blue-green-*`.
- **Improved Logging Feedback**: Updated the deployment started message to include the deployment name for better clarity.

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
