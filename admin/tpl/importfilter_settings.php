<?php
/* ###VERSIONSBLOCKINLCUDE### */


$SILENCE=false;
$max = 4;
$fields_array = array();
$ar_selected = array(0);

include 'sys/lib.import_export_filter.php';
include 'sys/lib.import.php';
include 'sys/lib.userfkt.php';

function addoptions(&$row, $i) {
	global $ar_functions;
	$opts = array();
	for($i=0; $i<count($ar_functions); $i++) {
		$selected = '';
		if($ar_functions[$i]['NAME'] == $row['USER_FUNCTION']) {
			$selected = ' selected';
		}
		$opts[] = '<option value="'.stdHtmlentities($ar_functions[$i]['NAME']).'" '.$selected.'>'.stdHtmlentities($ar_functions[$i]['LABEL']).'</option>';
	}
	$row['options'] = implode("\n", $opts);
}

function handle_filter_settings(&$row, $i) {
	global $fields_array, $ar_selected;

	if($row['FK_FIELD_DEF']) {
		$ar_selected[] = $row['FK_FIELD_DEF'];
	}
	$row['V1'] = $fields_array[$row['FK_FIELD_DEF']];
	$row['NO'] = $row['CSV_COL'];
	$tmp = @unserialize(str_replace(array('°', '^'), array('{', '}'), $row['SER_EX_DATA']));
	if($tmp) {
		$row['EXAMPLE_DATA'] = stdHtmlentities(implode("\n", $tmp));
		$row['SER_DATA'] = stdHtmlentities($row['SER_EX_DATA']);
	}
	$row['COL'] = $row['CSV_COL_NAME'];
	addoptions($row,$i);
}

function build_fields_array($row, $i) {
	global $fields_array;
	#if($row[])
	$fields_array[$row['ID_FIELD_DEF']] = $row['V1'];
}

$import = new import();

$id_filter = (int)$_REQUEST['ID_IMPORT_FILTER'];
$ar_filter = $import->get_filter_data($id_filter);

$userfkt = new userfunctions($ar_filter['IDENT']);

$ar_functions = $import->get_user_functions($userfkt);

if($_REQUEST['saved']) {
	$tpl_content->addvar("SAVE", 1);
}

if(empty($_POST)) {
	$tpl_content->addvars($ar_filter);
	#echo ht(dump($import));
	$ar_settings = $import->get_user_settings();
	if(!empty($ar_settings)) {
		$ar_fields = $import->get_table_fields();

		#echo ht(dump($ar_settings));
		$tpl_content->addlist("liste_felder", $ar_fields, "tpl/de/importfilter_settings.felder.htm", 'build_fields_array');
		$tpl_content->addlist("liste", $ar_settings, 'tpl/de/importfilter_settings.row.htm', 'handle_filter_settings');

		$tpl_content->addvar('ar_selected', implode(",", $ar_selected));
		$ar_js = array();
		for($i=0; $i<count($ar_settings); $i++) {
			$ar_js[] = $ar_settings[$i]['CSV_COL'].': '."{ name: '".$ar_settings[$i]['CSV_COL_NAME']."', sql: '".$ar_settings[$i]['FK_FIELD_DEF']."', sqlname: '".$fields_array[$ar_settings[$i]['FK_FIELD_DEF']]."' }";
		}
		$tpl_content->addvar("liste_feld2feld", implode(",\n", $ar_js));
	}
} else {
	if(!empty($_FILES['DATEI']['tmp_name'])) {
		$tpl_content->addvars($ar_filter);
		$tpl_content->addvar('unsaved', 1);
        $ar_file = $import->readCsv2Array($_FILES['DATEI']['tmp_name'], $ar_filter['TRENNER'], $ar_filter['ISEMPTY'], 0, $max);
		$ar_file = $ar_file['data'];
        $ar_config = array();
        $header = array_shift($ar_file);
        $ar_fields = $import->get_table_fields();

        $ar_example_data = $ar_file;

		## merge data
		$ar_js = array();
		for($i=0; $i<count($header); $i++) {
            $fieldId = false;
            $fieldName = $header[$i];
            $fieldNameEx = $fieldName;
            foreach ($ar_fields as $ar_field_cur) {
                if ($ar_field_cur['F_NAME'] == $fieldName) {
                    $fieldId = $ar_field_cur['ID_FIELD_DEF'];
                    $fieldNameEx = $ar_field_cur['V1'];
                    $ar_config[$i] = $fieldId;
                    $ar_selected[] = $fieldId;
                }
            }

            $tmp_data = $import->get_example_data($ar_example_data, $i, $max);
			$ar_tmp_data = explode("\n", $tmp_data);
			$ser_data = (count($ar_tmp_data) ? serialize($ar_tmp_data) : NULL);
			$ser_data = (is_null($ser_data) ? NULL : stdHtmlentities(str_replace(array('{','}'), array('°','^'), $ser_data)));
			#echo ht(dump($tmp_data));
			#echo ht(dump($ar_tmp_data));

			$ar_js[] = $i.': '."{ name: '".$header[$i]."' }";
			$header[$i] = array(
				'COL' => $header[$i],
				'EXAMPLE_DATA' => $tmp_data,
				'SER_DATA' => $ser_data,
				'NO' => $i
			);
            if ($fieldId !== false) {
                $header[$i]["FK_FIELD_DEF"] = $fieldId;
                $header[$i]["V1"] = $fieldNameEx;
            }
		}

        $save = $import->save_settings(array("FK_FIELD_DEF" => $ar_config));
		$tpl_content->addvar("liste_feld2feld", implode(",\n", $ar_js));
		$tpl_content->addlist("liste", $header, 'tpl/de/importfilter_settings.row.htm','addoptions');
		$tpl_content->addlist("liste_felder", $ar_fields, "tpl/de/importfilter_settings.felder.htm");
	} else {
		$save = $import->save_settings($_POST);
		if($save) {
			die(forward("index.php?page=importfilter_settings&ID_IMPORT_FILTER=".$id_filter."&saved=1"));
		}
	}
}

?>