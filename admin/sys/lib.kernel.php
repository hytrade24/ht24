<?php
/* ###VERSIONSBLOCKINLCUDE### */

// prevent caching =============================================================
function nocache($time = NULL)
{
  header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
  header("Last-Modified: " .
    ($time
      ? gmdate("D, d M Y H:i:s", $time)
      : gmdate("D, d M Y H:i:s")
    ) ." GMT");
  header("Cache-Control: no-cache");
  header("Pragma: no-cache");
  header("Cache-Control: post-check=0, pre-check=0", FALSE);
}

// Session =====================================================================
function session_init()
{
    global $db;
    session_start();

    if ((int)$_COOKIE['ebizuid_' . session_name() . '_admin_uid']) {
        $cookieUid = (int)$_COOKIE['ebizuid_' . session_name() . '_admin_uid'];
        $cookieHash = $_COOKIE['ebizuid_' . session_name() . '_admin_hash'];

        $tmpUser = $db->fetch1("select * from `user` where ID_USER='" . mysql_real_escape_string($cookieUid) . "' and STAT=1");

        if ($tmpUser && pass_compare($cookieUid . $tmpUser['PASS'], $cookieHash)) {
            define ('SESSION', true);
            $uid = $cookieUid;

            return $uid;
        }
    }

    define ('SESSION', false);
    return 0;
}

// magic unquote gpc ===========================================================
function maqic_unquote_gpc()
{
  if (ini_get('magic_quotes_gpc'))
  {
    $tmp = array ('_POST', '_GET', '_REQUEST', '_COOKIE');
    foreach($tmp as $n)
      recurse($GLOBALS[$n], '$value=stripslashes($value)');
  }
}

function get_user($uid)
{
  if ($uid)
  {
    $user = $GLOBALS['db']->fetch1("select * from `user` where ID_USER=". $uid);
    if (!$user || !$user['STAT']) {
    	$user = array('ID_USER'=>0, 'STAT'=>0);
    	//die('access revoked');
    } else {
		unset($user['PASS']);
    }
  }
  else
    $user = array ('ID_USER'=>0, 'STAT'=>1);
  return $user;
}

// Sprache ermitteln ===========================================================
function get_language()
{
  global $db, $ar_urlrewritevars;

  $langval = false;
  if ($s_lang = $_REQUEST['lang'])
    ;
  elseif (!SESSION || !($s_lang = $_SESSION['lang']))
  {
    $lang = $db->fetch1("select BITVAL, ABBR from lang order by BITVAL desc limit 0,1");
    $s_lang = $lang['ABBR'];
    $langval = (int)$lang['BITVAL'];
  }
  if (false===$langval)
    $langval = (int)$db->fetch_atom("select BITVAL from lang where ABBR='$s_lang'");

  if (SESSION)
    $_SESSION['lang'] = $s_lang;

    $ar_urlrewritevars['lang'] = $s_lang;

  return array ($s_lang, $langval);
}

function set_language($newLang) {
    global $lang_list, $s_lang, $langval, $n_navroot, $s_cachepath, $nar_systemsettings, $nar_tplglobals, $originalSystemSettings;

    if(is_numeric($newLang)) {
        foreach($lang_list as $key => $l) {
            if($l['ID_LANG'] == $newLang) {
                $newLang = $l['ABBR'];
                break;
            }
        }
    }

    if(array_key_exists($newLang, $lang_list)) {        
        $s_lang = $lang_list[$newLang]['ABBR'];
        $langval = $lang_list[$newLang]['BITVAL'];

        // Navigations Array
        if ($lang_list[$newLang]['DOMAIN'] != '') {
            $nar_systemsettings['SITE']['SITEURL'] = $lang_list[$newLang]['DOMAIN'];
        } else {
            $nar_systemsettings['SITE']['SITEURL'] = $originalSystemSettings['SITE']['SITEURL'];
        }
        if ($lang_list[$newLang]['BASE_URL'] != '') {
            $nar_systemsettings['SITE']['BASE_URL'] = $lang_list[$newLang]['BASE_URL'];
        } else {
            $nar_systemsettings['SITE']['BASE_URL'] = $originalSystemSettings['SITE']['BASE_URL'];
        }
    }
}

function s_findkid($id, $rec=0)
{
  global $ar_nav, $nar_pageallow;
  if($id==0)
  {
    foreach($ar_nav as $key => $row)
    {
      if($row['B_VIS'] && $row['IDENT'] && $nar_pageallow['admin/'. $row['IDENT']])
        return $row['IDENT'];
    }
  }
  else
  {
    $row = $ar_nav[$id];
    if ($row['B_VIS'] || !$rec)
    {
      if (($tmp = $row['IDENT']) && $nar_pageallow['admin/'. $tmp])
        return $tmp;
      if ($row['B_VIS'] && (!$row['IDENT'] || $nar_pageallow['admin/'. $row['IDENT']]))
        foreach($row['KIDS'] as $kid_id)
          if ($tmp = s_findkid($kid_id, $rec+1))
            return $tmp;
    }
  }
  return false;
}

function parse(&$tpl_main)
{
  global $ar_urlrewritevars;
  // html correction
  $ar_headlines = array ();
  function merkremove2($match, $m1='', $m2='') {
    if ($m2 && !strcasecmp('meta', $m1))
      $GLOBALS['ar_headlines'][strtolower($m2)] = $match;
    else
      $GLOBALS['ar_headlines'][] = $match;
    return '';
  }
/* berni raus 03.03.06
  $text =
  // 5. gemerkte metas+styles hinter </title> oder hinter <head> wieder einfuegen
    preg_replace("%<". (preg_match('%<title>.+</title>%Usi', $text) ? '/title' : 'head')
      . ">%ie", '"$0".implode("", $ar_headlines)',
     // 4. inline styles merken & weg
    preg_replace("/(\s+)?<style.*>.*<\/style>/Uie", 'merkremove("$0")',
    // 3. sonstige meta tags merken & weg
    preg_replace("/(\s+)?<meta.*>/Uie", 'merkremove("$0", "$1", "$2")',
    // 2. meta tags mit name merken & weg
    preg_replace("/(\s+)?<(meta).*name=\"(.*)\".*>/Uie", 'merkremove("$0", "$1", "$2")',
    // 1. Template parsen
    $tpl_main->process()
  ))));
*/

  $text =$tpl_main->process(); // berni eingebaut 03.03.06

  // url rewrite
  if ($ar_urlrewritevars && count($ar_urlrewritevars))
  {
    // params
    $ar_ref = $ar_frm = array ();
    foreach($ar_urlrewritevars as $k=>$v)
    {
      if (is_array ($v))
        foreach($v as $kk=>$vv)
        {
          $ar_ref[] = rawurlencode($k). '['. rawurlencode($kk). ']='. rawurlencode($vv);
          $ar_frm[] = "\n  ". '<input type="hidden" name="'
            . stdHtmlentities($k). '['. stdHtmlentities($kk). ']" value="'. stdHtmlentities($vv). '" />';
        }
      else
      {
        $ar_ref[] = rawurlencode($k). '='. rawurlencode($v);
        $ar_frm[] = "\n  ". '<input type="hidden" name="'. stdHtmlentities($k). '" value="'. stdHtmlentities($v). '" />';
      }
    }

    // urls
#preg_match_all('/("|\'|\s)index.php((\?)(.*))?\\1/U', $text, $ar_m, PREG_SET_ORDER);die(ht(dump($ar_m)));
    $text = preg_replace('/("|\')index.php((\?)(.*))?\\1/U', '$1index.php?'
      . str_replace('$', '\\$', implode('&', $ar_ref)). '&$4$1', $text);

    // forms
#preg_match_all('/(\<form.*\baction=("|\')index.php)(\?.*)?\\2(.*\>)/Ui', $text, $ar_m, PREG_SET_ORDER);die(ht(dump($ar_m)));
    $text = preg_replace('/(\<form.*\baction=("|\')index.php)(\?.*)?\\2(.*\>)/Ui',
      '$1$2$4'. implode('', $ar_frm), $text);
  }

  /* berni raus 03.03.06
  // wenn mehrere title-tags im Text stehen: letztes in head-Tag einbauen
  if (preg_match_all('%<title>(.+)</title>%Usi', $text, $ar_matches,
    PREG_SET_ORDER | PREG_OFFSET_CAPTURE) && count($ar_matches)>1)
  {
    $tmp = end($ar_matches);
    $s_title = $tmp[1][0];
    for ($i=count($ar_matches)-1; $i>0; $i--)
    {
      $tmp = &$ar_matches[$i][0];
      $text = substr($text, 0, $tmp[1]). substr($text, $tmp[1]+strlen($tmp[0]));
    }
    $text = preg_replace('%(<title>).*(</title>)%Usi', '$1'. $s_title. '$2', $text);
  }
  */

  return $text;
}

function delete_user($id, $ar)
{
  global $ab_path, $db;
  $ar_check = check_user($id);
  $pfad = $GLOBALS['ab_path']."cache/users/".$id;
  //die($pfad);
  #echo ht(dump($ar_check));
  if($ar_check['deleteable'])
  {
    if(!empty($ar_check['update_tables']) && empty($ar['ID_NEW']))
	{
	  $ar_check['need_new'] = 1;
	  return $ar_check;
	}
	else
	{
		foreach($ar_check['update_tables'] as $table => $field) {
			$db->query("update ".$table." set ".$field." = ".$ar['ID_NEW']." where ".$field." = ".$id);
		}

		foreach($ar_check['delete_tables'] as $table => $field) {
			$db->query("delete from ".$table." where ".$field." = ".$id);
			system("rm -rf ".$pfad." \n");
		}

		// Anzeigen löschen
		require_once $ab_path."sys/lib.ads.php";
		$ar_articles = $db->fetch_nar("SELECT ID_AD_MASTER, AD_TABLE FROM `ad_master` WHERE FK_USER=".(int)$id);
		foreach ($ar_articles as $id_ad => $ad_table) {
            Ad_Marketplace::deleteAd($id_ad, $ad_table);
		}
		#die(ht(dump($db->q_queries)));
		$db->submit();
		return array_merge(array('deleted' => 1), $ar_check);
	}
  }
  else
    return $ar_check;
}

function check_user($id)
{
  global $db;
  include "../conf/delete_user.php";
  $deleteable = true;
  $msg = $err = $update_tables = $delete_tables = array();
  ### kann user gelÃ¶scht werden?
  foreach ($ar_nodel as $table => $field)
  {
    $num = $db->fetch_atom("select count(*) from ".$table." where ".$field." = ".$id);
	#echo $num."<br />"; // ht(dump($GLOBALS['lastresult']));
	if($num > 0)
	{
	  $deleteable = false;
	  $err[] = "Es wurden ".$num." DatensÃ¤tze in der Tabelle ".$table." gefunden. Daher konnte der User
	   nicht gelÃ¶scht werden.";
	}
  }
  if($deleteable)
  {
    // Pakete und deren Abhänigkeiten löschen
    $arPackets = array_keys($db->fetch_nar("SELECT ID_PACKET_ORDER FROM `packet_order` WHERE FK_USER=".(int)$id));
    if (!empty($arPackets)) {
        $db->querynow("DELETE FROM `packet_order_billableitem` WHERE FK_PACKET_ORDER IN (".implode(", ", $arPackets).")");
        $db->querynow("DELETE FROM `packet_order_invoice` WHERE FK_PACKET_ORDER IN (".implode(", ", $arPackets).")");
        $db->querynow("DELETE FROM `packet_order_usage` WHERE ID_PACKET_ORDER IN (".implode(", ", $arPackets).")");
        $db->querynow("DELETE FROM `packet_order` WHERE ID_PACKET_ORDER IN (".implode(", ", $arPackets).")");
    }
	### mÃ¼ssen updates gemacht werden?
	foreach($ar_update as $table => $field)
	{
	  $num = $db->fetch_atom("select count(*) from ".$table." where ".$field." = ".$id);
	  if($num > 0)
	  {
	    $update_tables[$table] = $field;
		$msg[] = "Es wurden ".$num." zu verÃ¤ndernde DatensÃ¤tze in Tabelle ".$table." gefunden.";
	  }
	}
	foreach($ar_delete as $table => $field)
	{
	  $num = $db->fetch_atom("select count(*) from ".$table." where ".$field." = ".$id);
	  #echo ht(dump($GLOBALS['lastresult']));
	  if($num > 0)
	  {
	    $delete_tables[$table] = $field;
		$msg[] = "In Tabelle ".$table." wurden ".$num." DatensÃ¤tze zum lÃ¶schen gefunden.";
	  }
	}
  } // kann gelÃ¶scht werden
  return array ('msg' => implode("<br />", $msg), 'err' => implode('<br />', $err),
    'update_tables' => $update_tables, 'delete_tables' => $delete_tables,
	'deleteable' => $deleteable);
}

function eventlog($str_event='info', $str_info='', $str_err=NULL)
{
   global $ab_path, $db;
   $str_event = strtolower($str_event);
   if (($str_event == "info") && ($GLOBALS["evenlog_debug"] !== true)) {
       return;
   }

   if(!$db || !is_object($db))
   {
     $fp = @fopen($ab_path."cache/LOG.txt", "wa");
	 if($fp)
	 {
	   fwrite($fp, $str_event."\t".$str_info."\t".$str_err."\n");
	   fwrite($fp, 'error'."\tkeine Datenbank verfÃ¼gbar\t".$str_err."\n");
	   fclose($fp);
	 } // fp = true
	 else
	   echo "<pre>".$str_event."\t".$str_info."\t".$str_err."\n</pre>";
   } // keine db
   else
   {
       $backtrace = null;
       if ($GLOBALS["nar_systemsettings"]["SITE"]["ERROR_BACKTRACE"]) {
           $ar_backtrace = debug_backtrace(0);
           if (count($ar_backtrace) > 1) {
               array_shift($ar_backtrace);
               foreach ($ar_backtrace as $btIndex => $btEntry) {
                   if (!empty($btEntry["args"])) {
                       foreach ($btEntry["args"] as $argIndex => $argValue) {
                           if (gettype($argValue) == "object") {
                               $ar_backtrace[$btIndex]["args"][$argIndex] = get_class($argValue) . "-Object";
                           }
                       }
                       $ar_backtrace[$btIndex]["args_joined"] = '("' . implode('", "', $ar_backtrace[$btIndex]["args"]) . '")';
                   } else {
                       $ar_backtrace[$btIndex]["args_joined"] = "";
                   }
               }
               $backtrace = serialize($ar_backtrace);
           }
       }
       $ar = array("FK_USER" => (int)$GLOBALS['uid'], "STAMP" => date('Y-m-d H:i:s'), "EVENT" => $str_event, "S_INFO" => $str_info, "S_ERR" => $str_err, "S_BACKTRACE" => $backtrace);
       $db->update("eventlog", $ar);
   } // db ist da
} // eventlog()

 function todo($dsc, $datei=NULL, $fkt=NULL, $code=NULL, $stamp=NULL, $sysname=NULL)
 {
   global $db;
   $chk = false;
   if($sysname)
   {
     $chk = $db->fetch_atom("select ID_CRONTAB from crontab
	   where
	   		(
	   			SYSNAME='".trim($sysname)."' OR
	   			(  EINMALIG = 1 AND DATEI ='".sqlString($datei)."' )
	   		)
	   and ERLEDIGT IS NULL");
   }
   if(!$chk)
   {
     if(!$stamp)
	   $stamp = date('Y-m-d H:i:s');
     $ins = array
	 (
	   'DSC' => $dsc,
	   'DATEI' => $datei,
	   'FUNKTION' => $fkt,
	   'CODE' => $code,
	   'EINMALIG' => 1,
	   'SYSNAME' => $sysname,
	   'PRIO' => 1,
	   'FIRST' => $stamp
	 );
	 $id = $db->update('crontab', $ins);
	 eventlog("info", "Neue Aufgabe eingetragen ID_CRONTAB: ".$id);
   } // kann eingetragen werden
 } // todo()

function updateSystemSettings($arUpdatedSettings) {
    global $db;
    $arOptionsByPlugin = array();
    // Read options from database
    $arOptions = $db->fetch_table("SELECT * FROM `option` ORDER BY plugin, typ");
    foreach ($arOptions as $row) {
        $arOptionsByPlugin[$row['plugin']][$row['typ']] = $row['value'];
    }
    // Add updated options
    foreach ($arUpdatedSettings as $plugin => $arPluginOptions) {
        foreach ($arPluginOptions as $typ => $value) {
            $queryUpdate = 'UPDATE `option` SET value="'.mysql_real_escape_string($value).'" '.
                'WHERE plugin="'.mysql_real_escape_string($plugin).'" AND typ="'.mysql_real_escape_string($typ).'"';
            $result = $db->querynow($queryUpdate);
            $arOptionsByPlugin[$plugin][$typ] = $value;
        }
    }
    // Write new cache file
    $optionFile = $GLOBALS["ab_path"]."cache/option.php";
    $optionDump = '<?'. 'php $nar_systemsettings = '. php_dump($arOptionsByPlugin, 0). '; ?'. '>';
    file_put_contents($optionFile, $optionDump);
    chmod($optionFile, 0777);
    return true;
}

function registerAutoloader() {
    global $ab_path;

    spl_autoload_register(function ($class) {
        global $ab_path;

        // elastica
        if (file_exists($ab_path .'sys/elastica/lib/' . $class . '.php')) {
            require_once($ab_path .'sys/elastica/lib/' . $class . '.php');
        }

        // PSR-0
        $class_file = $ab_path.'sys/'.str_replace('_', DIRECTORY_SEPARATOR, $class).".php";
        if (file_exists($class_file)) {
            require_once($class_file);
        }

    });
}

?>
