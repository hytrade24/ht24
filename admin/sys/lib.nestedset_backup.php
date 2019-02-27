<?php
/* ###VERSIONSBLOCKINLCUDE### */


class nestedset_backup {
	var $db; // Referenz zur Datenbankklasse
	var $error_log = array();

	function nestedset_backup(&$db) {
		$this->db = &$db;
	}

	/**
	 * Erstellt ein SQL-Dump von der Tabelle "nav" sowie von der Tabelle "string", allerdings dort nur von den Eintraegen
	 * deren S_TABLE Spalte den Wert "nav" enthalten.
	 *
	 * @param string $name darf nur gesetzt werden, wenn das Backup manuell vom Benutzer erzeugt wurde
	 * @param int $system_insert muss 1 oder 0 sein
	 */
	function make_backup ($name='', $system_insert=1) {
		// Statement fuer zum Einfuegen aller Datensaetze aus der Tabelle nav
		$nav_dump_statement = 'INSERT INTO nav	(`ID_NAV`, `FK_INFOSEITE`, `B_SYS`, `B_VIS`, `ROOT`, `LFT`, `RGT`, `IDENT`, `ALIAS`, `FK_MODUL`, `BF_LANG`, `S_LAYOUT`) VALUES ';
		$result = $this->db->querynow('SELECT `ID_NAV`, `FK_INFOSEITE`, `B_SYS`, `B_VIS`, `ROOT`, `LFT`, `RGT`, `IDENT`, `ALIAS`, `FK_MODUL`, `BF_LANG`, `S_LAYOUT` FROM nav');
		if ($result['str_error']) {
			$this->error_log[] = 'Konnte keine Verbindung zur "nav" Tabelle herstellen! - '.$result['str_error'];
			return false;
		}

		while ($row = mysql_fetch_assoc($result['rsrc'])) {
			$nav_dump_statement .= "('".$row["ID_NAV"]."', '".$row["FK_INFOSEITE"]."', '".$row["B_SYS"]."', '".$row["B_VIS"]."',
					'".$row["ROOT"]."', '".$row["LFT"]."', '".$row["RGT"]."', '".$row["IDENT"]."', ".($row["ALIAS"] === NULL?'NULL':"'".$row["ALIAS"]."'").",
					'".$row["FK_MODUL"]."', '".$row["BF_LANG"]."', '".$row["S_LAYOUT"]."'), ";
		}
		$nav_dump_statement = substr($nav_dump_statement, 0, -2);

		// Statement fuer zum Einfuegen aller Datensaetze aus der Tabelle string die den Wert "nav" in der Spalte S_TABLE haben
		$string_dump_statement = 'INSERT INTO string (`S_TABLE`, `FK`, `BF_LANG`, `T1`, `V1`, `V2`) VALUES ';
		$result = $this->db->querynow('SELECT `S_TABLE`, `FK`, `BF_LANG`, `T1`, `V1`, `V2` FROM string WHERE `S_TABLE`="nav"');
		if ($result['str_error']) {
			$this->error_log[] = 'Konnte keine Verbindung zur "string" Tabelle herstellen! - '.$result['str_error'];
			return false;
		}

		while ($row = mysql_fetch_assoc($result['rsrc'])) {
			$string_dump_statement .= " ('".$row["S_TABLE"]."', '".$row["FK"]."', '".$row["BF_LANG"]."', '".mysql_escape_string($row["T1"])."', '".mysql_escape_string($row["V1"])."', '".mysql_escape_string($row["V2"])."'), ";
		}
		$string_dump_statement = substr($string_dump_statement, 0, -2);

		$insert = $this->db->querynow("INSERT INTO nav_backup (`nav_tab_bak`, `string_tab_bak`, `name`, `system_insert`, `backup_ts`, `restore_ts`)
		VALUES('".mysql_escape_string($nav_dump_statement)."', '".mysql_escape_string($string_dump_statement)."', '".mysql_escape_string($name)."', '".mysql_escape_string($system_insert)."', '".date('Y-m-d H:i:s')."', '')");
		if ($insert) {
			return true;
		}
		$this->error_log[] = 'Konnte die Daten nicht in die Tabelle "nav_backup" eintragen! - '.$insert['str_error'];
		return false;
	}

	/**
	 * Stellt die Daten fuer die Tabellen "nav" und "string" wieder her.
	 *
	 * $number wird benÃ¶tigt, um immer einen weiteren Datensatz anzusprechen.
	 *
	 * @param int $number
	 * @return true oder false
	 */
	function restore_backup($backup_ts) {
		global $ab_path;
		$delete_nav_tab = $this->db->querynow("TRUNCATE nav");
		if (!empty($delete_nav_tab['str_error'])) {
			$this->error_log[] = 'Konnte die Tabelle "nav" nicht leeren! - '.$delete_nav_tab['str_error'];
		}

		$delete_string_tab = $this->db->querynow("DELETE FROM string WHERE `S_TABLE`='nav'");
		if (!empty($delete_string_tab['str_error'])) {
			$this->error_log[] = 'Konnte aus der Tabelle "string" keine Eintraege entfernen! - '.$delete_string_tab['str_error'];
		}

		$row = $this->db->fetch1("SELECT `nav_tab_bak`, `string_tab_bak`, `name`, `system_insert`, `backup_ts` FROM nav_backup WHERE `backup_ts`='".mysql_escape_string($backup_ts)."' ORDER BY `backup_ts` DESC");
		if (!empty($row['str_error'])) {
			$this->error_log[] = 'Konnte keine Verbindung zur "nav_backup" Tabelle herstellen! - '.$row['str_error'];
		}

		$this->db->querynow("UPDATE nav_backup SET `restore_ts`='".date('Y-m-d H:i:s')."' WHERE `backup_ts`='".$row['backup_ts']."'");

		$row2 = $this->db->querynow($row['nav_tab_bak']);
		if (!empty($row['str_error'])) {
			$this->error_log[] = 'Konnte die Daten nicht in Tabelle "nav" uebertragen! - '.$row2['str_error'];
		}

		$row3 = $this->db->querynow($row['string_tab_bak']);
		if (!empty($row['str_error'])) {
			$this->error_log[] = 'Konnte die Daten nicht in Tabelle "string" uebertragen! - '.$row3['str_error'];
		}

		$row = $this->db->querynow("SELECT (RGT-LFT)&1 ODD FROM nav WHERE ROOT=1 HAVING ODD=0");
		if ($row['int_result'] == 0) {
			include "sys/lib.perm_admin.php";
			include "sys/lib.cache.php";
		    pageperm2role_rewrite();
			cache_nav_all(1);
			cache_nav_all(2);
			return true;
		}
		$this->error_log[] = 'Fehler im Nestedset!'.$row['str_error'];
		return false;
	}

	/**
	 * Versucht solange ein Backup wieder einzuspielen, bis ein brauchbares dabei ist.
	 * In der Regel sollte es das erste und aktuellste Backup sein.
	 *
	 * @return true oder false
	 */
	function restore_until_it_works () {
  		$dump_list = $this->get_dumps();
  		$count_dump_list = count($dump_list);
	  	for ($i=0; $i<$count_dump_list; ++$i) {
	  		if ($this->restore_backup($dump_list[$i]['backup_ts'])) {
	  			return true;
	  		}
	  	}
	  	$this->error_log[] = 'Nestedset vollkommen zerst&ouml;rt. Kein funktionierendes Backup verf&uuml;gbar!';
	  	return false;
	}

	/**
	 * Raeumt die Tabelle "nav_backup" auf. Loescht dabei bis auf 40 Eintraege alles, wo "system_insert"=1 ist.
	 *
	 * @return true oder false
	 */
	function tidy_up () {
		#echo "CALL TIDY Up !!";
		//$delete = $this->db->querynow("DELETE FROM `nav_backup` WHERE `name`='' AND `system_insert`='1' ORDER BY `backup_ts` DESC LIMIT 40,10000 ");
		$res = $this->db->querynow("select  backup_ts from nav_backup where system_insert='1' order
		 by backup_ts DESC");
		$i=1;
		while($row = mysql_fetch_row($res['rsrc']))
		{
		  if($i > 40)
		  {
		    $sql = $this->db->querynow("delete from nav_backup where backup_ts='".$row[0]."'");

			#echo $sql."<br />";
		  }
		  #echo $row[0]." :: ".$i."<br />";
		  $i++;
		}
		$sql = $this->db->querynow("OPTIMIZE TABLE `nav_backup`");
		#echo ht(dump($sql));
		#echo ht(dump($res));
		if (!empty($delete['str_error'])) {
			$this->error_log[] = 'Die Tabelle "nav_backup" konnte nicht aufgeraeumnt werden! - '.$delete['str_error'];
			return false;
		}
		return true;
	}

	/**
	 * Gibt einen Array mit einer bestimmten Anzahl an Eintraegen zurueck.
	 *
	 * @param int $start Ab welchem Eintrag der Array befuellt werden soll.
	 * @param unknown_type $end Bis zu welchem Eintrag der Array befuellt werden soll.
	 * @param unknown_type $order_by_tab Nach welcher Spalte sortiert werden soll
	 * @param unknown_type $order_by Aufwaerts oder Abwaerts sortieren
	 * @return array
	 */
	function get_dumps($start=0, $end=0, $order_by_tab='backup_ts', $order_by='DESC') {
		if ($start == 0 && $end == 0) {
			$result = $this->db->querynow("SELECT `name`, `system_insert`, `backup_ts`, `restore_ts` FROM `nav_backup` ORDER BY `".mysql_escape_string($order_by_tab)."` ".mysql_escape_string($order_by));
		} else {
			$result = $this->db->querynow("SELECT `name`, `system_insert`, `backup_ts`, `restore_ts` FROM `nav_backup` ORDER BY `".mysql_escape_string($order_by_tab)."` ".mysql_escape_string($order_by)." LIMIT ".$start.($end != 0 ? ", ".$end : null));
		}
		#echo ht(dump($result));
		$dump_array = array();
		$counter = 0;
		while ($row = mysql_fetch_assoc($result['rsrc'])) {
			$dump_array[$counter]['name'] 			= $row['name'];
			$dump_array[$counter]['system_insert'] 	= $row['system_insert'];
			$dump_array[$counter]['backup_ts'] 		= $row['backup_ts'];
			$dump_array[$counter]['restore_ts'] 	= ($row['restore_ts'] != '0000-00-00 00:00:00' ? $row['restore_ts'] : 0);
			$counter++;
		}
		return $dump_array;
	}

	/**
	 * Sucht anhand des Namens nach einem Dump des Users.
	 *
	 * @param string $name Name des Dumps, nachdem gesucht werden soll
	 * @return array
	 */
	function search_dump($name) {
		$result = $this->db->querynow("SELECT `nav_tab_bak`, `string_tab_bak`, `name`, `backup_ts`, `restore_ts` FROM `nav_backup` WHERE `name` LIKE '%".mysql_escape_string($name)."%' AND `system_insert`='0' ORDER BY `backup_ts` DESC");
		$dump_array = array();
		$counter = 0;
		while ($row = mysql_fetch_assoc($result['rsrc'])) {
			$dump_array[$counter]['nav_tab_bak'] 	= $row['nav_tab_bak'];
			$dump_array[$counter]['string_tab_bak'] = $row['string_tab_bak'];
			$dump_array[$counter]['name'] 			= $row['name'];
			$dump_array[$counter]['backup_ts'] 		= $row['backup_ts'];
			$dump_array[$counter]['restore_ts'] 	= ($row['restore_ts'] != '0000-00-00 00:00:00' ? $row['restore_ts'] : 0);
			$counter++;
		}
		return $dump_array;
	}

	function delete_dump($backup_ts) {
		$delete = $this->db->querynow("DELETE FROM `nav_backup` WHERE `backup_ts`='".mysql_escape_string($backup_ts)."'");
		if (!empty($delete['str_error'])) {
			$this->error_log[] = 'Das Backup vom '.$backup_ts.' konnte nicht gel&ouml;scht werden! - '.$delete['str_error'];
			return false;
		}
		return true;
	}

	/**
	 * Gibt die Errorlog in einem Array zurueck
	 *
	 * @return array
	 */
	function get_error_log () {
		return $this->error_log;
	}
}
?>