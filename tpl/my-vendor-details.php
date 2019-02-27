<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once 'sys/lib.vendor.php';
require_once 'sys/lib.vendor.category.php';

$userId = $uid;
$vendorManagement = VendorManagement::getInstance($db);
$vendorCategoryManagement = VendorCategoryManagement::getInstance($db);

$vendor = $vendorManagement->fetchByUserId($userId);
$vendorId = $vendor['ID_VENDOR'];

$tpl_content->addvar("ID_VENDOR", $vendorId);

if (!empty($ar_params[1])) {
    $tpl_content->addvar("NOTICE_".strtoupper($ar_params[1]), 1);
}

$cachedir = $GLOBALS['ab_path']."cache/vendor/vendor_details";
if (!is_dir($cachedir)) {
	mkdir($cachedir,0777,true);
}
$cachefile_vendor_details = $cachedir."/".$GLOBALS['s_lang']."."."vendor_details_".$userId."_".$vendor["ID_VENDOR"].".htm";

if(isset($_POST) && $_POST['DO'] == 'SAVE') {
    $err = array();

    if (!empty($err)) {
        $tpl_content->addvar("errors", get_messages("ANBIETER", implode(',', $err)));
        $tpl_content->addvar("VENDOR_DO_ENABLE", 1);
        $_POST['STATUS'] = 0;
    }
    else {
        $vendor_master_row = array(
            "ID_VENDOR_MASTER"  =>  $vendorId
        );

        foreach ( $_POST["tmp_type"] as $key => $row ) {
            if ( isset($_POST[$key]) ) {
	            $vendor_master_row[$key] = mysql_real_escape_string($_POST[$key]);
            }
            else if ( isset($_POST["check"][$key]) ) {
	            $vendor_master_row[$key] = 'x'.implode("x",$_POST["check"][$key]).'x';
            }
            else {
	            $vendor_master_row[$key] = '';
            }
        }

        $sql = 'SELECT *
                    FROM vendor_master v
                    WHERE v.ID_VENDOR_MASTER = ' . $vendorId;
        $result = $db->fetch1( $sql );
        if ( is_array($result) ) {//use update method
	        $result = $db->update("vendor_master",$vendor_master_row);
        }
        else {//use custom query
            $query = 'INSERT INTO vendor_master ('.implode(",",array_keys($vendor_master_row)).')
                        VALUES
                      ("'.implode('","',array_values($vendor_master_row)).'")';

			$result = $db->querynow( $query );
		}
		if ( $result ) {
			$tpl_content->addvar("NOTICE_SUCCESS",1);
			//.................
			if ( file_exists($cachefile_vendor_details) ) {
				@system("rm -f ".$cachefile_vendor_details);
			}
			//.................
		}

        $vendor = $vendorManagement->fetchByUserId( $userId );
    }
}

$sql = 'SELECT v.FK_KAT
            FROM vendor_category v
            WHERE v.FK_VENDOR = ' . $vendorId;

$vendor_categories = $db->fetch_table( $sql );

$vendor_categories_flatten = array();

foreach ( $vendor_categories as $row ) {
    array_push($vendor_categories_flatten,$row["FK_KAT"]);
}

$tpl_content->isTemplateRecursiveParsable = true;
$tpl_content->isTemplateCached = true;

$sql = 'SELECT 
			ID_FIELD_GROUP 
		FROM `field_group` a
		INNER JOIN `table_def` t
			ON t.T_NAME = "vendor_master"
			AND a.FK_TABLE_DEF = t.ID_TABLE_DEF';

$result_field_groups = $db->fetch_col( $sql );

$arInputBlocks = '';

$fieldGroups = array(null);
$fieldGroups = array_merge($fieldGroups,$result_field_groups);


foreach ( $fieldGroups as $fieldGroup ) {
	$arInputBlocks .= CategoriesBase::getInputFieldsCache(
		$vendor_categories_flatten,
		$vendor,
		false,
		$fieldGroup,
		true
	);
}

$tpl_content->addvar("vendor_details",$arInputBlocks);

?>
