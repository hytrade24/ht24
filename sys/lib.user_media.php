<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once __DIR__."/lib.ads.php";
require_once __DIR__."/lib.youtube.php";

class UserMediaManagement {
    const MEDIA_MAX_IMAGES = 10;
    const MEDIA_MAX_UPLOADS = 10;
    const MEDIA_MAX_VIDEOS = 10;
    
    const IMAGE_DEFAULT_FORMAT = 12;

    private $db;
    private $table;
    private $userId;
    private $packetId;
    private $packetPaid;
    private $adData;
    private $freeAmount;
    private $tplImages;
    private $tplImagesRow;
    private $tplUploads;
    private $tplUploadsRow;
    private $tplVideos;
    private $tplVideosRow;

    function __construct(ebiz_db $db, $table, $userId = null) {
        $this->db = $db;
        $this->table = $table;
        $this->userId = $userId;
        $this->packetId = null;
        $this->packetPaid = false;
        $this->adData = array();
        $this->freeAmount = array(
            "images"    => $GLOBALS["nar_systemsettings"]["MARKTPLATZ"]["FREE_IMAGES"],
            "videos"    => $GLOBALS["nar_systemsettings"]["MARKTPLATZ"]["FREE_VIDEOS"],
            "uploads"   => $GLOBALS["nar_systemsettings"]["MARKTPLATZ"]["FREE_UPLOADS"]
        );
        $this->tplImages = "my-user-media-images.htm";
        $this->tplImagesRow = "my-user-media-images.row.htm";
        $this->tplUploads = "my-user-media-documents.htm";
        $this->tplUploadsRow = "my-user-media-documents.row.htm";
        $this->tplVideos = "my-user-media-videos.htm";
        $this->tplVideosRow = "my-user-media-videos.row.htm";
        if (is_array($_SESSION['EBIZ_TRADER_USER_MEDIA'])) {
            $this->loadFromArray($_SESSION['EBIZ_TRADER_USER_MEDIA']["adData"]);
            if (array_key_exists("freeAmount", $_SESSION['EBIZ_TRADER_USER_MEDIA'])) {
              $this->freeAmount = $_SESSION['EBIZ_TRADER_USER_MEDIA']["freeAmount"];
            }
        }
    }

    function __destruct() {
        $_SESSION['EBIZ_TRADER_USER_MEDIA'] = array(
            "adData"      => $this->adData,
            "freeAmount"  => $this->freeAmount
        );
    }

    public static function getCachePath($table, $id, $create = false) {
        $path = $GLOBALS['ab_path']."cache/media/".$table."/".substr(md5($id), 0, 8);
        if ($create && !is_dir($path)) {
            mkdir($path, 0777, true);
        }
        return $path;
    }

    public static function getDefaultImage(ebiz_db $db, $table, $id) {
        $arImage = $db->fetch1("SELECT * FROM `media_image` WHERE `TABLE`='".mysql_real_escape_string($table)."' AND `FK`=".(int)$id." AND `IS_DEFAULT`=1");
        if (is_array($arImage)) {
            $arImage = array_merge($arImage, array_flatten(unserialize($arImage["SER_META"]), true, "_", "META_"));
            return $arImage;
        } else {
            return false;
        }
    }
    
    /**
     * Discard any article data that may be remaining from previously creating an article.
     * @return bool true if all article data was discarded from session.
     */
    public function clearUploads() {
        $this->adData = array();
        return true;
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
            "images_free"           => $this->freeAmount["images"],
            "images_available"		=> $images_left,
            "images_limit"          => $images_limit,
            "videos_count"          => $video_count,
            "videos_free"           => $this->freeAmount["videos"],
            "videos_available"		=> $videos_left,
            "videos_limit"          => $videos_limit,
            "downloads_count"       => $video_count,
            "downloads_free"        => $this->freeAmount["uploads"],
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
        if (!is_array($this->adData["images"])) {
            return array();
        }
        $arImages = $this->adData["images"];
        foreach ($arImages as $imageIndex => $arImage) {
            $arImages[$imageIndex] = array_merge($arImages[$imageIndex], array_flatten(unserialize($arImage["SER_META"]), true, "_", "META_"));
        }
        return $arImages;
    }

    /**
     * Returns a list of all documents
     * @return array
     */
    public function getUploads() {
        if (!is_array($this->adData["uploads"])) {
            return array();
        }
        $arUploads = $this->adData["uploads"];
        foreach ($arUploads as $uploadIndex => $arUpload) {
            $arUploads[$uploadIndex] = array_merge($arUploads[$uploadIndex], array_flatten(unserialize($arUpload["SER_META"]), true, "_", "META_"));
            $arUploads[$uploadIndex]['FILENAME_SHORT'] = substr($arUpload["FILENAME"], 0, 32);
            $arUploads[$uploadIndex]['FILESIZE'] = filesize($GLOBALS['ab_path'].$arUpload['SRC']);
        }
        return $arUploads;
    }

    /**
     * Returns a list of all videos
     * @return array
     */
    public function getVideos() {
        if (!is_array($this->adData["videos"])) {
            return array();
        }
        $arVideos = $this->adData["videos"];
        foreach ($arVideos as $videoIndex => $arVideo) {
            $arVideos[$videoIndex] = array_merge($arVideos[$videoIndex], array_flatten(unserialize($arVideo["SER_META"]), true, "_", "META_"));
        }
        return $arVideos;
    }
    
    public function getTplImages() {
        return $this->tplImages;
    }
    
    public function getTplImagesRow() {
        return $this->tplImagesRow;
    }
    
    public function getTplUploads() {
        return $this->tplUploads;
    }
    
    public function getTplUploadsRow() {
        return $this->tplUploadsRow;
    }
    
    public function getTplVideos() {
        return $this->tplVideos;
    }
    
    public function getTplVideosRow() {
        return $this->tplVideosRow;
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
                "images_available"		=> $this->freeAmount["images"],
                "videos_available"		=> $this->freeAmount["videos"],
                "downloads_available"	=> $this->freeAmount["uploads"]
            );
        }
        return $ar_packet_usage;
    }

    /**
     * Whether the currently selected packet is paid (price > 0) or free.
     * @return bool     true if price of the packet is > 0
     */
    public function isPacketPaid() {
        return $this->packetPaid;
    }

    /**
     * @param $arFile               File upload from the $_FILES array e.g.: $_FILES["UPLOAD_IMAGE"]
     * @param array $error          If an error occurs the error reason will be appended to this array
     * @param array $arImage        Will be assigned the array containing all image data if successful
     * @param int   $idBildformat   The image format that will be used for scaling/thumbnail creation. (See "CMS > Medien Datenbank > Bildformate" in admin) 
     * @return bool                 true if the image upload was successful
     */
    public function handleImageUpload($arFile, &$error = array(), &$arImage = array(), $idBildformat = null) {
        if ($idBildformat === null) {
            $idBildformat = self::IMAGE_DEFAULT_FORMAT;
        }
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
            $img_thumb = new image($idBildformat, $tmp_dir, true);
            $img_thumb->check_file(array("tmp_name"=>$tmp_name,"name"=>$name));
            if (empty($img_thumb->err)) {
                $arImage = array(
                    'TABLE'         => $this->table,
                    'FK'            => 0,
                    'CUSTOM'        => 1,
                    'IS_DEFAULT'    => (empty($this->adData['images']) ? true : false),
                    'TMP'           => $img_thumb->img,
                    'TMP_THUMB'     => $img_thumb->thumb,
                    'FILENAME'      => $arFile["name"],
                    'TYPE'          => $arFile["type"],
                    'SER_META'      => serialize(array())
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
            $arUpload['TABLE'] = $this->table;
            $arUpload['FK'] = 0;
            $arUpload['EXT'] = $ext;
            $arUpload['TMP'] = $temp_file;
            $arUpload['FILENAME'] = $filename;
            $arUpload['SER_META'] = serialize(array());
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
    public function handleVideoUpload($youtubeUrl) {
        $arUsage = $this->getMediaUsage();
        if ($arUsage['videos_available'] <= 0) {
            $error[] = Translation::readTranslation('marketplace', 'ad.create.video.limit.reached', null, array(), "Sie haben die maximale Anzahl an Videos erreicht!");
            return false;
        }
        $code = Youtube::ExtractCodeFromURL($youtubeUrl);
        if ($code != false && !in_array($code, array_column($this->adData['videos'], "CODE"))) {
            $video_data = array(
                "TABLE"         => $this->table,
                "FK"            => (int)$this->adData["ID_AD_MASTER"],
                "CODE"	        => $code,
                'SER_META'      => serialize(array())
            );
            $this->adData['videos'][] = $video_data;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Load the article data from the given array.
     * @param $adData   array   Article data as an array
     * @return          bool    true if the article data was successfully loaded
     */
    public function loadFromArray($adData) {
        // Get article data
        $this->adData = $adData;
        return true;
    }

    /**
     * Load the article data from the given array.
     * @param $id       int     Id of the article to be loaded
     * @return          bool    true if the article was successfully loaded
     */
    public function loadFromDatabase($id) {
        // Images
        $this->adData['images'] = $this->db->fetch_table("SELECT * FROM `media_image` WHERE `TABLE`='".mysql_real_escape_string($this->table)."' AND FK=".(int)$id);
        // Documents
        $this->adData['uploads'] = $this->db->fetch_table("SELECT * FROM `media_upload` WHERE `TABLE`='".mysql_real_escape_string($this->table)."' AND FK=".(int)$id);
        // Videos
        $this->adData['videos'] = $this->db->fetch_table("SELECT * FROM `media_video` WHERE `TABLE`='".mysql_real_escape_string($this->table)."' AND FK=".(int)$id);
    }

    /**
     * Set the number of free images
     * @param int   $amount
     */
    public function setFreeImages($amount) {
        $this->freeAmount["images"] = $amount;
    }

    /**
     * Set the number of free videos
     * @param int   $amount
     */
    public function setFreeVideos($amount) {
        $this->freeAmount["videos"] = $amount;
    }

    /**
     * Set the number of free documents
     * @param int   $amount
     */
    public function setFreeUploads($amount) {
        $this->freeAmount["uploads"] = $amount;
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

    public function setTplImages($templateFilename) {
        $this->tplImages = $templateFilename;
    }

    public function setTplImagesRow($templateFilename) {
        $this->tplImagesRow = $templateFilename;
    }

    public function setTplUploads($templateFilename) {
        $this->tplUploads = $templateFilename;
    }

    public function setTplUploadsRow($templateFilename) {
        $this->tplUploadsRow = $templateFilename;
    }

    public function setTplVideos($templateFilename) {
        $this->tplVideos = $templateFilename;
    }

    public function setTplVideosRow($templateFilename) {
        $this->tplVideosRow = $templateFilename;
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

    /**
     * Render the list of article images.
     * @return string       The final rendered image list as HTML code.
     */
    public function renderMediaImages() {
        global $s_lang;
        $arImages = $this->adData["images"];
        foreach ($arImages as $imageIndex => $arImage) {
            $arImages[$imageIndex] = array_merge($arImages[$imageIndex], array_flatten(unserialize($arImage["SER_META"]), true, "_", "META_"));
            if ($arImage['FK'] == 0) {
                $arImages[$imageIndex]['BASE64'] = base64_encode( file_get_contents($arImage['TMP_THUMB']) );
            }
        }
        $tpl_images = new Template("tpl/".$s_lang."/".$this->tplImages);
        $tpl_images->addlist("liste", $arImages, "tpl/".$s_lang."/".$this->tplImagesRow);
        return $tpl_images->process(true);
    }

    /**
     * Render the list of article downloads.
     * @return string       The final rendered download list as HTML code.
     */
    public function renderMediaDownloads() {
        global $s_lang;
        $arUploads = $this->adData["uploads"];
        foreach ($arUploads as $uploadIndex => $arUpload) {
            $arUploads[$uploadIndex] = array_merge($arUploads[$uploadIndex], array_flatten(unserialize($arUpload["SER_META"]), true, "_", "META_"));
            $arUploads[$uploadIndex]['FILENAME_SHORT'] = substr($arUpload["FILENAME"], 0, 32);
        }
        $tpl_files = new Template("tpl/".$s_lang."/".$this->tplUploads);
        $tpl_files->addlist("liste", $arUploads, "tpl/".$s_lang."/".$this->tplUploadsRow);
        return $tpl_files->process(true);
    }

    /**
     * Render the list of article videos.
     * @return string       The final rendered video list as HTML code.
     */
    public function renderMediaVideos() {
        global $s_lang;
        $arVideos = $this->adData["videos"];
        foreach ($arVideos as $videoIndex => $arVideo) {
            $arVideos[$videoIndex] = array_merge($arVideos[$videoIndex], array_flatten(unserialize($arVideo["SER_META"]), true, "_", "META_"));
        }
        $tpl_videos = new Template("tpl/".$s_lang."/".$this->tplVideos);
        $tpl_videos->addlist("liste", $arVideos, "tpl/".$s_lang."/".$this->tplVideosRow);
        return $tpl_videos->process(true);
    }

    public function renderMediaView($template = "default", $s_lang = null) {
        if ($s_lang === null) {
            $s_lang = $GLOBALS['s_lang'];
        }
        $tpl_media = new Template("tpl/".$s_lang."/view_user_media.".$template.".htm");
        $arImages = $this->getImages();
        $arImageDefault = false;
        foreach ($arImages as $imageIndex => $arImage) {
            if ($arImage['IS_DEFAULT']) {
                $arImageDefault = $arImage;
                array_splice($arImages, $imageIndex, 1);
                break;
            }
        }

        if ($arImageDefault !== false) {
            $tpl_media->addvars($arImageDefault, "IMAGE_DEFAULT_");
        }
        $tpl_media->addlist("liste_images", $arImages, "tpl/".$s_lang."/view_user_media.".$template.".row_image.htm");
        $tpl_media->addlist("liste_uploads", $this->getUploads(), "tpl/".$s_lang."/view_user_media.".$template.".row_upload.htm");
        $tpl_media->addlist("liste_videos", $this->getVideos(), "tpl/".$s_lang."/view_user_media.".$template.".row_video.htm");
        return $tpl_media->process(true);
    }

    public function save($id = null, $arMeta = array()) {
        // Get packet for this ad (if there is one)
        require_once $GLOBALS['ab_path']."sys/packet_management.php";
        $packets = PacketManagement::getInstance($this->db);
        $packetOrder = false;
        if ($this->adData["FK_PACKET_ORDER"] > 0) {
            $packetOrder = $packets->order_get($this->adData["FK_PACKET_ORDER"]);
        }

        /**
         * Files (Images/Uploads)
         */
        $uploads_dir = self::getCachePath($this->table, $id, true);
        // Add images
        $arArticleImages = $this->db->fetch_nar("SELECT ID_MEDIA_IMAGE, IS_DEFAULT FROM `media_image` WHERE `TABLE`='".mysql_real_escape_string($this->table)."' AND FK=".$id);
        foreach ($this->adData['images'] as $index => $arImage) {
            if ($arImage['ID_MEDIA_IMAGE'] > 0) {
                $id_image = $arImage['ID_MEDIA_IMAGE'];
                $arImage['SER_META'] = (isset($arMeta["IMAGES"][$index]) ? serialize($arMeta["IMAGES"][$index]) : serialize(array()));
                if (($arImage['IS_DEFAULT'] != $arArticleImages[$id_image]['IS_DEFAULT'])
                    || ($arImage['SER_META'] != $arArticleImages[$id_image]['SER_META'])) {
                    $this->db->querynow("UPDATE `media_image` SET
                        IS_DEFAULT=".(int)$arImage['IS_DEFAULT'].", SER_META='".mysql_real_escape_string($arImage['SER_META'])."'
                        WHERE ID_MEDIA_IMAGE=".(int)$id_image);
                }
                unset($arArticleImages[$id_image]);
            } else {
                $src = $uploads_dir."/".basename($arImage['TMP']);
                $src_thumb = $uploads_dir."/".basename($arImage['TMP_THUMB']);
                if (rename($arImage['TMP'], $src)
                    && rename($arImage['TMP_THUMB'], $src_thumb)) {
                    $arImage['TABLE'] = $this->table;
                    $arImage['FK'] = $id;
                    $arImage['SRC'] = "/".str_replace($GLOBALS['ab_path'], "", $src);
                    $arImage['SRC_THUMB'] = "/".str_replace($GLOBALS['ab_path'], "", $src_thumb);
                    $arImage['SER_META'] = (isset($arMeta["IMAGES"][$index]) ? serialize($arMeta["IMAGES"][$index]) : serialize(array()));
                    $id_image = $this->db->update("media_image", $arImage, true);
                    if (!$id_image) {
                        // Failed to insert image
                        return false;
                    }
                }
            }
        }
        // Remove deleted images
        foreach ($arArticleImages as $id_image => $imageIsDefault) {
            if ($packetOrder !== false) {
                $packetOrder->itemRemContent('image', $id_image);
            }
            $this->db->querynow("DELETE FROM `media_image` WHERE ID_MEDIA_IMAGE=".$id_image);
        }

        // Add uploads
        $arArticleUploads = $this->db->fetch_nar("SELECT ID_MEDIA_UPLOAD, EXT FROM `media_upload` WHERE `TABLE`='".mysql_real_escape_string($this->table)."' AND FK=".$id);
        foreach ($this->adData['uploads'] as $index => $arUpload) {
            if ($arUpload['ID_MEDIA_UPLOAD'] > 0) {
                $id_upload = $arUpload['ID_MEDIA_UPLOAD'];
                $arUpload['SER_META'] = (isset($arMeta["UPLOADS"][$index]) ? serialize($arMeta["UPLOADS"][$index]) : serialize(array()));
                if ($arUpload['SER_META'] != $arArticleUploads[$id_image]['SER_META']) {
                    $this->db->querynow("UPDATE `media_upload` SET
                        SER_META='".mysql_real_escape_string($arUpload['SER_META'])."'
                        WHERE ID_MEDIA_UPLOAD=".(int)$id_upload);
                }
                unset($arArticleUploads[$id_upload]);
            } else {
                $src = $uploads_dir.'/'.$arUpload['FILENAME'].'_x_'.time().'_x_.'.$arUpload['EXT'];
                if (rename($arUpload['TMP'], $src)) {
                    $arUpload['TABLE'] = $this->table;
                    $arUpload['FK'] = $id;
                    $arUpload['SRC'] = "/".str_replace($GLOBALS['ab_path'], "", $src);
                    $arUpload['SER_META'] = (isset($arMeta["UPLOADS"][$index]) ? serialize($arMeta["UPLOADS"][$index]) : serialize(array()));
                    $id_upload = $this->db->update("media_upload", $arUpload, true);
                    if (!$id_upload) {
                        // Failed to insert image
                        return false;
                    }
                }
            }
        }
        // Remove deleted uploads
        foreach ($arArticleUploads as $id_upload => $uploadExt) {
            if ($packetOrder !== false) {
                $packetOrder->itemRemContent('download', $id_upload);
            }
            $this->db->querynow("DELETE FROM `media_upload` WHERE ID_MEDIA_UPLOAD=".$id_upload);
        }

        /**
         * Videos
         */
        $arArticleVideos = $this->db->fetch_nar("SELECT ID_MEDIA_VIDEO, CODE FROM `media_video` WHERE `TABLE`='".mysql_real_escape_string($this->table)."' AND FK=".$id);
        foreach ($this->adData['videos'] as $index => $arVideo) {
            if ($arVideo['ID_MEDIA_VIDEO'] > 0) {
                $id_video = $arVideo['ID_MEDIA_VIDEO'];
                $arVideo['SER_META'] = (isset($arMeta["VIDEOS"][$index]) ? serialize($arMeta["VIDEOS"][$index]) : serialize(array()));
                if ($arVideo['SER_META'] != $arArticleVideos[$id_image]['SER_META']) {
                    $this->db->querynow("UPDATE `media_video` SET
                        SER_META='".mysql_real_escape_string($arVideo['SER_META'])."'
                        WHERE ID_MEDIA_VIDEO=".(int)$id_video);
                }
                unset($arArticleVideos[$id_video]);
            } else {
                $arVideo['TABLE'] = $this->table;
                $arVideo['FK'] = $id;
                $arVideo['SER_META'] = (isset($arMeta["VIDEOS"][$index]) ? serialize($arMeta["VIDEOS"][$index]) : serialize(array()));
                $id_video = $this->db->update("media_video", $arVideo, true);
                if (!$id_video) {
                    // Failed to insert video
                    return false;
                }
            }
        }
        // Remove deleted uploads
        foreach ($arArticleVideos as $id_video => $videoCode) {
            if ($packetOrder !== false) {
                $packetOrder->itemRemContent('video', $id_video);
            }
            $this->db->querynow("DELETE FROM `media_video` WHERE ID_MEDIA_VIDEO=".$id_video);
        }
    }

}