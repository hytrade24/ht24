<?php
/* ###VERSIONSBLOCKINLCUDE### */


/*
Letzte Änderung
2006-06.29   GetUserId(), GetUserName(), GetModuleValue() eingefügt
2006-06.06   Funktion AddRole2User erweitert
2006-06.06   Zeile bei #220606 eingefügt
2006-05.29   Funktionen AddRole2User($role_name,$user_id), DelRole2User($role_name,$user_id) eingefügt
2006-05.18   Funktion CountryCode($uid) eingefügt
*/

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
      if ( !is_numeric($role_name[$i]) ) # wenn keine Rollen-ID, sondern Name übergeben => in Anführungszeichen setzen
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
  	for($i=0; $i<count($role_name); $i++) # für jede Rolle
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

function DelRole2User($role_name,$user_id)
{
	global $db;
	$role_id = $db->fetch_atom("SELECT ID_ROLE from role where LABEL='".$role_name."'");
	if ($role_id)
	{
		$db->querynow("delete from `role2user` where FK_USER=".$user_id." and FK_ROLE=".$role_id.";");
		$db->querynow("UPDATE `user` SET SER_PAGEPERM='', SER_KATPERM='' where ID_USER=".$user_id.";");
		retsetUserPerms($user_id);
	}
}

# liefert den CountryCode eines Users, z.B. "DE"
function CountryCode($uid=NULL)
{
	GLOBAL $db;
	if ($uid == NULL)
		$uid = $_GLOBALS['uid'];
	$fkc	= $db->fetch_atom("SELECT FK_COUNTRY from user where ID_USER=".$uid);
	$cc 	= $db->fetch_atom("SELECT CODE from country where ID_COUNTRY=".$fkc);
	return $cc;
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
* erstellt String mit den Seitennamen für den aktuellen Navigationspfad
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
* Vorteile gegenüber array_walk:
* - Wie der Name schon andeutet, läuft die Funktion rekursiv auch durch Elemente, die Arrays sind.
* - Definition einer callback-Funktion ist überflüssig - die Operation wird direkt als String angegeben, der durch exec geschickt wird.
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
* verschickt Mail mit Anhängen
*
* @param string $to
* @param string $from
* @param string $subject
* @param string $message
* @param array $files=false Schlüssel = Dateiname in Mail; Wert = Pfad zur Datei
* @param string $lb = "\n" Zeilenumbruch
*
* @return boolean Ergebnis des mail-Kommandos
*/
  function mail_attach($to, $from, $subject, $message, $files=FALSE, $lb="\n")
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
* @param string $str_nulltext=false Falls angegeben, wird am Anfang des selects ein Element mi value="0" und dem angegebenen Label eingefügt.
*
* @return string HTML-Text
*/
  function nar2select($str_selectproperties, $val_selected, $data, $str_nulltext = FALSE)
  {
#echo "<b>$val_selected</b><br />";
    $ar_opts = array ();
#echo ht(dump($data));
    if ($str_nulltext !== FALSE) $ar_opts[] = '
    <option value="0">'. $str_nulltext. '</option>';
    foreach ($data as $value=>$label)
    {
#echo "$value==". php_dump($val_selected). " => $label : ". ($val_selected == $value ? 'selected ' : ''). "<br />";
      $ar_opts[] = '
    <option '. ((is_array ($val_selected) ? in_array ($value, $val_selected) : $val_selected == $value) ? 'selected ' : '')
        . 'value="'. stdHtmlentities($value). '">'. stdHtmlentities($label). '</option>';
    }
    return '<select '.$str_selectproperties.' class="notizSmall">
      '.implode('', $ar_opts).'
  </select>';
  }

// HTTP ------------------------------------------------------------------------
/**
* Weiterleitung
*
* <b>stat</b>
* - 0 header location, anschließend
* - 1 meta refresh falls kein target angegeben, anschließend
* - 2 javascript-Weiterleitung, anschließend
* - >=3 href-Tag mit Text "weiter"
* Wurden die http-Header schon gesendet oder wird $trg angegeben, entfallen die header-Anweisung und das Meta-Refresh.
* <b>trg</b>
* Falls angegeben, wird der Frame / das Fenster etc. mit dem entsprechenden Namen als Ziel.
* Sonderfälle top, parent, blank
* <b>die</b>
* Falls gesetzt, wird das Skript nach Einleiten der Weiterleitung beendet.
* @param string $url
* @param uint $stat
* @param string $trg=false
* @param boolean $die=true
*/
  function forward($url, $stat=0, $trg=FALSE, $die=TRUE, $delay=0)
  {
    // forward to given URL, $trg=target window
    // stat: <1: header(location), <2: if (!target) meta refresh,
    //       <3: JavaScript,      any: a href
    global $sitetitle, $nar_systemsettings;

    // baseurl
      if(strpos($url, 'http') === FALSE) {
        $baseUrl = $nar_systemsettings['SITE']['BASE_URL'];
        if(strpos($url, $baseUrl) !== 0) {
          
          if (substr($baseUrl, -1) == '/' && substr($url, 0, 1) == '/') {
              $url = substr($url, 1);
          }
        
          $url = $baseUrl.$url;
        }
      }

    if (is_null($trg)) $trg = FALSE;
    if (is_null($die)) $die = TRUE;
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
* Für Popups: öffnendes Fenster aktualisieren und aktuelles Skript beenden
*
* Ist $_POST['saveclose'] wahr, wird nach dem Aktualisieren das aktuelle Fenster geschlossen;
* sonst folgt eine Weiterleitung an die angegebene Adresse.
*
* @param string $s_url
* @global boole $_POST['saveclose']
*/
  function opener_refresh($s_url=NULL)
  {
    if ($_POST['saveclose'])
      die (jsblock('opener.location.reload();opener.focus();window.close();'));
    else
    {
      echo jsblock('opener.location.reload();');
      if ($s_url) forward($s_url, 1);
    }
  }

// ERROR HANDLING --------------------------------------------------------------

if (!function_exists("myerr")) {
      
  /**
  * Fehlermail versenden
  *
  * Erstellt eine Fehlermail mit der angegebenen Message im Subject und den Inhalten aller Superglobals im Body an ERRMAIL.
  * Ist $die wahr, wird anschließend der Parameter msg an die error.php weitergeleitet.
  * Ist SILENCE als false definiert, der Mailbody per echo ausgegeben und die Weiterleitung entfällt.
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
  
    #$body .= "=== MYSQL Last Result === \n".print_r($lastresult, true)." \n\n";
  
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

}

/**
* Dateiupload
*
* verschiebt den Upload ans angegebene Ziel,
* löscht eine eventuell vorhandene ältere Version ($fn_unlink).
* Bei Fehlern werden die Arrays $msg, $wrn und $err mit entsprechenden Meldungen gefüllt.
*
* @param assoc $f Element des $_FILES-Arrays (siehe http://www.php.net/features.file-upload)
* @param string $path_trg Pfad, unter dem der Upload gespeichert werden soll. Der Dateiname setzt sich aus Präfix, einer uniqid und der Originalendung der Datei zusammen.
* @param string $s_prefix='f'
* @param string $fn_unlink=false Falls angegeben, wird die entsprechende Datei aus dem Verzeichnis $path_trg entfernt.

* @global array $msg
* @global array $err
* @global array $wrn

* @return string Bei Erfolg Name der neuen Datei (ohne Pfad), sonst $fn_unlink
*/
function fupload($f, $path_trg, $s_prefix='f', $fn_unlink=FALSE)
{
  global $msg, $err, $wrn;
  if ($f && $f['name'])
  {
#echo ht(dump($f));
    if (!$f['tmp_name'] || !$f['size'])
      $err[] = 'Upload fehlgeschlagen';
    else
    {
	  $str_fn = uniqid($s_prefix).strrchr($f['name'], '.');
	  #$str_fn = time() . $str_fn;
	  #echo ht(dump($f));
      $move_file = move_uploaded_file($f['tmp_name'], $fn_new = $path_trg.$str_fn);
	  #echo "move file: ".ht(dump($move_file));
	  if ($move_file)
      {
        chmod ($fn_new, 0777);
        $msg[] = "Datei hochgeladen als $str_fn.";
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
	{
      #echo "str_fn: ".$str_fn;
	  return $str_fn;
    }
  }

  return ($fn_unlink ? $fn_unlink : FALSE);
}



# Upload eines Bildes mit Resize von 100x100
# @param assoc $f - Element des $_FILES-Arrays (siehe http://www.php.net/features.file-upload)
# @param string $Filename - endgueltiger Dateiname
# @param string $path_trg - Speicherort der Datei
# @param boolan $fn_unlink - wenn TRUE dann wird die Datei geloescht falls vorhanden

function named_picupload($f, $filename, $path_trg, $fn_unlink=FALSE)
{
  global $err, $new_filename, $nar_systemsettings;
  $ok= FALSE;
  $new_filename=array();
  if ($f && $f['name'])
  {
  	$pos = stripos($f['type'],'image');
    if (!$f['tmp_name'] || !$f['size'] || ($pos === FALSE))
      $err[] = 'UPLOAD_ERROR';
	 else
    {
		#$filename=$filename.preg_replace("/([^a-z0-9_\.-])/si","_",$f['name']);
		$ext = strrchr(preg_replace("/([^a-z0-9_\.-])/si","_",$f['name']),'.');
		//$filename=$filename.$ext;
		$new_filename['name']=$filename;
		$new_filename['ext']=$ext;
		//$new_filename['ext']='jpg';
		if ($fn_unlink && file_exists("$path_trg/".$filename.$ext))
          if (!unlink("$path_trg/".$filename.$ext))
			  if (!unlink("$path_trg/".$filename.'_s'.$ext))
          		$err[] = 'UPLOAD_COULD_NOT_DELETE_FILE';

	  if (move_uploaded_file($f['tmp_name'], "$path_trg/".$filename.$ext))
      {
          $binConvert = $nar_systemsettings['SYS']['PATH_CONVERT'];

          system($str = $binConvert . " '" . "$path_trg/" . $filename . $ext . "' -geometry 100x100  -background white -alpha background -quality 100 '" . "$path_trg/" . $filename . '.jpg' . "'\n");
          system($str = $binConvert . " '" . "$path_trg/" . $filename . $ext . "' -geometry 50x50 -background white -alpha background -quality 100 '" . "$path_trg/" . $filename . '_s' . '.jpg' . "'\n");

          chmod("$path_trg/" . $filename . '.jpg', 0666);
          chmod("$path_trg/" . $filename . '_s' . '.jpg', 0666);
          $ok = TRUE;
      }
      else
        $err[] = 'UPLOAD_ERROR_?';
    }
    if (count($err))
	 return $err;
  }
  return $ok;
} // end function named_upload



/**
* Passt die tatsächlichen Maße einer Bilddatei einem Anzeigefenster an
*
* liefert das gleiche Array zurück wie die PHP-Funktion getimagesize,
* passt allerdings die Maße so an, dass das Bild maximal die angegebene Breite und Höhe annimmt.
*
* @param string $fn_bild Pfad und Dateiname
* @param uint $w_fenster=NULL maximale Breite
* @param uint $h_fenster=NULL maximale Höhe
* @param boole $b_allowblowup=false Flag: Vergrößern zulassen

* @return array (uint Breite, uint Höhe, string 'width=".." height=".."')
*/
function getimageresize(
  $fn_bild, # Dateiname
  $w_fenster = NULL, $h_fenster = NULL, # max. Breite/Höhe; NULL=egal
  $b_allowblowup=FALSE # Flag: Vergrößern zulassen
)
{
  if (!file_exists($fn_bild) || !($ar = getimagesize($fn_bild)))
    return;
  // Faktor = max. Größe / aktuelle Größe
  $f_w = (is_null($w_fenster) ? 1 : $w_fenster / $ar[0]);
  $f_h = (is_null($h_fenster) ? 1 : $h_fenster / $ar[1]);
  // Faktor = min(Breitenfaktor, Höhenfaktor)
  $f = min($f_h, $f_w);
  // Maßangaben im Array anpassen
  if ($f<1 || ($f>1 && $b_allowblowup))
  {
    $ar[0] = max(1, (int)($f * $ar[0]));
    $ar[1] = max(1, (int)($f * $ar[1]));
    $ar[3] = 'height="'. $ar[0]. '" width="'. $ar[1]. '"';
  }
  // modifiziertes Array zurück
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

//echo "User :".$user."<br>";
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
    require 'cache/pageperm.1.php';
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
      left join role2user r on r.FK_USER=". $uid. "
      left join pageperm2role s on s.FK_ROLE=r.FK_ROLE and s.IDENT=$sql_ident
      left join pageperm2user v on v.FK_USER=". $uid. " and v.IDENT=$sql_ident
    group by 1
    having PRM>0
    order by 1");
    $db->querynow("update user
      set SER_PAGEPERM='". mysql_escape_string($user['SER_PAGEPERM'] = serialize($nar_ret)). "'
      where ID_USER=". $uid);

	return $nar_ret;
  }
  else {
  //echo ht(dump($user['SER_PAGEPERM']));
    return unserialize($user['SER_PAGEPERM']);
	}
} // function pageperm_read

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
      require 'cache/katperm.1.php';
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
#        ifnull(v.B_OVR, count(s.FK_ROLE)<count(r.FK_ROLE)) as PRM
#        left join katperm2user v on v.FK_USER=". $uid. " and v.IDENT=n.IDENT
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
    return FALSE;
  }

  function validate_date($param)
  {
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})(\s+(\d{2}(:|$)){0,3})?$/', $param, $match))
      return FALSE;
    if (count($match)>4)
    {
      $tmp = explode(':', $match[4]);
      if ($tmp[0]<0 || $tmp[0]>23 || $tmp[1]<0 || $tmp[1]>59 || $tmp[2]<0 || $tmp[2]>59)
        return FALSE;
    }
    return checkdate($match[2], $match[3], $match[1]);
  }

//#######################################################
// Erstellt Blaetter-Abschnitt
//#######################################################

function htm_browse($n_count, $n_current_page, $s_href, $n_perpage,$pagenavpages=5,$countMax=200000) {
	global $db;
	if ($n_count <= $n_perpage) {
		return "";
	}
	$n_pagecount = ceil($n_count / $n_perpage);
	$pagenav = "";
	$pStart = max(1, $n_current_page - $pagenavpages);
	$pMax = min($n_pagecount, ceil($countMax / $n_perpage));
	$pEnd = min($n_current_page + $pagenavpages, $pMax);
	if ($pStart > 1) {
        $firstlink = "		<li><a href=\"" . $s_href . 1 . "\">«</a></li>\n";
    }
    if (($pEnd < $pMax) && ($n_pagecount == $pMax)) {
        $lastlink = "		<li><a href=\"" . $s_href . $pMax . "\">»</a></li>\n";
    }
	if ($n_current_page > 1) {
		$prevlink = "		<li><a href=\"" . $s_href . ($n_current_page - 1) . "\">&lt;</a></li>\n";
	} else {
		$prevlink = "		<li class=\"disabled\"><span>&lt;</span></li>\n";
	}
	if ($n_current_page < $pMax) {
		$nextlink = "		<li><a href=\"" . $s_href . ($n_current_page + 1) . "\">&gt;</a></li>\n";
	} else {
		$nextlink = "		<li class=\"disabled\"><span>&gt;</span></li>\n";
	}
	for ($p = $pStart; $p <= $pEnd; $p++) {
        if ($p == $n_current_page) {
            $pagenav .= "		<li class=\"active\"><a href=\"" . $s_href . $p . "\">" . $p . "</a></li>\n";
        } else {
            $pagenav .= "		<li><a href=\"" . $s_href . $p . "\">" . $p . "</a></li>\n";
        }
    }
  	return 	"<div class=\"pagination pagination-centered\">\n".
  			"	<ul>\n".
  			$firstlink.$prevlink.$pagenav.$nextlink.$lastlink.
  			"	</ul>\n".
  			"</div>";
}

function htm_browse_extended($n_count, $n_current_page, $s_href, $n_perpage,$pagenavpages=5, $addtionalParams = "", $tpl_parent = null,$countMax=200000) {
  	if ($n_count <= $n_perpage) {
  		return "";
  	}
    if ($tpl_parent === null) {
      $tpl_parent = $GLOBALS["tpl_content"];
    }
  	$n_pagecount = ceil($n_count/$n_perpage);
    //die("test: ".$s_href);
    $s_href_action = $tpl_parent->tpl_uri_action($s_href);
  	$pagenav = "";
    
	$pStart = max(1, $n_current_page - $pagenavpages);
	$pMax = min($n_pagecount, ceil($countMax / $n_perpage));
	$pEnd = min($n_current_page + $pagenavpages, $pMax);
	if ($pStart > 1) {
        $firstlink = "		<li><a href=\"".str_replace('{PAGE}', 1, $s_href_action.$addtionalParams)."\" data-page=\"1\">«</a></li>\n";
    }
    if (($pEnd < $pMax) && ($n_pagecount == $pMax)) {
        $lastlink = "		<li><a href=\"".str_replace('{PAGE}', $pMax, $s_href_action.$addtionalParams)."\" data-page=\"".($pMax)."\">»</a></li>\n";
    }
  	if ($n_current_page > 1) {
  		$prevlink = "		<li><a href=\"".str_replace('{PAGE}', ($n_current_page-1), $s_href_action.$addtionalParams)."\" data-page=\"".($n_current_page-1)."\">&lt;</a></li>\n";
  	} else {
  		$prevlink = "		<li class=\"disabled\"><span>&lt;</span></li>\n";
  	}
  	if ($n_current_page < $pMax) {
  		$nextlink = "		<li><a href=\"".str_replace('{PAGE}', ($n_current_page+1), $s_href_action.$addtionalParams)."\" data-page=\"".($n_current_page+1)."\">&gt;</a></li>\n";
  	} else {
  		$nextlink = "		<li class=\"disabled\"><span>&gt;</span></li>\n";
  	}
	for ($p = $pStart; $p <= $pEnd; $p++) {
        if ($p == $n_current_page) {
            $pagenav .= "		<li class=\"active\"><a href=\"" . str_replace('{PAGE}', $p, $s_href_action.$addtionalParams) . "\" data-page=\"".($p)."\">" . $p . "</a></li>\n";
        } else {
            $pagenav .= "		<li><a href=\"" . str_replace('{PAGE}', $p, $s_href_action.$addtionalParams) . "\" data-page=\"".($p)."\">" . $p . "</a></li>\n";
        }
    }
    
  	return 	"<div class=\"pagination pagination-centered\">\n".
  			"	<ul>\n".
  			$firstlink.$prevlink.$pagenav.$nextlink.$lastlink.
  			"	</ul>\n".
  			"</div>";
}



  function monthstr($m)
  {
    static $nar = array (
      1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
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
    elseif (TRUE===$val)
      return 'true';
    elseif (FALSE===$val)
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
        'EMAIL'   => ($user
          ? (strpos($user, '@')
            ? $user
            : $user.strstr($GLOBALS['nar_systemsettings']['SUPPORT']['SP_EMAIL'], '@')
          )
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
    $mail_content['T1'] = parse_mail($mail_content['T1'], $templateParams);
    if ($b_html && file_exists($ab_path."cache/design/mail/".$s_lang."/".$mailTemplate.".htm")) {
        $tpl_mail = new Template($ab_path."mail/".$s_lang."/".$mailTemplate.".htm");
        $tpl_mail->addvars($templateParams);
      $mail_content_html = $tpl_mail;
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

/** /
function array_trim($ar)
{
echo '<i>array_trim</i>';
  if (is_array ($ar))
  {
    $ret = array ();
    foreach ($ar as $k=>$v)
      if (is_array ($v) ? $v = array_trim($v) : $v)
        $ret[$k] = $v;
    return $ret;
  }
  else
    return $ar;
}
/*/
  function array_trim(&$ar)
  {
#echo '<i>array_trim</i>';
    if (is_array ($ar))
      foreach ($ar as $k=>$v)
        if (is_array ($v))
        {
          array_trim($v);
          if (!count($v))
            unset($ar[$k]);
        }
        elseif (!$v)
          unset($ar[$k]);
  }
/**/

  function modify(&$ar)
  {
    $ret = FALSE;
    for($i=1; $i<func_num_args(); $i++)
    {
      $s_key = func_get_arg($i);
      if ($ar[$s_key] != $_POST[$s_key])
      {
        $ar[$s_key] = $_POST[$s_key];
        $ret = TRUE;
      }
    }
    return $ret;
  }

// SUCHE =======================================================================
/*
  laedt Suchparameter bzw. erzeugt/ermittelt Such-ID

  Such-ID wird aus $ar_params[$n_id_search_idx] gelesen
    und dorthin geschrieben
  $nar_move: $idx => $key: Aenderung der Suchparameter per URL
    wenn $ar_params[$idx] <> Suchparameter $key, wird der Parameter angepasst
      und eine entsprechende ID ermittelt/erzeugt

  die folgenden globalen Variablen werden gesetzt:
  Such-ID in
    $id_search
    $ar_params[$n_id_search_idx]
    $tpl_content->vars[ID_SEARCH]
  Such-Parameter in
    $nar_search
    $tpl_content->vars (mit Praefix s_)
*/
  function search_session($n_id_search_idx, $nar_move=array ())
  {
    global $db, $ar_params, $tpl_content, $id_search, $nar_search;
#echo "<b>search_session($n_id_search_idx, ". php_dump($nar_move). ");</b><br />";
  // _POST? Parameter sammeln
#die(ht(dump($ar_params)));
    if ('search'==$_POST['do'])
    {
      recurse($_POST, '$value=trim($value);');
      foreach ($_POST as $k=>$v)
      {
#echo '<b>', $k, '</b>', ht(dump($v));
        if (is_array ($v)) array_trim($v);
#echo ht(dump($v)), '<hr>';
        if ($v && preg_match('/^s_/', $k))
          if (is_array ($v) && preg_match('/^s_bf_/', $k)) // Bitfeld?
            $nar_search[substr($k, 5)] = array_sum($v);
          else
            $nar_search[substr($k, 2)] = $v;
      }
      foreach($nar_move as $idx=>$key)
        if ($ar_params[$idx] != $nar_search[$key])
          $ar_params[$idx] = $nar_search[$key];
#echo ht(dump($ar_params));
    }
    elseif ($id_search = $ar_params[$n_id_search_idx]) // Such-ID in URL?
    {
      $sql_id = mysql_escape_string($id_search);
      // Parameter laden
      if ($tmp = $db->fetch_atom("select SER_PARAMS from search where ID_SEARCH='". $sql_id. "'"))
      {
        $nar_search = unserialize($tmp);
        if ($nar_move) foreach ($nar_move as $idx=>$key)
          if ($ar_params[$idx] != $nar_search[$key])
            // Parameter geaendert? neue Such-ID
          {
            $nar_search[$key] = $ar_params[$idx];
            $id_search = FALSE;
          }
        if ($id_search)
          // sonst: Timestamp aktualisieren
          $db->querynow("update search set STAMP=now() where ID_SEARCH='". $sql_id. "'");
      }
      else
      {
        // Such-Datensatz nicht gefunden
        $id_search = FALSE;
        $tpl_content->addvar('err', 'Suchparameter konnten nicht geladen werden.');
      }
    }
    else
      $nar_search = array ();

    // Suchparameter vorhanden, aber keine ID? --> ID suchen
    if (count($nar_search) && !$id_search)
    {
      ksort($nar_search);
      $sql_ser = mysql_escape_string(serialize($nar_search));
      if ($id_search = $db->fetch_atom("select ID_SEARCH from search
        where SER_PARAMS='". $sql_ser. "'"))
        // gefunden: Timestamp aktualisieren
        $db->querynow("update search set STAMP=now() where ID_SEARCH='". $id_search. "'");
      else
        // sonst: neuen Datensatz anlegen
      {
        $tmp = array_diff(array_keys($nar_search), array_values($nar_move));
#echo ht(dump($tmp));
        if ($tmp)
        {
          $id_search = md5(uniqid(rand()));
          $db->querynow("insert into search (ID_SEARCH, STAMP, SER_PARAMS)
            values ('". $id_search. "', now(), '". $sql_ser. "')");
        }
        else
          // es sei denn, die Suchparameter sind alle in nar_move enthalten
          $id_search = FALSE;
      }
    }

    if ($id_search)
    {
      // ar_params um Such-ID erweitern (wichtig fuers Durchreichen!)
      while (count($ar_params)<$n_id_search_idx)
        $ar_params[] = '';
      $ar_params[$n_id_search_idx] = $id_search;
      $tpl_content->addvar('ID_SEARCH', $id_search);
      $tpl_content->addvars($nar_search, 's_');
    }
  }

 function userImg($ar_file, $target, $thumb = FALSE, $resize = FALSE, $rename = FALSE)
 {
   ### Funktion für Bildupload aus dem öffentlich Userbereich
   ### PARAMS
   /*
     $ar_file = $_FILES['NAME_DES_FELD']
	 $target = Pfad in den Ordner, wo das Bild abgelegt werden soll
	 $thumb muss ein Array sein, das so aussieht:
	  array('width' => (int), 'height' => (int), 'name' => 'Namensprefifix')
	  Wird das array nicht übergeben, wird kein Thumb erzeugt
	 $resize muss ein Array sein wie beiu thumb. Wird es nicht übergeben, bleibt das Bild im Originalzustand
	 $rename = name des neuen bilds. bei false, bleibt der Name des originals erhalten
   */
   ### Rückgabe
   /*
     es kommt ein array zurück das so aussieht:

	 array
	 (
	   'IMG' => array(
	     'path' => 'Dateipfad ohne Datei selbst'
		 'file' => 'Name der Datei',
		 'with' => breite,
		 'height' => höhe
	   ),
	   'THUMB' => array(
	     'path' => 'Dateipfad ohne Datei selbst'
		 'file' => 'Name der Datei',
		 'with' => breite,
		 'height' => höhe
	   )
	 )
   */

   global $err, $db, $nar_systemsettings;

   ### check auf gültiges Bild
   #echo ht(dump($ar_file));
   $hack = explode("/", $ar_file['type']);
   if($hack[0] != "image")
     $err[] = 'no_image';
   else
   {
     $endung = strtolower($hack[1]);
	 $ar_info = getimagesize($ar_file['tmp_name']);
	 if(!$ar_info || empty($ar_info))
	   $err[] = "no_image";
   } // mime == image

   ### Namensbehandlung
   if(empty($err))
   {
     $hack = explode(".", $ar_file['name']);
	 $kick = (count($hack)-1);
	 $datei = str_replace($hack[$kick], "", $ar_file['name']);

	 if($rename)
	   $new_name = $rename.".".$datei;
	 else
	   $new_name = $datei;
	 $ohne_endung = preg_replace("[^a-z0-9_\.-]", "-", $new_name);
	 $new_name = $ohne_endung.$endung;
   } // ist bild
   else
     return FALSE;

   #### Pfad überprüfen
   $pos = strrpos($target, "/");
   if($pos != (strlen($target)-1))
     $target = $target."/";

   ### datei schon vh.
   $time = time();
   if(file_exists($target.$new_name))
     $new_name = $ohne_endung."-".$time.".".$endung;

     $binConvert = $nar_systemsettings['SYS']['PATH_CONVERT'];
   ### thumb
   if($thumb)
   {
     exec($str = $binConvert." '".$ar_file['tmp_name']."' -geometry ".$thumb['width']."x".$thumb['height']." '".$t_name = $target.$thumb['name'].$new_name."' 2>&1", $o);
       chmod($t_name, 0777);
   }
   ### eigentliches Bild
   if($resize)
   {
     system($str = $binConvert." '".$ar_file['tmp_name']."' -geometry ".$resize['width']."x".$resize['height']." '".$f_name = $target.$resize['name'].$new_name."'\n");
       chmod($f_name, 0777);
   }
   else
   {
     move_uploaded_file($ar_file['tmp_name'], $f_name = $target.$new_name);
   } // nicht resizen

   ### target für http aufarbeiten
   global $ab_path;
   echo $ab_path."<hr>";
   $target = str_replace($ab_path, "", $target);

   ### Rückgabe
   return array
	 (
	   'IMG' => array(
	     'path' => $target,
		 'file' => $new_name
	   ),
	   'THUMB' => array(
	     'path' => ($thumb ? $target : FALSE),
		 'file' => ($thumb ? $thumb['name'].$new_name : FALSE)
	   )
	 );

 } // userupload()


 function comment_mail($id, $what)
 {
    $id=(int)$id;
	global $db, $s_lang, $langval, $nar_systemsettings;

	#die($db->lang_select("news"));

	switch($what)
	{
	  case "news":
	   $ar = $db->fetch1("select t.*, s.V1, u.`NAME` as UNAME
	    from `news` t
		 left join string_c s on s.S_TABLE='news'
		  and s.FK=t.ID_NEWS
		  and s.BF_LANG=if(t.BF_LANG_C & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_C+0.5)/log(2)))
		 left join `user` u on t.FK_AUTOR=u.ID_USER
		 where ID_NEWS=".$id."
		");
	  break;
	  case "script":
	   $ar = $db->fetch1("select t.*, s.V1, u.`NAME` as UNAME
	    from `script` t
		 left join string_script s on s.S_TABLE='script'
		  and s.FK=t.ID_SCRIPT
		  and s.BF_LANG=if(t.BF_LANG_SCRIPT & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_SCRIPT+0.5)/log(2)))
		 left join `user` u on t.FK_USER=u.ID_USER
		 where t.ID_SCRIPT=".$id."
		");
	  break;
	  case "tutorial":
	    $ar = $db->fetch1("select t.*, u.NAME as UNAME
		  from tutorial t
		   left join `user` u on t.FK_USER=u.ID_USER
		  where ID_TUTORIAL=".$id);
	  break;

	  default: die("missing \$what in function comment_mail");
	} // switch $what

	$ar['URL'] = $nar_systemsettings['SITE']['SITEURL'];
	sendMailTemplateToUser(0, $ar['FK_USER'], "comment_".$what, $ar);

 } // comment_mail()


function addCanonicalTagByIdent($ident) {
	global $tpl_main;
	addCanonicalTagByUri($tpl_main->tpl_uri_action_full($ident));
}

function addCanonicalTagByUri($uri) {
	global $tpl_main;

	$canonicalMeta = '<link rel="canonical" href="'.$uri.'"/>';

	if($tpl_main->vars['canonical'] == "") {
		$tpl_main->vars['canonical'] = "\n".$canonicalMeta;
	}
}

function perm_checkview($checkthis) {
	global $tpl_content, $uid, $get_uid, $db;

	switch ($checkthis) {
		case 'ALL':
			return 1;
			break;
		case 'USER':
			if ($uid > 0) {
				return 1;
			} else {
				return 0;
			}
			break;
		case 'CONTACT':
			$data = $db->fetch_atom("select status from user_contact where ((FK_USER_A = '" . $uid . "' AND FK_USER_B = '" . $get_uid . "') OR (FK_USER_A = '" . $get_uid . "' AND FK_USER_B = '" . $uid . "'))");
			if ($data == 1) return $data; else
				return 0;
			break;
		default:
			return 0;
			break;
	}
}

function callback_misc_killbb(&$row, $i) {
	$row['DSC'] = preg_replace("#(\[)([^\s\]]*)(\])#si", "", $row['DSC']);
	$row['DSC'] = substr(strip_tags($row['DSC']), 0, 250);
	$row['DSC'] = preg_replace("#(\[)([^\s\]]*)(\])#si", "", $row['DSC']);
	$row['DSC'] = str_replace("&nbsp;", ' ', $row['DSC']);
	$row['DSC'] = str_replace("&nbsp", ' ', $row['DSC']);
}

if (!function_exists("array_flatten")) {
  function array_flatten(&$input, $keepValues = false, $glue = "_", $prefix = "", &$result = array()) {
    return array_flatten_trader7($input, $keepValues, $glue, $prefix, $result);
  }
}

function array_flatten_trader7(&$input, $keepValues = false, $glue = "_", $prefix = "", &$result = array())
{
    if (!is_array($input)) {
        return false;
    }
    $isAssoc = (array_keys($input) !== range(0, count($input) - 1));
    foreach ($input as $index => $value) {
        $indexFull = ($isAssoc ? $prefix . $index : substr($prefix, 0, -1));
        if (is_array($value)) {
            // Recursion
            array_flatten_trader7($value, $keepValues, $glue, $indexFull . $glue, $result);
        } else {
            if ($keepValues === true) {
                $result[$indexFull] = $value;
            } else if ($keepValues === false) {
                $result[$indexFull . $glue . $value] = 1;
            } else if ($keepValues == "both") {
                $result[$indexFull] = $value;
                $result[$indexFull . $glue . $value] = 1;
            }
        }
    }
    return $result;
}

function getCookieDomain() {
	$host = $_SERVER['HTTP_HOST'];
	$hack = explode(".", $host);
	$n = count($hack);

	if(count($hack) >= 2) {
        array_shift($hack);
        $cookie_domain = ".".implode(".", $hack);
	} else {
		$cookie_domain = NULL;
	}

	return $cookie_domain;
}

function callback_jqTreeTransformNodes($parent, &$arNestedSet) {
    $arResult = array();
    while ((count($arNestedSet) > 0) && ($arNestedSet[0]["PARENT"] == $parent)) {
        $arNodeRaw = array_shift($arNestedSet);
        $hasChilds = ($arNodeRaw["RGT"] - $arNodeRaw["LFT"] > 1);
        $arNode = array(
            "id"            => $arNodeRaw["ID_KAT"],
            "class"         => "category",
            "accept"        => false,
            "children"      => false,
            "dragable"      => true,
            "expandable"    => $hasChilds,
            "label"         => $arNodeRaw["V1"],
            "text"         => $arNodeRaw["V1"]
        );
        if ($hasChilds) {
            $arNode["children"] = callback_jqTreeTransformNodes($arNode["id"], $arNestedSet);
        }
        $arResult[] = $arNode;
    }
    return $arResult;
}


?>
