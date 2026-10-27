<?php
/* Accept only a literal return array. Never execute installed/web-owned PHP. */
function dashboardSavedSettings($path)
{
    if (!is_file($path) || !is_readable($path) || filesize($path) > 65536) throw new RuntimeException('Saved configuration unavailable or too large');
    $text = file_get_contents($path);
    $items = array();
    foreach (token_get_all($text) as $token) {
        if (is_array($token) && in_array($token[0],array(T_WHITESPACE,T_COMMENT,T_DOC_COMMENT),true)) continue;
        $items[] = $token;
    }
    $i=0;
    if (!isset($items[$i]) || !is_array($items[$i]) || $items[$i++][0] !== T_OPEN_TAG ||
        !isset($items[$i]) || !is_array($items[$i]) || $items[$i++][0] !== T_RETURN) throw new RuntimeException('Expected literal PHP return array');
    if (isset($items[$i]) && is_array($items[$i]) && $items[$i][0] === T_ARRAY) {
        $i++; if (!isset($items[$i]) || $items[$i++] !== '(') throw new RuntimeException('Expected array opening');
        $close=')';
    } elseif (isset($items[$i]) && $items[$i] === '[') { $i++; $close=']'; }
    else throw new RuntimeException('Expected literal array');
    $values=array();
    while (isset($items[$i]) && $items[$i] !== $close) {
        $key=dashboardSettingLiteral($items,$i);
        if (!isset($items[$i]) || !is_array($items[$i]) || $items[$i++][0] !== T_DOUBLE_ARROW) throw new RuntimeException('Expected setting arrow');
        $value=dashboardSettingLiteral($items,$i);
        if (array_key_exists($key,$values)) throw new RuntimeException('Duplicate setting');
        $values[$key]=$value;
        if (isset($items[$i]) && $items[$i] === ',') $i++;
        elseif (!isset($items[$i]) || $items[$i] !== $close) throw new RuntimeException('Computed settings are unsupported');
    }
    if (!isset($items[$i]) || $items[$i++] !== $close || !isset($items[$i]) || $items[$i++] !== ';') throw new RuntimeException('Incomplete settings');
    if (isset($items[$i]) && is_array($items[$i]) && $items[$i][0] === T_CLOSE_TAG) $i++;
    if ($i !== count($items)) throw new RuntimeException('Executable configuration is unsupported');
    $keys=array('callsign','timezone','document_root','dplus_status','g2_status','dplus_log','g2_log');
    if (count($values) !== count($keys)) throw new RuntimeException('Expected all seven saved settings');
    foreach ($keys as $key) {
        if (!isset($values[$key]) || preg_match('/[\x00\r\n]/',$values[$key])) throw new RuntimeException('Missing or unsafe setting');
    }
    if (!preg_match('/^[A-Z0-9]{3,7}$/D',$values['callsign'])) throw new RuntimeException('Invalid callsign');
    new DateTimeZone($values['timezone']);
    foreach (array_slice($keys,2) as $key) if ($values[$key] === '' || $values[$key][0] !== '/') throw new RuntimeException('Expected absolute input paths');
    return $values;
}
function dashboardSettingLiteral($tokens,&$i)
{
    if (!isset($tokens[$i]) || !is_array($tokens[$i]) || $tokens[$i][0] !== T_CONSTANT_ENCAPSED_STRING) throw new RuntimeException('Expected literal string');
    $raw=$tokens[$i++][1];
    if ($raw[0] === "'") return str_replace(array("\\'","\\\\"),array("'","\\"),substr($raw,1,-1));
    // Plain double-quoted literals are allowed; ambiguous PHP escapes are not.
    $value=substr($raw,1,-1);
    if (strpos($value,'\\') !== false || strpos($value,'$') !== false) throw new RuntimeException('Use single-quoted literal settings');
    return $value;
}
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    try {
        $values=dashboardSavedSettings(isset($argv[1]) ? $argv[1] : '');
        foreach (array('callsign','timezone','document_root','dplus_status','g2_status','dplus_log','g2_log') as $key) echo $values[$key],"\n";
    } catch (Exception $e) { fwrite(STDERR,'ERROR: '.$e->getMessage()."\n"); exit(1); }
}
