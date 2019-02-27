<?php
/* ###VERSIONSBLOCKINLCUDE### */



$tpl_content->addvar('IP', $_SERVER['REMOTE_ADDR']);
$tpl_content->addvar("ID_AD", (int)$_REQUEST['ID_AD']);

if(count($_POST)) {
    $err = array();
    if (!$uid) {
        if (empty($_POST['NAME'])) {
            $err[] = 'user_name';
        }
        if (empty($_POST['EMAIL'])) {
            $err[] = 'user_email';
        }
    }
    if (empty($_POST['GRUND'])) {
        $err[] = 'reason';
    }
    if (!secure_question($_POST)) {
        $err[] = 'secQuestion';
    }
	if(!empty($err)) {
        $tpl_content->addvar("err", 1);
        foreach ($err as $errIdent) {
            $tpl_content->addvar("err_".$errIdent, 1);
        }
        $tpl_content->addvars($_POST);
	} else {
		$_POST['GRUND'] = $_POST['GRUND'];
		$ar_ad = $db->fetch1("
			SELECT
				ad_master.PRODUKTNAME AS TITEL,
				ad_master.ID_AD_MASTER AS ID_AD,
				ad_master.FK_USER AS FK_SELLER,
				ad_master.FK_MAN,
				user.NAME
			FROM
				ad_master
			LEFT JOIN
				user ON ad_master.FK_USER=user.ID_USER
			WHERE
				ad_master.ID_AD_MASTER=".(int)$_REQUEST['ID_AD']);

		$productManufacturer = ((int)$ar_ad["FK_MAN"] > 0 ? $db->fetch_atom("SELECT NAME FROM `manufacturers` WHERE ID_MAN=".(int)$ar_ad["FK_MAN"]) : "");


		$_POST = array_merge($_POST, $ar_ad);
		sendMailTemplateToUser(0, $_POST['FK_SELLER'], 'anzeige_melden', $_POST);

		sendMailTemplateToUser(0, 0, 'ADMIN_NEW_TASK', array(
			'TASK_VERSTOSS' => 1,
			'AD_NAME' => $productManufacturer.' '.$ar_ad['TITEL'],
			'SELLER_NAME' => $ar_ad['NAME']
		));


		$ar_insert = array(
			'FK_AD' => $_POST['ID_AD'],
			'FK_USER' => (int)$uid,
			'STAMP' => date('Y-m-d H:i'),
			'GRUND' => $_POST['GRUND'],
			'IP' => $_SERVER['REMOTE_ADDR'],
			'NAME' => $_POST['NAME'],
			'EMAIL' => $_POST['EMAIL']
		);
		$SILENCE=false;
		$db->update("verstoss", $ar_insert);

		$tpl_content->addvar("SENT", 1);

	}
}
?>