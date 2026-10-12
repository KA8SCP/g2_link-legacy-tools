#!/usr/bin/env bash
set -euo pipefail
echo 'Read-only diagnostics; review output before sharing.'
uname -srm
if [[ -r /etc/os-release ]]; then cat /etc/os-release; fi
if command -v php >/dev/null; then php -v; else echo 'PHP CLI unavailable'; fi
for path in /dstar/tmp/status.html /var/www/html/fs.html; do
  if [[ -e "$path" ]]; then
    stat --format='%n mode=%a owner=%U group=%G bytes=%s modified=%y' -- "$path"
    [[ -r "$path" ]] && echo 'Readable by diagnostic user' || echo 'Not readable by diagnostic user'
  else echo "Missing: $path"; fi
done
if command -v getenforce >/dev/null; then getenforce; fi
echo 'Web-server access must be checked under its own account/policy.'
echo 'Marker path and application versions must be recorded from the local gateway.'
