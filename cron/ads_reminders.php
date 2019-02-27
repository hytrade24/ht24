<?php
/**
 * ebiz-trader
 *
 * @copyright Copyright (c) 2012 ebiz-consult e.K.
 * @version 6.4.0
 */


global $db, $langval, $nar_systemsettings;
require_once($ab_path . "sys/lib.ads.php");

$reminderDays = $nar_systemsettings['MARKTPLATZ']['ADS_DAYS_REMIND'];
if (!preg_match("/^[0-9,]+$/", $reminderDays)) {
    // Invalid setting, use default
    $reminderDays = 3;
}

/* Hole alle User die Anzeigen besitzen die bald auslaufen */
$usersWithRemindAds = $db->fetch_table("
    SELECT
        user.*,
        DATEDIFF(STAMP_END, NOW()) as DAYS
    FROM `ad_master`
    LEFT JOIN user ON user.ID_USER = ad_master.FK_USER
    WHERE DATEDIFF(STAMP_END, NOW()) IN (".$reminderDays.") AND ((STATUS & 3) = 1) AND (DELETED=0)
    GROUP BY user.ID_USER
");

if(count($usersWithRemindAds) > 0) {
    echo "Remind ads..";

    foreach($usersWithRemindAds as $key=>$userWithRemindAds) {
        /* Zähle die Anzeigen */
        $numberOfRemindAds = $db->fetch_atom("SELECT COUNT(*) FROM `ad_master` WHERE FK_USER = '".mysql_real_escape_string($userWithRemindAds['ID_USER'])."' AND DATEDIFF(STAMP_END, NOW()) IN (".$reminderDays.") AND ((STATUS & 3) = 1) AND (DELETED=0)");
        $langval = $db->fetch_atom("SELECT BITVAL FROM `lang` WHERE ID_LANG=".$userWithRemindAds["FK_LANG"]);
        $userWantsMail = $db->fetch_atom("SELECT GET_MAIL_AD_TIMEOUT FROM usersettings WHERE FK_USER=" . $userWithRemindAds["ID_USER"]);

        if($userWantsMail) {
            require_once $ab_path."sys/lib.translation.php";
            $intInterval = (int)$userWithRemindAds['DAYS'];
            $strInterval = $intInterval." ".Translation::readTranslation('general', ($intInterval > 1 ? 'date.days' : 'date.day'), null, array(), ($intInterval > 1 ? 'Tage' : 'Tag'));
            if($numberOfRemindAds == 1) {
                // Info über eine bald auslaufende Anzeige

                $ad = $db->fetch1("SELECT ID_AD_MASTER, FK_KAT, FK_USER, FK_MAN, AD_TABLE, STAMP_END, PRODUKTNAME FROM `ad_master` WHERE FK_USER = '".mysql_real_escape_string($userWithRemindAds['ID_USER'])."' AND DATEDIFF(STAMP_END, NOW()) IN (".$reminderDays.") AND ((STATUS & 3) = 1) AND (DELETED=0)");

                $mail_content = array();
                $mail_content['DAYS'] = $intInterval;
                $mail_content['INTERVAL'] = $strInterval;
                $mail_content['PRODUCT'] = $ad["PRODUKTNAME"];
                $mail_content['PRODUCT_ID'] = $ad["ID_AD_MASTER"];
                $mail_content['PRODUCT_KAT'] = $ad["FK_KAT"];
                $mail_content['PRODUCT_MANUFACTURER'] = ((int)$ad["FK_MAN"] > 0 ? $db->fetch_atom("SELECT NAME FROM `manufacturers` WHERE ID_MAN=".(int)$ad["FK_MAN"]) : "");
                sendMailTemplateToUser(0, $userWithRemindAds["ID_USER"], 'REMIND_AD_REMOVE_SOON', $mail_content);
            } else {
                // Info über mehrere bald auslaufende Anzeigen

                $mail_content = array();
                $mail_content['DAYS'] = $intInterval;
                $mail_content['INTERVAL'] = $strInterval;
                $mail_content['NUMBER_OF_REMIND_ADS'] = $numberOfRemindAds;
                $mail['T1'] = parse_mail($mail['T1'], $mail_content);
                sendMailTemplateToUser(0, $userWithRemindAds["ID_USER"], 'REMIND_AD_REMOVE_SOON_BULK', $mail_content);
            }
        }
    }
}
?>