<?php
/* ###VERSIONSBLOCKINLCUDE### */



if (!empty($_GET['delete'])) {
	$db->querynow("DELETE FROM `coinpackage` WHERE `ID_COINPACKAGE`='".mysql_escape_string($_GET['delete'])."'");
}

$result = $db->querynow("SELECT `ID_COINPACKAGE`, `COINPACKAGE_TITLE`, `NUMBER_OF_COINS`, `COST`
							FROM `coinpackage` ORDER BY `COINPACKAGE_TITLE` ASC");

$coinpackage_array = array();
$counter = 0;
while ($row = mysql_fetch_assoc($result['rsrc'])) {
	$coinpackage_array[$counter]['ID_COINPACKAGE'] 		= $row['ID_COINPACKAGE'];
	$coinpackage_array[$counter]['COINPACKAGE_TITLE'] 	= $row['COINPACKAGE_TITLE'];
	$coinpackage_array[$counter]['NUMBER_OF_COINS'] 	= $row['NUMBER_OF_COINS'];
	$coinpackage_array[$counter]['COST'] 				= number_format($row['COST'], 2, ',', '.');
	$coinpackage_array[$counter]['COST_BRUTTO'] 		= number_format($row['COST']*$nar_systemsettings['COINS']['TAX']/100+$row['COST'], 2, ',', '.');
	$counter++;
}

$tpl_content->addlist('list', $coinpackage_array, 'tpl/de/coinpackages.row.htm');

?>