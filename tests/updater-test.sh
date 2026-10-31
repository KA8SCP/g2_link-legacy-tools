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
php "$UPDATE_TEST_DEST/index.php"
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
# New sites must not silently become an update; unsafe PHP must not run.
if bash "$fixture/scripts/update-dashboard.sh" --directory "$fixture/html/missing" > "$fixture/missing.log" 2>&1; then exit 1; fi
printf '<?php file_put_contents("%s", "executed"); return array();\n' "$fixture/executed" > "$UPDATE_TEST_DEST/dashboard.local.php"
if bash "$fixture/scripts/update-dashboard.sh" "${args[@]}" > "$fixture/unsafe.log" 2>&1; then exit 1; fi
[[ ! -e $fixture/executed ]]
echo 'Updater cancellation, rollback, configuration preservation, deployment and unsafe-input checks passed.'
