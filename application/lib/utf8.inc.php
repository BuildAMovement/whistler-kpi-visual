<?php
/**
 * UTF8 helper functions
 *
 * @license    LGPL 2.1 (http://www.gnu.org/copyleft/lesser.html)
 * @author     Andreas Gohr <andi@splitbrain.org>
 */

/**
 * check for mb_string support
 */
if (!defined('UTF8_MBSTRING')) {
    if (function_exists('mb_substr') && !defined('UTF8_NOMBSTRING')) {
        define('UTF8_MBSTRING', 1);
    } else {
        define('UTF8_MBSTRING', 0);
    }
}

/**
 * Check if PREG was compiled with UTF-8 support
 *
 * Without this many of the functions below will not work, so this is a minimal requirement
 */
if (!defined('UTF8_PREGSUPPORT')) {
    define('UTF8_PREGSUPPORT', (bool) @preg_match('/^.$/u', 'Ã±'));
}

/**
 * Check if PREG was compiled with Unicode Property support
 *
 * This is not required for the functions below, but might be needed in a UTF-8 aware application
 */
if (!defined('UTF8_PROPERTYSUPPORT')) {
    define('UTF8_PROPERTYSUPPORT', (bool) @preg_match('/^\pL$/u', 'Ã±'));
}

if (UTF8_MBSTRING) {
    mb_internal_encoding('UTF-8');
}

if (!function_exists('utf8_isASCII')) {

    /**
     * Checks if a string contains 7bit ASCII only
     *
     * @author Andreas Haerter <andreas.haerter@dev.mail-node.com>
     */
    function utf8_isASCII($str)
    {
        return (preg_match('/(?:[^\x00-\x7F])/', $str) !== 1);
    }
}

if (!function_exists('utf8_strip')) {

    /**
     * Strips all highbyte chars
     *
     * Returns a pure ASCII7 string
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    function utf8_strip($str)
    {
        $ascii = '';
        $len = strlen($str);
        for ($i = 0; $i < $len; $i++) {
            if (ord($str{$i}) < 128) {
                $ascii .= $str{$i};
            }
        }
        return $ascii;
    }
}

if (!function_exists('utf8_check')) {

    /**
     * Tries to detect if a string is in Unicode encoding
     *
     * @author <bmorel@ssi.fr>
     * @link http://www.php.net/manual/en/function.utf8-encode.php
     */
    function utf8_check($Str)
    {
        $len = strlen($Str);
        for ($i = 0; $i < $len; $i++) {
            $b = ord($Str[$i]);
            if ($b < 0x80)
                continue; // 0bbbbbbb
            elseif (($b & 0xE0) == 0xC0)
                $n = 1; // 110bbbbb
            elseif (($b & 0xF0) == 0xE0)
                $n = 2; // 1110bbbb
            elseif (($b & 0xF8) == 0xF0)
                $n = 3; // 11110bbb
            elseif (($b & 0xFC) == 0xF8)
                $n = 4; // 111110bb
            elseif (($b & 0xFE) == 0xFC)
                $n = 5; // 1111110b
            else
                return false; // Does not match any model
            
            for ($j = 0; $j < $n; $j++) { // n bytes matching 10bbbbbb follow ?
                if ((++$i == $len) || ((ord($Str[$i]) & 0xC0) != 0x80))
                    return false;
            }
        }
        return true;
    }
}

if (!function_exists('utf8_basename')) {

    /**
     * A locale independent basename() implementation
     *
     * works around a bug in PHP's basename() implementation
     *
     * @see basename()
     * @link https://bugs.php.net/bug.php?id=37738
     * @param string $path
     *            A path
     * @param string $suffix
     *            If the name component ends in suffix this will also be cut off
     * @return string
     */
    function utf8_basename($path, $suffix = '')
    {
        $path = trim($path, '\\/');
        $rpos = max(strrpos($path, '/'), strrpos($path, '\\'));
        if ($rpos)
            $path = substr($path, $rpos + 1);
        
        $suflen = strlen($suffix);
        if ($suflen && (substr($path, -$suflen) == $suffix)) {
            $path = substr($path, 0, -$suflen);
        }
        
        return $path;
    }
}

if (!function_exists('utf8_strlen')) {

    /**
     * Unicode aware replacement for strlen()
     *
     * utf8_decode() converts characters that are not in ISO-8859-1
     * to '?', which, for the purpose of counting, is alright - It's
     * even faster than mb_strlen.
     *
     * @author <chernyshevsky at hotmail dot com>
     * @see strlen()
     * @see utf8_decode()
     */
    function utf8_strlen($string)
    {
        return strlen(utf8_decode($string));
    }
}

if (!function_exists('utf8_substr')) {

    /**
     * UTF-8 aware alternative to substr
     *
     * Return part of a string given character offset (and optionally length)
     *
     * @author Harry Fuecks <hfuecks@gmail.com>
     * @author Chris Smith <chris@jalakai.co.uk>
     * @param string $str            
     * @param int $offset
     *            number of UTF-8 characters offset (from left)
     * @param int $length
     *            (optional) length in UTF-8 characters from offset
     * @return mixed string or false if failure
     */
    function utf8_substr($str, $offset, $length = null)
    {
        if (UTF8_MBSTRING) {
            if ($length === null) {
                return mb_substr($str, $offset);
            } else {
                return mb_substr($str, $offset, $length);
            }
        }
        
        /*
         * Notes:
         *
         * no mb string support, so we'll use pcre regex's with 'u' flag
         * pcre only supports repetitions of less than 65536, in order to accept up to MAXINT values for
         * offset and length, we'll repeat a group of 65535 characters when needed (ok, up to MAXINT-65536)
         *
         * substr documentation states false can be returned in some cases (e.g. offset > string length)
         * mb_substr never returns false, it will return an empty string instead.
         *
         * calculating the number of characters in the string is a relatively expensive operation, so
         * we only carry it out when necessary. It isn't necessary for +ve offsets and no specified length
         */
        
        // cast parameters to appropriate types to avoid multiple notices/warnings
        $str = (string) $str; // generates E_NOTICE for PHP4 objects, but not PHP5 objects
        $offset = (int) $offset;
        if (!is_null($length))
            $length = (int) $length;
        
        // handle trivial cases
        if ($length === 0)
            return '';
        if ($offset < 0 && $length < 0 && $length < $offset)
            return '';
        
        $offset_pattern = '';
        $length_pattern = '';
        
        // normalise -ve offsets (we could use a tail anchored pattern, but they are horribly slow!)
        if ($offset < 0) {
            $strlen = strlen(utf8_decode($str)); // see notes
            $offset = $strlen + $offset;
            if ($offset < 0)
                $offset = 0;
        }
        
        // establish a pattern for offset, a non-captured group equal in length to offset
        if ($offset > 0) {
            $Ox = (int) ($offset / 65535);
            $Oy = $offset % 65535;
            
            if ($Ox)
                $offset_pattern = '(?:.{65535}){' . $Ox . '}';
            $offset_pattern = '^(?:' . $offset_pattern . '.{' . $Oy . '})';
        } else {
            $offset_pattern = '^'; // offset == 0; just anchor the pattern
        }
        
        // establish a pattern for length
        if (is_null($length)) {
            $length_pattern = '(.*)$'; // the rest of the string
        } else {
            
            if (!isset($strlen))
                $strlen = strlen(utf8_decode($str)); // see notes
            if ($offset > $strlen)
                return ''; // another trivial case
            
            if ($length > 0) {
                
                $length = min($strlen - $offset, $length); // reduce any length that would go passed the end of the string
                
                $Lx = (int) ($length / 65535);
                $Ly = $length % 65535;
                
                // +ve length requires ... a captured group of length characters
                if ($Lx)
                    $length_pattern = '(?:.{65535}){' . $Lx . '}';
                $length_pattern = '(' . $length_pattern . '.{' . $Ly . '})';
            } else if ($length < 0) {
                
                if ($length < ($offset - $strlen))
                    return '';
                
                $Lx = (int) ((-$length) / 65535);
                $Ly = (-$length) % 65535;
                
                // -ve length requires ... capture everything except a group of -length characters
                // anchored at the tail-end of the string
                if ($Lx)
                    $length_pattern = '(?:.{65535}){' . $Lx . '}';
                $length_pattern = '(.*)(?:' . $length_pattern . '.{' . $Ly . '})$';
            }
        }
        
        $match = [];
        if (!preg_match('#' . $offset_pattern . $length_pattern . '#us', $str, $match))
            return '';
        return $match[1];
    }
}

if (!function_exists('utf8_substr_replace')) {

    /**
     * Unicode aware replacement for substr_replace()
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     * @see substr_replace()
     */
    function utf8_substr_replace($string, $replacement, $start, $length = 0)
    {
        $ret = '';
        if ($start > 0)
            $ret .= utf8_substr($string, 0, $start);
        $ret .= $replacement;
        $ret .= utf8_substr($string, $start + $length);
        return $ret;
    }
}

if (!function_exists('utf8_ltrim')) {

    /**
     * Unicode aware replacement for ltrim()
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     * @see ltrim()
     * @param string $str            
     * @param string $charlist            
     * @return string
     */
    function utf8_ltrim($str, $charlist = '')
    {
        if ($charlist == '')
            return ltrim($str);
        
        // quote charlist for use in a characterclass
        $charlist = preg_replace('!([\\\\\\-\\]\\[/])!', '\\\${1}', $charlist);
        
        return preg_replace('/^[' . $charlist . ']+/u', '', $str);
    }
}

if (!function_exists('utf8_rtrim')) {

    /**
     * Unicode aware replacement for rtrim()
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     * @see rtrim()
     * @param string $str            
     * @param string $charlist            
     * @return string
     */
    function utf8_rtrim($str, $charlist = '')
    {
        if ($charlist == '')
            return rtrim($str);
        
        // quote charlist for use in a characterclass
        $charlist = preg_replace('!([\\\\\\-\\]\\[/])!', '\\\${1}', $charlist);
        
        return preg_replace('/[' . $charlist . ']+$/u', '', $str);
    }
}

if (!function_exists('utf8_trim')) {

    /**
     * Unicode aware replacement for trim()
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     * @see trim()
     * @param string $str            
     * @param string $charlist            
     * @return string
     */
    function utf8_trim($str, $charlist = '')
    {
        if ($charlist == '')
            return trim($str);
        
        return utf8_ltrim(utf8_rtrim($str, $charlist), $charlist);
    }
}

if (!function_exists('utf8_strtolower')) {

    /**
     * This is a unicode aware replacement for strtolower()
     *
     * Uses mb_string extension if available
     *
     * @author Leo Feyer <leo@typolight.org>
     * @see strtolower()
     * @see utf8_strtoupper()
     */
    function utf8_strtolower($string)
    {
        if (UTF8_MBSTRING)
            return mb_strtolower($string, 'utf-8');
        
        global $UTF8_UPPER_TO_LOWER;
        return strtr($string, $UTF8_UPPER_TO_LOWER);
    }
}

if (!function_exists('utf8_strtoupper')) {

    /**
     * This is a unicode aware replacement for strtoupper()
     *
     * Uses mb_string extension if available
     *
     * @author Leo Feyer <leo@typolight.org>
     * @see strtoupper()
     * @see utf8_strtoupper()
     */
    function utf8_strtoupper($string)
    {
        if (UTF8_MBSTRING)
            return mb_strtoupper($string, 'utf-8');
        
        global $UTF8_LOWER_TO_UPPER;
        return strtr($string, $UTF8_LOWER_TO_UPPER);
    }
}

if (!function_exists('utf8_ucfirst')) {

    /**
     * UTF-8 aware alternative to ucfirst
     * Make a string's first character uppercase
     *
     * @author Harry Fuecks
     * @param
     *            string
     * @return string with first character as upper case (if applicable)
     */
    function utf8_ucfirst($str)
    {
        switch (utf8_strlen($str)) {
            case 0:
                return '';
            case 1:
                return utf8_strtoupper($str);
            default:
                $matches = [];
                preg_match('/^(.{1})(.*)$/us', $str, $matches);
                return utf8_strtoupper($matches[1]) . $matches[2];
        }
    }
}

if (!function_exists('utf8_ucwords')) {

    /**
     * UTF-8 aware alternative to ucwords
     * Uppercase the first character of each word in a string
     *
     * @author Harry Fuecks
     * @param
     *            string
     * @return string with first char of each word uppercase
     * @see http://www.php.net/ucwords
     */
    function utf8_ucwords($str)
    {
        // Note: [\x0c\x09\x0b\x0a\x0d\x20] matches;
        // form feeds, horizontal tabs, vertical tabs, linefeeds and carriage returns
        // This corresponds to the definition of a "word" defined at http://www.php.net/ucwords
        $pattern = '/(^|([\x0c\x09\x0b\x0a\x0d\x20]+))([^\x0c\x09\x0b\x0a\x0d\x20]{1})[^\x0c\x09\x0b\x0a\x0d\x20]*/u';
        
        return preg_replace_callback($pattern, 'utf8_ucwords_callback', $str);
    }

    /**
     * Callback function for preg_replace_callback call in utf8_ucwords
     * You don't need to call this yourself
     *
     * @author Harry Fuecks
     * @param array $matches
     *            matches corresponding to a single word
     * @return string with first char of the word in uppercase
     * @see utf8_ucwords
     * @see utf8_strtoupper
     */
    function utf8_ucwords_callback($matches)
    {
        $leadingws = $matches[2];
        $ucfirst = utf8_strtoupper($matches[3]);
        $ucword = utf8_substr_replace(ltrim($matches[0]), $ucfirst, 0, 1);
        return $leadingws . $ucword;
    }
}

if (!function_exists('utf8_deaccent')) {

    /**
     * Replace accented UTF-8 characters by unaccented ASCII-7 equivalents
     *
     * Use the optional parameter to just deaccent lower ($case = -1) or upper ($case = 1)
     * letters. Default is to deaccent both cases ($case = 0)
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    function utf8_deaccent($string, $case = 0)
    {
        if ($case <= 0) {
            global $UTF8_LOWER_ACCENTS;
            $string = strtr($string, $UTF8_LOWER_ACCENTS);
        }
        if ($case >= 0) {
            global $UTF8_UPPER_ACCENTS;
            $string = strtr($string, $UTF8_UPPER_ACCENTS);
        }
        return $string;
    }
}

if (!function_exists('utf8_romanize')) {

    /**
     * Romanize a non-latin string
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    function utf8_romanize($string)
    {
        if (utf8_isASCII($string))
            return $string; // nothing to do
        
        global $UTF8_ROMANIZATION;
        return strtr($string, $UTF8_ROMANIZATION);
    }
}

if (!function_exists('utf8_stripspecials')) {

    /**
     * Removes special characters (nonalphanumeric) from a UTF-8 string
     *
     * This function adds the controlchars 0x00 to 0x19 to the array of
     * stripped chars (they are not included in $UTF8_SPECIAL_CHARS)
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     * @param string $string
     *            The UTF8 string to strip of special chars
     * @param string $repl
     *            Replace special with this string
     * @param string $additional
     *            Additional chars to strip (used in regexp char class)
     * @return string
     */
    function utf8_stripspecials($string, $repl = '', $additional = '')
    {
        global $UTF8_SPECIAL_CHARS2;
        
        static $specials = null;
        if (is_null($specials)) {
            // $specials = preg_quote(unicode_to_utf8($UTF8_SPECIAL_CHARS), '/');
            $specials = preg_quote($UTF8_SPECIAL_CHARS2, '/');
        }
        
        return preg_replace('/[' . $additional . '\x00-\x19' . $specials . ']/u', $repl, $string);
    }
}

if (!function_exists('utf8_strpos')) {

    /**
     * This is an Unicode aware replacement for strpos
     *
     * @author Leo Feyer <leo@typolight.org>
     * @see strpos()
     * @param
     *            string
     * @param
     *            string
     * @param
     *            integer
     * @return integer
     */
    function utf8_strpos($haystack, $needle, $offset = 0)
    {
        $comp = 0;
        $length = null;
        
        while (is_null($length) || $length < $offset) {
            $pos = strpos($haystack, $needle, $offset + $comp);
            
            if ($pos === false)
                return false;
            
            $length = utf8_strlen(substr($haystack, 0, $pos));
            
            if ($length < $offset)
                $comp = $pos - $length;
        }
        
        return $length;
    }
}

if (!function_exists('utf8_tohtml')) {

    /**
     * Encodes UTF-8 characters to HTML entities
     *
     * @author Tom N Harris <tnharris@whoopdedo.org>
     * @author <vpribish at shopping dot com>
     * @link http://www.php.net/manual/en/function.utf8-decode.php
     */
    function utf8_tohtml($str)
    {
        $ret = '';
        foreach (utf8_to_unicode($str) as $cp) {
            if ($cp < 0x80)
                $ret .= chr($cp);
            elseif ($cp < 0x100)
                $ret .= "&#$cp;";
            else
                $ret .= '&#x' . dechex($cp) . ';';
        }
        return $ret;
    }
}

if (!function_exists('utf8_unhtml')) {

    /**
     * Decodes HTML entities to UTF-8 characters
     *
     * Convert any &#..; entity to a codepoint,
     * The entities flag defaults to only decoding numeric entities.
     * Pass HTML_ENTITIES and named entities, including &amp; &lt; etc.
     * are handled as well. Avoids the problem that would occur if you
     * had to decode "&amp;#38;&#38;amp;#38;"
     *
     * unhtmlspecialchars(utf8_unhtml($s)) -> "&#38;&#38;"
     * utf8_unhtml(unhtmlspecialchars($s)) -> "&&amp#38;"
     * what it should be -> "&#38;&amp#38;"
     *
     * @author Tom N Harris <tnharris@whoopdedo.org>
     * @param string $str
     *            UTF-8 encoded string
     * @param boolean $entities
     *            Flag controlling decoding of named entities.
     * @return string UTF-8 encoded string with numeric (and named) entities replaced.
     */
    function utf8_unhtml($str, $entities = null)
    {
        static $decoder = null;
        if (is_null($decoder))
            $decoder = new utf8_entity_decoder();
        if (is_null($entities))
            return preg_replace_callback('/(&#([Xx])?([0-9A-Za-z]+);)/m', 'utf8_decode_numeric', $str);
        else
            return preg_replace_callback('/&(#)?([Xx])?([0-9A-Za-z]+);/m', array(
                &$decoder,
                'decode'
            ), $str);
    }
}

if (!function_exists('utf8_decode_numeric')) {

    /**
     * Decodes numeric HTML entities to their correct UTF-8 characters
     *
     * @param $ent string
     *            A numeric entity
     * @return string
     */
    function utf8_decode_numeric($ent)
    {
        switch ($ent[2]) {
            case 'X':
            case 'x':
                $cp = hexdec($ent[3]);
                break;
            default:
                $cp = intval($ent[3]);
                break;
        }
        return unicode_to_utf8(array(
            $cp
        ));
    }
}

if (!class_exists('utf8_entity_decoder')) {

    /**
     * Encapsulate HTML entity decoding tables
     */
    class utf8_entity_decoder
    {

        var $table;

        /**
         * Initializes the decoding tables
         */
        function __construct()
        {
            $table = get_html_translation_table(HTML_ENTITIES);
            $table = array_flip($table);
            $this->table = array_map(array(
                &$this,
                'makeutf8'
            ), $table);
        }

        /**
         * Wrapper aorund unicode_to_utf8()
         *
         * @param $c string            
         * @return mixed
         */
        function makeutf8($c)
        {
            return unicode_to_utf8(array(
                ord($c)
            ));
        }

        /**
         * Decodes any HTML entity to it's correct UTF-8 char equivalent
         *
         * @param $ent string
         *            An entity
         * @return string
         */
        function decode($ent)
        {
            if ($ent[1] == '#') {
                return utf8_decode_numeric($ent);
            } elseif (array_key_exists($ent[0], $this->table)) {
                return $this->table[$ent[0]];
            } else {
                return $ent[0];
            }
        }
    }
}

if (!function_exists('utf8_to_unicode')) {

    /**
     * Takes an UTF-8 string and returns an array of ints representing the
     * Unicode characters.
     * Astral planes are supported ie. the ints in the
     * output can be > 0xFFFF. Occurrances of the BOM are ignored. Surrogates
     * are not allowed.
     *
     * If $strict is set to true the function returns false if the input
     * string isn't a valid UTF-8 octet sequence and raises a PHP error at
     * level E_USER_WARNING
     *
     * Note: this function has been modified slightly in this library to
     * trigger errors on encountering bad bytes
     *
     * @author <hsivonen@iki.fi>
     * @author Harry Fuecks <hfuecks@gmail.com>
     * @param string $str
     *            UTF-8 encoded string
     * @param boolean $strict
     *            Check for invalid sequences?
     * @return mixed array of unicode code points or false if UTF-8 invalid
     * @see unicode_to_utf8
     * @link http://hsivonen.iki.fi/php-utf8/
     * @link http://sourceforge.net/projects/phputf8/
     */
    function utf8_to_unicode($str, $strict = false)
    {
        $mState = 0; // cached expected number of octets after the current octet
                     // until the beginning of the next UTF8 character sequence
        $mUcs4 = 0; // cached Unicode character
        $mBytes = 1; // cached expected number of octets in the current sequence
        
        $out = array();
        
        $len = strlen($str);
        
        for ($i = 0; $i < $len; $i++) {
            
            $in = ord($str{$i});
            
            if ($mState == 0) {
                
                // When mState is zero we expect either a US-ASCII character or a
                // multi-octet sequence.
                if (0 == (0x80 & ($in))) {
                    // US-ASCII, pass straight through.
                    $out[] = $in;
                    $mBytes = 1;
                } else if (0xC0 == (0xE0 & ($in))) {
                    // First octet of 2 octet sequence
                    $mUcs4 = ($in);
                    $mUcs4 = ($mUcs4 & 0x1F) << 6;
                    $mState = 1;
                    $mBytes = 2;
                } else if (0xE0 == (0xF0 & ($in))) {
                    // First octet of 3 octet sequence
                    $mUcs4 = ($in);
                    $mUcs4 = ($mUcs4 & 0x0F) << 12;
                    $mState = 2;
                    $mBytes = 3;
                } else if (0xF0 == (0xF8 & ($in))) {
                    // First octet of 4 octet sequence
                    $mUcs4 = ($in);
                    $mUcs4 = ($mUcs4 & 0x07) << 18;
                    $mState = 3;
                    $mBytes = 4;
                } else if (0xF8 == (0xFC & ($in))) {
                    /*
                     * First octet of 5 octet sequence.
                     *
                     * This is illegal because the encoded codepoint must be either
                     * (a) not the shortest form or
                     * (b) outside the Unicode range of 0-0x10FFFF.
                     * Rather than trying to resynchronize, we will carry on until the end
                     * of the sequence and let the later error handling code catch it.
                     */
                    $mUcs4 = ($in);
                    $mUcs4 = ($mUcs4 & 0x03) << 24;
                    $mState = 4;
                    $mBytes = 5;
                } else if (0xFC == (0xFE & ($in))) {
                    // First octet of 6 octet sequence, see comments for 5 octet sequence.
                    $mUcs4 = ($in);
                    $mUcs4 = ($mUcs4 & 1) << 30;
                    $mState = 5;
                    $mBytes = 6;
                } elseif ($strict) {
                    /*
                     * Current octet is neither in the US-ASCII range nor a legal first
                     * octet of a multi-octet sequence.
                     */
                    trigger_error('utf8_to_unicode: Illegal sequence identifier ' . 'in UTF-8 at byte ' . $i, E_USER_WARNING);
                    return false;
                }
            } else {
                
                // When mState is non-zero, we expect a continuation of the multi-octet
                // sequence
                if (0x80 == (0xC0 & ($in))) {
                    
                    // Legal continuation.
                    $shift = ($mState - 1) * 6;
                    $tmp = $in;
                    $tmp = ($tmp & 0x0000003F) << $shift;
                    $mUcs4 |= $tmp;
                    
                    /**
                     * End of the multi-octet sequence.
                     * mUcs4 now contains the final
                     * Unicode codepoint to be output
                     */
                    if (0 == --$mState) {
                        
                        /*
                         * Check for illegal sequences and codepoints.
                         */
                        // From Unicode 3.1, non-shortest form is illegal
                        if (((2 == $mBytes) && ($mUcs4 < 0x0080)) || ((3 == $mBytes) && ($mUcs4 < 0x0800)) || ((4 == $mBytes) && ($mUcs4 < 0x10000)) || (4 < $mBytes) || 
                        // From Unicode 3.2, surrogate characters are illegal
                        (($mUcs4 & 0xFFFFF800) == 0xD800) || 
                        // Codepoints outside the Unicode range are illegal
                        ($mUcs4 > 0x10FFFF)) {
                            
                            if ($strict) {
                                trigger_error('utf8_to_unicode: Illegal sequence or codepoint ' . 'in UTF-8 at byte ' . $i, E_USER_WARNING);
                                
                                return false;
                            }
                        }
                        
                        if (0xFEFF != $mUcs4) {
                            // BOM is legal but we don't want to output it
                            $out[] = $mUcs4;
                        }
                        
                        // initialize UTF8 cache
                        $mState = 0;
                        $mUcs4 = 0;
                        $mBytes = 1;
                    }
                } elseif ($strict) {
                    /**
                     * ((0xC0 & (*in) != 0x80) && (mState != 0))
                     * Incomplete multi-octet sequence.
                     */
                    trigger_error('utf8_to_unicode: Incomplete multi-octet ' . '   sequence in UTF-8 at byte ' . $i, E_USER_WARNING);
                    
                    return false;
                }
            }
        }
        return $out;
    }
}

if (!function_exists('unicode_to_utf8')) {

    /**
     * Takes an array of ints representing the Unicode characters and returns
     * a UTF-8 string.
     * Astral planes are supported ie. the ints in the
     * input can be > 0xFFFF. Occurrances of the BOM are ignored. Surrogates
     * are not allowed.
     *
     * If $strict is set to true the function returns false if the input
     * array contains ints that represent surrogates or are outside the
     * Unicode range and raises a PHP error at level E_USER_WARNING
     *
     * Note: this function has been modified slightly in this library to use
     * output buffering to concatenate the UTF-8 string (faster) as well as
     * reference the array by it's keys
     *
     * @param array $arr
     *            of unicode code points representing a string
     * @param boolean $strict
     *            Check for invalid sequences?
     * @return mixed UTF-8 string or false if array contains invalid code points
     * @author <hsivonen@iki.fi>
     * @author Harry Fuecks <hfuecks@gmail.com>
     * @see utf8_to_unicode
     * @link http://hsivonen.iki.fi/php-utf8/
     * @link http://sourceforge.net/projects/phputf8/
     */
    function unicode_to_utf8($arr, $strict = false)
    {
        if (!is_array($arr))
            return '';
        ob_start();
        
        foreach (array_keys($arr) as $k) {
            
            if (($arr[$k] >= 0) && ($arr[$k] <= 0x007f)) {
                // ASCII range (including control chars)
                
                echo chr($arr[$k]);
            } else if ($arr[$k] <= 0x07ff) {
                // 2 byte sequence
                
                echo chr(0xc0 | ($arr[$k] >> 6));
                echo chr(0x80 | ($arr[$k] & 0x003f));
            } else if ($arr[$k] == 0xFEFF) {
                // Byte order mark (skip)
                
                // nop -- zap the BOM
            } else if ($arr[$k] >= 0xD800 && $arr[$k] <= 0xDFFF) {
                // Test for illegal surrogates
                
                // found a surrogate
                if ($strict) {
                    trigger_error('unicode_to_utf8: Illegal surrogate ' . 'at index: ' . $k . ', value: ' . $arr[$k], E_USER_WARNING);
                    return false;
                }
            } else if ($arr[$k] <= 0xffff) {
                // 3 byte sequence
                
                echo chr(0xe0 | ($arr[$k] >> 12));
                echo chr(0x80 | (($arr[$k] >> 6) & 0x003f));
                echo chr(0x80 | ($arr[$k] & 0x003f));
            } else if ($arr[$k] <= 0x10ffff) {
                // 4 byte sequence
                
                echo chr(0xf0 | ($arr[$k] >> 18));
                echo chr(0x80 | (($arr[$k] >> 12) & 0x3f));
                echo chr(0x80 | (($arr[$k] >> 6) & 0x3f));
                echo chr(0x80 | ($arr[$k] & 0x3f));
            } elseif ($strict) {
                
                trigger_error('unicode_to_utf8: Codepoint out of Unicode range ' . 'at index: ' . $k . ', value: ' . $arr[$k], E_USER_WARNING);
                
                // out of range
                return false;
            }
        }
        
        $result = ob_get_contents();
        ob_end_clean();
        return $result;
    }
}

if (!function_exists('utf8_to_utf16be')) {

    /**
     * UTF-8 to UTF-16BE conversion.
     *
     * Maybe really UCS-2 without mb_string due to utf8_to_unicode limits
     */
    function utf8_to_utf16be(&$str, $bom = false)
    {
        $out = $bom ? "\xFE\xFF" : '';
        if (UTF8_MBSTRING)
            return $out . mb_convert_encoding($str, 'UTF-16BE', 'UTF-8');
        
        $uni = utf8_to_unicode($str);
        foreach ($uni as $cp) {
            $out .= pack('n', $cp);
        }
        return $out;
    }
}

if (!function_exists('utf16be_to_utf8')) {

    /**
     * UTF-8 to UTF-16BE conversion.
     *
     * Maybe really UCS-2 without mb_string due to utf8_to_unicode limits
     */
    function utf16be_to_utf8(&$str)
    {
        $uni = unpack('n*', $str);
        return unicode_to_utf8($uni);
    }
}

if (!function_exists('utf8_bad_replace')) {

    /**
     * Replace bad bytes with an alternative character
     *
     * ASCII character is recommended for replacement char
     *
     * PCRE Pattern to locate bad bytes in a UTF-8 string
     * Comes from W3 FAQ: Multilingual Forms
     * Note: modified to include full ASCII range including control chars
     *
     * @author Harry Fuecks <hfuecks@gmail.com>
     * @see http://www.w3.org/International/questions/qa-forms-utf-8
     * @param string $str
     *            to search
     * @param string $replace
     *            to replace bad bytes with (defaults to '?') - use ASCII
     * @return string
     */
    function utf8_bad_replace($str, $replace = '')
    {
        $UTF8_BAD = '([\x00-\x7F]' . // ASCII (including control chars)
'|[\xC2-\xDF][\x80-\xBF]' . // non-overlong 2-byte
'|\xE0[\xA0-\xBF][\x80-\xBF]' . // excluding overlongs
'|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}' . // straight 3-byte
'|\xED[\x80-\x9F][\x80-\xBF]' . // excluding surrogates
'|\xF0[\x90-\xBF][\x80-\xBF]{2}' . // planes 1-3
'|[\xF1-\xF3][\x80-\xBF]{3}' . // planes 4-15
'|\xF4[\x80-\x8F][\x80-\xBF]{2}' . // plane 16
'|(.{1}))'; // invalid byte
        ob_start();
        $matches = [];
        while (preg_match('/' . $UTF8_BAD . '/S', $str, $matches)) {
            if (!isset($matches[2])) {
                echo $matches[0];
            } else {
                echo $replace;
            }
            $str = substr($str, strlen($matches[0]));
        }
        $result = ob_get_contents();
        ob_end_clean();
        return $result;
    }
}

if (!function_exists('utf8_correctIdx')) {

    /**
     * adjust a byte index into a utf8 string to a utf8 character boundary
     *
     * @param $str string
     *            utf8 character string
     * @param $i int
     *            byte index into $str
     * @param $next bool
     *            direction to search for boundary,
     *            false = up (current character)
     *            true = down (next character)
     *            
     * @return int byte index into $str now pointing to a utf8 character boundary
     *        
     * @author chris smith <chris@jalakai.co.uk>
     */
    function utf8_correctIdx(&$str, $i, $next = false)
    {
        if ($i <= 0)
            return 0;
        
        $limit = strlen($str);
        if ($i >= $limit)
            return $limit;
        
        if ($next) {
            while (($i < $limit) && ((ord($str[$i]) & 0xC0) == 0x80))
                $i++;
        } else {
            while ($i && ((ord($str[$i]) & 0xC0) == 0x80))
                $i--;
        }
        
        return $i;
    }
}

// only needed if no mb_string available
if (!UTF8_MBSTRING) {
    /**
     * UTF-8 Case lookup table
     *
     * This lookuptable defines the upper case letters to their correspponding
     * lower case letter in UTF-8
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    global $UTF8_LOWER_TO_UPPER;
    if (empty($UTF8_LOWER_TO_UPPER))
        $UTF8_LOWER_TO_UPPER = array(
            "ï½š" => "ï¼º",
            "ï½™" => "ï¼¹",
            "ï½˜" => "ï¼¸",
            "ï½—" => "ï¼·",
            "ï½–" => "ï¼¶",
            "ï½•" => "ï¼µ",
            "ï½”" => "ï¼´",
            "ï½“" => "ï¼³",
            "ï½’" => "ï¼²",
            "ï½‘" => "ï¼±",
            "ï½" => "ï¼°",
            "ï½" => "ï¼¯",
            "ï½Ž" => "ï¼®",
            "ï½" => "ï¼­",
            "ï½Œ" => "ï¼¬",
            "ï½‹" => "ï¼«",
            "ï½Š" => "ï¼ª",
            "ï½‰" => "ï¼©",
            "ï½ˆ" => "ï¼¨",
            "ï½‡" => "ï¼§",
            "ï½†" => "ï¼¦",
            "ï½…" => "ï¼¥",
            "ï½„" => "ï¼¤",
            "ï½ƒ" => "ï¼£",
            "ï½‚" => "ï¼¢",
            "ï½" => "ï¼¡",
            "á¿³" => "á¿¼",
            "á¿¥" => "á¿¬",
            "á¿¡" => "á¿©",
            "á¿‘" => "á¿™",
            "á¿" => "á¿˜",
            "á¿ƒ" => "á¿Œ",
            "á¾¾" => "Î™",
            "á¾³" => "á¾¼",
            "á¾±" => "á¾¹",
            "á¾°" => "á¾¸",
            "á¾§" => "á¾¯",
            "á¾¦" => "á¾®",
            "á¾¥" => "á¾­",
            "á¾¤" => "á¾¬",
            "á¾£" => "á¾«",
            "á¾¢" => "á¾ª",
            "á¾¡" => "á¾©",
            "á¾—" => "á¾Ÿ",
            "á¾–" => "á¾ž",
            "á¾•" => "á¾",
            "á¾”" => "á¾œ",
            "á¾“" => "á¾›",
            "á¾’" => "á¾š",
            "á¾‘" => "á¾™",
            "á¾" => "á¾˜",
            "á¾‡" => "á¾",
            "á¾†" => "á¾Ž",
            "á¾…" => "á¾",
            "á¾„" => "á¾Œ",
            "á¾ƒ" => "á¾‹",
            "á¾‚" => "á¾Š",
            "á¾" => "á¾‰",
            "á¾€" => "á¾ˆ",
            "á½½" => "á¿»",
            "á½¼" => "á¿º",
            "á½»" => "á¿«",
            "á½º" => "á¿ª",
            "á½¹" => "á¿¹",
            "á½¸" => "á¿¸",
            "á½·" => "á¿›",
            "á½¶" => "á¿š",
            "á½µ" => "á¿‹",
            "á½´" => "á¿Š",
            "á½³" => "á¿‰",
            "á½²" => "á¿ˆ",
            "á½±" => "á¾»",
            "á½°" => "á¾º",
            "á½§" => "á½¯",
            "á½¦" => "á½®",
            "á½¥" => "á½­",
            "á½¤" => "á½¬",
            "á½£" => "á½«",
            "á½¢" => "á½ª",
            "á½¡" => "á½©",
            "á½—" => "á½Ÿ",
            "á½•" => "á½",
            "á½“" => "á½›",
            "á½‘" => "á½™",
            "á½…" => "á½",
            "á½„" => "á½Œ",
            "á½ƒ" => "á½‹",
            "á½‚" => "á½Š",
            "á½" => "á½‰",
            "á½€" => "á½ˆ",
            "á¼·" => "á¼¿",
            "á¼¶" => "á¼¾",
            "á¼µ" => "á¼½",
            "á¼´" => "á¼¼",
            "á¼³" => "á¼»",
            "á¼²" => "á¼º",
            "á¼±" => "á¼¹",
            "á¼°" => "á¼¸",
            "á¼§" => "á¼¯",
            "á¼¦" => "á¼®",
            "á¼¥" => "á¼­",
            "á¼¤" => "á¼¬",
            "á¼£" => "á¼«",
            "á¼¢" => "á¼ª",
            "á¼¡" => "á¼©",
            "á¼•" => "á¼",
            "á¼”" => "á¼œ",
            "á¼“" => "á¼›",
            "á¼’" => "á¼š",
            "á¼‘" => "á¼™",
            "á¼" => "á¼˜",
            "á¼‡" => "á¼",
            "á¼†" => "á¼Ž",
            "á¼…" => "á¼",
            "á¼„" => "á¼Œ",
            "á¼ƒ" => "á¼‹",
            "á¼‚" => "á¼Š",
            "á¼" => "á¼‰",
            "á¼€" => "á¼ˆ",
            "á»¹" => "á»¸",
            "á»·" => "á»¶",
            "á»µ" => "á»´",
            "á»³" => "á»²",
            "á»±" => "á»°",
            "á»¯" => "á»®",
            "á»­" => "á»¬",
            "á»«" => "á»ª",
            "á»©" => "á»¨",
            "á»§" => "á»¦",
            "á»¥" => "á»¤",
            "á»£" => "á»¢",
            "á»¡" => "á» ",
            "á»Ÿ" => "á»ž",
            "á»" => "á»œ",
            "á»›" => "á»š",
            "á»™" => "á»˜",
            "á»—" => "á»–",
            "á»•" => "á»”",
            "á»“" => "á»’",
            "á»‘" => "á»",
            "á»" => "á»Ž",
            "á»" => "á»Œ",
            "á»‹" => "á»Š",
            "á»‰" => "á»ˆ",
            "á»‡" => "á»†",
            "á»…" => "á»„",
            "á»ƒ" => "á»‚",
            "á»" => "á»€",
            "áº¿" => "áº¾",
            "áº½" => "áº¼",
            "áº»" => "áºº",
            "áº¹" => "áº¸",
            "áº·" => "áº¶",
            "áºµ" => "áº´",
            "áº³" => "áº²",
            "áº±" => "áº°",
            "áº¯" => "áº®",
            "áº­" => "áº¬",
            "áº«" => "áºª",
            "áº©" => "áº¨",
            "áº§" => "áº¦",
            "áº¥" => "áº¤",
            "áº£" => "áº¢",
            "áº¡" => "áº ",
            "áº›" => "á¹ ",
            "áº•" => "áº”",
            "áº“" => "áº’",
            "áº‘" => "áº",
            "áº" => "áºŽ",
            "áº" => "áºŒ",
            "áº‹" => "áºŠ",
            "áº‰" => "áºˆ",
            "áº‡" => "áº†",
            "áº…" => "áº„",
            "áºƒ" => "áº‚",
            "áº" => "áº€",
            "á¹¿" => "á¹¾",
            "á¹½" => "á¹¼",
            "á¹»" => "á¹º",
            "á¹¹" => "á¹¸",
            "á¹·" => "á¹¶",
            "á¹µ" => "á¹´",
            "á¹³" => "á¹²",
            "á¹±" => "á¹°",
            "á¹¯" => "á¹®",
            "á¹­" => "á¹¬",
            "á¹«" => "á¹ª",
            "á¹©" => "á¹¨",
            "á¹§" => "á¹¦",
            "á¹¥" => "á¹¤",
            "á¹£" => "á¹¢",
            "á¹¡" => "á¹ ",
            "á¹Ÿ" => "á¹ž",
            "á¹" => "á¹œ",
            "á¹›" => "á¹š",
            "á¹™" => "á¹˜",
            "á¹—" => "á¹–",
            "á¹•" => "á¹”",
            "á¹“" => "á¹’",
            "á¹‘" => "á¹",
            "á¹" => "á¹Ž",
            "á¹" => "á¹Œ",
            "á¹‹" => "á¹Š",
            "á¹‰" => "á¹ˆ",
            "á¹‡" => "á¹†",
            "á¹…" => "á¹„",
            "á¹ƒ" => "á¹‚",
            "á¹" => "á¹€",
            "á¸¿" => "á¸¾",
            "á¸½" => "á¸¼",
            "á¸»" => "á¸º",
            "á¸¹" => "á¸¸",
            "á¸·" => "á¸¶",
            "á¸µ" => "á¸´",
            "á¸³" => "á¸²",
            "á¸±" => "á¸°",
            "á¸¯" => "á¸®",
            "á¸­" => "á¸¬",
            "á¸«" => "á¸ª",
            "á¸©" => "á¸¨",
            "á¸§" => "á¸¦",
            "á¸¥" => "á¸¤",
            "á¸£" => "á¸¢",
            "á¸¡" => "á¸ ",
            "á¸Ÿ" => "á¸ž",
            "á¸" => "á¸œ",
            "á¸›" => "á¸š",
            "á¸™" => "á¸˜",
            "á¸—" => "á¸–",
            "á¸•" => "á¸”",
            "á¸“" => "á¸’",
            "á¸‘" => "á¸",
            "á¸" => "á¸Ž",
            "á¸" => "á¸Œ",
            "á¸‹" => "á¸Š",
            "á¸‰" => "á¸ˆ",
            "á¸‡" => "á¸†",
            "á¸…" => "á¸„",
            "á¸ƒ" => "á¸‚",
            "á¸" => "á¸€",
            "Ö†" => "Õ–",
            "Ö…" => "Õ•",
            "Ö„" => "Õ”",
            "Öƒ" => "Õ“",
            "Ö‚" => "Õ’",
            "Ö" => "Õ‘",
            "Ö€" => "Õ",
            "Õ¿" => "Õ",
            "Õ¾" => "ÕŽ",
            "Õ½" => "Õ",
            "Õ¼" => "ÕŒ",
            "Õ»" => "Õ‹",
            "Õº" => "ÕŠ",
            "Õ¹" => "Õ‰",
            "Õ¸" => "Õˆ",
            "Õ·" => "Õ‡",
            "Õ¶" => "Õ†",
            "Õµ" => "Õ…",
            "Õ´" => "Õ„",
            "Õ³" => "Õƒ",
            "Õ²" => "Õ‚",
            "Õ±" => "Õ",
            "Õ°" => "Õ€",
            "Õ¯" => "Ô¿",
            "Õ®" => "Ô¾",
            "Õ­" => "Ô½",
            "Õ¬" => "Ô¼",
            "Õ«" => "Ô»",
            "Õª" => "Ôº",
            "Õ©" => "Ô¹",
            "Õ¨" => "Ô¸",
            "Õ§" => "Ô·",
            "Õ¦" => "Ô¶",
            "Õ¥" => "Ôµ",
            "Õ¤" => "Ô´",
            "Õ£" => "Ô³",
            "Õ¢" => "Ô²",
            "Õ¡" => "Ô±",
            "Ô" => "ÔŽ",
            "Ô" => "ÔŒ",
            "Ô‹" => "ÔŠ",
            "Ô‰" => "Ôˆ",
            "Ô‡" => "Ô†",
            "Ô…" => "Ô„",
            "Ôƒ" => "Ô‚",
            "Ô" => "Ô€",
            "Ó¹" => "Ó¸",
            "Óµ" => "Ó´",
            "Ó³" => "Ó²",
            "Ó±" => "Ó°",
            "Ó¯" => "Ó®",
            "Ó­" => "Ó¬",
            "Ó«" => "Óª",
            "Ó©" => "Ó¨",
            "Ó§" => "Ó¦",
            "Ó¥" => "Ó¤",
            "Ó£" => "Ó¢",
            "Ó¡" => "Ó ",
            "ÓŸ" => "Óž",
            "Ó" => "Óœ",
            "Ó›" => "Óš",
            "Ó™" => "Ó˜",
            "Ó—" => "Ó–",
            "Ó•" => "Ó”",
            "Ó“" => "Ó’",
            "Ó‘" => "Ó",
            "ÓŽ" => "Ó",
            "ÓŒ" => "Ó‹",
            "ÓŠ" => "Ó‰",
            "Óˆ" => "Ó‡",
            "Ó†" => "Ó…",
            "Ó„" => "Óƒ",
            "Ó‚" => "Ó",
            "Ò¿" => "Ò¾",
            "Ò½" => "Ò¼",
            "Ò»" => "Òº",
            "Ò¹" => "Ò¸",
            "Ò·" => "Ò¶",
            "Òµ" => "Ò´",
            "Ò³" => "Ò²",
            "Ò±" => "Ò°",
            "Ò¯" => "Ò®",
            "Ò­" => "Ò¬",
            "Ò«" => "Òª",
            "Ò©" => "Ò¨",
            "Ò§" => "Ò¦",
            "Ò¥" => "Ò¤",
            "Ò£" => "Ò¢",
            "Ò¡" => "Ò ",
            "ÒŸ" => "Òž",
            "Ò" => "Òœ",
            "Ò›" => "Òš",
            "Ò™" => "Ò˜",
            "Ò—" => "Ò–",
            "Ò•" => "Ò”",
            "Ò“" => "Ò’",
            "Ò‘" => "Ò",
            "Ò" => "ÒŽ",
            "Ò" => "ÒŒ",
            "Ò‹" => "ÒŠ",
            "Ò" => "Ò€",
            "Ñ¿" => "Ñ¾",
            "Ñ½" => "Ñ¼",
            "Ñ»" => "Ñº",
            "Ñ¹" => "Ñ¸",
            "Ñ·" => "Ñ¶",
            "Ñµ" => "Ñ´",
            "Ñ³" => "Ñ²",
            "Ñ±" => "Ñ°",
            "Ñ¯" => "Ñ®",
            "Ñ­" => "Ñ¬",
            "Ñ«" => "Ñª",
            "Ñ©" => "Ñ¨",
            "Ñ§" => "Ñ¦",
            "Ñ¥" => "Ñ¤",
            "Ñ£" => "Ñ¢",
            "Ñ¡" => "Ñ ",
            "ÑŸ" => "Ð",
            "Ñž" => "ÐŽ",
            "Ñ" => "Ð",
            "Ñœ" => "ÐŒ",
            "Ñ›" => "Ð‹",
            "Ñš" => "ÐŠ",
            "Ñ™" => "Ð‰",
            "Ñ˜" => "Ðˆ",
            "Ñ—" => "Ð‡",
            "Ñ–" => "Ð†",
            "Ñ•" => "Ð…",
            "Ñ”" => "Ð„",
            "Ñ“" => "Ðƒ",
            "Ñ’" => "Ð‚",
            "Ñ‘" => "Ð",
            "Ñ" => "Ð€",
            "Ñ" => "Ð¯",
            "ÑŽ" => "Ð®",
            "Ñ" => "Ð­",
            "ÑŒ" => "Ð¬",
            "Ñ‹" => "Ð«",
            "ÑŠ" => "Ðª",
            "Ñ‰" => "Ð©",
            "Ñˆ" => "Ð¨",
            "Ñ‡" => "Ð§",
            "Ñ†" => "Ð¦",
            "Ñ…" => "Ð¥",
            "Ñ„" => "Ð¤",
            "Ñƒ" => "Ð£",
            "Ñ‚" => "Ð¢",
            "Ñ" => "Ð¡",
            "Ñ€" => "Ð ",
            "Ð¿" => "ÐŸ",
            "Ð¾" => "Ðž",
            "Ð½" => "Ð",
            "Ð¼" => "Ðœ",
            "Ð»" => "Ð›",
            "Ðº" => "Ðš",
            "Ð¹" => "Ð™",
            "Ð¸" => "Ð˜",
            "Ð·" => "Ð—",
            "Ð¶" => "Ð–",
            "Ðµ" => "Ð•",
            "Ð´" => "Ð”",
            "Ð³" => "Ð“",
            "Ð²" => "Ð’",
            "Ð±" => "Ð‘",
            "Ð°" => "Ð",
            "Ïµ" => "Î•",
            "Ï²" => "Î£",
            "Ï±" => "Î¡",
            "Ï°" => "Îš",
            "Ï¯" => "Ï®",
            "Ï­" => "Ï¬",
            "Ï«" => "Ïª",
            "Ï©" => "Ï¨",
            "Ï§" => "Ï¦",
            "Ï¥" => "Ï¤",
            "Ï£" => "Ï¢",
            "Ï¡" => "Ï ",
            "ÏŸ" => "Ïž",
            "Ï" => "Ïœ",
            "Ï›" => "Ïš",
            "Ï™" => "Ï˜",
            "Ï–" => "Î ",
            "Ï•" => "Î¦",
            "Ï‘" => "Î˜",
            "Ï" => "Î’",
            "ÏŽ" => "Î",
            "Ï" => "ÎŽ",
            "ÏŒ" => "ÎŒ",
            "Ï‹" => "Î«",
            "ÏŠ" => "Îª",
            "Ï‰" => "Î©",
            "Ïˆ" => "Î¨",
            "Ï‡" => "Î§",
            "Ï†" => "Î¦",
            "Ï…" => "Î¥",
            "Ï„" => "Î¤",
            "Ïƒ" => "Î£",
            "Ï‚" => "Î£",
            "Ï" => "Î¡",
            "Ï€" => "Î ",
            "Î¿" => "ÎŸ",
            "Î¾" => "Îž",
            "Î½" => "Î",
            "Î¼" => "Îœ",
            "Î»" => "Î›",
            "Îº" => "Îš",
            "Î¹" => "Î™",
            "Î¸" => "Î˜",
            "Î·" => "Î—",
            "Î¶" => "Î–",
            "Îµ" => "Î•",
            "Î´" => "Î”",
            "Î³" => "Î“",
            "Î²" => "Î’",
            "Î±" => "Î‘",
            "Î¯" => "ÎŠ",
            "Î®" => "Î‰",
            "Î­" => "Îˆ",
            "Î¬" => "Î†",
            "Ê’" => "Æ·",
            "Ê‹" => "Æ²",
            "ÊŠ" => "Æ±",
            "Êˆ" => "Æ®",
            "Êƒ" => "Æ©",
            "Ê€" => "Æ¦",
            "Éµ" => "ÆŸ",
            "É²" => "Æ",
            "É¯" => "Æœ",
            "É©" => "Æ–",
            "É¨" => "Æ—",
            "É£" => "Æ”",
            "É›" => "Æ",
            "É™" => "Æ",
            "É—" => "ÆŠ",
            "É–" => "Æ‰",
            "É”" => "Æ†",
            "É“" => "Æ",
            "È³" => "È²",
            "È±" => "È°",
            "È¯" => "È®",
            "È­" => "È¬",
            "È«" => "Èª",
            "È©" => "È¨",
            "È§" => "È¦",
            "È¥" => "È¤",
            "È£" => "È¢",
            "ÈŸ" => "Èž",
            "È" => "Èœ",
            "È›" => "Èš",
            "È™" => "È˜",
            "È—" => "È–",
            "È•" => "È”",
            "È“" => "È’",
            "È‘" => "È",
            "È" => "ÈŽ",
            "È" => "ÈŒ",
            "È‹" => "ÈŠ",
            "È‰" => "Èˆ",
            "È‡" => "È†",
            "È…" => "È„",
            "Èƒ" => "È‚",
            "È" => "È€",
            "Ç¿" => "Ç¾",
            "Ç½" => "Ç¼",
            "Ç»" => "Çº",
            "Ç¹" => "Ç¸",
            "Çµ" => "Ç´",
            "Ç³" => "Ç²",
            "Ç¯" => "Ç®",
            "Ç­" => "Ç¬",
            "Ç«" => "Çª",
            "Ç©" => "Ç¨",
            "Ç§" => "Ç¦",
            "Ç¥" => "Ç¤",
            "Ç£" => "Ç¢",
            "Ç¡" => "Ç ",
            "ÇŸ" => "Çž",
            "Ç" => "ÆŽ",
            "Çœ" => "Ç›",
            "Çš" => "Ç™",
            "Ç˜" => "Ç—",
            "Ç–" => "Ç•",
            "Ç”" => "Ç“",
            "Ç’" => "Ç‘",
            "Ç" => "Ç",
            "ÇŽ" => "Ç",
            "ÇŒ" => "Ç‹",
            "Ç‰" => "Çˆ",
            "Ç†" => "Ç…",
            "Æ¿" => "Ç·",
            "Æ½" => "Æ¼",
            "Æ¹" => "Æ¸",
            "Æ¶" => "Æµ",
            "Æ´" => "Æ³",
            "Æ°" => "Æ¯",
            "Æ­" => "Æ¬",
            "Æ¨" => "Æ§",
            "Æ¥" => "Æ¤",
            "Æ£" => "Æ¢",
            "Æ¡" => "Æ ",
            "Æž" => "È ",
            "Æ™" => "Æ˜",
            "Æ•" => "Ç¶",
            "Æ’" => "Æ‘",
            "ÆŒ" => "Æ‹",
            "Æˆ" => "Æ‡",
            "Æ…" => "Æ„",
            "Æƒ" => "Æ‚",
            "Å¿" => "S",
            "Å¾" => "Å½",
            "Å¼" => "Å»",
            "Åº" => "Å¹",
            "Å·" => "Å¶",
            "Åµ" => "Å´",
            "Å³" => "Å²",
            "Å±" => "Å°",
            "Å¯" => "Å®",
            "Å­" => "Å¬",
            "Å«" => "Åª",
            "Å©" => "Å¨",
            "Å§" => "Å¦",
            "Å¥" => "Å¤",
            "Å£" => "Å¢",
            "Å¡" => "Å ",
            "ÅŸ" => "Åž",
            "Å" => "Åœ",
            "Å›" => "Åš",
            "Å™" => "Å˜",
            "Å—" => "Å–",
            "Å•" => "Å”",
            "Å“" => "Å’",
            "Å‘" => "Å",
            "Å" => "ÅŽ",
            "Å" => "ÅŒ",
            "Å‹" => "ÅŠ",
            "Åˆ" => "Å‡",
            "Å†" => "Å…",
            "Å„" => "Åƒ",
            "Å‚" => "Å",
            "Å€" => "Ä¿",
            "Ä¾" => "Ä½",
            "Ä¼" => "Ä»",
            "Äº" => "Ä¹",
            "Ä·" => "Ä¶",
            "Äµ" => "Ä´",
            "Ä³" => "Ä²",
            "Ä±" => "I",
            "Ä¯" => "Ä®",
            "Ä­" => "Ä¬",
            "Ä«" => "Äª",
            "Ä©" => "Ä¨",
            "Ä§" => "Ä¦",
            "Ä¥" => "Ä¤",
            "Ä£" => "Ä¢",
            "Ä¡" => "Ä ",
            "ÄŸ" => "Äž",
            "Ä" => "Äœ",
            "Ä›" => "Äš",
            "Ä™" => "Ä˜",
            "Ä—" => "Ä–",
            "Ä•" => "Ä”",
            "Ä“" => "Ä’",
            "Ä‘" => "Ä",
            "Ä" => "ÄŽ",
            "Ä" => "ÄŒ",
            "Ä‹" => "ÄŠ",
            "Ä‰" => "Äˆ",
            "Ä‡" => "Ä†",
            "Ä…" => "Ä„",
            "Äƒ" => "Ä‚",
            "Ä" => "Ä€",
            "Ã¿" => "Å¸",
            "Ã¾" => "Ãž",
            "Ã½" => "Ã",
            "Ã¼" => "Ãœ",
            "Ã»" => "Ã›",
            "Ãº" => "Ãš",
            "Ã¹" => "Ã™",
            "Ã¸" => "Ã˜",
            "Ã¶" => "Ã–",
            "Ãµ" => "Ã•",
            "Ã´" => "Ã”",
            "Ã³" => "Ã“",
            "Ã²" => "Ã’",
            "Ã±" => "Ã‘",
            "Ã°" => "Ã",
            "Ã¯" => "Ã",
            "Ã®" => "ÃŽ",
            "Ã­" => "Ã",
            "Ã¬" => "ÃŒ",
            "Ã«" => "Ã‹",
            "Ãª" => "ÃŠ",
            "Ã©" => "Ã‰",
            "Ã¨" => "Ãˆ",
            "Ã§" => "Ã‡",
            "Ã¦" => "Ã†",
            "Ã¥" => "Ã…",
            "Ã¤" => "Ã„",
            "Ã£" => "Ãƒ",
            "Ã¢" => "Ã‚",
            "Ã¡" => "Ã",
            "Ã " => "Ã€",
            "Âµ" => "Îœ",
            "z" => "Z",
            "y" => "Y",
            "x" => "X",
            "w" => "W",
            "v" => "V",
            "u" => "U",
            "t" => "T",
            "s" => "S",
            "r" => "R",
            "q" => "Q",
            "p" => "P",
            "o" => "O",
            "n" => "N",
            "m" => "M",
            "l" => "L",
            "k" => "K",
            "j" => "J",
            "i" => "I",
            "h" => "H",
            "g" => "G",
            "f" => "F",
            "e" => "E",
            "d" => "D",
            "c" => "C",
            "b" => "B",
            "a" => "A"
        );
    
    /**
     * UTF-8 Case lookup table
     *
     * This lookuptable defines the lower case letters to their corresponding
     * upper case letter in UTF-8
     *
     * @author Andreas Gohr <andi@splitbrain.org>
     */
    global $UTF8_UPPER_TO_LOWER;
    if (empty($UTF8_UPPER_TO_LOWER))
        $UTF8_UPPER_TO_LOWER = array(
            "ï¼º" => "ï½š",
            "ï¼¹" => "ï½™",
            "ï¼¸" => "ï½˜",
            "ï¼·" => "ï½—",
            "ï¼¶" => "ï½–",
            "ï¼µ" => "ï½•",
            "ï¼´" => "ï½”",
            "ï¼³" => "ï½“",
            "ï¼²" => "ï½’",
            "ï¼±" => "ï½‘",
            "ï¼°" => "ï½",
            "ï¼¯" => "ï½",
            "ï¼®" => "ï½Ž",
            "ï¼­" => "ï½",
            "ï¼¬" => "ï½Œ",
            "ï¼«" => "ï½‹",
            "ï¼ª" => "ï½Š",
            "ï¼©" => "ï½‰",
            "ï¼¨" => "ï½ˆ",
            "ï¼§" => "ï½‡",
            "ï¼¦" => "ï½†",
            "ï¼¥" => "ï½…",
            "ï¼¤" => "ï½„",
            "ï¼£" => "ï½ƒ",
            "ï¼¢" => "ï½‚",
            "ï¼¡" => "ï½",
            "á¿¼" => "á¿³",
            "á¿¬" => "á¿¥",
            "á¿©" => "á¿¡",
            "á¿™" => "á¿‘",
            "á¿˜" => "á¿",
            "á¿Œ" => "á¿ƒ",
            "Î™" => "á¾¾",
            "á¾¼" => "á¾³",
            "á¾¹" => "á¾±",
            "á¾¸" => "á¾°",
            "á¾¯" => "á¾§",
            "á¾®" => "á¾¦",
            "á¾­" => "á¾¥",
            "á¾¬" => "á¾¤",
            "á¾«" => "á¾£",
            "á¾ª" => "á¾¢",
            "á¾©" => "á¾¡",
            "á¾Ÿ" => "á¾—",
            "á¾ž" => "á¾–",
            "á¾" => "á¾•",
            "á¾œ" => "á¾”",
            "á¾›" => "á¾“",
            "á¾š" => "á¾’",
            "á¾™" => "á¾‘",
            "á¾˜" => "á¾",
            "á¾" => "á¾‡",
            "á¾Ž" => "á¾†",
            "á¾" => "á¾…",
            "á¾Œ" => "á¾„",
            "á¾‹" => "á¾ƒ",
            "á¾Š" => "á¾‚",
            "á¾‰" => "á¾",
            "á¾ˆ" => "á¾€",
            "á¿»" => "á½½",
            "á¿º" => "á½¼",
            "á¿«" => "á½»",
            "á¿ª" => "á½º",
            "á¿¹" => "á½¹",
            "á¿¸" => "á½¸",
            "á¿›" => "á½·",
            "á¿š" => "á½¶",
            "á¿‹" => "á½µ",
            "á¿Š" => "á½´",
            "á¿‰" => "á½³",
            "á¿ˆ" => "á½²",
            "á¾»" => "á½±",
            "á¾º" => "á½°",
            "á½¯" => "á½§",
            "á½®" => "á½¦",
            "á½­" => "á½¥",
            "á½¬" => "á½¤",
            "á½«" => "á½£",
            "á½ª" => "á½¢",
            "á½©" => "á½¡",
            "á½Ÿ" => "á½—",
            "á½" => "á½•",
            "á½›" => "á½“",
            "á½™" => "á½‘",
            "á½" => "á½…",
            "á½Œ" => "á½„",
            "á½‹" => "á½ƒ",
            "á½Š" => "á½‚",
            "á½‰" => "á½",
            "á½ˆ" => "á½€",
            "á¼¿" => "á¼·",
            "á¼¾" => "á¼¶",
            "á¼½" => "á¼µ",
            "á¼¼" => "á¼´",
            "á¼»" => "á¼³",
            "á¼º" => "á¼²",
            "á¼¹" => "á¼±",
            "á¼¸" => "á¼°",
            "á¼¯" => "á¼§",
            "á¼®" => "á¼¦",
            "á¼­" => "á¼¥",
            "á¼¬" => "á¼¤",
            "á¼«" => "á¼£",
            "á¼ª" => "á¼¢",
            "á¼©" => "á¼¡",
            "á¼" => "á¼•",
            "á¼œ" => "á¼”",
            "á¼›" => "á¼“",
            "á¼š" => "á¼’",
            "á¼™" => "á¼‘",
            "á¼˜" => "á¼",
            "á¼" => "á¼‡",
            "á¼Ž" => "á¼†",
            "á¼" => "á¼…",
            "á¼Œ" => "á¼„",
            "á¼‹" => "á¼ƒ",
            "á¼Š" => "á¼‚",
            "á¼‰" => "á¼",
            "á¼ˆ" => "á¼€",
            "á»¸" => "á»¹",
            "á»¶" => "á»·",
            "á»´" => "á»µ",
            "á»²" => "á»³",
            "á»°" => "á»±",
            "á»®" => "á»¯",
            "á»¬" => "á»­",
            "á»ª" => "á»«",
            "á»¨" => "á»©",
            "á»¦" => "á»§",
            "á»¤" => "á»¥",
            "á»¢" => "á»£",
            "á» " => "á»¡",
            "á»ž" => "á»Ÿ",
            "á»œ" => "á»",
            "á»š" => "á»›",
            "á»˜" => "á»™",
            "á»–" => "á»—",
            "á»”" => "á»•",
            "á»’" => "á»“",
            "á»" => "á»‘",
            "á»Ž" => "á»",
            "á»Œ" => "á»",
            "á»Š" => "á»‹",
            "á»ˆ" => "á»‰",
            "á»†" => "á»‡",
            "á»„" => "á»…",
            "á»‚" => "á»ƒ",
            "á»€" => "á»",
            "áº¾" => "áº¿",
            "áº¼" => "áº½",
            "áºº" => "áº»",
            "áº¸" => "áº¹",
            "áº¶" => "áº·",
            "áº´" => "áºµ",
            "áº²" => "áº³",
            "áº°" => "áº±",
            "áº®" => "áº¯",
            "áº¬" => "áº­",
            "áºª" => "áº«",
            "áº¨" => "áº©",
            "áº¦" => "áº§",
            "áº¤" => "áº¥",
            "áº¢" => "áº£",
            "áº " => "áº¡",
            "á¹ " => "áº›",
            "áº”" => "áº•",
            "áº’" => "áº“",
            "áº" => "áº‘",
            "áºŽ" => "áº",
            "áºŒ" => "áº",
            "áºŠ" => "áº‹",
            "áºˆ" => "áº‰",
            "áº†" => "áº‡",
            "áº„" => "áº…",
            "áº‚" => "áºƒ",
            "áº€" => "áº",
            "á¹¾" => "á¹¿",
            "á¹¼" => "á¹½",
            "á¹º" => "á¹»",
            "á¹¸" => "á¹¹",
            "á¹¶" => "á¹·",
            "á¹´" => "á¹µ",
            "á¹²" => "á¹³",
            "á¹°" => "á¹±",
            "á¹®" => "á¹¯",
            "á¹¬" => "á¹­",
            "á¹ª" => "á¹«",
            "á¹¨" => "á¹©",
            "á¹¦" => "á¹§",
            "á¹¤" => "á¹¥",
            "á¹¢" => "á¹£",
            "á¹ " => "á¹¡",
            "á¹ž" => "á¹Ÿ",
            "á¹œ" => "á¹",
            "á¹š" => "á¹›",
            "á¹˜" => "á¹™",
            "á¹–" => "á¹—",
            "á¹”" => "á¹•",
            "á¹’" => "á¹“",
            "á¹" => "á¹‘",
            "á¹Ž" => "á¹",
            "á¹Œ" => "á¹",
            "á¹Š" => "á¹‹",
            "á¹ˆ" => "á¹‰",
            "á¹†" => "á¹‡",
            "á¹„" => "á¹…",
            "á¹‚" => "á¹ƒ",
            "á¹€" => "á¹",
            "á¸¾" => "á¸¿",
            "á¸¼" => "á¸½",
            "á¸º" => "á¸»",
            "á¸¸" => "á¸¹",
            "á¸¶" => "á¸·",
            "á¸´" => "á¸µ",
            "á¸²" => "á¸³",
            "á¸°" => "á¸±",
            "á¸®" => "á¸¯",
            "á¸¬" => "á¸­",
            "á¸ª" => "á¸«",
            "á¸¨" => "á¸©",
            "á¸¦" => "á¸§",
            "á¸¤" => "á¸¥",
            "á¸¢" => "á¸£",
            "á¸ " => "á¸¡",
            "á¸ž" => "á¸Ÿ",
            "á¸œ" => "á¸",
            "á¸š" => "á¸›",
            "á¸˜" => "á¸™",
            "á¸–" => "á¸—",
            "á¸”" => "á¸•",
            "á¸’" => "á¸“",
            "á¸" => "á¸‘",
            "á¸Ž" => "á¸",
            "á¸Œ" => "á¸",
            "á¸Š" => "á¸‹",
            "á¸ˆ" => "á¸‰",
            "á¸†" => "á¸‡",
            "á¸„" => "á¸…",
            "á¸‚" => "á¸ƒ",
            "á¸€" => "á¸",
            "Õ–" => "Ö†",
            "Õ•" => "Ö…",
            "Õ”" => "Ö„",
            "Õ“" => "Öƒ",
            "Õ’" => "Ö‚",
            "Õ‘" => "Ö",
            "Õ" => "Ö€",
            "Õ" => "Õ¿",
            "ÕŽ" => "Õ¾",
            "Õ" => "Õ½",
            "ÕŒ" => "Õ¼",
            "Õ‹" => "Õ»",
            "ÕŠ" => "Õº",
            "Õ‰" => "Õ¹",
            "Õˆ" => "Õ¸",
            "Õ‡" => "Õ·",
            "Õ†" => "Õ¶",
            "Õ…" => "Õµ",
            "Õ„" => "Õ´",
            "Õƒ" => "Õ³",
            "Õ‚" => "Õ²",
            "Õ" => "Õ±",
            "Õ€" => "Õ°",
            "Ô¿" => "Õ¯",
            "Ô¾" => "Õ®",
            "Ô½" => "Õ­",
            "Ô¼" => "Õ¬",
            "Ô»" => "Õ«",
            "Ôº" => "Õª",
            "Ô¹" => "Õ©",
            "Ô¸" => "Õ¨",
            "Ô·" => "Õ§",
            "Ô¶" => "Õ¦",
            "Ôµ" => "Õ¥",
            "Ô´" => "Õ¤",
            "Ô³" => "Õ£",
            "Ô²" => "Õ¢",
            "Ô±" => "Õ¡",
            "ÔŽ" => "Ô",
            "ÔŒ" => "Ô",
            "ÔŠ" => "Ô‹",
            "Ôˆ" => "Ô‰",
            "Ô†" => "Ô‡",
            "Ô„" => "Ô…",
            "Ô‚" => "Ôƒ",
            "Ô€" => "Ô",
            "Ó¸" => "Ó¹",
            "Ó´" => "Óµ",
            "Ó²" => "Ó³",
            "Ó°" => "Ó±",
            "Ó®" => "Ó¯",
            "Ó¬" => "Ó­",
            "Óª" => "Ó«",
            "Ó¨" => "Ó©",
            "Ó¦" => "Ó§",
            "Ó¤" => "Ó¥",
            "Ó¢" => "Ó£",
            "Ó " => "Ó¡",
            "Óž" => "ÓŸ",
            "Óœ" => "Ó",
            "Óš" => "Ó›",
            "Ó˜" => "Ó™",
            "Ó–" => "Ó—",
            "Ó”" => "Ó•",
            "Ó’" => "Ó“",
            "Ó" => "Ó‘",
            "Ó" => "ÓŽ",
            "Ó‹" => "ÓŒ",
            "Ó‰" => "ÓŠ",
            "Ó‡" => "Óˆ",
            "Ó…" => "Ó†",
            "Óƒ" => "Ó„",
            "Ó" => "Ó‚",
            "Ò¾" => "Ò¿",
            "Ò¼" => "Ò½",
            "Òº" => "Ò»",
            "Ò¸" => "Ò¹",
            "Ò¶" => "Ò·",
            "Ò´" => "Òµ",
            "Ò²" => "Ò³",
            "Ò°" => "Ò±",
            "Ò®" => "Ò¯",
            "Ò¬" => "Ò­",
            "Òª" => "Ò«",
            "Ò¨" => "Ò©",
            "Ò¦" => "Ò§",
            "Ò¤" => "Ò¥",
            "Ò¢" => "Ò£",
            "Ò " => "Ò¡",
            "Òž" => "ÒŸ",
            "Òœ" => "Ò",
            "Òš" => "Ò›",
            "Ò˜" => "Ò™",
            "Ò–" => "Ò—",
            "Ò”" => "Ò•",
            "Ò’" => "Ò“",
            "Ò" => "Ò‘",
            "ÒŽ" => "Ò",
            "ÒŒ" => "Ò",
            "ÒŠ" => "Ò‹",
            "Ò€" => "Ò",
            "Ñ¾" => "Ñ¿",
            "Ñ¼" => "Ñ½",
            "Ñº" => "Ñ»",
            "Ñ¸" => "Ñ¹",
            "Ñ¶" => "Ñ·",
            "Ñ´" => "Ñµ",
            "Ñ²" => "Ñ³",
            "Ñ°" => "Ñ±",
            "Ñ®" => "Ñ¯",
            "Ñ¬" => "Ñ­",
            "Ñª" => "Ñ«",
            "Ñ¨" => "Ñ©",
            "Ñ¦" => "Ñ§",
            "Ñ¤" => "Ñ¥",
            "Ñ¢" => "Ñ£",
            "Ñ " => "Ñ¡",
            "Ð" => "ÑŸ",
            "ÐŽ" => "Ñž",
            "Ð" => "Ñ",
            "ÐŒ" => "Ñœ",
            "Ð‹" => "Ñ›",
            "ÐŠ" => "Ñš",
            "Ð‰" => "Ñ™",
            "Ðˆ" => "Ñ˜",
            "Ð‡" => "Ñ—",
            "Ð†" => "Ñ–",
            "Ð…" => "Ñ•",
            "Ð„" => "Ñ”",
            "Ðƒ" => "Ñ“",
            "Ð‚" => "Ñ’",
            "Ð" => "Ñ‘",
            "Ð€" => "Ñ",
            "Ð¯" => "Ñ",
            "Ð®" => "ÑŽ",
            "Ð­" => "Ñ",
            "Ð¬" => "ÑŒ",
            "Ð«" => "Ñ‹",
            "Ðª" => "ÑŠ",
            "Ð©" => "Ñ‰",
            "Ð¨" => "Ñˆ",
            "Ð§" => "Ñ‡",
            "Ð¦" => "Ñ†",
            "Ð¥" => "Ñ…",
            "Ð¤" => "Ñ„",
            "Ð£" => "Ñƒ",
            "Ð¢" => "Ñ‚",
            "Ð¡" => "Ñ",
            "Ð " => "Ñ€",
            "ÐŸ" => "Ð¿",
            "Ðž" => "Ð¾",
            "Ð" => "Ð½",
            "Ðœ" => "Ð¼",
            "Ð›" => "Ð»",
            "Ðš" => "Ðº",
            "Ð™" => "Ð¹",
            "Ð˜" => "Ð¸",
            "Ð—" => "Ð·",
            "Ð–" => "Ð¶",
            "Ð•" => "Ðµ",
            "Ð”" => "Ð´",
            "Ð“" => "Ð³",
            "Ð’" => "Ð²",
            "Ð‘" => "Ð±",
            "Ð" => "Ð°",
            "Î•" => "Ïµ",
            "Î£" => "Ï²",
            "Î¡" => "Ï±",
            "Îš" => "Ï°",
            "Ï®" => "Ï¯",
            "Ï¬" => "Ï­",
            "Ïª" => "Ï«",
            "Ï¨" => "Ï©",
            "Ï¦" => "Ï§",
            "Ï¤" => "Ï¥",
            "Ï¢" => "Ï£",
            "Ï " => "Ï¡",
            "Ïž" => "ÏŸ",
            "Ïœ" => "Ï",
            "Ïš" => "Ï›",
            "Ï˜" => "Ï™",
            "Î " => "Ï–",
            "Î¦" => "Ï•",
            "Î˜" => "Ï‘",
            "Î’" => "Ï",
            "Î" => "ÏŽ",
            "ÎŽ" => "Ï",
            "ÎŒ" => "ÏŒ",
            "Î«" => "Ï‹",
            "Îª" => "ÏŠ",
            "Î©" => "Ï‰",
            "Î¨" => "Ïˆ",
            "Î§" => "Ï‡",
            "Î¦" => "Ï†",
            "Î¥" => "Ï…",
            "Î¤" => "Ï„",
            "Î£" => "Ïƒ",
            "Î£" => "Ï‚",
            "Î¡" => "Ï",
            "Î " => "Ï€",
            "ÎŸ" => "Î¿",
            "Îž" => "Î¾",
            "Î" => "Î½",
            "Îœ" => "Î¼",
            "Î›" => "Î»",
            "Îš" => "Îº",
            "Î™" => "Î¹",
            "Î˜" => "Î¸",
            "Î—" => "Î·",
            "Î–" => "Î¶",
            "Î•" => "Îµ",
            "Î”" => "Î´",
            "Î“" => "Î³",
            "Î’" => "Î²",
            "Î‘" => "Î±",
            "ÎŠ" => "Î¯",
            "Î‰" => "Î®",
            "Îˆ" => "Î­",
            "Î†" => "Î¬",
            "Æ·" => "Ê’",
            "Æ²" => "Ê‹",
            "Æ±" => "ÊŠ",
            "Æ®" => "Êˆ",
            "Æ©" => "Êƒ",
            "Æ¦" => "Ê€",
            "ÆŸ" => "Éµ",
            "Æ" => "É²",
            "Æœ" => "É¯",
            "Æ–" => "É©",
            "Æ—" => "É¨",
            "Æ”" => "É£",
            "Æ" => "É›",
            "Æ" => "É™",
            "ÆŠ" => "É—",
            "Æ‰" => "É–",
            "Æ†" => "É”",
            "Æ" => "É“",
            "È²" => "È³",
            "È°" => "È±",
            "È®" => "È¯",
            "È¬" => "È­",
            "Èª" => "È«",
            "È¨" => "È©",
            "È¦" => "È§",
            "È¤" => "È¥",
            "È¢" => "È£",
            "Èž" => "ÈŸ",
            "Èœ" => "È",
            "Èš" => "È›",
            "È˜" => "È™",
            "È–" => "È—",
            "È”" => "È•",
            "È’" => "È“",
            "È" => "È‘",
            "ÈŽ" => "È",
            "ÈŒ" => "È",
            "ÈŠ" => "È‹",
            "Èˆ" => "È‰",
            "È†" => "È‡",
            "È„" => "È…",
            "È‚" => "Èƒ",
            "È€" => "È",
            "Ç¾" => "Ç¿",
            "Ç¼" => "Ç½",
            "Çº" => "Ç»",
            "Ç¸" => "Ç¹",
            "Ç´" => "Çµ",
            "Ç²" => "Ç³",
            "Ç®" => "Ç¯",
            "Ç¬" => "Ç­",
            "Çª" => "Ç«",
            "Ç¨" => "Ç©",
            "Ç¦" => "Ç§",
            "Ç¤" => "Ç¥",
            "Ç¢" => "Ç£",
            "Ç " => "Ç¡",
            "Çž" => "ÇŸ",
            "ÆŽ" => "Ç",
            "Ç›" => "Çœ",
            "Ç™" => "Çš",
            "Ç—" => "Ç˜",
            "Ç•" => "Ç–",
            "Ç“" => "Ç”",
            "Ç‘" => "Ç’",
            "Ç" => "Ç",
            "Ç" => "ÇŽ",
            "Ç‹" => "ÇŒ",
            "Çˆ" => "Ç‰",
            "Ç…" => "Ç†",
            "Ç·" => "Æ¿",
            "Æ¼" => "Æ½",
            "Æ¸" => "Æ¹",
            "Æµ" => "Æ¶",
            "Æ³" => "Æ´",
            "Æ¯" => "Æ°",
            "Æ¬" => "Æ­",
            "Æ§" => "Æ¨",
            "Æ¤" => "Æ¥",
            "Æ¢" => "Æ£",
            "Æ " => "Æ¡",
            "È " => "Æž",
            "Æ˜" => "Æ™",
            "Ç¶" => "Æ•",
            "Æ‘" => "Æ’",
            "Æ‹" => "ÆŒ",
            "Æ‡" => "Æˆ",
            "Æ„" => "Æ…",
            "Æ‚" => "Æƒ",
            "S" => "Å¿",
            "Å½" => "Å¾",
            "Å»" => "Å¼",
            "Å¹" => "Åº",
            "Å¶" => "Å·",
            "Å´" => "Åµ",
            "Å²" => "Å³",
            "Å°" => "Å±",
            "Å®" => "Å¯",
            "Å¬" => "Å­",
            "Åª" => "Å«",
            "Å¨" => "Å©",
            "Å¦" => "Å§",
            "Å¤" => "Å¥",
            "Å¢" => "Å£",
            "Å " => "Å¡",
            "Åž" => "ÅŸ",
            "Åœ" => "Å",
            "Åš" => "Å›",
            "Å˜" => "Å™",
            "Å–" => "Å—",
            "Å”" => "Å•",
            "Å’" => "Å“",
            "Å" => "Å‘",
            "ÅŽ" => "Å",
            "ÅŒ" => "Å",
            "ÅŠ" => "Å‹",
            "Å‡" => "Åˆ",
            "Å…" => "Å†",
            "Åƒ" => "Å„",
            "Å" => "Å‚",
            "Ä¿" => "Å€",
            "Ä½" => "Ä¾",
            "Ä»" => "Ä¼",
            "Ä¹" => "Äº",
            "Ä¶" => "Ä·",
            "Ä´" => "Äµ",
            "Ä²" => "Ä³",
            "I" => "Ä±",
            "Ä®" => "Ä¯",
            "Ä¬" => "Ä­",
            "Äª" => "Ä«",
            "Ä¨" => "Ä©",
            "Ä¦" => "Ä§",
            "Ä¤" => "Ä¥",
            "Ä¢" => "Ä£",
            "Ä " => "Ä¡",
            "Äž" => "ÄŸ",
            "Äœ" => "Ä",
            "Äš" => "Ä›",
            "Ä˜" => "Ä™",
            "Ä–" => "Ä—",
            "Ä”" => "Ä•",
            "Ä’" => "Ä“",
            "Ä" => "Ä‘",
            "ÄŽ" => "Ä",
            "ÄŒ" => "Ä",
            "ÄŠ" => "Ä‹",
            "Äˆ" => "Ä‰",
            "Ä†" => "Ä‡",
            "Ä„" => "Ä…",
            "Ä‚" => "Äƒ",
            "Ä€" => "Ä",
            "Å¸" => "Ã¿",
            "Ãž" => "Ã¾",
            "Ã" => "Ã½",
            "Ãœ" => "Ã¼",
            "Ã›" => "Ã»",
            "Ãš" => "Ãº",
            "Ã™" => "Ã¹",
            "Ã˜" => "Ã¸",
            "Ã–" => "Ã¶",
            "Ã•" => "Ãµ",
            "Ã”" => "Ã´",
            "Ã“" => "Ã³",
            "Ã’" => "Ã²",
            "Ã‘" => "Ã±",
            "Ã" => "Ã°",
            "Ã" => "Ã¯",
            "ÃŽ" => "Ã®",
            "Ã" => "Ã­",
            "ÃŒ" => "Ã¬",
            "Ã‹" => "Ã«",
            "ÃŠ" => "Ãª",
            "Ã‰" => "Ã©",
            "Ãˆ" => "Ã¨",
            "Ã‡" => "Ã§",
            "Ã†" => "Ã¦",
            "Ã…" => "Ã¥",
            "Ã„" => "Ã¤",
            "Ãƒ" => "Ã£",
            "Ã‚" => "Ã¢",
            "Ã" => "Ã¡",
            "Ã€" => "Ã ",
            "Îœ" => "Âµ",
            "Z" => "z",
            "Y" => "y",
            "X" => "x",
            "W" => "w",
            "V" => "v",
            "U" => "u",
            "T" => "t",
            "S" => "s",
            "R" => "r",
            "Q" => "q",
            "P" => "p",
            "O" => "o",
            "N" => "n",
            "M" => "m",
            "L" => "l",
            "K" => "k",
            "J" => "j",
            "I" => "i",
            "H" => "h",
            "G" => "g",
            "F" => "f",
            "E" => "e",
            "D" => "d",
            "C" => "c",
            "B" => "b",
            "A" => "a"
        );
}
; // end of case lookup tables

/**
 * UTF-8 lookup table for lower case accented letters
 *
 * This lookuptable defines replacements for accented characters from the ASCII-7
 * range. This are lower case letters only.
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @see utf8_deaccent()
 */
global $UTF8_LOWER_ACCENTS;
if (empty($UTF8_LOWER_ACCENTS))
    $UTF8_LOWER_ACCENTS = array(
        'Ã ' => 'a',
        'Ã´' => 'o',
        'Ä' => 'd',
        'á¸Ÿ' => 'f',
        'Ã«' => 'e',
        'Å¡' => 's',
        'Æ¡' => 'o',
        'ÃŸ' => 'ss',
        'Äƒ' => 'a',
        'Å™' => 'r',
        'È›' => 't',
        'Åˆ' => 'n',
        'Ä' => 'a',
        'Ä·' => 'k',
        'Å' => 's',
        'á»³' => 'y',
        'Å†' => 'n',
        'Äº' => 'l',
        'Ä§' => 'h',
        'á¹—' => 'p',
        'Ã³' => 'o',
        'Ãº' => 'u',
        'Ä›' => 'e',
        'Ã©' => 'e',
        'Ã§' => 'c',
        'áº' => 'w',
        'Ä‹' => 'c',
        'Ãµ' => 'o',
        'á¹¡' => 's',
        'Ã¸' => 'o',
        'Ä£' => 'g',
        'Å§' => 't',
        'È™' => 's',
        'Ä—' => 'e',
        'Ä‰' => 'c',
        'Å›' => 's',
        'Ã®' => 'i',
        'Å±' => 'u',
        'Ä‡' => 'c',
        'Ä™' => 'e',
        'Åµ' => 'w',
        'á¹«' => 't',
        'Å«' => 'u',
        'Ä' => 'c',
        'Ã¶' => 'oe',
        'Ã¨' => 'e',
        'Å·' => 'y',
        'Ä…' => 'a',
        'Å‚' => 'l',
        'Å³' => 'u',
        'Å¯' => 'u',
        'ÅŸ' => 's',
        'ÄŸ' => 'g',
        'Ä¼' => 'l',
        'Æ’' => 'f',
        'Å¾' => 'z',
        'áºƒ' => 'w',
        'á¸ƒ' => 'b',
        'Ã¥' => 'a',
        'Ã¬' => 'i',
        'Ã¯' => 'i',
        'á¸‹' => 'd',
        'Å¥' => 't',
        'Å—' => 'r',
        'Ã¤' => 'ae',
        'Ã­' => 'i',
        'Å•' => 'r',
        'Ãª' => 'e',
        'Ã¼' => 'ue',
        'Ã²' => 'o',
        'Ä“' => 'e',
        'Ã±' => 'n',
        'Å„' => 'n',
        'Ä¥' => 'h',
        'Ä' => 'g',
        'Ä‘' => 'd',
        'Äµ' => 'j',
        'Ã¿' => 'y',
        'Å©' => 'u',
        'Å­' => 'u',
        'Æ°' => 'u',
        'Å£' => 't',
        'Ã½' => 'y',
        'Å‘' => 'o',
        'Ã¢' => 'a',
        'Ä¾' => 'l',
        'áº…' => 'w',
        'Å¼' => 'z',
        'Ä«' => 'i',
        'Ã£' => 'a',
        'Ä¡' => 'g',
        'á¹' => 'm',
        'Å' => 'o',
        'Ä©' => 'i',
        'Ã¹' => 'u',
        'Ä¯' => 'i',
        'Åº' => 'z',
        'Ã¡' => 'a',
        'Ã»' => 'u',
        'Ã¾' => 'th',
        'Ã°' => 'dh',
        'Ã¦' => 'ae',
        'Âµ' => 'u',
        'Ä•' => 'e'
    );

/**
 * UTF-8 lookup table for upper case accented letters
 *
 * This lookuptable defines replacements for accented characters from the ASCII-7
 * range. This are upper case letters only.
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @see utf8_deaccent()
 */
global $UTF8_UPPER_ACCENTS;
if (empty($UTF8_UPPER_ACCENTS))
    $UTF8_UPPER_ACCENTS = array(
        'Ã€' => 'A',
        'Ã”' => 'O',
        'ÄŽ' => 'D',
        'á¸ž' => 'F',
        'Ã‹' => 'E',
        'Å ' => 'S',
        'Æ ' => 'O',
        'Ä‚' => 'A',
        'Å˜' => 'R',
        'Èš' => 'T',
        'Å‡' => 'N',
        'Ä€' => 'A',
        'Ä¶' => 'K',
        'Åœ' => 'S',
        'á»²' => 'Y',
        'Å…' => 'N',
        'Ä¹' => 'L',
        'Ä¦' => 'H',
        'á¹–' => 'P',
        'Ã“' => 'O',
        'Ãš' => 'U',
        'Äš' => 'E',
        'Ã‰' => 'E',
        'Ã‡' => 'C',
        'áº€' => 'W',
        'ÄŠ' => 'C',
        'Ã•' => 'O',
        'á¹ ' => 'S',
        'Ã˜' => 'O',
        'Ä¢' => 'G',
        'Å¦' => 'T',
        'È˜' => 'S',
        'Ä–' => 'E',
        'Äˆ' => 'C',
        'Åš' => 'S',
        'ÃŽ' => 'I',
        'Å°' => 'U',
        'Ä†' => 'C',
        'Ä˜' => 'E',
        'Å´' => 'W',
        'á¹ª' => 'T',
        'Åª' => 'U',
        'ÄŒ' => 'C',
        'Ã–' => 'Oe',
        'Ãˆ' => 'E',
        'Å¶' => 'Y',
        'Ä„' => 'A',
        'Å' => 'L',
        'Å²' => 'U',
        'Å®' => 'U',
        'Åž' => 'S',
        'Äž' => 'G',
        'Ä»' => 'L',
        'Æ‘' => 'F',
        'Å½' => 'Z',
        'áº‚' => 'W',
        'á¸‚' => 'B',
        'Ã…' => 'A',
        'ÃŒ' => 'I',
        'Ã' => 'I',
        'á¸Š' => 'D',
        'Å¤' => 'T',
        'Å–' => 'R',
        'Ã„' => 'Ae',
        'Ã' => 'I',
        'Å”' => 'R',
        'ÃŠ' => 'E',
        'Ãœ' => 'Ue',
        'Ã’' => 'O',
        'Ä’' => 'E',
        'Ã‘' => 'N',
        'Åƒ' => 'N',
        'Ä¤' => 'H',
        'Äœ' => 'G',
        'Ä' => 'D',
        'Ä´' => 'J',
        'Å¸' => 'Y',
        'Å¨' => 'U',
        'Å¬' => 'U',
        'Æ¯' => 'U',
        'Å¢' => 'T',
        'Ã' => 'Y',
        'Å' => 'O',
        'Ã‚' => 'A',
        'Ä½' => 'L',
        'áº„' => 'W',
        'Å»' => 'Z',
        'Äª' => 'I',
        'Ãƒ' => 'A',
        'Ä ' => 'G',
        'á¹€' => 'M',
        'ÅŒ' => 'O',
        'Ä¨' => 'I',
        'Ã™' => 'U',
        'Ä®' => 'I',
        'Å¹' => 'Z',
        'Ã' => 'A',
        'Ã›' => 'U',
        'Ãž' => 'Th',
        'Ã' => 'Dh',
        'Ã†' => 'Ae',
        'Ä”' => 'E'
    );

/**
 * UTF-8 array of common special characters
 *
 * This array should contain all special characters (not a letter or digit)
 * defined in the various local charsets - it's not a complete list of non-alphanum
 * characters in UTF-8. It's not perfect but should match most cases of special
 * chars.
 *
 * The controlchars 0x00 to 0x19 are _not_ included in this array. The space 0x20 is!
 * These chars are _not_ in the array either: _ (0x5f), : 0x3a, . 0x2e, - 0x2d, * 0x2a
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @see utf8_stripspecials()
 */
global $UTF8_SPECIAL_CHARS;
if (empty($UTF8_SPECIAL_CHARS))
    $UTF8_SPECIAL_CHARS = array(
        0x001a,
        0x001b,
        0x001c,
        0x001d,
        0x001e,
        0x001f,
        0x0020,
        0x0021,
        0x0022,
        0x0023,
        0x0024,
        0x0025,
        0x0026,
        0x0027,
        0x0028,
        0x0029,
        0x002b,
        0x002c,
        0x002f,
        0x003b,
        0x003c,
        0x003d,
        0x003e,
        0x003f,
        0x0040,
        0x005b,
        0x005c,
        0x005d,
        0x005e,
        0x0060,
        0x007b,
        0x007c,
        0x007d,
        0x007e,
        0x007f,
        0x0080,
        0x0081,
        0x0082,
        0x0083,
        0x0084,
        0x0085,
        0x0086,
        0x0087,
        0x0088,
        0x0089,
        0x008a,
        0x008b,
        0x008c,
        0x008d,
        0x008e,
        0x008f,
        0x0090,
        0x0091,
        0x0092,
        0x0093,
        0x0094,
        0x0095,
        0x0096,
        0x0097,
        0x0098,
        0x0099,
        0x009a,
        0x009b,
        0x009c,
        0x009d,
        0x009e,
        0x009f,
        0x00a0,
        0x00a1,
        0x00a2,
        0x00a3,
        0x00a4,
        0x00a5,
        0x00a6,
        0x00a7,
        0x00a8,
        0x00a9,
        0x00aa,
        0x00ab,
        0x00ac,
        0x00ad,
        0x00ae,
        0x00af,
        0x00b0,
        0x00b1,
        0x00b2,
        0x00b3,
        0x00b4,
        0x00b5,
        0x00b6,
        0x00b7,
        0x00b8,
        0x00b9,
        0x00ba,
        0x00bb,
        0x00bc,
        0x00bd,
        0x00be,
        0x00bf,
        0x00d7,
        0x00f7,
        0x02c7,
        0x02d8,
        0x02d9,
        0x02da,
        0x02db,
        0x02dc,
        0x02dd,
        0x0300,
        0x0301,
        0x0303,
        0x0309,
        0x0323,
        0x0384,
        0x0385,
        0x0387,
        0x03c6,
        0x03d1,
        0x03d2,
        0x03d5,
        0x03d6,
        0x05b0,
        0x05b1,
        0x05b2,
        0x05b3,
        0x05b4,
        0x05b5,
        0x05b6,
        0x05b7,
        0x05b8,
        0x05b9,
        0x05bb,
        0x05bc,
        0x05bd,
        0x05be,
        0x05bf,
        0x05c0,
        0x05c1,
        0x05c2,
        0x05c3,
        0x05f3,
        0x05f4,
        0x060c,
        0x061b,
        0x061f,
        0x0640,
        0x064b,
        0x064c,
        0x064d,
        0x064e,
        0x064f,
        0x0650,
        0x0651,
        0x0652,
        0x066a,
        0x0e3f,
        0x200c,
        0x200d,
        0x200e,
        0x200f,
        0x2013,
        0x2014,
        0x2015,
        0x2017,
        0x2018,
        0x2019,
        0x201a,
        0x201c,
        0x201d,
        0x201e,
        0x2020,
        0x2021,
        0x2022,
        0x2026,
        0x2030,
        0x2032,
        0x2033,
        0x2039,
        0x203a,
        0x2044,
        0x20a7,
        0x20aa,
        0x20ab,
        0x20ac,
        0x2116,
        0x2118,
        0x2122,
        0x2126,
        0x2135,
        0x2190,
        0x2191,
        0x2192,
        0x2193,
        0x2194,
        0x2195,
        0x21b5,
        0x21d0,
        0x21d1,
        0x21d2,
        0x21d3,
        0x21d4,
        0x2200,
        0x2202,
        0x2203,
        0x2205,
        0x2206,
        0x2207,
        0x2208,
        0x2209,
        0x220b,
        0x220f,
        0x2211,
        0x2212,
        0x2215,
        0x2217,
        0x2219,
        0x221a,
        0x221d,
        0x221e,
        0x2220,
        0x2227,
        0x2228,
        0x2229,
        0x222a,
        0x222b,
        0x2234,
        0x223c,
        0x2245,
        0x2248,
        0x2260,
        0x2261,
        0x2264,
        0x2265,
        0x2282,
        0x2283,
        0x2284,
        0x2286,
        0x2287,
        0x2295,
        0x2297,
        0x22a5,
        0x22c5,
        0x2310,
        0x2320,
        0x2321,
        0x2329,
        0x232a,
        0x2469,
        0x2500,
        0x2502,
        0x250c,
        0x2510,
        0x2514,
        0x2518,
        0x251c,
        0x2524,
        0x252c,
        0x2534,
        0x253c,
        0x2550,
        0x2551,
        0x2552,
        0x2553,
        0x2554,
        0x2555,
        0x2556,
        0x2557,
        0x2558,
        0x2559,
        0x255a,
        0x255b,
        0x255c,
        0x255d,
        0x255e,
        0x255f,
        0x2560,
        0x2561,
        0x2562,
        0x2563,
        0x2564,
        0x2565,
        0x2566,
        0x2567,
        0x2568,
        0x2569,
        0x256a,
        0x256b,
        0x256c,
        0x2580,
        0x2584,
        0x2588,
        0x258c,
        0x2590,
        0x2591,
        0x2592,
        0x2593,
        0x25a0,
        0x25b2,
        0x25bc,
        0x25c6,
        0x25ca,
        0x25cf,
        0x25d7,
        0x2605,
        0x260e,
        0x261b,
        0x261e,
        0x2660,
        0x2663,
        0x2665,
        0x2666,
        0x2701,
        0x2702,
        0x2703,
        0x2704,
        0x2706,
        0x2707,
        0x2708,
        0x2709,
        0x270c,
        0x270d,
        0x270e,
        0x270f,
        0x2710,
        0x2711,
        0x2712,
        0x2713,
        0x2714,
        0x2715,
        0x2716,
        0x2717,
        0x2718,
        0x2719,
        0x271a,
        0x271b,
        0x271c,
        0x271d,
        0x271e,
        0x271f,
        0x2720,
        0x2721,
        0x2722,
        0x2723,
        0x2724,
        0x2725,
        0x2726,
        0x2727,
        0x2729,
        0x272a,
        0x272b,
        0x272c,
        0x272d,
        0x272e,
        0x272f,
        0x2730,
        0x2731,
        0x2732,
        0x2733,
        0x2734,
        0x2735,
        0x2736,
        0x2737,
        0x2738,
        0x2739,
        0x273a,
        0x273b,
        0x273c,
        0x273d,
        0x273e,
        0x273f,
        0x2740,
        0x2741,
        0x2742,
        0x2743,
        0x2744,
        0x2745,
        0x2746,
        0x2747,
        0x2748,
        0x2749,
        0x274a,
        0x274b,
        0x274d,
        0x274f,
        0x2750,
        0x2751,
        0x2752,
        0x2756,
        0x2758,
        0x2759,
        0x275a,
        0x275b,
        0x275c,
        0x275d,
        0x275e,
        0x2761,
        0x2762,
        0x2763,
        0x2764,
        0x2765,
        0x2766,
        0x2767,
        0x277f,
        0x2789,
        0x2793,
        0x2794,
        0x2798,
        0x2799,
        0x279a,
        0x279b,
        0x279c,
        0x279d,
        0x279e,
        0x279f,
        0x27a0,
        0x27a1,
        0x27a2,
        0x27a3,
        0x27a4,
        0x27a5,
        0x27a6,
        0x27a7,
        0x27a8,
        0x27a9,
        0x27aa,
        0x27ab,
        0x27ac,
        0x27ad,
        0x27ae,
        0x27af,
        0x27b1,
        0x27b2,
        0x27b3,
        0x27b4,
        0x27b5,
        0x27b6,
        0x27b7,
        0x27b8,
        0x27b9,
        0x27ba,
        0x27bb,
        0x27bc,
        0x27bd,
        0x27be,
        0x3000,
        0x3001,
        0x3002,
        0x3003,
        0x3008,
        0x3009,
        0x300a,
        0x300b,
        0x300c,
        0x300d,
        0x300e,
        0x300f,
        0x3010,
        0x3011,
        0x3012,
        0x3014,
        0x3015,
        0x3016,
        0x3017,
        0x3018,
        0x3019,
        0x301a,
        0x301b,
        0x3036,
        0xf6d9,
        0xf6da,
        0xf6db,
        0xf8d7,
        0xf8d8,
        0xf8d9,
        0xf8da,
        0xf8db,
        0xf8dc,
        0xf8dd,
        0xf8de,
        0xf8df,
        0xf8e0,
        0xf8e1,
        0xf8e2,
        0xf8e3,
        0xf8e4,
        0xf8e5,
        0xf8e6,
        0xf8e7,
        0xf8e8,
        0xf8e9,
        0xf8ea,
        0xf8eb,
        0xf8ec,
        0xf8ed,
        0xf8ee,
        0xf8ef,
        0xf8f0,
        0xf8f1,
        0xf8f2,
        0xf8f3,
        0xf8f4,
        0xf8f5,
        0xf8f6,
        0xf8f7,
        0xf8f8,
        0xf8f9,
        0xf8fa,
        0xf8fb,
        0xf8fc,
        0xf8fd,
        0xf8fe,
        0xfe7c,
        0xfe7d,
        0xff01,
        0xff02,
        0xff03,
        0xff04,
        0xff05,
        0xff06,
        0xff07,
        0xff08,
        0xff09,
        0xff09,
        0xff0a,
        0xff0b,
        0xff0c,
        0xff0d,
        0xff0e,
        0xff0f,
        0xff1a,
        0xff1b,
        0xff1c,
        0xff1d,
        0xff1e,
        0xff1f,
        0xff20,
        0xff3b,
        0xff3c,
        0xff3d,
        0xff3e,
        0xff40,
        0xff5b,
        0xff5c,
        0xff5d,
        0xff5e,
        0xff5f,
        0xff60,
        0xff61,
        0xff62,
        0xff63,
        0xff64,
        0xff65,
        0xffe0,
        0xffe1,
        0xffe2,
        0xffe3,
        0xffe4,
        0xffe5,
        0xffe6,
        0xffe8,
        0xffe9,
        0xffea,
        0xffeb,
        0xffec,
        0xffed,
        0xffee,
        0x01d6fc,
        0x01d6fd,
        0x01d6fe,
        0x01d6ff,
        0x01d700,
        0x01d701,
        0x01d702,
        0x01d703,
        0x01d704,
        0x01d705,
        0x01d706,
        0x01d707,
        0x01d708,
        0x01d709,
        0x01d70a,
        0x01d70b,
        0x01d70c,
        0x01d70d,
        0x01d70e,
        0x01d70f,
        0x01d710,
        0x01d711,
        0x01d712,
        0x01d713,
        0x01d714,
        0x01d715,
        0x01d716,
        0x01d717,
        0x01d718,
        0x01d719,
        0x01d71a,
        0x01d71b,
        0xc2a0,
        0xe28087,
        0xe280af,
        0xe281a0,
        0xefbbbf
    );

// utf8 version of above data
global $UTF8_SPECIAL_CHARS2;
if (empty($UTF8_SPECIAL_CHARS2))
    $UTF8_SPECIAL_CHARS2 = "\x1A" . ' !"#$%&\'()+,/;<=>?@[\]^`{|}~Â€ÂÂ‚ÂƒÂ„Â…Â†Â‡ÂˆÂ‰ÂŠÂ‹ÂŒÂÂŽÂÂÂ‘Â’Â“Â”Â•ï¿½' . 'ï¿½Â—Â˜Â™ÂšÂ›ÂœÂÂžÂŸÂ Â¡Â¢Â£Â¤Â¥Â¦Â§Â¨Â©ÂªÂ«Â¬Â­Â®Â¯Â°Â±Â²Â³Â´ÂµÂ¶Â·Â¸Â¹ÂºÂ»Â¼Â½ï¿½' . 'ï¿½Â¿Ã—Ã·Ë‡Ë˜Ë™ËšË›ËœËÌ€ÌÌƒÌ‰Ì£Î„Î…Î‡Ï–Ö°Ö±Ö²Ö³Ö´ÖµÖ¶Ö·Ö¸Ö¹Ö»Ö¼Ö½Ö¾Ö¿ï¿½' . 'ï¿½××‚×ƒ×³×´ØŒØ›ØŸÙ€Ù‹ÙŒÙÙŽÙÙÙ‘Ù’Ùªà¸¿â€Œâ€â€Žâ€â€“â€”â€•â€—â€˜â€™â€šâ€œâ€ï¿½' . 'ï¿½ï¿½â€ â€¡â€¢â€¦â€°â€²â€³â€¹â€ºâ„â‚§â‚ªâ‚«â‚¬â„–â„˜â„¢â„¦â„µâ†â†‘â†’â†“â†”â†•â†µ' . 'â‡â‡‘â‡’â‡“â‡”âˆ€âˆ‚âˆƒâˆ…âˆ†âˆ‡âˆˆâˆ‰âˆ‹âˆâˆ‘âˆ’âˆ•âˆ—âˆ™âˆšâˆâˆžâˆ âˆ§âˆ¨ï¿½' . 'ï¿½âˆªâˆ«âˆ´âˆ¼â‰…â‰ˆâ‰ â‰¡â‰¤â‰¥âŠ‚âŠƒâŠ„âŠ†âŠ‡âŠ•âŠ—âŠ¥â‹…âŒâŒ âŒ¡âŒ©âŒªâ‘©â”€ï¿½' . 'ï¿½ï¿½â”Œâ”â””â”˜â”œâ”¤â”¬â”´â”¼â•â•‘â•’â•“â•”â••â•–â•—â•˜â•™â•šâ•›â•œâ•â•žâ•Ÿâ• ' . 'â•¡â•¢â•£â•¤â•¥â•¦â•§â•¨â•©â•ªâ•«â•¬â–€â–„â–ˆâ–Œâ–â–‘â–’â–“â– â–²â–¼â—†â—Šâ—ï¿½' . 'ï¿½â˜…â˜Žâ˜›â˜žâ™ â™£â™¥â™¦âœâœ‚âœƒâœ„âœ†âœ‡âœˆâœ‰âœŒâœâœŽâœâœâœ‘âœ’âœ“âœ”âœ•ï¿½' . 'ï¿½ï¿½âœ—âœ˜âœ™âœšâœ›âœœâœâœžâœŸâœ âœ¡âœ¢âœ£âœ¤âœ¥âœ¦âœ§âœ©âœªâœ«âœ¬âœ­âœ®âœ¯âœ°âœ±' . 'âœ²âœ³âœ´âœµâœ¶âœ·âœ¸âœ¹âœºâœ»âœ¼âœ½âœ¾âœ¿â€ââ‚âƒâ„â…â†â‡âˆâ‰âŠâ‹ï¿½' . 'ï¿½âââ‘â’â–â˜â™âšâ›âœââžâ¡â¢â£â¤â¥â¦â§â¿âž‰âž“âž”âž˜âž™âžšï¿½' . 'ï¿½ï¿½âžœâžâžžâžŸâž âž¡âž¢âž£âž¤âž¥âž¦âž§âž¨âž©âžªâž«âž¬âž­âž®âž¯âž±âž²âž³âž´âžµâž¶' . 'âž·âž¸âž¹âžºâž»âž¼âž½âž¾' . 'ã€€ã€ã€‚ã€ƒã€ˆã€‰ã€Šã€‹ã€Œã€ã€Žã€ã€ã€‘ã€’ã€”ã€•ã€–ã€—ã€˜ã€™ã€šã€›ã€¶' . 'ï›™ï›šï››ï£—ï£˜ï£™ï£šï£›ï£œï£ï£žï£Ÿï£ ï£¡ï£¢ï££ï£¤ï£¥ï¿½' . 'ï¿½ï£§ï£¨ï£©ï£ªï£«ï£¬ï£­ï£®ï£¯ï£°ï£±ï£²ï£³ï£´ï£µï£¶ï£·ï£¸ï£¹ï£ºï£»ï£¼ï£½ï£¾ï¹¼ï¹½' . 'ï¼ï¼‚ï¼ƒï¼„ï¼…ï¼†ï¼‡ï¼ˆï¼‰ï¼Šï¼‹ï¼Œï¼ï¼Žï¼ï¼šï¼›ï¼œï¼ï¼žï¼Ÿï¼ ï¼»ï¼¼ï¼½ï¼¾ï½€ï½›ï½œï½ï½ž' . 'ï½Ÿï½ ï½¡ï½¢ï½£ï½¤ï½¥ï¿ ï¿¡ï¿¢ï¿£ï¿¤ï¿¥ï¿¦ï¿¨ï¿©ï¿ªï¿«ï¿¬ï¿­ï¿®' . 'ð›¼ð›½ð›¾ð›¿ðœ€ðœðœ‚ðœƒðœ„ðœ…ðœ†ðœ‡ðœˆðœ‰ðœŠðœ‹ðœŒðœðœŽðœðœðœ‘ðœ’ðœ“ðœ”ðœ•ðœ–ðœ—ðœ˜ðœ™ðœšðœ›' . ' â€‡â€¯â ï»¿';

/**
 * Romanization lookup table
 *
 * This lookup tables provides a way to transform strings written in a language
 * different from the ones based upon latin letters into plain ASCII.
 *
 * Please note: this is not a scientific transliteration table. It only works
 * oneway from nonlatin to ASCII and it works by simple character replacement
 * only. Specialities of each language are not supported.
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @author Vitaly Blokhin <vitinfo@vitn.com>
 * @link http://www.uconv.com/translit.htm
 * @author Bisqwit <bisqwit@iki.fi>
 * @link http://kanjidict.stc.cx/hiragana.php?src=2
 * @link http://www.translatum.gr/converter/greek-transliteration.htm
 * @link http://en.wikipedia.org/wiki/Royal_Thai_General_System_of_Transcription
 * @link http://www.btranslations.com/resources/romanization/korean.asp
 * @author Arthit Suriyawongkul <arthit@gmail.com>
 * @author Denis Scheither <amorphis@uni-bremen.de>
 * @author Eivind Morland <eivind.morland@gmail.com>
 */
global $UTF8_ROMANIZATION;
if (empty($UTF8_ROMANIZATION))
    $UTF8_ROMANIZATION = array(
        // scandinavian - differs from what we do in deaccent
        'Ã¥' => 'a',
        'Ã…' => 'A',
        'Ã¤' => 'a',
        'Ã„' => 'A',
        'Ã¶' => 'o',
        'Ã–' => 'O',
        
        // russian cyrillic
        'Ð°' => 'a',
        'Ð' => 'A',
        'Ð±' => 'b',
        'Ð‘' => 'B',
        'Ð²' => 'v',
        'Ð’' => 'V',
        'Ð³' => 'g',
        'Ð“' => 'G',
        'Ð´' => 'd',
        'Ð”' => 'D',
        'Ðµ' => 'e',
        'Ð•' => 'E',
        'Ñ‘' => 'jo',
        'Ð' => 'Jo',
        'Ð¶' => 'zh',
        'Ð–' => 'Zh',
        'Ð·' => 'z',
        'Ð—' => 'Z',
        'Ð¸' => 'i',
        'Ð˜' => 'I',
        'Ð¹' => 'j',
        'Ð™' => 'J',
        'Ðº' => 'k',
        'Ðš' => 'K',
        'Ð»' => 'l',
        'Ð›' => 'L',
        'Ð¼' => 'm',
        'Ðœ' => 'M',
        'Ð½' => 'n',
        'Ð' => 'N',
        'Ð¾' => 'o',
        'Ðž' => 'O',
        'Ð¿' => 'p',
        'ÐŸ' => 'P',
        'Ñ€' => 'r',
        'Ð ' => 'R',
        'Ñ' => 's',
        'Ð¡' => 'S',
        'Ñ‚' => 't',
        'Ð¢' => 'T',
        'Ñƒ' => 'u',
        'Ð£' => 'U',
        'Ñ„' => 'f',
        'Ð¤' => 'F',
        'Ñ…' => 'x',
        'Ð¥' => 'X',
        'Ñ†' => 'c',
        'Ð¦' => 'C',
        'Ñ‡' => 'ch',
        'Ð§' => 'Ch',
        'Ñˆ' => 'sh',
        'Ð¨' => 'Sh',
        'Ñ‰' => 'sch',
        'Ð©' => 'Sch',
        'ÑŠ' => '',
        'Ðª' => '',
        'Ñ‹' => 'y',
        'Ð«' => 'Y',
        'ÑŒ' => '',
        'Ð¬' => '',
        'Ñ' => 'eh',
        'Ð­' => 'Eh',
        'ÑŽ' => 'ju',
        'Ð®' => 'Ju',
        'Ñ' => 'ja',
        'Ð¯' => 'Ja',
        // Ukrainian cyrillic
        'Ò' => 'Gh',
        'Ò‘' => 'gh',
        'Ð„' => 'Je',
        'Ñ”' => 'je',
        'Ð†' => 'I',
        'Ñ–' => 'i',
        'Ð‡' => 'Ji',
        'Ñ—' => 'ji',
        // Georgian
        'áƒ' => 'a',
        'áƒ‘' => 'b',
        'áƒ’' => 'g',
        'áƒ“' => 'd',
        'áƒ”' => 'e',
        'áƒ•' => 'v',
        'áƒ–' => 'z',
        'áƒ—' => 'th',
        'áƒ˜' => 'i',
        'áƒ™' => 'p',
        'áƒš' => 'l',
        'áƒ›' => 'm',
        'áƒœ' => 'n',
        'áƒ' => 'o',
        'áƒž' => 'p',
        'áƒŸ' => 'zh',
        'áƒ ' => 'r',
        'áƒ¡' => 's',
        'áƒ¢' => 't',
        'áƒ£' => 'u',
        'áƒ¤' => 'ph',
        'áƒ¥' => 'kh',
        'áƒ¦' => 'gh',
        'áƒ§' => 'q',
        'áƒ¨' => 'sh',
        'áƒ©' => 'ch',
        'áƒª' => 'c',
        'áƒ«' => 'dh',
        'áƒ¬' => 'w',
        'áƒ­' => 'j',
        'áƒ®' => 'x',
        'áƒ¯' => 'jh',
        'áƒ°' => 'xh',
        // Sanskrit
        'à¤…' => 'a',
        'à¤†' => 'ah',
        'à¤‡' => 'i',
        'à¤ˆ' => 'ih',
        'à¤‰' => 'u',
        'à¤Š' => 'uh',
        'à¤‹' => 'ry',
        'à¥ ' => 'ryh',
        'à¤Œ' => 'ly',
        'à¥¡' => 'lyh',
        'à¤' => 'e',
        'à¤' => 'ay',
        'à¤“' => 'o',
        'à¤”' => 'aw',
        'à¤…à¤‚' => 'amh',
        'à¤…à¤ƒ' => 'aq',
        'à¤•' => 'k',
        'à¤–' => 'kh',
        'à¤—' => 'g',
        'à¤˜' => 'gh',
        'à¤™' => 'nh',
        'à¤š' => 'c',
        'à¤›' => 'ch',
        'à¤œ' => 'j',
        'à¤' => 'jh',
        'à¤ž' => 'ny',
        'à¤Ÿ' => 'tq',
        'à¤ ' => 'tqh',
        'à¤¡' => 'dq',
        'à¤¢' => 'dqh',
        'à¤£' => 'nq',
        'à¤¤' => 't',
        'à¤¥' => 'th',
        'à¤¦' => 'd',
        'à¤§' => 'dh',
        'à¤¨' => 'n',
        'à¤ª' => 'p',
        'à¤«' => 'ph',
        'à¤¬' => 'b',
        'à¤­' => 'bh',
        'à¤®' => 'm',
        'à¤¯' => 'z',
        'à¤°' => 'r',
        'à¤²' => 'l',
        'à¤µ' => 'v',
        'à¤¶' => 'sh',
        'à¤·' => 'sqh',
        'à¤¸' => 's',
        'à¤¹' => 'x',
        // Sanskrit diacritics
        'Ä€' => 'A',
        'Äª' => 'I',
        'Åª' => 'U',
        'á¹š' => 'R',
        'á¹œ' => 'R',
        'á¹„' => 'N',
        'Ã‘' => 'N',
        'á¹¬' => 'T',
        'á¸Œ' => 'D',
        'á¹†' => 'N',
        'Åš' => 'S',
        'á¹¢' => 'S',
        'á¹€' => 'M',
        'á¹‚' => 'M',
        'á¸¤' => 'H',
        'á¸¶' => 'L',
        'á¸¸' => 'L',
        'Ä' => 'a',
        'Ä«' => 'i',
        'Å«' => 'u',
        'á¹›' => 'r',
        'á¹' => 'r',
        'á¹…' => 'n',
        'Ã±' => 'n',
        'á¹­' => 't',
        'á¸' => 'd',
        'á¹‡' => 'n',
        'Å›' => 's',
        'á¹£' => 's',
        'á¹' => 'm',
        'á¹ƒ' => 'm',
        'á¸¥' => 'h',
        'á¸·' => 'l',
        'á¸¹' => 'l',
        // Hebrew
        '×' => 'a',
        '×‘' => 'b',
        '×’' => 'g',
        '×“' => 'd',
        '×”' => 'h',
        '×•' => 'v',
        '×–' => 'z',
        '×—' => 'kh',
        '×˜' => 'th',
        '×™' => 'y',
        '×š' => 'h',
        '×›' => 'k',
        '×œ' => 'l',
        '×' => 'm',
        '×ž' => 'm',
        '×Ÿ' => 'n',
        '× ' => 'n',
        '×¡' => 's',
        '×¢' => 'ah',
        '×£' => 'f',
        '×¤' => 'p',
        '×¥' => 'c',
        '×¦' => 'c',
        '×§' => 'q',
        '×¨' => 'r',
        '×©' => 'sh',
        '×ª' => 't',
        // Arabic
        'Ø§' => 'a',
        'Ø¨' => 'b',
        'Øª' => 't',
        'Ø«' => 'th',
        'Ø¬' => 'g',
        'Ø­' => 'xh',
        'Ø®' => 'x',
        'Ø¯' => 'd',
        'Ø°' => 'dh',
        'Ø±' => 'r',
        'Ø²' => 'z',
        'Ø³' => 's',
        'Ø´' => 'sh',
        'Øµ' => 's\'',
        'Ø¶' => 'd\'',
        'Ø·' => 't\'',
        'Ø¸' => 'z\'',
        'Ø¹' => 'y',
        'Øº' => 'gh',
        'Ù' => 'f',
        'Ù‚' => 'q',
        'Ùƒ' => 'k',
        'Ù„' => 'l',
        'Ù…' => 'm',
        'Ù†' => 'n',
        'Ù‡' => 'x\'',
        'Ùˆ' => 'u',
        'ÙŠ' => 'i',
        
        // Japanese characters (last update: 2008-05-09)
        
        // Japanese hiragana
        
        // 3 character syllables, ã£ doubles the consonant after
        'ã£ã¡ã‚ƒ' => 'ccha',
        'ã£ã¡ã‡' => 'cche',
        'ã£ã¡ã‚‡' => 'ccho',
        'ã£ã¡ã‚…' => 'cchu',
        'ã£ã³ã‚ƒ' => 'bbya',
        'ã£ã³ã‡' => 'bbye',
        'ã£ã³ãƒ' => 'bbyi',
        'ã£ã³ã‚‡' => 'bbyo',
        'ã£ã³ã‚…' => 'bbyu',
        'ã£ã´ã‚ƒ' => 'ppya',
        'ã£ã´ã‡' => 'ppye',
        'ã£ã´ãƒ' => 'ppyi',
        'ã£ã´ã‚‡' => 'ppyo',
        'ã£ã´ã‚…' => 'ppyu',
        'ã£ã¡ã‚ƒ' => 'ccha',
        'ã£ã¡ã‡' => 'cche',
        'ã£ã¡' => 'cchi',
        'ã£ã¡ã‚‡' => 'ccho',
        'ã£ã¡ã‚…' => 'cchu',
        // 'ã£ã²ã‚ƒ'=>'hya','ã£ã²ã‡'=>'hye','ã£ã²ãƒ'=>'hyi','ã£ã²ã‚‡'=>'hyo','ã£ã²ã‚…'=>'hyu',
        'ã£ãã‚ƒ' => 'kkya',
        'ã£ãã‡' => 'kkye',
        'ã£ããƒ' => 'kkyi',
        'ã£ãã‚‡' => 'kkyo',
        'ã£ãã‚…' => 'kkyu',
        'ã£ãŽã‚ƒ' => 'ggya',
        'ã£ãŽã‡' => 'ggye',
        'ã£ãŽãƒ' => 'ggyi',
        'ã£ãŽã‚‡' => 'ggyo',
        'ã£ãŽã‚…' => 'ggyu',
        'ã£ã¿ã‚ƒ' => 'mmya',
        'ã£ã¿ã‡' => 'mmye',
        'ã£ã¿ãƒ' => 'mmyi',
        'ã£ã¿ã‚‡' => 'mmyo',
        'ã£ã¿ã‚…' => 'mmyu',
        'ã£ã«ã‚ƒ' => 'nnya',
        'ã£ã«ã‡' => 'nnye',
        'ã£ã«ãƒ' => 'nnyi',
        'ã£ã«ã‚‡' => 'nnyo',
        'ã£ã«ã‚…' => 'nnyu',
        'ã£ã‚Šã‚ƒ' => 'rrya',
        'ã£ã‚Šã‡' => 'rrye',
        'ã£ã‚Šãƒ' => 'rryi',
        'ã£ã‚Šã‚‡' => 'rryo',
        'ã£ã‚Šã‚…' => 'rryu',
        'ã£ã—ã‚ƒ' => 'ssha',
        'ã£ã—ã‡' => 'sshe',
        'ã£ã—' => 'sshi',
        'ã£ã—ã‚‡' => 'ssho',
        'ã£ã—ã‚…' => 'sshu',
        
        // seperate hiragana 'n' ('n' + 'i' != 'ni', normally we would write "kon'nichi wa" but the apostrophe would be converted to _ anyway)
        'ã‚“ã‚' => 'n_a',
        'ã‚“ãˆ' => 'n_e',
        'ã‚“ã„' => 'n_i',
        'ã‚“ãŠ' => 'n_o',
        'ã‚“ã†' => 'n_u',
        'ã‚“ã‚„' => 'n_ya',
        'ã‚“ã‚ˆ' => 'n_yo',
        'ã‚“ã‚†' => 'n_yu',
        
        // 2 character syllables - normal
        'ãµã' => 'fa',
        'ãµã‡' => 'fe',
        'ãµãƒ' => 'fi',
        'ãµã‰' => 'fo',
        'ã¡ã‚ƒ' => 'cha',
        'ã¡ã‡' => 'che',
        'ã¡' => 'chi',
        'ã¡ã‚‡' => 'cho',
        'ã¡ã‚…' => 'chu',
        'ã²ã‚ƒ' => 'hya',
        'ã²ã‡' => 'hye',
        'ã²ãƒ' => 'hyi',
        'ã²ã‚‡' => 'hyo',
        'ã²ã‚…' => 'hyu',
        'ã³ã‚ƒ' => 'bya',
        'ã³ã‡' => 'bye',
        'ã³ãƒ' => 'byi',
        'ã³ã‚‡' => 'byo',
        'ã³ã‚…' => 'byu',
        'ã´ã‚ƒ' => 'pya',
        'ã´ã‡' => 'pye',
        'ã´ãƒ' => 'pyi',
        'ã´ã‚‡' => 'pyo',
        'ã´ã‚…' => 'pyu',
        'ãã‚ƒ' => 'kya',
        'ãã‡' => 'kye',
        'ããƒ' => 'kyi',
        'ãã‚‡' => 'kyo',
        'ãã‚…' => 'kyu',
        'ãŽã‚ƒ' => 'gya',
        'ãŽã‡' => 'gye',
        'ãŽãƒ' => 'gyi',
        'ãŽã‚‡' => 'gyo',
        'ãŽã‚…' => 'gyu',
        'ã¿ã‚ƒ' => 'mya',
        'ã¿ã‡' => 'mye',
        'ã¿ãƒ' => 'myi',
        'ã¿ã‚‡' => 'myo',
        'ã¿ã‚…' => 'myu',
        'ã«ã‚ƒ' => 'nya',
        'ã«ã‡' => 'nye',
        'ã«ãƒ' => 'nyi',
        'ã«ã‚‡' => 'nyo',
        'ã«ã‚…' => 'nyu',
        'ã‚Šã‚ƒ' => 'rya',
        'ã‚Šã‡' => 'rye',
        'ã‚Šãƒ' => 'ryi',
        'ã‚Šã‚‡' => 'ryo',
        'ã‚Šã‚…' => 'ryu',
        'ã—ã‚ƒ' => 'sha',
        'ã—ã‡' => 'she',
        'ã—' => 'shi',
        'ã—ã‚‡' => 'sho',
        'ã—ã‚…' => 'shu',
        'ã˜ã‚ƒ' => 'ja',
        'ã˜ã‡' => 'je',
        'ã˜ã‚‡' => 'jo',
        'ã˜ã‚…' => 'ju',
        'ã†ã‡' => 'we',
        'ã†ãƒ' => 'wi',
        'ã„ã‡' => 'ye',
        
        // 2 character syllables, ã£ doubles the consonant after
        'ã£ã°' => 'bba',
        'ã£ã¹' => 'bbe',
        'ã£ã³' => 'bbi',
        'ã£ã¼' => 'bbo',
        'ã£ã¶' => 'bbu',
        'ã£ã±' => 'ppa',
        'ã£ãº' => 'ppe',
        'ã£ã´' => 'ppi',
        'ã£ã½' => 'ppo',
        'ã£ã·' => 'ppu',
        'ã£ãŸ' => 'tta',
        'ã£ã¦' => 'tte',
        'ã£ã¡' => 'cchi',
        'ã£ã¨' => 'tto',
        'ã£ã¤' => 'ttsu',
        'ã£ã ' => 'dda',
        'ã£ã§' => 'dde',
        'ã£ã¢' => 'ddi',
        'ã£ã©' => 'ddo',
        'ã£ã¥' => 'ddu',
        'ã£ãŒ' => 'gga',
        'ã£ã’' => 'gge',
        'ã£ãŽ' => 'ggi',
        'ã£ã”' => 'ggo',
        'ã£ã' => 'ggu',
        'ã£ã‹' => 'kka',
        'ã£ã‘' => 'kke',
        'ã£ã' => 'kki',
        'ã£ã“' => 'kko',
        'ã£ã' => 'kku',
        'ã£ã¾' => 'mma',
        'ã£ã‚' => 'mme',
        'ã£ã¿' => 'mmi',
        'ã£ã‚‚' => 'mmo',
        'ã£ã‚€' => 'mmu',
        'ã£ãª' => 'nna',
        'ã£ã­' => 'nne',
        'ã£ã«' => 'nni',
        'ã£ã®' => 'nno',
        'ã£ã¬' => 'nnu',
        'ã£ã‚‰' => 'rra',
        'ã£ã‚Œ' => 'rre',
        'ã£ã‚Š' => 'rri',
        'ã£ã‚' => 'rro',
        'ã£ã‚‹' => 'rru',
        'ã£ã•' => 'ssa',
        'ã£ã›' => 'sse',
        'ã£ã—' => 'sshi',
        'ã£ã' => 'sso',
        'ã£ã™' => 'ssu',
        'ã£ã–' => 'zza',
        'ã£ãœ' => 'zze',
        'ã£ã˜' => 'jji',
        'ã£ãž' => 'zzo',
        'ã£ãš' => 'zzu',
        
        // 1 character syllabels
        'ã‚' => 'a',
        'ãˆ' => 'e',
        'ã„' => 'i',
        'ãŠ' => 'o',
        'ã†' => 'u',
        'ã‚“' => 'n',
        'ã¯' => 'ha',
        'ã¸' => 'he',
        'ã²' => 'hi',
        'ã»' => 'ho',
        'ãµ' => 'fu',
        'ã°' => 'ba',
        'ã¹' => 'be',
        'ã³' => 'bi',
        'ã¼' => 'bo',
        'ã¶' => 'bu',
        'ã±' => 'pa',
        'ãº' => 'pe',
        'ã´' => 'pi',
        'ã½' => 'po',
        'ã·' => 'pu',
        'ãŸ' => 'ta',
        'ã¦' => 'te',
        'ã¡' => 'chi',
        'ã¨' => 'to',
        'ã¤' => 'tsu',
        'ã ' => 'da',
        'ã§' => 'de',
        'ã¢' => 'di',
        'ã©' => 'do',
        'ã¥' => 'du',
        'ãŒ' => 'ga',
        'ã’' => 'ge',
        'ãŽ' => 'gi',
        'ã”' => 'go',
        'ã' => 'gu',
        'ã‹' => 'ka',
        'ã‘' => 'ke',
        'ã' => 'ki',
        'ã“' => 'ko',
        'ã' => 'ku',
        'ã¾' => 'ma',
        'ã‚' => 'me',
        'ã¿' => 'mi',
        'ã‚‚' => 'mo',
        'ã‚€' => 'mu',
        'ãª' => 'na',
        'ã­' => 'ne',
        'ã«' => 'ni',
        'ã®' => 'no',
        'ã¬' => 'nu',
        'ã‚‰' => 'ra',
        'ã‚Œ' => 're',
        'ã‚Š' => 'ri',
        'ã‚' => 'ro',
        'ã‚‹' => 'ru',
        'ã•' => 'sa',
        'ã›' => 'se',
        'ã—' => 'shi',
        'ã' => 'so',
        'ã™' => 'su',
        'ã‚' => 'wa',
        'ã‚’' => 'wo',
        'ã–' => 'za',
        'ãœ' => 'ze',
        'ã˜' => 'ji',
        'ãž' => 'zo',
        'ãš' => 'zu',
        'ã‚„' => 'ya',
        'ã‚ˆ' => 'yo',
        'ã‚†' => 'yu',
        // old characters
        'ã‚‘' => 'we',
        'ã‚' => 'wi',
        
        // convert what's left (probably only kicks in when something's missing above)
        // 'ã'=>'a','ã‡'=>'e','ãƒ'=>'i','ã‰'=>'o','ã…'=>'u',
        // 'ã‚ƒ'=>'ya','ã‚‡'=>'yo','ã‚…'=>'yu',
        
        // never seen one of those (disabled for the moment)
        // 'ãƒ´ã'=>'va','ãƒ´ã‡'=>'ve','ãƒ´ãƒ'=>'vi','ãƒ´ã‰'=>'vo','ãƒ´'=>'vu',
        // 'ã§ã‚ƒ'=>'dha','ã§ã‡'=>'dhe','ã§ãƒ'=>'dhi','ã§ã‚‡'=>'dho','ã§ã‚…'=>'dhu',
        // 'ã©ã'=>'dwa','ã©ã‡'=>'dwe','ã©ãƒ'=>'dwi','ã©ã‰'=>'dwo','ã©ã…'=>'dwu',
        // 'ã¢ã‚ƒ'=>'dya','ã¢ã‡'=>'dye','ã¢ãƒ'=>'dyi','ã¢ã‚‡'=>'dyo','ã¢ã‚…'=>'dyu',
        // 'ãµã'=>'fwa','ãµã‡'=>'fwe','ãµãƒ'=>'fwi','ãµã‰'=>'fwo','ãµã…'=>'fwu',
        // 'ãµã‚ƒ'=>'fya','ãµã‡'=>'fye','ãµãƒ'=>'fyi','ãµã‚‡'=>'fyo','ãµã‚…'=>'fyu',
        // 'ã™ã'=>'swa','ã™ã‡'=>'swe','ã™ãƒ'=>'swi','ã™ã‰'=>'swo','ã™ã…'=>'swu',
        // 'ã¦ã‚ƒ'=>'tha','ã¦ã‡'=>'the','ã¦ãƒ'=>'thi','ã¦ã‚‡'=>'tho','ã¦ã‚…'=>'thu',
        // 'ã¤ã‚ƒ'=>'tsa','ã¤ã‡'=>'tse','ã¤ãƒ'=>'tsi','ã¤ã‚‡'=>'tso','ã¤'=>'tsu',
        // 'ã¨ã'=>'twa','ã¨ã‡'=>'twe','ã¨ãƒ'=>'twi','ã¨ã‰'=>'two','ã¨ã…'=>'twu',
        // 'ãƒ´ã‚ƒ'=>'vya','ãƒ´ã‡'=>'vye','ãƒ´ãƒ'=>'vyi','ãƒ´ã‚‡'=>'vyo','ãƒ´ã‚…'=>'vyu',
        // 'ã†ã'=>'wha','ã†ã‡'=>'whe','ã†ãƒ'=>'whi','ã†ã‰'=>'who','ã†ã…'=>'whu',
        // 'ã˜ã‚ƒ'=>'zha','ã˜ã‡'=>'zhe','ã˜ãƒ'=>'zhi','ã˜ã‚‡'=>'zho','ã˜ã‚…'=>'zhu',
        // 'ã˜ã‚ƒ'=>'zya','ã˜ã‡'=>'zye','ã˜ãƒ'=>'zyi','ã˜ã‚‡'=>'zyo','ã˜ã‚…'=>'zyu',
        
        // 'spare' characters from other romanization systems
        // 'ã '=>'da','ã§'=>'de','ã¢'=>'di','ã©'=>'do','ã¥'=>'du',
        // 'ã‚‰'=>'la','ã‚Œ'=>'le','ã‚Š'=>'li','ã‚'=>'lo','ã‚‹'=>'lu',
        // 'ã•'=>'sa','ã›'=>'se','ã—'=>'si','ã'=>'so','ã™'=>'su',
        // 'ã¡ã‚ƒ'=>'cya','ã¡ã‡'=>'cye','ã¡ãƒ'=>'cyi','ã¡ã‚‡'=>'cyo','ã¡ã‚…'=>'cyu',
        // 'ã˜ã‚ƒ'=>'jya','ã˜ã‡'=>'jye','ã˜ãƒ'=>'jyi','ã˜ã‚‡'=>'jyo','ã˜ã‚…'=>'jyu',
        // 'ã‚Šã‚ƒ'=>'lya','ã‚Šã‡'=>'lye','ã‚Šãƒ'=>'lyi','ã‚Šã‚‡'=>'lyo','ã‚Šã‚…'=>'lyu',
        // 'ã—ã‚ƒ'=>'sya','ã—ã‡'=>'sye','ã—ãƒ'=>'syi','ã—ã‚‡'=>'syo','ã—ã‚…'=>'syu',
        // 'ã¡ã‚ƒ'=>'tya','ã¡ã‡'=>'tye','ã¡ãƒ'=>'tyi','ã¡ã‚‡'=>'tyo','ã¡ã‚…'=>'tyu',
        // 'ã—'=>'ci',,ã„'=>'yi','ã¢'=>'dzi',
        // 'ã£ã˜ã‚ƒ'=>'jja','ã£ã˜ã‡'=>'jje','ã£ã˜'=>'jji','ã£ã˜ã‚‡'=>'jjo','ã£ã˜ã‚…'=>'jju',
        
        // Japanese katakana
        
        // 4 character syllables: ãƒƒ doubles the consonant after, ãƒ¼ doubles the vowel before (usualy written with macron, but we don't want that in our URLs)
        'ãƒƒãƒ“ãƒ£ãƒ¼' => 'bbyaa',
        'ãƒƒãƒ“ã‚§ãƒ¼' => 'bbyee',
        'ãƒƒãƒ“ã‚£ãƒ¼' => 'bbyii',
        'ãƒƒãƒ“ãƒ§ãƒ¼' => 'bbyoo',
        'ãƒƒãƒ“ãƒ¥ãƒ¼' => 'bbyuu',
        'ãƒƒãƒ”ãƒ£ãƒ¼' => 'ppyaa',
        'ãƒƒãƒ”ã‚§ãƒ¼' => 'ppyee',
        'ãƒƒãƒ”ã‚£ãƒ¼' => 'ppyii',
        'ãƒƒãƒ”ãƒ§ãƒ¼' => 'ppyoo',
        'ãƒƒãƒ”ãƒ¥ãƒ¼' => 'ppyuu',
        'ãƒƒã‚­ãƒ£ãƒ¼' => 'kkyaa',
        'ãƒƒã‚­ã‚§ãƒ¼' => 'kkyee',
        'ãƒƒã‚­ã‚£ãƒ¼' => 'kkyii',
        'ãƒƒã‚­ãƒ§ãƒ¼' => 'kkyoo',
        'ãƒƒã‚­ãƒ¥ãƒ¼' => 'kkyuu',
        'ãƒƒã‚®ãƒ£ãƒ¼' => 'ggyaa',
        'ãƒƒã‚®ã‚§ãƒ¼' => 'ggyee',
        'ãƒƒã‚®ã‚£ãƒ¼' => 'ggyii',
        'ãƒƒã‚®ãƒ§ãƒ¼' => 'ggyoo',
        'ãƒƒã‚®ãƒ¥ãƒ¼' => 'ggyuu',
        'ãƒƒãƒŸãƒ£ãƒ¼' => 'mmyaa',
        'ãƒƒãƒŸã‚§ãƒ¼' => 'mmyee',
        'ãƒƒãƒŸã‚£ãƒ¼' => 'mmyii',
        'ãƒƒãƒŸãƒ§ãƒ¼' => 'mmyoo',
        'ãƒƒãƒŸãƒ¥ãƒ¼' => 'mmyuu',
        'ãƒƒãƒ‹ãƒ£ãƒ¼' => 'nnyaa',
        'ãƒƒãƒ‹ã‚§ãƒ¼' => 'nnyee',
        'ãƒƒãƒ‹ã‚£ãƒ¼' => 'nnyii',
        'ãƒƒãƒ‹ãƒ§ãƒ¼' => 'nnyoo',
        'ãƒƒãƒ‹ãƒ¥ãƒ¼' => 'nnyuu',
        'ãƒƒãƒªãƒ£ãƒ¼' => 'rryaa',
        'ãƒƒãƒªã‚§ãƒ¼' => 'rryee',
        'ãƒƒãƒªã‚£ãƒ¼' => 'rryii',
        'ãƒƒãƒªãƒ§ãƒ¼' => 'rryoo',
        'ãƒƒãƒªãƒ¥ãƒ¼' => 'rryuu',
        'ãƒƒã‚·ãƒ£ãƒ¼' => 'sshaa',
        'ãƒƒã‚·ã‚§ãƒ¼' => 'sshee',
        'ãƒƒã‚·ãƒ¼' => 'sshii',
        'ãƒƒã‚·ãƒ§ãƒ¼' => 'sshoo',
        'ãƒƒã‚·ãƒ¥ãƒ¼' => 'sshuu',
        'ãƒƒãƒãƒ£ãƒ¼' => 'cchaa',
        'ãƒƒãƒã‚§ãƒ¼' => 'cchee',
        'ãƒƒãƒãƒ¼' => 'cchii',
        'ãƒƒãƒãƒ§ãƒ¼' => 'cchoo',
        'ãƒƒãƒãƒ¥ãƒ¼' => 'cchuu',
        'ãƒƒãƒ†ã‚£ãƒ¼' => 'ttii',
        'ãƒƒãƒ‚ã‚£ãƒ¼' => 'ddii',
        
        // 3 character syllables - doubled vowels
        'ãƒ•ã‚¡ãƒ¼' => 'faa',
        'ãƒ•ã‚§ãƒ¼' => 'fee',
        'ãƒ•ã‚£ãƒ¼' => 'fii',
        'ãƒ•ã‚©ãƒ¼' => 'foo',
        'ãƒ•ãƒ£ãƒ¼' => 'fyaa',
        'ãƒ•ã‚§ãƒ¼' => 'fyee',
        'ãƒ•ã‚£ãƒ¼' => 'fyii',
        'ãƒ•ãƒ§ãƒ¼' => 'fyoo',
        'ãƒ•ãƒ¥ãƒ¼' => 'fyuu',
        'ãƒ’ãƒ£ãƒ¼' => 'hyaa',
        'ãƒ’ã‚§ãƒ¼' => 'hyee',
        'ãƒ’ã‚£ãƒ¼' => 'hyii',
        'ãƒ’ãƒ§ãƒ¼' => 'hyoo',
        'ãƒ’ãƒ¥ãƒ¼' => 'hyuu',
        'ãƒ“ãƒ£ãƒ¼' => 'byaa',
        'ãƒ“ã‚§ãƒ¼' => 'byee',
        'ãƒ“ã‚£ãƒ¼' => 'byii',
        'ãƒ“ãƒ§ãƒ¼' => 'byoo',
        'ãƒ“ãƒ¥ãƒ¼' => 'byuu',
        'ãƒ”ãƒ£ãƒ¼' => 'pyaa',
        'ãƒ”ã‚§ãƒ¼' => 'pyee',
        'ãƒ”ã‚£ãƒ¼' => 'pyii',
        'ãƒ”ãƒ§ãƒ¼' => 'pyoo',
        'ãƒ”ãƒ¥ãƒ¼' => 'pyuu',
        'ã‚­ãƒ£ãƒ¼' => 'kyaa',
        'ã‚­ã‚§ãƒ¼' => 'kyee',
        'ã‚­ã‚£ãƒ¼' => 'kyii',
        'ã‚­ãƒ§ãƒ¼' => 'kyoo',
        'ã‚­ãƒ¥ãƒ¼' => 'kyuu',
        'ã‚®ãƒ£ãƒ¼' => 'gyaa',
        'ã‚®ã‚§ãƒ¼' => 'gyee',
        'ã‚®ã‚£ãƒ¼' => 'gyii',
        'ã‚®ãƒ§ãƒ¼' => 'gyoo',
        'ã‚®ãƒ¥ãƒ¼' => 'gyuu',
        'ãƒŸãƒ£ãƒ¼' => 'myaa',
        'ãƒŸã‚§ãƒ¼' => 'myee',
        'ãƒŸã‚£ãƒ¼' => 'myii',
        'ãƒŸãƒ§ãƒ¼' => 'myoo',
        'ãƒŸãƒ¥ãƒ¼' => 'myuu',
        'ãƒ‹ãƒ£ãƒ¼' => 'nyaa',
        'ãƒ‹ã‚§ãƒ¼' => 'nyee',
        'ãƒ‹ã‚£ãƒ¼' => 'nyii',
        'ãƒ‹ãƒ§ãƒ¼' => 'nyoo',
        'ãƒ‹ãƒ¥ãƒ¼' => 'nyuu',
        'ãƒªãƒ£ãƒ¼' => 'ryaa',
        'ãƒªã‚§ãƒ¼' => 'ryee',
        'ãƒªã‚£ãƒ¼' => 'ryii',
        'ãƒªãƒ§ãƒ¼' => 'ryoo',
        'ãƒªãƒ¥ãƒ¼' => 'ryuu',
        'ã‚·ãƒ£ãƒ¼' => 'shaa',
        'ã‚·ã‚§ãƒ¼' => 'shee',
        'ã‚·ãƒ¼' => 'shii',
        'ã‚·ãƒ§ãƒ¼' => 'shoo',
        'ã‚·ãƒ¥ãƒ¼' => 'shuu',
        'ã‚¸ãƒ£ãƒ¼' => 'jaa',
        'ã‚¸ã‚§ãƒ¼' => 'jee',
        'ã‚¸ãƒ¼' => 'jii',
        'ã‚¸ãƒ§ãƒ¼' => 'joo',
        'ã‚¸ãƒ¥ãƒ¼' => 'juu',
        'ã‚¹ã‚¡ãƒ¼' => 'swaa',
        'ã‚¹ã‚§ãƒ¼' => 'swee',
        'ã‚¹ã‚£ãƒ¼' => 'swii',
        'ã‚¹ã‚©ãƒ¼' => 'swoo',
        'ã‚¹ã‚¥ãƒ¼' => 'swuu',
        'ãƒ‡ã‚¡ãƒ¼' => 'daa',
        'ãƒ‡ã‚§ãƒ¼' => 'dee',
        'ãƒ‡ã‚£ãƒ¼' => 'dii',
        'ãƒ‡ã‚©ãƒ¼' => 'doo',
        'ãƒ‡ã‚¥ãƒ¼' => 'duu',
        'ãƒãƒ£ãƒ¼' => 'chaa',
        'ãƒã‚§ãƒ¼' => 'chee',
        'ãƒãƒ¼' => 'chii',
        'ãƒãƒ§ãƒ¼' => 'choo',
        'ãƒãƒ¥ãƒ¼' => 'chuu',
        'ãƒ‚ãƒ£ãƒ¼' => 'dyaa',
        'ãƒ‚ã‚§ãƒ¼' => 'dyee',
        'ãƒ‚ã‚£ãƒ¼' => 'dyii',
        'ãƒ‚ãƒ§ãƒ¼' => 'dyoo',
        'ãƒ‚ãƒ¥ãƒ¼' => 'dyuu',
        'ãƒ„ãƒ£ãƒ¼' => 'tsaa',
        'ãƒ„ã‚§ãƒ¼' => 'tsee',
        'ãƒ„ã‚£ãƒ¼' => 'tsii',
        'ãƒ„ãƒ§ãƒ¼' => 'tsoo',
        'ãƒ„ãƒ¼' => 'tsuu',
        'ãƒˆã‚¡ãƒ¼' => 'twaa',
        'ãƒˆã‚§ãƒ¼' => 'twee',
        'ãƒˆã‚£ãƒ¼' => 'twii',
        'ãƒˆã‚©ãƒ¼' => 'twoo',
        'ãƒˆã‚¥ãƒ¼' => 'twuu',
        'ãƒ‰ã‚¡ãƒ¼' => 'dwaa',
        'ãƒ‰ã‚§ãƒ¼' => 'dwee',
        'ãƒ‰ã‚£ãƒ¼' => 'dwii',
        'ãƒ‰ã‚©ãƒ¼' => 'dwoo',
        'ãƒ‰ã‚¥ãƒ¼' => 'dwuu',
        'ã‚¦ã‚¡ãƒ¼' => 'whaa',
        'ã‚¦ã‚§ãƒ¼' => 'whee',
        'ã‚¦ã‚£ãƒ¼' => 'whii',
        'ã‚¦ã‚©ãƒ¼' => 'whoo',
        'ã‚¦ã‚¥ãƒ¼' => 'whuu',
        'ãƒ´ãƒ£ãƒ¼' => 'vyaa',
        'ãƒ´ã‚§ãƒ¼' => 'vyee',
        'ãƒ´ã‚£ãƒ¼' => 'vyii',
        'ãƒ´ãƒ§ãƒ¼' => 'vyoo',
        'ãƒ´ãƒ¥ãƒ¼' => 'vyuu',
        'ãƒ´ã‚¡ãƒ¼' => 'vaa',
        'ãƒ´ã‚§ãƒ¼' => 'vee',
        'ãƒ´ã‚£ãƒ¼' => 'vii',
        'ãƒ´ã‚©ãƒ¼' => 'voo',
        'ãƒ´ãƒ¼' => 'vuu',
        'ã‚¦ã‚§ãƒ¼' => 'wee',
        'ã‚¦ã‚£ãƒ¼' => 'wii',
        'ã‚¤ã‚§ãƒ¼' => 'yee',
        'ãƒ†ã‚£ãƒ¼' => 'tii',
        'ãƒ‚ã‚£ãƒ¼' => 'dii',
        
        // 3 character syllables - doubled consonants
        'ãƒƒãƒ“ãƒ£' => 'bbya',
        'ãƒƒãƒ“ã‚§' => 'bbye',
        'ãƒƒãƒ“ã‚£' => 'bbyi',
        'ãƒƒãƒ“ãƒ§' => 'bbyo',
        'ãƒƒãƒ“ãƒ¥' => 'bbyu',
        'ãƒƒãƒ”ãƒ£' => 'ppya',
        'ãƒƒãƒ”ã‚§' => 'ppye',
        'ãƒƒãƒ”ã‚£' => 'ppyi',
        'ãƒƒãƒ”ãƒ§' => 'ppyo',
        'ãƒƒãƒ”ãƒ¥' => 'ppyu',
        'ãƒƒã‚­ãƒ£' => 'kkya',
        'ãƒƒã‚­ã‚§' => 'kkye',
        'ãƒƒã‚­ã‚£' => 'kkyi',
        'ãƒƒã‚­ãƒ§' => 'kkyo',
        'ãƒƒã‚­ãƒ¥' => 'kkyu',
        'ãƒƒã‚®ãƒ£' => 'ggya',
        'ãƒƒã‚®ã‚§' => 'ggye',
        'ãƒƒã‚®ã‚£' => 'ggyi',
        'ãƒƒã‚®ãƒ§' => 'ggyo',
        'ãƒƒã‚®ãƒ¥' => 'ggyu',
        'ãƒƒãƒŸãƒ£' => 'mmya',
        'ãƒƒãƒŸã‚§' => 'mmye',
        'ãƒƒãƒŸã‚£' => 'mmyi',
        'ãƒƒãƒŸãƒ§' => 'mmyo',
        'ãƒƒãƒŸãƒ¥' => 'mmyu',
        'ãƒƒãƒ‹ãƒ£' => 'nnya',
        'ãƒƒãƒ‹ã‚§' => 'nnye',
        'ãƒƒãƒ‹ã‚£' => 'nnyi',
        'ãƒƒãƒ‹ãƒ§' => 'nnyo',
        'ãƒƒãƒ‹ãƒ¥' => 'nnyu',
        'ãƒƒãƒªãƒ£' => 'rrya',
        'ãƒƒãƒªã‚§' => 'rrye',
        'ãƒƒãƒªã‚£' => 'rryi',
        'ãƒƒãƒªãƒ§' => 'rryo',
        'ãƒƒãƒªãƒ¥' => 'rryu',
        'ãƒƒã‚·ãƒ£' => 'ssha',
        'ãƒƒã‚·ã‚§' => 'sshe',
        'ãƒƒã‚·' => 'sshi',
        'ãƒƒã‚·ãƒ§' => 'ssho',
        'ãƒƒã‚·ãƒ¥' => 'sshu',
        'ãƒƒãƒãƒ£' => 'ccha',
        'ãƒƒãƒã‚§' => 'cche',
        'ãƒƒãƒ' => 'cchi',
        'ãƒƒãƒãƒ§' => 'ccho',
        'ãƒƒãƒãƒ¥' => 'cchu',
        'ãƒƒãƒ†ã‚£' => 'tti',
        'ãƒƒãƒ‚ã‚£' => 'ddi',
        
        // 3 character syllables - doubled vowel and consonants
        'ãƒƒãƒãƒ¼' => 'bbaa',
        'ãƒƒãƒ™ãƒ¼' => 'bbee',
        'ãƒƒãƒ“ãƒ¼' => 'bbii',
        'ãƒƒãƒœãƒ¼' => 'bboo',
        'ãƒƒãƒ–ãƒ¼' => 'bbuu',
        'ãƒƒãƒ‘ãƒ¼' => 'ppaa',
        'ãƒƒãƒšãƒ¼' => 'ppee',
        'ãƒƒãƒ”ãƒ¼' => 'ppii',
        'ãƒƒãƒãƒ¼' => 'ppoo',
        'ãƒƒãƒ—ãƒ¼' => 'ppuu',
        'ãƒƒã‚±ãƒ¼' => 'kkee',
        'ãƒƒã‚­ãƒ¼' => 'kkii',
        'ãƒƒã‚³ãƒ¼' => 'kkoo',
        'ãƒƒã‚¯ãƒ¼' => 'kkuu',
        'ãƒƒã‚«ãƒ¼' => 'kkaa',
        'ãƒƒã‚¬ãƒ¼' => 'ggaa',
        'ãƒƒã‚²ãƒ¼' => 'ggee',
        'ãƒƒã‚®ãƒ¼' => 'ggii',
        'ãƒƒã‚´ãƒ¼' => 'ggoo',
        'ãƒƒã‚°ãƒ¼' => 'gguu',
        'ãƒƒãƒžãƒ¼' => 'maa',
        'ãƒƒãƒ¡ãƒ¼' => 'mee',
        'ãƒƒãƒŸãƒ¼' => 'mii',
        'ãƒƒãƒ¢ãƒ¼' => 'moo',
        'ãƒƒãƒ ãƒ¼' => 'muu',
        'ãƒƒãƒŠãƒ¼' => 'nnaa',
        'ãƒƒãƒãƒ¼' => 'nnee',
        'ãƒƒãƒ‹ãƒ¼' => 'nnii',
        'ãƒƒãƒŽãƒ¼' => 'nnoo',
        'ãƒƒãƒŒãƒ¼' => 'nnuu',
        'ãƒƒãƒ©ãƒ¼' => 'rraa',
        'ãƒƒãƒ¬ãƒ¼' => 'rree',
        'ãƒƒãƒªãƒ¼' => 'rrii',
        'ãƒƒãƒ­ãƒ¼' => 'rroo',
        'ãƒƒãƒ«ãƒ¼' => 'rruu',
        'ãƒƒã‚µãƒ¼' => 'ssaa',
        'ãƒƒã‚»ãƒ¼' => 'ssee',
        'ãƒƒã‚·ãƒ¼' => 'sshii',
        'ãƒƒã‚½ãƒ¼' => 'ssoo',
        'ãƒƒã‚¹ãƒ¼' => 'ssuu',
        'ãƒƒã‚¶ãƒ¼' => 'zzaa',
        'ãƒƒã‚¼ãƒ¼' => 'zzee',
        'ãƒƒã‚¸ãƒ¼' => 'jjii',
        'ãƒƒã‚¾ãƒ¼' => 'zzoo',
        'ãƒƒã‚ºãƒ¼' => 'zzuu',
        'ãƒƒã‚¿ãƒ¼' => 'ttaa',
        'ãƒƒãƒ†ãƒ¼' => 'ttee',
        'ãƒƒãƒãƒ¼' => 'chii',
        'ãƒƒãƒˆãƒ¼' => 'ttoo',
        'ãƒƒãƒ„ãƒ¼' => 'ttsuu',
        'ãƒƒãƒ€ãƒ¼' => 'ddaa',
        'ãƒƒãƒ‡ãƒ¼' => 'ddee',
        'ãƒƒãƒ‚ãƒ¼' => 'ddii',
        'ãƒƒãƒ‰ãƒ¼' => 'ddoo',
        'ãƒƒãƒ…ãƒ¼' => 'dduu',
        
        // 2 character syllables - normal
        'ãƒ•ã‚¡' => 'fa',
        'ãƒ•ã‚§' => 'fe',
        'ãƒ•ã‚£' => 'fi',
        'ãƒ•ã‚©' => 'fo',
        'ãƒ•ã‚¥' => 'fu',
        // 'ãƒ•ãƒ£'=>'fya','ãƒ•ã‚§'=>'fye','ãƒ•ã‚£'=>'fyi','ãƒ•ãƒ§'=>'fyo','ãƒ•ãƒ¥'=>'fyu',
        'ãƒ•ãƒ£' => 'fa',
        'ãƒ•ã‚§' => 'fe',
        'ãƒ•ã‚£' => 'fi',
        'ãƒ•ãƒ§' => 'fo',
        'ãƒ•ãƒ¥' => 'fu',
        'ãƒ’ãƒ£' => 'hya',
        'ãƒ’ã‚§' => 'hye',
        'ãƒ’ã‚£' => 'hyi',
        'ãƒ’ãƒ§' => 'hyo',
        'ãƒ’ãƒ¥' => 'hyu',
        'ãƒ“ãƒ£' => 'bya',
        'ãƒ“ã‚§' => 'bye',
        'ãƒ“ã‚£' => 'byi',
        'ãƒ“ãƒ§' => 'byo',
        'ãƒ“ãƒ¥' => 'byu',
        'ãƒ”ãƒ£' => 'pya',
        'ãƒ”ã‚§' => 'pye',
        'ãƒ”ã‚£' => 'pyi',
        'ãƒ”ãƒ§' => 'pyo',
        'ãƒ”ãƒ¥' => 'pyu',
        'ã‚­ãƒ£' => 'kya',
        'ã‚­ã‚§' => 'kye',
        'ã‚­ã‚£' => 'kyi',
        'ã‚­ãƒ§' => 'kyo',
        'ã‚­ãƒ¥' => 'kyu',
        'ã‚®ãƒ£' => 'gya',
        'ã‚®ã‚§' => 'gye',
        'ã‚®ã‚£' => 'gyi',
        'ã‚®ãƒ§' => 'gyo',
        'ã‚®ãƒ¥' => 'gyu',
        'ãƒŸãƒ£' => 'mya',
        'ãƒŸã‚§' => 'mye',
        'ãƒŸã‚£' => 'myi',
        'ãƒŸãƒ§' => 'myo',
        'ãƒŸãƒ¥' => 'myu',
        'ãƒ‹ãƒ£' => 'nya',
        'ãƒ‹ã‚§' => 'nye',
        'ãƒ‹ã‚£' => 'nyi',
        'ãƒ‹ãƒ§' => 'nyo',
        'ãƒ‹ãƒ¥' => 'nyu',
        'ãƒªãƒ£' => 'rya',
        'ãƒªã‚§' => 'rye',
        'ãƒªã‚£' => 'ryi',
        'ãƒªãƒ§' => 'ryo',
        'ãƒªãƒ¥' => 'ryu',
        'ã‚·ãƒ£' => 'sha',
        'ã‚·ã‚§' => 'she',
        'ã‚·ãƒ§' => 'sho',
        'ã‚·ãƒ¥' => 'shu',
        'ã‚¸ãƒ£' => 'ja',
        'ã‚¸ã‚§' => 'je',
        'ã‚¸ãƒ§' => 'jo',
        'ã‚¸ãƒ¥' => 'ju',
        'ã‚¹ã‚¡' => 'swa',
        'ã‚¹ã‚§' => 'swe',
        'ã‚¹ã‚£' => 'swi',
        'ã‚¹ã‚©' => 'swo',
        'ã‚¹ã‚¥' => 'swu',
        'ãƒ‡ã‚¡' => 'da',
        'ãƒ‡ã‚§' => 'de',
        'ãƒ‡ã‚£' => 'di',
        'ãƒ‡ã‚©' => 'do',
        'ãƒ‡ã‚¥' => 'du',
        'ãƒãƒ£' => 'cha',
        'ãƒã‚§' => 'che',
        'ãƒ' => 'chi',
        'ãƒãƒ§' => 'cho',
        'ãƒãƒ¥' => 'chu',
        // 'ãƒ‚ãƒ£'=>'dya','ãƒ‚ã‚§'=>'dye','ãƒ‚ã‚£'=>'dyi','ãƒ‚ãƒ§'=>'dyo','ãƒ‚ãƒ¥'=>'dyu',
        'ãƒ„ãƒ£' => 'tsa',
        'ãƒ„ã‚§' => 'tse',
        'ãƒ„ã‚£' => 'tsi',
        'ãƒ„ãƒ§' => 'tso',
        'ãƒ„' => 'tsu',
        'ãƒˆã‚¡' => 'twa',
        'ãƒˆã‚§' => 'twe',
        'ãƒˆã‚£' => 'twi',
        'ãƒˆã‚©' => 'two',
        'ãƒˆã‚¥' => 'twu',
        'ãƒ‰ã‚¡' => 'dwa',
        'ãƒ‰ã‚§' => 'dwe',
        'ãƒ‰ã‚£' => 'dwi',
        'ãƒ‰ã‚©' => 'dwo',
        'ãƒ‰ã‚¥' => 'dwu',
        'ã‚¦ã‚¡' => 'wha',
        'ã‚¦ã‚§' => 'whe',
        'ã‚¦ã‚£' => 'whi',
        'ã‚¦ã‚©' => 'who',
        'ã‚¦ã‚¥' => 'whu',
        'ãƒ´ãƒ£' => 'vya',
        'ãƒ´ã‚§' => 'vye',
        'ãƒ´ã‚£' => 'vyi',
        'ãƒ´ãƒ§' => 'vyo',
        'ãƒ´ãƒ¥' => 'vyu',
        'ãƒ´ã‚¡' => 'va',
        'ãƒ´ã‚§' => 've',
        'ãƒ´ã‚£' => 'vi',
        'ãƒ´ã‚©' => 'vo',
        'ãƒ´' => 'vu',
        'ã‚¦ã‚§' => 'we',
        'ã‚¦ã‚£' => 'wi',
        'ã‚¤ã‚§' => 'ye',
        'ãƒ†ã‚£' => 'ti',
        'ãƒ‚ã‚£' => 'di',
        
        // 2 character syllables - doubled vocal
        'ã‚¢ãƒ¼' => 'aa',
        'ã‚¨ãƒ¼' => 'ee',
        'ã‚¤ãƒ¼' => 'ii',
        'ã‚ªãƒ¼' => 'oo',
        'ã‚¦ãƒ¼' => 'uu',
        'ãƒ€ãƒ¼' => 'daa',
        'ãƒ‡ãƒ¼' => 'dee',
        'ãƒ‚ãƒ¼' => 'dii',
        'ãƒ‰ãƒ¼' => 'doo',
        'ãƒ…ãƒ¼' => 'duu',
        'ãƒãƒ¼' => 'haa',
        'ãƒ˜ãƒ¼' => 'hee',
        'ãƒ’ãƒ¼' => 'hii',
        'ãƒ›ãƒ¼' => 'hoo',
        'ãƒ•ãƒ¼' => 'fuu',
        'ãƒãƒ¼' => 'baa',
        'ãƒ™ãƒ¼' => 'bee',
        'ãƒ“ãƒ¼' => 'bii',
        'ãƒœãƒ¼' => 'boo',
        'ãƒ–ãƒ¼' => 'buu',
        'ãƒ‘ãƒ¼' => 'paa',
        'ãƒšãƒ¼' => 'pee',
        'ãƒ”ãƒ¼' => 'pii',
        'ãƒãƒ¼' => 'poo',
        'ãƒ—ãƒ¼' => 'puu',
        'ã‚±ãƒ¼' => 'kee',
        'ã‚­ãƒ¼' => 'kii',
        'ã‚³ãƒ¼' => 'koo',
        'ã‚¯ãƒ¼' => 'kuu',
        'ã‚«ãƒ¼' => 'kaa',
        'ã‚¬ãƒ¼' => 'gaa',
        'ã‚²ãƒ¼' => 'gee',
        'ã‚®ãƒ¼' => 'gii',
        'ã‚´ãƒ¼' => 'goo',
        'ã‚°ãƒ¼' => 'guu',
        'ãƒžãƒ¼' => 'maa',
        'ãƒ¡ãƒ¼' => 'mee',
        'ãƒŸãƒ¼' => 'mii',
        'ãƒ¢ãƒ¼' => 'moo',
        'ãƒ ãƒ¼' => 'muu',
        'ãƒŠãƒ¼' => 'naa',
        'ãƒãƒ¼' => 'nee',
        'ãƒ‹ãƒ¼' => 'nii',
        'ãƒŽãƒ¼' => 'noo',
        'ãƒŒãƒ¼' => 'nuu',
        'ãƒ©ãƒ¼' => 'raa',
        'ãƒ¬ãƒ¼' => 'ree',
        'ãƒªãƒ¼' => 'rii',
        'ãƒ­ãƒ¼' => 'roo',
        'ãƒ«ãƒ¼' => 'ruu',
        'ã‚µãƒ¼' => 'saa',
        'ã‚»ãƒ¼' => 'see',
        'ã‚·ãƒ¼' => 'shii',
        'ã‚½ãƒ¼' => 'soo',
        'ã‚¹ãƒ¼' => 'suu',
        'ã‚¶ãƒ¼' => 'zaa',
        'ã‚¼ãƒ¼' => 'zee',
        'ã‚¸ãƒ¼' => 'jii',
        'ã‚¾ãƒ¼' => 'zoo',
        'ã‚ºãƒ¼' => 'zuu',
        'ã‚¿ãƒ¼' => 'taa',
        'ãƒ†ãƒ¼' => 'tee',
        'ãƒãƒ¼' => 'chii',
        'ãƒˆãƒ¼' => 'too',
        'ãƒ„ãƒ¼' => 'tsuu',
        'ãƒ¯ãƒ¼' => 'waa',
        'ãƒ²ãƒ¼' => 'woo',
        'ãƒ¤ãƒ¼' => 'yaa',
        'ãƒ¨ãƒ¼' => 'yoo',
        'ãƒ¦ãƒ¼' => 'yuu',
        'ãƒµãƒ¼' => 'kaa',
        'ãƒ¶ãƒ¼' => 'kee',
        // old characters
        'ãƒ±ãƒ¼' => 'wee',
        'ãƒ°ãƒ¼' => 'wii',
        
        // seperate katakana 'n'
        'ãƒ³ã‚¢' => 'n_a',
        'ãƒ³ã‚¨' => 'n_e',
        'ãƒ³ã‚¤' => 'n_i',
        'ãƒ³ã‚ª' => 'n_o',
        'ãƒ³ã‚¦' => 'n_u',
        'ãƒ³ãƒ¤' => 'n_ya',
        'ãƒ³ãƒ¨' => 'n_yo',
        'ãƒ³ãƒ¦' => 'n_yu',
        
        // 2 character syllables - doubled consonants
        'ãƒƒãƒ' => 'bba',
        'ãƒƒãƒ™' => 'bbe',
        'ãƒƒãƒ“' => 'bbi',
        'ãƒƒãƒœ' => 'bbo',
        'ãƒƒãƒ–' => 'bbu',
        'ãƒƒãƒ‘' => 'ppa',
        'ãƒƒãƒš' => 'ppe',
        'ãƒƒãƒ”' => 'ppi',
        'ãƒƒãƒ' => 'ppo',
        'ãƒƒãƒ—' => 'ppu',
        'ãƒƒã‚±' => 'kke',
        'ãƒƒã‚­' => 'kki',
        'ãƒƒã‚³' => 'kko',
        'ãƒƒã‚¯' => 'kku',
        'ãƒƒã‚«' => 'kka',
        'ãƒƒã‚¬' => 'gga',
        'ãƒƒã‚²' => 'gge',
        'ãƒƒã‚®' => 'ggi',
        'ãƒƒã‚´' => 'ggo',
        'ãƒƒã‚°' => 'ggu',
        'ãƒƒãƒž' => 'ma',
        'ãƒƒãƒ¡' => 'me',
        'ãƒƒãƒŸ' => 'mi',
        'ãƒƒãƒ¢' => 'mo',
        'ãƒƒãƒ ' => 'mu',
        'ãƒƒãƒŠ' => 'nna',
        'ãƒƒãƒ' => 'nne',
        'ãƒƒãƒ‹' => 'nni',
        'ãƒƒãƒŽ' => 'nno',
        'ãƒƒãƒŒ' => 'nnu',
        'ãƒƒãƒ©' => 'rra',
        'ãƒƒãƒ¬' => 'rre',
        'ãƒƒãƒª' => 'rri',
        'ãƒƒãƒ­' => 'rro',
        'ãƒƒãƒ«' => 'rru',
        'ãƒƒã‚µ' => 'ssa',
        'ãƒƒã‚»' => 'sse',
        'ãƒƒã‚·' => 'sshi',
        'ãƒƒã‚½' => 'sso',
        'ãƒƒã‚¹' => 'ssu',
        'ãƒƒã‚¶' => 'zza',
        'ãƒƒã‚¼' => 'zze',
        'ãƒƒã‚¸' => 'jji',
        'ãƒƒã‚¾' => 'zzo',
        'ãƒƒã‚º' => 'zzu',
        'ãƒƒã‚¿' => 'tta',
        'ãƒƒãƒ†' => 'tte',
        'ãƒƒãƒ' => 'cchi',
        'ãƒƒãƒˆ' => 'tto',
        'ãƒƒãƒ„' => 'ttsu',
        'ãƒƒãƒ€' => 'dda',
        'ãƒƒãƒ‡' => 'dde',
        'ãƒƒãƒ‚' => 'ddi',
        'ãƒƒãƒ‰' => 'ddo',
        'ãƒƒãƒ…' => 'ddu',
        
        // 1 character syllables
        'ã‚¢' => 'a',
        'ã‚¨' => 'e',
        'ã‚¤' => 'i',
        'ã‚ª' => 'o',
        'ã‚¦' => 'u',
        'ãƒ³' => 'n',
        'ãƒ' => 'ha',
        'ãƒ˜' => 'he',
        'ãƒ’' => 'hi',
        'ãƒ›' => 'ho',
        'ãƒ•' => 'fu',
        'ãƒ' => 'ba',
        'ãƒ™' => 'be',
        'ãƒ“' => 'bi',
        'ãƒœ' => 'bo',
        'ãƒ–' => 'bu',
        'ãƒ‘' => 'pa',
        'ãƒš' => 'pe',
        'ãƒ”' => 'pi',
        'ãƒ' => 'po',
        'ãƒ—' => 'pu',
        'ã‚±' => 'ke',
        'ã‚­' => 'ki',
        'ã‚³' => 'ko',
        'ã‚¯' => 'ku',
        'ã‚«' => 'ka',
        'ã‚¬' => 'ga',
        'ã‚²' => 'ge',
        'ã‚®' => 'gi',
        'ã‚´' => 'go',
        'ã‚°' => 'gu',
        'ãƒž' => 'ma',
        'ãƒ¡' => 'me',
        'ãƒŸ' => 'mi',
        'ãƒ¢' => 'mo',
        'ãƒ ' => 'mu',
        'ãƒŠ' => 'na',
        'ãƒ' => 'ne',
        'ãƒ‹' => 'ni',
        'ãƒŽ' => 'no',
        'ãƒŒ' => 'nu',
        'ãƒ©' => 'ra',
        'ãƒ¬' => 're',
        'ãƒª' => 'ri',
        'ãƒ­' => 'ro',
        'ãƒ«' => 'ru',
        'ã‚µ' => 'sa',
        'ã‚»' => 'se',
        'ã‚·' => 'shi',
        'ã‚½' => 'so',
        'ã‚¹' => 'su',
        'ã‚¶' => 'za',
        'ã‚¼' => 'ze',
        'ã‚¸' => 'ji',
        'ã‚¾' => 'zo',
        'ã‚º' => 'zu',
        'ã‚¿' => 'ta',
        'ãƒ†' => 'te',
        'ãƒ' => 'chi',
        'ãƒˆ' => 'to',
        'ãƒ„' => 'tsu',
        'ãƒ€' => 'da',
        'ãƒ‡' => 'de',
        'ãƒ‚' => 'di',
        'ãƒ‰' => 'do',
        'ãƒ…' => 'du',
        'ãƒ¯' => 'wa',
        'ãƒ²' => 'wo',
        'ãƒ¤' => 'ya',
        'ãƒ¨' => 'yo',
        'ãƒ¦' => 'yu',
        'ãƒµ' => 'ka',
        'ãƒ¶' => 'ke',
        // old characters
        'ãƒ±' => 'we',
        'ãƒ°' => 'wi',
        
        // convert what's left (probably only kicks in when something's missing above)
        'ã‚¡' => 'a',
        'ã‚§' => 'e',
        'ã‚£' => 'i',
        'ã‚©' => 'o',
        'ã‚¥' => 'u',
        'ãƒ£' => 'ya',
        'ãƒ§' => 'yo',
        'ãƒ¥' => 'yu',
        
        // special characters
        'ãƒ»' => '_',
        'ã€' => '_',
        'ãƒ¼' => '_', // when used with hiragana (seldom), this character would not be converted otherwise
                    
        // 'ãƒ©'=>'la','ãƒ¬'=>'le','ãƒª'=>'li','ãƒ­'=>'lo','ãƒ«'=>'lu',
                    // 'ãƒãƒ£'=>'cya','ãƒã‚§'=>'cye','ãƒã‚£'=>'cyi','ãƒãƒ§'=>'cyo','ãƒãƒ¥'=>'cyu',
                    // 'ãƒ‡ãƒ£'=>'dha','ãƒ‡ã‚§'=>'dhe','ãƒ‡ã‚£'=>'dhi','ãƒ‡ãƒ§'=>'dho','ãƒ‡ãƒ¥'=>'dhu',
                    // 'ãƒªãƒ£'=>'lya','ãƒªã‚§'=>'lye','ãƒªã‚£'=>'lyi','ãƒªãƒ§'=>'lyo','ãƒªãƒ¥'=>'lyu',
                    // 'ãƒ†ãƒ£'=>'tha','ãƒ†ã‚§'=>'the','ãƒ†ã‚£'=>'thi','ãƒ†ãƒ§'=>'tho','ãƒ†ãƒ¥'=>'thu',
                    // 'ãƒ•ã‚¡'=>'fwa','ãƒ•ã‚§'=>'fwe','ãƒ•ã‚£'=>'fwi','ãƒ•ã‚©'=>'fwo','ãƒ•ã‚¥'=>'fwu',
                    // 'ãƒãƒ£'=>'tya','ãƒã‚§'=>'tye','ãƒã‚£'=>'tyi','ãƒãƒ§'=>'tyo','ãƒãƒ¥'=>'tyu',
                    // 'ã‚¸ãƒ£'=>'jya','ã‚¸ã‚§'=>'jye','ã‚¸ã‚£'=>'jyi','ã‚¸ãƒ§'=>'jyo','ã‚¸ãƒ¥'=>'jyu',
                    // 'ã‚¸ãƒ£'=>'zha','ã‚¸ã‚§'=>'zhe','ã‚¸ã‚£'=>'zhi','ã‚¸ãƒ§'=>'zho','ã‚¸ãƒ¥'=>'zhu',
                    // 'ã‚¸ãƒ£'=>'zya','ã‚¸ã‚§'=>'zye','ã‚¸ã‚£'=>'zyi','ã‚¸ãƒ§'=>'zyo','ã‚¸ãƒ¥'=>'zyu',
                    // 'ã‚·ãƒ£'=>'sya','ã‚·ã‚§'=>'sye','ã‚·ã‚£'=>'syi','ã‚·ãƒ§'=>'syo','ã‚·ãƒ¥'=>'syu',
                    // 'ã‚·'=>'ci','ãƒ•'=>'hu',ã‚·'=>'si','ãƒ'=>'ti','ãƒ„'=>'tu','ã‚¤'=>'yi','ãƒ‚'=>'dzi',
                    
        // "Greeklish"
        'Î“' => 'G',
        'Î”' => 'E',
        'Î˜' => 'Th',
        'Î›' => 'L',
        'Îž' => 'X',
        'Î ' => 'P',
        'Î£' => 'S',
        'Î¦' => 'F',
        'Î¨' => 'Ps',
        'Î³' => 'g',
        'Î´' => 'e',
        'Î¸' => 'th',
        'Î»' => 'l',
        'Î¾' => 'x',
        'Ï€' => 'p',
        'Ïƒ' => 's',
        'Ï†' => 'f',
        'Ïˆ' => 'ps',
        
        // Thai
        'à¸' => 'k',
        'à¸‚' => 'kh',
        'à¸ƒ' => 'kh',
        'à¸„' => 'kh',
        'à¸…' => 'kh',
        'à¸†' => 'kh',
        'à¸‡' => 'ng',
        'à¸ˆ' => 'ch',
        'à¸‰' => 'ch',
        'à¸Š' => 'ch',
        'à¸‹' => 's',
        'à¸Œ' => 'ch',
        'à¸' => 'y',
        'à¸Ž' => 'd',
        'à¸' => 't',
        'à¸' => 'th',
        'à¸‘' => 'd',
        'à¸’' => 'th',
        'à¸“' => 'n',
        'à¸”' => 'd',
        'à¸•' => 't',
        'à¸–' => 'th',
        'à¸—' => 'th',
        'à¸˜' => 'th',
        'à¸™' => 'n',
        'à¸š' => 'b',
        'à¸›' => 'p',
        'à¸œ' => 'ph',
        'à¸' => 'f',
        'à¸ž' => 'ph',
        'à¸Ÿ' => 'f',
        'à¸ ' => 'ph',
        'à¸¡' => 'm',
        'à¸¢' => 'y',
        'à¸£' => 'r',
        'à¸¤' => 'rue',
        'à¸¤à¹…' => 'rue',
        'à¸¥' => 'l',
        'à¸¦' => 'lue',
        'à¸¦à¹…' => 'lue',
        'à¸§' => 'w',
        'à¸¨' => 's',
        'à¸©' => 's',
        'à¸ª' => 's',
        'à¸«' => 'h',
        'à¸¬' => 'l',
        'à¸®' => 'h',
        'à¸°' => 'a',
        'à¸±' => 'a',
        'à¸£à¸£' => 'a',
        'à¸²' => 'a',
        'à¹…' => 'a',
        'à¸³' => 'am',
        'à¹à¸²' => 'am',
        'à¸´' => 'i',
        'à¸µ' => 'i',
        'à¸¶' => 'ue',
        'à¸µ' => 'ue',
        'à¸¸' => 'u',
        'à¸¹' => 'u',
        'à¹€' => 'e',
        'à¹' => 'ae',
        'à¹‚' => 'o',
        'à¸­' => 'o',
        'à¸µà¸¢à¸°' => 'ia',
        'à¸µà¸¢' => 'ia',
        'à¸·à¸­à¸°' => 'uea',
        'à¸·à¸­' => 'uea',
        'à¸±à¸§à¸°' => 'ua',
        'à¸±à¸§' => 'ua',
        'à¹ƒ' => 'ai',
        'à¹„' => 'ai',
        'à¸±à¸¢' => 'ai',
        'à¸²à¸¢' => 'ai',
        'à¸²à¸§' => 'ao',
        'à¸¸à¸¢' => 'ui',
        'à¸­à¸¢' => 'oi',
        'à¸·à¸­à¸¢' => 'ueai',
        'à¸§à¸¢' => 'uai',
        'à¸´à¸§' => 'io',
        'à¹‡à¸§' => 'eo',
        'à¸µà¸¢à¸§' => 'iao',
        'à¹ˆ' => '',
        'à¹‰' => '',
        'à¹Š' => '',
        'à¹‹' => '',
        'à¹‡' => '',
        'à¹Œ' => '',
        'à¹Ž' => '',
        'à¹' => '',
        'à¸º' => '',
        'à¹†' => '2',
        'à¹' => 'o',
        'à¸¯' => '-',
        'à¹š' => '-',
        'à¹›' => '-',
        'à¹' => '0',
        'à¹‘' => '1',
        'à¹’' => '2',
        'à¹“' => '3',
        'à¹”' => '4',
        'à¹•' => '5',
        'à¹–' => '6',
        'à¹—' => '7',
        'à¹˜' => '8',
        'à¹™' => '9',
        
        // Korean
        'ã„±' => 'k',
        'ã…‹' => 'kh',
        'ã„²' => 'kk',
        'ã„·' => 't',
        'ã…Œ' => 'th',
        'ã„¸' => 'tt',
        'ã…‚' => 'p',
        'ã…' => 'ph',
        'ã…ƒ' => 'pp',
        'ã…ˆ' => 'c',
        'ã…Š' => 'ch',
        'ã…‰' => 'cc',
        'ã……' => 's',
        'ã…†' => 'ss',
        'ã…Ž' => 'h',
        'ã…‡' => 'ng',
        'ã„´' => 'n',
        'ã„¹' => 'l',
        'ã…' => 'm',
        'ã…' => 'a',
        'ã…“' => 'e',
        'ã…—' => 'o',
        'ã…œ' => 'wu',
        'ã…¡' => 'u',
        'ã…£' => 'i',
        'ã…' => 'ay',
        'ã…”' => 'ey',
        'ã…š' => 'oy',
        'ã…˜' => 'wa',
        'ã…' => 'we',
        'ã…Ÿ' => 'wi',
        'ã…™' => 'way',
        'ã…ž' => 'wey',
        'ã…¢' => 'uy',
        'ã…‘' => 'ya',
        'ã…•' => 'ye',
        'ã…›' => 'oy',
        'ã… ' => 'yu',
        'ã…’' => 'yay',
        'ã…–' => 'yey'
    );


