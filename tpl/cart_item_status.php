<?php
/* ###VERSIONSBLOCKINLCUDE### */

function killbb(&$row, $i)
{
    //$row['DSC'] = strip_tags($row['DSC']);
    $row['DSC'] = substr(strip_tags(html_entity_decode($row['DSC'])), 0, 250);
    $row['DSC'] = preg_replace("#(\[)([^\s\]]*)(\])#si", "", $row['DSC']);
}

require_once $ab_path . 'sys/lib.ads.php';
require_once $ab_path . 'sys/lib.shopping.cart.php';
require_once $ab_path . 'sys/lib.ad_variants.php';
$shoppingCartManagement = ShoppingCartManagement::getInstance();
$variants = AdVariantsManagement::getInstance($db);

$adId = (int)$ar_params['1'];
$adVariantId = (int)$ar_params['2'];

$article = $shoppingCartManagement->getArticle($adId, $adVariantId);
if ($article) {
	// Varianten text
	$ar_liste = array();
	$ar_variant = $variants->getAdVariantTextById($article['ARTICLEDATA']['ID_AD_VARIANT']);
	foreach($ar_variant as $i => $row) {
        $tpl_tmp = new Template("tpl/".$s_lang."/cart.row_variant.htm");
        $tpl_tmp->addvars($row);
        $tpl_tmp->addvar('i', $i);
        $tpl_tmp->addvar('even', 1-($i&1));
        $ar_liste[] = $tpl_tmp;
	}

    $data = array_merge($article['ARTICLEDATA'], array(
    		'USER_ID_USER' => $article['USERDATA']['ID_USER'],
    		'USER_NAME' => $article['USERDATA']['NAME'],
    		'CART_QUANTITY' => $article['QUANTITY'],
    		'CART_TOTAL_SHIPPING_PRICE' => $article['TOTAL_SHIPPING_PRICE'],
    		'CART_TOTAL_ARTICLE_PRICE' => $article['TOTAL_ARTICLE_PRICE'],
    		'CART_TOTAL_PRICE' => $article['TOTAL_PRICE'],
    		'AVAILABILITY' => $article['AVAILABILITY'],
    		'AVAILABILITY_DATE_FROM' => $article['AVAILABILITY_DATE_FROM'],
    		'AVAILABILITY_TIME_FROM' => $article['AVAILABILITY_TIME_FROM'],
    		'AVAILABILITY_DATE_TO' => $article['AVAILABILITY_DATE_TO'],
    		'VARIANT_TEXT' => $ar_liste
    	));
    killbb($data, null);

    $tpl_content->addvars($data);

} else {
    $tpl_content->addvar("err", 1);
}
