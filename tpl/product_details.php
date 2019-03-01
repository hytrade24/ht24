<?php

//////////////// IMENSO //////////////////////
include_once $GLOBALS["ab_path"]."sys/MicrosoftTranslator.php";

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


// The Regular Expression filter


//////////////// IMENSO //////////////////////

if($s_lang == 'en' )
{
	$arProduct['FULL_PRODUKTNAME'] = Translate( $arProduct['FULL_PRODUKTNAME'] );
	$arProduct['BESCHREIBUNG'] = Translate( $arProduct['BESCHREIBUNG'] ); 
}


//XAVER//

//$reg_exUrl = "/(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/";

$reg_exUrl = "@(https?|ftp)://(-\.)?([^\s/?\.#-]+\.?)+(/[^\s]*)?$@iS";

// The Text you want to filter for urls
$text = $arProduct['BESCHREIBUNG'];

// Check if there is a url in the text
if(preg_match($reg_exUrl, $text, $url)) {

       // make the urls hyper links
	   $arProduct['BESCHREIBUNG'] = preg_replace($reg_exUrl, '<a href="'.$url[0].'" rel="nofollow" target="_blank">'.$url[0].'</a>', $text);

} else {

       // if no urls in the text just return the text
       $arProduct['BESCHREIBUNG'] = $text;

}

$tpl_content->addvars($arProduct);

//@(https?|ftp)://(-\.)?([^\s/?\.#-]+\.?)+(/[^\s]*)?$@iS