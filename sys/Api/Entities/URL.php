<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 11.06.15
 * Time: 10:58
 */

class Api_Entities_URL {
    
    protected static $urlOptionCache = array();
    
    protected $urlHost;
    protected $urlPath;
    protected $urlQuery;
    protected $urlSecure;
    
    protected $pageIdent;
    protected $pageIdentPath;
    protected $pageAlias;
    protected $pageParameters;
    protected $pageParametersOptional;
    protected $pageCustom;

    protected $langId;
    protected $langAbbr;
    protected $langVal;
    
    function __construct($urlHost = false, $urlPath = false, $urlSecure = false, $pageIdent = false, $pageIdentPath = false, $pageAlias = false, $pageParameters = array(), $pageParametersOptional = array(), $pageCustom = false, $langId = NULL) {
        $this->urlHost = Api_IDN::decodeIDN($urlHost);
        #$this->urlHost = $urlHost;
        $this->urlPath = $urlPath;
        $this->urlSecure = $urlSecure;
        // Remove query if existing
        $requestUriQueryIndex = strpos($this->urlPath, "?");
        if ($requestUriQueryIndex !== false) {
            $this->urlQuery = substr($this->urlPath, $requestUriQueryIndex+1);
            $this->urlPath = substr($this->urlPath, 0, $requestUriQueryIndex);
        } else {
            $this->urlQuery = false;
        }
        $this->pageIdent = $pageIdent;
        $this->pageIdentPath = $pageIdentPath;
        $this->pageAlias = $pageAlias;
        $this->pageParameters = $pageParameters;
        $this->pageParametersOptional = $pageParametersOptional;
        $this->pageCustom = $pageCustom;
        // Set language
        $this->langId = false;
        $this->langAbbr = false;
        $this->langVal = false;
        if ($langId !== null) {
            $this->setLanguageById( $langId );
        } else {
            if ($GLOBALS["urlCurrentRequest"] instanceof Api_Entities_URL) {
                // Use language of the current pages request
                $this->setLanguageById( $GLOBALS["urlCurrentRequest"]->getLanguageId() );
            } else {
                // Use default language
                $this->setLanguageById( $GLOBALS['nar_systemsettings']['SITE']['std_country'] );
            }
        }
    }

    public static function createFromURL($urlHost, $urlPath = false, $urlSecure = false, $langId = NULL, $pageIdent = false, $pageIdentPath = false, $pageAlias = false, $pageParameters = array(), $pageParametersOptional = array(), $pageCustom = false) {
        return new Api_Entities_URL($urlHost, $urlPath, $urlSecure, $pageIdent, $pageIdentPath, $pageAlias, $pageParameters, $pageParametersOptional, $pageCustom, $langId);
    }

    public static function createFromPage($pageIdent, $pageIdentPath = false, $pageAlias = false, $pageParameters = array(), $pageParametersOptional = array(), $pageCustom = false, $langId = NULL) {
        return new Api_Entities_URL(false, false, false, $pageIdent, $pageIdentPath, $pageAlias, $pageParameters, $pageParametersOptional, $pageCustom, $langId);
    }

    public static function createFromURLRaw($urlRaw) {
        $urlParsed = parse_url($urlRaw);
        return self::createFromURL($urlParsed["host"], $urlParsed["path"].(array_key_exists("query", $urlParsed) ? "?".$urlParsed["query"] : ""), $urlParsed["scheme"] === "https");
    }
    
    public static function createByBasePath($urlBasePath, $isSSL = null, $useSSL = 1) {
        if ($isSSL === null) {
            $isSSL = !empty($_SERVER['HTTPS']);
        }
        if ($GLOBALS["nar_systemsettings"]["SITE"]["USE_SSL"]) {
            // Result is not absolute, ssl can be prepended
            if ($GLOBALS["nar_systemsettings"]["SITE"]["USE_SSL_GLOBAL"]) {
                $useSSL = 2;
            }
            switch ($useSSL) {
                case 2:
                    // SSL immer aktivieren
                    $isSSL = true;
                    break;
                case 0:
                    // SSL immer deaktivieren
                    $isSSL = false;
                    break;
                case 1:
                default:
                    // Aktuelle einstellung beibehalten / relativ verlinken
                    break;
            }
        }
        $uriFull = rtrim($GLOBALS["nar_systemsettings"]['SITE']['BASE_URL'], "/") . "/" . ltrim($urlBasePath, "/");
        $urlHost = rtrim(str_replace("http://", "", self::getUrlOptionSiteUrl()), "/");
        $urlLink = Api_Entities_URL::createFromURL($urlHost, $uriFull, $isSSL);

        Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::URL_OUTPUT, $urlLink);

        return $urlLink;
    }
    
    public static function createByPagePath($urlParams, $urlParamsOptional, $absolute = false, $isSSL = null, $useSSL = 1) {
        global $ar_nav, $nar_ident2nav;

        if ($isSSL === null) {
            $isSSL = !empty($_SERVER['HTTPS']);
        }
        // Parse url parameters
        $parameter = explode(',', $urlParams);
        $ident = array_shift($parameter);
        $uriParameters = $parameter;
        $urlHost = rtrim(str_replace("http://", "", $GLOBALS["nar_systemsettings"]['SITE']['SITEURL']), "/");
        $urlLink = Api_Entities_URL::createFromPage($ident, false, false, $uriParameters, $urlParamsOptional);
        $urlLink->setHost($urlHost);
        $urlLink->setSecure($isSSL);
        // Trigger API-Event allowing to manipulate the parameters before generating the URL
        $urlParams = new Api_Entities_EventParamContainer(array(
            "url" => $urlLink,
            "template" => self::getUrlOptionTemplateTemporary()     // TODO: Do not require a template anymore for the future!
        ));
        Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::URL_GENERATE, $urlParams);
        $uriParameters = $urlLink->getPageParameters();
        $urlParamsOptional = $urlLink->getPageParametersOptional();

        $id_nav = $nar_ident2nav[$ident];
        if ($id_nav != NULL) {
            $ident_path = (!empty($ar_nav[$id_nav]["ident_path"]) ? explode("/", $ar_nav[$id_nav]["ident_path"]) : array());
            $url_path = "/" . (!empty($ident_path) ? $ident_path[0] . "/" : "");
            $url_ident = ($ar_nav[$id_nav]["ALIAS"] ? $ar_nav[$id_nav]["ALIAS"] : $ar_nav[$id_nav]["IDENT"]);
            $urlLink->setPageIdentPath($url_path);

            if (is_array($GLOBALS['ar_nav_urls_by_id'][$id_nav])) {
                require_once $GLOBALS['ab_path'] . "sys/lib.nav.url.php";
                $navUrlMan = NavUrlManagement::getInstance($GLOBALS["db"]);
                $href = $navUrlMan->generateUrlByNav($id_nav, $uriParameters, $urlParamsOptional, self::getUrlOptionTemplateTemporary());
                if ($href !== false) {
                    return self::createByBasePath($href, $absolute, $isSSL, 1);
                }
            }

            $url_ident_file = $url_ident . ((count($uriParameters) > 0) ? (',' . implode(',', $uriParameters)) : '') . '.htm';
            if ($url_path == '/') {
                if (in_array($url_ident, array("index"))) {
                    return self::createByBasePath($url_ident_file, $absolute, $isSSL, 1);
                } else if ((isset($uriParameters) && (count($uriParameters) > 0)) || $ar_nav[$id_nav]["PARENT"]) {
                    return self::createByBasePath($url_ident . '/' . $url_ident_file, $absolute, $isSSL, 1);
                } else {
                    return self::createByBasePath($url_ident . '/', $absolute, $absolute, $isSSL, 1);
                }
            } else {
                return self::createByBasePath($url_path . $url_ident_file, $absolute, $isSSL, 1);
            }
        }
        return null;
    }
    
    public static function getUrlOptionSiteUrl() {
        return $GLOBALS["nar_systemsettings"]['SITE']['SITEURL'];
    }
    
    public static function getUrlOptionTemplateTemporary() {
        if (!array_key_exists("TEMPLATE_TEMP", self::$urlOptionCache)) {
            require_once $GLOBALS["ab_path"]."sys/lib.template.php";
            self::$urlOptionCache["TEMPLATE_TEMP"] = new Template("tpl/de/empty.htm");
        }
        return self::$urlOptionCache["TEMPLATE_TEMP"];
    }
    
    public static function encodeText($value) {
        return addnoparse(chtrans($value));
    }
    
    public function getHost() {
        return $this->urlHost;
    }

    public function getPath() {
        return $this->urlPath;
    }

    public function getQuery() {
        return $this->urlQuery;
    }

    public function getSecure() {
        return $this->urlSecure;
    }

    public function getLanguageId() {
        return $this->langId;
    }

    public function getLanguageAbbr() {
        return $this->langAbbr;
    }

    public function getLanguageBitval() {
        return $this->langVal;
    }

    public function getPageIdent() {
        return $this->pageIdent;
    }

    public function getPageIdentPath() {
        return $this->pageIdentPath;
    }

    public function getPageAlias() {
        return $this->pageAlias;
    }

    public function getPageParameter($index) {
        if (array_key_exists($index, $this->pageParameters)) {
            return $this->pageParameters[$index];
        } else {
            return null;
        }
    }

    public function getPageParameters() {
        return $this->pageParameters;
    }

    public function getPageParameterOptional($index) {
        if (array_key_exists($index, $this->pageParametersOptional)) {
            return $this->pageParametersOptional[$index];
        } else {
            return null;
        }
    }

    public function getPageParametersOptional() {
        return $this->pageParametersOptional;
    }
    
    public function isPageCustom() {
        return $this->pageCustom;
    }

    public function setHost($urlHost) {
        $this->urlHost = $urlHost;
    }

    public function setPath($urlPath) {
        $this->urlPath = $urlPath;
    }

    public function setQuery($urlQuery) {
        $this->urlQuery = $urlQuery;
    }

    public function setSecure($urlSecure) {
        $this->urlSecure = ($urlSecure ? true : false);
    }

    public function setRaw($urlRaw) {
        $urlParsed = parse_url($urlRaw."?test=bla");
        $this->urlHost = $urlParsed["host"];
        $this->urlPath = $urlParsed["path"].(array_key_exists("query", $urlParsed) ? "?".$urlParsed["query"] : "");
        $this->urlSecure = ($urlParsed["scheme"] === "https" ? true : false);
    }
    
    public function setLanguage($langId, $langAbbr, $langVal) {
        $this->langId = $langId;
        $this->langAbbr = $langAbbr;
        $this->langVal = $langVal;
    }
    
    public function setLanguageById($langId) {
        foreach ($GLOBALS['lang_list'] as $langIndex => $langDetails) {
            if ($langDetails['ID_LANG'] == $langId) {
                $this->langId = $langId;
                $this->langAbbr = $langDetails["ABBR"];
                $this->langVal = $langDetails["BITVAL"];
                return true;
            }
        }
        return false;
    }

    public function setLanguageByAbbr($langAbbr) {
        foreach ($GLOBALS['lang_list'] as $langIndex => $langDetails) {
            if ($langDetails['ABBR'] == $langAbbr) {
                $this->langId = $langDetails["ID_LANG"];
                $this->langAbbr = $langAbbr;
                $this->langVal = $langDetails["BITVAL"];
                return true;
            }
        }
        return false;
    }

    public function setPageIdent($pageIdent) {
        $this->pageIdent = $pageIdent;
    }

    public function setPageIdentPath($pageIdentPath) {
        $this->pageIdentPath = $pageIdentPath;
    }

    public function setPageAlias($pageAlias) {
        $this->pageAlias = $pageAlias;
    }

    public function setPageParameter($index, $value) {
        $this->pageParameters[$index] = $value;
    }

    public function setPageParameters($pageParameters) {
        $this->pageParameters = $pageParameters;
    }

    public function setPageParameterOptional($index, $value) {
        $this->pageParametersOptional[$index] = $value;
    }

    public function setPageParametersOptional($pageParametersOptional) {
        $this->pageParametersOptional = $pageParametersOptional;
    }
    
    public function setPageCustom($pageCustom = true) {
        $this->pageCustom = $pageCustom;
    }

    public function getRaw($forceHost = false) {
        $currentHost = false;
        $currentSecure = false;
        if ($GLOBALS["urlCurrentRequest"] instanceof Api_Entities_URL) {
            $currentHost = $GLOBALS["urlCurrentRequest"]->getHost();
            $currentSecure = $GLOBALS["urlCurrentRequest"]->getSecure();
        }
        $urlResult = $this->urlPath.($this->urlQuery !== false ? "?".$this->urlQuery : "");                             // Url / query
        if (($this->urlHost !== false) && ($forceHost ||
                ($currentHost === false) || ($currentHost != $this->urlHost) ||                                         // Host unknown/mismatch?
                ($currentSecure != $this->urlSecure)))                                                                  // https>http or http>https
        {
            $urlResult = ($this->urlSecure ? "https://" : "http://").$this->urlHost.$urlResult;     // Protocol / host
        }
        return $urlResult;
    }

}