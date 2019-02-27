<?php
/* ###VERSIONSBLOCKINLCUDE### */



class ArticleManagement {
    const imageMaxWidth = 745;
    const imageMaxHeight = 600;
    
	private static $db;
    private static $langval = 128;
	private static $instance = null;

	/**
	 * Singleton 
	 * 
	 * @param ebiz_db $db
	 * @return ArticleManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);
		
		return self::$instance;
	}	

    /**
     * Holt einen Artikel anhand einer Artikel Id
     *
     * @throws 	Exception
     * @param 	$articleId
     * @return 	assoc
     */
    public function fetchByArticleId($articleId) {
        $db = $this->getDb();

        $article = $db->fetch1($x = "
            SELECT
                n.*,
                s.*,
    			if(n.OK&1,1,0) as FREIGABE, 
    			if(n.OK&2,1,0) as FREIGABE2
            FROM
                `news` n
			LEFT JOIN `string_c` s ON 
				s.FK=n.ID_NEWS AND s.S_TABLE='news' AND 
				s.BF_LANG=if(n.BF_LANG_C & ".$this->getLangval().", ".$this->getLangval().", 1 << floor(log(n.BF_LANG_C+0.5)/log(2)))
			WHERE
				n.ID_NEWS=".$articleId);
        
        return $article;
    }

    public function fetchAllArticlesByUserId($userId, $param = array()) {
        $db = $this->getDb();

        $sqlLimit = "";
        if(isset($param['LIMIT']) && $param['LIMIT'] != null) {
            if(isset($param['OFFSET']) && $param['OFFSET'] != null) { $sqlLimit = ' '.mysql_real_escape_string((int) $param['OFFSET']).', '.mysql_real_escape_string((int) $param['LIMIT']).' '; } else { $sqlLimit = ' '.mysql_real_escape_string((int) $param['LIMIT']).' '; }
        }

        return $db->fetch_table("
            SELECT
                n.*,
                s.*,
    			if(n.OK&1,1,0) as FREIGABE,
    			if(n.OK&2,1,0) as FREIGABE2
            FROM
                `news` n
			LEFT JOIN `string_c` s ON
				s.FK=n.ID_NEWS AND s.S_TABLE='news' AND
				s.BF_LANG=if(n.BF_LANG_C & ".$this->getLangval().", ".$this->getLangval().", 1 << floor(log(n.BF_LANG_C+0.5)/log(2)))
			WHERE
				n.FK_USER=".mysql_real_escape_string($userId)."
				AND n.OK = 3
		    ORDER BY STAMP DESC
		    ".($sqlLimit?'LIMIT '.$sqlLimit:'')."
        ");
    }

	/**
	 * Speichert einen Artikel in mehreren Sprachen
	 * 
	 * @param $articleData	assoc	Assoziatives Array mit den Daten des Artikels.
	 * @param $articleId	int		ID des Artikels (optional)
	 */
    public function saveArticleMultiLang($articleData, $articleId = null) {
    	if ($articleId == null) $articleId = $articleData["ID_NEWS"];
    	
    	$articleDataBase = $articleData;
        unset($articleDataBase["V1"]);
        unset($articleDataBase["V2"]);
        unset($articleDataBase["T1"]);
    	
		if (!$articleDataBase['FREIGABE']) $articleDataBase['FREIGABE'] = 0;
		$articleDataBase['OK'] = ($articleDataBase['FREIGABE'] > 0 ? 1 : 0);
		$articleDataBase['FK_AUTOR'] = $articleDataBase['FK_USER'];
		    if ($articleDataBase['OK']) {
            if ( !isset($articleDataBase['ID_NEWS']) ) {
                $articleDataBase['STAMP'] = date("Y-m-d H:i");
            }
        }

        // Process links
        $linksHtml = array();
        foreach ($articleData['links'] as $linkIndex => $arLink) {
            if (preg_match("/^https?:\/\/.+$/i", $arLink["href"])) {
                // Valid link
                $arLink["label"] = trim($arLink["label"]);
                if (empty($arLink["label"])) {
                    $arLink["label"] = $arLink["href"];
                }
                $linksHtml[] = "<!-- Link ".($linkIndex+1)." -->".
                    "<li>".
                        '<a target="_blank" rel="nofollow" title="'.htmlspecialchars($arLink["label"]).'" href="'.htmlspecialchars($arLink["href"]).'">'.
                        htmlspecialchars($arLink["label"]).
                        "</a>".
                    "</li>";
            }
        }
        $articleDataBase['LINKS'] = implode(" ", $linksHtml)."<!-- Link 9999 -->";
        $articleDataBase["SER_IMAGES"] = null;
        $articleDataBase["SER_MEDIA"] = array(
            "images" => array(),
            "videos" => array(),
            "preview" => array()
        );
        if (array_key_exists("MEDIA_PREVIEW", $articleData)) {
            foreach ($articleData["MEDIA_PREVIEW"] as $previewLang => $previewUrl) {
                $articleDataBase["SER_MEDIA"]["preview"][$previewLang] = $previewUrl;
            }
        }
    	
        $db = $this->getDb();
        
        // validate addressData
        $addressDataKeys = array("TITLE", "LOCATION", "LU_ANREDE", "FIRSTNAME", "LASTNAME", "COMPANY", "PHONE", "MOBILE", "FAX", "EMAIL", "STREET", "ZIP", "CITY", "FK_COUNTRY", "LATITUDE", "LONGITUDE");
        foreach($addressDataKeys as $addressDataKey) {
            if(array_key_exists($addressDataKey, $articleData) && empty($articleData[$addressDataKey])) {
                $articleDataBase[$addressDataKey] = NULL;
            }
        }

        // process address
        $mapsLanguage = $GLOBALS["s_lang"];
        $q_country = 'SELECT s.V1
					FROM country c
					INNER JOIN string s
					ON c.ID_COUNTRY = '.$articleDataBase["FK_COUNTRY"].'
					AND s.S_TABLE = "COUNTRY"
					AND s.FK = c.ID_COUNTRY
					INNER JOIN lang l
					ON l.ABBR = "'.$mapsLanguage.'"
					AND s.BF_LANG = l.BITVAL';
        $geoCoordinates = Geolocation_Generic::getGeolocationCached(
            $articleDataBase["STREET"],
            $articleDataBase["ZIP"],
            $articleDataBase["CITY"],
            $db->fetch_atom($q_country),
            $mapsLanguage
        );
        $articleDataBase["LATITUDE"] = $geoCoordinates['LATITUDE'];
        $articleDataBase["LONGITUDE"] = $geoCoordinates['LONGITUDE'];

        $languages = $articleDataBase["langs"];
        foreach ($languages as $langval) {
            $articleDataBase["SER_MEDIA"]["images"][$langval] = null;
            $articleDataBase["SER_MEDIA"]["videos"][$langval] = null;
        	$ar_cur_lang = $articleDataBase;
        	$ar_cur_lang["V1"] = $articleData["V1"][$langval];
        	$ar_cur_lang["V2"] = $articleData["V2"][$langval];
        	$ar_cur_lang["T1"] = $this->saveArticleParseText(
                $articleData["T1"][$langval], $articleData["ID_NEWS"], $articleData["FK_USER"], $langval, 
                $articleDataBase["SER_MEDIA"]["images"][$langval], $articleDataBase["SER_MEDIA"]["videos"][$langval]
            );
            if (!array_key_exists($langval, $articleDataBase["SER_MEDIA"]["preview"]) || ($articleDataBase["SER_MEDIA"]["preview"][$langval] === null)) {
                if (!empty($articleDataBase["SER_MEDIA"]["videos"][$langval])) {
                    $articleDataBase["SER_MEDIA"]["preview"][$langval] = $articleDataBase["SER_MEDIA"]["videos"][$langval][0];
                } else if (!empty($articleDataBase["SER_MEDIA"]["images"][$langval])) {
                    $articleDataBase["SER_MEDIA"]["preview"][$langval] = $articleDataBase["SER_MEDIA"]["images"][$langval][0];
                }
            }
            $ar_cur_lang["SER_IMAGES"] = $articleDataBase["SER_IMAGES"];
            $ar_cur_lang["SER_MEDIA"] = serialize($articleDataBase["SER_MEDIA"]);
		       
        	if (!empty($ar_cur_lang["V1"]) || !empty($ar_cur_lang["V2"]) || !empty($ar_cur_lang["T1"])) {
	        	$ar_cur_lang["BF_LANG_C"] = $langval;
	        	$id_insert = $db->update("news", $ar_cur_lang);
	        	if (($articleId == null) && ($id_insert > 0)) {
	        		$articleId = $articleDataBase["ID_NEWS"] = $id_insert; 
	        	}
        	}
        }
        return $articleId;
    }
    
    private function saveArticleParseText($text, $id_news, $id_user, $langval, &$images = null, &$videos = null) {
    	global $ab_path, $tpl_main;
        $db = $this->getDb();
        
		### TAG FILTER
		$text = strip_tags($text, "<img><br><b><strong><i><u><ul><li><ol><p><em><a><div><iframe>");
		### KICK STYLES (for all tags except images)
		$text = preg_replace("/(\<(?!img)[^\>]*)(style=)(\"|')([^\"']*)(\"|')/", "\$1", $text);
        //$text = preg_replace("/(style=)(\"|')([^\"']*)(\"|')/", "", $text);
        /**
         * Bilder skalieren falls nötig
         * @var DOMDocument $domContent
         */
        $domContent = new DOMDocument();
        $domContent->loadHTML("<html><body id='newsContent'>".$text."</body></html>");
        /**
         * Check all images
         * @var DOMElement $domImage
         */
        $arImagesSrc = array();
        $urlBaseDefault = $GLOBALS["nar_systemsettings"]["SITE"]["BASE_URL"];
        foreach ($GLOBALS["lang_list"] as $langAbbr => $langDetails) {
            if ($langDetails["BITVAL"] == $langval) {
                if (!empty($langDetails["BASE_URL"]) && ($langDetails["BASE_URL"] != "/")) {
                    $urlBaseDefault = $langDetails["BASE_URL"];
                }
                break;
            }
        }
        $domListImages = $domContent->getElementsByTagName("img");
        foreach ($domListImages as $domImage) {
            $imageSourceAttr = $domImage->getAttributeNode("src");
            $imageSourceOrigAttr = $domImage->getAttributeNode("data-src");
            $imageSource = false;
            if ($imageSourceAttr !== false) {
                $imageSource = ($imageSourceOrigAttr !== false ? $imageSourceOrigAttr->value : $imageSourceAttr->value);
            }
            if (strpos($imageSource, $urlBaseDefault) === 0) {
                $imageSourceRel = str_replace($urlBaseDefault, "/", $imageSource);
            } else {
                $imageSourceRel = $imageSource;
                foreach ($GLOBALS["lang_list"] as $langAbbr => $langDetails) {
                    if (empty($langDetails["BASE_URL"])) {
                        continue;
                    }
                    if (strpos($imageSource, $langDetails["BASE_URL"]) === 0) {
                        $imageSourceRel = str_replace($langDetails["BASE_URL"], "/", $imageSource);
                    }
                }
            }
            if (($imageSource !== false) && (strpos($imageSource, "/") === 0)) {
                $imageSourceAbs = realpath($ab_path.$imageSourceRel);
                $imageSource = $urlBaseDefault.substr($imageSourceRel, 1);
                $imageWidth = self::imageMaxWidth;
                $imageHeight = self::imageMaxHeight;
                $imageWidthAttr = $domImage->getAttributeNode("width");
                $imageHeightAttr = $domImage->getAttributeNode("height");
                $domImage->setAttribute("src", $imageSource);
                if (($imageSourceAbs !== false) && (strpos($imageSourceAbs, $ab_path) == 0)) {
                    // Valid image within absolute path
                    $imageSize = getimagesize($imageSourceAbs);
                    if (($imageWidthAttr !== false) && ($imageWidthAttr->value > 0) && 
                        ($imageHeightAttr !== false) && ($imageHeightAttr->value > 0)) {
                        // Both dimensions set
                        $imageWidth = $imageWidthAttr->value;
                        $imageHeight = $imageHeightAttr->value;
                    } else if (($imageWidthAttr !== false) && ($imageWidthAttr->value > 0)) {
                        $imageWidth = $imageWidthAttr->value;
                        $imageHeight = $imageSize[1] / ($imageSize[0] / $imageWidth);
                    } else if (($imageHeightAttr !== false) && ($imageHeightAttr->value > 0)) {
                        $imageHeight = $imageHeightAttr->value;
                        $imageWidth = $imageSize[0] / ($imageSize[1] / $imageHeight);
                    }
                    if (($imageSize[0] > $imageWidth) || ($imageSize[1] > $imageHeight)) {
                        $thumbParams = '"'.$imageSourceRel.'",'.$imageWidth.','.$imageHeight;
                        $imageThumbnail = $tpl_main->tpl_thumbnail($thumbParams);
                        $imageThumbnailRel = $imageThumbnail;
                        #die(var_dump($thumbParams, $imageThumbnail, $imageSize, $imageWidth, $imageHeight));
                        foreach ($GLOBALS["lang_list"] as $langAbbr => $langDetails) {
                            if (empty($langDetails["BASE_URL"])) {
                                continue;
                            }
                            if (strpos($imageThumbnail, $langDetails["BASE_URL"]) === 0) {
                                $imageThumbnailRel = str_replace($langDetails["BASE_URL"], "/", $imageThumbnail);
                            }
                        }
                        $imageThumbnail = $urlBaseDefault.substr($imageThumbnailRel, 1);
                        list($imageWidth,$imageHeight) = getimagesize($ab_path.$imageThumbnailRel);
                        $domImage->setAttribute("src", $imageThumbnail);
                        $domImage->setAttribute("data-src", $imageSource);
                        $domImage->setAttribute("width", $imageWidth);
                        $domImage->setAttribute("height", $imageHeight);
                    }
                }
                $domImageParent = $domImage->parentNode;
                if ($domImageParent->tagName !== "a") {
                    // Add link for image slider
                    $domImageLink = $domContent->createElement('a');
                    $domImageLink->setAttribute("rel", "lightbox-gallery");
                    $domImageLink->setAttribute("href", $imageSource);
                    $domImageLink->appendChild( $domImage->cloneNode(true) );
                    // Replace image by linked image
                    $domImageParent->replaceChild($domImageLink, $domImage);
                }
                $arImagesSrc[] = $imageSource;
            }
        }
        if (!empty($arImagesSrc)) {
            if ($images !== null) {
                $arImagesSrc = array_merge($images, $arImagesSrc);
            }
            $images = $arImagesSrc;
        }
        /**
         * Check all images
         * @var DOMElement $domImage
         */
        $arVideosSrc = array();
        $domListVideos = $domContent->getElementsByTagName("iframe");
        foreach ($domListVideos as $domVideo) {
            $videoSourceAttr = $domVideo->getAttributeNode("src");
            if (preg_match("/^https:\/\/www\.youtube\.com\/embed\/(.+)$/", $videoSourceAttr->value, $arVideoMatch)) {
                $arVideosSrc[] = $arVideoMatch[0];
            }
        }
        if (!empty($arVideosSrc)) {
            if ($videos !== null) {
                $arVideosSrc = array_merge($videos, $arVideosSrc);
            }
            $videos = $arVideosSrc;
        }
        /**
         * Save resulting DOM as HTML
         */
        $text = $domContent->saveHTML( $domContent->getElementById("newsContent") );
        $text = str_replace(array('<body id="newsContent">', '</body>'), '', $text);
        return $text;
    }
		
	/**
	 * @return ebiz_db $db
	 */
	public function getDb() {
		return self::$db;
	}
	
	/**
	 * @param ebiz_db $db
	 */
	public function setDb(ebiz_db $db) {
		self::$db = $db;
	}

    public function getLangval() {
        return self::$langval;
    }
    public function setLangval($langval) {
        self::$langval = $langval;
    }
	
	private function __construct() {
	}
	
	private function __clone() {
	}
}