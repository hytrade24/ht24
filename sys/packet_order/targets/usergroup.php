<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path."sys/lib.ads.php";
require_once $ab_path."sys/packet_management.php";

class PacketTargetUsergroup {

	public static function updateRoles($id_user) {
		global $db, $langval;
		# Standard-Rolle auslesen
		$fk_usergroup_new = $db->fetch_atom("SELECT ID_USERGROUP FROM `usergroup` WHERE IS_DEFAULT=1");
		$ID_MODULOPTION = $db->fetch_atom("select ID_MODULOPTION from `moduloption` where OPTION_VALUE='DEFAULT_ROLE'");
		$role_def = $db->fetch_atom("select s.V1 from `moduloption` t left join string_opt s on s.S_TABLE='moduloption' and s.FK=t.ID_MODULOPTION and s.BF_LANG=if(t.BF_LANG_OPT & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_OPT+0.5)/log(2))) where S_TABLE='moduloption' AND FK=".$ID_MODULOPTION); # Default Rolle ermittelt
		$role_check = $db->fetch_atom("select ID_ROLE from `role` where ID_ROLE=".$role_def);
		# Überprüfen, ob es diese Rolle wirklich gibt..
		if (($role_check > 0) && ($id_user > 0) && ($fk_usergroup_new > 0)) {
			## Vorherigen rollen auslesen
			$ar_roles_prev = array_keys($db->fetch_nar("SELECT FK_ROLE FROM `role2user` WHERE FK_USER=".(int)$id_user));
			## Aktuelle rollen auslesen
			$ar_roles_cur = array($role_def);
			$ar_usergroups_active = $db->fetch_nar("
				SELECT ID_PACKET_ORDER, PARAMS FROM `packet_order`
				WHERE TYPE IN ('GROUP','GROUP_ABO') AND (STATUS&1)=1 AND FK_USER=".(int)$id_user);
			foreach ($ar_usergroups_active as $id_packet_order => $id_usergroup) {
				$ar_roles = $db->fetch_nar("SELECT FK_ROLE, FK_USERGROUP FROM `usergroup_role` WHERE FK_USERGROUP=".(int)$id_usergroup);
				foreach ($ar_roles as $fk_role => $fk_usergroup) {
					if (!in_array($fk_role, $ar_roles_cur)) {
						$ar_roles_cur[] = $fk_role;
					}
					$fk_usergroup_new = $fk_usergroup;
				}
			}
			## Nicht mehr verfügbare Rollen entfernen
			foreach ($ar_roles_prev as $index => $id_role) {
				## Admin hack
				if (($id_role != 2) && !in_array($id_role, $ar_roles_cur)) {
					## Rolle nicht mehr verfügbar!
					$db->querynow("delete from `role2user` where FK_USER=".(int)$id_user." and FK_ROLE=".$id_role.";");
				}
			}
			## Neue rollen hinzufügen
			foreach ($ar_roles_cur as $index => $id_role) {
				## Admin hack
				if (($id_role != 2) && !in_array($id_role, $ar_roles_prev)) {
					## Rolle neu hinzu gekommen!
					$db->querynow("insert into role2user set FK_USER=".(int)$id_user.", FK_ROLE=".$id_role);
				}
			}

			// Einstellung Rechnung Sofort stellen
      $arUsergroup = $db->fetch1("SELECT PREPAID, PROV_PREPAID FROM `usergroup` WHERE ID_USERGROUP=".$fk_usergroup_new);
			$chargeAtOnce = $arUsergroup["PREPAID"];
			$chargeAtOnceProv = $arUsergroup["PROV_PREPAID"];


			## Rechte neu schreiben
			$db->querynow("UPDATE `user` SET FK_USERGROUP=".$fk_usergroup_new.",SER_PAGEPERM=null, SER_KATPERM=null where ID_USER=".(int)$id_user);
			$db->querynow("UPDATE `usercontent` SET  CHARGE_AT_ONCE = '".(int)$chargeAtOnce."', PROV_PREPAID='".(int)$chargeAtOnceProv."' where FK_USER=".(int)$id_user);
			resetUserPerms($id_user);

            // Nicht zugängliche Anzeigenpakete kündigen
            $packets = PacketManagement::getInstance($db);
            $packets->cancelUnavaiablePackets($id_user);

			return true;
		}
		return false;
	}

	/**
	 * Mitgliedschaft aktivieren
	 *
	 * @param int     $id_packet_order
	 */
	public static function activate($db, $id_packet_order, $id_user) {
		// Update user roles
		if (self::updateRoles($id_user)) {
			return true;
		} else {
			// Reset status
			$db->querynow("UPDATE `packet_order` SET STATUS=STATUS-(STATUS&1) WHERE ID_PACKET_ORDER=".$id_packet_order);
			return false;
		}
	}

	/**
	 * Mitgliedschaft deaktivieren
	 *
	 * @param int     $id_packet_order
	 */
	public function deactivate($db, $id_packet_order, $id_user) {
		// Update user roles
		if (self::updateRoles($id_user)) {
			return true;
		} else {
			// Reset status
			$db->querynow("UPDATE `packet_order` SET STATUS=(STATUS|1) WHERE ID_PACKET_ORDER=".$id_packet_order);
			return false;
		}
	}
}

?>