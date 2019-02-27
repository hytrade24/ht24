<?php
/* ###VERSIONSBLOCKINLCUDE### */


$SILENCE=false;
$npage = (int)$_REQUEST['npage'];
if(!$npage) {
	$npage = 1;
}
$perpage = 20;
$limit = ($npage*$perpage)-$perpage;

$liste = $db->fetch_table("
	select
		SQL_CALC_FOUND_ROWS
		ads.*,
		adm.PRODUKTNAME,
		adm.FK_USER as ID_SELLER,
		concat(seller.VORNAME, ' ', seller.NACHNAME, ' (', seller.NAME, ')') AS SELLER,
		ads.FK_USER AS ID_BUYER,
		concat(buyer.VORNAME, ' ', buyer.NACHNAME, ' (', buyer.NAME, ')') AS BUYER
	from
		ad_sold ads
	left join
		ad_master adm ON ads.FK_AD = adm.ID_AD_MASTER
	left join
		user seller ON adm.FK_USER=seller.ID_USER
	left join
		user buyer ON ads.FK_USER=buyer.ID_USER
	where
		ads.STAMP_STORNO IS NOT NULL
		and STAMP_STORNO_OK IS NULL
	order by
		STAMP_STORNO ASC
	limit
		".$limit.", ".$perpage);
$tpl_content->addlist("liste", $liste, "tpl/de/pending_stornos.row.htm");
$tpl_content->addvar("pending", $db->fetch_atom("select found_rows()"));

?>