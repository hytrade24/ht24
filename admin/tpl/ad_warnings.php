<?php
/* ###VERSIONSBLOCKINLCUDE### */



if($_REQUEST['kick']) {
	$db->querynow("delete from verstoss where ID_VERSTOSS=".(int)$_REQUEST['kick']);
}

$orderby = 'oldest';
$show = 'all';

$perpage = 30;
$npage = ($_REQUEST['npage'] ? (int)$_REQUEST['npage'] : 1);
$limit = ($perpage*$npage)-$perpage;

if(isset($_GET['FK_AD'])) { $_POST = array('FK_AD' => $_GET['FK_AD'], 'show' => $show, 'orderby' => $orderby); }

if(count($_POST)) {
	$ids = $db->update("searchstring", array('S_STRING' => serialize($_POST)));
	die(forward("index.php?page=ad_warnings&ID_S=".$ids));
} else if((int)$_GET['ID_S']) {
	$id_s = (int)$_GET['ID_S'];
	$ser = $db->fetch_atom("select
		S_STRING
			from
				searchstring
			where
				ID_SEARCHSTRING=".$id_s);
	if(!empty($ser)) {
		$ar = unserialize($ser);
		$orderby = $ar['orderby'];
		$show = $ar['show'];
	}
}

$orders = array(
	'oldest' => array('Älteste zuerst', 'v.STAMP ASC'),
	'newest' => array('Neuste zuerst', 'v.STAMP DESC'),
	'melder' => array('Melder', 'u.NAME ASC'),
	'owner' => array('Besitzer', 'ua.NAME ASC'),
);

$ar_opt = array();
foreach($orders as $order => $data) {
	$selected = ($order == $orderby ? ' selected' : '');
	$ar_opt[] = '<option value="'.$order.'"'.$selected.'>'.$data[0].'</option>';
}
$tpl_content->addvar("orders", implode("\n", $ar_opt));

$ar_opt = array();
$shows = array(
	'all' => array('Alle', ''),
	'updates' => array('Bereits aktualisierte', ' STAMP_AD_UPDATE > STAMP '),
	'not_updated' => array('Noch nicht aktualisierte', ' STAMP_AD_UPDATE <= STAMP ')
);
foreach($shows as $str_show => $data) {
	$selected = ($str_show == $show ? ' selected' : '');
	$ar_opt[] = '<option value="'.$str_show.'"'.$selected.'>'.$data[0].'</option>';
}
$tpl_content->addvar("shows", implode("\n", $ar_opt));
if($ar != null) { $tpl_content->addvars($ar); }

$orderby = $orders[$orderby][1];

$whereSql = array();
if($shows[$show][1] != "") {
	$whereSql[] = $shows[$show][1];
}
if(isset($ar['FK_AD']) && $ar['FK_AD'] != "") {
	$whereSql[] = " v.FK_AD = '".mysql_real_escape_string($ar['FK_AD'])."' ";
}
if(isset($ar['FK_AUTOR']) && $ar['FK_AUTOR'] != "") {
	$whereSql[] = " ua.ID_USER = '".mysql_real_escape_string($ar['FK_AUTOR'])."' ";
}

$liste = $db->fetch_table("
	select
		SQL_CALC_FOUND_ROWS
		v.*,
		DATEDIFF(STAMP, STAMP_AD_UPDATE) as DIFF,
		(v.STAMP_AD_UPDATE = '0000-00-00 00:00:00') as IS_NOT_UPDATED,
		am.PRODUKTNAME,
		am.FK_KAT,
		u.NAME AS MELDER,
		am.FK_USER AS FK_USER_OWNER,
		ua.NAME AS OWNER,
		IF(am.FK_USER = 1, '', MD5(ua.PASS)) as SIG_OWNER,
		".(int)$id_s." as ID_S,
		'ad_warnings' AS curpage
	from
		verstoss v
	left join
		ad_master am ON v.FK_AD = am.ID_AD_MASTER
	left join
		user u ON v.FK_USER=u.ID_USER
	left join
		user ua ON am.FK_USER=ua.ID_USER
	".(count($whereSql)?'WHERE':'')."
		".implode(" AND ", $whereSql)."
	ORDER BY
		".$orderby."
	LIMIT
		".$limit.", ".$perpage);


if(count($liste)) {
	$all=$db->fetch_atom("select found_rows()");
	$tpl_content->addlist("liste", $liste, 'tpl/de/ad_warnings.row.htm');
	$pager = htm_browse($all, $npage, 'index.php?page=ad_warnings&ID_S='.$id_s."&npage=", $perpage);
	$tpl_content->addvar("pager", $pager);
}

?>