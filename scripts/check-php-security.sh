#!/usr/bin/env bash
# Read-only, offline prerequisite for standard RPM-based gateway installations.
set -euo pipefail
root=$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
fail() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }
for utility in php rpm realpath ps readlink grep; do
    command -v "$utility" >/dev/null || fail "Cannot verify PHP security: missing $utility"
done
cli=$(realpath -e -- "$(command -v php)")
[[ $(rpm -qf --qf '%{NAME}' "$cli") == php-cli ]] || fail 'PHP executable is not owned by the system php-cli package; separate runtime qualification required.'
inventory=$(rpm -qa --qf '%{NAME}\t%{VERSION}\t%{RELEASE}\t%{VENDOR}\n' 'php*')
printf '%s\n' "$inventory" | php "$root/check-php-security.php"
# Package updates alone do not replace code in existing workers.
while read -r pid command; do
    case $command in
        httpd|apache2|php-fpm)
            [[ -d /proc/$pid ]] || continue
            executable=$(readlink "/proc/$pid/exe") || fail "Cannot inspect web worker $pid; retry after checking service state."
            [[ $executable != *' (deleted)' ]] || fail 'A web worker still uses a replaced executable. Restart the relevant web/PHP service before installing.'
            [[ -r /proc/$pid/maps ]] || fail "Cannot inspect web worker $pid mappings."
            if grep -E '(/libphp[^ /]*\.so|/php[^ /]*\.so).*\(deleted\)' "/proc/$pid/maps" >/dev/null; then
                fail 'A web worker still maps replaced PHP code. Restart the relevant web/PHP service before installing.'
            fi
            ;;
    esac
done < <(ps -eo pid=,comm=)
printf 'PHP worker replacement check passed. This is package verification, not a vulnerability scan or proof of web-route isolation.\n'
