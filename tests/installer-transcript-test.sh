#!/usr/bin/env bash
# Isolated transcript lifecycle checks; no gateway/web paths are used.
set -euo pipefail
root=$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
fixture=$(mktemp -d)
trap 'rm -rf -- "$fixture"' EXIT
# Extract the actual logging setup, redirecting its only persistent path.
sed -n '/^# Capture output before prerequisite checks/,/^# Qualify g2_link/p' \
    "$root/scripts/install-dashboard-interactive.sh" | sed '$d' > "$fixture/setup"
# Ownership check must match this isolated runner, including non-root CI.
sed -i "s|transcript_root=/var/log/g2-dashboard-install|transcript_root=$fixture/logs|; s|== 0 \&\&|== $(id -u) \&\&|" "$fixture/setup"
for expected in 0 7; do
    {
        printf 'set -euo pipefail\numask 077\ndie() { echo "ERROR: $*" >&2; exit 1; }\n'
        cat "$fixture/setup"
        printf 'echo stdout-marker\necho stderr-marker >&2\nexit %s\n' "$expected"
    } > "$fixture/run"
    status=0
    bash "$fixture/run" > "$fixture/console" 2>&1 || status=$?
    [[ $status == "$expected" ]]
    transcript=$(sed -n 's/^Transcript: //p' "$fixture/console" | head -n 1)
    [[ -f $transcript && $(stat -c %a "$transcript") == 600 ]]
    grep -q stdout-marker "$transcript"
    grep -q stderr-marker "$transcript"
    grep -q "exit status: $expected" "$transcript"
done
# Exercise the actual early prerequisite failure with logging already active.
sed "s|transcript_root=/var/log/g2-dashboard-install|transcript_root=$fixture/logs|; s|\[\[ \${EUID:-1} == 0 \]\]|[[ 1 == 1 ]]|; s|== 0 \&\&|== $(id -u) \&\&|" \
    "$root/scripts/install-dashboard-interactive.sh" > "$fixture/installer"
status=0
printf '/nonexistent/g2-link-test-binary\n' | bash "$fixture/installer" > "$fixture/early" 2>&1 || status=$?
[[ $status == 1 ]]
transcript=$(sed -n 's/^Transcript: //p' "$fixture/early" | head -n 1)
grep -q 'g2_link prerequisite missing' "$transcript"
grep -q 'Existing g2_link executable' "$transcript"
echo 'Installer transcript output, permissions, early failure and exit status checks passed.'
