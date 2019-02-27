<?php
/* ###VERSIONSBLOCKINLCUDE### */


if (count($_POST)) {
	$err = array();
	$tpl_content->addvars($_POST);

	if (empty($_POST['DESCRIPTION'])) $err[] = "Bitte System- Namen angeben";

	if (count($err)) {
		$tpl_content->addvar("err", implode("<br />", $err));
	} else {
		$id = $db->update("mailvorlage_notification_group", $_POST);

		$tpl_content->addvar("ok", 1);
		if (empty($_POST['ID_MAILVORLAGE'])) $tpl_content->addvar("ID_MAILVORLAGE_NOTIFICATION_GROUP", $id);
	}
} else {
	if (!empty($_REQUEST['ID_MAILVORLAGE_NOTIFICATION_GROUP'])) {

		$ar = $db->fetch1("
			SELECT
				g.*
			FROM mailvorlage_notification_group g
			WHERE
				g.ID_MAILVORLAGE_NOTIFICATION_GROUP = '".(int)$_REQUEST['ID_MAILVORLAGE_NOTIFICATION_GROUP']."'
		");
		$tpl_content->addvars($ar);
	}
}

?>
