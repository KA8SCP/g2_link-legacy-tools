#!/usr/bin/env bash
set -euo pipefail
root=$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
for script in "$root"/scripts/*.sh "$root/dashboard/install.sh"; do bash -n "$script"; done
bash "$root/scripts/verify-dashboard.sh"
php "$root/tests/dashboard-test.php"
echo 'Release syntax, hashes and isolated fixtures passed.'
