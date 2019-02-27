<?php

require_once $ab_path."sys/lib.packet.membership.upgrade.php";
require_once $ab_path."sys/packet_management.php";
require_once $ab_path."sys/lib.user.php";

$packetMembershipUpgradeManagement = PacketMembershipUpgradeManagement::getInstance($db);
$userManagement = UserManagement::getInstance($db);
$packetManagement = PacketManagement::getInstance($db);

$perpage = 25; // Elemente pro Seite
$limit = ((($_REQUEST['npage'] ? $_REQUEST['npage'] : $_REQUEST['npage'] = 1) - 1) * $perpage);
$action = $_REQUEST["action"];
$param = array();

if($action == 'approve' && isset($_REQUEST['ID_PACKET_MEMBERSHIP_UPGRADE'])) {
    $result = $packetMembershipUpgradeManagement->approveUpgrade($_REQUEST['ID_PACKET_MEMBERSHIP_UPGRADE']);
    if($result) {
        $tpl_content->addvar('approved', 1);
    } else {
        $tpl_content->addvar('err', 1);
    }
} elseif($action == 'decline' && isset($_REQUEST['ID_PACKET_MEMBERSHIP_UPGRADE'])) {
    $result = $packetMembershipUpgradeManagement->declineUpgrade($_REQUEST['ID_PACKET_MEMBERSHIP_UPGRADE']);
    if($result) {
        $tpl_content->addvar('declined', 1);
    } else {
        $tpl_content->addvar('err', 1);
    }
}


$userWithUpgradeRequests = $packetMembershipUpgradeManagement->fetchAllByParam(array(
    'STATUS' => PacketMembershipUpgradeManagement::STATUS_OPEN,
    'LIMIT' => $perpage,
    'OFFSET' => $limit
));

$numberOfUsersWithUpgradeRequests = $packetMembershipUpgradeManagement->countByParam(array(
    'STATUS' => PacketMembershipUpgradeManagement::STATUS_OPEN
));

$tplRequests = array();
foreach($userWithUpgradeRequests as $key => $userWithUpgradeRequest) {
    $tmpUser = $userManagement->fetchById($userWithUpgradeRequest['FK_USER']);
    $oldMembership = $packetManagement->getActiveMembershipByUserId($userWithUpgradeRequest['FK_USER']);
    $newMembership = $packetManagement->getFull($userWithUpgradeRequest['FK_PACKET_RUNTIME']);

    $tplRequests[$key] = array_merge(
        $userWithUpgradeRequest,
        array_prefix_key($tmpUser, 'USER_'),
        array('OLD_MEMBERSHIP_V1' => (($oldMembership !== NULL)?$oldMembership->getPacketName():'')),
        array_prefix_key($newMembership, 'NEW_MEMBERSHIP_')
    );
}

$tpl_content->addlist('liste', $tplRequests,  'tpl/de/user_unlock.row.htm');
$tpl_content->addvar("all", $numberOfUsersWithUpgradeRequests);
$tpl_content->addvar("pager", htm_browse($numberOfUsersWithUpgradeRequests, $_REQUEST['npage'], "index.php?page=".$tpl_content->vars['curpage']."&".http_build_query($param)."&npage=", $perpage));
