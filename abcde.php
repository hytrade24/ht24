<?php
error_reporting(0);
include_once "src/MicrosoftTranslator.php";
require_once 'api.php';

$res = $db->querynow("update ad_master set PRODUKTNAME_EN ='', BESCHREIBUNG_EN =''");
die();

$login_query = "select ID_AD_MASTER , PRODUKTNAME,BESCHREIBUNG from ad_master 
	where ID_AD_MASTER > 57875
	order by ID_AD_MASTER ASC
	LIMIT 30";
// $login_query = "select ID_AD_MASTER , PRODUKTNAME,BESCHREIBUNG from ad_master where ID_AD_MASTER < 13034 order by ID_AD_MASTER ASC ";
$rs = $db->fetch_table($login_query);

foreach ($rs as $row) 
{
	$PRODUKTNAME = Translate( $row['PRODUKTNAME'] );
	$BESCHREIBUNG = Translate( $row['BESCHREIBUNG'] );
	$res = $db->querynow("update ad_master set 
		PRODUKTNAME_EN ='".$PRODUKTNAME."',
		BESCHREIBUNG_EN ='".$BESCHREIBUNG."'
      	where ID_AD_MASTER=". $row['ID_AD_MASTER'] );
	echo "<br>".$row['ID_AD_MASTER'];
}
?>


