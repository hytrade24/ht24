<?php
/* ###VERSIONSBLOCKINLCUDE### */
function setBilling(&$row, $i) {
	global $db, $langval, $packets, $tpl_content, $s_lang;
	if ($row["TYPE"] == "MEMBERSHIP") {
		$id_usergroup = (int)$db->fetch_atom("SELECT PARAMS FROM `packet_collection` WHERE ID_PACKET=".$row["ID_PACKET"]." AND
			FK_PACKET IN (".PacketManagement::getType("usergroup_once").", ".PacketManagement::getType("usergroup_abo").")");
		if ($id_usergroup > 0) {
			$row["USERGROUP"] = $db->fetch_atom(
				"SELECT V1 FROM `usergroup` u
				INNER JOIN `string_usergroup` s ON s.S_TABLE='usergroup' AND s.FK=u.ID_USERGROUP AND
					s.BF_LANG=if(u.BF_LANG_USERGROUP & ".$langval.", ".$langval.", 1 << floor(log(u.BF_LANG_USERGROUP+0.5)/log(2)))
				WHERE ID_USERGROUP=".$id_usergroup);
		}
		$row["PACKETS_TEXT"] = $packets->getCollectionContent($row["ID_PACKET"]);
	}
	if (is_array($row["RUNTIMES"])) {
        $usergroup_default = array();
        foreach ($row["RUNTIMES"] as $runtimeIndex => $arRuntime) {
            foreach ($GLOBALS['ar_default_packets'] as $id_usergroup => $id_packet_runtime) {
                if ($arRuntime["ID_PACKET_RUNTIME"] == $id_packet_runtime) {
                    $arUsergroup = $db->fetch1(
                        "SELECT V1, u.* FROM `usergroup` u
                        INNER JOIN `string_usergroup` s ON s.S_TABLE='usergroup' AND s.FK=u.ID_USERGROUP AND
                            s.BF_LANG=if(u.BF_LANG_USERGROUP & ".$langval.", ".$langval.", 1 << floor(log(u.BF_LANG_USERGROUP+0.5)/log(2)))
				        WHERE ID_USERGROUP=".$id_usergroup);
                    $tplUsergroup = new Template("tpl/".$s_lang."/packet_membership.default_row.htm");
                    $tplUsergroup->addvars($arUsergroup);
                    $usergroup_default[] = $tplUsergroup;
                }
            }
        }
        $row["DEFAULT"] = $usergroup_default;

		$ar_liste_runtimes = array();
		$ar_liste_users = array();
		foreach ($row["RUNTIMES"] as $index => $ar_row) {
            $ar_row["CYCLE_".$ar_row["BILLING_CYCLE"]] = 1;
            //Anzahl der buchungen
            $ar_row['USERCOUNT']= (int)$db->fetch_atom("SELECT count(FK_PACKET_RUNTIME) FROM user WHERE FK_PACKET_RUNTIME=".$ar_row["ID_PACKET_RUNTIME"]);

            // Laufzeiten
	        $tpl_tmp_runtimes = new Template("tpl/de/packets.row_runtime.htm", $tpl_content->table);
	        $tpl_tmp_runtimes->addvars($ar_row);
	        $tpl_tmp_runtimes->addvar('i', $index);
	        $tpl_tmp_runtimes->addvar('even', 1-($index & 1));
	        $ar_liste_runtimes[] = $tpl_tmp_runtimes;
            // Benutzer mit dieser Mitgliedschaft
	        $tpl_tmp_users = new Template("tpl/de/packets.row_user.htm", $tpl_content->table);
	        $tpl_tmp_users->addvars($ar_row);
	        $tpl_tmp_users->addvar('i', $index);
	        $tpl_tmp_users->addvar('even', 1-($index & 1));
	        $ar_liste_users[] = $tpl_tmp_users;
		}
		$row["RUNTIMES"] = $ar_liste_runtimes;
		$row["MEMBERS"] = $ar_liste_users;
	} else {
		$row["RUNTIMES"] = false;
		$row["MEMBERS"] = false;
	}
	$row["TYPE_".$row["TYPE"]] = 1;
	$row["BILLING_".$row["BILLING_CYCLE"]] = 1;
}

global $packets;

//$SILENCE = false;

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
			die(forward("index.php?page=packet_membership"));
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

		die(forward("index.php?page=packet_membership&npage=".$curpage));
	}
}

$GLOBALS['ar_default_packets'] = $db->fetch_nar("SELECT ID_USERGROUP, FK_PACKET_RUNTIME_DEFAULT FROM `usergroup` WHERE FK_PACKET_RUNTIME_DEFAULT IS NOT NULL");

$all = 0;
$ar_packets = $packets->getList($curpage, $perpage, $all, array("TYPE='MEMBERSHIP'"), array("F_ORDER ASC", "TYPE ASC", "V1 ASC"));

if (!empty($ar_packets)) {
	$tpl_content->addvar("pager", htm_browse($all, $curpage, "index.php?page=packet_membership&npage=", $perpage));
	$tpl_content->addlist("liste", $ar_packets, "tpl/de/packet_membership.row.htm", "setBilling");
}
$tpl_content->addvar("curpage", $curpage);

?>