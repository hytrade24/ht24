<?php
/* ###VERSIONSBLOCKINLCUDE### */



# LÃ¶scht eine Rolle inkl Bild


function del_role($id) {
	global $db;
	
	if ($id > 3) // nur wenn kein Std Rolle gelÃ¶scht wird
{
		//    $nar_users = $db->fetch_nar('select FK_USER, FK_USER from FK_ROLE='. $id);
		// gelÃ¶scht berni
		

		$nar_users = $db->fetch_nar ( 'select FK_USER from role2user where FK_ROLE=' . $id );
		$s_users = implode ( ', ', $nar_users );
		// Zuordnungen loeschen
		$db->query ( "delete from perm2role where FK_ROLE=" . $id );
		$db->query ( "delete from pageperm2role where FK_ROLE=" . $id );
		$db->query ( "delete from katperm2role where FK_ROLE=" . $id );
		$db->query ( "delete from role2user where FK_ROLE=" . $id );
		if ($s_users)
			$db->query ( 'update user set SER_PAGEPERM=null, SER_KATPERM=null
        where ID_USER in (' . $s_users . ')' );
		$db->submit ();
		// Rolle loeschen
		$db->query ( "delete from role where ID_ROLE=" . $id );
		//if ($db->delete('role', $id))
		//{
		// Cache leeren
		@unlink ( '../cache/pageperm.' . $id . '.php' );
		@unlink ( '../cache/katperm.' . $id . '.php' );
		@unlink ( 'tpl/role' . $id . '.png' );
		//}
		$db->submit ();
		// perm2user aktualisieren
		if ($s_users) {
			
			echo 'select s.FK_PERM, v.FK_USER, bit_or(BF_ALLOW)
        from role2user v, perm2role s
        where v.FK_USER in (' . $s_users . ') and s.FK_ROLE=v.FK_ROLE
        group by s.FK_PERM, v.FK_USER';
			
			$res = $db->querynow ( 'select s.FK_PERM, v.FK_USER, bit_or(BF_ALLOW)
        from role2user v, perm2role s
        where v.FK_USER in (' . $s_users . ') and s.FK_ROLE=v.FK_ROLE
        group by s.FK_PERM, v.FK_USER' );
			
			while ( list ( $id_perm, $id_user, $bf_inherit ) = mysql_fetch_row ( $res ['rsrc'] ) )
				$db->query ( 'update_perm2user set BF_INHERIT=' . $bf_inherit . '
          where FK_PERM=' . $id_perm . ' and FK_USER=' . $id_user );
			$db->submit ();
		}
	}
}
?>