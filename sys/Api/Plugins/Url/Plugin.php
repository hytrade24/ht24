<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 08.09.14
 * Time: 16:04
 */

class Api_Plugins_Url_Plugin extends Api_TraderApiPlugin {

    /**
     * Defines the priority of the Plugin. (Higher number = more important)
     * @return int
     */
    static public function getPriority() {
        return 0;
    }

    /**
     * Returns true if this plugin is a system-plugin which can not be disabled!
     * @return bool
     */
    public function isSystemPlugin() {
        return true;
    }

    /**
     * Register the events needed by your plugin within this function.
     * @return bool     False for error, plugin will not be loaded if an error occurs
     */
    public function registerEvents() {
        $this->registerEvent( Api_TraderApiEvents::URL_PROCESS_LANGUAGE, "urlProcessLanguage" );
        $this->registerEvent( Api_TraderApiEvents::URL_PROCESS_PAGE, "urlProcessPage" );
        $this->registerEvent( Api_TraderApiEvents::URL_GENERATE, "urlGenerate" );
        return true;
    }
    
    public function urlProcessLanguage(Api_Entities_URL $url) {
        $urlRaw = "http://".$url->getHost().$url->getPath();
        if ($GLOBALS['nar_systemsettings']['SITE']['MOD_REWRITE']) {
            // Get language by domain
            foreach ($GLOBALS['lang_list'] as $langIndex => $langCurrent) {
                $langDomain = (!empty($langCurrent['DOMAIN']) ? $langCurrent['DOMAIN'] : $GLOBALS['nar_systemsettings']['SITE']['SITEURL']);
                $langDomainFull = $langDomain.$langCurrent["BASE_URL"];
                if ($langDomainFull != '' && strpos($urlRaw, $langDomainFull) === 0) {
                    $url->setLanguage($langCurrent["ID_LANG"], $langCurrent["ABBR"], $langCurrent["BITVAL"]);
                    return true; // Consume event
                }
            }
            // Get language by subdomain (e.g. de.example.com / en.example.com)
            $arHostExplode = explode('.', strtolower($url->getHost()));
            if (is_array($arHostExplode) && !empty($arHostExplode) && array_key_exists($arHostExplode[0], $GLOBALS['lang_list'])) {
                $url->setLanguageByAbbr($arHostExplode[0]);
            }
        }
        if (array_key_exists("lang", $_REQUEST)) {
            $url->setLanguageByAbbr($_REQUEST["lang"]);
        }
        // Keep current/default language
        return false;
    }
    
    public function urlProcessPage(Api_Entities_URL $url) {
        if ($_REQUEST['nav'] > 0) {
            $arNav = $GLOBALS['ar_nav'][(int)$_REQUEST['nav']];
            $url->setPageIdent( $arNav['IDENT'] );
            if (!empty($arNav['ALIAS'])) {
                $url->setPageAlias( $arNav['ALIAS'] );
            }
            return true; // Consume event
        }
        // Load url management class
        require_once $GLOBALS['ab_path']."sys/lib.nav.url.php";
        $navUrlMan = NavUrlManagement::getInstance($GLOBALS['db']);
        // Get url base
        $urlBase = $GLOBALS['nar_systemsettings']['SITE']['BASE_URL'];
        $urlPageIdent = false;
        $urlPageParams = false;
        $urlPageParamsOpt = false;
        // Parse url
        if ($navUrlMan->parseUrl(str_replace($urlBase, "/", urldecode($url->getPath())), $urlPageIdent, $urlPageParams, $urlPageParamsOpt)) {
            $url->setPageIdent($urlPageIdent);
            $url->setPageParameters($urlPageParams);
            $url->setPageParametersOptional($urlPageParamsOpt);
            return true; // Consume event
        } else {
            if (!empty($_REQUEST['page']) && array_key_exists($_REQUEST['page'], $GLOBALS['nar_ident2nav'])) {
                $url->setPageIdent($_REQUEST['page']);
                $url->setPageAlias($_REQUEST['page']);
                return true; // Consume event
            }
        }
        // Keep current/default page
        return false;
    }
    
    public function urlGenerate(Api_Entities_EventParamContainer $params) {
        /** @var Api_Entities_URL $url */
        $url = $params->getParam("url");
        $languageChanged = ($url->getLanguageAbbr() != $GLOBALS["s_lang"]);
        switch ($url->getPageIdent()) {
            case 'marktplatz':
                $categoryId = (int)$url->getPageParameter(0);
                if (($categoryId > 0) && (($url->getPageParameter(1) == NULL) || $languageChanged)) {
                    $url->setPageParameter(1, Api_Entities_URL::encodeText($this->urlGenerate_getMarketplaceKatName($categoryId, $url->getLanguageAbbr())));
                }
                break;
            case 'marktplatz_anzeige':
                $articleId = (int)$url->getPageParameter(0);
                if (($articleId > 0) && (($url->getPageParameter(1) == NULL) || ($url->getPageParameterOptional("KAT_PATH") === null) || $languageChanged)) {
                    $arArticle = $GLOBALS["db"]->fetch1("SELECT FK_KAT, PRODUKTNAME FROM `ad_master` WHERE ID_AD_MASTER=".$articleId);
                    if (is_array($arArticle)) {
                        // Force correct article title
                        $url->setPageParameter(1, Api_Entities_URL::encodeText($arArticle["PRODUKTNAME"]));
                        // Force correct category path as optional parameter
                        if (($url->getPageParameterOptional("KAT_PATH") === null) || $languageChanged) {
                            $url->setPageParameterOptional("KAT_PATH", $this->urlGenerate_getMarketplaceKatPath($arArticle["FK_KAT"], $url->getLanguageAbbr()));
                        }
                    }
                }
                break;
        }
    }

    protected function urlGenerate_getMarketplaceKatName($categoryId, $s_lang, $seperator = "/", $urlEncode = true) {
        // Escape category id and validate
        $categoryId = (int)$categoryId;
        if ($categoryId <= 0) {
            return "";
        }
        $arLanguage = $GLOBALS['lang_list'][$s_lang];
        // Normale Kategorie-Darstellung
        require_once("sys/lib.shop_kategorien.php");
        $kat_cache = new TreeCategories("kat", 1);
        $arKat = $kat_cache->element_read($categoryId, $arLanguage["BITVAL"]);
        return $arKat["V1"];
    }

    protected function urlGenerate_getMarketplaceKatPath($categoryId, $s_lang, $seperator = "/", $urlEncode = true) {
        // Escape category id and validate
        $categoryId = (int)$categoryId;
        if ($categoryId <= 0) {
            return "";
        }
        $arLanguage = $GLOBALS['lang_list'][$s_lang];
        // Normale Kategorie-Darstellung
        $cacheFile = $GLOBALS['ab_path']."cache/marktplatz/ariane_".$s_lang.".".$categoryId.".txt";
        $cacheFileLifeTime = $GLOBALS['nar_systemsettings']['CACHE']['LIFETIME_CATEGORY'];
        $modifyTime = @filemtime($cacheFile);
        $diff = ((time() - $modifyTime) / 60);
        if(($diff > $cacheFileLifeTime) || !file_exists($cacheFile)) {
            require_once $GLOBALS['ab_path']."sys/lib.pub_kategorien.php";
            $kat_cache = new CategoriesCache();
            $kat_cache->cacheKatArianeText($categoryId, $arLanguage["ID_LANG"]);
        }
        $path = file_get_contents($cacheFile);
        $arPath = explode("|", $path);
        if (($urlEncode !== null) && $urlEncode) {
            foreach ($arPath as $pathIndex => $pathName) {
                $arPath[$pathIndex] = addnoparse(chtrans($pathName));
            }
        }
        return implode($seperator, $arPath);
    }
}