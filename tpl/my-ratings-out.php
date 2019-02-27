<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once 'sys/lib.ad_rating.php';
$adRatingManagement = AdRatingManagement::getInstance($db);


$npage = ((int)$ar_params[4] ? (int)$ar_params[4] : 1);
$perpage = 20;
$limit = ($perpage*$npage)-$perpage;

if ($ar_params[2] == "remove") {
	$id_ad_sold_rating = $ar_params[3];
	$ar_ad_sold_rating = $db->fetch1("SELECT *
    		FROM `ad_sold_rating`
    		WHERE ID_AD_SOLD_RATING=".$id_ad_sold_rating." AND FK_USER_FROM=".$uid);
	if (!empty($ar_ad_sold_rating)) {
		$adRatingManagement->cancelAdRating($id_ad_sold_rating, $uid);
		die(forward("/my-ratings,out.htm"));
	}
}
$query = "
  	SELECT
  		SQL_CALC_FOUND_ROWS
  		r.*,
  		s.STAMP_BOUGHT,
  		s.FK_AD,
  		s.ID_AD_SOLD,
		s.PRODUKTNAME,
  		u.`NAME` AS `USER`,
  		u.ID_USER AS UID
  	FROM
  		`ad_sold_rating` r
  	LEFT JOIN
  		`ad_sold` s ON r.FK_AD_SOLD=s.ID_AD_SOLD
  	LEFT JOIN
  		`user` u ON r.FK_USER=u.ID_USER
  	WHERE
  		r.FK_USER_FROM=".$uid."
  	ORDER BY
  		s.STAMP_BOUGHT DESC
  	LIMIT
  		".$limit.", ".$perpage;

$ratings = $db->fetch_table( $query );

$tpl_content->addlist("liste", $ratings, $ab_path.'tpl/'.$s_lang.'/my-ratings-out.row.htm');
$tpl_content->addvar("pager", htm_browse($all, $npage, "/my-ratings,out,,,", $perpage));

?>