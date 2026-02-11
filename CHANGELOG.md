# Changelog

All notable changes to this project will be documented in this file.

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
- Notifications (Slack/Discord).
