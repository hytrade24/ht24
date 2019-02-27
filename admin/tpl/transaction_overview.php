<?php
/* ###VERSIONSBLOCKINLCUDE### */



// aufraeumen nie abgeschlossener transaktionen
$db->querynow("DELETE FROM `transaction` WHERE `STAMP`<= CURDATE() - INTERVAL 40 DAY AND `STATUS`!='Completed'");
$db->querynow("DELETE FROM `purchase` WHERE `STAMP`<= CURDATE() - INTERVAL 40 DAY AND `STATUS`!='Completed'");

$perpage = 20;
if(!isset($_REQUEST['npage'])) {
	$_REQUEST['npage'] = 1;
}

$limit = ($_REQUEST['npage']*$perpage)-$perpage;

$all = $db->fetch_atom("SELECT count(*) FROM `transaction`");

$result = $db->querynow("SELECT t1.`ID_TRANSACT`, t1.`FK_USER`, t1.`NUMBER_OF_COINS`, t1.`REASON_FOR_TRANSFER`, t1.`TYPE`, t1.`STAMP`, t1.`STATUS`, t1.`PAYPAL_ID`, t2.`NAME`
						FROM `transaction` t1
						LEFT JOIN `user` t2 ON (t2.ID_USER=t1.FK_USER)
						ORDER BY t1.`STAMP` DESC 
						LIMIT ".$limit.", ".$perpage);

$transaction_array = array();
$counter = 0;
while ($row = mysql_fetch_assoc($result['rsrc'])) {
	$transaction_array[$counter]['ID_TRANSACT'] 		= $row['ID_TRANSACT'];
	$transaction_array[$counter]['USER'] 				= $row['NAME'];
	$transaction_array[$counter]['USER_ID'] 			= $row['FK_USER'];
	$transaction_array[$counter]['NUMBER_OF_COINS'] 	= $row['NUMBER_OF_COINS'];
	$transaction_array[$counter]['REASON_FOR_TRANSFER'] = $row['REASON_FOR_TRANSFER'];
	$transaction_array[$counter]['TYPE'] 				= ($row['TYPE'] == 'in' ? 1 : 0);;
	$transaction_array[$counter]['STAMP'] 				= $row['STAMP'];
	$transaction_array[$counter]['STATUS'] 				= $row['STATUS'];
	$transaction_array[$counter]['PAYPAL_ID'] 			= $row['PAYPAL_ID'];
	$counter++;
}

$tpl_content->addlist('list', $transaction_array, 'tpl/de/transaction_overview.row.htm');

$tpl_content->addvar("pager", htm_browse($all, $_REQUEST['npage'], "index.php?page=transaction_overview&npage=", $perpage)); 
?>