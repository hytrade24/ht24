<?php

$id = (int)$ar_params[1];

if($ar_params[3] == 'ok')
	$tpl_content->addvar("K_SAVED", 1);

$npage = ((int)$ar_params[4] ? $ar_params[4] : 1);
$tpl_content->addvar("npage", $npage);
$perpage = 20;
$limit = (($perpage*$npage)-$perpage);

$ar_artikel = $db->fetch1("select t.*, s.V1, s.V2
		from `news` t
		left join string_c s on s.S_TABLE='news' and s.FK=t.ID_NEWS
		and s.BF_LANG=if(t.BF_LANG_C & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_C+0.5)/log(2)))
		where t.ID_NEWS=".(int)$id." and OK = 3");

if(!empty($ar_artikel))
{
	$tpl_content->addvars($ar_artikel);
	$all = $db->fetch_atom("select count(*) from kommentar_news where FK=".$id." and PUBLISH = 1");
	$tpl_content->addvar("all", $all);
	$liste = $db->fetch_table("select KOMMENTAR_PARSED,ID_KOMMENTAR_NEWS,FK,u.RATING,STAMP,u.NAME,u.STAMP_REG,u.CACHE,FK_USER from kommentar_news
			left join user u on ID_USER = FK_USER
			where FK=".$id." and PUBLISH=1
			order by STAMP DESC
			limit ".$limit.", ".$perpage."
			");
	$tpl_content->addlist("liste", $liste, "tpl/".$s_lang."/kommentare.row.htm");

	$pager = htm_browse($all, $npage, "/news/kommentare,".$id.",,,", $perpage);
	$tpl_content->addvar("pager", $pager);
	//kat tree
	#require_once $ab_path.'sys/lib.news.php';
	#$categoryTree = getNewsCategoryJSONTree(array($ar_artikel["FK_KAT"]));
	#$tpl_main->addvar("CATEGORY_JSON_TREE", $categoryTree);
} // artikel ist da

?>