<?php
/* ###VERSIONSBLOCKINLCUDE### */

function addCycle(&$row) {
	$row["CYCLE_".$row["BILLING_CYCLE"]] = 1;
}

$SILENCE=false;
$perpage = 20;
$npage = ($_REQUEST['npage'] ? (int)$_REQUEST['npage'] : 1);
$limit = ($perpage*$npage)-$perpage;

require_once $ab_path."sys/packet_management.php";
$packets = PacketManagement::getInstance($db);

$id_user = (int)$_REQUEST['ID_USER'];
$ar_user = $db->fetch1("SELECT * FROM `user` WHERE ID_USER=".$id_user);

$tpl_content->addvar("ID_USER", $id_user);
$tpl_content->addvar("NAME", $ar_user["NAME"]);

if($_REQUEST['insert'] == 'true') {
	$tpl_content->addvar("inserted", 1);
}

if(count($_POST)) {
	$err = array();
	if ($_POST['SUB']) {
		$_POST['PRICE'] = str_replace(",", '.', $_POST['PRICE']);
		if (array_key_exists("FK_PACKET", $_POST)) {
			// Paket hinzubuchen
			if(!$_POST['FK_PACKET_RUNTIME']) {
				$err[] = "Bitte wählen Sie eine Laufzeit aus.";
			}
			if(count($err)) {
				$tpl_content->addvar("err", implode("<br>", $err));
			} else {
				$price = ($_POST['PRICE'] > 0 ? $_POST['PRICE'] : 0);
				$packets->order($_POST['FK_PACKET_RUNTIME'], $id_user, 1, NULL, NULL, $price);
				die(forward("index.php?page=user_kontingent&ID_USER=".$id_user."&done=packet"));
			}
		}
		if (array_key_exists("FK_MEMBERSHIP", $_POST)) {
			// Mitgliedschaft ändern
			if(!$_POST['FK_MEMBERSHIP_RUNTIME']) {
				$err[] = "Bitte wählen Sie eine Laufzeit aus.";
			}
			if(count($err)) {
				$tpl_content->addvar("err", implode("<br>", $err));
			} else {
				require_once $ab_path."sys/lib.packet.membership.upgrade.php";
				$upgrade = PacketMembershipUpgradeManagement::getInstance($db);
				$upgrade->initUpgrade($id_user, $_POST['FK_MEMBERSHIP_RUNTIME'], true);
				die(forward("index.php?page=user_kontingent&ID_USER=".$id_user."&done=membership"));
			}
		}
	} else {
		if($_POST['FK_PACKET']) {
			$id_packet = (int)$_POST['FK_PACKET'];
			$ar_packet = $packets->get($id_packet);
			$ar_packet['CONTENT'] = $packets->getCollectionContent($id_packet);
			// Gewählte laufzeit auslesen
			$ar_runtime = $ar_packet["RUNTIMES"][0];
			if (isset($_POST['FK_PACKET_RUNTIME'])) {
				$ar_runtime_sel = $packets->getFull($_POST['FK_PACKET_RUNTIME']);
				if ($ar_runtime_sel["FK_PACKET"] == $id_packet) {
					$ar_runtime = $ar_runtime_sel;
				}
			}
			// Preis updaten
			$_POST["PRICE"] = $ar_runtime["BILLING_PRICE"];
			// Templatevariablen einfügen
			$tpl_content->addvars($ar_packet, "PACKET_");
			addCycle($ar_runtime);
			$tpl_content->addvars($ar_runtime, "RUNTIME_");
			$tpl_content->addlist("liste_runtime", $ar_packet["RUNTIMES"], "tpl/de/user_kontingent.row_runtime.htm", 'addCycle');
		}
		if($_POST['FK_MEMBERSHIP']) {
			$id_packet = (int)$_POST['FK_MEMBERSHIP'];
			$ar_packet = $packets->get($id_packet);
			$ar_packet['CONTENT'] = $packets->getCollectionContent($id_packet);
			// Gewählte laufzeit auslesen
			$ar_runtime = $ar_packet["RUNTIMES"][0];
			if (isset($_POST['FK_MEMBERSHIP_RUNTIME'])) {
				$ar_runtime_sel = $packets->getFull($_POST['FK_MEMBERSHIP_RUNTIME']);
				if ($ar_runtime_sel["FK_PACKET"] == $id_packet) {
					$ar_runtime = $ar_runtime_sel;
				}
			}
			// Preis updaten
			$_POST["PRICE"] = $ar_runtime["BILLING_PRICE"];
			// Templatevariablen einfügen
			$tpl_content->addvars($ar_packet, "MEMBERSHIP_");
			addCycle($ar_runtime);
			$tpl_content->addvars($ar_runtime, "MEMBERSHIP_RUNTIME_");
			$tpl_content->addlist("liste_membership_runtime", $ar_packet["RUNTIMES"], "tpl/de/user_kontingent.row_runtime.htm", 'addCycle');
		}
	}
	#echo ht(dump($_POST));
	$tpl_content->addvars($_POST);
}

$ar_free = $db->fetch_nar("
	SELECT
		FK_PACKET,
		SUM(COUNT_MAX) - SUM(COUNT_USED) AS FREE
	FROM
		packet_order
	WHERE
		FK_USER=".$id_user." AND (STATUS&1)=1
	GROUP BY
		FK_PACKET");

$tpl_content->addvar("free_ads", $ar_free[PacketManagement::getType("ad_once")] + $ar_free[PacketManagement::getType("ad_abo")]);
$tpl_content->addvar("free_img", $ar_free[PacketManagement::getType("image_once")] + $ar_free[PacketManagement::getType("image_abo")]);

### Anzeigenpakete
$ar_orders = $db->fetch_nar("SELECT ID_PACKET_ORDER, FK_PACKET FROM `packet_order` WHERE FK_COLLECTION IS NULL AND FK_USER=".(int)$id_user);
$ar_orders_user = array();
foreach ($ar_orders as $id_packet_order => $fk_packet) {
	$order = $packets->order_get($id_packet_order);
	$isCollection = ($order->getType() == "COLLECTION") || ($order->getType() == "MEMBERSHIP");
	if (!$isCollection) {
	    continue;
    }
	$ar_orders_user[] = array(
		"ID_PACKET_ORDER"						=> $id_packet_order,
		"NAME" 											=> $order->getPacketName(),
		"ACTIVE"										=> $order->isActive(),
		"PAID"											=> $order->isPaid(),
		"TYPE_".$order->getType() 	=> 1,
		"RECURRING"						 			=> $order->isRecurring(),
		//"FK_INVOICE"						=> $order->getInvoiceId(),
		"CONTENTS"									=> ($isCollection ? $order->getCollectionContent() : $order->getType()),
		"STAMP_START"								=> $order->getPaymentDateFirst(),
		"STAMP_NEXT"								=> $order->getPaymentDateNext(),
		"STAMP_END"									=> $order->getPaymentDateLast(),
		"STAMP_CANCEL_UNTIL"				=> $order->getPaymentDateCancel(),
		"CANCEL_ENABLED"						=> ($order->isRecurring() ? $order->isCancelable() : false)
	);
}

//$ar_packets = $packets->getUserList($ar_user["FK_USERGROUP"], 1, 256);
$ar_packets = $packets->getList(1, 512, $packetCount, array("TYPE='COLLECTION'"), array("FK_USERGROUP"));
$id_usergroup = false;
$name_usergroup = "Unbekannt";
foreach ($ar_packets as $index => $ar_packet) {
	if (($id_usergroup === false) || ($id_usergroup != $ar_packet["FK_USERGROUP"])) {
		$id_usergroup = (int)$ar_packet["FK_USERGROUP"];
		$name_usergroup = $db->fetch_atom("SELECT s.V1
			FROM `usergroup` g
			LEFT JOIN `string_usergroup` s
				ON s.S_TABLE='usergroup' AND s.FK=g.ID_USERGROUP AND
				s.BF_LANG=if(g.BF_LANG_USERGROUP & 128, 128, 1 << floor(log(g.BF_LANG_USERGROUP+0.5)/log(2)))
			WHERE g.ID_USERGROUP=".$id_usergroup);
		$ar_packets[$index]["GROUP_BEGIN"] = $name_usergroup;
		if ($index > 0) {
			$ar_packets[$index-1]["GROUP_END"] = 1;
		}
	}
}
$ar_packets[count($ar_packets)-1]["GROUP_END"] = 1;

$objMembershipActive = $packets->getActiveMembershipByUserId($id_user);

$arMemberships = $packets->getList(1, 512, $packetCount, array("TYPE='MEMBERSHIP'"), array("FK_USERGROUP"));
$arUsergroupTypes = array( $packets->getType("usergroup_once"), $packets->getType("usergroup_abo") );
$id_usergroup = false;
$name_usergroup = "Unbekannt";
foreach ($arMemberships as $index => $ar_membership) {
	$arUsergroup = $db->fetch1("SELECT g.ID_USERGROUP, s.V1
		FROM `packet_collection` pc
		JOIN `usergroup` g ON g.ID_USERGROUP=pc.PARAMS
		LEFT JOIN `string_usergroup` s
			ON s.S_TABLE='usergroup' AND s.FK=g.ID_USERGROUP AND
			s.BF_LANG=if(g.BF_LANG_USERGROUP & 128, 128, 1 << floor(log(g.BF_LANG_USERGROUP+0.5)/log(2)))
		WHERE pc.ID_PACKET=".$ar_membership["ID_PACKET"]."
			AND pc.FK_PACKET IN (".implode(", ", $arUsergroupTypes).")");
	if (($id_usergroup === false) || ($id_usergroup != $arUsergroup["ID_USERGROUP"])) {
		$id_usergroup = (int)$arUsergroup["ID_USERGROUP"];
		$name_usergroup = $arUsergroup["V1"];
		$arMemberships[$index]["GROUP_BEGIN"] = $name_usergroup;
		if ($index > 0) {
			$arMemberships[$index-1]["GROUP_END"] = 1;
		}
	}
}
$arMemberships[count($arMemberships)-1]["GROUP_END"] = 1;

$tpl_content->addlist("liste", $ar_orders_user, "tpl/de/user_kontingent.row.htm");
$tpl_content->addlist("liste_pakete", $ar_packets, "tpl/de/user_kontingent.row_option.htm");

if ($objMembershipActive !== null) {
	$tpl_content->addvar("MEMBERSHIP_ACTIVE_NAME", $objMembershipActive->getPacketName());
	$tpl_content->addvars($objMembershipActive->asArray(), "MEMBERSHIP_ACTIVE_");
}
$tpl_content->addlist("liste_memberships", $arMemberships, "tpl/de/user_kontingent.row_membership.htm");

?>