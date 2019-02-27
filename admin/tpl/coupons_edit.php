<?php

$couponManagement = Coupon_CouponManagement::getInstance($db);
$couponUsageManagement = Coupon_CouponUsageManagement::getInstance($db);
$couponId = (int)$_REQUEST['ID_COUPON'];

$err = array();
$info = array();

if (count($_POST)) {

	if (!$_POST['COUPON_NAME']) {
		$err[] = 'Bezeichnung fehlt.';
	}
	if (!$_POST['COUPON_DESCRIPTION']) {
		$err[] = 'Beschreibung fehlt.';
	}
	if (!$_POST['TYPE']) {
		$err[] = 'Typ fehlt.';
	}

	if (empty($_POST['COUPON_CODES'])) {
		$err[] = 'Es wurden keine Gutscheincodes eingegeben.';
	}

	$oldcodes = $db->fetch_nar("SELECT ID_COUPON_CODE, CODE FROM coupon_code WHERE FK_COUPON = '".$couponId."'");

	$newCodes = explode("\n", $_POST['COUPON_CODES']);
	if(is_array($newCodes)) {
		foreach($newCodes as $key => $newCode) {
			if((int)$db->fetch_atom("SELECT COUNT(*) as a FROM coupon_code WHERE `CODE` = '".mysql_real_escape_string(trim($newCode))."' AND  FK_COUPON != '".(int)$couponId."'") > 0) {
				unset($newCodes[$key]);
				$info[] = 'Der Gutscheincode '.$newCode.' ist bereits in Verwendung und wurde deshalb entfernt';
			}
		}

		if(count($newCodes) == 0) {
			$err[] = 'Alle Gutscheincodes sind bereits in Verwendung';
		}
	} else {
		$err[] = 'Es wurden keine Gutscheincodes eingegeben.';
	}


	if (!count($err)) {
		if($couponId == 0) {
			$_POST['STAMP_CREATE'] = date("Y-m-d H:i:s");
		}

		$_POST['TYPE_CONFIG'] = serialize($_POST['TYPE_CONFIG']);

		$couponId = $db->update('coupon', $_POST);

		if (!$couponId) {
			$err[] = 'Fehler beim Speichern.';
		} else {

			$deleteCodes = array_diff($oldcodes, $newCodes);
			if(is_array($deleteCodes) && count($deleteCodes) > 0) {
				foreach($deleteCodes as $key => $deleteCode) {
					$db->querynow("DELETE FROM coupon_code WHERE FK_COUPON = '".(int)$couponId."' AND CODE = '".mysql_real_escape_string($deleteCode)."'");
				}
			}


			foreach($newCodes as $key => $newCode) {
				if(trim($newCode) != '') {
					$newCode = preg_replace("/[^A-Z0-9 ]/", '', strtoupper($newCode));
					$db->querynow("INSERT IGNORE INTO coupon_code (FK_COUPON, CODE) VALUES ('" . $couponId . "', '" . $newCode . "')");
				}
			}

			$db->querynow("DELETE FROM coupon_restriction WHERE FK_COUPON = '".(int)$couponId."'");
			foreach($_POST['RESTRICTION_TYPE'] as $restrictionKey => $restrictionEnabled) {
				if($restrictionEnabled == 1) {
					$db->update("coupon_restriction", array('FK_COUPON' => $couponId, 'RESTRICTION_TYPE' => $restrictionKey, 'RESTRICTION_CONFIG' => serialize($_POST['RESTRICTION_CONFIG'][$restrictionKey])));
				}
			}

			$tpl_content->addvar("success", 1);
		}
	} else {
		$tpl_content->addvar("err", implode("<br>", $err));
	}

	$tpl_content->addvar("info", implode("<br>", $info));

}

if ($couponId) {
	$coupon = $couponManagement->fetchById($couponId);
	$couponType = $couponManagement->getCouponType($coupon);

	$tpl_content->addvar("TYPE_".$coupon['TYPE'], 1);
	$couponTypeConfig = $couponType->getTypeConfiguration();
    if (!is_array($couponTypeConfig)) {
        $couponTypeConfig = array();
    }
	$tpl_content->addvars(array_flatten($couponTypeConfig, true, '_', 'TYPE_CONFIG_'));

	// restrictions
	$couponRestrictionsConfig = array();
	$couponRestrictions = $db->fetch_table("SELECT * FROM coupon_restriction WHERE FK_COUPON = '".(int)$couponId."'");
	foreach($couponRestrictions as $key => $couponRestriction) {
		$restrictionConfig = unserialize($couponRestriction['RESTRICTION_CONFIG']);

		$couponRestrictionsConfig[$couponRestriction['RESTRICTION_TYPE']] = $restrictionConfig;
		$tpl_content->addvar('RESTRICTION_TYPE_'.$couponRestriction['RESTRICTION_TYPE'], 1);
		if(is_array($restrictionConfig)) {
			$tpl_content->addvars(array_flatten($restrictionConfig, TRUE, '_', 'RESTRICTION_CONFIG_' . $couponRestriction['RESTRICTION_TYPE'] . '_'));
		}
	}

} else {
	$coupon = array();
	$couponTypeConfig = array();
}

$data = array_merge($coupon, $_POST);
$tpl_content->addvars($data);

// Coupon Types

$tplCouponListTypes = array();
$couponListTypes = $couponManagement->getAllCouponTypes();

foreach($couponListTypes as $key => $couponListType) {
	$tmpCouponType = $couponManagement->getCouponType(array('TYPE'=> $couponListType, 'TYPE_CONFIG' => null));

	$tplCouponListTypes[$key]['SELECTED'] = (isset($couponType) && $couponListType == get_class($couponType));
	$tplCouponListTypes[$key]['TYPE_NAME'] = $tmpCouponType->getName();
	$tplCouponListTypes[$key]['TYPE_CLASS'] = $couponListType;
}

if(count($tplCouponListTypes) > 0) {
	$tpl_content->addlist('coupon_types', $tplCouponListTypes, 'tpl/de/coupons_edit.row_types.htm');
}


if (count($err)) {
	$tpl_content->addvar('err', implode('<br />', $err));
}



// restrictions

$usergroups = $db->fetch_table("SELECT
  g.*, s.V1, s.V2, s.T1
  FROM `usergroup` g LEFT JOIN `string_usergroup` s ON  s.FK=g.ID_USERGROUP AND s.S_TABLE='usergroup' AND s.BF_LANG=if(g.BF_LANG_USERGROUP & ".$langval.", ".$langval.", 1 << floor(log(g.BF_LANG_USERGROUP+0.5)/log(2)))
  ORDER BY g.F_ORDER ASC
");

foreach($usergroups as $key => $usergroup) {
	$usergroups[$key]['CHECKED'] = (is_array($couponRestrictionsConfig['Coupon_Restriction_UsergroupRestriction']['USERGROUPS_ALLOWED']) && in_array($usergroup['ID_USERGROUP'], $couponRestrictionsConfig['Coupon_Restriction_UsergroupRestriction']['USERGROUPS_ALLOWED']));
}

$tpl_content->addlist("restrictions_usergroups_list", $usergroups, "tpl/de/coupons_edit.restrictions.usergroup_row.htm");


// type

require_once $ab_path."sys/packet_management.php";
$packets = PacketManagement::getInstance($db);

$tplPacketList = array();
$ar_packets = $packets->getList(1, 100, $all, array("TYPE='MEMBERSHIP'"), array("F_ORDER ASC", "TYPE ASC", "V1 ASC"));
foreach($ar_packets as $key => $ar_packet) {
	foreach($ar_packet['RUNTIMES'] as $runtimeId => $runtime) {
		$tplPacketList[] = array_merge($ar_packet, $runtime, array(
			"CYCLE_".$runtime["BILLING_CYCLE"] => 1, 
			'CHECKED' => (is_array($couponTypeConfig['TARGETS']) && in_array($runtime['ID_PACKET_RUNTIME'], $couponTypeConfig['TARGETS']) && $couponTypeConfig['TARGET_TYPE'] == 'PACKET'),
			'CHECKED_REGISTER' => (is_array($couponTypeConfig['COUPON_MEMBERSHIP']) && in_array($runtime['ID_PACKET_RUNTIME'], $couponTypeConfig['COUPON_MEMBERSHIP']))
		));
	}
}
$tpl_content->addlist("type_membership_list", $tplPacketList, "tpl/de/coupons_edit.type_config.membership_row.htm");
$tpl_content->addlist("type_membership_options", $tplPacketList, "tpl/de/coupons_edit.type_config.membership_row_option.htm");

$tplPacketList = array();
$ar_packets = $packets->getList(1, 100, $all, array("TYPE='COLLECTION'"), array("F_ORDER ASC", "TYPE ASC", "V1 ASC"));
foreach($ar_packets as $key => $ar_packet) {
	foreach($ar_packet['RUNTIMES'] as $runtimeId => $runtime) {
		$tplPacketList[] = array_merge($ar_packet, $runtime, array("CYCLE_".$runtime["BILLING_CYCLE"] => 1, 'CHECKED' => (is_array($couponTypeConfig['TARGETS']) && in_array($runtime['ID_PACKET_RUNTIME'], $couponTypeConfig['TARGETS']) && $couponTypeConfig['TARGET_TYPE'] == 'PACKET')));
	}
}
$tpl_content->addlist("type_packet_list", $tplPacketList, "tpl/de/coupons_edit.type_config.membership_row.htm");

$tplServiceList = array();
$ar_packets_base = $packets->getBaseList(1, 100, $a = 0, array("p.ID_PACKET IN (".implode(",", PacketManagement::getComponentTypes()).")"), array("TYPE ASC", "V1 ASC"));
foreach($ar_packets_base as $key => $ar_packet_base) {
	$ar_packets_base[$key]['CHECKED'] = (is_array($couponTypeConfig['TARGETS']) && in_array($ar_packet_base['ID_PACKET'], $couponTypeConfig['TARGETS']) && $couponTypeConfig['TARGET_TYPE'] == 'SERVICE');
}
$tpl_content->addlist("type_service_list", $ar_packets_base, "tpl/de/coupons_edit.type_config.setvice_row.htm");


// codelist
$tpl_content->addvar('COUPON_CODES', implode("\n", $db->fetch_nar("SELECT ID_COUPON_CODE, CODE FROM coupon_code WHERE FK_COUPON = '".$couponId."'")));


if($couponId != null && $_GET['DO'] == 'Usage') {


	$perpage = 20; // Elemente pro Seite
	$limit = ((($_REQUEST['npage'] ? $_REQUEST['npage'] : $_REQUEST['npage']=1)-1)*$perpage);

	$param = array(
		'FK_COUPON' => $couponId,
		'SORT_BY' => 'ccu.STAMP_ACTIVATE',
		'SORT_DIR' => 'DESC',
		'LIMIT' => $perpage,
		'OFFSET' => $limit
	);

	$usageUsers = $couponUsageManagement->fetchAllByParam($param);
	$numberOfUsages = $couponUsageManagement->countByParam($param);


	$tpl_content->addlist('liste_usage', $usageUsers, 'tpl/'.$s_lang.'/coupons_edit.row_usage.htm');

	$tpl_content->addvar("liste_usage_pager", htm_browse($numberOfUsages, $_REQUEST['npage'], "index.php?page=".$tpl_content->vars['curpage']."&".http_build_query($_GET)."&npage=", $perpage));
	$tpl_content->addvar("liste_usage_all", $numberOfUsages);

	$tpl_content->addvar('DO', 'Usage');
	$tpl_content->addvar('DO_USAGE', '1');


}
?>