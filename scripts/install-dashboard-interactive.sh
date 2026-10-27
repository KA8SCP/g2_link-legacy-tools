#!/usr/bin/env bash
# Dashboard only: do not install or restart gateway services.
set -euo pipefail
umask 077
die() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }
ask() {
    local answer
    if ${update_mode:-false}; then
        printf '%s: %s\n' "$2" "${!1}" >&2
        return
    fi
    printf '%s [%s]: ' "$2" "$3" >&2
    read -r answer
    printf -v "$1" '%s' "${answer:-$3}"
}
yes() { local answer; printf '%s [y/N]: ' "$1" >&2; read -r answer; [[ $answer == y || $answer == Y ]]; }
[[ ${EUID:-1} == 0 ]] || die 'Run as root on the gateway.'
# Capture output before prerequisite checks; stdin stays on the terminal.
for utility in tee mktemp mkfifo install stat date; do
    command -v "$utility" >/dev/null || die "Missing transcript prerequisite: $utility"
done
transcript_root=/var/log/g2-dashboard-install
[[ ! -L $transcript_root ]] || die 'Transcript directory must not be a symlink.'
if [[ ! -e $transcript_root ]]; then install -d -m 0700 -- "$transcript_root"; fi
[[ -d $transcript_root && $(stat -c %u "$transcript_root") == 0 &&
    $(stat -c %a "$transcript_root") == 700 ]] || die 'Transcript directory must be root-owned with mode 0700.'
transcript=$(mktemp "$transcript_root/install-$(date -u +%Y%m%dT%H%M%SZ).XXXXXXXX.log")
exec 3>&1 4>&2
# An explicit background child can be waited for on CentOS 7's Bash 4.2;
# process-substitution PIDs are not reliably waitable there.
transcript_pipe_dir=$(mktemp -d "$transcript_root/.pipe.XXXXXXXX")
mkfifo -- "$transcript_pipe_dir/output"
tee -- "$transcript" < "$transcript_pipe_dir/output" &
transcript_pid=$!
exec > "$transcript_pipe_dir/output" 2>&1
work=''
finish() {
    local rc=$?
    trap - EXIT
    printf '\nInstaller finished at %s; exit status: %s\nTranscript: %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$rc" "$transcript"
    if [[ -n $work && -d $work && $work == /var/tmp/g2-dashboard-install.* ]]; then rm -rf -- "$work"; fi
    exec 1>&3 2>&4 3>&- 4>&-
    if ! wait "$transcript_pid"; then
        printf 'ERROR: transcript capture failed: %s\n' "$transcript" >&2
        if (( rc == 0 )); then rc=1; fi
    fi
    rm -f -- "$transcript_pipe_dir/output"
    rmdir -- "$transcript_pipe_dir"
    exit "$rc"
}
trap finish EXIT
printf 'Installer started at %s\nTranscript: %s\nPrompts and output are recorded; typed responses are not echoed. Do not enter credentials.\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$transcript"
# Parse options before prompts. Updates require an existing saved configuration.
update_mode=false
package_dir=''
requested_destination=/var/www/html/dashboard
repo=https://github.com/KA8SCP/g2_link-legacy-tools.git
ref=v0.5.0
check_url=''
backup_root=/var/backups/g2-dashboard
while (( $# )); do
    case $1 in
        --update) update_mode=true; shift ;;
        --directory|--ref|--check-url|--backup-root|--package-dir)
            (( $# >= 2 )) || die "Missing value for $1"
            case $1 in
                --directory) requested_destination=$2 ;;
                --ref) ref=$2 ;;
                --check-url) check_url=$2 ;;
                --backup-root) backup_root=$2 ;;
                --package-dir) package_dir=$(realpath -e -- "$2") ;;
            esac
            shift 2 ;;
        *) die 'Usage: install-dashboard-interactive.sh [--package-dir PATH] or update-dashboard.sh [--directory PATH] [--ref TAG_OR_BRANCH] [--check-url LOCAL_URL] [--backup-root PATH]' ;;
    esac
done
work=$(mktemp -d /var/tmp/g2-dashboard-install.XXXXXXXX)
if $update_mode; then
    command -v php >/dev/null || die 'PHP CLI is required.'
    [[ $requested_destination == /* && ! -L $requested_destination ]] || die 'Update directory must be absolute and not a symlink.'
    destination=$(realpath -e -- "$requested_destination")
    [[ -f $destination/index.php && -f $destination/dashboard.local.php && ! -L $destination/dashboard.local.php ]] || die 'No baseline dashboard/site configuration found; use the installer for a first installation.'
    cp -- "$destination/dashboard.local.php" "$work/saved-dashboard.local.php"
    script_root=$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
    php "$script_root/read-dashboard-settings.php" "$work/saved-dashboard.local.php" > "$work/settings"
    mapfile -t saved < "$work/settings"
    (( ${#saved[@]} == 7 )) || die 'Incomplete saved settings.'
    gateway=${saved[0]}; timezone=${saved[1]}; document_root=${saved[2]}
    dp_status=${saved[3]}; g2_status=${saved[4]}; dp_log=${saved[5]}; g2_log=${saved[6]}
    [[ $destination == "$document_root/"* && $document_root != / ]] || die 'Saved document root does not contain dashboard.'
    web_user=$(stat -c %U "$destination/index.php")
    web_group=$(stat -c %G "$destination/index.php")
    if [[ -z $check_url ]]; then check_url="http://127.0.0.1${destination#"$document_root"}/"; fi
    printf 'Update %s; preserving saved configuration and existing file ownership %s:%s.\nSource: %s (%s)\n' "$gateway" "$web_user" "$web_group" "$repo" "$ref"
fi
# Qualify g2_link before checking dashboard-specific PHP/web prerequisites.
# Paths are explicit because legacy installations need not put g2_link in PATH.
if ! $update_mode; then
default_g2_binary=$(command -v g2_link || true)
ask g2_binary 'Existing g2_link executable' "${default_g2_binary:-/dstar/tools/g2_link}"
[[ $g2_binary == /* && $g2_binary != *$'\n'* && $g2_binary != *$'\r'* ]] || die 'g2_link executable path must be absolute.'
[[ -f $g2_binary && -x $g2_binary ]] || die 'g2_link prerequisite missing: supply an installed executable before installing the unified dashboard.'
fi
ask g2_status 'Existing nonempty g2_link status HTML' '/var/www/html/fs.html'
[[ $g2_status == /* && $g2_status != *$'\n'* && $g2_status != *$'\r'* ]] || die 'g2_link status path must be absolute.'
[[ -f $g2_status && -s $g2_status && -r $g2_status ]] || die 'g2_link status feed is missing, empty or unreadable. Configure g2_link/status generation before installing the unified dashboard; empty placeholders do not qualify.'
printf 'g2_link prerequisites found; checking dashboard-specific PHP/web requirements.\n'
for utility in php git sha256sum tar install mktemp realpath getent curl stat; do
    command -v "$utility" >/dev/null || die "Missing prerequisite: $utility"
done
php -r 'exit(version_compare(PHP_VERSION,"5.4.0",">=") ? 0 : 1);' || die 'PHP CLI 5.4 or newer is required.'
# Initial-install PHP security gate
if ! $update_mode; then
    security_scripts=$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
    bash "$security_scripts/check-php-security.sh" || die 'Initial installation stopped: PHP2Shell security prerequisite failed. No dashboard was deployed. Patch/qualify the web PHP runtime first; see docs/PHP-SECURITY.md.'
fi
# End initial-install PHP security gate
printf 'Unified D-STAR Dashboard installation\n'
printf 'Existing DPlus and working web PHP integration are required; PHP-FPM is not a universal Icom prerequisite.\n'
printf 'This installs the dashboard only; gateway services and link settings stay unchanged.\n\n'
if [[ -z $package_dir ]]; then
    ask repo 'GitHub repository URL' 'https://github.com/KA8SCP/g2_link-legacy-tools.git'
    [[ $repo =~ ^https://github\.com/[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+(\.git)?$ ]] || die 'Use a GitHub HTTPS repository URL without credentials.'
    ask ref 'Release tag or branch (prefer a reviewed release tag)' "$ref"
    if [[ $ref == latest ]]; then
        curl --fail --silent --show-error --connect-timeout 15 --max-time 30 \
            https://api.github.com/repos/KA8SCP/g2_link-legacy-tools/releases/latest > "$work/release.json"
        tool_scripts=$(CDPATH= cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
        ref=$(php "$tool_scripts/read-release-tag.php" "$work/release.json")
        printf 'Selected published stable release: %s\n' "$ref"
    fi
    [[ $ref != -* && $ref != *$'\n'* && -n $ref ]] || die 'Invalid Git ref.'
    git clone --depth 1 --branch "$ref" -- "$repo" "$work/package"
    package_dir=$work/package
    revision=$(cd -- "$package_dir" && git rev-parse HEAD)
else
    repo='local reviewed package'; ref='local'; revision='local package; see SHA256SUMS'
    if [[ -d $package_dir/.git ]]; then revision=$(cd -- "$package_dir" && git rev-parse HEAD); fi
fi
source_dir=$package_dir/dashboard
[[ -f $source_dir/SHA256SUMS && -f $source_dir/dashboard_config.php ]] || die 'Repository does not contain the current portable dashboard release.'
bash "$package_dir/scripts/verify-dashboard.sh"
release_version=$(php -r 'require $argv[1]; echo DASHBOARD_VERSION;' "$source_dir/dashboard_release.php")
printf 'Package version: %s\n' "$release_version"

ask document_root 'Web document root' '/var/www/html'
document_root=$(realpath -e -- "$document_root")
[[ -d $document_root && $document_root != / && $document_root != *$'\n'* ]] || die 'Invalid document root.'
ask destination 'Dashboard directory' "$document_root/dashboard"
[[ ! -L $destination ]] || die 'Dashboard destination must not be a symlink.'
destination=$(realpath -m -- "$destination")
[[ $destination == "$document_root/"* && $destination != "$document_root" ]] || die 'Dashboard must be below the document root.'
[[ -d $(dirname -- "$destination") ]] || die 'Dashboard parent directory must exist.'
[[ ! -e $destination || -d $destination ]] || die 'Destination is not a directory.'
[[ $destination != *$'\n'* ]] || die 'Invalid destination.'
default_gateway=$(php "$package_dir/scripts/read-dashboard-callsign.php" "$destination/dashboard.local.php")
if [[ -n $default_gateway ]]; then printf 'Established dashboard callsign: %s\n' "$default_gateway"; fi
ask gateway 'Gateway callsign (without module; required)' "$default_gateway"
gateway=${gateway^^}
[[ $gateway =~ ^[A-Z0-9]{3,7}$ ]] || die 'Invalid callsign.'
ask timezone 'Gateway log timezone' 'America/New_York'
php -r 'try {new DateTimeZone($argv[1]);} catch(Exception $e) {exit(1);}' "$timezone" || die 'Invalid timezone.'
ask dp_status 'DPlus status HTML' '/dstar/tmp/status.html'

ask dp_log 'DPlus log' '/var/log/dplus.log'
ask g2_log 'g2_link log' '/var/log/g2_link.log'
for path in "$dp_status" "$g2_status" "$dp_log" "$g2_log"; do
    [[ $path == /* && $path != *$'\n'* && $path != *$'\r'* ]] || die 'Input paths must be absolute.'
done
default_owner=''
if command -v ps >/dev/null; then
    mapfile -t worker_users < <(ps -eo user=,comm= | awk '$1 != "root" && $2 == "php-fpm" {print $1}' | sort -u)
    if (( ${#worker_users[@]} == 1 )); then default_owner=${worker_users[0]}; fi
    if [[ -z $default_owner ]]; then
        mapfile -t worker_users < <(ps -eo user=,comm= | awk '$1 != "root" && ($2 == "httpd" || $2 == "apache2") {print $1}' | sort -u)
        if (( ${#worker_users[@]} == 1 )); then default_owner=${worker_users[0]}; fi
    fi
fi
if [[ -z $default_owner ]]; then
    for account in apache nginx www-data; do
        if getent passwd "$account" >/dev/null; then default_owner=$account; break; fi
    done
fi
ask web_user 'PHP worker operating-system user' "$default_owner"
getent passwd "$web_user" >/dev/null || die 'Web user does not exist.'
default_group=$(id -gn "$web_user")
ask web_group 'Web file group' "$default_group"
getent group "$web_group" >/dev/null || die 'Web group does not exist.'
for path in "$dp_status" "$g2_status" "$dp_log" "$g2_log"; do
    if [[ ! -f $path ]]; then
        printf 'Unavailable input: %s\n' "$path"
        yes 'Continue with this input unavailable?' || die 'Supply the correct input path first.'
    elif command -v runuser >/dev/null; then
        runuser -u "$web_user" -- test -r "$path" || die "Web user cannot read $path; review local permissions before installation."
    fi
done
relative=${destination#"$document_root"}
ask check_url 'Local dashboard URL for HTTP verification' "http://127.0.0.1$relative/"
[[ $check_url =~ ^https?://(127\.0\.0\.1|localhost|\[::1\])(:[0-9]+)?/ ]] || die 'Verification URL must be local HTTP/HTTPS.'
ask backup_root 'Backup directory outside the web root' '/var/backups/g2-dashboard'
backup_root=$(realpath -m -- "$backup_root")
[[ $backup_root != / && $backup_root != "$document_root" && $backup_root != "$document_root/"* ]] || die 'Backups must be outside the web root.'
if [[ ! -e $backup_root ]]; then
    install -d -m 0700 -- "$backup_root"
fi
[[ -d $backup_root && $(stat -c %u "$backup_root") == 0 &&
    $(stat -c %a "$backup_root") == 700 ]] || die 'Use a dedicated root-owned backup directory with mode 0700.'
stamp=$(date -u +%Y%m%dT%H%M%SZ)-$$
backup=$backup_root/dashboard-$stamp.tar.gz

candidate=$work/candidate
mkdir -- "$candidate"
mapfile -t files < <(awk '{print $2}' "$source_dir/SHA256SUMS")
(( ${#files[@]} >= 15 )) || die 'Incomplete dashboard manifest.'
for file in "${files[@]}"; do
    [[ $file =~ ^[A-Za-z0-9_.-]+$ && ! -L $source_dir/$file && -f $source_dir/$file ]] || die 'Unsafe or missing release file.'
    install -m 0644 -- "$source_dir/$file" "$candidate/$file"
    [[ ! -L $destination/$file ]] || die "Existing deployment file is a symlink: $file"
done
[[ ! -L $destination/dashboard.local.php ]] || die 'Existing site configuration is a symlink.'
[[ ! -L $destination/dashboard-build.json ]] || die 'Existing build metadata is a symlink.'
if $update_mode; then
    cp -- "$work/saved-dashboard.local.php" "$candidate/dashboard.local.php"
else
php -r '
$keys=array("callsign","timezone","document_root","dplus_status","g2_status","dplus_log","g2_log");
$settings=array(); foreach($keys as $i=>$key) $settings[$key]=$argv[$i+2];
$text="<?php\nreturn ".var_export($settings,true).";\n";
if(file_put_contents($argv[1],$text)!==strlen($text)) exit(1);
' "$candidate/dashboard.local.php" "$gateway" "$timezone" "$document_root" "$dp_status" "$g2_status" "$dp_log" "$g2_log"
fi
php -l "$candidate/dashboard.local.php"
php -r '
$revision=preg_match("/^[0-9a-f]{40,64}$/",$argv[2]) ? $argv[2] : "";
$data=json_encode(array("revision"=>$revision));
if(file_put_contents($argv[1],$data)!==strlen($data)) exit(1);
' "$candidate/dashboard-build.json" "$revision"
php -r 'require $argv[1]."/dashboard_config.php"; date_default_timezone_set(dashboardConfig("timezone")); echo "Configuration: ",dashboardConfig("callsign"),"\n";' "$candidate"

printf '\nReady to install %s at %s\nRepository: %s\nRef: %s\nCommit: %s\nVerification: %s\nBackup: %s\n' "$gateway" "$destination" "$repo" "$ref" "$revision" "$check_url" "$backup"
yes 'Install this reviewed configuration?' || { echo 'Cancelled; dashboard unchanged.'; exit 0; }
if $update_mode; then
    cmp -s -- "$work/saved-dashboard.local.php" "$destination/dashboard.local.php" || die 'Saved settings changed during review; rerun the updater.'
fi
existed=false
if [[ -d $destination ]]; then
    existed=true
    tar -czf "$backup" -C "$(dirname -- "$destination")" -- "$(basename -- "$destination")"
else
    printf 'New installation; no previous dashboard.\n' > "$backup_root/new-install-$stamp.txt"
fi
printf 'Repository: %s\nRef: %s\nCommit: %s\nVersion: %s\nDestination: %s\nTranscript: %s\n' "$repo" "$ref" "$revision" "$release_version" "$destination" "$transcript" > "$backup_root/install-$stamp.txt"
cp -- "$source_dir/SHA256SUMS" "$backup_root/SHA256SUMS-$stamp"
rollback() {
    trap - ERR
    echo 'Installation verification failed; reverting dashboard.' >&2
    if [[ -d $destination ]]; then mv -- "$destination" "$backup_root/failed-$stamp"; fi
    if $existed; then tar -xzf "$backup" -C "$(dirname -- "$destination")"; fi
    echo "Failure files and backup retained in $backup_root" >&2
}
trap 'rollback; exit 1' ERR
install -d -o "$web_user" -g "$web_group" -m 0755 -- "$destination"
# Dependencies and configuration first; entry point last.
for file in "${files[@]}" dashboard.local.php dashboard-build.json; do
    [[ $file == index.php ]] && continue
    install -o "$web_user" -g "$web_group" -m 0644 -- "$candidate/$file" "$destination/$file"
done
install -o "$web_user" -g "$web_group" -m 0644 -- "$candidate/index.php" "$destination/index.php"
if command -v restorecon >/dev/null; then restorecon -R -- "$destination"; fi
bash "$package_dir/scripts/verify-dashboard.sh" "$destination"
curl --fail --silent --show-error --max-time 120 "$check_url" > "$work/page.html"
php -r '
$s=file_get_contents($argv[1]);
$ok=strpos($s,"Unified Last Heard")!==false && strpos($s,"RF Command Reference")!==false &&
strpos($s,"Remote Users")!==false && strpos($s,"Software &amp; Sources")!==false &&
!preg_match("/Warning:|Fatal error:|Parse error:|<\\?php/",$s);
exit($ok ? 0 : 1);
' "$work/page.html"
trap - ERR
printf '\nInstalled and HTTP-verified: %s\n' "$check_url"
if $existed; then
    printf 'Rollback archive: %s\n' "$backup"
else
    printf 'New-install record: %s/new-install-%s.txt\n' "$backup_root" "$stamp"
fi
printf 'Review the page and perform local RF/remote transmission tests.\n'

