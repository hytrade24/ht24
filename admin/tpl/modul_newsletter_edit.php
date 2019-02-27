<?php
/* ###VERSIONSBLOCKINLCUDE### */


function addUrl2img($param) {
	$s_path = $GLOBALS['nar_systemsettings']['SITE']['SITEURL'];
	$param = str_replace('href="/', 'href="'.$s_path.'/', $param);
	$param = str_replace('src="/', 'src="'.$s_path.'/', $param);
	return $param;
	#return preg_replace("/(img)(.*?)(src=\")([^\"]*)(\")/sie", "'$1$2$3'. (0===strpos('$4', '$s_path/') ? '' : '$s_path/'). '$4$5$6'", $param);
}

if(count($_POST)) {
	$err = array();
	$_POST['T1']=addUrl2img($_POST['T1']);
	$tpl_content->addvars($_POST);
	if(!$_POST['V1']) {
		$err[] = "Bitte geben Sie einen Betreff ein";
	}
	if(!$_POST['T1']) {
		$err[] = "Bitte geben Sie einen Mailtext ein";
	}
	if(count($err)) {
		$tpl_content->addvar("err", implode("<br />", $err));
	}
	else {
		if(!$_POST['STAMP']) {
			$_POST['STAMP'] = date('Y-m-d H:i');
		}
		$id = $db->update("nl", $_POST);
		if(!$_POST['ID_NL']) {
			if($_POST['VERSAND']==1) {
				$todo = $db->fetch_atom("select count(*) from nl_recp
	     			where CODE IS NULL");
			} else {
				$todo = $db->fetch_atom("select count(*) from user");
			}
			$db->update("nl_log", array_merge($_POST, array(
				"FK_NL" => $id,
				"NLDATUM" => date('Y-m-d'),
        		"TODO" => $todo)));
		}
		forward("index.php?page=modul_newsletter_sendeberichte");
	}
}

elseif (isset($_REQUEST['ID_NL']))
{
	$tpl_content->addvars($db->fetch1($db->lang_select("nl")." where ID_NL=".$_REQUEST['ID_NL']));
}

?>
