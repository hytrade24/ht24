<?php

require_once "inc.legacy.php";
require_once "inc.server.php";

$cacheFilename = $_REQUEST["file"];
$cacheFilenameAbs = __DIR__."/cache/marktplatz/anzeigen/".$cacheFilename;

if (preg_match("/^[0-9a-f]{3}\/[0-9a-f]{3}\/[0-9a-f]{3}\/([0-9]+)\/.+/", $cacheFilename, $arCacheMatch) && file_exists($cacheFilenameAbs)) {
    $cacheArticleId = (int)$arCacheMatch[1];
    $mysql = mysql_connect($db_host, $db_user, $db_pass);
    mysql_select_db($db_name, $mysql);
    $mysqlResult = mysql_query("SELECT STATUS, DELETED FROM `ad_master` WHERE ID_AD_MASTER=".$cacheArticleId);
    if ($mysqlResult) {
        $articleStatus = mysql_fetch_assoc($mysqlResult);
        if (is_array($articleStatus)) {
            if ($articleStatus["DELETED"] == 0) {
                header("Content-Type: ".mime_content_type($cacheFilenameAbs));
                readfile($cacheFilenameAbs);
                die();
            }
        }
    }
}
die(header("HTTP/1.0 404 Not Found"));