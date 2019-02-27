<?php
/* ###VERSIONSBLOCKINLCUDE### */


$SILENCE=false;
$perpage = 20;
$npage = ($_REQUEST['npage'] ? (int)$_REQUEST['npage'] : 1);
$limit = ($perpage*$npage)-$perpage;

$id_user = (int)$_REQUEST['ID_USER'];

$tpl_content_links->addvar("ID_USER", $id_user);

$tpl_content->addvar("ID_USER", $id_user);
$name_= $db->fetch_atom("
	SELECT
		NAME
	FROM
		user
	WHERE
		ID_USER=".$id_user);
$what = $_REQUEST['what'] = ($_REQUEST['what'] ? (int)$_REQUEST['what'] : 1);
$tpl_content->addvar("what", $what);

$tpl_content_links->addvar("NAME_", $name_);
$tpl_content->addvar("NAME", $name_);

if($what == 1)
{
	$where = "ad_sold.FK_USER_VK = ".$id_user;
}
else
{
	$where = "ad_sold.FK_USER = ".$id_user;
}

$query = "
	SELECT
		ad_sold.*,
		ad_master.PRODUKTNAME,
		seller.NAME AS SELLER,
		buyer.NAME AS BUYER
	FROM
		ad_sold
	LEFT JOIN
		ad_master ON ad_sold.FK_AD=ad_master.ID_AD_MASTER
	LEFT JOIN
		user AS seller ON ad_sold.FK_USER_VK = seller.ID_USER
	LEFT JOIN
		user AS buyer ON ad_sold.FK_USER = buyer.ID_USER
	WHERE
		".$where."
	ORDER BY
		STAMP_BOUGHT DESC
	LIMIT
		".$limit.", ".$perpage;
$liste = $db->fetch_table($query);
$tpl_content->addlist("liste", $liste, "tpl/de/user_transaktion.row.htm");

?>