<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once $ab_path.'sys/lib.ads.php';

$limit = $_GET['LIMIT']?(int)$_GET['LIMIT']:1;
$offset = $_GET['OFFSET']?(int)$_GET['OFFSET']:0;

$rows = $db->fetch_table("SELECT ID_AD_MASTER, FK_KAT, STAMP_START FROM ad_master ORDER BY ID_AD_MASTER LIMIT $offset,$limit");

echo "###ANZEIGEN####";
foreach($rows as $key => $row) {
    echo "-- Anzeige ".$row['ID_AD_MASTER'].' ('.$row['FK_KAT'].') '.$row['STAMP_START'].' --<br>';
    $rowDate = strtotime($row['STAMP_START']);

    $oldCachePaths = array(
        $ab_path.'cache/marktplatz/'.date("Y", $rowDate).'/'.date("m", $rowDate)."/".$row['ID_AD_MASTER'],
        $ab_path.'cache/marktplatz/'.date("Y", $rowDate).'/'.date("m", $rowDate)."/".$row['ID_AD_MASTER'].'.'.$row['FK_KAT']
    );

    $newCachePath = AdManagment::getAdCachePath($row['ID_AD_MASTER'], true);

    foreach($oldCachePaths as $pKey => $path) {
        if(is_dir($path)) {
            echo "move ".$path." to ".$newCachePath."<br />";
            system("cp ".$path."/* ".$newCachePath.'/');
        } else {
            echo "not found ".$path."<br>";
        }
    }
    echo "<br >";
}
echo "<br>";

echo "###BILDER####";

$rowsImages = $db->fetch_table("SELECT ID_IMAGE, FK_AD, SRC, SRC_THUMB FROM ad_images ORDER BY FK_AD LIMIT $offset,$limit");
foreach($rowsImages as $key => $rowImage) {
    echo "-- BILD ".$rowImage['ID_IMAGE'].' ('.$rowImage['FK_AD'].') --<br>';


    $srcBase = basename($rowImage['SRC']);
    $srcThumbBase = basename($rowImage['SRC_THUMB']);

    $newCachePath = AdManagment::getAdCachePath($rowImage['FK_AD'], true);
    $newCachePathRelative = "/".str_replace($ab_path, "", $newCachePath);

    echo "base: ".$srcBase."<br>";
    echo "to: ".$newCachePathRelative.'/'.$srcBase."<br>";

    $db->querynow("UPDATE ad_images
        SET
            SRC = '".mysql_real_escape_string($newCachePathRelative.'/'.$srcBase)."',
            SRC_THUMB = '".mysql_real_escape_string($newCachePathRelative.'/'.$srcThumbBase)."'
        WHERE
            ID_IMAGE = '".$rowImage['ID_IMAGE']."'
        ");

}
echo "<br>";

echo "###FILES####";

$rowsFiles = $db->fetch_table("SELECT ID_AD_UPLOAD, FK_AD, SRC FROM ad_upload ORDER BY FK_AD LIMIT $offset,$limit");
foreach($rowsFiles as $key => $rowFile) {
    echo "-- FILE ".$rowFile['ID_AD_UPLOAD'].' ('.$rowFile['FK_AD'].') --<br>';

    $srcBase = basename($rowFile['SRC']);
    $newCachePath = AdManagment::getAdCachePath($rowFile['FK_AD'], true);

    echo "base: ".$srcBase."<br>";
    echo "to: ".$newCachePath.'/'.$srcBase."<br>";

    $db->querynow("UPDATE ad_upload
        SET
            SRC = '".mysql_real_escape_string($newCachePath.'/'.$srcBase)."'
        WHERE
            ID_AD_UPLOAD = '".$rowFile['ID_AD_UPLOAD']."'
        ");



}

