<?php
/* ###VERSIONSBLOCKINLCUDE### */

function addUsergroups(&$row, $i)
{
    global $db, $s_lang, $tpl_modul, $nar_systemsettings;

    $ar_usergroups = array_keys(
        $db->fetch_nar(
            "SELECT PARAMS
                    FROM `packet_collection` WHERE ID_PACKET=" . $row["ID_PACKET"] . " AND
			FK_PACKET IN (" . PacketManagement::getType("usergroup_once") . ", " . PacketManagement::getType(
                "usergroup_abo"
            ) . ")"
        )
    );
    $row["RECURRING_" . $row["RECURRING"]] = 1;
    $row["FK_USERGROUP"] = $ar_usergroups['0'];
    $row["IS_FREE"] = 1;

    if (is_array($row["RUNTIMES"])) {
        $ar_liste = array();
        foreach ($row["RUNTIMES"] as $index => $ar_row) {
            if ($ar_row["BILLING_PRICE"] > 0) {
                $row["IS_FREE"] = 0;
            }
            $ar_row["RUNTIME_NUM"] = $ar_row["BILLING_FACTOR"] * $ar_row["RUNTIME_FACTOR"];
            $ar_row["CYCLE_" . $ar_row["BILLING_CYCLE"]] = 1;
            $tpl_tmp = new Template("module/tpl/" . $s_lang . "/register.row_runtime.htm", $tpl_modul->table);
            $tpl_tmp->addvar('CURRENCY_DEFAULT', $nar_systemsettings['MARKTPLATZ']['CURRENCY']);
            $tpl_tmp->addvar('FK_PACKET_RUNTIME', $_POST['FK_PACKET_RUNTIME']);
            $tpl_tmp->addvar('i', $index);
            $tpl_tmp->addvar('even', 1 - ($index & 1));
            $tpl_tmp->addvars($ar_row);
            $ar_liste[] = $tpl_tmp;
        }
        $row["RUNTIMES"] = $ar_liste;
    } else {
        $row["RUNTIMES"] = false;
    }
}

// Session für Käufe prüfen und ggf. Daten übernehmen (E-Mail-Adresse)
if (!$uid) {
    list($accessUser, $accessHash) = explode("!", $_SESSION['TRADER_USER_ACCESS_HASH']);
    $accessCheck = $db->fetch_atom("SELECT MD5(CONCAT(NAME,SALT,EMAIL)) FROM `user` WHERE ID_USER=".(int)$accessUser);
    if (($accessUser > 0) && ($accessCheck == $accessHash)) {
        $uid = (int)$accessUser;
    }
} else {
    die(forward("/user-welcome/"));
}

include $ab_path . "sys/lib.usercreate.php";
require_once $ab_path . "sys/packet_management.php";
require_once $ab_path . "sys/lib.user.authentication.php";

$packets = PacketManagement::getInstance($db);
$userAuthenticationManagement = UserAuthenticationManagement::getInstance($db);

$ar_invite = false;
if (isset($ar_params[1])) {
    $ar_invite = $db->fetch1(
        "SELECT * FROM `club_invite` WHERE CODE='" . mysql_real_escape_string($ar_params[1]) . "'"
    );
    if ($ar_invite !== false) {
        unset($ar_invite["NAME"]);
        $tpl_modul->addvars($ar_invite);
    }
}

if ($nar_systemsettings["SITE"]["FORUM_VB"]) {
    // vBulletin-Forum wird integriert
    require_once 'sys/lib.forum_vb.php';
    $apiForum = new ForumVB();

    // Regeln auslesen
    $html_rules = $apiForum->GetForumRules();
    $tpl_modul->addvar("forum_rules", $html_rules);
}

if ($uid > 0) {
    $arUser = $db->fetch1("SELECT * FROM `user` WHERE ID_USER=".$uid);
    $tpl_modul->addvars($arUser);
}

if (array_key_exists('REGISTER_PACKET', $_SESSION)) {
    $tpl_modul->addvar("SELECT_PACKET_RUNTIME", (int)$_SESSION['REGISTER_PACKET']);
    unset($_SESSION['REGISTER_PACKET']);
}

$usergroupsBase = array();
$usergroups = $db->fetch_table(
    "
              SELECT
                  g.*, s.V1, s.V2, s.T1
              FROM `usergroup` g
                  LEFT JOIN
                    `string_usergroup` s ON
                        s.FK=g.ID_USERGROUP AND s.S_TABLE='usergroup' AND
                        s.BF_LANG=if(g.BF_LANG_USERGROUP & " . $langval . ", " . $langval . ", 1 << floor(log(g.BF_LANG_USERGROUP+0.5)/log(2)))
		WHERE g.IS_AVAILABLE=1
		ORDER BY g.F_ORDER ASC
		"
);

foreach ($usergroups as $usergroupIndex => $usergroupDetail) {
    $usergroupNameParts = explode(" ", $usergroupDetail["V1"]);
    $usergroupNameBase = implode(" ", array_slice($usergroupNameParts, 0, -1));
    if (!array_key_exists($usergroupNameBase, $usergroupsBase)) {
        $usergroupsBase[$usergroupNameBase] = [];
    }
    $usergroupsBase[$usergroupNameBase][] = $usergroupDetail["V1"];
}

$ar_packet_content = array();
$all = 0;
$memberships = $packets->getList(
    1,
    256,
    $all,
    array("TYPE='MEMBERSHIP'", "(STATUS&1)=1"),
    array("F_ORDER ASC", "TYPE ASC", "V1 ASC"),
    false,
    true
);

// Gutschein codes
$couponMembershipIds = array();
if ($nar_systemsettings["MARKTPLATZ"]["COUPON_ENABLED"]) {
    $tpl_modul->addvar("OPTION_COUPON_ENABLED", 1);

    $tpl_modul->addvar('COUPON_WIDGET_TARGET_TYPE', 'PACKET');
    $tpl_modul->addvar('COUPON_WIDGET_TARGETS', $allPacketRuntimeIds);
    $tpl_modul->addvar('COUPON_WIDGET_TARGETS_JSON', json_encode($allPacketRuntimeIds));
    
    if (!empty($ar_params[2])) {
        $_SESSION['COUPON_CODE'] = $ar_params[2];
    }
    if (array_key_exists('COUPON_CODE', $_POST)) {
        $_SESSION['COUPON_CODE'] = $_POST['COUPON_CODE'];
    }

    if (array_key_exists('COUPON_CODE', $_SESSION)) {
        $couponManagement = Coupon_CouponManagement::getInstance($db);
        $coupon = $couponManagement->fetchCouponByCode($_SESSION['COUPON_CODE']);
        if ($coupon["TYPE"] == "Coupon_Type_RegisterMembershipCouponType") {
            $couponConfig = unserialize($coupon["TYPE_CONFIG"]); 
            $couponMembershipIds = $couponConfig["COUPON_MEMBERSHIP"];
            foreach ($couponMembershipIds as $couponMembershipIndex => $couponMembershipId) {
                $couponMembership = $packets->getFull($couponMembershipId);
                $taxPercent = $db->fetch_atom("SELECT TAX_VALUE FROM `tax` WHERE ID_TAX=".$couponMembership["FK_TAX"]);
    			$tax = (100 + $taxPercent) / 100;
                $couponMembershipAdded = false;
                foreach ($memberships as $membershipIndex => $membershipDetails) {
                    if ($membershipDetails["ID_PACKET"] == $couponMembership["ID_PACKET"]) {
                        // New runtime
                        $memberships[$membershipIndex]["RUNTIMES"][] = array(
                            "ID_PACKET_RUNTIME"     => $couponMembership["ID_PACKET_RUNTIME"],
                            "FK_PACKET"             => $couponMembership["FK_PACKET"],
                            "BILLING_FACTOR"        => $couponMembership["BILLING_FACTOR"],
                            "BILLING_CYCLE"         => $couponMembership["BILLING_CYCLE"],
                            "BILLING_CANCEL_DAYS"   => $couponMembership["BILLING_CANCEL_DAYS"],
                            "RUNTIME_FACTOR"        => $couponMembership["RUNTIME_FACTOR"],
                            "BILLING_PRICE"         => $couponMembership["BILLING_PRICE"],
                            "BILLING_PRICE_BRUTTO"  => round($couponMembership["BILLING_PRICE"] * 100 * $tax) / 100,
                            "COUPON_RUNTIME"        => true
                        );
                        break;
                    }
                }
                if (!$couponMembershipAdded) {
                    // New membership
                    if ($couponMembership["SER_OPTIONS"] !== NULL) {
                        $couponMembership["OPTIONS"] = @unserialize($couponMembership["SER_OPTIONS"]);
                    }
                    $couponMembership["RUNTIMES"] = $ar_runtimes;
                    $couponMembership["TAX_PERCENT"] = $taxPercent;
                    $couponMembership["RUNTIMES"] = array(array(
                        "ID_PACKET_RUNTIME"     => $couponMembership["ID_PACKET_RUNTIME"],
                        "FK_PACKET"             => $couponMembership["FK_PACKET"],
                        "BILLING_FACTOR"        => $couponMembership["BILLING_FACTOR"],
                        "BILLING_CYCLE"         => $couponMembership["BILLING_CYCLE"],
                        "BILLING_CANCEL_DAYS"   => $couponMembership["BILLING_CANCEL_DAYS"],
                        "RUNTIME_FACTOR"        => $couponMembership["RUNTIME_FACTOR"],
                        "BILLING_PRICE"         => $couponMembership["BILLING_PRICE"],
                        "BILLING_PRICE_BRUTTO"  => round($couponMembership["BILLING_PRICE"] * 100 * $tax) / 100,
                        "COUPON_RUNTIME"        => true
                    ));
                    $couponMembership["COUPON_MEMBERSHIP"] = true;
                    $memberships[] = $couponMembership;
                }
            }
            $couponMembershipId = $couponMembershipIds[0];
            // Select first coupon membership by default
            $tpl_modul->addvar("SELECT_PACKET_RUNTIME", $couponMembershipId);
            $tpl_modul->addvar("SELECT_PACKET_RUNTIME_SHOW_PACKETS", true);
        }
        $tpl_modul->addvar("COUPON_CODE", $_SESSION["COUPON_CODE"]);
    }
}

$membershipsByUsergroup = array();
$allPacketRuntimeIds = array();

foreach ($memberships as $key => $membership) {
    foreach($membership['RUNTIMES'] as $rkey => $rvalue) {
        $allPacketRuntimeIds[] = $rvalue['ID_PACKET_RUNTIME'];
    }


    addUsergroups($memberships[$key], 0);
    $membershipsByUsergroup[$memberships[$key]['FK_USERGROUP']][] = $memberships[$key];
    $ar_packet_content[$membership['ID_PACKET']] = $db->fetch_nar(
        "SELECT FK_PACKET, COUNT FROM `packet_collection` WHERE ID_PACKET=" . $membership['ID_PACKET']
    );
}

//.........
$table_heads = array();
$table_tds = array();
$table_T1 = array();
//.........

$usergroupMemberships = array();
$usergroupById = array();
foreach ($usergroups as $key => $usergroup) {
    $usergroups[$key]['COUNT_GROUPS'] = min(12, count($usergroups));
    $usergroupById[$usergroup['ID_USERGROUP']] = $usergroups[$key];
}

$all = 0;
$ar_packets = $packets->getList(
    1,
    50,
    $all,
    array("(TYPE='BASE' OR TYPE='BASE_ABO')", "(STATUS&1)=1"),
    array("F_ORDER ASC", "TYPE ASC", "V1 ASC")
);

//......
$is_td_added = true;
//..........

$usergroupTemplate = '';
foreach ($usergroups as $key => $usergroup) {
    $tpl_tmp = new Template("module/tpl/" . $s_lang . "/register.row_group.htm");
    
    $membershipTable = array(
        array(
            'TYP' => 'NAME',
            'TYP_N' => 0,
            'V1' => '',
            'COLS' => array()
        ),
        array(
            'TYP' => 'DESCRIPTION',
            'TYP_N' => 1,
            'V1' => '',
            'COLS' => array()
        )
    );

    $packetGroupedById = array();
    $packetGroupedByName = array();
    foreach ($ar_packets as $packetKey => $ar_packet) {
        $packetGroupedById[$ar_packet['ID_PACKET']] = $ar_packet;
        if (array_key_exists($ar_packet['V1'], $packetGroupedByName)) {
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
    $membershipTable[] = array(
        'V1' => get_messages('REGISTER', 'PACKET_RUNTIME'),
        'TYP' => 'RUNTIME',
        'TYP_N' => 3,
        'COLS' => array()
    );
    $membershipTable[] = array('V1' => '', 'TYP' => 'BUTTON', 'TYP_N' => 4, 'COLS' => array());
        
    $numberOfCols = count($membershipsByUsergroup[$usergroup['ID_USERGROUP']]) + 1;

    $rowTemplate = '';
    foreach ($membershipTable as $tKey => $membershipTableRow) {        
        $row_css_name = "design-register-row-".$membershipTableRow['TYP'];
        if (isset($membershipsByUsergroup[$usergroup['ID_USERGROUP']])) {
            foreach ($membershipsByUsergroup[$usergroup['ID_USERGROUP']] as $mKey => $membership) {
                switch ($membershipTableRow['TYP']) {
                    case 'NAME':
                        $membershipTable[$tKey]['COLS'][] = array('V1' => $membership['V1'], 'COUPON_MEMBERSHIP' => $membership["COUPON_MEMBERSHIP"]);
                        break;
                    case 'DESCRIPTION':
                        $membershipTable[$tKey]['COLS'][] = array('V1' => $membership['T1'], 'COUPON_MEMBERSHIP' => $membership["COUPON_MEMBERSHIP"]);
                        break;
                    case 'PACKET':
                        $packetTypeIds = $packetGroupedByName[$membershipTableRow['V1']];

                        $packetDisplay = 0;
                        $isAboPacket = false;
                        foreach ($packetTypeIds as $packetTypeId) {
                            if (isset($ar_packet_content[$membership["ID_PACKET"]][$packetTypeId])) {
                                $packetDisplay = $ar_packet_content[$membership["ID_PACKET"]][$packetTypeId];
                                if ($packetGroupedById[$packetTypeId]['TYPE'] == 'BASE_ABO') {
                                    $isAboPacket = true;
                                }
                                break;
                            }
                        }
                        $row_css_name .= " ".$row_css_name."-".$membershipTableRow['V1'];

                        $membershipTable[$tKey]['COLS'][] = array_merge(
                            $membership,
                            array(
                                'V1' => $packetDisplay,
                                'ABO_PACKET' => $isAboPacket,
                                'PACKET_NAME' => $membershipTableRow['V2']
                            )
                        );
                        break;
                    case 'PLUGIN':
                        $featureIdent = $membershipTableRow["FEATURE_IDENT"];
                        $arCol = array("V1" => "", 'COUPON_MEMBERSHIP' => $membership["COUPON_MEMBERSHIP"]);
                        if (is_array($membership["OPTIONS"]) && array_key_exists($featureIdent, $membership["OPTIONS"])) {
                            $arCol = array_merge($arCol, $membership["OPTIONS"][$featureIdent]);
                        }
                        if ($membershipTableRow["FEATURE_TPL"] !== NULL) {
                            $tplCol = new Template("tpl/".$language."/empty.htm");
                            $tplCol->tpl_text = $membershipTableRow["FEATURE_TPL"];
                            $tplCol->addvars($arCol);
                            $arCol = array("V1" => $tplCol, 'COUPON_MEMBERSHIP' => $membership["COUPON_MEMBERSHIP"]);
                        }
                        $membershipTable[$tKey]['COLS'][] = $arCol;
                        break;
                    case 'RUNTIME':
                        $runtimeTemplate = '';
                        foreach ($membership['RUNTIMES'] as $runtimeKey => $runtime) {
                            $runtime->addvars($membership);
                            $runtime->addvars($usergroup);
                            $runtimeTemplate .= $runtime->process();
                        }
                        $membershipTable[$tKey]['COLS'][] = array_merge($membership, array('V1' => $runtimeTemplate));

                        break;
                    case 'BUTTON':
                        $membershipTable[$tKey]['COLS'][] = array_merge(
                            $membership,
                            array('V1' => '', 'ID_USERGROUP' => $usergroup['ID_USERGROUP'])
                        );

                        break;
                }

            }
        } else {
            $tpl_tmp->addvar('NO_PACKETS', true);

        }
        $tpl_tmp_row = new Template("module/tpl/" . $s_lang . "/register.packet.row.htm");
        $tpl_tmp_row->addlist(
            'cols',
            $membershipTable[$tKey]['COLS'],
            "module/tpl/" . $s_lang . "/register.packet.col.htm"
        );
        //.............
        $tpl_tmp_col = new Template("tpl/de/empty.htm");
        $tpl_tmp_col->tpl_text = "{cols}";
        $tpl_tmp_col->addvar("TYP_N",$membershipTableRow["TYP_N"]);
	    if ( $membershipTableRow["TYP_N"] == 4 ) {
		    $private_access = $db->fetch_atom(
			    "SELECT PRIVATE 
                FROM `usergroup` 
                WHERE ID_USERGROUP=" . $usergroup["ID_USERGROUP"]
		    );
		    $tpl_tmp_col->addvar("PRIVATE",$private_access);
	    }
	    $tpl_tmp_col->addlist(
		    'cols',
		    $membershipTable[$tKey]['COLS'],
		    "module/tpl/" . $s_lang . "/register.packet.col.htm"
	    );
	    $table_tds[$usergroup["V1"]]["cols"][] = $tpl_tmp_col->process();
	    //.............
        $tpl_tmp_row->addvar("ROW_CSS", $row_css_name);
        $tpl_tmp_row->addvar("V1", (!empty($membershipTableRow["V2"]) ? $membershipTableRow["V2"] : $membershipTableRow["V1"]));
        $tpl_tmp_row->addvars($membershipTableRow);
        $tpl_tmp_row->addvars($usergroupById[$usergroup['ID_USERGROUP']]);
        $tpl_tmp_row->addvar('NUMBER_OF_COLS', $numberOfCols);

        $rowTemplate .= $tpl_tmp_row->process();

	    if ( $is_td_added ) {
		    $table_heads[] = (!empty($membershipTableRow["V2"]) ? $membershipTableRow["V2"] : $membershipTableRow["V1"]);
        }
    }
	$is_td_added = false;


    $tpl_tmp->addvar('rows', $rowTemplate);
    $tpl_tmp->addvars($usergroupById[$usergroup['ID_USERGROUP']]);
    $usergroupTemplate .= $tpl_tmp->process();

	$table_T1[] = $usergroupById[$usergroup['ID_USERGROUP']]["T1"];

}

$tpl_modul->addvar('COUNT_GROUPS', min(12, count($usergroups)));

$usergroup_content = "";
$usergroups = [];

foreach ($usergroupsBase as $usergroupBaseName => $usergroupBaseContents) {
    $all_rows = '';
    $temp = '<td></td>';
    for ( $i = 0; $i<3; $i++ ){
        $t = new Template("tpl/de/empty.htm");
        $t->tpl_text = "<td>{td}</td>";
        $t->addvar("td",$table_T1[$i]);
        $temp .= $t->process();
    }
    $t = new Template("module/tpl/".$s_lang."/register.tr.htm");
    $t->addvar("tr",$temp);
    $all_rows .= $t->process();
    $usergroupId = str_replace([" ", "ä", "ö", "ü"], ["-", "ae", "oe", "ue"], strtolower($usergroupBaseName));
    $usergroups[] = ["ID_USERGROUP" => $usergroupId, "V1" => $usergroupBaseName];
    $length = 0;
    foreach ($usergroupBaseContents as $usergroupFullIndex => $usergroupFullName) {
        $length = max( $length, count($table_tds[$usergroupFullName]["cols"]) );
    }
    for ( $i = 0; $i<$length; $i++ ) {
        $t = new Template("module/tpl/".$s_lang."/register.td.htm");
        $t->addvar("td", $table_heads[$i] );
        $tds = $t->process();
        foreach ($usergroupBaseContents as $usergroupFullIndex => $usergroupFullName) {
            $tds .= $table_tds[$usergroupFullName]["cols"][$i];
        }
        $t_row = new Template("module/tpl/".$s_lang."/register.tr.htm");
        $t_row->addvar("tr",$tds);
        $b = $t_row->process();
        $all_rows .= $b;
    }
    
    $tpl_tmp = new Template("module/tpl/" . $s_lang . "/register.row_group.htm");
    $tpl_tmp->addvar("rows",$all_rows);
    $tpl_tmp->addvar("ID_USERGROUP","-".$usergroupId);
    $tpl_tmp->addvar("IS_ACTIVE",1);
    $usergroup_content .= $tpl_tmp->process();
}

$tpl_modul->addlist('usergroups', $usergroups, 'module/tpl/' . $s_lang . '/register.row.htm');
$tpl_modul->addvar("usergroups_content",$usergroup_content);

#  $tpl_modul->addlist('memberships', $memberships, 'module/tpl/'.$s_lang.'/register.row_packet.htm', 'addUsergroups');

if (empty($ar_params[1])) {

    include_once 'module/register/inc.profile_check.php';

    $data = $db->fetch_blank('user');

	if(isset($_SESSION['REGISTER_SOCIAL_MEDIA']) && $userAuthenticationManagement->isSocialMediaLoginEnabled()) {
		$socialMediaProvider = json_decode($_SESSION['REGISTER_SOCIAL_MEDIA'], true);

		$socialMediaData = $socialMediaProvider['userProfile'];

		$data = array_merge($data, array(
				'NAME' => preg_replace("/\W/", '', $socialMediaData['displayName']),
				'VORNAME' => $socialMediaData['firstName'],
				'NACHNAME' => $socialMediaData['lastName'],
				'EMAIL' => $socialMediaData['email'],
				'STRASSE' => $socialMediaData['address'],
				'PLZ' => $socialMediaData['zip'],
				'ORT' => $socialMediaData['city']
		));

		$tpl_modul->addvar('REGISTER_SOCIAL_MEDIA', 1);
		$tpl_modul->addvar('REGISTER_SOCIAL_MEDIA_PROVIDER', $socialMediaProvider['provider']);
	}


    if (count($_POST)) // Falls Formular abgeschickt wurde...
    {
        if (!$uid) {
            $uid_mail = $db->fetch_atom("SELECT ID_USER FROM `user` WHERE EMAIL='".mysql_real_escape_string($_POST['EMAIL'])."' AND IS_VIRTUAL=1");
            if ($uid_mail > 0) {
                $uid = (int)$uid_mail;
            }
        }

        $err = array(); // Fehler in diesem Array sammeln
        recurse($_POST, '$value=trim($value);');
        $_POST['FK_COUNTRY'] = $_POST['land'];

        $membership_paid = false;
        if ($_POST['FK_USERGROUP'] > 0) {
            $id_usergroup = (int)$_POST['FK_USERGROUP'];
        }
        if ($_POST["FK_PACKET_RUNTIME"] > 0) {
            $id_packet_runtime = (int)$_POST["FK_PACKET_RUNTIME"];
            $ar_packet = $packets->getFull($id_packet_runtime);
            if (!in_array($id_packet_runtime, $couponMembershipIds) && !$ar_packet["STATUS"]) {
                $err[] = 'MEMBERSHIP_INVALID';
            }
            if (($ar_packet != null) && ($ar_packet["BILLING_PRICE"] > 0)) {
                $membership_paid = true;
            }
            $id_membership = (int)$db->fetch_atom(
                "SELECT PARAMS FROM `packet_collection`
                                WHERE ID_PACKET=" . $ar_packet["ID_PACKET"] . " AND FK_PACKET IN (" . $packets->getType(
                    "usergroup_once"
                ) . ", " . $packets->getType("usergroup_once") . ")"
            );
            if ($id_membership > 0) {
                $id_usergroup = $id_membership;
            }
        }
        #profile_check();

        $tmp = validate_nick($_POST['NAME']); // Eingegebenen Nickname überprüfen
        if ($tmp & 1) {
            $err[] = 'NAME_TOO_SHORT';
        }
        if ($tmp & 2) {
            $err[] = 'BAD_NAME';
        }
        if (!$tmp && (int)$db->fetch_atom(
                "select count(*) from user where NAME='" . mysql_real_escape_string($_POST['NAME']) . "' AND
                    (IS_VIRTUAL=0 OR EMAIL!='" . mysql_real_escape_string($_POST['EMAIL']) . "')"
            )
        ) {
            $err[] = 'NAME_EXISTS';
        } else {
            if (!$tmp && (int)$db->fetch_atom(
                    "select count(*) from user where EMAIL='" . mysql_real_escape_string($_POST['EMAIL']) . "' AND IS_VIRTUAL=0"
                )
            ) {
                $err[] = 'EMAIL_EXISTS';
            }
        }

        if ($membership_paid) {
            // Mitgliedschaft ist kostenpflichtig! Anschrift erforderlich!
            if (empty($_POST["VORNAME"]) || empty($_POST["NACHNAME"]) || empty($_POST["STRASSE"])
                || empty($_POST["PLZ"]) || empty($_POST["ORT"]) || empty($_POST["FK_COUNTRY"])
            ) {
                $err[] = 'ERR_REQUIRED_FELDS';
            }
        }

        if ($_POST['pass1'] == "") // Verschiedene Passwortabfragen
        {
            $err[] = 'NO_PASS';
        }

        if ($_POST['AGB'] == "") // Verschiedene Passwortabfragen
        {
            $err[] = 'noAGB';
        }


        if (!secure_question($_POST)) {
            $err[] = 'secQuestion';
        }


        if (strlen($_POST['pass1']) < 6) {
            $err[] = 'PASS_TOO_SHORT';
        } else {
            if ($_POST['pass1'] && ($_POST['pass1'] != $_POST['pass2'])) {
                $err[] = 'PASS_REPEAT';
            } elseif ($_POST['pass1']) {
                $salt = pass_generate_salt();
                $_POST['SALT'] = $salt;
                $_POST['PASS'] = pass_encrypt($_POST['pass1'], $salt);
            }
        } // ENDE Passwortabfragen

        if (strlen($_POST['VORNAME']) == 0) {
            $err[] = 'NO_FIRST_NAME';
        }

        if (strlen($_POST['NACHNAME']) == 0) {
            $err[] = 'NO_LAST_NAME';
        }

        $private_access = $db->fetch_atom("SELECT PRIVATE FROM `usergroup` WHERE ID_USERGROUP=" . $id_usergroup);
        $_POST["PRIVATE"] = $private_access;
        if ($private_access > 0) {
            if (($private_access == 1) && ($_POST["ACCEPT_PRIVATE"] != 1)) {
                $err[] = 'ACCEPT_PRIVATE';
            }
            if (($private_access == 2) && ($_POST["ACCEPT_COMPANY"] != 1)) {
                $err[] = 'ACCEPT_COMPANY';
            }
        }

        #date_implode ($_POST,'GEBDAT'); // Felder für Geburtsdatum für Datenbank zusammenf�gen

        # Informationen �ber Default-Rolle holen
        $ID_MODULOPTION = $db->fetch_atom("SELECT ID_MODULOPTION FROM `moduloption` WHERE OPTION_VALUE='DEFAULT_ROLE'");

        $role = $db->fetch_atom(
            "select s.V1 from `moduloption` t left join string_opt s on s.S_TABLE='moduloption' and s.FK=t.ID_MODULOPTION and s.BF_LANG=if(t.BF_LANG_OPT & " . $langval . ", " . $langval . ", 1 << floor(log(t.BF_LANG_OPT+0.5)/log(2))) where S_TABLE='moduloption' AND FK=" . $ID_MODULOPTION
        ); # Default Rolle ermittelt
        $role_check = $db->fetch_atom(
            "select ID_ROLE from `role` where ID_ROLE=" . $role
        ); # �berpr�fen, ob es diese Rolle wirklich gibt..

        if (!$role_check) # Falls die Rolle nicht existiert, Fehler generieren
        {
            $err[] = 'NO_ROLE';
            sendMailTemplateToUser(0, $nar_systemsettings['SUPPORT']['SP_EMAIL'], 'ERROR_DEFAULTUSER', $_POST);
        }
        if (empty($err) && $nar_systemsettings["SITE"]["FORUM_VB"]) {
            $result = $apiForum->RegisterUser($_POST['NAME'], $_POST['EMAIL'], $_POST["pass1"]);
            if (($result["response"]["errormessage"][0] == "registeremail")
                || ($result["response"]["errormessage"][0] == "moderateuser")
                || ($result["response"]["errormessage"][0] == "registration_complete")
            ) {
                // Registrierung erfolgreich
                $_POST["VB_USER"] = $apiForum->GetUserId($_POST['NAME']);
            } else {
                // Registrierung fehlgeschlagen
                $err[] = "EMAIL_EXISTS";
            }
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
        
        $eventParams = new Api_Entities_EventParamContainer(array("data" => $_POST, "errors" => $err));
        $apiHandler->triggerEvent(Api_TraderApiEvents::USER_REGISTER_CHECK, $eventParams);
        if ($eventParams->isDirty()) {
            $_POST = $eventParams->getParam("data");
            $err = $eventParams->getParam("errors");
        }

        if (count($err)) // Falls Fehlermeldungen generiert wurden...
        {

            $err = implode(",", $err);
            $err = get_messages("register", $err);
            #die(ht(dump($err)));
            $tpl_modul->addvar('err', implode('<br />- ', $err)); // Diese im Template ausgeben
            $data = array_merge($data, $_POST);
        } else // Ansonsten...
        {
            if ($uid > 0) {
                // Existierenden virtuellen user registrieren
                $_POST['ID_USER'] = $uid;
            } else {
                $salt = pass_generate_salt();
                $_POST['SALT'] = $salt;
                $_POST['IS_VIRTUAL'] = 0;
            }
            $_POST['FK_LANG'] = $lang_list[$s_lang]['ID_LANG'];
            $_POST['FK_USERGROUP'] = $db->fetch_atom("SELECT ID_USERGROUP FROM `usergroup` WHERE IS_DEFAULT=1");
            $_POST['STAMP_REG'] = date('Y-m-d'); // Datum der Anmeldung
            $_POST['PASS'] = pass_encrypt($_POST['pass1'], $salt);

            if ($bf_stat = $nar_systemsettings['USER']['REGCONFIRM']) // Bestätigungscode generieren
            {
                $_POST['CODE'] = createpass();
            }
            $_POST['STAT'] = ($nar_systemsettings['USER']['REGCHECK'] ? 0 : ($nar_systemsettings['USER']['REGCONFIRM'] ? 2 : 1));
            if (!$_POST['LANGVAL']) {
                $_POST['LANGVAL'] = $langval;
            }
            if (isset($_COOKIE["register_sale_code"])) {
                require_once $ab_path."sys/lib.sales.php";
                $salesManagment = SalesManagement::getInstance();
                $_POST['FK_USER_SALES'] = $salesManagment->getUserByRegisterCode($_COOKIE["register_sale_code"]);
                $_POST['FK_SALES_CODE'] = $salesManagment->getIdByRegisterCode($_COOKIE["register_sale_code"]);
            }
            //.......................
	        $mapsLanguage = $s_lang;
	        $street  = mysql_real_escape_string($_POST["STRASSE"]);
	        $zip  = mysql_real_escape_string($_POST["PLZ"]);
	        $city  = mysql_real_escape_string($_POST["ORT"]);

	        if ( $street != "" && $zip != "" && $city != "" && $_POST["FK_COUNTRY"] != "" ) {
		        $q_country = '
                  SELECT s.V1
					FROM country c
					INNER JOIN string s
					ON c.ID_COUNTRY = '.mysql_real_escape_string($_POST["FK_COUNTRY"]).'
					AND s.S_TABLE = "COUNTRY"
					AND s.FK = c.ID_COUNTRY
					INNER JOIN lang l
					ON l.ABBR = "'.$mapsLanguage.'"
					AND s.BF_LANG = l.BITVAL';
		        $country  = $db->fetch_atom($q_country);

		        $geoCoordinates = Geolocation_Generic::getGeolocationCached($street, $zip, $city, $country, $mapsLanguage);
		        $_POST["LATITUDE"] = $geoCoordinates["LATITUDE"];
		        $_POST["LONGITUDE"] = $geoCoordinates["LONGITUDE"];
            }
            if ($id = $db->update('user', $_POST)) // Alle Angaben und Informationen in Datenbank Table "user" eintragen
            {
                if ($uid > 0) {
                    // ID des virtuellen Users verwenden
                    $id = $uid;
                } else {
                    // ID für des neuen Users annehmen (für Gutscheine)
                    $uid = $id;
                }
                if ($couponCode !== null) {
                    $couponUsage = $couponManagement->useCouponCode($couponCode)->couponUsage;

                    if($couponUsage['USAGE_STATE'] == Coupon_CouponUsageManagement::USAGE_STATE_ACTIVATED && $couponUsageManagement->isCouponsUsageCompatible($couponUsage, $couponTargetType, $couponTargets)) {
                        $_POST['FK_COUPON_CODE_USAGE'] = $couponUsage['ID_COUPON_CODE_USAGE'];
                        $db->querynow("UPDATE `user` SET FK_COUPON_CODE_USAGE=".$couponUsage['ID_COUPON_CODE_USAGE']." WHERE ID_USER=".$id);
                    }
                }
                $db->querynow(
                    "update nl_recp set FK_USER=" . $id . ", STAMP=null
  				where EMAIL='" . mysql_escape_string($_POST['EMAIL']) . "' and FK_USER is null"
                );

                createUser($id, $_POST);

                //echo ("update `user` set `LOGO` = 'cache/users/".$id."/no.gif', `LOGO_S` = 'cache/users/".$id."/no_s.gif' where `ID_USER` = ".$id);

                AddRole2User($role, $id);

				if(isset($_SESSION['REGISTER_SOCIAL_MEDIA']) && $socialMediaProvider != null && $userAuthenticationManagement->isSocialMediaLoginEnabled()) {
					$userAuthenticationManagement->createUserAuthenticationForUserId($id, $socialMediaProvider['provider'], $socialMediaProvider['userProfile']['identifier'], $socialMediaProvider['userProfile']);
					$userAuthenticationManagement->resetRegisterCookie();
				}

                // Trigger register event
                $apiHandler->triggerEvent(Api_TraderApiEvents::USER_REGISTER, array("id" => $id, "data" => $_POST));

                
                ### Add initial packet (if exists)
                if (($_POST['STAT'] == 1) && ($_POST["FK_PACKET_RUNTIME"] > 0)) {
        
                    // Gutscheincode
                    $couponCodeUsageId = (int)$db->fetch_atom("SELECT FK_COUPON_CODE_USAGE FROM `user` WHERE ID_USER=".(int)$id);
                    if($couponCodeUsageId > 0) {
                        $couponUsageManagement = Coupon_CouponUsageManagement::getInstance($db);
                        try {
                            $couponUsage = $couponUsageManagement->fetchActivatedCouponUsageByUserId($couponCodeUsageId, $id, 'PACKET', array($_POST["FK_PACKET_RUNTIME"]));
                            $db->querynow("UPDATE `user` SET FK_COUPON_CODE_USAGE=NULL WHERE ID_USER=".(int)$id);
                        } catch(Exception $e) {
                            eventlog("error", "Coupon-Code konnte nicht eingelöst werden!", $e->getMessage());
                            //$db->querynow("DELETE FROM `coupon_code_usage` WHERE ID_COUPON_CODE_USAGE=".(int)$couponCodeUsageId);
                            $db->querynow("UPDATE `user` SET FK_COUPON_CODE_USAGE=NULL WHERE ID_USER=".(int)$id);
                        }
                    }
        
                    // Paket bestellen
                    $packets->order($_POST["FK_PACKET_RUNTIME"], $id, 1, NULL, NULL, NULL, $couponUsage);
                    $db->querynow("UPDATE `user` SET FK_PACKET_RUNTIME=NULL WHERE ID_USER=".(int)$id);
                    $usrdata = $db->fetch1("select * from user where ID_USER=".(int)$id);
                }
                
                if ($bf_stat) {
                    $_POST['LINK_ID'] = $id;
                    sendMailTemplateToUser(0, $id, 'REGISTER_CONFIRM', $_POST); // Bestätigungsmail versenden


                    forward("/" . $tpl_modul->vars['curpage'] . ",confirm.htm"); // Auf Infoseite weiterleiten
                } else {
                    sendMailTemplateToUser(0, $id, 'REGISTER_CONFIRMED', array());

                    if ($nar_systemsettings['USER']['SEND_REGADMINMAIL'] == '1') {
                        sendMailTemplateToUser(0, 0, 'USER_REG_TO_ADMIN', $_POST);
                    }

                    forward("/" . $tpl_modul->vars['curpage'] . ",welcome," . $id . ".htm");
                }
            }
        }
    } else {
        $userAuthenticationManagement = UserAuthenticationManagement::getInstance($db);
        $enabledProviders = $userAuthenticationManagement->getHybridAuthProviders();

        foreach($enabledProviders as $providerName => $provider) {
            $enabledProviders[$providerName]['PROVIDERNAME'] = $providerName;
        }
        $tpl_modul->addlist('social_media_login_providers', $enabledProviders, 'tpl/'.$s_lang.'/register.social-media-provider.row.htm');
        $tpl_modul->addvar('SOCIAL_MEDIA_LOGIN_ENABLED', $userAuthenticationManagement->isSocialMediaLoginEnabled());
    }
    if (!isset($data['FK_USERGROUP'])) {
        $data['FK_USERGROUP'] = 0;
    }


    $tpl_modul->addvars($data);
    $tpl_modul->addvar(
        'langval',
        nar2select(
            'name="LANGVAL" id="langval"',
            (($tmp = $data['LANGVAL']) ? $tmp : $langval),
            $db->fetch_nar($db->lang_select('lang', 'BITVAL, LABEL') . 'where B_PUBLIC=1')
        )
    );
} else {
    if ($ar_params[1] == 'confirm') {
        $tpl_modul->addvar('confirm', 1);
        include $ab_path . 'module/register/confirm.php';
    }
    if ($ar_params[1] == 'welcome') {
        $tpl_modul->addvar("NAME", $db->fetch_atom("select NAME from user where ID_USER=" . (int)$ar_params[2]));
        $tpl_modul->addvar("welcome", 1);
    }
}
?>
