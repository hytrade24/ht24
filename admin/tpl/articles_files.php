<?php
/* ###VERSIONSBLOCKINLCUDE### */

$frame="";
if ($_REQUEST['frompopup']==1)
    $frame = "&frompopup=1&frame=popup";
    
$article_id = (int)$_REQUEST["ID_AD"];

if ($_REQUEST["action"] == "file_del") {
	$file_id = $_REQUEST["ID_FILE"];
	$ar_up = $db->fetch1("
		SELECT
			ad_upload.*
		FROM
			ad_upload
		JOIN
			ad_master ON ad_master.ID_AD_MASTER = ad_upload.FK_AD AND ad_master.FK_USER=".$uid."
		WHERE
			ID_AD_UPLOAD=".$file_id);
	if(!empty($ar_up))
	{
		unlink($ar_up['SRC']);
		$db->querynow("DELETE FROM `ad_upload` WHERE ID_AD_UPLOAD=".$file_id);
	}
	die(forward("index.php?page=articles_files&ID_AD=".$article_id.$frame));
}
if ($_REQUEST["action"] == "image_del") {
	$image_id = $_REQUEST["ID_IMAGE"];
	$image_delete = $db->fetch1("SELECT * FROM `ad_images` WHERE FK_AD=".$article_id." AND ID_IMAGE=".$image_id);
	if ($image_delete["CUSTOM"] == 1) {
		@unlink($ab_path.substr($image_delete["SRC"], 1));
		@unlink($ab_path.substr($image_delete["SRC_THUMB"], 1));
	}
	$db->querynow("DELETE FROM `ad_images` WHERE ID_IMAGE=".$image_delete["ID_IMAGE"]);
	$image_default = $db->fetch_atom("SELECT count(*) FROM `ad_images` WHERE FK_AD=".$article_id." AND IS_DEFAULT=1");
	if ($image_default == 0)
		$db->querynow("UPDATE `ad_images` SET IS_DEFAULT=1 WHERE FK_AD=".$article_id." LIMIT 1");
	die(forward("index.php?page=articles_files&ID_AD=".$article_id.$frame));
}
if ($_REQUEST["action"] == "image_default") {
	$image_id = $_REQUEST["ID_IMAGE"];
	$db->querynow("UPDATE `ad_images` SET IS_DEFAULT=0 WHERE FK_AD=".$article_id);
	$db->querynow("UPDATE `ad_images` SET IS_DEFAULT=1 WHERE FK_AD=".$article_id." AND ID_IMAGE=".$image_id);
	die(forward("index.php?page=articles_files&ID_AD=".$article_id.$frame));
}

$article_base	= $db->fetch1("SELECT * FROM `ad_master` WHERE ID_AD_MASTER=".$article_id);
$article_data	= $db->fetch1("SELECT * FROM `".$article_base["AD_TABLE"]."` WHERE ID_".strtoupper($article_base["AD_TABLE"])."=".$article_id);
$article_images	= $db->fetch_table("SELECT * FROM `ad_images` WHERE FK_AD=".$article_id);
$article_files	= $db->fetch_table("SELECT * FROM `ad_upload` WHERE FK_AD=".$article_id);

$article_tpl = array(
    "product_manufacturer"  => $db->fetch_atom("SELECT NAME FROM manufacturers
                                                  WHERE ID_MAN=".(int)$article_data["FK_MAN"]),
    "product_articlename"   => $article_data["PRODUKTNAME"],
    "product_price"         => $article_data["PREIS"],
  	"product_mwst"         	=> $article_data["MWST"],
  	"product_versand"       => $article_data["VERSANDKOSTEN"],
    "product_sold"					=> (($article_data["STATUS"]&4)==4 ? true : false),
    "product_country"       => $db->fetch_atom("SELECT V1 FROM string
                                                  WHERE S_TABLE='country' AND BF_LANG=".$langval." AND
                                                    FK=".(int)$article_data["FK_COUNTRY"]),
    "product_zip"           => $article_data["ZIP"],
    "product_city"          => $article_data["CITY"],
    "product_lat"			=> $article_data["LATITUDE"],
    "product_lon"			=> $article_data["LONGITUDE"],
    "product_runtime_left"  => $article_data["DAYS_LEFT"],
    "vk_username"			=> $db->fetch_atom("SELECT NAME FROM `user` WHERE ID_USER=".$article_data["FK_USER"]),
    "vk_user"				=> $article_data["FK_USER"],
	"agb"					=> $article_data["AD_AGB"],
	"widerruf"				=> $article_data["AD_WIDERRUF"],
	'MWST'					=> $article_data["MWST"],
	'FK_KAT' 				=> $article_data["FK_KAT"],
	'NOTIZ'					=> $article_base["NOTIZ"]
);

$tpl_content->addvars($article_base);
$tpl_content->addvars($article_tpl);
$tpl_content->addlist("list_images", $article_images, "tpl/de/articles_files.row_images.htm");
$tpl_content->addlist("list_files", $article_files, "tpl/de/articles_files.row_files.htm");

?>