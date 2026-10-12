#!/usr/bin/env bash
set -euo pipefail
root=$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
if (( $# != 1 )); then echo 'Usage: install-dashboard.sh /absolute/new-staging-directory' >&2; exit 2; fi
destination=$1
[[ "$destination" == /* ]] || { echo 'An absolute staging path is required.' >&2; exit 2; }
[[ ! -e "$destination" && ! -L "$destination" ]] || { echo 'Destination already exists; refusing to overwrite.' >&2; exit 1; }
bash "$root/scripts/verify-dashboard.sh"
umask 022
mkdir -- "$destination"
while read -r checksum file; do
  install -m 0644 -- "$root/dashboard/$file" "$destination/$file"
done < "$root/dashboard/SHA256SUMS"
bash "$root/scripts/verify-dashboard.sh" "$destination"
echo "Staged and verified: $destination"
echo 'Apply local web ownership/SELinux policy and test the candidate URL before switching live.'
