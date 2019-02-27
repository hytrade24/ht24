<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 08.09.14
 * Time: 16:04
 */

class Api_Plugins_VendorHomepage_Plugin extends Api_TraderApiPlugin {

    /**
     * Hier die idents der Seiten eintragen, die zum Benutzerprofil gehören. Syntax:
     *      "ident" => indexOfUserId
     * Wobei indexOfUserId der index des Parameters (in der URL) ist, in dem die ID des Benutzers steht.
     * @var array   $arVendorPages
     */
    private static $arUserPages = array(
        "view_vendor"           => 2,
        "view_user_vendor"      => 2,
        "view_user"             => 2,
        "view_user_news"        => 2,
        "view_user_jobs"        => 2,
        "view_user_events"      => 2,
        "view_user_contacts"    => 2,
        "view_user_rating"      => 2,
        "view_user_impressum"   => 2,
        "view_user_clubs"       => 2
    );
    private static $arUserPageVars = array(
        "view_vendor"           => array("active_vendor" => 1),
        "view_user_vendor"      => array("active_vendor" => 1),
        "view_user"             => array("active_shop" => 1),
        "view_user_news"        => array("active_news" => 1),
        "view_user_jobs"        => array("active_jobs" => 1),
        "view_user_events"      => array("active_events" => 1),
        "view_user_contacts"    => array("active_contact" => 1),
        "view_user_rating"      => array("active_rating" => 1),
        "view_user_impressum"   => array("active_impressum" => 1),
        "view_user_clubs"       => array("active_club" => 1)
    );
    private static $arUserPageTpls = array(
        "view_vendor"           => "vendor_homepage_view_vendor",
        "view_user_vendor"      => "vendor_homepage_view_vendor",
        "view_user"             => "vendor_homepage_view_user",
        "view_user_news"        => "vendor_homepage_view_user_news",
        "view_user_jobs"        => "vendor_homepage_view_user_jobs",
        "view_user_events"      => "vendor_homepage_view_user_events",
        "view_user_contacts"    => "vendor_homepage_view_user_contacts",
        "view_user_rating"      => "vendor_homepage_view_user_rating",
        "view_user_impressum"   => "vendor_homepage_view_user_impressum",
        "view_user_clubs"       => "vendor_homepage_view_user_clubs"
    );
    private static $arUserPageUrls = array(
        "view_user_vendor"      => "/index.htm",
        "view_vendor"           => "/index.htm",
        "view_user"             => "/shop.htm",
        "view_user_news"        => "/news.htm",
        "view_user_jobs"        => "/jobs.htm",
        "view_user_events"      => "/events.htm",
        "view_user_contacts"    => "/contacts.htm",
        "view_user_rating"      => "/rating.htm",
        "view_user_impressum"   => "/impressum.htm",
        "view_user_clubs"       => "/clubs.htm"
    );
    
    private $domains = false;
    private $homepageId = false;
    private $homepageUserId = false;
    private $homepageUserVars = array();
    private $homepageUserIndexParams = false;

    /**
     * Defines the priority of the Plugin. (Higher number = more important)
     * @return int
     */
    static public function getPriority() {
        return 10;
    }
    
    /**
     * Register the events needed by your plugin within this function.
     * @return bool     False for error, plugin will not be loaded if an error occurs
     */
    public function registerEvents() {
        // Membership events
        $this->registerEvent( Api_TraderApiEvents::MEMBERSHIP_CHANGED, "membershipChanged");
        $this->registerEvent( Api_TraderApiEvents::MEMBERSHIP_OTHER_FEATURES, "membershipOtherFeatures");
        $this->registerEvent( Api_TraderApiEvents::MEMBERSHIP_OTHER_FEATURES_ADMIN, "membershipOtherFeaturesAdmin");
        // Template events
        $this->registerEvent( Api_TraderApiEvents::TEMPLATE_SETUP_FRAME, "templateSetupFrame");
        $this->registerEvent( Api_TraderApiEvents::TEMPLATE_SETUP_CONTENT, "templateSetupContent");
        // Url events
        $this->registerEvent( Api_TraderApiEvents::URL_OUTPUT, "urlOutput" );
        $this->registerEvent( Api_TraderApiEvents::URL_PROCESS_PAGE, "urlProcessPage" );
        // Steps
        $this->registerEvent( Api_TraderApiEvents::STEPS_GENERAL_INIT, "stepsGeneralInit" );
        $this->registerEvent( Api_TraderApiEvents::STEPS_GENERAL_DB_LOAD, "stepsGeneralDbLoad" );
        $this->registerEvent( Api_TraderApiEvents::STEPS_GENERAL_DB_SAVE, "stepsGeneralDbSave" );
        $this->registerEvent( Api_TraderApiEvents::STEPS_GENERAL_SCRIPTS, "stepsGeneralScripts" );
        $this->registerEvent( Api_TraderApiEvents::STEPS_GENERAL_RENDER, "stepsGeneralRender" );
        $this->registerEvent( Api_TraderApiEvents::STEPS_GENERAL_SUBMIT, "stepsGeneralSubmit" );
        // Custom events
        $this->registerEvent( "VENDOR_HOMEPAGE_PLUGIN_CACHE", "clearDomainCache" );
        $this->registerEvent( "VENDOR_HOMEPAGE_PLUGIN_CACHE_USER", "clearUserCache" );
        $this->registerEvent( "VENDOR_HOMEPAGE_PLUGIN_SAVE_USER_CSS", "saveUserCssEvent" );
        $this->registerEvent( "VENDOR_HOMEPAGE_PLUGIN_SAVE_USER_FOOTER", "saveUserFooterEvent" );
        return true;
    }
    
    private function cacheDomains() {
        $arDomains = Api_VendorHomepageManagement::getInstance($GLOBALS['db'])->fetchAll(array("ACTIVE" => 1));
        foreach ($arDomains as $domainIndex => $domainData) {
            $arDomains[$domainIndex]["DOMAIN_SUB"] = strtolower($domainData["DOMAIN_SUB"]);
            $arDomains[$domainIndex]["DOMAIN_FULL"] = strtolower($domainData["DOMAIN_FULL"]);
        }

        return $this->utilWriteCacheFile("domains.php", "<?php return ".php_dump($arDomains)."; ?>");
    }

    private function getDomains() {
        if (!is_array($this->domains)) {
            $cacheFile = $this->utilGetCacheFileAbsolute("domains.php");
            if (!file_exists($cacheFile)) {
                if (!$this->cacheDomains()) {
                    return false;
                }
            }
            $this->domains = include $cacheFile;
        }
        return $this->domains;
    }
    
    public function clearDomainCache() {
        $cacheFile = $this->utilGetCacheFileAbsolute("domains.php");
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
        return true;
    }
    
    public function clearUserCache($userId) {
        $fileCacheHeader = $this->utilGetCacheFileAbsolute("user.".$userId.".header.htm");
        if (file_exists($fileCacheHeader)) {
            unlink($fileCacheHeader);
        }
        return true;
    }

    public function readUserCss($userId) {
        return $this->utilReadCacheFile("user.".$userId.".css");
    }

    public function saveUserCss($userId, $css) {
        $fileCacheUserCss = $this->utilGetCacheFileAbsolute("user.".(int)$userId.".css");
        $cssContent = trim($css);
        if($cssContent != '') {
            // create or update css file
            file_put_contents($fileCacheUserCss, $cssContent);
        } else {
            // delete css file
            unlink($fileCacheUserCss);
        }
    }

    public function saveUserCssEvent(Api_Entities_EventParamContainer $params) {
        $this->saveUserCss($params->getParam("ID_USER"), $params->getParam("USER_CSS"));
    }

    public function readUserFooter($userId, $s_lang = null) {
        if ($s_lang === null) {
            $s_lang = $GLOBALS["s_lang"];
        }
        $result = $this->utilReadCacheFile("user.".$userId.".footer.htm");
        if ($result === false) {
            $tplFooterDefault = new Template("tpl/".$s_lang."/vendor_homepage_footer_default.htm");
            $result = $tplFooterDefault->process();
        }
        return $result;
    }

    public function saveUserFooter($userId, $footer) {
        $fileCacheUserFooter = $this->utilGetCacheFileAbsolute("user.".(int)$userId.".footer.htm");
        $footerContent = trim($footer);
        if($footerContent != '') {
            // create or update footer cachefile
            file_put_contents($fileCacheUserFooter, $footerContent);
        } else {
            // delete footer cachefile
            unlink($fileCacheUserFooter);
        }
    }

    public function saveUserFooterEvent(Api_Entities_EventParamContainer $params) {
        $this->saveUserFooter($params->getParam("ID_USER"), $params->getParam("USER_FOOTER"));
    }
    
    public function stepsGeneralInit(Api_Entities_EventParamContainer $params) {
        if ($params->getParam("ident") == "vendorCreateSteps") {
            /** @var Form_Steps_Vendor $vendorSteps */
            $vendorSteps = $params->getParam("stepsObject");
            
            $vendorSteps->addStep(
                "homepage", 
                Translation::readTranslation("marketplace", "vendor.step.homepage", null, array(), "Homepage"), 
                $this->utilGetTemplate("step-homepage.htm")
            );
        }
    }
    
    public function stepsGeneralDbLoad(Api_Entities_EventParamContainer $params) {
        if ($params->getParam("ident") == "vendorCreateSteps") {
            /** @var Form_Steps_Vendor $vendorSteps */
            $vendorSteps = $params->getParam("stepsObject");
            /** @var array $arData */
            $arData = $params->getParam("data");
            
            $vendorHomepageManagement = Api_VendorHomepageManagement::getInstance($this->db);
            $vendorHomepage = $vendorHomepageManagement->fetchOneAsObject(array("FK_USER" => $vendorSteps->getUserId()));
            
            $arHomepage = array();
            if ($vendorHomepage !== false) {
                $arHomepage = $vendorHomepage->asArray(true);
            }
            
            $params->setParamArrayValue("data", "HOMEPAGE", $arHomepage);
            $params->setParamArrayValue("data", "HOMEPAGE_CSS", $this->readUserCss($vendorSteps->getUserId()));
            $params->setParamArrayValue("data", "HOMEPAGE_FOOTER", $this->readUserFooter($vendorSteps->getUserId()));
            
            require_once $GLOBALS["ab_path"]."sys/lib.user_media.php";
            $userMedia = new UserMediaManagement($this->db, "vendor_homepage", $vendorSteps->getUserId());
            if (!empty($arHomepage)) {
                $userMedia->loadFromDatabase((int)$arHomepage["ID_VENDOR_HOMEPAGE"]);
            }
            $arImages = $userMedia->getImages();
            foreach ($arImages as $imageIndex => $imageDetails) {
                $imageFile = pathinfo($imageDetails["SRC"]);
                $arImages[$imageIndex]["FILENAME"] = $imageFile['basename'];
            }
            $params->setParamArrayValue("data", "HOMEPAGE_IMAGES", $arImages);
        }
    }
    
    public function stepsGeneralDbSave(Api_Entities_EventParamContainer $params) {
        if ($params->getParam("ident") == "vendorCreateSteps") {
            /** @var Form_Steps_Vendor $vendorSteps */
            $vendorSteps = $params->getParam("stepsObject");
            /** @var array $arData */
            $arData = $params->getParam("data");
            
            $vendorHomepageManagement = Api_VendorHomepageManagement::getInstance($this->db);
            $vendorHomepage = $vendorHomepageManagement->fetchOneAsObject(array("FK_USER" => $vendorSteps->getUserId()));
            
            if (!empty($arData["HOMEPAGE"])) {
                if ($vendorHomepage !== false) {
                    // Update existing
                    $vendorHomepage->setActive( array_key_exists("ACTIVE", $arData["HOMEPAGE"]) ? $arData["HOMEPAGE"]["ACTIVE"] : 0 );
                    $vendorHomepage->setDomainSub( array_key_exists("DOMAIN_SUB", $arData["HOMEPAGE"]) ? $arData["HOMEPAGE"]["DOMAIN_SUB"] : null );
                    $vendorHomepage->setDomainFull( array_key_exists("DOMAIN_FULL", $arData["HOMEPAGE"]) ? $arData["HOMEPAGE"]["DOMAIN_FULL"] : null );
                    $vendorHomepage->updateDatabase();
                } else {
                    // Create new
                    $vendorHomepage = $vendorHomepageManagement->createNew($arData["HOMEPAGE"]);
                }
                
                require_once $GLOBALS["ab_path"]."sys/lib.user_media.php";
                $imagePath = UserMediaManagement::getCachePath("vendor_homepage", $vendorHomepage->getId(), true)."/";
                $imageIds = array();
                foreach ($arData["HOMEPAGE_IMAGES"] as $imageIndex => $imageDetails) {
                    $imageDetails["FK"] = $vendorHomepage->getId();
                    if (is_array($imageDetails["SRC"])) {
                        $imageFilename = $imageDetails["SRC"]['file'];
                        $imageFileCache = $GLOBALS["ab_path"]."filestorage/uploads/".$imageFilename;
                        $imageFileLive = $imagePath.$imageFilename;
                        rename($imageFileCache, $imageFileLive);
                        $imageDetails['SRC'] = "/".str_replace($GLOBALS['ab_path'], "", $imageFileLive);
                        $imageDetails['SRC_THUMB'] = "/".str_replace($GLOBALS['ab_path'], "", $imageFileLive);
                    }
                    if (!array_key_exists("META", $imageDetails) || !is_array($imageDetails['META'])) {
                        $imageDetails['META'] = array();
                    }
                    $imageDetails['SER_META'] = serialize($imageDetails['META']);
                    $imageDetails['ID_MEDIA_IMAGE'] = $this->db->update("media_image", $imageDetails);
                    if ($imageDetails['ID_MEDIA_IMAGE'] > 0) {
                        $imageIds[] = (int)$imageDetails['ID_MEDIA_IMAGE'];
                    }
                }
                $imagesDeleted = $this->db->fetch_table("
                    SELECT * FROM `media_image`
                    WHERE `TABLE`='vendor_homepage' AND FK=".(int)$vendorHomepage->getId()."
                        ".(!empty($imageIds) ? "AND ID_MEDIA_IMAGE NOT IN (".implode(", ", $imageIds).")" : ""));
                if (!empty($imagesDeleted)) {
                    $imageIdsDeleted = array();
                    foreach ($imagesDeleted as $imageDetails) {
                        $imageDeletedSrc = $GLOBALS["ab_path"].$imageDetails["SRC"];
                        if (file_exists($imageDeletedSrc)) {
                            unlink($imageDeletedSrc);
                        }
                        $imageDeletedSrcThumb = $GLOBALS["ab_path"].$imageDetails["SRC_THUMB"];
                        if (file_exists($imageDeletedSrcThumb)) {
                            unlink($imageDeletedSrcThumb);
                        }
                        $imageIdsDeleted[] = (int)$imageDetails["ID_MEDIA_IMAGE"];
                    }
                    $this->db->querynow("DELETE FROM `media_image` WHERE ID_MEDIA_IMAGE IN (".implode(", ", $imageIdsDeleted).")");
                }
                
                $this->saveUserCss($vendorSteps->getUserId(), $arData["HOMEPAGE_CSS"]);
                $this->saveUserFooter($vendorSteps->getUserId(), $arData["HOMEPAGE_FOOTER"]);
            }
        }
    }
    
    public function stepsGeneralScripts(Api_Entities_EventParamContainer $params) {
        if ($params->getParam("ident") == "vendorCreateSteps") {
            /** @var Form_Steps_Vendor $vendorSteps */
            $vendorSteps = $params->getParam("stepsObject");
            /** @var string $scripts */
            $scripts = $params->getParam("scripts");
                
            $scripts .= $this->utilGetTemplate("step-homepage-scripts.htm")->process();
            
            $params->setParam("scripts", $scripts);
        }
    }
    
    public function stepsGeneralRender(Api_Entities_EventParamContainer $params) {
        if ($params->getParam("ident") == "vendorCreateSteps") {
            /** @var array $arStep */
            $arStep = $params->getParam("step");
            if ($arStep["IDENT"] == "homepage") {
                /** @var Form_Steps_Vendor $vendorSteps */
                $vendorSteps = $params->getParam("stepsObject");
                /** @var array $arData */
                $arData = $params->getParam("data");
                /** @var Template $tplContent */
                $tplContent = $params->getParam("templateContent");
                
                #die(var_dump($arData["HOMEPAGE"]));
                $tplContent->addvars($arData["HOMEPAGE"], "HOMEPAGE_");
                $tplContent->addvar("USER_CSS", $arData["HOMEPAGE_CSS"]);
                $tplContent->addvar("USER_FOOTER", $arData["HOMEPAGE_FOOTER"]);
                $tplContent->addvar("MARKETPLACE_HOST", rtrim(str_replace(array("http://www.", "http://"), "", $GLOBALS["nar_systemsettings"]['SITE']['SITEURL']), "/"));
                
                $arImages = $params->getParamArrayValue("data", "HOMEPAGE_IMAGES");
                foreach ($arImages as $imageIndex => $imageDetail) {
                    $imageFileSize = 0;
                    if (is_array($imageDetail["SRC"])) {
                        $imageFile = $GLOBALS["ab_path"]."filestorage/uploads/".$imageDetail["SRC"]["file"];
                        $imageFileSize = filesize($imageFile);
                        $arImages[$imageIndex]["SRC"] = "data:".$imageDetail["SRC"]["type"].";base64,".base64_encode(file_get_contents($imageFile));
                    } else {
                        $imageFile = $GLOBALS["ab_path"].$imageDetail["SRC"];
                        $imageFileSize = filesize($imageFile);
                        $arImages[$imageIndex]["SRC"] = $tplContent->tpl_uri_baseurl($imageDetail["SRC"]);
                    }
                    $arImages[$imageIndex]["FILESIZE"] = $imageFileSize;
                }
                $tplContent->addvar("IMAGES", $this->utilGetTemplateList("step-homepage.image.htm", $arImages));
            }
        }
    }
    
    public function stepsGeneralSubmit(Api_Entities_EventParamContainer $params) {
        if ($params->getParam("ident") == "vendorCreateSteps") {
            /** @var array $arStep */
            $arStep = $params->getParam("step");
            if ($arStep["IDENT"] == "homepage") {
                /** @var Form_Steps_Vendor $vendorSteps */
                $vendorSteps = $params->getParam("stepsObject");
                /** @var array $arData */
                $arData = $params->getParam("dataRequest");
                /** @var array $arData */
                $arFiles = $params->getParam("files");
                /** @var array $arData */
                $arResponse = $params->getParam("response");

                if (array_key_exists("file", $arFiles)) {
                    $uploadSuccessful = false;
                    $uploadIsDefault = false;
                    $uploadIndex = null;
                    if ($arFiles["error"] == 0) {
                        $uploadSuccessful = true;
                        $uploadCache = new Cache_Upload($GLOBALS["ab_path"] . "filestorage/uploads");
                        $uploadFile = $uploadCache->addFileUpload($arFiles["file"]["tmp_name"], $arFiles["file"]["name"]);
                        $arImages = $params->getParamArrayValue("data", "HOMEPAGE_IMAGES");
                        $uploadIsDefault = (empty($arImages) ? 1 : 0);
                        $uploadIndex = count($arImages);
                        $arImages[] = array(
                            "TABLE" => "vendor_homepage",
                            "FK" => null,
                            "IS_DEFAULT" => $uploadIsDefault,
                            "FILENAME" => $arFiles["file"]["name"],
                            "SRC" => array(
                                "file" => $uploadFile,
                                "type" => $arFiles["file"]["type"]
                            ),
                            "SRC_THUMB" => null,
                            "SER_META" => null
                        );
                        $params->setParamArrayValue("data", "HOMEPAGE_IMAGES", $arImages);
                    }
                    $arResponse = array(
                        "success" => $uploadSuccessful, "index" => $uploadIndex, "isDefault" => $uploadIsDefault
                    );
                    $params->setParam("response", $arResponse);
                    return true;
                }
                if (array_key_exists("homepage", $arData)) {
                    switch ($arData["homepage"]) {
                        case "update":
                            // TODO: Update details
                        case "request":
                            if (array_key_exists("DOMAIN_SUB", $arData) && preg_match("/^[a-z0-9]+[a-z0-9-]*[a-z0-9]+$/i", $_POST["DOMAIN_SUB"])) {
                                $arHomepage = $params->getParamArrayValue("data", "HOMEPAGE");
                                $arHomepage["ACTIVE"] = 0;
                                $arHomepage["DOMAIN_TYPE"] = "SUBDOMAIN";
                                $arHomepage["DOMAIN_SUB"] = $arData["DOMAIN_SUB"];
                                $arHomepage["FK_USER"] = $vendorSteps->getUserId();
                                $params->setParamArrayValue("data", "HOMEPAGE", $arHomepage);
                            } else {
                                $arResponse["ERRORS"]["DOMAIN_SUB"] = Translation::readTranslation(
                                    "marketplace", "vendor.homepage.error.invalid.subdomain", null, array(),
                                    "Name der Subdomain: Muss mindestens 2 Zeichen lang sein und darf nur aus Buchstaben, Zahlen und Bindestrichen bestehen. " .
                                    "Es darf sich kein Bindestrich am Anfang oder Ende des Namens befinden!"
                                );
                                $params->setParam("continue", false);
                            }
                            $arResponse["STEP_NEXT"] = "homepage";
                            break;
                        case "imageDelete":
                            $imageFile = $arData["image"];
                            $imageFound = false;
                            $arImages = $params->getParamArrayValue("data", "HOMEPAGE_IMAGES");
                            foreach ($arImages as $imageIndex => $imageDetails) {
                                if ($imageDetails["FILENAME"] == $imageFile) {
                                    array_splice($arImages, $imageIndex, 1);
                                    $imageFound = true;
                                    break;
                                }
                            }
                            $params->setParamArrayValue("data", "HOMEPAGE_IMAGES", $arImages);
                            $arResponse = array("success" => false);
                            if ($imageFound) {
                                $arResponse = array("success" => true, "images" => array());
                                foreach ($arImages as $imageIndex => $imageDetails) {
                                    $arResponse["images"][$imageDetails["FILENAME"]] = $imageIndex;
                                }
                            }
                            break;
                        case "imageDefault":
                            $imageFile = $arData["image"];
                            $imageFound = false;
                            $arImages = $params->getParamArrayValue("data", "HOMEPAGE_IMAGES");
                            foreach ($arImages as $imageIndex => $imageDetails) {
                                if ($imageDetails["FILENAME"] == $imageFile) {
                                    $arImages[$imageIndex]["IS_DEFAULT"] = 1;
                                    $imageFound = true;
                                } else {
                                    $arImages[$imageIndex]["IS_DEFAULT"] = 0;
                                }
                            }
                            if ($imageFound) {
                                $params->setParamArrayValue("data", "HOMEPAGE_IMAGES", $arImages);
                            }
                            $arResponse = array("success" => $imageFound);
                            break;
                    }
                } else {
                    if (array_key_exists("META", $arData) && array_key_exists("IMAGES", $arData["META"])) {
                        $arImages = $params->getParamArrayValue("data", "HOMEPAGE_IMAGES");
                        foreach ($arData["META"]["IMAGES"] as $imageIndex => $imageMeta) {
                            if (array_key_exists($imageIndex, $arImages)) {
                                $arImages[$imageIndex]["META"] = $imageMeta;
                            }
                        }
                        $params->setParamArrayValue("data", "HOMEPAGE_IMAGES", $arImages);
                    }
                    if (array_key_exists("USER_CSS", $arData)) {
                        $params->setParamArrayValue("data", "HOMEPAGE_CSS", $arData["USER_CSS"]);
                    }
                    if (array_key_exists("USER_FOOTER", $arData)) {
                        $params->setParamArrayValue("data", "HOMEPAGE_FOOTER", $arData["USER_FOOTER"]);
                    }
                    $params->setParam("continue", true);
                }
                $params->setParam("response", $arResponse);
            }
        }
    }

    public function membershipChanged(Api_Entities_EventParamContainer $params) {
        global $db;
        $userId = $params->getParam("user");
        $membershipTo = $params->getParam("to");
        if ($membershipTo === NULL) {
            // Disable homepages that are active
            $db->querynow("UPDATE `vendor_homepage` SET ACTIVE=3 WHERE FK_USER=".(int)$userId." AND ACTIVE=1");
        } else {
            $membershipOptions = $membershipTo->getPacketOptions();
            if (array_key_exists("vendorHomepage", $membershipOptions) && $membershipOptions["vendorHomepage"]["AVAILABLE"]) {
                // Enable homepages that were disabled due unavailability
                $db->querynow("UPDATE `vendor_homepage` SET ACTIVE=1 WHERE FK_USER=".(int)$userId." AND ACTIVE=3");
            } else {
                // Disable homepages that are active
                $db->querynow("UPDATE `vendor_homepage` SET ACTIVE=3 WHERE FK_USER=".(int)$userId." AND ACTIVE=1");
            }
        }
        $this->clearDomainCache();
    }

    public function membershipOtherFeatures(Api_Entities_MembershipFeatures $features) {
        // TODO: Check if usergroup has this feature
        $features->addFeatureRegister("Homepage", "vendorHomepage", Api_Entities_MembershipFeatures::COLUMN_TYPE_CUSTOM, $this->utilGetTemplateRaw("register.feature.htm"));
    }

    public function membershipOtherFeaturesAdmin(Api_Entities_MembershipFeatures $features) {
        // TODO: Check if usergroup has this feature
        $features->addFeatureAdmin("Homepage", "vendorHomepage", $this->utilGetTemplate("admin.feature.htm"));
    }
    
    protected function utilShowUserContact($contactSetting, $userIdViewing, $userIdTarget) {
        switch($contactSetting)
       	{
       		case 'ALL':
       			return 1;
       		case 'USER':
                return ($userIdViewing>0 ? 1 : 0);
       		case 'CONTACT':
                $data = $GLOBALS['db']->fetch_atom("select status from user_contact where ((FK_USER_A = '".$userIdViewing."' AND FK_USER_B = '".$userIdTarget."') OR (FK_USER_A = '".$userIdTarget."' AND FK_USER_B = '".$userIdViewing."'))");
                return ($data==1 ? 1 : 0);
       		default:
       			break;
       	}
        return 0;
    }

    public function urlOutputUser(Api_Entities_URL &$url, $userId) {
        foreach ($this->getDomains() as $domainIndex => $arDomain) {
            if ($arDomain["FK_USER"] == $userId) {
                if (!empty($arDomain["DOMAIN_SUB"])) {
                    $marketplaceHost = rtrim(str_replace(array("http://www.", "http://"), "", $GLOBALS["nar_systemsettings"]['SITE']['SITEURL']), "/");
                    $url->setHost($arDomain["DOMAIN_SUB"].".".$marketplaceHost);
                    $url->setSecure(false);
                    return true;
                } else if (!empty($arDomain["DOMAIN_FULL"])) {
                    $url->setHost($arDomain["DOMAIN_FULL"]);
                    return true;
                }
            }
        }
        return false;
    }

    public function urlOutput(Api_Entities_URL $url) {
        if ($this->homepageUserId > 0) {
            $pageIdent = $url->getPageIdent();
            if ($pageIdent !== false) {
                if (array_key_exists($pageIdent, self::$arUserPageUrls)) {
                    $params = false;
                    $paramPos = strpos($url->getPath(), ",");
                    $urlPathFull = rtrim($GLOBALS["nar_systemsettings"]['SITE']['BASE_URL'], "/") . "/";
                    $urlPathUser = ltrim(self::$arUserPageUrls[$pageIdent], "/");
                    if ($paramPos !== false) {
                        $params = substr($url->getPath(), $paramPos);
                        if ($this->homepageUserIndexParams[0] == $pageIdent) {
                            // Is index page, check for default params
                            $paramsShort = strstr($params, ".", true);
                            $paramsShort = substr($paramsShort === false ? $params : $paramsShort, 1);
                            $paramsIndex = implode(",", array_slice($this->homepageUserIndexParams, 1));
                            if ($paramsShort !== $paramsIndex) {
                                $urlPathUser = strstr($urlPathUser, ".", true).$params;
                            }
                        } else {
                            $urlPathUser = strstr($urlPathUser, ".", true).$params;
                        }
                    } else {
                        $arParams = $url->getPageParameters();
                        if (!empty($arParams)) {
                            $urlPathUser = strstr($urlPathUser, ".", true).",".implode(",", $arParams).".htm";
                        }
                    }
                    $urlPathFull .= $urlPathUser;
                    $url->setPath( $urlPathFull );
                }
                if (array_key_exists($pageIdent, self::$arUserPages)) {
                    $arParams = $url->getPageParameters();
                    $userIdParamIndex = self::$arUserPages[$pageIdent];
                    if (count($arParams) >= $userIdParamIndex) {
                        $userId = (int)$arParams[$userIdParamIndex-1];
                        if ($userId == $this->homepageUserId) {
                            return $this->urlOutputUser($url, $userId);
                        }
                    }
                }
            } else {
                $cachePath = rtrim($GLOBALS["nar_systemsettings"]['SITE']['BASE_URL'], "/")."/cache/";
                if (strpos($url->getPath(), $cachePath) === 0) {
                    // URL to some cache file. Keep current domain.
                    return $this->urlOutputUser($url, $this->homepageUserId);
                }
            }
        }
        return false;
    }

    public function urlProcessPage(Api_Entities_URL $url) {
        if (strpos($url->getPath(), "index.php") !== false) {
            // Ignore direct calls to index.php
            return false;
        }
        $marketplaceHost = rtrim(str_replace(array("http://www.", "http://"), "", $GLOBALS["nar_systemsettings"]['SITE']['SITEURL']), "/");
        foreach ($this->getDomains() as $domainIndex => $arDomain) {
            if (!empty($arDomain["DOMAIN_SUB"]) && ($url->getHost() != $arDomain["DOMAIN_SUB"].".".$marketplaceHost)) {
                // Host does not match subdomain!
                continue;
            }
            if (!empty($arDomain["DOMAIN_FULL"]) && ($url->getHost() != $arDomain["DOMAIN_FULL"])) {
                // Host does not match subdomain!
                continue;
            }
            $this->homepageId = $arDomain["ID_VENDOR_HOMEPAGE"];
            $this->homepageUserId = $arDomain["FK_USER"];
            $this->homepageUserIndexParams = array("view_user_vendor", $GLOBALS['db']->fetch_atom("SELECT NAME FROM `user` WHERE ID_USER=".$this->homepageUserId), $this->homepageUserId);
            $urlUserIndex = 0;
            foreach (self::$arUserPageUrls as $urlIdent => $urlUserPathRel) {
                $urlUserPath = rtrim($GLOBALS["nar_systemsettings"]['SITE']['BASE_URL'], "/") . "/" . ltrim(strstr($urlUserPathRel, ".", true), "/");
                if (strpos($url->getPath(), $urlUserPath) === 0) {
                    $url->setPageIdent($urlIdent);
                    $url->setPageAlias($urlIdent);
                    if (count($url->getPageParameters()) <= 1) {
                        if ($this->homepageUserIndexParams[0] == $urlIdent) {
                            // Is index page (first defined user url)
                            $url->setPageIdent("view_vendor");
                            $url->setPageParameters($this->homepageUserIndexParams);
                            $url->setPageCustom(true);
                        } else {
                            // Is regular page called without parameters
                            $url->setPageParameters(array($urlIdent));
                            $url->setPageCustom(true);
                        }
                    }
                    break;
                }
                $urlUserIndex++;
            }
            if (array_key_exists($url->getPageIdent(), self::$arUserPageVars)) {
                $this->homepageUserVars = self::$arUserPageVars[ $url->getPageIdent() ];
                $url->setPageCustom(true);
            } else {
                $url->setPath("/index.htm");
                $url->setPageIdent("view_vendor");
                $url->setPageParameters($this->homepageUserIndexParams);
                $url->setPageParametersOptional(array());
                $url->setPageCustom(true);
                $this->homepageUserVars = array();
            }
            return true; // Consume event
        }
        return false;
    }
    
    public function templateSetupFrame(Api_Entities_EventParamContainer $params) {
        if ($this->homepageUserId > 0) {
            global $ab_path, $db, $langval, $s_lang;
            $params->setParam("name", "vendor");
            $params->setParam("layout", "");
            $arVariables = $params->getParam("variables");
            // Hide contact links within article rows
            $arVariables["HIDE_AD_ROW_CONTACT_BUTTON"] = 1;

            // User CSS
            $fileCacheUserCss = $this->utilGetCacheFileAbsolute("user.".$this->homepageUserId.".css");
            if (file_exists($fileCacheUserCss)) {
                $arVariables["USER_CSS_ISSET"] = true;
                $arVariables["USER_CSS_CHANGED"] = filemtime($fileCacheUserCss);
            } else {
                $arVariables["USER_CSS_ISSET"] = false;
            }

            // Check header image
            $fileCacheHeader = $this->utilGetCacheFileAbsolute("user.".$this->homepageUserId.".header.htm");
            if (!file_exists($fileCacheHeader)) {
                require_once $ab_path."sys/lib.user_media.php";
               	$userMedia = new UserMediaManagement($db, "vendor_homepage");
                $userMedia->loadFromDatabase($this->homepageId);
                $arImages = $userMedia->getImages();
                $arImageHeader = false;
                foreach ($arImages as $imageIndex => $arImage) {
                    if (array_key_exists("META_POSITION", $arImage) && ($arImage["META_POSITION"] == 1)) {
                        $arImageHeader = $arImage;
                        break;
                    }
                }
                $tplHeader = $this->utilGetTemplate("homepage.header.htm");
                if ($arImageHeader !== false) {
                    $tplHeader->addvars($arImageHeader);
                }
                file_put_contents($fileCacheHeader, $tplHeader->process());
            }
            $arVariables["VENDOR_HEADER"] = file_get_contents($fileCacheHeader);

            // User Footer
            $arVariables["USER_FOOTER"] = $this->readUserFooter($this->homepageUserId);

            // Set user variables
            $data = $db->fetch1("
           		select
           			u.VORNAME as USER_VORNAME,
           			u.NACHNAME as USER_NACHNAME,
           			u.NAME as USER_NAME,
           			u.FIRMA as USER_FIRMA,
           			u.CACHE as USER_CACHE,
           			u.STAMP_REG as USER_STAMP_REG,
           			u.LASTACTIV as USER_LASTACTIV,
           			u.URL as USER_URL,
           			u.STRASSE as USER_STRASSE ,
           			u.PLZ as USER_PLZ,
           			u.ORT as USER_ORT,
           			u.ID_USER as USER_ID_USER,
           			u.UEBER as USER_UEBER,
           			ROUND(u.RATING) as USER_lastrate,
           			TIMESTAMPDIFF(YEAR,u.GEBDAT,CURDATE()) as USER_age,
           			u.TEL as USER_TEL,
           			u.TOP_USER as USER_TOP_USER,
           			u.TOP_SELLER AS USER_TOP_SELLER,
           			u.PROOFED AS USER_PROOFED
           		from user u
           		where u.ID_USER=". $this->homepageUserId); // Userdaten lesen
           	include ($GLOBALS['nar_systemsettings']['SITE']['USER_PATH'].$data['USER_CACHE']."/".$this->homepageUserId."/useroptions.php");
            $arVariables["showcontact"] = $this->utilShowUserContact($useroptions['LU_SHOWCONTAC'], $GLOBALS['uid'], $this->homepageUserId);
           	$arVariables["USER_ALLOW_CONTACS"] = $useroptions['ALLOW_CONTACS'];
           	$arVariables["USER_ALLOW_ADD_USER_CONTACT"] = $useroptions['ALLOW_ADD_USER_CONTACT'];
           	$arVariables["USER_SHOW_STATUS_USER_ONLINE"] = $useroptions['SHOW_STATUS_USER_ONLINE'];
           	$arVariables["VENDOR_ALLOW_CONTACS"] = $useroptions['ALLOW_CONTACS'];
           	$arVariables["VENDOR_ALLOW_ADD_USER_CONTACT"] = $useroptions['ALLOW_ADD_USER_CONTACT'];
           	$arVariables["VENDOR_SHOW_STATUS_USER_ONLINE"] = $useroptions['SHOW_STATUS_USER_ONLINE'];
            require_once 'sys/lib.ad_rating.php';
           	$adRatingManagement = AdRatingManagement::getInstance($db);
           	$arVariables["rating_avg"] = $adRatingManagement->getRatingByUserId($this->homepageUserId);
            $arVariables['newstitle'] = $data['USER_NAME'];
           	if($data['USER_ID_USER'] != $GLOBALS['uid']) {
           		$res = $db->querynow("update user_views set `VIEWS`=`VIEWS`+1 where
           		    FK_USER=".$this->homepageUserId." and STAMP=CURDATE()");
           		if(!$res['int_result'])
           		$res = $db->querynow("insert into user_views set `VIEWS`=1, FK_USER=".$data['USER_ID_USER'].", STAMP=CURDATE()");
           	} // nicht der eigene user
           	$arVariables["UID"] = $GLOBALS['uid'];
            $arVariables = array_merge($arVariables, $this->homepageUserVars, $data);
            // Set vendor variables
            require_once 'sys/lib.vendor.php';
            require_once 'sys/lib.vendor.category.php';
            $vendorManagement = VendorManagement::getInstance($db);
            $vendorCategoryManagement = VendorCategoryManagement::getInstance($db);
            $vendorManagement->setLangval($langval);
            $vendorCategoryManagement->setLangval($langval);

            $isUserVendor = $vendorManagement->isUserVendorByUserId($this->homepageUserId);
            $arVariables["USER_IS_VENDOR"] = $isUserVendor;

            if ($isUserVendor) {
                $tmp = $vendorManagement->fetchByUserId($this->homepageUserId);
                $vendor = $vendorManagement->fetchByVendorId($tmp['ID_VENDOR']);
                $vendorTemplate = array();
                if ($vendor == null) {
                    die();
                }
                // Template aufbereiten
                foreach ($vendor as $key => $value) {
                    $vendorTemplate['VENDOR_' . $key] = $value;
                }
                if ($vendorTemplate['VENDOR_CHANGED'] == '0000-00-00 00:00:00') {
                    $vendorTemplate['VENDOR_CHANGED'] = 0;
                }
                // Kategorie Liste
                $categories = $vendorCategoryManagement->fetchAllVendorCategoriesByVendorId($vendor['ID_VENDOR']);
                $tpl_categories = new Template($ab_path . "tpl/" . $s_lang . "/vendor.row.categories.htm");
                $tpl_categories->addlist("categories", $categories, $ab_path . 'tpl/' . $s_lang . '/vendor.row.categories.row.htm');
                $vendorTemplate['VENDOR_CATEGORIES'] = $tpl_categories->process();
                $vendorTemplate['VENDOR_LOGO'] = ($vendorTemplate['VENDOR_LOGO'] != "") ? 'cache/vendor/logo/' . $vendorTemplate['VENDOR_LOGO'] : null;
                $vendorTemplate['VENDOR_DESCRIPTION'] = $vendorManagement->fetchVendorDescriptionByLanguage($vendor['ID_VENDOR']);
                $vendorTemplate['USER_ID_USER'] = $vendor['FK_USER'];
                $arVariables = array_merge($arVariables, $vendorTemplate);
            }
            // Check if jobs available
            require_once 'sys/lib.job.php';
            $jobManagement = JobManagement::getInstance($db);
            $hasJobs = (count($jobManagement->fetchAllJobsByUserId($this->homepageUserId)) > 0);
            $arVariables["USER_HAS_JOBS"] = $hasJobs;
            // Check if clubs/groups available
            require_once $ab_path."sys/lib.club.php";
            $clubManagement = ClubManagement::getInstance($db);
            $countClubs = $clubManagement->countClubsWhereUserIsMember($this->homepageUserId);
            $arVariables["USER_HAS_CLUBS"] = ($countClubs > 0);
            $arVariables["USER_CLUB_COUNT"] = $countClubs;
            // Check if events available
            require_once $ab_path . "sys/lib.calendar_event.php";
            $calendarEventManagement = CalendarEventManagement::getInstance($db);
            $countEvents = $calendarEventManagement->countByParam(array("FK_REF_TYPE" => "user", "FK_REF" => $this->homepageUserId));
            $arVariables["USER_HAS_EVENTS"] = ($countEvents > 0);
            $arVariables["USER_EVENT_COUNT"] = $countEvents;
            $arVariables["USER_HAS_NEWS"] = $db->fetch_atom("SELECT count(*) FROM `news` WHERE FK_AUTOR=" . $this->homepageUserId . " AND OK=3");
            // Impressum Tab
            $userHasImpressum = $db->fetch_atom("SELECT (IMPRESSUM <> '') FROM usercontent WHERE FK_USER = '" . (int)$this->homepageUserId . "'");
            $arVariables["USER_HAS_IMPRESSUM"] = $userHasImpressum;
            $params->setParam("variables", $arVariables);
        }
    }
    
    public function templateSetupContent(Api_Entities_EventParamContainer $params) {
        if ($this->homepageUserId > 0) {
            if (array_key_exists($params->getParam("name"), self::$arUserPageTpls)) {
                $params->setParam( "name", self::$arUserPageTpls[$params->getParam("name")] );
            }
        }
    }
}