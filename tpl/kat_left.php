<?php
/* ###VERSIONSBLOCKINLCUDE### */



include_once "sys/lib.nestedsets.php";
include_once "sys/lib.shop_kategorien.php";

$kat = new TreeCategories("kat", 1);
$id_kat = ($ar_params[2] > 0 ? $ar_params[2] :
              ($ar_params[1] > 0 ? $ar_params[1] : $kat->tree_get_parent()) );
if($ar_params[0] == 'view_user')
{
	$id_kat = $db->fetch_atom("
		SELECT
			ID_KAT
		FROM
			kat
		ORDER BY
			LFT ASC
		LIMIT 1");
}
if($id_kat)
{
	$cachefile = "cache/marktplatz/liste_de.".$id_kat.".htm";
	if (!file_exists($cachefile))
	{
		require_once("sys/lib.pub_kategorien.php");
		$kat_cache = new CategoriesCache();
		$kat_cache->cacheKatList($id_kat);
	}
}

$tpl_content->addvar("kats", @file_get_contents($cachefile));
;
?>