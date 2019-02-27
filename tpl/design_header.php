<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path.'sys/lib.shopping.cart.php';
require_once $ab_path.'sys/lib.shop_kategorien.php';
include_once $ab_path."sys/lib.pub_kategorien.php";
$shoppingCartManagement = ShoppingCartManagement::getInstance();

$tpl_content->addvar('CART_COUNT_ITEMS', $shoppingCartManagement->getNumberOfArticles());

$use_cart = $nar_systemsettings['MARKTPLATZ']['BUYING_ENABLED'] & $nar_systemsettings['MARKTPLATZ']['USE_CART'];
$tpl_content->addvar("USE_CART", $use_cart);

$id_kat_root = (int)$nar_systemsettings['MARKTPLATZ']['CATEGORY_ROOT'];
if($id_kat_root <= 0) {
    $kat = new TreeCategories("kat", 1);
    $id_kat_root = $kat->tree_get_parent();
}
$cacheFileLifeTime = $nar_systemsettings['CACHE']['LIFETIME_CATEGORY'];
$cachefileMenu = "cache/marktplatz/menu_".$s_lang.".".$id_kat_root.".htm";

$modifyTime = @filemtime($cachefileMenu);
$diff = ((time()-$modifyTime)/60);

if (($diff > $cacheFileLifeTime) || !file_exists($cachefileMenu)) {
    $kat = new TreeCategories("kat", 1);
    $kat_cache = new CategoriesCache();

    $categories = $kat->element_get_childs( $id_kat_root );

    foreach ($categories as $categoryIndex => $categoryDetails) {
        $id_kat = $categoryDetails["ID_KAT"];
        $childDeepLevel = 1;
        $options = [ "LIMIT_CHILDS" => [ 5, 3 ] ];
        $extra = "";
        if (!empty($options)) {
            $extra = ".".md5(json_encode($options));
        }
        $cachefile = "cache/marktplatz/tree_".$s_lang.".".$id_kat.".".$childDeepLevel.$extra.".htm";
        $kat_cache->cacheKatTree($id_kat, $childDeepLevel, $childrenHover, $options, $categoryDetails);

        $tpl_kats = new Template("tpl/".$s_lang."/empty");
        $tpl_kats->tpl_text = @file_get_contents($cachefile);
        $tpl_kats->isTemplateRecursiveParsable = FALSE;
        $tpl_kats->isTemplateCached = FALSE;
        $categories[$categoryIndex]["hover"] = $tpl_kats->process();
    }

    $tpl_kats = new Template("tpl/".$s_lang."/empty");
    $tpl_kats->tpl_text = "{list_cat}";
    $tpl_kats->isTemplateRecursiveParsable = FALSE;
    $tpl_kats->isTemplateCached = FALSE;
    $tpl_kats->addlist("list_cat",$categories,"tpl/".$s_lang."/design_header.nav.row.htm");
    file_put_contents($cachefileMenu, $tpl_kats->process());
    @chmod($cachefileMenu, 0777);
}

$tpl_menu = new Template("tpl/".$s_lang."/empty");
$tpl_menu->tpl_text = @file_get_contents($cachefileMenu);
$tpl_menu->isTemplateRecursiveParsable = TRUE;
$tpl_menu->isTemplateCached = TRUE;
$tpl_content->addvar("list_cat", $tpl_menu);