#!/usr/bin/env bash
# Starts Laravel Octane on the Swoole server.
#
# Swoole isn't installed system-wide (no root access on this machine) — it
# was compiled from source into ~/.local/php-extensions/swoole.so instead.
# PHP_INI_SCAN_DIR appends that directory to PHP's normal ini scan dirs (the
# leading ':' keeps every default extension — sockets, openssl, curl, etc —
# loading as usual), and it's inherited by the worker subprocess Octane
# spawns internally, which is what actually needs the extension loaded.
#
# Usage: ./octane-swoole.sh [--workers=N] [--port=8000] [any octane:start flag]

export PHP_INI_SCAN_DIR=":${HOME}/.local/php-extensions"

php artisan octane:start --server=swoole "$@"
