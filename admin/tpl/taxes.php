<?php
/* ###VERSIONSBLOCKINLCUDE### */



if($_REQUEST['del']) {
	$query = "DELETE FROM
		tax
	WHERE
		ID_TAX=".(int)$_REQUEST['del'];
	$res = $db->querynow($query);
	if($res['int_result'] > 0) {
		die(forward('index.php?page=taxes&deleted=1'));
	}
}

if(count($_POST)) {
	foreach($_POST['TAX_VALUE'] as $id_tax => $value) {
		$ar = array(
			'ID_TAX' => (int)$id_tax,
			'TAX_VALUE' => (double)preg_replace('/[^0-9\.]/si', '', str_replace(",", ".", $value)),
			'TXT' => $_POST['TXT'][$id_tax],
		);
		$db->update('tax', $ar);
	}
	if(!empty($_POST['TXT_NEU']) || !empty($_POST['TAX_VALUE_NEU'])) {
		$ar = array(
            'TAX_VALUE' => (double)preg_replace('/[^0-9\.]/si', '', str_replace(",", ".", $_POST['TAX_VALUE_NEU'])),
			'TXT' => $_POST['TXT_NEU'],
		);
		$db->update('tax', $ar);
	}
	die(forward('index.php?page=taxes&ok=1'));
}

if($_REQUEST['ok']) {
	$tpl_content->addvar("ok", 1);
}

if($_REQUEST['deleted']) {
	$tpl_content->addvar("deleted", 1);
}

$liste = $db->fetch_table("
	SELECT
		*
	FROM
		tax
	ORDER BY
		TAX_VALUE ASC");
$tpl_content->addlist("liste", $liste, 'tpl/de/taxes.row.htm');

?>