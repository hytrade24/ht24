<?php
/* ###VERSIONSBLOCKINLCUDE### */



$id = (int)$_REQUEST['ID_MAN'];
if($id)
{
	$ar = $db->fetch1("
		SELECT
			*
		FROM
			manufacturers
		WHERE
			ID_MAN=".$id);
	$ar['CONFIRMED'] = ($ar['CONFIRMED'] ? 0 : 1);
	$db->querynow("
		UPDATE
			manufacturers
		SET
			CONFIRMED = ".$ar['CONFIRMED']."
		WHERE
			ID_MAN=".$id);
	$tpl_content->addvars($ar);
}

?>