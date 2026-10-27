#!/usr/bin/env bash
# Exercise the real read-only wrapper using isolated process/package boundaries.
set -euo pipefail
root=$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
fixture=$(mktemp -d)
trap 'rm -rf -- "$fixture"' EXIT
export SECURITY_REAL_PHP=$(command -v php)
export SECURITY_EVALUATOR="$root/scripts/check-php-security.php"
mkdir "$fixture/bin"
cat > "$fixture/bin/php" <<'EOF'
#!/usr/bin/env bash
exec "$SECURITY_REAL_PHP" -r 'require getenv("SECURITY_EVALUATOR"); try { dashboardPhpSecurityInventory(stream_get_contents(STDIN),"5.4.45"); } catch(Exception $e) {fwrite(STDERR,$e->getMessage());exit(1);}'
EOF
cat > "$fixture/bin/rpm" <<'EOF'
#!/usr/bin/env bash
if [[ $1 == -qf ]]; then printf '%s' "${SECURITY_OWNER:-php-cli}"; exit; fi
for package in php-cli php-common php; do
 printf '%s\t5.4.45\t%s.el7\tGetPageSpeed LLC\n' "$package" "${SECURITY_RELEASE:-23}"
done
EOF
cat > "$fixture/bin/ps" <<'EOF'
#!/usr/bin/env bash
if [[ ${SECURITY_OLD_WORKER:-no} == yes ]]; then printf '%s php-fpm\n' "$PPID"; fi
EOF
cat > "$fixture/bin/readlink" <<'EOF'
#!/usr/bin/env bash
printf '/usr/sbin/php-fpm (deleted)\n'
EOF
chmod +x "$fixture/bin/"*
export PATH="$fixture/bin:$PATH"
bash "$root/scripts/check-php-security.sh" > "$fixture/output" 2>&1
for scenario in old-package foreign-cli stale-worker; do
 status=0
 case $scenario in
  old-package) SECURITY_RELEASE=19 bash "$root/scripts/check-php-security.sh" > "$fixture/output" 2>&1 || status=$? ;;
  foreign-cli) SECURITY_OWNER=custom-php bash "$root/scripts/check-php-security.sh" > "$fixture/output" 2>&1 || status=$? ;;
  stale-worker) SECURITY_OLD_WORKER=yes bash "$root/scripts/check-php-security.sh" > "$fixture/output" 2>&1 || status=$? ;;
 esac
 [[ $status != 0 ]] || { echo "FAIL: $scenario accepted"; exit 1; }
done
# Initial install invokes the gate; updater is deliberately outside this scope.
sed -n '/^# Initial-install PHP security gate/,/^# End initial-install PHP security gate/p' "$root/scripts/install-dashboard-interactive.sh" > "$fixture/gate"
[[ -s $fixture/gate ]]
mkdir "$fixture/scripts"
cp "$fixture/gate" "$fixture/scripts/installer"
cat > "$fixture/scripts/check-php-security.sh" <<'EOF'
exit 9
EOF
status=0
bash -c 'update_mode=false; die() { exit 17; }; source "$1"' _ "$fixture/scripts/installer" || status=$?
[[ $status == 17 ]]
bash -c 'update_mode=true; die() { exit 17; }; source "$1"' _ "$fixture/scripts/installer"
echo 'PHP security wrapper and initial-install stop checks passed.'
