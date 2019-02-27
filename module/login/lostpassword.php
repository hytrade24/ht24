<?php
/* ###VERSIONSBLOCKINLCUDE### */


$tpl_modul->addvar('lost', 1);
if (count($_POST)) {
    $foundUser = array();
    if($_POST['USER'] != "") {
        $foundUser = $db->fetch1("select * from user where NAME='" . mysql_real_escape_string($_POST['USER']) . "'");
    } elseif($_POST['EMAIL'] != "") {
        $foundUser = $db->fetch1("select * from user where EMAIL='" . mysql_real_escape_string($_POST['EMAIL']) . "'");
    }

    if (empty($foundUser)) {
        $tpl_modul->addvar("notfound", 1);
        $tpl_modul->addvar("USER", $_POST['USER']);
        $tpl_modul->addvar("EMAIL", $_POST['EMAIL']);
    } else {
        $data = array_merge($foundUser, $_POST);
        $data['CODE'] = md5($foundUser['ID_USER'].';'.$foundUser['PASS']);

        sendMailTemplateToUser(0, $foundUser['ID_USER'], 'LOSTPASSWORD_CONFIRM', $data);

        forward('login,lostpassword,2.htm');
    }
}
if ($ar_params[2] == 1) {
    $tpl_modul->addvar("psw_sent", 1);
} if ($ar_params[2] == 2) {
    $tpl_modul->addvar("psw_confirm", 1);
} elseif ($ar_params[2] == 'confirm' && (int)$ar_params[3] > 0 && $ar_params[4] != "") {
    $userId = (int)$ar_params[3];
    $code = $ar_params[4];

    $foundUser = $db->fetch1("select * from user where ID_USER = '".$userId."'");
    if($code === md5($foundUser['ID_USER'].';'.$foundUser['PASS'])) {
        $newSalt = pass_generate_salt();
        $newPassword = substr(md5(microtime(true)), 10, 8);
        $newPasswordHash = pass_encrypt($newPassword, $newSalt);
        $db->update('user', array('ID_USER' => $foundUser['ID_USER'], 'PASS' => $newPasswordHash, 'SALT' => $newSalt));

		if ($nar_systemsettings["SITE"]["FORUM_VB"] && $foundUser['VB_USER'] > 0) {

			$id_vb_user = $db->fetch_atom("SELECT VB_USER FROM `user` WHERE ID_USER=".(int)$foundUser['ID_USER']);
			if ($id_vb_user > 0) {

				// vBulletin-Forum wird integriert
				require_once $ab_path.'sys/lib.forum_vb.php';
				$apiForum = new ForumVB();
				// Update password
				$apiForum->SetUserPassword($id_vb_user, $newPassword);
			}
		}

        $data['PASS'] = $newPassword;
        $data = array_merge($foundUser, $data);

        sendMailTemplateToUser(0, $foundUser['ID_USER'], 'LOSTPASSWORD', $data);

        forward('login,lostpassword,1.htm');

    } else {
        $tpl_modul->addvar("error", 1);
    }
}
?>
