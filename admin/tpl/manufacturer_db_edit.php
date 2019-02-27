<?php
require_once $ab_path . 'sys/lib.hdb.php';
require_once $ab_path. 'sys/lib.cache.adapter.php';
$manufacturerDatabaseManagement = ManufacturerDatabaseManagement::getInstance($db);
$cacheAdapter = new CacheAdapter();

if (empty($_POST)) {
	if (isset($_REQUEST["ID_MAN"])) {
		$tpl_content->addvar("ID_MAN", $_REQUEST["ID_MAN"]);
		$manufacturers = $db->fetch1("SELECT * FROM manufacturers WHERE ID_MAN=" . $_REQUEST["ID_MAN"]);
		$manufacturers["MAN_NAME"] = $manufacturers["NAME"];
		$tpl_content->addvars($manufacturers);
		
		$manufacturersDetail = Api_StringManagement::getInstance($db)->readRaw("man_detail", "t.FK_MAN=".(int)$_REQUEST["ID_MAN"]);
		if (is_array($manufacturersDetail)) {
		    $tpl_content->addvars($manufacturersDetail);
        }
	}
} else {
	$tpl_content->addvars($_POST);

	$errors = array();

	if ( isset($_POST["sample-csv"]) ) {

		header('Content-Encoding: UTF-8');
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename=sample-csv-file.csv');

		$target_file = $ab_path . 'uploads/sample-csv-file.csv';

		$output = fopen('php://output', 'w');
		fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
		$records = array(
			array(
				"Audi",
				"audi.com",
				"1"
			),
			array(
				"bmw",
				"bmw.com",
				"1"
			),
			array(
				"vw",
				"vw.com",
				"0"
			)
		);
		foreach ( $records as $row ) {
			$str = implode(";",$row).PHP_EOL;
			fwrite($output,$str);
		}
		fclose( $output );

		die();
	}

	if ( isset($_FILES["bulk_upload"]) && $_FILES["bulk_upload"]["tmp_name"] != "" ) {
		$target_dir = $ab_path . 'uploads/';
		$target_file = $target_dir.$_FILES["bulk_upload"]["name"];
		$fileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));
		if ( $fileType != "csv" ) {
			$errors[] = "Upload file extension is not csv";
		}
		else {
			if (move_uploaded_file($_FILES["bulk_upload"]["tmp_name"],$target_file)) {

				$bulk_file = fopen($target_file,"r");
				$str = fread( $bulk_file, filesize($target_file) );
				fclose($bulk_file);

                if ( strtolower(mb_detect_encoding($str, 'UTF-8')) == "utf-8" ) {
		            $str = preg_replace("/\xEF\xBB\xBF/", "", $str);
					$all_manufacturers = explode(PHP_EOL, $str);

					foreach ( $all_manufacturers as $index => $row ) {
						$single_manufacture = explode(";",$row);
						$manufacture_name = $single_manufacture[0];
						if ( $manufacture_name == ''  ) {
							continue;
						}
						$manufacture_url = (isset($single_manufacture[1])) ? strtolower($single_manufacture[1]) : '';

						if ( $bulk_upload_type == "1" ) {
							$manufacture_confirmed = 1;
						}
						else if ( $bulk_upload_type == "0" ) {
							if ( isset($single_manufacture[2]) ) {
								if ( $single_manufacture[2][0] == "1" ) {
									$manufacture_confirmed = 1;
								}
								else if ( $single_manufacture[2][0] == "0" ) {
									$manufacture_confirmed = 0;
								}
								else {
									$manufacture_confirmed = 1;
								}
							}
							else {
								$manufacture_confirmed = 1;
							}
						}
						else if ( $bulk_upload_type == "-1" ) {
							$manufacture_confirmed = 0;
						}

						$manufacturerDatabaseManagement->updateManufacturerById($id, array(
							'NAME' => $manufacture_name,
							'URL' => $manufacture_url,
							'CONFIRMED' => $manufacture_confirmed
						));
					}
					unlink( $target_file );
					die(forward("index.php?page=manufacturer_db"));

				}
				else {
					$errors[] = "Wrong file encoding. We only accept UTF-8 file encodig.";
				}

			}
			else {
				$errors[] = "File not uploaded successfully";
			}
		}
		unlink( $target_file );
	}

	if ( count($errors) == 0 ) {
		if (strlen($_POST["MAN_NAME"]) < 1) {
			$errors[] = "Sie müssen einen Namen angeben!";
		}

		if (empty($errors)) {
			$_POST["NAME"] = $_POST["MAN_NAME"];

			if(!isset($_POST['CONFIRMED'])) {
				$_POST['CONFIRMED'] = 0;
			}


			unset($_POST["MAN_NAME"]);
			//$id = $db->update('manufacturers', $_POST);
			if (empty($_POST["ID_MAN"])) {

				$id = $manufacturerDatabaseManagement->updateManufacturerById(null, array(
					'NAME' => $_POST["NAME"],
					'URL' => $_POST["URL"],
					'CONFIRMED' => $_POST['CONFIRMED']
				));

			} else {
				$id = $manufacturerDatabaseManagement->updateManufacturerById($_POST["ID_MAN"], array(
					'NAME' => $_POST["NAME"],
					'URL' => $_POST["URL"],
					'CONFIRMED' => $_POST['CONFIRMED']
				));
			}
			
			if ($id) {
			    $arDetails = array(
			        "FK_MAN" => $id,
                    "T1" => $_POST["T1"]
                );
			    $idDetails = $db->fetch_atom("SELECT ID_MAN_DETAIL FROM `man_detail` WHERE FK_MAN=".(int)$id);
			    if ($idDetails > 0) {
			        $arDetails["ID_MAN_DETAIL"] = $idDetails;
                }
                $db->update("man_detail", $arDetails);
			    // Assign groups
                $arGroupIds = (!empty($_POST["FK_GROUPS"]) ? $_POST["FK_GROUPS"] : []);
                $arGroupInserts = [];
                foreach ($arGroupIds as $groupIndex => $groupId) {
                    $arGroupIds[$groupIndex] = (int)$groupId;
                    $arGroupInserts[] = "(".(int)$groupId.", ".(int)$id.")";
                }
                $db->querynow("
                    DELETE FROM `man_group_mapping`
                    WHERE FK_MAN=".(int)$id.(!empty($arGroupIds) ? " AND FK_MAN_GROUP NOT IN (".implode(", ", $arGroupIds).")" : ""));
                if (!empty($arGroupInserts)) {
                    $db->querynow("
                        INSERT IGNORE INTO `man_group_mapping`
                          (FK_MAN_GROUP, FK_MAN)
                        VALUES
                          ".implode(", ", $arGroupInserts));
                }
				// Clear caches
				require_once $GLOBALS['ab_path']."sys/lib.cache.admin.php";
				$cacheAdmin = new CacheAdmin();
				$cacheAdmin->emptyCache("subtpl_ads_search");
				die(forward("index.php?page=manufacturer_db&id=" . $id));
			}
		} else {
			$tpl_content->addvar("errors", " - " . implode("<br> - ", $errors));
		}
	} else {
		$tpl_content->addvar("errors", " - " . implode("<br> - ", $errors));
	}

}

$arGroups = Api_Entities_ManufacturerGroup::getByParam();
if ($_REQUEST["ID_MAN"] > 0) {
    $arGroupsSelected = $db->fetch_col("SELECT FK_MAN_GROUP FROM `man_group_mapping` WHERE FK_MAN=".(int)$_REQUEST["ID_MAN"]);
    foreach ($arGroups as $groupIndex => $groupDetail) {
        $arGroups[$groupIndex]["SELECTED"] = (in_array($groupDetail["ID_MAN_GROUP"], $arGroupsSelected));
    }
}
$tpl_content->addlist("groups", $arGroups, "tpl/".$s_lang."/manufacturer_db_edit.row_group.htm");

?>