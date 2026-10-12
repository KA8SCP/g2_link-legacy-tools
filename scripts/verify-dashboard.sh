#!/usr/bin/env bash
set -euo pipefail
root=$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
if (( $# > 1 )); then echo 'Usage: verify-dashboard.sh [dashboard-directory]' >&2; exit 2; fi
directory=${1:-"$root/dashboard"}
for command in sha256sum php; do
  command -v "$command" >/dev/null || { echo "Missing requirement: $command" >&2; exit 1; }
done
[[ -f "$root/dashboard/SHA256SUMS" ]] || { echo 'Release manifest missing.' >&2; exit 1; }
for file in index.php style.css dashboard_config.php rf_command_reference.html; do
  [[ -f "$directory/$file" && ! -L "$directory/$file" ]] || { echo "Missing file or unsupported symlink: $directory/$file" >&2; exit 1; }
done
(cd -- "$directory" && sha256sum --check "$root/dashboard/SHA256SUMS")
while read -r checksum file; do
  [[ "$checksum" =~ ^[0-9a-f]{64}$ && "$file" =~ ^[A-Za-z0-9_.-]+$ ]] || { echo 'Invalid manifest entry.' >&2; exit 1; }
  [[ -f "$directory/$file" && ! -L "$directory/$file" ]] || { echo "Invalid release file: $file" >&2; exit 1; }
  if [[ "$file" == *.php ]]; then php -l "$directory/$file"; fi
done < "$root/dashboard/SHA256SUMS"
if [[ -f "$directory/dashboard.local.php" ]]; then php -l "$directory/dashboard.local.php"; fi
