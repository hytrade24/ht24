<?
	$langval = &$GLOBALS['langval'];
	###pfad korrigiert by jura 04.02.08
	require_once($ab_path.'sys/lib.tutorial.php');
	$tutid = $_REQUEST["tutid"];
	$myTut = new tutorial('tutorial');
	$tpl_content->addvars( $myTut->getTut( $tutid ) );
	$tpl_content->addvars( $_REQUEST );
	### erst mal entfernt funktion unbekannt by jura 04.02.08
	//$messages = get_messages("TUTORIAL");
	//$tpl_content->addvar("message", $messages["STATUS_RESET"]);
?>