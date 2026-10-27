#!/usr/bin/env bash
set -euo pipefail
root=$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
for script in "$root"/scripts/*.sh "$root/dashboard/install.sh"; do bash -n "$script"; done
bash "$root/scripts/verify-dashboard.sh"
php "$root/tests/dashboard-test.php"
php "$root/tests/php-security-test.php"
bash "$root/tests/php-security-test.sh"
bash "$root/tests/installer-transcript-test.sh"
bash "$root/tests/updater-test.sh"
node "$root/tests/theme-test.js"
echo 'Release syntax, hashes and isolated fixtures passed.'
