<?php
/* ###VERSIONSBLOCKINLCUDE### */



include_once "sys/lib.nestedsets.php";
include_once "sys/lib.shop_kategorien.php";

function hide_childs(&$row) {
    global $id_kat, $id_root_kat, $kat, $show_paid;
    $row["kids"] = $kat->element_has_childs($row["ID_KAT"]);
    if ($row["PARENT"] != $id_kat) {
      $row["HIDDEN"] = 1;
    }
    if (!$show_paid && !$row["B_FREE"]) {
      $row["REMOVED"] = 1;
    }
    $row["KAT_ROOT"] = $id_root_kat;
}

global $show_paid, $id_root_kat, $exclusiveTable, $kat;
$show_paid = 0;

if(isset($_GET['exclusiveTable'])) { $exclusiveTable = $_GET['exclusiveTable']; } else { $exlusiveTable = null; }

function diplayChild(&$row) {
	global $exclusiveTable;

	hide_childs($row);
	if(($exclusiveTable) != null && ($row['KAT_TABLE'] != $exclusiveTable)) {
		$row["REMOVED"] = 1;
	}

}


if (($_REQUEST["mode"] == "ajax") && ($_REQUEST["page"] == "my-marktplatz-quickedit-kat")) {
  	$ajax_page = $_REQUEST["do"];

  	if ($ajax_page == "kats") {
  		$show_paid = ($_REQUEST["paid"] ? 1 : 0);
	    $kat = new TreeCategories("kat", 1);
	    $id_kat = ($_REQUEST["root"] ? $_REQUEST["root"] : $kat->tree_get_parent());
	    $id_root_kat = $kat->tree_get_parent($id_kat);
		$ar_kat = $kat->element_read($id_kat);
	    $html_baum = "";
	    if (($id_root_kat > 0) && ($id_kat != $id_root_kat)) {
	    	$ar_root = $kat->element_read($id_root_kat);
			//$tpl_content->addvar("NAME_ROOT", $ar_root["V1"]);
    		//if (parseInt(result.root) > 0) {
    		//}
    		$html_baum = '<li id="kat_back_to_root">'.
    			'	<a href="javascript:UpdateKatSelectorQuickedit('.$id_root_kat.', \''.stdHtmlspecialchars($ar_root["V1"]).'\', -1, \''.$ar_kat["KAT_TABLE"].'\');">'.
    			'		Zur&uuml;ck zu '.stdHtmlspecialchars($ar_root["V1"]).
    			'	</a>'.
    			'</li>';
	    }
	    $ar_tree = $kat->element_get_childs($id_kat);

	    $tpl_content->addvar("kat_table", $ar_kat["KAT_TABLE"]);
		$tpl_content->addlist("baum", $ar_tree, "tpl/".$s_lang."/my-marktplatz-quickedit-kat.row.htm", 'diplayChild');

	    $html_baum .= $tpl_content->process_text("{baum}");

  		header('Content-type: application/json');
	    die(json_encode(array(
	    	"root"	=> (empty($_REQUEST["root"]) ? 0 : $_REQUEST["root"]),
	    	"tree"	=> ($html_baum)
	    )));
  	}
} else {
  	// Subtpl Aufruf
	$tpl_article = $tpl_main->vars["content"];

	$show_paid = 1; // ($tpl_content->vars["PAID"] ? 1 : 0);
	$kat = new TreeCategories("kat", 1);
	$id_kat = ($tpl_article->vars["FK_KAT"] > 0 ? (int)$tpl_article->vars["FK_KAT"] : $kat->tree_get_parent());
	$id_root_kat = $kat->tree_get_parent($id_kat);
	$ar_kat = $kat->element_read($id_kat);
	$html_baum = "";
	if (($id_root_kat > 0) && ($id_kat != $id_root_kat)) {
		$ar_root = $kat->element_read($id_root_kat);
		$tpl_content->addvar("kat_head", '<li id="kat_back_to_root">'.
			'	<a href="javascript:UpdateKatSelectorQuickedit('.$id_root_kat.', \''.stdHtmlspecialchars($ar_root["V1"]).'\', -1, \''.$ar_kat["KAT_TABLE"].'\');">'.
			'		Zur&uuml;ck zu '.stdHtmlspecialchars($ar_root["V1"]).
			'	</a>'.
			'</li>');
	}
	$ar_tree = $kat->element_get_childs($id_kat);

	$tpl_content->addvar("kat_current", ($id_kat > 1 ? $ar_kat["V1"] : "---") );
	$tpl_content->addvar("kat_table", $ar_kat["KAT_TABLE"]);
	$tpl_content->addlist("baum", $ar_tree, "tpl/".$s_lang."/my-marktplatz-quickedit-kat.row.htm", 'diplayChild');
}