<?php
/* ###VERSIONSBLOCKINLCUDE### */



$show = ($_REQUEST['show'] ? $_REQUEST['show'] : 'show_open');
$action = $_REQUEST['action'];
$id_sold = $_REQUEST['id'];

$perpage = 20; // Elemente pro Seite
$limit = ((($_REQUEST['npage'] ? $_REQUEST['npage'] : $_REQUEST['npage']=1)-1)*$perpage);

$tpl_content->addvar($show, 1);

if ($id_sold > 0) {
	$sold = $db->fetch1("SELECT * FROM `ad_sold` WHERE ID_AD_SOLD=".$id_sold);
	$article_data = $db->fetch1("SELECT *, DATEDIFF(STAMP_END,NOW()) as DAYS_LEFT FROM `".$sold["FK_TABLE"]."` WHERE ID_".strtoupper($sold["FK_TABLE"])."=".$sold["FK_AD"]);
    $article_tpl = array(
		"product_id"				=>	$sold["FK_AD"],
		"product_kat"				=>	$article_data["FK_KAT"],
		"product_table"				=>	$sold["FK_TABLE"],
		"product_manufacturer"		=>	$db->fetch_atom("SELECT NAME FROM manufacturers
															WHERE ID_MAN=".(int)$article_data["FK_MAN"]),
		"product_articlename"		=>	$article_data["PRODUKTNAME"],
		"product_price"				=>	$article_data["PREIS"],
		"product_country"			=>	$db->fetch_atom("SELECT V1 FROM string
														WHERE S_TABLE='country' AND BF_LANG=".$langval." AND
															FK=".(int)$article_data["FK_COUNTRY"]),
		"product_zip"				=>	$article_data["ZIP"],
		"product_city"				=>	$article_data["CITY"],
		"product_price_overall"		=>	$article_data["PREIS"] + $article_data["VERSANDKOSTEN"],
		"product_runtime_left"		=>	$article_data["DAYS_LEFT"],
		"product_shipping"			=>	$article_data["VERSANDKOSTEN"],
		"vk_username"				=>	$db->fetch_atom("SELECT NAME FROM `user` WHERE ID_USER=".$sold["FK_USER_VK"]),
		"vk_user"					=>	$sold["FK_USER_VK"],
		"ek_username"				=>	$db->fetch_atom("SELECT NAME FROM `user` WHERE ID_USER=".$sold["FK_USER"]),
		"ek_user"					=>	$sold["FK_USER"]
	);

	if ($action == "delete") {
		// Kauf löschen
		if (($sold["STATUS"]&3) == 2) {
			// Kauf wurde bereits abgelehnt
			$db->querynow("DELETE FROM `ad_sold` WHERE ID_AD_SOLD=".$id_sold);
			die(forward("index.php?page=articles_sold&ok=1"));
		}
		if (($sold["STATUS"]&3) == 0) {
	        // Kauf noch offen
	        $db->querynow("DELETE FROM `ad_sold` WHERE ID_AD_SOLD=".$id_sold);
	        $db->querynow("UPDATE `".$sold["FK_TABLE"]."`
	        		SET STATUS=(STATUS|1)-(STATUS&4)
	        			WHERE ID_".strtoupper($sold["FK_TABLE"])."=".$sold["FK_AD"]);

	        $db->querynow("UPDATE
	        		`ad_temp`
	        	SET
	        		DONE=0
	        	WHERE
	        		`TABLE`='".mysql_escape_string($sold["FK_TABLE"])."' AND FK_AD=".$sold["FK_AD"]);

			die(forward("index.php?page=articles_sold&ok=1"));
		}
	}
} else {
	if ($_REQUEST["ok"] == 1) {
		$tpl_content->addvar("ok_delete", 1);
	}
}

if ($show == "show_open") {
	$where = "(s.STATUS&3) = 0";
}
if ($show == "show_done") {
	$where = "(s.STATUS&3) = 3";
}
if ($show == "show_pending") {
	$where = "(s.STATUS&3) = 3";
}
if (!empty($_REQUEST['ID_AD_SOLD'])) {
	$where .= " AND s.ID_AD_SOLD=".(int)$_REQUEST['ID_AD_SOLD'];
}

$query_count = "
  	SELECT
  		count(*)
  	FROM `ad_master` a
	  	LEFT JOIN `ad_sold` s
	  		ON s.FK_AD = a.ID_AD_MASTER
  	WHERE ".$where."
  		ORDER BY s.STAMP_BOUGHT DESC";
$query = "
  	SELECT
  		a.*,
  		a.ID_AD_MASTER as ID_ARTIKEL,
  		if(a.STATUS&1,1,if(a.STATUS&2,2,0)) as SOLD,
  		if(a.STATUS&4,1,0) as STORNO,
  		s.ID_AD_SOLD as ID_SOLD,
  		s.STAMP_BOUGHT,
  		s.MENGE,
  		s.PREIS,
  		(s.PREIS * s.MENGE) AS PREIS_GESAMT,
  		s.PROV,
  		(SELECT NAME FROM `user` WHERE ID_USER=s.FK_USER) as USERNAME_EK,
  		(SELECT NAME FROM `user` WHERE ID_USER=s.FK_USER_VK) as USERNAME_VK,
  		(SELECT
  				i.SRC_THUMB
  			FROM `ad_images` i
  			WHERE
  				(i.FK_AD = a.ID_AD_MASTER) AND (i.IS_DEFAULT = 1)
  			LIMIT 1) as SRC_THUMB
  	FROM `ad_master` a
	  	LEFT JOIN `ad_sold` s
	  		ON s.FK_AD = a.ID_AD_MASTER
  	WHERE ".$where."
  		ORDER BY s.STAMP_BOUGHT DESC
	LIMIT ".$limit.",".$perpage;

$SILENCE = false;

$all = $db->fetch_atom($query_count);
$ads = $db->fetch_table($query);

$tpl_content->addlist("liste", $ads, "tpl/de/articles_sold.row.htm");
$tpl_content->addvar("pager", htm_browse($all, $_REQUEST['npage'], "index.php?page=articles_sold&show=".$show."&npage=", $perpage));

?>