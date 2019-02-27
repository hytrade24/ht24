<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once 'sys/lib.user_contact.php';
require_once 'sys/lib.ad_rating.php';

$paginatorPage = ((int)$ar_params[3] ? (int)$ar_params[3] : 1);
$paginatorItemsPerPage = 25;
$paginatorOffset = ($paginatorItemsPerPage*$paginatorPage)-$paginatorItemsPerPage;

$userId = ((int)$ar_params[2] ? (int)$ar_params[2] : null);
$user_ = $db->fetch1("select ID_USER,VORNAME,NACHNAME,NAME,CACHE,STAMP_REG,LASTACTIV,URL,STRASSE,PLZ,ORT,LU_PROFESSION,ID_USER as USER_ID_USER ,UEBER, ROUND(RATING) as lastrate,TIMESTAMPDIFF(YEAR,GEBDAT,CURDATE()) as age,TEL,RATING,UEBER from user where ID_USER='". $userId."'"); 

$tpl_content->addvar("active_contact", 1);

if(($userId != null) && ($user != null)) {
	
	$userContactManagement = UserContactManagement::getInstance($db);
	$adRatingManagement = AdRatingManagement::getInstance($db);
		
	/**
	 * Liste aller Bewertungen
	 */
	
	$userContacts = $userContactManagement->fetchUserContactsByUserId($userId, UserContactManagement::STATUS_ACCEPTED, $paginatorOffset .', ' . $paginatorItemsPerPage);
	$countUserContacts = $userContactManagement->countUserContactsByUserId($userId, UserContactManagement::STATUS_ACCEPTED);
	
	foreach ($userContacts as $key => $userContact) {
		$ar_user_contact = $db->fetch1("SELECT * FROM user WHERE ID_USER = '".mysql_escape_string($userContact['USER_ID'])."'");
		if($ar_user_contact) { 
			$userContacts[$key]['USER_CACHE'] = $ar_user_contact['CACHE']; 
			$userContacts[$key]['USER_ID_USER'] = $ar_user_contact['ID_USER']; 
			$userContacts[$key]['USER_FIRMA'] = $ar_user_contact['FIRMA']; 
			$userContacts[$key]['USER_STRASSE'] = $ar_user_contact['STRASSE']; 
			$userContacts[$key]['USER_PLZ'] = $ar_user_contact['PLZ']; 
			$userContacts[$key]['USER_ORT'] = $ar_user_contact['ORT'];
            $userContacts[$key]['USER_ORT'] = $ar_user_contact['ORT'];  
			$userContacts[$key]['USER_RATING'] = $ar_user_contact['RATING']; 
            $userContacts[$key]['UEBER'] = $ar_user_contact['UEBER']; 
            
		}
	}
  
	$tpl_content->addlist("userContacts", $userContacts, $ab_path.'tpl/'.$s_lang.'/view_user_contacts.row.htm');
	
	$pager = htm_browse_extended($countUserContacts, $npage, "view_user_contacts,".$tpl_content->tpl_urllabel($user_['NAME']).",".$userId.",{PAGE}", $paginatorItemsPerPage);
	$tpl_content->addvar("pager", $pager);
	
	$tpl_content->addvar("t_".$view, 1);
	$tpl_content->addvar("UID", $uid);
	$tpl_content->addvars($user_, "USER_");
	
	#$tpl_content->addvars($user_, 'USER_');
} else {
	$nullUser = $db->fetch_blank('user');
	$tpl_content->addvars($nullUser);
}