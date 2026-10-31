#!/usr/bin/env bash
# Fresh-site readiness probe. Never edits web config or existing PHP files.
set -euo pipefail
die() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }
(( $# == 5 )) || die 'Usage: check-web-readiness.sh DOCUMENT_ROOT DESTINATION LOCAL_URL WEB_USER WEB_GROUP'
document_root=$(realpath -e -- "$1")
destination=$(realpath -m -- "$2")
url=$3
web_user=$4
web_group=$5
[[ -d $document_root && $document_root != / &&
   $destination == "$document_root/"* && ! -L $2 &&
   -d $(dirname -- "$destination") ]] || die 'Invalid dashboard directory.'
[[ $url =~ ^https?://(127\.0\.0\.1|localhost|\[::1\])(:[0-9]+)?/ &&
   $url != *'#'* && $url != *$'\n'* && $url != *$'\r'* ]] || die 'Use the intended local HTTP/HTTPS route.'
relative=${destination#"$document_root"}
url_path=/${url#*://*/}
url_path=${url_path%%\?*}
[[ $url_path == "$relative/" || $url_path == "$relative/index.php" ]] ||
    die 'Readiness URL must address the selected dashboard directory or index.php.'
entry=$destination/index.php
[[ ! -L $entry ]] || die 'Dashboard index must not be a symlink.'
if [[ -e $entry ]]; then
    [[ -f $entry ]] || die 'Existing index.php is not a regular file.'
    echo 'Existing index.php preserved: fresh-site probe skipped; final dashboard HTTP verification remains required.'
    exit 0
fi
work=$(mktemp -d)
created_directory=false
created_probe=false
cleanup() {
    local rc=$?
    trap - EXIT
    if $created_probe; then
        if [[ ! -L $entry ]] && cmp -s -- "$work/probe.php" "$entry"; then
            rm -f -- "$entry" || rc=1
        else
            printf 'ERROR: probe changed unexpectedly; inspect %s before proceeding.\n' "$entry" >&2
            rc=1
        fi
    fi
    rm -f -- "$work/probe.php" "$work/response"
    rmdir -- "$work" || rc=1
    if $created_directory; then
        rmdir -- "$destination" || {
            echo 'ERROR: temporary dashboard directory not empty; inspect it before proceeding.' >&2
            rc=1
        }
    fi
    exit "$rc"
}
trap cleanup EXIT
token="g2-dashboard-ready-${work##*/}-$$"
cat > "$work/probe.php" <<'PHP'
<?php
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
if (version_compare(PHP_VERSION, '5.4.0', '<') ||
    !class_exists('DateTime') || !class_exists('DateTimeZone')) {
    echo 'FAIL: PHP version or date support'; exit;
}
foreach (array('json_decode', 'preg_match', 'tmpfile', 'fopen',
    'file_get_contents', 'stream_copy_to_stream') as $function) {
    if (!function_exists($function)) { echo 'FAIL: PHP capability'; exit; }
}
$temporary = tmpfile();
if (!$temporary) { echo 'FAIL: PHP temporary storage'; exit; }
fclose($temporary);
PHP
printf 'echo "%s|", PHP_VERSION;\n' "$token" >> "$work/probe.php"
if [[ ! -d $destination ]]; then
    install -d -m 0755 -o "$web_user" -g "$web_group" -- "$destination"
    created_directory=true
fi
# Exclusive creation prevents overwriting a concurrent/previous deployment.
(set -o noclobber; cat "$work/probe.php" > "$entry") || die 'Cannot create temporary entry point without overwriting a file.'
created_probe=true
chmod 0644 -- "$entry"
chown "$web_user:$web_group" -- "$entry"
if command -v restorecon >/dev/null; then restorecon -- "$entry"; fi
printf 'Testing PHP execution at %s using a temporary, secret-free entry point.\n' "$url"
if ! status=$(curl --silent --show-error --connect-timeout 15 --max-time 30 \
    --output "$work/response" --write-out '%{http_code}' "$url"); then
    die 'Web readiness request failed. Check the selected site/port, TLS trust and active web/PHP services. Dashboard not deployed.'
fi
body=$(cat "$work/response")
cli_version=$(php -r 'echo PHP_VERSION;')
if [[ $status != 200 || $body != "$token|"* ]]; then
    printf 'Web readiness failed (HTTP %s). Dashboard not deployed.\n' "$status" >&2
    echo 'Check Nginx index/FastCGI routing and PHP-FPM socket access (docs/NGINX-DASHBOARD.md), or Apache PHP handler and post-PHP-update full restart.' >&2
    echo 'HTTP 200 alone is insufficient: PHP source, directory listings, redirects and unrelated pages are rejected. Review PHP version/extensions/temp access and the configured route.' >&2
    exit 1
fi
web_version=${body#"$token|"}
[[ $web_version =~ ^[0-9]+\.[0-9]+\.[0-9]+[A-Za-z0-9.+~-]*$ ]] || die 'Unrecognized PHP version in web response.'
[[ $web_version == "$cli_version" ]] ||
    die "CLI/web PHP version mismatch ($cli_version versus $web_version); qualify the selected runtime before deployment."
printf 'Web readiness passed: CLI PHP %s; web PHP %s; required basic capabilities available. Temporary probe removed on exit.\n' "$cli_version" "$web_version"
