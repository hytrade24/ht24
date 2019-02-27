<?php

$arExportSkipColumns = array("ID_HDB_PRODUCT", "FK_MAN", "FK_TABLE_DEF", "FULL_PRODUKTNAME", "HDB_TABLE", "PRODUCT_TYPE_DESCRIPTION", "DATA_USER");

require_once $ab_path . 'sys/lib.hdb.php';
require_once $ab_path . 'sys/lib.hdb.merge.php';
$manufacturerDatabaseManagement = ManufacturerDatabaseManagement::getInstance($db);
$manufacturerMergeManagement = ManufacturerMergeManagement::getInstance($db);

$tplDataColumn = new Template('tpl/de/hdb_products.datacol.htm');

if(isset($_REQUEST['hdb_table'])) {
	$hdbTable = $_REQUEST['hdb_table'];
	$productType = $manufacturerDatabaseManagement->fetchProductTypeByTable($hdbTable);

} else {
	$hdbTable = null;
}

if(isset($_REQUEST['DO_MASSCONFIRM']) && !empty($_REQUEST['SELECT_PRODUCT'])) {
	foreach($_REQUEST['SELECT_PRODUCT'] as $key => $selectProductId) {
		$manufacturerDatabaseManagement->saveProduct($selectProductId, $hdbTable, array(
			'CONFIRMED' => 1
		));

	}
	$tpl_content->addvar("success_mass", count($_REQUEST['SELECT_PRODUCT']));
}
if(isset($_REQUEST['DO_MASSUNCONFIRM']) && !empty($_REQUEST['SELECT_PRODUCT'])) {
	foreach($_REQUEST['SELECT_PRODUCT'] as $key => $selectProductId) {
		$manufacturerDatabaseManagement->saveProduct($selectProductId, $hdbTable, array(
			'CONFIRMED' => 0
		));

	}
	$tpl_content->addvar("success_mass", count($_REQUEST['SELECT_PRODUCT']));
}
if(isset($_REQUEST['DO_MASSSYNC']) && !empty($_REQUEST['SELECT_PRODUCT'])) {
    foreach($_REQUEST['SELECT_PRODUCT'] as $key => $selectProductId) {
        $manufacturerDatabaseManagement->getManufacturerDatabaseSyncManagement()->syncProductById($selectProductId, $hdbTable);

    }
    $tpl_content->addvar("success_mass", count($_REQUEST['SELECT_PRODUCT']));
}
if(isset($_REQUEST['DO_MASSEXPORT']) && !empty($_REQUEST['SELECT_PRODUCT'])) {
    require_once $ab_path . 'sys/lib.hdb.csv.php';
    $hdbCSV = ManufacturerDatabaseCSV::getInstance($db);
    $hdbCSV->exportById($hdbTable, $_REQUEST['SELECT_PRODUCT'], $arExportSkipColumns);
    die();
}
if(isset($_REQUEST['DO_MASSDELETE']) && !empty($_REQUEST['SELECT_PRODUCT'])) {
		foreach($_REQUEST['SELECT_PRODUCT'] as $key => $selectProductId) {
		$manufacturerDatabaseManagement->deleteProduct((int)$selectProductId, $hdbTable);
	}
	$tpl_content->addvar("success_delete", count($_REQUEST['SELECT_PRODUCT']));
}

if(isset($_REQUEST['DO_START_MERGE']) && $_REQUEST['DO_START_MERGE'] == 1) {
	$manufacturerMergeManagement->startProductMerge($hdbTable);
}
if(isset($_REQUEST['DO_CANCEL_MERGE']) && $_REQUEST['DO_CANCEL_MERGE'] == 1) {
	$manufacturerMergeManagement->cancelProductMerge($hdbTable);
}
if(isset($_REQUEST['DO_ADDMERGETOOL']) && !empty($_REQUEST['SELECT_PRODUCT'])) {
	foreach($_REQUEST['SELECT_PRODUCT'] as $key => $selectProductId) {
		$product = $manufacturerDatabaseManagement->fetchProductById($selectProductId, $hdbTable);
		$manufacturerMergeManagement->addProductMergeData($product, $hdbTable);
	}
}
if(isset($_REQUEST['DO_HDB_MERGE_DELETE']) && !empty($_REQUEST['SELECT_PRODUCT'])) {
	foreach($_REQUEST['SELECT_PRODUCT'] as $key => $selectProductId) {
		$manufacturerMergeManagement->removeProductMergeData($selectProductId, $hdbTable);
	}
}
if(isset($_REQUEST['DO_HDB_MERGE_RUN']) && !empty($_REQUEST['HDB_MERGE_PRODUCT_MAINENTRY'])) {
	$manufacturerMergeManagement->runProductMerge($_REQUEST['HDB_MERGE_PRODUCT_MAINENTRY'], $hdbTable);

	$tpl_content->addvar('success_merge', 1);
}

$visibleColumns = is_array($_REQUEST['VISIBLE_COLUMNS'])?$_REQUEST['VISIBLE_COLUMNS']:array();

$searchData = array(
	'CONFIRMED' => -1,
	'HAS_USER_DATA' => -1
);
$searchData = is_array($_REQUEST['SEARCH'])?array_merge($searchData, $_REQUEST['SEARCH']):$searchData;

if(!isset($searchData['LIMIT'])) {
	$searchData['LIMIT'] = 100;
}
$curpage = ($_REQUEST['npage'] ? $_REQUEST['npage'] : $_REQUEST['npage']=1);
$searchData['OFFSET'] = (($curpage-1)*$searchData['LIMIT']);


if($hdbTable != null && $productType != null) {
	$tplColumns = array();
	$tplPossibleVisibleColumns = array();

	$columns = $productType['CONFIG']['COLUMNS'];
	foreach($columns as $key => $col) {
		$columnIsVisible = ($col['DEFAULT_VISIBLE'] == true && empty($visibleColumns)) || in_array($key, $visibleColumns);
		$columnData = array_merge($col, array(
				'COL_NAME' => $key,
				'COL_IS_VISIBLE' => $columnIsVisible
		));
		$columnData['SEARCH_INPUT'] = $manufacturerDatabaseManagement->processSearchFieldColumn($columnData, $searchData);

		if($columnIsVisible) {
			$tplColumns[] = $columnData;
		}

		if($col['HIDDEN'] != true) {
			$tplPossibleVisibleColumns[] = $columnData;
		}
	}

    if (isset($_REQUEST['DO_EXPORT_CSV'])) {
        require_once $ab_path . 'sys/lib.hdb.csv.php';
        $hdbCSV = ManufacturerDatabaseCSV::getInstance($db);
        $hdbCSV->exportByParam($hdbTable, $searchData, $arExportSkipColumns);
        die();
    }

	$products = $manufacturerDatabaseManagement->fetchAllByParam($hdbTable, $searchData, $columns);
	$all = $manufacturerDatabaseManagement->countByParam($hdbTable, $searchData, $columns);

	foreach($products as $key => $product) {
		$tplTmpDataCol = '';

		foreach($tplColumns as $colKey => $colValue) {
			$tplDataColumn->addvar('TYPE_'.$colValue['TYPE'], 1);
			$tplDataColumn->addvar('DATAVALUE', $manufacturerDatabaseManagement->processDataColumnValue($product, $colValue));
			$tplTmpDataCol .= $tplDataColumn->process();
		}

		$products[$key]['COUNT_DATA_USER'] = (strlen($product['DATA_USER'])<10)?0:1;
		$products[$key]['TPL_DATACOL'] = $tplTmpDataCol;
	}


	$tpl_content->addlist('PRODUCTS', $products, 'tpl/de/hdb_products.row.htm');
	$tpl_content->addlist('TH_COLS', $tplColumns, 'tpl/de/hdb_products.th_cols.htm');
	$tpl_content->addvar('TH_COLNUMBER', count($tplColumns));
	$tpl_content->addlist('POSSIBLE_VISIBLE_COLUMNS', $tplPossibleVisibleColumns, 'tpl/de/hdb_products.possible_visible_columns_row.htm');
	$tpl_content->addlist('SEARCH_FIELD_LIST', $tplColumns, 'tpl/de/hdb_products.searchfieldlist.htm');

	$tpl_content->addvar('pager', htm_browse($all, $curpage, "index.php?page=hdb_products&hdb_table=".$hdbTable."&".http_build_query(array('SEARCH' => $searchData, 'VISIBLE_COLUMNS' => $visibleColumns))."&npage=", $searchData['LIMIT']));
}

$productTypes = $manufacturerDatabaseManagement->fetchAllProductTypes();

if($hdbTable == null) {
	foreach($productTypes as $key => $productType) {
		$productTypes[$key]['ANZ_GESAMT'] =  $db->fetch_atom("SELECT COUNT(*) FROM `".$productType['HDB_TABLE']."`");
		$productTypes[$key]['ANZ_NOT_CONFIRMED'] =  $db->fetch_atom("SELECT COUNT(*) FROM `".$productType['HDB_TABLE']."`	WHERE CONFIRMED=8");
		$productTypes[$key]['ANZ_USERDATA'] = $db->fetch_atom("SELECT COUNT(*) FROM `".$productType['HDB_TABLE']."`	WHERE CONFIRMED=1 AND DATA_USER IS NOT NULL AND DATA_USER != ''");

	}
}


$tpl_content->addlist('liste_types', $productTypes, 'tpl/de/hdb_index.row_types.htm');
$tpl_content->addvars($searchData, "SEARCH_DATA_");

$tpl_content->addvar('HDB_TABLE', $hdbTable);

if($manufacturerMergeManagement->isActiveProductMerge($hdbTable)) {
	$tpl_content->addvar('HDB_MERGE_PRODUCT_SESSION_ACTIVE', 1);
	if($manufacturerMergeManagement->hasProductMergeData($hdbTable)) {

		$mergeProducts = $manufacturerMergeManagement->getProductMergeData($hdbTable);
		foreach($mergeProducts as $key => $mergeProduct) {
			$tplTmpDataCol = '';

			foreach($tplColumns as $colKey => $colValue) {
				$tplDataColumn->addvar('DATAVALUE', $manufacturerDatabaseManagement->processDataColumnValue($mergeProduct, $colValue));
				$tplTmpDataCol .= $tplDataColumn->process();
			}

			$mergeProducts[$key]['COUNT_DATA_USER'] = (strlen($mergeProduct['DATA_USER'])<10)?0:1;
			$mergeProducts[$key]['TPL_DATACOL'] = $tplTmpDataCol;
		}

		$tpl_content->addlist('HDB_MERGE_PRODUCT_DATA', $mergeProducts, 'tpl/de/hdb_products.merge.row.htm');
	}
}