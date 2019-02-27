<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 10.09.14
 * Time: 11:56
 */

class Api_UrlManagement {

    private static $instance = array();

    /**
     * @param ebiz_db   $db
     * @param int|null  $langval
     * @return Api_UrlManagement
     */
    public static function getInstance(ebiz_db $db, $langval = null) {
        if ($langval === null) {
            $langval = $GLOBALS['langval'];
        }
        if (!array_key_exists($langval, self::$instance)) {
            self::$instance[$langval] = new Api_UrlManagement($db, $langval);
        }
        return self::$instance[$langval];
    }

    private $db;
    private $langval;

    function __construct(ebiz_db $db, $langval) {
        $this->db = $db;
        $this->langval = (int)$langval;
    }

    public function baseUrl($urlPath, $absolute = false, $isSSL = null, $useSSL = 1) {
        // TODO: Apply language! (And restore after URL generation...)
        $urlObject = Api_Entities_URL::createByBasePath($urlPath, $isSSL, $useSSL);
        return $urlObject->getRaw($absolute);
    }

    public function pageUrl($urlParams, $urlParamsOptional = array(), $absolute = false, $isSSL = null, $useSSL = 1) {
        $urlObject = Api_Entities_URL::createByPagePath($urlParams, $urlParamsOptional, $absolute, $isSSL, $useSSL);
        return ($urlObject !== null ? $urlObject->getRaw($absolute) : null);
    }
    
} 