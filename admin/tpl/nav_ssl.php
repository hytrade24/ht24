<?php

function updateOptions($arChanged) {
    global $db, $nar_systemsettings;
    $data = $db->fetch_table("select * from `option` order by plugin, typ");
    $ar = array ();
    foreach ($data as $row) {
        $ar[$row['plugin']][$row['typ']] = $row['value'];
    }
    foreach ($arChanged as $plugin => $arOptions) {
        foreach ($arOptions as $option => $value) {
            $ar[$plugin][$option] = $value;
            $nar_systemsettings[$plugin][$option] = $value;
            $db->querynow("UPDATE `option` SET value='".mysql_real_escape_string($value)."'
                WHERE plugin='".mysql_real_escape_string($plugin)."'
                    AND typ='".mysql_real_escape_string($option)."';");
        }
    }
    $s_code = '<?'. 'php $nar_systemsettings = '. php_dump($ar, 0). '; ?'. '>';
    $fp = fopen($file_name = '../cache/option.php', 'w');
    fputs($fp, $s_code);
    fclose ($fp);
    chmod($file_name, 0777);
    return true;
}

global $nar_systemsettings;

if (isset($_REQUEST['do'])) {
    switch ($_REQUEST['do']) {
        case 'disable':
            // Update global options
            updateOptions(array(
                "SITE" => array("USE_SSL" => 0, "USE_SSL_GLOBAL" => 0)
            ));
            break;
        case 'enable_user':
            // Update global options
            updateOptions(array(
                "SITE" => array("USE_SSL" => 1, "USE_SSL_GLOBAL" => 0)
            ));
            break;
        case 'reset_user':
            // Disable SSL globally
            $db->querynow('UPDATE `nav` SET B_SSL=0 WHERE ROOT=1;');
            // Enable SSL for some system pages (login/register/sales)
            $db->querynow('UPDATE `nav` SET B_SSL=2 WHERE ROOT=1 AND IDENT IN
              ("login", "marktplatz_kaufen", "marktplatz_handeln", "register", "register_ajax",
               "cart", "cart_checkout", "cart_checkout_dome")');
            // Enable SSL for "my-pages"/"Mein Account"
            $ar_nav_user = $db->fetch1("SELECT * FROM `nav` WHERE ROOT=1 AND IDENT='my-pages'");
            $db->querynow('UPDATE `nav` SET B_SSL=2 WHERE ROOT=1 AND
                LFT BETWEEN '.$ar_nav_user["LFT"].' AND '.$ar_nav_user["RGT"].';');
            // Enable SSL (optional mode) for "my-pages"/"Mein Account"
            $ar_nav_sys = $db->fetch1("SELECT * FROM `nav` WHERE ROOT=1 AND IDENT='system'");
            $db->querynow('UPDATE `nav` SET B_SSL=2 WHERE ROOT=1 AND
                LFT BETWEEN '.$ar_nav_sys["LFT"].' AND '.$ar_nav_sys["RGT"].';');
            // Enable SSL (optional mode) for some system pages
            $db->querynow('UPDATE `nav` SET B_SSL=2 WHERE ROOT=1 AND IDENT IN
              ("ad_reminder", "ad_showup", "marktplatz_kontakt", "marktplatz_anzeige_empfehlen",
               "anzeige_melden", "marktplatz_anzeige_gefallen", "marktplatz_neu_ajax",
               "cart_item_status", "ad_request_kontakt", "cart_item_status", "cart_checkout_done",
               "my-ad-top", "my-neu-msg");');
            break;
        case 'enable_full':
            // Enable SSL globally, update global options
            updateOptions(array(
                "SITE" => array("USE_SSL" => 1, "USE_SSL_GLOBAL" => 1)
            ));
            break;
    }
    // Clear cache
    require_once 'sys/lib.cache.php';
    cache_nav_all(1);
    // Clear navigation cache
    unset($_SESSION['navedit1'.$s_lang]);
    unset($_SESSION['NAVDATE_tmp21']);
    // Forward
    die(forward("index.php?page=nav_ssl&done=saved"));
}

$useSSL = ($nar_systemsettings["SITE"]["USE_SSL_GLOBAL"] ? 1 : $nar_systemsettings["SITE"]["USE_SSL"]);
$tpl_content->addvar("SSL_ENABLED", $useSSL);
$tpl_content->addvar("SSL_FULL", $nar_systemsettings["SITE"]["USE_SSL_GLOBAL"]);
if (isset($_REQUEST['done'])) {
    $tpl_content->addvar("done_".$_REQUEST['done'], 1);
}

?>