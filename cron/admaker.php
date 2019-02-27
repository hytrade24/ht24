<?php
/* ###VERSIONSBLOCKINLCUDE### */



/*
 *
 * Beim Testen über die Shell ggf. die letzte Zeile wieder einkommentieren!
 *
 */
global $db, $nar_systemsettings, $debug_echo;

### Includes
require_once "debug.php";
require_once "admaker_inc.php";
require_once $ab_path."sys/lib.anzeigen.php";
require_once $ab_path."sys/lib.pub_kategorien.php";
require_once $ab_path."sys/lib.ads.php";
require_once $ab_path."sys/packet_management.php";
require_once $ab_path.'sys/lib.ad_constraint.php';

/**
 * Nach x Aufgaben wird diese Teilaufgabe abgebrochen um ggf. der max_execution_time zu entkommen
 * @var int
 */
$stopAfterIterations = 20;
$countIterations = 0;

### Klassen initialisieren
$packets = PacketManagement::getInstance($db);
$kat_cache = new CategoriesCache();

### Logdatei öffnen
$debug_echo = true;	// Bei true werden alle Debug ausgaben auch per echo ausgegeben
Debug_Open($ab_path."cronjob_log.txt");

### Debug output
Debug_Append("Cronjob 'admaker' gestartet.");

### rebuild cache after cron?
$rebuild_cache = false;

### languages
$ar_lang = $db->fetch_table("
	SELECT
		ABBR,
		BITVAL
	FROM
		`lang`
	WHERE
		B_PUBLIC=1");


### alte beenden
$arOldBids = $db->fetch_table("
			SELECT *
			FROM `trade`
			WHERE
			    BID_STATUS='ACTIVE' AND
				STAMP_BID < DATE_SUB(NOW(), interval ".(int)$nar_systemsettings['MARKTPLATZ']['TRADE_MAX_HOURS']." HOUR)");
foreach ($arOldBids as $index => $arBid) {
    $activeBids = $db->fetch_atom("SELECT count(*) FROM `trade` WHERE BID_STATUS='ACTIVE' AND FK_NEGOTIATION=".(int)$arBid["FK_NEGOTIATION"]);
    if ($activeBids <= 1) {
        AdManagment::CancelTrade($arBid["FK_NEGOTIATION"], 1, true);
    } else {
        AdManagment::CancelTradeSingle($arBid["ID_TRADE"], 1, true);
    }
}


### find new ads
/*
 * alter code vor dem 9.2.2010
 *
$res = $db->querynow("
	SELECT
		FK_AD,
		LU_LAUFZEIT,
		`TABLE`
	FROM
		ad_temp
	WHERE
		(STAT IS NULL) AND (DONE = 0)");
 *
 * neuer code (9.2.2010)
 */
$res = $db->querynow("
	SELECT
		ID_AD_MASTER AS FK_AD,
		FK_PACKET_ORDER,
		LU_LAUFZEIT,
		AD_TABLE AS `TABLE`
	FROM
		ad_master
	WHERE
		(CRON_STAT IS NULL) AND (CRON_DONE = 0) AND (DELETED=0)");
/*
 * ende neuer code
 */
#echo ht(dump($res));
while($row = mysql_fetch_assoc($res['rsrc']))
{
    if($countIterations >= $stopAfterIterations) { break; }

	### Debug output
	Debug_Append("Verarbeite Anzeige - ID: ".$row["FK_AD"].", TABLE: ".$row["TABLE"]);
	### update stat
	/*
	 * alter code vor 9.2.2010
	 *
	$db->query("UPDATE ad_temp SET STAT=1 WHERE FK_AD=".$row['FK_AD']." AND `TABLE` = '".$row['TABLE']."'");
	 *
	 * neuer code 9.2.2010
	 */
    $db->querynow("UPDATE ad_master SET CRON_STAT=1 WHERE ID_AD_MASTER=".$row['FK_AD']);
    /*
     * ende neuer code
     */
	### GET THE AD
	$ad_master = $db->fetch1("SELECT * FROM `ad_master` WHERE ID_AD_MASTER=".$row['FK_AD']);
    if ($ad_master["CONFIRMED"] != 1) {
        eventlog("warning", "Anzeige aktivieren fehlgeschlagen. [id=".$row['FK_AD']."] Administrator hat keine Freigabe erteilt!\nDebug info:", var_export($ad_master, true));
        continue;
    }

	$ad = $db->fetch1("
		SELECT
			a.*, i.SRC_THUMB, a.ID_".strtoupper($row['TABLE'])." as ID_ARTICLE, m.NAME as MANUFACTURER,
			DATEDIFF(a.STAMP_END, NOW()) as RUNTIME_LEFT, a.ID_".strtoupper($row['TABLE'])." as ID_AD
		FROM
			".$row['TABLE']." a
			LEFT JOIN
			  `ad_images` i ON i.FK_AD = a.ID_".strtoupper($row['TABLE'])."
			LEFT JOIN
			  `manufacturers` m ON m.ID_MAN = a.FK_MAN
		WHERE
			a.ID_".strtoupper($row['TABLE'])."=".$row['FK_AD']);

	$ad = array_merge($ad_master, $ad);

	### Debug output
	$username = $db->fetch_atom("SELECT NAME FROM `user` WHERE ID_USER=".(int)$ad["FK_USER"]);
	Debug_Append("Eingestellt von - User-ID: ".$ad["FK_USER"].", Name: ".$username);

	for($i=0; $i<count($ar_lang); $i++)
	{
		$all_ok = true;
		### SET LANGUAGE
		$s_lang = $GLOBALS['s_lang'] = $ar_lang[$i]['ABBR'];
		$langval = $GLOBALS['langval'] = $ar_lang[$i]['BITVAL'];
		### Debug output
		Debug_Append("Verarbeite Sprache: ".$s_lang." (".$langval.")");

		### Kategorie
		$ar_kat = $db->fetch1("
			SELECT
		        s.V1 AS KAT,
		        s.V2 AS KATDSC,
		        s.T1 AS KATKEYWORDS,
		        t.B_FREE
			FROM
				`kat` t
            LEFT JOIN
				string_kat s
			ON
				s.S_TABLE='kat' AND s.FK=t.ID_KAT AND
				s.BF_LANG=if(t.BF_LANG_KAT & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_KAT+0.5)/log(2)))
			WHERE
				ID_KAT=".$ad['FK_KAT']);
		list($ar_kat["KATKEYWORDS"], $kat_meta) = explode("||||", $ar_kat["KATKEYWORDS"]);
		$ad = array_merge($ad, $ar_kat);
		Rest_MarketplaceAds::extendAdDetailsSingle($ad);

		$time = ($ad['STAMP_START'] ? strtotime($ad['STAMP_START']) : time());

		Debug_Append("(".$langval.") Schreibe Row-Cache...");
		### ROW files
		$file = $ab_path."tpl/".$s_lang."/marktplatz.row.htm";
		$tpl = new Template($file);
		$tpl->isTemplateRecursiveParsable = TRUE;
		$tpl->addvars($ad);

        $path = AdManagment::getAdCachePath($ad['ID_'.strtoupper($row['TABLE'])], true);

		$html = $tpl->process();
		$html = str_replace(array("^", "°"), array("{", "}"), $html);
		file_put_contents($path."/row.".$s_lang.".htm", $html);
		### Debug output
		Debug_Append("(".$langval.") Template geschrieben: ".$path."/row.".$s_lang.".htm");

		Debug_Append("(".$langval.") Schreibe Box-Cache...");
		### BOX files
		$file = $ab_path."tpl/".$s_lang."/ads.box.htm";
		$tpl_box = new Template($file);
		$tpl_box->addvars($ad);
		### Name auf 20 Zeichen kürzen
		$tpl_box->addvar("SHORTNAME", (strlen($ad["PRODUKTNAME"]) > 22 ? substr($ad["PRODUKTNAME"], 0, 19)."..." : $ad["PRODUKTNAME"]));

        $path = AdManagment::getAdCachePath($ad['ID_'.strtoupper($row['TABLE'])], true);

		$html = $tpl_box->process();
		$html = str_replace(array("^", "°"), array("{", "}"), $html);
		file_put_contents($path."/box.".$s_lang.".htm", $html);
		Debug_Append("(".$langval.") Template geschrieben: ".$path."/box.".$s_lang.".htm");

		### complete ad
		if ($row["LU_LAUFZEIT"] > 0) {
			$runtime_days = $db->fetch_atom("SELECT VALUE FROM lookup WHERE ID_LOOKUP=".$row["LU_LAUFZEIT"]);
		} else {
			Debug_Append("Fehler! Keine Laufzeit vorhanden!");
			$all_ok = false;
		}

        // Geolocation
        if($ad['LONGITUDE'] == 0 || $ad['LATITUDE'] == 0) {
            $countryAsName = $db->fetch_atom("SELECT V1 FROM `string` WHERE S_TABLE='country' AND BF_LANG=".$langval." AND FK=".(int)$ad["FK_COUNTRY"]);
            $geoCoordinates = Geolocation_Generic::getGeolocationCached("", $ad['ZIP'], $ad['CITY'], $countryAsName);
            if (($geoCoordinates !== null) && ($geoCoordinates !== false)) {
                $ad['LONGITUDE'] = $geoCoordinates['LONGITUDE'];
                $ad['LATITUDE'] = $geoCoordinates['LATITUDE'];

                $db->querynow("UPDATE ad_master SET LONGITUDE = '".$ad['LONGITUDE']."', LATITUDE = '".$ad['LATITUDE']."' WHERE ID_AD_MASTER = '".$ad['ID_AD']."'");
                $db->querynow("UPDATE ".$row['TABLE']." SET LONGITUDE = '".$ad['LONGITUDE']."', LATITUDE = '".$ad['LATITUDE']."' WHERE ID_".strtoupper($row['TABLE'])." = '".$ad['ID_AD']."'");
                Debug_Append("Geokoordinaten suchen...");

            }
        }


		/*
		$image_count = $db->fetch_atom("SELECT count(*) FROM `ad_images` WHERE FK_AD=".$ad['ID_'.strtoupper($row['TABLE'])]);
	    $image_count = $image_count - $nar_systemsettings["MARKTPLATZ"]["FREE_IMAGES"];
	    $image_count = ($image_count <= 0 ? 0 : $image_count);
	    $image_count_avail = $packet_class->GetPacketCount(85, $ad["FK_USER"]);

	    $upload_count = $db->fetch_atom("SELECT count(*) FROM `ad_upload` WHERE FK_AD=".$ad['ID_'.strtoupper($row['TABLE'])]);
	    $upload_count = $upload_count - $nar_systemsettings["MARKTPLATZ"]["FREE_UPLOADS"];
	    $upload_count = ($upload_count <= 0 ? 0 : $upload_count);
	    $upload_count_avail = $packet_class->GetPacketCount(88, $ad["FK_USER"]);
	    */

		### ID des verwendeten Anzeigenpakets holen
		$id_packet_order = $row["FK_PACKET_ORDER"];
        $ar_order = array();
		$packet_ok = true;
		if ($id_packet_order > 0) {
			// Status des Anzeigenpakets auslesen
			$order = $packets->order_get($id_packet_order);
			$ar_order = $order->getPacketUsage($ad["ID_ARTICLE"]);
			if ($ar_order["ads_available"] < $ar_order["ads_required"]) $packet_ok = false;
			if ($ar_order["images_available"] < $ar_order["images_required"]) $packet_ok = false;
			if ($ar_order["downloads_available"] < $ar_order["downloads_required"]) $packet_ok = false;
		} else if ($ad["B_FREE"] || $nar_systemsettings["MARKTPLATZ"]["FREE_ADS"]) {
			// Kostenlose Anzeige(n)
			### Verwendete Bilder auslesen
			$images = $db->fetch_atom("SELECT count(*) FROM `ad_images` WHERE FK_AD=".(int)$ad["ID_ARTICLE"]);
			$uploads = $db->fetch_atom("SELECT count(*) FROM `ad_upload` WHERE FK_AD=".(int)$ad["ID_ARTICLE"]);
			if (($images > $nar_systemsettings["MARKTPLATZ"]["FREE_IMAGES"])
				|| ($uploads > $nar_systemsettings["MARKTPLATZ"]["FREE_UPLOADS"])) {
				$packet_ok = false;
			}
		} else {
			$packet_ok = false;
		}

	    if ($all_ok && (($ad["STATUS"] & 5) == 0)) {
	    	if ($id_packet_order > 0) {
	    		// Verwendete Anzeigen/Bilder/Uploads vom Paket abziehen
				if (($ar_order["ads_required"] + $ar_order["images_required"] + $ar_order["downloads_required"]) > 0) {
					// Neue Anzeigen/Bilder/Downloads vom Anzeigenpaket abziehen.
					if ($ar_order["ads_required"] > 0) {
						foreach ($ar_order["ads_new"] as $index => $id_ad_new) {
							$order->itemAddContent("ad", $id_ad_new);
						}
					}
					if ($ar_order["images_required"] > 0) {
						foreach ($ar_order["images_new"] as $index => $id_image_new) {
							$order->itemAddContent("image", $id_image_new);
						}
					}
					if ($ar_order["downloads_required"] > 0) {
						foreach ($ar_order["downloads_new"] as $index => $id_upload_new) {
							$order->itemAddContent("download", $id_upload_new);
						}
					}
				}
	    	}
			### Verschieben wenn sich der Pfad geändert hat
			$new_path = AdManagment::getAdCachePath($ad['ID_'.strtoupper($row['TABLE'])], true);

			if ($path != $new_path) {
				unset($ad['STAMP_START']);
				createPath($new_path, true, $ab_path);
				system('mv '.$path.'/*.* '.$new_path);
				system('rm -r '.$path);
				Debug_Append("-> Cache-Box verschoben: ".$path." -> ".$new_path);
			}
			$ad["STATUS"] = 1;

			### put ad online
			$db->querynow("
				UPDATE
					`".$row["TABLE"]."`
				SET
					STAMP_START = NOW(),
					STAMP_END = (NOW() + INTERVAL ".$runtime_days." DAY),
    		    	STATUS = STATUS | 1
				WHERE
					ID_".strtoupper($row['TABLE'])."=".$row["FK_AD"]
			);

    		/*
    		 * zusätzlicher code 9.2.2010
    		 */
    		$db->querynow("
    			UPDATE
    				`ad_master`
    			SET
    		    	STAMP_START = NOW(),
    		    	STAMP_END = (NOW() + INTERVAL ".$runtime_days." DAY),
    		    	STATUS = STATUS | 1
    		    WHERE
    		    	ID_AD_MASTER=".$row["FK_AD"]
    		);
    		/*
    		 * ende zusätzlicher code
    		 */

    		$ad["STATUS"] = $ad["STATUS"] | 1;
    		$db->querynow("
    		INSERT INTO `usercontent`
				(`FK_USER`, `ADS_USED`)
			VALUES
    			(".$uid.",1)
    		ON DUPLICATE KEY UPDATE
    			ADS_USED=ADS_USED+1");

    		require_once $ab_path."sys/lib.ad_agent.php";
    		ad_agent::CheckAd($ad);

			### Anzeige wurde erfolgreich freigeschaltet
			Debug_Append("Anzeige erstmals freigeschaltet!");
		} else if (($ad["STATUS"] & 1) == 0) {
			### Paket verfügt nicht über ausreichend Anzeigen/Bilder/Downloads
	    	$all_ok = false;
			Debug_Append("========== FEHLER ==========");
			Debug_Append("Kontingent des Paketes ist nicht ausreichend!");
			Debug_Append("Verwendete Paket mit Vertrags-ID  ".$id_packet_order.".");
			Debug_Append("Anzeigen: ".$ar_order["ads_required"]." benötigt, vorhanden: ".$ar_order["ads_available"]);
			Debug_Append("Bilder: ".$ar_order["images_required"]." benötigt, vorhanden: ".$ar_order["images_available"]);
			Debug_Append("Uploads: ".$ar_order["uploads_required"]." benötigt, vorhanden: ".$ar_order["uploads_available"]);
		}
        if ((($ad["STATUS"] & 1) == 0) && ($id_packet_order > 0)) {
            ### Anzahl der verfügbaren Anzeigen updaten wenn Anzeige ausgelaufen ist (nur bei Abo)
            if ($order->isRecurring() && $order->isUsed("ad", (int)$ad["ID_ARTICLE"])) {
                $order->itemRemContent("ad", (int)$ad["ID_ARTICLE"]);
            }
        }
		### Debug ausgabe
		Debug_Append("Keywords für die Suche werden erzeugt.");
	    ### SEARCH DB
		$fieldlist = $db->fetch_nar("
			SELECT
				F_NAME, FK_LISTE
			FROM
				`field_def`
			WHERE
				FK_TABLE_DEF=
			(SELECT ID_TABLE_DEF FROM `table_def` WHERE T_NAME='".mysql_escape_string($row["TABLE"])."' LIMIT 1)");

		### Kategorie auslesen

		### Ariane-Faden der Kategorien
	    $category_path = $kat_cache->kats_read_path($ad["FK_KAT"], $langval);
	    $category_path_plain = array();
	    foreach ($category_path as $index => $category) {
	    	$category_path_plain[] = $category["V1"];
	    }
	    $ad["CATEGORY_PATH"] = implode(" ", $category_path_plain);

	    ### Texte aus Feldern der Anzeige holen
		$ignoreKeys = array('AD_AGB', 'AD_WIDERRUF');
	    $search_text = array();
	    foreach ($ad as $key => $value) {
			if (!is_numeric($value)) {
				if(!in_array($key, $ignoreKeys)) {
					$value = str_replace(array('Ä','Ü','Ö','ä','ü','ö','ß', '-'), array('Ae','Ue','Oe','ae','ue','oe','ss', '_'), $value);
					$value = preg_replace("/[^\sa-z0-9_]/si", "", $value);
					$search_text[] = strtolower(strip_tags($value));
				}
			}
			if ($fieldlist[$key] > 0 && $value != "") {
				$liste_value = $value;
				$value = $db->fetch_atom("
					SELECT
						s.V1
					FROM
						`liste_values` l
					LEFT JOIN
						`string_liste_values` s
	        		ON
	        			s.S_TABLE='liste_values' AND s.FK=l.ID_LISTE_VALUES AND
	        			s.BF_LANG=if(l.BF_LANG_LISTE_VALUES & ".$langval.", ".$langval.", 1 << floor(log(l.BF_LANG_LISTE_VALUES+0.5)/log(2)))
					WHERE
						l.FK_LISTE=".$fieldlist[$key]." AND l.ID_LISTE_VALUES=".$liste_value);
				$value = str_replace(array('Ä','Ü','Ö','ä','ü','ö','ß','-'), array('Ae','Ue','Oe','ae','ue','oe','ss','_'), $value);
				$value = preg_replace("/[^\sa-z0-9_]/si", "", $value);
				$search_text[] = strtolower($value);
			}
		}

	    /**
	     * Lookups auflösen
	     */
	    ### Country
	    $search_text[] = $db->fetch_atom("
	    	SELECT
	    		V1
	    	FROM
	    		`string`
			WHERE
				S_TABLE='country' AND BF_LANG=".$langval." AND
				FK=".(int)$ad["FK_COUNTRY"]);

	    ### Text zusammenfügen
	    $search_text = implode(" ", $search_text);

	    ### Alte Einträge aus der Suchtabelle entfernen
	    $db->querynow("
	    	DELETE FROM
	    		`ad_search`
	    	WHERE
	    		FK_AD=".$ad["ID_ARTICLE"]." AND
	    		AD_TABLE='".$row['TABLE']."' AND
	    		LANG='".$s_lang."'");

	    if (($ad["STATUS"] & 3) == 1) {
			### Debug ausgabe
			Debug_Append("Keywords für die Suche werden eingetragen.");
			### Insert new search-text
			$db->querynow("
				INSERT INTO `ad_search`
					(FK_AD, FK_USER, LANG, AD_TABLE, STEXT)
		      	VALUES
		      		(".$ad["ID_ARTICLE"].", ".$ad["FK_USER"].", '".$s_lang."', '".$row['TABLE'].
		      		"', '".mysql_escape_string($search_text)."')");
	    }

		### kick from temp
		if($all_ok)
		{
			/*
			 * alter code vor 9.2.2010
			 *
			$db->querynow("
				UPDATE
					ad_temp
				SET
				  DONE=1, STAT = NULL
				WHERE
					FK_AD=".$row['FK_AD']."
					AND `TABLE`='".$row['TABLE']."'");
			 *
			 * neuer code 9.2.2010
			 *
			 */
			$db->querynow("
				UPDATE
					ad_master
				SET
				  CRON_DONE=1, CRON_STAT = NULL
				WHERE
					ID_AD_MASTER=".$row['FK_AD']."
					AND `AD_TABLE`='".$row['TABLE']."'");
			/*
			 * ende neuer code
			 */
		}
		else
		{
			/*
			 * alter code vor 9.2.2010
			 *
			$db->querynow("
				UPDATE
					ad_temp
				SET
					STAT = NULL
				WHERE
					FK_AD=".$row['FK_AD']."
					AND `TABLE`='".$row['TABLE']."'");
			 *
			 * neuer code 9.2.2010
			 *
			 */
			$db->querynow("
				UPDATE
					ad_master
				SET
					CRON_STAT = 0
				WHERE
					ID_AD_MASTER=".$row['FK_AD']."
					AND `AD_TABLE`='".$row['TABLE']."'");
			/*
			 * ende neuer code
			 */
		}
	} 	// languages

	### Debug ausgabe
	Debug_Append("Verarbeitung der Anzeige abgeschlossen.");
	Debug_Append("=======================================");

    $countIterations++;
}

// file_put_contents($ab_path."_debug_admaker.txt", ob_get_contents());

Debug_Append("Cronjob 'admaker' beendet.\n");
Debug_Close();

?>