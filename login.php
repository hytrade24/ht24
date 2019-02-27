<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once 'api.php';
require_once $ab_path.'sys/lib.user.authentication.php';


$host = $_SERVER['HTTP_HOST'];
$hack = explode(".", $host);
$n = count($hack);

if(count($hack) >= 2) {
    if(count($hack) > 3) {
        array_shift($hack);
    }
	$cookie_domain = ".".implode(".", $hack);
} else {
	$cookie_domain = NULL;
}

$ajax = FALSE;
$stay = FALSE;

if ($_REQUEST['frame'] == 'ajax') {
	include "sys/ajax/config.ajax.php";
	$ajax = TRUE;
}

$usr = FALSE;
$usrAdminCheck = true;
$authenticated = false;
$userAuthenticationManagement = UserAuthenticationManagement::getInstance($db);

$tpl_dummy = new Template("tpl/de/empty.htm");

if ($userAuthenticationManagement->isSocialMediaLoginEnabled()) {
    // Bei der Registrierung ausgewähltes Paket speichern
    $_SESSION['REGISTER_PACKET'] = (array_key_exists('REGISTER_PACKET', $_REQUEST) ? (int)$_REQUEST['REGISTER_PACKET'] : false);
    if (!empty($_REQUEST['SOCIAL_MEDIA_PROVIDER'])) {
        $hybridAuth = new Hybrid_Auth($userAuthenticationManagement->getHybridAuthConfigurationPath());

        $adapter = Hybrid_Auth::authenticate( $_REQUEST['SOCIAL_MEDIA_PROVIDER'] );

        $user_profile = $adapter->getUserProfile();
        if($user_profile != null) {
            $usr = $userAuthenticationManagement->findUserByProviderUid($_REQUEST['SOCIAL_MEDIA_PROVIDER'],  $user_profile->identifier);

            if($usr != null) {
                $authenticated = true;
            } else {
                // not yet registered
                $registerSocialMediaData = array(
                    'provider' => $_REQUEST['SOCIAL_MEDIA_PROVIDER'],
                    'userProfile' => $user_profile
                );
                $_SESSION['REGISTER_SOCIAL_MEDIA'] = json_encode($registerSocialMediaData);

                die(forward( $tpl_dummy->tpl_uri_action("register") ));
            }

        }
    } else if (!empty($_REQUEST['SOCIAL_MEDIA_CANCEL'])) {
        unset($_SESSION['REGISTER_SOCIAL_MEDIA']);
        die(forward( $tpl_dummy->tpl_uri_action("register") ));
    }
}

if (!empty($_POST['pass']) && $authenticated == false) {
	// Regular login
	$is_email = strpos( $_POST['user'], "@" );
	$login_query = '';
	if ( $is_email == false ) {
		$login_query = "select * from `user` where NAME='" . mysql_real_escape_string(trim($_POST['user'])) . "'";
	}
	else {
		$login_query = "select * from `user` where EMAIL='" . mysql_real_escape_string(trim($_POST['user'])) . "'";
	}
	$tmpUser = $db->fetch1($login_query);

	if ($tmpUser && pass_compare($_POST['pass'], $tmpUser['PASS'], $tmpUser['SALT'])) {
		$usr = $tmpUser;
		$authenticated = true;
	}

} else if (!empty($_REQUEST['sig'])) {
	// Admin login
	$usr = $db->fetch1($sql = "select * from `user` where ID_USER='" . mysql_escape_string(trim($_REQUEST['user'])) . "' and MD5(PASS)='" . sqlString($_REQUEST['sig']) . "'");
    if ($usr !== false) {
        $usrAdminCheck = false;
        $_SESSION['USER_IS_ADMIN'] = 1;
        /** @var Api_Plugins_Leads_Plugin $pluginLeads */
        $pluginLeads = Api_TraderApiHandler::getInstance($db)->getPlugin("Leads");
        $pluginLeads->login($usr["ID_USER"], ($_REQUEST["emp"] > 0 ? (int)$_REQUEST["emp"] : null));
    }
}

if (($usr !== FALSE) && ($usr['VB_USER'] > 0)) {
	if ($nar_systemsettings["SITE"]["FORUM_VB"]) {
		// vBulletin-Forum wird integriert
		require_once 'sys/lib.forum_vb.php';
		$apiForum = new ForumVB();
		if (!$apiForum->Login($usr["NAME"], $_POST["pass"])) {
			// Forum login failed!
			$usr = FALSE;
		}
	}
}

$leadLogin = false;

if ($usr && ($usr['STAT'] == 1)) {
	if ($_REQUEST['stay'] == 1) $stay = 1;

	$uid = (int)$usr['ID_USER'];
	$_SESSION['uid'] = $uid;

	$cookieContentHash = pass_encrypt($uid . $usr['PASS']);

	setcookie('ebizuid_' . session_name() . '_uid', $uid, ($stay ? strtotime('+1 year') : NULL), '/', $cookie_domain);
	setcookie('ebizuid_' . session_name() . '_hash', $cookieContentHash, ($stay ? strtotime('+1 year') : NULL), '/', $cookie_domain);

    /** @var Api_Plugins_Leads_Plugin $pluginLeads */
    $pluginLeads = Api_TraderApiHandler::getInstance($db)->getPlugin("Leads");
    $pluginLeads->login($usr["ID_USER"], ($_REQUEST["emp"] > 0 ? (int)$_REQUEST["emp"] : null));
} else {
    /** @var Api_Plugins_Leads_Plugin $pluginLeads */
    $pluginLeads = Api_TraderApiHandler::getInstance($db)->getPlugin("Leads");
    $leadLogin = $pluginLeads->loginLaravel($_POST["user"], $_POST["pass"]);
}

if ($usr) {
    if ($usr['STAT'] == 1) {
        $ident = $db->fetch_atom("select s.V1 from `moduloption` t
			left join string_opt s on s.S_TABLE='moduloption' and s.FK=t.ID_MODULOPTION
			and s.BF_LANG=if(t.BF_LANG_OPT & 128,128, 1 << floor(log(t.BF_LANG_OPT+0.5)/log(2)))
			where OPTION_VALUE = 'STARTPAGE'");
        $url = ($ident ? $tpl_dummy->tpl_uri_action($ident) : $tpl_dummy->tpl_uri_action($_REQUEST['forward'] . ",loggedin"));

        if ($ajax) {
            $_RESULT['msg']['ok'][] = 'login';
        }
        if (!empty($_REQUEST['redirect']) && !preg_match("/login/si", $_REQUEST['redirect'])) {
            $url = $_REQUEST['redirect'];
        } else {
            $tplBase = new Template('');
            switch ($_REQUEST['do']) {
                case 'adEdit':
                    $url = $tplBase->tpl_uri_action('my-marktplatz-neu,' . $_REQUEST['id']);
                    break;
                case 'artikelEdit':
                    $url = $tplBase->tpl_uri_action('artikel_edit,' . $_REQUEST['id_news']);
                    break;
            }
        }
    } else {
        $url = $tpl_dummy->tpl_uri_action((array_key_exists("forward", $_REQUEST) ? $_REQUEST['forward'] : 'login') . ",fail," . $usr['NAME'] . ",stat");
    }
    if ($usrAdminCheck) {
        $_SESSION['USER_IS_ADMIN'] = (int)$db->fetch_atom("SELECT count(*) FROM `role2user` ru JOIN `role` r ON r.ID_ROLE=ru.FK_ROLE AND FK_USER=" . $uid . " WHERE r.LABEL='Admin'");
    }
} else if ($leadLogin) {
	$url = $tpl_dummy->tpl_uri_baseurl("/leads/user/dashboard");
} else {
    if (!empty($_REQUEST['redirect'])) {
        setcookie("login_redirect", $_REQUEST['redirect']);
    }
	$url = $tpl_dummy->tpl_uri_action($_REQUEST['forward'] . ",fail," . $_POST['user']);
	if ($ajax) $_RESULT['msg']['err'][] = 'fail';
}
if (!$ajax) {
    forward($url);
} else {
    header("Content-Type: application/json");
    die(json_encode(array("success" => $authenticated, "url" => $url)));
}

?>