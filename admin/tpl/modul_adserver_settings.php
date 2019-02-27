<?php
/* ###VERSIONSBLOCKINLCUDE### */


#$tpl_content->table = 'user';
if (count($_POST))
{
	$db->querynow ("update modul set B_VIS=".$_POST['B_VIS']." where IDENT='adserver'");
    forward('index.php?nav='. $nav, 2);
}
$B_VIS = $db->fetch_atom("select B_VIS from modul where IDENT='adserver'");
$tpl_content->addvar('B_VIS',$B_VIS);
?>