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

if (!function_exists("cache")) {
    // load/create cache file ======================================================
    function cache(&$trgvar, $s_filename, $s_php, $timeout = 300)
        /*
         * &$trgvar       target variable
         * $s_filename    cache file path
         * $s_php         php code that echoes the cache file´s contents
         * $timeout=300   max. age of cache file (NULL, 0 etc.: force refresh); true: force NO refresh
         */
    {
        if (true !== $timeout || !$timeout || !$s_filename ||
            !file_exists($s_filename) || time() - filemtime($s_filename) > $timeout) {
            ob_start();
            eval($s_php);
            $trgvar = ob_get_contents();
            ob_end_clean();
            if ($s_filename) {
                $fp = fopen($s_filename, 'w');
                fputs($fp, $trgvar);
                fclose($fp);
            }
        } else
            $trgvar = implode('', file($s_filename));
    }
}

// Session =====================================================================
function session_init()
{
    global $db;
    $cookieDomain = (!empty($GLOBALS['nar_systemsettings']['SITE']['COOKIE_DOMAIN']) ? $GLOBALS['nar_systemsettings']['SITE']['COOKIE_DOMAIN'] : null);
    session_set_cookie_params(0, '/', $cookieDomain);
    session_start();

    if ((int)$_COOKIE['ebizuid_' . session_name() . '_uid']) {
        $cookieUid = (int)$_COOKIE['ebizuid_' . session_name() . '_uid'];
        $cookieHash = $_COOKIE['ebizuid_' . session_name() . '_hash'];

        $tmpUser = $db->fetch1("select * from `user` where ID_USER='" . mysql_real_escape_string($cookieUid) . "' and STAT=1");

        if ($tmpUser && pass_compare($cookieUid . $tmpUser['PASS'], $cookieHash)) {
            define ('SESSION', true);
            $uid = $cookieUid;

            if ($_SESSION['USER_IS_ADMIN'] === null) {
                $_SESSION['USER_IS_ADMIN'] = (int)$db->fetch_atom("SELECT count(*) FROM `role2user` ru JOIN `role` r ON r.ID_ROLE=ru.FK_ROLE AND FK_USER=".$uid." WHERE r.LABEL='Admin'");
            }
            return $uid;
        }
    }
    else {

    define ('SESSION', false);
    if ($_COOKIE['ebiz_referer']=='') {
        $_COOKIE['ebiz_referer'] = $_SERVER['HTTP_REFERER'];
    }
    }
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

function get_user($uid, $preventLogout = false) {
	global $ab_path, $nar_systemsettings;
	if ($uid) {
		$user = $GLOBALS['db']->fetch1("SELECT
				u.ID_USER, u.FK_COMPANY, u.FIRMA, u.FK_GROUP, u.FK_USERGROUP,
				u.FK_USER_INVOICE, u.FK_USER_VERSAND,
				u.FK_LANG, u.STAT, u.IS_VIRTUAL, u.VB_USER, u.NAME, u.SER_PAGEPERM, u.SER_KATPERM,
				u.HASNEWS, u.VORNAME, u.NACHNAME, u.CACHE, u.EMAIL, u.STRASSE, u.PLZ, u.ORT, u.FK_COUNTRY, u.TEL,
				u.FK_PAYMENT_ADAPTER, u.DEFAULT_CONSTRAINTS, u.ABO_FORUM, u.TOP_USER,
				u.JSON_ADDITIONAL,
				g.PRIVATE, g.BF_CONSTRAINTS as USER_CONSTRAINTS_ALLOWED
			FROM `user` u
			LEFT JOIN `usergroup` g ON g.ID_USERGROUP=u.FK_USERGROUP
			WHERE u.ID_USER=". $uid);
		if (!$user || !$user['STAT']) {
            if ($preventLogout) {
                return array ('ID_USER'=>0, 'STAT'=>1);
            } else {
                die(forward("/logout.php"));
            }
			//die('access revoked');
		}
		unset($user['PASS']);
    
    if ($user["JSON_ADDITIONAL"] !== null) {
      $arUserJson = json_decode($user["JSON_ADDITIONAL"], true);
      if (is_array($arUserJson)) {
        $user["JSON_ADDITIONAL"] = $arUserJson;
      } else {
        $user["JSON_ADDITIONAL"] = array();
      }
    } else {
      $user["JSON_ADDITIONAL"] = array();
    }

		require_once $ab_path."sys/lib.ad_constraint.php";
		$user = AdConstraintManagement::appendAdContraintMapping($user, "USER_CONSTRAINTS_ALLOWED");
		return $user;
	} else {
		$user = array ('ID_USER'=>0, 'STAT'=>1);
	}
	return $user;
}

// Sprache ermitteln ===========================================================
function get_language() {
    global $db, $ar_urlrewritevars, $lang_list;

    $langval = false;
    if ($GLOBALS['nar_systemsettings']['SITE']['MOD_REWRITE']) {
        $domainRaw = Api_IDN::decodeIDN($_SERVER['HTTP_HOST']);
        $tmp = explode('.', strtolower($domainRaw));
        $s_lang = null;

        $uri = 'http://'.$domainRaw.$_SERVER["REQUEST_URI"];

        foreach ($lang_list as $language) {
            $domain = (!empty($language['DOMAIN']) ? $language['DOMAIN'] : $GLOBALS['nar_systemsettings']['SITE']['SITEURL']);
            $domain_full = $domain.$language["BASE_URL"];
            if ($domain_full != '' && strpos($uri, $domain_full) === 0) {
                $s_lang = $language['ABBR'];
            }
        }

        if($s_lang == null) {
            $availableLanguages = array_keys($lang_list);

            if ((count($tmp) < 3) || !in_array($tmp['0'], $availableLanguages)) {
                // use default language

                foreach ($lang_list as $key => $value) {
                    if ($lang_list[$key]['ID_LANG'] == $GLOBALS['nar_systemsettings']['SITE']['std_country']) break;
                }

                $s_lang = $key;
            } else {
                $s_lang = $tmp['0'];
            }
        }

    } elseif ($s_lang = $_REQUEST['lang']) ; elseif (!SESSION || !($s_lang = $_SESSION['lang'])) {
        $lang = $db->fetch1("select BITVAL, ABBR from lang order by BITVAL desc limit 0,1");
        $s_lang = $lang['ABBR'];
        $langval = (int)$lang['BITVAL'];
    }
    if (false === $langval) $langval = (int)$db->fetch_atom("select BITVAL from lang where ABBR='$s_lang'");

    if (SESSION) $_SESSION['lang'] = $s_lang; else
        $ar_urlrewritevars['lang'] = $s_lang;

    return array($s_lang, $langval);
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
        include $s_cachepath. 'nav'. $n_navroot. '.'. $s_lang. '.php'; // Struktur: $ar_nav
        include $s_cachepath. 'nav'. $n_navroot. '.php'; // Zuordnung Ident/Alias => ID_NAV: $nar_ident2nav

        // Infoseiten array
        @include_once "cache/info.".$s_lang.".php";

        if($lang_list[$newLang]['DOMAIN'] != '') {
            $nar_systemsettings['SITE']['SITEURL'] = $lang_list[$newLang]['DOMAIN'];
        } else {
            $nar_systemsettings['SITE']['SITEURL'] = $originalSystemSettings['SITE']['SITEURL'];
        }

        if($lang_list[$newLang]['BASE_URL'] != '') {
            $nar_systemsettings['SITE']['BASE_URL'] = $lang_list[$newLang]['BASE_URL'];
        } else {
            $nar_systemsettings['SITE']['BASE_URL'] = $originalSystemSettings['SITE']['BASE_URL'];
        }

        $nar_tplglobals['lang'] = $s_lang;
        $lang_list[$newLang]['is_current'] = true;
    }
}

function setAccessControlAllowOriginHeader() {
	global $lang_list, $nar_systemsettings;

	if(isset($_SERVER['HTTP_ORIGIN'])) {
		$allowedDomains = array();
		$allowedDomains[] = str_replace(array("http://", "https://"), array("", ""), $nar_systemsettings['SITE']['SITEURL']);
		foreach($lang_list as $key => $lang) {
			if($lang['DOMAIN'] != "") {
				$allowedDomains[] = str_replace(array("http://", "https://"), array("", ""), $lang['DOMAIN']);
			}
		}

		$domainRegex = implode("|", $allowedDomains);

		if(preg_match("/^http(s)?:\/\/(.*\.)?(".$domainRegex.")$/", $_SERVER['HTTP_ORIGIN'])) {
			header("Access-Control-Allow-Origin: ".$_SERVER['HTTP_ORIGIN']);
			header('Access-Control-Allow-Credentials: true');
		}
	}
}

// error401 =====================================================================
function error401()
{
	die(forward('401.htm'));
}
// error401 =====================================================================

function s_findkid($id, $rec=0)
{
  global $ar_nav, $nar_pageallow;
  if($id==0)
  {
    foreach($ar_nav as $key => $row)
    {
      if($row['B_VIS'] && $row['IDENT'] && $nar_pageallow[$row['IDENT']])
      	return $row['IDENT'];
    }
  }
  else
  {
    $row = $ar_nav[$id];
    if ($row['B_VIS'] || !$rec)
    {
      if (($tmp = $row['IDENT']) && $nar_pageallow[$tmp])
        return $tmp;
      if ($row['B_VIS'] && (!$row['IDENT'] || $nar_pageallow[$row['IDENT']]))
        foreach($row['KIDS'] as $kid_id)
          if ($tmp = s_findkid($kid_id, $rec+1))
            return $tmp;
    }
  }
  return false;
}

/*
function mod_rewrite($s_url)
{
  $url = parse_url($s_url);
  if ($url['query'])
  {
    $tld = false;
    parse_str($url['query'], $arr);
    $query = $comma = array ();
    foreach($arr as $k=>$v) if ($k) switch($k)
    {
      case 'lang': // subdomain
        $tld = $v; break;
      case 'page': // fuer komma-separierte einschuebe
        $comma[0] = $v; break;
      default: // hinter ?
        $query[$k] = "$k=$v";
    }
    if ($tld)
    {
      if (!$url['host'])
      {
        $uri = parse_url($_SERVER['SCRIPT_URI']);
        $url['scheme'] = $uri['scheme'];
        $url['host'] = $uri['host'];
        $url['port'] = $uri['port'];
        if(!preg_match('#^/#', $url['path']))
          $url['path'] = dirname($uri['path']. '/'. $url['path']);
      }
      $url['host'] = preg_replace('#^([^.]+\.)?([^.]+(\.[^.]+)?)$#', $tld.'.$2', $url['host']);
    }
    if (count($comma))
    {
      $ar = array ();
      $n = max(array_keys($comma));
      for ($i=1;$i<=$n;$i++) $ar[] = $comma[$i];
      $url['path'] = preg_replace('#\b([^.]+)(\.\w+)$#e',
        '"'. ($comma[0] ? $comma[0] : '$1')
        .(count($ar) ? ','.implode(',', $ar) : '')
        .'". ($comma[0] && ".php"=="$2" ? ".htm" : "$2")', $url['path']);
    }
    $s_ret = ($url['scheme'] ? $url['scheme']. '://' : '')
      . ($url['user'] ? $url['user']. ($url['pass'] ? ':'.$url['pass'] : ''). '@' : '')
      . $url['host']
      . ($url['port'] ? ':'.$url['port'] : '')
      . $url['path']
      . (count($query) ? '?'. implode('&', $query) : ''). $url['fragment'];
    return $s_ret;
  }
  else
    return $s_url;
}
*/

function parse(&$tpl_main)
{
  global $ar_urlrewritevars;

  // html correction
  $ar_headlines = array ();
  function merkremove($match, $m1='', $m2='') {
    if ($m2 && !strcasecmp('meta', $m1))
      $GLOBALS['ar_headlines'][strtolower($m2)] = $match;
    else
      $GLOBALS['ar_headlines'][] = $match;
    return '';
  }

  //berni 02.06.2006
/*
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
  $text=$tpl_main->process();  //berni 02.06.2006

  // url rewrite
#unset($ar_urlrewritevars['lang']);
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
  // wenn mehrere title-tags im Text stehen: letztes in head-Tag einbauen
  /* berni 02.06.2006
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
  // (mod) url rewrite
#  $text = preg_replace('%([\'"])(index.php\?(.+))\1%e', '"$1". mod_rewrite("$2"). "$1"', $text);

  return $text;
}

if (!function_exists("eventlog")) {
  
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
       fwrite($fp, 'error'."\tkeine Datenbank verfügbar\t".$str_err."\n");
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
             #if (count($ar_backtrace) > 1) {
             #    array_shift($ar_backtrace);
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
             #}
         }
         $ar = array("FK_USER" => (int)$GLOBALS['uid'], "STAMP" => date('Y-m-d H:i:s'), "EVENT" => $str_event, "S_INFO" => $str_info, "S_ERR" => $str_err, "S_BACKTRACE" => $backtrace);
         $db->update("eventlog", $ar);
     } // db ist da
  } // eventlog()
    
}


 function todo($dsc, $datei=NULL, $fkt=NULL, $code=NULL, $stamp=NULL, $sysname=NULL)
 {
   global $db;
   $chk = false;
   if($sysname)
   {
     $chk = $db->fetch_atom("select ID_CRONTAB from crontab
	   where SYSNAME='".trim($sysname)."' and ERLEDIGT IS NULL");
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

//
function useronline ($user,$uid,$path)
{
	global $db,$s_lang;
	$ip         =getenv('REMOTE_ADDR');
	$Hostname_ = gethostbyaddr($ip);
	$timeout = 20;
	//$file   = getenv("REQUEST_URI");
  
  // Update useronline
  if (!array_key_exists("frame", $_REQUEST) || ($_REQUEST["frame"] != "ajax")) {
		$db->querynow("DELETE FROM useronline WHERE (now() - INTERVAL ".$timeout." MINUTE >= LASTACTIV) or (USERIP='$ip' and ID_USER='$uid')");
		$db->querynow("INSERT INTO `useronline` (`ID_USER`,`USER`,`PAGENAME`,`USERIP`,`HOST`,`S_LANG`,`REFERER`)  VALUES ('".$uid."','".$user."','".$path."','".$ip."','".$Hostname_."','".$s_lang."','".mysql_escape_string($_COOKIE['ebiz_referer'])."')");
  }
  
		$res=$db->fetch1("select count(*) as useronlines from `useronline`");
		if ($uid > 0 )
			$db->querynow("update user set LASTACTIV =now() where ID_USER = '".$uid."'");
		return $res['useronlines'];
}

function getUserBox($uid,$cache)
{
  #echo $uid;
  $uid=(int)$uid;
  global $ab_path, $db, $s_lang;
  $stamp = @filemtime($boxpath = $ab_path."cache/users/".$cache."/".$uid."/box.".$s_lang.".htm");
  if(!$stamp || $stamp < (time()-86400))
  {
    $tpl = new Template("tpl/".$s_lang."/userbox.htm");
	$ar=$db->fetch1("select * from `user` where ID_USER=".$uid);
	$tpl->addvars($ar);
	if(!is_dir($ab_path."cache/users/".$cache."/".$uid))
	{
	  system("mkdir ".$ab_path."cache/users/".$cache."/".$uid."\n");
	  system("chmod 0777 ".$ab_path."cache/users/".$cache."/".$uid."\n");
	}
	@file_put_contents($boxpath, $code = $tpl->process());
	return $code;
  } // muss neu
  else
  {
    return file_get_contents($boxpath);
  } // nicht neu
} // getUserbox()

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
