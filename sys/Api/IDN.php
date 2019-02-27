<?php

class Api_IDN {

    /**
     * Adapt bias for punycode algorithm
     * @param $delta
     * @param $numpoints
     * @param $firsttime
     * @return int
     */
    private static function punyAdapt($delta, $numpoints, $firsttime) {
        $delta = $firsttime ? $delta / 700 : $delta / 2; 
        $delta += $delta / $numpoints;
        for ($k = 0; $delta > 455; $k += 36) {
            $delta = intval($delta / 35);
        }
        return $k + (36 * $delta) / ($delta + 38);
    }

    /**
     * Translate character to punycode number
     * @param string $cp
     * @return int
     */
    private static function decodeDigit($cp) {
        $cp = strtolower($cp);
        if ($cp >= 'a' && $cp <= 'z') {
            return ord($cp) - ord('a');
        } elseif ($cp >= '0' && $cp <= '9') {
            return ord($cp) - ord('0')+26;
        }
    }

    /**
     * Make utf8 string from unicode codepoint number
     * @param String$cp
     * @return string
     */
    private static function utf8($cp) {
        if ($cp < 128) {
            return chr($cp);
        }
        if ($cp < 2048) {
            return chr(192+($cp >> 6)).chr(128+($cp & 63));
        }
        if ($cp < 65536) {
            return 
                chr(224+($cp >> 12)).
                chr(128+(($cp >> 6) & 63)).
                chr(128+($cp & 63));
        }
        if ($cp < 2097152) {
            return 
                chr(240+($cp >> 18)).
                chr(128+(($cp >> 12) & 63)).
                chr(128+(($cp >> 6) & 63)).
                chr(128+($cp & 63));
        }
        // Should never reach this code.
        throw new Exception("IDN conversion failed! Invalid value on Api_IDN::utf8()");
    }

    /**
     * Main decoding function
     * @param string $input
     * @return string
     */
    private static function decodePart($input) {
        if (substr($input,0,4) != "xn--") // prefix check...
            return $input;
        $input = substr($input,4); // discard prefix
        $a = explode("-",$input);
        if (count($a) > 1) {
            $input = str_split(array_pop($a));
            $output = str_split(implode("-",$a));
        } else {
            $output = array();
            $input = str_split($input);
        }
        // init punycode variables
        $n = 128; $i = 0; $bias = 72; 
        while (!empty($input)) {
            $oldi = $i;
            $w = 1;
            for ($k = 36;;$k += 36) {
                $digit = self::decodeDigit(array_shift($input));
                $i += $digit * $w;
                if ($k <= $bias) {
                    $t = 1;
                } elseif ($k >= $bias + 26) {
                    $t = 26;
                } else {
                    $t = $k - $bias;
                }
                if ($digit < $t) {
                    break;
                }
                $w *= intval(36 - $t);
            }
            $bias = self::punyAdapt($i-$oldi, count($output)+1, $oldi == 0);
            $n += intval($i / (count($output) + 1));
            $i %= count($output) + 1;
            array_splice($output,$i,0,array(self::utf8($n)));
            $i++;
        }
        return implode("",$output);
    }

    /**
     * Decode IDN host-/domainname
     * @param string $name
     * @return string
     */
    public static function decodeIDN($name) {
        if (empty($name)) {
            return $name;
        }
        if (function_exists("idn_to_utf8")) {
            return idn_to_utf8($name);
        }
        return implode(".", array_map(self::class."::decodePart",explode(".",$name)));
    }

    /**
     * Encode IDN host-/domainname
     * @param string $name
     * @return string
     */
    public static function encodeIDN($name) {
        if (function_exists("idn_to_ascii")) {
            return idn_to_ascii($name);
        }
        return $name;
    }

}