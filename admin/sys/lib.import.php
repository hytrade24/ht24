<?php
/* ###VERSIONSBLOCKINLCUDE### */



/**
 * Nicht vergessen die lib.import_export_filter.php zu laden
 *
 * @author benjamin schmalenberger
 * @package import/export Filter
 * @version 1.0
 *
 */
class import extends import_export_filter {
	/**
	 * @var array import-settings
	 */
	public $ar_settings;

	protected $list_values;

	protected $ar_list2value;

	public $last_err_fields=NULL;

	/**
	 *
	 * @var array field def
	 */
	protected $ar_field_def;

	/**
	 * @var int
	 */
	private $importFile = null;

	public function __construct() {
	}

	protected function get_def_fields($ar_fields) {
		global $db, $langval;
		if(count($ar_fields)) {
			$res = $db->querynow("
				select
					s.V1,
					s.FK AS ID_FIELD_DEF,
					f.F_NAME,
					f.F_TYP,
					f.FK_LISTE
				from
					string_field_def s
				join
					field_def f ON s.FK = f.ID_FIELD_DEF
				WHERE
					s.S_TABLE='field_def' AND
					s.FK IN (".implode(',', $ar_fields).")
					AND s.BF_LANG=".$langval);
			while($row = mysql_fetch_assoc($res['rsrc'])) {
				$this->ar_field_def[$row['ID_FIELD_DEF']] = $row;
			}
		} else {
			return false;
		}
	}

	protected function get_insert_fields() {
		$ar_tmp = array();
		foreach($this->ar_settings as $col => $data) {
			if($data['FK_FIELD_DEF'] == -1) {
				$this->ar_field_def[$data['FK_FIELD_DEF']]['F_NAME'] = 'IMPORT_PIC';
			}
			$ar_tmp[] = $this->ar_field_def[$data['FK_FIELD_DEF']]['F_NAME'];
			#$ar_tmp[] = $field_data['F_NAME'];
		}
		return implode(',', $ar_tmp);
	}

	/**
	 *
	 * @param array $ar_csvline
	 * @return string
	 */
	protected function insert_string($ar_csvline, $importFile = "null") {
		global $uid;
		$ar_tmp = array();
		#echo ht(dump($this->ar_settings));

		foreach($this->ar_settings as $col => $null) {

			$value = $ar_csvline[$col];
			$func = $this->ar_settings[$col]['USER_FUNCTION'];
			if($func) {
				#echo 'func is '.$func.' for field: '.$col.' and value: '.$value;
				$value = $this->userfkt->$func($value);
				#echo ' new value: '.$value.'<br>';
			}
			$ar_tmp[] = "'".mysql_real_escape_string($value)."'";
		}
		return '('.$uid.',"'.mysql_real_escape_string($importFile).'",'.implode(",", $ar_tmp).')';
	}


	protected function get_req_fields($id_kat = null) {
		global $db;
		$ar_fields=array();
		if ($id_kat == null) {
			$res = $db->querynow("
				select * from field_def where FK_TABLE_DEF=".$this->ar_filter['FK_TABLE_DEF']."
				and B_NEEDED=1 and B_ENABLED=1");
			while($row = mysql_fetch_assoc($res['rsrc'])) {
				$ar_fields[$row['F_NAME']] = $row;
			}
		} else {
			$res = $db->querynow("
				SELECT f.*, kf.B_ENABLED, kf.B_NEEDED, kf.B_SEARCHFIELD
				FROM field_def f
				LEFT JOIN kat2field kf ON kf.FK_FIELD=f.ID_FIELD_DEF AND FK_KAT=".(int)$id_kat."
				WHERE f.FK_TABLE_DEF=".$this->ar_filter['FK_TABLE_DEF']." AND kf.B_NEEDED=1 AND kf.B_ENABLED=1");
			while($row = mysql_fetch_assoc($res['rsrc'])) {
				$ar_fields[$row['F_NAME']] = $row;
			}
		}
		return $ar_fields;
	}

	/**
	 * sets current filter
	 *
	 * @param int $id_filter filter id
	 * @return int $id_filter
	 */
	public function set_filter($id_filter) {
		$this->id_filter = (int)$id_filter;
		return $this->id_filter;
	}

	/**
	 * gets import setting from db
	 *
	 * @return array settings
	 */
	public function get_import_settings() {
		global $db;
		$settings = array();
		$fk_field_def = array();

		$res = $db->querynow("
			select
				im.*
			from
				import_settings im
			left join
				field_def fd ON im.FK_FIELD_DEF=fd.ID_FIELD_DEF
			where
				im.FK_IMPORT_FILTER=".$this->id_filter."
				AND im.FK_FIELD_DEF IS NOT NULL
			ORDER BY
				fd.F_ORDER ASC");
		if(!empty($res['str_error'])) {
			$this->err('SETTING_DB_FAIL');
			return false;
		} else {
			while($row = mysql_fetch_assoc($res['rsrc'])) {
				$settings[$row['CSV_COL']] = $row;
				$fk_field_def[$row['FK_FIELD_DEF']] = $row['FK_FIELD_DEF'];
			}
			$this->get_def_fields($fk_field_def);
			foreach($settings as $col => $data) {
				$settings[$col]['DEF_NAME'] = $this->ar_field_def[$data['FK_FIELD_DEF']]['V1'];
				$settings[$col]['DEF_SQL_NAME'] = $this->ar_field_def[$data['FK_FIELD_DEF']]['F_NAME'];
				$settings[$col]['DEF_TYP'] = $this->ar_field_def[$data['FK_FIELD_DEF']]['F_TYP'];
				$settings[$col]['FK_LISTE'] = $this->ar_field_def[$data['FK_FIELD_DEF']]['FK_LISTE'];
			}
		}
		$this->ar_settings = $settings;
		return $settings;
	}

	/**
	 * gets field def. for jqGrid()
	 *
	 * @return string
	 */
	public function get_import_table_fields() {
		global $db, $langval;
		$ar_list_options = array();
		// editoptions:{value:"FE:FedEx;IN:InTime;TN:TNT;AR:ARAMEX"}
		$ar_editable = array(
			'TEXT' => array(
				'edittype' => "text",
				'width' => 'width: 200',
			),
			'LONGTEXT' => array(
				'edittype' => "textarea",
				'classes' => 'texted',
				'width' => 'width: 300',
			),
			'INT' => array(
				'edittype' => "text",
				'width' => 'width: 100',
			),
			'FLOAT' => array(
				'edittype' => "text",
				'width' => 'width: 100',
				'align' => 'right',
			),
			'LIST' => array(
				'edittype' => 'select',
				'width' => 'width: 120',
			)
		);
		if(empty($this->ar_filter)) {
			$this->get_filter_data($this->id_filter);
		}
		$this->get_import_settings();
		$ar_retun = array(
			'NAMES' => array(),
			'MODELS' => array(),
		);
		foreach($this->ar_settings as $col => $col_data) {

			$edit = "false";
			$edittype = "''";
			$classes=false;
			$width = "width: 100";
			$editoptions = "";
			$align='left';
			$formatter='';

			if($col_data['FK_FIELD_DEF'] == -1) {
				$col_data['DEF_SQL_NAME'] = 'IMPORT_PIC';
				$col_data['DEF_NAME'] = 'Bild';
				$edit = 'true';
				$edittype = 'text';
				$formatter = "formatter:'link',formatoptions:{'target': '_blank'},";
			}

			if(is_array($ar_editable[$col_data['DEF_TYP']]) && !strstr($col_data['DEF_SQL_NAME'], 'FK_')) {
				$edit = 'true';
				$edittype = $ar_editable[$col_data['DEF_TYP']]['edittype'];
				if($ar_editable[$col_data['DEF_TYP']]['classes']) {
					$classes = "classes: \"".$ar_editable[$col_data['DEF_TYP']]['classes']."\"";
				}
				if($ar_editable[$col_data['DEF_TYP']]['width']) {
					$width = $ar_editable[$col_data['DEF_TYP']]['width'];
				}
				if($ar_editable[$col_data['DEF_TYP']]['align']) {
					$align = $ar_editable[$col_data['DEF_TYP']]['align'];
				}
				if($ar_editable[$col_data['DEF_TYP']]['edittype'] == 'select') {
					if(!isset($ar_list_options[$col_data['FK_LISTE']])) {
						$query = "
							select
								t.*,
								s.V1,
								s.V2,
								s.T1
							from
								`liste_values` t
							left join
								string_liste_values s
									on s.S_TABLE='liste_values'
									and s.FK=t.ID_LISTE_VALUES
									and s.BF_LANG=if(t.BF_LANG_LISTE_VALUES & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_LISTE_VALUES+0.5)/log(2)))
							where
								t.FK_LISTE=".(int)$col_data['FK_LISTE']."
								order by t.ORDER ASC";
						$ar_tmp = array();
						$res = $db->querynow($query);
						while($row = mysql_fetch_assoc($res['rsrc'])) {
							$ar_tmp[] = $row['ID_LISTE_VALUES'].": ".stdHtmlentities($row['V1']);
						}
						$ar_list_options[$col_data['FK_LISTE']] = "editoptions: {'value':'".implode(";", $ar_tmp)."'},";
					}
					$editoptions = $ar_list_options[$col_data['FK_LISTE']];
				}
			}
			$ar_return['NAMES'][] = "'".trim($col_data['DEF_NAME'])."'";
			$ar_return['MODELS'][] = "{
				".$formatter."
				".$editoptions."
				name: '".$col_data['DEF_SQL_NAME']."',
				align: '".$align."',
				index: '".$col_data['DEF_SQL_NAME']."',
				search: false,
				editable: ".$edit.",
				edittype: \"".$edittype."\",
				".$width."
				".($classes ? ', '.$classes : '')."
			}";
		}
		return $ar_return;
	}



	/**
	 * saves csv data in temp table
	 *
	 * @param string $csv csv
	 * @return boolean
	 */
	public function move_to_temp_table($csv, $importFile = null, $startAtCsvLine = 0, $readLimit = null) {
		global $db, $uid;
		$this->get_filter_data($this->id_filter);
		$this->get_import_settings();
		$this->load_user_lib();
		//$this->delete_temp_data();

		$insert = array();

		$tmp = $this->readCsv2Array($csv, $this->ar_filter['TRENNER'], $this->ar_filter['ISEMPTY'], $startAtCsvLine, $readLimit);

        $csvLines = $tmp['data'];
        $csvIsFinished = $tmp['isFinished'];

        if($startAtCsvLine == 0) {
		    $header = array_shift($csvLines);
        }

		/*$insert = array();
		$ar_lines = $this->csv2array($csv);
		$header = array_shift($ar_lines);

		var_dump($ar_lines);
		for($i=0; $i<count($ar_lines); $i++) {
			$ar_line = $this->csvline2array($ar_lines[$i], $this->ar_filter['TRENNER'], $this->ar_filter['ISEMPTY']);


			$insert[] = $this->insert_string($ar_line, $importFile);
		}die();*/

		foreach($csvLines as $key=>$ar_line) {
			$insert[] = $this->insert_string($ar_line, $importFile);
		}


		$fields = $this->get_insert_fields();
		#echo $fields;
		#die(ht(dump($insert)));
		$query = "
			INSERT INTO
				import_tmp_".strtolower($this->ar_filter['IDENT'])."
				(FK_USER,FK_IMPORT_FILE,".$fields.")
				VALUES
				".implode("\n,", $insert);

		$res = $db->querynow($query);

		if(!empty($res['str_error'])) {
			echo ht(dump($res));
			$this->err('INSERT_FAIL');
			return false;
		}

		return array('isFinished' => $csvIsFinished, 'result' => true);
	}

	/**
	 * deletes temp data from temp_table

	 */
	public function delete_temp_data($user_id=NULL) {
		global $db, $uid;
		$fk_user = (is_null($user_id) ? $uid : $user_id);
		$db->querynow("
			delete from
				import_tmp_".strtolower($this->ar_filter['IDENT'])."
			where
				FK_USER=".$fk_user);
	}

	public function get_sql_select_fields() {
		if(empty($this->ar_settings)) {
			$this->get_import_settings();
		}
		$ar_return = array();
		foreach($this->ar_settings as $col => $col_data) {
			if($col_data['FK_FIELD_DEF'] == -1) {
				$col_data['DEF_SQL_NAME'] = 'IMPORT_PIC';
			}
			$ar_return[] = "`".$col_data['DEF_SQL_NAME']."`";
		}
		return $ar_return;
	}

	function getkey2col_array() {
		if(is_null($this->ar_settings)) {
			$this->get_import_settings();
		}
		$ar_return = array();
		foreach($this->ar_settings as $col_number => $col_data) {
			$ar_return[$col_data['DEF_SQL_NAME']] = $col_number;
		}
		return $ar_return;
	}

	function handle_import_value($value, $colnum, $key) {
		global $db, $langval;
		//$ar_tmp_values;
		if(is_null($this->ar_settings)) {
			$this->get_import_settings();
		}
		$value = preg_replace("/\n|\r|\r\n/si", " ", $value);
		if(!empty($this->ar_settings[$colnum]['USER_FUNCTION'])) {
			$lib = $this->load_user_lib();
			$fkt='REV_'.$this->ar_settings[$colnum]['USER_FUNCTION'];
			if(method_exists($lib, $fkt)) {
				$value = $lib->$fkt($value);
			}
		}
		#echo $this->ar_settings[$colnum]['USER_FUNCTION']."\n";
		#print_r($this->ar_settings[$colnum]); die();
		if($this->ar_settings[$colnum]['DEF_TYP'] == 'LIST') {
			if(!isset($this->list_values[$this->ar_settings[$colnum]['FK_LISTE']])) {
				$query = "
					select
						t.*,
						s.V1,
						s.V2,
						s.T1
					from
						`liste_values` t
					left join
						string_liste_values s
							on s.S_TABLE='liste_values'
							and s.FK=t.ID_LISTE_VALUES
							and s.BF_LANG=if(t.BF_LANG_LISTE_VALUES & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_LISTE_VALUES+0.5)/log(2)))
					where
						t.FK_LISTE=".(int)$this->ar_settings[$colnum]['FK_LISTE']."
						order by t.ORDER ASC";
				$ar_tmp=array();
				$res = $db->querynow($query);
				while($row = mysql_fetch_assoc($res['rsrc'])) {
					$ar_tmp[$row['ID_LISTE_VALUES']] = $row['V1'];
				}
				$this->list_values[$this->ar_settings[$colnum]['FK_LISTE']] = $ar_tmp;
			}
			$value = $this->list_values[$this->ar_settings[$colnum]['FK_LISTE']][(int)$value];
		#	echo dump($this->list_values);
		}
		return $value;
	}

	public function calc_import() {
		global $db, $uid;
		$all = $db->fetch_atom("
			select count(*) from import_tmp_".strtolower($this->ar_filter['IDENT'])."
			where FK_USER=".$uid);
		$fail = $db->fetch_atom("
			select count(*) from import_tmp_".strtolower($this->ar_filter['IDENT'])."
			where FK_USER=".$uid."
			and (
				(FK_KAT IS NULL OR FK_KAT = 0)
			)");
		return array(
			'COUNT' => $all,
			'FAIL' => $fail,
			'CALC' => ($all-$fail),
		);
	}

	/**
	 * gets next ad to be imported
	 *
	 * @return array ad data
	 */
	public function get_next() {
		global $db, $uid;
		$id_name = "ID_".strtoupper($this->ar_filter['T_NAME']);

		$ar_data = $db->fetch1("
			select
				*
			from
				import_tmp_".strtolower($this->ar_filter['IDENT'])."
			where
				FK_USER=".$uid."
			ORDER BY
				".$id_name." ASC
			LIMIT 1");
		return $ar_data;
	}

	public function pre_check($ar_data) {
		global $uid, $db, $nar_systemsettings, $langval, $ab_path;
		$this->last_err_fields=NULL;

		$id_name = "ID_".strtoupper($this->ar_filter['T_NAME']);
		$id_value = $ar_data[$id_name];
		$file_log = fopen($ab_path."cache/log_import_".md5("import_hash_user_".$ar_data["FK_USER"]).".log", "a+");

		if(!is_array($this->ar_list2value)) {
			$this->ar_list2value = array();
		}
		if(!$ar_data['FK_KAT']) {
			$this->last_err_fields = array('FK_KAT');
			fwrite($file_log, "[".date("d.m.Y H:i:s")."] Validierung von Anzeige #".$id_value." fehlgeschlagen! Keine Kategorie gewählt!\n");
			fclose($file_log);
			return false;
		} else {
			if(isset($ar_data['LU_LAUFZEIT'])) { $_REQUEST['LU_LAUFZEIT'] = $ar_data['LU_LAUFZEIT']; }
			if($uid == null && isset($ar_data['FK_USER'])) { $uid = $ar_data['FK_USER']; }

			$user = $db->fetch1("SELECT * FROM `user` WHERE ID_USER=".$uid);
			if(empty($ar_data['STAMP_START'])) {
				$ar_data['STAMP_START'] = date('Y-m-d H:i:s');
			}
			$ar_data['STAMP_END'] = date('Y-m-d H:I:s', strtotime('+'.$db->fetch_atom("select `VALUE` from lookup where ID_LOOKUP=".(int)$_REQUEST['LU_LAUFZEIT']).' days'));
			if(empty($ar_data['ZIP'])) {
				$ar_data['ZIP'] = $user['PLZ'];
			}
			if(empty($ar_data['CITY'])) {
				$ar_data['CITY'] = $user['ORT'];
			}
			if(empty($ar_data['FK_COUNTRY'])) {
				$ar_data['FK_COUNTRY'] = $user['FK_COUNTRY'];
			}


			$ar_data['AD_TABLE'] = $this->ar_filter['T_NAME'];
            if ($ar_data['FK_PACKET_ORDER'] == null) {
			    $ar_data['FK_PACKET_ORDER'] = (int)$_REQUEST['ID_PACKET_ORDER'];
            }
			$ar_req = $this->get_req_fields($ar_data["FK_KAT"]);
			$err = array();
			$ar_ignore = array('LONGITUDE', 'LATITUDE', 'STATUS', 'LIEFERUNG', 'FK_MAN');
			foreach($ar_req as $field => $ar_field_def) {
				if(!in_array($field, $ar_ignore) && empty($ar_data[$field])) {
					$err[] = $field;
				} elseif($ar_field_def['F_TYP'] == 'LIST' && (int)$ar_field_def['FK_LISTE'] > 0) {
					$fk = (int)$ar_field_def['FK_LISTE'];
					if(!isset($this->ar_list2value[$fk])) {
						$query = "
							select
								t.ID_LISTE_VALUES
							from
								liste_values t
							where
								t.FK_LISTE=".$fk."
								order by t.ORDER ASC";
						$tmp=array();
						$res = $db->querynow($query);
						while($row = mysql_fetch_row($res['rsrc'])) {
							$tmp[] = $row[0];
						}
						$this->ar_list2value[$fk] = $tmp;
					}
					if(!in_array((int)$ar_data[$field], $this->ar_list2value[$fk])) {
						$err[] = $field;
						fwrite($file_log, "[".date("d.m.Y H:i:s")."] Validierung von Anzeige #".$id_value." fehlgeschlagen! Liste '".$field."' enthält einen ungültigen Wert!\n");
					}
				}
			}
            if(($ar_data['LU_LAUFZEIT'] == "" && $_REQUEST['LU_LAUFZEIT'] == "") OR $ar_data['LU_LAUFZEIT'] == 0) {
                $err[] = "LU_LAUFZEIT";
				fwrite($file_log, "[".date("d.m.Y H:i:s")."] Validierung von Anzeige #".$id_value." fehlgeschlagen! Keine gültige Laufzeit gewählt!\n");
            }

			#die(dump($this->ar_list2value));
			if(count($err)) {
				$this->last_err_fields=$err;
				fwrite($file_log, "[".date("d.m.Y H:i:s")."] Validierung von Anzeige #".$id_value." fehlgeschlagen! ".
					"Folgende Felder sind nicht (korrekt) ausgefüllt:\n\t".implode(", ", $err)."\n");
				fclose($file_log);
				return false;
			} else {
				fclose($file_log);
				return $ar_data;
			}
		}
	}

	/**
	 * tries to import one ad per call
	 *
	 * @param array $ar_data import data
	 * @return bool
	 */
    public function import_one($ar_data) {
        global $uid, $db, $nar_systemsettings, $langval, $ab_path;
        require_once $ab_path.'sys/lib.ads.php';
		require_once $ab_path."sys/packet_management.php";

        if(!$this->ar_settings) {
            $this->get_import_settings();
        }

        if(($ar_data = $this->pre_check($ar_data)) && ($ar_data['FK_PACKET_ORDER'] != '-1')) {
            if(isset($ar_data['LU_LAUFZEIT'])) { $_REQUEST['LU_LAUFZEIT'] = $ar_data['LU_LAUFZEIT']; }

            $ar_insert=array();
            $tmp = array();
            $res = $db->querynow("
                select
                    F_NAME
                from
                    field_def
                where
                    FK_TABLE_DEF=".$this->ar_filter['FK_TABLE_DEF']
            );

            while($row = mysql_fetch_row($res['rsrc'])) {
                $tmp[] = $row[0];
            }

            for($i=0; $i<count($tmp); $i++) {
                if(isset($ar_data[$tmp[$i]]) && $tmp[$i] != 'ID_'.strtoupper($this->ar_filter['T_NAME'])) {
                    $value = $ar_data[$tmp[$i]];
                    $ar_insert[$tmp[$i]] = trim($value);
                }
            }

            $usercontent = $db->fetch1("SELECT * FROM usercontent WHERE FK_USER = '".(int)$ar_insert['FK_USER']."' ");
            if(trim($ar_data['AD_AGB']) == "") {
                // keine AGB, nimm User AGB
                $ar_insert['AD_AGB'] = $usercontent['AGB'];
            }
            if(trim($ar_data['AD_WIDERRUF']) == "") {
                // keine AGB, nimm User AGB
                $ar_insert['AD_WIDERRUF'] = $usercontent['WIDERRUF'];
            }


            $ar_insert['AD_TABLE'] = $this->ar_filter['T_NAME'];
            $ar_insert['CRON_DONE'] = 0;
            $ar_insert['STATUS'] = 0;
            $ar_insert['FK_PACKET_ORDER'] = $ar_data['FK_PACKET_ORDER'];
            $ar_insert['STAMP_END'] = '000-00-00';
            $ar_insert['LU_LAUFZEIT'] = $_REQUEST['LU_LAUFZEIT'];

            /**
             * ÃberprÃ¼fen ob es eine aktive Anzeige mit selben Import Identifier gibt
             */
            $isDuplicate = false;
            if(trim($ar_insert['IMPORT_IDENTIFIER']) != "") {
                $countAdWithIdentifier = $db->fetch_atom("SELECT COUNT(*) FROM ".$this->ar_filter['T_NAME']." WHERE FK_USER = '".(int)$ar_insert['FK_USER']."' AND IMPORT_IDENTIFIER = '".mysql_real_escape_string($ar_insert['IMPORT_IDENTIFIER'])."'  AND IMPORT_IDENTIFIER != '' AND IMPORT_IDENTIFIER IS NOT NULL  AND FK_KAT = '".mysql_real_escape_string($ar_insert['FK_KAT'])."'");
                if($countAdWithIdentifier == 1) {
                    $duplicateId = $db->fetch_atom("SELECT ID_".strtoupper($this->ar_filter['T_NAME'])." FROM ".$this->ar_filter['T_NAME']." WHERE FK_USER = '".(int)$ar_insert['FK_USER']."' AND IMPORT_IDENTIFIER = '".mysql_real_escape_string($ar_insert['IMPORT_IDENTIFIER'])."'  AND IMPORT_IDENTIFIER != '' AND IMPORT_IDENTIFIER IS NOT NULL AND FK_KAT = '".mysql_real_escape_string($ar_insert['FK_KAT'])."'");
                    $duplicate = $db->fetch1("SELECT * FROM ad_master WHERE ID_AD_MASTER = '".$duplicateId."' ");

                    $ar_insert['CRON_DONE'] = 0;
                    $ar_insert['STATUS'] = 0;
                    $ar_insert['STAMP_END'] = '000-00-00';
                    $ar_insert['STAMP_START'] = $duplicate['STAMP_START'];
                    $ar_insert['STAMP_DEACTIVATE'] = $duplicate['STAMP_DEACTIVATE'];

                    $ar_insert['ID_AD_MASTER'] = $duplicateId;
                    $ar_insert["ID_".strtoupper($this->ar_filter['T_NAME'])] = $duplicateId;

                    $isDuplicate = true;
                } else {
                    // Update statistic
                    AdManagment::logCreateArticle($ar_insert['FK_USER']);
                }
            }

            // Moderate ads?
            if ($nar_systemsettings["MARKTPLATZ"]["MODERATE_ADS"]) {
                $userIsAutoConfirmed = $db->fetch_atom("SELECT AUTOCONFIRM_ADS FROM `user` WHERE ID_USER=".$ar_data['FK_USER']);
                if ($userIsAutoConfirmed) {
                    $ar_insert["CONFIRMED"] = 1;
                } else {
                    $ar_insert["CONFIRMED"] = 0;
                    $ar_insert['CRON_DONE'] = 1;
                }
            } else {
                $ar_insert["CONFIRMED"] = 1;
            }

            $id = $db->update('ad_master', $ar_insert);

            $ar_insert['STAMP_END'] = NULL;
            $ar_insert["ID_".strtoupper($this->ar_filter['T_NAME'])] = $id;

            $db->querynow("INSERT INTO ".$this->ar_filter['T_NAME']." ("."ID_".strtoupper($this->ar_filter['T_NAME']).") VALUES('".$id."')");

            $id_artikel = $db->update($this->ar_filter['T_NAME'], $ar_insert);

            // Anzeige abrechnen
            /* WIRD IM CRON ERLEDIGT!
            $packets = PacketManagement::getInstance($db);
            $order = $packets->order_get($ar_insert['FK_PACKET_ORDER']);
            $order->itemAddContent("ad", $id);
            */

            if(!empty($ar_data['IMPORT_PIC'])) {
                $id_article = $id;
                $file_get = @file_get_contents($ar_data['IMPORT_PIC']);
                if($file_get) {
                    $uploads_dir = AdManagment::getAdCachePath($id_article, true, true);

                    file_put_contents($tmp_name = $uploads_dir.'/tmp', $file_get);
                    $name = 'imported.jpg';
                    require_once($ab_path."sys/lib.image.php");

                    $img_thumb = new image(12, $uploads_dir, true);
                    $img_thumb->check_file(array("tmp_name"=>$tmp_name,"name"=>$name));
                    $src = "/".str_replace($ab_path, "", $img_thumb->img);
                    $src_thumb = "/".str_replace($ab_path, "", $img_thumb->thumb);

                    if($isDuplicate) {
                        // lÃ¶sche altes default bild
                        $db->querynow("DELETE FROM ad_images WHERE FK_AD = '".mysql_real_escape_string($id_article)."' AND IS_DEFAULT = 1");
                    }

                    $image_data = array(
                        "FK_AD"       => $id_article,
                        "CUSTOM"      => 1,
                        "IS_DEFAULT"  => 1,
                        "SRC"         => $src,
                        "SRC_THUMB"   => $src_thumb
                    );
                    $db->update("ad_images", $image_data, true);
                }
            }
            
            if(!empty($ar_data['IMPORT_PAYMENT'])) {
                // TODO: Import von Zahlungsweisen implementieren
            } else if (!$isDuplicate) {
                require_once $ab_path."sys/lib.ad_payment_adapter.php";
                require_once $ab_path."sys/lib.payment.adapter.user.php";
                $adPaymentAdapterManagement = AdPaymentAdapterManagement::getInstance($db);
                $paymentAdapterUserManagement = PaymentAdapterUserManagement::getInstance($db);
                $userDefaultPaymentAdapters = $paymentAdapterUserManagement->fetchAllAutoCheckedPaymentAdapterByUser((int)$ar_insert['FK_USER']);
                // Payment Adapter
                $adPaymentAdapterManagement->updatePaymentAdapterForAd($id_artikel, $userDefaultPaymentAdapters);
            }
            return $id;

        } else {
            return false;
        }
    }

	/**
	 * deletes last ad from import_temp
	 * @return bool
	 */
	public function delete_last($id=NULL) {
		global $db, $uid;
		$id_name = "ID_".strtoupper($this->ar_filter['T_NAME']);

		$res = $db->querynow("
			delete from
				import_tmp_".strtolower($this->ar_filter['IDENT'])."
			where
				FK_USER=".$uid."
				".(is_null($id) ? '' : " and ID_".strtoupper($this->ar_filter['T_NAME']))."
			ORDER BY
				".$id_name." ASC
			LIMIT 1");
		return (empty($res['str_error']) ? true : false);
	}

	public function save_grid($ar_data) {
		global $db;

		if(empty($this->ar_filter)) {
			$this->get_filter_data($ar_data['ID_IMPORT_FILTER']);
		}
		if(empty($this->ar_settings)) {
			$this->get_import_settings();
		}

		$id = (int)str_replace('data_', '', $ar_data['id']);
		$ar_update = array();

		foreach($this->ar_settings as $col => $col_data) {
			if(isset($ar_data[$col_data['DEF_SQL_NAME']])) {
				$value = $ar_data[$col_data['DEF_SQL_NAME']];
				if($col_data['USER_FUNCTION'] && $col_data['DEF_TYP'] != 'LIST') {
					$lib = $this->load_user_lib();
					$fkt = $col_data['USER_FUNCTION'];
					if(method_exists($lib, $fkt)) {
						$value = $lib->$fkt($value);
					}
				}
				$value = trim($value);
				$ar_update[] = $col_data['DEF_SQL_NAME']." = '".mysql_real_escape_string($value)."'";
			}
		}
		if(!empty($ar_data['IMPORT_PIC'])) {
			$ar_update[] = "IMPORT_PIC = '".mysql_real_escape_string($ar_data['IMPORT_PIC'])."'";
		}
		$query = "
			update
				import_tmp_".strtolower($this->ar_filter['IDENT'])."
			SET
				".implode(",\n", $ar_update)."
			where
				ID_".strtoupper($this->ar_filter['T_NAME'])." = ".$id;
		$res = $db->querynow($query);
		return (empty($res['str_error']) ? true : false);
	}

	/**
	 * deletes filter from db
	 *
	 * @param $id
	 * @return unknown_type
	 */
	public function delete_filter($id) {
		global $db;
		$this->set_filter($id);
		$ar_filter = $this->get_filter_data($this->id_filter);
		### kick fk
		$res = $db->querynow("ALTER TABLE `kat`  DROP COLUMN `FK_".$ar_filter['IDENT']."`");

		if(empty($res['str_error'])) {
			### kick settings

            $resultDeleteFile = $db->querynow("delete from import_file WHERE FK_IMPORT_FILTER = '".$this->id_filter."' ");
            $resultDropData = $db->querynow("drop table import_tmp_".strtolower($ar_filter['IDENT'])." ");

			$res = $db->querynow("delete from import_settings where FK_IMPORT_FILTER=".$this->id_filter);
			if(empty($res['str_error'])) {
				### kick filter data
				$db->delete('import_filter', $this->id_filter);
			} else {
				echo ht(dump($res));
			}
		} else {
			echo ht(dump($res));
		}
	}


	/**
	 * @return the $importFile
	 */
	public function getImportFile() {
		return $this->importFile;
	}

	/**
	 * @param int $importFile
	 */
	public function setImportFile($importFile) {
		$this->importFile = $importFile;
	}



}

?>