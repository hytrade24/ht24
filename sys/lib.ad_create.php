<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once __DIR__."/lib.ads.php";
require_once __DIR__."/lib.ad_constraint.php";

class AdCreate {
    const MEDIA_MAX_IMAGES = 10;
    const MEDIA_MAX_UPLOADS = 10;
    const MEDIA_MAX_VIDEOS = 10;

    private $db;
    private $arSteps;
    private $arSystemGroups;
    private $indexStepLast;
    private $options;

    private $userId;
    private $katId;
    private $packetId;
    private $packetPaid;
    private $adData;

    private $useManufacturers;
    private $useProducts;

    function __construct(ebiz_db $db, $userId = null, $katId = null, $optionsSteps = array(), $optionsSystemGroups = array()) {
        $this->db = $db;
        $this->userId = $userId;
        $this->katId = $katId;
        $this->packetId = null;
        $this->packetPaid = false;
        $this->adData = array();
        $this->options = array("steps" => $optionsSteps, "systemgroups" => $optionsSystemGroups);
        $this->useManufacturers = true;
        $this->useProducts = true;
        if (is_array($_SESSION['EBIZ_TRADER_AD_CREATE'])) {
            $this->useManufacturers = (bool)$_SESSION['EBIZ_TRADER_AD_CREATE']['useHdbMan'];
            $this->useProducts = (bool)$_SESSION['EBIZ_TRADER_AD_CREATE']['useHdbProduct'];
            $this->loadFromArray($_SESSION['EBIZ_TRADER_AD_CREATE']["adData"]);
            $this->indexStepLast = $_SESSION['EBIZ_TRADER_AD_CREATE']["lastStep"];
        } else if ($this->katId > 0) {
            $this->loadFromArray( AdManagment::createArticleAsArray($this->katId, $this->userId) );
        } else {
            $this->initializeSystemGroups($this->options['systemgroups']);
            $this->initializeSteps($this->options['steps']);
        }
    }

    function __destruct() {
        $_SESSION['EBIZ_TRADER_AD_CREATE'] = array(
            "lastStep"      => $this->indexStepLast,
            "adData"        => $this->adData,
            "useHdbMan"     => $this->useManufacturers,
            "useHdbProduct" => $this->useProducts
        );
    }
    
    public static function getSystemGroups($adData, $options = array()) {
        global $nar_systemsettings;
        /**
         * Extend options
         */
        // Article location
        if(!$nar_systemsettings['MARKTPLATZ']['USE_ARTICLE_LOCATION']) {
            $options['locationEnabled'] = 0;
        } else {
            if(isset($adData['categoryOptions']['USE_ARTICLE_LOCATION']) && $adData['categoryOptions']['USE_ARTICLE_LOCATION'] == 0) {
                $options['locationEnabled'] = 0;
            } else {
                $options['locationEnabled'] = 1;
            }
        }
        // Base price
        if(!$nar_systemsettings['MARKTPLATZ']['USE_ARTICLE_BASEPRICE']) {
            $options['basepriceEnabled'] = 0;
        } else {
            if(isset($adData['categoryOptions']['USE_ARTICLE_BASEPRICE']) && $adData['categoryOptions']['USE_ARTICLE_BASEPRICE'] == 0) {
                $options['basepriceEnabled'] = 0;
            } else {
                $options['basepriceEnabled'] = 1;
            }
        }
        // EAN Database
        if(!$nar_systemsettings['MARKTPLATZ']['USE_ARTICLE_EAN']) {
            $options['eanEnabled'] = 0;
        } else {
            $options['eanEnabled'] = 1;
        }
        // Manufacturer/Product database
        if(!$nar_systemsettings['MARKTPLATZ']['USE_PRODUCT_DB']) {
            $options['hdbEnabled'] = 0;
        } else {
            $options['hdbEnabled'] = 1;
        }
        // Requests
        if ($adData['VERKAUFSOPTIONEN'] == 5) {
            $options['isRequest'] = 1;    // Article is request
        } else if (!isset($options['variantEnabled'])) {
            $options['isRequest'] = 0;    // Default value
        }        
        /**
         * Build system groups
         */
        $arSystemGroups = array();
        $arSystemGroups['BASE'] = array(
            array(
                'F_NAME'    => "HERSTELLER",
                'F_TYP'     => "TEXT",
                'B_NEEDED'  => 0
            ),
            array(
                'F_NAME'    => "PRODUKTNAME",
                'F_TYP'     => "TEXT",
                'B_NEEDED'  => 1
            ),
            array(
                'F_NAME'    => "FK_ARTICLE_EXT",
                'F_TYP'     => "TEXT",
                'B_NEEDED'  => 0
            ),
            array(
                'F_NAME'    => "NOTIZ",
                'F_TYP'     => "TEXT",
                'B_NEEDED'  => 0
            ),
            array(
                'F_NAME'    => "LU_LAUFZEIT",
                'F_TYP'     => "LISTE",
                'B_NEEDED'  => ($adData['ID_AD_MASTER'] > 0 ? 0 : 1)
            ),
            array(
                'F_NAME'    => "BESCHREIBUNG",
                'F_TYP'     => "TEXT",
                'B_NEEDED'  => 1
            )
        );      
        if($options['eanEnabled']) {
            $arSystemGroups['BASE'][] = array(
                'F_NAME' 	=> "EAN",
                'F_TYP'		=> "TEXT",
                'B_NEEDED'	=> 0
            );
        }    
        if($options['locationEnabled']) {
            $arSystemGroups['LOCATION'] = array(
                  array(
                      'F_NAME'    => "FK_GEO_REGION",
                      'F_TYP'     => "INT",
                      'B_NEEDED'  => 0
                  ),
                  array(
                      'F_NAME' => "LATITUDE",
                      'F_TYP' => "FLOAT",
                      'B_NEEDED' => 0
                  ),
                array(
                    'F_NAME'    => "LONGITUDE",
                    'F_TYP'     => "FLOAT",
                    'B_NEEDED'  => 0
                ),
                array(
                    'F_NAME'    => "STREET",
                    'F_TYP'     => "TEXT",
                    'B_NEEDED'  => 0
                ),
                array(
                    'F_NAME'    => "ZIP",
                    'F_TYP'     => "TEXT",
                    'B_NEEDED'  => 1
                ),
                array(
                    'F_NAME'    => "CITY",
                    'F_TYP'     => "TEXT",
                    'B_NEEDED'  => 1
                ),
                array(
                    'F_NAME'    => "ADMINISTRATIVE_AREA_LEVEL_1",
                    'F_TYP'     => "TEXT",
                    'B_NEEDED'  => 0
                ),
                array(
                    'F_NAME'    => "FK_COUNTRY",
                    'F_TYP'     => "LISTE",
                    'B_NEEDED'  => 1
                )
            );
        }
        $arSystemGroups['PRICE'] = array(
            array(
                'F_NAME' => "MENGE",
                'F_TYP' => "INT",
                'B_NEEDED' => 1
            ),
            array(
                'F_NAME' => "MOQ",
                'F_TYP' => "INT",
                'B_NEEDED' => 0
            ),
            array(
                'F_NAME' => "MIETPREISE",
                'F_TYP' => "TEXT",
                'B_NEEDED' => 0
            ),
            array(
                'F_NAME' => "PREIS",
                'F_TYP' => "FLOAT",
                'B_NEEDED' => 1
            ),
            array(
                'F_NAME' => "PSEUDOPREIS",
                'F_TYP' => "FLOAT",
                'B_NEEDED' => 0
            ),
            array(
                'F_NAME' => "B_PSEUDOPREIS_DISCOUNT",
                'F_TYP' => "CHECKBOX",
                'B_NEEDED' => 0
            ),
            array(
                'F_NAME' => "MWST",
                'F_TYP' => "CHECKBOX",
                'B_NEEDED' => 0
            ),
            array(
                'F_NAME' => "BF_CONSTRAINTS",
                'F_TYP' => "INT",
                'B_NEEDED' => 0
            ),
            array(
                'F_NAME' => "TRADE",
                'F_TYP' => "CHECKBOX",
                'B_NEEDED' => 0
            ),
            array(
                'F_NAME' => "AUTOBUY",
                'F_TYP' => "FLOAT",
                'B_NEEDED' => 0
            ),
            array(
                'F_NAME' => "AUTOCONFIRM",
                'F_TYP' => "CHECKBOX",
                'B_NEEDED' => 0
            ),
            array(
                'F_NAME' => "VERKAUFSOPTIONEN",
                'F_TYP' => "INT",
                'B_NEEDED' => 1
            )
        );
        if ($options['affiliateLink']) {
            $arSystemGroups['PRICE'][] = array(
                'F_NAME' => "AFFILIATE_LINK",
                'F_TYP' => "TEXT",
                'B_NEEDED' => 0
            );
        }
        if ($options['basepriceEnabled']) {
            array_push($arSystemGroups['PRICE'], array(
                'F_NAME' => "BASISPREIS_PREIS",
                'F_TYP' => "FLOAT",
                'B_NEEDED' => 0
            ), array(
                'F_NAME' => "BASISPREIS_MENGE",
                'F_TYP' => "INT",
                'B_NEEDED' => 0
            ), array(
                'F_NAME' => "BASISPREIS_EINHEIT",
                'F_TYP' => "LISTE",
                'B_NEEDED' => 0
            ));
        }
        if ($options['isRequest']) {
            // Invoice / delivery address
            $arSystemGroups['REQUEST'] = array(
                array(
                    'F_NAME' => "ID_USER_INVOICE",
                    'F_TYP' => "INT",
                    'B_NEEDED' => 1
                ),
                array(
                    'F_NAME' => "ID_USER_VERSAND",
                    'F_TYP' => "INT",
                    'B_NEEDED' => 1
                ),
                array(
                    'F_NAME' => "FK_PAYMENT_ADAPTER",
                    'F_TYP' => "INT",
                    'B_NEEDED' => 0
                )
            );
        } else {
            // Shipping address
            $arSystemGroups['SHIPPING'] = array(
                array(
                    'F_NAME' => "VERSANDOPTIONEN",
                    'F_TYP' => "INT",
                    'B_NEEDED' => 0
                ),
                array(
                    'F_NAME' => "VERSANDKOSTEN",
                    'F_TYP' => "FLOAT",
                    'B_NEEDED' => 0
                ),
                array(
                    'F_NAME' => "LIEFERTERMIN",
                    'F_TYP' => "TEXT",
                    'B_NEEDED' => 0
                ),
                array(
                    'F_NAME' => "VERSANDKOSTEN_INFO",
                    'F_TYP' => "TEXT",
                    'B_NEEDED' => 0
                )
            );
        }
        $arSystemGroups['LEGAL'] = array(
            array(
                'F_NAME' => "AD_AGB",
                'F_TYP' => "TEXT",
                'B_NEEDED' => 0
            ),
            array(
                'F_NAME' => "AD_WIDERRUF",
                'F_TYP' => "TEXT",
                'B_NEEDED' => 0
            )
        );
        return $arSystemGroups;
    }
    
    public function getFinishUrl($template = null) {
        if ($template === null) {
            $template = new Template("tpl/de/empty.htm");
        }
        // Default finish url
        $url = $template->tpl_uri_action("marktplatz_anzeige,".$this->getAdId().",".addnoparse(chtrans( $this->getAdTitle() )).",neu");
        // Plugin event
        $finishUrlParams = new Api_Entities_EventParamContainer(array(
            "adCreate" => $this,
            "template" => $template,
            "url" => $url
        ));
        Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_AD_CREATE_FINISH_URL, $finishUrlParams);
        if ($finishUrlParams->isDirty()) {
            $url = $finishUrlParams->getParam("url");
        }
        return $url;
    }

    /**
     * Discard any article data that may be remaining from previously creating an article.
     * @return bool true if all article data was discarded from session.
     */
    public function clearArticle() {
        $this->katId = null;
        $this->adData = array();
        $this->indexStepLast = 0;
        $this->initializeSystemGroups($this->options['systemgroups']);
        $this->initializeSteps($this->options['steps']);
        return true;
    }

    /**
     * Initializes an array of field groups that are not assigned using the article tables.
     * @param array $options    Options for creating the groups
     */
    private function initializeSystemGroups($options = array()) {
        $this->arSystemGroups = self::getSystemGroups($this->adData, $options);
    }

    /**
     * Initializes an array of steps to be done for creating a new ad.
     * @param array $options    Options for creating the steps
     */
    private function initializeSteps($options = array()) {
		global $nar_systemsettings;

        // Set options depending on the article data
        if ($this->adData['ID_AD_MASTER'] > 0) {
            $options['new'] = 0;        // Article is not new
        } else if (!isset($options['new'])) {
            $options['new'] = 1;        // Default value
        }
        if ($this->adData['isVariantCategory']) {
            $options['variant'] = 1;    // Article is variant article
        } else if (!isset($options['variant'])) {
            $options['variant'] = 0;    // Default value
        }
        if ($this->adData['isVariant']) {
            $options['variantEnabled'] = 1;    // Article is variant article
        } else if (!isset($options['variantEnabled'])) {
            $options['variantEnabled'] = 0;    // Default value
        }
        if ($this->adData['VERKAUFSOPTIONEN'] == 5) {
            $options['isRequest'] = 1;    // Article is request
        } else if (!isset($options['variantEnabled'])) {
            $options['isRequest'] = 0;    // Default value
        }        

		#var_dump($this->adData['FK_KAT'], $this->adData['categoryOptions']);
		if(!$nar_systemsettings['MARKTPLATZ']['USE_ARTICLE_LOCATION']) {
			$options['locationEnabled'] = 0;
		} else {
			if(isset($this->adData['categoryOptions']['USE_ARTICLE_LOCATION']) && $this->adData['categoryOptions']['USE_ARTICLE_LOCATION'] == 0) {
				$options['locationEnabled'] = 0;
			} else {
				$options['locationEnabled'] = 1;
			}
		}

        if($nar_systemsettings['MARKTPLATZ']['USE_PRODUCT_DB'] && $this->useProducts) {
            $options['hdbEnabled'] = 1;
        } else {
            $options['hdbEnabled'] = 0;
        }

        // Create list of steps
        $this->arSteps = array();
        if ($options['new']) {
            if (!$options['free'] && !$options['packet']) {
                require_once $GLOBALS['ab_path']."sys/packet_management.php";
                $packets = PacketManagement::getInstance($this->db);
                $ar_required = array(PacketManagement::getType("ad_once") => 1);
                $ar_required_abo = array(PacketManagement::getType("ad_abo") => 1);
                $ar_packets = array_merge($packets->order_find_collections($this->userId, $ar_required), $packets->order_find_collections($this->userId, $ar_required_abo));
                if (count($ar_packets) != 1) {
                    // Packet selection
                    $this->arSteps[] = array(
                        "IDENT"         => 'packet',
                        "TITLE"         => Translation::readTranslation("marketplace", "ad.create.packet.title", null, array(), "Anzeigenpaket"),
                        "TEMPLATE_FILE" => "my-marktplatz-neu.step.packet.htm",
                        "TEMPLATE_VARS" => array(
                            "PACKETS" => array()
                        )
                    );
                } else {
                    // Exactly one packet available, do not show packet selection
                    $this->setPacket( (int)($ar_packets[0]["ID_PACKET_ORDER"]) );
                }
            } else {
                $this->setPacket( (int)($options['packet']) );
            }

            // Category selection
            $this->arSteps[] = array(
                "IDENT"         => 'category',
                "TITLE"         => Translation::readTranslation("marketplace", "ad.create.category.title", null, array(), "Kategorie"),
                "TEMPLATE_FILE" => "my-marktplatz-neu.step.category.htm",
                "TEMPLATE_VARS" => array(
                    "CATEGORIES" => array(
                        "TEMPLATE"          => "my-marktplatz-neu.step.category.row.htm",
                        "TEMPLATE_LAYER"    => "my-marktplatz-neu.step.category.layer.htm"
                    )
                )
            );
            // HDB Search
            if($options['hdbEnabled'] == 1) {
                $this->arSteps[] = array(
                        "IDENT" => 'hdb',
                        "TITLE" => Translation::readTranslation("marketplace", "ad.create.hdb.title", NULL, array(), "Produktdatenbank"),
                        "TEMPLATE_FILE" => "my-marktplatz-neu.step.hdb.htm",
                        "TEMPLATE_VARS" => array(
                                "HDB" => array()
                        )
                );
            }
        }
        // Article data
        $this->arSteps[] = array(
            "IDENT"         => 'article_base',
            "TITLE"         => Translation::readTranslation("marketplace", "ad.create.article.title", null, array(), "Artikel-Bezeichnung"),
            "TEMPLATE_FILE" => "my-marktplatz-neu.step.article.htm",
            "TEMPLATE_VARS" => array(
                "ARTICLE_FIELDS" => array(
                    "GROUPS" => array("BASE")
                )
            )
        );
        if ($this->katId === null) {
            // Article details
            $this->arSteps[] = array(
                "IDENT"         => 'article_details',
                "TITLE"         => Translation::readTranslation("marketplace", "ad.create.details.title", null, array(), "Artikel-Details"),
                "TEMPLATE_FILE" => "my-marktplatz-neu.step.details.htm",
                "TEMPLATE_VARS" => array(
                    "ARTICLE_FIELDS" => array(
                        "GROUPS" => array_merge($this->getGroups(array('DEFAULT', 'TABLE')), array('PRICE', 'SHIPPING', 'LEGAL'))
                    )
                )
            );
        } else {
            $arArticleTabs = $this->getFieldTabs();
            $tabIndex = 1;
            foreach ($arArticleTabs as $arTab) {
                $arGroups = $arTab["GROUPS"];
                if ($arTab["B_DEFAULT"]) {
                    $arGroups[] = 'PRICE';
                    $arGroups[] = 'SHIPPING';
                    $arGroups[] = 'LEGAL';
                }
                // Article details
                $this->arSteps[] = array(
                    "IDENT"         => 'article_details'.$tabIndex++,
                    "TITLE"         => $arTab["V1"],
                    "TEMPLATE_FILE" => "my-marktplatz-neu.step.details.htm",
                    "TEMPLATE_VARS" => array(
                        "ARTICLE_FIELDS" => array(
                            "GROUPS" => $arGroups
                        )
                    )
                );
            }
        }
        if ($options['variant']) {
            // Article details
            $this->arSteps[] = array(
                "IDENT"         => 'article_variants',
                "TITLE"         => Translation::readTranslation("marketplace", "ad.create.variants.title", null, array(), "Artikel-Varianten"),
                "TEMPLATE_FILE" => "my-marktplatz-neu.step.variants.htm",
                "TEMPLATE_VARS" => array(
                    "VARIANTS" => array()
                )
            );
        }
        if ($options['isRequest']) {
            // Request details (invoice- & delivery address & payment, ...)
            $this->arSteps[] = array(
              "IDENT" => 'article_request',
              "TITLE" => Translation::readTranslation("marketplace", "ad.create.request.title", null, array(), "Gesuch-Details"),
              "TEMPLATE_FILE" => "my-marktplatz-neu.step.request.htm",
              "TEMPLATE_VARS" => array(
                "ARTICLE_FIELDS" => array(
                  "GROUPS" => array("REQUEST")
                )
              )
            );
        } else if ($options['locationEnabled']) {
          // Article location
          $this->arSteps[] = array(
            "IDENT"         => 'article_location',
            "TITLE"         => Translation::readTranslation("marketplace", "ad.create.location.title", null, array(), "Artikel-Standort"),
            "TEMPLATE_FILE" => "my-marktplatz-neu.step.location.htm",
            "TEMPLATE_VARS" => array(
              "ARTICLE_FIELDS" => array(
                "GROUPS" => array("LOCATION")
              )
            )
          );
        }
        // Media uploads
        $this->arSteps[] = array(
            "IDENT"         => 'article_media',
            "TITLE"         => Translation::readTranslation("marketplace", "ad.create.media.title", null, array(), "Medien"),
            "TEMPLATE_FILE" => "my-marktplatz-neu.step.media.htm",
            "TEMPLATE_VARS" => array(
                "ARTICLE_MEDIA" => array()
            )
        );
        // Confirm input
        $this->arSteps[] = array(
            "IDENT"         => 'article_confirm',
            "TITLE"         => Translation::readTranslation("marketplace", "ad.create.confirm.title", null, array(), "Eingaben bestätigen"),
            "MAXIMIZED"     => true,
            "TEMPLATE_FILE" => "my-marktplatz-neu.step.confirm.htm",
            "TEMPLATE_VARS" => array(
                "ARTICLE_CONFIRM" => array()
            )
        );
        // Plugin event
        $productEditParams = new Api_Entities_EventParamContainer(array(
            "adCreate" => $this,
            "steps" => $this->arSteps
        ));
        Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_AD_CREATE_INIT_STEPS, $productEditParams);
        if ($productEditParams->isDirty()) {
            $this->arSteps = $productEditParams->getParam("steps");
        }
    }

    /**
     * This function checks whether some fields are required or not depending on the settings of other fields
     * @param $arArticle        Array containing all article fields
     * @param $groupId          ID of the fields input group
     * @param $fieldName        Name of the field
     * @param $fieldType        Type of the field
     * @param $neededByDefault  Fallback return value
     * @return bool             true if required, false if not.
     */
    protected function isFieldRequired(&$arArticle, $groupId, $fieldName, $fieldType, $neededByDefault) {
        if (!$this->adData["isSalesCategory"]) {
            if ($groupId == 'PRICE') {
                return false;
            }
        }

        if (($groupId == 'PRICE') && ($fieldName == 'MENGE')) {
            return ($arArticle['VERKAUFSOPTIONEN'] == 4 ? false : $neededByDefault);
        }
        if (($groupId == 'PRICE') && ($fieldName == 'PREIS')) {
            return ($arArticle['VERKAUFSOPTIONEN'] === 0 ? $neededByDefault : false);
        }
        if (($groupId == 'SHIPPING') && ($fieldName == 'VERSANDKOSTEN')) {
            if (($arArticle['VERKAUFSOPTIONEN'] == 4) || ($arArticle['VERKAUFSOPTIONEN'] == 5)) {
                return false;
            }
            return ($arArticle['VERSANDOPTIONEN'] == 3 ? true : $neededByDefault);
        }
        return $neededByDefault;
    }

    /**
     * Checks whether the given step should be be displayed maximized (without the step overview aside)
     * @param $stepIndex
     */
    public function isStepMaximized($stepIndex) {
        if (!array_key_exists($stepIndex, $this->arSteps)) {
            return false;   // Step not found
        }
        if (array_key_exists("MAXIMIZED", $this->arSteps[$stepIndex])
            && $this->arSteps[$stepIndex]["MAXIMIZED"]) {
            return true;
        } else {
            return false;
        }
    }
    public function isVariantArticle() {
        return !empty($this->adData["_VARIANTS_FIELDS"]);
    }
    
    /**
     * Creates the article by inserting it into database and optionally enabling it.
     * @param bool $enable  true if the article should be enabled after adding it to database
     * @return bool|uint    article id if successfull or false if creating the article failed
     */
    protected function create($enable = true, &$errors = array()) {
        $arFields = $this->getFields();
        foreach ($arFields as $index => $arField) {
            $name = $arField["F_NAME"];
            $type = $arField["F_TYP"];
            $value = $this->adData[$name];
            switch ($type) {
                case 'MULTICHECKBOX':
                case 'MULTICHECKBOX_AND':
                    $value = explode("x", trim($value, "x"));
                    break;
                case 'VARIANT':
                    $value = $this->adData["_VARIANTS_FIELDS"][$name];
                    break;
            }
            $groupId = $this->getFieldGroupIdByName($name);
            $isNeeded = $this->isFieldRequired($this->adData, $groupId, $name, $type, $arField["B_NEEDED"]);
            $validateResult = $this->validateField($name, $value, $isNeeded, $type);
            if (!$validateResult["valid"]) {
                $errors[$name] = $validateResult["error_msg"];
            }
        }
        if (empty($errors)) {
            $success = false;
            $idArticle = false;
            if ($this->adData['ID_AD_MASTER'] > 0) {
                // Existing article, update
                $idArticle = $this->adData['ID_AD_MASTER'];
                AdManagment::updateArticleFromArray($this->adData, $this->userId, $enable, $success);
            } else {
                // New article, create
                $idArticle = AdManagment::createArticleFromArray($this->adData, $this->userId, $enable, $success);
                // Update statistic
                AdManagment::logCreateArticle($this->userId);
            }
            if ($idArticle > 0) {
                
                // Clear volatile cache
                $article = Api_Entities_MarketplaceArticle::getById($idArticle);
                if ($article instanceof Api_Entities_MarketplaceArticle) {
                    $article->createJsonCache();
                    $article->clearVolatileCache();
                }
                $this->createVariants($idArticle);
                $this->loadFromDatabase($idArticle);
                if (!$success) {
                    $errors["_CONFIRM_ERROR"] = "Fehler beim aktivieren der Anzeige!";
                }
            } else {
                $errors["_CONFIRM_ERROR"] = "Fehler beim Speichern der Anzeige!";
            }
            return $success;
        } else {
            return false;
        }
    }

    protected function createVariants($idArticle) {
        /**
         * Variants
         */
        $arVariantIds = array();
        if (!empty($this->adData["_VARIANTS"])) {
            $idVariantDefault = false;
            $arVariants = $this->adData["_VARIANTS"];
            foreach ($arVariants as $arVariantRaw) {
                $arVariant = array(
                    'FK_AD_MASTER'  => $idArticle,
                    'STATUS'        => $arVariantRaw['STATUS'],
                    'PREIS'         => $arVariantRaw['PREIS'],
                    'MENGE'         => $arVariantRaw['MENGE'],
                );
                // Create / update variant
                if ($arVariantRaw['ID_AD_VARIANT'] > 0) {
                    // Update existing
                    $idVariant = $arVariant['ID_AD_VARIANT'] = (int)$arVariantRaw['ID_AD_VARIANT'];
                    $this->db->update("ad_variant", $arVariant);
                } else {
                    $idVariant = $this->db->update("ad_variant", $arVariant);
                }
                $arVariantIds[] = $idVariant;
                // Add links to variant values (if not present yet)
                foreach ($arVariantRaw["FIELDS"] as $fieldIndex => $arField) {
                    $arField = array_merge( $this->getFieldByName($arField["F_NAME"]), $arField );
                    $arVariantValue = array(
                        'FK_AD_VARIANT'     => $idVariant,
                        'FK_FIELD_DEF'      => $arField['ID_FIELD_DEF'],
                        'F_NAME'            => $arField['F_NAME'],
                        'FK_LISTE_VALUES'   => $arField['LIST_VALUE']
                    );
                    $idVariantValue = $this->db->update("ad_variant2liste_values", $arVariantValue);
                }

                if ($arVariantRaw['IS_DEFAULT']) {
                    $idVariantDefault = $idVariant;
                }
            }
            // Delete removed variants
            $queryDeleted = "SELECT ID_AD_VARIANT FROM `ad_variant` WHERE ID_AD_VARIANT NOT IN (".implode(", ", $arVariantIds).") AND FK_AD_MASTER=".$idArticle;
            $arVariantsDelete = array_keys($this->db->fetch_nar($queryDeleted));
            $this->db->querynow("DELETE FROM `ad_variant2liste_values` WHERE FK_AD_VARIANT IN (".implode(", ", $arVariantsDelete).")");
            $this->db->querynow("DELETE FROM `ad_variant` WHERE ID_AD_VARIANT IN (".implode(", ", $arVariantsDelete).")");
            // Set default
            if ($idVariantDefault !== false) {
                // Update default variant
                $this->db->querynow("UPDATE `ad_master` SET FK_AD_VARIANT='".$idVariantDefault."' WHERE ID_AD_MASTER=".$idArticle);
            }
        }
    }

    /**
     * Delete the given image from article.
     * @param $imageIndex   int     Index of the image to be deleted
     * @return              bool    true if the image was deleted successfully
     */
    public function deleteImage($imageIndex) {
        if (count($this->adData['images']) < ($imageIndex-1)) {
            // Out of bounds
            return false;
        }
        // Write to session
        array_splice($this->adData['images'], $imageIndex, 1);
        if (count($this->adData['images']) > 0) {
            $hasDefault = false;
            // Ensure one default
            foreach ($this->adData['images'] as $index => $ar_image) {
                if ($this->adData['images'][$index]['IS_DEFAULT']) $hasDefault = true;
            }
            if (!$hasDefault) {
                // No default! Set first one...
                $this->adData['images'][0]['IS_DEFAULT'] = 1;
            }
        }
        return true;
    }

    /**
     * Delete the given file from article.
     * @param $fileIndex    int     Index of the file to be deleted
     * @return              bool    true if the file was deleted successfully
     */
    public function deleteFile($fileIndex) {
        if (count($this->adData['uploads']) < ($fileIndex-1)) {
            // Out of bounds
            return false;
        }
        // Write to session
        array_splice($this->adData['uploads'], $fileIndex, 1);
        return true;
    }

    /**
     * Delete the given video from article.
     * @param $videoIndex   int     Index of the video to be deleted
     * @return              bool    true if the video was deleted successfully
     */
    public function deleteVideo($videoIndex) {
        if (count($this->adData['videos']) < ($videoIndex-1)) {
            // Out of bounds
            return false;
        }
        // Write to session
        array_splice($this->adData['videos'], $videoIndex, 1);
        return true;
    }

    /**
     * Returns the id of the currently active ad (0 if new)
     * @return int  id of the ad
     */
    public function getAdId() {
        return (int)$this->adData["ID_AD_MASTER"];
    }

    /**
     * Returns the title of the currently active ad (0 if new)
     * @return string   title of the ad
     */
    public function getAdTitle() {
        return $this->adData["PRODUKTNAME"];
    }

    /**
     * Returns the manufacturer and the title ("" if new) of the currently active ad
     * @return string
     */
    public function getAdTitleLong() {
        $title="";
        if(array_key_exists("PRODUKTNAME",$this->adData))
        {
            if($this->adData["FK_MAN"] > 0)
            {
                $title=$this->db->fetch_atom("SELECT NAME FROM manufacturers WHERE ID_MAN=".(int)$this->adData["FK_MAN"])." ".$this->adData["PRODUKTNAME"];
            }
            else
            {
                $title=$this->adData["PRODUKTNAME"];
            }
        }
        return $title;
    }



    /**
     * Returns the id of the used article table (resolved using category)
     * @return int|null
     */
    public function getAdTableId() {
        if ($this->katId > 0) {
            $table = $this->db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".$this->katId);
            $idTable = $this->db->fetch_atom("SELECT ID_TABLE_DEF FROM `table_def` WHERE T_NAME='".mysql_real_escape_string($table)."'");
            return $idTable;
        } else {
            return null;
        }
    }

    /**
     * Returns the full article data of the current article
     * @return array
     */
    public function getAdData() {
      return $this->adData;
    }

    /**
     * Returns the id of the selected category
     * @return int|null
     */
    public function getCategoryId() {
        return $this->katId;
    }
    
    public function getCategoryName() {
        if ($this->katId > 0) {
            require_once $GLOBALS["ab_path"]."sys/lib.pub_kategorien.php";
            $katRoot = 1;
            $kat = new TreeCategories("kat", $katRoot);
            $katDetail = $kat->element_read($this->katId);
            if (is_array($katDetail)) {
                return $katDetail["V1"];
            }
        }
        return "";
    }

    /**
     * @param $fieldName
     * @return mixed|null
     */
    public function getCustomAdData($fieldName) {
        if (array_key_exists($fieldName, $this->adData)) {
            return $this->adData[$fieldName];
        } else {
            return null;
        }
    }
    
    public function getJsonAdData($fieldName) {
        if (array_key_exists("JSON_ADDITIONAL", $this->adData) && array_key_exists($fieldName, $this->adData["JSON_ADDITIONAL"])) {
            return $this->adData["JSON_ADDITIONAL"][$fieldName];
        } else {
            return null;
        }        
    }

    /**
     * Returns a field by name if found. Contains name, type and wheter its required.
     * @param   string $fieldName   Name of the field to be found
     * @return  array|bool          The field with name, type and whether its required as array or false if not found.
     */
    public function getFieldByName($fieldName) {
        // Look for matching field in system groups
        foreach ($this->arSystemGroups as $groupName => $arFields) {
            foreach ($arFields as $fieldIndex => $arField) {
                if ($arField["F_NAME"] == $fieldName) {
                    return $arField;
                }
            }
        }
        // No system field found, search in database
        global $langval;
        $arField = $this->db->fetch1("
                SELECT
                    f.ID_FIELD_DEF, f.F_NAME, f.F_TYP, IFNULL(kf.B_NEEDED,f.B_NEEDED) AS B_NEEDED, s.*
				FROM `kat` k
				LEFT JOIN `table_def` t ON t.T_NAME=k.KAT_TABLE
				LEFT JOIN `field_def` f ON f.FK_TABLE_DEF=t.ID_TABLE_DEF
				LEFT JOIN `kat2field` kf ON kf.FK_KAT=k.ID_KAT AND kf.FK_FIELD=f.ID_FIELD_DEF
                LEFT JOIN `string_field_def` s ON s.S_TABLE='field_def' AND s.FK=f.ID_FIELD_DEF
					AND s.BF_LANG=if(f.BF_LANG_FIELD_DEF & ".$langval.", ".$langval.", 1 << floor(log(f.BF_LANG_FIELD_DEF+0.5)/log(2)))
				WHERE k.ID_KAT=".(int)$this->katId." AND kf.B_ENABLED=1 AND f.B_ENABLED=1
				    AND f.F_NAME='".mysql_real_escape_string($fieldName)."'");
        return $arField;
    }

    /**
     * Returns a list of all fields with name, type and whether its required.
     * @param   int $idFieldGroup   Field group to be read
     * @return array    List of all fields with name, type and whether its required.
     */
    public function getFields($idFieldGroup = null) {
        if (array_key_exists($idFieldGroup, $this->arSystemGroups)) {
            // System group, read from array
            return $this->arSystemGroups[$idFieldGroup];
        } else {
            // Regular group, read from database
            global $langval;
            $arFields = $this->db->fetch_table("
                SELECT
                    f.F_NAME, f.F_TYP, f.FK_LISTE, IFNULL(kf.B_NEEDED,f.B_NEEDED) AS B_NEEDED, f.IS_SPECIAL, s.*
				FROM `kat` k
				LEFT JOIN `table_def` t ON t.T_NAME=k.KAT_TABLE
				LEFT JOIN `field_def` f ON f.FK_TABLE_DEF=t.ID_TABLE_DEF
				LEFT JOIN `kat2field` kf ON kf.FK_KAT=k.ID_KAT AND kf.FK_FIELD=f.ID_FIELD_DEF
                LEFT JOIN `string_field_def` s ON s.S_TABLE='field_def' AND s.FK=f.ID_FIELD_DEF
					AND s.BF_LANG=if(f.BF_LANG_FIELD_DEF & ".$langval.", ".$langval.", 1 << floor(log(f.BF_LANG_FIELD_DEF+0.5)/log(2)))
				WHERE k.ID_KAT=".(int)$this->katId." AND kf.B_ENABLED=1 AND f.B_ENABLED=1
				    AND f.FK_FIELD_GROUP".($idFieldGroup === null ? " IS NULL" : "=".(int)$idFieldGroup));
            return $arFields;
        }
    }

    /**
     * Get the defined input-tabs/-pages for the current category
     * @return array
     */
    public function getFieldTabs() {
        global $langval;
        $table = $this->db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".$this->katId);
        $idTable = $this->db->fetch_atom("SELECT ID_TABLE_DEF FROM `table_def` WHERE T_NAME='".mysql_real_escape_string($table)."'");
        $arTabIds = array_keys($this->db->fetch_nar(
            "SELECT FK_FIELD_TAB FROM `field_group` WHERE FK_TABLE_DEF=".$idTable." AND FK_FIELD_TAB IS NOT NULL GROUP BY FK_FIELD_TAB"
        ));
        $arTabs = array();
        $defaultSet = false;
        if (!empty($arTabIds)) {
            $arTabs = $this->db->fetch_table($q="
                SELECT
                    t.*, s.*
				FROM `field_tab` t
                LEFT JOIN `string_field_tab` s ON s.S_TABLE='field_tab' AND s.FK=t.ID_FIELD_TAB
					AND s.BF_LANG=if(t.BF_LANG_FIELD_TAB & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_FIELD_TAB+0.5)/log(2)))
                JOIN `field_group` g ON g.FK_FIELD_TAB=t.ID_FIELD_TAB
                JOIN `field_def` f ON f.FK_FIELD_GROUP=g.ID_FIELD_GROUP AND f.B_ENABLED=1
				JOIN `kat2field` kf ON kf.FK_KAT=".$this->katId." AND kf.FK_FIELD=f.ID_FIELD_DEF AND kf.B_ENABLED=1
				WHERE t.ID_FIELD_TAB IN (".implode(", ", $arTabIds).")
				GROUP BY t.ID_FIELD_TAB
				HAVING count(f.ID_FIELD_DEF) > 0
				ORDER BY F_ORDER");
            foreach ($arTabs as $tabIndex => $tabData) {
                $tabId = $tabData['ID_FIELD_TAB'];
                $arTabs[$tabIndex]['GROUPS'] = array_keys($this->db->fetch_nar("
                    SELECT ID_FIELD_GROUP FROM `field_group`
                    WHERE FK_TABLE_DEF=".$idTable." AND FK_FIELD_TAB=".(int)$tabId." ORDER BY F_ORDER ASC"
                ));
                if ($tabData['B_DEFAULT']) {
                    array_unshift($arTabs[$tabIndex]['GROUPS'], null);
                    $defaultSet = true;
                }
            }

        }

        // Add default tab
        array_unshift($arTabs, array(
            'V1'        => Translation::readTranslation("marketplace", "ad.create.details.title", null, array(), "Artikel-Details"),
            'B_DEFAULT' => ($defaultSet ? 0 : 1),
            'GROUPS'    => array_keys(
                $this->db->fetch_nar("SELECT ID_FIELD_GROUP FROM `field_group` WHERE FK_TABLE_DEF=".$idTable." AND FK_FIELD_TAB IS NULL ORDER BY F_ORDER ASC")
            )
        ));
        if (!$defaultSet) {
            array_unshift($arTabs[0]['GROUPS'], null);
        }
        return $arTabs;
    }

    public function getFieldGroupIdByName($fieldName) {
        foreach ($this->arSystemGroups as $groupId => $arFields) {
            foreach ($arFields as $fieldIndex => $arField) {
                if ($fieldName == $arField["F_NAME"]) {
                    return $groupId;
                }
            }
        }
        return null;
    }

    /**
     * Get the list of the current categories field groups.
     * @return array    List of group ids (default group is null)
     */
    public function getGroups($types = array('SYSTEM', 'DEFAULT', 'TABLE')) {
        $arFieldGroups = array();
        foreach ($types as $index => $type) {
            switch ($type) {
                case 'SYSTEM':
                    // System field groups
                    foreach ($this->arSystemGroups as $idGroup => $arGroup) {
                        $arFieldGroups[] = $idGroup;
                    }
                    break;
                case 'DEFAULT':
                    // Default field group
                    $arFieldGroups[] = null;
                    break;
                case 'TABLE':
                    if ($this->katId !== null) {
                        // Groups by article table
                        $table = $this->db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".$this->katId);
                        $idTable = $this->db->fetch_atom("SELECT ID_TABLE_DEF FROM `table_def` WHERE T_NAME='".mysql_real_escape_string($table)."'");
                        $arFieldGroupsAdditional = array_keys($this->db->fetch_nar("SELECT ID_FIELD_GROUP FROM `field_group` WHERE FK_TABLE_DEF=".$idTable." ORDER BY F_ORDER ASC"));
                        foreach ($arFieldGroupsAdditional as $indexGroup => $idGroup) {
                            $arFieldGroups[] = $idGroup;
                        }
                    }
                    break;
            }
        }
        return $arFieldGroups;
    }

    public function getMediaUsage() {
        global $nar_systemsettings;
        $ar_packet_usage = $this->getPacketUsage();
        $image_count = count($this->adData['images']);
        $video_count = count($this->adData['videos']);
        $upload_count = count($this->adData['uploads']);
        $images_left = (($ar_packet_usage["images_available"] + $image_count) > self::MEDIA_MAX_IMAGES ? self::MEDIA_MAX_IMAGES - $image_count : $ar_packet_usage["images_available"] - $image_count);
        $images_limit = $image_count + $images_left;
        $videos_left = (($ar_packet_usage["videos_available"] + $video_count) > self::MEDIA_MAX_VIDEOS ? self::MEDIA_MAX_VIDEOS - $video_count : $ar_packet_usage["videos_available"] - $video_count);
        $videos_limit = $video_count + $videos_left;
        $upload_formats = $nar_systemsettings['MARKTPLATZ']['UPLOAD_TYPES'];
        $uploads_left = (($ar_packet_usage["downloads_available"] + $upload_count) > self::MEDIA_MAX_UPLOADS ? self::MEDIA_MAX_UPLOADS - $upload_count : $ar_packet_usage["downloads_available"] - $upload_count);
        $uploads_limit = $upload_count + $uploads_left;
        return array(
            "ads_available"			=> $ar_packet_usage["ads_available"] - ($this->adData["ID_AD_MASTER"] > 0 ? 0 : 1),
            "images_count"          => $image_count,
            "images_free"           => $nar_systemsettings["MARKTPLATZ"]["FREE_IMAGES"],
            "images_available"		=> $images_left,
            "images_limit"          => $images_limit,
            "videos_count"          => $video_count,
            "videos_free"           => $nar_systemsettings["MARKTPLATZ"]["FREE_VIDEOS"],
            "videos_available"		=> $videos_left,
            "videos_limit"          => $videos_limit,
            "downloads_count"       => $video_count,
            "downloads_free"        => $nar_systemsettings["MARKTPLATZ"]["FREE_UPLOADS"],
            "downloads_available"	=> $uploads_left,
            "downloads_limit"       => $uploads_limit,
            "downloads_formats"     => $upload_formats
        );
    }

    /**
     * Returns a list of all images
     * @return array
     */
    public function getImages() {
        return $this->adData['images'];
    }

    public function getPacketUsage() {
        global $ab_path, $nar_systemsettings;
        require_once $ab_path."sys/packet_management.php";
        $packets = PacketManagement::getInstance($this->db);
        $id_packet_order = (int)$this->adData["FK_PACKET_ORDER"];
        $ar_packet_usage = array();
        if ($id_packet_order > 0) {
            $order = $packets->order_get($id_packet_order);
            $ar_packet_usage_raw = $order->getPacketUsage(0);
            $ar_packet_usage = array(
                "ads_available"			=> $ar_packet_usage_raw["ads_available"],
                "images_available"		=> $ar_packet_usage_raw["images_available"],
                "videos_available"		=> $ar_packet_usage_raw["videos_available"],
                "downloads_available"	=> $ar_packet_usage_raw["downloads_available"]
            );
        } else {
            $ar_packet_usage = array(
                "ads_available"			=> $nar_systemsettings["MARKTPLATZ"]["FREE_ADS"],
                "images_available"		=> $nar_systemsettings["MARKTPLATZ"]["FREE_IMAGES"],
                "videos_available"		=> $nar_systemsettings["MARKTPLATZ"]["FREE_VIDEOS"],
                "downloads_available"	=> $nar_systemsettings["MARKTPLATZ"]["FREE_UPLOADS"]
            );
        }
        return $ar_packet_usage;
    }

    /**
     * Gets a step by the numeric index or string ident.
     * @param $stepIndex    Numeric index or ident of the step to be read
     * @return array        List of steps for creating the new ad
     */
    public function getStep($stepIndex) {
        if (!preg_match("/^[0-9]+$/", $stepIndex)) {
            // Not numeric, find ident
            $stepIndex = $this->getStepIndex($stepIndex);
        }
        return $this->arSteps[$stepIndex];
    }

    /**
     * Gets the index of a step by ident
     * @param $stepIndent   Ident of the step to be found
     * @return array        List of steps for creating the new ad
     */
    public function getStepIndex($stepIndent) {
        foreach ($this->arSteps as $curIndex => $curStep) {
            if ($curStep["IDENT"] == $stepIndent) {
                $stepIndent = $curIndex;
                break;
            }
        }
        return $stepIndent;
    }

    /**
     * Gets the full list of steps for creating a new article
     * @return array    List of steps for creating the new ad
     */
    public function getStepList() {
        foreach ($this->arSteps as $indexStep => $arStep) {
            $this->arSteps[$indexStep]["ENABLED"] = ($this->indexStepLast >= $indexStep ? 1 : 0);
        }
        return $this->arSteps;
    }

    /**
     * @param $arFile           File upload from the $_FILES array e.g.: $_FILES["UPLOAD_IMAGE"]
     * @param array $error      If an error occurs the error reason will be appended to this array
     * @param array $arImage    Will be assigned the array containing all image data if successful
     * @return bool             true if the image upload was successful
     */
    public function handleImageUpload($arFile, &$error = array(), &$arImage = array()) {
        if (!is_array($error)) {
            $error = array();
        }
        $arUsage = $this->getMediaUsage();
        if ($arUsage['images_available'] <= 0) {
            $error[] = Translation::readTranslation('marketplace', 'ad.create.image.limit.reached', null, array(), "Sie haben die maximale Anzahl an Bilder erreicht!");
            return false;
        }
        if ($arFile["error"] == UPLOAD_ERR_OK) {
            // Keep in temp and write to session
            global $ab_path;
            require_once $ab_path."sys/lib.image.php";
            $tmp_dir = sys_get_temp_dir();
            $tmp_name = $arFile["tmp_name"];
            $name = $arFile["name"];
            $img_thumb = new image(12, $tmp_dir, true);
            $img_thumb->check_file(array("tmp_name"=>$tmp_name,"name"=>$name));
            if (empty($img_thumb->err)) {
                $arImage = array(
                    'FK_AD'         => 0,
                    'CUSTOM'        => 1,
                    'IS_DEFAULT'    => (empty($this->adData['images']) ? true : false),
                    'TMP'           => $img_thumb->img,
                    'TMP_THUMB'     => $img_thumb->thumb,
                    'FILENAME'      => $arFile["name"],
                    'TYPE'          => $arFile["type"]
                );
                // Add to session
                $arImage['INDEX'] = count($this->adData['images']);
                $this->adData['images'][] = $arImage;
                return true;
            } else {
                $error[] = Translation::readTranslation('marketplace', 'ad.create.image.invalid', null, array(), "Ungültiges Dateiformat oder Bild beschädigt!");
                return false;
            }
        } else {
            $error[] = "UPLOAD_FILE_FAILED_SERVER";
            return false;
        }
    }

    /**
     * @param $arFile       File upload from the $_FILES array e.g.: $_FILES["UPLOAD_FILE"]
     * @param array $error  If an error occurs the error reason will be appended to this array
     * @return bool     true if the file upload was successful
     */
    public function handleFileUpload($arFile, &$error = array(), &$arUpload = array()) {
        global $nar_systemsettings;
        if (!is_array($error)) {
            $error = array();
        }
        $arUsage = $this->getMediaUsage();
        if ($arUsage['downloads_available'] <= 0) {
            $error[] = Translation::readTranslation('marketplace', 'ad.create.document.limit.reached', null, array(), "Sie haben die maximale Anzahl an Dokumenten erreicht!");
            return false;
        }
        if ($arFile["error"] == UPLOAD_ERR_OK) {
            // Keep in temp and write to session
            $filename = $arFile['name'];
            $hack = explode(".", $filename);
            $n = count($hack)-1;
            $ext = $hack[$n];
            $filename = preg_replace("/(^.*)(\.".$ext."$)/si", "$1", $filename);
            // Check extension
            $upload_formats = $nar_systemsettings['MARKTPLATZ']['UPLOAD_TYPES'];
            $allowed = explode(',', $upload_formats);
            if(!in_array($ext, $allowed)) {
                $error[] = Translation::readTranslation('marketplace', 'ad.create.document.format.wrong', null, array(), "Ungültiges Dateiformat!");
                return false;
            }
            // Proceed with upload
            $temp_file = tempnam(sys_get_temp_dir(), 'AdUpload');
            move_uploaded_file($arFile['tmp_name'], $temp_file);
            $arUpload = $arFile;
            $arUpload['FK_AD'] = 0;
            $arUpload['EXT'] = $ext;
            $arUpload['TMP'] = $temp_file;
            $arUpload['FILENAME'] = $filename;
            //$arUpload['IS_FREE'] = "1";
            // Add to session
            $arUpload['INDEX'] = count($this->adData['uploads']);
            $this->adData['uploads'][] = $arUpload;
            return true;
        } else {
            $error[] = "UPLOAD_FILE_FAILED_SERVER";
            return false;
        }
    }

    /**
     * Adds a youtube video to the article
     * @param $youtubeUrl   URL of the Youtube video
     * @return bool         true if the video was successfully added
     */
    public function handleVideoUpload($youtubeUrl, &$video_data = null) {
        $arUsage = $this->getMediaUsage();
        if ($arUsage['videos_available'] <= 0) {
            $error[] = Translation::readTranslation('marketplace', 'ad.create.video.limit.reached', null, array(), "Sie haben die maximale Anzahl an Videos erreicht!");
            return false;
        }
        require_once $GLOBALS["ab_path"]."sys/lib.youtube.php";
        $code = Youtube::ExtractCodeFromURL($youtubeUrl);
        if ($code != false && !in_array($code, array_column($this->adData['videos'], "CODE"))) {
            $video_data = array(
                "FK_AD"       => (int)$this->adData["ID_AD_MASTER"],
                "INDEX"       => count($this->adData['videos']),
                "CODE"	      => $code
            );
            $this->adData['videos'][] = $video_data;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Whether the currently selected packet is paid (price > 0) or free.
     * @return bool     true if price of the packet is > 0
     */
    public function isPacketPaid() {
        return $this->packetPaid;
    }

    /**
     * Load the article data from the given array.
     * @param $adData   array   Article data as an array
     * @return          bool    true if the article data was successfully loaded
     */
    public function loadFromArray($adData) {
        // Get user data
        $arUser = get_user($this->userId);
        $arUser["ZIP"] = $arUser["PLZ"];
        $arUser["CITY"] = $arUser["ORT"];
        $adData = array_merge($arUser, $adData);
        // Get article data
        $this->adData = AdConstraintManagement::appendAdContraintMapping($adData);
        if (!is_array($this->adData["JSON_ADDITIONAL"])) {
            $this->adData["JSON_ADDITIONAL"] = json_decode($this->adData["JSON_ADDITIONAL"], true);
            if ($this->adData["JSON_ADDITIONAL"] === null) {
                $this->adData["JSON_ADDITIONAL"] = array();
            }
        }
        if ($this->adData["FK_KAT"] > 0) {
            $this->katId = (int)$this->adData["FK_KAT"];
        }
        if ($this->adData["FK_PACKET_ORDER"] > 0) {
            $this->setPacket( (int)$this->adData["FK_PACKET_ORDER"] );
        }
        $this->initializeSystemGroups($this->options['systemgroups']);
        $this->initializeSteps($this->options['steps']);
        return true;
    }

    /**
     * Load the article data from the given array.
     * @param $idAd     int     Id of the article to be loaded
     * @return          bool    true if the article was successfully loaded
     */
    public function loadFromDatabase($idAd) {
        if ($this->userId === null) {
            return false;
        }
        // Article base data
        $arAdMaster = $this->db->fetch1("SELECT * FROM `ad_master` WHERE ID_AD_MASTER=".$idAd);
        if (!is_array($arAdMaster)) {
            return false;
        }
		if((int)$arAdMaster['FK_USER'] !== (int)$this->userId) {
			throw new Exception("Load Article is not permitted. You are not the owner");
		}
        if ($arAdMaster["FK_PACKET_ORDER"]) {
            $this->packetId = $arAdMaster["FK_PACKET_ORDER"];
        }
        // Category table
        $table = $this->db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".$arAdMaster["FK_KAT"]);
        // Article full data
        $arAdTable = $this->db->fetch1("SELECT * FROM `".$table."` WHERE ID_".strtoupper($table)."=".$idAd);
        // Decode additional json data
		if (array_key_exists("JSON_ADDITIONAL", $arAdMaster)) {
			$arAdTable["JSON_ADDITIONAL"] = json_decode($arAdMaster["JSON_ADDITIONAL"], true);
		}
        // Merge data
        if (is_array($arAdTable)) {
            $arAdTable = array_merge($arAdMaster, $arAdTable);
        } else {
            $arAdTable = $arAdMaster;
        }
        // Read manufacturer name
        if ($arAdTable["FK_MAN"] > 0) {
            $arAdTable["HERSTELLER"] = $this->db->fetch_atom("SELECT NAME FROM manufacturers WHERE ID_MAN=".(int)$arAdTable["FK_MAN"]);
        }
        // Use runtime and note from master entry
        $arAdTable["LU_LAUFZEIT"] = $arAdMaster["LU_LAUFZEIT"];
        $arAdTable["NOTIZ"] = $arAdMaster["NOTIZ"];
        // User data
        $arUser = get_user($this->userId);
        $arUser["ZIP"] = $arUser["PLZ"];
        $arUser["CITY"] = $arUser["ORT"];
        $arAdTable = array_merge($arUser, $arAdTable);
        // Legal texts
        $arAdTable["AD_AGB"] = (empty($arAdTable["AD_AGB"]) ? $this->db->fetch_atom("SELECT AGB FROM `usercontent` WHERE FK_USER=".$this->userId) : $arAdTable["AD_AGB"]);
        $arAdTable["AD_WIDERRUF"] = (empty($arAdTable["AD_WIDERRUF"]) ? $this->db->fetch_atom("SELECT WIDERRUF FROM `usercontent` WHERE FK_USER=".$this->userId) : $arAdTable["AD_WIDERRUF"]);
        // B2B options
        $this->adData = AdConstraintManagement::appendAdContraintMapping($arAdTable);
        // Images
        $this->adData['images'] = $this->db->fetch_table("SELECT * FROM `ad_images` WHERE FK_AD=".$idAd);
        foreach ($this->adData['images'] as $imageIndex => $arImage) {
            $arImageMeta = @unserialize($arImage["SER_META"]);
            if (is_array($arImageMeta)) {
                $this->adData['images'][$imageIndex]['META'] = $arImageMeta;
            }
        }
        // Documents
        $this->adData['uploads'] = $this->db->fetch_table("SELECT * FROM `ad_upload` WHERE FK_AD=".$idAd);
        // Videos
        $this->adData['videos'] = $this->db->fetch_table("SELECT * FROM `ad_video` WHERE FK_AD=".$idAd);
        // Set category
        $this->setCategory($arAdMaster["FK_KAT"]);
        // Variant data
        require_once $GLOBALS["ab_path"]."sys/lib.ad_variants.php";
        $adVariantsManagement = AdVariantsManagement::getInstance($this->db);
        $arVariants = $adVariantsManagement->getVariantTable($idAd);
        foreach ($arVariants as $variantIndex => $arVariant) {
            foreach ($arVariant["FIELDS"] as $variantFieldIndex => $arVariantField) {
                $arVariants[$variantIndex]["FIELDS"][$variantFieldIndex] = array(
                    'F_NAME'        => $arVariantField['F_NAME'],
                    'LIST_VALUE'    => $arVariantField['FK_LISTE_VALUES']
                );
            }
        }
        $this->adData["_VARIANTS"] = $arVariants;
        $arVariantsFields = $adVariantsManagement->getAdVariantFieldsById($idAd);
        $arVariantsFieldIdByName = array();
        $this->adData["_VARIANTS_FIELDS"] = array();
        foreach ($arVariantsFields as $arVariantField) {
            $arVariantsFieldIdByName[ $arVariantField["F_NAME"] ] = $arVariantField["ID_FIELD_DEF"];
            $arVariantFieldValues = array();
            foreach ($arVariantField['values'] as $arVariantFieldValue) {
                $arVariantFieldValues[] = $arVariantFieldValue['ID_LISTE_VALUES'];
            }
            $this->adData["_VARIANTS_FIELDS"][ $arVariantField['F_NAME'] ] = $arVariantFieldValues;
        }
        if (!empty($this->adData["_VARIANTS_FIELDS"])) {
            foreach ($this->adData['images'] as $imageIndex => $arImage) {
                $arVariantsImage = $this->db->fetch_nar("SELECT ID_FIELD_DEF, ID_LISTE_VALUE FROM `ad_images_variants` WHERE ID_IMAGE=".$arImage["ID_IMAGE"]);
                $this->adData['images'][$imageIndex]["VARIANTS"] = array();
                foreach ($this->adData["_VARIANTS_FIELDS"] as $fieldName => $fieldListValues) {
                    if (!array_key_exists($fieldName, $arVariantsFieldIdByName)) {
                        $this->adData['images'][$imageIndex]["VARIANTS"][$fieldName] = "";
                    } else {
                        $variantFieldId = (int)$arVariantsFieldIdByName[$fieldName];
                        if (!array_key_exists($variantFieldId, $arVariantsImage)) {
                            $this->adData['images'][$imageIndex]["VARIANTS"][$fieldName] = "";
                        } else {
                            $this->adData['images'][$imageIndex]["VARIANTS"][$fieldName] = $arVariantsImage[$variantFieldId];
                        }
                    }
                }
                    // TODO: Load variant values from database!
                $this->adData['images'][$imageIndex]['variantsJson'] = json_encode($this->adData['images'][$imageIndex]["VARIANTS"]);
                $this->adData['images'][$imageIndex]['variantsText'] = $this->getImageVariantsText($imageIndex);
            }
        }
        // Get payment adapters
        $adPaymentAdapterManagement = AdPaymentAdapterManagement::getInstance($this->db);
        $this->adData['paymentAdapters'] = $adPaymentAdapterManagement->fetchAllPaymentAdapterNamesForAd($idAd);
        // Initialize groups and steps
        $this->initializeSystemGroups($this->options['systemgroups']);
        $this->initializeSteps($this->options['steps']);
        $this->indexStepLast = count($this->arSteps) - 1;
        return true;
    }

    /**
     * @param $fieldName
     * @param $value
     * @return bool
     */
    public function setCustomAdData($fieldName, $value) {
        $this->adData[$fieldName] = $value;
        return true;
    }

    /**
     * @param $fieldName
     * @param $value
     * @return bool
     */
    public function setJsonAdData($fieldName, $value) {
        if (!array_key_exists("JSON_ADDITIONAL", $this->adData)) {
            $this->adData["JSON_ADDITIONAL"] = array();
        }
        $this->adData["JSON_ADDITIONAL"][$fieldName] = $value;
        return true;
    }
    
    /**
     * Rotate image
     * @param $imageIndex   int     Index of the image that should be the new default
     * @param $degrees      int     Rotate image about X degrees
     * @return              bool    true if the default image was rotated successfully
     */
    public function rotateImage($imageIndex, $degrees) {
        if (count($this->adData['images']) < ($imageIndex-1)) {
            // Out of bounds
            return false;
        }
        if ($this->adData['images'][$imageIndex]['ID_IMAGE'] > 0) {
            $imageFile = $GLOBALS["ab_path"].ltrim($this->adData['images'][$imageIndex]['SRC'], "/");
            $imageFileThumb = $GLOBALS["ab_path"].ltrim($this->adData['images'][$imageIndex]['SRC_THUMB'], "/");
        } else {
            $imageFile = $this->adData['images'][$imageIndex]['TMP'];
            $imageFileThumb = $this->adData['images'][$imageIndex]['TMP_THUMB'];
        }
        $binConvert = $GLOBALS['nar_systemsettings']['SYS']['PATH_CONVERT'];
        system($binConvert." ".$imageFile." -rotate ".(int)$degrees." ".$imageFile);
        system($binConvert." ".$imageFileThumb." -rotate ".(int)$degrees." ".$imageFileThumb);
        $_REQUEST["show"] = "images";
        return true;
    }
    
    /**
     * Sets the category for the article.
     * @param $katId    int     Category to be selected
     * @return          bool    true if the article data was successfully loaded
     */
    public function setCategory($katId) {
        $this->katId = $katId;
        if (!isset($this->adData["FK_KAT"]) || ($this->adData["FK_KAT"] != $katId)) {
            $this->loadFromArray( array_merge( $this->adData, AdManagment::createArticleAsArray($this->katId, $this->userId) ) );
        }
        $this->adData["FK_KAT"] = $this->katId;
        $this->adData["FK_PACKET_ORDER"] = $this->packetId;
        $this->adData["FK_TABLE_DEF"] = $this->db->fetch_atom("SELECT ID_TABLE_DEF FROM `table_def` WHERE T_NAME='".mysql_real_escape_string($this->adData["AD_TABLE"])."'");
        // Get category options
        $arCategory = $this->db->fetch1("SELECT B_SALES, SER_OPTIONS, KAT_TABLE FROM `kat` WHERE ID_KAT=".$katId);
        $this->adData["isSalesCategory"] = $arCategory['B_SALES'];
        $this->adData["categoryOptions"] = unserialize($arCategory['SER_OPTIONS']);
        $this->adData["isVariantCategory"] = $this->db->fetch_atom("
            SELECT f.ID_FIELD_DEF
            FROM `field_def` f
            LEFT JOIN `kat2field` kf ON kf.FK_FIELD=f.ID_FIELD_DEF
            WHERE f.B_ENABLED=1 AND f.F_TYP='VARIANT' AND kf.FK_KAT=".$this->katId." AND kf.B_ENABLED=1
            LIMIT 1");
        $manufacturerGroups = $this->db->fetch_atom("SELECT COUNT(*) FROM `man_group`");
        if ($manufacturerGroups > 0) {
          $this->useManufacturers = $this->db->fetch_atom("
              SELECT COUNT(*) FROM `man_group_category` WHERE FK_KAT=".$this->katId) > 0;
          $this->useProducts = $this->db->fetch_atom("
              SELECT COUNT(*) FROM `hdb_table_".mysql_real_escape_string($arCategory["KAT_TABLE"])."` t
              JOIN `man_group_mapping` m ON t.FK_MAN=m.FK_MAN
              JOIN `man_group_category` c ON m.FK_MAN_GROUP=c.FK_MAN_GROUP
              WHERE c.FK_KAT=".$this->katId." AND t.CONFIRMED=1") > 0;
        } else {
          $this->useManufacturers = $this->db->fetch_atom("
              SELECT COUNT(*) FROM `manufacturers` WHERE CONFIRMED=1") > 0;
          $this->useProducts = $this->db->fetch_atom("
              SELECT COUNT(*) FROM `hdb_table_".mysql_real_escape_string($arCategory["KAT_TABLE"])."` t
              WHERE t.CONFIRMED=1") > 0;
        }
        return true;
    }

    /**
     * Set the packet to be used for creating this article
     * @param $idPacketOrder    Id of the packet order
     */
    public function setPacket($idPacketOrder) {
        $this->packetId = $idPacketOrder;
        $this->packetPaid = ($this->db->fetch_atom("SELECT PRICE FROM `packet_order` WHERE ID_PACKET_ORDER=".$this->packetId) > 0 ? true : false);
        $this->adData["FK_PACKET_ORDER"] = $this->packetId;
    }

    /**
     * Sets the given
     * @param $imageIndex   int     Index of the image that should be the new default
     * @return              bool    true if the default image was successfully set
     */
    public function setImageDefault($imageIndex) {
        if (count($this->adData['images']) < ($imageIndex-1)) {
            // Out of bounds
            return false;
        }
        foreach ($this->adData['images'] as $index => $ar_image) {
            $this->adData['images'][$index]['IS_DEFAULT'] = 0;
        }
        $this->adData['images'][$imageIndex]['IS_DEFAULT'] = 1;
        $_REQUEST["show"] = "images";
        return true;
    }
    
    public function setImageVariants($imageIndex, $imageVariants) {
        if (count($this->adData['images']) < ($imageIndex-1)) {
            // Out of bounds
            return false;
        }
        $this->adData['images'][$imageIndex]['VARIANTS'] = $imageVariants;
        return true;
    }
    
    public function getImageVariantsText($imageIndex, $implodeStr = "<br />") {
        if (count($this->adData['images']) < ($imageIndex-1)) {
            // Out of bounds
            return false;
        }
        if (empty($this->adData["_VARIANTS_FIELDS"])) {
            return false;
        }
        $arVariantsText = array();
        foreach ($this->adData["_VARIANTS_FIELDS"] as $fieldName => $fieldListValues) {
            $fieldLabel = Ad_Marketplace::getFieldLabelByName($this->adData["FK_TABLE_DEF"], $fieldName);
            $fieldValue = 0;
            if (array_key_exists("VARIANTS", $this->adData['images'][$imageIndex])
                && array_key_exists($fieldName, $this->adData['images'][$imageIndex]["VARIANTS"])) {
                $fieldValue = (int)$this->adData['images'][$imageIndex]["VARIANTS"][$fieldName];
            }
            if ($fieldValue > 0) {
                $arVariantsText[] = $fieldLabel.": ".Ad_Marketplace::getListLabelById($fieldValue);
            } else {
                $arVariantsText[] = $fieldLabel.": ".Translation::readTranslation("marketplace", "ad.create.images.assign.to.all.variants", null, array(), "Alle");
            }
        }

        return implode($implodeStr, $arVariantsText);
    }

    /**
     * Submit data for a step
     * @param $arData   array   Data from the step to be submitted
     * @param $done     bool    Will be set true after the last step was successfully submitted
     */
    public function submitStep($arData, &$done = false) {
        global $ab_path;
        // Initialize variables
        $arDataNew = array();
        $errors = array();
        $continue = true;
        $stepIndex = $this->getStepIndex($arData['step']);
        $stepIndexNext = $stepIndex + 1;
        $stepData = $this->getStep($stepIndex);
        $stepVars = (!isset($stepData["TEMPLATE_VARS"]) ? array(): $stepData["TEMPLATE_VARS"]);
        // Check submitted elements
        foreach ($stepVars as $tplVarIdent => $tplVarOptions) {
            // Plugin event
            $stepSubmitParams = new Api_Entities_EventParamContainer(array(
                "adCreate"          => $this,
                "step"              => $tplVarIdent,
                "stepOptions"       => $tplVarOptions,
                "dataInput"         => $arData,
                "dataOutput"        => $arDataNew,
                "continue"          => $continue,
                "errors"            => $errors
            ));
            Api_TraderApiHandler::getInstance()->triggerEvent( Api_TraderApiEvents::MARKETPLACE_AD_CREATE_SUBMIT_STEP, $stepSubmitParams );
            if ($stepSubmitParams->isDirty()) {
                $tplVarOptions = $stepSubmitParams->getParam("stepOptions");
                $arData = $stepSubmitParams->getParam("dataInput");
                $arDataNew = $stepSubmitParams->getParam("dataOutput");
                $continue = $stepSubmitParams->getParam("continue");
                $errors = $stepSubmitParams->getParam("errors");
            }
            switch ($tplVarIdent) {
                case 'PACKETS':
                    $this->setPacket((int)$arData["FK_PACKET_ORDER"]);
                    break;
                case 'CATEGORIES':
                    // Submit category
                    $this->setCategory((int)$arData["FK_KAT"]);
                    break;
                case 'HDB':
                    require_once $ab_path."sys/lib.hdb.php";
                    $manufacturerDatabaseManagement = ManufacturerDatabaseManagement::getInstance($this->db);

                    $hdbProductId = $arData['ID_HDB_PRODUCT'];
                    $hdbTable = $arData['HDB_TABLE'];

                    if(!empty($hdbProductId) && !empty($hdbTable)) {
                        $hdbProduct = $manufacturerDatabaseManagement->fetchProductById($hdbProductId, $hdbTable);
                        $hdbProductType = $manufacturerDatabaseManagement->fetchProductTypeByTable($hdbTable);

                        if($hdbProduct != null && $hdbProductType != null && $hdbProduct['CONFIRMED'] == 1) {
                            $productData = $hdbProduct;
                            $productData['HERSTELLER'] = $productData['MANUFACTURER_NAME'];
                            $productData['FK_PRODUCT'] = $hdbProductId;

                            unset($productData['ID_HDB_PRODUCT']);
                            unset($productData['ID_'.strtoupper($hdbTable)]);
                            unset($productData['FK_TABLE_DEF']);
                            unset($productData['DATA_USER']);
                            unset($productData['CONFIRMED']);
                            unset($productData['FULL_PRODUKTNAME']);
                            unset($productData['MANUFACTURER_NAME']);
                            unset($productData['HDB_TABLE']);
                            unset($productData['PRODUCT_TYPE_DESCRIPTION']);

                            if (array_key_exists('FK_KAT', $productData) && ($productData['FK_KAT'] > 0)) {
                                $this->setCategory((int)$productData['FK_KAT']);
                            } else if (array_key_exists('ID_KAT', $arData) && ($arData['ID_KAT'] > 0)) {
                                $this->setCategory((int)$arData["ID_KAT"]);
                            }
                            $productData["FK_KAT"] = $this->katId;

                            if(isset($productData['IMPORT_IMAGES']) && !empty($productData['IMPORT_IMAGES'])) {
                                
                                $hdbImages = unserialize($productData['IMPORT_IMAGES']);
                                $this->adData['images'] = array();
                                foreach ($hdbImages as $imageIndex => $imagePath) {
                                    
                                    $isUrl = preg_match("/^https?\:\/\/.+/i", $imagePath);
                                    if ($isUrl) {
                                        $hdbImage = tempnam(sys_get_temp_dir(), "hdb_image");
                                        copy($imagePath, $hdbImage);
                                    } else {
                                        $hdbImage = $ab_path.$imagePath;                                        
                                    }
    
                                    $this->handleImageUpload(array(
                                        'name' => pathinfo($hdbImage, PATHINFO_FILENAME),
                                        'tmp_name' => $hdbImage,
                                        'error' => UPLOAD_ERR_OK,
                                        'type' => finfo_file(finfo_open(FILEINFO_MIME_TYPE), $hdbImage)
    
                                    ));
                                    
                                    if ($isUrl) {
                                        unlink($hdbImage);
                                    }
                                }

                            }

                            $arDataNew = array_merge($arDataNew, $productData);

                        }
                    } else {
                        if ($arData["ID_KAT"] > 0) {
                            $this->setCategory((int)$arData["ID_KAT"]);
                        }
                    }

                    break;
                case 'ARTICLE_FIELDS':
                    // Kategorie-Einstellungen
                    $ar_kat = $this->db->fetch1("SELECT B_SALES, SER_OPTIONS FROM kat where ID_KAT=".$this->katId);
                    $ar_kat_options = unserialize($ar_kat['SER_OPTIONS']);
                    if (!$ar_kat["B_SALES"] && !in_array($arData['VERKAUFSOPTIONEN'], $ar_kat_options['SALES'])) {
                        $ar_kat_options['SALES'][] = $arData['VERKAUFSOPTIONEN'];
                    }
                    // Get allowed sale types
                    $salesAllowed = array();
                    if (empty($ar_kat_options['SALES'])) {
                        $salesAllowed[] = 0;
                    } else if (isset($arData['VERKAUFSOPTIONEN']) && !in_array($arData['VERKAUFSOPTIONEN'], $ar_kat_options['SALES'])) {
                        // Invalid sale option
                        $errors['VERKAUFSOPTIONEN'] = Translation::readTranslation('marketplace', 'ad.create.sales.option.invalid', null, array(), 'Unzulässige Verkaufsoption!');
                    } else if ($arData['VERKAUFSOPTIONEN'] == 2) {
                        $arData['PREIS'] = 0;
                    }
                    if ($arData['VERKAUFSOPTIONEN'] == 3) {
                        $arRentPrices = Api_LookupManagement::getInstance($this->db)->readByArt("VERMIETEN");
                        $pricePerDayMin = false;
                        foreach ($arRentPrices as $lookupIndex => $arLookup) {
                            $lookupId = (int)$arLookup["ID_LOOKUP"];
                            $lookupDays = (strtotime("+".$arLookup["VALUE"]) - time()) / 3600 / 24;
                            if (array_key_exists($lookupId, $arData['MIETPREISE']) && ($arData['MIETPREISE'][$lookupId] > 0)) {
                                $pricePerDay = str_replace(",", ".", $arData['MIETPREISE'][$lookupId]) / $lookupDays;
                                if (($pricePerDayMin === false) || ($pricePerDayMin > $pricePerDay)) {
                                    $pricePerDayMin = $pricePerDay;
                                }
                            }
                        }
                        $arData['MIETPREISE'] = serialize($arData['MIETPREISE']);
                        if ($pricePerDayMin !== false) {
                            $arData['PREIS'] = $pricePerDayMin;
                        } else {
                            $errors['MIETPREISE'] = Translation::readTranslation('marketplace', 'ad.create.rent.price.missing', null, array(), 'Bitte geben Sie mindestens einen Mietpreis ein!');
                        }
                    }
                    if (is_array($arData["BF_CONSTRAINTS"])) {
                        $bfConstraintsValue = 0;
                        foreach ($arData["BF_CONSTRAINTS"] as $constraintIndex => $constraintValue) {
                            $bfConstraintsValue += $constraintValue;
                        }
                        $arData["BF_CONSTRAINTS"] = $bfConstraintsValue;
                    }
                    $inputFieldsParams = new Api_Entities_EventParamContainer(array(
                        "dataInput"         => $arData,
                        "dataOutput"        => $arDataNew,
                        "variantsChanged"   => false
                    ));
                    Api_TraderApiHandler::getInstance()->triggerEvent( Api_TraderApiEvents::MARKETPLACE_AD_CREATE_SUBMIT_FIELDS, $inputFieldsParams );
                    $variantsChanged = false;
                    if ($inputFieldsParams->isDirty()) {
                        $arData = $inputFieldsParams->getParam("dataInput");
                        $arDataNew = $inputFieldsParams->getParam("dataOutput");
                        $variantsChanged = $inputFieldsParams->getParam("variantsChanged");
                    }
                    // Submit article fields
                    foreach ($tplVarOptions["GROUPS"] as $groupIndex => $groupId) {
                        // Process system groups
                        switch ($groupId) {
                            case 'BASE':
                                $arData['LU_LAUFZEIT'] = 105;
                                break;
                            case 'PRICE':
                                $arDataNew['paymentAdapters'] = $arData['ad_payment_adapter'];
                                $arDataNew['AFFILIATE'] = (!empty($arData['AFFILIATE_LINK']) ? 1 : 0);
                                break;
                            case 'REQUEST':
                                // Invoice address
                                $this->setJsonAdData("ID_USER_INVOICE", $arData["ID_USER_INVOICE"]);
                                $this->setJsonAdData("INVOICE_COMPANY", $arData["INVOICE_COMPANY"]);
                                $this->setJsonAdData("INVOICE_FIRSTNAME", $arData["INVOICE_FIRSTNAME"]);
                                $this->setJsonAdData("INVOICE_LASTNAME", $arData["INVOICE_LASTNAME"]);
                                $this->setJsonAdData("INVOICE_STREET", $arData["INVOICE_STREET"]);
                                $this->setJsonAdData("INVOICE_ZIP", $arData["INVOICE_ZIP"]);
                                $this->setJsonAdData("INVOICE_CITY", $arData["INVOICE_CITY"]);
                                $this->setJsonAdData("INVOICE_FK_COUNTRY", $arData["INVOICE_FK_COUNTRY"]);
                                $this->setJsonAdData("INVOICE_PHONE", $arData["INVOICE_PHONE"]);
                                // Shipping address
                                $this->setJsonAdData("ID_USER_VERSAND", $arData["ID_USER_VERSAND"]);
                                $this->setJsonAdData("VERSAND_COMPANY", $arData["VERSAND_COMPANY"]);
                                $this->setJsonAdData("VERSAND_FIRSTNAME", $arData["VERSAND_FIRSTNAME"]);
                                $this->setJsonAdData("VERSAND_LASTNAME", $arData["VERSAND_LASTNAME"]);
                                $this->setJsonAdData("VERSAND_STREET", $arData["VERSAND_STREET"]);
                                $this->setJsonAdData("VERSAND_ZIP", $arData["VERSAND_ZIP"]);
                                $this->setJsonAdData("VERSAND_CITY", $arData["VERSAND_CITY"]);
                                $this->setJsonAdData("VERSAND_FK_COUNTRY", $arData["VERSAND_FK_COUNTRY"]);
                                $this->setJsonAdData("VERSAND_PHONE", $arData["VERSAND_PHONE"]);
                                break;
                        }
                        $arFields = $this->getFields($groupId);
                        foreach ($arFields as $fieldIndex => $arField) {
                            if (array_key_exists("IS_SPECIAL", $arField) && $arField["IS_SPECIAL"]) {
                                continue;
                            }
                            $name = $arField["F_NAME"];
                            $type = $arField["F_TYP"];
                            $value = $arData[$name];
                            // Process value by type
                            switch ($type) {
                                case 'MULTICHECKBOX':
                                case 'MULTICHECKBOX_AND':
                                    $value = $arData['check'][$name];
                                    break;
                                case 'VARIANT':
                                    $value = $arData['variants'][$name];
                                    break;
                                case 'DATE':
                                    if (preg_match("/^([0-9]{2})\.([0-9]{2})\.([0-9]{4})$/", $arData[$name], $tmpDate)) {
                                        $value = $tmpDate[3]."-".$tmpDate[2]."-".$tmpDate[1];
                                    } else {
                                        $value = false;
                                    }
                                    break;
                                case 'DATE_MONTH':
                                    if (preg_match("/^([0-9]{2})\.([0-9]{4})$/", $arData[$name], $tmpDate)) {
                                        $value = $tmpDate[2]."-".$tmpDate[1]."-01";
                                    } else {
                                        $value = false;
                                    }
                                    break;
                                case 'DATE_YEAR':
                                    if (preg_match("/^([0-9]{4})$/", $arData[$name], $tmpDate)) {
                                        $value = $tmpDate[1];
                                    } else {
                                        $value = false;
                                    }
                                    break;
                                case 'INT':
                                    if (isset($arData[$name])) {
                                        $valueNew = (int)$value;
                                        $arData[$name] = $value = $valueNew;
                                    }
									                  break;
                                case 'FLOAT':
                                    if (isset($arData[$name])) {
                                        $valueNew = str_replace(",", ".", $value);
                                        $arData[$name] = $value = $valueNew;
                                    }
                                    break;
                                case 'CHECKBOX':
                                    if(!isset($arData[$name]) || $arData[$name] == null) {
                                        $arData[$name] = $value = 0;
                                    }
                                    break;
                            }
                            // Validate
                            $isNeeded = $this->isFieldRequired($arData, $groupId, $name, $type, $arField["B_NEEDED"]);
                            $validateResult = $this->validateField($name, $value, $isNeeded, $type);
                            if (!$validateResult["valid"]) {
                                #var_dump($arField, $validateResult);
                                $errors[$name] = $validateResult["error_msg"];
                            } else if ($value !== null) {
                                switch ($type) {
                                    case 'MULTICHECKBOX':
                                    case 'MULTICHECKBOX_AND':
                                        $arDataNew[$name] = "x".implode("x", $value)."x";
                                        break;
                                    case 'VARIANT';
                                        $variantsChanged = true;
                                        $arDataNew['isVariant'] = 1;
                                        $arDataNew['_VARIANTS_FIELDS'][$name] = $value;
                                        break;
                                    default:
                                        $arDataNew[$name] = $value;
                                }
                            }
                        }
                    }
                    if ($variantsChanged) {
                        require_once $ab_path."sys/lib.ad_variants.php";
                        $adVariantsManagement = AdVariantsManagement::getInstance($this->db);
                        $arVariantsFields = $arDataNew['_VARIANTS_FIELDS'];
                        if (is_array($this->adData['_VARIANTS_FIELDS'])) {
                            foreach ($this->adData['_VARIANTS_FIELDS'] as $variantFieldName => $variantFieldValues) {
                                if (!isset($arVariantsFields[$variantFieldName])) {
                                    $arVariantsFields[$variantFieldName] = $variantFieldValues;
                                }
                            }
                        }
                        $arDataNew['_VARIANTS'] = $adVariantsManagement->generateAdVariantTableFromArray(array_merge($this->adData, $arDataNew), $arVariantsFields);
                    }
                    break;
                case 'ARTICLE_MEDIA':                    
                    if (array_key_exists("META", $arData) && array_key_exists("IMAGES", $arData["META"])) {
                        foreach ($arData["META"]["IMAGES"] as $imageIndex => $arImageMeta) {
                            $this->adData["images"][$imageIndex]["META"] = $arImageMeta;
                        }

                    }
	                if (array_key_exists("IS_PAID", $arData)) {
		                foreach ($this->adData["uploads"] as $uploadIndex => $arUpload) {
			                $this->adData["uploads"][$uploadIndex]["IS_PAID"] = array_key_exists($uploadIndex, $arData["IS_PAID"]);
		                }
	                }
	                else {
		                if ( $this->adData["categoryOptions"]["FORCE_ATLEAST_ONE_PAID_DOWNLOAD"] == 1 ) {
			                $errors[] = array(
				                "error_document"    =>  "Mindestens ein kostenpflichtiger Download muss vorhanden sein."
			                );
		                }
	                }
                    break;
                case 'VARIANTS':
                    $arDataNew['_VARIANTS'] = $this->adData['_VARIANTS'];
                    $arVariants = $arData['variants'];
                    if (is_array($arVariants)) {
                        foreach ($arVariants as $variantIndex => $variantOptions) {
                            if (is_array($arDataNew['_VARIANTS'][$variantIndex])) {
                                $arDataNew['_VARIANTS'][$variantIndex] = array_merge($arDataNew['_VARIANTS'][$variantIndex], $variantOptions);
                            } else {
                                $arDataNew['_VARIANTS'][$variantIndex] = $variantOptions;
                            }
                        }
                    }
                    break;
                case 'ARTICLE_CONFIRM':
                    $this->create(true, $errors);
                    break;
            }
        }



        if (empty($errors)) {
            $this->adData = array_merge($this->adData, $arDataNew);
            if ($continue) {
                if ($stepIndexNext > $this->indexStepLast) {
                    if ($stepIndexNext < count($this->arSteps)) {
                        // There is a next step, unlock it
                        $this->indexStepLast = $stepIndexNext;
                    } else {
                        // Last step submitted
                        $done = true;
                    }
                }
            }
            return true;
        } else {
            return $errors;
        }
    }

    /**
     * Returns the input fields for the current category.
     * @param array $arData         Data to be filled into the input fields
     * @param array $arFieldGroups  List of groups to be rendered
     * @return string
     */
    public function renderFieldInputs($arData, $arFieldGroups = false) {
        global $ab_path, $s_lang, $nar_systemsettings;

        require_once $ab_path."sys/lib.pub_kategorien.php";
        // Get all available field groups if none were supplied
        if (!is_array($arFieldGroups)) {
            $table = $this->db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".$this->katId);
            $idTable = $this->db->fetch_atom("SELECT ID_TABLE_DEF FROM `table_def` WHERE T_NAME='".mysql_real_escape_string($table)."'");
            $arFieldGroups = array();
            $arFieldGroups[] = null;
            $arFieldGroupsAdditional = array_keys($this->db->fetch_nar("SELECT ID_FIELD_GROUP FROM `field_group` WHERE FK_TABLE_DEF=".$idTable." ORDER BY F_ORDER ASC"));
            foreach ($arFieldGroupsAdditional as $indexGroup => $idGroup) {
                $arFieldGroups[] = $idGroup;
            }
        }
        // Get input blocks
        $arInputBlocks = array();
        foreach ($arFieldGroups as $indexGroup => $idGroup) {
            if (array_key_exists($idGroup, $this->arSystemGroups)) {
                // System group
                require_once $ab_path."sys/lib.cache.template.php";
                $filenameRel = "tpl/".$s_lang."/my-marktplatz-neu.fields.".$idGroup.".htm";
                $filenameAbs = CacheTemplate::getHeadFile($filenameRel);
                if (file_exists($filenameAbs)) {
                    $tplStep = new Template($filenameRel);
                    $tplStep->addvars($arData);
                    // Apply usergroup settings
                    $arUsergroup = $this->db->fetch1("SELECT * FROM `usergroup` WHERE ID_USERGROUP=".$GLOBALS["user"]["FK_USERGROUP"]);
                    $arUsergroupOptions = @unserialize($arUsergroup["SER_OPTIONS"]);
                    if ($arUsergroupOptions !== false) {
                        $arUsergroupOptions = array_flatten($arUsergroupOptions, "both");
                        $tplStep->addvars($arUsergroupOptions, "USERGROUP_OPTIONS_");
                    }
                    // Apply specific configurations
                    switch ($idGroup) {
                        case 'PRICE':
                            // Payment Adapter
                            $paymentAdapterUserManagement = PaymentAdapterUserManagement::getInstance($this->db);
                            $paymentAdapers = $paymentAdapterUserManagement->fetchAllAvailablePaymentAdapterByUser($this->userId);
                            $userDefaultPaymentAdapters = $paymentAdapterUserManagement->fetchAllAutoCheckedPaymentAdapterByUser($this->userId);
                            foreach($paymentAdapers as $key => $paymentAdaper) {
                                if (is_array($this->adData['paymentAdapters']) &&
                                    array_key_exists($paymentAdaper['ID_PAYMENT_ADAPTER'], $this->adData['paymentAdapters'])) {
                                    $paymentAdapers[$key]['CHECKED'] = TRUE;
                                }
                                if ($this->adData['ID_ AD_MASTER'] == NULL && array_key_exists($paymentAdaper['ID_PAYMENT_ADAPTER'], $userDefaultPaymentAdapters)) {
                                    $paymentAdapers[$key]['CHECKED'] = TRUE;
                                }
                            }
                            if ((count($arData["categoryOptions"]["SALES"]) == 1) && ($arData["categoryOptions"]["SALES"][0] == 4)) {
                                $tplStep->addvar("PRICE_DISABLED", 1);
                            }
                            if ($arData["categoryOptions"]["HIDE_QUANTITY"]) {
                                $tplStep->addvar("HIDE_QUANTITY", 1);
                            }
                            if ($arData["categoryOptions"]["HIDE_PSEUDO_PRICE"]) {
                                $tplStep->addvar("HIDE_PSEUDO_PRICE", 1);
                            }
                            $tplStep->addlist('AD_PAYMENT_ADAPTER', $paymentAdapers, "tpl/".$s_lang."/my-marktplatz-neu.payment-adapter.row.htm");

                            // Mietpreise
                            if ($nar_systemsettings['MARKTPLATZ']['ENABLE_RENT']) {
                                $arRentPrices = Api_LookupManagement::getInstance($this->db)->readByArt("VERMIETEN");
                                $arRentPricesCur = @unserialize($this->adData["MIETPREISE"]);
                                if (is_array($arRentPricesCur)) {
                                    foreach ($arRentPrices as $rentIndex => $arRentPrice) {
                                        $arRentPrices[$rentIndex]["AD_VALUE"] = $arRentPricesCur[ $arRentPrice["ID_LOOKUP"] ];
                                    }   
                                }
                                $tplStep->addvar("ENABLE_RENT", 1);
                                $tplStep->addlist("liste_mietpreise", $arRentPrices, "tpl/de/my-marktplatz-neu.fields.PRICE.RENT.htm");
                            }
                            // Basispreis
                            if (!$nar_systemsettings['MARKTPLATZ']['USE_ARTICLE_BASEPRICE']) {
                                $options['basepriceEnabled'] = 0;
                            } else {
                                if (isset($this->adData['categoryOptions']['USE_ARTICLE_BASEPRICE']) && $this->adData['categoryOptions']['USE_ARTICLE_BASEPRICE'] == 0) {
                                    $options['basepriceEnabled'] = 0;
                                } else {
                                    $options['basepriceEnabled'] = 1;
                                }
                            }
                            if ($options['basepriceEnabled'] == 1) {
                                $listValue = $this->db->fetch_atom("SELECT FK_LISTE FROM field_def WHERE F_NAME = 'BASISPREIS_EINHEIT' LIMIT 1");

                                $tplStep->addvar('OPTIONS_USE_ARTICLE_BASEPRICE', 1);
                                $tplStep->addvar('OPTIONS_USE_ARTICLE_BASEPRICE_WHERECLAUSE', "FK_LISTE='" . (int)$listValue . "'");
                            }
                            break;
                        case 'SHIPPING':
                            if ($arData["categoryOptions"]["HIDE_SHIPPING"]) {
                                $tplStep->addvar("HIDE_SHIPPING", 1);
                            }
                            if ((count($arData["categoryOptions"]["SALES"]) == 1) && ($arData["categoryOptions"]["SALES"][0] == 4)) {
                                $tplStep->addvar("SHIPPING_DISABLED", 1);
                            }
                            // Trigger plugin event
                            $eventShippingParams = new Api_Entities_EventParamContainer(array(
                                "adCreate"          => $this,
                                "template"          => $tplStep,
                                "usergroupOptions"  => $arUsergroupOptions,
                                "result"            => null
                            ));
                            Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_AD_CREATE_SHIPPING, $eventShippingParams);
                            if ($eventShippingParams->isDirty()) {
                                $result = $eventShippingParams->getParam("result");
                                if ($result !== null) {
                                    // Insert plugin result instead of default template
                                    $arInputBlocks[] = $result;
                                    continue 2;
                                }
                            }
                            break;
                    }

                    $arInputBlocks[] = $tplStep->process(false);
                }
            } else {
                // Article table group
                $arInputBlocks[] = CategoriesBase::getInputFieldsCache($this->katId, $arData, false, $idGroup);
            }
        }
        return implode("\n\n\n", $arInputBlocks);
    }

    /**
     * Render the list of article images.
     * @return string       The final rendered image list as HTML code.
     */
    public function renderMediaImages() {
        global $s_lang;
        // Trigger plugin event
        $paramAdMedia = new Api_Entities_EventParamContainer(array(
            "adCreate"  => $this,
            "list"		=> $this->adData["images"],
            "result"	=> null
        ));
        Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_AD_CREATE_RENDER_IMAGES, $paramAdMedia);
        if ($paramAdMedia->isDirty() && ($paramAdMedia->getParam("result") !== null)) {
            return $paramAdMedia->getParam("result");
        } else {
            $arImages = $this->adData["images"];
            foreach ($arImages as $imageIndex => $arImage) {
                if ($arImage['FK_AD'] == 0) {
                    $arImages[$imageIndex]['BASE64'] = base64_encode( @file_get_contents($arImage['TMP_THUMB']) );
                } else {
                    $arImages[$imageIndex]['SRC_THUMB'] = $arImage['SRC_THUMB']."?".time();
                }
                if (array_key_exists("META", $arImage)) {
                    $arImages[$imageIndex] = array_merge($arImages[$imageIndex], array_flatten($arImage["META"], true, "_", "META_"));
                }
                if (!empty($this->adData["_VARIANTS_FIELDS"])) {
                    foreach ($this->adData["_VARIANTS_FIELDS"] as $fieldName => $fieldListValues) {
                        $arImages[$imageIndex]['variantsJson'] = json_encode($arImages[$imageIndex]["VARIANTS"]);
                        $arImages[$imageIndex]['variantsText'] = $this->getImageVariantsText($imageIndex);
                    }
                }
            }
            $tpl_images = new Template("tpl/".$s_lang."/my-marktplatz-neu-images.htm");
            $tpl_images->addvar("ID_AD", $this->getAdId());
            $tpl_images->addlist("liste", $arImages, "tpl/".$s_lang."/my-marktplatz-neu-images.row.htm");
            if ($this->isVariantArticle()) {
                $tpl_images->addvar("HAS_VARIANTS", 1);
            }
            return $tpl_images->process(true);
        }
    }

    /**
     * Render the list of article downloads.
     * @return string       The final rendered download list as HTML code.
     */
    public function renderMediaDownloads() {
        global $s_lang;
        // Trigger plugin event
        $paramAdMedia = new Api_Entities_EventParamContainer(array(
            "adCreate"  => $this,
            "list"		=> $this->adData["uploads"],
            "result"	=> null
        ));
        Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_AD_CREATE_RENDER_DOWNLOADS, $paramAdMedia);
        if ($paramAdMedia->isDirty() && ($paramAdMedia->getParam("result") !== null)) {
            return $paramAdMedia->getParam("result");
        } else {
            $arUploads = $this->adData["uploads"];
            foreach ($arUploads as $uploadIndex => $arUpload) {
                $arUploads[$uploadIndex]['FILENAME_SHORT'] = substr($arUpload["FILENAME"], 0, 32);
            }
            $tpl_files = new Template("tpl/".$s_lang."/my-marktplatz-neu-documents.htm");
            $tpl_files->addvar("ID_AD", $this->getAdId());
            //...
	        $show_paid_document_download_option = 0;
	        if ( $this->adData["VERKAUFSOPTIONEN"] == "0" && $this->adData["categoryOptions"]["PERMISSION_FOR_PAID_DOWNLOADS"] == "1" ) {
		        $show_paid_document_download_option = 1;

		        if ( isset($this->adData["categoryOptions"]["FORCE_ATLEAST_ONE_PAID_DOWNLOAD"])
		             && $this->adData["categoryOptions"]["FORCE_ATLEAST_ONE_PAID_DOWNLOAD"] == "1" ) {
			        $tpl_files->addvar("FORCE_ATLEAST_ONE_PAID_DOWNLOAD_1",1);
		        }
		        else {
			        $this->adData["categoryOptions"]["FORCE_ATLEAST_ONE_PAID_DOWNLOAD"] = "0";
			        $tpl_files->addvar("FORCE_ATLEAST_ONE_PAID_DOWNLOAD_0",0);
		        }
	        }
	        $tpl_files->addvar("SHOW_PAID_DOCUMENT_DOWNLOAD_OPTION",$show_paid_document_download_option);
	        //....
            $tpl_files->addlist("liste", $arUploads, "tpl/".$s_lang."/my-marktplatz-neu-documents.row.htm");
            return $tpl_files->process(true);
        }
    }

    /**
     * Render the list of article videos.
     * @return string       The final rendered video list as HTML code.
     */
    public function renderMediaVideos() {
        global $s_lang;
        // Trigger plugin event
        $paramAdMedia = new Api_Entities_EventParamContainer(array(
            "adCreate"  => $this,
            "list"		=> $this->adData["videos"],
            "result"	=> null
        ));
        Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_AD_CREATE_RENDER_VIDEOS, $paramAdMedia);
        if ($paramAdMedia->isDirty() && ($paramAdMedia->getParam("result") !== null)) {
            return $paramAdMedia->getParam("result");
        } else {
            $tpl_videos = new Template("tpl/" . $s_lang . "/my-marktplatz-neu-videos.htm");
            $tpl_videos->addvar("ID_AD", $this->getAdId());
            $tpl_videos->addlist("liste", $this->adData["videos"], "tpl/" . $s_lang . "/my-marktplatz-neu-videos.row.htm");
            return $tpl_videos->process(true);
        }
    }

    /**
     * Renders the list of steps for creating an article.
     * @param bool|string   $stepActive     Numeric index or ident of the step to be rendered active (false if none)
     * @param bool|string   $templateBase   Template to be used rendering the whole list (false for default)
     * @param bool|string   $templateRow    Template to be used rendering each item (false for default)
     * @return string       The final rendered step list as HTML code.
     */
    public function renderStepList($stepActive = false, $templateBase = false, $templateRow = false) {
        global $s_lang;
        if ($templateBase === false) {
            $templateBase = "tpl/".$s_lang."/my-marktplatz-neu.steps.htm";
        }
        if ($templateRow === false) {
            $templateRow = "tpl/".$s_lang."/my-marktplatz-neu.steps.row.htm";
        }
        $arSteps = $this->getStepList($stepActive);
        $tpl_list = new Template($templateBase);
        $tpl_list->addvar("STEP_ACTIVE", $stepActive);
        $tpl_list->addlist("liste", $arSteps, $templateRow);
        return $tpl_list->process();
    }

    /**
     * Renders the content of the given step for creating an article.
     * @param int|string    $stepActive     Numeric index or ident of the step to be rendered active (false if none)
     * @param array         $tplVars        Variables to be passed to the step's template
     * @return string       The final rendered step list as HTML code.
     */
    public function renderStepContent($stepActive, $tplVars = array()) {
        global $s_lang, $ab_path, $langval, $nar_systemsettings, $uid;
        $arStep = $this->getStep($stepActive);
        if ($arStep["TEMPLATE_FILE"] instanceof Template) {
            $tpl_step = $arStep["TEMPLATE_FILE"];
        } else {
            $tpl_step = new Template("tpl/".$s_lang."/".$arStep["TEMPLATE_FILE"]);
        }
        $tpl_step->addvar("CURRENCY_DEFAULT", $nar_systemsettings['MARKTPLATZ']['CURRENCY']);
        $tpl_step->addvar("STEP_INDEX", $stepActive);
        $tpl_step->addvar("STEP_IDENT", $arStep["IDENT"]);
        $tpl_step->addvar("STEP_TITLE", $arStep["TITLE"]);
        $tpl_step->addvar("ARTICLE_TITLE_LONG", $this->getAdTitleLong());
        $tpl_step->addvar("ARTICLE_CATEGORY_ID", $this->getCategoryId());
        $tpl_step->addvar("ARTICLE_CATEGORY_NAME", $this->getCategoryName());
        foreach ($arStep["TEMPLATE_VARS"] as $tplVarIdent => $tplVarOptions) {
            switch ($tplVarIdent) {
                case 'PACKETS':
                    require_once $ab_path."sys/packet_management.php";
                    $packets = PacketManagement::getInstance($this->db);
                    $ar_required = array(PacketManagement::getType("ad_once") => 1);
                    $ar_required_abo = array(PacketManagement::getType("ad_abo") => 1);
                    $ar_packets = array_merge($packets->order_find_collections($this->userId, $ar_required), $packets->order_find_collections($this->userId, $ar_required_abo));
                    $tpl_step->addlist("liste", $ar_packets, "tpl/".$s_lang."/my-marktplatz-neu.row_packet.htm");
                    if (empty($ar_packets)) {
                        // Unbezahlte pakete mit anzeigen auslesen
                        $ar_packets_unpaid = array_merge($packets->order_find_collections($this->userId, $ar_required, 0), $packets->order_find_collections($this->userId, $ar_required_abo, 0));
                        foreach ($ar_packets_unpaid as $index => $ar_packet) {
                            $ar_packets_unpaid[$index]["TIMED_OUT"] = (($ar_packet["STAMP_END"] !== null) && (strtotime($ar_packet["STAMP_END"]) < time()));
                            $ar_packets_unpaid[$index]["FK_INVOICE"] = $this->db->fetch_atom("SELECT FK_INVOICE FROM `packet_order_invoice` WHERE FK_PACKET_ORDER=".$ar_packet["ID_PACKET_ORDER"]);
                            $ar_packets_unpaid[$index]["INVOICE_STATUS"] = $this->db->fetch_atom("SELECT STATUS FROM `billing_invoice` WHERE ID_BILLING_INVOICE=".(int)$ar_packets_unpaid[$index]["FK_INVOICE"]);
                        }
                        $tpl_step->addvars($GLOBALS["user"], "USER_");
                        $tpl_step->addlist("liste_unpaid", $ar_packets_unpaid, "tpl/".$s_lang."/my-marktplatz-neu.row_packet_unpaid.htm");
                    }
                    break;
                case 'CATEGORIES':                    
                    require_once $ab_path."sys/lib.pub_kategorien.php";

                    // Merge options
                    $tplVarOptions = array_merge($tplVarOptions, $tplVars);
                    // Get user roles
                    $arUserRoles = array_keys($this->db->fetch_nar("SELECT FK_ROLE FROM `role2user` WHERE FK_USER=".$uid));
                    // Add list of categories
                    $katRoot = 1;
                    $kat = new TreeCategories("kat", $katRoot);
                    $categoriesBase = new CategoriesBase();

                    $show_paid = ($nar_systemsettings['MARKTPLATZ']['FREE_ADS'] || $this->packetPaid ? 1 : 0);
                    $id_kat_top = 64584;
                    $id_kat_parent = ($tplVarOptions["ROOT"] ? $tplVarOptions["ROOT"] : $id_kat_top);
                    $ar_kat = $kat->element_read($id_kat_parent);
                    $arKatLayers = array_keys($this->db->fetch_nar(
                        "SELECT ID_KAT FROM `kat` WHERE ROOT=".$katRoot." AND ".$ar_kat['LFT']." BETWEEN LFT AND RGT ORDER BY LFT ASC"
                    ));
                    $arListe = array();
                    foreach ($arKatLayers as $layerIndex => $id_kat) {
                        $tpl_layer = new Template("tpl/".$s_lang."/".$tplVarOptions["TEMPLATE_LAYER"]);
                        $id_root_kat = $kat->tree_get_parent($id_kat);
                        // Add category ids
                        $tpl_layer->addvar('ID_ROOT_KAT', $id_root_kat);
                        $tpl_layer->addvar('ID_KAT', $id_kat);
                        // Add root category settings
                        if (($id_root_kat > 0) && ($id_kat != $id_root_kat)) {
                            $ar_root = $kat->element_read($id_root_kat);
                            $tpl_step->addvars($ar_root, "ROOTKAT_");
                        } else {
                            continue;
                        }
                        // Get category tree
                        $arTree = $kat->element_get_childs($id_kat);
                        // Read children and remove paid ones (if not allowed)
                        foreach ($arTree as $treeIndex => $treeNode) {
                            $arTree[$treeIndex]["kids"] = $kat->element_has_childs($treeNode["ID_KAT"]);
                            if ($treeNode["PARENT"] != $id_kat) {
                                $arTree[$treeIndex]["HIDDEN"] = 1;
                            }
                            if (!$show_paid && !$treeNode["B_FREE"]) {
                                $arTree[$treeIndex]["REMOVED"] = 1;
                            } else {
                                $hasAccess = $this->db->fetch_atom("SELECT count(*) FROM `role2kat`
                                    WHERE FK_ROLE IN (".implode(", ", $arUserRoles).") AND FK_KAT=".$treeNode["ID_KAT"]." AND ALLOW_NEW_AD=1");
                                if (!$hasAccess) {
                                    $arTree[$treeIndex]["REMOVED"] = 1;
                                }
                            }
                            $arTree[$treeIndex]["KAT_ROOT"] = $id_root_kat;
                            $arTree[$treeIndex]["ACTIVE"] = ($treeNode["ID_KAT"] == $arKatLayers[$layerIndex+1] ? 1 : 0);
                        }
                        // Remove unavailable
                        for ($treeIndex = count($arTree) - 1; $treeIndex >= 0; $treeIndex--) {
                            if ($arTree[$treeIndex]["REMOVED"]) {
                                unset($arTree[$treeIndex]);
                            }
                        }
                        if (!empty($arTree)) {
                            $tpl_layer->addlist("CATEGORIES", $arTree, "tpl/".$s_lang."/".$tplVarOptions["TEMPLATE"]);
                            $arListe[] = $tpl_layer;
                        }
                    }
                    $tpl_step->addvar("CATEGORIES", $arListe);

                    if($this->katId != null) {
                        $categoryHashMap = $categoriesBase->getCategoryPathHashMap();

                        $tpl_step->addvar("FK_KAT", $this->katId);
                        $tpl_step->addvar("PRESELECTED_FK_KAT_NAME", $categoryHashMap['ID'][$this->katId]['V1']);
                        if ($tplVarOptions["NO_WARNING"]) {
                            $tpl_step->addvar("NO_WARNING", 1);
                        }
                    }

                    break;
                case 'HDB':
                    $tpl_step->addvar("ID_CATEGORY", $this->katId);
                    $tpl_step->addvar("USE_EAN", $nar_systemsettings['MARKTPLATZ']['USE_ARTICLE_EAN']);

                    break;
                case 'ARTICLE_BASE':
                case 'ARTICLE_LOCATION':
                    $tpl_step->addvars($this->adData);
                    break;
                case 'ARTICLE_FIELDS':
                    require_once $ab_path."sys/lib.pub_kategorien.php";
                    $kat = new TreeCategories("kat", 1);
                    $ar_kat = $kat->element_read($this->katId);
                    $ar_kat_options = (is_array($ar_kat['OPTIONS']) ? array_flatten($ar_kat['OPTIONS']) : array());
                    $ar_article = AdConstraintManagement::appendAdContraintMapping($this->adData, "USER_CONSTRAINTS_ALLOWED");
                    $ar_article = AdConstraintManagement::appendAdContraintMapping($ar_article, "BF_CONSTRAINTS");
                    $ar_article["B_SALES"] = ($ar_kat['B_SALES'] && $nar_systemsettings['MARKTPLATZ']['BUYING_ENABLED']);
                    foreach ($ar_kat_options as $opt_key => $opt_value) {
                        $ar_article["OPTIONS_".$opt_key] = $opt_value;
                    }
                    $ar_article["AD_CONSTRAINTS"] = $nar_systemsettings['MARKTPLATZ']['AD_CONSTRAINTS'];
                    $ar_article["USE_PRODUCT_DB"] = $this->useManufacturers;
                    $ar_article["CURRENCY_DEFAULT"] = $nar_systemsettings['MARKTPLATZ']['CURRENCY'];
                    $ar_article["AVAILABILITY"] = (!empty($ar_article["AVAILABILITY"]) ? json_encode(unserialize($ar_article["AVAILABILITY"])) : false);


                    // Trigger plugin event
                    $paramAdFieldsLoad = new Api_Entities_EventParamContainer(array(
                        "adCreate"  => $this,
                        "data"		=> $ar_article,
                        "template"	=> $tpl_step
                    ));
                    Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_AD_CREATE_INPUT_FIELDS_LOAD, $paramAdFieldsLoad);
                    if ($paramAdFieldsLoad->isDirty()) {
                        $ar_article = $paramAdFieldsLoad->getParam("data");
                    }

                    $html_input = $this->renderFieldInputs($ar_article, $tplVarOptions["GROUPS"]);
                    $tpl_step->addvars($ar_article);
                    $tpl_step->addvar("FIELDS", $html_input);
                    
                    // Check runtimes
                    if (in_array("BASE", $tplVarOptions["GROUPS"])) {
                        $arRuntimes = Api_LookupManagement::getInstance($this->db)->readByArt("LAUFZEIT");
                        if (count($arRuntimes) == 1) {
                            $tpl_step->addvar("SHOW_LAUFZEIT", 0);
                            $tpl_step->addvar("LU_LAUFZEIT", array_keys($arRuntimes)[0]);
                        } else {
                            $tpl_step->addvar("SHOW_LAUFZEIT", 1);
                        }
                    }

                    // Additional Variables
                    if (!$nar_systemsettings['MARKTPLATZ']['USE_ARTICLE_EAN']) {
                        $options['eanEnabled'] = 0;
                    } else {
                        $options['eanEnabled'] = 1;
                    }
                    $tpl_step->addvar('OPTIONS_USE_ARTICLE_EAN', $options['eanEnabled']);

                    break;
                case 'ARTICLE_MEDIA':
                    $arMediaUsage = $this->getMediaUsage();
                    $arMediaUsage["images"] = $this->renderMediaImages();
                    $arMediaUsage["downloads"] = $this->renderMediaDownloads();
                    $arMediaUsage["videos"] = $this->renderMediaVideos();
                    $tpl_step->addvar('UPLOAD_MAX_FILESIZE', Tools_Utility::getUploadMaxFilsize());
                    $tpl_step->addvars($arMediaUsage);
	                //...
	                $show_paid_document_download_option = 0;
	                if ( $this->adData["VERKAUFSOPTIONEN"] == "0" && $this->adData["categoryOptions"]["PERMISSION_FOR_PAID_DOWNLOADS"] == "1" ) {
		                $show_paid_document_download_option = 1;

		                if ( isset($this->adData["categoryOptions"]["FORCE_ATLEAST_ONE_PAID_DOWNLOAD"])
		                     && $this->adData["categoryOptions"][""] == "1" ) {
			                $tpl_step->addvar("FORCE_ATLEAST_ONE_PAID_DOWNLOAD_1",1);
		                }
		                else {
			                $tpl_step->addvar("FORCE_ATLEAST_ONE_PAID_DOWNLOAD_0",0);
		                }
	                }
	                $tpl_step->addvar("SHOW_PAID_DOCUMENT_DOWNLOAD_OPTION",$show_paid_document_download_option);
	                //....
                    if (!empty($this->adData["_VARIANTS_FIELDS"])) {
                        $arImageVariantInputs = Ad_Marketplace::getVariantFields($this->adData["FK_TABLE_DEF"], $this->katId);
                        $arImageVariantInputsTpl = array();
                        foreach ($arImageVariantInputs as $i => $arVariantField) {
                            $tplField = new Template("tpl/".$s_lang."/my-marktplatz-neu-images.variants.row.htm");
                            $tplField->isTemplateCached = true;
                            $tplField->addvars($arVariantField);
                            $arImageVariantInputsTpl[] = $tplField->process(true);
                        }
                        $tpl_step->addvar("HAS_VARIANTS", 1);
                        $tpl_step->addvar("imageVariantInputs", implode("\n", $arImageVariantInputsTpl));
                    }
                    break;
                case 'ARTICLE_CONFIRM':
                    $article = Api_Entities_MarketplaceArticle::createFromMasterArray($this->adData);
                    $arArticleData = array_flatten($this->adData, true, "_", "AD_");
                    $tpl_step->addvar("AD_FK_USER", $this->userId);
                    // Basic settings
                    $tpl_step->addvar("aktiv", true);
                    $tpl_step->addvar("preview", true);
                    $tpl_step->addvar("buying_enabled", ($nar_systemsettings["MARKTPLATZ"]["BUYING_ENABLED"] ? true : false));
                    $tpl_step->addvar("B_SALES", $this->db->fetch_atom("SELECT B_SALES FROM kat where ID_KAT=".$this->katId));
                    $tpl_step->addvar("comments_enabled", ($nar_systemsettings["MARKTPLATZ"]["ALLOW_COMMENTS_AD"] ? $arArticleData["ALLOW_COMMENTS"] : false));
                    $tpl_step->addvar("USE_CART", $nar_systemsettings['MARKTPLATZ']['BUYING_ENABLED'] & $nar_systemsettings['MARKTPLATZ']['USE_CART']);
                    
                    // Hersteller
                    $arArticleData["AD_MANUFACTURER"] = $this->adData["HERSTELLER"];
                    // Anzeigentitel / Produktname
                    $arArticleData["AD_TITLE"] = $arArticleData['AD_PRODUKTNAME'];
                            /*$this->db->fetch_atom("
                        SELECT s.V1
                        FROM product p
                        LEFT JOIN string_product s
                            ON s.S_TABLE='product' and s.FK=p.ID_PRODUCT
                            and s.BF_LANG=if(p.BF_LANG_PRODUCT & ".$langval.", ".$langval.", 1 << floor(log(".$langval."+0.5)/log(2)))
                        WHERE p.ID_PRODUCT=".(int)$this->adData["FK_PRODUCT"]);*/

                    // Status
                    $arArticleData["AD_SOLD"] = (($this->adData["STATUS"]&4)==4 ? true : false);
                    // Beschreibung
                    require_once $ab_path."sys/lib.bbcode.php";
                    $bbcode = new bbcode();
                    $arArticleData["AD_DESCRIPTION"] = ($nar_systemsettings['MARKTPLATZ']['ALLOW_HTML'] == 0 ? nl2br($article->getDescriptionText(true)) : $article->getDescriptionHtml(null, true));
                    // Standort
                    $arArticleData["AD_COUNTRY"] = $this->db->fetch_atom("SELECT V1 FROM string WHERE S_TABLE='country' AND BF_LANG=".$langval." AND FK=".(int)$this->adData["FK_COUNTRY"]);
                    // Marktplatz einstellungen
                    $arArticleData = array_merge($arArticleData, array_flatten($nar_systemsettings["MARKTPLATZ"], true, "_", "SETTINGS_MARKTPLATZ_"));

                    // B2B/...
                    $arArticleData = AdConstraintManagement::appendAdContraintMapping($arArticleData, "USER_CONSTRAINTS_ALLOWED");
                    $arArticleData = AdConstraintManagement::appendAdContraintMapping($arArticleData, "BF_CONSTRAINT");

                    // Base Price
                    if (isset($this->adData["BASISPREIS_PREIS"]) && $this->adData["BASISPREIS_PREIS"] > 0) {
                        $arArticleData["AD_BASISPREIS_EINHEIT"] = $this->db->fetch_atom("SELECT V1
                              FROM liste_values l
                              LEFT JOIN string_liste_values s ON
                                s.FK=l.ID_LISTE_VALUES AND s.S_TABLE='liste_values' AND
                                s.BF_LANG=if(l.BF_LANG_LISTE_VALUES & " . $langval . ", " . $langval . ", 1 << floor(log(l.BF_LANG_LISTE_VALUES+0.5)/log(2)))
                              WHERE l.ID_LISTE_VALUES='" . $this->adData["BASISPREIS_EINHEIT"] . "'"
                        );
                    }
                    // Rent
                    if ($nar_systemsettings['MARKTPLATZ']['ENABLE_RENT'] && ($this->adData["VERKAUFSOPTIONEN"] == 3) && !empty($this->adData["MIETPREISE"])) {
                        $arMietpreise = unserialize($this->adData["MIETPREISE"]);
                        $arMietpreiseList = array();
                        $arMietpreiseRuntimes = Api_LookupManagement::getInstance($this->db, $langval)->readByArt("VERMIETEN");
                        foreach ($arMietpreiseRuntimes as $indexRuntime => $arRuntime) {
                            if (array_key_exists($arRuntime["ID_LOOKUP"], $arMietpreise)) {
                                $arMietpreiseList[] = array_merge($arRuntime, array(
                                    "PRICE"	=> $arMietpreise[$arRuntime["ID_LOOKUP"]]
                                ));
                            }
                        }
                        $tpl_step->addlist("list_rent", $arMietpreiseList, "tpl/".$s_lang."/marktplatz_anzeige.row_rent.htm");
                    }
                    // Images
                    $arImages = $this->adData['images'];
                    if (!empty($arImages)) {
                        foreach ($arImages as $imageIndex => $arImage) {
                            if ($arImage['IS_DEFAULT']) {
                                $arImageFirst = $arImage;
                                array_splice($arImages, $imageIndex, 1);
                                break;
                            }
                        }

                        if (!empty($arImageFirst["SRC"])) {
                            $tpl_step->addvar("product_image", $arImageFirst["SRC"]);
                        } else {
                            $tpl_step->addvar("product_image", 'data:'.$arImageFirst['TYPE'].';base64,'.base64_encode( @file_get_contents($arImageFirst['TMP']) ));
                            $tpl_step->addvar("product_image_thumbnail", 'data:image/jpeg;base64,'.base64_encode( @file_get_contents($arImageFirst['TMP']) ));
                        }
                        foreach ($arImages as $imageIndex => $arImage) {
                            if ($arImage['FK_AD'] == 0) {
                                $arImages[$imageIndex]['BASE64'] = base64_encode( @file_get_contents($arImage['TMP']) );
                                $arImages[$imageIndex]['BASE64_THUMB'] = base64_encode( @file_get_contents($arImage['TMP_THUMB']) );
                            }
                        }
                        $tpl_step->addlist("product_images", $arImages, "tpl/".$s_lang."/marktplatz_images.row.htm");
                    }
                    // Videos
                    if (count($this->adData['videos']) > 0) {
                        $tpl_step->addlist("product_videos", $this->adData['videos'], "tpl/".$s_lang."/marktplatz_videos.row.htm");
                    }
                    // Documents
                    $arUploads = $this->adData['uploads'];
                    if (count($arUploads) > 0) {
	                    $article_files_free = array();
	                    $article_files_paid = array();
                        foreach ($arUploads as $uploadIndex => $arUpload) {
                            $arUploads[$uploadIndex]['FILENAME_SHORT'] = substr($arUpload["FILENAME"], 0, 32);
                            if ( $arUpload["IS_PAID"] == "1" ) {
                            	array_push($article_files_paid,$arUploads[$uploadIndex]);
                            }
	                        if ( $arUpload["IS_PAID"] == "0" || !isset($arUpload["IS_PAID"]) ) {
		                        array_push($article_files_free,$arUploads[$uploadIndex]);
	                        }
                        }
	                    if (count($article_files_free) > 0) {
		                    $tpl_step->addlist("product_files_free", $article_files_free, "tpl/".$s_lang."/marktplatz_files.row.htm");
	                    }
	                    if (count($article_files_paid) > 0) {
		                    $tpl_step->addlist("product_files_paid", $article_files_paid, "tpl/".$s_lang."/marktplatz_files.row.htm");
	                    }
                    }
                    // Variants
                    $arVariantFields = array();
                    if (!empty($this->adData["_VARIANTS_FIELDS"])) {
                        foreach ($this->adData["_VARIANTS_FIELDS"] as $variantFieldName => $variantFieldValues) {
                            $arVariantField = $this->getFieldByName($variantFieldName);
                            // Escape value
                            $arVariantValuesEscaped = array();
                            foreach ($variantFieldValues as $arVariantValue) {
                                $arVariantValuesEscaped[] = (int)$arVariantValue;
                            }
                            // List values
                            $arVariantValuesFull = $this->db->fetch_table("
                                SELECT t.*, s.V1, s.V2, s.T1
                                FROM `liste_values` t
                                LEFT JOIN `string_liste_values` s
                                    ON s.S_TABLE='liste_values' AND s.FK=t.ID_LISTE_VALUES
                                    AND s.BF_LANG=if(t.BF_LANG_LISTE_VALUES & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_LISTE_VALUES+0.5)/log(2)))
                                WHERE
                                    t.ID_LISTE_VALUES IN (".implode(",", $arVariantValuesEscaped).")
                                GROUP BY t.ID_LISTE_VALUES
                                ORDER BY t.ORDER ASC");
                            $arVariantField["liste"] = array();
                            foreach ($arVariantValuesFull as $arVariantValue) {
                                $tplVariantValue = new Template("tpl/".$s_lang."/marktplatz_anzeige.variant.row.htm");
                                $tplVariantValue->addvars($arVariantValue);
                                $arVariantField["liste"][] = $tplVariantValue;
                            }

                            $arVariantFields[] = $arVariantField;
                        }
                    }
                    $tpl_step->addlist("VARIANTS", $arVariantFields, 'tpl/'.$s_lang.'/marktplatz_anzeige.variant.htm');
                    
                    // Properties only available if ad is/was online
                    if ($this->adData['ID_AD_MASTER'] > 0) {
                        require_once $ab_path."sys/lib.ad_like.php";
                        // Likes, clicks and reminders
                        $adLikeManagement = AdLikeManagement::getInstance($this->db);
                        $arArticleData["adLikeCount"] = $adLikeManagement->countLikesByAdId($this->adData['ID_AD_MASTER']);
                        $arArticleData["adClicks"] = $this->db->fetch_atom("SELECT AD_CLICKS FROM ad_master WHERE ID_AD_MASTER = '".mysql_real_escape_string($this->adData['ID_AD_MASTER'])."'");
                        $arArticleData["adReminderCount"] = $this->db->fetch_atom("SELECT COUNT(*) as a FROM watchlist WHERE FK_REF = '".mysql_real_escape_string($this->adData['ID_AD_MASTER'])."' AND FK_REF_TYPE = 'ad_master'");
                    } else {
                        $arArticleData["NEW"] = 1;
                        $arArticleData["adLikeCount"] = 0;
                        $arArticleData["adClicks"] = 0;
                        $arArticleData["adReminderCount"] = 0;
                    }
                    // Set default variant values
                    if (!empty($arArticleData["_VARIANTS"])) {
                        foreach ($arArticleData["_VARIANTS"] as $arVariant) {
                            if ($arVariant['IS_DEFAULT']) {
                                $arArticleData = array_merge($arArticleData, $arVariant);
                            }
                        }
                    }
                    $tpl_step->addvars($arArticleData);
                                                    
                    /**
                     * Detail-Tabs
                     */
                    $arDetailTabs = array();
                    
                    // Description
                    $arDetailTabs[] = array(
                        "ACTIVE" => true,
                        "IDENT" => "marketplaceArticleDescription",
                        "LABEL" => Translation::readTranslation("marketplace", "description", null, array(), "Beschreibung"),
                        "CONTENT" => $arArticleData["AD_DESCRIPTION"]
                    );
                    // Html description fields
                    $htmlDescFields = $article->getFieldsHtml();
                    if (!empty($htmlDescFields)) {
                        foreach ($htmlDescFields as $htmlDescField) {
                            if (empty($htmlDescField["VALUE"])) {
                                continue;
                            }
                            $arDetailTabs[] = array(
                                "IDENT" => "marketplaceArticleDescription_".$htmlDescField["F_NAME"],
                                "LABEL" => $htmlDescField["V1"],
                                "CONTENT" => $htmlDescField["VALUE"]
                            );
                        }
                    }
                    // Availability
                    if (!empty($article_tpl["AD_AVAILABILITY"])) {
                        $arDetailTabs[] = array(
                            "IDENT" => "marketplaceArticleAvailability",
                            "LABEL" => Translation::readTranslation("marketplace", "availability", null, array(), "Verfügbarkeit"),
                            "CONTENT" => $tpl_step->tpl_subtpl("tpl/".$s_lang."/ad_availability_calendar.htm,ID_AD")
                        );
                    }
                    // Terms and conditions / Recall conditions
                    if ($GLOBALS["nar_systemsettings"]["MARKTPLATZ"]["BUYING_ENABLED"]) {
                        if (!empty($arArticleData["AD_AD_AGB"])) {
                            $arDetailTabs[] = array(
                                "IDENT" => "marketplaceArticleAGB",
                                "LABEL" => Translation::readTranslation("marketplace", "agb", null, array(), "AGB"),
                                "CONTENT" => $arArticleData["AD_AD_AGB"]
                            );
                        }
                        if (!empty($arArticleData["AD_AD_WIDERRUF"])) {
                            $arDetailTabs[] = array(
                                "IDENT" => "marketplaceArticleWiderruf",
                                "LABEL" => Translation::readTranslation("marketplace", "conditions", null, array(), "Widerrufsbelehrung"),
                                "CONTENT" => $arArticleData["AD_AD_WIDERRUF"]
                            );
                        }
                        if (!empty($this->adData['paymentAdapters'])) {
                            $paymentAdapterUserManagement = PaymentAdapterUserManagement::getInstance($this->db);
                            $paymentAdapersUser = $paymentAdapterUserManagement->fetchAllAvailablePaymentAdapterByUser($this->userId);
                            $arPaymentAdapters = array();
                            foreach ($this->adData['paymentAdapters'] as $paymentAdapterId => $paymentAdapterName) {
                                foreach ($paymentAdapersUser as $paymentAdapterUser) {
                                    if ($paymentAdapterUser["ID_PAYMENT_ADAPTER"] == $paymentAdapterId) {
                                        $arPaymentAdapters[] = $paymentAdapterUser;
                                        break;
                                    }
                                }
                            }
                            $arDetailTabs[] = array(
                                "IDENT" => "marketplaceArticleZahlungsinformation",
                                "LABEL" => Translation::readTranslation("marketplace", "payment.information", null, array(), "Zahlungsinformationen"),
                                "CONTENT" => Template::createTemplateList('tpl/'.$s_lang.'/marktplatz_anzeige.payment_adapter.htm', $arPaymentAdapters)
                            );
                        }
                    }
                    
                    // Plugin event
                    $eventAdDetailsParams = new Api_Entities_EventParamContainer(array(
                        "article"			=> $article,
                        "tabs"		        => $arDetailTabs,
                        "template"			=> $tpl_step
                    ));
                    Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_AD_DETAILS, $eventAdDetailsParams);
                    $tpl_step->addlist("PLUGIN_TABS_LINKS", $eventAdDetailsParams->getParam("tabs"), "tpl/".$s_lang."/marktplatz_anzeige.details.tabs.nav.htm");
                    $tpl_step->addlist("PLUGIN_TABS_PANES", $eventAdDetailsParams->getParam("tabs"), "tpl/".$s_lang."/marktplatz_anzeige.details.tabs.content.htm");
                    
                    // Trigger plugin event
                    $paramAdConfirm = new Api_Entities_EventParamContainer(array(
                        "data"		=> $this->adData,
                        "template"	=> $tpl_step
                    ));
                    Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_AD_CONFIRM, $paramAdConfirm);
                    
                    $tpl_step->isTemplateCached = true;
                    $tpl_step->isTemplateRecursiveParsable = true;
                    break;
                case 'VARIANTS':
                    $variantTable = (is_array($this->adData['_VARIANTS']) ? $this->adData['_VARIANTS'] : array());

                    $arVariantFields = array();
                    if (is_array($this->adData['_VARIANTS_FIELDS'])) {
                        foreach($this->adData['_VARIANTS_FIELDS'] as $variantFieldName => $variantFieldValues) {
                            if (!empty($variantFieldValues)) {
                                // Escape value
                                $arVariantValuesEscaped = array();
                                foreach ($variantFieldValues as $arVariantValue) {
                                    $arVariantValuesEscaped[] = (int)$arVariantValue;
                                }
                                // Save new values
                                $arVariant = $this->getFieldByName($variantFieldName);
                                $arVariant["values"] = $this->db->fetch_table("
                                    SELECT t.*, s.V1, s.V2, s.T1
                                    FROM `liste_values` t
                                    LEFT JOIN `string_liste_values` s
                                        ON s.S_TABLE='liste_values' AND s.FK=t.ID_LISTE_VALUES
                                        AND s.BF_LANG=if(t.BF_LANG_LISTE_VALUES & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_LISTE_VALUES+0.5)/log(2)))
                                    WHERE
                                        t.ID_LISTE_VALUES IN (".implode(",", $arVariantValuesEscaped).")
                                    GROUP BY t.ID_LISTE_VALUES
                                    ORDER BY t.ORDER ASC");
                                $arVariantFields[$variantFieldName] = $arVariant;
                            }
                        }
                    }

                    $colModel = array();
                    $colNames = array();
                    foreach($arVariantFields as $variantFieldName => $variantFieldValues) {
                        $colNames[] = $this->db->fetch_atom("
                            SELECT
                                s.V1
                            FROM `field_def` f
                            LEFT JOIN `string_field_def` s
                                ON s.S_TABLE='field_def' AND s.FK=f.ID_FIELD_DEF
                                AND s.BF_LANG=if(f.BF_LANG_FIELD_DEF & ".$langval.", ".$langval.", 1 << floor(log(f.BF_LANG_FIELD_DEF+0.5)/log(2)))
				            LEFT JOIN `table_def` t ON t.ID_TABLE_DEF=f.FK_TABLE_DEF
				            LEFT JOIN `kat` k ON k.KAT_TABLE=t.T_NAME
                            WHERE f.F_NAME='".mysql_real_escape_string($variantFieldName)."'
                                AND k.ID_KAT='".(int)$this->katId."'");
                        $colModel[] = array(
                            'name' => $variantFieldName,
                            'index' => $variantFieldName,
                            'sortable' => FALSE
                        );
                    }

                    $variantTableData = array();
                    $hasDefault = false;
                    foreach($variantTable as $variantIndex => $variantTableRow) {
                        $tmpRow = array();
                        foreach ($variantTableRow['FIELDS'] as $fieldIndex => $arField) {
                            $arFieldFull = $this->db->fetch1("
                                SELECT t.*, s.V1, s.V2, s.T1
                                FROM `liste_values` t
                                LEFT JOIN `string_liste_values` s
                                    ON s.S_TABLE='liste_values' AND s.FK=t.ID_LISTE_VALUES
                                    AND s.BF_LANG=if(t.BF_LANG_LISTE_VALUES & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_LISTE_VALUES+0.5)/log(2)))
                                WHERE
                                    t.ID_LISTE_VALUES=".$arField["LIST_VALUE"]."
                                GROUP BY t.ID_LISTE_VALUES");
                            $tmpRow[$arField['F_NAME']] = $arFieldFull['V1'];
                        }
                        $tmpRow['id'] = $variantIndex;
                        $tmpRow['IS_DEFAULT'] = $variantTableRow["IS_DEFAULT"];
                        $tmpRow['MENGE'] = $variantTableRow['MENGE'];
                        $tmpRow['PREIS'] = $variantTableRow['PREIS'];
                        $tmpRow['STATUS'] = $variantTableRow['STATUS'];
                        if ($tmpRow['IS_DEFAULT']) {
                            $hasDefault = true;
                        }

                        $variantTableData[] = $tmpRow;
                    }
                    if (!empty($variantTableData) && ($hasDefault == false)) {
                        $variantTableData[0]['IS_DEFAULT'] = true;
                    }

                    $tpl_step->addvar('COLMODEL', json_encode($colModel));
                    $tpl_step->addvar('COLNAMES', json_encode($colNames));
                    $tpl_step->addvar('VARIANTDATA', json_encode($variantTableData));
                    $tpl_step->addvar('CURRENCY_DEFAULT', $nar_systemsettings['MARKTPLATZ']['CURRENCY']);
                    $tpl_step->addvars($this->adData);
                    break;
            }
        }
        // Trigger plugin event
        $paramRenderStep = new Api_Entities_EventParamContainer(array(
            "adCreate"  => $this,
            "template"	=> $tpl_step,
            "step"      => $arStep
        ));
        Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_AD_CREATE_RENDER_STEP, $paramRenderStep);
        return $tpl_step->process();
    }


    public function validateField($name, $value, $needed = null, $type = null) {
        if (($needed === null) || ($type === null)) {
            $arField = $this->getFieldByName($name);
            $needed = $arField["B_NEEDED"];
            $type = $arField["F_TYP"];
        } else {
            $type = strtoupper($type);
        }
        $arResult = array(
            'valid' => 1,
            'error' => "",
            'type' => $type,
            'required' => $needed
        );
        /**************************************
         * Standard-Felder kontrollieren
         **************************************/
        if (!is_numeric($value) && empty($value) && $needed) {
            $arResult["fname"] = $name;
            $arResult["valid"] = 0;
            $arResult["error"] = "FIELD_NEEDED";
            $arResult["error_msg"] = implode("", get_messages("AD_NEW", $arResult["error"]));
            return $arResult;
        }

        switch ($type) {
            /*************************
             * Zahlen
             *************************/
            case 'INT':
                if (!empty($value) || $needed) {
                    $value = str_replace(",", ".", $value);
                    if (round($value) != $value) {
                        $arResult["fname"] = $name;
                        $arResult["valid"] = 0;
                        $arResult["error"] = "NOT_INTEGER";
                    }
                    if (!is_numeric($value)) {
                        $arResult["fname"] = $name;
                        $arResult["valid"] = 0;
                        $arResult["error"] = "NOT_NUMERIC";
                    }
                }
                break;
            case 'FLOAT':
                $value = str_replace(",", ".", $value);
                if (!empty($value) || $needed) {
                    if (!is_numeric($value)) {
                        $arResult["fname"] = $name;
                        $arResult["valid"] = 0;
                        $arResult["error"] = "NOT_NUMERIC";
                    }
                }
                break;
            /*************************
             * Auswahllisten
             *************************/
            case 'LIST':
            case 'LISTE':
                if ((!is_numeric($value) || ($value <= 0)) && ($needed == 1)) {
                    $arResult["fname"] = $name;
                    $arResult["valid"] = 0;
                    $arResult["error"] = "INVALID_SELECTION";
                }
                break;
			case 'VARIANT':
            case 'MULTICHECKBOX':
            case 'MULTICHECKBOX_AND':
                if ((((int)$value == 0) || empty($value)) && ($needed == 1)) {
                    $arResult["fname"] = $name;
                    $arResult["valid"] = 0;
                    $arResult["error"] = "FIELD_NEEDED";
                }
                break;
        }
        switch ($name) {
            /*************************
             * Kurzer Text
             *************************/
            case 'PRODUKTNAME':
                // - Artikelbezeichnung
                if (strlen(trim($value)) < 2) {
                    $arResult["fname"] = $name;
                    $arResult["valid"] = 0;
                    $arResult["error"] = "TOO_SHORT";
                }
                break;
            case 'ZIP':
            case 'CITY':
                // - Postleitzahl
                // - Ort
                if (strlen(trim($value)) < 3) {
                    $arResult["fname"] = $name;
                    $arResult["valid"] = 0;
                    $arResult["error"] = "TOO_SHORT";
                }
                break;
            /*************************
             * Langer Text
             *************************/
            case 'BESCHREIBUNG':
                $value = strip_tags($value);	// Remove html tags
                break;
            /*************************
             * Auswahllisten (Zahl>0)
             *************************/
            case 'FK_COUNTRY':
            case 'ZUSTAND':
                // - Land
                // - Versandkosten
                // - Breite, Höhe, Tiefe
                // - Leistung
                if (!is_numeric($value) || ($value <= 0)) {
                    $arResult["fname"] = $name;
                    $arResult["valid"] = 0;
                    $arResult["error"] = "INVALID_SELECTION";
                }
                break;
            /*************************
             * Positive Zahl (größer Null)
             *************************/
            case 'MENGE':
            case 'PREIS':
                $value = str_replace(',', '.', $value);
                if ($value < 0) {
                    $arResult["fname"] = $name;
                    $arResult["valid"] = 0;
                    $arResult["error"] = "NEGATIVE_NUMBER";
                }
                if (!empty($value) || $needed) {
                    // Nur prüfen wenn nicht leer oder Pflichtfeld
                    if (!is_numeric($value)) {
                        $arResult["fname"] = $name;
                        $arResult["valid"] = 0;
                        $arResult["error"] = "NOT_NUMERIC";
                    }
                    if ($value < 0.01) {
                        $arResult["fname"] = $name;
                        $arResult["valid"] = 0;
                        $arResult["error"] = "NULL_NUMBER";
                    }
                }
                break;
            case 'AUTOBUY':
                $value = str_replace(',', '.', $value);
                if (!empty($value) && ($value != 0)) {
                    if (!is_numeric($value)) {
                        $arResult["fname"] = $name;
                        $arResult["valid"] = 0;
                        $arResult["error"] = "NOT_NUMERIC";
                    }
                    if ($value < 0) {
                        $arResult["fname"] = $name;
                        $arResult["valid"] = 0;
                        $arResult["error"] = "NEGATIVE_NUMBER";
                    }
                    if ($value < 0.01) {
                        $arResult["fname"] = $name;
                        $arResult["valid"] = 0;
                        $arResult["error"] = "NULL_NUMBER";
                    }
                }
                break;
            /*************************
             * Positive Zahl
             *************************/
            case 'VERSANDKOSTEN':
            case 'BREITE': case 'HOEHE': case 'TIEFE':
            case 'LEISTUNG':
                // - Verkaufspreis
                // - Versandkosten
                // - Breite, Höhe, Tiefe
                // - Leistung
                $value = str_replace(',', '.', $value);
                if ($needed || !empty($value)) {
                    if (!is_numeric($value)) {
                        $arResult["fname"] = $name;
                        $arResult["valid"] = 0;
                        $arResult["error"] = "NOT_NUMERIC";
                    }
                }
                if ($value < 0) {
                    $arResult["fname"] = $name;
                    $arResult["valid"] = 0;
                    $arResult["error"] = "NEGATIVE_NUMBER";
                }
                break;
        }
        
		// Trigger plugin event
		$paramAdValidate = new Api_Entities_EventParamContainer(array(
            "fieldName"     => $name,
            "fieldValue"    => $value,
            "fieldNeeded"   => $needed,
            "fieldType"     => $type,
            "adCreate"      => $this,
            "result"        => $arResult
        ));
		Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_AD_FIELD_VALIDATE, $paramAdValidate);
        if ($paramAdValidate->isDirty()) {
            $arResult = $paramAdValidate->getParam("result");
        }
        
        if (!$arResult["valid"] && !array_key_exists("error_msg", $arResult)) {
            $arResult["error_msg"] = implode("", get_messages("AD_NEW", $arResult["error"]));
        }
        return $arResult;
    }
} 