<?php

class Tools_Utility {


    public static function configSizeToBytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        switch($last)
        {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        return $val;
    }

    public static function getUploadMaxFilsize() {
        //select maximum upload size
        $max_upload = self::configSizeToBytes(ini_get('upload_max_filesize'));
        //select post limit
        $max_post = self::configSizeToBytes(ini_get('post_max_size'));
        // return the smallest of them, this defines the real limit
        return min($max_upload, $max_post);
    }

}