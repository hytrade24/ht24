<?php
/* ###VERSIONSBLOCKINLCUDE### */


#$SILENCE=false;

// Trigger plugin event
$pluginInfoParams = new Api_Entities_EventParamContainer(array("pluginInfo" => ""));
Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::USER_VIEW_HOME, $pluginInfoParams);
$tpl_content->addvar("PLUGIN_INFO", $pluginInfoParams->getParam("pluginInfo"));

//--------------------------------//
//     NACHRICHTEN / ANFRAGEN     //
//--------------------------------//
require_once $ab_path.'sys/lib.chat.php';
require_once $ab_path.'sys/lib.chat.user.php';
#require_once $ab_path.'sys/lib.user.php';
require_once 'sys/lib.chat.user.read.message.php';


$chatUserReadMessageManagement = ChatUserReadMessageManagement::getInstance($db);
$userMails = $chatUserReadMessageManagement->countUnreadMessagesExByUserId($uid);

//Check if Welcome page is empty
$userWelcomeFlag=true;

$tpl_content->addvar('NEW_MAILS', $userMails['COUNT_UNREAD']);
$tpl_content->addvar('NEW_MAILS_AD', $userMails['COUNT_UNREAD_AD']);
$tpl_content->addvar('NEW_MAILS_OTHER', $userMails['COUNT_UNREAD'] - $userMails['COUNT_UNREAD_AD']);
/*

$chatManagement = ChatManagement::getInstance($db);
$userManagement = UserManagement::getInstance($db);

$chatUnreadParams = array(
	'CHAT_USER_ID' 			=> $uid,
	'READABLE_BY_USERID' 	=> $uid,
	'IS_UNREAD'				=> true,
	'CHAT_USER_STATUS' 		=> ChatUserManagement::STATUS_ACTIVE,
	'MODUS' 				=> 'INBOX',
	'LIMIT' 				=> 5,
	'OFFSET' 				=> 0,
	'SEARCH_FK_TYPE'		=> 1			// Bezug auf Anzeige
);
$chatUnreadCount = $chatManagement->countByParam($chatUnreadParams);
if ($chatUnreadCount > 0) {
	require_once $ab_path.'sys/lib.chat.messages.php';
	require_once $ab_path.'sys/lib.chat.user.virtual.php';
	require_once $ab_path.'sys/lib.chat.user.read.message.php';
	$chatMessagesManagement = ChatMessageManagement::getInstance($db);
	$chatUserManagement = ChatUserManagement::getInstance($db);
	$chatUserVirtualManagement = ChatUserVirtualManagement::getInstance($db);
	$chatUserReadMessageManagement = ChatUserReadMessageManagement::getInstance($db);
	
	$chatUnreadList = $chatManagement->fetchAllByParam($chatUnreadParams);
	$chatUnreadListTpl = array();
	foreach($chatUnreadList as $key => $chat) {
		$chatUsers = $chatUserManagement->fetchAllChatUserByChatId($chat['ID_CHAT']);
		foreach($chatUsers as $uKey=>$chatUser) {
			if($chatUser['FK_USER'] != $uid) {
				$chat['USER_TYPE'] = $chatUser['TYPE'];
				if($chatUser['TYPE'] == 0) {
					$tmpUser = $db->fetch1("SELECT * FROM user WHERE ID_USER = '".$chatUser['FK_USER']."'");
					$chat['USER_ID'] = $chatUser['FK_USER'];
					$chat['USER_NAME'] = $tmpUser['NAME'];
					$chat['USER_VIRTUAL'] = $tmpUser['IS_VIRTUAL'];
	
					$chatUserFull = $userManagement->fetchFullDatasetById($chat['USER_ID']);
					$chat['USER_LOGO'] = $chatUserFull['LOGO'];
					$chat['USER_VORNAME'] = $chatUserFull['VORNAME'];
					$chat['USER_NACHNAME'] = $chatUserFull['NACHNAME'];
					$chat['USER_FIRMA'] = $chatUserFull['FIRMA'];
	
				} else {
					$tmpUser = $chatUserVirtualManagement->find($chatUser['FK_CHAT_USER_VIRTUAL']);
					$chat['USER_VIRTUAL_ID'] = $chatUser['FK_CHAT_USER_VIRTUAL'];
					$chat['USER_EMAIL'] = $tmpUser['EMAIL'];
					$chat['USER_NAME'] = $tmpUser['NAME'];
				}
			} else {
				$ownChatUser = $chatUser;
			}
		}
		$lastMessage = $chatMessagesManagement->fetchLastMessageByChatAndUser($chat['ID_CHAT'], $uid, $modus);
		$chat['LASTMESSAGE'] = substr($lastMessage['MESSAGE'], 0, 200);
		$realLastMessage = $chatMessagesManagement->fetchLastMessageByChatAndUser($chat['ID_CHAT'], $uid);
		if($realLastMessage['SENDER'] == $ownChatUser['ID_CHAT_USER']) {
			$chat['IS_LASTMESSAGE_FROM_ME'] = true;
		}
		if($chat['FK_AD'] != null) {
			$tmpAd = $db->fetch1("SELECT * FROM ad_master WHERE ID_AD_MASTER = '".$chat['FK_AD']."'");
			$chat['FK_KAT'] = $tmpAd['FK_KAT'];
			$chat['FK_AD_NAME'] = $tmpAd['PRODUKTNAME'];
		}
		// has chat unread messages
		$hasChatUnreadMessagesForUser = $chatUserReadMessageManagement->existUnreadMessagesInChatForUser($chat['ID_CHAT'], $uid);
		$chat['MARK_UNREAD'] = $hasChatUnreadMessagesForUser;
		$chatUnreadListTpl[] = $chat;
	}
	$tpl_content->addlist("liste_messages", $chatUnreadListTpl, "tpl/".$s_lang."/my-pages.row_message.htm");
}
*/
//-----------------------------//

require_once $ab_path.'sys/lib.ad_order.php';
$adOrderManagement = AdOrderManagement::getInstance($db);

function addVariants(&$row, $i) {
	global $db, $langval;
	$ar_variant = (isset($row["SER_VARIANT"]) ? unserialize($row["SER_VARIANT"]) : array());
	$ar_variant_list = array();
	foreach ($ar_variant as $index => $ar_current) {
		$value = $db->fetch_atom("SELECT sl.V1 FROM `liste_values` t
				LEFT JOIN `string_liste_values` sl
					ON sl.S_TABLE='liste_values' AND sl.FK=t.ID_LISTE_VALUES
					AND sl.BF_LANG=if(t.BF_LANG_LISTE_VALUES & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_LISTE_VALUES+0.5)/log(2)))
				WHERE t.ID_LISTE_VALUES=".$ar_current["ID_LISTE_VALUES"]);
		if ($value !== false) {
			$ar_variant_list[] = $value;
		} else {
			$ar_variant_list[] = $ar_current["VALUE"];
		}
	}
	$row["VARIANT"] = (empty($ar_variant_list) ? "" : implode(", ", $ar_variant_list));
	$row['BID_STATUS_'.$row['BID_STATUS']] = 1;
}


require_once $ab_path."sys/packet_management.php";
$packets = PacketManagement::getInstance($db);
$membership_cur = $packets->getActiveMembershipByUserId($uid);
if ($membership_cur != null) {
	// Details auslesen
    $ar_tax = $membership_cur->getPaymentTax();
	$ar_details = $membership_cur->asArray();
	$ar_details["ID"] = $membership_cur->getOrderId();
	$ar_details["NAME"] = $membership_cur->getPacketName();
    $ar_details["PRICE_BRUTTO"] = $ar_details["PRICE"] * ($ar_tax["TAX_VALUE"] / 100 + 1);
    $ar_details["PRICE_NETTO"] = $db->fetch_atom("SELECT PRICE_NETTO FROM `usergroup` WHERE ID_USERGROUP=".$user["FK_USERGROUP"]);
	$ar_details["BILLING_CYCLE_".$ar_details["BILLING_CYCLE"]] = 1;
    // Upgrade möglichkeiten prüfen
    require_once $ab_path."sys/lib.packet.membership.upgrade.php";
    $upgrade = PacketMembershipUpgradeManagement::getInstance($db);
    $upgradeAvailable = $upgrade->isUpgradeAvailable($membership_cur->getPacketId());
    $ar_details["UPGRADABLE"] = ($upgradeAvailable ? 1 : 0);
	// Ins Template einfügen
	$tpl_content->addvars($ar_details, "MEMBERSHIP_");
} else {
	// Unbezahlte pakete mit anzeigen auslesen
	$ar_required = array(PacketManagement::getType("usergroup_once") => 1);
	$ar_required_abo = array(PacketManagement::getType("usergroup_abo") => 1);
    $ar_packets_unpaid = array();
    $ar_packets_disabled = array_merge($packets->order_find_collections($uid, $ar_required, 0), $packets->order_find_collections($uid, $ar_required_abo, 0));
    foreach ($ar_packets_disabled as $index => $ar_packet) {
		$arInvoice = $db->fetch1("
			SELECT pi.FK_INVOICE, i.STATUS FROM `packet_order_invoice` pi
			JOIN `billing_invoice` i ON i.ID_BILLING_INVOICE=pi.FK_INVOICE 
			WHERE pi.FK_PACKET_ORDER=".$ar_packet["ID_PACKET_ORDER"]);
        if (is_array($arInvoice) && ($arInvoice["STATUS"] == 0)) {
            $ar_packets_disabled[$index]["FK_INVOICE"] = $arInvoice["FK_INVOICE"];
            $ar_packets_unpaid[] = $ar_packets_disabled[$index];
        }
    }
    unset($ar_packets_disabled);
    $tpl_content->addlist("liste_packets_unpaid", $ar_packets_unpaid, "tpl/".$s_lang."/my-pages.row_packet_unpaid.htm");
}



$liste = $db->fetch_table("
	select
		*,
		ad_master.FK_USER AS FK_SELLER,
		verstoss.STAMP AS STAMP_VERSTOSS,
		meldung.ID_USER AS FK_MELDER,
		meldung.NAME AS MELDER
	from
		verstoss
	left join
		ad_master ON verstoss.FK_AD = ad_master.ID_AD_MASTER
	left join
		user on user.ID_USER=ad_master.FK_USER
	left join
		user meldung ON verstoss.FK_USER=meldung.ID_USER
	where
		ad_master.FK_USER=".$uid."
		and verstoss.STAMP_AD_UPDATE <= verstoss.STAMP
	group by
		verstoss.FK_AD");
if(count($liste)) {
	$tpl_content->addlist("liste_verstoss", $liste, "tpl/".$s_lang."/my-pages.verstoss.htm");
    $userWelcomeFlag=false;//new
}

/* Club-Einladungen */
$num_club_invites = $db->fetch_atom("
	SELECT COUNT(*)
	FROM
		club_invite
	WHERE
		FK_USER=".(int)$uid);
$tpl_content->addvar('num_club_invites', $num_club_invites);

/* Club-Member-Requests */
$num_club_member_requests = $db->fetch_atom("
	SELECT COUNT(*)
	FROM
	club_member_request
	WHERE FK_CLUB IN(
		SELECT ID_CLUB FROM club WHERE FK_USER=".$uid."
	) AND STATUS = 0");
$tpl_content->addvar('num_club_member_requests', $num_club_member_requests);

/* verkäufe */
$userOrders = $adOrderManagement->fetchAllByParam(array(
	'USER_SELLER' => $uid,
	'LIMIT' => 5,
	'OFFSET' => 0
));
$tpl_content->addlist("liste_sales", $userOrders, $str =  "tpl/".$s_lang."/my-pages.sales.htm", 'callback_order_addOrderItems');



/* handeln: anzahl */
$num_trades = $db->fetch_atom("
		SELECT COUNT(*)
		FROM trade
		WHERE (FK_USER_FROM = ".$uid." OR FK_USER_TO = ".$uid.") AND BID_STATUS='ACTIVE'
			AND FK_AD_REQUEST IS NULL");
$tpl_content->addvar('num_trades', $num_trades);

/* handeln: anzahl */
$num_offers = $db->fetch_atom("
		SELECT COUNT(*)
		FROM trade
		WHERE (FK_USER_TO = ".$uid.") AND (FK_USER_AD_OWNER = FK_USER_FROM) AND BID_STATUS='ACTIVE'
			AND FK_AD_REQUEST IS NOT NULL");
$tpl_content->addvar('num_offers', $num_offers);


/* handeln */
$in = array(0);
$res = $db->querynow("
	SELECT
		MAX(ID_TRADE)
	FROM
		trade
	WHERE
		(
			FK_USER_FROM = ".$uid."
			OR FK_USER_TO = ".$uid."
		)
	GROUP BY
		FK_AD
	ORDER BY
		STAMP_BID DESC
	LIMIT 5");
while($row = mysql_fetch_row($res['rsrc']))
{
	$in[] = $row[0];
}
$liste = $db->fetch_table("
	SELECT
		trade.*,
		ad_master.PRODUKTNAME,
		ad_master.STATUS&3 AS AD_STATUS
	FROM
		trade
	LEFT JOIN
		ad_master ON ad_master.ID_AD_MASTER = trade.FK_AD
	WHERE
		trade.ID_TRADE IN(".implode(',', $in).")
	GROUP BY
    	ad_master.ID_AD_MASTER
	ORDER BY
		STAMP_BID DESC");
$tpl_content->addlist("liste_handeln", $liste, "tpl/".$s_lang."/my-pages.handeln.htm", 'addVariants');

/* einkäufe */
/* verkäufe */
$userOrdersBuy = $adOrderManagement->fetchAllByParam(array(
	'USER_BUYER' => $uid,
	'LIMIT' => 5,
	'OFFSET' => 0
));
$countUnpaidOrders =  $adOrderManagement->countByParam(array(
	'STATUS_PAYMENT' => 0,
	'USER_BUYER' => $uid
));

$tpl_content->addlist("liste_shopping", $userOrdersBuy, $str =  "tpl/".$s_lang."/my-pages.shopping.htm", 'callback_order_addOrderItems');
$tpl_content->addvar('COUNT_UNPAID_ORDERS', $countUnpaidOrders);

/* Auslaufende */
$num_ads_going_down = $db->fetch_atom("
	SELECT COUNT(*)
			FROM
    		ad_master
			WHERE
    		FK_USER=".$uid."
    		AND STATUS&3 = 1 AND (DELETED=0)
    		AND DATEDIFF(STAMP_END, NOW()) <= 7;");
$tpl_content->addvar('num_ads_going_down', $num_ads_going_down);

/*
$ads = $db->fetch_table("
	SELECT
			SQL_CALC_FOUND_ROWS
    		am.ID_AD_MASTER, am.FK_KAT, am.PRODUKTNAME, am.VERKAUFSOPTIONEN, am.PREIS,
    		am.ID_AD_MASTER AS ID_ARTIKEL,
    		LEFT(am.BESCHREIBUNG, 250) AS DSC,
    		DATEDIFF(NOW(), am.STAMP_START) as RUNTIME,
    		DATEDIFF(am.STAMP_END, NOW()) as TIMELEFT
    	FROM
    		ad_master am
		WHERE
    		am.FK_USER=".$uid."
    		AND am.STATUS&3 = 1 AND (am.DELETED=0)
    		AND DATEDIFF(am.STAMP_END, NOW()) <= 7
    	GROUP BY
    		am.ID_AD_MASTER
    	ORDER BY
			am.STAMP_START DESC
    	LIMIT
    		5");
$tpl_content->addlist("liste_timeout", $ads, "tpl/".$s_lang."/my-pages.timeout.htm");
*/

/* Kontingent */
$kostenlos = $nar_systemsettings['MARKTPLATZ']['FREE_ADS'];
$tpl_content->addvar("kostenlos", $kostenlos);
if(!$kostenlos) {
	require_once $ab_path."sys/packet_management.php";
	$packets = PacketManagement::getInstance($db);
	$ads_free = $db->fetch_atom("SELECT (SUM(COUNT_MAX) - SUM(COUNT_USED)) as COUNT
		FROM `packet_order` WHERE FK_USER=".$uid." AND (STATUS&1)=1 AND
			FK_PACKET IN (".PacketManagement::getType("ad_once").", ".PacketManagement::getType("ad_abo").")");
	$tpl_content->addvar("ads_free", $ads_free);
}

/* Count unpaid invoices */
$num_invoices  = $db->fetch_atom("
		SELECT COUNT(*)
		FROM billing_invoice
		WHERE FK_USER = ".$uid." AND STATUS = 0");
$tpl_content->addvar('num_invoices', $num_invoices);

# Rechnungen
require_once $ab_path . 'sys/lib.billing.invoice.php';
$billingInvoiceManagement = BillingInvoiceManagement::getInstance($db);
$invoices  = $billingInvoiceManagement->fetchAllByParam(array(
    'FK_USER' => $uid,
    'STATUS' => 0,
    'LIMIT' => 5
));

$tpl_content->addvar("INVOICE_HIDE_CHECKBOX", TRUE);
$tpl_content->addlist("invoices", $invoices, "tpl/".$s_lang."/invoices.row.htm");


## ad agent count
$num_ad_agents = $db->fetch_atom("SELECT COUNT(*) FROM ad_agent WHERE FK_USER = ".$uid.";");
$tpl_content->addvar("num_ad_agents", $num_ad_agents);
/*
## ad agents list
$ad_agents = $db->fetch_table("
  	SELECT
  		a.*,
  		string_kat.V1 as SEARCH_KAT_TXT,
  		man.NAME as SEARCH_MAN_TXT,
  		user.NAME as SEARCH_USER_TXT
  	FROM `ad_agent` a
  		LEFT JOIN `kat` kat
  			ON kat.ID_KAT=a.SEARCH_KAT
  		LEFT JOIN `string_kat` string_kat
  			ON string_kat.FK=a.SEARCH_KAT and string_kat.S_TABLE='kat' and
  				 string_kat.BF_LANG=if(kat.BF_LANG_KAT & ".$langval.", ".$langval.", 1 << floor(log(kat.BF_LANG_KAT+0.5)/log(2)))
  		LEFT JOIN `manufacturers` man
  			ON man.ID_MAN=a.SEARCH_MAN
  		LEFT JOIN `user` user
  			ON user.ID_USER=a.SEARCH_USER
  	WHERE
  		a.FK_USER=".$uid."
  	ORDER BY
  		LAST_RUN DESC
  	LIMIT 5");
  $tpl_content->addlist("liste_agents", $ad_agents, "tpl/".$s_lang."/my-pages.agents.htm");
*/
### bewertungen

### Zeige Startseite (Info-Page) falls der Kunde keine Inhalte hat.
if(count($userOrders)) {
    $userWelcomeFlag=false;
}
if($NEW_MAILS_AD>0){
    $userWelcomeFlag=false;
}
if($num_ad_agents>0){
    $userWelcomeFlag=false;
}
if($num_trades>0){
    $userWelcomeFlag=false;
}
if($num_offers>0){
    $userWelcomeFlag=false;
}
if($num_club_invites>0){
    $userWelcomeFlag=false;
}
if($num_club_member_requests>0){
    $userWelcomeFlag=false;
}
if($num_ads_going_down>0){
    $userWelcomeFlag=false;
}
if($num_invoices>0){
    $userWelcomeFlag=false;
}
$tpl_content->addvar('userWelcomeFlag', $userWelcomeFlag);


#### Kommentare
$tpl_content->addvar("showcomments",$nar_systemsettings['MARKTPLATZ']['ALLOW_COMMENTS_AD']);
require_once $ab_path."sys/lib.comment.php";
require_once $ab_path."sys/lib.calendar_event.php";
$commentManagement = CommentManagement::getInstance($db);
$calendarManagement = CalendarEventManagement::getInstance($db);
$num_comments = $commentManagement->fetchCountByParams( array("FK_USER_OWNER" => $uid) );
$num_events = $calendarManagement->countByParam( array("FK_USER_MOD" => $uid, "IS_CONFIRMED" => 0) );
$tpl_content->addvar("num_comments", $num_comments);
$tpl_content->addvar("num_events", $num_events);
#### Kommentare anzeigen ENDE
?>
