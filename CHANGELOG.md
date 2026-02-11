# Changelog

All notable changes to this project will be documented in this file.

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
