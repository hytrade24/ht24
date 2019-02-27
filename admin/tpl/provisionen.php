<?php
/* ###VERSIONSBLOCKINLCUDE### */





if($_REQUEST['del']) {
	$db->delete('provsatz', (int)$_REQUEST['del']);
}

$liste = $db->fetch_table("
	select
		*
	from
		provsatz
	order by
		PRICE ASC
");
$tpl_content->addlist("provs", $liste, "tpl/de/provisionen.row.htm");

$ar_no = array();
for($i=0; $i<count($liste); $i++) {
	$ar_no[] = $liste[$i]['PSATZ'];
}

$tpl_content->addvar("use_prov", $nar_systemsettings['MARKTPLATZ']['USE_PROV']);
$ar_satz = array(
	'<option value="0">0%</option>',
);
for($i=1; $i<101; $i++) {
	$ar_satz[] = '<option style="texta-align:right;" value="'.$i.'" '.($i==$_POST['PSATZ'] ? ' selected' : '').'>'.$i.' %</option>';
}
$tpl_content->addvar("saetze", implode("\n", $ar_satz));

if(count($_POST)) {
	$err = false;
	$id = $db->fetch_atom("select ID_PROVSATZ from provsatz where PSATZ=".mysql_real_escape_string($_POST['PSATZ'])."
		or PRICE='".mysql_real_escape_string($_POST['PRICE'])."'");
	if($id > 0) {
		$err = true;
	}
	if(!$err) {
		$db->update("provsatz", $_POST);
		die(forward("index.php?page=provisionen&ok=1"));
	} else {
		$tpl_content->addvar('err', 1);
		$tpl_content->addvars($_POST);
	}
} elseif($_REQUEST['ok']) {
	$tpl_content->addvar("OK", 1);
}





?>