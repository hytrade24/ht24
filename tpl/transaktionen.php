<?php
/* ###VERSIONSBLOCKINLCUDE### */



$perpage = 10;
if(!isset($ar_params[1])) {
	$ar_params[1] = 1;
}

$limit = ($ar_params[1]*$perpage)-$perpage;

$all = $db->fetch_atom("SELECT count(*) FROM `transaction` WHERE FK_USER='".$uid."' AND `STATUS`!='default'");

$result2 = $db->querynow("SELECT `REASON_FOR_TRANSFER`, `NUMBER_OF_COINS`, `TYPE`, `STAMP`, `STATUS` FROM transaction WHERE FK_USER='".$uid."' AND `STATUS`!='default' ORDER BY `STAMP` DESC LIMIT ".$limit.", ".$perpage);
$transaction_array = array();
$counter = 0;
while ($row2 = mysql_fetch_assoc($result2['rsrc'])) {
	$transaction_array[$counter]['REASON_FOR_TRANSFER'] = $row2['REASON_FOR_TRANSFER'];
	$transaction_array[$counter]['NUMBER_OF_COINS'] 	= $row2['NUMBER_OF_COINS'];
	$transaction_array[$counter]['TYPE'] 				= ($row2['TYPE'] == 'in' ? 1 : 0);
	$transaction_array[$counter]['STAMP'] 				= $row2['STAMP'];
	$transaction_array[$counter]['STATUS'] 				= $row2['STATUS'];
	$counter++;
}

$tpl_content->addlist('list2', $transaction_array, 'tpl/de/transaktionen.row.htm');

$tpl_content->addvar("pager", htm_browse($all, $ar_params[1], "/transaktionen,", $perpage)); 
?>