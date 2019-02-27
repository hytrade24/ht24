<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once 'sys/lib.ad_import.php';

$readCsvLinesPerRequest = 10;

if(empty($_FILES['CSV']['tmp_name'])) {
    if(!isset($_POST['DO']) || $_POST['DO'] != "IMPORTCSV") {
        // Import Filter
        $res = $db->querynow("
            select
                t.ID_IMPORT_FILTER,
                s.V1,
                s.T1
            from
                `import_filter` t
            left join
                string_app s
                on s.S_TABLE='import_filter'
                and s.FK=t.ID_IMPORT_FILTER
                and s.BF_LANG=if(t.BF_LANG_APP & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_APP+0.5)/log(2)))
            where
                t.B_AKTIV=1
            order by
                s.V1 ASC");

        $ar_opt = $ar_desc = array(); #die(ht(dump($res)));
        while($row = mysql_fetch_assoc($res['rsrc'])) {
            $ar_opt[] = '<option value="'.$row['ID_IMPORT_FILTER'].'">'.$row['V1'].'</option>';
            $ar_desc[] = '<input type="hidden" name="filter_desc[]" id="filter_desc_'.$row['ID_IMPORT_FILTER'].'" value="'.nl2br(stdHtmlentities($row['T1'])).'" />';
        }
        if(!empty($ar_opt)) {
            $tpl_content->addvar('options', implode("\n", $ar_opt));
            $tpl_content->addvar('descs', implode("\n", $ar_desc));
        }
    } else {
        $importFileId = $_POST['IMPORT_FILE_ID'];
        $line = $_POST['LINE'];

        $adImportManagement = AdImportManagement::getInstance($db);
        $result = $adImportManagement->loadImportFileToTmpTable($importFileId, $line, $readCsvLinesPerRequest);

        if($result['result'] == true) {
            if($result['isFinished'] == true) {
                echo json_encode(array('finished' => true));
            } else {
                echo json_encode(array('finished' => false, 'line' => $result['line']));
            }
        }
        die();
    }
} else {
	/**
	 * Upload der CSV Datei nach cache/import. Dort wird die CSV per Cron verarbeitet
	 */

	if(preg_match("/\.csv$/", $_FILES['CSV']['name'])) {
		$csvFilename = md5($_FILES['CSV']['tmp_name']).'.csv';
		$csvFile = $ab_path.'cache/import/'.$csvFilename;

		move_uploaded_file($_FILES['CSV']['tmp_name'], $csvFile);
		chmod($csvFile, 0777);

		$adImportManagement = AdImportManagement::getInstance($db);
		$importFileId = $adImportManagement->createImportFile($uid, $_POST['import_file_name'], $_POST['ID_IMPORT_FILTER'], $csvFile);

        echo $importFileId;
        die();
		//die(forward('/my-pages/my-imports.htm'));
	}
}