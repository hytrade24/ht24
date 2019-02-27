<?php
/* ###VERSIONSBLOCKINLCUDE### */



if (!empty($_POST['send'])) {
	$err = '';
	if (empty($_POST['FK_USER'])) {
		$err .= 'Bitte einen Empf&auml;nger ausw&auml;hlen!<br>';
	}
	if (empty($_POST['REASON_FOR_TRANSFER'])) {
		$err .= 'Bitte einen Verwendungszweck angeben!<br>';
	}
	if (empty($_POST['NUMBER_OF_COINS'])) {
		$err .= 'Bitte Anzahl Coins angeben!<br>';
	}
	if (!empty($_POST['NUMBER_OF_COINS']) && !preg_match("/^[0-9]{1,}$/", $_POST['NUMBER_OF_COINS'])) {
		$err .= 'Die Anzahl Coins muss eine Ganzzahl sein!<br>';
	}
	if (empty($_POST['TYPE'])) {
		$err .= 'Bitte einen Typ ausw&auml;hlen!<br>';
	}
	$tpl_content->addvar('err', $err);
}

if (empty($err) && !empty($_POST['send'])) {
	$db->querynow("INSERT INTO `transaction` (`ID_TRANSACT`, `FK_USER`, `NUMBER_OF_COINS`, `REASON_FOR_TRANSFER`, `TYPE`, `STAMP`, `STATUS`)
				   VALUES ('', '".mysql_escape_string($_POST['FK_USER'])."',
				   '".mysql_escape_string($_POST['NUMBER_OF_COINS'])."',
				   '".mysql_escape_string($_POST['REASON_FOR_TRANSFER'])."',
				   '".mysql_escape_string($_POST['TYPE'])."',
				   '".mysql_escape_string($_POST['_y'].'.'.$_POST['_m'].'.'.$_POST['_d'].' '.$_POST['_h'].':'.$_POST['_i'].':'.$_POST['_s'])."', 'Completed')");
	forward('index.php?page=transaction_overview');
} else {

	$result = $db->querynow("SELECT `ID_USER`, `NAME` FROM `user` ORDER BY `NAME` ASC");
	$user_list = '';
	while ($row = mysql_fetch_assoc($result['rsrc'])) {
		$user_list .= "<option value=\"".$row['ID_USER']."\">".$row['NAME']."</option>\n";
	}
	$tpl_content->addvar('USER_LIST', $user_list);
}
?>