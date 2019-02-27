<?php

class Api_Plugins_SitemapXml_Plugin extends Api_TraderApiPlugin {

    /** @var int     Update interval in hours */
    private static $updateInterval = 24;

    /** @var int     Maximum number of urls in one sitemap */
    private static $maxUrlCount = 45000;
    /** @var int     Maximum size of a sitemap in bytes */
    private static $maxByteSize = 10000000;



    /**
     * @var Template|null
     */
    private $tplTemp = null;

    /**
     * Defines the priority of the Plugin. (Higher number = more important)
     * @return int
     */
    static public function getPriority() {
        return 0;
    }

    /**
     * Register the events needed by your plugin within this function.
     * @return bool     False for error, plugin will not be loaded if an error occurs
     */
    public function registerEvents() {
        $this->registerEvent(Api_TraderApiEvents::CRONJOB_DONE, "cronjob");
        return true;
    }

    public function cronjob() {
        $this->updateSitemap();
    }

	public function debug() {
		$this->updateSitemap();
	}

    private function isSSL() {
        return $GLOBALS["nar_systemsettings"]["SITE"]["USE_SSL_GLOBAL"];
    }

    private function getStatus( $language = "" ) {
        $sitemapPath = $GLOBALS["ab_path"]."cache/sitemap";
        if (!is_dir($sitemapPath)) {
            mkdir($sitemapPath, 0777, true);
        }
	    $statusFile = '';
        if ( $language == "" ) {
	        $statusFile = $sitemapPath."/status.json";
        }
        else {
	        $statusFile = $sitemapPath."/status_".$language.".json";
        }
        $statusResult = array(
            "UPDATE_NEXT" => time(),
            "UPDATE_DETAIL" => array()
        );
        if (file_exists($statusFile)) {
            $statusResultStored = @json_decode(file_get_contents($statusFile), true);
            if (is_array($statusResultStored)) {
                $statusResult = $statusResultStored;
            }
        }
        return $statusResult;
    }

    /**
     * Returns the full path to the sitename file.
     * Either the main "index" sitemap (if part is null) or the "sub" sitemap (if part is some string)
     * @param string|null $sitenamePart
     */
    public function getSitemapFile($sitenamePart = null, $compressed = true, $language = "") {
        $sitemapPath = $GLOBALS["ab_path"]."cache/sitemap";
        if (!is_dir($sitemapPath)) {
            mkdir($sitemapPath, 0777, true);
        }
	    $sitemapPath .= "/sitemap";
        if ( $language != "" ) {
        	$sitemapPath .= '_'.$language;
        }
	    $sitemapPath .= ($sitenamePart === null ? "" : "_".$sitenamePart);
        $sitemapPath .= ($compressed ? ".xml.gz" : ".xml");
        return $sitemapPath;
    }

    /**
     * @return Template
     */
    public function getTemporaryTemplate($vars = array()) {
        if ($this->tplTemp === null) {
            // Initialize template
            $this->tplTemp = new Template("tpl/de/empty.htm");
        }
        // Set variables
        $this->tplTemp->vars = $vars;
        // Return
        return $this->tplTemp;
    }

    private function setStatus($arStatus,$language = "") {
        $sitemapPath = $GLOBALS["ab_path"]."cache/sitemap";
        if (!is_dir($sitemapPath)) {
            mkdir($sitemapPath, 0777, true);
        }
	    $statusFile = "";
        if ( $language == "" ) {
	        $statusFile = $sitemapPath."/status.json";
        }
        else {
	        $statusFile = $sitemapPath."/status_".$language.".json";
        }
        return @file_put_contents($statusFile, json_encode($arStatus));
    }

    private function updateSitemap() {
    	global $ab_path, $originalSystemSettings;

        $arSitemapUrls = array();

	    require_once __DIR__."/class/XmlSitemapWriter.php";
	    require_once __DIR__."/class/XmlSitemapIndexWriter.php";

	    global $ar_nav_urls, $ar_nav_urls_by_id;

	    $sys_langs = $GLOBALS["lang_list"];

        foreach ( $sys_langs as $key => $language ) {

	        $arStatus = $this->getStatus( $language["ABBR"] );

	        $timeStart = time();

	        if ($arStatus["UPDATE_NEXT"] > $timeStart) {
		        continue;
	        }

        	$idLang = $language['ID_LANG'];

	        // Store current settings
	        $currentLangId = $language['ID_LANG'];
	        $currentSiteURL = $GLOBALS["nar_systemsettings"]['SITE']['SITEURL'];
	        $currentSiteURLBase = $GLOBALS["nar_systemsettings"]['SITE']['BASE_URL'];
	        // Load target language
	        if (file_exists($ab_path.'cache/nav.url.'.$idLang.'.php')) {
		        include $ab_path.'cache/nav.url.'.$idLang.'.php';    // Siehe sys/lib.nav.url.php -> updateCache()
	        } else {
		        $ar_nav_urls = array();
		        $ar_nav_urls_by_id = array();
	        }
	        $GLOBALS["nar_systemsettings"]['SITE']['SITEURL'] = ($language["DOMAIN"] != "" ? $language["DOMAIN"] : $originalSystemSettings['SITE']['SITEURL']);
	        $GLOBALS["nar_systemsettings"]['SITE']['BASE_URL'] = ($language["BASE_URL"] != "" ? $language["BASE_URL"] : $originalSystemSettings['SITE']['BASE_URL']);
	        $GLOBALS["nar_systemsettings"]['SITE']['ABBR'] = $language["ABBR"];
	        $GLOBALS["nar_systemsettings"]['SITE']['BITVAL'] = $language["BITVAL"];


	        $sitemapFile = $this->getSitemapFile(null, true, $language["ABBR"]);
	        $sitemapFileUncompressed = $this->getSitemapFile(null, false, $language["ABBR"]);
	        $sitemapXml = new XmlSitemapIndexWriter();
	        $sitemapUrl = str_replace($ab_path, $GLOBALS["nar_systemsettings"]['SITE']['SITEURL'].$GLOBALS["nar_systemsettings"]['SITE']['BASE_URL'], $sitemapFile);
	        $arSitemapUrls[] = $sitemapUrl;

	        // Navigation entries
	        $this->updateSitemapPart($sitemapXml, $arStatus, "nav", $timeStart);
	        // News articles
	        $this->updateSitemapPart($sitemapXml, $arStatus, "news", $timeStart);
	        // Marketplace articles
	        $this->updateSitemapPart($sitemapXml, $arStatus, "ads", $timeStart);
	        // Events categories
	        $this->updateSitemapPart($sitemapXml, $arStatus, "events_cat", $timeStart);
	        // Events
	        $this->updateSitemapPart($sitemapXml, $arStatus, "events_detail", $timeStart);
	        // Groups categories
	        $this->updateSitemapPart($sitemapXml, $arStatus, "groups_cat", $timeStart);
	        // Groups
	        $this->updateSitemapPart($sitemapXml, $arStatus, "groups_detail", $timeStart);
	        // Vendors categories
	        $this->updateSitemapPart($sitemapXml, $arStatus, "vendors_cat", $timeStart);
	        // Vendors
	        $this->updateSitemapPart($sitemapXml, $arStatus, "vendors_detail", $timeStart);
	        // Jobs categories
	        $this->updateSitemapPart($sitemapXml, $arStatus, "jobs_cat", $timeStart);
	        // Jobs
	        $this->updateSitemapPart($sitemapXml, $arStatus, "jobs_detail", $timeStart);

	        // Update sitemap status
	        $this->setStatus($arStatus,$language["ABBR"]);

	        // Write sitemap
	        $sitemapSource = $sitemapXml->getDocument();
	        file_put_contents($sitemapFileUncompressed, $sitemapSource);
	        file_put_contents($sitemapFile, gzencode($sitemapSource));

	        // Restore original values
	        if (file_exists($ab_path.'cache/nav.url.'.$currentLangId.'.php')) {
		        include $ab_path.'cache/nav.url.'.$currentLangId.'.php';    // Siehe sys/lib.nav.url.php -> updateCache()
	        } else {
		        $ar_nav_urls = array();
		        $ar_nav_urls_by_id = array();
	        }
	        $GLOBALS["nar_systemsettings"]['SITE']['SITEURL'] = $currentSiteURL;
	        $GLOBALS["nar_systemsettings"]['SITE']['BASE_URL'] = $currentSiteURLBase;

        }

	    unset( $GLOBALS["nar_systemsettings"]['SITE']['ABBR'] );
	    unset( $GLOBALS["nar_systemsettings"]['SITE']['BITVAL'] );


	    if ( !file_exists($GLOBALS["ab_path"]."robots.txt") ||
	         !file_exists($GLOBALS["ab_path"]."robots.row.txt") || true ) {

		    // Create robots.txt
		    $tplRobots = $this->utilGetTemplate("robots.txt");
		    $tplRobots->addvar("sitemaps", "Sitemap: ".implode("\nSitemap: ", $arSitemapUrls));

		    file_put_contents($GLOBALS["ab_path"]."robots.txt", $tplRobots->process() );
	    }

        return true;
    }

    private function updateSitemapPlugins(XmlSitemapIndexWriter $sitemapXml, &$arStatus, $timeStart) {
        $pluginEventParams = new Api_Entities_EventParamContainer(array(
            "sitemapPlugin"     => $this,
            "sitemapXml"        => $sitemapXml,
            "status"            => $arStatus,
            "refreshAllowed"    => ((time() - $timeStart) < 10 ? true : false)
        ));
        Api_TraderApiHandler::getInstance()->triggerEvent("SITEMAP_XML_PLUGIN_UPDATE", $pluginEventParams);
    }

    private function updateSitemapPart(XmlSitemapIndexWriter $sitemapXml, &$arStatus, $partName, $timeStart) {
        /*
         * News articles
         */
        $timeGone = time() - $timeStart;
        $addExisting = array_key_exists($partName, $arStatus["UPDATE_DETAIL"]);
        if ($timeGone < 10) {
            if (!array_key_exists($partName, $arStatus["UPDATE_DETAIL"])
                || ($arStatus["UPDATE_DETAIL"][$partName]["UPDATE_NEXT"] <= $timeStart)) {
                // Delete current sitemaps
                if (!array_key_exists($partName, $arStatus["UPDATE_DETAIL"]) || ($arStatus["UPDATE_DETAIL"][$partName]["UPDATE_OFFSET"] == 0)) {
                    exec("rm -f " . $this->getSitemapFile($partName."_".$GLOBALS["nar_systemsettings"]['SITE']['ABBR']."_"."*"));
                }
                // Add article URLs
                $sitemapItemOffset = (array_key_exists($partName, $arStatus["UPDATE_DETAIL"]) ? (int)$arStatus["UPDATE_DETAIL"][$partName]["UPDATE_OFFSET"] : 0);
                $sitemapIndex = (($sitemapItemOffset > 0) && array_key_exists($partName, $arStatus["UPDATE_DETAIL"]) ? (int)$arStatus["UPDATE_DETAIL"][$partName]["COUNT_FILES"] : 0);
                $doContinue = true;
                do {
                    switch ($partName) {
                        default:
                            $doContinue = false;
                            break;
                        case "nav":
                            $addExisting = true;
                            $doContinue = false;
                            $this->updateSitemapNav($sitemapIndex++,$sitemapItemOffset);
	                        break;
                        case "news":
                            $doContinue = !$this->updateSitemapNews($sitemapIndex++, $sitemapItemOffset);
                            break;
                        case "ads":
                            $doContinue = !$this->updateSitemapAds($sitemapIndex++, $sitemapItemOffset);
                            break;
                        case "events_cat":
                            $doContinue = !$this->updateSitemapEventsCategories($sitemapIndex++, $sitemapItemOffset);
                            break;
                        case "events_detail":
                            $doContinue = !$this->updateSitemapEvents($sitemapIndex++, $sitemapItemOffset);
                            break;
                        case "groups_cat":
                            $doContinue = !$this->updateSitemapGroupsCategories($sitemapIndex++, $sitemapItemOffset);
                            break;
                        case "groups_detail":
                            $doContinue = !$this->updateSitemapGroups($sitemapIndex++, $sitemapItemOffset);
                            break;
                        case "vendors_cat":
                            $doContinue = !$this->updateSitemapVendorCategories($sitemapIndex++, $sitemapItemOffset);
                            break;
                        case "vendors_detail":
                            $doContinue = !$this->updateSitemapVendors($sitemapIndex++, $sitemapItemOffset);
                            break;
                        case "jobs_cat":
                            $doContinue = !$this->updateSitemapJobCategories($sitemapIndex++, $sitemapItemOffset);
                            break;
                        case "jobs_detail":
                            $doContinue = !$this->updateSitemapJobs($sitemapIndex++, $sitemapItemOffset);
                            break;
                    }
                    if ($sitemapItemOffset > 0) {
                        $sitemapXml->addSitemap(
                            $this->getTemporaryTemplate()->tpl_uri_baseurl_full(
                            	"/cache/sitemap/sitemap_" . $partName."_".$GLOBALS["nar_systemsettings"]['SITE']['ABBR']."_".($sitemapIndex-1).".xml.gz",
	                            $this->isSSL()
                            ),
                            time()
                        );
                    } else {
	                    $sitemapIndex--;
                    }
                    $timeGone = time() - $timeStart;
                    if ($timeGone > 10) {
                        break;
                    }
                } while ($doContinue);
                $addExisting = false; // Prevent adding the sitemaps twice
                if ($doContinue) {
                    // Continue on next call
                    $arStatus["UPDATE_DETAIL"][$partName] = array(
                        "UPDATE_NEXT"   => time(),
                        "UPDATE_LAST"   => time(),
                        "UPDATE_OFFSET" => $sitemapItemOffset,
                        "COUNT_FILES"   => $sitemapIndex
                    );
                } else {
                    $arStatus["UPDATE_DETAIL"][$partName] = array(
                        "UPDATE_NEXT"   => $timeStart + (self::$updateInterval * 3600),
                        "UPDATE_LAST"   => time(),
                        "UPDATE_OFFSET" => 0,
                        "COUNT_FILES"   => $sitemapIndex
                    );
                }
            }
        }
        if ($addExisting) {
            for ($sitemapIndex = 0; $sitemapIndex < $arStatus["UPDATE_DETAIL"][$partName]["COUNT_FILES"]; $sitemapIndex++) {
                $sitemapXml->addSitemap(
                    $this->getTemporaryTemplate()->tpl_uri_baseurl_full(
                    	"/cache/sitemap/sitemap_" . $partName."_".$GLOBALS["nar_systemsettings"]['SITE']['ABBR']."_".$sitemapIndex.".xml.gz",
	                    $this->isSSL()
                    ),
                    $arStatus["UPDATE_DETAIL"][$partName]["UPDATE_LAST"]
                );
            }
        }
    }

    private function updateSitemapNav($fileIndex = 0, &$offset = 0, $queryCount = 40000) {
        // Initialize sitemap writer
        $sitemapFile = $this->getSitemapFile("nav"."_".$GLOBALS["nar_systemsettings"]['SITE']['ABBR']."_".$fileIndex);
        $sitemapXml = new XmlSitemapWriter();
        // TODO: Write nav urls
	    include $GLOBALS["ab_path"]."cache/pageperm.1.php";
	    include $GLOBALS["ab_path"]."cache/nav1.".$GLOBALS["nar_systemsettings"]['SITE']['ABBR'].".php";
        $prev_ar_nav = $GLOBALS["ar_nav"];
        $GLOBALS["ar_nav"] = $ar_nav;
        $this->updateSitemapNav_Recursive($sitemapXml, $nar_pageperm[1], 0, $offset);
	    $GLOBALS["ar_nav"] = $prev_ar_nav;
	    if ($offset > 0) {
		    // Write sitemap
		    file_put_contents($sitemapFile, gzencode($sitemapXml->getDocument()));
	    }
        return true;
    }

    private function updateSitemapNav_Recursive(XmlSitemapWriter $sitemapXml, &$arPagePermissions, $navId = 0, &$offset = 0) {
        if (!array_key_exists($navId, $GLOBALS["ar_nav"])) {
            return false;
        }
        $arNav = $GLOBALS["ar_nav"][$navId];
        if (($navId == 0) || ($arNav["B_VIS"] && $arPagePermissions[$arNav["IDENT"]])) {
            if (!empty($arNav["IDENT"])) {
                $sitemapXml->addUrl(
                    $this->getTemporaryTemplate()->tpl_uri_action_full($arNav["IDENT"], $this->isSSL()),
                    time(),
                    XmlSitemapWriter::CHANGE_WEEKLY
                );
	            $offset++;
            }
            if (!empty($arNav["KIDS"])) {
                foreach ($arNav["KIDS"] as $childIndex => $childId) {
                    $this->updateSitemapNav_Recursive($sitemapXml, $arPagePermissions, $childId, $offset);
                }
            }
        }
        return true;
    }

    private function updateSitemapByQuery($partName, $urlPattern, $queryString, $queryCount = 40000, $fileIndex = 0, &$offset = 0) {
        // Initialize sitemap writer
        require_once __DIR__."/class/XmlSitemapWriter.php";
        $sitemapFile = $this->getSitemapFile($partName."_".$GLOBALS["nar_systemsettings"]['SITE']['ABBR']."_".$fileIndex);
        $sitemapXml = new XmlSitemapWriter();
        $sitemapDone = true;
        $sitemapCount = 0;
        // Query next batch of articles
        $queryResult = $this->db->querynow($queryString);
        while (($arObject = @mysql_fetch_assoc($queryResult["rsrc"])) !== false) {
            // Add next article to sitemap
            $sitemapXml->addUrl(
                (is_callable($urlPattern) ? $urlPattern($arObject) : $this->getTemporaryTemplate($arObject)->tpl_uri_action_full($urlPattern, $this->isSSL())),
                time(), // TODO: Real modification date?
                XmlSitemapWriter::CHANGE_WEEKLY
            );
            $sitemapCount++;
            $offset++;
            // Check sitemap count/size limit
            if (($sitemapXml->getCountUrls() >= self::$maxUrlCount) || ($sitemapXml->getSizeEstimate() >= self::$maxByteSize)) {
                // Count/size limit reached!
                $sitemapDone = false;
                break;
            }
        }
        if ($sitemapCount == $queryCount) {
            $sitemapDone = false;
        }
        if ($sitemapCount > 0) {
	        // Write sitemap
	        file_put_contents($sitemapFile, gzencode($sitemapXml->getDocument()));
        }
        return $sitemapDone;
    }

    private function updateSitemapNews($fileIndex = 0, &$offset = 0, $queryCount = 40000) {
        // Initialize sitemap writer
        $sitemapFile = $this->getSitemapFile("news"."_".$GLOBALS["nar_systemsettings"]['SITE']['ABBR']."_".$fileIndex);
        $sitemapXml = new XmlSitemapWriter();
        $sitemapDone = true;
        $sitemapCount = 0;
        // Query next batch of articles
        $newsManagement = Api_NewsManagement::getInstance($this->db);
        $arNews = $newsManagement->fetchAll(array("LIMIT" => $queryCount, "OFFSET" => $offset));
        foreach ($arNews as $newsIndex => $arNewsArticle) {
            // Add next article to sitemap
            $sitemapXml->addUrl(
                $newsManagement->generateNewsUrl($arNewsArticle, false, $this->isSSL()),
                time(), // TODO: Real modification date?
                XmlSitemapWriter::CHANGE_WEEKLY
            );
            $sitemapCount++;
            $offset++;
            // Check sitemap count/size limit
            if (($sitemapXml->getCountUrls() >= self::$maxUrlCount) || ($sitemapXml->getSizeEstimate() >= self::$maxByteSize)) {
                // Count/size limit reached!
                $sitemapDone = false;
                break;
            }
        }
        if ($sitemapCount == $queryCount) {
            $sitemapDone = false;
        }
        // Write sitemap
        file_put_contents($sitemapFile, gzencode($sitemapXml->getDocument()));
        return $sitemapDone;
    }

    private function updateSitemapAds($fileIndex = 0, &$offset = 0, $queryCount = 40000) {
        // Initialize sitemap writer
        $sitemapFile = $this->getSitemapFile("ads"."_".$GLOBALS["nar_systemsettings"]['SITE']['ABBR']."_".$fileIndex);
        $sitemapXml = new XmlSitemapWriter();
        $sitemapDone = true;
        $sitemapCount = 0;
        // Query next batch of articles
        $queryResult = $this->db->querynow("
          SELECT * FROM `ad_master`
          WHERE STATUS IN (1,5,9,13) AND DELETED=0
          ORDER BY STAMP_START DESC
          LIMIT ".(int)$queryCount." OFFSET ".(int)$offset);
        while (($arObject = @mysql_fetch_assoc($queryResult["rsrc"])) !== false) {
	        $article = Api_Entities_MarketplaceArticle::createFromMasterArray($arObject, $this->db, $GLOBALS["nar_systemsettings"]['SITE']['BITVAL']);
            // Add next article to sitemap
            $sitemapXml->addUrl(
                $article->getUrl(true, $this->getTemporaryTemplate(), false, $this->isSSL()),
                time(), // TODO: Real modification date?
                XmlSitemapWriter::CHANGE_WEEKLY
            );
            $sitemapCount++;
            $offset++;
            // Check sitemap count/size limit
            if (($sitemapXml->getCountUrls() >= self::$maxUrlCount) || ($sitemapXml->getSizeEstimate() >= self::$maxByteSize)) {
                // Count/size limit reached!
                $sitemapDone = false;
                break;
            }
        }
        if ($sitemapCount == $queryCount) {
            $sitemapDone = false;
        }
        // Write sitemap
        file_put_contents($sitemapFile, gzencode($sitemapXml->getDocument()));
        return $sitemapDone;
    }

    private function updateSitemapGroupsCategories($fileIndex = 0, &$offset = 0, $queryCount = 40000) {
        require_once $GLOBALS["ab_path"]."sys/lib.club.category.php";
        $queryAds = "
                SELECT t.*, s.V1, s.V2, s.T1
                FROM kat t
                LEFT JOIN string_kat s ON s.S_TABLE='kat' AND s.FK=t.ID_KAT
                  AND s.BF_LANG=if(t.BF_LANG_KAT & ".$GLOBALS["nar_systemsettings"]['SITE']['BITVAL'].", ".$GLOBALS["nar_systemsettings"]['SITE']['BITVAL'].", 1 << floor(log(t.BF_LANG_KAT+0.5)/log(2)))
                WHERE t.ROOT='".ClubCategoryManagement::CATEGORY_ROOT."' AND t.B_VIS=1 AND t.LFT>1
                ORDER BY t.LFT ASC
                LIMIT ".(int)$queryCount." OFFSET ".(int)$offset;
        return $this->updateSitemapByQuery(
            "groups_cat", "groups,{ID_KAT}",
            $queryAds, $queryCount, $fileIndex, $offset
        );
    }

    private function updateSitemapVendorCategories($fileIndex = 0, &$offset = 0, $queryCount = 40000) {
        require_once $GLOBALS["ab_path"]."sys/lib.vendor.category.php";
        $queryAds = "
          SELECT t.*, s.V1, s.V2, s.T1
          FROM `kat` t
          LEFT JOIN string_kat s ON s.S_TABLE='kat' AND s.FK=t.ID_KAT
            AND s.BF_LANG=if(t.BF_LANG_KAT & ".$GLOBALS["nar_systemsettings"]['SITE']['BITVAL'].", ".$GLOBALS["nar_systemsettings"]['SITE']['BITVAL'].", 1 << floor(log(t.BF_LANG_KAT+0.5)/log(2)))
          WHERE ROOT='".VendorCategoryManagement::CATEGORY_ROOT."' AND B_VIS=1 AND LFT>1
          ORDER BY LFT ASC
          LIMIT ".(int)$queryCount." OFFSET ".(int)$offset;
        return $this->updateSitemapByQuery(
            "vendors_cat", "vendor,{ID_KAT}",
            $queryAds, $queryCount, $fileIndex, $offset
        );
    }

    private function updateSitemapJobCategories($fileIndex = 0, &$offset = 0, $queryCount = 40000) {
        require_once $GLOBALS["ab_path"]."sys/lib.job.php";
        $queryAds = "
          SELECT t.*, s.V1, s.V2, s.T1
          FROM `kat` t
          LEFT JOIN string_kat s ON s.S_TABLE='kat' AND s.FK=t.ID_KAT
            AND s.BF_LANG=if(t.BF_LANG_KAT & ".$GLOBALS["nar_systemsettings"]['SITE']['BITVAL'].", ".$GLOBALS["nar_systemsettings"]['SITE']['BITVAL'].", 1 << floor(log(t.BF_LANG_KAT+0.5)/log(2)))
          WHERE ROOT='".JobManagement::CATEGORY_ROOT."' AND B_VIS=1 AND LFT>1
          ORDER BY LFT ASC
          LIMIT ".(int)$queryCount." OFFSET ".(int)$offset;
        return $this->updateSitemapByQuery(
            "jobs_cat", "jobs,{ID_KAT}",
            $queryAds, $queryCount, $fileIndex, $offset
        );
    }

    private function updateSitemapJobs($fileIndex = 0, &$offset = 0, $queryCount = 40000) {
        $queryAds = '
        SELECT a.ID_JOB, a.FK_USER, a.FK_KAT, b.V1, c.NAME FROM job a
            INNER JOIN string_job b ON b.S_TABLE="job" AND b.FK=1
            INNER JOIN user c ON c.ID_USER = a.FK_USER
            ORDER BY a.STAMP_CREATE DESC 
            LIMIT '.(int)$queryCount.' OFFSET '.(int)$offset;
        return $this->updateSitemapByQuery(
            "jobs_detail", "view_user_jobs,{NAME},{FK_USER},{FK_KAT},1,{ID_JOB},{urllabel(V1)}",
            $queryAds, $queryCount, $fileIndex, $offset
        );
    }

    private function updateSitemapEventsCategories($fileIndex = 0, &$offset = 0, $queryCount = 40000) {
        require_once $GLOBALS["ab_path"]."sys/lib.calendar_event.php";
        $queryAds = "
          SELECT t.*, s.V1, s.V2, s.T1 
          FROM `kat` t
          LEFT JOIN string_kat s ON s.S_TABLE='kat' AND s.FK=t.ID_KAT 
            AND s.BF_LANG=if(t.BF_LANG_KAT & ".$GLOBALS["nar_systemsettings"]['SITE']['BITVAL'].", ".$GLOBALS["nar_systemsettings"]['SITE']['BITVAL'].", 1 << floor(log(t.BF_LANG_KAT+0.5)/log(2)))
          WHERE ROOT='".CalendarEventManagement::CATEGORY_ROOT."' AND B_VIS=1 AND LFT>1
          ORDER BY LFT ASC
          LIMIT ".(int)$queryCount." OFFSET ".(int)$offset;
        return $this->updateSitemapByQuery(
            "events_cat", "calendar_events,{ID_KAT}",
            $queryAds, $queryCount, $fileIndex, $offset
        );
    }

    private function updateSitemapGroups($fileIndex = 0, &$offset = 0, $queryCount = 40000) {
        $queryAds = "
            SELECT ID_CLUB, NAME FROM club c
                WHERE c.STATUS=1
                ORDER BY c.STAMP DESC
                LIMIT ".(int)$queryCount." OFFSET ".(int)$offset;
        return $this->updateSitemapByQuery(
            "groups_detail", "club,{urllabel(NAME)},{ID_CLUB}",
            $queryAds, $queryCount, $fileIndex, $offset
        );
    }

    private function updateSitemapEvents($fileIndex = 0, &$offset = 0, $queryCount = 40000) {
        $queryAds = "
          SELECT ID_CALENDAR_EVENT, TITLE FROM `calendar_event`
          WHERE PRIVACY=1 AND IS_CONFIRMED=1 AND MODERATED=1
          ORDER BY STAMP_START DESC
          LIMIT ".(int)$queryCount." OFFSET ".(int)$offset;
        return $this->updateSitemapByQuery(
            "events_detail", "calendar_events_view,{urllabel(TITLE)},{ID_CALENDAR_EVENT}",
            $queryAds, $queryCount, $fileIndex, $offset
        );
    }

    private function updateSitemapVendors($fileIndex = 0, &$offset = 0, $queryCount = 40000) {
        $queryAds = "
            SELECT a.ID_VENDOR, a.NAME, a.FK_USER FROM vendor a
                WHERE a.STATUS = 1 AND a.MODERATED = 1
                ORDER BY a.CREATED DESC
                LIMIT ".(int)$queryCount." OFFSET ".(int)$offset;
        return $this->updateSitemapByQuery(
            "vendors_detail", "view_user_vendor,{urllabel(NAME)},{FK_USER}",
            $queryAds, $queryCount, $fileIndex, $offset
        );
    }
}