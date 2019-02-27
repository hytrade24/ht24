<?php

if($_REQUEST['do'] == "del") {
	$db->delete("mailvorlage_notification_group", $_REQUEST['ID_MAILVORLAGE_NOTIFICATION_GROUP']);
}

 $liste = $db->fetch_table("
	SELECT
	 	g.*,
	 	(SELECT COUNT(*) FROM mailvorlage m WHERE m.FK_MAILVORLAGE_NOTIFICATION_GROUP = g.ID_MAILVORLAGE_NOTIFICATION_GROUP) as ANZ_MAIL
	FROM mailvorlage_notification_group g
	ORDER BY g.DESCRIPTION
");

$tpl_content->addlist("liste", $liste, "tpl/de/emailvorlagen_notification_group.row.htm");

?>
