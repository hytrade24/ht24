<?php
/* ###VERSIONSBLOCKINLCUDE### */




 function GetUserId($username)
 {
   GLOBAL $db;
   return $db->fetch_atom("select ID_USER from user where NAME='".mysql_escape_string($username)."'");
 }

 function GetUsername($id)
 {
   GLOBAL $db;
   return $db->fetch_atom("select NAME from user where ID_USER=".$id);
 }

 # liefert den Wert von $field aus Modul mit Namen $modulname, z.B. GetModulValue("galerie","MOD")
 function GetModuleValue($modulname,$field)
 {
   GLOBAL $db, $langval;
   $id = $db->fetch_atom("select ID_MODUL from modul where IDENT='$modulname'");
   $res = $db->fetch_atom("select s.V1 from `moduloption` t
    left join string_opt s on s.S_TABLE='moduloption' and s.FK=t.ID_MODULOPTION and s.BF_LANG=if(t.BF_LANG_OPT & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_OPT+0.5)/log(2)))
   where t.FK_MODUL=".$id." and t.OPTION_VALUE='".$field."'");
   return $res;
 }

function AddRole2User($role_name,$id)
{
	global $db;
  # Kommaauflistung ala "user,member" => array[user,member]
  if (!is_array($role_name) and strpos($role_name,",")>0)
    $role_name = explode(",",$role_name);

	if(is_array($role_name)) # mehrere Rollen
  {
    for ($i=0;$i<count($role_name);$i++)
    {
      if ( !is_numeric($role_name[$i]) ) # wenn keine Rollen-ID, sondern Name Ã¼bergeben => in AnfÃ¼hrungszeichen setzen
      {
        $role_name[$i] = str_replace('"',"'",$role_name[$i]); # "user" durch 'user' ersetzen
        if (strpos("'"," "+$role_name[$i]) == 0) # user durch 'user' ersetzen
          $role_name[$i] = "'" . $role_name[$i] . "'";
      }
    }
  }
  else # eine Rolle
  {
    if ( !is_numeric($role_name) )
    {
      $role_name = str_replace('"',"'",$role_name); # "user" durch 'user' ersetzen
      if (strpos("'"," "+$role_name) == 0) # user durch 'user' ersetzen
        $role_name = "'" . $role_name . "'";
    }
  }

  if ( is_array($role_name) )
  {
  	for($i=0; $i<count($role_name); $i++) # fÃ¼r jede Rolle
		{
      if ( !is_int($role_name[$i]))
      	$role_id = $db->fetch_atom("SELECT ID_ROLE from role where LABEL=".$role_name[$i]);	# Rollen-ID holen
      else
        $role_id = $role_name[$i];

      if (!$role_id)
        die("Rolle konnte nicht gefunden werden. Bitte den Admin informieren!");

		  $res = $db->querynow("insert into role2user set FK_USER=".$id.", FK_ROLE=".$role_id); # Eintrag vornehmen
    if ( $res["int_result"] and  $res["int_result"] != 1062 )
      die(ht(dump($res)));
		}
	}
  else
  {
    //echo (ht(dump($role_name)));
    if ( !is_numeric($role_name) )
    	$role_id = $db->fetch_atom("SELECT ID_ROLE from role where LABEL=".$role_name);	# Rollen-ID holen
    else
      $role_id = $role_name;

    if (!$role_id)
      die("Rolle konnte nicht gefunden werden. Bitte den Admin informieren!");

    $res = $db->querynow("insert into role2user set FK_USER=".$id.", FK_ROLE=".$role_id);
    if ( $res["int_result"] and $res["int_result"] != 1062 )
      die(ht(dump($res)));
  }

	$db->querynow("UPDATE `user` SET SER_PAGEPERM=null, SER_KATPERM=null where ID_USER=".$id);
  resetUserPerms($id);
}

function resetUserPerms($id)
{
		  global $db;
		  $ar_data = $db->fetch_table("select p.ID_PERM, p.IDENT,
			  ifnull(v.BF_GRANT, 0) BF_GRANT, ifnull(v.BF_REVOKE, 0) BF_REVOKE,
			  ifnull(v.BF_INHERIT,0) BF_INHERIT, ifnull(v.BF_CHECK,0) BF_CHECK,
			  ifnull(bit_or(s.BF_ALLOW), 0) BF_INHERIT_NOW
			from perm p
			  left join role2user on role2user.FK_USER=". $id. "
			  left join perm2role s on s.FK_ROLE=role2user.FK_ROLE
			  left join perm2user v on v.FK_USER=". $id. " and v.FK_PERM=ID_PERM
			group by ID_PERM order by ID_PERM");

		  $n_updates = 0;
		  foreach ($ar_data as $i=>$row)
		  {
			if ($row['BF_INHERIT'] != $row['BF_INHERIT_NOW'])
			{
			  $res = $db->perm_inherit($row['ID_PERM'], $id);
			  if ($res['int_result'] && !$res['str_error'])
				$n_updates++;
			}
			if ($row['BF_CHECK'] != ($x = ($row['BF_INHERIT_NOW'] | $row['BF_GRANT']) &~ $row['BF_REVOKE'])) {
			  $db->perm_inherit($row['ID_PERM'], $id);
			  }
		  }
}

function DelRole2User($role_id,$user_id)
{
	global $db;
	if ($role_id)
	{
		$db->querynow("delete from `role2user` where FK_USER=".$user_id." and FK_ROLE=".$role_id.";");
		$db->querynow("UPDATE `user` SET SER_PAGEPERM='', SER_KATPERM='' where ID_USER=".$user_id.";");
		resetUserPerms($user_id);
	}
}

/**
* Navigationspfad
*
* @param ID $id_nav
* @return string IDENT/IDENT/IDENT
* @tables nav
*/
  function nav_ident_path($id_nav, $s_delim='/')
  {
    global $db;
/**/ # nested set
    return implode($s_delim, $db->fetch_nar("select n.ID_NAV, n.IDENT from nav n, nav s
      where s.ID_NAV=$id_nav and s.ROOT=n.ROOT and s.LFT between n.LFT and n.RGT
      order by n.LFT"));
/*/
#echo "<h2>$id_nav</h2>";
    $row = $db->fetch1("select ID_NAV,PARENT,IDENT from nav
      where ID_NAV=$id_nav");
#echo ht(dump($row));
    $res = array ($row['IDENT']);
    while ($row['PARENT'])
    {
      $row = $db->fetch1("select ID_NAV,PARENT,IDENT from nav n
        where ID_NAV=$row[PARENT]");
      array_unshift($res, $row['IDENT']);
#echo ht(dump($row));
    }
#echo implode('/', $res), '<hr />';
#if (22==$id_nav) die ();
    return implode('/', $res);
/**/
  }

/**
* erstellt String mit den Seitennamen fÃ¼r den aktuellen Navigationspfad
*
* @param ID $id_nav=0
* @global $db
* @return string "Label > Label > Label"
* /
  function nav_implode($id_nav=0)
  {
    global $db;
    list($parent, $label) = each($db->fetch_nar(
      "select PARENT, LABEL from nav where ID_NAV=$id_nav"
    ));
    $ar = array ($label);
    while ((int)$parent)
    {
      list($parent, $label) = each($db->fetch_nar(
        "select PARENT, LABEL from nav where ID_NAV=$parent"
      ));
      $ar[] = $label;
    }
    return implode(' > ', array_reverse($ar));
  } // function nav_implode /**/


/**
* wendet die angegebene Operation auf alle Elemente eines Arrays / Objekts an
*
* Vorteile gegenÃ¼ber array_walk:
* - Wie der Name schon andeutet, lÃ¤uft die Funktion rekursiv auch durch Elemente, die Arrays sind.
* - Definition einer callback-Funktion ist Ã¼berflÃ¼ssig - die Operation wird direkt als String angegeben, der durch exec geschickt wird.
*
* @param mixed &$var
* @param string $s_op
* @global $db
* @return string "Label > Label > Label"
*/
  function recurse(&$var, $s_op = '$value=$value')
  {
    if (is_object($var))
      foreach ($var as $k=>$v)
        recurse($var->$k,$s_op);
    elseif (is_array ($var))
      foreach ($var as $k=>$v)
        recurse($var[$k],$s_op);
    else
    {
      $value = &$var;
      eval($s_op. ';');
    }
  } // function recurse

/**
* verschickt Mail mit AnhÃ¤ngen
*
* @param string $to
* @param string $from
* @param string $subject
* @param string $message
* @param array $files=false SchlÃ¼ssel = Dateiname in Mail; Wert = Pfad zur Datei
* @param string $lb = "\n" Zeilenumbruch
*
* @return boolean Ergebnis des mail-Kommandos
*/
  function mail_attach($to, $from, $subject, $message, $files=false, $lb="\n")
  {
    // $to Recipient
    // $from Sender (like "email@domain.com" or "Name <email@domain.com>")
    // $subject Subject
    // $message Content
    // $files hash-array of files to attach
    // $lb is linebreak characters... some mailers need \r\n, others need \n
    $mime_boundary = "<<<:" . md5(uniqid(mt_rand(), 1));
    $header = "From: ".$from;
    if (is_array ($files))
    {
      $header.= $lb;
      $header.= "MIME-Version: 1.0".$lb;
      $header.= "Content-Type: multipart/mixed;".$lb;
      $header.= " boundary=\"".$mime_boundary."\"".$lb;
      $content = "This is a multi-part message in MIME format.".$lb.$lb;
      $content.= "--".$mime_boundary.$lb;
      $content.= "Content-Type: text/plain; charset=\"iso-8859-1\"".$lb;
      $content.= "Content-Transfer-Encoding: 7bit".$lb.$lb;
    }
    $content.= $message.$lb;
    if (is_array ($files))
    {
      $content.= "--".$mime_boundary.$lb;
      foreach ($files as $filename=>$filelocation)
        if (is_readable($filelocation))
        {
          $data = chunk_split(base64_encode(implode("", file($filelocation))));
          $content.= "Content-Disposition: attachment;".$lb;
          $content.= "Content-Type: Application/Octet-Stream;";
          $content.= " name=\"".$filename."\"".$lb;
          $content.= "Content-Transfer-Encoding: base64".$lb.$lb;
          $content.= $data.$lb;
          $content.= "--".$mime_boundary.$lb;
        }
    }
    if (@mail($to, $subject, $content, $header))
      return TRUE;
    return FALSE;
  } // function mail_attach

// HTML ------------------------------------------------------------------------
/**
* sucht in Verzeichnissen nach einer Datei
*
* @param array $ar_dirs
* @param string $str_fn
*
* @return string Pfad
* Das Array wird von vorne nach hinten durchsucht.
* Ist die Datei nicht vorhanden, wird so getan, als sei sie im letzten angegebenen Verzeichnis.
*/
  function findfile($ar_dirs, $str_fn)
  {
    for ($i=0; $i<count($ar_dirs); $i++)
#{if (ereg('^nav_', $str_fn)) echo "$str_path<br />";
      if (file_exists($str_path="$ar_dirs[$i]/$str_fn"))
        return $str_path;
#}
    return end($ar_dirs)."/$str_fn";
  } // function findfile

/**
* erzeugt ein Select aus einem assoziativen Array
*
* @param string $str_selectproperties z.B. <i>name="elementname"</i> etc.
* @param string $val_selected
* @param assoc $data (Value => Label)
* @param string $str_nulltext=false Falls angegeben, wird am Anfang des selects ein Element mi value="0" und dem angegebenen Label eingefÃ¼gt.
*
* @return string HTML-Text
*/
  function nar2select($str_selectproperties, $val_selected, $data, $str_nulltext = false)
  {
#echo "<b>$val_selected</b><br />";
    $ar_opts = array ();
#echo '<!-- '.ht(dump($str_nulltext)).' -->';
    if ($str_nulltext !== false) $ar_opts[] = '
    <option value="0">'. $str_nulltext. '</option>';
    foreach ($data as $value=>$label)
{
#echo "$value==$val_selected => $label : ". ($val_selected == $value ? 'selected ' : ''). "<br />";
      $ar_opts[] = '
    <option '. ((is_array ($val_selected) ? in_array ($value, $val_selected) : $val_selected == $value) ? 'selected ' : '')
        . 'value="'. stdHtmlentities($value). '">'. stdHtmlentities($label). '</option>';
}
    return '<select '.$str_selectproperties.'>
      '.implode('', $ar_opts).'
  </select>';
  }

// HTTP ------------------------------------------------------------------------
/**
* Weiterleitung
*
* <b>stat</b>
* - 0 header location, anschlieÃend
* - 1 meta refresh falls kein target angegeben, anschlieÃend
* - 2 javascript-Weiterleitung, anschlieÃend
* - >=3 href-Tag mit Text "weiter"
* Wurden die http-Header schon gesendet oder wird $trg angegeben, entfallen die header-Anweisung und das Meta-Refresh.
* <b>trg</b>
* Falls angegeben, wird der Frame / das Fenster etc. mit dem entsprechenden Namen als Ziel.
* SonderfÃ¤lle top, parent, blank
* <b>die</b>
* Falls gesetzt, wird das Skript nach Einleiten der Weiterleitung beendet.
* @param string $url
* @param uint $stat
* @param string $trg=false
* @param boolean $die=true
*/
  function forward($url, $stat=0, $trg=false, $die=true, $delay=0)
  {
    // forward to given URL, $trg=target window
    // stat: <1: header(location), <2: if (!target) meta refresh,
    //       <3: JavaScript,      any: a href
    global $sitetitle;
    if (is_null($trg)) $trg = false;
    if (is_null($die)) $die = true;
    if ($stat<0)
      $stat = (int)headers_sent();
    if ($trg)
    {
      $jstrg = ('blank'==$trg ? 'popup(640,480).' : "$trg.");
      $httrg = ' target="'. ('top'==$trg || 'parent'==$trg ? '_':''). $trg. '"';
      $stat = max($stat, 2);
    }
    else
      $jstrg = $httrg = '';
    if ($stat<1 && !$trg && !$delay && !headers_sent())
      header('Location: '.$url);
    if ($stat<2)
    {
      echo '<html><head>
  <title>forwarder - ', $sitetitle, '</title>';
#die(dump($stat));
      if (!$trg) echo '
  <meta http-equiv="refresh" content="'. ceil($delay). '; URL=', $url, '">';
      echo '
</head>';
    }
    if ($stat<3)
    {
      $s_cmd = $jstrg. "location.href='$url'";
      echo '<script language="JavaScript"><!--
  '.($delay ? 'window.setTimeout("'. $s_cmd. '", '. ceil($delay*1000) .');' : $s_cmd. ';' ). '
//--></script>';
    }
    echo '<body>
  <a href="', $url, '"'. $httrg. '>weiter</a>
</body></html>';
    if ($die) die ();
  } // function forward

/**
* FÃ¼r Popups: Ã¶ffnendes Fenster aktualisieren und aktuelles Skript beenden
*
* Ist $_POST['saveclose'] wahr, wird nach dem Aktualisieren das aktuelle Fenster geschlossen;
* sonst folgt eine Weiterleitung an die angegebene Adresse.
*
* @param string $s_url
* @global boole $_POST['saveclose']
*/
  function opener_refresh($s_url=NULL)
  {
    if ($_REQUEST['saveclose'])
      die (jsblock('opener.location.reload();opener.focus();window.close();'));
    else
    {
      echo jsblock('opener.location.reload();');
      if ($s_url) forward($s_url, 1);
    }
  }

// ERROR HANDLING --------------------------------------------------------------
/**
* Fehlermail versenden
*
* Erstellt eine Fehlermail mit der angegebenen Message im Subject und den Inhalten aller Superglobals im Body an ERRMAIL.
* Ist $die wahr, wird anschlieÃend der Parameter msg an die error.php weitergeleitet.
* Ist SILENCE als false definiert, der Mailbody per echo ausgegeben und die Weiterleitung entfÃ¤llt.
*
* @param string $msg
* @param boole $die=true
* @global boole $SILENCE
* @global define ERRMAIL
* @global define SITETITLE
*/
function myerr($msg, $die = TRUE, $sendMail = true) {
	// shows $msg, server time, current vars and values
	// if $GLOBALS[SILENCE]==true: send all per mail;
	// else: if $die echo all; else echo $msg;
	// if $die==$true die
	global $lastresult;

	if (stristr($_SERVER['REQUEST_URI'], "error.php")) die("<h1>Schwerer Datenbankfehler!</h1><p>Ein Administrator wurde per Email informiert!</p>");

	$globs = array('_GET' => $_GET, '_POST' => $_POST, '_ENV' => $_ENV, '_SERVER' => $_SERVER, '_COOKIE' => $_COOKIE, '_FILES' => $_FILES, '_SESSION' => $_SESSION);

	$msg = $msg . ' ' . $_SERVER[REQUEST_URI];

	$body = "message: $msg\n
		server time: " . date('Y-m-d H:i:s') . "\n";
	$body .= "host: " . $_SERVER['HTTP_HOST'] . "\n";
	$body .= "referer: " . $_SERVER['HTTP_REFERER'] . "\n\n";

	$e = new Exception();
	$body .= "==== Backtrace ==== \n".$e->getTraceAsString()."\n\n";

	foreach ($globs as $var => $value) {
		$body .= "==== $var ==== \n" . print_r($value, true)."\n\n";
	}

	$body .= "=== MYSQL Last Result === \n".print_r($lastresult, true)." \n\n";

	if ($GLOBALS['SILENCE']) {
        if ($sendMail) {
            $ok = true;
            eventlog('error', 'Fehler beim Aufruf der URL '.$_SERVER['REQUEST_URI'], $msg."\n\nhost: " . $_SERVER['HTTP_HOST'] . "\nreferer: " . $_SERVER['HTTP_REFERER'] . "\n");
            //$ok = mail(ERRMAIL, SITETITLE . ' Error', $body);
        } else {
            $ok = false;
        }
		if (SESSION) {
			$_SESSION['msg'] = $msg;
			if ($die) forward('/error.php?ok=' . (int)$ok);
		} else if ($die) forward('/error.php?ok=' . (int)$ok . '&msg=' . rawurlencode($msg));
	} else {
		if ($die) die ($body); else
			echo $body;
	}
} // function myerr

/**
* Dateiupload
*
* verschiebt den Upload ans angegebene Ziel,
* lÃ¶scht eine eventuell vorhandene Ã¤ltere Version ($fn_unlink).
* Bei Fehlern werden die Arrays $msg, $wrn und $err mit entsprechenden Meldungen gefÃ¼llt.
*
* @param assoc $f Element des $_FILES-Arrays (siehe http://www.php.net/features.file-upload)
* @param string $path_trg Pfad, unter dem der Upload gespeichert werden soll. Der Dateiname setzt sich aus PrÃ¤fix, einer uniqid und der Originalendung der Datei zusammen.
* @param string $s_prefix='f'
* @param string $fn_unlink=false Falls angegeben, wird die entsprechende Datei aus dem Verzeichnis $path_trg entfernt.

* @global array $msg
* @global array $err
* @global array $wrn

* @return string Bei Erfolg Name der neuen Datei (ohne Pfad), sonst $fn_unlink
*/
function fupload($f, $path_trg, $s_prefix='f', $fn_unlink=false)
{
  global $msg, $err, $wrn;
  if ($f && $f['name'])
  {
#echo ht(dump($f));
    if (!$f['tmp_name'] || !$f['size'])
      $err[] = 'Upload fehlgeschlagen';
    else
    {
      #echo $f['name'];
	  $str_fn = preg_replace("/([^a-z0-9_\.-])/si","_",$f['name']);
	  $str_fn = time() . $str_fn;
	  if (@move_uploaded_file($f['tmp_name'], $fn_new = "$path_trg/$str_fn"))
      {
        chmod ($fn_new, 0666);
        $msg[] = "Datei hochgeladen als $str_fn.";
		$filename = $str_fn;
        if ($fn_unlink && file_exists($fn_old = "$path_trg/$fn_unlink"))
          if (@unlink($fn_old = "$path_trg/$fn_unlink"))
            $msg[] = "alte Datei $fn_unlink gel&ouml;scht";
          else
            $wrn[] = "$fn_unlink konnte nicht gel&ouml;scht werden";
      }
      else
        $err[] = 'move_file fehlgeschlagen';
    }
    if (!count($err))
      return $str_fn;
	return $err;
  }
  return ($fn_unlink ? $fn_unlink : false);
}

/**
* Passt die tatsÃ¤chlichen MaÃe einer Bilddatei einem Anzeigefenster an
*
* liefert das gleiche Array zurÃ¼ck wie die PHP-Funktion getimagesize,
* passt allerdings die MaÃe so an, dass das Bild maximal die angegebene Breite und HÃ¶he annimmt.
*
* @param string $fn_bild Pfad und Dateiname
* @param uint $w_fenster=NULL maximale Breite
* @param uint $h_fenster=NULL maximale HÃ¶he
* @param boole $b_allowblowup=false Flag: VergrÃ¶Ãern zulassen

* @return array (uint Breite, uint HÃ¶he, string 'width=".." height=".."')
*/
  function getimageresize(
    $fn_bild, # Dateiname
    $w_fenster = NULL, $h_fenster = NULL, # max. Breite/HÃ¶he; NULL=egal
    $b_allowblowup=false # Flag: VergrÃ¶Ãern zulassen
  )
  {
    if (!file_exists($fn_bild) || !($ar = getimagesize($fn_bild)))
      return;
    // Faktor = max. GrÃ¶Ãe / aktuelle GrÃ¶Ãe
    $f_w = (is_null($w_fenster) ? 1 : $w_fenster / $ar[0]);
    $f_h = (is_null($h_fenster) ? 1 : $h_fenster / $ar[1]);
    // Faktor = min(Breitenfaktor, HÃ¶henfaktor)
    $f = min($f_h, $f_w);
    // MaÃangaben im Array anpassen
    if ($f<1 || ($f>1 && $b_allowblowup))
    {
      $ar[0] = max(1, (int)($f * $ar[0]));
      $ar[1] = max(1, (int)($f * $ar[1]));
      $ar[3] = 'height="'. $ar[0]. '" width="'. $ar[1]. '"';
    }
    // modifiziertes Array zurÃ¼ck
    return $ar;
  } // function getimageresize

/**
* liest bzw. generiert die Seitenrechte eines Users
*
* liefert Array (IDENT=>1) fuer zulaessige Seiten
*
* @param mixed $user User - uint ID, string NAME oder assoc; wenn NULL, greifen die Globals

* @global assoc $user User-Daten
* @global uint $uid User-ID

* @return array (string Ident => uint 1); wenn der User nicht zu ermitteln ist: Rechte fuer Rolle 1
*/
function pageperm_read($user = NULL)
{
  global $db;
  if (!$user)
  {
    if (!is_array ($user = $GLOBALS['user']))
      $user = $db->fetch1("select * from user where ID_USER=" .$GLOBALS['uid']);
  }
  elseif ((int)$user)
    $user = $db->fetch1("select * from user where ID_USER=". (int)$user);
  elseif (is_string($user))
    $user = $db->fetch1("select * from user where NAME='". mysql_escape_string($user). "'");
  if (!$user || !$user['ID_USER'])
  {
    require '../cache/pageperm.1.php';
    return $nar_pageperm[1];
  }

  // Feld leer? dann neu generieren
/** /if(1)/*/  if (!$user['SER_PAGEPERM'])/**/
  {
    $uid = $user['ID_USER'];
    $sql_ident = "if(ROOT=1, n.IDENT, concat('admin/', n.IDENT))";
    $nar_ret = $db->fetch_nar("select
      $sql_ident,
      ifnull(v.B_OVR, count(s.FK_ROLE)<count(r.FK_ROLE)) as PRM
    from nav n
      left join role2user r on r.FK_USER=".$uid."
      left join pageperm2role s on s.FK_ROLE=r.FK_ROLE and s.IDENT=$sql_ident
      left join pageperm2user v on v.FK_USER=".$uid." and v.IDENT=$sql_ident
    group by 1
    having PRM>0
    order by 1");


    $db->querynow("update user
      set SER_PAGEPERM='". mysql_escape_string($user['SER_PAGEPERM'] = serialize($nar_ret)). "'
      where ID_USER=". $uid);
    return $nar_ret;
  }
  else {

  	//echo ht(dump(unserialize($user['SER_PAGEPERM'])));
    return unserialize($user['SER_PAGEPERM']);
	}
} // function pageperm_read

function pageperm_read_role($roleId) {
    global $db;
    $sql_ident = "if(ROOT=1, n.IDENT, concat('admin/', n.IDENT))";
    $nar_ret = $db->fetch_nar($q="
    SELECT
        $sql_ident,
        IF(COUNT(s.FK_ROLE)>0,0,1) as PRM
    FROM nav n
    LEFT JOIN pageperm2role s ON s.FK_ROLE=".(int)$roleId." and s.IDENT=$sql_ident
    GROUP BY 1
    ORDER BY 1");
    return $nar_ret;
}

function katperm_read($user = NULL)
{
  global $db;
  if (!$user)
  {
    if (!is_array ($user = $GLOBALS['user']))
      $user = $db->fetch1("select * from user where ID_USER=" .$GLOBALS['uid']);
  }
  elseif ((int)$user)
    $user = $db->fetch1("select * from user where ID_USER=". (int)$user);
  elseif (is_string($user))
    $user = $db->fetch1("select * from user where NAME='". mysql_escape_string($user). "'");

  if (!$user || !$user['ID_USER'])
  {
    require '../cache/katperm.1.php';
    return $nar_katperm[1];
  }

  // Feld leer? dann neu generieren
  if (!$user['SER_KATPERM'])
  {
    $uid = $user['ID_USER'];
    $nar_ret = $db->fetch_nar("select
      ID_KAT,
      count(s.FK_ROLE)<count(r.FK_ROLE) as PRM
    from kat n
      left join role2user r on r.FK_USER=". $uid. "
      left join katperm2role s on s.FK_ROLE=r.FK_ROLE and s.FK_KAT=n.ID_KAT
    group by 1
    having PRM>0
    order by 1");
#      ifnull(v.B_OVR, count(s.FK_ROLE)<count(r.FK_ROLE)) as PRM
#      left join katperm2user v on v.FK_USER=". $uid. " and v.IDENT=n.IDENT
    $db->querynow("update user
      set SER_KATPERM='". mysql_escape_string($user['SER_KATPERM'] = serialize($nar_ret)). "'
      where ID_USER=". $uid);
    return $nar_ret;
  }
  else
    return unserialize($user['SER_KATPERM']);
} // function katperm_read

  function date2iso($param)
  {
    $hack = explode('.', $param);
    if (count($hack) == 3 && strlen($param) == 10)
      return sprintf('%02d-%02d-%04d', $hack[2], $hack[1], $hack[0]);
    return false;
  }

  function validate_date($param)
  {
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})(\s+(\d{2}(:|$)){0,3})?$/', $param, $match))
      return false;
    if (count($match)>4)
    {
      $tmp = explode(':', $match[4]);
      if ($tmp[0]<0 || $tmp[0]>23 || $tmp[1]<0 || $tmp[1]>59 || $tmp[2]<0 || $tmp[2]>59)
        return false;
    }
    return checkdate($match[2], $match[3], $match[1]);
  }


//#######################################################
// Erstellt Blaetter-Abschnitt
//#######################################################

function htm_browse($n_count, $n_current_page, $s_href, $n_perpage)
{
  global $db;
#echo "<b>htm_browse($n_count, $n_current_page, $s_href, $n_perpage)</b><br>";
  $pagenavpages = 5;
#  $n_count = (int)$db->fetch_atom($s_countquery);
  if ($n_count <= $n_perpage)
  {
    return "";
  }
  $n_pagecount = ceil($n_count/$n_perpage);
  if ($n_current_page>1)
    $prevlink ='&nbsp;<span class="pagenum"><a href="'.$s_href.($n_current_page-1).'" data-page="'.($n_current_page-1).'" title="rÃ¼ckw&auml;rts" class="pagenum">&laquo;</a></span>';
  if ($n_current_page<$n_pagecount)
    $nextlink = '&nbsp;<span class="pagenum"><a href="'.$s_href.($n_current_page+1).'" data-page="'.($n_current_page+1).'" title="vorw&auml;rts" class="pagenum">&raquo;</a></span>';
  for ($p=1; $p<=$n_pagecount; $p++)
  {
    if ($pagenavpages && ($p <= $n_current_page-$pagenavpages || $p >= $n_current_page+$pagenavpages))
    {
      if ($p==1)
        $firstlink = '&nbsp;<a href="'.$s_href.$p.'" data-page="'.($p).'" title="zum Anfang">&laquo; Anfang</a> ...';
      if ($p==$n_pagecount)
        $lastlink ='... <a href="'.$s_href.$p.'" data-page="'.($p).'" title="zum Ende">Ende &raquo;</a>';
    }
    else
      if ($p==$n_current_page)
        $pagenav .='&nbsp;<span class="pagenumstatic">'.$p.'</span>';
      else
        $pagenav .= '&nbsp;<span class="pagenum"><a href="'.$s_href.$p.'" data-page="'.($p).'" title="zur Seite '.$p.' ">'.$p.'</a></span>';
  }

  return '
  <table border="0" align="center" class="browseTable"><tr>
    <td width="100%" align="center">Navigation  -> Seitenanzahl : (<b>'.$n_pagecount.'</b>)<br><br>
<b>'.$firstlink.'&nbsp;'.$prevlink.'&nbsp;'.$pagenav.'&nbsp;'.$nextlink.'&nbsp;'.$lastlink.'</b></td>
  </tr></table>';
}

function handle_move_request($s_table, $s_rmcallback=NULL, $s_where='1', $s_parentcol='PARENT')
{
  global $db;
  $s_id = 'ID_'. strtoupper($s_table);
  if ($id = (int)$_REQUEST['id'])
  {
    $tmp = $db->fetch1("select $s_parentcol,POS from $s_table where $s_id=$id");
    $pos = (int)$tmp['POS'];
    $parent = (int)$tmp[$s_parentcol];
    switch ($_REQUEST['do'])
    {
      case 'v0':
        if ($pos)
        {
          $db->query("update $s_table set POS=0 where $s_id=$id");
          $db->query("update $s_table set POS=POS-1 where $s_parentcol=$parent and POS>$pos and $s_where");
        }
        break;
      case 'v1':
        if (!$pos)
        {
          $db->query("update $s_table set POS=POS+1 where $s_parentcol=$parent and POS>$pos and $s_where");
          $db->query("update $s_table set POS=1 where $s_id=$id");
        }
        break;
      case 'rmx':
/** /
echo 'rm - kinder l&ouml;schen<br>';
/*/
        $q = array ($id=>$id);
        $ar_del = array ();
        while ($id = array_shift($q))
        {
          $q += $db->fetch_nar("select $s_id, $s_id from $s_table where $s_parentcol=$id and $s_where");
          if ($s_rmcallback)
            $s_rmcallback($id);
          $ar_del[] = $id;
        }
        $db->query("delete from $s_table where $s_id in(". implode(', ', $ar_del).")");
/**/
        break;
      case 'rm':
/** /
echo 'rm - kinder verschieben<br>';
/*/
        if ($s_rmcallback)
          $s_rmcallback($id);
        $db->query("delete from $s_table where $s_id=$id");
        $ar_mv0 = $db->fetch_nar("select $s_id, $s_id from $s_table where $s_parentcol=$id and POS=0 and $s_where");
        $ar_mv1 = $db->fetch_nar("select $s_id, $s_id from $s_table where $s_parentcol=$id and POS>0 and $s_where");
        if ($pos)
        {
          $db->query("update $s_table set POS=POS+". (count($ar_mv1)-1). "
            where $s_parentcol=$parent and POS>$pos");
          $db->query("update $s_table set $s_parentcol=$parent, POS=POS+". ($pos-1). " where $s_parentcol=$id and POS>0 and $s_where");
        }
        $db->query("update $s_table set $s_parentcol=$parent, POS=0 where $s_parentcol=$id and $s_where");
/**/
        break;
      case 'up':
        if ($pos>1)
        {
          $tmp = $db->fetch1("select $s_id,POS from $s_table
            where $s_parentcol=$parent and POS<$pos and POS>0 and $s_where
            order by POS desc limit 0,1");
#echo ht(dump($GLOBALS[lastresult])),ht(dump($tmp));
          if($tmp[$s_id])
          {
            $db->query("update $s_table set POS=$tmp[POS] where $s_id=$id");
            $db->query("update $s_table set POS=$pos where $s_id=$tmp[$s_id]");
          }
        }
        break;
      case 'dn':
        $tmp = $db->fetch1("select $s_id,POS from $s_table
          where $s_parentcol=$parent and POS>$pos and $s_where order by POS limit 0,1");
        if($tmp[$s_id])
        {
          $db->query("update $s_table set POS=$tmp[POS] where $s_id=$id");
          $db->query("update $s_table set POS=$pos where $s_id=$tmp[$s_id]");
        }
        break;
      case 'lt':
        if ($parent)
        {
          $tmp = $db->fetch1("select PARENT,POS from $s_table where $s_id=$parent");
          if (!$tmp['POS'])
            $tmp['POS'] = 1;
          if ($pos)
          {
            $db->query("update $s_table set POS=POS+1 where PARENT=$tmp[PARENT] and POS>$tmp[POS] and $s_where");
            $db->query("update $s_table set PARENT=$tmp[PARENT], POS=$tmp[POS]+1 where $s_id=$id");
            $db->query("update $s_table set POS=POS-1 where PARENT=$parent and POS>$pos and $s_where");
          }
          else
            $db->query("update $s_table set PARENT=$tmp[PARENT] where $s_id=$id");
        }
        break;
      case 'rt':
        if ($pos>1)
        {
          if($id_pred = $db->fetch_atom("select $s_id from $s_table
            where PARENT=$parent and POS<$pos and POS>0 and $s_where
            order by POS desc limit 0,1"))
          {
            $pos_neu = 1+(int)$db->fetch_atom("select max(POS) from $s_table where PARENT=$id_pred and $s_where");
            $db->query("update $s_table set PARENT=$id_pred, POS=$pos_neu where $s_id=$id");
            $db->query("update $s_table set POS=POS-1 where PARENT=$parent and POS>$pos and $s_where");
          }
        }
        break;
    } // end switch $do
#die(ht(dump($db->q_queries)));
    if (count($db->q_queries))
    {
      $db->submit();
      return true;
    }
  }
  return false;
} // function handle_move_request

  function monthstr($m)
  {
    static $nar = array (
      1 => 'Januar', 'Februar', 'MÃ¤rz', 'April', 'Mai', 'Juni',
      'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember');
     return (($ret = $nar[(int)$m]) ? $ret : $m);
  }

  function php_dump($val, $noindentmax=5, $s_indent='')
  {
    if (is_array ($val))
    {
      if ($n = count($val))
      {
        if ($n>$noindentmax)
          { $s_a = "\n$s_indent  "; $s_z = "\n$s_indent"; $s_m = ','; }
        else
          { $s_a = $s_z = ''; $s_m = ', '; }
        $ar_ret = array ();
        if (count(array_intersect(array_keys(array_fill(0, $n, 1)), array_keys($val))) != $n)
          foreach($val as $k=>$v)
            $ar_ret[] = $s_a. php_dump($k, $noindentmax, $s_indent. '  ')
              . " => ". php_dump($v, $noindentmax, $s_indent. '  ');
        else // numerisch ab 0 aufsteigend indiziert
          foreach($val as $k=>$v)
            $ar_ret[] = $s_a. php_dump($v, $s_indent. '  ');
        return 'array ('. implode($s_m, $ar_ret). $s_z. ")";
      }
      else
        return 'array ()';
    }
    elseif (is_object($val))
      return 'new '. get_class($val). '()';
    elseif (preg_match("/^[1-9][0-9]*$/", $val) || preg_match("/^[0-9]+\.[0-9]+$/", $val)) //(is_numeric($val))
      return (($val >= -PHP_INT_MAX) && ($val <= PHP_INT_MAX) ? $val : "'".$val."'");
    elseif (true===$val)
      return 'true';
    elseif (false===$val)
      return 'false';
    else
      return "'". str_replace("'", "\\'", $val). "'";
  }

  function kmail_user(&$user)
  {
    if (is_numeric($user) && $user>0)
      $user = $GLOBALS['db']->fetch1("select * from user where ID_USER=". $user);
    elseif (preg_match('/^(.*)\<(.*)\>$/', $user, $match))
      $user = array (
        'ID_USER' => 0,
        'NAME'    => $match[1],
        'EMAIL'   => $match[2]
      );
    elseif (!$user || is_string($user))
      $user = array (
        'ID_USER' => 0,
        'NAME'    => $GLOBALS['nar_systemsettings']['SITE']['SITENAME'],
        'EMAIL'   => ($user ? (strpos($user, '@') ? $user : $user.strstr($GLOBALS['nar_systemsettings']['SUPPORT']['SP_EMAIL'], '@'))
                            : $GLOBALS['nar_systemsettings']['SUPPORT']['SP_EMAIL'])
      );
  }

function sendMailTemplateToUser($from, $to, $mailTemplate, $templateParams, $b_hideaddr = true, $b_cc = false, $b_html = null, $ar_attachments = array()) {
    global $ab_path, $db, $s_lang, $nar_systemsettings;
    require_once $ab_path.'sys/lib.user.php';

    $b_html = ($b_html !== null ? $b_html : $nar_systemsettings["EMAIL"]["USE_HTML"]);

    if((int)$to > 0) {
        $userManagement = UserManagement::getInstance($db);
        $recipientUser = $userManagement->fetchById($to);
        $tmpLanguage = $s_lang;

        if($recipientUser) {
            $userLanguage = $recipientUser['FK_LANG'];
            set_language($userLanguage);
        }
    }

    $templateParams['SITEURL'] = $nar_systemsettings['SITE']['SITEURL'];
    $templateParams['SITENAME'] = $nar_systemsettings['SITE']['SITENAME'];
    $templateParams['CURRENCY_DEFAULT'] = $nar_systemsettings['MARKTPLATZ']['CURRENCY'];

    $mail_content = $db->fetch1($db->lang_select("mailvorlage")." where SYS_NAME='".$mailTemplate."'");
    $mail_content_html = null;
    $mail_content['V1'] = parse_mail($mail_content['V1'], $templateParams);
    if ($b_html && file_exists($ab_path."cache/design/mail/".$s_lang."/".$mailTemplate.".htm")) {
        $tpl_mail = new Template($ab_path."mail/".$s_lang."/".$mailTemplate.".htm");
        $tpl_mail->addvars($templateParams);
      $mail_content_html = $tpl_mail;
    } else {
        $mail_content['T1'] = parse_mail($mail_content['T1'], $templateParams);
    }

	$additionalCC = array();
	$additionalBCC = array();
	if($mail_content['FK_MAILVORLAGE_NOTIFICATION_GROUP'] != null && $mail_content['FK_MAILVORLAGE_NOTIFICATION_GROUP'] > 0) {
		$mailNotificationGroup = $db->fetch1("SELECT * FROM mailvorlage_notification_group g WHERE g.ID_MAILVORLAGE_NOTIFICATION_GROUP = '".(int)$mail_content['FK_MAILVORLAGE_NOTIFICATION_GROUP']."'");
		$additionalCC = explode("\n", $mailNotificationGroup['CC']);
		$additionalBCC = explode("\n", $mailNotificationGroup['BCC']);
	}

    kmail($from, $to, $mail_content['V1'], $mail_content['T1'], $b_hideaddr, $b_cc, $mail_content_html, $ar_attachments, $additionalCC, $additionalBCC);

    if($recipientUser) {
        set_language($tmpLanguage);
    }
}

function kmail($from, $to, $subject, $body, $b_hideaddr = TRUE, $b_cc = FALSE, $bodyHtml = null, $ar_attachments = array(), $additionalCC = array(), $additionalBcc = array())
{
    global $db, $langval, $ab_path, $nar_systemsettings, $s_lang;
    require_once $ab_path . 'sys/swiftmailer/swift_required.php';

    #die("Test: ".$b_html." / ".$subject." / ".$from." / ".$to);
    // Get text footer
        $footer = $db->fetch_atom("
		SELECT
			s.T1
		FROM `mailvorlage` m
		LEFT JOIN `string_mail` s
			ON s.FK=m.ID_MAILVORLAGE AND s.S_TABLE='mailvorlage' AND
			s.BF_LANG=if(m.BF_LANG_MAIL & " . $langval . ", " . $langval . ", 1 << floor(log(m.BF_LANG_MAIL+0.5)/log(2)))
		WHERE
			m.SYS_NAME='FOOTER'");
    $body .= "\n" . $footer;
    if ($bodyHtml !== null) {
        // Get html footer (if available)
        if (file_exists($ab_path . "cache/design/mail/" . $s_lang . "/FOOTER.htm")) {
            $footer = new Template($ab_path . "mail/" . $s_lang . "/FOOTER.htm");
        } else {
            $footer = nl2br(stdHtmlentities($footer));
        }
        $tplMail = new Template($ab_path."mail/".$s_lang."/SKIN.htm");
        $tplMail->addvar("V1", $subject);
        $tplMail->addvar("T1", $bodyHtml);
        $tplMail->addvar("FOOTER", $footer);
        $bodyHtml = $tplMail->process(false);
    }
    kmail_user($from);
    kmail_user($to);

    // Datensatz eintragen
    $row = array(
        'FK_USER_TO' => $to['ID_USER'],
        'SUBJECT' => $subject,
        'BODY' => $body,
        'B_HIDEADDR' => (int)$b_hideaddr,
        'STAMP' => 'now()'
    );

    if ($from['ID_USER'])
        $row['FK_USER_FROM'] = $from['ID_USER'];
    else
        $row['S_FROM'] = $from['NAME'] . ' <' . $from['EMAIL'] . '>';
    if ($row['FK_USER_TO'])
        $id = (int)$GLOBALS['db']->update('mail', $row);
    else
        $id = NULL;


    $recipientName = $to['VORNAME'] . ' ' . $to['NACHNAME'];

	if(!isset($to['EMAIL']) || strlen(trim($to['EMAIL'])) == 0) {
		eventlog('error', 'E-Mail Versand ohne gültige E-Mail Adresse', print_r(array($to,$subject), true));

		return false;
	}

	try {

        $message = (new Swift_Message())
            ->setCharset('utf-8')
            ->setSubject($subject)
            ->setFrom(array($from['EMAIL'] => $from['NAME']))
            ->setTo(array($to['EMAIL'] => $recipientName))
            ->setBody($body, "text/plain");
        if ($bodyHtml !== null) {
            $message->addPart($bodyHtml, "text/html");
        }

        if ($b_cc) {
            $message->setBcc($from['EMAIL']);
        }
        if (is_array($additionalCC) && count($additionalCC) > 0) {
            foreach ($additionalCC as $key => $value) {
                $message->addCc(trim($value));
            }
        }
        if (is_array($additionalBcc) && count($additionalBcc) > 0) {
            foreach ($additionalBcc as $key => $value) {
                $message->addBcc(trim($value));
            }
        }
        if ($b_hideaddr) {
            $hiddenFrom = array(('noreply' . strstr($GLOBALS['nar_systemsettings']['SUPPORT']['SP_EMAIL'], '@')) => $from['NAME']);
            $message->setFrom($hiddenFrom);
            $message->setReplyTo($hiddenFrom);
        }
        if (!empty($ar_attachments)) {
            // Datei-Anhänge hinzufügen
            foreach ($ar_attachments as $attachmentIndex => $attachmentSwift) {
                $message->attach($attachmentSwift);
            }
        }

        if (isset($nar_systemsettings['EMAIL']['DELIVERY_ADDRESS']) && trim($nar_systemsettings['EMAIL']['DELIVERY_ADDRESS'] != '')) {
            $message->setTo($nar_systemsettings['EMAIL']['DELIVERY_ADDRESS']);
        }

        if ($nar_systemsettings['EMAIL']['TRANSPORT_TYPE'] == "smtp") {//use smtp settings

            $encryption = $nar_systemsettings['EMAIL']['SMTP_ENCRYPTION'];
            if ($encryption == "none") {
                $encryption = null;
            }

            $transport = new Swift_SmtpTransport(
                $nar_systemsettings['EMAIL']['SMTP_HOST'],
                $nar_systemsettings['EMAIL']['SMTP_PORT'],
                $encryption
            );
            $transport->setUsername($nar_systemsettings['EMAIL']['SMTP_USER']);
            $transport->setPassword($nar_systemsettings['EMAIL']['SMTP_PASS']);

        } else if ($nar_systemsettings['EMAIL']['TRANSPORT_TYPE'] == "sendmail") {//use send mail settings
            $transport = new Swift_SendmailTransport($nar_systemsettings['EMAIL']['SENDMAIL_PATH']);
        }

        $mailer = new Swift_Mailer($transport);

        $result = $mailer->send($message);
    } catch (Exception $e) {
		eventlog('error', 'E-Mail Versand fehlgeschlagen!', $e->getMessage(), true);
    }

    return array($id, ($result > 0));
}
 # $SILENCE=false;

function parse_mail($string,$ar=array())
{
  $tpl_tmp = new Template("tpl/de/empty.htm");
  $tpl_tmp->tpl_text = $string;
  $tpl_tmp->addvars($ar);
  return $tpl_tmp->process();
}

  function scriptfreischaltung($in)
  {

  } // scriptfreischaltung


function array_flatten(&$input, $keepValues = false, $glue = "_", $prefix = "", &$result = array()) {
    if (!is_array($input)) {
        return false;
    }
    $isAssoc = (array_keys($input) !== range(0, count($input) - 1));
    foreach ($input as $index => $value) {
        $indexFull = ($isAssoc ? $prefix.$index : substr($prefix, 0, -1));
        if (is_array($value)) {
            // Recursion
            array_flatten($value, $keepValues, $glue, $indexFull.$glue, $result);
        } else {
            if ($keepValues === true) {
                $result[$indexFull] = $value;
            } else if ($keepValues === false) {
                $result[$indexFull.$glue.$value] = 1;
            } else if ($keepValues == "both") {
                $result[$indexFull] = $value;
                $result[$indexFull.$glue.$value] = 1;
            }
        }
    }
    return $result;
}
?>