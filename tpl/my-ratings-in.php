<?php
/* ###VERSIONSBLOCKINLCUDE### */



$npage = ((int)$ar_params[2] ? $ar_params[2] : 1);
$perpage = 20;
$limit = ($perpage*$npage)-$perpage;

$ratings = $db->fetch_table($q = "
  	SELECT
  		SQL_CALC_FOUND_ROWS
  		r.*,
  		s.STAMP_BOUGHT,
  		s.FK_AD,
  		s.ID_AD_SOLD,
		s.PRODUKTNAME,
  		(SELECT NAME FROM `user` WHERE ID_USER=r.FK_USER_FROM) as USERNAME_FROM
  	FROM
  		`ad_sold_rating` r
  	LEFT JOIN
  		`ad_sold` s ON r.FK_AD_SOLD=s.ID_AD_SOLD
  	WHERE
  		r.FK_USER=".$uid."
  	ORDER BY
  		s.STAMP_BOUGHT DESC
  	LIMIT
  		".$limit.", ".$perpage);

$all = $db->fetch_atom("SELECT FOUND_ROWS()");

$tpl_content->addlist("liste", $ratings, $ab_path.'tpl/'.$s_lang.'/my-ratings-in.row.htm');
$tpl_content->addvar("pager", htm_browse($all, $npage, "/my-ratings,in,", $perpage));

?>