<?php
/* ###VERSIONSBLOCKINLCUDE### */



function GetExecutionTime(&$row, $i) {
	global $ab_path;
	$lockfile = $ab_path."cache/_cron".$row['ID_CRONTAB'].".lock";
	if (file_exists($lockfile)) {
		$execution_start = file_get_contents($lockfile);
		$row["EXECUTION_TIME"] = floor((time() - $execution_start) / 60);
	} else {
		$row["EXECUTION_TIME"] = -1;
	}
}

 if($_REQUEST['del'])
 {
   $db->querynow("delete from crontab where ID_CRONTAB=".$_REQUEST['del']);
 } // löschen

 if($idc = $_REQUEST['donow'])
 {
   $ar = $db->fetch1("select * from crontab where ID_CRONTAB=".$idc);

   unset($ar['ID_CRONTAB']);
   $ar['EINMALIG'] = 1;
	 $ar['ERLEDIGT']=NULL;
	 $ar['LAST']=NULL;
   $ar['FIRST'] =  date('Y-m-d H:i:s');
   $ar['EINHEIT']=NULL;

   $db->update("crontab", $ar);
   // Trigger cronjob now!
   @file_get_contents($tpl_content->tpl_uri_baseurl_full("/cron/cronjob.php"));  
   die(forward("index.php?page=crontab"));

 }
 if ($idc = (int)$_REQUEST['unlock'])
 {
	$lockfile = $ab_path."cache/_cron".$idc.".lock";
 	$db->querynow("UPDATE `crontab` SET FEHLER=0 WHERE ID_CRONTAB=".$idc);
 	@unlink($lockfile);
	die(forward("index.php?page=crontab"));
 }

 $npage = ($_REQUEST['npage'] ? $_REQUEST['npage'] : 1);
 $perpage = 50;
 $limit = ($npage*$perpage)-$perpage;

 $tpl_content->addvar("npage", $npage);

 ### Feste Crons

 $ar_liste = $db->fetch_table("select * from crontab
  where EINMALIG IS NULL
  order by LAST DESC
 ");

 $tpl_content->addlist("liste", $ar_liste, "tpl/de/crontab.row.htm", "GetExecutionTime");

 ### Aufgaben

 // Nur die 5 aktuellsten pro Tag (ausgenommen bei Fehlern)

 $all = $db->fetch_atom("select count(*) from crontab ct
   where ct.EINMALIG IS NOT NULL and
    ((SELECT count(*) FROM crontab WHERE
      SYSNAME=ct.SYSNAME AND DATEDIFF(FIRST,ct.FIRST)=0 AND FIRST>ct.FIRST)<5
    OR ERLEDIGT IS NULL)");

 $liste = $db->fetch_table("select ct.*,left(ct.CODE, 30) as shorttext from crontab ct
   where ct.EINMALIG IS NOT NULL and
    ((SELECT count(*) FROM crontab WHERE
      SYSNAME=ct.SYSNAME AND DATEDIFF(FIRST,ct.FIRST)=0 AND FIRST>ct.FIRST)<5
    OR ERLEDIGT IS NULL)
   order by ct.FIRST DESC
   limit ".$limit.", ".$perpage);

 /*

 $all = $db->fetch_atom("select count(*) from crontab where
   EINMALIG IS NOT NULL
   ");

 $liste = $db->fetch_table("select *,left(CODE, 30) as shorttext from crontab
   where EINMALIG IS NOT NULL
   order by FIRST DESC
   limit ".$limit.", ".$perpage);

 */

 $tpl_content->addlist("liste_aufgaben", $liste, "tpl/de/crontab.aufgaben.htm");

 ### pager
 $tpl_content->addvar("pager", htm_browse($all, $_REQUEST['npage'], "index.php?page=crontab&npage=", $perpage));

$cron_lastexec = 0;
$cron_lastdone = 0;
if (file_exists($ab_path . "cache/_crontmp.php")) {
	$cron_lastexec = filemtime($ab_path . "cache/_crontmp.php");
}
if (file_exists($ab_path . "cache/_crondone.php")) {
	$cron_lastdone = filemtime($ab_path . "cache/_crondone.php");
}
$tpl_main->addvar("LAST_CRON_EXEC", date("d.m.Y H:i:s", $cron_lastexec));
$tpl_main->addvar("NEXT_CRON_SECS", 60 - (time() - $cron_lastexec));
$tpl_main->addvar("NEXT_CRON_EXEC", date("d.m.Y H:i:s", $cron_lastexec + 60));
if ((time() - $cron_lastdone) > 120) {
	$tpl_main->addvar("LAST_CRON_DONE", "<font color='red'>" . date("d.m.Y H:i:s", $cron_lastdone) . "</font>");
	$tpl_main->addvar("LAST_CRON_FAIL", 1);
} else {
	$tpl_main->addvar("LAST_CRON_DONE", date("d.m.Y H:i:s", $cron_lastdone));
}
/*
 todo("TEST", NULL, NULL, "<?php touch('/home/www/web1/html/php_dev/cache/touch.txt'); ?>", date('Y-m-d H:i:s', time()+120), 'EXAMPLE');
*/
?>