<?php
/* ###VERSIONSBLOCKINLCUDE### */

function setPacketType(&$row, $i) {
	global $db, $packets, $s_lang, $langval, $tax;
	$row["TYPE_".$row["TYPE"]] = 1;
	$array = $db->fetch_table("SELECT
			p.PRICE,
			(p.PRICE * (t.TAX_VALUE / 100 + 1)) as PRICE_BRUTTO,
			s.V1
		FROM `packet_price` p
		LEFT JOIN `packet` ON `packet`.ID_PACKET=p.FK_PACKET
		LEFT JOIN `tax` t ON t.ID_TAX=`packet`.FK_TAX
		LEFT JOIN `usergroup` u ON u.ID_USERGROUP=p.FK_USERGROUP
		LEFT JOIN `string_usergroup` s ON s.S_TABLE='usergroup' AND s.FK=u.ID_USERGROUP AND
			s.BF_LANG=if(u.BF_LANG_USERGROUP & ".$langval.", ".$langval.", 1 << floor(log(u.BF_LANG_USERGROUP+0.5)/log(2)))
		WHERE p.FK_PACKET=".(int)$row["ID_PACKET"]);
	$ar_liste = array();
	foreach($array as $i=>$ar_price) {
		$tpl_tmp = new Template("tpl/de/packet_components.row_price.htm");
		$tpl_tmp->addvars($ar_price);
		$tpl_tmp->addvar('i', $i);
		$tpl_tmp->addvar('even', 1-($i&1));
		$ar_liste[] = $tpl_tmp;
	}
	$row["preise"] = $ar_liste;
	$ar_liste = array();
	foreach($array as $i=>$ar_price) {
		$tpl_tmp = new Template("tpl/de/packet_components.row_price.htm");
		$ar_price["PRICE"] = $ar_price["PRICE_BRUTTO"];
		$tpl_tmp->addvars($ar_price);
		$tpl_tmp->addvar('i', $i);
		$tpl_tmp->addvar('even', 1-($i&1));
		$ar_liste[] = $tpl_tmp;
	}
	$row["preise_brutto"] = $ar_liste;
}

global $packets, $tax;

$SILENCE = false;

require_once $ab_path."sys/packet_management.php";
$packets = PacketManagement::getInstance($db);

$tax = $db->fetch1("SELECT * FROM `tax` WHERE ID_TAX=".$nar_systemsettings["MARKTPLATZ"]["TAX_DEFAULT"]);
$tpl_content->addvar("TAX_PERCENT", $tax["TAX_VALUE"]);

$curpage = (isset($_REQUEST["npage"]) ? $_REQUEST["npage"] : 1);
$perpage = (isset($_REQUEST["perpage"]) ? $_REQUEST["perpage"] : 20);

if (isset($_REQUEST["action"])) {
    $action = $_REQUEST["action"];
    $id_packet = $_REQUEST["id"];
    if ($action == "sort") {
        foreach($_REQUEST['F_ORDER'] as $key => $value) {
            $db->update("packet", array(
                'ID_PACKET' => (int)$key,
                'F_ORDER' => (int)$value
            ));
        }

        die(forward("index.php?page=packet_components&npage=".$curpage));
    }
}


$ar_where = array();
if (!isset($_REQUEST["all"])) {
	$ar_where = array("p.ID_PACKET IN (".implode(",", PacketManagement::getComponentTypes()).")");
}

$all = 0;
$ar_packets_base = $packets->getBaseList($curpage, $perpage, $all, $ar_where, array("F_ORDER ASC", "TYPE ASC"));
if (!empty($ar_packets_base)) {
	$tpl_content->addvar("pager", htm_browse($all, $curpage, "index.php?page=packet_components&npage=", $perpage));
	$tpl_content->addlist("liste_top", $ar_packets_base, "tpl/de/packet_components.row_top.htm", "setPacketType");
}

$ar_packets_components = $packets->getBaseList($curpage, $perpage, $all, array("(p.ID_PACKET <= 12 OR p.ID_PACKET = 211 OR p.ID_PACKET = 227)"), array("F_ORDER ASC", "TYPE ASC"));
if (!empty($ar_packets_components)) {
    $tpl_content->addvar("pager", htm_browse($all, $curpage, "index.php?page=packet_components&npage=", $perpage));
    $tpl_content->addlist("liste_base", $ar_packets_components, "tpl/de/packet_components.row.htm", "setPacketType");
}


?>