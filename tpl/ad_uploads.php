<?php
/* ###VERSIONSBLOCKINLCUDE### */

header('Content-Type: text/html; charset=iso-8859-1');
$ar = $db->fetch1("
	SELECT
		ID_AD_MASTER AS FK_AD,
		FK_PACKET_ORDER,
		FK_KAT,
		AD_TABLE,
		date_format(CURDATE(), '%Y') AS `YEAR`,
		date_format(CURDATE(), '%m') AS `MONTH`
	FROM
		ad_master
	WHERE
		ID_AD_MASTER=".(int)$_REQUEST['FK_AD']."
		AND FK_USER=".$uid);

require_once "sys/packet_management.php";
require_once "sys/lib.ads.php";
$packets = PacketManagement::getInstance($db);

$id_packet_order = $ar["FK_PACKET_ORDER"];
$order = $packets->order_get($id_packet_order);
$ar_order = $order->getPacketUsage($_REQUEST['FK_AD']);

$ar['UPLOADED'] = $db->fetch_atom("SELECT count(*) FROM `ad_upload` WHERE FK_AD=".(int)$_REQUEST['FK_AD']);
$ar['UPS_LEFT'] = $ar_order["downloads_available"] + $nar_systemsettings["MARKTPLATZ"]["FREE_UPLOADS"];
### Max 10 Uploads
$ar['UPS_LEFT'] = ( ($ar['UPS_LEFT']+$ar['UPLOADED']) > 10 ? 10 - $ar['UPLOADED'] : $ar['UPS_LEFT']);
$ar['UPS_FREE'] = $nar_systemsettings["MARKTPLATZ"]["FREE_UPLOADS"];
$ar['FORMATS_ALLOWED'] = $nar_systemsettings['MARKTPLATZ']['UPLOAD_TYPES'];

$tpl_content->addvars($ar);

if (!empty($_FILES) && ($ar['UPLOADED'] < 10) && ($ar['UPS_LEFT'] > 0)) {
	// Mehr Bilder erlaubt
	$up = array(
			'FK_AD' => $_REQUEST['FK_AD'],

		);
	$folder = AdManagment::getAdCachePath($ar['FK_AD'], true);
	$err = array();
	$filename = $_FILES['DATA']['name'];
	$hack = explode(".", $filename);
	$n = count($hack)-1;
	$ext = $up['EXT'] = $hack[$n];
	$up['FILENAME'] = preg_replace("/(^.*)(\.".$ext."$)/si", "$1", $filename);
	$up['SRC'] = $folder.'/'.$up['FILENAME'].'_x_'.time().'_x_.'.$ext;
	$allowed = explode(',', $nar_systemsettings['MARKTPLATZ']['UPLOAD_TYPES']);
	echo ht(dump($allowed));
	if(!in_array($ext, $allowed))
	{
		$err[] = "NOT_ALLOWED";
	}
	if(count($err))
	{
		for($i=0; $i<count($err); $i++)
		{
			$tpl_content->addvar($err[$i], 1);
		}
		$tpl_content->addvar("ERR_UP", 1);
	}
	else
	{
		move_uploaded_file($_FILES['DATA']['tmp_name'], $up['SRC']);

		$id_upload = $db->update("ad_upload", $up, true);
		$order->itemAdd($id_upload);
		die(forward("/index.php?page=ad_uploads&frame=ajax&FK_AD=".$ar['FK_AD']));
	}
	#echo ht(dump($up));
	#die("extension = ".$ext." filename = ".$filename);
}
$liste = $db->fetch_table("
	SELECT
		*,
		".$ar['FK_KAT']." AS FK_KAT,
		LEFT(FILENAME, 30) AS FILENAME_SHORT
	FROM
		ad_upload
	WHERE
		FK_AD=".$ar['FK_AD']);
$tpl_content->addlist("liste", $liste, "tpl/".$s_lang."/ad_uploads.row.htm");

?>