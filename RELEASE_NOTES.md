# Release Notes - v1.6.0 (2026-02-23)

## 🚀 Live Log Monitoring with Tmux

This release introduces a powerful new way to monitor your self-deployments. You can now launch real-time journal logs immediately after triggering your deployment scripts.

### 🆕 New Features

- **Tmux Split-Screen Monitoring**: When deploying multiple units (e.g., a frontend and a backend service), the package will automatically open a `tmux` session with a horizontal split-window view, allowing you to watch both logs at once.
- **`--tail` Option**: Use the new `--tail` flag on the `selfdeploy:run` command to automatically jump into the logs without any user confirmation.
- **Smart Fallback**: No `tmux`? No problem. The package will detect if `tmux` is missing and gracefully fall back to tailing each journal sequentially in your current terminal.
- **Interactive Prompts**: If you run a manual deployment, you'll be prompted at the end: _"Do you want to tail journals in tmux?"_.

### 🛠 How to Use

Monitor logs automatically:
```bash
php artisan selfdeploy:run --tail
```

Deploy silently (no prompts):
```bash
php artisan selfdeploy:run --force
```

---
*For more details, see the [README.md](README.md) or the [CHANGELOG.md](CHANGELOG.md).*
