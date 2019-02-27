<?php
/* ###VERSIONSBLOCKINLCUDE### */



set_include_path(dirname(__FILE__).PATH_SEPARATOR.get_include_path());

require_once dirname(__FILE__).'/sys/lib.kernel.php';
require_once dirname(__FILE__).'/inc.server.php';
require_once dirname(__FILE__).'/inc.app.php';
require_once dirname(__FILE__).'/inc.all.php';

require_once dirname(__FILE__).'/sys/lib.misc.php';
require_once dirname(__FILE__).'/sys/lib.string.php';
require_once dirname(__FILE__).'/sys/lib.db.mysql.php';
require_once dirname(__FILE__).'/sys/lib.template.php';

// Register autoloader
registerAutoloader();

$db = new ebiz_db($db_name, $db_host, $db_user, $db_pass);
unset($db_user); unset($db_pass);

$originalSystemSettings = $nar_systemsettings;

$uid = session_init();
$user = get_user($uid);

$s_cachepath = dirname(__FILE__).'/cache/';
$n_navroot = 1;
if(file_exists(dirname(__FILE__).'/cache/lang.de.php')) {
	require_once dirname(__FILE__).'/cache/lang.de.php';
}
if(file_exists(dirname(__FILE__).'/cache/info.de.php')) {
	require_once dirname(__FILE__).'/cache/info.de.php';
}
list($s_lang, $langval) = get_language();
$idLang = $GLOBALS['lang_list'][ $GLOBALS['s_lang'] ]['ID_LANG'];

global $ar_nav_urls, $ar_nav_urls_by_id;
include $s_cachepath. 'nav.url.'.$idLang.'.php';    // Siehe sys/lib.nav.url.php -> updateCache()
if(file_exists($s_cachepath. 'nav'. $n_navroot. '.'. $s_lang. '.php')) {
	include $s_cachepath. 'nav'. $n_navroot. '.'. $s_lang. '.php'; // Struktur: $ar_nav
	include $s_cachepath. 'nav'. $n_navroot. '.php'; // Zuordnung Ident/Alias => ID_NAV: $nar_ident2nav
}

// Language settings
$language = $lang_list[$s_lang];
if ($language['DOMAIN'] != '') {
    $GLOBALS['nar_systemsettings']['SITE']['SITEURL'] = $language['DOMAIN'];
}
if ($language['BASE_URL'] != '') {
    $GLOBALS['nar_systemsettings']['SITE']['BASE_URL'] = $language['BASE_URL'];
}
$idLang = $GLOBALS['lang_list'][ $GLOBALS['s_lang'] ]['ID_LANG'];

$originalSystemSettings = $nar_systemsettings;
if($language['DOMAIN'] != '') {
    global $nar_systemsettings;
    $nar_systemsettings['SITE']['SITEURL'] = $language['DOMAIN'];
    if($language['BASE_URL'] != '') {
        $nar_systemsettings['SITE']['BASE_URL'] = $language['BASE_URL'];
    }
}

$baseurl = $nar_systemsettings['SITE']['BASE_URL'];
global $ab_baseurl;
$ab_baseurl = $baseurl;

/**
 * Load plugins
 * @var Api_TraderApiHandler $apiHandler
 */
$apiHandler = Api_TraderApiHandler::getInstance($db);
$apiHandler->loadPlugins();

if (array_key_exists("apiAction", $_POST)) {
    $apiError = "Unknown";
    header("Content-Type: application/json");
    switch ($_POST["apiAction"]) {
        case "urlGenerate":
            if (preg_match("/^[^\^\°\{\}]*$/", $_POST["url"])) {
                $tpl_temp = new Template("tpl/de/empty.htm");
                $urlResult = $tpl_temp->tpl_uri_action($_POST["url"]);
                die(json_encode(array("success" => true, "url" => $urlResult)));
            }
            break;
    }
    die(json_encode(array("success" => false, "error" => $apiError)));
}

if ($_REQUEST["importAddresses"]) {
    $arImportAds = array_keys($db->fetch_nar("SELECT ID_AD_MASTER FROM `ad_master` WHERE FK_COUNTRY=1 LIMIT 50000"));
    $fileHandle = fopen("addresses.csv", "r");
    $index = 0;
    $arLineHeader = fgetcsv($fileHandle, 0, ',', '"');
    while (!empty($arImportAds) && ($arLine = fgetcsv($fileHandle, 0, ',', '"'))) {
        $idImportAd = array_pop($arImportAds);
        $street = $arLine[3]." ".$arLine[1];
        $city = $arLine[7];
        $zip = $arLine[9];
        $db->querynow($q="
          UPDATE `ad_master` SET 
            FK_COUNTRY=41,
            STREET='".mysql_real_escape_string($street)."', 
            CITY='".mysql_real_escape_string($city)."',
            ZIP='".mysql_real_escape_string($zip)."',
            LATITUDE=0,
            LONGITUDE=0
          WHERE ID_AD_MASTER=".$idImportAd);
        $index++;
    }
    die("test: ".$index." done.");
}