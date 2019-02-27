<?php

require_once $ab_path . 'sys/lib.hdb.php';

$manufacturerDatabaseManagement = ManufacturerDatabaseManagement::getInstance($db);

if (array_key_exists("ajax", $_REQUEST)) {

	function callback_jqTreeTransformNodes($parent, &$arNestedSet) {
		$arResult = array();
		while ((count($arNestedSet) > 0) && ($arNestedSet[0]["PARENT"] == $parent)) {
			$arNodeRaw = array_shift($arNestedSet);
			$hasChilds = ($arNodeRaw["RGT"] - $arNodeRaw["LFT"] > 1);
			$arNode = array(
				"id" => $arNodeRaw["ID_KAT"],
				"class" => "category",
				"accept" => false,
				"children" => false,
				"dragable" => true,
				"expandable" => $hasChilds,
				"label" => $arNodeRaw["V1"],
				"text" => $arNodeRaw["V1"]
			);
			if ($hasChilds) {
				$arNode["children"] = callback_jqTreeTransformNodes($arNode["id"], $arNestedSet);
			}
			$arResult[] = $arNode;
		}
		return $arResult;
	}
	
	switch ($_REQUEST["ajax"]) {
		case "LOAD_CATEGORIES":
			switch ($_REQUEST["jqTreeAction"]) {
				case "readChilds":
					require_once $ab_path . "sys/lib.shop_kategorien.php";
					$show_paid = ($_REQUEST["paid"] ? 1 : 0);
					$kat = new TreeCategories("kat", 1);
					$katIdRoot = $kat->tree_get_parent();
					$arTree = callback_jqTreeTransformNodes($katIdRoot, $kat->tree_get());


					header('Content-type: application/json');
					die(json_encode(array(
						"success" => true,
						"nodes" => $arTree
					)));
			}
			die();
			break;
	}
}

if(isset($_REQUEST['hdb_table'])) {
	$hdbTable = $_REQUEST['hdb_table'];
	$productType = $manufacturerDatabaseManagement->fetchProductTypeByTable($hdbTable);
	if($productType == null) {
		forward("index.php?page=hdb_products");
		die();
	}
} else {
	$hdbTable = null;
	forward("index.php?page=hdb_products");
	die();
}

$hdbProduct = array();
$hdbProductId = (int)$_REQUEST['ID_HDB_PRODUCT'];
$dataUser = array();

if(!empty($_POST)) {
    $errors = array();
    
    $productEditParams = new Api_Entities_EventParamContainer(array(
        "productId" => ($hdbProductId > 0 ? (int)$hdbProductId : null),
        "productData" => $_POST,
        "errors" => $errors
    ));
    Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::ADMIN_PRODUCTDB_EDIT_SUBMIT, $productEditParams);
    if ($productEditParams->isDirty()) {
        $_POST = $productEditParams->getParam("productData");
        $errors = $productEditParams->getParam("errors");
    }

    if (empty($_POST["FK_MAN"])) {
        $_POST["FK_MAN"] = null;
    }
	//$_POST['CONFIRMED'] = ($_POST['CONFIRMED'] > 0 ? 1 : 0);
	unset($_POST['DATA_USER']);
	$_POST['FK_TABLE_DEF'] = $productType['ID_TABLE_DEF'];


	$columns = $productType['CONFIG']['COLUMNS'];
	foreach($columns as $key => $col) {
		switch ($col['TYPE']) {
			case 'MULTICHECKBOX':
			case 'MULTICHECKBOX_AND':
				if(is_array($_POST[$key])) {
					$_POST[$key] = 'x'.implode('x',$_POST[$key]).'x';
				} else {
					$_POST[$key] = '';
				}
				break;
		}
	}

	if (empty($errors)) {
        if (isset($_POST['DELETE_FILE']) && count($_POST['DELETE_FILE'] > 0)) {
            foreach ($_POST['DELETE_FILE'] as $key => $deleteFile) {
                if (strlen($deleteFile) > 0) {
                    $_POST[$key] = '';
                    unlink($manufacturerDatabaseManagement->getManufacturerDatabaseUploadDirectory() . $deleteFile);
                }
            }
        }

        if (isset($_POST['IMPORT_IMAGES_KEEP']) && ($_POST["CONFIRMED"] == 1)) {
            if ($_POST['IMPORT_IMAGES_KEEP'] == 0) {
                $_POST['IMPORT_IMAGES'] = array();
            }
        }
        if ($hdbProductId == 0) {
            $hdbProductId = $db->update($hdbTable, array(
                'ID_' . strtoupper($hdbTable) => null
            ));
        }


        $hdbUploadDirectory = $manufacturerDatabaseManagement->getManufacturerDatabaseUploadDirectory();
        $hdbRelativeUploadDirectory = $manufacturerDatabaseManagement->getManufacturerDatabaseUploadDirectory(false);
        if (array_key_exists("IMPORT_IMAGES", $_FILES) && !empty($_FILES["IMPORT_IMAGES"])) {
            $file = $_FILES["IMPORT_IMAGES"];
            if ($file['name'] != "") {
                if (is_array($file['name'])) {
                    // Multiple files
                    if (!is_array($_POST["IMPORT_IMAGES"])) {
                        $_POST["IMPORT_IMAGES"] = array();
                    }
                    foreach ($file['name'] as $fileIndex => $fileName) {
                        if (empty($fileName)) {
                            continue;
                        }
                        $hdbUploadFile = $hdbProductId . '_IMPORT_IMAGES_' . md5($fileName) . '.' . pathinfo($fileName, PATHINFO_EXTENSION);
                        move_uploaded_file($file['tmp_name'][$fileIndex], $hdbUploadDirectory . $hdbUploadFile);

                        $_POST["IMPORT_IMAGES"][] = $hdbRelativeUploadDirectory . $hdbUploadFile;
                    }
                } else {
                    // Single file
                    $hdbUploadFile = $hdbProductId . '_IMPORT_IMAGES_' . md5($file['name']) . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
                    move_uploaded_file($file['tmp_name'], $hdbUploadDirectory . $hdbUploadFile);

                    $_POST["IMPORT_IMAGES"] = $hdbRelativeUploadDirectory . $hdbUploadFile;
                }
            }
        }

        if (isset($_POST['OVERRIDE_FIELD']) && is_array($_POST['OVERRIDE_FIELD'])) {
            foreach ($_POST['OVERRIDE_FIELD'] as $key => $value) {
                $_POST[$key] = $value;
            }
        }

        $saveResult = $manufacturerDatabaseManagement->saveProduct($hdbProductId, $hdbTable, $_POST);
        $hdbProductId = $saveResult;

        if (isset($_POST['USER_CLEAR_INDEX']) && is_array($_POST['USER_CLEAR_INDEX'])) {
            $manufacturerDatabaseManagement->deleteUserDataIndexesForProduct($hdbProductId, $hdbTable, $_POST['USER_CLEAR_INDEX']);
        }
        if ($_POST['USER_CLEAR'] == 1) {
            $manufacturerDatabaseManagement->deleteUserDataForProduct($hdbProductId, $hdbTable);
        }
        if ($saveResult > 0) {
            $productEditParams = new Api_Entities_EventParamContainer(array(
                "productId" => ($hdbProductId > 0 ? (int)$hdbProductId : null),
                "productData" => $_POST,
                "table" => $hdbTable,
                "errors" => $errors
            ));
            Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::ADMIN_PRODUCTDB_EDIT_SAVED, $productEditParams);
            if ($productEditParams->isDirty()) {
                $errors = $productEditParams->getParam("errors");
            }
        } else {
            $errors[] = "SAVE_FAILED";
        }
    }


	if(empty($errors)) {
		$tpl_content->addvar('success', 1);
	} else {
		$tpl_content->addvar('errors', 1);
	}

}


if($hdbProductId != 0) {
	$hdbProduct = $manufacturerDatabaseManagement->fetchProductById($hdbProductId, $hdbTable);

	if($hdbProduct == null) {
		$tpl_content->addvar("errors", 1);
	}else {
		$tpl_content->addvars($hdbProduct);

		$dataUser = (unserialize($hdbProduct['DATA_USER']) == null)?array():unserialize($hdbProduct['DATA_USER']);
	}
}

// Hersteller
$cacheFile = $ab_path."cache/marktplatz/hdb_manufacturers_".$s_lang.".htm";
$cacheFileLifeTime = 1140;
$modifyTime = @filemtime($cacheFile);
$diff = ((time()-$modifyTime)/60);

if(($diff > $cacheFileLifeTime) || !file_exists($cacheFile)) {
	$tpl_tmp = new Template($ab_path."tpl/de/empty.htm");
	$tpl_tmp->tpl_text = '{liste_manufacturers}';

	$manufacturers = $db->fetch_table("SELECT *  FROM manufacturers  ORDER BY `NAME` ");
	$tmpRow = '';
	foreach($manufacturers as $key => $manufacturer) {
		$tplRow = new Template('tpl/de/hdb_products_edit.man_row.htm');
		$manufacturers[$key]['ACTIVE'] = $manufacturer['ID_MAN'] == $hdbProduct['FK_MAN'];
		if($manufacturer['CONFIRMED'] == 0) {
			$manufacturers[$key]['NAME'] .= ' (*)';
		}
		$tplRow->addvars($manufacturer);
		$tmpRow .= $tplRow->process();
	}

	$tpl_tmp->addvar('liste_manufacturers', $tmpRow);
	$tpl_tmp->isTemplateRecursiveParsable = TRUE;
	$cacheContent = $tpl_tmp->process();
	$cacheContent = str_replace('^', '{', $cacheContent);
	$cacheContent = str_replace('°', '}', $cacheContent);

	file_put_contents($cacheFile, $cacheContent);
}

$tplListeMan = @file_get_contents($cacheFile);
$tpl_content->addvar('CURRENT_MAN', $hdbProduct['FK_MAN']);
$tpl_content->addvar('liste_manufacturers', $tpl_content->process_text($tplListeMan));


// Spalten
$tplColumns = array();
$columns = $productType['CONFIG']['COLUMNS'];
foreach($columns as $key => $col) {

	$columnData = array_merge($col, array(
			'COL_NAME' => $key
	));
	$columnData['EDIT_INPUT'] = $manufacturerDatabaseManagement->processEditFieldColumn($columnData, $hdbProduct);
	$columnData['USER_DATA'] = $manufacturerDatabaseManagement->processEditUserDataFieldColumn($columnData, $dataUser);

	$tplColumns[] = $columnData;
}

$productEditParams = new Api_Entities_EventParamContainer(array(
    "manufacturerId" => (array_key_exists("FK_MAN", $hdbProduct) ? (int)$hdbProduct["FK_MAN"] : null),
    "productId" => ($hdbProductId > 0 ? (int)$hdbProductId : null),
    "productData" => $hdbProduct,
    "userData" => $dataUser,
    "columns" => $tplColumns
));
Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::ADMIN_PRODUCTDB_EDIT, $productEditParams);
if ($productEditParams->isDirty()) {
    $tplColumns = $productEditParams->getParam("columns");
}
    
$tpl_content->addlist('liste_columns', $tplColumns, 'tpl/de/hdb_products_edit.edit_row.htm');
$tpl_content->addvar('COUNT_DATA_USER', count($dataUser));

$tpl_content->addlist('col_data_user_accept', $dataUser, 'tpl/de/hdb_products_edit.col_data_user_accept.htm');
$tpl_content->addvar("HDB_TABLE", $hdbTable);

if (!empty($hdbProduct["IMPORT_IMAGES"])) {
	$arImages = array();
	$arImagesRaw = unserialize($hdbProduct["IMPORT_IMAGES"]);
	foreach ($arImagesRaw as $imageIndex => $imageUrl) {
		$arImages[] = array("URL" => $imageUrl);
	}
	$tpl_content->addlist('images', $arImages, 'tpl/de/hdb_products_edit.img_row.htm');
}

