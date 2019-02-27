<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path.'sys/lib.user.authentication.php';

$userAuthenticationManagement = UserAuthenticationManagement::getInstance($db);
$enabledProviders = $userAuthenticationManagement->getHybridAuthProviders();

foreach($enabledProviders as $providerName => $provider) {
	$enabledProviders[$providerName]['PROVIDERNAME'] = $providerName;
}

if (!isset($_COOKIE['login_redirect'])) {
    $s_page_prev = join(',', $ar_params).".htm";
    $tpl_modul->addvar('prevpage', $s_page_prev);
} else {
    $tpl_modul->addvar('prevpage', $_COOKIE['login_redirect']);
    setcookie("login_redirect", "", time() - 3600);
}

### Ge�ndert am 24.04.2006
### Es gibt jetzt mehrere Templates

// MODE Auswahl
if($GLOBALS['uid'])
{
  $ar_params[1] = 'loggedin';
}

if($ar_params[1])
{
  #if(!file_exists($GLOBALS['ab_path']."module/login/".$ar_params[1].".php"))
   # $ar_params[1] = NULL;
}

$smode = (!isset($ar_params[1]) || $ar_params[1] == "fail" ? 'loginform' : $ar_params[1]);
if (file_exists("module/login/" . $smode . ".php")) {
	include "module/login/" . $smode . ".php";
}

$tpl_sub = new Template("module/tpl/".$s_lang."/".$smode.".htm");

$tpl_modul->addlist('social_media_login_providers', $enabledProviders, 'module/tpl/'.$s_lang.'/login.social-media-provider.row.htm');
$tpl_modul->addvar('SOCIAL_MEDIA_LOGIN_ENABLED', $userAuthenticationManagement->isSocialMediaLoginEnabled());

#echo $smode;
$tpl_modul->addvar("MODE_TPL", $tpl_sub);

?>