<?php
/* ###VERSIONSBLOCKINLCUDE### */


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
    foreach($nar as $from => $to)
      $s = preg_replace($from, $to, $s);
    return $s;
  }

  function createpass($name)
  {
    static $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do
    {
      $pass = '';
      for ($i=0; $i<8; $i++)
        $pass .= substr($chars, rand(0,strlen($chars)), 1);
    }
    while (strlen(count_chars($pass, 3))<8 || !strcasecmp($pass, $name));
    return $pass;
  }

function pass_generate_salt()
{
    return uniqid(mt_rand(), true);
}

function pass_encrypt($str, $salt = '', $algorithm = NULL)
{
    if ($algorithm == 'sha512' || ($algorithm == NULL && function_exists("hash_algos") && in_array("sha512", hash_algos()))) {
        $passPrefix = '1;';
        $hash = hash("sha512", hash("sha512", $str) . ';' . hash("sha512", $salt));

    } else {
        $passPrefix = '0;';
        $hash = md5(md5($str) . ';' . md5($salt));
    }
    return $passPrefix . $hash;
}

function pass_compare($compare, $hash, $salt = '')
{
    if (substr($hash, 0, 2) == '1;') {
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
    return preg_match(
'/^[_a-z0-9-]+(\.[_a-z0-9-]+)*\.?@([_a-z0-9-]+\.)+([a-z]+)$/i',
      $address
    );
  } // function validate_email

  function validate_url($address)
  {
    // false wenn email leer oder syntaktisch inkorrekt
    // true sonst
    return preg_match(
'/^[a-z]+:\/\/([_a-z0-9-]+\.)+([a-z]{2}|com|edu|gov|int|mil|net|org|shop|aero|'.(DEVHOST ? 'e':'').'biz|coop|info|museum|name|pro)(\/.*)?$/i',
      $address
    );
  } // function validate_email

  function validate_ip($address)
    // false wenn leer oder syntaktisch inkorrekt
  {
    return preg_match('/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/', $address, $ar_tmp)
      && max($ar_tmp)<=255;
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

  function iso2date($date, $withtime)
  {
    list($y,$m,$d) = explode('-', substr($date,0,10));
    return "$d.$m.$y". ((int)$withtime ? substr($date, 10) : '');
  }

  // Werte fuer Listenansicht umwandeln
  function val_convert($v, $s_highlight='')
  {
    if (is_null($v)) $v = '<i>null</i>';
    else
    {
      if (strlen($v)>53) $v = substr($v,0,50). ' ...';
      $v = stdHtmlentities($v);
      if (preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $v, $match))
        $v = iso2date($v, $match[1] && !preg_match('/^ [0:]+$/', $match[1]));
      elseif ($s_highlight)
        $v = preg_replace('/'. preg_quote(stdHtmlentities($s_highlight)). '/i',
          '<span class="highlight">$0</span>', $v);
    }
    return $v;
  }
// -----------------------------------------------------------------------------
  function get_messages($s_ident)
  {
    global $db, $s_lang;
    static $nar_data;
    if (!($ret = $nar_data[$s_ident]))
      $ret = $nar_data[$s_ident]
        = $db->fetch_nar($db->lang_select('message', 'ERR,LABEL')."
          where FKT='". mysql_escape_string($s_ident). "'");
    return $ret;
  }


function stdHtmlentities($string) {
    return htmlentities($string, ENT_QUOTES | ENT_XHTML, "UTF-8");
}

function stdHtmlspecialchars($string) {
    return htmlspecialchars($string, ENT_QUOTES | ENT_XHTML, "UTF-8");
}

function array_prefix_key($array, $prefix) {
     $ar_tmp = array();
     foreach ($array as $k => $v) {
         $ar_tmp[$prefix . $k] = $v;
     }
     return $ar_tmp;
 }
?>