<?php

/**
 *
 * Iterates object properties (or array or any other primitive type variable),
 * and apply callback function on all its members / properties.
 * Returns changed object
 *
 * @param mixed $data
 * @param callable $function
 * @return mixed
 */
function object_walk($data, $function)
{
    if (is_array($data) || ($is_o = is_object($data))) {
        if ($is_o)
            $data = (array) $data;
        foreach ($data as $key => $value) {
            if (is_array($value) || is_object($value))
                $data[$key] = object_walk($value, $function);
            else
                $data[$key] = call_user_func($function, $value);
        }
        if ($is_o)
            $data = (object) $data;
        return $data;
    } else {
        return call_user_func($function, $data);
    }
}

function normalize($str)
{
    include_once 'utf8.inc.php';
    return strtolower(trim(utf8_deaccent(preg_replace(array(
        '~[\.\"\']+~',
        '~[\s\/&\-\?]+~'
    ), array(
        '',
        '-'
    ), $str))));
}

/**
 *
 * Returns existing file from a list of files provided
 *
 * @param array $buf
 * @param string $postfix
 * @param string $prefix
 * @param boolean $return_full_path
 * @return string
 */
function pick_existing_file($files_list, $postfix = '', $prefix = '', $return_full_path = true)
{
    foreach ($files_list as $value) {
        if (file_exists($prefix . $value . $postfix)) {
            return $return_full_path ? $prefix . $value . $postfix : $value;
            break;
        }
    }
    return;
}

function pprint_r($obj)
{
    echo "<pre>", print_r($obj, true), '</pre>';
}

function sk_query_remove($fields = array(), $base_url = false, $fix_amps = true, $field_filter_relaxed = true)
{
    if ($base_url == "?")
        $base_url = $_SERVER['PHP_SELF'];
    ;
    if ($base_url)
        $base_url .= "?";
    
    $pattern = array();
    $pattern_suffix = $field_filter_relaxed ? '((\[|%5B).*?(\]|%5D)|)' : '';
    foreach ((array) $fields as $field) {
        $pattern[] = "/(^|&)$field$pattern_suffix=[^&]*/i";
    }
    $pattern[] = '/^&/i';
    $pattern[] = '/&$/i';
    return $fix_amps ? preg_replace('/\&(?!amp;)/', '&amp;', $base_url . preg_replace($pattern, "", $_SERVER['QUERY_STRING'])) : $base_url . preg_replace($pattern, "", $_SERVER['QUERY_STRING']);
}

function unistripslashes($data)
{
    if (get_magic_quotes_gpc())
        return object_walk($data, 'stripslashes');
    return $data;
}

function fraction_to_min_sec($coord, $latitude = true)
{
    $isnorth = $coord >= 0;
    $coord = abs($coord);
    $deg = floor($coord);
    $coord = ($coord - $deg) * 60;
    $min = floor($coord);
    $sec = floor(($coord - $min) * 60);
    return sprintf("%d&deg;%d'%d\"%s", $deg, $min, $sec, $isnorth ? ($latitude ? 'N' : 'E') : ($latitude ? 'S' : 'W'));
}

function human_filesize($bytes, $decimals = 2)
{
    $size = array(
        'B',
        'kB',
        'MB',
        'GB',
        'TB',
        'PB',
        'EB',
        'ZB',
        'YB'
    );
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$size[$factor];
}

function preg_grep_keys($pattern, $input, $flags = 0)
{
    return array_intersect_key($input, array_flip(preg_grep($pattern, array_keys($input), $flags)));
}

function mydie($msg = '')
{
    echo $msg;
    $bt = debug_backtrace();
    $caller = array_shift($bt);
    echo "<br>" . $caller['line'] . ' @ ' . $caller['file'];
    die();
}

function mypassmarker($msg = '')
{
    echo $msg ? "<br>$msg" : '';
    $bt = debug_backtrace();
    $caller = array_shift($bt);
    echo "<br>" . $caller['line'] . ' @ ' . $caller['file'];
}

function is_not_null($val)
{
    return !is_null($val);
}

function array_diff_recursive($arr1, $arr2)
{
    $result = array();
    foreach ($arr1 as $key => $value) {
        if (!is_array($arr2) || !array_key_exists($key, $arr2)) {
            $result[$key] = $value;
            continue;
        }
        if (is_array($value)) {
            if (range(0, count($value) - 1) == array_keys($value)) {
                $diff = is_array($arr2[$key]) ? array_diff($value, $arr2[$key]) : $value;
                if ($diff) {
                    $result[$key] = array_values($diff);
                }
            } else {
                $recursiveArrayDiff = array_diff_recursive($value, $arr2[$key]);
                if (count($recursiveArrayDiff)) {
                    $result[$key] = $recursiveArrayDiff;
                }
            }
            continue;
        }
        if ($value != $arr2[$key]) {
            $result[$key] = $value;
        }
    }
    return $result;
}

function array_get_and_unset(&$array, $key, $default = null)
{
    $value = isset($array[$key]) ? $array[$key] : $default;
    unset($array[$key]);
    return $value;
}

function curlang()
{
    return \Ufw\Registry::getInstance()->get('lang');
}

// $swstats = [];
// register_shutdown_function(function() {
// echo array_sum($GLOBALS['swstats']), ', entry count: ', count($GLOBALS['swstats']);
// });
function TT($str)
{
    // global $swstats;
    // static $storage = null, $hash = [];
    
    // if (curlang() == 'sr-Latn') {
    // return $str;
    // }
    // if ($isScalar = is_scalar($str)) {
    // if (isset($hash[$str])) {
    // return $hash[$str];
    // }
    // } else {
    // if (!$storage) {
    // $storage = new \SplObjectStorage();
    // }
    // if ($storage->contains($str)) {
    // return $storage[$str];
    // }
    // }
    // error_log($str . "\n", 3, APPLICATION_PATH . '/log/translate.txt');
    
    // $sw = new \Ufw\Utils\Stopwatch();
    $out = _TT($str);
    
    // if ($isScalar) {
    // $hash[$str] = $out;
    // } else {
    // $storage[$str] = $out;
    // }
    
    // $swstats[] = $sw->elapsed();
    
    return $out;
}

function ttm($str)
{
    // global $swstats;
    static $storages = [], $hash = [];
    
    if ($isScalar = is_scalar($str)) {
        if (isset($hash[curlang()][$str])) {
            return $hash[curlang()][$str];
        }
    } else {
        if (!$storages) {
            $storages[curlang()] = new \SplObjectStorage();
        }
        if ($storages[curlang()]->contains($str)) {
            return $storages[curlang()][$str];
        }
    }
    // $sw = new \Ufw\Utils\Stopwatch();
    $out = _TT($str);
    if ($isScalar) {
        $hash[curlang()][$str] = $out;
    } else {
        $storages[curlang()][$str] = $out;
    }
    // $swstats[] = $sw->elapsed();
    return $out;
}

function _TT($str)
{
    if (is_scalar($str)) {
        return is_string($str) ? \Utils\Cyr2Lat::getInstance()->TT($str) : $str;
    } elseif (is_array($str) || (($str instanceof \ArrayAccess) && ($str instanceof \Traversable))) {
        foreach ($str as $key => $value) {
            $str[$key] = _TT($value);
        }
    } elseif ($str instanceof \Ufw\InfoHash) {
        foreach ($str as $key => $value) {
            $str->info[$key] = _TT($value);
        }
        $str->rehash();
    }
    return $str;
}