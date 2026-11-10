#!/bin/sh
set -eu
cd "$(dirname "$0")"
command -v python3 >/dev/null || { echo 'Python 3 is required for the launcher. Install it and retry.'; exit 1; }
exec python3 scripts/local-launch.py
