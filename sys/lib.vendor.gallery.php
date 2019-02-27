<?php
/* ###VERSIONSBLOCKINLCUDE### */


/**
 *
 * @changed 2011-12-16 Danny Rosifka, Anbieter Video Verwaltung hinzugefügt
 */

class VendorGalleryManagement {
    private static $db;
    private static $instance = null;

    /**
     * Singleton
     *
     * @param ebiz_db $db
     * @return VendorGalleryManagement
     */
    public static function getInstance(ebiz_db $db) {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        self::setDb($db);

        return self::$instance;
    }

    /**
     * Fügt ein neues Anbieter Bild ein
     *
     * @param $vendorGalleryFilename
     * @param $vendorId
     * @return void
     */
    public function insertFile($vendorGalleryName, $vendorGalleryFilename, $vendorId) {
        global $nar_systemsettings;
        $db = $this->getDb();

        $countGallery = $this->countVendorGalleryByVendorId($vendorId);

        if ($countGallery >= $nar_systemsettings['USER']['VENDOR_GALLERY_MAX_IMAGES']) {
            return false;
        } else {
            return $db->update("vendor_gallery", array('FK_VENDOR' => $vendorId, 'FILENAME' => $vendorGalleryFilename, 'NAME' => $vendorGalleryName));
        }
    }

    /**
     * Fügt ein neues Anbieter Video ein
     *
     * @param $youtubeId
     * @param $vendorId
     * @return void
     */
    public function insertVideo($vendorGalleryName, $youtubeId, $vendorId) {
        global $nar_systemsettings;
        $db = $this->getDb();

        $countGallery = $this->countVendorGalleryByVendorId($vendorId);

        if ($countGallery >= $nar_systemsettings['USER']['VENDOR_GALLERY_MAX_IMAGES']) {
            return false;
        } else {
            return $db->update("vendor_gallery_video", array('FK_VENDOR' => $vendorId, 'YOUTUBEID' => $youtubeId, 'NAME' => $vendorGalleryName));
        }
    }

    /**
     * Löscht ein Anbieter Bild
     *
     * @param int $vendorGalleryId
     * @param int $userId
     * @return void
     */
    public function deleteById($vendorGalleryId, $userId) {
        $db = $this->getDb();

        if ($this->existVendorGalleryByUserId($vendorGalleryId, $userId)) {
            $db->querynow("
                DELETE
                    g
                FROM
                    vendor_gallery g, vendor v
                WHERE
                    v.ID_VENDOR = g.FK_VENDOR
                    AND v.FK_USER = '" . mysql_real_escape_string($userId) . "'
                    AND g.ID_VENDOR_GALLERY = '" . mysql_real_escape_string($vendorGalleryId) . "'
            ");

            return true;
        }
    }
    
    public function deleteWhereIdNotIn($vendorGalleryIds, $vendorId) {
        $db = $this->getDb();

        foreach ($vendorGalleryIds as $imageIndex => $imageId) {
            $vendorGalleryIds[$imageIndex] = (int)$imageId;
        }
        
        $arImages = $db->fetch_table("
            SELECT ID_VENDOR_GALLERY, FILENAME
            FROM `vendor_gallery`
            WHERE FK_VENDOR=".(int)$vendorId.(!empty($vendorGalleryIds) ? " AND ID_VENDOR_GALLERY NOT IN (".implode(", ", $vendorGalleryIds).")" : ""));
        $arImageIds = array();
        foreach ($arImages as $arImage) {
            $arImageIds[] = $arImage["ID_VENDOR_GALLERY"];
            $imageFileAbs = $GLOBALS["ab_path"].'cache/vendor/gallery/'.$arImage["FILENAME"];
            if (file_exists($imageFileAbs)) {
                unlink($imageFileAbs);
            }
        }
        $db->querynow("DELETE FROM `vendor_gallery` WHERE ID_VENDOR_GALLERY IN (".implode(", ", $arImageIds).")");
        return true;
    }

    /**
     * Löscht ein Anbieter Video
     *
     * @param int $vendorGalleryVideoId
     * @param int $userId
     * @return void
     */
    public function deleteVideoById($vendorGalleryVideoId, $userId) {
        $db = $this->getDb();

        if ($this->existVendorGalleryVideoByUserId($vendorGalleryVideoId, $userId)) {
            $db->querynow("
                DELETE
                    g
                FROM
                    vendor_gallery_video g, vendor v
                WHERE
                    v.ID_VENDOR = g.FK_VENDOR
                    AND v.FK_USER = '" . mysql_real_escape_string($userId) . "'
                    AND g.ID_VENDOR_GALLERY_VIDEO = '" . mysql_real_escape_string($vendorGalleryVideoId) . "'
            ");


            return true;
        }
    }
    
    public function deleteVideoWhereIdNotIn($vendorGalleryVideoIds, $vendorId) {
        $db = $this->getDb();

        foreach ($vendorGalleryVideoIds as $videoIndex => $videoId) {
            $vendorGalleryVideoIds[$videoIndex] = (int)$videoId;
        }
        
        $db->querynow("
            DELETE FROM `vendor_gallery_video`
            WHERE FK_VENDOR=".(int)$vendorId.(!empty($vendorGalleryVideoIds) ? " AND ID_VENDOR_GALLERY_VIDEO NOT IN (".implode(", ", $vendorGalleryVideoIds).")" : ""));
        return true;
    }

    /**
     * Holt ein Anbieter Bild anhand ID under User ID
     *
     * @param $vendorGalleryId
     * @param $userId
     * @return array
     */
    public function fetchById($vendorGalleryId, $userId) {
        $db = $this->getDb();

        $result = $db->fetch1("
            SELECT g.* FROM vendor_gallery g
            JOIN vendor v ON v.ID_VENDOR = g.FK_VENDOR
            WHERE
                v.FK_USER = '" . mysql_real_escape_string($userId) . "'
                AND g.ID_VENDOR_GALLERY = '" . mysql_real_escape_string($vendorGalleryId) . "'
        ");

        return $result;
    }

    /**
     * Holt alle Anbieter Bilder eines Benutzers
     *
     * @param $userId
     * @return array
     */
    public function fetchAllByUserId($userId) {
        $db = $this->getDb();

        $result = $db->fetch_table("
            SELECT g.* FROM vendor_gallery g
            JOIN vendor v ON v.ID_VENDOR = g.FK_VENDOR
            WHERE
                FK_USER = '" . mysql_real_escape_string($userId) . "'
        ");

        return $result;
    }

    /**
     * Holt alle Anbieter Videos eines Benutzers
     *
     * @param $userId
     * @return array
     */
    public function fetchAllVideosByUserId($userId) {
        $db = $this->getDb();

        $result = $db->fetch_table("
                SELECT g.* FROM vendor_gallery_video g
                JOIN vendor v ON v.ID_VENDOR = g.FK_VENDOR
                WHERE
                    FK_USER = '" . mysql_real_escape_string($userId) . "'
            ");

        return $result;
    }

    /**
     * Prüft ob ein Anbieter Bild mit der Id $vendorGalleryId existiert, der dem
     * Benutzer mit der Id $userId gehört
     *
     * @param $vendorGalleryId
     * @param $userId
     * @return bool
     */
    private function existVendorGalleryByUserId($vendorGalleryId, $userId) {
        $db = $this->getDb();

        $result = $db->fetch_atom("
            SELECT COUNT(*) FROM vendor_gallery g
            JOIN vendor v ON v.ID_VENDOR = g.FK_VENDOR
            WHERE
                g.ID_VENDOR_GALLERY = '" . mysql_real_escape_string($vendorGalleryId) . "'
                AND v.FK_USER = '" . mysql_real_escape_string($userId) . "'
        ");

        return ($result > 0);
    }

    /**
     * Prüft ob ein Anbieter Video mit der Id $vendorGalleryVideoId existiert, der dem
     * Benutzer mit der Id $userId gehört
     *
     * @param $vendorGalleryId
     * @param $userId
     * @return bool
     */
    private function existVendorGalleryVideoByUserId($vendorGalleryVideoId, $userId) {
        $db = $this->getDb();

        $result = $db->fetch_atom("
            SELECT COUNT(*) FROM vendor_gallery_video g
            JOIN vendor v ON v.ID_VENDOR = g.FK_VENDOR
            WHERE
                g.ID_VENDOR_GALLERY_VIDEO = '" . mysql_real_escape_string($vendorGalleryVideoId) . "'
                AND v.FK_USER = '" . mysql_real_escape_string($userId) . "'
        ");

        return ($result > 0);
    }

    private function countVendorGalleryByVendorId($vendorId) {
        $db = $this->getDb();

        $countImages = $db->fetch_atom("SELECT COUNT(*) FROM vendor_gallery WHERE FK_VENDOR = '" . mysql_real_escape_string($vendorId) . "'");
        $countVideo = $db->fetch_atom("SELECT COUNT(*) FROM vendor_gallery_video WHERE FK_VENDOR = '" . mysql_real_escape_string($vendorId) . "'");

        return ($countImages + $countVideo);
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

    private function __construct() {
    }

    private function __clone() {
    }
}