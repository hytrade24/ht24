<?php
/* ###VERSIONSBLOCKINLCUDE### */


if (!function_exists("chtrans")) {
  
  function chtrans($s)
  {
    static $nar = array (
      '/&(\w)uml;/' => '$1e',
      '/&(\w)(ague|circumflex|grave);/' => '$1',
      '/&szlig;/' => 'ss',
      '/&[a-z]+;/i' => '',
      '/&\#[0-9]+;/' => '',
      '/&/' => '+',
      '/\s/' => '-',
      '/\./' => '-',
      '/[^\w-]/' => '',
      '/^[_+-]+/' => '',
      '/[_+-]+$/' => ''
    );

    $s = stdHtmlentities($s);
    foreach($nar as $from => $to) {
      $s = preg_replace($from, $to, $s);
    }
    return $s;
  }
  
}

  function createpass($name='')
  {
    static $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
srand(time());
    do
    {
      $pass = '';
      for ($i=0; $i<8; $i++)
        $pass .= substr($chars, rand(0,strlen($chars)), 1);
    }
    while (strlen(count_chars($pass, 3))<8 || !strcasecmp($pass, $name));
    return $pass;
  }

    function pass_generate_salt() {
        return uniqid(mt_rand(), true);
    }

    function pass_encrypt($str, $salt = '', $algorithm = NULL)
    {
        if($algorithm == 'sha512' || ($algorithm == NULL && function_exists("hash_algos") && in_array("sha512", hash_algos()))) {
            $passPrefix = '1;';
            $hash = hash("sha512", hash("sha512", $str).';'.hash("sha512", $salt));

        } else {
            $passPrefix = '0;';
            $hash = md5(md5($str).';'.md5($salt));
        }
        return $passPrefix.$hash;
    }

    function pass_compare($compare, $hash, $salt = '') {
        if(substr($hash, 0, 2) == '1;') {
            return ($hash === pass_encrypt($compare, $salt, 'sha512'));
        } else {
            return ($hash === pass_encrypt($compare, $salt, 'md5'));
        }
    }


// Validierung -----------------------------------------------------------------
  function validate_email($address)
  {
    // false wenn email leer oder syntaktisch inkorrekt
    // true sonst
    return preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[a-z]+/i', $address);
  } // function validate_email

  function validate_url($address)
  {
    // false wenn email leer oder syntaktisch inkorrekt
    // true sonst
    return preg_match(
'/^[a-z]+:\/\/([_a-z0-9-]+\.)+([a-z]{2}|com|edu|gov|int|mil|net|org|shop|aero|'.(DEVHOST ? 'e':'').'biz|coop|info|museum|name|pro)(/.*)?$/i',
      $address
    );
  } // function validate_email

  function validate_ip($address)
    // false wenn leer oder syntaktisch inkorrekt
  {
    return preg_match('/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/', $address, $ar_tmp)
      && max($ar_tmp)<=255;
  }

  function validate_nick($s)
    // 1: zu kurz
    // 2: falsche Syntax
    // 3: beides
    // 0: ok
  {
    $s = trim($s);
    return
      (strlen($s)<3 ? 1 : 0)
    +
      (preg_match('/^[a-z0-9_-]+$/i', $s) ? 0 : 2)
    ;
  }

// String-Modifikationen -------------------------------------------------------
  function make_paramstr($ar = false)
  {
    $tmp = array ();
    if (!$ar) $ar = $_GET+$_POST;
    foreach($ar as $k=>$v)
      $tmp[$k] = rawurlencode($k).'='.rawurlencode($v);
    return implode('&', $tmp). '&';
  }

  function js_quote($str, $fl_inattr=false)
  {
    $str = preg_replace("/(\n|\r\n|\n\r|\r)/", '\\n', str_replace("'", "\\'", $str));
    if ($fl_inattr) $str = str_replace('"', '&quot;', $str);
    return $str;
  }

  function jsblock($str_js)
  {
    return '<script language="JavaScript" type="text/javascript">
<!--
  '. $str_js. '
//-->
</script>';
  }

  function date_implode(&$ar, $str_fieldname, $fl_withtime=false)
  {
    if (!$ar[$str_fieldname.'_y']) return;
    $ar[$str_fieldname] = ($fl_withtime
      ? sprintf('%04d-%02d-%02d %02d:%02d:%02d',
        $ar[$str_fieldname.'_y'], $ar[$str_fieldname.'_m'], $ar[$str_fieldname.'_d'],
        $ar[$str_fieldname.'_h'], $ar[$str_fieldname.'_i'], $ar[$str_fieldname.'_s'])
      : sprintf('%04d-%02d-%02d',
        $ar[$str_fieldname.'_y'], $ar[$str_fieldname.'_m'], $ar[$str_fieldname.'_d'])
      )
    ;
  }
  function time_implode(&$ar, $str_fieldname) // 2004-07-02
  {
    if (!$ar[$str_fieldname.'_h']) return;
    $ar[$str_fieldname] = sprintf('%02d:%02d:%02d',
      $ar[$str_fieldname.'_h'], $ar[$str_fieldname.'_i'], $ar[$str_fieldname.'_s']
    );
  }

  function iso2date($date, $withtime=true)
  {
  	global $s_lang;
    list($y,$m,$d) = explode('-', substr($date,0,10));

    if($s_lang == "en")
      return "$y.$m.$d". ((int)$withtime ? substr($date, 10) : '');
    else
      return "$d.$m.$y". ((int)$withtime ? substr($date, 10) : '');
  }

// -----------------------------------------------------------------------------
  function get_messages($s_ident, $str = NULL)
  {
    global $db, $s_lang, $langval;

	$where = array();
	if ($s_ident != 'null')
		$where[] = " t.FKT='".sqlString($s_ident)."'";
	if($str)
	{
	  $hack = explode(",", $str);
	  $in = array();
	  for($i=0; $i<count($hack); $i++)
	  {
	    $in[] = "'".sqlString(trim($hack[$i]))."'";
	  } // for
	  $where[] = "t.ERR IN (".implode(",", $in).")";
	} // str > ""

	$query = "select  t.ID_MESSAGE,s.V1 from `message` t
              left join string_app s on s.S_TABLE='message' and s.FK=t.ID_MESSAGE
              and s.BF_LANG=if(t.BF_LANG_APP & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_APP+0.5)/log(2)))
			  ".(count($where) ? " where ".implode(" and ", $where) : '')."
			 ";

			# echo $query;
	$ret = $db->fetch_nar($query);

	#echo ht(dump($GLOBALS['lastresult']));
	#echo ht(dump($ret));
    return $ret;
  }

if (!function_exists("stdHtmlentities")) {
    
	function stdHtmlentities($string) {
		return htmlentities($string, ENT_QUOTES | ENT_XHTML, "UTF-8");
	}
	
}

if (!function_exists("stdHtmlspecialchars")) {
    
	function stdHtmlspecialchars($string) {
		return htmlspecialchars($string, ENT_QUOTES | ENT_XHTML, "UTF-8");
	}

}

    function array_prefix_key($array, $prefix) {
        $ar_tmp = array();
        foreach ($array as $k => $v) {
            $ar_tmp[$prefix . $k] = $v;
        }
        return $ar_tmp;
    }

/**
 * @param $string
 *
 * @return string
 */
function generateFulltextSearchstring($string, &$removed = array(), $removeBelowLength = 0) {
	$hack = explode(" ", $string);
	$searchString = '';

	for ($i = 0; $i < count($hack); $i++) {
		$hack[$i] = trim($hack[$i]);
		$text = $hack[$i];

		if (strlen($text) < $removeBelowLength) {
		    $removed[] = $text;
        } else {
            if($text !== '*') {
                $text = str_replace(array('Ä', 'Ö', 'Ü', 'ä', 'ü', 'ö', 'ß', '-'), array(
                    'Ae', 'Ue', 'Oe', 'ae', 'ue', 'oe', 'ss', '_'
                ), $text);
                $text = preg_replace("/[^\s\*a-z0-9_-]/si", "", $text);

                if (strlen($text) > 3) {
                    $hack[$i] = '*' . $text . '*';
                } else {
                    $hack[$i] = $text;
                }
                $searchString .= '+('.$hack[$i].') ';
            } else {
                $searchString .= '*';
            }
        }
	}


	return trim($searchString);
}
// zusatz für affiliate cron
function file_url($url) {
    $arExceptions = array("%3D" => "=", "%24" => "$");
    $parts = parse_url($url);
    $path_parts = array_map('rawurldecode', explode('/', $parts['path']));

    $url_path = str_replace(array_keys($arExceptions), array_values($arExceptions), implode('/', array_map('rawurlencode', $path_parts)));
    if (array_key_exists("query", $parts) && !empty($parts["query"])) {
        $url_path .= "?".$parts["query"];
    }
    return $parts['scheme'] . '://' . $parts['host'] . $url_path;
}
// zusatz für affiliate cron - ende
?>