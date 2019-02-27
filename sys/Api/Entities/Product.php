<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 11.06.15
 * Time: 10:58
 */

class Api_Entities_Product {

    /**
     * Create an article object by its data as an assoc array
     * @param array     $arArticleMaster
     * @return Api_Entities_Product
     */
    public static function createFromArray($arProductData, ebiz_db $db = null, $langval = null) {
        $articleTable = self::getArticleTableByCategory((int)$arProductData["FK_KAT"]);
        return new self($arProductData["ID_HDB_TABLE_".strtoupper($articleTable)], null, $arProductData, $db, $langval);
    }

    /**
     * Create an array of article objects by an array of assoc article datasets
     * @param array     $arProductList
     * @return array
     */
    public static function createMultipleFromArray($arProductList, ebiz_db $db = null, $langval = null) {
        $arResult = array();
        foreach ($arProductList as $productIndex => $arProduct) {
            $arResult[] = self::createFromArray($arProduct, $db, $langval);
        }
        return $arResult;
    }
    
    /**
     * Get an article object by id
     * @param int           $productId
     * @param bool          $useCache
     * @param ebiz_db|null  $db
     * @return Api_Entities_Product
     */
    public static function getById($productId, $categoryId, $useCache = true, ebiz_db $db = null, $langval = null) {
        if ($db === null) {
            $db = $GLOBALS['db'];
        }
        if ($langval === null) {
            $langval = $GLOBALS['langval'];
        }
        if ($useCache) {
            // Use cache to prevent loading the same article multiple times from database
            if (!array_key_exists($productId, self::$productCache)) {
                self::$productCache[$categoryId."#".$productId] = new self($productId, $categoryId, null, $db, $langval);
            }
            return self::$productCache[$categoryId."#".$productId];
        } else {
            // Do not use cache, force a new object which will read the most current article data from database
            return new self($productId, null, $db, $langval);
        }
    }

    private static function getArticleTableByCategory($categoryId, ebiz_db $db = null) {
        if ($db === null) {
            $db = $GLOBALS['db'];
        }
        return $db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".$categoryId);
    }
    
    protected static $productCache = array();
    
    protected $productId;
    protected $productData;
    protected $categoryId;
    protected $categoryTable;
    protected $fieldsGroups;
    protected $fieldsSystemGroups;
    protected $fieldsVariant;
    protected $db;
    protected $langval;
    
    function __construct($productId, $categoryId, $arProductData = null, ebiz_db $db = null, $langval = null) {
        $this->productId = ($productId > 0 ? (int)$productId : null);
        $this->productData = $arProductData;
        $this->categoryId = ($categoryId !== null ? (int)$categoryId : ($arProductData !== null ? $arProductData["FK_KAT"] : null));
        $this->categoryTable = null;
        $this->fieldsGroups = null;
        $this->fieldsSystemGroups = null;
        $this->fieldsVariant = null;
        $this->db = ($db === null ? $GLOBALS['db'] : $db);
        $this->langval = ($langval === null ? $GLOBALS['langval'] : $langval);
    }

    public function createJsonCache() {
        $result = array();
        foreach($GLOBALS["lang_list"] as $langAbbr => $langDetails) {
            $this->langval = $langDetails["BITVAL"];
            $this->fieldsGroups = null;
            $arGroups = $this->getData_FieldsGroups();
            foreach($arGroups as $groupIndex => $groupId) {
                $arFields = $this->getFields($groupId);
                foreach ($arFields as $index => $arField) {
                    if(!array_key_exists("V1", $arField)) {
                        $arFieldStrings = $this->getFieldStringsByName($arField["F_NAME"]);
                        if (is_array($arFieldStrings)) {
                            $arField = array_merge($arField, $arFieldStrings);
                        }
                    }
                    $fieldValue = $this->getData_Product($arField["F_NAME"]);
                    $result["raw"][$arField["F_NAME"]] = $fieldValue;
                    $result[$langAbbr."_label"][$arField["F_NAME"]] = $arField["V1"];
                    if(isset($fieldValue)) {
                        if($arField["F_TYP"] == "CHECKBOX") {
                            if($fieldValue == 1) {
                                $result[$langAbbr][$arField["F_NAME"]] = $arField["V1"];
                            } else {
                                $result[$langAbbr][$arField["F_NAME"]] = '';
                            }
                        } else if(in_array($arField["F_TYP"], ["MULTICHECKBOX", "MULTICHECKBOX_AND", "LIST"])) {
                            $ids = array_filter(explode('x', $fieldValue)); // checkbox-ids
                            if(!empty($ids)) {
                                $result[$langAbbr][$arField["F_NAME"]] = implode(', ', $this->db->fetch_col("
                              SELECT s.V1
                              FROM `liste_values` t
                              LEFT JOIN string_liste_values s
                                ON s.S_TABLE='liste_values' AND s.FK=t.ID_LISTE_VALUES
                                   AND s.BF_LANG=if(t.BF_LANG_LISTE_VALUES & ".$this->langval.", ".$this->langval.", 1 << floor(log(t.BF_LANG_LISTE_VALUES+0.5)/log(2)))
                              WHERE t.ID_LISTE_VALUES IN(".implode(',', $ids).")"));
                            } else {
                                $result[$langAbbr][$arField["F_NAME"]] = '';
                            }
                        } else if(in_array($arField["F_TYP"], ["TEXT", "LONGTEXT"])) {
                            $result[$langAbbr][$arField["F_NAME"]] = htmlentities($fieldValue);
                        } else if(in_array($arField["F_TYP"], ["INT", "FLOAT"])) {
                            if(isset($arField["V2"])) {
                                $result[$langAbbr][$arField["F_NAME"]] = $fieldValue.' '.htmlentities($arField["V2"]);
                            } else {
                                $result[$langAbbr][$arField["F_NAME"]] = $fieldValue;
                            }
                        } else if($arField["F_TYP"] == 'DATE') {
                            $result[$langAbbr][$arField["F_NAME"]] = date('d.m.Y', strtotime($fieldValue));
                        } else {
                            $result[$langAbbr][$arField["F_NAME"]] = $fieldValue;
                        }
                    } else {
                        $result[$langAbbr][$arField["F_NAME"]] = '';
                    }
                }
            }
        }
        /*
        echo "RESULT\r\n\r\n";
        var_dump($result);
        die();
        */
        // TODO: Update database
        return $result;
    }

    /**
     * Clear all cache files that are automatically generated
     */
    public function clearVolatileCache() {
        $cachePath = $this->getVolatileCachePath();
        if (file_exists($cachePath."/description.htm")) {
            unlink($cachePath."/description.htm");
        }
        if (file_exists($cachePath."/description.txt")) {
            unlink($cachePath."/description.txt");
        }
        if (file_exists($cachePath."/urls.json")) {
            unlink($cachePath."/urls.json");
        }
    }

    /**
     * Returns the article dataset as assoc array
     * @param string|null   $fieldName
     * @return array|string|null
     */
    public function getData_Product($fieldName = null) {
        if (($this->productData === null) && ($this->productId !== null)) {
            $articleTable = $this->getData_ArticleTable();
            $arProductData = $this->db->fetch1("
              SELECT * FROM `hdb_table_".mysql_real_escape_string($articleTable)."` 
              WHERE ID_HDB_TABLE_".mysql_real_escape_string(strtoupper($articleTable))."=".(int)$this->productId);
            if (is_array($arProductData)) {
                $this->productData = $arProductData;
                /*
                // Images
                $this->productData['images'] = $this->db->fetch_table("SELECT * FROM `ad_images` WHERE FK_AD=".(int)$this->productId);
                foreach ($this->productData['images'] as $imageIndex => $arImage) {
                    $arImageMeta = @unserialize($arImage["SER_META"]);
                    if (is_array($arImageMeta)) {
                        $this->productData['images'][$imageIndex]['META'] = $arImageMeta;
                    }
                }
                // Documents
                $this->productData['uploads'] = $this->db->fetch_table("SELECT * FROM `ad_upload` WHERE FK_AD=".(int)$this->productId);
                // Videos
                $this->productData['videos'] = $this->db->fetch_table("SELECT * FROM `ad_video` WHERE FK_AD=".(int)$this->productId);
                */
            }
        }
        if (is_array($this->productData)) {
            if ($fieldName !== null) {
                // Get single field data
                return (array_key_exists($fieldName, $this->productData) ? $this->productData[$fieldName] : null);
            } else {
                return $this->productData;
            }
        } else {
            return null;
        }
    }
    
    public function getData_ArticleTable() {
        if ($this->categoryTable === null) {
            $this->categoryTable = self::getArticleTableByCategory( $this->getData_CategoryId() );
        }
        return $this->categoryTable;
    }
    
    public function getData_ArticleTableId() {
        $articleTable = $this->getData_ArticleTable();
        if ($articleTable !== null) {
            $articleTableId = (int)$this->db->fetch_atom("SELECT ID_TABLE_DEF FROM `table_def` WHERE T_NAME='".mysql_real_escape_string($articleTable)."'");
            if ($articleTableId > 0) {
                return $articleTableId;
            }
        }
        return null;
    }
    
    public function getData_CategoryId() {
        if (($this->categoryId === null) && ($this->productData !== null)) {
            $this->categoryId = $this->getData_Product("FK_KAT");
        }
        return $this->categoryId;
    }

    /**
     * Returns the article dataset as assoc array
     * @return array
     */
    public function getData_FieldsGroups() {
        if ($this->fieldsGroups === null) {
            $articleTableId = $this->getData_ArticleTableId();
            if ($articleTableId !== null) {
                $this->fieldsGroups = $this->db->fetch_col("SELECT ID_FIELD_GROUP FROM `field_group` WHERE FK_TABLE_DEF=".$articleTableId);
            } else {
                $this->fieldsGroups = array();
            }
            $this->fieldsGroups[] = null;
            if ($this->getData_FieldsSystemGroups() !== null) {
                foreach ($this->fieldsSystemGroups as $groupIndex => $groupFields) {
                    $this->fieldsGroups[] = $groupIndex;
                }
            }
        }
        return $this->fieldsGroups;
    }

    /**
     * Returns the article dataset as assoc array
     * @return array
     */
    public function getData_FieldsSystemGroups() {
        if ($this->fieldsSystemGroups === null) {
            $arArticle = $this->getData_Product();
            if ($arArticle !== null) {
                require_once $GLOBALS["ab_path"]."sys/lib.ad_create.php";
                $this->fieldsSystemGroups = AdCreate::getSystemGroups( $arArticle );
            }
        }
        return $this->fieldsSystemGroups;
    }
    
    /**
     * Returns a field by name if found. Contains name, type and wheter its required.
     * @param   string $fieldName   Name of the field to be found
     * @return  array|bool          The field with name, type and whether its required as array or false if not found.
     */
    public function getFieldByName($fieldName) {
        $this->getData_FieldsSystemGroups();
        // Look for matching field in system groups
        foreach ($this->fieldsSystemGroups as $groupName => $arFields) {
            foreach ($arFields as $fieldIndex => $arField) {
                if ($arField["F_NAME"] == $fieldName) {
                    return $arField;
                }
            }
        }
        // No system field found, search in database
        $categoryId = $this->getData_CategoryId();
        $arField = $this->db->fetch1("
                SELECT
                    f.ID_FIELD_DEF, f.F_NAME, f.F_TYP, IFNULL(kf.B_NEEDED,f.B_NEEDED) AS B_NEEDED, s.*
				FROM `kat` k
				LEFT JOIN `table_def` t ON t.T_NAME=k.KAT_TABLE
				LEFT JOIN `field_def` f ON f.FK_TABLE_DEF=t.ID_TABLE_DEF
				LEFT JOIN `kat2field` kf ON kf.FK_KAT=k.ID_KAT AND kf.FK_FIELD=f.ID_FIELD_DEF
                LEFT JOIN `string_field_def` s ON s.S_TABLE='field_def' AND s.FK=f.ID_FIELD_DEF
					AND s.BF_LANG=if(f.BF_LANG_FIELD_DEF & ".$this->langval.", ".$this->langval.", 1 << floor(log(f.BF_LANG_FIELD_DEF+0.5)/log(2)))
				WHERE k.ID_KAT=".(int)$categoryId." AND kf.B_ENABLED=1 AND f.B_ENABLED=1
				    AND f.F_NAME='".mysql_real_escape_string($fieldName)."'");
        return $arField;
    }

    public function getFieldStringsByName($fieldName) {
        $categoryId = $this->getData_CategoryId();
        return $this->db->fetch1("
                SELECT
                    s.*
				FROM `kat` k
				LEFT JOIN `table_def` t ON t.T_NAME=k.KAT_TABLE
				LEFT JOIN `field_def` f ON f.FK_TABLE_DEF=t.ID_TABLE_DEF
				LEFT JOIN `kat2field` kf ON kf.FK_KAT=k.ID_KAT AND kf.FK_FIELD=f.ID_FIELD_DEF
                LEFT JOIN `string_field_def` s ON s.S_TABLE='field_def' AND s.FK=f.ID_FIELD_DEF
					AND s.BF_LANG=if(f.BF_LANG_FIELD_DEF & ".$this->langval.", ".$this->langval.", 1 << floor(log(f.BF_LANG_FIELD_DEF+0.5)/log(2)))
				WHERE k.ID_KAT=".(int)$categoryId." AND f.F_NAME='".mysql_real_escape_string($fieldName)."'");
    }

    /**
     * Returns a list of all fields with name, type and whether its required.
     * @param   int $idFieldGroup   Field group to be read
     * @return array    List of all fields with name, type and whether its required.
     */
    public function getFields($idFieldGroup = null) {
        $this->getData_FieldsSystemGroups();
        if (array_key_exists($idFieldGroup, $this->fieldsSystemGroups)) {
            // System group, read from array
            return $this->fieldsSystemGroups[$idFieldGroup];
        } else {
            // Regular group, read from database
            $categoryId = $this->getData_CategoryId();
            $arFields = $this->db->fetch_table("
                SELECT
                    f.F_NAME, f.F_TYP, f.FK_FIELD_GROUP, f.FK_LISTE, f.IS_SPECIAL, IFNULL(kf.B_NEEDED,f.B_NEEDED) AS B_NEEDED, s.*
                FROM `kat` k
                LEFT JOIN `table_def` t ON t.T_NAME=k.KAT_TABLE
                LEFT JOIN `field_def` f ON f.FK_TABLE_DEF=t.ID_TABLE_DEF
                LEFT JOIN `kat2field` kf ON kf.FK_KAT=k.ID_KAT AND kf.FK_FIELD=f.ID_FIELD_DEF
                        LEFT JOIN `string_field_def` s ON s.S_TABLE='field_def' AND s.FK=f.ID_FIELD_DEF
                  AND s.BF_LANG=if(f.BF_LANG_FIELD_DEF & ".$this->langval.", ".$this->langval.", 1 << floor(log(f.BF_LANG_FIELD_DEF+0.5)/log(2)))
                WHERE k.ID_KAT=".(int)$categoryId." AND kf.B_ENABLED=1 AND f.B_ENABLED=1
                    AND f.FK_FIELD_GROUP".($idFieldGroup === null ? " IS NULL" : "=".(int)$idFieldGroup)."
                ORDER BY f.FK_FIELD_GROUP ASC, f.F_ORDER ASC");
            return $arFields;
        }
    }
    
    /**
     * Get the id of the article
     * @return int|null
     */
    public function getId() {
        return $this->productId;
    }

    /**
     * Get the cache directory of the article
     * @param bool $createIfNotExist
     * @param bool $absoluteUrl
     * @return string
     */
    public function getCachePath($createIfNotExist = true, $absoluteUrl = true) {        
        $cachePathBase = "cache/marktplatz/product";
        $cachePathHash = md5($this->productId);
        $cachePathHashParts = array(
            substr($cachePathHash, 0, 3),
            substr($cachePathHash, 3, 3),
            substr($cachePathHash, 6, 3)
        );
        $cachePathArticle = $cachePathBase."/".implode("/", $cachePathHashParts)."/".$this->productId;
        $cachePathArticleAbsolute = $GLOBALS["ab_path"].$cachePathArticle;
        // Create path if requested
        if ($createIfNotExist && !is_dir($cachePathArticleAbsolute)) {
            mkdir($cachePathArticleAbsolute, 0777, true);
        }
        return ($absoluteUrl ? $cachePathArticleAbsolute : $cachePathArticle);
    }

    /**
     * Get the url of the article
     * @param bool          $absoluteUrl
     * @param Template|null $tplTemp
     * @return string
     */
    public function getUrl($absoluteUrl = false, &$tplTemp = null, $disableCache = false, $useSSL = null) {
        $cachePath = $this->getVolatileCachePath();
        $cacheFile = $cachePath."/urls.json";
        $cacheStorage = array();
        $cacheIdent = ($absoluteUrl ? "abs_" : "rel_").$this->langval;
        if (file_exists($cacheFile) && !$disableCache) {
            $cacheStorageLoader = json_decode(file_get_contents($cacheFile), true);
            if (is_array($cacheStorageLoader)) {
                $cacheStorage = $cacheStorageLoader;
                $cacheStorageLoader = null;
            }
        }
        if (!array_key_exists($cacheIdent, $cacheStorage) || $disableCache) {
            if ($tplTemp === null) {
                $tplTemp = new Template("tpl/de/empty.htm");
            }
            $tplTemp->addvar("PRODUKTNAME", $this->getData_Product("PRODUKTNAME"));
            $urlParams = "product_details,".$this->getData_CategoryId().",".$this->getId().                 // Page ident, product and category id
                "|PRODUKTNAME={urllabel(PRODUKTNAME)}";                                                     // Product name
            $cacheStorage[$cacheIdent] = ($absoluteUrl ? $tplTemp->tpl_uri_action_full($urlParams, $useSSL) : $tplTemp->tpl_uri_action($urlParams, false, $useSSL));
            
            file_put_contents($cacheFile, json_encode($cacheStorage));
        }
        return $cacheStorage[$cacheIdent];
    }
    
    /**
     * Get the cache directory of the article
     * @param bool $createIfNotExist
     * @param bool $absoluteUrl
     * @return string
     */
    public function getVolatileCachePath($createIfNotExist = true, $absoluteUrl = true) {        
        $cachePathBase = "cache/marktplatz/product_volatile";
        $cachePathHash = ($this->productId - ($this->productId % 1000));
        $cachePathArticle = $cachePathBase."/".$cachePathHash."/".$this->productId;
        $cachePathArticleAbsolute = $GLOBALS["ab_path"].$cachePathArticle;
        // Create path if requested
        if ($createIfNotExist && !is_dir($cachePathArticleAbsolute)) {
            @mkdir($cachePathArticleAbsolute, 0777, true);
        }
        return ($absoluteUrl ? $cachePathArticleAbsolute : $cachePathArticle);
    }

    /**
     * Get the name of the user
     * @return string
     */
    public function getTitle() {
        return $this->getData_Product("PRODUKTNAME");
    }

    /**
     * Get the articles descripton as html
     * @param array     $removeTags     HTML-Tags to be removed from the description
     * @return string
     */
    public function getDescriptionHtml($removeTags = null, $disableCache = false) {
        $cachePath = $this->getVolatileCachePath();
        $cacheFile = $cachePath."/description.htm";
        if (!file_exists($cacheFile) || $disableCache) {
            // Set default parameter value if not set
            if ($removeTags === null) {
                $removeTags = array("script", "style", "link");
            }
            // Generate html description
            $descriptionHtml = $this->getData_Product("BESCHREIBUNG");
            if (is_array($removeTags) && !empty($removeTags)) {
                // Convert UTF-8 characters to html entities
                $descriptionHtml = mb_convert_encoding($descriptionHtml, 'HTML-ENTITIES', 'UTF-8');
                // Create DOMDocument and load html description
                $descriptionHtmlDom = new DOMDocument();
                @$descriptionHtmlDom->loadHTML("<div>".$descriptionHtml."</div>");
                // Remove DOCTYPE and html-/body-tags 
                $descriptionHtmlDom->removeChild($descriptionHtmlDom->doctype);
                $descriptionHtmlDom->replaceChild($descriptionHtmlDom->firstChild->firstChild->firstChild, $descriptionHtmlDom->firstChild);
                // Remove forbidden tags
                foreach ($removeTags as $tagIndex => $tagName) {
                    $nodeList = $descriptionHtmlDom->getElementsByTagName($tagName);
                    for ($nodeIndex = $nodeList->length; --$nodeIndex >= 0;) {
                        $node = $nodeList->item($nodeIndex);
                        $node->parentNode->removeChild($node);
                    }
                }
                $descriptionHtml = $descriptionHtmlDom->saveHTML();
            }
            if ($disableCache) {
                return $descriptionHtml;
            }
            file_put_contents($cacheFile, $descriptionHtml);
            return $descriptionHtml;
        } else {
            // Return cached html description
            return file_get_contents($cacheFile);
        }
    }

    /**
     * Get a plain text description of the article
     * @return array|null|string
     */
    public function getDescriptionText($disableCache = false, $manualTagRemoval = array()) {
        $cachePath = $this->getVolatileCachePath();
        $cacheFile = $cachePath."/description.txt";
        if (!file_exists($cacheFile) || $disableCache) {
            // Generate text description
            $descriptionHtml = $this->getDescriptionHtml(null, $disableCache);
            foreach ($manualTagRemoval as $tagIndex => $tagName) {
                $descriptionHtml = preg_replace("/<".preg_quote($tagName).">.+</".preg_quote($tagName).">/U", "", $descriptionHtml);
            }
            $descriptionText = strip_tags($descriptionHtml);
            if ($disableCache) {
                return $descriptionText;
            }
            file_put_contents($cacheFile, $descriptionText);
            return $descriptionText;
        } else {
            // Return cached text description
            return file_get_contents($cacheFile);
        }
    }

}