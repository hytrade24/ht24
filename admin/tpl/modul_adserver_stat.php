<?php
/* ###VERSIONSBLOCKINLCUDE### */

  



require_once 'sys/lib.stats.php';
$perpage=1;
if ($_REQUEST['FK_ADS']) {
	$ar_data = $db->fetch1('select * from ads where ID_ADS='.$_REQUEST['FK_ADS']);
	$tpl_content->addvars($ar_data);
	
	$where = "where FK_ADS=".$_REQUEST['FK_ADS'];
	
}

//Pages
//Anzahl der unterschiedlichen Jahr/Monat eintaege auselsen
$all = $db->querynow("select count(*) from ads_stats $where group by Year(DATUM),  month(DATUM)  ");
$all =mysql_num_rows($all['rsrc']); // Anzahl

//Limit zusammen bauen
$limit = ((($_REQUEST['npage'] ? $_REQUEST['npage'] : $_REQUEST['npage']=1)-1)*$perpage);

if ($limit==0)
	$s_limit=" limit 1";
else
	$s_limit =  'limit '.$limit.','.$perpage;

//Datum aus DB-Lesen
$ar_datum=$db->fetch1("select Year(DATUM) as jahr, month(DATUM) as monat from ads_stats $where group by Year(DATUM), month(DATUM)  order by Year(DATUM) DESC , month(DATUM) DESC ".$s_limit);

//Pager bauen
$tpl_content->addvar("pager", htm_browse($all, $_REQUEST['npage'], "index.php?page=".$tpl_content->vars['curpage']."&frame=".$tpl_content->vars['curframe']."&FK_ADS=".$_REQUEST['FK_ADS']."&npage=", $perpage));

if ($_REQUEST['npage']>1) {
	$tpl_content->addvar('nextseite',0);
}
else {
	$tpl_content->addvars(stats_getdata_year($_REQUEST['FK_ADS']));
	$tpl_content->addvars(stats_getdata_month('', '',$_REQUEST['FK_ADS']));
	$tpl_content->addvar('nextseite',1);
}
	
$tpl_content->addvar('displayeddatum', $month[$ar_datum['monat']].' '.$ar_datum['jahr']);
$tpl_content->addvars(stats_getdata($_REQUEST['FK_ADS'], $ar_datum['monat'],$ar_datum['jahr']));
?>