<?php

if (isset($_POST["COMMENT"]) &&
		isset($_POST["RATING"]) &&
		isset($_POST["ID_AD_SOLD_RATING"]) ) {

	$comment = $_POST["COMMENT"];
	$rating = $_POST["RATING"];
	$ID_AD_SOLD_RATING = $_POST["ID_AD_SOLD_RATING"];

	$arValues = array(
		"ID_AD_SOLD_RATING"     =>  $ID_AD_SOLD_RATING,
		"COMMENT"               =>  $comment,
		"RATING"                =>  $rating
	);
	$db->update(
		"ad_sold_rating",
		$arValues
	);
}
//........
$query = 'SELECT asr.*, u_from.NAME as VON, u_to.NAME as AN, ad_m.PRODUKTNAME
			FROM ad_sold_rating asr
			INNER JOIN user u_from
			ON asr.ID_AD_SOLD_RATING = '.$_GET["rating"].'
			AND u_from.ID_USER = asr.FK_USER_FROM
			INNER JOIN user u_to
			ON u_to.ID_USER = asr.FK_USER
			INNER JOIN ad_sold ad_s
			ON ad_s.ID_AD_SOLD = asr.FK_AD_SOLD
			INNER JOIN ad_master ad_m
			ON ad_m.ID_AD_MASTER = ad_s.FK_AD';

$rating = $db->fetch_table($query);

$tpl_content->addvars($rating[0]);

?>