<?php
function busbook_ereg_match($pattern, $string, &$matches, $flags = '')
{
    $pattern = '~' . str_replace('~', '\\~', (string) $pattern) . '~' . $flags;
    return preg_match($pattern, (string) $string, $matches);
}

function ereg($pattern, $string, &$registers = null)
{
    $matched = busbook_ereg_match($pattern, $string, $matches);
    $registers = $matches;
    return $matched === 1 ? strlen($matches[0]) : false;
}

function eregi($pattern, $string, &$registers = null)
{
    $matched = busbook_ereg_match($pattern, $string, $matches, 'i');
    $registers = $matches;
    return $matched === 1 ? strlen($matches[0]) : false;
}

function split($pattern, $string, $limit = -1)
{
    $pattern = '~' . str_replace('~', '\\~', (string) $pattern) . '~';
    return preg_split($pattern, (string) $string, (int) $limit);
}

function each(&$array)
{
    $key = key($array);
    if ($key === null) {
        return false;
    }

    $value = current($array);
    next($array);
    return array(0 => $key, 1 => $value, 'key' => $key, 'value' => $value);
}