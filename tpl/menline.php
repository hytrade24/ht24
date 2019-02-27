<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once 'sys/lib.user_contact.php';
require_once 'sys/lib.chat.user.read.message.php';
require_once 'sys/lib.watchlist.php';
require_once 'sys/lib.watchlist_user.php';
require_once $ab_path.'sys/lib.user.authentication.php';

#$GLOBALS['SILENCE']=false;
if($uid)
{
    $chatUserReadMessageManagement = ChatUserReadMessageManagement::getInstance($db);
	$userMails = $chatUserReadMessageManagement->countUnreadMessagesExByUserId($uid);
	/** @var Api_Plugins_Leads_Plugin $pluginLeads */
	$pluginLeads = Api_TraderApiHandler::getInstance($db)->getPlugin("Leads");
	$chatLeadCount = $pluginLeads->getUserChatCount();
	$userMails['COUNT_UNREAD'] += $chatLeadCount;
	
	$tpl_content->addvar('NEW_MAILS', $userMails['COUNT_UNREAD']);
	$tpl_content->addvar('NEW_MAILS_AD', $userMails['COUNT_UNREAD_AD']);
	$tpl_content->addvar('NEW_MAILS_LEADS', $chatLeadCount);
	$tpl_content->addvar('NEW_MAILS_OTHER', $userMails['COUNT_UNREAD'] - $userMails['COUNT_UNREAD_AD'] - $chatLeadCount);


	$userContactManagement = UserContactManagement::getInstance($db);
	$userContactCount = $userContactManagement->countUserContactsByUserId($uid, UserContactManagement::STATUS_ACCEPTED);
	$userContactRequestCount = $userContactManagement->countUserContactsByAcceptorUserId($uid, UserContactManagement::STATUS_REQUESTED);
	$tpl_content->addvar("USER_CONTACT_COUNT", $userContactCount);
	$tpl_content->addvar("USER_CONTACT_REQUEST_COUNT", $userContactRequestCount);


	// Watchlist
	$watchlistManagement = WatchlistManagement::getInstance($db);
	$watchlistUserManagement = WatchlistUserManagement::getInstance($db);

	$watchLists = $watchlistUserManagement->fetchAllByParam(array(
		'FK_USER' => $uid
	));

	$merken = 0;
	foreach($watchLists as $key => $watchList) {
		$topLinks = $watchlistManagement->fetchAllByParam(array(
			'FK_USER' => $uid,
			'FK_WATCHLIST_USER' => $watchList['ID_WATCHLIST_USER'],
			'LIMIT' => 3,
			'SORT_BY' => 'w.STAMP_CREATE',
			'SORT_DIR' => 'DESC'
		));
		$watchLists[$key]['numberOfLinks'] = $watchlistManagement->getLastFetchByParamCount();
		$merken += $watchLists[$key]['numberOfLinks'];


		$tmpTemplate = new Template("tpl/de/empty.htm");
		$tmpTemplate->tpl_text = '{liste}';
		$tmpTemplate->addlist('liste', $topLinks, 'tpl/'.$s_lang.'/watchlist_widget.row_item.htm');
		$watchLists[$key]['watchlist_liste_links'] = $tmpTemplate->process();
		$watchLists[$key]['WATCHLIST_TITLE'] = $GLOBALS["tpl_main"]->vars["pagetitle"];
	}

	$tpl_content->addlist("watchlist_list", $watchLists, "tpl/".$s_lang."/watchlist_widget.row_list.htm");
	$currentWatchlistUrl = implode(',', $ar_params);
	$tpl_content->addvar('WATCHLIST_CURURL', $currentWatchlistUrl);
	$tpl_content->addvar("MLISTE", (int)$merken);

}

$userAuthenticationManagement = UserAuthenticationManagement::getInstance($db);
$enabledProviders = $userAuthenticationManagement->getHybridAuthProviders();

foreach($enabledProviders as $providerName => $provider) {
	$enabledProviders[$providerName]['PROVIDERNAME'] = $providerName;
}
$tpl_content->addlist('social_media_login_providers', $enabledProviders, 'tpl/'.$s_lang.'/menline.social-media-provider.row.htm');
$tpl_content->addvar('SOCIAL_MEDIA_LOGIN_ENABLED', $userAuthenticationManagement->isSocialMediaLoginEnabled());

$tpl_content->addvar("COOKIE_WARNING", $nar_systemsettings['SITE']['COOKIE_WARNING'] 
	&& (!array_key_exists("cookiebar", $_COOKIE) || $_COOKIE["cookiebar"] != "hide"));

?>