<?php
/* ###VERSIONSBLOCKINLCUDE### */


  $groups = $db->fetch_table("SELECT
  		g.*, s.V1, s.V2, s.T1,
  		(select count(*) from user where FK_USERGROUP=ID_USERGROUP AND STAT = 1) as anzahl,
  		(SELECT count(*) FROM `packet_collection`
  				WHERE FK_PACKET IN (SELECT ID_PACKET FROM `packet` WHERE TYPE IN ('GROUP', 'GROUP_ABO'))
  					AND PARAMS=g.ID_USERGROUP) as anzahl_mitgliedschaften
  	FROM `usergroup` g
  		LEFT JOIN `string_usergroup` s ON
        s.FK=g.ID_USERGROUP AND s.S_TABLE='usergroup' AND
        s.BF_LANG=if(g.BF_LANG_USERGROUP & ".$langval.", ".$langval.", 1 << floor(log(g.BF_LANG_USERGROUP+0.5)/log(2)))
    ORDER BY g.F_ORDER ASC
       ");

  $tpl_content->addlist("liste", $groups, "tpl/de/usergroups.row.htm");

  if (!empty($_REQUEST["delete"])) {
  	// Delete group
  	$id_usergroup = (int)$_REQUEST["delete"];
  	if ($id_usergroup > 0) {
  		$groupUsers = $db->fetch_atom("SELECT count(*) FROM `user` WHERE FK_USERGROUP=".$id_usergroup);
  		$groupPackets = $db->fetch_atom("SELECT count(*) FROM `packet_collection`
  				WHERE FK_PACKET IN (SELECT ID_PACKET FROM `packet` WHERE TYPE IN ('GROUP', 'GROUP_ABO'))
  					AND PARAMS='".$id_usergroup."'");
  		if ($groupUsers > 0) {
  			$err[] = 'Dieser Benutzergruppe sind derzeit noch '.$groupUsers.' Benutzer zugeordnet.
  					Bitte ändern Sie die Benutzergruppe der Benutzer bevor Sie die Benutzergruppe löschen.';
  		}
  		if ($groupPackets > 0) {
  			$err[] = 'Für diese Benutzergruppe sind derzeit noch in '.$groupPackets.' Mitgliedschaften vorhanden.
  					Bitte löschen Sie diese zunächst bevor Sie die Benutzergruppe löschen.';
  		}
  		if (empty($err)) {
  			// Preise etc. zur Benutzergruppe löschen
	  		$db->querynow("DELETE FROM `advertisement_kat_price` WHERE FK_USERGROUP=".$id_usergroup);
	  		$db->querynow("DELETE FROM `packet_group` WHERE FK_USERGROUP=".$id_usergroup);
	  		$db->querynow("DELETE FROM `packet_price` WHERE FK_USERGROUP=".$id_usergroup);
	  		$db->querynow("DELETE FROM `provsatz` WHERE FK_USERGROUP=".$id_usergroup);
  			// Benutzergruppe und Rollenzuordnungen löschen
	  		$db->querynow("DELETE FROM `usergroup` WHERE ID_USERGROUP=".$id_usergroup);
	  		$db->querynow("DELETE FROM `string_usergroup` WHERE S_TABLE='usergroup' AND FK=".$id_usergroup);
	  		$db->querynow("DELETE FROM `packet_group` WHERE FK_USERGROUP=".$id_usergroup);
	        $db->querynow("DELETE FROM `usergroup_role` WHERE FK_USERGROUP=".$id_usergroup);
	  		die(forward("index.php?page=usergroups"));
  		} else {
  			$tpl_content->addvar("err", ' - '.implode('<br /> - ', $err));
  		}
  	}
  } elseif (isset($_REQUEST["action"]) && $_REQUEST["action"] == 'sort') {

  		foreach($_REQUEST['F_ORDER'] as $key => $value) {
  			$db->update("usergroup", array(
  				'ID_USERGROUP' => (int)$key,
  				'F_ORDER' => (int)$value
  			));
  		}

  		die(forward("index.php?page=usergroups&npage=".$curpage));

  }

  if ($_REQUEST["update"] == "ok") {
    // Update / Insert successfull
    $tpl_content->addvar("update_ok", 1);
  }
?>