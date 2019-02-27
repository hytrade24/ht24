<?
###pfad korrigiert by jura 04.02.08
	require_once($ab_path.'admin/sys/lib.tutorial.php');
	$tutid = $_REQUEST["tutid"];
	if (!isset($tutid)) {
		die("Ohne ID geht nix!");
	}
	$myTut = new tutorial('string_tutorial');
	$current = $myTut->getTut($tutid);
	$tpl_content->addvar("topic", $current["V1"]);
	$tpl_content->addvar("descr", $current["V2"]);
	$tpl_content->addvar("text", $myTut->translate($current["T1"],$tutid));

	// Für die Kommentare
	  $contentId = $tutid;
	  $s_mode = 'list';
	  $s_table = "tutorial";
//	$tpl_content->addvar("root", $ab_path);
//	$tpl_content->addvar("root", "/");
	
	
?>
