<?php

function checkDirRecursive($dirDefault, $dirUser, $language = "de", $file = "") {
	global $ab_path;
	if (is_file($dirDefault."/default/".$file)) {
		$fileDefault = $dirDefault."/default/".$file;
		$fileUser = $dirUser."/".$language."/".$file;
		if (!file_exists($fileUser)) {
			$fileUser = $dirUser."/default/".$file;
		}
		if (file_exists($fileUser)) {
			if (filemtime($fileDefault) > filemtime($fileUser)) {
				$fileShort = str_replace($ab_path, '', $fileUser);
				echo $fileShort." muss angepasst werden!\n<br />";
			}
		}
	} else {
		$dirBase = $dirDefault."/default/".$file;
		$dirListing = dir($dirBase);
		while (false !== ($filename = $dirListing->read())) {
			if (($filename != ".") && ($filename != "..")) {
				$fileFull = (empty($file) ? $filename : $file."/".$filename);
				checkDirRecursive($dirDefault, $dirUser, $language, $fileFull);
			}
		}
	}
}

function leadsExecute($commandString) {
    require_once $GLOBALS["ab_path"]."inc.laravel.php";
    $app = $GLOBALS["laravel"]["app"];
    #$kernel = $GLOBALS["laravel"]["kernel"];
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

    // Chdir into leads directory
    $dirLeads = realpath(__DIR__."/../../ebiz-kernel");
    chdir($dirLeads);
    // Execute command
    $commandArray = explode(" ", $commandString);
    $status = $kernel->handle(
        $input = new Symfony\Component\Console\Input\ArgvInput($commandArray),
        new Symfony\Component\Console\Output\ConsoleOutput()
    );
    $kernel->terminate($input, $status);
}

global $ab_path;

$db_import = null;
//$db_import = new ebiz_db('db_name', 'db_host', 'db_user', 'db_pass');
$errors = array();

switch ($_REQUEST["do"]) {
    case 'leads_recache':
        session_write_close();
        ob_end_clean();
        $stepIndex = (array_key_exists("step", $_REQUEST) ? (int)$_REQUEST["step"] : 0);
        switch ($stepIndex) {
            case 0:
                forward('index.php?lang=de&page=welcome&ebizTools=42&do='.$_REQUEST['do'].'&step=1', 1, false, false);
                leadsExecute("artisan vendor:publish --force --all");
                die();
            case 1:
                forward('index.php?lang=de&page=welcome&ebizTools=42&do='.$_REQUEST['do'].'&step=2', 1, false, false);
                leadsExecute("artisan cache:clear");
                die();
            case 2:
                forward('index.php?page=welcome&ebizTools=42&done='.$_REQUEST['do'], 1, false, false);
                leadsExecute("artisan config:cache");
                die();
        }
        die("DEBUG!");
        #system("php --version");
        #exec("php composer.phar self-update");
        #$output = shell_exec("php --version");
        #$commandString = PHP_BINARY." composer.phar self-update";
        #$commandHandle = popen($commandString, "r");
        #$output = stream_get_contents($commandHandle);
        #pclose($commandHandle);
        #die(var_dump($commandString, $output));
    die("TEST");
		die(forward('index.php?page=welcome&ebizTools=42&done='.$_REQUEST['do']));
	case 'ad_search':
	    $db->querynow("TRUNCATE `ad_search`;");
        $adSearchTodoCount = $db->fetch_atom("
          SELECT COUNT(*)
          FROM `ad_master` a
          WHERE a.ID_AD_MASTER NOT IN (SELECT FK_AD FROM ad_search) AND (a.STATUS&3)=1");
        file_put_contents($GLOBALS["ab_path"]."cache/_maintenance_ad_search", $adSearchTodoCount);
		die(forward('index.php?page=welcome&ebizTools=42&done='.$_REQUEST['do']));
	case 'ad_search_cleanup':
	    if (!empty($_REQUEST["start"])) {
	        $db->querynow("
                DELETE s1
                FROM `ad_search` s1
                JOIN `ad_search` s2 ON s1.FK_AD=s2.FK_AD AND s1.LANG=s2.LANG AND s1.ID_AD_SEARCH<s2.ID_AD_SEARCH;
                
                DELETE s
                FROM `ad_search` s
                LEFT JOIN `ad_master` a ON a.ID_AD_MASTER=s.FK_AD
                WHERE a.STATUS IS NULL OR a.DELETED=1 
                  OR (a.STATUS NOT IN (1,3,5,7,9,11,13,15));");
		    die(forward('index.php?page=welcome&ebizTools=42&done='.$_REQUEST['do']));
        }
	    $adsSearchOverall = $db->fetch_atom("SELECT COUNT(*) FROM `ad_search`");
	    $adsSearchDuplicate = $db->fetch_atom("
                SELECT COUNT(s1.ID_AD_SEARCH)
                FROM `ad_search` s1
                JOIN `ad_search` s2 ON s1.FK_AD=s2.FK_AD AND s1.LANG=s2.LANG AND s1.ID_AD_SEARCH<s2.ID_AD_SEARCH;");
	    $adsSearchObsolete = $db->fetch_atom("
	        SELECT COUNT(*)
	        FROM `ad_search` s
	        LEFT JOIN `ad_master` a ON a.ID_AD_MASTER=s.FK_AD
	        WHERE a.STATUS IS NULL OR a.DELETED=1 
	          OR (a.STATUS NOT IN (1,3,5,7,9,11,13,15))");
	    $adsSearchCount = $adsSearchDuplicate + $adsSearchObsolete;
	    $tpl_content->addvar("output", 
            "<b>Es werden ".$adsSearchCount." von ".$adsSearchOverall." Einträgen aus dem Such-Index gelöscht!</b>"
            ." (".(round($adsSearchCount * 10000 / $adsSearchOverall) / 100)."%) Wirklich fortfahren?<br>\n"
            ."<a href=\"index.php?page=welcome&ebizTools=42&do=ad_search_cleanup&start=1\">Ja! Wirklich löschen!</a>\n"
            ."<br><br><br>"
        );
	    return;
    case 'backup_mail_yml':
        
        require_once $GLOBALS["ab_path"]."lib/yaml/Yaml.php";
        require_once $GLOBALS["ab_path"]."lib/yaml/Parser.php";
        require_once $GLOBALS["ab_path"]."lib/yaml/Inline.php";
        require_once $GLOBALS["ab_path"]."lib/yaml/Unescaper.php";
        require_once $GLOBALS["ab_path"]."lib/yaml/DumperMod.php";
        require_once $GLOBALS["ab_path"]."lib/yaml/Escaper.php";
        require_once $GLOBALS["ab_path"]."lib/yaml/Exception/ExceptionInterface.php";
        require_once $GLOBALS["ab_path"]."lib/yaml/Exception/RuntimeException.php";
        require_once $GLOBALS["ab_path"]."lib/yaml/Exception/ParseException.php";
        require_once $GLOBALS["ab_path"]."lib/yaml/Exception/DumpException.php";

        $arMailsYml = [];
		$arLang = $db->fetch_table("
			SELECT ABBR, BITVAL
			FROM `lang`
			WHERE B_PUBLIC=1");
		for ($i = 0; $i < count($arLang); $i++) {
		    $mailLangAbbr = $arLang[$i]["ABBR"];
		    $mailLangBitVal = $arLang[$i]["BITVAL"];
            $arMails = Api_StringManagement::getInstance($db, $mailLangBitVal)->readRaw("mailvorlage", "1=1", "t.*, s.V1, s.V2, s.T1", 0);
            foreach ($arMails as $arMail) {
                $mailSysName = $arMail["SYS_NAME"];
                if (!array_key_exists($mailSysName, $arMailsYml)) {
                    $arMailsYml[$mailSysName] = $arMail;
                    $arMailsYml[$mailSysName]["V1"] = [];
                    $arMailsYml[$mailSysName]["V2"] = [];
                    $arMailsYml[$mailSysName]["T1"] = [];
                }
                $arMailsYml[$mailSysName]["V1"][$mailLangAbbr] = $arMail["V1"];
                $arMailsYml[$mailSysName]["V2"][$mailLangAbbr] = $arMail["V2"];
                $arMailsYml[$mailSysName]["T1"][$mailLangAbbr] = $arMail["T1"];
            }
        }
        $zipFilenameAbs = $GLOBALS["ab_path"]."filestorage/mailBackupYml.zip";
        $zipInvoices = new ZipArchive();
        $zipInvoices->open($zipFilenameAbs, ZipArchive::OVERWRITE + ZipArchive::CREATE);
        foreach ($arMailsYml as $mailSysName => $mailData) {
            $mailDataFiltered = [ "V1" => $mailData["V1"], "T1" => $mailData["T1"] ];
            $zipInvoices->addFromString($mailSysName.".yml", \Symfony\Component\Yaml\Yaml::dump($mailDataFiltered, 4, 2));
        }
        $zipInvoices->close();
        header('Content-type: application/zip');
        header('Content-Disposition: attachment; filename="'.basename($zipFilenameAbs).'"');
        die(file_get_contents($zipFilenameAbs));
        
    case 'update_mail_yml':
        
        require_once $GLOBALS["ab_path"]."lib/yaml/Yaml.php";
        require_once $GLOBALS["ab_path"]."lib/yaml/Parser.php";
        require_once $GLOBALS["ab_path"]."lib/yaml/Inline.php";
        require_once $GLOBALS["ab_path"]."lib/yaml/Unescaper.php";
        require_once $GLOBALS["ab_path"]."lib/yaml/DumperMod.php";
        require_once $GLOBALS["ab_path"]."lib/yaml/Escaper.php";
        require_once $GLOBALS["ab_path"]."lib/yaml/Exception/ExceptionInterface.php";
        require_once $GLOBALS["ab_path"]."lib/yaml/Exception/RuntimeException.php";
        require_once $GLOBALS["ab_path"]."lib/yaml/Exception/ParseException.php";
        require_once $GLOBALS["ab_path"]."lib/yaml/Exception/DumpException.php";

        $arMailsYml = [];
		$arLang = $db->fetch_table("
			SELECT ABBR, BITVAL
			FROM `lang`
			WHERE B_PUBLIC=1");
		for ($i = 0; $i < count($arLang); $i++) {
		    $mailLangAbbr = $arLang[$i]["ABBR"];
		    $mailLangBitVal = $arLang[$i]["BITVAL"];
            $arMails = Api_StringManagement::getInstance($db, $mailLangBitVal)->readRaw("mailvorlage", "1=1", "t.*, s.V1, s.V2, s.T1", 0);
            foreach ($arMails as $arMail) {
                $mailSysName = $arMail["SYS_NAME"];
                if (!array_key_exists($mailSysName, $arMailsYml)) {
                    $arMailsYml[$mailSysName] = $arMail;
                    $arMailsYml[$mailSysName]["V1"] = [];
                    $arMailsYml[$mailSysName]["V2"] = [];
                    $arMailsYml[$mailSysName]["T1"] = [];
                }
                $arMailsYml[$mailSysName]["V1"][$mailLangAbbr] = $arMail["V1"];
                $arMailsYml[$mailSysName]["V2"][$mailLangAbbr] = $arMail["V2"];
                $arMailsYml[$mailSysName]["T1"][$mailLangAbbr] = $arMail["T1"];
            }
        }
        $arMailsYmlFinal = [];
        $zipFilenameAbs = $GLOBALS["ab_path"]."filestorage/mailUpdate.yml";
        $zipInvoices = new ZipArchive();
        $zipInvoices->open($zipFilenameAbs, ZipArchive::OVERWRITE + ZipArchive::CREATE);
        foreach ($arMailsYml as $mailSysName => $mailData) {
            $arMailsYmlFinal[] = [
                "action" => "mailEdit",
                "parameters" => [
                    "SYS_NAME" => $mailData["SYS_NAME"],
                    "HTML_EDITOR" => $mailData["HTML_EDITOR"],
                    "BACKUP" => "MailBackup/".$mailData["SYS_NAME"].".yml",
                    "V1" => $mailData["V1"],
                    #"T1" => $mailData["T1"]
                ]
            ];
        }
        $zipInvoices->close();
        header('Content-type: application/zip');
        header('Content-Disposition: attachment; filename="updateMails.yml"');
        die(\Symfony\Component\Yaml\Yaml::dump($arMailsYmlFinal, 4, 2));
        
    case 'backup_xml':
        require_once $ab_path."admin/sys/lib.backup.php";
        $backup = new Backup($db);
        die("test: ".$backup->xmlBackup());
	case 'sync_meta':
		/**
		 * META-TAGS FÜR SEITEN SYNCRONISIEREN
		 */
		if ($db_import == null) {
			die("Zweite Datenbankverbindung erforderlich!! \$db_import nicht gesetzt!");
		}
		$count = 0;
		$countUpdate = 0;
		$countMiss = 0;
		// Get categories
		$ar_pages_old = $db_import->fetch_table("SELECT el.*, s.T1, s.V1, s.V2, s.BF_LANG as BF_LANG_STRING FROM `nav` el
			LEFT JOIN `string` s ON s.S_TABLE='nav' AND s.FK=el.ID_NAV
				AND s.BF_LANG=if(el.BF_LANG & 128, 128, 1 << floor(log(el.BF_LANG+0.5)/log(2)))
			WHERE ROOT=1
			ORDER BY el.LFT");
		$ar_pages_new = $db->fetch_table("SELECT el.*, s.T1, s.V1, s.V2, s.BF_LANG as BF_LANG_STRING FROM `nav` el
			LEFT JOIN `string` s ON s.S_TABLE='nav' AND s.FK=el.ID_NAV
				AND s.BF_LANG=if(el.BF_LANG & 128, 128, 1 << floor(log(el.BF_LANG+0.5)/log(2)))
			WHERE ROOT=1
			ORDER BY el.LFT");
		$ar_pages_new_by_id = array();
		$ar_pages_mapping = array();
		for ($index_old = count($ar_pages_old)-1; $index_old >= 0; $index_old--) {
			$ar_page = array_pop($ar_pages_old);
			$id_page_new = 0;
			$found = false;
			for ($index_new = count($ar_pages_new)-1; $index_new >= 0; $index_new--) {
				$ar_page_new = $ar_pages_new[$index_new];
				// Titel vergleichen
				if ($ar_page_new['V1'] == $ar_page['V1']) {
					$count++;
					// Verknüpfung merken
					$id_page_new = $ar_page_new['ID_NAV'];
					$ar_pages_new_by_id[$id_page_new] = $ar_page_new;
					break;
				}
			}
			if ($id_page_new > 0) {
				// Verknüpfung gefunden
				$ar_pages_mapping[ $id_page_new ] = $ar_page;
				unset($ar_pages_old[$index_old]);
			} else {
				echo("<b>Warnung! Seite übersprungen:</b> '".$ar_page['V1']."'<br />\n");
				$countMiss++;
			}
		}
		// Syncronisieren
		foreach ($ar_pages_mapping as $id_page_new => $ar_page_old) {
			$ar_page_new = $ar_pages_new_by_id[$id_page_new];
			if ((trim($ar_page_old['T1']) != "") && ($ar_page_new['T1'] != $ar_page_old['T1'])) {
				$db->querynow("UPDATE `string` SET T1='".mysql_real_escape_string($ar_page_old['T1'])."'
						WHERE S_TABLE='nav' AND FK=".(int)$id_page_new." AND BF_LANG=".(int)$ar_page_old['BF_LANG_STRING']);
				$countUpdate++;
			}
		}
		echo('Found: '.$count.', Updated: '.$countUpdate.', Missing: '.$countMiss);
		break;
	case 'update_templates':
		$dirDesign = $ab_path."design";
		$dirDesignDefault = $dirDesign."/default";
		$dirDesignUser = $dirDesign."/user";
		checkDirRecursive($dirDesignDefault, $dirDesignUser);
		break;
    case 'update_test':
        $tpl_content->addvar("UPDATE_TEST", 1);
        if (!empty($_POST)) {
            $tpl_content->addvars($_POST);
            // Get instructions
            $updateYml = $_POST["updateYML"];
            if (empty($updateYml)) {
                $errors[] = "Bitte geben Sie mindestens eine Anweisung an";
                break;
            }
            $updateFile = $ab_path."admin/update_test.yml";
            if (!@file_put_contents($updateFile, $updateYml)) {
                $errors[] = "Fehler beim Schreiben der Update-Anweisungen in die Datei '".$updateFile."'.";
                break;
            }
						if (file_exists($updateFile.".progress")) {
							unlink($updateFile.".progress");
						}
            require_once $ab_path."sys/lib.update.php";
            $update = new Update($db, $updateFile);
            while (!$update->run()) {
                $error = $update->getLastError();
                if ($error !== false) {
                    $errors[] = $error;
                    break;
                }
            }
            if (empty($errors)) {
                die(forward('index.php?page=welcome&ebizTools=42&done='.$_REQUEST['do']));
            }
        }
        break;
    case 'convert_myisam_to_innodb':
        $alterTableStatements = array();
        $result = $db->fetch_table("SHOW TABLE STATUS");
        foreach($result as $row) {
            if($row["Engine"] == "MyISAM") {
                $alterTableStatements[] = "ALTER TABLE `".$row["Name"]."` ENGINE=InnoDB;\n";
            }
        }
        $tpl_content->addvar('ALTER_TABLE_STATEMENTS', $alterTableStatements);
        break;
	case 'test':
		include "ebiz-tools-test.php";
		die(forward('index.php?page=welcome&ebizTools=42&done='.$_REQUEST['do']));
	default:
		break;
}

if (!empty($errors)) {
    $tpl_content->addvar("errors", implode("<br />\n", $errors));
}

if (!empty($_REQUEST['done'])) {
	$tpl_content->addvar('done', $_REQUEST['done']);
	$tpl_content->addvar('done_'.$_REQUEST['done'], 1);
}

return;

$import_options = array('url' => 'http://test.secondsol.de');

$ar_kats_old = unserialize(file_get_contents("../kats.txt"));
if (!empty($ar_kats_old)) {
	$ar_kats = $db->fetch_nar("SELECT LFT, ID_KAT FROM `kat` WHERE ROOT=1");
	$import_options['katLinks'] = array();
	foreach ($ar_kats_old as $index => $ar_kat) {
		if (isset($ar_kats[$ar_kat["LFT"]])) {
			$import_options['katLinks'][$ar_kat["ID_KAT"]] = $ar_kats[$ar_kat["LFT"]];
		}
		//echo("UPDATE `kat2field` SET FK_KAT=".$ar_kats[$ar_kat["LFT"]]." WHERE FK_KAT=".$ar_kat["ID_KAT"].";<br />\n");
	}
}

if ($_REQUEST["syncronizeKatMeta"]) {
	$count = 0;
	$countUpdate = 0;
	$countMiss = 0;
	// Get categories
	$ar_kats_old = $db_import->fetch_table("SELECT el.*, s.T1, s.V1, s.V2 FROM `kat` el
		LEFT JOIN `string_kat` s ON s.S_TABLE='kat' AND s.FK=el.ID_KAT
			AND s.BF_LANG=if(el.BF_LANG_KAT & 128, 128, 1 << floor(log(el.BF_LANG_KAT+0.5)/log(2)))
		WHERE ROOT=1
		ORDER BY el.LFT");
	$ar_kats_new = $db->fetch_table("SELECT el.*, s.T1, s.V1, s.V2 FROM `kat` el
		LEFT JOIN `string_kat` s ON s.S_TABLE='kat' AND s.FK=el.ID_KAT
			AND s.BF_LANG=if(el.BF_LANG_KAT & 128, 128, 1 << floor(log(el.BF_LANG_KAT+0.5)/log(2)))
		WHERE ROOT=1
		ORDER BY el.LFT");
	$ar_kats_new_by_id = array();
	$ar_kats_mapping = array();
	for ($index_old = count($ar_kats_old)-1; $index_old >= 0; $index_old--) {
		$ar_kat = array_pop($ar_kats_old);
		$id_kat_new = 0;
		$found = false;
		for ($index_new = count($ar_kats_new)-1; $index_new >= 0; $index_new--) {
			$ar_kat_new = $ar_kats_new[$index_new];
			// Titel vergleichen
			if ($ar_kat_new['V1'] == $ar_kat['V1']) {
				$count++;
				// Verknüpfung merken
				$id_kat_new = $ar_kat_new['ID_KAT'];
				$ar_kats_new_by_id[$id_kat_new] = $ar_kat_new;
				break;
			}
		}
		if ($id_kat_new > 0) {
			// Verknüpfung gefunden
			$ar_kats_mapping[ $id_kat_new ] = $ar_kat;
			unset($ar_kats_old[$index_old]);
		} else {
			echo("<b>Warnung! Kategorie übersprungen:</b> '".$ar_kat['V1']."'<br />\n");
			$countMiss++;
		}
	}
	// Syncronisieren
	foreach ($ar_kats_mapping as $id_kat_new => $ar_kat_old) {
		$ar_kat_new = $ar_kats_new_by_id[$id_kat_new];
		if (empty($ar_kat_new['T1'])) {
			$db->querynow("UPDATE `string_kat` SET T1='".mysql_real_escape_string($ar_kat_old['T1'])."'
					WHERE S_TABLE='kat' AND FK=".(int)$id_kat_new." AND BF_LANG=".(int)$ar_kat_old['BF_LANG']);
			$countUpdate++;
		}
	}
	echo('Found: '.$count.', Updated: '.$countUpdate.', Missing: '.$countMiss);
}

if ($_REQUEST["importAds"]) {
	$ar_counter = array();
	// Tabellen leeren
	$db->querynow("TRUNCATE TABLE `ad_master`");
	$db->querynow("TRUNCATE TABLE `ad_images`");
	$db->querynow("TRUNCATE TABLE `ad_upload`");
	$db->querynow("TRUNCATE TABLE `ad_video`");
	$db->querynow("TRUNCATE TABLE `ad_sold`");
	$db->querynow("TRUNCATE TABLE `ad_sold_rating`");
	// Master-Tabelle
	$ar_ads_src = $db_import->fetch_table("SELECT * FROM `ad_master`");
	foreach ($ar_ads_src as $index => $ar_ad) {
		if (is_array($import_options['katLinks']) && isset($import_options['katLinks'][ $ar_ad['FK_KAT'] ])) {
			$ar_ad["FK_KAT"] = $import_options['katLinks'][ $ar_ad['FK_KAT'] ];
		}
		$db->querynow("INSERT INTO `ad_master` (ID_AD_MASTER) VALUES (".(int)$ar_ad["ID_AD_MASTER"].")");
		$db->update('ad_master', $ar_ad);
		$ar_counter["ad_master"]++;
	}
	// Artikel-Tabellen
	$ar_tables = $db_import->fetch_nar("SELECT ID_TABLE_DEF, T_NAME FROM `table_def`");
	foreach ($ar_tables as $id_table => $table_name) {
		$db->querynow("TRUNCATE TABLE `".mysql_real_escape_string($table_name)."`");
		$ar_ads_src = $db_import->fetch_table("SELECT * FROM `".mysql_real_escape_string($table_name)."`");
		foreach ($ar_ads_src as $index => $ar_ad) {
			$id_ad = (int)$ar_ad[ "ID_".strtoupper($table_name) ];
			if (is_array($import_options['katLinks']) && isset($import_options['katLinks'][ $ar_ad['FK_KAT'] ])) {
				$ar_ad["FK_KAT"] = $import_options['katLinks'][ $ar_ad['FK_KAT'] ];
			}
			$db->querynow("INSERT INTO `".mysql_real_escape_string($table_name)."` (ID_".strtoupper($table_name).")".
					" VALUES (".(int)$id_ad.")");
			$db->update($table_name, $ar_ad);
			$ar_counter[$table_name]++;
			// => Bilder
			$ar_images_src = $db_import->fetch_table("SELECT * FROM `ad_images` WHERE FK_AD=".$id_ad);
			foreach ($ar_images_src as $index => $ar_image) {
				if (isset($import_options['url'])) {
					$file_target = rtrim($ab_path, "/").$ar_image["SRC"];
					$file_target_thumb = rtrim($ab_path, "/").$ar_image["SRC_THUMB"];
					if (!file_exists($file_target)) {
						$file_source = $import_options['url'].$ar_image["SRC"];
						$directory = pathinfo($file_target);
						@mkdir($directory['dirname'], 0777, true);
						file_put_contents($file_target, @file_get_contents($file_source));
					}
					if (!file_exists($file_target_thumb)) {
						$file_source_thumb = $import_options['url'].$ar_image["SRC_THUMB"];
						$directory = pathinfo($file_target_thumb);
						@mkdir($directory['dirname'], 0777, true);
						file_put_contents($file_target_thumb, @file_get_contents($file_source_thumb));
					}
				}
				$db->update('ad_images', $ar_image, true);
				$ar_counter['ad_images']++;
			}
			// => Dokumente
			$ar_uploads_src = $db_import->fetch_table("SELECT * FROM `ad_upload` WHERE FK_AD=".$id_ad);
			foreach ($ar_uploads_src as $index => $ar_upload) {
				if (isset($import_options['url'])) {
					$file_target = rtrim($ab_path, "/").$ar_upload["SRC"];
					if (!file_exists($file_target)) {
						$file_source = $import_options['url'].$ar_upload["SRC"];
						$directory = pathinfo($file_target);
						@mkdir($directory['dirname'], 0777, true);
						file_put_contents($file_target, @file_get_contents($file_source));
					}
				}
				$db->update('ad_upload', $ar_upload, true);
				$ar_counter['ad_upload']++;
			}
			// => Dokumente
			$ar_videos_src = $db_import->fetch_table("SELECT * FROM `ad_video` WHERE FK_AD=".$id_ad);
			foreach ($ar_videos_src as $index => $ar_video) {
				$db->update('ad_video', $ar_video, true);
				$ar_counter['ad_video']++;
			}
			// => Verkäufe
			$ar_sales_src = $db_import->fetch_table("SELECT * FROM `ad_sold` WHERE FK_AD=".$id_ad);
			foreach ($ar_sales_src as $index => $ar_sold) {
				$db->querynow("INSERT INTO `ad_sold` (ID_AD_SOLD) VALUES (".(int)$ar_sold["ID_AD_SOLD"].")");
				$db->update('ad_sold', $ar_sold);
				$ar_counter['ad_sold']++;
				// => Bewertungen
				$ar_ratings_src = $db_import->fetch_table("SELECT * FROM `ad_sold_rating` WHERE FK_AD_SOLD=".(int)$ar_sold["ID_AD_SOLD"]);
				foreach ($ar_ratings_src as $index => $ar_sold_rating) {
					$db->update('ad_sold_rating', $ar_sold_rating);
					$ar_counter['ad_sold_rating']++;
				}
			}
		}
	}
	var_dump($ar_counter);
}
if ($_REQUEST["importAdvertisements"]) {
	$ar_counter = array();
	// Tabellen leeren
	$db->querynow("TRUNCATE TABLE `advertisement`");
	$db->querynow("TRUNCATE TABLE `advertisement_kat`");
	$db->querynow("TRUNCATE TABLE `advertisement_stat`");
	$db->querynow("TRUNCATE TABLE `advertisement_user`");
	$db->querynow("TRUNCATE TABLE `advertisement_view`");
	// Werbung
	$ar_adverts_src = $db_import->fetch_table("SELECT * FROM `advertisement`");
	foreach ($ar_adverts_src as $index => $ar_advert) {
		$db->querynow("INSERT INTO `advertisement` (ID_ADVERTISEMENT) VALUES (".(int)$ar_advert["ID_ADVERTISEMENT"].")");
		$db->update('advertisement', $ar_advert);
		$ar_counter["advertisement"]++;
	}
	$ar_adverts_kat_src = $db_import->fetch_table("SELECT * FROM `advertisement_kat`");
	foreach ($ar_adverts_kat_src as $index => $ar_advert_kat) {
		if (is_array($import_options['katLinks']) && isset($import_options['katLinks'][ $ar_advert_kat['FK_KAT'] ])) {
			$ar_advert_kat["FK_KAT"] = $import_options['katLinks'][ $ar_advert_kat['FK_KAT'] ];
		}
		$db->update('advertisement_kat', $ar_advert_kat);
		$ar_counter["advertisement_kat"]++;
	}
	$ar_adverts_stat_src = $db_import->fetch_table("SELECT * FROM `advertisement_stat`");
	foreach ($ar_adverts_stat_src as $index => $ar_advert_stat) {
		$db->update('advertisement_stat', $ar_advert_stat);
		$ar_counter["advertisement_stat"]++;
	}
	$ar_adverts_user_src = $db_import->fetch_table("SELECT * FROM `advertisement_user`");
	foreach ($ar_adverts_user_src as $index => $ar_advert_user) {
		$db->update('advertisement_user', $ar_advert_user);
		$ar_counter["advertisement_user"]++;
	}
	$ar_adverts_view_src = $db_import->fetch_table("SELECT * FROM `advertisement_view`");
	foreach ($ar_adverts_view_src as $index => $ar_advert_view) {
		$db->update('advertisement_view', $ar_advert_view);
		$ar_counter["advertisement_view"]++;
	}
	var_dump($ar_counter);
}
if ($_REQUEST["importAgents"]) {
	$ar_counter = array();
	// Tabellen leeren
	$db->querynow("TRUNCATE TABLE `ad_agent`");
	$db->querynow("TRUNCATE TABLE `ad_agent_temp`");
	// Gesuche
	$ar_agents_src = $db_import->fetch_table("SELECT * FROM `ad_agent`");
	foreach ($ar_agents_src as $index => $ar_agent) {
		if (is_array($import_options['katLinks']) && isset($import_options['katLinks'][ $ar_agent['SEARCH_KAT'] ])) {
			$ar_agent["SEARCH_KAT"] = $import_options['katLinks'][ $ar_agent['SEARCH_KAT'] ];
		}
		$ar_agent["SEARCH_ARRAY"] = serialize(
				array("page" => "presearch_ajax", "frame" => "ajax", "FK_KAT" => $ar_agent['SEARCH_KAT'], "HASH" => md5(microtime()))
		);
		$db->update('ad_agent', $ar_agent);
		$ar_counter["ad_agent"]++;
	}
	// Gesuche Temp
	$ar_agents_temp_src = $db_import->fetch_table("SELECT * FROM `ad_agent_temp`");
	foreach ($ar_agents_temp_src as $index => $ar_agent_temp) {
		if (is_array($import_options['katLinks']) && isset($import_options['katLinks'][ $ar_agent_temp['FK_KAT'] ])) {
			$ar_agent_temp["FK_KAT"] = $import_options['katLinks'][ $ar_agent_temp['FK_KAT'] ];
		}
		$db->update('ad_agent_temp', $ar_agent_temp);
		$ar_counter["ad_agent_temp"]++;
	}
	var_dump($ar_counter);
}
if ($_REQUEST["importRequests"]) {
	$ar_counter = array();
	// Tabellen leeren
	$db->querynow("TRUNCATE TABLE `ad_request`");
	// Gesuche
	$ar_requests_src = $db_import->fetch_table("SELECT * FROM `ad_request`");
	foreach ($ar_requests_src as $index => $ar_request) {
		$ar_request["FK_KAT"] -= 1000;
		$db->update('ad_request', $ar_request);
		$ar_counter["ad_request"]++;
	}
	var_dump($ar_counter);
}
if ($_REQUEST["importMails"]) {
	$ar_counter = array();
	// Gesuche
	$ar_mails_src = $db_import->fetch_table("SELECT * FROM `mailvorlage`");
	foreach ($ar_mails_src as $index => $ar_mail) {
		$id_mail = $db->fetch_atom("SELECT ID_MAILVORLAGE FROM `mailvorlage`
				WHERE FK_MODUL=".(int)$ar_mail["FK_MODUL"]." AND SYS_NAME='".mysql_real_escape_string($ar_mail["SYS_NAME"])."'");
		if ($id_mail > 0) {
			$db->update("mail", $ar_mail);
			$ar_counter["mail"]++;
			$ar_string_mails = $db_import->fetch_table("SELECT * FROM `string_mail` WHERE S_TABLE='mailvorlage' AND FK=".(int)$id_mail);
			foreach ($ar_string_mails as $index => $ar_string_mail) {
				$db->update("string_mail", $ar_string_mail);
				$ar_counter["string_mail"]++;
			}
		}
	}
	var_dump($ar_counter);
}

if ($_REQUEST["encryptPasswords"]) {
	$users = $db->fetch_table("SELECT * FROM `user`");
	foreach ($users as $index => $userCur) {
		if (empty($userCur["SALT"])) {
			$userCur["SALT"] = pass_generate_salt();
			$userCur["PASS"] = pass_encrypt($userCur["PASS"], $userCur["SALT"]);
			$db->querynow("UPDATE `user` SET SALT='".mysql_real_escape_string($userCur["SALT"])."', ".
					"PASS='".mysql_real_escape_string($userCur["PASS"])."' WHERE ID_USER=".(int)$userCur["ID_USER"]);
		}
	}
}

/*
$menu_labels = $db->fetch_table("SELECT n.IDENT, s.* FROM `nav` n
			LEFT JOIN `string` s
				ON s.S_TABLE='nav' AND s.FK=n.ID_NAV
					AND s.BF_LANG=if(n.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(n.BF_LANG+0.5)/log(2)))
			WHERE n.IDENT<>'' AND n.ROOT=1");

if (isset($_POST['import_menu_labels'])) {
	//var_dump($_POST['import_menu_labels']);
	//echo("\n<br />");

	// $_POST['import_menu_labels']
	$menu_labels = unserialize( file_get_contents($ab_path."../import_menu.txt") );
	$updated = 0;
	foreach ($menu_labels as $index => $ar_current) {
		$id_nav = (int)$db->fetch_atom("SELECT ID_NAV FROM `nav` WHERE ROOT=1 AND IDENT='".mysql_real_escape_string($ar_current["IDENT"])."'");
		if ($id_nav > 0) {
			$db->querynow($q = "UPDATE `string` SET
						V1='".mysql_real_escape_string($ar_current["V1"])."',
						V2='".mysql_real_escape_string($ar_current["V2"])."',
						T1='".mysql_real_escape_string($ar_current["T1"])."'
					WHERE S_TABLE='nav' AND FK='".$id_nav."'
						AND BF_LANG=".(int)$ar_current["BF_LANG"]);
			$updated++;
		} else {
			echo("Ident '".$ar_current["IDENT"]."' not found!\n<br />");
		}
	}
	die(var_dump($updated));
}
*/

//die(serialize($menu_labels));
$tpl_content->addvar("ser_menu", serialize($menu_labels));

?>