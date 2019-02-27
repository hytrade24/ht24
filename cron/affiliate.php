<?php

global $ab_path, $db, $nar_systemsettings;
$taskStart = microtime(true);
$maxImagesPerRun = 20;

require_once $ab_path.'sys/lib.ads.php';
require_once $ab_path.'sys/lib.affiliate.php';
require_once $ab_path.'sys/affiliate/AffiliateFactory.php';
require_once($ab_path."sys/lib.image.php");

$affiliateManagement = AffiliateManagement::getInstance($db);

if($nar_systemsettings['PLUGIN']['AFFILIATE'] == 0 && strlen($nar_systemsettings['PLUGIN']['AFFILIATE_KEY']) > 5) {
	return;
}


$doNotImport = FALSE;
$doTest = FALSE;

// Deaktivierte holen
$disabledAffiliate = $affiliateManagement->fetchNextCronjob(AffiliateManagement::STATUS_DISABLED);
if($disabledAffiliate != NULL) {

	$affiliate = $disabledAffiliate;
	$doNotImport = TRUE;
} else {
	$affiliate = $affiliateManagement->fetchNextCronjob(AffiliateManagement::STATUS_ENABLED);
}
if($affiliate == false) {
    $affiliate = $affiliateManagement->fetchNextCronjob(AffiliateManagement::STATUS_TESTING);
    $doTest = TRUE;
}
if($affiliate != NULL) {
	$affiliateAdapter = Affiliate_AffiliateFactory::factory($affiliate['ADAPTER']);
	$affiliateAdapter->init($affiliate);


	// Task Delete Old
	if(!$doNotImport) {
		// Task Import
		echo "run $affiliate[DESCRIPTION]<br>";

		$affiliateAdapter->import();
	} else {
		$affiliateAdapter->cleanUp();
	}

	$db->querynow("UPDATE affiliate SET STAMP_LAST = NOW() WHERE ID_AFFILIATE = '".(int)$affiliate['ID_AFFILIATE']."'");


} else {
	// load and save images local
	$downloadedAdIds = array();

	$numberOfAffiliatesWithoutImages = $db->fetch_atom("SELECT COUNT(*) as anz	FROM ad_master WHERE AFFILIATE = '1' AND AFFILIATE_URL_IMAGE IS NOT NULL AND AFFILIATE_URL_IMAGE != ''");
	$randOffset = rand(0, max(0, $numberOfAffiliatesWithoutImages - $maxImagesPerRun));
	echo "randoffset ".$randOffset."\n";

	$affiliateAdsWithoutImages = $db->fetch_table("
		SELECT
			*
		FROM ad_master
		WHERE AFFILIATE = '1' AND AFFILIATE_URL_IMAGE IS NOT NULL AND AFFILIATE_URL_IMAGE != ''
		LIMIT $randOffset, $maxImagesPerRun;
	");


	foreach($affiliateAdsWithoutImages as $key => $affiliateAdWithoutImage) {

		$file_get = file_get_contents(file_url($affiliateAdWithoutImage['AFFILIATE_URL_IMAGE']));
		if($file_get) {
			echo "url ".$affiliateAdWithoutImage['AFFILIATE_URL_IMAGE']."\n";
			$uploads_dir = AdManagment::getAdCachePath($affiliateAdWithoutImage['ID_AD_MASTER'], true, true);

			file_put_contents($tmp_name = $uploads_dir.'/tmp', $file_get);
			$name = 'imported.jpg';

			$img_thumb = new image(12, $uploads_dir, true);
			$img_thumb->check_file(array("tmp_name"=>$tmp_name,"name"=>$name));
			$src = "/".str_replace($ab_path, "", $img_thumb->img);
			$src_thumb = "/".str_replace($ab_path, "", $img_thumb->thumb);

			$image_data = array(
				"FK_AD"       => $affiliateAdWithoutImage['ID_AD_MASTER'],
				"CUSTOM"      => 1,
				"IS_DEFAULT"  => 1,
				"SRC"         => $src,
				"SRC_THUMB"   => $src_thumb
			);
			$db->querynow("DELETE FROM ad_images WHERE FK_AD = '".(int)$affiliateAdWithoutImage['ID_AD_MASTER']."'");
			$db->update("ad_images", $image_data, true);
			$downloadedAdIds[] = $affiliateAdWithoutImage['ID_AD_MASTER'];
			echo "download images for id ".$affiliateAdWithoutImage['ID_AD_MASTER']."<br>";
		} else {
			echo " image not found ".$affiliateAdWithoutImage['AFFILIATE_URL_IMAGE']."\n";
			#var_dump($file_get); echo "\n\n";
		}
		echo "\n\n";
	}

	if(count($downloadedAdIds) > 0) {
		$db->querynow("UPDATE ad_master SET AFFILIATE_URL_IMAGE_ORIGINAL = AFFILIATE_URL_IMAGE WHERE ID_AD_MASTER IN (".implode(',', $downloadedAdIds).")");
		$db->querynow("UPDATE ad_master SET AFFILIATE_URL_IMAGE = NULL WHERE ID_AD_MASTER IN (".implode(',', $downloadedAdIds).")");
	}
}
$taskEnd = microtime(true);

