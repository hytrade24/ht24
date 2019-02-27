<?php

require_once $ab_path.'sys/lib.user.authentication.php';

$userAuthenticationManagement = UserAuthenticationManagement::getInstance($db);

if(isset($_GET['ACTION']) && $_GET['ACTION'] == 'connect' && isset($_REQUEST['SOCIAL_MEDIA_PROVIDER'])) {
	$connectResult = $userAuthenticationManagement->connect($_REQUEST['SOCIAL_MEDIA_PROVIDER']);

	if($connectResult != null) {
		if($connectResult['userData'] == null) {
			$userAuthenticationManagement->createUserAuthenticationForUserId($uid, $connectResult['provider'], $connectResult['socialMediaProfile']->identifier, (array)$connectResult['socialMediaProfile']);
			$tpl_content->addvar('ok', 1);
		} else {
			$tpl_content->addvar('err', 1);
		}

	}
} elseif(isset($_GET['ACTION']) && $_GET['ACTION'] == 'disconnect' && isset($_REQUEST['SOCIAL_MEDIA_PROVIDER'])) {
	$userAuthenticationManagement->disconnect($uid, $_REQUEST['SOCIAL_MEDIA_PROVIDER']);

	$tpl_content->addvar('ok', 1);
}


$enabledProviders = $userAuthenticationManagement->getHybridAuthProviders();
$userProviders = $userAuthenticationManagement->fetchAllProvidersByUserId($uid);

$userProvidersGroupedByProvider = array();
foreach($userProviders as $key => $userProvider) {
	$userProvidersGroupedByProvider[strtolower($userProvider['PROVIDER'])] = $userProvider;
}

foreach($enabledProviders as $providerName => $provider) {
	if(isset($userProvidersGroupedByProvider[strtolower($providerName)])) {
		$tmpUserProvider = $userProvidersGroupedByProvider[strtolower($providerName)];

		$enabledProviders[$providerName] = array_merge($provider, array(
			'USER_CONNECTED' => 1,
			'DISPLAY_NAME' => $tmpUserProvider['DISPLAYNAME'],
			'PROVIDER_EMAIL' => $tmpUserProvider['EMAIL']
		));
	} else {

	}

	$enabledProviders[$providerName]['PROVIDERNAME'] = $providerName;
}


$tpl_content->addlist('liste', $enabledProviders, 'tpl/'.$s_lang.'/my-profil-social-media.row.htm');
$tpl_content->addvar('SOCIAL_MEDIA_LOGIN_ENABLED', $userAuthenticationManagement->isSocialMediaLoginEnabled());
