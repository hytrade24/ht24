<?php
/* ###VERSIONSBLOCKINLCUDE### */



class tabledef
{
	### private vars
	private $master_table = array();	// definition of master table

	### static
	static $field_info = array();		// fieldinformations for external use

	### public vars
	public $err = array();				// errors
	public $table = NULL;				// current table
	public $tables = array();			// all tables (array)
	public $ar_table = array();			// definition of current table
	public $master_fields = array();	// fields from master table
	public $warning_data=0;
    public $ar_field_types = array(     // fieldtypes
        'TEXT' => array
        (
            'DESC' => 'Textfeld (255 Zeichen)',
            'SQL' => "ALTER TABLE `#TABLE#` ADD `#FIELD#` `#FIELD2#` VARCHAR(255) NULL ;",
            'FK' => NULL,
        ),
        'LONGTEXT' => array
        (
            'DESC' => 'Textfeld unbegrenzte LÃ¤nge',
            'SQL' => "ALTER TABLE `#TABLE#` ADD `#FIELD#` `#FIELD2#` LONGTEXT NULL ;",
            'FK' => NULL,
        ),
        'HTMLTEXT' => array
        (
            'DESC' => 'Textfeld mit HTML-Editor',
            'SQL' => "ALTER TABLE `#TABLE#` ADD `#FIELD#` `#FIELD2#` LONGTEXT NULL ;",
            'FK' => NULL,
        ),
        'INT' => array
        (
            'DESC' => 'Zahlenfeld (Ganzzahlen)',
            'SQL' => 'ALTER TABLE `#TABLE#` ADD `#FIELD#` `#FIELD2#` BIGINT UNSIGNED NOT NULL',
            'FK' => NULL,
        ),
        'FLOAT' => array
        (
            'DESC' => 'Zahlenfeld (Kommazahlen)',
            'SQL' => 'ALTER TABLE `#TABLE#` ADD `#FIELD#` `#FIELD2#` FLOAT UNSIGNED NOT NULL',
            'FK' => NULL,
        ),
        'CHECKBOX' => array
        (
            'DESC' => 'Checkbox (Ja/nein, keine Mehrfachauswahl)',
            'SQL' => 'ALTER TABLE `#TABLE#` ADD `#FIELD#` `#FIELD2#` TINYINT(1) UNSIGNED NOT NULL',
            'FK' => NULL,
        ),
        'MULTICHECKBOX' => array
        (
            'DESC' => 'Mehrfach-Checkbox (Ja/nein, Mehrfachauswahl möglich, Oder-Suche)',
            'SQL' => 'ALTER TABLE `#TABLE#` ADD `#FIELD#` `#FIELD2#` VARCHAR(255) NOT NULL',
            'FK' => NULL,
        ),
        'MULTICHECKBOX_AND' => array
        (
            'DESC' => 'Mehrfach-Checkbox (Ja/nein, Mehrfachauswahl möglich, Und-Suche)',
            'SQL' => 'ALTER TABLE `#TABLE#` ADD `#FIELD#` `#FIELD2#` VARCHAR(255) NOT NULL',
            'FK' => NULL,
        ),
        'LIST' => array
        (
            'DESC' => 'Auswahlliste',
            'SQL' => 'ALTER TABLE `#TABLE#` ADD `#FIELD#` `#FIELD2#` BIGINT UNSIGNED NOT NULL',
            'FK' => 'field_list',
        ),
        'DATE' => array
        (
            'DESC' => "Datumsfeld (Jahr / Monat / Tag)",
            'SQL' => "ALTER TABLE `#TABLE#` ADD `#FIELD#` `#FIELD2#` DATETIME NULL",
            'FK' => NULL,
        ),
        'DATE_MONTH' => array
        (
            'DESC' => "Datumsfeld (Jahr / Monat)",
            'SQL' => "ALTER TABLE `#TABLE#` ADD `#FIELD#` `#FIELD2#` DATE NULL",
            'FK' => NULL,
        ),
        'DATE_YEAR' => array
        (
            'DESC' => "Datumsfeld (Jahr)",
            'SQL' => "ALTER TABLE `#TABLE#` ADD `#FIELD#` `#FIELD2#` YEAR NULL",
            'FK' => NULL,
        ),
        'VARIANT' => array
        (
            'DESC' => 'Produktvariante',
            'SQL' => 'ALTER TABLE `#TABLE#` ADD `#FIELD#` `#FIELD2#` BIGINT UNSIGNED NOT NULL',
            'FK' => 'field_list',
        ),
    );

	### constructor
	public function __construct($table = NULL, $no_master = false)
	{
		/*
		 *
		 */
	}	// tabledef()

	### static functions

	static function getFieldInfo($table)
	{
		/*
		 * writes an array with all field informations
		 * of given $table
		 */
		global $db, $langval;
		$table_id = $db->fetch_atom("
			SELECT
				ID_TABLE_DEF
			FROM
				table_def
			WHERE
				T_NAME='".sqlString($table)."'");

		self::$field_info = array();

		$res = $db->querynow("
			select
				t.ID_FIELD_DEF,
				t.F_TYP,
				t.B_ENABLED,
				t.B_SEARCH,
				t.B_NEEDED,
				t.F_NAME,
				t.FK_LISTE,
                t.F_DEC_INTERN,
                t.IS_SPECIAL,
				s.V1,
				s.V2
			from
				`field_def` t
			left join
				string_field_def s on s.S_TABLE='field_def' and s.FK=t.ID_FIELD_DEF
				and s.BF_LANG=if(t.BF_LANG_FIELD_DEF & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_FIELD_DEF+0.5)/log(2)))
			WHERE
				t.FK_TABLE_DEF=".(int)$table_id."
			ORDER BY
				F_ORDER ASC");

		while($row = mysql_fetch_assoc($res['rsrc']))
		{
			self::$field_info[] = $row;
		}
	}	// getFieldInfo()

	### public functions

	public function __destruct()
	{
		/*
		 * Debugging only
		 */
		#echo ht(dump($this));
	}	// __destruct

	public function getMaster()
	{
		/*
		 * Reads definition of master table
		 */

		$table_bak = false;
		$ar_table_bak = array();

		if($this->table != "artikel_master")
		{
			$table_bak = $this->table;
			$ar_table_bak = $this->ar_table;
		}
		$this->table = "artikel_master";
		$this->getTable();
		$this->getFields();
		$this->master_fields = $this->ar_table['FIELDS'];
		if($table_bak)
		{
			$this->table = $table_bak;
			$this->ar_table = $ar_table_bak;
		}
		#echo ht(dump($this))."<hr>";
	}	// getMaster()

	public function using($table)
	{
		/*
		 * gets current table
		 */

		if(empty($this->tables))
		{
			$this->getTables();
		}
		if(isset($this->tables[$table]) && !empty($this->tables[$table]))
		{
			$this->table = $table;
			$this->ar_table = $this->tables[$table];
		}
		else
		{
			die("Using table failed, cause of unknown table ".$table);
		}
	}	// use()

	public function make_copy($name, $org, $sel_fields=array())
	{
		/*
		 *	Creates a copy of existing table
		 */

		global $db,$langval;

		if(isset($this->tables[$name]) && !empty($this->tables[$name]))
		{
			die("Table ".$name." already exists!");
		}
		else
		{
			if(empty($this->master_fields))
			{
				$this->getMaster();
			}

			$name = strtolower($name);

			$this->table = $org;
			$this->getTable($org, true);
			$this->getFields();

			$org_fields = $tmp_fileds =array();
			$id_name = NULL;

			foreach($this->master_fields as $key => $arMasterField)
			{
				if ($arMasterField['IS_MASTER']) {
					if(strstr($key, "ID_"))
					{
						$key = "ID_".strtoupper($org);
						$id_name = $key;
					}
					$org_fields[] = "`".mysql_real_escape_string($key)."`";
				}
			}
			if(!empty($sel_fields))
			{
				foreach($sel_fields as $field => $null)
				{                                        
					$org_fields[] = "`".mysql_real_escape_string($field)."`";
					$tmp_fields[] = "'".$field."'";
				}
			}
#echo ht(dump($sel_fields));
			if(empty($tmp_fields))
			{
				$tmp_fields[] = "''";
			}
			$sel_fields_string = implode(",", $tmp_fields);

			$sql = "
				CREATE TABLE IF NOT EXISTS `".sqlString($name)."` ENGINE=MyISAM
					SELECT ".implode(",", $org_fields) ." FROM ".$org;
			#die(ht(dump($sql)));

			$res = $db->querynow($sql);

			if(empty($res['str_error']))
			{
				$new_id_name = "ID_".strtoupper($name);
				$res = $db->querynow("
					ALTER TABLE `".$name."` CHANGE ".$id_name." ".$new_id_name." BIGINT UNSIGNED NOT NULL ");
			}

			if(empty($res['str_error']))
			{
				### field desc
				#echo ht(dump($this->ar_table));
				$fields = $db->fetch_table("
					SELECT
						t.*,
						s.V1,
						s.V2,
						s.T1
					FROM
						`field_def` t
					LEFT JOIN
						string_field_def s ON s.S_TABLE='field_def'
						AND s.FK=t.ID_FIELD_DEF
						AND s.BF_LANG=if(t.BF_LANG_FIELD_DEF & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_FIELD_DEF+0.5)/log(2)))
					WHERE
						t.FK_TABLE_DEF=".$this->tables[$org]['ID_TABLE_DEF']."
						AND (
							t.IS_MASTER = 1 OR
							t.F_NAME IN (".$sel_fields_string.")
						)
					GROUP BY
						t.F_NAME");

				$this->table = $name;
				$this->getTable($name, true);
				//$this->getFields();
				//$this->getFieldsDB($this->tables[$org]['ID_TABLE_DEF']);
				$ar_groups = array();
				$ar_groups_fields = array();

				for($i=0; $i<count($fields); $i++)
				{
					if($fields[$i]['F_NAME'] == $id_name)
					{
						$fields[$i]['F_NAME'] = $new_id_name;
						$fields[$i]['IS_MASTER'] = 1;
					}
					unset($fields[$i]['ID_FIELD_DEF'], $fields[$i]['FK']);
					$fields[$i]['FK_TABLE_DEF'] = $this->ar_table['ID_TABLE_DEF'];
					#echo ht(dump($fields[$i]));
					$id_group = (int)$fields[$i]["FK_FIELD_GROUP"];
					if ($id_group > 0) {
						if (isset($ar_groups[$id_group])) {
							// Gruppe wurde bereits geklont
							$id_group = $ar_groups[$id_group];
						} else {
							// Neue Feldgruppe - Für die neue Tabelle klonen
							$group_lang = $db->fetch_atom("SELECT BF_LANG_APP FROM `field_group` WHERE ID_FIELD_GROUP=".$id_group);
							$group_strings = $db->fetch_table("SELECT * FROM `string_app` WHERE S_TABLE='field_group' AND FK=".$id_group);
							$res = $db->querynow("INSERT INTO `field_group` (FK_TABLE_DEF, BF_LANG_APP, SER_FIELDS)
									VALUES (".$this->ar_table['ID_TABLE_DEF'].", ".$group_lang.", '')");
							$id_group_new = (int)$res['int_result'];
							if ($id_group_new > 0) {
								foreach ($group_strings as $index => $group_string_cur) {
									$db->querynow("INSERT INTO `string_app` (S_TABLE, FK, BF_LANG, T1, V1, V2)
										VALUES ('field_group', ".$id_group_new.",
											".mysql_escape_string($group_string_cur["BF_LANG"]).",
											'".mysql_escape_string($group_string_cur["T1"])."',
											'".mysql_escape_string($group_string_cur["V1"])."',
											'".mysql_escape_string($group_string_cur["V2"])."')");
								}
								$ar_groups[$id_group] = $id_group_new;
								$ar_groups_fields[$id_group_new] = array();
								$id_group = $id_group_new;
							}
						}
						$fields[$i]["FK_FIELD_GROUP"] = $id_group;
					}

					$id_field_def = $db->update("field_def", $fields[$i]);
					if (($id_group > 0) && ($id_field_def > 0)) {
						$ar_groups_fields[$id_group][] = $id_field_def;
					}
				}
				### INDEXIES
				//ALTER TABLE `artikel_004` ADD PRIMARY KEY (`ID_ARTIKEL_004`) ;
				$db->querynow("TRUNCATE `".$name."`");
				$db->querynow("
					ALTER TABLE `".$name."` CHANGE `".$new_id_name."` `".$new_id_name."` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT ,  ADD PRIMARY KEY(`".$new_id_name."`) ");

				$this->getFieldsDB($this->ar_table['ID_TABLE_DEF']);
				#echo ht(dump($this->ar_table['FIELDS']));
				foreach($fields as $id => $ar)
				{
					if($ar['B_SEARCH'])
					{
						$index = $db->querynow("
							ALTER TABLE
								".$name."
							ADD INDEX `INDEX_".$ar['F_NAME']."` (".$ar['F_NAME'].")");
						#die(ht(dump($index)));
					}
				}
				#die();
			}
			else
			{
				$this->err[] = "Datenbankfehler beim Kopieren der Tabelle aufgetreten!";
				$this->err[] = $res['str_error'];
				$this->err[] = ht(dump($res));
				$res_drop = $db->querynow("
					DROP TABLE IF EXISTS ".$name);
				echo ht(dump($res_drop));
				$db->delete("table_def", $db->fetch_atom("SELECT ID_TABLE_DEF from table_def WHERE T_NAME='".$name."'"));
			}
		}
	}	// make_copy()

	public function saveField($ar_opt)
	{
		/*
		 * Saving options / definitions of a field (existing or new)
		 */

		if(empty($ar_opt['ID_FIELD_DEF']))
		{
			// New field
			if (!array_key_exists("IS_SPECIAL", $ar_opt)) {
				$ar_opt["IS_SPECIAL"] = 0;
			}
			$sql = $this->ar_field_types[$ar_opt['F_TYP']]['SQL'];
			if(empty($sql))
			{
				$this->err[] = "No Query to create a field type ".$ar_opt['F_TYP'];
			}
			else
			{
				//die($this->table);
				if($this->table == 'artikel_master')
				{
					$table_bak = $this->table;
					$ar_table_bak = $this->ar_table;
					$this->getTables(1, true);
					$ar_opt['FIELDNAME'] = $ar_opt['SQL_FIELD'];
					$ar_opt['SQL'] = $sql;

					foreach($this->tables as $table => $def)
					{
						//SQL_FIELD
						$this->table = $table;
						if (($this->table == 'artikel_master') || ($this->table == 'vendor_master')) {
							continue;
						}
						$this->getTable($this->table);
						$this->addField($ar_opt);
					}
					$this->table = $table_bak;
					$this->ar_table = $ar_table_bak;
					$id_field = $this->addField($ar_opt);
                    return $id_field;
				}
				else
				{
					// 20.02.2012 - Hotfix von Jens - LÃ¶schen von Feldern fÃ¼hrt zu Problemen
					// Felder der Tabelle auslesen wenn noch nicht bekannt
					if (empty($this->ar_table['FIELDS'])) $this->getFields();
					// Index des nÃ¤chsten "ARTICLE_xxx"-Felds bestimmen
					$index_next = 1;
					foreach ($this->ar_table['FIELDS'] as $field_name => $ar_field) {
						if (preg_match("/^ARTIKEL_([0-9]{3})$/i", $field_name, $ar_matches)) {
							$index = (int)$ar_matches[1];
							if ($index >= $index_next) $index_next = $index + 1;
						}
					}
					if(isset($ar_opt['SQL_FIELD']) && $ar_opt['SQL_FIELD'] != "") {
						$ar_opt['FIELDNAME'] = $ar_opt['SQL_FIELD'];
					} else {
						$ar_opt['FIELDNAME'] = 'ARTIKEL_'.sprintf("%03d", $index_next);
					}
					$ar_opt['SQL'] = $sql;
                    $id_field = $this->addField($ar_opt);
                    return $id_field;
				}
			}
		}	// new field
		else
		{
			if(empty($this->master_fields))
			{
				$this->getMaster();
			}
			if($this->table == "artikel_master")
			{
				$table_bak = $this->table;
				$ar_table_bak = $this->ar_table;
				
				$this->getTables(1, true);
				$sql = $this->ar_field_types[$ar_opt['F_TYP']]['SQL'];
				$sql = str_replace(" ADD ", " CHANGE ", $sql);
				$ar_opt['SQL'] = $sql;

				foreach($this->tables as $table => $def)
				{
					if ($table == 'vendor_master') {
						continue;
					}
					$this->table = $table;
					$this->getTable($this->table);
					$this->getFieldsDB($this->ar_table['ID_TABLE_DEF']);
					$ar_opt['FK_TABLE_DEF'] = $this->tables[$this->table]['FIELDS'][$ar_opt['F_NAME']]['FK_TABLE_DEF'];
					$ar_opt['ID_FIELD_DEF'] = $this->tables[$this->table]['FIELDS'][$ar_opt['F_NAME']]['ID_FIELD_DEF'];
					$ar_opt['FK'] = $this->tables[$this->table]['FIELDS'][$ar_opt['F_NAME']]['FK'];
					if (!array_key_exists("IS_SPECIAL", $ar_opt)) {
						$ar_opt['IS_SPECIAL'] = $this->tables[$this->table]['FIELDS'][$ar_opt['F_NAME']]['IS_SPECIAL'];
					}
					#die(ht(dump($ar_opt)));
					$ar_opt['SQL'] = $sql;
					$this->changeField($ar_opt);
				}

				$this->table = $table_bak;
				$this->ar_table = $ar_table_bak;

			}
			$sql = $this->ar_field_types[$ar_opt['F_TYP']]['SQL'];
			if(empty($sql))
			{
				$this->err[] = "No Query to create a field type ".$ar_opt['F_TYP'];
			}
			else
			{
				$sql = str_replace(" ADD ", " CHANGE ", $sql);
				$ar_opt['SQL'] = $sql;
				$this->changeField($ar_opt);
			}
            return $ar_opt['ID_FIELD_DEF'];
		}	// existing field
        return false;
	}	// saveField()

	public function getTables($reload=false, $getFields=false)
	{
		/*
		 * Gets all tables
		 */

		global $db, $langval;

		if(empty($this->tables) || $reload)
		{
			$ar_tables = array();
			$res = $db->querynow("SHOW TABLES FROM `".$db->str_dbname."`");
			while($row = mysql_fetch_row($res['rsrc']))
			{
				if ((strpos($row[0], "artikel_") === 0) || ($row[0] == "vendor_master")) {
					$tableinfo = $db->fetch1($q="SHOW TABLE STATUS FROM `".$db->str_dbname."` LIKE '".$row[0]."'");
					$ar_table_def = $db->fetch1($q="
						SELECT
							t.*,
							s.V1,
							s.V2,
							s.T1
						FROM
							`table_def` t
						LEFT JOIN
							string_app s on s.S_TABLE='table_def'
							AND s.FK=t.ID_TABLE_DEF
							AND s.BF_LANG=if(t.BF_LANG_APP & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_APP+0.5)/log(2)))
						WHERE
							t.T_NAME='".$row[0]."'
						");

					$hack = explode("_", $row[0]);
					unset($hack[0]);
					$short_name = implode("_", $hack);

					if($getFields)
					{
						$this->table = $row[0];
						#die("table is ".$short_name);
						$this->getTable($this->table);
						$this->getFields();
						$row['FIELDS'] = $this->ar_table['FIELDS'];
						$row['C_FIELDS'] = count($row['FIELDS']);
					}

					$row = array_merge($row, $tableinfo, $ar_table_def);
					#echo ht(dump($row));die();
					$ar_tables[$ar_table_def['T_NAME']] = $row;
					$ar_tables[$ar_table_def['T_NAME']]["T_NAME_".$ar_table_def['T_NAME']]=1;
				}
			} // while tables
			$this->tables = &$ar_tables;
		} // read live cause of missing cache
	} // getTables()

	public function getTableById($id)
	{
		/*
		 * Gets current table by using just the table ID
		 */

		global $db;
		$id = (int)$id;
		$name = $db->fetch_atom("
			SELECT
				T_NAME
			FROM
				table_def
			WHERE
				ID_TABLE_DEF=".$id);
		$this->getTable($name);

	}	// getTable by ID()

	public function getTable($table=NULL, $new = false)
	{
		/*
		 * Gets current table
		 */

		//$table = preg_replace("/(^)(artikel_)/si", "$1", $table);
		//$table = strtolower($table);

		$reload = false;
		if($new)
		{
			$reload = true;
		}
		if($table)
		{
			$this->table = $table;
		}
		if($this->table)
		{
			global $db;
			if(empty($this->tables) || $new)
			{
				$this->getTables($reload);
				if($this->table == 'artikel_master')
				{
					$this->master_table = $this->tables['artikel_master'];
					//$this->ar_table = $this->tables[$this->table];
				}
			}
			$this->ar_table = $this->tables[$this->table];
			#echo ht(dump($this));
		}	// this-<table
		else
		{
			die("No Table selected!");
		}
	}	// getTableDef()

	public function getFieldsDB($table_id)
	{
		/*
		 * Gets options / description for each Field in current table
		 */

		global $db, $langval;

		$res = $db->querynow("
			select t.*, s.V1, s.V2, s.T1,
				sg.V1 AS FIELD_GROUP,
				sg.V2 AS FIELD_GROUP_DESC
			FROM `field_def` t
			LEFT JOIN `string_field_def` s on s.S_TABLE='field_def' and s.FK=t.ID_FIELD_DEF
					and s.BF_LANG=if(t.BF_LANG_FIELD_DEF & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_FIELD_DEF+0.5)/log(2)))
			LEFT JOIN `field_group` g ON t.FK_FIELD_GROUP=g.ID_FIELD_GROUP
			LEFT JOIN `string_app` sg on sg.S_TABLE='field_group' and sg.FK=g.ID_FIELD_GROUP
					and sg.BF_LANG=if(g.BF_LANG_APP & ".$langval.", ".$langval.", 1 << floor(log(g.BF_LANG_APP+0.5)/log(2)))
			WHERE
				t.FK_TABLE_DEF=".(int)$table_id."
			ORDER BY
				g.F_ORDER ASC,
				t.FK_FIELD_GROUP ASC,
				t.F_ORDER ASC
			");
		#echo ht(dump($res));
		$new = array();
		while($row = mysql_fetch_assoc($res['rsrc']))
		{
			list($row["T1"], $row["T2"], $row["T3"]) = explode("§§§", $row["T1"]);
			$ar_desc = explode("||", $row["T1"]);
			$row["T1_DESC"] = $ar_desc[0];
			$row["T1_HELP"] = $ar_desc[1];
			$ar_desc = explode("||", $row["T2"]);
			$row["T2_DESC"] = $ar_desc[0];
			$row["T2_HELP"] = $ar_desc[1];
			$ar_desc = explode("||", $row["T3"]);
			$row["T3_DESC"] = $ar_desc[0];
			$row["T3_HELP"] = $ar_desc[1];
			//$new[] = $row;
			if(!is_array($this->tables[$this->table]['FIELDS'][$row['F_NAME']]))
			{
				$this->tables[$this->table]['FIELDS'][$row['F_NAME']]=array();
				#echo ht(dump($this->tables[$this->table]['FIELDS']));
			}
			$this->tables[$this->table]['FIELDS'][$row['F_NAME']] = array_merge($this->tables[$this->table]['FIELDS'][$row['F_NAME']], $row);
			$this->tables[$this->table]['FIELDS_ORDERED'][] = array_merge($this->tables[$this->table]['FIELDS'][$row['F_NAME']], $row);
		}
		//$this->tables[$this->table]['FIELDS'] = $new;
		if($this->table != 'artikel_master')
		{
			#die(ht(dump($new)));
		}
	}	// getFieldsDB

	public function getFields()
	{
		/*
		 * Gets fields from Database
		 */

		global $db;
		if(!$this->table)
		{
			die("No Table selected");
		}
		elseif(empty($this->ar_table['FIELDS']))
		{
			if(empty($this->master_fields) && $this->table != "artikel_master")
			{
				$table_bak = $this->table;
				$ar_table_bak = $this->ar_table;
				$this->getMaster();
				$this->table = $table_bak;
				$this->ar_table = $ar_table_bak;
			}
			//$this->getFieldsDB($this->ar_table['ID_TABLE_DEF']);
			#echo "table:: ".$this->table;
			$res = $db->querynow("
				SHOW FIELDS
					from
				".$this->table);
			#echo ht(dump($res));
			$this->tables[$this->table]['FIELDS'] = array();
			while($row = mysql_fetch_assoc($res['rsrc']))
			{
				$ar_desc = explode("||", $row["T1"]);
				$row["T1_DESC"] = $ar_desc[0];
				$row["T1_HELP"] = $ar_desc[1];
				$ar_desc = explode("||", $row["T2"]);
				$row["T2_DESC"] = $ar_desc[0];
				$row["T2_HELP"] = $ar_desc[1];
				$ar_desc = explode("||", $row["T3"]);
				$row["T3_DESC"] = $ar_desc[0];
				$row["T3_HELP"] = $ar_desc[1];
				$master = NULL;
				if(is_array($this->master_fields[$row['Field']]))
				{
					$master = 1;
				}
				$row['is_master_field'] = $master;
				$this->tables[$this->table]['FIELDS'][$row['Field']] = $row;
			}
			$this->ar_table['FIELDS'] = &$this->tables[$this->table]['FIELDS'];
			$this->getFieldsDB($this->ar_table['ID_TABLE_DEF']);
		}
		#die(ht(dump($this->ar_table)));
	}	//getFields()

	public function delTable($table_id)
	{
		global $db;
		$this->getTableById((int)$table_id);
		$this->getFields();
		if($this->ar_table['Rows'] > 0)
		{
			$this->err[] = "Tabelle ist nicht leer, und kann daher nicht gelÃ¶scht werden!";
			return false;
		}
		else
		{
			$liste = $db->fetch_table("
				SELECT
					ID_FIELD_DEF
				FROM
					field_def
				WHERE
					FK_TABLE_DEF=".$table_id);
			foreach($liste as $field => $ar)
			{
				$db->delete("field_def", $ar['ID_FIELD_DEF']);
			}
			$db->delete("table_def", $this->ar_table['ID_TABLE_DEF']);
			$db->querynow("
				DROP TABLE ".$this->table);

			return true;
		}
	}	// delTable()

    public function deleteTableField($fieldFName) {
        global $db;
        // field_def del row
        // artikel_001 del column
        // kat2field

        if ($this->ar_table['T_NAME'] == 'artikel_master') {
        	$ar_field_ids = $db->fetch_nar("SELECT f.ID_FIELD_DEF, t.T_NAME
        				FROM `field_def` f
        				LEFT JOIN `table_def` t ON f.FK_TABLE_DEF=t.ID_TABLE_DEF
        				WHERE f.F_NAME='".mysql_real_escape_string($fieldFName)."'
        				ORDER BY f.FK_TABLE_DEF DESC");
        	foreach ($ar_field_ids as $id_field_def => $table_def) {
	            $db->querynow("DELETE FROM kat2field WHERE FK_FIELD = '".$id_field_def."'");
	            $db->querynow("ALTER TABLE ".$table_def." DROP COLUMN ".$fieldFName);

	            $db->delete("field_def", $id_field_def);
        	}
        } else {
	        $fieldId = $db->fetch_atom("SELECT f.ID_FIELD_DEF FROM field_def f JOIN table_def t ON f.FK_TABLE_DEF = t.ID_TABLE_DEF WHERE f.F_NAME = '".$fieldFName."' AND t.T_NAME = '".$this->ar_table['T_NAME']."'");

	        if((int) $fieldId > 0) {
	            $db->querynow("DELETE FROM kat2field WHERE FK_FIELD = '".$fieldId."'");
	            $db->querynow("ALTER TABLE ".$this->ar_table['T_NAME']." DROP COLUMN ".$fieldFName);

	            $db->delete("field_def", $fieldId);

	            return true;
	        }
        }
    }

	public function checkMasterData($field)
	{
		global $db;
		if(empty($this->tables))
		{
			$this->getTables();
		}

		$this->warning_data=0;

		foreach($this->tables as $table => $data)
		{
			if (($table == "artikel_master") || ($table == "vendor_master"))
			{
				continue;
			}

			$sql = "SELECT
				count(".$field.")
			FROM
				".$table."
			WHERE
				".$field." <> ''
				AND ".$field." IS NOT NULL";

			$this->warning_data += $db->fetch_atom($sql);
		}
	}	// checkMasterData()

	### private functions

	private function changeField($conf)
	{
		global $db, $langval;

		/*
		$conf['ITEMS'] = $conf['ITEMS']; //, ENT_COMPAT , "UTF-8"));
		$conf['V1'] = $conf['V1']; //, ENT_COMPAT , "UTF-8"));
		$conf['V2'] = $conf['V2'];
		$conf['T1'] = $conf['T1'];
		*/

		$sql = $conf['SQL'];
		#die(ht(dump($conf)));
		$sql = str_replace("#TABLE#", $this->table, $sql);
		$sql = str_replace("#FIELD#", $conf['F_NAME'], $sql);
		$sql = str_replace("#FIELD2#", $conf['F_NAME'], $sql);

		$res = $db->querynow($sql);
		#echo $sql ."<br />";
		if($res['str_error'])
		{
			$this->err[] = "Feld konnte nicht ver&auml;ndert werden!<br />".$res['str_error']."<br>".$sql.var_export(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), true);
		}
		else
		{
			$conf['FK_TABLE_DEF'] = $this->ar_table['ID_TABLE_DEF'];
			$id_new = $db->update("field_def", $conf);

			if(!$conf['ID_FIELD_DEF'])
			{
				$id = $id_new;
			}
			else
			{
				$id = $conf['ID_FIELD_DEF'];
			}

			if(in_array($conf['F_TYP'], array('LIST', 'VARIANT', 'MULTICHECKBOX', 'MULTICHECKBOX_AND')))
			{
				if($conf['FK_LISTE'] == 'NEW')
				{
					$ar_liste = array();
					$ar_liste['NAME'] = $conf['V1'].($conf['LIST_GLOBAL'] ? "" : " (Tabelle ".$this->ar_table['V1'].")");
					$ar_liste['FK_FIELD_DEF'] = $id;
					$ar_liste['LIST_GLOBAL'] = ($conf['LIST_GLOBAL'] ? 1 : 0);
					$ar_liste['STAMP_CREATE'] = date('Y-m-d H:i:s');
					$ar_liste['STAMP_UPDATE'] = date('Y-m-d H:i:s');
					$id_liste = $db->update("liste", $ar_liste);
					$hack = explode("\n", $conf["ITEMS"]);
			      	for($i=0;$i<count($hack); $i++)
			      	{
			        	if (!empty($hack[$i]))
			        	{
			          		$db->update("liste_values", array("ID_LISTE_VALUES" => 0,
			                                            "FK_LISTE" => $id_liste,
			                                            "V1" => $hack[$i]));
			        	}
			      	}
					$db->querynow("
						UPDATE
							field_def
						SET
							FK_LISTE=".$id_liste."
						WHERE
							ID_FIELD_DEF=".$id);
				}	// new list
			}	// field type = liste
			#echo ht(dump($GLOBALS['lastresult']));
		}
		$this->updateStampUpdate();
	}	// changeField()

	/**
	 *
	 * @access public
	 * @param int $id
	 * @param string $direction
	 */
	public function reorder($id, $direction)
	{
		echo "ID: ".$id ." moving: ".$direction;
		global $db;
		$ar_field = $db->fetch1("
			SELECT
				*
			FROM
				field_def
			WHERE
				ID_FIELD_DEF=".(int)$id);
		$n = $ar_field['F_ORDER'];
		if($direction == 'down')
		{
			$new = $n+1;
			$db->querynow("
				UPDATE
					field_def
				SET
					F_ORDER=F_ORDER-1
				WHERE
					F_ORDER = ".$new);
			$res = $db->querynow("
				UPDATE
					field_def
				SET
					F_ORDER=".$new."
				WHERE
					ID_FIELD_DEF=".$id);
			#die(ht(dump($res)));
		}
		else
		{
			$new = $n-1;
			$db->querynow("
				UPDATE
					field_def
				SET
					F_ORDER=F_ORDER+1
				WHERE
					F_ORDER = ".$new);
			$res = $db->querynow("
				UPDATE
					field_def
				SET
					F_ORDER=".$new."
				WHERE
					ID_FIELD_DEF=".$id);
		}
	}	// reorder()

	/**
	 *
	 * @access public
	 * @param int $id_table
	 */
	public function repair_order($id_table)
	{
		global $db;
		$id = (int)$id_table;

		$res = $db->querynow("
			SELECT
				ID_FIELD_DEF,
				F_ORDER
			FROM
				field_def
			WHERE
				FK_TABLE_DEF=".$id."
			ORDER BY
				FK_FIELD_GROUP ASC,
				F_ORDER ASC,
				ID_FIELD_DEF ASC");
		$f_order = 1;
		while($row = mysql_fetch_assoc($res['rsrc']))
		{
			if($row['F_ORDER'] != $f_order)
			{
				$res_x = $db->querynow("
					UPDATE
						field_def
					SET
						F_ORDER=".$f_order."
					WHERE
						ID_FIELD_DEF=".$row['ID_FIELD_DEF']);
				//echo ht(dump($res_x));
			}
			$f_order++;
		}
	}	// repair_order()
	
	private function updateStampUpdate() {
		$GLOBALS["db"]->querynow("UPDATE `table_def` SET STAMP_UPDATE=NOW() WHERE T_NAME='".mysql_real_escape_string($this->table)."'");
	}

	private function addField($conf)
	{
		/*
		 * Creates a new Filed
		 */

		global $db;

		/*
		$conf['ITEMS'] = $conf['ITEMS']; //, ENT_COMPAT , "UTF-8"));
		$conf['V1'] = $conf['V1']; //, ENT_COMPAT , "UTF-8"));
		$conf['V2'] = $conf['V2'];
		$conf['T1'] = $conf['T1'];
		*/

		$sql = $conf['SQL'];
		#die(ht(dump($conf)));
		$sql = str_replace("#TABLE#", $this->table, $sql);
		$sql = str_replace("#FIELD#", $conf['FIELDNAME'], $sql);
		$sql = str_replace("`#FIELD2#`", "", $sql);

		$res = $db->querynow($sql);
		if($res['str_error'])
		{
			$this->err[] = "Feld konnte nicht angelegt werden!<br />".$res['str_error'];
		}
		else
		{
			$max = $db->fetch_atom("
				SELECT
					MAX(F_ORDER)
				FROM
					field_def
				WHERE
					FK_TABLE_DEF=".$this->ar_table['ID_TABLE_DEF']);
			$max++;
			$conf['F_ORDER'] = $max;
			$conf['FK_TABLE_DEF'] = $this->ar_table['ID_TABLE_DEF'];
			$conf['F_NAME'] = $conf['FIELDNAME'];
            $conf['B_ENABLED'] = ($conf['INITIALLY_ENABLED'] ? 1 : (int)$conf['B_ENABLED']);

			$id = $db->update("field_def", $conf);

			if(in_array($conf['F_TYP'], array('LIST', 'VARIANT', 'MULTICHECKBOX', 'MULTICHECKBOX_AND')))
			{
				if($conf['FK_LISTE'] == 'NEW')
				{
					$ar_liste = array();
					$ar_liste['NAME'] = $conf['V1'].($conf['LIST_GLOBAL'] ? "" : " (Tabelle ".$this->ar_table['V1'].")");
					$ar_liste['FK_FIELD_DEF'] = $id;
					$ar_liste['LIST_GLOBAL'] = ($conf['LIST_GLOBAL'] ? 1 : 0);
					$ar_liste['STAMP_CREATE'] = date('Y-m-d H:i:s');
					$ar_liste['STAMP_UPDATE'] = date('Y-m-d H:i:s');
					$id_liste = $db->update("liste", $ar_liste);
					$hack = explode("\n", $conf["ITEMS"]);
			      	for($i=0;$i<count($hack); $i++)
			      	{
			        	if (!empty($hack[$i]))
			        	{
			          		$db->update("liste_values", array("ID_LISTE_VALUES" => 0,
			                                            "FK_LISTE" => $id_liste,
			                                            "V1" => $hack[$i]));
			        	}
			      	}
			      	$db->querynow("
			      		UPDATE
			      			field_def
			      		SET
			      			FK_LISTE=".$id_liste."
			      		WHERE
			      			ID_FIELD_DEF=".$id);
				}	// new list
			}	// field type = liste
			$this->updateStampUpdate();
			return $id;
		}
        return false;
	}	// addField()

}	// class tablefed

?>
