#!/usr/bin/env bash
# Real updater deployment/rollback in an isolated fixture; no services touched.
set -euo pipefail
root=$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
fixture=$(mktemp -d)
trap 'rm -rf -- "$fixture"' EXIT
mkdir -p "$fixture/scripts" "$fixture/bin" "$fixture/html/dashboard" "$fixture/backups"
chmod 700 "$fixture/backups"
cp "$root/scripts/update-dashboard.sh" "$root/scripts/read-dashboard-settings.php" \
    "$root/scripts/check-web-readiness.sh" "$fixture/scripts/"
sed "s|transcript_root=/var/log/g2-dashboard-install|transcript_root=$fixture/transcripts|; s|\[\[ \${EUID:-1} == 0 \]\]|[[ 1 == 1 ]]|; s|== 0 \&\&|== $(id -u) \&\&|" \
    "$root/scripts/install-dashboard-interactive.sh" > "$fixture/scripts/install-dashboard-interactive.sh"
# Avoid root-only readability/SELinux helpers; all fixture files belong to runner.
printf '#!/usr/bin/env bash\nshift 3\nexec "$@"\n' > "$fixture/bin/runuser"
printf '#!/usr/bin/env bash\nexit 0\n' > "$fixture/bin/restorecon"
# HTTP boundary substitute: checks actual installed page rendering via PHP.
cat > "$fixture/bin/curl" <<'EOF'
#!/usr/bin/env bash
if [[ ${UPDATE_TEST_HTTP_FAIL:-0} == 1 ]]; then echo 'simulated HTTP failure' >&2; exit 22; fi
output=''
writeout=false
fail=false
while (( $# )); do
    case $1 in
        --output) output=$2; shift 2 ;;
        --write-out) writeout=true; shift 2 ;;
        --max-time|--connect-timeout) shift 2 ;;
        --fail) fail=true; shift ;;
        --silent|--show-error) shift ;;
        *) url=$1; shift ;;
    esac
done
if [[ ${UPDATE_TEST_DIRECTORY_FAIL:-0} == 1 && $url == */ ]]; then
    $fail && exit 22
    [[ -z $output ]] || : > "$output"
    $writeout && printf 403
    exit 0
fi
if [[ -n $output ]]; then
    if [[ ${UPDATE_TEST_SOURCE:-0} == 1 ]]; then cat "$UPDATE_TEST_DEST/index.php" > "$output"
    else php "$UPDATE_TEST_DEST/index.php" > "$output"; fi
else
    php "$UPDATE_TEST_DEST/index.php"
fi
$writeout && printf 200
exit 0
EOF
chmod +x "$fixture/bin/"*
export PATH="$fixture/bin:$PATH"
export UPDATE_TEST_DEST="$fixture/html/dashboard"
php -r '
$base=$argv[1]; $settings=array("callsign"=>"VE3TTT","timezone"=>"America/Toronto",
"document_root"=>$base."/html","dplus_status"=>$base."/dplus.html","g2_status"=>$base."/g2.html",
"dplus_log"=>$base."/dplus.log","g2_log"=>$base."/g2.log");
file_put_contents($base."/html/dashboard/dashboard.local.php","<?php\n// Keep this site comment.\nreturn ".var_export($settings,true).";\n");
' "$fixture"
printf 'old dashboard\n' > "$UPDATE_TEST_DEST/index.php"
printf 'status\n' > "$fixture/g2.html"
touch "$fixture/dplus.html" "$fixture/dplus.log" "$fixture/g2.log"
cp "$UPDATE_TEST_DEST/dashboard.local.php" "$fixture/original-settings"
args=(--directory "$UPDATE_TEST_DEST" --package-dir "$root" --backup-root "$fixture/backups")
printf 'n\n' | bash "$fixture/scripts/update-dashboard.sh" "${args[@]}" > "$fixture/cancel.log" 2>&1
grep -q 'Cancelled; dashboard unchanged' "$fixture/cancel.log"
grep -q 'old dashboard' "$UPDATE_TEST_DEST/index.php"
if printf 'y\n' | UPDATE_TEST_HTTP_FAIL=1 bash "$fixture/scripts/update-dashboard.sh" "${args[@]}" > "$fixture/failure.log" 2>&1; then exit 1; fi
grep -q 'reverting dashboard' "$fixture/failure.log"
grep -q 'old dashboard' "$UPDATE_TEST_DEST/index.php"
cmp "$fixture/original-settings" "$UPDATE_TEST_DEST/dashboard.local.php"
printf 'y\n' | bash "$fixture/scripts/update-dashboard.sh" "${args[@]}" > "$fixture/success.log" 2>&1
grep -q 'Installed and HTTP-verified' "$fixture/success.log"
cmp "$fixture/original-settings" "$UPDATE_TEST_DEST/dashboard.local.php"
[[ $(stat -c %U "$UPDATE_TEST_DEST/index.php") == "$(id -un)" ]]
[[ -f $UPDATE_TEST_DEST/theme.js ]]

# Preflight-only on an existing target preserves files and creates no backup.
find "$fixture/backups" -type f -printf '%f\n' | sort > "$fixture/backups-before"
cp "$UPDATE_TEST_DEST/index.php" "$fixture/preflight-index"
printf 'y\n' | bash "$fixture/scripts/update-dashboard.sh" --preflight-only "${args[@]}" > "$fixture/preflight-existing" 2>&1
grep -q 'Preflight completed; dashboard not installed or updated' "$fixture/preflight-existing"
cmp "$fixture/preflight-index" "$UPDATE_TEST_DEST/index.php"
cmp "$fixture/original-settings" "$UPDATE_TEST_DEST/dashboard.local.php"
find "$fixture/backups" -type f -printf '%f\n' | sort > "$fixture/backups-after"
cmp "$fixture/backups-before" "$fixture/backups-after"
# A broken directory URL warns but does not roll back a working index.php.
printf 'y\n' | UPDATE_TEST_DIRECTORY_FAIL=1 bash "$fixture/scripts/update-dashboard.sh" "${args[@]}" > "$fixture/directory-warning" 2>&1
grep -q 'Installed and HTTP-verified' "$fixture/directory-warning"
grep -q 'directory index setting' "$fixture/directory-warning"
grep -q 'Acceptance checklist' "$fixture/directory-warning"
# Fresh interactive preflight: fake only the package security boundary, which
# has its own tests. Exercise the real prompts/config validation/probe cleanup.
printf '#!/usr/bin/env bash\necho "isolated security boundary"\n' > "$fixture/scripts/check-php-security.sh"
printf '#!/bin/sh\nexit 0\n' > "$fixture/g2-binary"
chmod +x "$fixture/g2-binary"
fresh=$fixture/html/preflight-dashboard
export UPDATE_TEST_DEST="$fresh"
fresh_inputs() {
    printf '%s\n' "$fixture/g2-binary" "$fixture/g2.html" "$fixture/html" "$fresh" \
        'TESTGW' 'UTC' "$fixture/dplus.html" "$fixture/dplus.log" "$fixture/g2.log" \
        "$(id -un)" "$(id -gn)" 'http://127.0.0.1/preflight-dashboard/index.php' \
        "$fixture/preflight-backups" y
}
fresh_inputs | bash "$fixture/scripts/install-dashboard-interactive.sh" --preflight-only --package-dir "$root" > "$fixture/preflight-fresh" 2>&1
grep -q 'Web readiness passed' "$fixture/preflight-fresh"
grep -q 'Preflight completed; dashboard not installed or updated' "$fixture/preflight-fresh"
[[ ! -e $fresh && ! -e $fixture/preflight-backups ]]
if fresh_inputs | UPDATE_TEST_SOURCE=1 bash "$fixture/scripts/install-dashboard-interactive.sh" --preflight-only --package-dir "$root" > "$fixture/preflight-source" 2>&1; then exit 1; fi
grep -q 'Raw PHP source returned' "$fixture/preflight-source"
[[ ! -e $fresh && ! -e $fixture/preflight-backups ]]
export UPDATE_TEST_DEST="$fixture/html/dashboard"

# New sites must not silently become an update; unsafe PHP must not run.
if bash "$fixture/scripts/update-dashboard.sh" --directory "$fixture/html/missing" > "$fixture/missing.log" 2>&1; then exit 1; fi
printf '<?php file_put_contents("%s", "executed"); return array();\n' "$fixture/executed" > "$UPDATE_TEST_DEST/dashboard.local.php"
if bash "$fixture/scripts/update-dashboard.sh" "${args[@]}" > "$fixture/unsafe.log" 2>&1; then exit 1; fi
[[ ! -e $fixture/executed ]]
echo 'Updater cancellation, rollback, configuration preservation, deployment and unsafe-input checks passed.'
