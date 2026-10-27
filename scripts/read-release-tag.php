<?php
/* Select only a published stable release from GitHub's latest-release response. */
function dashboardStableReleaseTag($json)
{
    $release=json_decode($json,true);
    if (!is_array($release) || !isset($release['tag_name'],$release['draft'],$release['prerelease'],$release['published_at']) ||
        $release['draft'] !== false || $release['prerelease'] !== false ||
        !is_string($release['published_at']) || $release['published_at'] === '' ||
        !is_string($release['tag_name']) || !preg_match('/^v?[0-9]+\.[0-9]+\.[0-9]+$/D',$release['tag_name'])) {
        throw new RuntimeException('No valid published stable dashboard release found; choose a reviewed --ref explicitly.');
    }
    return $release['tag_name'];
}
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    try { echo dashboardStableReleaseTag(file_get_contents($argv[1])); }
    catch (Exception $e) { fwrite(STDERR,'ERROR: '.$e->getMessage()."\n"); exit(1); }
}
