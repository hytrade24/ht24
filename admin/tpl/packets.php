<?php
/* ###VERSIONSBLOCKINLCUDE### */
function setBilling(&$row, $i) {
	global $tpl_content, $s_lang, $packets;
	if ($row["TYPE"] == "COLLECTION") {
		$row["PACKETS_TEXT"] = $packets->getCollectionContent($row["ID_PACKET"]);
	}
	if (is_array($row["RUNTIMES"])) {
		$ar_liste = array();
		foreach ($row["RUNTIMES"] as $index => $ar_row) {
			$ar_row["CYCLE_".$ar_row["BILLING_CYCLE"]] = 1;
	        $tpl_tmp = new Template("tpl/de/packets.row_runtime.htm", $tpl_content->table);
	        $tpl_tmp->addvars($ar_row);
	        $tpl_tmp->addvar('i', $index);
	        $tpl_tmp->addvar('even', 1-($index & 1));
	        $ar_liste[] = $tpl_tmp;
		}
		$row["RUNTIMES"] = $ar_liste;
	} else {
		$row["RUNTIMES"] = false;
	}
	$row["TYPE_".$row["TYPE"]] = 1;
}

global $packets;

require_once $ab_path."sys/packet_management.php";
$packets = PacketManagement::getInstance($db);

$curpage = (isset($_REQUEST["npage"]) ? $_REQUEST["npage"] : 1);
$perpage = (isset($_REQUEST["perpage"]) ? $_REQUEST["perpage"] : 10);

if (isset($_REQUEST["action"])) {
	$action = $_REQUEST["action"];
	$id_packet = $_REQUEST["id"];
	if ($action == "del") {
		// Paket löschen
		if ($packets->delete($id_packet)) {
			die(forward("index.php?page=packets"));
		} else {
			$tpl_content->addvar("error_delete", 1);
		}
	} elseif ($action == "sort") {
		foreach($_REQUEST['F_ORDER'] as $key => $value) {
			$db->update("packet", array(
				'ID_PACKET' => (int)$key,
				'F_ORDER' => (int)$value
			));
		}

		die(forward("index.php?page=packets&npage=".$curpage));
	}
}

$all = 0;
$ar_packets = $packets->getCollectionList($curpage, $perpage, $all, array(), array("F_ORDER ASC", "TYPE ASC", "V1 ASC"));
if (!empty($ar_packets)) {
	$tpl_content->addvar("pager", htm_browse($all, $curpage, "index.php?page=packets&npage=", $perpage));
	$tpl_content->addlist("liste", $ar_packets, "tpl/de/packets.row.htm", "setBilling");
}
$tpl_content->addvar("curpage", $curpage);

?>