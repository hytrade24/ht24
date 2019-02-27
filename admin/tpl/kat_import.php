<?php
/* ###VERSIONSBLOCKINLCUDE### */

function option_checked(&$row, $i) {
	if ($_POST["KAT_TABLE"] == $row["NAME"]) $row["SELECTED"] = true;
}


$SILENCE = false;

include "sys/lib.nestedsets.php";
include "sys/lib.katnested.php";

include "sys/lib.shop_kategorien.php";

require_once "sys/tabledef.php";

$deftable = new tabledef(NULL, true);
$deftable->getTables();
$tpl_content->addlist("liste", $deftable->tables, "tpl/de/m_kat_edit.tablerow.htm", "option_checked");

if (!empty($_POST)) {
	$kat = new TreeCategories("kat", 1);
	$kat->tree_lock();
	//$deftable = new tabledef(NULL, true);
	//$deftable->getTables(0, 1, false);

	$id_root = $id_last = $kat->tree_get_parent();
	$id_order = 1;
	$ar_ids = array();
	$ar_parents = array($id_root);
	$parent = $id_root;
	$seperator = (isset($_POST['CSV_SEPERATOR']) ? $_POST['CSV_SEPERATOR'] : ";");

	$_POST["IMPORT"] = "";
	$fileTarget = $ab_path."/cache/import_kat.csv";
	if (move_uploaded_file($_FILES['CSV']['tmp_name'], $fileTarget)) {
		$_POST["IMPORT"] = preg_replace("/\xEF\xBB\xBF/", "", str_replace("\r\n", "\n", file_get_contents($fileTarget)));
		unlink($fileTarget);
	}

	if (isset($_POST['DELETE_KATS'])) {
		$ar_kats_old = array_keys($db->fetch_nar("SELECT ID_KAT FROM `kat` WHERE ROOT=1 AND PARENT>0"));
		if (!empty($ar_kats_old)) {
			$db->querynow($query_delete = "DELETE FROM `kat` WHERE ID_KAT IN (".implode(", ", $ar_kats_old).")");
			$db->querynow($query_delete_string = "DELETE FROM `string_kat` WHERE S_TABLE='kat' AND FK IN (".implode(", ", $ar_kats_old).")");
		}
	}

    $ar_article_tables = $db->fetch_nar("SELECT T_NAME, ID_TABLE_DEF FROM `table_def`");
	$ar_kats = explode("\n", $_POST["IMPORT"]);
    $ar_kats_added = array();
    $index = 0;
	while ($index < count($ar_kats)) {
	    $kat_cur = $ar_kats[$index];
		$ar_kat = array();
		$ar_kat_temp = explode($seperator, $kat_cur);
		// explode(",", $kat_cur);
		$quotes = false;
		$quotes_text = "";
        $resume = false;
        do {
            foreach ($ar_kat_temp as $indexCol => $str_kat) {
				if (!empty($str_kat)) {
					if (!$quotes) {
						if (((substr($str_kat, 0, 1) == '"') && (substr($str_kat, 1, 1) != '\\'))
							 && ((substr($str_kat, -1, 1) == '"') && (substr($str_kat, -2, 1) != '\\'))) {
							$ar_kat[] = substr($str_kat, 1, -1);
							#break;
						} elseif ((substr($str_kat, 0, 1) == '"') && (substr($str_kat, 1, 1) != '\\')) {
							$quotes = true;
							$quotes_text = substr($str_kat, 1);
						} else {
							$ar_kat[] = $str_kat;
							#break;
						}
					} elseif ($quotes) {
						if ((substr($str_kat, -1, 1) == '"') && (substr($str_kat, -2, 1) != '\\')) {
							$quotes = false;
							$quotes_text .= $seperator.substr($str_kat, 0, -1);
							$ar_kat[] = $quotes_text;
							#break;
						} else {
							$quotes_text .= $seperator.$str_kat;
						}
					}
				} else {
					$ar_kat[] = "";
				}
			}
            $index++;
            if ($quotes && ($index < count($ar_kats))) {
                $kat_cur = $ar_kats[$index];
                $ar_kat_temp = explode($seperator, $kat_cur);
                $resume = true;
            } else {
                $resume = false;
            }
        } while ($resume);
		$level = 0;
		while (empty($ar_kat[$level]) && ($level < count($ar_kat))) {
			$level++;
		}
		//$level = count($ar_kat);
		while (count($ar_parents) < $level) {
			$ar_parents[] = $id_last;
			$parent = $id_last;
		}
		while (count($ar_parents) > $level) {
			$parent = $ar_parents[$level-1];
			array_pop($ar_parents);
		}
		if ($level == 0) {
			$parent = $id_root;
		}
		$name = trim($ar_kat[$level]);
		$desc = (isset($ar_kat[$level+1]) ? $ar_kat[$level+1] : "");
		$meta = (isset($ar_kat[$level+2]) ? $ar_kat[$level+2] : "");
		$id_kat = (isset($ar_kat[$level+3]) ? (int)$ar_kat[$level+3] : "");
		$langval = (isset($ar_kat[$level+4]) ? (int)$ar_kat[$level+4] : $langval);
		$ad_table = (isset($ar_kat[$level+5]) ? $ar_kat[$level+5] : false);
        if (($ad_table === false) || !array_key_exists($ad_table, $ar_article_tables)) {
            $ad_table = (isset($_POST["KAT_TABLE"]) ? $_POST["KAT_TABLE"] : 'artikel_master');
        }
		$lu_katart = (isset($ar_kat[$level+6]) ? (int)$ar_kat[$level+6] : 18);
		if (!empty($name)) {
			$repl_from = array("\\\"", "\"\"", "\\r", "\\n");
			$repl_to = array("\"", "\"", "\r", "\n");
			$name = str_replace($repl_from, $repl_to, $name);
			$desc = str_replace($repl_from, $repl_to, $desc);
			$meta = str_replace($repl_from, $repl_to, $meta);
			$ad_table = str_replace($repl_from, $repl_to, $ad_table);
			$ar_kat_old = false;
			if ($id_kat > 0) {
				if (isset($ar_ids[$id_kat]))
					$id_kat = $ar_ids[$id_kat];
				$ar_kat_old = $kat->element_read($id_kat, $langval);
			}
			if ($id_kat != $id_root) {
				#echo($id_kat." != ".$id_root."\n");
				if ($ar_kat_old === false) {
					$allowCustomIds = isset($_POST['ALLOW_CUSTOM_IDS']);
					// Neue Kategorie!
					$ar_kat = array(
						"V1"			=> $name,
						"V2"			=> $desc,
						"T1"			=> $meta,
						"ORDER_FIELD"	=> $id_order++,
						"KAT_TABLE"		=> $ad_table,
						"LU_KATART"		=> $lu_katart
					);
					if ($id_kat > 0) {
						$ar_kat["ID_KAT"] = (int)$id_kat;
					}
					#$debug = @file_get_contents($ab_path."debug_import.txt");
					#$debug .= "\n".var_export($ar_kat, true);
					#file_put_contents($ab_path."debug_import.txt", $debug);
					if ($kat->element_create($parent, $ar_kat)) {
						$id_last = $kat->updateid;
                        $ar_kats_added[] = $id_last;
						if ($id_kat > 0) {
							$ar_ids[$id_kat] = $id_last;
						}
						#echo("($parent|$level) $name: $id_last\n<br />");
					} else {
						#echo("Insert failed: ".$name."\n<br />");
					}
				} else {
					// Bestehende Kategorie updaten!
					$ar_kat = $ar_kat_old;
					$ar_kat["ID_KAT"] = $id_kat;
					$ar_kat["V1"] = $name;
					$ar_kat["V2"] = $desc;
					$ar_kat["T1"] = $meta;
					$ar_kat["PARENT"] = ($id_kat == $id_root ? 0 : $parent);
					$ar_kat["ORDER_FIELD"] = $id_order++;
					$ar_kat["KAT_TABLE"] = $ad_table;
					$ar_kat["LU_KATART"] = $lu_katart;
					if ($kat->element_update($id_kat, $ar_kat)) {
						$id_last = $id_kat;
						#echo("($parent|$level) $name: $id_last\n<br />");
					} else {
						#echo("Update failed: ".$name."\n<br />");
					}
				}
			}
		}
	}
    // Enable access rights for new categories
    $nar_roles = $db->fetch_nar("select ID_ROLE, LABEL from role order by ID_ROLE");
    $queryUpdateValues = array();
    foreach ($ar_kats_added as $katIndex => $katId) {
        foreach ($nar_roles as $roleId => $roleName) {
            $queryUpdateValues[] = "(".(int)$roleId.", ".(int)$katId.", 1)";
        }
    }
    if (!empty($queryUpdateValues)) {
        $queryUpdateAccess = "INSERT INTO `role2kat` (FK_ROLE, FK_KAT, ALLOW_NEW_AD) VALUES ".implode(", ", $queryUpdateValues);
        $db->querynow($queryUpdateAccess);
    }
    // Update category > field mappings
    $queryUpdateFields = "
        INSERT IGNORE INTO `kat2field`
            SELECT k.ID_KAT, f.ID_FIELD_DEF, f.B_ENABLED, f.B_NEEDED, f.B_SEARCH
            FROM `field_def` f
            LEFT JOIN `table_def` ft ON ft.ID_TABLE_DEF=f.FK_TABLE_DEF
            LEFT JOIN `kat` k ON k.KAT_TABLE=ft.T_NAME
            WHERE f.F_NAME IN (\"FK_MAN\", \"PRODUKTNAME\", \"PREIS\", \"EAN\")
                OR (f.IS_SPECIAL=0 AND f.B_ENABLED=1)";
    $db->querynow($queryUpdateFields);
    // Unlock category tree and create nested set
	$kat->tree_unlock();
    $kat->tree_create_nestedset();
	// Rewrite category permissions
	require_once "sys/lib.perm_admin.php";
 	katperm2role_rewrite();
	// Show success message
    $tpl_content->addvar("done", 1);
	$tpl_content->addvars($_POST);
    // Clear caches
    require_once $GLOBALS['ab_path']."sys/lib.cache.admin.php";
    $cacheAdmin = new CacheAdmin();
    $cacheAdmin->emptyCacheCategory("marketplace");
    $cacheAdmin->emptyCacheCategory("subtpl");
} else {
	$tpl_content->addvar("CSV_SEPERATOR", ";");
}

?>