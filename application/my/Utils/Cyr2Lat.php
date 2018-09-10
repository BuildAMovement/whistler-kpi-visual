<?php
namespace Utils;

/**
 * 
 * @method static \Utils\Cyr2Lat getInstance()
 *
 */
class Cyr2Lat implements \SplObserver
{
    use \Ufw\Singleton;

    protected $curLang = null;

    protected $xlat = array(
        '%s' => '%s',
        '%d' => '%d',
        'A' => "А",
        'B' => "Б",
        'V' => "В",
        'G' => "Г",
        'D' => "Д",
        'Đ' => "Ђ",
        'E' => "Е",
        'Ž' => "Ж",
        'Z' => "З",
        'I' => "И",
        'J' => "Ј",
        "K" => "К",
        "L" => "Л",
        "LJ" => "Љ",
        "M" => "М",
        "N" => "Н",
        'NJ' => "Њ",
        "O" => "О",
        "P" => "П",
        "R" => "Р",
        "S" => "С",
        "Š" => "Ш",
        "T" => "Т",
        "Ć" => "Ћ",
        "U" => "У",
        "F" => "Ф",
        "H" => "Х",
        "C" => "Ц",
        "Č" => "Ч",
        "DŽ" => "Џ",
        "Š" => "Ш",
        'a' => "а",
        "b" => "б",
        "v" => "в",
        "g" => "г",
        "d" => "д",
        "đ" => "ђ",
        "e" => "е",
        "ž" => "ж",
        'z' => "з",
        "i" => "и",
        "j" => "ј",
        "k" => "к",
        "l" => "л",
        'lj' => "љ",
        "m" => "м",
        "n" => "н",
        'nj' => "њ",
        "o" => "о",
        "p" => "п",
        "r" => "р",
        "s" => "с",
        "š" => "ш",
        "t" => "т",
        "ć" => "ћ",
        'u' => "у",
        "f" => "ф",
        "h" => "х",
        "c" => "ц",
        "č" => "ч",
        "dž" => "џ",
        "š" => "ш",
        
        'Nja' => "Ња",
        'Nje' => "Ње",
        'Nji' => "Њи",
        'Njo' => "Њо",
        'Nju' => "Њу",
        'Lja' => "Ља",
        'Lje' => "Ље",
        'Lji' => "Љи",
        'Ljo' => "Љо",
        'Lju' => "Љу",
        'Dža' => "Џа",
        'Dže' => "Џе",
        'Dži' => "Џи",
        'Džo' => "Џо",
        "Džu" => "Џу",
        'nja' => "ња",
        'nje' => "ње",
        'nji' => "њи",
        'njo' => "њо",
        'nju' => "њу",
        'lja' => "ља",
        'lje' => "ље",
        'lji' => "љи",
        'ljo' => "љо",
        'lju' => "љу",
        'džu' => "џу"
    );

    protected $xlat_flipped = null;

    protected $xlat_ucase = array(
        "А" => "A",
        "Б" => "B",
        "В" => "V",
        "Г" => "G",
        "Џ" => "DŽ",
        "Д" => "D",
        "Ђ" => "Đ",
        "Е" => "E",
        "Ж" => "Ž",
        "З" => "Z",
        "И" => "I",
        "Ј" => "J",
        "К" => "K",
        "Љ" => "LJ",
        "Л" => "L",
        "М" => "M",
        "Њ" => "NJ",
        "Н" => "N",
        "О" => "O",
        "П" => "P",
        "Р" => "R",
        "С" => "S",
        "Т" => "T",
        "Ћ" => "Ć",
        "У" => "U",
        "Ф" => "F",
        "Х" => "H",
        "Ц" => "C",
        "Ч" => "Č",
        "Ш" => "Š"
    );

    /**
     * konvertor u cirilicna slova
     *
     * @param string $str
     * @return string
     */
    public function toCyr($str)
    {
        $splitted = preg_split('~(<[^>]+>|\&[a-z]+;|\&0x[0-9a-f]+;|\&\#[0-9]+;)~sSi', $str, -1, PREG_SPLIT_DELIM_CAPTURE);
        $pattern = join('', array_keys($this->xlat_ucase));
        $pattern_nonchar = join('', array_keys($this->xlat));
        // parni su sadrzaj, neparni su delimiteri
        $callback_1 = function ($matches) {
            return "\"{$matches[1]}=\"" . $this->toCyr($matches[3]) . "\"";
        };
        $callback_2 = function ($matches) {
            return $matches[1] . strtr($matches[2], $this->xlat_ucase);
        };
        for ($i = 0, $l = count($splitted), $out = ''; $i < $l; $i++) {
            if ($i % 2) {
                $out .= preg_replace_callback('~(\s(title|alt))="([^"]+)"~', $callback_1, $splitted[$i]);
                // $out .= preg_replace('~(\s(title|alt))="([^"]+)"~e', '"$1=\"" . My_Utility::sr("$3") . "\""', $splitted[$i]);
            } else {
                
                $splitted[$i] = preg_replace_callback("~(^|[^$pattern_nonchar])([$pattern]+)(?=($|[^$pattern_nonchar]))~u", $callback_2, $splitted[$i]);
                $out .= strtr($splitted[$i], $this->xlat);
            }
        }
        return preg_replace('~\%(\d+\$|)с~u', '%$1s', $out);
    }

    public function toCyrPlain($str)
    {
        return strtr($str, $this->xlat);
    }

    /**
     * Konvertor u latinicna slova
     *
     * @param string $str
     *            return string
     */
    public function toLat($str)
    {
        if (!$this->xlat_flipped) {
            $this->xlat_flipped = array_flip($this->xlat);
        }
        $splitted = preg_split('~(<[^>]+>|\&[a-z]+;|\&0x[0-9a-f]+;|\&\#[0-9]+;)~sSi', $str, -1, PREG_SPLIT_DELIM_CAPTURE);
        // parni su sadrzaj, neparni su delimiteri
        for ($i = 0, $l = count($splitted), $out = ''; $i < $l; $i++) {
            if ($i % 2)
                $out .= preg_replace('~(\s(title|alt))="([^"]+)"~e', '"$1=\"" . My_Utility::sr_latn("$3") . "\""', $splitted[$i]);
            else
                $out .= strtr($splitted[$i], $this->xlat_flipped);
        }
        return $out;
    }

    public function toLatPlain($str)
    {
        if (!$this->xlat_flipped) {
            $this->xlat_flipped = array_flip($this->xlat);
        }
        return strtr($str, $this->xlat_flipped);
    }

    protected function getCurLang()
    {
        if (!isset($this->curLang)) {
            $this->curLang = \Ufw\Registry::getInstance()->get('lang');
            \Ufw\Registry::getInstance()->attach($this, 'lang');
        }
        return $this->curLang;
    }

    public function update(\SplSubject $subject, $value = '')
    {
        if ($subject instanceof \Ufw\Registry) {
            $this->curLang = null;
        }
    }

    public function TT($str)
    {
        $out = $str;
        switch ($this->getCurLang()) {
            case 'sr-Cyrl':
                $out = $this->toCyrPlain($str);
                break;
            case 'sr-Latn':
                $out = $this->toLatPlain($str);
                break;
        }
        return $out;
    }
}
