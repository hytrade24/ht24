<?php
/* ###VERSIONSBLOCKINLCUDE### */



 ### Zeitraum nach welchem nicht bestätigte USER gelöscht werden sollen
 ### angabe MySQL tauglich
 ### z.B. 14 day oder 1 year
 $del_time = "14 day";
 
 ### nicht bestätigte Newsletteranmeldungen löschen
 global $db, $nar_systemsettings, $langval;
 
 $res = $db->querynow("delete from nl_recp 
   where STAMP is NOT NULL and STAMP <= now()");
 
 if($res['int_result'] > 0)
   eventlog("info", $res['int_result']." veraltete Newslettereinträge gelöscht!");
 
 if($res['str_error'])
   eventlog("error", "Datenbankfehler /cron/old_users.php", dump($res));

 ### veraltete Neuanmeldungen löschen
 
 $res = $db->querynow("select * from `user` where 
  `STAT` = 2 AND STAMP_REG <= date_sub(NOW(), interval ".$del_time.")");
 
 if($res['int_result'] > 0)
 {
   while($row = mysql_fetch_assoc($res['rsrc']))
   {
     $del = $db->querynow("delete from role2user where FK_USER=".$row['ID_USER']);
	 if(!$del['str_error'])
	 {
	   $del = $db->querynow("update nl_recp set FK_USER=0 where FK_USER=".$row['ID_USER']);
	   if(!$del['str_error'])
	   {
	     $del = $db->querynow("delete from `user`where ID_USER=".$row['ID_USER']);
		 if(!$del['str_error'])
		   eventlog("warning", "nicht bestätigter User ".$row['NAME']." gelöscht!");
	     else
		   eventlog("error", "Datenbankfehler /cron/old_users.php", dump($del));
	   }
	   else
	     eventlog("error", "Datenbankfehler /cron/old_users.php", dump($del));	   
	 }
	 else
	   eventlog("error", "Datenbankfehler /cron/old_users.php", dump($del));
   } // while
 } // user gefunden 
 
 if($res['str_error'])
   eventlog("error", "Datenbankfehler /cron/old_users.php", dump($res));


// Anbieterverzeichnis, inaktive User informieren
require_once $ab_path.'sys/lib.vendor.php';
$vendorManagement = VendorManagement::getInstance($db);
$vendorManagement->setLangval($langval);

$daysWithoutLoginToInfo = (int)$nar_systemsettings['USER']['VENDOR_DAYS_WITHOUT_LOGIN_INFO'];
if($daysWithoutLoginToInfo > 0) {
    $lastActiveDay = new DateTime($daysWithoutLoginToInfo ." days ago");
    $inactiveUsers = $db->fetch_table($a = "
        SELECT
            u.*
        FROM
            user u
        JOIN
            vendor v ON u.ID_USER = v.FK_USER
        WHERE
            v.STATUS = 1
            AND DATE(u.LASTACTIV) = '".$lastActiveDay->format("Y-m-d")."'
    ");


    foreach($inactiveUsers as $key => $inactiveUser) {
        $langval = $db->fetch_atom("SELECT BITVAL FROM `lang` WHERE ID_LANG=".$inactiveUser["FK_LANG"]);

        $mail_content = array();
        $mail_content['VORNAME'] = $inactiveUser["VORNAME"];
        $mail_content['NACHNAME'] = $inactiveUser["NACHNAME"];
        $mail_content['NAME'] = $inactiveUser["NAME"];
        $mail_content['INACTIVE_DAYS'] = $daysWithoutLoginToInfo;

        sendMailTemplateToUser(0, $inactiveUser["ID_USER"], 'VENDOR_INFO_INACTIVE',$mail_content);
    }

}

// Anbieterverzeichnis, inaktive User deaktivieren
$daysWithoutLoginToDisable = (int)$nar_systemsettings['USER']['VENDOR_DAYS_WITHOUT_LOGIN_DEL'];
if($daysWithoutLoginToDisable > 0) {
    $lastActiveDay = new DateTime($daysWithoutLoginToDisable ." days ago");

    $inactiveUsers = $db->fetch_table($a = "
        SELECT
            u.*
        FROM
            user u
        JOIN
            vendor v ON u.ID_USER = v.FK_USER
        WHERE
            v.STATUS = 1
            AND DATE(u.LASTACTIV) = '".$lastActiveDay->format("Y-m-d")."'
    ");


    foreach($inactiveUsers as $key => $inactiveUser) {
        $vendorManagement->saveVendorByUserId(array('STATUS' => 0), $inactiveUser['ID_USER']);

        $mail_content = array();
        $mail_content['SITEURL'] = $nar_systemsettings['SITE']['SITEURL'];
        $mail_content['SITENAME'] = $nar_systemsettings['SITE']['SITENAME'];
        $mail_content['VORNAME'] = $inactiveUser["VORNAME"];
        $mail_content['NACHNAME'] = $inactiveUser["NACHNAME"];
        $mail_content['NAME'] = $inactiveUser["NAME"];
        $mail_content['INACTIVE_DAYS'] = $daysWithoutLoginToDisable;

        sendMailTemplateToUser(0, $inactiveUser["ID_USER"], 'VENDOR_DISABLE_INACTIVE', $mail_content);
    }
}
 
?>