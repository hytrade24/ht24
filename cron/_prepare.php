<?php
/* ###VERSIONSBLOCKINLCUDE### */


if (!$uid)
{
  $s_dir = getcwd();
  chdir ('..');
  // prepare
  $n_navroot = 2;
  $s_cachepath = '../cache/';
  require_once '../inc.app.php';
  require_once 'sys/lib.kernel.php';
  nocache();
//  $uid = session_init();
  require_once 'inc.all.php';
  maqic_unquote_gpc();
  // db connect
  if (!$noconnect)
  {
    $dbobj = new ebiz_db($db_name, $db_host, $db_user, $db_pass);
    unset($db_user); unset($db_pass);
  }
  // done
  chdir($s_dir);
}
?>