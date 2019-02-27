<?php
/* ###VERSIONSBLOCKINLCUDE### */



$id_ad = (int)$_REQUEST["ID_AD"];

if ($id_ad > 0) {
	$images = $db->fetch_table("SELECT * FROM `ad_images` WHERE FK_AD=".$id_ad." ORDER BY IS_DEFAULT DESC");
	$tpl_content->addlist("liste", $images, "tpl/de/articles_images.row.htm");
}

?>