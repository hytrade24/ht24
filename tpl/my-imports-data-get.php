<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once 'sys/lib.ad_import.php';
require_once "admin/sys/tabledef.php";

$importFileId = (int)$_REQUEST['ID_IMPORT_FILE'];

$adImportManagement = AdImportManagement::getInstance($db);

if(!$adImportManagement->existsImportFileByUserId($importFileId, $uid)) { die(); }


$categoryTable = $adImportManagement->getCategoryTableByImportFileId($importFileId);


$npage = ($_REQUEST['npage'] ? (int)$_REQUEST['npage'] : 1);
$perpage = ($_REQUEST['rows'] ? (int)$_REQUEST['rows'] : 20);
$offset = ($perpage*$npage)-$perpage;
$sortBy = ($_REQUEST['sidx'] ? $_REQUEST['sidx'] : "ID_".strtoupper($categoryTable));
$sortDir = ($_REQUEST['sord'] ? $_REQUEST['sord'] : "ASC");
$status = ($_REQUEST['IMPORT_STATUS'] ? $_REQUEST['IMPORT_STATUS'] : null);


$param = array(
	'limit' => $perpage,
	'offset' => $offset,
	'sort' => $sortBy." ".$sortDir,
	'status' => $status
);

$imports = $adImportManagement->fetchAllImportsByImportFileIdAndUserId($importFileId, $uid, $param);
$countImports = $adImportManagement->countImportsByImportFileIdAndUserId($importFileId, $uid, $param);

tabledef::getFieldInfo($categoryTable);

$colNames = array();
$colModel = array();

$excludeCols = array("FK_USER", "STATUS", "FK_MAN", "FK_PRODUCT",  "STAMP_START", "STAMP_END", "STAMP_DEACTIVATE");


$response = array(
	'page' => $npage,
	'total' => ceil($countImports/$perpage),
	'records' => $countImports,
	'rows' => array()
);


foreach ($imports as $key=>$import) {	
	$cells = array();
	
	$cells[] = $import['IMPORT_STATUS'];
	
	foreach (tabledef::$field_info as $key=>$col) {	
		if(!in_array($col['F_NAME'], $excludeCols)) {
			switch ($col['F_TYP']) {
				case 'LIST':				
					// Feldtyp Liste				
					if($import[$col['F_NAME']] != "") {
						$listOptionValue = $db->fetch_atom("
							  select
									s.V1
							  from `liste_values` t
							  left join string_liste_values s
								on s.S_TABLE='liste_values'
									and s.FK=t.ID_LISTE_VALUES
									and s.BF_LANG=if(t.BF_LANG_LISTE_VALUES & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_LISTE_VALUES+0.5)/log(2)))
							  where
									t.FK_LISTE=".$col['FK_LISTE']." AND t.ID_LISTE_VALUES = ".mysql_real_escape_string($import[$col['F_NAME']]) );

						$colValue = $listOptionValue;
					} else {
						$colValue = "";
					}
					
					break;
				case 'LONGTEXT':
					$colValue = preg_replace("/\\n/", "", $import[$col['F_NAME']]);
					if(preg_match("/\<(.*)\>(.*)\<\/(.*)\>/", $colValue)) { $colValue = "[HTML CODE]"; }
					break;
				default:
					if($col['F_NAME'] == "FK_KAT") {
						// Sonderfall Kategorie
						if($import[$col['F_NAME']] > 0) {
							$listOptionValue = $db->fetch_atom("
								select
							        s.V1
							      from
							        `string_kat` s
							      WHERE
							      	s.S_TABLE = 'kat'
							      	AND s.BF_LANG = '128'
							        AND s.FK = ".mysql_real_escape_string($import[$col['F_NAME']])."
							");
						} else {
							$listOptionValue = "";
						}
						$colValue = $listOptionValue;
					} elseif ($col['F_NAME'] == "FK_COUNTRY") {
						// Sonderfall Land
						if($import[$col['F_NAME']] > 0) {
							$listOptionValue = $db->fetch_atom("
								select
							        s.V1
							      from
							        `string` s
							      WHERE
							      	s.S_TABLE = 'country'
							      	AND s.BF_LANG = '128'
							        AND s.FK = ".mysql_real_escape_string($import[$col['F_NAME']])."
							");
						} else {
							$listOptionValue = "";
						}
						$colValue = $listOptionValue;
					} else {					
						$colValue = $import[$col['F_NAME']];
					}
					break;
			}
			
			$cells[] = $colValue;
		}
	}
	
	$cells[] = $import['IMPORT_PIC'];
	
	$response['rows'][] = array(
		'id' => $import['ID_'.strtoupper($categoryTable)],
		'cell' => $cells
	);
}

echo json_encode($response);