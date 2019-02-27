<?php
/* ###VERSIONSBLOCKINLCUDE### */

$id = (int)$_REQUEST['ID_AD_UPLOAD'];
if (isset($_REQUEST['INDEX'])) {
    $index = (int)$_REQUEST['INDEX'];
    $arUpload = $_SESSION['EBIZ_TRADER_AD_CREATE']["adData"]["uploads"][$index];
    if (is_array($arUpload)) {
        $id = (int)$arUpload['ID_AD_UPLOAD'];
        if ($id > 0) {
            header( 'Content-type: application/octet-stream' );
            header( 'Content-Length: ' . filesize( $ar['SRC'] ) );
            header( 'Content-Disposition: attachment; filename="'.$arUpload['FILENAME'].'.'.$arUpload['EXT'].'"' );
            echo file_get_contents($arUpload['SRC']);
            die();
        } else {
            header( 'Content-type: application/octet-stream' );
            header( 'Content-Length: ' . filesize( $ar['SRC'] ) );
            header( 'Content-Disposition: attachment; filename="'.$arUpload['FILENAME'].'.'.$arUpload['EXT'].'"' );
            echo file_get_contents($arUpload['TMP']);
            die();
        }
    }
} else {
    $ar = $db->fetch1("
        SELECT
            *
        FROM
            ad_upload
        WHERE
            ID_AD_UPLOAD=".$id
    );
    $allow = false;
    if ( $ar["IS_PAID"] == "0" ) {
	    $allow = true;
    }
    else if ( $ar["IS_PAID"] == "1" ) {
    	$query = "SELECT *
    	            FROM ad_upload a
    	            INNER JOIN ad_sold b
    	            ON a.ID_AD_UPLOAD = ".$id."
    	            AND b.FK_AD = a.FK_AD
    	            AND b.FK_USER = ".$uid."
    	            ORDER BY b.STAMP_PAYED DESC
    	            LIMIT 1";
    	$result = $db->fetch_table( $query );
    	// nach PAID_DOWNLOAD_REFUNDTIME schon abgelaufen Auto-Bestätigung
    	if ( !empty($result) && ( !empty($result[0]["RENOUNCE_REFUND_RIGHT"]) || ( (time() - strtotime($result[0]["STAMP_BOUGHT"])) > ($nar_systemsettings["MARKTPLATZ"]["PAID_DOWNLOAD_REFUNDTIME"]*86400)) ) ) {
    		$allow = true;
	    }
    }
    if ( $allow ) {
	    if(!empty($ar)) {
		    header( 'Content-type: application/octet-stream' );
		    header( 'Content-Length: ' . filesize( $ar['SRC'] ) );
		    header( 'Content-Disposition: attachment; filename="'.$ar['FILENAME'].'.'.$ar['EXT'].'"' );
		    echo file_get_contents($ar['SRC']);
		    die();
	    }
    }
}

die(forward("/404/"));


?>