<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path."sys/lib.club.php";
$clubManagement = ClubManagement::getInstance($db);


$get_uid = ((int)$tpl_content->vars['OVERRIDE_USER_ID'] > 0) ? $tpl_content->vars['OVERRIDE_USER_ID'] : $ar_params[2];


function checkview($checkthis) {
	global $tpl_content,$uid,$get_uid,$db;

	switch($checkthis)
	{
		case 'ALL':
			return 1;
			break;
		case 'USER':
			if ($uid>0){
				return 1;
			} else {
				return 0;
			}
			break;
		case 'CONTACT':
            $data = $db->fetch_atom("select status from user_contact where ((FK_USER_A = '".$uid."' AND FK_USER_B = '".$get_uid."') OR (FK_USER_A = '".$get_uid."' AND FK_USER_B = '".$uid."'))");
            if ($data==1)
			 return $data;
            else
              return 0;
			break;
		default:
			return 0;
			break;
	}
}

if ($get_uid > 0 ) {

	$data = $db->fetch1("
		select
			u.VORNAME as USER_VORNAME,
			u.NACHNAME as USER_NACHNAME,
			u.NAME as USER_NAME,
			u.FIRMA as USER_FIRMA,
			u.CACHE as USER_CACHE,
			u.STAMP_REG as USER_STAMP_REG,
			u.LASTACTIV as USER_LASTACTIV,
			u.URL as USER_URL,
			u.STRASSE as USER_STRASSE ,
			u.PLZ as USER_PLZ,
			u.ORT as USER_ORT,
			u.ID_USER as USER_ID_USER,
			u.UEBER as USER_UEBER,
			ROUND(u.RATING) as USER_lastrate,
			TIMESTAMPDIFF(YEAR,u.GEBDAT,CURDATE()) as USER_age,
			u.TEL as USER_TEL,
			u.TOP_USER as USER_TOP_USER,
			u.TOP_SELLER AS USER_TOP_SELLER,
			u.PROOFED AS USER_PROOFED
		from user u
		where u.ID_USER=". $get_uid); // Userdaten lesen
	include_once ($GLOBALS['nar_systemsettings']['SITE']['USER_PATH'].$data['USER_CACHE']."/".$get_uid."/useroptions.php");

    $tpl_content->addvar("showcontact",checkview($useroptions['LU_SHOWCONTAC']));
	$tpl_content->addvar("USER_ALLOW_CONTACS",$useroptions['ALLOW_CONTACS']);
	$tpl_content->addvar("USER_ALLOW_ADD_USER_CONTACT",$useroptions['ALLOW_ADD_USER_CONTACT']);
	$tpl_content->addvar("USER_SHOW_STATUS_USER_ONLINE",$useroptions['SHOW_STATUS_USER_ONLINE']);
	$tpl_content->addvar("VENDOR_ALLOW_CONTACS",$useroptions['ALLOW_CONTACS']);
	$tpl_content->addvar("VENDOR_ALLOW_ADD_USER_CONTACT",$useroptions['ALLOW_ADD_USER_CONTACT']);
	$tpl_content->addvar("VENDOR_SHOW_STATUS_USER_ONLINE",$useroptions['SHOW_STATUS_USER_ONLINE']);
    #$tpl_content->addvar("isuser",checkview('USER'));

    $queryAds = Rest_MarketplaceAds::getQueryByParams(["FK_USER" => $vendor["FK_USER"]]);
    $countAds = $queryAds->fetchCount();

    $tpl_content->addvar("VENDOR_AD_COUNT",$countAds);

	$nar_tplglobals['newstitle'] = $data['USER_NAME'];

	if($data['USER_ID_USER'] != $uid) {
		$res = $db->querynow("update user_views set `VIEWS`=`VIEWS`+1 where
		    FK_USER=".$get_uid." and STAMP=CURDATE()");
		if(!$res['int_result'])
		$res = $db->querynow("insert into user_views set `VIEWS`=1, FK_USER=".$data['USER_ID_USER'].", STAMP=CURDATE()");
	} // nicht der eigene user


	$tpl_content->addvar("t_".$view, 1);
	$tpl_content->addvar("UID", $uid);
	if ($data['USER_ID_USER'] < 1)
	$data = $db->fetch_blank('user');

	$tpl_content->addvars($data);
	//Kommentare lesen

	/** Kontakte **/
	require_once 'sys/lib.user_contact.php';

	$userContactManagement = UserContactManagement::getInstance($db);
	$userContacts = $userContactManagement->fetchUserContactsByUserId($get_uid, UserContactManagement::STATUS_ACCEPTED, 12);
	$existsUserContact = $userContactManagement->existsUserContact($uid, $get_uid);

	foreach($userContacts as $key=>$userContact) {
		$user = $db->fetch1("SELECT * FROM user WHERE ID_USER = '".mysql_escape_string($userContact['USER_ID'])."'");
		if($user) {
			$userContacts[$key]['USER_CACHE'] = $user['CACHE'];
			$userContacts[$key]['USER_ID_USER'] = $user['ID_USER'];
			$userContacts[$key]['USER_FIRMA'] = $user['FIRMA'];
			$userContacts[$key]['USER_STRASSE'] = $user['STRASSE'];
			$userContacts[$key]['USER_PLZ'] = $user['PLZ'];
			$userContacts[$key]['USER_ORT'] = $user['ORT'];
		}
	}

	$tpl_content->addlist("userContacts", $userContacts, $ab_path.'tpl/'.$s_lang.'/view_user.contact.row.htm');
	$tpl_content->addvar("IN_CONTACT", $existsUserContact);

	/** User **/
	require_once 'sys/lib.user.php';

	$userManagement = UserManagement::getInstance($db);

	$isUserOnline = $userManagement->isUserOnline($get_uid);
	$tpl_content->addvar("isUserOnline", $isUserOnline);

    // Vendor
    require_once 'sys/lib.vendor.php';
    require_once 'sys/lib.vendor.category.php';

    $vendorManagement = VendorManagement::getInstance($db);
    $vendorCategoryManagement = VendorCategoryManagement::getInstance($db);

    $vendorManagement->setLangval($langval);
    $vendorCategoryManagement->setLangval($langval);

    $isUserVendor = $vendorManagement->isUserVendorByUserId($get_uid);
    $tpl_content->addvar("USER_IS_VENDOR", $isUserVendor);

    if($isUserVendor) {
        $tmp = $vendorManagement->fetchByUserId($get_uid);


        $vendor = $vendorManagement->fetchByVendorId($tmp['ID_VENDOR']);
        $vendorTemplate = array();

        if($vendor == null) { die(); }

        // Template aufbereiten
        foreach($vendor as $key=>$value) { $vendorTemplate['VENDOR_'.$key] = $value; }

        if($vendorTemplate['VENDOR_CHANGED'] == '0000-00-00 00:00:00') {
            $vendorTemplate['VENDOR_CHANGED'] = 0;
        }

        // Kategorie Liste
        $categories = $vendorCategoryManagement->fetchAllVendorCategoriesByVendorId($vendor['ID_VENDOR']);
        $vendorMainCategory = null;
        foreach ( $categories as $row ) {
        	if ( $row["IS_PREFERRED"] == "1" ) {
        		$vendorMainCategory = $row["FK_KAT"];
        		$vendor["MAIN_CATEGORY"] = $row["FK_KAT"];
		        $vendorTemplate['VENDOR_MAIN_CATEGORY'] = $row["FK_KAT"];
	        }
        }
        $tpl_categories = new Template($ab_path."tpl/".$s_lang."/vendor.row.categories.htm");
        $tpl_categories->addlist("categories", $categories, $ab_path.'tpl/'.$s_lang.'/vendor.row.categories.row.htm');
        $vendorTemplate['VENDOR_CATEGORIES'] = $tpl_categories->process();

        $vendorTemplate['VENDOR_LOGO'] = ($vendorTemplate['VENDOR_LOGO'] != "")?'cache/vendor/logo/'.$vendorTemplate['VENDOR_LOGO']:null;
        $vendorTemplate['VENDOR_DESCRIPTION'] = $vendorManagement->fetchVendorDescriptionByLanguage($vendor['ID_VENDOR']);
        $vendorTemplate['USER_ID_USER'] = $vendor['FK_USER'];

        $tpl_content->addvars($vendorTemplate);

	    $query = 'SELECT c.CODE
					FROM vendor v 
					INNER JOIN country c
					ON v.FK_USER = ' . $vendor['FK_USER'] .' 
					AND c.ID_COUNTRY = v.FK_COUNTRY';

	    $tpl_content->addvar("COUNTRY_CODE",$db->fetch_atom( $query ));

	    $tpl_content->addvar("VENDOR_OPEN_PAGE_".$ar_params[0],1);
    }

    require_once 'sys/lib.job.php';
    $jobManagement = JobManagement::getInstance($db);
    $userJobsCount = count($jobManagement->fetchAllJobsByUserId($get_uid));
    $hasJobs = ($userJobsCount > 0);
	$tpl_content->addvar("USER_HAS_JOBS", $hasJobs);
    $tpl_content->addvar("USER_JOBS_COUNT", $userJobsCount);

	$countClubs = $clubManagement->countClubsWhereUserIsMember($get_uid);
    $tpl_content->addvar("USER_HAS_CLUBS", ($countClubs > 0));
    $tpl_content->addvar("USER_CLUB_COUNT", $countClubs);

	require_once $ab_path."sys/lib.calendar_event.php";
	$calendarEventManagement = CalendarEventManagement::getInstance($db);
	$countEvents = $calendarEventManagement->countByParam(array("FK_REF_TYPE" => "user", "FK_REF" => $get_uid));
    $tpl_content->addvar("USER_HAS_EVENTS", ($countEvents > 0));
    $tpl_content->addvar("USER_EVENT_COUNT", $countEvents);

    $tpl_content->addvar("USER_HAS_NEWS", $db->fetch_atom("SELECT count(*) FROM `news` WHERE FK_AUTOR=".$get_uid." AND OK=3"));


	// Impressum Tab
	$userHasImpressum = $db->fetch_atom("SELECT (IMPRESSUM <> '') FROM usercontent WHERE FK_USER = '".(int)$get_uid."'");
	$tpl_content->addvar("USER_HAS_IMPRESSUM", $userHasImpressum);
}