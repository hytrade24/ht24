<?php
/* ###VERSIONSBLOCKINLCUDE### */

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
require_once 'api.php';

if ($uid = $_SESSION['uid']) {
	$db->querynow("delete from locks where FK_USER=$uid");

	$is_vb_user = ($db->fetch_atom("SELECT VB_USER FROM `user` WHERE ID_USER=" . (int)$uid) > 0 ? TRUE : FALSE);
	if ($is_vb_user) {
		if ($nar_systemsettings["SITE"]["FORUM_VB"]) {
			// vBulletin-Forum wird integriert
			require_once 'sys/lib.forum_vb.php';
			$apiForum = new ForumVB();
			$apiForum->Logout();
		}
	}
}

setcookie('ebizuid_' . session_name() . '_uid', FALSE, time() - 86400, '/', $cookie_domain);
setcookie('ebizuid_' . session_name() . '_hash', FALSE, time() - 86400, '/', $cookie_domain);

session_destroy();

/** @var Api_Plugins_Leads_Plugin $pluginLeads */
$pluginLeads = Api_TraderApiHandler::getInstance($db)->getPlugin("Leads");
$pluginLeads->logout();

$uid = 0;
if (!($s_url = $_REQUEST['forward'])) $s_url = 'index.htm';
forward($s_url, 1, 'top');
?>