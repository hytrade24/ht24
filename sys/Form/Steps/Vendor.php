<?php

class Form_Steps_Vendor extends Form_Steps {
    
    protected $arUser = array();
    protected $arSearchFields = array();
    
    public function __construct(ebiz_db $db = null) {
        parent::__construct($db);
        $this->arUser = $GLOBALS["user"];
    }

    /**
     * Initialize the available steps
     */
    protected function initSteps() {
        // Base information
        $this->addStep(
            "base", 
            Translation::readTranslation("marketplace", "vendor.step.base", null, array(), "Grundinformationen"), 
            "my-vendor-steps.base.htm"
        );
        // Description, Keywords and Imprint
        $this->addStep(
            "description", 
            Translation::readTranslation("marketplace", "vendor.step.description", null, array(), "Beschreibung"), 
            "my-vendor-steps.description.htm"
        );
        // Times
        $this->addStep(
            "times", 
            Translation::readTranslation("marketplace", "vendor.step.times", null, array(), "Öffnungszeiten"), 
            "my-vendor-steps.times.htm"
        );
        // Media
        $this->addStep(
            "media", 
            Translation::readTranslation("marketplace", "vendor.step.media", null, array(), "Bilder & Videos"), 
            "my-vendor-steps.media.htm"
        );
        // Categorys
        $this->addStep(
            "category", 
            Translation::readTranslation("marketplace", "vendor.step.category", null, array(), "Kategorien & Schlagworte"), 
            "my-vendor-steps.category.htm"
        );
        // Search fields
        $this->arSearchFields = array();
        if (!empty($this->arData["CATEGORIES"])) {
            $categoryIds = array();
            $categoryIdsRaw = explode(",", $this->arData["CATEGORIES"]);
            foreach ($categoryIdsRaw as $categoryId) {
                if (preg_match("/^([0-9]+)_([A-Z]+)$/", $categoryId, $categoryMatch)) {
                    $categoryIds[] = $categoryMatch[1];
                }
            }
            
            $arFieldGroupsFromDb = $this->db->fetch_col('
                SELECT ID_FIELD_GROUP 
                FROM `field_group` a
                INNER JOIN `table_def` t
                    ON t.T_NAME = "vendor_master"
                    AND a.FK_TABLE_DEF = t.ID_TABLE_DEF');
            $arFieldGroups = array(null);
            $arFieldGroups = array_merge($arFieldGroups, $arFieldGroupsFromDb);
            foreach ( $arFieldGroups as $fieldGroup ) {
                $fieldGroupContent = CategoriesBase::getInputFieldsCache($categoryIds, $this->arData, false, $fieldGroup, true);
                if (!empty($fieldGroupContent)) {
                    $this->arSearchFields[] = $fieldGroupContent;
                }
            }
        }
        #var_dump($this->arData["CATEGORIES"], $this->arSearchFields);
        if (!empty($this->arSearchFields)) {
            $this->addStep(
                "searchfields", 
                Translation::readTranslation("marketplace", "vendor.step.searchfields", null, array(), "Suchfelder"), 
                "my-vendor-steps.searchfields.htm"
            );
        }
        // Locations
        $this->addStep(
            "locations", 
            Translation::readTranslation("marketplace", "vendor.step.locations", null, array(), "Standorte"), 
            "my-vendor-steps.locations.htm"
        );
    }

    /**
     * Get the id of the current vendor entry
     * @return int|null
     */
    public function getVendorId() {
        return (array_key_exists("ID_VENDOR", $this->arData) && ($this->arData["ID_VENDOR"] > 0) ? (int)$this->arData["ID_VENDOR"] : null);
    }

    /**
     * Get the id of the current vendor entrys user
     * @return int|null
     */
    public function getUserId() {
        return (array_key_exists("FK_USER", $this->arData) && ($this->arData["FK_USER"] > 0) ? (int)$this->arData["FK_USER"] : null);
    }

    /**
     * Return the name for the current vendor
     * @return string|null
     */
    protected function getObjectTitle() {
        return (array_key_exists("NAME", $this->arData) ? $this->arData["NAME"] : null);
    }

    /**
     * Return the ident that is used to save the information inbetween steps
     * @return string
     */
    protected function getSessionIdent() {
        return "vendorCreateSteps";
    }

    /**
     * Return javascript/css/... includes that should be loaded
     * @return string
     */
    protected function getScriptIncludes() {
        $tpl_scripts = new Template("tpl/".$GLOBALS["s_lang"]."/my-vendor-steps-scripts.htm");
        return $tpl_scripts->process();
    }

    /**
     * Return the nav ident for the page that will handle the steps
     * @return string
     */
    protected function getUrlIdent() {
        return "my-vendor-steps";
    }

    /**
     * Return the nav ident for the page that will be shown after completing the process
     * @return string
     */
    protected function getUrlFinishIdent() {
        return "my-vendor,saved";
    }

    /**
     * Will be called before rendering the content of a step
     * @param array $arStep
     * @param array $arData
     * @param Template $tpl_content
     * @param Template $tpl_step
     * @return mixed
     */
    protected function prepareStepContent($arStep, $arData, Template $tpl_content, Template $tpl_step) {
        switch ($arStep["IDENT"]) {
            case "base":
                $tpl_content->addvar("STATUS", $this->arData["STATUS"]);
                $tpl_content->addvar("NAME", $this->arData["NAME"]);
                $tpl_content->addvar("STRASSE", $this->arData["STRASSE"]);
                $tpl_content->addvar("PLZ", $this->arData["PLZ"]);
                $tpl_content->addvar("ORT", $this->arData["ORT"]);
                $tpl_content->addvar("FK_COUNTRY", $this->arData["FK_COUNTRY"]);
                $tpl_content->addvar("TEL", $this->arData["TEL"]);
                $tpl_content->addvar("FAX", $this->arData["FAX"]);
                $tpl_content->addvar("URL", $this->arData["URL"]);
                $logoSource = $this->arData["LOGO"];
                if ($logoSource !== null) {
                    $logoFileInfo = array( "extension" => "jpg" );
                    $logoFileSize = 0;
                    if (is_array($logoSource)) {
                        $logoFile = $GLOBALS["ab_path"]."filestorage/uploads/".$logoSource["file"];
                        $logoFileInfo = pathinfo($logoFile);
                        $logoFileSize = filesize($logoFile);
                        $logoSource = "data:".$logoSource["type"].";base64,".base64_encode(file_get_contents($logoFile));
                    } else {
                        $logoFile = $GLOBALS["ab_path"].'cache/vendor/logo/'.$logoSource;
                        $logoFileInfo = pathinfo($logoFile);
                        $logoFileSize = filesize($logoFile);
                        $logoSource = $tpl_content->tpl_uri_baseurl('cache/vendor/logo/'.$logoSource);
                    }
                    $tpl_content->addvar("LOGO", $logoSource);
                    $tpl_content->addvar("LOGO_NAME", "logo.".$logoFileInfo["extension"]);
                    $tpl_content->addvar("LOGO_SIZE", $logoFileSize);
                }
                break;
            case "description":
                $arLanguages = $this->db->fetch_table("SELECT * FROM lang WHERE B_PUBLIC = 1");
                foreach ($arLanguages as $langIndex => $langDetail) {
                    $arLanguages[$langIndex]["VENDOR_DESCRIPTION"] = (array_key_exists($langDetail["BITVAL"], $this->arData["T1"]) ? $this->arData["T1"][$langDetail["BITVAL"]] : "");
                }
                $tpl_content->addlist("languageHeader", $arLanguages, 'tpl/'.$GLOBALS["s_lang"].'/my-vendor-steps-tabs.lang.htm');
                $tpl_content->addlist("languageBody", $arLanguages, 'tpl/'.$GLOBALS["s_lang"].'/my-vendor-steps.description.lang.body.htm');
                $tpl_content->addvar("AGB", $this->arData["AGB"]);
                $tpl_content->addvar("WIDERRUF", $this->arData["WIDERRUF"]);
                $tpl_content->addvar("ZAHLUNG", $this->arData["ZAHLUNG"]);
                $tpl_content->addvar("IMPRESSUM", $this->arData["IMPRESSUM"]);
                break;
            case "times":
                $business_hours = @json_decode($this->arData['BUSINESS_HOURS'], true);
                if (!is_array($business_hours)) {
                    $business_hours = [];
                }
                foreach ($business_hours as $weekday => $weekdayHours) {
                    $tpl_content->addvar('BUSINESS_HOURS_'.$weekday, $weekdayHours);
                }
                break;
            case "media":
                $arImages = $this->arData["IMAGES"];
                $arVideos = $this->arData["VIDEOS"];
                foreach ($arImages as $imageIndex => $imageDetail) {
                    $imageFileSize = 0;
                    if (is_array($imageDetail["FILENAME"])) {
                        $imageFile = $GLOBALS["ab_path"]."filestorage/uploads/".$imageDetail["FILENAME"]["file"];
                        $imageFileSize = filesize($imageFile);
                        $arImages[$imageIndex]["FILENAME"] = "data:".$imageDetail["FILENAME"]["type"].";base64,".base64_encode(file_get_contents($imageFile));
                    } else {
                        $imageFile = $GLOBALS["ab_path"].'cache/vendor/gallery/'.$imageDetail["FILENAME"];
                        $imageFileSize = filesize($imageFile);
                        $arImages[$imageIndex]["FILENAME"] = $tpl_content->tpl_uri_baseurl('cache/vendor/gallery/'.$imageDetail["FILENAME"]);
                    }
                    $arImages[$imageIndex]["FILESIZE"] = $imageFileSize;
                }
                $tpl_content->addlist("IMAGES", $arImages, 'tpl/'.$GLOBALS['s_lang'].'/my-vendor-steps.media.image.htm');
                $tpl_content->addlist("VIDEOS", $arVideos, 'tpl/'.$GLOBALS['s_lang'].'/my-vendor-steps.media.video.htm');
                $tpl_content->addvar("maxbilder", $GLOBALS['nar_systemsettings']['USER']['VENDOR_GALLERY_MAX_IMAGES']);                
                break;
            case "category":
                // Kategorien
                require_once $GLOBALS["ab_path"].'sys/lib.vendor.category.php';
                $vendorCategoryManagement = VendorCategoryManagement::getInstance($this->db);
                $preSelectedNodes = array();
                if (!empty($this->arData["CATEGORIES"])) {
                    $preSelectedNodes = explode(",", $this->arData["CATEGORIES"]);
                }                
                $categoryJSONTree = $vendorCategoryManagement->getVendorCategoryJSONTree($preSelectedNodes);
                $tpl_content->addvar("CATEGORY_JSON_TREE", $categoryJSONTree);
                $tpl_content->addvar("CATEGORY_TREE_MAX_SELECTS", VendorCategoryManagement::MAX_CATEGORY_PER_USER);
                // Keywords
                $arLanguages = $this->db->fetch_table("SELECT * FROM lang WHERE B_PUBLIC = 1");
                $arSearchwords = array();
                foreach ($arLanguages as $langIndex => $langDetail) {
                    $arSearchwords[ $langDetail["ABBR"] ] = $this->arData["KEYWORDS"][ $langDetail["ABBR"] ];
                }
                $tpl_content->addlist("searchWordLanguageHeader", $arLanguages, 'tpl/'.$GLOBALS["s_lang"].'/my-vendor-steps-tabs.lang.htm');
                $tpl_content->addlist("searchWordLanguageBody", $arLanguages, 'tpl/'.$GLOBALS["s_lang"].'/my-vendor-steps.category.lang.searchwords.htm');
                $tpl_content->addvar("searchWordJson", json_encode($arSearchwords));
                break;
            case "searchfields":
                $tpl_content->addvar("vendor_details", $this->arSearchFields);
                break;
            case "locations":
                $arLocations = array();
                $arLanguages = $this->db->fetch_table("SELECT * FROM lang WHERE B_PUBLIC = 1");
                if (array_key_exists("LOCATIONS", $this->arData)) {
                    foreach ($this->arData["LOCATIONS"] as $arLocation) {
                        $arLocation["T1"] = null;
                        $arLocations[] = $arLocation;
                    }
                    if (array_key_exists("edit", $arData) && (count($this->arData["LOCATIONS"]) > $arData["edit"])) {
                        $locationIndex = (int)$arData["edit"];
                        $arLocation = $this->arData["LOCATIONS"][$locationIndex];
                        foreach ($arLanguages as $langIndex => $langDetail) {
                            $arLanguages[$langIndex]["VENDOR_PLACE_DESCRIPTION"] = (array_key_exists($langDetail["BITVAL"], $arLocation["T1"]) ? $arLocation["T1"][$langDetail["BITVAL"]] : "");
                        }                    
                        $tpl_content->addvar("INDEX", $locationIndex);
                        $tpl_content->addvar("NAME", $arLocation["NAME"]);
                        $tpl_content->addvar("STRASSE", $arLocation["STRASSE"]);
                        $tpl_content->addvar("PLZ", $arLocation["PLZ"]);
                        $tpl_content->addvar("ORT", $arLocation["ORT"]);
                        $tpl_content->addvar("FK_COUNTRY", $arLocation["FK_COUNTRY"]);
                        $tpl_content->addvar("EDIT", 1);
                    }
                }
                $tpl_content->addvar("BASE_FIRMA", $this->arData["NAME"]);
                $tpl_content->addvar("BASE_STRASSE", $this->arData["STRASSE"]);
                $tpl_content->addvar("BASE_PLZ", $this->arData["PLZ"]);
                $tpl_content->addvar("BASE_ORT", $this->arData["ORT"]);
                $tpl_content->addvar("BASE_COUNTRY", $this->arData["COUNTRY"]);
                $tpl_content->addlist("liste", $arLocations, 'tpl/'.$GLOBALS["s_lang"].'/my-vendor-steps.locations.row.htm');
                $tpl_content->addlist("languageHeader", $arLanguages, 'tpl/'.$GLOBALS["s_lang"].'/my-vendor-steps-tabs.lang.htm');
                $tpl_content->addlist("languageBody", $arLanguages, 'tpl/'.$GLOBALS["s_lang"].'/my-vendor-steps.locations.lang.body.htm');
                break;
        }
    }

    /**
     * Will be called after submitting a step
     * @param array $arStep
     * @param array $arData
     * @param array $arFiles
     * @param array $arResponse
     * @return mixed
     */
    protected function submitStep($arStep, $arData, $arFiles, &$arResponse) {
        switch ($arStep["IDENT"]) {
            case "base":
                // Dropzone upload
                if (array_key_exists("file", $arFiles)) {
                    $uploadSuccessful = false;
                    if ($arFiles["error"] == 0) {
                        $uploadSuccessful = true;
                        $uploadCache = new Cache_Upload($GLOBALS["ab_path"]."filestorage/uploads");
                        $uploadFile = $uploadCache->addFileUpload($arFiles["file"]["tmp_name"], $arFiles["file"]["name"]);
                        $this->arData["LOGO"] = array(
                            "file" => $uploadFile,
                            "type" => $arFiles["file"]["type"]
                        );
                    }
                    $arResponse = array(
                        "success" => $uploadSuccessful
                    );
                    return true;
                }
                if (array_key_exists("info", $arData) && ($arData["info"] == "copy-from-user")) {
                    $this->arData["STATUS"] = (int)$arData["STATUS"];
                    $this->arData["NAME"] = $this->arUser["FIRMA"];
                    $this->arData["STRASSE"] = $this->arUser["STRASSE"];
                    $this->arData["PLZ"] = $this->arUser["PLZ"];
                    $this->arData["ORT"] = $this->arUser["ORT"];
                    $this->arData["FK_COUNTRY"] = $this->arUser["FK_COUNTRY"];
                    $this->arData["TEL"] = $this->arUser["TEL"];
                    $this->arData["FAX"] = $this->arUser["FAX"];
                    $this->arData["URL"] = $this->arUser["URL"];
                    $arResponse["STEP_NEXT"] = "base";
                    return true;
                }
                // Validate
                $valid = true;
                if (empty($arData["NAME"])) {
                    $arResponse["ERRORS"]["NAME"] = Translation::readTranslation(
                        "marketplace", "vendor.base.error.missing.name", null, array(), 
                        "Bitte geben Sie einen Firmennamen ein."
                    );
                    $valid = false;
                }
                if (!$valid) {
                    return false;
                }
                // Default submit
                $this->arData["STATUS"] = (array_key_exists("STATUS", $arData) && $arData["STATUS"] ? 1 : 0);
                $this->arData["NAME"] = (array_key_exists("NAME", $arData) ? $arData["NAME"] : "");
                $this->arData["STRASSE"] = (array_key_exists("STRASSE", $arData) ? $arData["STRASSE"] : "");
                $this->arData["PLZ"] = (array_key_exists("PLZ", $arData) ? $arData["PLZ"] : "");
                $this->arData["ORT"] = (array_key_exists("ORT", $arData) ? $arData["ORT"] : "");
                $this->arData["FK_COUNTRY"] = (array_key_exists("FK_COUNTRY", $arData) ? $arData["FK_COUNTRY"] : "");
                $this->arData["TEL"] = (array_key_exists("TEL", $arData) ? $arData["TEL"] : "");
                $this->arData["FAX"] = (array_key_exists("FAX", $arData) ? $arData["FAX"] : "");
                $this->arData["URL"] = (array_key_exists("URL", $arData) ? $arData["URL"] : "");
                return true;
            case "description":
                if (!empty($arData["T1"])) {
                    // Update descriptions
                    if (!array_key_exists("T1", $this->arData) || !is_array($this->arData["T1"])) {
                        $this->arData["T1"] = array();
                    }
                    foreach ($arData["T1"] as $descLangVal => $descContent) {
                        $this->arData["T1"][$descLangVal] = $descContent;
                    }
                }
                $this->arData["AGB"] = $arData["AGB"];
                $this->arData["WIDERRUF"] = $arData["WIDERRUF"];
                $this->arData["ZAHLUNG"] = $arData["ZAHLUNG"];
                $this->arData["IMPRESSUM"] = $arData["IMPRESSUM"];
                return true;
            case "times":
                $arBusinessHours = array();
                if (array_key_exists('BUSINESS_HOURS', $arData) && is_array($arData['BUSINESS_HOURS'])) {
                    $arBusinessHours = $arData['BUSINESS_HOURS'];
                }
                $this->arData['BUSINESS_HOURS'] = json_encode($arBusinessHours);
                return true;
            case "media":
                // Dropzone upload
                if (array_key_exists("media", $arData)) {
                    switch ($arData["media"]) {
                        case "delete":
                            $filename = $arData["file"];
                            $success = false;
                            foreach ($this->arData["IMAGES"] as $imageIndex => $imageDetail) {
                                if ($imageDetail["NAME"] == $filename) {
                                    array_splice($this->arData["IMAGES"], $imageIndex, 1);
                                    $success = true;
                                    break;
                                }
                            }
                            $arResponse = array("success" => $success);
                            return true;
                        case "delete-video":
                            $youtubeId = $arData["id"];
                            $success = false;
                            foreach ($this->arData["VIDEOS"] as $videoIndex => $videoDetail) {
                                if ($videoDetail["YOUTUBEID"] == $youtubeId) {
                                    array_splice($this->arData["VIDEOS"], $videoIndex, 1);
                                    $success = true;
                                    break;
                                }
                            }
                            $arResponse = array("success" => $success);
                            return true;
                        case "upload-video":
                            require_once 'sys/lib.youtube.php';
                            $youtube = new Youtube();
                            $youtubeId = $youtube->ExtractCodeFromURL($arData['youtubelink']);
                            if ($youtubeId !== null) {
                                $this->arData["VIDEOS"][] = $youtubeDetails = array(
                                    "FK_VENDOR"     => $this->getVendorId(),
                                    "YOUTUBEID"     => $youtubeId,
                                    "NAME"          => ""
                                );
                                $templateVideo = new Template("tpl/".$GLOBALS["s_lang"]."/my-vendor-steps.media.video.htm");
                                $templateVideo->addvars($youtubeDetails);
                                $arResponse = array(
                                    "success" => true,
                                    "html" => $templateVideo->process() 
                                );
                                return true;
                            } else {
                                die(var_dump($youtubeId, $arData));
                            }
                            break;
                    }
                    $arResponse["STEP_NEXT"] = "media";
                    return true;
                }
                if (array_key_exists("file", $arFiles)) {
                    $uploadSuccessful = false;
                    if ($arFiles["error"] == 0) {
                        $uploadSuccessful = true;
                        $uploadCache = new Cache_Upload($GLOBALS["ab_path"]."filestorage/uploads");
                        $uploadFile = $uploadCache->addFileUpload($arFiles["file"]["tmp_name"], $arFiles["file"]["name"]);
                        $this->arData["IMAGES"][] = array(
                            "FK_VENDOR"     => $this->getVendorId(),
                            "FILENAME"      => array(
                                "file" => $uploadFile,
                                "type" => $arFiles["file"]["type"]
                            ),
                            "NAME"          => $arFiles["file"]["name"]
                        );
                    }
                    $arResponse = array(
                        "success" => $uploadSuccessful
                    );
                    return true;
                }
                return true;
            case "category":
                $this->arData["CATEGORIES"] = $arData["CATEGORIES"];
                $this->arData["KEYWORDS"] = (array_key_exists("SEARCHWORDS", $arData) ? $arData["SEARCHWORDS"] : array());
                $this->reinitSteps();
                if (!empty($this->arSearchFields)) {
                    $arResponse["STEP_NEXT"] = "searchfields";
                }
                return true;
            case "searchfields":                
                foreach ( $arData["tmp_type"] as $key => $row ) {
                    if ( isset($arData[$key]) ) {
                        $this->arData[$key] = mysql_real_escape_string($arData[$key]);
                    }
                    else if ( isset($arData["check"][$key]) ) {
                        $this->arData[$key] = 'x'.implode("x",$arData["check"][$key]).'x';
                    }
                }
                return true;
            case "locations":
                if (array_key_exists("location", $arData)) {
                    switch ($arData["location"]) {
                        case "new":
                        case "update":
                            $arLocation = array(
                                "NAME"          => $arData["NAME"],
                                "STRASSE"       => $arData["STRASSE"],
                                "PLZ"           => $arData["PLZ"],
                                "ORT"           => $arData["ORT"],
                                "COUNTRY"       => "",
                                "FK_COUNTRY"    => $arData["FK_COUNTRY"],
                                "T1"            => array()
                            );
                            if ($arLocation["FK_COUNTRY"] > 0) {
                                $arCountry = Api_StringManagement::getInstance($this->db)->readById("country", $arData["FK_COUNTRY"]);
                                $arLocation["COUNTRY"] = $arCountry["V1"]; 
                            }
                            if (!empty($arData["T1"])) {
                                // Update descriptions
                                foreach ($arData["T1"] as $descLangVal => $descContent) {
                                    $arLocation["T1"][$descLangVal] = $descContent;
                                }
                            }
                            if (array_key_exists("INDEX", $arData) && ($arData["INDEX"] < count($this->arData["LOCATIONS"]))) {
                                // Update existing location
                                $this->arData["LOCATIONS"][ $arData["INDEX"] ] = $arLocation;
                            } else {
                                // Add new location
                                $this->arData["LOCATIONS"][] = $arLocation;
                            }
                            break;
                        case "delete":
                            $locationIndex = (int)$arData["index"];
                            if ($arData["INDEX"] < count($this->arData["LOCATIONS"])) {
                                array_splice($this->arData["LOCATIONS"], $locationIndex, 1);
                            }
                            break;
                    }
                    $arResponse["STEP_NEXT"] = "locations";
                }
                return true;
        }
        return false;
    }

    /**
     * Called to load a vendor from the database
     * @param int $id
     * @return mixed
     */
    protected function loadEntityFromDatabase($id) {
        $arLanguages = $this->db->fetch_table("SELECT * FROM lang WHERE B_PUBLIC = 1");
        // Base information
        require_once $GLOBALS["ab_path"].'sys/lib.vendor.php';
        $vendorManagement = VendorManagement::getInstance($this->db);
        $this->arData = $vendorManagement->fetchByUserId($id);
        $vendorId = $this->getVendorId();
        // User information
        $arUserContent = $this->db->fetch1("
            SELECT `AGB`, `WIDERRUF`, `ZAHLUNG`, `IMPRESSUM`
            FROM `usercontent`
            WHERE FK_USER=".(int)$id);
        $this->arData["AGB"] = $arUserContent["AGB"];
        $this->arData["WIDERRUF"] = $arUserContent["WIDERRUF"];
        $this->arData["ZAHLUNG"] = $arUserContent["ZAHLUNG"];
        $this->arData["IMPRESSUM"] = $arUserContent["IMPRESSUM"];
        // Kategorien
        require_once $GLOBALS["ab_path"].'sys/lib.vendor.category.php';
        $vendorCategoryManagement = VendorCategoryManagement::getInstance($this->db);
        $selectedCategories = $vendorCategoryManagement->fetchAllVendorCategoriesByVendorId($vendorId);
        $this->arData["CATEGORIES"] = array();
        foreach ($selectedCategories as $key => $selectedCategory) {
            if ($selectedCategory["IS_PREFERRED"] == "1") {
                $this->arData["CATEGORIES"][] = $selectedCategory["FK_KAT"] . "_P";
            } else {
                $this->arData["CATEGORIES"][] = $selectedCategory["FK_KAT"] . "_NP";
            }
        }
        $this->arData["CATEGORIES"] = implode(",", $this->arData["CATEGORIES"]);
        // Keywords
        $this->arData["KEYWORDS"] = array();
        foreach ($arLanguages as $languageIndex => $languageDetails) {
            $arSearchwords = $vendorManagement->fetchAllSearchWordsByUserIdAndLanguage($id, $languageDetails["ABBR"]);
            $arSearchwordsSimple = array();
            foreach ($arSearchwords as $arSearchword) {
                $arSearchwordsSimple[] = $arSearchword["wort"];
            }
            $this->arData["KEYWORDS"][ $languageDetails["ABBR"] ] = $arSearchwordsSimple;
        }
        // Sprachrelevante Felder
        $this->arData["T1"] = array();
        $arLanguages = $this->db->fetch_table("SELECT * FROM lang WHERE B_PUBLIC = 1");
        foreach ($arLanguages as $languageIndex => $arLanguage) {
            $this->arData["T1"][ $arLanguage["BITVAL"] ] = $vendorManagement->fetchVendorDescriptionByLanguage($vendorId, $arLanguage['BITVAL']);
        }
        // Standorte
        require_once $GLOBALS["ab_path"].'sys/lib.vendor.place.php';
        $vendorPlaceManagement = VendorPlaceManagement::getInstance($this->db);
        $this->arData["LOCATIONS"] = $vendorPlaceManagement->fetchAllByUserId($id);
        foreach ($this->arData["LOCATIONS"] as $locationIndex => $locationDetail) {
            $this->arData["LOCATIONS"][$locationIndex]["T1"] = array();
            foreach ($arLanguages as $languageIndex => $arLanguage) {
                $this->arData["LOCATIONS"][$locationIndex]["T1"][ $arLanguage["BITVAL"] ] = $vendorPlaceManagement->fetchVendorPlaceDescriptionByLanguage($locationDetail["ID_VENDOR_PLACE"], $arLanguage['BITVAL']);
            }
        }
        // Media
        require_once $GLOBALS["ab_path"].'sys/lib.vendor.gallery.php';
        $vendorGalleryManagement = VendorGalleryManagement::getInstance($this->db);
        $this->arData["IMAGES"] = $vendorGalleryManagement->fetchAllByUserId($id);
        $this->arData["VIDEOS"] = $vendorGalleryManagement->fetchAllVideosByUserId($id);
        // Enable all steps
        $this->indexStepLast = count($this->arSteps);
        return true;
    }
    
    /**
     * Called when finally saving the vendor to the database
     * @return mixed
     */
    protected function saveEntityToDatabase() {
        $userId = $this->getUserId();
        $vendorId = $this->getVendorId();
        $arLanguages = $this->db->fetch_table("SELECT * FROM lang WHERE B_PUBLIC = 1");
        // Base information
        require_once $GLOBALS["ab_path"].'sys/lib.vendor.php';
        $vendorManagement = VendorManagement::getInstance($this->db);
        if (array_key_exists("LOGO", $this->arData) && is_array($this->arData["LOGO"])) {
            $galleryFilename = $this->arData["LOGO"]['file'];
            $galleryFileCache = $GLOBALS["ab_path"]."filestorage/uploads/".$galleryFilename;
            $galleryFileLive = $GLOBALS["ab_path"].'cache/vendor/logo/'.$galleryFilename;
            rename($galleryFileCache, $galleryFileLive);
            $this->arData["LOGO"] = $galleryFilename;
        }
        $vendorManagement->saveVendorByUserId($this->arData, $userId);
        // User information
        $this->db->querynow($a = "INSERT INTO
            `usercontent`
                (`FK_USER`, `AGB`, `WIDERRUF`, `ZAHLUNG`, `IMPRESSUM`)
            VALUES
                (".$userId.",'".mysql_real_escape_string($this->arData['AGB'])."','".mysql_real_escape_string($this->arData['WIDERRUF'])."', '".mysql_real_escape_string($this->arData['ZAHLUNG'])."', '".mysql_real_escape_string($this->arData['IMPRESSUM'])."')
        ON DUPLICATE KEY UPDATE
            AGB='" . mysql_real_escape_string($this->arData['AGB']) . "',
            WIDERRUF='" . mysql_real_escape_string($this->arData['WIDERRUF']) . "',
            ZAHLUNG='" . mysql_real_escape_string($this->arData['ZAHLUNG']) . "',
            IMPRESSUM='" . mysql_real_escape_string($this->arData['IMPRESSUM']) . "'");
        // Kategorien
        require_once $GLOBALS["ab_path"].'sys/lib.vendor.category.php';
        $vendorCategoryManagement = VendorCategoryManagement::getInstance($this->db);
        $vendorCategoryManagement->addVendorCategories(explode(",", $this->arData["CATEGORIES"]), $vendorId);
        // Searchwords
        foreach ($arLanguages as $languageIndex => $languageDetails) {
            $arSearchwordsExisting = $vendorManagement->fetchAllSearchWordsByUserIdAndLanguage($userId, $languageDetails["ABBR"]);
            $arSearchwordsExistingSimple = array();
            foreach ($arSearchwordsExisting as $arSearchword) {
                $arSearchwordsExistingSimple[] = $arSearchword["wort"];
            }
            $arSearchwordsNew = (array_key_exists($languageDetails["ABBR"], $this->arData["KEYWORDS"]) ? $this->arData["KEYWORDS"][ $languageDetails["ABBR"] ] : array());
            $arSearchwordsAdded = array_diff($arSearchwordsNew, $arSearchwordsExistingSimple);
            foreach ($arSearchwordsAdded as $word) {
                $vendorManagement->addVendorSearchWordByUserId($word, $userId, $languageDetails["ABBR"]);
            }
            $arSearchwordsRemoved = array_diff($arSearchwordsExistingSimple, $arSearchwordsNew);
            foreach ($arSearchwordsRemoved as $word) {
                $vendorManagement->deleteVendorSearchWordByUserId($word, $userId, $languageDetails["ABBR"]);
            }
        }
        // Standorte
        require_once $GLOBALS["ab_path"].'sys/lib.vendor.place.php';
        $vendorPlaceManagement = VendorPlaceManagement::getInstance($this->db);
        $vendorPlaceIds = array();
        foreach ($this->arData["LOCATIONS"] as $locationIndex => $locationDetails) {
            if (array_key_exists("ID_VENDOR_PLACE", $locationDetails)) {
                $vendorPlaceIds[] = $locationDetails["ID_VENDOR_PLACE"];
                $vendorPlaceManagement->updateByIdAndUserId($locationDetails, $locationDetails["ID_VENDOR_PLACE"], $userId);
            } else {
                $vendorPlaceId = null;
                $vendorPlaceManagement->insertVendorPlace($locationDetails, $vendorId, $vendorPlaceId);
                if ($vendorPlaceId > 0) {
                    $vendorPlaceIds[] = $this->arData["LOCATIONS"][$locationIndex]["ID_VENDOR_PLACE"] = $vendorPlaceId;
                }
            }
        }
        $vendorPlaceManagement->deleteVendorPlaceWhereIdNotIn($vendorPlaceIds, $vendorId);
        // Media
        require_once $GLOBALS["ab_path"].'sys/lib.vendor.gallery.php';
        $vendorGalleryManagement = VendorGalleryManagement::getInstance($this->db);
        // - Images
        $vendorImageIds = array();
        foreach ($this->arData["IMAGES"] as $imageIndex => $imageDetails) {
            if (array_key_exists("ID_VENDOR_GALLERY", $imageDetails)) {
                $vendorImageIds[] = $imageDetails["ID_VENDOR_GALLERY"];
            } else {
                if (is_array($imageDetails["FILENAME"])) {
                    $galleryFilename = $imageDetails["FILENAME"]['file'];
                    $galleryFileCache = $GLOBALS["ab_path"]."filestorage/uploads/".$galleryFilename;
                    $galleryFileLive = $GLOBALS["ab_path"].'cache/vendor/gallery/'.$galleryFilename;
                    rename($galleryFileCache, $galleryFileLive);
                    $imageDetails["FILENAME"] = $this->arData["IMAGES"][$imageIndex]["FILENAME"] = $galleryFilename;
                }
                $vendorImageId = $vendorGalleryManagement->insertFile($imageDetails["NAME"], $imageDetails["FILENAME"], $vendorId);
                if ($vendorImageId > 0) {
                    $vendorImageIds[] = $this->arData["IMAGES"][$imageIndex]["ID_VENDOR_GALLERY"] = $vendorImageId;
                }
            }
        }
        $vendorGalleryManagement->deleteWhereIdNotIn($vendorImageIds, $vendorId);
        // - Videos
        $vendorVideoIds = array();
        foreach ($this->arData["VIDEOS"] as $videoIndex => $videoDetails) {
            if (array_key_exists("ID_VENDOR_GALLERY_VIDEO", $videoDetails)) {
                $vendorVideoIds[] = $videoDetails["ID_VENDOR_GALLERY_VIDEO"];
            } else {
                $vendorVideoId = $vendorGalleryManagement->insertVideo($videoDetails["NAME"], $videoDetails["YOUTUBEID"], $vendorId);
                if ($vendorVideoId > 0) {
                    $vendorVideoIds[] = $this->arData["VIDEOS"][$videoIndex]["ID_VENDOR_GALLERY_VIDEO"] = $vendorVideoId;
                }
            }
        }
        $vendorGalleryManagement->deleteVideoWhereIdNotIn($vendorVideoIds, $vendorId);
        // Clear cache files
        foreach ($arLanguages as $languageIndex => $languageDetails) {
            $cacheSearchFields = $GLOBALS['ab_path']."cache/vendor/vendor_details/".$languageDetails['ABBR']."."."vendor_details_".$this->getUserId()."_".$this->getVendorId().".htm";
            if (file_exists($cacheSearchFields)) {
                unlink($cacheSearchFields);
            }
        }
    }
}