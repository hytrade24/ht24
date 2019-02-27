<?php
/* ###VERSIONSBLOCKINLCUDE### */



global $ab_path, $langval;
require_once $ab_path.'sys/lib.chat.php';
require_once $ab_path.'sys/lib.chat.messages.php';

$chatManagement = ChatManagement::getInstance($db);
$chatMessagesManagement = ChatMessageManagement::getInstance($db);

if ($_REQUEST['saved'] == 1) {
	$tpl_content->addvar('saved', $_REQUEST['saved']);
}

if(isset($_GET['DO'])) {
    if($_GET['DO'] == 'load' && isset($_REQUEST['ID_CHAT_MESSAGE'])) {
		$tpl_content->addvars( $chatMessagesManagement->find($_REQUEST['ID_CHAT_MESSAGE']) );
    }
    if ($_GET['DO'] == 'save') {
    	if (isset($_REQUEST['ID_CHAT_MESSAGE'])) {
            $_REQUEST['MESSAGE'] = $_REQUEST['MESSAGE'];
            $chatMessagesManagement->updateById($_REQUEST['ID_CHAT_MESSAGE'], $_REQUEST);
			header('Content-type: application/json');
			die(json_encode(array("success" => true)));
    	} else {
			die(json_encode(array("success" => false)));
    	}
    }
}

?>