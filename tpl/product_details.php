<?php
//////////////// IMENSO //////////////////////
if(file_exists('./vendor/autoload.php')) 
{
	require_once('./vendor/autoload.php');
}
use \Statickidz\GoogleTranslate;
function translate_api($source,$target,$text)
{
	$trans = new GoogleTranslate();
	$result = $trans->translate($source, $target, $text);
	return $result;
}

$productId = (array_key_exists("ID_HDB_PRODUCT", $_POST) ? (int)$_POST["ID_HDB_PRODUCT"] : (int)$ar_params[2]);
if (array_key_exists("ID_HDB_PRODUCT", $tpl_content->vars) && ($tpl_content->vars["ID_HDB_PRODUCT"] > 0)) {
    $productId = $tpl_content->vars["ID_HDB_PRODUCT"];
}
$categoryId = (array_key_exists("ID_KAT", $_POST) ? (int)$_POST["ID_KAT"] : (int)$ar_params[1]);
if (array_key_exists("ID_KAT", $tpl_content->vars) && ($tpl_content->vars["ID_KAT"] > 0)) {
    $categoryId = $tpl_content->vars["ID_KAT"];
}
$categoryTable = $db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".(int)$categoryId);

require_once $GLOBALS["ab_path"]."sys/lib.hdb.php";
$hdbManagement = ManufacturerDatabaseManagement::getInstance($db);

$arProduct = $hdbManagement->fetchProductById($productId, "hdb_table_".$categoryTable);

//////////////// IMENSO //////////////////////
if(file_exists('./vendor/autoload.php')) 
{
	if($s_lang == 'en' )
	{
		$arProduct['FULL_PRODUKTNAME']= translate_api('de','en',$arProduct['FULL_PRODUKTNAME']);
		$arProduct['BESCHREIBUNG'] = translate_api('de','en',$arProduct['BESCHREIBUNG']);

		
	}
}

$tpl_content->addvars($arProduct);