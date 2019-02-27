<?php

class Api_Plugins_YoutubeVideoUpload_Plugin extends Api_TraderApiPlugin {
    
    protected static $googleOAuthScopes = array('https://www.googleapis.com/auth/youtube');

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
        $this->registerEvent(Api_TraderApiEvents::ADMIN_WELCOME_ERROR, "adminWelcomeError");
        $this->registerEvent(Api_TraderApiEvents::AJAX_PLUGIN, "ajaxPlugin");
        $this->registerEvent(Api_TraderApiEvents::CRONJOB_DONE, "cronjob");
        $this->registerEvent(Api_TraderApiEvents::MARKETPLACE_AD_ENABLE, "marketplaceAdEnable");
        $this->registerEvent(Api_TraderApiEvents::MARKETPLACE_AD_DISABLE, "marketplaceAdDisable");
        $this->registerEvent(Api_TraderApiEvents::MARKETPLACE_AD_UPDATE, "marketplaceAdUpdate");
        $this->registerEvent(Api_TraderApiEvents::MARKETPLACE_AD_CREATE_SUBMIT_STEP, "marketplaceAdCreateSubmitStep");
        $this->registerEvent(Api_TraderApiEvents::MARKETPLACE_AD_CREATE_RENDER_VIDEOS, "marketplaceAdCreateRenderVideos");
        $this->registerEvent(Api_TraderApiEvents::MARKETPLACE_CATEGORY_EDIT_TEMPLATE, "marketplaceCategoryEditTemplate");
        $this->registerEvent(Api_TraderApiEvents::MARKETPLACE_CATEGORY_UPDATED, "marketplaceCategoryUpdated");
        $this->registerEvent(Api_TraderApiEvents::VENDOR_RENDER_VIDEOS, "vendorRenderVideos");
        $this->registerEvent(Api_TraderApiEvents::VIDEO_UPLOAD_INPUT, "videoUploadInput");
        return true;
    }

    /**
     * Returns the configuration form for this plugin
     * @return Template
     */
    public function getConfigurationForm() {
        // Prepare template
        $tplConfig = parent::getConfigurationForm();
        $tplConfig->addvar("OAUTH_REDIRECT_URL", $this->getGoogleAPIPlugin()->getGoogleRedirectUrl());
        $youtube = $this->getGoogleAPIYoutubeService();
        if ($youtube !== null) {
            $arLang = array("REGION" => "DE", "LOCALE" => "de_DE");   // Fallback
            $arLangList = $GLOBALS["lang_list"];
            foreach ($arLangList as $langAbbr => $langDetails) {
                if ($langAbbr == $GLOBALS["s_lang"]) {
                    $arLang = $langDetails;
                    $arLang["REGION"] = substr($arLang["LOCALE"], -2);
                }
            }
            $arCategoryList = $this->getCategoryList();
            $arCategoryOptions = array();
            foreach ($arCategoryList as $categoryIndex => $categoryDetails) {
                $isSelected = false;
                if ($this->pluginConfiguration["YOUTUBE_DEFAULT_CATEGORY"] == $categoryDetails["id"]) {
                    // Category selected
                    $isSelected = true;
                }
                $arCategoryOptions[] = array("name" => $categoryDetails["name"], "value" => $categoryDetails["id"], "selected" => $isSelected);
            }
            $tplConfig->addvar("categoryOptions", $this->utilGetTemplateList("categoryEdit.category.htm", $arCategoryOptions));
        }
        return $tplConfig;
    }
    
    /**
     * Returns some specific information about the plugins configuration
     * @param $arParams (optional) Array with parameters for this request.
     * @return mixed
     */
    public function getConfigurationAjax($arParams = array()) {
        if (array_key_exists("ajax", $_GET)) {
            switch ($_GET["ajax"]) {
                case "youtubeResetAuth":
                    $arResult = array("success" => true);
                    $clientId = $arParams["CONFIG"]["OAUTH_ID"];
                    $clientKey = $arParams["CONFIG"]["OAUTH_KEY"];
                    $accountKey = $arParams["CONFIG"]["ACCOUNT_KEY"];
                    /** @var Api_Plugins_GoogleAPI_Plugin $pluginGoogleApi */
                    $pluginGoogleApi = Api_Plugins_GoogleAPI_Plugin::getInstance($this->db);
                    $pluginGoogleApi->deauthenticateGoogleClient($clientId, $clientKey, $accountKey, self::$googleOAuthScopes);
                    break;
                case "youtubeAccountValidate":
                    header("Content-Type: application/json");
                    $arResult = array("success" => true);
                    $clientId = $this->pluginConfiguration["OAUTH_ID"] = $arParams["CONFIG"]["OAUTH_ID"];
                    $clientKey = $this->pluginConfiguration["OAUTH_KEY"] = $arParams["CONFIG"]["OAUTH_KEY"];
                    $accountKey = $this->pluginConfiguration["ACCOUNT_KEY"] = $arParams["CONFIG"]["ACCOUNT_KEY"];
                    $this->pluginConfiguration["YOUTUBE_DEFAULT_CATEGORY"] = $arParams["CONFIG"]["YOUTUBE_DEFAULT_CATEGORY"];
                    $this->saveConfiguration();
                    /** @var Api_Plugins_GoogleAPI_Plugin $pluginGoogleApi */
                    $pluginGoogleApi = Api_Plugins_GoogleAPI_Plugin::getInstance($this->db);
                    /**
                     * Check google client authentification
                     * @var Google_Client $googleClient
                     */
                    $googleAuthUrl = null;
                    $googleClient = $pluginGoogleApi->authenticateGoogleClient($clientId, $clientKey, $accountKey, self::$googleOAuthScopes, $googleAuthUrl);
                    if ($googleClient === false) {
                        if ($googleAuthUrl !== null) {
                            $tplError = $this->utilGetTemplate("error_auth_weblogin.htm");
                            $tplError->addvar("URL", $googleAuthUrl);
                            $arResult["success"] = false;
                            $arResult["error"] = $tplError->process();
                            die(json_encode($arResult));
                        } else {
                            $arResult["success"] = false;
                            $arResult["error"] = "Debug1! ".@var_export($googleClient, true);
                            die(json_encode($arResult));
                        }
                    }
                    /**
                     * Check youtube service
                     * @var Google_Service_YouTube $youtubeService
                     */
                    $uploadUrl = $this->getGoogleAPIYoutubeUploadURL(array(), $clientId, $clientKey, $accountKey, $googleClient->getAccessToken());
                    if ($uploadUrl === null) {
                        $arResult["success"] = false;
                        $arResult["error"] = "Debug2! ".@var_export($googleClient, true);
                        die(json_encode($arResult));
                    }
                    $arResult["url"] = $uploadUrl["url"];
                    $arResult["token"] = $uploadUrl["token"];
                    die(json_encode($arResult));
                    break;
            }
        }
    }
    
    public function adminWelcomeError(Api_Entities_EventParamContainer $params) {
        if (empty($this->pluginConfiguration["OAUTH_ID"]) || empty($this->pluginConfiguration["OAUTH_KEY"]) || empty($this->pluginConfiguration["ACCOUNT_KEY"])) {
            $params->setParamArrayAppend("errors", $this->utilGetTemplate("welcome-error.htm")->process());
        }
    }
    
    public function ajaxPlugin(Api_Entities_EventParamContainer $params) {
        $jsonResult = array("success" => false);
        switch ($params->getParam("action")) {
            case "delete":
                $videoIndex = $_POST["id"];
                $videoTarget = (array_key_exists("target", $_POST) ? $_POST["target"] : "ad_master");
                switch ($videoTarget) {
                    case "ad_master":
                    default:
                        require_once $GLOBALS["ab_path"] . "sys/lib.ad_create.php";
                        $adCreate = new AdCreate($this->db, ($GLOBALS["uid"] > 0 ? $GLOBALS["uid"] : null));
                        $arVideos = $adCreate->getCustomAdData("videos");
                        if (count($arVideos) > $videoIndex) {
                            $arVideo = $arVideos[$videoIndex - 1];
                            $adCreate->deleteVideo($videoIndex);
                            if (!$arVideo["ID_AD_VIDEO"]) {
                                $this->youtube_deleteVideo($arVideo["CODE"]);
                            }
                        }
                        die($adCreate->renderMediaVideos());
                    case "vendor":
                        // TODO
                        break;
                }
                break;
            case "upload":
                header("Content-Type: application/json");
                // Get current ad
                require_once $GLOBALS["ab_path"]."sys/lib.ad_create.php";
                $adCreate = new AdCreate($this->db, ($GLOBALS["uid"] > 0 ? $GLOBALS["uid"] : null));
                $categoryId = (int)$adCreate->getCustomAdData("FK_KAT");
                $categoryIdYoutube = $this->pluginConfiguration["YOUTUBE_DEFAULT_CATEGORY"];
                $arCategoryMapping = $this->getCategoryMapping();
                if (array_key_exists($categoryId, $arCategoryMapping)) {
                    $categoryIdYoutube = $arCategoryMapping[$categoryId];
                }
                // Prepare upload
                $uploadUrl = $this->getGoogleAPIYoutubeUploadURL(array(
                    "title"         => $adCreate->getCustomAdData("PRODUKTNAME"),
                    "description"   => strip_tags($adCreate->getCustomAdData("BESCHREIBUNG")),
                    "category"      => $categoryIdYoutube
                ));
                if ($uploadUrl === null) {
                    // Error getting upload URL!
                    break;
                }
                $tplTemp = new Template("tpl/de/empty.htm");
                $returnUrl = "index.php?pluginAjax=YoutubeVideoUpload&pluginAjaxAction=uploadDone";
                $jsonResult["success"] = true;
                $jsonResult["url"] = $uploadUrl["url"]."?nexturl=".urlencode($tplTemp->tpl_uri_baseurl_full($returnUrl));
                $jsonResult["token"] = $uploadUrl["token"];
                if (array_key_exists("target", $_REQUEST)) {
                    $_SESSION["YOUTUBE_PLUGIN_VIDEO_TYPE"] = $_REQUEST["target"];
                } else {
                    unset($_SESSION["YOUTUBE_PLUGIN_VIDEO_TYPE"]);
                }
                break;
            case "uploadDone":
                if ($_REQUEST["status"] == 200) {
                    $videoId = $_REQUEST["id"];
                    $videoTarget = (array_key_exists("YOUTUBE_PLUGIN_VIDEO_TYPE", $_SESSION) ? $_SESSION["YOUTUBE_PLUGIN_VIDEO_TYPE"] : "ad_master");
                    $userId = ($GLOBALS["uid"] > 0 ? $GLOBALS["uid"] : null);
                    switch ($videoTarget) {
                        case "ad_master":
                        default:
                            // Set pending
                            $this->setVideoPending($videoId);
                            // Add video
                            $arVideo = array();
                            require_once $GLOBALS["ab_path"] . "sys/lib.ad_create.php";
                            $adCreate = new AdCreate($this->db, $userId);
                            $adCreate->handleVideoUpload("http://www.youtube.com/watch?v=" . $videoId, $arVideo);
                            $jsonResult["success"] = true;
                            $jsonResult['files'] = array(array(
                                'VIDEO_INDEX' => $arVideo['INDEX'],
                                'VIDEO_TYPE' => $arVideo['TYPE'],
                                'VIDEO_ID' => $arVideo['CODE']
                            ));
                            break;
                        case "vendor":
                            require_once 'sys/lib.vendor.php';
                            require_once 'sys/lib.vendor.gallery.php';
                            $vendorManagement = VendorManagement::getInstance($this->db);
                            $vendorGalleryManagement = VendorGalleryManagement::getInstance($this->db);
                            $vendor = $vendorManagement->fetchByUserId($userId);
                            if($vendor != null) {
                                $vendorId = $vendor['ID_VENDOR'];
                            }
                            $result = $vendorGalleryManagement->insertVideo("", $videoId, $vendorId);
                            if ($result > 0) {
                                $jsonResult["success"] = true;
                                $jsonResult['files'] = array(array(
                                    'VIDEO_INDEX'   => $result,
                                    'VIDEO_ID'      => $videoId
                                ));
                            } else {
                                $jsonResult["success"] = false;
                            }
                            break;
                    }
                }
                break;
        }
        die(json_encode($jsonResult));
    }

    public function cronjob() {
        $lastCleanup = null;
        if (file_exists(__DIR__."/cleanup.lock")) {
            $lastCleanup = filemtime(__DIR__."/cleanup.lock");
        }
        if (($lastCleanup === null) || ((time() - $lastCleanup) > 3600)) {
            touch(__DIR__."/cleanup.lock");
            $this->cleanupVideosPending();
        }
    }
    
    public function marketplaceAdEnable(Api_Entities_EventParamContainer $params) {
        $articleId = (int)$params->getParam("id");
        $arVideoIds = array();
        $arArticleVideos = $this->db->fetch_nar("SELECT ID_AD_VIDEO, CODE FROM `ad_video` WHERE FK_AD=".$articleId);
        foreach ($arArticleVideos as $videoId => $videoCode) {
            if ($this->youtube_validateVideo($videoCode)) {
                $arVideoIds[] = $videoCode;
            } else {
                // Remove invalid video
                $this->db->querynow("DELETE FROM `ad_video` WHERE ID_AD_VIDEO=".$videoId);
            }
        }
        // Set videos as listed
        $this->setVideoPending($arVideoIds, false);
    }
    
    public function marketplaceAdDisable(Api_Entities_EventParamContainer $params) {
        $articleId = (int)$params->getParam("id");
        $arVideoIds = array();
        $arArticleVideos = $this->db->fetch_nar("SELECT ID_AD_VIDEO, CODE FROM `ad_video` WHERE FK_AD=".$articleId);
        foreach ($arArticleVideos as $videoId => $videoCode) {
            if ($this->youtube_validateVideo($videoCode)) {
                $arVideoIds[] = $videoCode;
            } else {
                // Remove invalid video
                $this->db->querynow("DELETE FROM `ad_video` WHERE ID_AD_VIDEO=".$videoId);
            }
        }
        // Set videos as unlisted
        $this->setVideoPending($arVideoIds, true);
    }

    public function marketplaceAdUpdate(Api_Entities_EventParamContainer $params) {
        $arArticle = $params->getParam("data");
        if (array_key_exists("videos", $arArticle) && is_array($arArticle["videos"]) && !empty($arArticle["videos"])) {
            $arVideoIds = array();
            $arVideoIdsQuery = array();
            $arVideosInvalid = array();
            foreach ($arArticle["videos"] as $videoIndex => $videoDetails) {
                $arVideoIdsQuery[] = "'".mysql_real_escape_string($videoDetails["CODE"])."'";
                if ($this->youtube_validateVideo($videoDetails["CODE"])) {
                    $arVideoIds[] = $videoDetails["CODE"];
                } else {
                    array_unshift($arVideosInvalid, $videoIndex);
                }
            }
            if (!empty($arVideosInvalid)) {
                // Remove invalid videos
                foreach ($arVideosInvalid as $invalidIndex => $videoIndex) {
                    array_splice($arArticle["videos"], $videoIndex, 1);
                }
                $params->setParam("data", $arArticle);
            }
            if (!empty($arVideoIds)) {
                // Set videos as listed
                $this->setVideoPending($arVideoIds, false);
            }
            $arVideoIdsRemoved = $this->db->fetch_col("
                SELECT CODE FROM `ad_video`
                WHERE FK_AD=".$arArticle["ID_AD_MASTER"].
                (!empty($arVideoIdsQuery) ? " AND CODE NOT IN (".implode(", ", $arVideoIdsQuery).")" : ""));

            if (!empty($arVideoIdsRemoved)) {
                // Delete removed videos
                foreach ($arVideoIdsRemoved as $videoIndex => $videoId) {
                    $this->youtube_deleteVideo($videoId);
                }
            }
        }
    }
    
    public function marketplaceAdCreateSubmitStep(Api_Entities_EventParamContainer $params) {
        if ($params->getParam("step") == "ARTICLE_MEDIA") {
            /** @var AdCreate $adCreate */
            $adCreate = $params->getParam("adCreate");
            $arData = $params->getParam("dataInput");
            $arVideos = $adCreate->getCustomAdData("videos");
            if (array_key_exists("META", $arData) && array_key_exists("VIDEOS", $arData["META"])) {
                foreach ($arData["META"]["VIDEOS"] as $imageIndex => $arVideoMeta) {
                    if ($imageIndex < count($arVideos)) {
                        $videoCode = $arVideos[$imageIndex]["CODE"];
                        $this->youtube_updateVideo($videoCode, array(
                            "snippet.title" => $adCreate->getAdTitle().": ".$arVideoMeta["TITLE"]
                        ));
                    }
                }
            }
        }
    }
    
    public function marketplaceAdCreateRenderVideos(Api_Entities_EventParamContainer $params) {
        /** @var AdCreate $adCreate */
        $adCreate = $params->getParam("adCreate");
        $arVideos = $params->getParam("list");
        foreach ($arVideos as $videoIndex => $videoDetails) {
            $arVideos[$videoIndex]["i"] = $videoIndex;
            /** @var Google_Service_YouTube_Video $videoYoutube */
            $videoYoutube = $this->youtube_getVideoDetails($videoDetails["CODE"], "id,status,snippet");
            /** @var Google_Service_YouTube_VideoSnippet $videoYoutubeSnippet */
            $videoYoutubeSnippet = $videoYoutube->getSnippet();
            $videoYoutubeTitleStart = strpos($videoYoutubeSnippet->getTitle(), $adCreate->getAdTitle());
            if ($videoYoutubeTitleStart === false) {
                $videoYoutubeTitleStart = strpos($videoYoutubeSnippet->getTitle(), ":");
            } else {
                $videoYoutubeTitleStart += strlen($adCreate->getAdTitle());
            }
            if ($videoYoutubeTitleStart !== false) {
                $videoYoutubeTitleStart += 2;
            }
            if ($videoYoutubeTitleStart >= 0) {
                $arVideos[$videoIndex]["META_TITLE"] = substr($videoYoutubeSnippet->getTitle(), $videoYoutubeTitleStart);
            } else {
                $arVideos[$videoIndex]["META_TITLE"] = "";
            }
        }
        $tpl_videos = $this->utilGetTemplate("video.htm");
        $tpl_videos->addvar("liste", $this->utilGetTemplateList("video.row.htm", $arVideos) );
        $params->setParam("result", $tpl_videos->process(true));
    }

    public function marketplaceCategoryEditTemplate(Api_Entities_EventParamContainer $params) {
        $youtube = $this->getGoogleAPIYoutubeService();
        if ($youtube !== null) {
            $categoryId = $params->getParam("id");
            $arLang = array("REGION" => "DE", "LOCALE" => "de_DE");   // Fallback
            $arLangList = $GLOBALS["lang_list"];
            foreach ($arLangList as $langAbbr => $langDetails) {
                if ($langAbbr == $GLOBALS["s_lang"]) {
                    $arLang = $langDetails;
                    $arLang["REGION"] = substr($arLang["LOCALE"], -2);
                }
            }
            $categoryDefaultSelected = true;
            $arCategoryList = $this->getCategoryList();
            $arCategoryMapping = $this->getCategoryMapping();
            $arCategoryOptions = array();
            foreach ($arCategoryList as $categoryIndex => $categoryDetails) {
                $isSelected = false;
                if (array_key_exists($categoryId, $arCategoryMapping) && ($arCategoryMapping[$categoryId] == $categoryDetails["id"])) {
                    // Category selected
                    $isSelected = true;
                    $categoryDefaultSelected = false;
                }
                $arCategoryOptions[] = array("name" => $categoryDetails["name"], "value" => $categoryDetails["id"], "selected" => $isSelected);
            }
            // Add template to output
            $tplCategoryEdit = $this->utilGetTemplate("categoryEdit.htm");
            $tplCategoryEdit->addvar("categoryDefault", $categoryDefaultSelected);
            $tplCategoryEdit->addvar("categoryOptions", $this->utilGetTemplateList("categoryEdit.category.htm", $arCategoryOptions));
            $params->setParamArrayAppend("pluginHtml", $tplCategoryEdit);
        }
    }

    public function marketplaceCategoryUpdated(Api_Entities_EventParamContainer $params) {
        $arData = $params->getParam("data");
        $arCategoryIds = array($params->getParam("id"));
        if ($arData["youtubeCategoryRecursive"]) {
            $query = "SELECT ID_KAT FROM `kat` WHERE LFT BETWEEN " . (int)$arData["LFT"] . " AND " . (int)$arData["RGT"]." AND ROOT=1";
            $arCategoryIds = $this->db->fetch_col($query, 1);
        }
        $arCategoryMapping = $this->getCategoryMapping();
        foreach ($arCategoryIds as $categoryIndex => $categoryId) {
            if ($arData["youtubeCategoryId"] == "") {
                // Set default category
                if (array_key_exists($categoryId, $arCategoryMapping)) {
                    unset($arCategoryMapping[$categoryId]);
                }
            } else {
                // Set specific category
                $arCategoryMapping[$categoryId] = $arData["youtubeCategoryId"];
            }
        }
        $this->setCategoryMapping($arCategoryMapping);
    }
    
    public function vendorRenderVideos(Api_Entities_EventParamContainer $params) {
        /** @var VendorManagement $vendorManagement */
        $vendorManagement = $params->getParam("vendorManagement");
        /** @var VendorGalleryManagement $vendorGalleryManagement */
        $vendorGalleryManagement = $params->getParam("vendorGalleryManagement");
        $vendorDetails = $vendorManagement->fetchByUserId($GLOBALS["uid"]);
        $vendorName = $vendorDetails["NAME"];
        $arVideos = $params->getParam("list");
        foreach ($arVideos as $videoIndex => $videoDetails) {
            $arVideos[$videoIndex]["i"] = $videoIndex;
            /** @var Google_Service_YouTube_Video $videoYoutube */
            $videoYoutube = $this->youtube_getVideoDetails($videoDetails["YOUTUBEID"], "id,status,snippet");
            /** @var Google_Service_YouTube_VideoSnippet $videoYoutubeSnippet */
            $videoYoutubeSnippet = $videoYoutube->getSnippet();
            $videoYoutubeTitleStart = strpos($videoYoutubeSnippet->getTitle(), $vendorName);
            if ($videoYoutubeTitleStart === false) {
                $videoYoutubeTitleStart = strpos($videoYoutubeSnippet->getTitle(), ":");
            } else {
                $videoYoutubeTitleStart += strlen($vendorName);
            }
            if ($videoYoutubeTitleStart !== false) {
                $videoYoutubeTitleStart += 2;
            }
            if ($videoYoutubeTitleStart >= 0) {
                $arVideos[$videoIndex]["META_TITLE"] = substr($videoYoutubeSnippet->getTitle(), $videoYoutubeTitleStart);
            } else {
                $arVideos[$videoIndex]["META_TITLE"] = "";
            }
        }
        $tpl_videos = $this->utilGetTemplate("video.htm");
        $tpl_videos->addvar("CODE", $videoDetails["YOUTUBEID"]);
        $tpl_videos->addvar("liste", $this->utilGetTemplateList("video.row.htm", $arVideos) );
        $params->setParam("result", $tpl_videos->process(true));
    }
    
    public function videoUploadInput(Api_Entities_EventParamContainer $params) {
        $tplInput = $this->utilGetTemplate("input.htm");
        $tplInput->addvars($params->getParams());
        $params->setParam("result", $tplInput->process());
    }

    protected function getCategoryMapping() {
        $arCategoryMapping = array();
        if (file_exists(__DIR__."/categoryMapping.json")) {
            $arCategoryMapping = json_decode(file_get_contents(__DIR__."/categoryMapping.json"), true);
        }
        return $arCategoryMapping;
    }

    protected function setCategoryMapping($arCategoryMapping) {
        return file_put_contents(__DIR__."/categoryMapping.json", json_encode($arCategoryMapping));
    }

    /**
     * @return Api_Plugins_GoogleAPI_Plugin|bool
     * @throws Exception
     */
    protected function getGoogleAPIPlugin() {
        $pluginGoogleApi = Api_Plugins_GoogleAPI_Plugin::getInstance($this->db);
        if ($pluginGoogleApi === false) {
            throw new Exception("Required plugin 'GoogleAPI' not loaded!");
        }
        return $pluginGoogleApi;
    }
    
    /**
     * @param string|null   $clientId
     * @param string|null   $clientSecret
     * @param string|null   $developerKey
     * @param array         $arScopes
     * @return Google_Client
     * @throws Exception
     */
    protected function getGoogleAPIClient($clientId = null, $clientSecret = null, $developerKey = null) {
        if ($clientId === null) {
            $clientId = $this->pluginConfiguration["OAUTH_ID"];
        }
        if ($clientSecret === null) {
            $clientSecret = $this->pluginConfiguration["OAUTH_KEY"];
        }
        if ($developerKey === null) {
            $developerKey = $this->pluginConfiguration["ACCOUNT_KEY"];
        }
        return $this->getGoogleAPIPlugin()->getGoogleClient($clientId, $clientSecret, $developerKey, self::$googleOAuthScopes);
    }

    /**
     * @param string|null   $clientId
     * @param string|null   $clientSecret
     * @param string|null   $developerKey
     * @param array         $arScopes
     * @return Google_Service_YouTube
     * @throws Exception
     */
    protected function getGoogleAPIYoutubeService($clientId = null, $clientSecret = null, $developerKey = null) {
        if ($clientId === null) {
            $clientId = $this->pluginConfiguration["OAUTH_ID"];
        }
        if ($clientSecret === null) {
            $clientSecret = $this->pluginConfiguration["OAUTH_KEY"];
        }
        if ($developerKey === null) {
            $developerKey = $this->pluginConfiguration["ACCOUNT_KEY"];
        }
        return $this->getGoogleAPIPlugin()->getGoogleService("YouTube", $clientId, $clientSecret, $developerKey, self::$googleOAuthScopes);
    }

    /**
     * @param string|null   $clientId
     * @param string|null   $clientSecret
     * @param string|null   $developerKey
     * @param array         $arScopes
     * @return string
     */
    protected function getGoogleAPIAccessToken($clientId = null, $clientSecret = null, $developerKey = null) {
        return $this->getGoogleAPIClient($clientId, $clientSecret, $developerKey, self::$googleOAuthScopes)->getAccessToken();
    }

    /**
     * @param string|null   $clientId
     * @param string|null   $clientSecret
     * @param string|null   $developerKey
     * @param array         $arScopes
     * @return string
     */
    protected function getGoogleAPIYoutubeUploadURL($arVideoMeta = array(), $clientId = null, $clientSecret = null, $developerKey = null, $accessToken = null) {
        // Ensure developer key and access token
        if ($developerKey === null) {
            $developerKey = $this->pluginConfiguration["ACCOUNT_KEY"];
        }
        if ($accessToken === null) {
            $accessToken = $this->getGoogleAPIClient($clientId, $clientSecret, $developerKey)->getAccessToken();
        }
        if (!$accessToken) {
            trigger_error("Invalid OAuth access token!", E_USER_ERROR);
            return null;
        }
        // Prepare video meta data for upload
        $videoTitle = (array_key_exists("title", $arVideoMeta) && ($arVideoMeta["title"] !== null) ? $arVideoMeta["title"] : "Unbenannt");
        $videoDescription = (array_key_exists("description", $arVideoMeta) && ($arVideoMeta["description"] !== null) ? $arVideoMeta["description"] : "Beispielbeschreibung");
        if (strlen($videoDescription) > 4000) {
            // Truncate descriptions
            $truncatePos = (int)strrpos(substr($videoDescription, 0, 4000), " ");
            if ($truncatePos <= 0) {
                $truncatePos = 4000;
            }
            $videoDescription = substr($videoDescription, 0, $truncatePos) ." ...";
        }
        $videoCategory = (array_key_exists("category", $arVideoMeta) && ($arVideoMeta["category"] !== null) ? $arVideoMeta["category"] : "People");
        $videoKeywords = (array_key_exists("keywords", $arVideoMeta) && ($arVideoMeta["keywords"] !== null) ? explode(",", $arVideoMeta["keywords"]) : array());
        // XML request template
        $postBody = '<?xml version="1.0"?>
            <entry xmlns="http://www.w3.org/2005/Atom"
              xmlns:media="http://search.yahoo.com/mrss/"
              xmlns:yt="http://gdata.youtube.com/schemas/2007">
              <media:group>
                <media:title type="plain">'.htmlspecialchars($videoTitle).'</media:title>
                <media:description type="plain">
                  '.htmlspecialchars($videoDescription).'
                </media:description>
                <media:category scheme="http://gdata.youtube.com/schemas/2007/categories.cat">'.htmlspecialchars($videoCategory).'</media:category>
                <media:keywords>'.htmlspecialchars(implode(",", $videoKeywords)).'</media:keywords>
              </media:group>
              <yt:accessControl action="list" permission="denied" />
            </entry>';
        // Initialize request
        $arAccessToken = json_decode($accessToken, true);
        $curlHandle = curl_init();
        $arHeader = array(
            "X-GData-Key: key=".$developerKey,
            "Authorization: ".$arAccessToken["token_type"]." ".$arAccessToken["access_token"],
            "GData-Version: 2",
            "Content-length: ".strlen($postBody),
            "Content-type: application/atom+xml; charset=UTF-8"
        );
        curl_setopt_array($curlHandle, array(
            CURLOPT_URL             => 'gdata.youtube.com/action/GetUploadToken',
            CURLOPT_HTTPHEADER      => $arHeader,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_POST            => true,
            CURLOPT_POSTFIELDS      => $postBody
        ));
        $resultStr = curl_exec($curlHandle);
        $result = (array)simplexml_load_string($resultStr);
        if (array_key_exists("url", $result) && array_key_exists("token", $result)) {
            // Success
            return $result;
        } else {
            // Error
            eventlog("error", "Fehler beim vorbereiten eines YouTube-Uploads!", $resultStr);
            return null;
        }
    }

    protected function cleanupVideosPending() {
        $arRemoved = array();
        $arPending = $this->getVideosPending();
        foreach ($arPending as $pendingVideoId => $pendingDetails) {
            $pendingDuration = time() - $pendingDetails["uploaded"];
            if ($pendingDuration > 3600) {
                // Video was uploaded 1 hour ago, but article never finished. Delete video!
                $arRemoved[] = $pendingVideoId;
                $this->youtube_deleteVideo($pendingVideoId);
            }
        }
        file_put_contents(__DIR__."/videos_pending.json", json_encode($arPending));
        return true;
    }

    protected function getVideosPending() {
        $arPending = array();
        if (file_exists(__DIR__."/videos_pending.json")) {
            $arPending = json_decode(file_get_contents(__DIR__."/videos_pending.json"), true);
        }
        return $arPending;
    }

    protected function setVideoPending($arVideoIds, $isPending = true) {
        if (!is_array($arVideoIds)) {
            $arVideoIds = array($arVideoIds);
        }
        $arPending = $this->getVideosPending();
        foreach ($arVideoIds as $videoIndex => $videoId) {
            if ($isPending && !array_key_exists($videoId, $arPending)) {
                // Add to pending list
                if (!$this->youtube_updateVideo($videoId, array("status.privacyStatus" => "unlisted"))) {
                    return false;
                }
                $arPending[$videoId] = array(
                    "uploaded"  => time(),
                    "user"      => (int)$GLOBALS["uid"]
                );
            } else if (!$isPending && array_key_exists($videoId, $arPending)) {
                unset($arPending[$videoId]);
                if (!$this->youtube_updateVideo($videoId, array("status.privacyStatus" => "public"))) {
                    return false;
                }
            }
        }
        file_put_contents(__DIR__."/videos_pending.json", json_encode($arPending));
        return true;
    }

    /**
     * Get youtube video details
     * @param string        $videoId
     * @param string        $part
     * @param string|null   $accountKey
     * @return Google_Service_YouTube_Video|null
     */
    protected function youtube_getVideoDetails($videoId, $part = "id,status") {
        $youtube = $this->getGoogleAPIYoutubeService();
        try {
            $response = $youtube->videos->listVideos($part, array(
                'id' => $videoId,
            ));
            if (!empty($response)) {
                return $response[0];
            }
        } catch (Google_Service_Exception $e) {
            trigger_error($e->getMessage(), E_USER_ERROR);
        }
        return null;
    }

    /**
     * Delete youtube video
     * @param string    $videoId
     * @return bool|expected_class|Google_Http_Request
     */
    protected function youtube_deleteVideo($videoId) {
        $youtube = $this->getGoogleAPIYoutubeService();
        $videoIdCheck = $this->youtube_getVideoDetails($videoId, "id");
        if ($videoIdCheck["id"] == $videoId) {
            return $youtube->videos->delete($videoId);
        } else {
            return false;
        }
    }

    /**
     * Update youtube video
     * @param string    $videoId
     * @param array     $arNewData
     * @return bool
     */
    protected function youtube_updateVideo($videoId, $arNewData) {
        $youtube = $this->getGoogleAPIYoutubeService();
        // Get video data
        $arVideoDataNew = array();
        foreach ($arNewData as $dataKey => $dataValue) {
            list($dataPart, $dataName) = explode(".", $dataKey);
            if (!array_key_exists($dataPart, $arVideoDataNew)) {
                $arVideoDataNew[$dataPart] = array();
            }
            $arVideoDataNew[$dataPart][$dataName] = $dataValue;
        }
        $videoPart = implode(",", array_keys($arVideoDataNew));
        $arVideoDataOld = $this->youtube_getVideoDetails($videoId, "id,".$videoPart);
        // Update video data
        foreach ($arVideoDataNew as $dataPart => $dataFields) {
            if (!$arVideoDataOld->offsetExists($dataPart)) {
                trigger_error("Unknown 'part' value for video update: ".$dataPart, E_USER_ERROR);
                return false;
            }
            foreach ($dataFields as $fieldName => $fieldValue) {
                $arVideoDataOld[$dataPart][$fieldName] = $fieldValue;
            }
        }
        $updateResponse = $youtube->videos->update($videoPart, $arVideoDataOld);
        return true;
    }

    /**
     * Validate youtube video
     * @param string    $videoId
     * @return bool
     */
    protected function youtube_validateVideo($videoId) {
        $youtubeDetails = $this->youtube_getVideoDetails($videoId);
        if (($youtubeDetails === null) || ($youtubeDetails["id"] === null)) {
            return false;
        } else {
            /** @var Google_Service_YouTube_VideoStatus $youtubeStatus */
            $youtubeStatus = $youtubeDetails->getStatus();
            if ($youtubeStatus->getRejectionReason() !== null) {
                // Video rejected!
                $this->youtube_deleteVideo($videoId);
                return false;
            }
            return true;
        }
    }

    protected function getCategoryList()
    {
        $arCategories = array();
        $domCategories = new DOMDocument();
        $domCategories->loadXML( file_get_contents("http://gdata.youtube.com/schemas/2007/categories.cat") );
        $domListCategories = $domCategories->getElementsByTagName("category");
        /** @var DOMElement $domCategory */
        foreach ($domListCategories as $domCategory) {
            if ($domCategory->getElementsByTagName("assignable")->length == 0) {
                // Only list accessiable
                continue;
            }
            $arCategories[] = array("id" => $domCategory->getAttribute("term"), "name" => $domCategory->getAttribute("label"));
        }
        return $arCategories;
    }

}