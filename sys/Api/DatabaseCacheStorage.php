<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 07.10.16
 * Time: 11:43
 */

class Api_DatabaseCacheStorage {

    const INTERVAL_HOUR = 3600;
    const INTERVAL_DAY = 86400;

    const INTERVAL_CACHE_DEFAULT = 3600;

    private static $defaultCacheLifetime = 86400;   // 24 hours as default lifetime for caches
    
    private static $instance = null;
    
    public static function getInstance(ebiz_db $db = null) {
        if (self::$instance === null) {
            // Create new instance
            if ($db === null) {
                $db = $GLOBALS["db"];
            }
            self::$instance = new Api_DatabaseCacheStorage($db);
        }
        return self::$instance;
    }
    
    private $db;
    
    public function __construct(ebiz_db $db = null) {
        $this->db = $db;
    }
    
    protected function generateJoinForRelation($relationType, $relationValue, &$joinIndex = 0, $joinIdentPrefix = "sr") {
        $joinIdent = $joinIdentPrefix.(int)$joinIndex++;
        switch ($relationType) {
            default:
                // Regular join by relation
                return "
                    JOIN `cache_storage_relation` ".$joinIdent." 
                        ON s.ID_CACHE_STORAGE=".$joinIdent.".FK_CACHE_STORAGE
                            AND ".$joinIdent.".RELATION_TYPE='".mysql_real_escape_string($relationType)."' 
                            AND ".$joinIdent.".FK_RELATION='".mysql_real_escape_string($relationValue)."'";
            case "FK_KAT":
                // TODO: Special case for relations to categories
                $arKat = $this->db->fetch1("SELECT ID_KAT, LFT, RGT, ROOT FROM `kat` WHERE ID_KAT=".(int)$relationValue);
                return "
                    JOIN `cache_storage_relation` ".$joinIdent." 
                        ON s.ID_CACHE_STORAGE=".$joinIdent.".FK_CACHE_STORAGE
                            AND ".$joinIdent.".RELATION_TYPE='".mysql_real_escape_string($relationType)."' 
                            AND ".$joinIdent.".FK_RELATION IN
                            (SELECT ID_KAT";
                break;
        }
    }
    
    protected function generateJoinsForRelations($arRelations, $joinIdentPrefix = "sr", &$joinIndex = 0) {
        $arJoins = array();
        foreach ($arRelations as $relationType => $relationValues) {
            if (!is_array($relationValues)) {
                // Single value for this relation type
                $arJoins[] = $this->generateJoinForRelation($relationType, $relationValues, $joinIndex, $joinIdentPrefix);
            } else {
                // Multiple values for this relation type
                foreach ($relationValues as $relationValueIndex => $relationValue) {
                    $arJoins[] = $this->generateJoinForRelation($relationType, $relationValue, $joinIndex, $joinIdentPrefix);
                }

            }
        }
        return $arJoins;
    }
    
    public function addContent($hash, $content, $validUntil = null, $arRelations = array()) {
        if ($validUntil === null) {
            // Use default lifetime
            $validUntil = date("Y-m-d H:i:s", time() + self::$defaultCacheLifetime);
        } else if (preg_match("/^[0-9]+$/", $validUntil)) {
            // Use supplied unix timestamp as expiration date
            $validUntil = date("Y-m-d H:i:s", (int)$validUntil);
        }
        $query = "
            INSERT INTO `cache_storage`
              (IDENT_HASH, STAMP_VALID_UNTIL, CONTENT)
            VALUES
              ('".mysql_real_escape_string($hash)."', '".mysql_real_escape_string($validUntil)."', '".mysql_real_escape_string($content)."')
            ON DUPLICATE KEY UPDATE
              ID_CACHE_STORAGE=LAST_INSERT_ID(ID_CACHE_STORAGE), STAMP_VALID_UNTIL=VALUES(STAMP_VALID_UNTIL), CONTENT=VALUES(CONTENT)";
        $queryResult = $this->db->querynow($query);
        if (!$queryResult["rsrc"]) {
            // Failed to insert content
            return false;
        }
        $idCacheStorage = (int)$queryResult["int_result"];
        if ($idCacheStorage == 0) {
            // Failed to insert content
            return false;
        }
        // Insert successful! Add relations
        if (!empty($arRelations)) {
            $arRelationValues = array();
            foreach ($arRelations as $relationType => $relationValues) {
                if (!is_array($relationValues)) {
                    // Single value for this relation type
                    $arRelationValues[] = "(".(int)$idCacheStorage.", '".mysql_real_escape_string($relationType)."', '".mysql_real_escape_string($relationValues)."')";
                } else {
                    // Multiple values for this relation type
                    foreach ($relationValues as $relationValueIndex => $relationValue) {
                        $arRelationValues[] = "(".(int)$idCacheStorage.", '".mysql_real_escape_string($relationType)."', '".mysql_real_escape_string($relationValue)."')";
                    }
                }
            }
            if (!empty($arRelationValues)) {
                $relationInsertResult = $this->db->querynow("
                    INSERT INTO `cache_storage_relation`
                      (FK_CACHE_STORAGE, RELATION_TYPE, FK_RELATION)
                    VALUES
                      ".implode(",\n", $arRelationValues));
                return $relationInsertResult["rsrc"];
            }
        }
        return true;
    }
    
    public function checkContentValidByHash($hash) {
        $stampValid = $this->db->fetch_atom("SELECT STAMP_VALID_UNTIL FROM `cache_storage` WHERE IDENT_HASH='".mysql_real_escape_string($hash)."'");
        if ($stampValid === null) {
            return false;
        }
        $stampValidInt = strtotime($stampValid);
        if ($stampValidInt > time()) {
            return true;
        } else {
            return false;
        }
    }
    
    public function cleanup() {
        $arInvalidCache = $this->db->fetch_col("SELECT ID_CACHE_STORAGE FROM `cache_storage` WHERE STAMP_VALID_UNTIL<NOW()");
        foreach ($arInvalidCache as $index => $id) {
            $this->deleteContentById($id);
        }
    }
    
    public function deleteContentById($id) {
        $this->db->querynow("DELETE FROM `cache_storage` WHERE ID_CACHE_STORAGE=".(int)$id);
        $this->db->querynow("DELETE FROM `cache_storage_relation` WHERE FK_CACHE_STORAGE=".(int)$id);
        return true;
    }
    
    public function deleteContentByHash($hash) {
        $id = $this->db->fetch_atom("SELECT ID_CACHE_STORAGE FROM `cache_storage` WHERE HASH='".mysql_real_escape_string($hash)."'");
        if ($id > 0) {
            return $this->deleteContentById($id);
        } else {
            return false;
        }
    }
    
    public function deleteContentByRelations($arRelations) {
        // Join required relations
        $arJoins = $this->generateJoinsForRelations($arRelations);
        // Return result
        $arContents = $this->db->fetch_table("
            SELECT s.ID_CACHE_STORAGE FROM `cache_storage` s
            ".implode("\n", $arJoins));
        foreach ($arContents as $index => $arContent) {
            if (!$this->deleteContentById($arContent["ID_CACHE_STORAGE"])) {
                return false;
            }
        }
        return true;
    }
    
    public function getContentById($id) {
        return $this->db->fetch_atom("
            SELECT CONTENT FROM `cache_storage` WHERE ID_CACHE_STORAGE=".(int)$id);
    }
    
    public function getContentByHash($hash) {
        return $this->db->fetch_atom("
            SELECT CONTENT FROM `cache_storage` WHERE IDENT_HASH='".mysql_real_escape_string($hash)."'");
    }
    
    public function getContentsByRelations($arRelations) {
        // Join required relations
        $arJoins = $this->generateJoinsForRelations($arRelations);
        // Return result
        return $this->db->fetch_table("
            SELECT s.* FROM `cache_storage` s
            ".implode("\n", $arJoins));
    }
    
}