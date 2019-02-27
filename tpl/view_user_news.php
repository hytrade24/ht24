<?php
/* ###VERSIONSBLOCKINLCUDE### */

include_once $ab_path."sys/lib.shop_kategorien.php";

$kat = new TreeCategories("kat",2);
$id_kat_root = $kat->tree_get_parent();
/*echo '<pre>';
var_dump( $kat, $id_kat_root );
echo '</pre>';
die();*/

// Parameters
$userId = ((int)$ar_params[2] ? (int)$ar_params[2] : null);
$id_kat = ((int)$ar_params[3] ? (int)$ar_params[3] : 0);
$npage = ((int)$ar_params[4] ? (int)$ar_params[4] : 1);
$id_news = ((int)$ar_params[5] ? (int)$ar_params[5] : 0);

$cacheHash = md5( ".userId." . $userId. ".news_kat_root." . $id_kat_root . ".news." . $id_news );
$cacheDir = $ab_path."cache/news/categories";
if (!is_dir($cacheDir)) {
	mkdir($cacheDir, 0777, true);
}
$cacheFile = $cacheDir."/".$cacheHash.".htm";
$cacheFileAge = (file_exists($cacheFile) ? (time() - filemtime($cacheFile)) / 60 : false );
$cacheLifetime = $GLOBALS['nar_systemsettings']['CACHE']['LIFETIME_CATEGORY'];
$cacheContent = "";
if (($cacheLifetime <= 0) || ($cacheFileAge === false) || ($cacheFileAge > $cacheLifetime)) {
	$categoryParent = $kat->element_read($id_kat_root);
	$categoryList = $db->fetch_table("
        SELECT
            k.*, s.*,
            COUNT(n.ID_NEWS) as `COUNT`
        FROM `news` n
        JOIN `kat` k ON k.ID_KAT=n.FK_KAT
        JOIN `string_kat` s ON s.S_TABLE='kat' AND s.FK=k.ID_KAT
          AND s.BF_LANG=if(k.BF_LANG_KAT & ".$langval.", ".$langval.", 1 << floor(log(k.BF_LANG_KAT+0.5)/log(2)))
        WHERE ROOT=2 AND LFT>".(int)$categoryParent["LFT"]." AND RGT<".(int)$categoryParent["RGT"]."
        GROUP BY n.FK_KAT");
	// Output result
	$tpl_news_categories = new Template("tpl/".$s_lang."/user_news_category_box.htm");
	$tpl_news_categories->isTemplateRecursiveParsable = TRUE;
	$tpl_news_categories->isTemplateCached = TRUE;
	$tpl_news_categories->addlist("liste", $categoryList, "tpl/".$s_lang."/user_news_category_box.row.htm");
	//$tpl_news_categories->isTemplateCached = FALSE;

	$cacheContent = $tpl_news_categories->process(true);
	file_put_contents($cacheFile, $cacheContent);
}
else {
	$cacheContent = file_get_contents($cacheFile);
}

$perpage = 10;
$limit = ($perpage*$npage)-$perpage;

$user_ = $db->fetch1("select VORNAME,NACHNAME,NAME,CACHE,STAMP_REG,LASTACTIV,URL,STRASSE,PLZ,ORT,LU_PROFESSION,ID_USER,UEBER, ROUND(RATING) as lastrate,TIMESTAMPDIFF(YEAR,GEBDAT,CURDATE()) as age,TEL from user where ID_USER='". $userId."'");

$tpl_content->addvar("active_news", 1);

$tpl_content->addvar("news_categories",$cacheContent);

if(($userId != null) && ($user_ != null)) {
	if ($id_news > 0) {
		$query = "SELECT
    		j.*, sj.*
    	FROM `news` j
    	LEFT JOIN `string_c` sj ON
    		sj.S_TABLE='news' AND sj.FK=j.ID_NEWS AND
    		sj.BF_LANG=if(j.BF_LANG_C & ".$langval.", ".$langval.", 1 << floor(log(j.BF_LANG_C+0.5)/log(2)))
    	WHERE j.OK=3 AND j.ID_NEWS=".(int)$id_news;
		$ar_news = $db->fetch1($query);
		$tpl_content->addvars($ar_news);
	}
	$query = "SELECT SQL_CALC_FOUND_ROWS
    		j.*, sj.*, u.*, sk.V1 as sk_V1, sk.V2 as sk_V2
    	FROM `news` j
    	LEFT JOIN `string_c` sj ON
    		sj.S_TABLE='news' AND sj.FK=j.ID_news AND
    		sj.BF_LANG=if(j.BF_LANG_C & ".$langval.", ".$langval.", 1 << floor(log(j.BF_LANG_C+0.5)/log(2)))
    	LEFT JOIN `user` u ON
    	j.FK_AUTOR = u.ID_USER
    	LEFT JOIN `string_kat` sk
    	ON sk.FK = j.FK_KAT
    	AND sk.S_TABLE = 'kat'
    	AND sk.BF_LANG = j.BF_LANG_C
    	WHERE j.OK=3 AND j.FK_AUTOR=".(int)$userId;
	if ($id_news > 0) $query .= " AND NOT j.ID_NEWS=".$id_news;
    $query .= "\nORDER BY j.STAMP DESC";
    $query .= "\nLIMIT ".$limit.", ".$perpage;
    $ar_results = $db->fetch_table($query);
	$all = $db->fetch_atom("SELECT FOUND_ROWS()");
	$newsManagement = Api_NewsManagement::getInstance($db);

	foreach ( $ar_results as $key => $single_news ) {
		$ar_results[$key]["IMAGE_URL"] = $newsManagement->getImageUrl($single_news);
		if (!$id_news > 0) {
			$ar_results[$key]["show_big_img"] = 1;
		}
	}
	$tpl_content->addlist("liste", $ar_results, $ab_path.'tpl/'.$s_lang.'/view_user_news.row.htm');

	$pager = htm_browse_extended($all, $npage, "view_user_news,".chtrans($user_['NAME']).",".$userId.",".$id_kat.",{PAGE}", $perpage);
	$tpl_content->addvar("pager", $pager);
	
	$checkComments = function() {
		include $GLOBALS["ab_path"]."module/news_adv/ini.php";
		return $ar_modul_option["comment"];
	};
	$tpl_content->addvar("comments_enabled", $checkComments());

	$tpl_content->addvar("t_".$view, 1);
	$tpl_content->addvar("UID", $uid);

	$tpl_content->addvars($user_, 'USER_');
} else {
	$nullUser = $db->fetch_blank('user');
	$tpl_content->addvars($nullUser);
}
?>