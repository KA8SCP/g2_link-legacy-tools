#!/usr/bin/env bash
# Uses the same staging, transcript, backup and HTTP rollback as installation.
set -euo pipefail
root=$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
exec bash "$root/scripts/install-dashboard-interactive.sh" --update "$@"
