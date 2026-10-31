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
trap 'exit 130' INT
trap 'exit 143' TERM HUP
token="g2-dashboard-ready-${work##*/}-$$"
printf '<?php $probeToken = "%s";\n' "$token" > "$work/probe.php"
cat >> "$work/probe.php" <<'PHP'
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
function readinessFail($kind, $detail) {
    global $probeToken;
    echo $probeToken, '|FAIL|', $kind, '|', $detail;
    exit;
}
if (version_compare(PHP_VERSION, '5.4.0', '<')) readinessFail('version', PHP_VERSION);
if (!class_exists('DateTime') || !class_exists('DateTimeZone')) readinessFail('date', 'DateTime/DateTimeZone');
foreach (array('json_decode', 'preg_match', 'tmpfile', 'fopen',
    'file_get_contents', 'stream_copy_to_stream') as $function) {
    if (!function_exists($function)) readinessFail('capability', $function);
}
$temporary = @tmpfile();
if (!$temporary) readinessFail('temporary', 'tmpfile');
fclose($temporary);
echo $probeToken, '|', PHP_VERSION;
PHP
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
    die 'Web request failed: check local address/port, TLS trust and running web/PHP services. Dashboard not deployed.'
fi
case $status in
    200) ;;
    403) die 'HTTP 403: access denied or directory index forbidden. Check the selected site, index.php routing and worker permissions; do not enable directory browsing.' ;;
    404) die 'HTTP 404: the selected web route does not map to the dashboard target. Check virtual host, document root and URL.' ;;
    502|503|504) die "HTTP $status: web/PHP backend unavailable. Check PHP-FPM service, configured socket/upstream and socket access." ;;
    301|302|303|307|308) die "HTTP $status: verification was redirected. Choose the correct local route and trusted HTTPS hostname; redirects are not followed." ;;
    *) die "Unexpected HTTP status $status. Check the web-server logs; dashboard not deployed." ;;
esac
body=$(cat "$work/response")
if grep -Fq '<?php' "$work/response"; then
    die 'Raw PHP source returned: PHP was not executed. Check the handler; after Apache/mod_php installation or update, validate configuration and fully restart Apache.'
fi
if grep -Eiq '<(title|h1)>Index of' "$work/response"; then
    die 'Directory listing returned: configure index.php and PHP routing for the dashboard, or use the explicit index.php verification URL.'
fi
if [[ $body == "$token|FAIL|"* ]]; then
    failure=${body#"$token|FAIL|"}
    case $failure in
        version\|*) die 'Web PHP is older than required PHP 5.4; configure the intended supported runtime.' ;;
        date\|*) die 'Web PHP date support is missing: DateTime/DateTimeZone are required.' ;;
        capability\|*)
            capability=${failure#capability|}
            [[ $capability =~ ^[a-z_]+$ ]] || die 'Malformed PHP capability response.'
            die "Web PHP capability missing or disabled: $capability. Review the web runtime configuration." ;;
        temporary\|*) die 'Web PHP cannot create temporary files. Check its temporary directory, free space and worker permissions.' ;;
        *) die 'Unrecognized PHP capability failure.' ;;
    esac
fi
if [[ $body != "$token|"* ]]; then
    if grep -Eiq 'Fatal error:|Parse error:|Warning:' "$work/response"; then
        die 'PHP error output returned: inspect the PHP/web error log for this request.'
    fi
    die 'Unexpected page/content returned: check the virtual host, proxy, cache and verification URL. HTTP 200 alone does not verify PHP.'
fi
web_version=${body#"$token|"}
[[ $web_version =~ ^[0-9]+\.[0-9]+\.[0-9]+[A-Za-z0-9.+~-]*$ ]] || die 'Unrecognized PHP version in web response.'
cli_version=$(php -r 'echo PHP_VERSION;')
[[ $web_version == "$cli_version" ]] ||
    die "CLI/web PHP version mismatch ($cli_version versus $web_version); qualify the selected runtime before deployment."
printf 'Web readiness passed: CLI PHP %s; web PHP %s; required basic capabilities available.\n' "$cli_version" "$web_version"
# A failed short URL is a usability warning, not failure of a working entry point.
base_url=${url%%\?*}
if [[ $base_url == */index.php ]]; then
    directory_url=${base_url%index.php}
    if directory_status=$(curl --silent --show-error --connect-timeout 15 --max-time 30 \
        --output "$work/response" --write-out '%{http_code}' "$directory_url") &&
        [[ $directory_status == 200 && $(cat "$work/response") == "$token|$web_version" ]]; then
        printf 'Directory URL passed separately: %s\n' "$directory_url"
    else
        echo 'WARNING: direct index.php works, but the directory URL did not pass. Check the dashboard DirectoryIndex/index setting; use the direct URL meanwhile.' >&2
    fi
fi
echo 'Temporary readiness probe removed on exit.'
