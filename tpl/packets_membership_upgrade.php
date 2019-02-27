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
	        $tpl_tmp = new Template("module/tpl/".$s_lang."/register.row_runtime.htm", $tpl_content->table);
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


require_once $ab_path."sys/lib.usercreate.php";
require_once $ab_path."sys/lib.packet.membership.upgrade.php";
require_once $ab_path."sys/packet_management.php";
$packets = PacketManagement::getInstance($db);
$upgrade = PacketMembershipUpgradeManagement::getInstance($db);

if (empty($_POST)) {
	// Vorauswahl des aktuellen Pakets verhindern
	$tpl_content->addvar("FK_PACKET_RUNTIME", false);
}

$memberships = array();
$ar_usergroups = array();
$membership_cur = $packets->getActiveMembershipByUserId($uid);
if ($membership_cur != null) {
	// Upgrade
	$membership_trial = $db->fetch_atom("SELECT IS_TRIAL FROM `packet_runtime` WHERE ID_PACKET_RUNTIME=".(int)$membership_cur->getPacketRuntimeId());
	$memberships = $upgrade->fetchAllUpgradeablePackets($membership_trial ? 0 : $membership_cur->getPacketId(), true);
} else {
	// Neubestellung
	$memberships = $upgrade->fetchAllUpgradeablePackets(0, true);
}
$usergroups = $db->fetch_table("
	SELECT
		g.*, s.V1, s.V2, s.T1
	FROM `usergroup` g
	LEFT JOIN `string_usergroup` s
		ON s.FK=g.ID_USERGROUP AND s.S_TABLE='usergroup' AND
		s.BF_LANG=if(g.BF_LANG_USERGROUP & ".$langval.", ".$langval.", 1 << floor(log(g.BF_LANG_USERGROUP+0.5)/log(2)))
	WHERE g.IS_AVAILABLE>0
	ORDER BY g.F_ORDER ASC ");

$ar_packet_content = array();
$membershipsByUsergroup = array();

foreach($memberships as $key => $membership) {
	if (empty($membership["RUNTIMES"])) {
		continue;
	}
	addUsergroups($memberships[$key], 0);
	$membershipsByUsergroup[$memberships[$key]['FK_USERGROUP']][] = $memberships[$key];
	$ar_packet_content[$membership['ID_PACKET']] = $db->fetch_nar("SELECT FK_PACKET, COUNT FROM `packet_collection` WHERE ID_PACKET=".$membership['ID_PACKET']);
}

$usergroupMemberships = array();
$usergroupById = array();
foreach($usergroups as $key => $usergroup) {
	$usergroups[$key]['COUNT_GROUPS'] = min(12,count($usergroups));
	$usergroupById[$usergroup['ID_USERGROUP']] = $usergroups[$key];
}

$all = 0;
$ar_packets = $packets->getList(1, 50, $all, array("(TYPE='BASE' OR TYPE='BASE_ABO')", "(STATUS&1)=1"), array("F_ORDER ASC", "TYPE ASC", "V1 ASC"));
$usergroupTemplate =  '';
foreach($usergroups as $key => $usergroup) {
	$tpl_tmp = new Template("module/tpl/".$s_lang."/register.row_group.htm");
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
	$membershipTable[] = array('V1' => '', 'TYP' => 'BUTTON', 'TYP_N' => 4, 'COLS' => array());

	$rowTemplate = '';
	foreach($membershipTable as $tKey => $membershipTableRow) {
        $row_css_name = "design-register-row-".$membershipTableRow['TYP'];
		$tpl_tmp_row = new Template("module/tpl/".$s_lang."/register.packet.row.htm");

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
							$runtime->addvars($membership);
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
			$ar_usergroups[$usergroup["ID_USERGROUP"]] = $usergroup;
		} else {
			$tpl_tmp->addvar('NO_PACKETS', true);
		}
        $tpl_tmp_row->addvar("ROW_CSS", $row_css_name);
		$tpl_tmp_row->addlist('cols', $membershipTable[$tKey]['COLS'], "module/tpl/".$s_lang."/register.packet.col.htm");
		$tpl_tmp_row->addvars($membershipTableRow);
       	$tpl_tmp_row->addvars($usergroupById[$usergroup['ID_USERGROUP']]);
		$tpl_tmp_row->addvar('NUMBER_OF_COLS', $numberOfCols);

		$rowTemplate .= $tpl_tmp_row->process();
	}

	$tpl_tmp->addvar('rows', $rowTemplate);
	$tpl_tmp->addvars($usergroupById[$usergroup['ID_USERGROUP']]);
	$usergroupTemplate .= $tpl_tmp->process();

}


$tpl_content->addlist('usergroups', $ar_usergroups, 'cache/design/tpl/'.$s_lang.'/packets_membership_upgrade.row.htm');
$tpl_content->addvar('usergroups_content', $usergroupTemplate);

$tpl_content->addvar('COUNT_GROUPS', min(12,count($ar_usergroups)));

// Gutschein codes
if ($nar_systemsettings["MARKTPLATZ"]["COUPON_ENABLED"]) {
    $tpl_content->addvar("OPTION_COUPON_ENABLED", 1);

    $tpl_content->addvar('COUPON_WIDGET_TARGET_TYPE', 'PACKET');
    $tpl_content->addvar('COUPON_WIDGET_TARGETS', $allPacketRuntimeIds);
    $tpl_content->addvar('COUPON_WIDGET_TARGETS_JSON', json_encode($allPacketRuntimeIds));
}

if(empty($ar_params[1])) {
	include_once 'module/register/inc.profile_check.php';
	$data = $db->fetch_blank('user');
	if (count($_POST) && ($uid > 0)) {
		$err = array (); // Fehler in diesem Array sammeln
		recurse($_POST, '$value=trim($value);');
		$_POST["ID_USER"] = $uid;
		$_POST['FK_COUNTRY'] = $_POST['land'];

		$membership_paid = false;
		if ($_POST["FK_PACKET_RUNTIME"] > 0) {
			$id_packet_runtime = (int)$_POST["FK_PACKET_RUNTIME"];
			$ar_packet = $packets->getFull($id_packet_runtime);
			if (($ar_packet != null) && ($ar_packet["BILLING_PRICE"] > 0)) {
				$membership_paid = true;
			}
			$id_membership = (int)$db->fetch_atom("SELECT PARAMS FROM `packet_collection`
					WHERE ID_PACKET=".$ar_packet["ID_PACKET"]." AND FK_PACKET IN (".$packets->getType("usergroup_once").", ".$packets->getType("usergroup_once").")");
			if ($id_membership > 0) {
				$id_usergroup = $id_membership;
			}
		}

		if ($membership_paid) {
			// Mitgliedschaft ist kostenpflichtig! Anschrift erforderlich!
			if (empty($_POST["VORNAME"]) || empty($_POST["NACHNAME"]) || empty($_POST["STRASSE"])
					|| empty($_POST["PLZ"]) || empty($_POST["ORT"]) || empty($_POST["FK_COUNTRY"]))
				$err[] = 'ERR_REQUIRED_FELDS';
		}
		if ($_POST['AGB'] == "") // Verschiedene Passwortabfragen
			$err[] = 'noAGB';

		$private_access = $db->fetch_atom("SELECT PRIVATE FROM `usergroup` WHERE ID_USERGROUP=".$id_usergroup);
		$_POST["PRIVATE"] = $private_access;
		if ($private_access > 0) {
			if (($private_access == 1) && ($_POST["ACCEPT_PRIVATE"] != 1))
				$err[] = 'ACCEPT_PRIVATE';
			if (($private_access == 2) && ($_POST["ACCEPT_COMPANY"] != 1))
				$err[] = 'ACCEPT_COMPANY';
		}
		
        // Gutscheincode
        $couponCode = null;
        $_POST['FK_COUPON_CODE_USAGE'] = null;
        if ($nar_systemsettings["MARKTPLATZ"]["COUPON_ENABLED"]) {
            $couponManagement = Coupon_CouponManagement::getInstance($db);
            $couponUsageManagement = Coupon_CouponUsageManagement::getInstance($db);

            $couponTargetType = "PACKET";
            $couponTargets = array($_POST['FK_PACKET_RUNTIME']);


            $result = array();
            if(isset($_POST['COUPON_CODE']) && !empty($_POST['COUPON_CODE'])) {
                try {

                    $coupon = $couponManagement->fetchCouponByCode($_POST['COUPON_CODE']);
                    if($coupon && $couponManagement->tryAndTestCouponCode($_POST['COUPON_CODE']) && $couponUsageManagement->isCouponsUsageCompatible($coupon, $couponTargetType, $couponTargets)) {
                        $couponCode = $_POST['COUPON_CODE'];
                    } else {
                       $err[] = 'COUPON_CODE_INVALID';
                    }

                } catch(Exception $e) {

                    $err[] = 'COUPON_CODE_INVALID';
                }
            }
        }
		
		if (count($err)) {
			// Falls Fehlermeldungen generiert wurden...
			$err = implode(",", $err);
			$err = get_messages("register", $err);
			#die(ht(dump($err)));
			$tpl_content->addvar('err',implode('<br />- ', $err)); // Diese im Template ausgeben
			$data = array_merge($data, $_POST);
		} else {
			// Ansonsten...
			if ($id = $db->update('user', $_POST)) {
				if ($couponCode !== null) {
					$couponUsage = $couponManagement->useCouponCode($couponCode)->couponUsage;

					if ($couponUsage['USAGE_STATE'] == Coupon_CouponUsageManagement::USAGE_STATE_ACTIVATED && $couponUsageManagement->isCouponsUsageCompatible($couponUsage, $couponTargetType, $couponTargets)) {
						$_POST['FK_COUPON_CODE_USAGE'] = $couponUsage['ID_COUPON_CODE_USAGE'];
					}
				}
				$result = $upgrade->initUpgrade($uid, $_POST["FK_PACKET_RUNTIME"], $couponCode);
				if ($result > 0) {
					die(forward(
						$tpl_content->tpl_uri_action("packets_membership_upgrade,success,".$result)
					));
				} else {
					die(forward(
						$tpl_content->tpl_uri_action("packets_membership_upgrade,failed,".$result)
					));
				}
			}
		}
	} else {
		$ar_user_data = $db->fetch1("SELECT * FROM `user` WHERE ID_USER=".$uid);
		$ar_user_data["land"] = $ar_user_data["FK_COUNTRY"];
		$tpl_content->addvars($ar_user_data);
	}
	$tpl_content->addvars($data);
	$tpl_content->addvar('langval',
		nar2select('name="LANGVAL" id="langval"', (($tmp = $data['LANGVAL']) ? $tmp : $langval),
			$db->fetch_nar($db->lang_select('lang', 'BITVAL, LABEL'). 'where B_PUBLIC=1')
		)
	);
} else {
	$tpl_content->addvar($ar_params[1], 1);
	$tpl_content->addvar("result", $ar_params[2]);
}

?>
