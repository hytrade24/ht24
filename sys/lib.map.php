<?php

/**
* asdjkasdkasj
*/
class GoogleMaps
{
    /**
     * Cache Pfad
     * @var string
     */
    private $cache_path = "cache/map";

    /**
     * Default Einstellungen für die Map
     * @var array
     */
    private $options = array(
        'zoom' => 5,
        'center' => 'new google.maps.LatLng(51.165691, 10.451526)',
        'mapTypeId' => 'google.maps.MapTypeId.ROADMAP'
    );

    /**
     * Liste der MarkerArrays zu includieren
     * @var array
     */
    private $markerIncludeList = array();

    /**
     * Design Pattern
     * @var [type]
     */
    private static $instance = null;

    /**
     * HTML Code für die Map
     * @var string
     */
    private $map = "";

    /**
     * Liste der MarkerArray's die in die Map eingefügt werden sollen
     * @var array
     */
    private $markers = array();

    /**
     * Zeit in Sekunden bevor es neu cachen soll.
     * @var integer
     */
    private $expire = 1800;

    /**
     * Map für die Typen
     * @var array
     */
    private $type = array(
        'MARKTPLATZ' => '/marktplatz',
        'VENDOR' => '/vendor',
        'EVENT' => '/events'
    );

    /**
     * Constructor
     */
    public function __construct()
    {

    }

    /**
     * get Instance
     * @return \GoogleMaps|null [type] [description]
     */
    public function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Liefert die Marker Daten als json, anhand einer HASH ID (generiert in einer suche)
     * @param  string $type Kategorie oder Hash
     * @param string $ident Name des files
     * @return json
     */
    private function getData($type, $ident) {
        // Try to update the map cache
        $db = $GLOBALS['db'];
        switch ($type) {
            case 'MARKTPLATZ': 
                if (!$this->cacheFileExists('marktplatz', $ident) || $this->isExpired('marktplatz', $ident)) {
                    include_once "sys/lib.shop_kategorien.php";
                    $kat = new TreeCategories("kat", 1);
                    $katId = $kat->tree_get_parent();
                    $mapQueryJoin = array();
                    $mapQueryWhere = array();
                    $mapUpdateCache = false;
                    if (preg_match("/^k([0-9]+)$/i", $ident, $arMatches)) {
                        $katId = (int)$arMatches[1];
                        $mapUpdateCache = true;
                    }
                    if ($mapUpdateCache) {
                        // Get category details
                        $katRow = $kat->element_read($katId);
                        $katIds = array_keys($db->fetch_nar("
                            SELECT ID_KAT
                              FROM `kat`
                            WHERE
                              (LFT >= ".$katRow["LFT"].") AND
                              (RGT <= ".$katRow["RGT"].") AND
                              (ROOT = ".$katRow["ROOT"].")"));
                        // Generate query
                        $search_query = "
                            SELECT group_concat(r.json) as json from (
                                SELECT
                                    concat('{',
                                        'ID:', adt.ID_AD_MASTER,
        
                                        ',LONGITUDE:', adt.LONGITUDE,
                                        ',LATITUDE:', adt.LATITUDE,
                                    '}') as json
                                FROM `".$katRow["KAT_TABLE"]."` a
                                JOIN ad_master adt ON a.ID_".strtoupper($katRow["KAT_TABLE"])." = adt.ID_AD_MASTER
                                LEFT JOIN `manufacturers` m ON m.ID_MAN=a.FK_MAN
                                ".implode(" ", $mapQueryJoin)."
                                WHERE (adt.STATUS&3)=1 AND (adt.DELETED=0) AND a.FK_KAT IN (".implode(", ", $katIds).")".
                                ($mapQueryWhere ? " AND ".$mapQueryWhere : "")."
                                GROUP BY adt.ID_AD_MASTER
                            ) as r";
                        $db->querynow('set session group_concat_max_len=4294967295');
                        $data = $db->fetch_atom($search_query);
                        #var_dump($katRow, $data);
                        #die("debug: ".$type.", ".$ident." / ".$katId);
                        $this->generateCacheFile('marktplatz', $ident, "[".$data."]", false);
                    }
                }
                break;
            case 'VENDOR':
                if (!$this->cacheFileExists('vendor', $ident) || $this->isExpired('vendor', $ident)) {
                    include_once "sys/lib.shop_kategorien.php";
                    $kat = new TreeCategories("kat", 4);
                    $katId = $kat->tree_get_parent();
                    $mapQueryJoin = array();
                    $mapQueryWhere = array();
                    $mapUpdateCache = false;
                    if (preg_match("/^k([0-9]+)$/i", $ident, $arMatches)) {
                        $katId = (int)$arMatches[1];
                        $mapUpdateCache = true;
                    }
                    if ($mapUpdateCache) {
                        // Get category details
                        $katRow = $kat->element_read($katId);
                        $katIds = array_keys($db->fetch_nar("
                            SELECT ID_KAT
                              FROM `kat`
                            WHERE
                              (LFT >= ".$katRow["LFT"].") AND
                              (RGT <= ".$katRow["RGT"].") AND
                              (ROOT = ".$katRow["ROOT"].")"));
                        // Generate query
                        $search_query = "
                            SELECT group_concat(r.json) as json from (
                                SELECT
                                    concat('{',
                                        'ID:', v.ID_VENDOR,
        
                                        ',LONGITUDE:', v.LONGITUDE,
                                        ',LATITUDE:', v.LATITUDE,
                                    '}') as json
                                FROM `vendor` v
                                ".implode(" ", $mapQueryJoin)."
                                WHERE v.STATUS=1 AND v.MODERATED=1 AND a.FK_KAT IN (".implode(", ", $katIds).")".
                                ($mapQueryWhere ? " AND ".$mapQueryWhere : "")."
                                GROUP BY v.ID_VENDOR
                            ) as r";
                        $db->querynow('set session group_concat_max_len=4294967295');
                        $data = $db->fetch_atom($search_query);
                        #var_dump($katRow, $data);
                        #die("debug: ".$type.", ".$ident." / ".$katId);
                        $this->generateCacheFile('vendor', $ident, "[".$data."]", false);
                    }
                }
                break;
        }
        return '<script src="/' . $this->cache_path . $this->type[$type] . '/' . $ident . '.jsongz"></script>';
    }

    /**
     * Gibt die Liste der Marker zurück
     * @return array
     */
    public function getMarkers()
    {
        return $this->markers;
    }

    /**
     * [getMarkerIncludeList description]
     * @return string [type] [description]
     */
    public function getMarkerIncludeList()
    {
        return implode("\n", $this->markerIncludeList);
    }

    /**
     * Gibt die generiert Map als HTML zurück
     * @return string
     */
    public function getMap()
    {
        return $this->map;
    }

    /**
     * Generiert die Map mit den Einstellungen
     */
    public function generateMap()
    {
        $options = "";

        foreach ($this->options as $key => $value) {
            $options .= $key . ': ' . $value . ',';
        }

        $this->map = "map = new google.maps.Map(document.getElementById('map-canvas'), { " . $options . " });";
    }

    /**
     * Fügt eine MarkerArray anhand des typ's und ident's in die include Liste
     * @param string $type
     * @param string $ident
     */
    public function addMarkerList($type, $ident)
    {
        // switch (strtoupper($type)) {
        //     case 'HASH':
        //         $this->markerIncludeList['h' . $ident] = $this->getData(strtoupper($type), $ident);
        //         $this->markers[] = array(
        //             'MARKER_NAME' => 'h' . $ident
        //         );
        //         break;

        //     case 'KATEGORIE':
        //         $this->markerIncludeList['k' . $ident] = $this->getData(strtoupper($type), $ident);
        //         $this->markers[] = array(
        //             'MARKER_NAME' => 'k' . $ident
        //         );
        //         break;

        //     default:
                $this->markerIncludeList[$ident] = $this->getData(strtoupper($type), $ident);
                $this->markers[] = array(
                    'MARKER_NAME' => $ident
                );
        //         break;
        // }
    }

    /**
     * Setzt die Optionen für die Map fest
     * @todo : eine Liste der Optionen
     * @param array $options
     * @return bool
     */
    public function setMapOptions($options)
    {
        $this->options = array_merge($this->options, $options);
        return true;
    }

    /**
     * Compress with gzip
     * @param string $data String zum komprimieren
     * @return string
     */
    public function compress($data)
    {
        return gzencode($data);
    }

    /**
     * Überprüft ob die map daten vorhanden sind
     * @param  string $type  Kategorie oder Hash
     * @param  string $ident Name des Files
     * @return bool
     */
    public function cacheFileExists($type, $ident)
    {
        return file_exists($this->getCachePathForType($type) . "/" . $ident . ".jsongz");
    }

    /**
     * Überprüft, ob der cache überfällig ist.
     * @param  string  $type  Kategorie oder Hash
     * @param  string  $ident Name des Files
     * @return boolean
     */
    public function isExpired($type, $ident)
    {
        $file = $this->getCachePathForType($type) . "/" . $ident . ".jsongz";

        if ((filemtime($file) + $this->expire) < time()) {
            return true;
        }

        return false;
    }

    /**
     * Setzt die Zeit fest, wann nochmal gecached werden soll.
     * @param int $expire Zeit in Sekunden
     */
    public function setExpireTime($expire)
    {
        $this->expire = (int)$expire;
    }

    /**
     * [generateCacheFile description]
     * @param $type
     * @param $ident
     * @param $data
     * @param  boolean $encode   [description]
     * @param  boolean $compress [description]
     * @internal param $ [type]  $type     [description]
     * @internal param $ [type]  $ident    [description]
     * @internal param $ [type]  $data     [description]
     * @return bool [type]            [description]
     */
    public function generateCacheFile($type, $ident, $data, $encode = true, $compress = true)
    {
        $data = $ident . '=' .($encode ? json_encode($data) : $data);

        // switch (strtoupper($type)) {
        //     case 'HASH':
        //         $data = 'h' . $data;
        //         break;

        //     case 'KATEGORIE':
        //         $data = 'k' . $data;
        //         break;

        //     default:
        //         break;
        // }

        $data = ($compress ? $this->compress($data) : $data);

        if (file_put_contents($this->getCachePathForType($type) . '/' . $ident . '.jsongz', $data)) {
            return true;
        }

        return false;
    }

    /**
     * Gibt den Pfad zu den Cache Ordnern der jeweiligen typen
     * @param  string $type
     * @return string       Der Pfad zum Cache
     */
    public function getCachePathForType($type)
    {
        global $ab_path;

        $path = $ab_path . $this->cache_path;

        if ($type !== null) {
            return $path . $this->type[strtoupper($type)];
        }

        return $path;
    }
}
