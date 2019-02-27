<?php

class UserUploadManagement {
    private static $instance = null;

    public static function getInstance(ebiz_db $db) {
        if (self::$instance === null) {
            self::$instance = new self($db);
        }
        return self::$instance;
    }

    private $db;
    private $errors;
    private $uploadLast;

    public function __construct(ebiz_db $db) {
        $this->db = $db;
        $this->errors = array();
        $this->uploadLast = false;
    }

    public function deleteUpload($filename, $userId = null, $userCache = null) {
        $directory_target = $this->getUploadDir($userId, $userCache);
        $directory = new DirectoryIterator($directory_target);
        foreach ($directory as $fileDetails) {
            if ($fileDetails->getFilename() == $filename) {
                unlink($GLOBALS['ab_path']."/".$directory_target."/".$fileDetails->getFilename());
                return true;
            }
        }
        return false;
    }

    public function getUploads($userId = null, $userCache = null) {
        $arList = array();
        $directory_target = $this->getUploadDir($userId, $userCache);
        if (!is_dir($directory_target)) {
            return $arList;
        }
        $directory = new DirectoryIterator($directory_target);
        foreach ($directory as $fileDetails) {
            if (!$fileDetails->isDot()) {
                $filename_target = $directory_target."/".$fileDetails->getFilename();
                $filename_full = $GLOBALS['ab_path']."/".$filename_target;
                $arList[] = array(
                    "FILE_FULL" => $filename_full,
                    "FILE_REL"  => $filename_target,
                    "FILE_NAME" => $fileDetails->getFilename(),
                    "FILE_PATH" => $directory_target,
                    "FILE_TYPE" => exif_imagetype($filename_full)
                );
            }
        }
        return $arList;
    }

    public function getUploadDir($userId = null, $userCache = null) {
        if ($userId === null) {
            $userId = $GLOBALS['uid'];
            $userCache = $GLOBALS['user']["CACHE"];
        } else if ($userCache === null) {
            $userCache = $this->db->fetch_atom("SELECT CACHE FROM `user` WHERE ID_USER=".$userId);
        }
        return "cache/users/".$userCache."/".$userId."/uploads";

    }

    public function getLastUpload() {
        return $this->uploadLast;
    }

    public function uploadFile($arFile, &$ajaxResponse = null) {
        // Set failure as default response
        $ajaxResponse = array(
            "success"   => false,
            "files"     => array()
        );
        // Get target filename
        $filename_base = pathinfo($arFile["name"], PATHINFO_FILENAME);
        $filename_ext = pathinfo($arFile["name"], PATHINFO_EXTENSION);
        $filename_safe = preg_replace("/[^0-9a-z-_]/i", "_", $filename_base).".".$filename_ext;
        // Get target directory
        $directory_target = $this->getUploadDir();
        if (!is_dir($GLOBALS['ab_path']."/".$directory_target)) {
            // Create target directory
            mkdir($GLOBALS['ab_path']."/".$directory_target, 0777, true);
        }
        // Get target filename
        $filename_target = $directory_target."/".$filename_safe;
        $filename_full = $GLOBALS['ab_path']."/".$filename_target;
        if (file_exists($filename_full)) {
            $errorMessage = Translation::readTranslation('marketplace', 'editor.image.upload.error.duplicate', null, array("FILE_NAME" => "'".$filename_safe."'"),
                "Eine Datei mit dem Namen <b>{FILE_NAME}</b> existiert bereits!");
            $this->errors[] = $errorMessage;
            $this->uploadLast = false;
            $ajaxResponse['files'][] = array('ERRORS' => $this->errors);
            return false;
        } else if (move_uploaded_file($arFile["tmp_name"], $filename_full)) {
            $this->uploadLast = array(
                "FILE_FULL" => $filename_full,
                "FILE_REL"  => $filename_target,
                "FILE_NAME" => $filename_safe,
                "FILE_PATH" => $directory_target,
                "FILE_TYPE" => exif_imagetype($filename_full),
                "FILE_THMB" => $GLOBALS['tpl_main']->tpl_thumbnail('"'.$filename_target.'",96,96,crop')
            );
            $ajaxResponse["success"] = true;
            $ajaxResponse["files"][] = $this->uploadLast;
            return true;
        } else {
            $errorMessage = Translation::readTranslation('marketplace', 'editor.image.upload.error', null, array(), "Unbekannter Fehler beim Upload!");
            $this->errors[] = $errorMessage;
            $this->uploadLast = false;
            $ajaxResponse['files'][] = array('ERRORS' => $this->errors);
            return false;
        }
    }
}

?>