<?php
/* ###VERSIONSBLOCKINLCUDE### */


  function profile_check($uid = NULL)
  {
    global $err, $db;
    if (is_null($uid)) $uid = $GLOBALS['uid'];
/**/
    $req = array (
      'VORNAME' => 'Vorname',
      'NACHNAME' => 'Name',
      'EMAIL' => 'E-Mail'
    );
    $ar_miss = array ();
    foreach ($req as $s_col => $s_label) if (!$_POST[$s_col])
      $ar_miss[] = $s_label;
    if (count($ar_miss))
      $err[] = 'Die folgenden Felder sind nicht ausgef&uuml;llt:<br />&nbsp;&nbsp;&nbsp;'
        . implode(', ', $ar_miss);
/*/
    $req = array (
      'VORNAME' => 'Vorname fehlt.',
      'NACHNAME' => 'Name fehlt.',
      'STRASSE' => 'Strasse fehlt.',
      'PLZ' => 'PLZ fehlt.',
      'ORT' => 'Ort fehlt.',
      'FK_LANG' => 'W&auml;hlen Sie eine Sprache.',
      'EMAIL' => 'E-Mail fehlt.'
    );
    foreach ($req as $s_col => $s_msg) if (!$_POST[$s_col])
      $err[] = $s_msg;
/**/

    if ($_POST['EMAIL'])
    {
      if (!validate_email($_POST['EMAIL']))
        $err['EMAIL'] = 'Bitte geben Sie eine g&uuml;ltige E-Mail-Adresse ein.';
      else
      {
        $sql_mail = "'". mysql_escape_string($_POST['EMAIL']). "'";
        if ((int)$db->fetch_atom("select count(*) from user
            where EMAIL=". $sql_mail. ($uid ? ' and ID_USER<>'. $uid : '')
          ) || ($uid && (int)$db->fetch_atom("select count(*) from nl_recp
            where EMAIL=". $sql_mail. ' and FK_USER<>'. $uid))
        )
          $err[] = 'Es existiert bereits ein User mit dieser E-Mail-Adresse.';
      }
    }

    if ($_POST['URL'])
    {
      $_POST['URL'] = preg_replace('(^\w+://(.*)$)', '$1', $_POST['URL']);
      if (!validate_url('http://'. $_POST['URL']))
        $err['URL'] = 'Die URL ist ung&uuml;ltig.';
    }
  }
?>