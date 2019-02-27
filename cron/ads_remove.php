<?php
/* ###VERSIONSBLOCKINLCUDE### */


global $db, $langval, $nar_systemsettings;
require_once($ab_path."sys/lib.ads.php");

/* Nicht mehr relevante Anzeigen löschen */

$deleteAds = $db->fetch_nar("
	SELECT
		ID_AD_MASTER, AD_TABLE
	FROM `ad_master`
	WHERE
		(CRON_DONE IS NULL) AND (STAMP_START IS NULL)
		AND FK_USER NOT IN (SELECT ID_USER FROM `useronline`)");
foreach ($deleteAds as $id_ad => $ad_table) {
	echo("Lösche nicht mehr Relevante Anzeige - ID: $id_ad Table: $ad_table\n");
    Ad_Marketplace::deleteAd($id_ad, $ad_table);
}

/* Hole alle User die alte Anzeigen besitzen */
$usersWithOldAds = $db->fetch_table("
    SELECT
        user.*
    FROM `ad_master`
    LEFT JOIN user ON user.ID_USER = ad_master.FK_USER
    WHERE (STAMP_END < NOW()) AND ((STATUS & 3) = 1) AND (DELETED=0)
    GROUP BY user.ID_USER
");

if(count($usersWithOldAds) > 0) {
    echo "Removing old ads..";

    foreach($usersWithOldAds as $key=>$userWithOldAds) {
        /* Zähle die Anzeigen */
        $numberOfoldAds = $db->fetch_atom("SELECT COUNT(*) FROM `ad_master` WHERE FK_USER = '".mysql_real_escape_string($userWithOldAds['ID_USER'])."' AND (STAMP_END < NOW()) AND ((STATUS & 3) = 1) AND (DELETED=0)");
        $langval = $db->fetch_atom("SELECT BITVAL FROM `lang` WHERE ID_LANG=".$userWithOldAds["FK_LANG"]);

        if($numberOfoldAds == 1) {
            // Info über eine ausgelaufene Anzeige

            $ad = $db->fetch1("SELECT ID_AD_MASTER, FK_KAT, FK_USER, FK_MAN, AD_TABLE, STAMP_END, PRODUKTNAME FROM `ad_master` WHERE FK_USER = '".mysql_real_escape_string($userWithOldAds['ID_USER'])."' AND (STAMP_END < NOW()) AND ((STATUS & 3) = 1) AND (DELETED=0)");

            $mail_content = array();
            $mail_content['PRODUCT'] = $ad["PRODUKTNAME"];
            $mail_content['PRODUCT_ID'] = $ad["ID_AD_MASTER"];
            $mail_content['PRODUCT_KAT'] = $ad["FK_KAT"];
			$mail_content['PRODUCT_MANUFACTURER'] = ((int)$ad["FK_MAN"] > 0 ? $db->fetch_atom("SELECT NAME FROM `manufacturers` WHERE ID_MAN=".(int)$ad["FK_MAN"]) : "");
            sendMailTemplateToUser(0, $userWithOldAds["ID_USER"], 'REMIND_AD_REMOVED',$mail_content);

            AdManagment::Disable($ad["ID_AD_MASTER"], $ad["AD_TABLE"]);
        } else {
            // Info über mehrere Ausgelaufene Anzeigen

            $mail_content = array();
            $mail_content['SITEURL'] = $nar_systemsettings['SITE']['SITEURL'];
            $mail_content['SITENAME'] = $nar_systemsettings['SITE']['SITENAME'];
            $mail_content['NUMBER_OF_REMOVED_ADS'] = $numberOfoldAds;
            sendMailTemplateToUser(0, $userWithOldAds["ID_USER"], 'REMIND_AD_REMOVED_BULK', $mail_content);

            AdManagment::DisableAllOldAdsByUser($userWithOldAds['ID_USER']);
        }
    }
}

?>