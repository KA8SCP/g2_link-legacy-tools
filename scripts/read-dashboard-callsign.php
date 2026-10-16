<?php
/* Read a saved literal default without executing web-owned PHP as root. */
function dashboardSavedCallsign($path)
{
    if (!is_file($path) || !is_readable($path) || filesize($path) > 1048576) return '';
    $text = @file_get_contents($path);
    if ($text === false) return '';
    $tokens = token_get_all($text);
    $significant = array();
    foreach ($tokens as $token) {
        if (is_array($token) && in_array($token[0], array(T_WHITESPACE,T_COMMENT,T_DOC_COMMENT), true)) continue;
        $significant[] = $token;
    }
    $found = array();
    for ($i=0; $i+2<count($significant); $i++) {
        $key = $significant[$i]; $arrow = $significant[$i+1]; $value = $significant[$i+2];
        if (!is_array($key) || $key[0] !== T_CONSTANT_ENCAPSED_STRING ||
            !in_array($key[1], array("'callsign'", '"callsign"'), true)) continue;
        if (!is_array($arrow) || $arrow[0] !== T_DOUBLE_ARROW ||
            !is_array($value) || $value[0] !== T_CONSTANT_ENCAPSED_STRING) return '';
        if (!isset($significant[$i+3]) ||
            !in_array($significant[$i+3], array(',',')',']'), true)) return '';
        $call = substr($value[1],1,-1);
        if (!preg_match('/^[A-Z0-9]{3,7}$/D', $call)) return '';
        $found[] = $call;
    }
    return count($found) === 1 ? $found[0] : '';
}
if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    echo isset($argv[1]) ? dashboardSavedCallsign($argv[1]) : '';
}
