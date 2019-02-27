<?php
/* ###VERSIONSBLOCKINLCUDE### */

function addUsergroups(&$row, $i) {
	global $db, $s_lang, $tpl_content, $nar_systemsettings;

	$ar_usergroups = array_keys($db->fetch_nar("SELECT PARAMS
		FROM `packet_collection` WHERE ID_PACKET=".$row["ID_PACKET"]." AND
			FK_PACKET IN (".PacketManagement::getType("usergroup_once").", ".PacketManagement::getType("usergroup_abo").")"));
	$row["RECURRING_".$row["RECURRING"]] = 1;
	$row["FK_USERGROUP"] = $ar_usergroups['0'];
	$row["IS_FREE"] = 1;

	if (is_array($row["RUNTIMES"])) {
		$ar_liste = array();
		foreach ($row["RUNTIMES"] as $index => $ar_row) {
			if ($ar_row["BILLING_PRICE"] > 0) {
					$row["IS_FREE"] = 0;
			}
			$ar_row["RUNTIME_NUM"] = $ar_row["BILLING_FACTOR"] * $ar_row["RUNTIME_FACTOR"];
			$ar_row["CYCLE_".$ar_row["BILLING_CYCLE"]] = 1;
			$tpl_tmp = new Template("tpl/".$s_lang."/preise.row_runtime.htm", $tpl_content->table);
			$tpl_tmp->addvar('CURRENCY_DEFAULT', $nar_systemsettings['MARKTPLATZ']['CURRENCY']);
			$tpl_tmp->addvar('FK_PACKET_RUNTIME', $_POST['FK_PACKET_RUNTIME']);
			$tpl_tmp->addvars($ar_row);
			$tpl_tmp->addvar('i', $index);
			$tpl_tmp->addvar('even', 1-($index & 1));
			$ar_liste[] = $tpl_tmp;
		}
		$row["RUNTIMES"] = $ar_liste;
	} else {
		$row["RUNTIMES"] = false;
	}
}

function setContentText(&$row, $i) {
	global $packets, $db, $s_lang, $tpl_content, $nar_systemsettings;
	if ($row["TYPE"] == "COLLECTION") {
		$row["PACKETS_TEXT"] = $packets->getCollectionContent($row["ID_PACKET"]);
	}
	$row["TYPE_".$row["TYPE"]] = 1;
	$row["RECURRING_".$row["RECURRING"]] = 1;
	$tax = $db->fetch1("SELECT * FROM `tax` WHERE ID_TAX=".$row["FK_TAX"]);
	// Steuern in %
	$row["TAX_PERCENT"] = $tax["TAX_VALUE"];
	// Preis mit Steuer
	$row["PRICE"] = $row["BILLING_PRICE"];
	$row["PRICE_BRUTTO"] = ($row["BILLING_PRICE"] * (1 + $tax["TAX_VALUE"] / 100));
	// Laufzeiten
	if (is_array($row["RUNTIMES"])) {
		$ar_liste = array();
		foreach ($row["RUNTIMES"] as $index => $ar_row) {
			$ar_row["RUNTIME_NUM"] = $ar_row["BILLING_FACTOR"] * $ar_row["RUNTIME_FACTOR"];
			$ar_row["CYCLE_".$ar_row["BILLING_CYCLE"]] = 1;
			$ar_row["CURRENCY_DEFAULT"] = $nar_systemsettings['MARKTPLATZ']['CURRENCY'];
			$tpl_tmp = new Template("tpl/".$s_lang."/preise.row_runtime_packet.htm", $tpl_content->table);
			$tpl_tmp->addvars($ar_row);
			$tpl_tmp->addvar('i', $index);
			$tpl_tmp->addvar('even', 1-($index & 1));
			$ar_liste[] = $tpl_tmp;
		}
		$row["RUNTIMES"] = $ar_liste;
	} else {
		$row["RUNTIMES"] = false;
	}
}

include $ab_path."sys/lib.usercreate.php";
require_once $ab_path."sys/packet_management.php";
$packets = PacketManagement::getInstance($db);

$defaultTax = $db->fetch1("SELECT * FROM `tax` WHERE ID_TAX=".$nar_systemsettings["MARKTPLATZ"]["TAX_DEFAULT"]);

$usergroups = $db->fetch_table("
  		SELECT
  			g.*, s.V1, s.V2, s.T1
  		FROM `usergroup` g
  			LEFT JOIN
                `string_usergroup` s ON
                    s.FK=g.ID_USERGROUP AND s.S_TABLE='usergroup' AND
                    s.BF_LANG=if(g.BF_LANG_USERGROUP & ".$langval.", ".$langval.", 1 << floor(log(g.BF_LANG_USERGROUP+0.5)/log(2)))
		WHERE ".($uid > 0 ? "g.IS_AVAILABLE>0" : "g.IS_AVAILABLE=1")."
		ORDER BY g.F_ORDER ASC ");


$ar_packet_content = array();
$all = 0;
$memberships = $packets->getList(1, 256, $all, array("TYPE='MEMBERSHIP'","(STATUS&1)=1"), array("F_ORDER ASC", "TYPE ASC", "V1 ASC"));
$membershipsByUsergroup = array();

foreach($memberships as $key => $membership) {
	addUsergroups($memberships[$key], 0);
	$membershipsByUsergroup[$memberships[$key]['FK_USERGROUP']][] = $memberships[$key];
	$ar_packet_content[$membership['ID_PACKET']] = $db->fetch_nar("SELECT FK_PACKET, COUNT FROM `packet_collection` WHERE ID_PACKET=".$membership['ID_PACKET']);
}

$usergroupMemberships = array();
$usergroupById = array();
foreach($usergroups as $key => $usergroup) {
	$usergroups[$key]['COUNT_GROUPS'] = min(12,count($usergroups));
    $usergroups[$key]['TAX_PERCENT'] = $defaultTax['TAX_VALUE'];
	$usergroups[$key]['PROV_MAX_BRUTTO'] = $usergroup['PROV_MAX'] * (1 + $defaultTax['TAX_VALUE']/100);
	$usergroupById[$usergroup['ID_USERGROUP']] = $usergroups[$key];

}

$all = 0;
$ar_packets = $packets->getList(1, 50, $all, array("(TYPE='BASE' OR TYPE='BASE_ABO')", "(STATUS&1)=1"), array("F_ORDER ASC", "TYPE ASC", "V1 ASC"));

$usergroupTemplate =  '';
foreach($usergroups as $key => $usergroup) {
	$tpl_tmp = new Template("tpl/".$s_lang."/preise.row_group.htm");


	/**
	 * Provisionen
	 */

	$tpl_tmp->addvar('use_prov', $nar_systemsettings['MARKTPLATZ']['USE_PROV']);
	$tpl_tmp->addvar('CURRENCY_DEFAULT', $nar_systemsettings['MARKTPLATZ']['CURRENCY']);

	$liste = $db->fetch_table("
		SELECT
			*
		FROM
			provsatz
		WHERE FK_USERGROUP=".$usergroup['ID_USERGROUP']."
		ORDER BY
			PRICE ASC");

	$tpl_tmp->addlist("liste_prov", $liste, "tpl/".$s_lang."/preise.prov.htm");

	/**
	 * Mitgliedschaften
	 */

	$membershipTable = array(
			array(
					'TYP' => 'NAME',
					'TYP_N' => 0,
					'V1' => '',
					'COLS' => array()
			), array(
					'TYP' => 'DESCRIPTION',
					'TYP_N' => 1,
					'V1' => '',
					'COLS' => array()
			)
	);

	$packetGroupedById = array();
	$packetGroupedByName = array();
	foreach($ar_packets as $packetKey => $ar_packet) {
		$packetGroupedById[$ar_packet['ID_PACKET']] = $ar_packet;
		if(array_key_exists($ar_packet['V1'], $packetGroupedByName)) {
			$packetGroupedByName[$ar_packet['V1']][] = $ar_packet['ID_PACKET'];
		} else {
			$packetGroupedByName[$ar_packet['V1']] = array($ar_packet['ID_PACKET']);
			$membershipTable[] = array_merge($ar_packet, array('TYP' => 'PACKET', 'TYP_N' => 2, 'COLS' => array()));

		}
	}

	$membershipFeatures = new Api_Entities_MembershipFeatures($usergroup['ID_USERGROUP']);
	Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MEMBERSHIP_OTHER_FEATURES, $membershipFeatures);

	foreach ($membershipFeatures->getFeaturesRegister() as $featureIdent => $arFeature) {
		$membershipTable[] = array_merge($arFeature, array(
			'TYP' => 'PLUGIN',
			'TYP_N' => 3,
			'COLS' => array(),
			'FEATURE_IDENT' => $featureIdent,
			'FEATURE_TPL' => $arFeature["TPL_TEXT"]
		));
	}

	$membershipTable[] = array('V1' => get_messages('REGISTER', 'PACKET_RUNTIME'), 'TYP' => 'RUNTIME', 'TYP_N' => 3, 'COLS' => array());

	$rowTemplate = '';
	foreach($membershipTable as $tKey => $membershipTableRow) {
		$tpl_tmp_row = new Template("tpl/".$s_lang."/preise.packet.row.htm");
		
		$row_css_name = "design-preise-row-".$membershipTableRow['TYP'];

		$numberOfCols = count($membershipsByUsergroup[$usergroup['ID_USERGROUP']]) + 1;

		if(isset($membershipsByUsergroup[$usergroup['ID_USERGROUP']])) {
			foreach ($membershipsByUsergroup[$usergroup['ID_USERGROUP']] as $mKey => $membership) {
				switch($membershipTableRow['TYP']) {
					case 'NAME': $membershipTable[$tKey]['COLS'][] = array('V1' => $membership['V1']); break;
					case 'DESCRIPTION': $membershipTable[$tKey]['COLS'][] = array('V1' => $membership['T1']); break;
					case 'PACKET':
						$packetTypeIds = $packetGroupedByName[$membershipTableRow['V1']];

						$packetDisplay = 0;
						$isAboPacket = false;
						foreach ($packetTypeIds as $packetTypeId) {
							if (isset($ar_packet_content[$membership["ID_PACKET"]][$packetTypeId])) {
								$packetDisplay = $ar_packet_content[$membership["ID_PACKET"]][$packetTypeId];
								if($packetGroupedById[$packetTypeId]['TYPE'] == 'BASE_ABO') {
									$isAboPacket = true;
								}
								break;
							}
						}
						$row_css_name .= " ".$row_css_name."-".$membershipTableRow['V1'];

						$membershipTable[$tKey]['COLS'][] = array_merge($membership, array('V1' => $packetDisplay, 'ABO_PACKET' => $isAboPacket, 'PACKET_NAME' => $membershipTableRow['V2']));
						break;
					case 'PLUGIN':
						$featureIdent = $membershipTableRow["FEATURE_IDENT"];
						$arCol = array("V1" => "");
						if (is_array($membership["OPTIONS"]) && array_key_exists($featureIdent, $membership["OPTIONS"])) {
							$arCol = array_merge($arCol, $membership["OPTIONS"][$featureIdent]);
						}
						if ($membershipTableRow["FEATURE_TPL"] !== NULL) {
							$tplCol = new Template("tpl/" . $language . "/empty.htm");
							$tplCol->tpl_text = $membershipTableRow["FEATURE_TPL"];
							$tplCol->addvars($arCol);
							$arCol = array("V1" => $tplCol);
						}
						$membershipTable[$tKey]['COLS'][] = $arCol;
						break;
					case 'RUNTIME':
						$runtimeTemplate = '';
						foreach($membership['RUNTIMES'] as $runtimeKey => $runtime) {
                            $runtime->addvars($usergroup);
							$runtimeTemplate .= $runtime->process();
						}

						$membershipTable[$tKey]['COLS'][] = array_merge($membership, array('V1' => $runtimeTemplate));

						break;
					case 'BUTTON':
						$membershipTable[$tKey]['COLS'][] = array_merge($membership, array('V1' => '', 'ID_USERGROUP' => $key));

						break;
				}

			}
		} else {
			$tpl_tmp->addvar('NO_PACKETS', true);
		}
		$tpl_tmp_row->addvar("ROW_CSS", $row_css_name);
		$tpl_tmp_row->addlist('cols', $membershipTable[$tKey]['COLS'], "tpl/".$s_lang."/preise.packet.col.htm");
		$tpl_tmp_row->addvars($membershipTableRow);
		$tpl_tmp_row->addvar('NUMBER_OF_COLS', $numberOfCols);

		$rowTemplate .= $tpl_tmp_row->process();
	}

	$tpl_tmp->addvar('rows', $rowTemplate);
	$tpl_tmp->addvars($usergroupById[$usergroup['ID_USERGROUP']]);

	/**
	 * Pakete
	 */

	$ar_packets_extra = $packets->getUserList($usergroup['ID_USERGROUP'], 1, 256, array(), array("F_ORDER ASC", "TYPE ASC"));
	$tpl_tmp->addlist("liste_packets", $ar_packets_extra, "tpl/".$s_lang."/preise.row_packet.htm", "setContentText");

	/**
	 * Template verarbeitet
	 */

	$usergroupTemplate .= $tpl_tmp->process();
}


$tpl_content->addlist('usergroups', $usergroups, "tpl/".$s_lang."/preise.row.htm");
$tpl_content->addvar('usergroups_content', $usergroupTemplate);

$tpl_content->addvar('COUNT_GROUPS', min(12,count($usergroups)));

?>
