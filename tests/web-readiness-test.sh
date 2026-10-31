#!/usr/bin/env bash
# Isolated HTTP boundary substitute; no server/service/gateway config touched.
set -euo pipefail
root=$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)
fixture=$(mktemp -d)
trap 'rm -rf -- "$fixture"' EXIT
mkdir -p "$fixture/html" "$fixture/bin"
printf 'legacy status page must stay active' > "$fixture/html/index.html"
cp "$fixture/html/index.html" "$fixture/legacy-original"
cat > "$fixture/bin/curl" <<'CURL'
#!/usr/bin/env bash
while (( $# )); do
    case $1 in
        --output) response=$2; shift 2 ;;
        --write-out|--connect-timeout|--max-time) shift 2 ;;
        --silent|--show-error) shift ;;
        *) url=$1; shift ;;
    esac
done
[[ $url == http://127.0.0.1/dashboard/ || $url == http://127.0.0.1/dashboard/index.php ]] || exit 9
case ${READINESS_TEST_MODE:-execute} in
    execute) php "$READINESS_TEST_ENTRY" > "$response"; printf 200 ;;
    mismatch) php "$READINESS_TEST_ENTRY" | sed 's/|[^|]*$/|0.0.0/' > "$response"; printf 200 ;;
    listing) printf '<title>Index of /dashboard</title>' > "$response"; printf 200 ;;
    capability|temporary|date|version)
        token=$(sed -n 's/^<?php \$probeToken = "\(.*\)";$/\1/p' "$READINESS_TEST_ENTRY")
        case $READINESS_TEST_MODE in
            capability) printf '%s|FAIL|capability|json_decode' "$token" > "$response" ;;
            temporary) printf '%s|FAIL|temporary|tmpfile' "$token" > "$response" ;;
            date) printf '%s|FAIL|date|DateTime/DateTimeZone' "$token" > "$response" ;;
            version) printf '%s|FAIL|version|5.3.0' "$token" > "$response" ;;
        esac
        printf 200 ;;
    directory_forbidden)
        if [[ $url == */index.php ]]; then php "$READINESS_TEST_ENTRY" > "$response"; printf 200
        else : > "$response"; printf 403; fi ;;
    source) cat "$READINESS_TEST_ENTRY" > "$response"; printf 200 ;;
    wrong) printf '<html>Other site</html>' > "$response"; printf 200 ;;
    forbidden) : > "$response"; printf 403 ;;
    gateway) : > "$response"; printf 502 ;;
    redirect) : > "$response"; printf 301 ;;
    fail) exit 7 ;;
esac
CURL
printf '#!/usr/bin/env bash\nexit 0\n' > "$fixture/bin/restorecon"
chmod +x "$fixture/bin/"*
export PATH="$fixture/bin:$PATH"
export READINESS_TEST_ENTRY="$fixture/html/dashboard/index.php"
user=$(id -un); group=$(id -gn)
args=("$fixture/html" "$fixture/html/dashboard" http://127.0.0.1/dashboard/index.php "$user" "$group")
bash "$root/scripts/check-web-readiness.sh" "${args[@]}" > "$fixture/pass" 2>&1
grep -q 'Web readiness passed' "$fixture/pass"
[[ ! -e $fixture/html/dashboard ]]
for mode in source listing wrong forbidden gateway redirect fail mismatch capability temporary date version; do
    if READINESS_TEST_MODE=$mode bash "$root/scripts/check-web-readiness.sh" "${args[@]}" > "$fixture/$mode" 2>&1; then exit 1; fi
    [[ ! -e $fixture/html/dashboard ]]
done

grep -q 'Raw PHP source returned' "$fixture/source"
grep -q 'Directory listing returned' "$fixture/listing"
grep -q 'Unexpected page/content' "$fixture/wrong"
grep -q 'HTTP 403' "$fixture/forbidden"
grep -q 'backend unavailable' "$fixture/gateway"
grep -q 'redirected' "$fixture/redirect"
grep -q 'CLI/web PHP version mismatch' "$fixture/mismatch"
grep -q 'missing or disabled: json_decode' "$fixture/capability"
grep -q 'cannot create temporary files' "$fixture/temporary"
grep -q 'date support is missing' "$fixture/date"
grep -q 'older than required' "$fixture/version"
READINESS_TEST_MODE=directory_forbidden bash "$root/scripts/check-web-readiness.sh" "${args[@]}" > "$fixture/directory-warning" 2>&1
grep -q 'direct index.php works' "$fixture/directory-warning"
[[ ! -e $fixture/html/dashboard ]]

mkdir "$fixture/html/dashboard"
printf 'retain this unrelated asset' > "$fixture/html/dashboard/keep.txt"
args[2]=http://127.0.0.1/dashboard/
bash "$root/scripts/check-web-readiness.sh" "${args[@]}" > "$fixture/directory" 2>&1
[[ -f $fixture/html/dashboard/keep.txt && ! -e $READINESS_TEST_ENTRY ]]
printf 'existing dashboard must not be overwritten' > "$READINESS_TEST_ENTRY"
cp "$READINESS_TEST_ENTRY" "$fixture/original"
READINESS_TEST_MODE=fail bash "$root/scripts/check-web-readiness.sh" "${args[@]}" > "$fixture/existing" 2>&1
cmp "$READINESS_TEST_ENTRY" "$fixture/original"
grep -q 'fresh-site probe skipped' "$fixture/existing"
rm "$READINESS_TEST_ENTRY"
ln -s "$fixture/original" "$READINESS_TEST_ENTRY"
if bash "$root/scripts/check-web-readiness.sh" "${args[@]}" > "$fixture/symlink" 2>&1; then exit 1; fi
cmp "$fixture/original" "$READINESS_TEST_ENTRY"
rm "$READINESS_TEST_ENTRY"
args[2]=http://127.0.0.1/other/index.php
if bash "$root/scripts/check-web-readiness.sh" "${args[@]}" > "$fixture/wrong-route" 2>&1; then exit 1; fi
[[ ! -e $READINESS_TEST_ENTRY ]]
cmp "$fixture/html/index.html" "$fixture/legacy-original"
echo 'Web readiness execution/source/status/cleanup/preservation/symlink/route checks passed.'
