<?php
/* ###VERSIONSBLOCKINLCUDE### */

class CacheAdmin {
    
    private static $categories = array(
        array("ident" => "marketplace", "name" => "Marktplatz", "description" => "Cache zu den Marktplatz-Anzeigen und -Kategorien"),
        array("ident" => "news", "name" => "News", "description" => "Cache zu den News-Artikeln"),
        array("ident" => "vendor", "name" => "Anbieter", "description" => "Cache zu den Anbietern"),
        array("ident" => "club", "name" => "Gruppen", "description" => "Cache zu den Gruppen"),
        array("ident" => "subtpl", "name" => "Subtemplates", "description" => "Subtemplate-Module mit Cache-Funktion")
    );
    
    private $db;
    private $arCaches;
    
    function __construct(ebiz_db $db = null) {
        $this->db = ($db === null ? $GLOBALS["db"] : $db);
        $this->arCaches = array();
        $this->readCaches();
    }
    
    private function addCache($ident, $name, $categories, $option, $files) {
        $this->arCaches[] = $this->readCacheDetails(array(
            "ident"         => $ident,
            "name"          => $name,
            "categories"    => $categories,
            "option"        => $option,
            "files"         => $files
        ));
    }
    
    private function readCaches() {
        global $ab_path;
        $this->addCache(
            "index_ads_new", "Startseite: Frisch eingestellte Produkte", array("marketplace"), "LIFETIME_INDEX_ADS",
            array($ab_path.'cache/marktplatz/neustart.*.htm', $ab_path.'cache/marktplatz/start_pager_.*.htm')
        );
        $this->addCache(
            "index_vendors_new", "Startseite: Händler vorgestellt", array("vendor"), "LIFETIME_INDEX_VENDOR",
            array($ab_path.'cache/marktplatz/start_haendlervorgestellt.*.htm')
        );
        $this->addCache(
            "marketplace_categories", "Startseite/Marktplatz: Kategorien", array("marketplace"), "LIFETIME_CATEGORY",
            array($ab_path.'cache/marktplatz/liste_*.htm', $ab_path.'cache/marktplatz/tree_*.htm', $ab_path.'cache/marktplatz/kat_*.htm')
        );
        $this->addCache(
            "marketplace_input", "Marktplatz: Eingabefelder für den Einstell-Prozess", array("marketplace"), false,
            array($ab_path.'cache/marktplatz/inputfields_*.htm')
        );
        $this->addCache(
            "club_categories", "Gruppen: Kategorien im Menü", array("club", "marketplace"), "LIFETIME_CLUB_CATEGORIES",
            array($ab_path.'cache/marktplatz/liste_*.htm')
        );
        $this->addCache(
            "user_categories", "Anbieter: Kategorien im Menü", array("vendor", "marketplace"), "LIFETIME_USER_CATEGORIES",
            array($ab_path."cache/marktplatz/kat_anbieter_*.htm")
        );
	    $this->addCache(
		    "vendor_search", "Anbieter: Suchmaske", array("vendor"), false,
		    array($ab_path."cache/marktplatz/vendor/inputfields_*.htm", $ab_path."cache/marktplatz/vendor/search/*.htm")
	    );
        $this->addCache(
            "vendor_search", "Anbieter: Keywords", array("vendor"), false,
            array($ab_path."cache/vendor/keywords/*")
        );
        $this->addCache(
            "vendor_search", "Anbieter: Vendor Details", array("vendor"), false,
            array($ab_path."cache/vendor/vendor_details/*")
        );
        $this->addCache(
            "subtpl_vendor_search", "Subtemplate: Anbieter/Branchenbuch Suchmaske", array("vendor", "subtpl"), false,
            array($ab_path.'cache/marktplatz/vendor/search/*.htm')
        );
        $this->addCache(
            "subtpl_vendor_statistics", "Subtemplate: Anbieter/Branchenbuch Statistik", array("vendor", "subtpl"), false,
            array($ab_path.'cache/vendor/vendor_statistics_*.htm')
        );
        $this->addCache(
            "subtpl_ads_newest", "Subtemplate: Anzeigen (neuste zuerst)", array("subtpl"), false,
            array($ab_path . 'cache/marktplatz/newest/*.htm')
        );
        $this->addCache(
            "subtpl_ads_random", "Subtemplate: Anzeigen (zufällige Sortierung)", array("subtpl"), false,
            array($ab_path . 'cache/marktplatz/random/*.htm')
        );
        $this->addCache(
            "subtpl_ads_search", "Subtemplate: Anzeigen Suchmaske", array("subtpl"), false,
            array($ab_path.'cache/marktplatz/search/*.htm', $ab_path.'cache/marktplatz/manufacturer/sbox_*.htm')
        );        
        $this->addCache(
            "subtpl_ads_user", "Subtemplate: Anzeigen eines Benutzers", array("subtpl"), false,
            array($ab_path . 'cache/marktplatz/user/*.htm')
        );
        $this->addCache(
            "subtpl_recent_events", "Subtemplate: Neuste Veranstaltungen", array("subtpl"), false,
            array($ab_path . 'cache/event/recent_*.htm')
        );
        $this->addCache(
            "subtpl_recent_groups", "Subtemplate: Neuste Gruppen", array("subtpl"), false,
            array($ab_path . 'cache/club/recent_*.htm')
        );
        $this->addCache(
            "subtpl_recent_news", "Subtemplate: Neuste News-Artikel", array("subtpl"), false,
            array($ab_path . 'cache/news/recent_*.htm')
        );
        $this->addCache(
            "subtpl_comments_ratings", "Subtemplate: Kommentare/Bewertungen", array("subtpl"), false,
            array($ab_path . 'cache/comment/ratings/summary_*.htm')
        );
	    $this->addCache(
		    "subtpl_vendor_presentation", "Subtemplate: Vorgestellte Anbieter", array("vendor", "subtpl"), false,
		    array($ab_path . 'cache/marktplatz/start_haendlervorgestellt.*.htm')
	    );

        
        $this->addCache(
          "hdb_producttype_config", "Hersteller DB: Typkonfiguration", array("marketplace"), "LIFETIME_CATEGORY",
          array($ab_path.'cache/marktplatz/hdb_producttype_config.*.php')
        );
        
    }
    
    private function readCacheDetails($arCache) {
        switch ($arCache["ident"]) {
            default:
                $this->readCacheSettings($arCache);
                $this->readCacheFileStatus($arCache);
                break;
        }
        return $arCache;
    }

    private function readCacheSettings(&$arCache) {
        global $nar_systemsettings;
        // Set defaults
        $arCache["settingInterval"] = false;
        // Read settings
        switch ($arCache['ident']) {
            default:
                $arCache["settingInterval"] = $nar_systemsettings["CACHE"][ $arCache["option"] ];
                break;
        }
        return true;
    }

    private function readCacheFileStatus(&$arCache) {
        // Set defaults
        $arCache["fileCount"] = 0;
        $arCache["fileUpdateMin"] = false;
        $arCache["fileUpdateMax"] = false;
        // Read file status
        if ($arCache['files'] !== false) {
            $arFiles = (is_array($arCache['files']) ? $arCache['files'] : array($arCache['files']));
            foreach ($arFiles as $wildcardIndex => $wildcard) {
                $arWildcardFiles = glob($wildcard);
                foreach ($arWildcardFiles as $fileIndex => $fileName) {
                    if (is_file($fileName)) {
                        $arCache["fileCount"]++;
                        $arCache["fileSize"] += filesize($fileName);
                        $fileChanged = filemtime($fileName);
                        if (($arCache["fileUpdateMin"] == false) || ($arCache["fileUpdateMin"] > $fileChanged)) {
                            $arCache["fileUpdateMin"] = $fileChanged;
                            $arCache["fileUpdateMinStamp"] = date("Y-m-d H:i:s", $fileChanged);
                        }
                        if (($arCache["fileUpdateMax"] == false) || ($arCache["fileUpdateMax"] < $fileChanged)) {
                            $arCache["fileUpdateMax"] = $fileChanged;
                            $arCache["fileUpdateMaxStamp"] = date("Y-m-d H:i:s", $fileChanged);
                        }
                    }
                }
            }
        }
        return true;
    }

    private function deleteCacheFiles($fileWildcards) {
        $arFiles = (is_array($fileWildcards) ? $fileWildcards : array($fileWildcards));
        foreach ($arFiles as $wildcardIndex => $wildcard) {
            system("rm -f ".$wildcard);
        }
        return true;
    }
    
    public function getCategories() {
        return self::$categories;
    }

    public function getCacheByIdent($ident) {
        $arCache = false;
        foreach ($this->arCaches as $cacheIndex => $cacheDetails) {
            if ($cacheDetails['ident'] == $ident) {
                $arCache = $cacheDetails;
                break;
            }
        }
        return $arCache;
    }
    
    public function getCacheList() {
        return $this->arCaches;
    }
    
    public function getCacheListByCategory($category) {
        $arResult = array();
        foreach ($this->arCaches as $cacheIndex => $cacheDetails) {
            if (in_array($category, $cacheDetails["categories"])) {
                $arResult[] = $cacheDetails;
            }
        }
        return $arResult;
    }

    /**
     * Clear the cache with the given ident
     * @param $ident
     * @return bool
     */
    public function emptyCache($ident) {
        $arCache = $this->getCacheByIdent($ident);
        if ($arCache === false) {
            return false;
        }
        return $this->emptyCachePrivate($arCache);
    }

    /**
     * Clear the cache with the given ident
     * @param $ident
     * @return bool
     */
    protected function emptyCachePrivate($arCache) {
        if ($this->emptyCacheSpecial($arCache['ident'])) {
            // Delete all cache files for the selected cache
            $this->deleteCacheFiles($arCache['files']);
        }
        return true;
    }

    /**
     * This function can do special actions for specific caches.
     * @param $ident
     * @return  bool    Return true to delete all cache-files defined for this cache afterwards,
     *                  false to not delete anything at all.
     */
    public function emptyCacheSpecial($ident) {
        switch ($ident) {
            default:
                return true;
        }
    }

    /**
     * Clear all caches withing the given category
     * @param $categoryIdent
     * @return bool
     */
    public function emptyCacheCategory($categoryIdent) {
        $arCaches = $this->getCacheListByCategory($categoryIdent);
        foreach ($arCaches as $cacheIndex => $cacheDetails) {
            $this->emptyCachePrivate($cacheDetails);
        }
        return true;
    }

    /**
     * Set update interval for the given cache
     * @param $ident
     * @param $param
     */
    public function setInterval($ident, $interval) {
        $arCache = $this->getCacheByIdent($ident);
        switch ($ident) {
            default:
                return $this->updateSystemSetting("CACHE", $arCache["option"], (int)$interval);
        }
    }

    private function updateSystemSetting($plugin, $typ, $value) {
        // Update setting in database
        $this->db->querynow("
          UPDATE `option` SET value='". mysql_real_escape_string($value)."'
          WHERE plugin='". mysql_real_escape_string($plugin)."' AND typ='". mysql_real_escape_string($typ)."'");
        // Rewrite settings-cache
        $this->updateSystemSettingCache();
        // Return success
        return true;
    }

    private function updateSystemSettingCache() {
        global $ab_path;
        $data = $this->db->fetch_table("select * from `option` order by plugin, typ");
        $ar = array ();
        foreach ($data as $row) {
            $ar[$row['plugin']][$row['typ']] = $row['value'];
        }
        $s_code = '<?'. 'php $nar_systemsettings = '. php_dump($ar, 0). '; ?'. '>';
        $fp = fopen($file_name = $ab_path.'cache/option.php', 'w');
        fputs($fp, $s_code);
        fclose ($fp);
        chmod($file_name, 0777);
    }

}