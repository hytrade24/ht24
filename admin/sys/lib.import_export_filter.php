<?php
/* ###VERSIONSBLOCKINLCUDE### */



/**
 *
 * @author benjamin schmalenberger
 * @package import/export Filter
 * @version 1.0
 *
 */
class import_export_filter {
	/**
	 * internes Fehler array
	 *
	 * @var array $error
	 */
	public $error;

	/**
	 *
	 * @var int database-id of current filter (if is set)
	 */
	public $id_filter;

	/**
	 *
	 * @var array data of current filter
	 */
	public $ar_filter;

	/**
	 *
	 * @var array user defined functions
	 */
	public $ar_user_functions;

	/**
	 * instance of lib.userfkt
	 * @var object
	 */
	protected $userfkt;

	protected $pattern = '6665544666';

	protected function load_user_lib() {
		global $ab_path;
		if(is_null($this->userfkt)) {
			include_once $ab_path.'admin/sys/lib.userfkt.php';
			$this->userfkt = new userfunctions($this->ar_filter['IDENT']);
		}
		return $this->userfkt;
	}

	/**
	 * erzeugt neuen internen Fehler
	 *
	 * @param string $str_error error-message
	 * @return array error msgs
	 */
	protected function err($str_error) {
		if(is_null($this->error)) {
			$this->error = array();
		}
		$this->error[] = trim($str_error);
		return $this->error;
	}

	/**
	 * builds kat-field and sets index
	 * @return boolean
	 */
	protected function build_index() {
		global $db;
		$query = "ALTER TABLE `kat`  ADD COLUMN `FK_".$this->ar_filter['IDENT']."` BIGINT UNSIGNED NULL DEFAULT NULL AFTER `ID_KAT`;";
		$res = $db->querynow($query);
		if(!empty($res['str_error'])) {
			$this->err('Index-Feld in Kategorietabelle konnte nicht erstellt werden!<br />'.stdHtmlentities($res['str_error']));
			return false;
		} else {

            /*
             * Index in der Tabelle kat wird nicht mehr erstellt, da die Funktion der Spalten fÃ¼r Importfilter im Raum steht
             * Aus BC GrÃ¼nden, lasse ich die Felder erst einmal drin
             *
             * @author Danny Rosifka
             * @date 2012-01-17
             * @todo PrÃ¼fen Spalten in der Tabelle kat fÃ¼r Import Filter notwendig sind
             */
            /*$query = "ALTER TABLE `kat`  ADD INDEX `".$this->ar_filter['IDENT']." Index` (`FK_".$this->ar_filter['IDENT']."`);";
			$res = $db->querynow($query);
			if(!empty($res['str_error'])) {
				$this->err('Datenbank-Index auf Kategoriefeld konnte nicht erstellt werden!<br />'.stdHtmlentities($res['str_error']));
				$db->querynow("ALTER TABLE `kat`  DROP COLUMN `FK_".$this->ar_filter['IDENT']."`;");
				return false;
			} else {
				return $this->id_filter;
			} */
            return $this->id_filter;
		}
	}

	/**
	 * deletes settings to current filter
	 *
	 * @return boolean
	 */
	protected function delete_settings() {
		global $db;
		$res = $db->querynow("
			delete from import_settings where FK_IMPORT_FILTER=".$this->id_filter);
		if(!empty($res['str_error'])) {
			$this->err($res['str_error']);
			return false;
		} else {
			return true;
		}
	}

	/**
	 * creates temp table for imports
	 *
	 */
	protected function create_temp_table() {
		global $db;
		$res = $db->querynow("drop table if exists import_tmp_".strtolower($this->ar_filter['IDENT']));

		if(!empty($res['str_error'])) {
			return false;
		} else {
			$table_org = $db->fetch_atom("SELECT T_NAME from table_def where ID_TABLE_DEF=".$this->ar_filter['FK_TABLE_DEF']);
			$res = $db->querynow("create table if not exists import_tmp_".strtolower($this->ar_filter['IDENT'])." like ".$table_org);
			if(!empty($res['str_error'])) {
				return false;
			} else {
				$res = $db->querynow("
					ALTER TABLE
						import_tmp_".strtolower($this->ar_filter['IDENT'])."
					ADD COLUMN `IMPORT_PIC` VARCHAR(500) NULL DEFAULT NULL AFTER `FK_USER`,
					ADD `FK_IMPORT_FILE` INT( 11 ) NULL AFTER `IMPORT_PIC`,
					ADD `FK_PACKET_ORDER` INT( 11 ) NULL AFTER `FK_IMPORT_FILE`,
					ADD `IMPORT_STATUS` TINYINT( 1 ) NOT NULL DEFAULT '2' AFTER `FK_PACKET_ORDER`,
					ADD `NOTIZ` VARCHAR(250)  NULL AFTER `IMPORT_STATUS`
				");

                $db->querynow("ALTER TABLE import_tmp_".strtolower($this->ar_filter['IDENT'])." ADD INDEX `INDEX_FILE_STATUS` ( `FK_IMPORT_FILE` , `IMPORT_STATUS` )");
				if(!empty($res['str_error'])) {
					return false;
				} else {
					echo 'ich gebe jetzt true zurÃ¼ck<br />';
					return true;
				}
			}
		}
	}

	/**
	 * Erstellt neuen Filter
	 * @param array $ar_filter data for new filter
	 * @return mixed
	 */
	public function create_filter($ar_filter) {
		global $db;
		$ar_filter['IDENT'] = strtoupper(trim($ar_filter['IDENT']));
		if(empty($ar_filter['IDENT'])) {
			$this->err('Kein technischer Name angegeben!');
		} else {
			$check = preg_match("/^[A-Z0-9]{3,10}$/s", $ar_filter['IDENT']);
			if($check) {
				$check = $db->fetch_atom("select ID_IMPORT_FILTER from import_filter
					where IDENT = '".mysql_escape_string($ar_filter['IDENT'])."'");
				if($check > 0) {
					$this->err('IDENT wird bereits verwendet');
				}
			} else {
				$this->err('Der IDENT darf nur aus minimal 3 und maximal 10 Buchstaben und Zahlen bestehen');
			}
		}
		if(empty($ar_filter['V1'])) {
			$this->err('Kein beschreibender Name angegeben!');
		}
		$ar_filter['B_AKTIV'] = ($ar_filter['B_AKTIV'] ? 1 : 0);
		if(empty($this->error)) {
			$this->id_filter = $ar_filter['ID_IMPORT_FILTER'] = $db->update("import_filter", $ar_filter);
			$this->ar_filter = $ar_filter;
			$temp_table = $this->create_temp_table();
			if(!$temp_table) {
				$this->err('TemporÃ¤re Tabelle konnte nicht erstellt werden!');
				return false;
			} else {
				return $this->build_index();
			}
		} else {
			return false;
		}
	}

	/**
	 * uncreate new filter
	 *
	 * @return boolean
	 */
	public function rollback_create() {
		global $db;
		if($this->id_filter) {
			$db->delete('import_filter', $this->id_filter);
			$this->id_filter = NULL;
			$this->ar_filter = NULL;
		}
	}

	public function get_filter_data($id_import) {
		global $db, $langval;

		if($langval == null) $langval = 128;

		$ar = $db->fetch1("
			select
				t.*,
				td.T_NAME,
				s.V1,
				s.V2,
				s.T1
			from
				`import_filter` t
			left join
				table_def td ON t.FK_TABLE_DEF = td.ID_TABLE_DEF
			left join
				string_app s on s.S_TABLE='import_filter'
				and s.FK=t.ID_IMPORT_FILTER
				and s.BF_LANG=if(t.BF_LANG_APP & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_APP+0.5)/log(2)))
			where
				ID_IMPORT_FILTER=".(int)$id_import);
		if(!empty($ar)) {
			$this->ar_filter = $ar;
			$this->id_filter = $id_import;
			return $ar;
		}
	}
	public function readCsv2Array($file, $trenner=';', $isempty='', $startAtCsvLine = 0, $readLimit = null) {
        $fp = fopen($file, "r");
		$csvLines = array();

        if($startAtCsvLine > 0) {
            $c = 0;
            for ($c = 0; ($c < $startAtCsvLine) && (!feof($fp)); $c++) {
			    fgetcsv($fp, 0, $trenner);
                #echo "hop line $c<br >";
		    }
        }


        $i = 0;
		while ((($data = fgetcsv($fp, 0, $trenner)) !== FALSE) && (($readLimit == null) || ($i < $readLimit))) {
			foreach($data as $ck=>$value) {
				$tmp=trim($value);
				if($tmp == $isempty) {
					$tmp = '';
				}
				$data[$ck] = $tmp;
			}
			$csvLines[] = $data;

            #echo "read line $i";
            $i++;
		}

        $fileIsFinished = (fgetcsv($fp, 0, $trenner) == false);
        fclose($fp);

		return array('data' => $csvLines, 'isFinished' => $fileIsFinished);
	}

	/**
	 * reads CSV and returns array with [limit] lines
	 *
	 * @param string $file path to csv
	 * @param int $limit retun n lines
	 * @return array
	 */
	public function csv2array($file, $limit=0) {
		$ar = $ar_tmp = array();
		$colcounter=0;
		$delimiter = ';';
		$file = file($file);
		//$file = str_replace('""', '"', $file);
		$cols = 0;
		$ar_trash_lines = array();
		$b_open = false;
		for($i=0; $i<count($file); $i++) {
			if($limit > 0 && $line_counter==$limit) {
				break;
			}
			$cur_line = $file[$i];
			$cur_line = preg_replace("/(style=\"\")([^\"]*)(\"\")/Uise", "'style=\"'.helper1('\\2').'\"'", $cur_line);

			if($cols === 0) {
				$hack = explode($delimiter, $cur_line);
				$cols = count($hack);
				unset($hack);
				$ar[] = $cur_line;
			} else {
				// check
				$hack = explode($delimiter, $cur_line);
				$colcounter = 0;

				for($k=0; $k<count($hack); $k++) {
					if(!$b_open && substr($hack[$k],0,1) == '"' && (substr($hack[$k], -2) == '""' || substr($hack[$k],-1) != '"')) {
						$ar_tmp[] = $cur_line;
						$b_open = true;
					} else {
						if($b_open == true) {
							if(substr($hack[$k],-2) == '""' || substr($hack[$k],-1) != '"')	{
								$ar_tmp[] = $hack[$k];
							} else {
								$ar_tmp[] = $hack[$k];
								$b_open = false;
								$colcounter++;
							}
						} else {
							$colcounter++;
						}
					}
				}

				if($colcounter != $cols) {
					if($b_open == true) {
						// nichts machen - Spalte noch offen
					} else {
						// jetzt Spalte "schliessen"
						$ar[] = implode("", $ar_tmp);

						$colcounter = 0;
						$ar_tmp = array();
						$line_counter++;
					}
				} else {
					// Nicht besonderes
					$ar[] = $cur_line;
					$line_counter++;
				}
			}
		}
#		echo ht(dump($ar));

		return $ar;
	}

	/**
	 * returns 1 line as array
	 *
	 * @param string $line CSV LINE
	 * @return array
	 */
	public function csvline2array($line, $delimiter=';', $isempty='') {
		$ar = $ar_tmp =  array();
		$hack = explode($delimiter, $line);
		$b_open = false;

		for($k=0; $k<count($hack); $k++) {
			if(substr($hack[$k],0,1) == '"' && substr($hack[$k],-1) != '"') {
				$ar_tmp[] = $hack[$k];
				$b_open = true;
			} else {
				if($b_open == true) {
					if(substr($hack[$k],-1) != '"')	{
						$ar_tmp[] = $hack[$k];
					} else {
						$ar_tmp[] = $hack[$k];
						$value = implode("", $ar_tmp);
						$value=trim($value);
						if($value == $isempty) {
							$value = '';
						}
						$ar[] = substr($value, 1, strlen($value)-1);
						$ar_tmp = array();
						$b_open = false;
					}
				} else {
					$value = trim($hack[$k]);
					if($value == $isempty) {
						$value = '';
					}
					$ar[] = $value;
				}
			}
		}
		for($i=0; $i<count($ar); $i++) {
			$ar[$i] = $cur_line = preg_replace("/(style=\")([^\"]*)(\")/Uise", "'style=\"'.helper2('\\2').'\"'", $ar[$i]);
			if(substr($ar[$i], 0, 1) == '"') {
				$ar[$i] = substr($ar[$i],1);
			}
			if(substr($ar[$i], -1) == '"') {
				$ar[$i] = substr($ar[$i],0, strlen($ar[$i])-1);
			}
		}
		return $ar;
	}

	/**
	 *
	 * @param array $ar_data
	 * @param int $limit
	 * @return string
	 */
	public function get_example_data($ar_data, $col, $limit) {
		$ar_tmp = array(); $empty = 0;
		for($i=0; $i<$limit; $i++) {
			$ar_data[$i][$col] = trim($ar_data[$i][$col]);
			if(empty($ar_data[$i][$col])) {
				$empty++;
			}
			$ar_tmp[] = ($i+1).'.: '.(empty($ar_data[$i][$col]) ? 'KEINE DATEN' : $ar_data[$i][$col]);
		}
		if($empty == count($ar_tmp)) {
			return NULL;
		}
		return ($i).' Beispiel-DatensÃ¯Â¿Â½tze'."\n\n".implode("\n", $ar_tmp);
	}

	/**
	 * gets sql table fields from database
	 *
	 * @return array
	 */
	public function get_table_fields() {
		global $db, $langval;
		$ar_ignore = array(
			"'ID_".strtoupper($this->ar_filter['T_NAME'])."'",
			"'FK_USER'",
			"'STATUS'",
			"'STAMP_START'",
			"'STAMP_END'",
			"'ADMIN_STAT'",
			"'CRON_STAT'",
			"'CRON_DONE'",
			"'AD_TABLE'",
			"'AD_CLICKS'",
			"'RUNTIME_DAYS'",
			"'STAMP_DEACTIVATE'",
			"'B_TOP'",
		);
        //"'LU_LAUFZEIT'"
		$query = "
			select
				t.*,
				s.V1
			from
				`field_def` t
			left join
				string_field_def s
					on s.S_TABLE='field_def'
					and s.FK=t.ID_FIELD_DEF
					and s.BF_LANG=if(t.BF_LANG_FIELD_DEF & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_FIELD_DEF+0.5)/log(2)))
			where
				t.FK_TABLE_DEF=".$this->ar_filter['FK_TABLE_DEF']."
				AND t.F_NAME NOT IN(".implode(",", $ar_ignore).")
			ORDER BY
				F_ORDER ASC";
		$ar_fields = $db->fetch_table($query);

        // LU LAUFZEIT hinzufÃ¼gen als besonderes FEld



		#die(ht(dump($ar_fields)));
		return $ar_fields;
	}

	/**
	 * gets user-defined functions from given/used
	 *
	 * @param instance $class
	 * @return array
	 */
	public function get_user_functions($class) {
		$ar = get_class_methods($class);
		if(!empty($ar)) {
			for($i=0; $i<count($ar); $i++) {
				if(substr($ar[$i],0,4) != 'REV_') {
					$tmp=array();
					$hack = explode("_", $ar[$i]);
					for($k=0; $k<count($hack); $k++) {
						$tmp[] = ucfirst($hack[$k]);
					}
					$this->ar_user_functions[] = array('LABEL' => implode(" ", $tmp), 'NAME' => $ar[$i]);
				}
			}
		}
		return $this->ar_user_functions;
	}

	public function get_user_settings() {
		global $db;
		$res = $db->querynow("select * from import_settings where FK_IMPORT_FILTER=".$this->id_filter."
			order by CSV_COL");
		#die(ht(dump($res)));
		$ar_return = array();
		while($row = mysql_fetch_assoc($res['rsrc'])) {
			$ar_return[] = $row;
		}
		return $ar_return;
	}

	/**
	 * saves all settings to current filter
	 *
	 * @param array $ar array with settings
	 * @return boolean
	 */
	public function save_settings($ar) {
		global $db;
		$del = $this->delete_settings();
		if($del) {
			$insert = array();
			foreach($ar['FK_FIELD_DEF'] as $num => $fk_field) {
				$tmp = array();

				$tmp[] = "''"; 														## id
				$tmp[] = $this->id_filter;											## fk_filter
				$tmp[] = $num;														## spalte (Zahl)
				$tmp[] = "'".mysql_escape_string($ar['SER_EX_DATA'][$num])."'";		## beispiel daten
				$tmp[] = "'".mysql_escape_string($ar['CSV_COLNAME'][$num])."'";		## Spalten Name
				$tmp[] = $this->ar_filter['FK_TABLE_DEF'];							## FK auf Tabelle
				$tmp[] = ($fk_field ? $fk_field : 'NULL');							## FK auf Feld
				$tmp[] = "'".$ar['fkt'][$num]."'";									## PHP Funktion

				$insert[] = '('.implode(",", $tmp).")";
			}
			$res = $db->querynow("insert into import_settings VALUES ".implode(",", $insert));
			#echo ht(dump($res));
			if(empty($res['str_error'])) {
				return true;
			} else {
				$this->err($res['str_error']);
				return false;
			}
		} else {
			$this->err('Daten konnten nicht gespeichert werden!');
			return false;
		}

	}

	public function change_table($id_filter, $fk_table) {
		$this->id_filter = $id_filter;
		if(empty($this->ar_filter)) {
			$this->ar_filter = $this->get_filter_data($id_filter);
		}
		if($this->ar_filter['FK_TABLE_DEF'] != $fk_table) {
			$this->create_temp_table();
			$this->delete_settings();
		}
	}

	public function updateTableFieldChange($field) {
		global $db;
		require_once "./sys/tabledef.php";

		$table = new tabledef();
		$table->getTable($field['table']);

		$sql = $table->ar_field_types[$field['F_TYP']]['SQL'];

		if(empty($field['ID_FIELD_DEF'])) {
			$sql = str_replace("#FIELD#", $field['SQL_FIELD'], $sql);
			$sql = str_replace("`#FIELD2#`", "", $sql);
		} else {
			$sql = str_replace(" ADD ", " CHANGE ", $sql);
			$sql = str_replace("#FIELD#", $field['F_NAME'], $sql);
			$sql = str_replace("`#FIELD2#`", $field['F_NAME'], $sql);
		}

		$tabledef = $db->fetch1("SELECT * FROM table_def WHERE T_NAME = '".mysql_real_escape_string($field['table'])."'");
		if($field['table'] == 'artikel_master') {
			$importFilters = $db->fetch_table("SELECT * FROM import_filter");
		} else {
			$importFilters = $db->fetch_table("SELECT * FROM import_filter WHERE FK_TABLE_DEF = '".(int)$tabledef['ID_TABLE_DEF']."'");
		}

		foreach($importFilters as $key => $importFilter) {
			$filterTableName = 'import_tmp_'.strtolower($importFilter['IDENT']);
			$filterSql = $sql;
			$filterSql = str_replace("#TABLE#", $filterTableName, $filterSql);

			$db->querynow($filterSql);
		}
	}

	public function deleteTableFieldChange($fieldname, $tablename) {
		global $db;
		$tabledef = $db->fetch1("SELECT * FROM table_def WHERE T_NAME = '".mysql_real_escape_string($tablename)."'");
		if($tablename == 'artikel_master') {
			$importFilters = $db->fetch_table("SELECT * FROM import_filter");
		} else {
			$importFilters = $db->fetch_table("SELECT * FROM import_filter WHERE FK_TABLE_DEF = '".(int)$tabledef['ID_TABLE_DEF']."'");
		}

		foreach($importFilters as $key => $importFilter) {
			$filterTableName = 'import_tmp_'.strtolower($importFilter['IDENT']);
			$db->querynow("ALTER TABLE ".$filterTableName." DROP COLUMN ".$fieldname);
		}
	}
}

function helper1($str) {
	return str_replace(";", "--!!--", $str);
}

function helper2($str) {
	return str_replace("--!!--", ";", $str);
}

?>