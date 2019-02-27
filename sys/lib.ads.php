<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $GLOBALS["ab_path"].'sys/lib.ad_variants.php';
require_once $GLOBALS["ab_path"]."sys/lib.pub_kategorien.php";

class AdManagment {

	private static $localCache = array();
	
	private static function getLocalCache($ident) {
		if (array_key_exists($ident, self::$localCache)) {
			return self::$localCache[$ident];
		}
		return null;
	}
	
	private static function setLocalCache($ident, &$value) {
		self::$localCache[$ident] = $value;
		return $value;
	}

	static function getAdById($adId)
    {
        global $db, $langval;

        $ar_kat_table = $db->fetch1("SELECT AD_TABLE, FK_KAT, FK_USER, STATUS FROM `ad_master` WHERE ID_AD_MASTER=" . $adId." AND DELETED=0");
        if ($ar_kat_table) {
            $kat_table = $ar_kat_table['AD_TABLE'];
            $id_kat = $ar_kat_table['FK_KAT'];

            $article_data = $db->fetch1("
				SELECT
					   SQL_CALC_FOUND_ROWS
					   adt.*,
					   a.AD_AGB,
					   a.AD_WIDERRUF,
					   a.FK_ARTICLE_EXT,
					   DATEDIFF(a.STAMP_END,NOW()) as DAYS_LEFT,
					   adt.ID_AD_MASTER as ID_AD,
					   adt.BESCHREIBUNG AS DSC, adt.TRADE AS product_trade,
					   (SELECT s.V1
						   FROM `kat` k
							 LEFT JOIN `string_kat` s ON s.S_TABLE='kat' AND s.FK=k.ID_KAT
							   AND s.BF_LANG=if(k.BF_LANG_KAT & " . $langval . ", " . $langval . ", 1 << floor(log(k.BF_LANG_KAT+0.5)/log(2)))
						   WHERE k.ID_KAT=a.FK_KAT	) as KAT,
					   (SELECT m.NAME FROM `manufacturers` m WHERE m.ID_MAN=a.FK_MAN) as MANUFACTURER,
					   (SELECT slang.V1 FROM `string` slang WHERE slang.FK=a.FK_COUNTRY
						   AND slang.BF_LANG='" . $langval . "' LIMIT 1) as LAND,
					   (SELECT i.SRC_THUMB FROM `ad_images` i WHERE i.IS_DEFAULT=1 AND i.FK_AD=ID_AD LIMIT 1) as SRC_THUMB,
					   (SELECT i.SRC FROM `ad_images` i WHERE i.IS_DEFAULT=1 AND i.FK_AD=ID_AD LIMIT 1) as SRC,
					   adt.B_TOP
				   FROM `" . $kat_table . "` a
				   JOIN ad_master adt ON a.ID_" . strtoupper($kat_table) . " = adt.ID_AD_MASTER
				   WHERE
					   (adt.STATUS&3)=1 AND (adt.DELETED=0) AND
					   ID_" . strtoupper($kat_table) . "=" . $adId);
            return $article_data;
        } else {
            return null;
        }
    }

    static function logCreateArticle($userId) {
        global $db;
        $db->querynow("
					INSERT INTO `ad_log`
						(`ID_DATE`, `FK_USER`,`CREATES`)
					VALUES
						('".date('Y-m-d')."',".$userId.",1)
					ON DUPLICATE KEY UPDATE
						CREATES=CREATES+1");
    }
     
    /**
     * Create a new ad with the required information as an array.
     * Nothing will be written to the database!
     */
    static function createArticleAsArray($id_kat, $id_user = NULL, $basedata = array()) {
        global $db;
		$arArticle = $basedata;
        $arUser = false;

        if ($id_user > 0) {
            // Userdaten als Vorgabe laden
            $arArticle = $arUser = $db->fetch1("SELECT * FROM `user` WHERE ID_USER=".$id_user);
            $arArticle["AD_AGB"] = $db->fetch_atom("SELECT AGB FROM `usercontent` WHERE FK_USER=".$id_user);
            $arArticle["AD_AGB"] = (isset($arArticle["AD_AGB"]) ? $arArticle["AD_AGB"] : null);
            $arArticle["AD_WIDERRUF"] = $db->fetch_atom("SELECT WIDERRUF FROM `usercontent` WHERE FK_USER=".$id_user);
            $arArticle["AD_WIDERRUF"] = (isset($arArticle["AD_WIDERRUF"]) ? $arArticle["AD_WIDERRUF"] : null);
        }
        // Add default options
        $arArticle["ZUSTAND"] = 1;
        $arArticle["MWST"] = 1;
        $arArticle["VERSANDOPTIONEN"] = 3;
		$arArticle["STREET"] = $arArticle["STRASSE"];
		$arArticle["ZIP"] = $arArticle["PLZ"];
        $arArticle["CITY"] = $arArticle["ORT"];
        $arArticle["BF_CONSTRAINTS"] = $arArticle["DEFAULT_CONSTRAINTS"];
        // Apply usergroup settings
        if ($arUser !== false) {
            $arUsergroup = $db->fetch1("SELECT * FROM `usergroup` WHERE ID_USERGROUP=".$arUser["FK_USERGROUP"]);
            $arUsergroupOptions = @unserialize($arUsergroup["SER_OPTIONS"]);
            if ($arUsergroupOptions !== false) {
                $arArticle["MWST"] = $arUsergroupOptions["AD_CREATE"]["MWST_DEFAULT"];
            }
        }
        // Add supplied parameters
        $arArticle["FK_KAT"] = $id_kat;
        $arArticle["FK_USER"] = $id_user;
		$arArticle["AD_TABLE"] = $db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=" . $id_kat);
        // Check if category exists
        if ($arArticle["AD_TABLE"] === false) return false;
        // Initialize arrays
        $arArticle['images'] = array();
        $arArticle['videos'] = array();
        $arArticle['uploads'] = array();
        // Return result array
        return $arArticle;  
    }

    /**
     * Insert an article array into database including images etc. into database.
     */
    static function createArticleFromArray($arArticle, $id_user = NULL, $enable = false, &$success = false) {
        global $db, $uid, $ab_path, $nar_systemsettings, $langval;
        if ($id_user == NULL) {
            $id_user = $uid;
        }
        $success = false;
        /**
         * Basic article entries
         */
        // Ensure "new-state"
        $arArticle["STATUS"] = 0;
        $arArticle['CRON_DONE'] = 0;
        $arArticle['CRON_STAT'] = NULL;
        $arArticle['FK_USER'] = $id_user;
        // Moderate ads?
        if ($nar_systemsettings["MARKTPLATZ"]["MODERATE_ADS"]) {
            $userIsAutoConfirmed = $db->fetch_atom("SELECT AUTOCONFIRM_ADS FROM `user` WHERE ID_USER=".$id_user);
            if ($userIsAutoConfirmed) {
                $arArticle["CONFIRMED"] = 1;
            } else {
                $arArticle["CONFIRMED"] = 0;
                $arArticle['CRON_DONE'] = 1;
            }
        } else {
            $arArticle["CONFIRMED"] = 1;
        }
        // Allow comments?
        if (!$arArticle["ALLOW_COMMENTS"] && ($id_user > 0)) {
            // Read default setting
            $userAllowComments = $db->fetch_atom("SELECT if(ALLOW_COMMENTS&1 > 0,1,0) FROM `usersettings` WHERE FK_USER=".$id_user);
            $arArticle["ALLOW_COMMENTS"] = $userAllowComments;
        }
        // Herstellerdatenbank
        if ($nar_systemsettings["MARKTPLATZ"]["USE_PRODUCT_DB"]) {
        	$arArticle = self::updateManufacturerDatabaseForArticle($arArticle);
        }
        // Insert into master
		if(!isset($arArticle['AD_TABLE']) || $arArticle['AD_TABLE'] == '') {
			$arArticle['AD_TABLE'] = $kat_table = $db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=" . $arArticle['FK_KAT']);
		} else {
			$kat_table = $arArticle['AD_TABLE'];
		}
		if (array_key_exists("JSON_ADDITIONAL", $arArticle)) {
			$arArticle["JSON_ADDITIONAL"] = json_encode($arArticle["JSON_ADDITIONAL"]);
		}
		
		// Trigger plugin event
		$paramAdCreate = new Api_Entities_EventParamContainer(array(
			"data"		=> $arArticle,
			"enable"	=> $enable,
			"import"	=> false
		));
		Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_AD_CREATE, $paramAdCreate);
		if ($paramAdCreate->isDirty()) {
			$arArticle = $paramAdCreate->getParam("data");
			$enable = $paramAdCreate->getParam("enable");
		}
		
        $id_name = 'ID_'.strtoupper($kat_table);
        $id_article = $db->update("ad_master", $arArticle);
        if (!$id_article) {
            // Failed to insert article
            return false;
        }
        // Insert into article table
        $db->querynow("INSERT INTO `".mysql_escape_string($kat_table)."` (".$id_name.")
                VALUES (".$id_article.")");
        $arArticle[$id_name] = $id_article;
        $db->update($kat_table, $arArticle);
        /**
         * Files (Images/Uploads)
         */
        $uploads_dir = AdManagment::getAdCachePath($id_article, true);
        // Add images
		if(isset($arArticle['images']) && is_array($arArticle['images'])) {
			foreach ($arArticle['images'] as $index => $arImage) {
				$src = $uploads_dir . "/" . basename($arImage['TMP']);
				$src_thumb = $uploads_dir . "/" . basename($arImage['TMP_THUMB']);
				if (rename($arImage['TMP'], $src) && rename($arImage['TMP_THUMB'], $src_thumb)) {
					$arImage['FK_AD'] = $id_article;
					$arImage['SRC'] = "/" . str_replace($ab_path, "", $src);
					$arImage['SRC_THUMB'] = "/" . str_replace($ab_path, "", $src_thumb);
					$arImage['SER_META'] = serialize(array_key_exists('META', $arImage) ? $arImage['META'] : array());
					$id_image = $db->update("ad_images", $arImage, true);
					if (!$id_image) {
						// Failed to insert image
						return FALSE;
					}
					if (array_key_exists("VARIANTS", $arImage) && !empty($arImage["VARIANTS"])) {
						foreach ($arImage["VARIANTS"] as $variantFieldName => $variantValue) {
							if (!empty($variantValue)) {
								$variantFieldId = Ad_Marketplace::getFieldIdByName($arArticle["FK_TABLE_DEF"], $variantFieldName);
								$db->querynow("
									INSERT INTO `ad_images_variants` (ID_IMAGE, ID_FIELD_DEF, ID_LISTE_VALUE)
									VALUES (".$id_image.", ".$variantFieldId.", ".(int)$variantValue.")");
							}
						}
						
					}
				}
			}
		}

        // Add uploads
		if(isset($arArticle['uploads']) && is_array($arArticle['uploads'])) {

			foreach ($arArticle['uploads'] as $index => $arUpload) {
				$src = $uploads_dir . '/' . $arUpload['FILENAME'] . '_x_' . time() . '_x_.' . $arUpload['EXT'];
				if (rename($arUpload['TMP'], $src)) {
					$arUpload['FK_AD'] = $id_article;
					$arUpload['SRC'] = $src;
					$id_upload = $db->update("ad_upload", $arUpload, true);
					if (!$id_upload) {
						// Failed to insert image
						return FALSE;
					}
				}
			}
		}
        /**
         * Videos
         */
		if(isset($arArticle['videos']) && is_array($arArticle['videos'])) {

			foreach ($arArticle['videos'] as $index => $arVideo) {
				if (!array_key_exists("CODE", $arVideo) || empty($arVideo["CODE"])) {
					// Skip empty inputs
					continue;
				}
				$arVideo['FK_AD'] = $id_article;
				$id_video = $db->update("ad_video", $arVideo, true);
				if (!$id_video) {
					// Failed to insert video
					return FALSE;
				}
			}
		}

        /**
         * Payment methods
         */
		if(isset($arArticle['paymentAdapters']) && is_array($arArticle['paymentAdapters'])) {
			$adPaymentAdapterManagement = AdPaymentAdapterManagement::getInstance($db);
			$adPaymentAdapterManagement->updatePaymentAdapterForAd($id_article, $arArticle['paymentAdapters']);
		}

		// Trigger plugin event
		$paramAdCreate = new Api_Entities_EventParamContainer(array(
			"id" 		=> $id_article,
			"data"		=> $arArticle,
			"enable"	=> $enable,
			"import"	=> false
		));
		Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_AD_CREATED, $paramAdCreate);
		if ($paramAdCreate->isDirty()) {
			$enable = $paramAdCreate->getParam("enable");
		}
		
        if ($enable) {
            if ($arArticle["CONFIRMED"] == 1) {
                $success = AdManagment::Enable($id_article, $kat_table);
            } else if (($arArticle["STATUS"]&3) == 1) {
                self::Disable($id_article, $kat_table);
                $success = true;
            } else {
                $success = true;
            }
        } else {
            $success = ($id_article > 0);
        }
        return $id_article;
    }

	static function createPath($path, $writeable=false, $base = NULL)
	{
		$path = str_replace($base, "", $path);

		$hack = explode("/", trim($path));
        $run = '';
		for($i=0; $i<count($hack); $i++)
		{
			if($hack[$i] === '')
			{
				continue;
			}
			$run .= $hack[$i]."/";
			if(!is_dir($base.$run))
			{
				system("mkdir ".$base.$run."\n");

				if(!is_dir($base.$run))
				{
					die("system() not working in AdManagement::createPath()");
				}
				if($writeable)
				{
					system("chmod 0777 ".$base.$run."\n");
					#die("here i am");
				}
			} // not a dir
		} // for hack
	} // createPath()

	static function deleteArticleImages(ebiz_db $db, $articleId) {
		$arImages = $db->fetch_table("SELECT SRC, SRC_THUMB FROM `ad_images` WHERE FK_AD=".(int)$articleId);
		foreach ($arImages as $imageIndex => $imageData) {
			if (file_exists($GLOBALS['ab_path'].$imageData["SRC"]) && !is_dir($GLOBALS['ab_path'].$imageData["SRC"])) {
				unlink($GLOBALS['ab_path'].$imageData["SRC"]);
			}
			if (file_exists($GLOBALS['ab_path'].$imageData["SRC_THUMB"]) && !is_dir($GLOBALS['ab_path'].$imageData["SRC_THUMB"])) {
				unlink($GLOBALS['ab_path'].$imageData["SRC_THUMB"]);
			}
		}
		$db->querynow("DELETE FROM ad_images WHERE FK_AD = '" . (int)$articleId . "'");
		return true;
	}
	
	/**
	 * Eine Anzeige kaufen
	 *
	 * @param	int		$id_ad
	 * @param	int		$uid_client
	 * @param	float	$preis
	 * @param	int		$menge
	 * @param	int		$id_trade
	 *
	 * @return	int|bool	Transaktions-ID des Verkaufs oder false bei Fehler.
	 */
	static function Buy($id_ad, $id_ad_variant, $ar_availability, $uid_client, $id_invoice_addr, $id_versand_addr, $preis, $menge = 1, $id_trade = 0, $id_order = NULL, $ar_article_override = array()) {
		// Globale Variablen "importieren"
		global $db, $uid, $nar_systemsettings, $langval;

		$ad = $db->fetch1("
			SELECT
					a.*,
					a.ID_AD_MASTER as ID_AD,
					a.BESCHREIBUNG AS DSC,
					m.NAME AS MANUFACTURER,
					(SELECT slang.V1 FROM `string` slang WHERE slang.FK=a.FK_COUNTRY
		    			AND slang.BF_LANG='".$langval."' LIMIT 1) as LAND
				FROM
					`ad_master` a
				LEFT JOIN
					manufacturers m on a.FK_MAN=m.ID_MAN
				WHERE
					a.ID_AD_MASTER = ".$id_ad."
					AND a.STATUS&3=1 AND a.DELETED=0");
		if (empty($ad)) return false;
		
		$ad = array_merge($ad, $ar_article_override);

		$variants = AdVariantsManagement::getInstance($db);
		$adVariant = $variants->getAdVariantDetailsById($id_ad_variant);
		$adVariantFields = $variants->getAdVariantTextById($id_ad_variant);

		$ad = array_merge($ad, $adVariant);

		$ad['PREIS_KOMPLETT'] = $menge * $preis;

		if ($id_trade > 0) {
			// Verhandlung akzeptiert!
			$db->querynow("
				UPDATE
					trade
				SET
					BID_STATUS = 'ACCEPTED'
				WHERE
					ID_TRADE=".$id_trade);
		}

		$prov = $provsatz = 0;
		if($nar_systemsettings['MARKTPLATZ']['USE_PROV'] == 1) {
			// Provisionssatz errechnen
			$preis_artikel = ((int)$menge * $preis);
			$id_usergroup = $db->fetch_atom("SELECT FK_USERGROUP FROM `user` WHERE ID_USER=".$ad["FK_USER"]);
			$ar_usergroup = $db->fetch1("SELECT PROV_MAX FROM `usergroup` WHERE ID_USERGROUP=".$id_usergroup);
			$res = $db->querynow("
				select
					*
				from
					provsatz
				where
					FK_USERGROUP=".$id_usergroup."
				order by
					PRICE ASC");
			$ar_psatz = array();
			while($row = mysql_fetch_assoc($res['rsrc'])) {
				if ($preis >= $row['PRICE']) {
					$provsatz = $row['PSATZ'];
				}
			}
			if($provsatz > 0) {
				$prov = ($preis /100)*$provsatz;
				#echo $provsatz.'%';
				#echo '<br />prov ist: '.$prov;
			}
			if (($ar_usergroup["PROV_MAX"] > 0) && ($ar_usergroup["PROV_MAX"] < $prov)) {
				// Provisions-Deckel
				$prov = $ar_usergroup["PROV_MAX"];
			}
		}
		$userdata = $db->fetch1("
			SELECT
				NAME as USERNAME_B,
				FIRMA, VORNAME, NACHNAME, STRASSE, PLZ, ORT, FK_COUNTRY, EMAIL, TEL
			FROM
				`user`
			WHERE
				ID_USER=".$uid_client);

		$data_sell = array(
			"FK_USER_VK"			=> $ad["FK_USER"],
			"FK_USER"				=> $uid_client,
			"FK_AD_ORDER"			=> $id_order,
			"FK_AD"					=> $id_ad,
			"FK_AD_VARIANT"			=> $id_ad_variant,
			"SER_VARIANT"			=> serialize($adVariantFields),
			"SER_AVAILABILITY"		=> (is_array($ar_availability) ? serialize($ar_availability) : null),
			"FK_MAN"				=> $ad["FK_MAN"],
			"PRODUKTNAME"			=> $ad["PRODUKTNAME"],
			"NOTIZ"					=> $ad["NOTIZ"],
			"MENGE"					=> $menge,
			"PREIS_PROV"			=> $preis,
			"PROVSATZ"				=> $provsatz,
			"PROV"					=> $prov,
			"PREIS"					=> $ad['PREIS_KOMPLETT'],
			"MWST"					=> $ad['MWST'],
			"VERSANDOPTIONEN"		=> $ad['VERSANDOPTIONEN'],
			"VERSANDKOSTEN"			=> $ad['VERSANDKOSTEN'],
			"VERSANDKOSTEN_INFO"	=> $ad['VERSANDKOSTEN_INFO'],
			"STAMP_BOUGHT"  		=> date('Y-m-d H:i:s')
		);

		if ($id_trade > 0) {
			$ar_versand = $db->fetch1("SELECT * FROM `trade` WHERE ID_TRADE=" . $id_trade);
			$data_sell = array_merge($data_sell, $ar_versand);
		} else {
			if (is_array($id_invoice_addr)) {
				$data_sell["INVOICE_FIRMA"] = $id_invoice_addr["COMPANY"];
				$data_sell["INVOICE_VORNAME"] = $id_invoice_addr["FIRSTNAME"];
				$data_sell["INVOICE_NACHNAME"] = $id_invoice_addr["LASTNAME"];
				$data_sell["INVOICE_STRASSE"] = $id_invoice_addr["STREET"];
				$data_sell["INVOICE_PLZ"] = $id_invoice_addr["ZIP"];
				$data_sell["INVOICE_ORT"] = $id_invoice_addr["CITY"];
				$data_sell["INVOICE_FK_COUNTRY"] = $id_invoice_addr["FK_COUNTRY"];
				$data_sell["INVOICE_TEL"] = $id_invoice_addr["PHONE"];
			} else if ($id_invoice_addr > 0) {
				$ar_invoice = $db->fetch1("SELECT * FROM `user_invoice` WHERE ID_USER_INVOICE=" . (int)$id_invoice_addr);
				if (!empty($ar_invoice)) {
					$data_sell["INVOICE_FIRMA"] = $ar_invoice["COMPANY"];
					$data_sell["INVOICE_VORNAME"] = $ar_invoice["FIRSTNAME"];
					$data_sell["INVOICE_NACHNAME"] = $ar_invoice["LASTNAME"];
					$data_sell["INVOICE_STRASSE"] = $ar_invoice["STREET"];
					$data_sell["INVOICE_PLZ"] = $ar_invoice["ZIP"];
					$data_sell["INVOICE_ORT"] = $ar_invoice["CITY"];
					$data_sell["INVOICE_FK_COUNTRY"] = $ar_invoice["FK_COUNTRY"];
					$data_sell["INVOICE_TEL"] = $ar_invoice["PHONE"];
				} else {
					return false;
				}
			} else {
				$ar_user = $db->fetch1("SELECT * FROM `user` WHERE ID_USER=" . (int)$uid_client);
				if (!empty($ar_user)) {
					$data_sell["INVOICE_FIRMA"] = $userdata["FIRMA"];
					$data_sell["INVOICE_VORNAME"] = $userdata["VORNAME"];
					$data_sell["INVOICE_NACHNAME"] = $userdata["NACHNAME"];
					$data_sell["INVOICE_STRASSE"] = $userdata["STRASSE"];
					$data_sell["INVOICE_PLZ"] = $userdata["PLZ"];
					$data_sell["INVOICE_ORT"] = $userdata["ORT"];
					$data_sell["INVOICE_FK_COUNTRY"] = $userdata["FK_COUNTRY"];
					$data_sell["INVOICE_TEL"] = $userdata["TEL"];
				} else {
					return false;
				}
			}
			if (is_array($id_versand_addr)) {
				$data_sell["VERSAND_FIRMA"] = $id_versand_addr["COMPANY"];
				$data_sell["VERSAND_VORNAME"] = $id_versand_addr["FIRSTNAME"];
				$data_sell["VERSAND_NACHNAME"] = $id_versand_addr["LASTNAME"];
				$data_sell["VERSAND_STRASSE"] = $id_versand_addr["STREET"];
				$data_sell["VERSAND_PLZ"] = $id_versand_addr["ZIP"];
				$data_sell["VERSAND_ORT"] = $id_versand_addr["CITY"];
				$data_sell["VERSAND_FK_COUNTRY"] = $id_versand_addr["FK_COUNTRY"];
				$data_sell["VERSAND_TEL"] = $id_versand_addr["PHONE"];
			} else if ($id_versand_addr > 0) {
				$ar_versand = $db->fetch1("SELECT * FROM `user_versand` WHERE ID_USER_VERSAND=" . (int)$id_versand_addr);
				if (!empty($ar_versand)) {
					$data_sell["VERSAND_FIRMA"] = $ar_versand["COMPANY"];
					$data_sell["VERSAND_VORNAME"] = $ar_versand["FIRSTNAME"];
					$data_sell["VERSAND_NACHNAME"] = $ar_versand["LASTNAME"];
					$data_sell["VERSAND_STRASSE"] = $ar_versand["STREET"];
					$data_sell["VERSAND_PLZ"] = $ar_versand["ZIP"];
					$data_sell["VERSAND_ORT"] = $ar_versand["CITY"];
					$data_sell["VERSAND_FK_COUNTRY"] = $ar_versand["FK_COUNTRY"];
					$data_sell["VERSAND_TEL"] = $ar_versand["PHONE"];
				} else {
					return false;
				}
			} else {
				$ar_user = $db->fetch1("SELECT * FROM `user` WHERE ID_USER=" . (int)$uid_client);
				if (!empty($ar_user)) {
					$data_sell["VERSAND_FIRMA"] = $userdata["FIRMA"];
					$data_sell["VERSAND_VORNAME"] = $userdata["VORNAME"];
					$data_sell["VERSAND_NACHNAME"] = $userdata["NACHNAME"];
					$data_sell["VERSAND_STRASSE"] = $userdata["STRASSE"];
					$data_sell["VERSAND_PLZ"] = $userdata["PLZ"];
					$data_sell["VERSAND_ORT"] = $userdata["ORT"];
					$data_sell["VERSAND_FK_COUNTRY"] = $userdata["FK_COUNTRY"];
					$data_sell["VERSAND_TEL"] = $userdata["TEL"];
				} else {
					return false;
				}
			}
		}

		$sell_id = $db->update("ad_sold", $data_sell);

		$ar_saledata = $db->fetch1("
			select
				ID_USER,
				SALES,
				STORNOS
			FROM
				user
			where
				ID_USER=".$ad['FK_USER']);
		$ar_saledata['SALES']++;
		//$ar_saledata['SALE2STORNO'] = round(($ar_saledata['SALE']/100)*$ar_saledata['STORNOS']);
		$db->update("user", $ar_saledata);

		$ar_saledata = $db->fetch1("
			select
				ID_USER,
				SALES,
				STORNOS
			FROM
				user
			where
				ID_USER=".$uid_client);
		$ar_saledata['BOUGHTS']++;
		$db->update("user", $ar_saledata);
		#die("hold on ...");

		$ad_old = $ad;

		$ad = $db->fetch1("
			SELECT
				ad_sold.*,
				ad_master.*,
				`user`.`NAME` AS USERNAME,
				'".$nar_systemsettings['SITE']['SITEURL']."' AS SITEURL
			FROM
				ad_sold
			LEFT JOIN
				ad_master ON ad_sold.FK_AD=ad_master.ID_AD_MASTER
			LEFT JOIN
				`user` ON ad_master.FK_USER=`user`.ID_USER
			WHERE
				ad_sold.ID_AD_SOLD=".$sell_id);
		$ad = array_merge($ad, $userdata);
		$ad['SITENAME'] = $nar_systemsettings['SITE']['SITENAME'];

		// Mail an den Verk?ufer


		// 31.5.2011 - tabelle.* wegoptimiert von Jens
		$ad = $db->fetch1("
		SELECT
			ad_sold.ID_AD_SOLD,
			ad_sold.MENGE AS MENGE_SOLD,
			ad_sold.FK_AD,
			ad_sold.PREIS,
			ad_master.AUTOCONFIRM,
			ad_master.FK_USER AS UID,
			ad_master.PRODUKTNAME,
			ad_master.MENGE,
			user.NAME,
			'".$nar_systemsettings['SITE']['SITEURL']."' AS SITEURL
		FROM
			ad_sold
		LEFT JOIN
			ad_master ON ad_sold.FK_AD=ad_master.ID_AD_MASTER
		LEFT JOIN
			user ON ad_sold.FK_USER=user.ID_USER
		WHERE
			ad_sold.ID_AD_SOLD=".$sell_id);

		$userdata = $db->fetch1("
			SELECT
				NAME as USERNAME,
				FIRMA as FIRMA_S,
				VORNAME as VORNAME_S,
				NACHNAME AS NACHNAME_S,
				STRASSE as STRASSE_S,
				PLZ as PLZ_S,
				ORT as ORT_S,
				EMAIL as EMAIL_S,
				TEL as TEL_S,
				V1 as LAND_S,
				ZAHLUNG
			FROM
				`user`
			LEFT JOIN
				`usercontent` ON user.ID_USER=usercontent.FK_USER
			LEFT JOIN
				`string` ON string.S_TABLE='country' AND string.FK=user.FK_COUNTRY AND string.BF_LANG='".$langval."'
			WHERE
				ID_USER=".$ad['UID']);
		$ad = array_merge($ad, $userdata);
		$ad['SITENAME'] = $nar_systemsettings['SITE']['SITENAME'];





		if (($sell_id > 0) && ($ad["MENGE"] >= $ad["MENGE_SOLD"])) {
            if ($id_trade > 0) {
                $arTrade = $db->fetch1("SELECT * FROM `trade` WHERE ID_TRADE=".$id_trade);
                if ($arTrade["FK_USER_TO"] == $ad["UID"]) {
                    $ad["AUTOCONFIRM"] = 1;
                }
                self::CancelTrade($arTrade["FK_NEGOTIATION"], $ad["UID"], false, true);
            }

			if ((int)$ad["AUTOCONFIRM"] == 1) {
				AdManagment::BuyConfirm($id_ad, $sell_id);
			} else {
				// Mail an den K?ufer
			}

			return $sell_id;

		}




		return false;
	}

	static function BuyConfirm($id_ad, $id_ad_sold) {
		// Globale Variablen "importieren"
		global $db, $nar_systemsettings, $ab_path, $langval;

		$ad = $db->fetch1("
			SELECT
					a.*,
					a.ID_AD_MASTER as ID_AD,
					a.BESCHREIBUNG AS DSC,
					m.NAME AS MANUFACTURER,
					(SELECT slang.V1 FROM `string` slang WHERE slang.FK=a.FK_COUNTRY
		    			AND slang.BF_LANG='".$langval."' LIMIT 1) as LAND
				FROM
					`ad_master` a
				LEFT JOIN
					manufacturers m on a.FK_MAN=m.ID_MAN
				WHERE
					a.ID_AD_MASTER = ".$id_ad);
		$data_sell = $db->fetch1("SELECT * FROM `ad_sold` WHERE ID_AD_SOLD=".$id_ad_sold." AND CONFIRMED=0");

		if (empty($ad) || empty($data_sell)) return false;

		$id_ad_variant = ($data_sell["FK_AD_VARIANT"] > 0 ? (int)$data_sell["FK_AD_VARIANT"] : $ad["FK_AD_VARIANT"]);
		if ($id_ad_variant > 0) {
			$variants = AdVariantsManagement::getInstance($db);
			// Get current variant
			$data_variant_cur = $variants->getAdVariantDetailsById($id_ad_variant);
			$data_menge = $data_variant_cur["MENGE"];
			if ($data_menge <= $data_sell["MENGE"]) {
				// Diese Variante wird ausverkauft
				$data_menge = 0;
			} else {
				$data_menge -= $data_sell["MENGE"];
			}
			$variants->setAdVariantQuantityById($id_ad_variant, $data_menge);
			// Get overall article quantity
			$data_variant_sum = $db->fetch_atom("SELECT SUM(MENGE) FROM `ad_variant` WHERE FK_AD_MASTER=".$id_ad);
			if ($data_variant_sum == 0) {
				// Diese Anzeige wird ausverkauft!
				$db->querynow("UPDATE `ad_master` SET MENGE=0, STATUS=((STATUS|4&1)-1)
						WHERE ID_AD_MASTER=".$id_ad);
				$db->querynow("UPDATE `".$ad['AD_TABLE']."` SET MENGE=0, STATUS=((STATUS|4&1)-1)
						WHERE ID_".strtoupper($ad['AD_TABLE'])."=".$id_ad);
                self::CancelAllTrades($id_ad, 0, 1);
			} else if ($id_ad_variant == $ad["FK_AD_VARIANT"]) {
				// Menge der Standard-Variante hat sich verändert
				$db->querynow("UPDATE `ad_master` SET MENGE=".(int)$data_menge."
						WHERE ID_AD_MASTER=".$id_ad);
				$db->querynow("UPDATE `".$ad['AD_TABLE']."` SET MENGE=".(int)$data_menge."
						WHERE ID_".strtoupper($ad['AD_TABLE'])."=".$id_ad);
			}
		} else {
			$data_menge = $ad["MENGE"];
			if ($data_menge <= $data_sell["MENGE"]) {
				// Diese Anzeige wird ausverkauft!
				$data_menge = 0;
				$db->querynow("UPDATE `ad_master` SET MENGE=0, STATUS=((STATUS|4&1)-1)
						WHERE ID_AD_MASTER=".$id_ad);
				$db->querynow("UPDATE `".$ad['AD_TABLE']."` SET MENGE=0, STATUS=((STATUS|4&1)-1)
						WHERE ID_".strtoupper($ad['AD_TABLE'])."=".$id_ad);
                self::CancelAllTrades($id_ad, 0, 1);
			} else {
				// Menge hat sich verändert
				$data_menge -= $data_sell["MENGE"];
				$db->querynow("UPDATE `ad_master` SET MENGE=".(int)$data_menge."
						WHERE ID_AD_MASTER=".$id_ad);
				$db->querynow("UPDATE `".$ad['AD_TABLE']."` SET MENGE=".(int)$data_menge."
						WHERE ID_".strtoupper($ad['AD_TABLE'])."=".$id_ad);
			}
		}

		if ($data_sell["PROV"] > 0) {
			require_once($ab_path."sys/lib.billing.invoice.php");
			require_once($ab_path."sys/lib.billing.billableitem.php");
			$billingInvoiceManagement = BillingInvoiceManagement::getInstance($db);
			$billingBillableItemManagement = BillingBillableItemManagement::getInstance($db);

			$fk_sales_user = $db->fetch_atom("SELECT FK_USER_SALES FROM `user` WHERE ID_USER=".$ad["FK_USER"]);
			if (!$fk_sales_user) {
				$fk_sales_user = null;
			}
			$taxId = $nar_systemsettings['MARKTPLATZ']['TAX_DEFAULT'];
			$invoiceItems[] = array(
				'DESCRIPTION' => substr($ad['PRODUKTNAME'], 0, 64) . "\n" . 'Provision zu Trans.-Id. ' . $id_ad_sold,
				'PRICE' => $data_sell["PROV"],
				'QUANTITY' => $data_sell["MENGE"],
				'FK_TAX' => $taxId,
				'REF_TYPE' => BillingInvoiceItemManagement::REF_TYPE_PROVISION,
				'REF_FK' => $id_ad_sold
			);

			// Gutschein


			$billing_data = array(
				'FK_USER' 			=> $ad['FK_USER'],
				"FK_USER_SALES" => $fk_sales_user, 
				'__items' 			=> $invoiceItems
			);

			if($billingInvoiceManagement->shouldChargeAtOnceByUserId($ad['FK_USER'], true)) {
				$invoiceId = $billingInvoiceManagement->createInvoice($billing_data);
			} else {
				$invoiceId = $billingBillableItemManagement->createMultipleBillableItems($billing_data);
			}

			#echo ht(dump($book));
		}


		$query = "UPDATE `ad_sold` SET CONFIRMED=1 WHERE ID_AD_SOLD=".$id_ad_sold;
		$db->querynow($query);

		$ad = $db->fetch1("
		SELECT
			ad_sold.ID_AD_SOLD,
			ad_sold.MENGE AS MENGE_SOLD,
			ad_sold.FK_AD,
			ad_sold.PREIS,
			ad_sold.FK_USER AS UID_BUY,
			ad_master.ID_AD_MASTER,
			ad_master.AD_TABLE,
			ad_master.FK_USER AS UID,
			ad_master.PRODUKTNAME,
			user.NAME,
			'".$nar_systemsettings['SITE']['SITEURL']."' AS SITEURL
		FROM
			ad_sold
		LEFT JOIN
			ad_master ON ad_sold.FK_AD=ad_master.ID_AD_MASTER
		LEFT JOIN
			user ON ad_sold.FK_USER=user.ID_USER
		WHERE
			ad_sold.ID_AD_SOLD=".$id_ad_sold);
		$ar_info = $db->fetch1("
			SELECT
				*,
				AD_AGB AS AGB,
				AD_WIDERRUF AS WIDERRUF
			FROM
				".$ad['AD_TABLE']."
			WHERE
				ID_".strtoupper($ad['AD_TABLE'])."=".$ad['ID_AD_MASTER']);
		$ad = array_merge($ar_info, $ad);
		$userdata = $db->fetch1("
			SELECT
				NAME as USERNAME,
				FIRMA as FIRMA_S,
				VORNAME as VORNAME_S,
				NACHNAME AS NACHNAME_S,
				STRASSE as STRASSE_S,
				PLZ as PLZ_S,
				ORT as ORT_S,
				EMAIL as EMAIL_S,
				TEL as TEL_S,
				V1 as LAND_S,
				ZAHLUNG
			FROM
				`user`
			LEFT JOIN
				`usercontent` ON user.ID_USER=usercontent.FK_USER
			LEFT JOIN
				`string` ON string.S_TABLE='country' AND string.FK=user.FK_COUNTRY AND string.BF_LANG='".$langval."'
			WHERE
				ID_USER=".$ad['UID']);

                   	$db->querynow("
    		INSERT INTO `ad_log`
				(`ID_DATE`, `FK_USER`,`SOLD`)
			VALUES
    			('".date('Y-m-d')."',".$ad["UID"].",1)
    		ON DUPLICATE KEY UPDATE
    			SOLD=SOLD+1");

		if ($ad_deactivate) {
			AdManagment::Disable($id_ad, $ad["AD_TABLE"]);
		}
		return true;
	}

	static function BuyRevert($id_ad, $id_ad_sold) {
		return false;
	}

	static function BuyDecline($id_ad, $id_ad_sold, $reason, $disable = false) {
		// Globale Variablen "importieren"
		global $db, $uid, $nar_systemsettings, $langval;

		$ad = $db->fetch1("
			SELECT
					a.*,
					a.ID_AD_MASTER as ID_AD,
					a.BESCHREIBUNG AS DSC,
					m.NAME AS MANUFACTURER,
					(SELECT slang.V1 FROM `string` slang WHERE slang.FK=a.FK_COUNTRY
		    			AND slang.BF_LANG='".$langval."' LIMIT 1) as LAND
				FROM
					`ad_master` a
				LEFT JOIN
					manufacturers m on a.FK_MAN=m.ID_MAN
				WHERE
					a.ID_AD_MASTER = ".$id_ad);
		$data_sell = $db->fetch1("SELECT * FROM `ad_sold` WHERE ID_AD_SOLD=".$id_ad_sold." AND CONFIRMED=0");

		if (empty($ad) || empty($data_sell) || ($uid != $ad["FK_USER"])) return false;

		$query = "UPDATE `ad_sold` SET CONFIRMED=2, DECLINE_REASON='".mysql_escape_string($reason)."' ".
				"WHERE ID_AD_SOLD=".$id_ad_sold;
		$db->querynow($query);

		$ad = $db->fetch1("
		SELECT
			ad_sold.ID_AD_SOLD,
			ad_sold.MENGE AS MENGE_SOLD,
			ad_sold.FK_AD,
			ad_sold.PREIS,
			ad_sold.FK_USER AS UID_BUY,
			ad_master.FK_USER AS UID,
			ad_master.PRODUKTNAME,
			ad_master.AD_TABLE,
			user.NAME,
			'".$nar_systemsettings['SITE']['SITEURL']."' AS SITEURL
		FROM
			ad_sold
		LEFT JOIN
			ad_master ON ad_sold.FK_AD=ad_master.ID_AD_MASTER
		LEFT JOIN
			user ON ad_sold.FK_USER=user.ID_USER
		WHERE
			ad_sold.ID_AD_SOLD=".$id_ad_sold);
		if ($disable) {
			AdManagment::Disable($id_ad, $ad["AD_TABLE"]);
		}

		$userdata = $db->fetch1("
			SELECT
				NAME as USERNAME,
				FIRMA as FIRMA_S,
				VORNAME as VORNAME_S,
				NACHNAME AS NACHNAME_S,
				STRASSE as STRASSE_S,
				PLZ as PLZ_S,
				ORT as ORT_S,
				EMAIL as EMAIL_S,
				TEL as TEL_S,
				V1 as LAND_S,
				ZAHLUNG
			FROM
				`user`
			LEFT JOIN
				`usercontent` ON user.ID_USER=usercontent.FK_USER
			LEFT JOIN
				`string` ON string.S_TABLE='country' AND string.FK=user.FK_COUNTRY AND string.BF_LANG='".$langval."'
			WHERE
				ID_USER=".$ad['UID']);
		$ad = array_merge($ad, $userdata);
		$ad['SITENAME'] = $nar_systemsettings['SITE']['SITENAME'];

		return true;
	}

    static function CancelAllTrades($id_ad, $id_variant, $id_user_cancel = false, $isTimeout = false, $isSold = false) {
        global $db;
        $arNegotiations = array_keys(
            $db->fetch_nar("SELECT FK_NEGOTIATION FROM `trade` WHERE FK_AD=".(int)$id_ad." AND FK_AD_VARIANT=".(int)$id_variant.
                " GROUP BY FK_NEGOTIATION")
        );
        if (!empty($arNegotiations)) {
            return self::CancelTrade($arNegotiations, $id_user_cancel, $isTimeout, $isSold);
        } else {
            // Nothing to cancel
            return true;
        }
    }

    static function CancelTrade($id_negotiation, $id_user_cancel = false, $isTimeout = false, $isSold = false) {
        if (is_array($id_negotiation)) {
            $result = true;
            foreach ($id_negotiation as $index => $negotiationId) {
                if (!self::CancelTrade($negotiationId, $id_user_cancel, $isTimeout, $isSold)) {
                    $result = false;
                    break;
                }
            }
            return $result;
        }
        global $db, $uid, $langval, $ab_path, $nar_systemsettings;
        if ($id_user_cancel === false) {
            $id_user_cancel = $uid;
        }
        $arTradeFirst = $db->fetch1("SELECT * FROM `trade` WHERE ID_TRADE=".(int)$id_negotiation);
        $arTradeLast = $arTradeFirst;
        if (is_array($arTradeFirst)) {
            // Ad
            $arAd = $db->fetch1("SELECT * FROM `ad_master` WHERE ID_AD_MASTER=".$arTradeFirst["FK_AD"]);
            // Variant
            require_once $ab_path.'sys/lib.ad_variants.php';
            $variants = AdVariantsManagement::getInstance($db);
            $adVariant = $variants->getAdVariantDetailsById($arTradeFirst["FK_AD_VARIANT"]);
            $adVariantFields = $variants->getAdVariantTextById($arTradeFirst["FK_AD_VARIANT"]);
            $ar_variant_list = array();
            foreach ($adVariantFields as $index => $ar_current) {
                // TODO: Read variants in all languages and use the one prefered by the user
                $value = $db->fetch_atom("
										SELECT sl.V1 FROM `liste_values` t
										LEFT JOIN `string_liste_values` sl
											ON sl.S_TABLE='liste_values' AND sl.FK=t.ID_LISTE_VALUES
											AND sl.BF_LANG=if(t.BF_LANG_LISTE_VALUES & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_LISTE_VALUES+0.5)/log(2)))
										WHERE t.ID_LISTE_VALUES=".$ar_current["ID_LISTE_VALUES"]);
                if ($value !== false) {
                    $ar_variant_list[] = $value;
                } else {
                    $ar_variant_list[] = $ar_current["VALUE"];
                }
            }
            $arAd["VARIANT"] = (empty($ar_variant_list) ? "" : implode(", ", $ar_variant_list));
            // Get all bids
            $arTradesActive = $db->fetch_table("SELECT * FROM `trade`
                WHERE FK_NEGOTIATION=".(int)$id_negotiation." AND BID_STATUS='ACTIVE'
                ORDER BY ID_TRADE ASC");
            $hasActiveBids = !empty($arTradesActive);
            if (!$isSold) {
                // Send E-Mail(s) to inform bidders about the canceled trade
                foreach ($arTradesActive as $index => $arTrade) {
                    if (($arTrade["FK_USER_FROM"] != $arTrade["FK_USER_AD_OWNER"])
                        && ($arTrade["FK_USER_FROM"] != $id_user_cancel)) {
                        $arUser = $db->fetch1("SELECT NAME, EMAIL FROM `user` WHERE ID_USER=".$arTrade["FK_USER_FROM"]);
                        $mailTpl = "TRADE_CANCEL";
                        if ($isTimeout) {
                            $mailTpl = "TRADE_CANCEL_TIMEOUT";
                        } else if ($id_user_cancel <= 1) {
                            $mailTpl = "TRADE_CANCEL_SOLD";
                        }
                        $emailData = array_merge(array(
                            "BID_OWN"       => 1,
                            "BID_NAME"      => $db->fetch_atom("SELECT NAME FROM `user` WHERE ID_USER=".(int)$arTrade["FK_USER_FROM"]),
                            "BID_HOURS"     => $nar_systemsettings['MARKTPLATZ']['TRADE_MAX_HOURS'],
                            "CANCEL_NAME"   => $db->fetch_atom("SELECT NAME FROM `user` WHERE ID_USER=".(int)$id_user_cancel),
                            "FK_AD"         => $arTrade["FK_AD"],
                            "FK_AD_VARIANT" => $arTrade["FK_AD_VARIANT"]
                        ), $arAd, $arUser);
                        sendMailTemplateToUser(0, $arTrade["FK_USER_FROM"], $mailTpl, $emailData);
                    }
                    $arTradeLast = $arTrade;
                }
            }
            // Send E-Mail to owner if not canceled by himself and there are active bids
            if ($hasActiveBids && ($arTradeFirst["FK_USER_AD_OWNER"] != $id_user_cancel)) {
                $arUser = $db->fetch1("SELECT NAME, EMAIL FROM `user` WHERE ID_USER=".$arTradeFirst["FK_USER_AD_OWNER"]);
                $mailTpl = "TRADE_CANCEL_OWNER";
                if ($isTimeout) {
                    $mailTpl = "TRADE_CANCEL_OWNER_TIMEOUT";
                } else if ($id_user_cancel <= 1) {
                    $mailTpl = "TRADE_CANCEL_OWNER_SOLD";
                }
                $isOwn = ($arTradeLast["FK_USER_FROM"] == $arTradeLast["FK_USER_AD_OWNER"]);
                $emailData = array_merge(array(
                    "BID_OWN"       => ($isOwn ? 1 : 0),
                    "BID_NAME"      => $db->fetch_atom("SELECT NAME FROM `user` WHERE ID_USER=".
                        ( $isOwn ? (int)$arTradeLast["FK_USER_TO"] : (int)$arTradeLast["FK_USER_FROM"]) ),
                    "BID_HOURS"     => $nar_systemsettings['MARKTPLATZ']['TRADE_MAX_HOURS'],
                    "CANCEL_NAME"   => $db->fetch_atom("SELECT NAME FROM `user` WHERE ID_USER=".(int)$id_user_cancel),
                    "FK_AD"         => $arTradeFirst["FK_AD"],
                    "FK_AD_VARIANT" => $arTradeFirst["FK_AD_VARIANT"]
                ), $arAd, $arUser);
                sendMailTemplateToUser(0, $arTradeFirst["FK_USER_AD_OWNER"], $mailTpl, $emailData);
            }
            // Delete this negotiation
            $db->querynow("DELETE FROM `trade` WHERE FK_NEGOTIATION=".$id_negotiation);
            self::CacheTradeDetails($arTradeFirst["FK_AD"], $arTradeFirst["FK_AD_VARIANT"], $id_negotiation, $arTradeFirst["FK_USER_AD_OWNER"]);
            return true;
        } else {
            return false;
        }
    }

    static function CancelTradeSingle($id_trade, $id_user_cancel = false, $isTimeout = false, $isSold = false) {
        global $db, $uid, $langval, $ab_path, $nar_systemsettings;
        if ($id_user_cancel === false) {
            $id_user_cancel = $uid;
        }
        $arTrade = $db->fetch1("SELECT * FROM `trade` WHERE ID_TRADE=".(int)$id_trade);
        if (is_array($arTrade) && ($arTrade["BID_STATUS"] == "ACTIVE")) {
            // Ad
            $id_negotiation = $arTrade["FK_NEGOTIATION"];
            $arAd = $db->fetch1("SELECT * FROM `ad_master` WHERE ID_AD_MASTER=".$arTrade["FK_AD"]);
            // Variant
            require_once $ab_path.'sys/lib.ad_variants.php';
            $variants = AdVariantsManagement::getInstance($db);
            $adVariant = $variants->getAdVariantDetailsById($arTrade["FK_AD_VARIANT"]);
            $adVariantFields = $variants->getAdVariantTextById($arTrade["FK_AD_VARIANT"]);
            $ar_variant_list = array();
            foreach ($adVariantFields as $index => $ar_current) {
                // TODO: Read variants in all languages and use the one prefered by the user
                $value = $db->fetch_atom("
										SELECT sl.V1 FROM `liste_values` t
										LEFT JOIN `string_liste_values` sl
											ON sl.S_TABLE='liste_values' AND sl.FK=t.ID_LISTE_VALUES
											AND sl.BF_LANG=if(t.BF_LANG_LISTE_VALUES & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_LISTE_VALUES+0.5)/log(2)))
										WHERE t.ID_LISTE_VALUES=".$ar_current["ID_LISTE_VALUES"]);
                if ($value !== false) {
                    $ar_variant_list[] = $value;
                } else {
                    $ar_variant_list[] = $ar_current["VALUE"];
                }
            }
            $arAd["VARIANT"] = (empty($ar_variant_list) ? "" : implode(", ", $ar_variant_list));
            // Get all bids
            $numTradesActive = $db->fetch_atom("SELECT count(*) FROM `trade`
                WHERE FK_NEGOTIATION=".(int)$id_negotiation." AND BID_STATUS='ACTIVE'");
            $hasActiveBids = ($numTradesActive > 0 ? true : false);
            if (!$isSold) {
                // Send E-Mail(s) to inform bidders about the canceled trade
                if (($arTrade["FK_USER_FROM"] != $arTrade["FK_USER_AD_OWNER"])
                    && ($arTrade["FK_USER_FROM"] != $id_user_cancel)) {
                    $arAd = $db->fetch1("SELECT * FROM `ad_master` WHERE ID_AD_MASTER=".$arTrade["FK_AD"]);
                    $arUser = $db->fetch1("SELECT NAME, EMAIL FROM `user` WHERE ID_USER=".$arTrade["FK_USER_FROM"]);
                    $mailTpl = "TRADE_CANCEL";
                    if ($isTimeout) {
                        $mailTpl = "TRADE_CANCEL_TIMEOUT";
                    } else if ($id_user_cancel <= 1) {
                        $mailTpl = "TRADE_CANCEL_SOLD";
                    }
                    $emailData = array_merge(array(
                        "BID_OWN"       => 1,
                        "BID_NAME"      => $db->fetch_atom("SELECT NAME FROM `user` WHERE ID_USER=".(int)$arTrade["FK_USER_FROM"]),
                        "BID_HOURS"     => $nar_systemsettings['MARKTPLATZ']['TRADE_MAX_HOURS'],
                        "CANCEL_NAME"   => $db->fetch_atom("SELECT NAME FROM `user` WHERE ID_USER=".(int)$id_user_cancel),
                        "FK_AD"         => $arTrade["FK_AD"],
                        "FK_AD_VARIANT" => $arTrade["FK_AD_VARIANT"]
                    ), $arAd, $arUser);
                    sendMailTemplateToUser(0, $arTrade["FK_USER_FROM"], $mailTpl, $emailData);
                }
            }
            // Send E-Mail to owner if not canceled by himself and there are active bids
            if ($hasActiveBids && ($arTrade["FK_USER_AD_OWNER"] != $id_user_cancel)) {
                $arUser = $db->fetch1("SELECT NAME, EMAIL FROM `user` WHERE ID_USER=".$arTrade["FK_USER_AD_OWNER"]);
                $mailTpl = "TRADE_CANCEL_OWNER";
                if ($isTimeout) {
                    $mailTpl = "TRADE_CANCEL_OWNER_TIMEOUT";
                } else if ($id_user_cancel <= 1) {
                    $mailTpl = "TRADE_CANCEL_OWNER_SOLD";
                }
                $isOwn = ($arTrade["FK_USER_FROM"] == $arTrade["FK_USER_AD_OWNER"]);
                $emailData = array_merge(array(
                    "BID_OWN"       => ($arTrade["FK_USER_FROM"] == $arTrade["FK_USER_AD_OWNER"] ? 1 : 0),
                    "BID_NAME"      => $db->fetch_atom("SELECT NAME FROM `user` WHERE ID_USER=".
                        ( $isOwn ? (int)$arTrade["FK_USER_TO"] : (int)$arTrade["FK_USER_FROM"]) ),
                    "BID_HOURS"     => $nar_systemsettings['MARKTPLATZ']['TRADE_MAX_HOURS'],
                    "CANCEL_NAME"   => $db->fetch_atom("SELECT NAME FROM `user` WHERE ID_USER=".(int)$id_user_cancel),
                    "FK_AD"         => $arTrade["FK_AD"],
                    "FK_AD_VARIANT" => $arTrade["FK_AD_VARIANT"]
                ), $arAd, $arUser);
                sendMailTemplateToUser(0, $arTrade["FK_USER_AD_OWNER"], $mailTpl, $emailData);
            }
            if ($numTradesActive > 1) {
                // End this bid
                $db->querynow("UPDATE `trade` SET BID_STATUS='ENDED' WHERE ID_TRADE=".$id_trade);
            } else {
                // End this negotiation
                $db->querynow("DELETE FROM `trade` WHERE FK_NEGOTIATION=".$id_negotiation);
            }
            self::CacheTradeDetails($arTrade["FK_AD"], $arTrade["FK_AD_VARIANT"], $id_negotiation, $arTrade["FK_USER_AD_OWNER"]);
            return true;
        } else {
            return false;
        }
    }

    static function CacheTradeDetails($id_ad, $id_ad_variant, $id_negotiation, $id_user) {
        global $db;
        // Daten zusammengefasst wegspeichern
        $ar_bid_max = $db->fetch1("SELECT * FROM `trade` WHERE FK_AD=".(int)$id_ad." AND FK_AD_VARIANT=".(int)$id_ad_variant."
            AND FK_USER_FROM<>FK_USER_AD_OWNER AND BID_STATUS='ACTIVE' ORDER BY BID DESC");
        if (is_array($ar_bid_max)) {
            $ar_trade_ad = array(
                "FK_AD"				=> $id_ad,
                "FK_AD_VARIANT"		=> $id_ad_variant,
                "FK_NEGOTIATION"	=> $id_negotiation,
                "FK_USER"			=> $id_user,
                "MAXBID"			=> $ar_bid_max['BID'],
                "MAXBID_USER_ID"	=> $ar_bid_max['FK_USER_FROM'],
                "MYMAXBID"			=> $db->fetch_atom("
									SELECT
										MAX(BID)
									FROM `trade`
									WHERE FK_AD=".$id_ad." AND FK_USER_FROM=FK_USER_AD_OWNER
										AND BID_STATUS='ACTIVE' GROUP BY FK_AD"),
                "COUNTUSER"			=> $db->fetch_atom("
									SELECT
										count(*)
									FROM `trade`
									WHERE FK_AD=".$id_ad." AND BID_STATUS='ACTIVE'
										AND FK_USER_AD_OWNER<>FK_USER_FROM"),
                "LAST_BID_DATE"		=> $db->fetch_atom("
									SELECT
										STAMP_BID
									FROM `trade`
									WHERE FK_AD=".$id_ad." AND BID_STATUS='ACTIVE'
										AND FK_USER_AD_OWNER<>FK_USER_FROM
									ORDER BY STAMP_BID DESC")
            );
            $query = "INSERT INTO `trade_ad` (FK_AD, FK_AD_VARIANT, FK_USER, MAXBID, MAXBID_USER_ID, MYMAXBID, COUNTUSER, LAST_BID_DATE)
			VALUES ('".$ar_trade_ad['FK_AD']."','".$ar_trade_ad['FK_AD_VARIANT']."','".$ar_trade_ad['FK_USER']."',".
                "'".$ar_trade_ad['MAXBID']."','".$ar_trade_ad['MAXBID_USER_ID']."',".
                "'".$ar_trade_ad['MYMAXBID']."','".$ar_trade_ad['COUNTUSER']."',".
                "'".$ar_trade_ad['LAST_BID_DATE']."')
			ON DUPLICATE KEY UPDATE
				FK_AD='".$ar_trade_ad['FK_AD']."',FK_AD_VARIANT='".$ar_trade_ad['FK_AD_VARIANT']."',".
                "FK_USER='".$ar_trade_ad['FK_USER']."',MAXBID='".$ar_trade_ad['MAXBID']."',".
                "MAXBID_USER_ID='".$ar_trade_ad['MAXBID_USER_ID']."',MYMAXBID='".$ar_trade_ad['MYMAXBID']."',".
                "COUNTUSER='".$ar_trade_ad['COUNTUSER']."',LAST_BID_DATE='".$ar_trade_ad['LAST_BID_DATE']."'";
            $db->querynow($query);
        } else {
            $db->querynow("DELETE FROM `trade_ad` WHERE FK_AD=".(int)$id_ad." AND FK_AD_VARIANT=".(int)$id_ad_variant);
        }
    }

	/**
	 * Enables an Ad
	 *
	 * @param int     $id_ad
	 * @param string  $ad_table
	 */
	static function Enable($id_ad, $ad_table) {
		global $db, $langval, $ab_path, $nar_systemsettings, $uid;

		$ar_ad = $db->fetch1("SELECT * FROM `ad_master` WHERE ID_AD_MASTER=".$id_ad." AND DELETED=0");
		if ($ar_ad["ADMIN_STAT"] > 0) {
			eventlog("warning", "Anzeige aktivieren fehlgeschlagen. [id=" . $id_ad . "]", "Anzeige ist gesperrt!");
			return false;
		}
		if ($ar_ad["CONFIRMED"] != 1) {
			eventlog("warning", "Anzeige aktivieren fehlgeschlagen. [id=" . $id_ad . "]", "Administrator hat keine Freigabe erteilt!");
			return false;
		}
		if ($ar_ad["ID_AD_MASTER"] > 0) {
            // Get user roles
            $arUserRoles = array_keys($db->fetch_nar("SELECT FK_ROLE FROM `role2user` WHERE FK_USER=".$uid));
            // Check category access
            $hasAccess = $db->fetch_atom("SELECT count(*) FROM `role2kat`
                WHERE FK_ROLE IN (".implode(", ", $arUserRoles).") AND FK_KAT=".$ar_ad["FK_KAT"]." AND ALLOW_NEW_AD=1");
            if (!$hasAccess) {
                eventlog("warning", "Anzeige aktivieren fehlgeschlagen. [id=".$id_ad."]", "Kein Zugriff auf die gewählte Kategorie!");
                return false;
            }
			// Anzeige bekannt
			$id_packet_order = $db->fetch_atom("SELECT FK_PACKET_ORDER FROM `ad_master` WHERE ID_AD_MASTER=".$id_ad);
			if ($id_packet_order > 0) {
				require_once $ab_path."sys/packet_management.php";
				$packets = PacketManagement::getInstance($db);
				$order = $packets->order_get($id_packet_order);
				if (($order === null) || !$order->isActive()) {
					eventlog("warning", "Anzeige aktivieren fehlgeschlagen. [id=" . $id_ad . "]", "Anzeigenpaket ist nicht mehr aktiv!");
					return false;
				}
				$forceNew = !$order->isRecurring() && (!isset($ar_ad["STAMP_END"]) || $ar_ad['STAMP_END'] == '0000-00-00 00:00:00');
				$ar_packet_usage = $order->getPacketUsage($id_ad, $forceNew);
			} else {
				$image_count = $db->fetch_atom("SELECT count(*) FROM `ad_images` WHERE FK_AD=".$id_ad);
				$video_count = $db->fetch_atom("SELECT count(*) FROM `ad_video` WHERE FK_AD=".$id_ad);
				$upload_count = $db->fetch_atom("SELECT count(*) FROM `ad_upload` WHERE FK_AD=".$id_ad);
				$ar_packet_usage = array(
						"ads_available"			=> $nar_systemsettings["MARKTPLATZ"]["FREE_ADS"],
						"images_available"		=> $nar_systemsettings["MARKTPLATZ"]["FREE_IMAGES"] - $image_count,
						"videos_available"		=> $nar_systemsettings["MARKTPLATZ"]["FREE_VIDEOS"] - $video_count,
						"downloads_available"	=> $nar_systemsettings["MARKTPLATZ"]["FREE_UPLOADS"] - $upload_count
				);
			}
			/*
			 * Kontingent prüfen!
			 */
			if ($ar_packet_usage["ads_available"] < 0) {
				eventlog("warning", "Anzeige aktivieren fehlgeschlagen. [id=".$id_ad."]", "Nicht genug freie Anzeigen verfügbar!");
				return false;
			}
			if ($ar_packet_usage["images_available"] < 0) {
				eventlog("warning", "Anzeige aktivieren fehlgeschlagen. [id=".$id_ad."]", "Nicht genug freie Bilder verfügbar!");
				return false;
			}
			if ($ar_packet_usage["downloads_available"] < 0) {
				eventlog("warning", "Anzeige aktivieren fehlgeschlagen. [id=".$id_ad."]", "Nicht genug freie Downloads verfügbar!");
				return false;
			}
			if ($ar_packet_usage["videos_available"] < 0) {
				eventlog("warning", "Anzeige aktivieren fehlgeschlagen. [id=".$id_ad."]", "Nicht genug freie Videos verfügbar!");
				return false;
			}
			/*
			 * Verbrauchte Paketbestandteile berechnen
			 */
			if (($ar_packet_usage["ads_required"] + $ar_packet_usage["images_required"] + $ar_packet_usage["downloads_required"]) > 0) {
				// Neue Anzeigen/Bilder/Downloads vom Anzeigenpaket abziehen.
				if ($ar_packet_usage["ads_required"] > 0) {
					$id_type = PacketManagement::getType($ar_packet_usage["ads_type"]);
					foreach ($ar_packet_usage["ads_new"] as $index => $id_ad_new) {
						$order->itemAddContent("ad", $id_ad);
					}
				}
				if ($ar_packet_usage["images_required"] > 0) {
					$id_type = PacketManagement::getType($ar_packet_usage["images_type"]);
					foreach ($ar_packet_usage["images_new"] as $index => $id_image_new) {
						$order->itemAddContent("image", $id_image_new);
					}
				}
				if ($ar_packet_usage["videos_required"] > 0) {
					$id_type = PacketManagement::getType($ar_packet_usage["videos_type"]);
					foreach ($ar_packet_usage["videos_new"] as $index => $id_video_new) {
						$order->itemAddContent("video", $id_video_new);
					}
				}
				if ($ar_packet_usage["downloads_required"] > 0) {
					$id_type = PacketManagement::getType($ar_packet_usage["downloads_type"]);
					foreach ($ar_packet_usage["downloads_new"] as $index => $id_upload_new) {
						$order->itemAddContent("download", $id_upload_new);
					}
				}
			}

			if (($ar_ad["STATUS"] & 1) == 1) {
				// Anzeige bereits aktiv!
				eventlog("info", "Anzeige ist bereits online! [id=".$id_ad."]");
			} else {
				// Anzeige derzeit deaktiviert!
				/*
				 * Anzeige online schalten
				*/
				$runtime_days = 0;
				if ($ar_ad["LU_LAUFZEIT"] > 0) {
					$runtime_days = $db->fetch_atom("SELECT VALUE FROM lookup WHERE ID_LOOKUP=".$ar_ad["LU_LAUFZEIT"]);
				} else {
					eventlog("warning", "Anzeige aktivieren fehlgeschlagen. [id=".$id_ad."]", "Keine Laufzeit vorhanden!");
                    return false;
				}

				if (!isset($ar_ad["STAMP_END"]) || $ar_ad['STAMP_END'] == '0000-00-00 00:00:00') {
					### Anzeige wurde erstmals freigeschaltet
					eventlog("info", "Anzeige wird erstmals eingestellt... [id=".$id_ad."]");

					$db->querynow("
					INSERT INTO `usercontent`
						(`FK_USER`, `ADS_USED`)
					VALUES
						(".$ar_ad['FK_USER'].",1)
					ON DUPLICATE KEY UPDATE
						ADS_USED=ADS_USED+1");
					$db->querynow("
						UPDATE
							`".$ad_table."`
						SET
							STAMP_START = NOW(),
							STAMP_END = (NOW() + INTERVAL ".$runtime_days." DAY),
							STATUS = (STATUS | 3) - 2
						WHERE
							ID_".strtoupper($ad_table)."=".$id_ad);
					$db->querynow("
						UPDATE
							`ad_master`
						SET
							STAMP_START = NOW(),
							STAMP_END = (NOW() + INTERVAL ".$runtime_days." DAY),
							STATUS = (STATUS | 3) - 2
						WHERE
							ID_AD_MASTER=".$id_ad);
				} else {
					### Anzeige reaktiviert - restzeit berechnen
					$sleepedSeconds = $db->fetch_atom("
						SELECT
							TIMESTAMPDIFF(SECOND, STAMP_DEACTIVATE, NOW())
						FROM
							ad_master
						WHERE
							ID_AD_MASTER=".$id_ad);


					$res = $db->querynow("
						UPDATE
							`".$ad_table."`
						SET
							STATUS = (STATUS|3)-2,
							STAMP_END = DATE_ADD(STAMP_END, interval ".$sleepedSeconds." SECOND),
							STAMP_DEACTIVATE = NULL
						WHERE
							ID_".strtoupper($ad_table)."=".$id_ad.";");

					$res2 = $db->querynow("
						UPDATE
							`ad_master`
						SET
							STATUS = (STATUS|3)-2,
							STAMP_END = DATE_ADD(STAMP_END, interval ".$sleepedSeconds." SECOND),
							STAMP_DEACTIVATE = NULL
						WHERE
							ID_AD_MASTER=".$id_ad.";");
				}
			}
		}

		self::updateSearchDbForAd($id_ad, $ad_table, $ar_ad);

		$ad = $db->fetch1("SELECT
				a.*, k.V1
			FROM `" . $ad_table . "` a
				LEFT JOIN `string_kat` k ON k.FK=a.FK_KAT AND
						k.BF_LANG=if(k.BF_LANG & " . $langval . ", " . $langval . ", 128)
			WHERE a.ID_" . strtoupper($ad_table) . "=" . $id_ad . ";");


		/**
		 * Anzeigen-Agent
		 */
		$ar_ad = array_merge($ad, $ar_ad);

		require_once $ab_path."sys/lib.ad_agent.php";
		ad_agent::CheckAd($ar_ad);


		// Menge aktualisieren
		if((int)$ar_ad['MENGE'] <= 0) {
			$db->update("ad_master", array('ID_AD_MASTER' => $id_ad, 'MENGE' => 1));
			$db->update($ar_ad['AD_TABLE'], array('ID_'.strtoupper($ar_ad['AD_TABLE']) => $id_ad, 'MENGE' => 1));
		}

        /**
         * Erfolgreich eingestellt und aktuallisiert
         */
        $db->querynow("
            UPDATE
                ad_master
            SET
              CRON_DONE=1, CRON_STAT = NULL
            WHERE
                ID_AD_MASTER=".$ar_ad['ID_AD_MASTER']."
                AND `AD_TABLE`='".$ar_ad['AD_TABLE']."'");

		// Trigger event
		$eventParams = new Api_Entities_EventParamContainer(array("id" => $id_ad, "table" => $ad_table, "data" => $ar_ad), true);
		Api_TraderApiHandler::getInstance($db)->triggerEvent( Api_TraderApiEvents::MARKETPLACE_AD_ENABLE, $eventParams );		
		
		eventlog("info", "Anzeige aktivieren erfolgreich. [id=".$id_ad."]", var_export($ar_packet_usage, true));
		return true;
	}

    static function Unlock($id_ad, $ad_table) {
        global $db;
        $db->querynow("UPDATE `ad_master` SET CONFIRMED=1, CRON_DONE=0 WHERE ID_AD_MASTER=".$id_ad);
        return AdManagment::Enable($id_ad, $ad_table);
    }

    static function UnlockDecline($id_ad, $ad_table, $reason, $mail = true) {
        global $db;
        $reason = trim($reason);
        $db->querynow("UPDATE `ad_master`
            SET CONFIRMED=2, DECLINE_REASON=".(empty($reason) ? "NULL" : "'".mysql_real_escape_string($reason)."'")."
            WHERE ID_AD_MASTER=".$id_ad);
        // Disable ad if active
        self::Disable($id_ad, $ad_table);
        if ($mail) {
            // Notify user by email
            $arMailAd = $db->fetch1("
            SELECT
                a.*,
                (SELECT m.NAME FROM `manufacturers` m WHERE m.ID_MAN=a.FK_MAN) as MANUFACTURER
            FROM `ad_master` a
            WHERE a.ID_AD_MASTER=".(int)$id_ad);
            $arMailAd["REASON"] = (empty($reason) ? false : $reason);
            $arMailUser = $db->fetch1("SELECT * FROM `user` WHERE ID_USER=".$arMailAd["FK_USER"]);
            sendMailTemplateToUser(0, $arMailUser["ID_USER"], "MODERATE_AD_DECLINED", array_merge($arMailAd, $arMailUser));
        }
        return true;
    }

    static function UnlockDeclineUser($id_user, $reason) {
        global $db;
        $arAds = $db->fetch_nar("SELECT ID_AD_MASTER, AD_TABLE FROM `ad_master` WHERE FK_USER=".$id_user." AND CONFIRMED=0");
        foreach ($arAds as $id_article => $kat_table) {
            self::UnlockDecline($id_article, $kat_table, $reason, false);
        }
    }

	/**
	 * Extends an Ad's runtime
	 *
	 * @param int       $id_ad
	 * @param string    $ad_table
	 * @param int       $days_add	Count of days to add
	 * @param function  $callback	Function that will check if the user is allowed to extend the ad (true/false)
	 */
	static function ExtendRuntime($id_ad, $ad_table, $days_add, $callback = null) {
		global $db, $ab_path;

		$ad_data = $db->fetch1("SELECT * FROM `".$ad_table."` WHERE ID_".strtoupper($ad_table)."=".$id_ad.";");
		if (($ad_data["STATUS"] & 1) == 1) {
			// Ad is active
			if (($callback === null) || $callback($id_ad)) {
				$db->querynow("UPDATE `".$ad_table."` SET STAMP_END = DATE_ADD(STAMP_END, INTERVAL ".$days_add." DAY) WHERE ID_".strtoupper($ad_table)."=".$id_ad.";");
				$db->querynow("UPDATE `ad_master` SET STAMP_END = DATE_ADD(STAMP_END, INTERVAL ".$days_add." DAY) WHERE ID_AD_MASTER=".$id_ad.";");
				return true;
			}
		} else {
            $timeEnd = strtotime($ad_data["STAMP_END"]);
            $timeDisable = strtotime($ad_data["STAMP_DEACTIVATE"]);
            if (($timeDisable !== false) && ($timeDisable < $timeEnd)) {
                // Ad was disabled/paused
                $db->querynow("UPDATE `".$ad_table."` SET STAMP_END = DATE_ADD(STAMP_END, INTERVAL ".$days_add." DAY) WHERE ID_".strtoupper($ad_table)."=".$id_ad.";");
                $db->querynow("UPDATE `ad_master` SET STAMP_END = DATE_ADD(STAMP_END, INTERVAL ".$days_add." DAY) WHERE ID_AD_MASTER=".$id_ad.";");
            } else {
                // Ad was disabled by timeout
                $runtimeId = $db->fetch_atom("SELECT ID_LOOKUP FROM lookup WHERE VALUE=".$days_add." AND ART = 'LAUFZEIT' LIMIT 1");
                $db->update("ad_master", array(
                    'ID_AD_MASTER' => $id_ad,
                    'LU_LAUFZEIT' => $runtimeId,
                    'STAMP_END' => NULL,
                    'STAMP_DEACTIVATE' => NULL
                ));
                $db->update($ad_table, array(
                    'ID_'.strtoupper($ad_table) => $id_ad,
                    'LU_LAUFZEIT' => $runtimeId,
                    'STAMP_END' => NULL,
                    'STAMP_DEACTIVATE' => NULL
                ));
            }

			return self::Enable($id_ad, $ad_table);
		}

		return false;
	}

	/**
	 * Enables an Ad
	 *
	 * @param int     $id_ad
	 * @param string  $ad_table
	 * @param int     $ad_kat_target
	 */
	static function Recreate($id_ad, $ad_table, $ad_kat_target = 0, $ar_override = array()) {
		global $db, $ab_path;

		$adVariantsManagement = AdVariantsManagement::getInstance($db);

		$ad_table_target = $ad_table;
		$ad_data = $db->fetch1("SELECT * FROM `".$ad_table."` WHERE ID_".strtoupper($ad_table)."=".$id_ad.";");
		$ad_data = array_merge($ad_data, $ar_override);
		$images = $db->fetch_table("SELECT * FROM `ad_images` WHERE FK_AD=".$id_ad);
		$files = $db->fetch_table("SELECT * FROM `ad_upload` WHERE FK_AD=".$id_ad);
		$videos = $db->fetch_table("SELECT * FROM `ad_video` WHERE FK_AD=".$id_ad);
		$old_id_ad = $id_ad;
		$old_kat = $ad_data['FK_KAT'];

		if ($ad_kat_target > 0) {
			// In andere Kategorie verschieben
			$ad_table_target = $db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".$ad_kat_target);
			$ad_data["FK_KAT"] = $ad_kat_target;
		}

		// Delete ID
		unset($ad_data["ID_".strtoupper($ad_table)]);
		// Reset Status
		$ad_data["AD_TABLE"] = $ad_table_target;
		$ad_data["CRON_STAT"] = -1;
		$ad_data["CRON_DONE"] = 0;
		$ad_data["STATUS"] = 0;
		$ad_data["MENGE"] = ($ad_data["MENGE"] > 0 ? $ad_data["MENGE"] : 1);
		unset($ad_data["STAMP_END"]);
		unset($ad_data["STAMP_DEACTIVATE"]);
		
		$enable = false;
		
		// Trigger plugin event
		$paramAdCreate = new Api_Entities_EventParamContainer(array(
			"data"		=> $ad_data,
			"enable"	=> $enable,
			"import"	=> false,
			"recreate"	=> true
		));
		Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_AD_CREATE, $paramAdCreate);
		if ($paramAdCreate->isDirty()) {
			$ad_data = $paramAdCreate->getParam("data");
			$enable = $paramAdCreate->getParam("enable");
		}
		
		$id_ad = $db->update("ad_master", $ad_data);
		$a = $db->querynow("
      	INSERT INTO
      		".$ad_table_target."
      	SET
      		ID_".strtoupper($ad_table_target)."=".$id_ad);


		$ad_data["ID_".strtoupper($ad_table_target)] = $id_ad;
		$db->update($ad_table_target, $ad_data);

		if (!$id_ad) // ($id_ad = $db->update($ad_table, $ad_data))) {
		{
			return 0;
		}

		// Copy images
		if (!empty($images)) {
			$path = self::getAdCachePath($id_ad, true);

			foreach ($images as $index => $image) {
				$file_full = $path.'/'.basename($image["SRC"]);
				$file_thumb = $path.'/'.basename($image["SRC_THUMB"]);
				system('cp "'.$ab_path.$image["SRC"].'" "'.$file_full.'"');
				system('cp "'.$ab_path.$image["SRC_THUMB"].'" "'.$file_thumb.'"');

                $file_full_base = "/".str_replace($ab_path, "", $file_full);
                $file_thumb_base = "/".str_replace($ab_path, "", $file_thumb);

				// Add to database
				$db->querynow("INSERT INTO `ad_images` (FK_AD, SRC, SRC_THUMB, CUSTOM, IS_DEFAULT) VALUES ".
            "(".$id_ad.",'".mysql_escape_string($file_full_base)."',
            	'".mysql_escape_string($file_thumb_base)."', 1, ".$image["IS_DEFAULT"].")");
			}
		}

		// Copy files
		if (!empty($files)) {
			$path = self::getAdCachePath($id_ad, true);

			foreach ($files as $index => $file) {
				$file_full = $path.'/'.basename($file["SRC"]);
				system('cp "'.$file["SRC"].'" "'.$file_full.'"');
				// Add to database

				$db->querynow("INSERT INTO `ad_upload` (FK_AD, SRC, FILENAME, EXT) VALUES ".
            "(".$id_ad.",'".mysql_escape_string($file_full)."',
            	'".mysql_escape_string($file["FILENAME"])."', '".mysql_escape_string($file["EXT"])."')");
			}
		}

		// Copy videos
		if (!empty($videos)) {
			$path = self::getAdCachePath($id_ad, true);

			foreach ($videos as $index => $video) {
				// Add to database
				$db->querynow("INSERT INTO `ad_video` (FK_AD, CODE) VALUES ".
            "(".$id_ad.",'".mysql_escape_string($video["CODE"])."')");
			}
		}

		// Copy Payment Adapter
		$db->querynow($a = "INSERT INTO ad2payment_adapter (FK_AD, FK_PAYMENT_ADAPTER) SELECT '".$id_ad."', o.FK_PAYMENT_ADAPTER FROM ad2payment_adapter o WHERE o.FK_AD = '".$old_id_ad."' ");

		// Copy Varianten
		if($adVariantsManagement->isVariantCategory($old_kat) && $adVariantsManagement->isVariantCategory($ad_data["FK_KAT"])) {
			$adVariantsManagement->copyAdVariantTableToAd($old_id_ad, $id_ad);
		}

		require_once(dirname(__FILE__)."/lib.pub_kategorien.php");

		// $db->querynow("UPDATE `".$ad_table."` SET STATUS = (STATUS|2)-2 WHERE ID_".strtoupper($ad_table)."=".$id_ad.";");

		### SEARCH DB

		### languages
		$ar_lang = $db->fetch_table("
        SELECT
          ABBR, BITVAL
        FROM
          `lang`
        WHERE
          B_PUBLIC=1");
		
		// Trigger plugin event
		$paramAdCreate = new Api_Entities_EventParamContainer(array(
			"id" 		=> $id_ad,
			"data"		=> $ad_data,
			"enable"	=> $enable,
			"import"	=> false,
			"recreate"	=> true
		));
		Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_AD_CREATED, $paramAdCreate);
		if ($paramAdCreate->isDirty()) {
			$enable = $paramAdCreate->getParam("enable");
		}
		
		return $id_ad;
	}

    /**
     * Deletes an Ad
     *
     * @param int			$id_ad
     * @param string	$ad_table
     */
    static function Delete($id_ad, $ad_table) {
        global $db, $ab_path;

        $article_data = $db->fetch1("SELECT * FROM `".$ad_table."`
        WHERE ID_".strtoupper($ad_table)."=".$id_ad);
        $id_kat = $article_data["FK_KAT"];

        $article_date = strtotime($article_data["STAMP_START"]);
        $directory = self::getAdCachePath($id_ad, true);

        require_once(dirname(__FILE__)."/lib.pub_kategorien.php");

        // Kommentare löschen
        require_once $ab_path."sys/lib.comment.php";
        $cmNews = CommentManagement::getInstance($db, 'ad_master');
        $cmNews->deleteAllComments($id_ad);

        ### Artikel löschen!

        $db->querynow("DELETE FROM `ad_master` WHERE ID_AD_MASTER=".$id_ad);
        $db->querynow("DELETE FROM `ad_images` WHERE FK_AD=".$id_ad);
        $db->querynow("DELETE FROM `ad_upload` WHERE FK_AD=".$id_ad);
        $db->querynow("DELETE FROM `ad_search` WHERE FK_AD=".$id_ad);
        $db->querynow("DELETE FROM `ad_likes` WHERE FK_AD=".$id_ad);
        $db->querynow("DELETE FROM `ad_video` WHERE FK_AD=".$id_ad);
        $db->querynow("DELETE FROM `".$ad_table."` WHERE ID_".strtoupper($ad_table)."=".$id_ad);

        $db->querynow("DELETE FROM `ad_agent_temp` WHERE FK_ARTICLE=".$id_ad);
        $db->querynow("DELETE FROM `trade` WHERE FK_AD=".$id_ad);
        $db->querynow("DELETE FROM `trade_ad` WHERE FK_AD=".$id_ad);
        $db->querynow("DELETE FROM `verstoss` WHERE FK_AD=".$id_ad);
        $db->querynow("DELETE v, v2v FROM ad_variant v, ad_variant2liste_values v2v WHERE v.FK_AD_MASTER = '".(int)$id_ad."' AND v.ID_AD_VARIANT = v2v.FK_AD_VARIANT");
        $db->querynow("DELETE FROM `ad2payment_adapter` WHERE FK_AD=".$id_ad);

        system("rm -r ".$directory);
    }


    /**
     * Deletes an Ad as user (will not delete it from database / filesystem)
     *
     * @param int			$id_ad
     * @param string	$ad_table
     */
    static function DeleteByUser($id_ad, $id_user, $kat_table = null) {
        global $db;
        $ar_article = $db->fetch1("SELECT * FROM `ad_master` WHERE ID_AD_MASTER=".(int)$id_ad);
        if ($ar_article["FK_USER"] != $id_user) {
            return false;
        }
        if ($kat_table === null) {
        	$kat_table = $db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".mysql_real_escape_string($ar_article["FK_KAT"]));
		}
        self::Disable($id_ad, $kat_table);
        $db->querynow("UPDATE `ad_master` SET DELETED=1 WHERE ID_AD_MASTER=".(int)$id_ad);

		$db->querynow("DELETE FROM `ad_images` WHERE FK_AD=".$id_ad);
  		$db->querynow("DELETE FROM `ad_upload` WHERE FK_AD=".$id_ad);

		$directory = self::getAdCachePath($id_ad, true);
		system("rm -r ".$directory);

        return true;
    }

	/**
	 * Disables an Ad
	 *
	 * @param int     $id_ad
	 * @param string  $ad_table
	 */
	static function Disable($id_ad, $ad_table, $flags = 0) {
		global $db, $ab_path;

        $ar_ad = $db->fetch1("SELECT * FROM `ad_master` WHERE ID_AD_MASTER=".(int)$id_ad);
		$id_kat = $ar_ad["FK_KAT"];
		if ($id_kat > 0) {
			// Anzeige gefunden!
			$s_status = "(STATUS|".(3 | $flags).")-1";
			$db->querynow("UPDATE `".$ad_table."` SET STATUS = ".$s_status.", STAMP_DEACTIVATE=NOW() WHERE ID_".strtoupper($ad_table)."=".$id_ad.";");
			$db->querynow("UPDATE `ad_master` SET STATUS = ".$s_status.", STAMP_DEACTIVATE=NOW() WHERE ID_AD_MASTER=".$id_ad.";");
			$db->querynow("DELETE FROM `ad_search` WHERE FK_AD=".$id_ad);

			require_once(dirname(__FILE__)."/lib.pub_kategorien.php");

			### Update Abo
			$id_packet_order = $db->fetch_atom("SELECT FK_PACKET_ORDER FROM `ad_master` WHERE ID_AD_MASTER=".$id_ad);

			if (!empty($id_packet_order)) {
				require_once $ab_path."sys/packet_management.php";
				$packets = PacketManagement::getInstance($db);
				$order = $packets->order_get($id_packet_order);
				if (($order != null) && $order->isRecurring()) {
					eventlog("info", "Anzeige deaktiviert. [id=".$id_ad."]", "Anzeigenpaket: ".$id_packet_order);
					// Frei gewordene Paketbestandteile wieder zur Verfügung stellen
					$order->itemRemContent("ad", $id_ad);
                    $ar_images = $db->fetch_nar("SELECT ID_IMAGE, FK_AD FROM `ad_images` WHERE FK_AD=".$id_ad);
                    foreach ($ar_images as $id_image => $fk_ad) {
                        $order->itemRemContent("image", $id_image);
                    }
                    $ar_uploads = $db->fetch_nar("SELECT ID_AD_UPLOAD, FK_AD FROM `ad_upload` WHERE FK_AD=".$id_ad);
                    foreach ($ar_uploads as $id_upload => $fk_ad) {
                        $order->itemRemContent("download", $id_upload);
                    }
                    $ar_videos = $db->fetch_nar("SELECT ID_AD_VIDEO, FK_AD FROM `ad_video` WHERE FK_AD=".$id_ad);
                    foreach ($ar_videos as $id_video => $fk_ad) {
                        $order->itemRemContent("video", $id_video);
                    }
				}
			}
			
			// Trigger event
			$eventParams = new Api_Entities_EventParamContainer(array("id" => $id_ad, "table" => $ad_table, "data" => $ar_ad), true);
			Api_TraderApiHandler::getInstance($db)->triggerEvent( Api_TraderApiEvents::MARKETPLACE_AD_DISABLE, $eventParams );
		}
	}

	/**
	 * @param $id_ad
	 * @param $ad_table
	 * @param $db
	 * @param $ar_ad
	 * @param $langval
	 *
	 * @return mixed
	 */
	public static function updateSearchDbForAd($id_ad, $ad_table, $ar_ad, $deletePrevious = true) {
		global $db;

		### SEARCH DB
		### languages
		$ar_lang = $db->fetch_table("
			SELECT
			  ABBR, BITVAL
			FROM
			  `lang`
			WHERE
			  B_PUBLIC=1");
		for ($i = 0; $i < count($ar_lang); $i++) {
			$tmpLangval =  $ar_lang[$i]['BITVAL'];
			$search_text = self::getAdSearchText($id_ad, $ad_table, $tmpLangval);
			### Alte Einträge aus der Suchtabelle entfernen
			if ($deletePrevious) {
				$db->querynow("
					DELETE FROM
						`ad_search`
					WHERE
						FK_AD=" . $ar_ad["ID_AD_MASTER"] . " AND
						AD_TABLE='" . $ar_ad['AD_TABLE'] . "' AND
						LANG='" . $ar_lang[$i]['ABBR'] . "'");
			}
			### Neuen Eintrag hinzufügen
			$db->querynow("INSERT INTO `ad_search` (FK_AD, FK_USER, LANG, AD_TABLE, STEXT)
				VALUES (" . $id_ad . ", " . $ar_ad["FK_USER"] . ", '" . $ar_lang[$i]['ABBR'] . "', '" . $ad_table . "',
				'" . mysql_escape_string($search_text) . "')", false, false, true);
		}
		return true;
		#Api_TraderApiHandler::getInstance($db)->triggerEvent(Api_TraderApiEvents::MARKETPLACE_AD_SEARCHDB_UPDATE, array("id" => $id_ad, "data" => $ar_ad));
	}

	public static function escapeAdSearchText($text) {
		$text = preg_replace_callback("/(^|\s)([^\s]+\-[^\s]+)($|\s)/si", function($matches) {
			$textParts = explode("-", $matches[2]);
			return $matches[2]." ".implode(" ", $textParts);
		}, $text);
		return preg_replace("/[^\sa-z0-9_-]/si", "", str_replace(
			array('Ä', 'Ö', 'Ü', 'ä', 'ö', 'ü', 'ß', '-'), 
			array('Ae', 'Oe', 'Ue', 'ae', 'oe', 'ue', 'ss', '_'),
			$text
		));
	}
	
	public static function getAdSearchTextRaw($arAd, $langval, $fieldlist = null, $fieldkeys = null) {
		global $db;
		$kat_cache = new CategoriesCache();
		$ad_table = "artikel_master";
		### Ariane-Faden der Kategorien
		$category_path = $kat_cache->kats_read_path($arAd["FK_KAT"], $langval);
		$category_path_plain = array();
		foreach ($category_path as $index => $category) {
			$category_path_plain[] = $category["V1"];
			$ad_table = $category["KAT_TABLE"];
		}
		$arAd["CATEGORY_PATH"] = implode(" ", $category_path_plain);
		### Artikel-Felder
		if ($fieldlist === null) {
			$fieldlist = self::getLocalCache("ad_search_fields_" . $ad_table);
			if ($fieldlist === null) {
				$fieldlist = self::setLocalCache("ad_search_fields_" . $ad_table, $db->fetch_nar("
					SELECT
						F_NAME, FK_LISTE
					FROM
						`field_def`
					WHERE
						FK_TABLE_DEF=
					(SELECT ID_TABLE_DEF FROM `table_def` WHERE T_NAME='" . mysql_real_escape_string($ad_table) . "' LIMIT 1)"));
			}
		}
		$search_text = array();

		$ignoreKeys = array("AD_AGB", "AD_WIDERRUF", "IMPORT_IMAGES");
		foreach ($arAd as $key => $value) {
			if (!array_key_exists($key, $fieldlist) || in_array($key, $ignoreKeys)) {
				continue;
			}
			if ($key == "BESCHREIBUNG") {
				$value = strip_tags($value);
			}
			if (!is_numeric($value)) {
				$search_text[] = strtolower(self::escapeAdSearchText($value));
			} else if ($fieldlist[$key] > 0 && $value != "") {
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
						s.BF_LANG=if(l.BF_LANG_LISTE_VALUES & " . $langval . ", " . $langval . ", 1 << floor(log(l.BF_LANG_LISTE_VALUES+0.5)/log(2)))
					WHERE
						l.FK_LISTE=" . $fieldlist[$key] . " AND l.ID_LISTE_VALUES=" . $liste_value);

				$search_text[] = strtolower(self::escapeAdSearchText($value));
			}
		}
		### Lookups auflösen
		### Hersteller
		if ($arAd["FK_MAN"] > 0) {
			$search_text[] = strtolower(self::escapeAdSearchText($db->fetch_atom("
			SELECT
				NAME
			FROM
				`manufacturers`
			WHERE
				ID_MAN=" . (int)$arAd["FK_MAN"])));
		}
		### Country
		if ($arAd["FK_COUNTRY"] > 0) {
			$search_text[] = strtolower(self::escapeAdSearchText($db->fetch_atom("
				SELECT
					V1
				FROM
					`string`
				WHERE
					S_TABLE='country' AND BF_LANG=" . $langval . " AND
					FK=" . (int)$arAd["FK_COUNTRY"])));
		}
		return implode(" ", $search_text);
	}
	
	/**
	 * @param $id_ad
	 * @param $ad_table
	 * @param $db
	 * @param $ar_ad
	 * @param $langval
	 *
	 * @return mixed
	 */
	public static function getAdSearchText($id_ad, $ad_table, $langval, $fieldlist = null) {
		global $db;

		$ad = $db->fetch1("
			SELECT
				a.*
			FROM `" . $ad_table . "` a
			WHERE a.ID_" . strtoupper($ad_table) . "=" . $id_ad . ";");

		return self::getAdSearchTextRaw($ad, $langval, $fieldlist);
	}

    /**
     * Deaktiviert alle abgelaufenen Anzeigen eines Users
     * @static
     * @param $userId
     * @return bool
     */
    static function DisableAllOldAdsByUser($userId, $flags = 0) {
        global $db;

        $oldAds = $db->fetch_table("
            SELECT
                ID_AD_MASTER, FK_KAT, FK_USER, AD_TABLE, STAMP_END, PRODUKTNAME
            FROM `ad_master`
            WHERE
                FK_USER = '".mysql_real_escape_string($userId)."'
                AND (STAMP_END < NOW())
                AND ((STATUS & 3) = 1) AND (DELETED=0)
        ");

        foreach($oldAds as $key=>$oldAd) {
            self::Disable($oldAd['ID_AD_MASTER'], $oldAd['AD_TABLE'], $flags);
        }
        return true;
    }

    /**
     * Gibt den Cache Pfad einer Anzeige zur¸ck
     *
     * Der Cache Pfad liegt in /cache/marktplatz/[JAHR]/[MONAT]/[HASH|0,3]/[HASH|3,4]/[ID]
     *
     * @static
     * @param int       $id                 Artikel ID
     * @param boolean   $createIfNotExist   Erzeugt den Cache Pfad sofern er noch nciht existiert
     *
     * @return string|null Vollst?ndiger absoluter Pfad ohne abschlie?enden Slash
     */
    static function getAdCachePath($id, $createIfNotExist = true, $absoluteUrl = true) {
        global $db, $ab_path;

        $articleData = $db->fetch1("SELECT * FROM `ad_master` WHERE ID_AD_MASTER = ".mysql_real_escape_string($id));
        if($articleData != null) {


            $path = 'cache/marktplatz';

            $path .= '/anzeigen';
            if($createIfNotExist && !is_dir($ab_path.$path)) { self::createPath($ab_path.$path, true, $ab_path); }

            /*$path .= '/'.date("Y", $articleStampStart);
            if($createIfNotExist && !is_dir($path)) { self::createPath($path, true, $ab_path); }

            $path .= '/'.date("m", $articleStampStart);
            if($createIfNotExist && !is_dir($path)) { self::createPath($path, true, $ab_path); }*/

			$hash = md5($id);
            $hashElements = array(
                substr($hash, 0, 3),
                substr($hash, 3, 3),
                substr($hash, 6, 3)
            );

            foreach($hashElements as $key => $hashElement) {
                $path .= '/'.$hashElement;
                if($createIfNotExist && !is_dir($ab_path.$path)) { self::createPath($ab_path.$path, true, $ab_path); }
            }

            $path .= '/'.$id;
            if($createIfNotExist && !is_dir($ab_path.$path)) { self::createPath($ab_path.$path, true, $ab_path); }

			if($absoluteUrl) {
				return $ab_path.$path;
			} else {
				return $path;
			}
        } else {
            return null;
        }
    }

	static function getAdFields($article) {
		global $db, $langval;

		$result = array();
		$id_kat = $article['FK_KAT'];

		$fields = $db->fetch_table("SELECT
			f.ID_FIELD_DEF,
			f.FK_TABLE_DEF,
			f.F_TYP,
			f.FK_LISTE,
			f.F_NAME,
			f.IS_SPECIAL,
			f.FK_FIELD_GROUP,
			f.ID_FIELD_DEF,
			f.FK_TABLE_DEF,
			kf.B_NEEDED,
			sf.V1,
			sf.V2
		FROM `kat2field` kf
		  LEFT JOIN `field_def` f ON f.ID_FIELD_DEF = kf.FK_FIELD
		  LEFT JOIN
			field2group f2g ON f.ID_FIELD_DEF=f2g.FK_FIELD_DEF
		  LEFT JOIN `string_field_def` sf ON sf.S_TABLE='field_def' AND sf.FK=kf.FK_FIELD
				AND sf.BF_LANG=if(f.BF_LANG_FIELD_DEF & ".$langval.", ".$langval.", 1 << floor(log(f.BF_LANG_FIELD_DEF+0.5)/log(2)))
		WHERE kf.FK_KAT=".$id_kat." AND kf.B_ENABLED=1 AND f.B_ENABLED=1
		GROUP BY f.ID_FIELD_DEF
		ORDER BY
			f.FK_FIELD_GROUP ASC,
			f.F_ORDER ASC");

		global $article_data;
		$article_data = $article;

		foreach($fields as $key => $field) {
			callback_ad_add_fields($field);

			foreach($field as $fieldKey => $fieldValue) {
				$result[$field['F_NAME'].'_'.$fieldKey] = $fieldValue;
			}
		}

		return $result;
	}


    /**
     * Insert an article array into database including images etc. into database.
     */
    static function updateArticleFromArray($arArticle, $id_user = NULL, $enable = false, &$success = false) {
        global $db, $uid, $ab_path, $nar_systemsettings, $langval;
        $success = false;
        $id_article = (int)$arArticle["ID_AD_MASTER"];
        $arArticleCheck = $db->fetch1("SELECT FK_USER FROM `ad_master` WHERE ID_AD_MASTER=".$id_article);
        if ($id_user == NULL) {
            $id_user = $uid;
        }
        if ($id_user != $arArticleCheck["FK_USER"]) {
            return false;
        } else {
            $arArticle["FK_USER"] = $id_user;
        }
		$kat_table = $db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".$arArticle['FK_KAT']);

        // Herstellerdatenbank
        if ($nar_systemsettings["MARKTPLATZ"]["USE_PRODUCT_DB"] && !empty($arArticle["HERSTELLER"])) {
			$arArticle = self::updateManufacturerDatabaseForArticle($arArticle);
        }
        // Moderate ads?
		$arArticle['CRON_STAT'] = NULL;
		if ($nar_systemsettings["MARKTPLATZ"]["MODERATE_ADS"]) {
			$userIsAutoConfirmed = $db->fetch_atom("SELECT AUTOCONFIRM_ADS FROM `user` WHERE ID_USER=".$id_user);
			if ($userIsAutoConfirmed) {
				$arArticle["CONFIRMED"] = 1;
			} else {
				$arArticle["CONFIRMED"] = 0;
				$arArticle['CRON_DONE'] = 1;
			}
		} else {
			$arArticle["CONFIRMED"] = 1;
		}
		if (array_key_exists("JSON_ADDITIONAL", $arArticle)) {
			$arArticle["JSON_ADDITIONAL"] = json_encode($arArticle["JSON_ADDITIONAL"]);
		}
		
		// Trigger plugin event
		$paramAdCreate = new Api_Entities_EventParamContainer(array(
			"data"		=> $arArticle,
			"enable"	=> $enable
		));
		Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_AD_UPDATE, $paramAdCreate);
		if ($paramAdCreate->isDirty()) {
			$arArticle = $paramAdCreate->getParam("data");
			$enable = $paramAdCreate->getParam("enable");
		}
		
        // Save changes into master
        $db->update("ad_master", $arArticle);
        // Insert into article table
        $id_name = 'ID_'.strtoupper($kat_table);
        $db->querynow("INSERT INTO `".mysql_real_escape_string($kat_table)."` (".$id_name.") VALUES (".$id_article.")");
        $arArticle[$id_name] = $id_article;
        $db->update($kat_table, $arArticle);
        // Get packet for this ad (if there is one)
        require_once $ab_path."sys/packet_management.php";
        $packets = PacketManagement::getInstance($db);
        $packetOrder = false;
        if ($arArticle["FK_PACKET_ORDER"] > 0) {
            $packetOrder = $packets->order_get($arArticle["FK_PACKET_ORDER"]);
        }
        /**
         * Files (Images/Uploads)
         */
        $uploads_dir = AdManagment::getAdCachePath($id_article, true);
        // Add images
        $arArticleImages = $db->fetch_nar("SELECT ID_IMAGE, IS_DEFAULT FROM `ad_images` WHERE FK_AD=".$id_article);
        foreach ($arArticle['images'] as $index => $arImage) {
            if ($arImage['ID_IMAGE'] > 0) {
                $id_image = $arImage['ID_IMAGE'];
				$arImage['SER_META'] = serialize(array_key_exists('META', $arImage) ? $arImage['META'] : array());
				$db->querynow("UPDATE `ad_images` SET IS_DEFAULT=".(int)$arImage['IS_DEFAULT'].", SER_META='".mysql_real_escape_string($arImage['SER_META'])."' WHERE ID_IMAGE=".(int)$id_image);
                unset($arArticleImages[$id_image]);
            } else {
                $src = $uploads_dir."/".basename($arImage['TMP']);
                $src_thumb = $uploads_dir."/".basename($arImage['TMP_THUMB']);
                if (rename($arImage['TMP'], $src)
                    && rename($arImage['TMP_THUMB'], $src_thumb)) {
                    $arImage['FK_AD'] = $id_article;
                    $arImage['SRC'] = "/".str_replace($ab_path, "", $src);
                    $arImage['SRC_THUMB'] = "/".str_replace($ab_path, "", $src_thumb);
					$arImage['SER_META'] = serialize(array_key_exists('META', $arImage) ? $arImage['META'] : array());
                    $id_image = $db->update("ad_images", $arImage, true);
                    if (!$id_image) {
                        // Failed to insert image
                        return false;
                    }
                }
            }
			if (array_key_exists("VARIANTS", $arImage) && !empty($arImage["VARIANTS"])) {
				$db->querynow("DELETE FROM `ad_images_variants` WHERE ID_IMAGE=".$id_image);
				foreach ($arImage["VARIANTS"] as $variantFieldName => $variantValue) {
					if (!empty($variantValue)) {
						$variantFieldId = Ad_Marketplace::getFieldIdByName($arArticle["FK_TABLE_DEF"], $variantFieldName);
						$db->querynow($q="
							INSERT INTO `ad_images_variants` (ID_IMAGE, ID_FIELD_DEF, ID_LISTE_VALUE)
							VALUES (".$id_image.", ".$variantFieldId.", ".(int)$variantValue.")");
					}
				}
			}
        }
        // Remove deleted images
        foreach ($arArticleImages as $id_image => $imageIsDefault) {
            if ($packetOrder !== false) {
                $packetOrder->itemRemContent('image', $id_image);
            }
            $db->querynow("DELETE FROM `ad_images` WHERE ID_IMAGE=".$id_image);
        }

        // Add uploads
        $arArticleUploads = $db->fetch_nar("SELECT ID_AD_UPLOAD, EXT FROM `ad_upload` WHERE FK_AD=".$id_article);
        foreach ($arArticle['uploads'] as $index => $arUpload) {
            if ($arUpload['ID_AD_UPLOAD'] > 0) {
                $id_upload = $arUpload['ID_AD_UPLOAD'];
                unset($arArticleUploads[$id_upload]);
                // TODO Update IS_FREE
	            $db->update("ad_upload", $arUpload, true);
            } else {
                $src = $uploads_dir.'/'.$arUpload['FILENAME'].'_x_'.time().'_x_.'.$arUpload['EXT'];
                if (rename($arUpload['TMP'], $src)) {
                    $arUpload['FK_AD'] = $id_article;
                    $arUpload['SRC'] = $src;
                    $id_upload = $db->update("ad_upload", $arUpload, true);
                    if (!$id_upload) {
                        // Failed to insert image
                        return false;
                    }
                }
            }
        }
        // Remove deleted uploads
        foreach ($arArticleUploads as $id_upload => $uploadExt) {
            if ($packetOrder !== false) {
                $packetOrder->itemRemContent('download', $id_upload);
            }
            $db->querynow("DELETE FROM `ad_upload` WHERE ID_AD_UPLOAD=".$id_upload);
        }

        /**
         * Videos
         */
        $arArticleVideos = $db->fetch_nar("SELECT ID_AD_VIDEO, CODE FROM `ad_video` WHERE FK_AD=".$id_article);
        foreach ($arArticle['videos'] as $index => $arVideo) {
            if ($arVideo['ID_AD_VIDEO'] > 0) {
                $id_video = $arVideo['ID_AD_VIDEO'];
                unset($arArticleVideos[$id_video]);
            } else {
                $arVideo['FK_AD'] = $id_article;
                $id_video = $db->update("ad_video", $arVideo, true);
                if (!$id_video) {
                    // Failed to insert video
                    return false;
                }
            }
        }
        // Remove deleted uploads
        foreach ($arArticleVideos as $id_video => $videoCode) {
            if ($packetOrder !== false) {
                $packetOrder->itemRemContent('video', $id_video);
            }
            $db->querynow("DELETE FROM `ad_video` WHERE ID_AD_VIDEO=".$id_video);
        }

        /**
         * Payment methods
         */
        $adPaymentAdapterManagement = AdPaymentAdapterManagement::getInstance($db);
        $adPaymentAdapterManagement->updatePaymentAdapterForAd($id_article, $arArticle['paymentAdapters']);

        // Disable if the ad needs to be moderated
        if (($arArticle["CONFIRMED"] == 0) && (($arArticle["STATUS"]&3) == 1)) {
            self::Disable($id_article, $kat_table);
        }
		// Trigger plugin event
		$paramAdCreate = new Api_Entities_EventParamContainer(array(
			"id"		=> $id_article,
			"data"		=> $arArticle,
			"enable"	=> $enable
		));
		Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_AD_UPDATED, $paramAdCreate);
		if ($paramAdCreate->isDirty()) {
			$enable = $paramAdCreate->getParam("enable");
		}

        if ($enable) {
            if ($arArticle["CONFIRMED"] == 1) {
                $success = AdManagment::Enable($id_article, $kat_table);
            } else {
                $success = true;
            }
        } else {
            $success = true;
        }
        return $id_article;
    }


	static function generateSearchString($searchData, $resultListLimit = 0) {
		global $db, $s_lang;
		
		// Get category
		include_once "sys/lib.shop_kategorien.php";
		$kat = new TreeCategories("kat", 1);
		$id_kat_root = $kat->tree_get_parent();
		$id_kat = ($searchData["FK_KAT"] > 0 ? $searchData["FK_KAT"] : $id_kat_root);
		$row_kat = $kat->element_read($id_kat);
		if (($row_kat["RGT"] - $row_kat["LFT"]) == 3) {
			// Only a single subcategory, automatically select that one to ensure the proper article table is used
			// (Mainly intended for single-category marketplaces)
			$row_kat_childs = $kat->element_get_childs($id_kat);
			if (is_array($row_kat_childs) && !empty($row_kat_childs)) {
				$row_kat = $row_kat_childs[0];
				$searchData["FK_KAT"] = $id_kat = $row_kat["ID_KAT"];
			} 
		}
		if (empty($row_kat)) {
			return false;
		}
		
		// Get search query
		$searchQuery = Ad_Marketplace::getQueryByParams($searchData);
		
		// Generate search hash
		$arQueryConfig = $searchQuery->getConfigQuery();
		unset($arQueryConfig["limit"]);
		unset($arQueryConfig["offset"]);
		$searchHash = md5( serialize($arQueryConfig) );
		
		// Store query in database
		$lifetime = time()+(60*60*24);
		$ar_search = array(
			'QUERY'		=> $searchHash,
			'LIFETIME'	=> date("Y-m-d H:i:s", $lifetime),
			'S_STRING'	=> serialize($searchData),
			'S_WHERE'	=> "",
			'S_HAVING'	=> ""
		);
		$idSearch = false;
		$known = $db->fetch_atom("SELECT ID_SEARCHSTRING FROM `searchstring` WHERE QUERY='".mysql_real_escape_string($searchHash)."'");
		if ($known > 0) {
			$idSearch = $ar_search["ID_SEARCHSTRING"] = $known;
			$db->update("searchstring", $ar_search);
		} else {
			$idSearch = $db->update("searchstring", $ar_search);
		}
		
		// Get articles
		$searchResultList = array();
		if ( $resultListLimit > 0 ) {
			$searchQuery->addField("ID_AD");
			//Rest_MarketplaceAds::addQueryFieldsByTemplate($searchQuery, "marktplatz.row.htm");
			$searchQuery->setLimit($resultListLimit);
			$searchResultList = $searchQuery->fetchTable();
			$searchResultList = Api_Entities_MarketplaceArticle::toAssocList(
				Api_Entities_MarketplaceArticle::createMultipleFromMinimalArray($searchResultList)
			);
		}
		// Return result
		#die($searchQuery->getQueryString());
		return array_merge($ar_search, array(
			'ID_SEARCHSTRING'	=> $idSearch,
			'ID_KAT' 			=> $id_kat,
			'RESULT_COUNT' 		=> $searchQuery->fetchCount(),
			'RESULT_LIST'       => $searchResultList,
			'HASH' 				=> $searchHash,
			#'SQLQUERY' 			=> $searchQuery->getQueryString()
		));
	}

	/**
	 * @param $arArticle
	 * @param $db
	 * @param $kat_table
	 * @param $ab_path
	 *
	 * @return mixed
	 */
	public static function updateManufacturerDatabaseForArticle($arArticle) {
		global $db, $ab_path;

		require_once $ab_path . 'sys/lib.hdb.php';
		$manufacturerDatabaseManagement = ManufacturerDatabaseManagement::getInstance($db);

		$kat_table = $db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=".$arArticle['FK_KAT']);
		$idTableDef = $db->fetch_atom("SELECT ID_TABLE_DEF FROM `table_def` WHERE T_NAME='" . $kat_table."'");
		$id_man = $db->fetch_atom("SELECT ID_MAN FROM `manufacturers` WHERE NAME='" . mysql_escape_string($arArticle["HERSTELLER"]) . "'");

		if (!$id_man && !empty($arArticle["HERSTELLER"])) {
			$id_man = $manufacturerDatabaseManagement->updateManufacturerById(NULL, array(
				'NAME' => $arArticle["HERSTELLER"],
				'CONFIRMED' => 0
			));
		}
		if ($id_man > 0) {
			$arArticle["FK_MAN"] = $id_man;
		}



		// Produktdatenbank
		$insertNewProduct = true;
		if (empty($arArticle["FK_PRODUCT"])) {
			$searchProductName = "";
			if (!empty($arArticle["HERSTELLER"])) {
				$searchProductName = "FULL_PRODUKTNAME LIKE '".mysql_real_escape_string($arArticle["HERSTELLER"]." ".$arArticle['PRODUKTNAME'])."'";
	 		} else if (!$id_man) {
				$searchProductName = "FULL_PRODUKTNAME LIKE '".mysql_real_escape_string($arArticle['PRODUKTNAME'])."'";
			} else {
				$searchProductName = "PRODUKTNAME LIKE '".mysql_real_escape_string($arArticle['PRODUKTNAME'])."'";
			}
			$id_product = $db->fetch_atom("
				SELECT ID_HDB_TABLE_".mysql_real_escape_string(strtoupper($kat_table))."
				FROM `hdb_table_".mysql_real_escape_string($kat_table)."`
				WHERE 
					".($id_man > 0 ? "FK_MAN=".(int)$id_man." AND" : "")." 
					".$searchProductName);
			if ($id_product > 0) {
				$arArticle["FK_PRODUCT"] = $id_product;
			}
		}
		if (isset($arArticle["FK_PRODUCT"]) && (int)$arArticle["FK_PRODUCT"] > 0) {
			$insertNewProduct = false;
			$hdbProduct = $manufacturerDatabaseManagement->fetchProductById((int)$arArticle["FK_PRODUCT"], 'hdb_table_' . $kat_table);
			if ($hdbProduct != NULL) {
				$productUserSuggestionIdenticalWithProductData = TRUE;
				$enabledFields = array_flip($manufacturerDatabaseManagement->fetchProductTypeColumnsByTable('hdb_table_' . $kat_table));
				$compareArticle = array_intersect_key($arArticle, $hdbProduct, $enabledFields);
				$compareArticle['PRODUKTNAME'] = $arArticle['PRODUKTNAME'];
				$compareArticle['FK_MAN'] = $arArticle['FK_MAN'];

				$productUserSuggestion = array();
				unset($compareArticle['CONFIRMED']);
				unset($compareArticle['IMPORT_IMAGES']);

				foreach ($compareArticle as $suggestKey => $suggestValue) {
					if ($hdbProduct[$suggestKey] != $compareArticle[$suggestKey]) {
						$productUserSuggestion[$suggestKey] = $suggestValue;
						$productUserSuggestionIdenticalWithProductData = FALSE;
					}
				}

				if(empty($hdbProduct['IMPORT_IMAGES']) && is_array($arArticle['images']) && count($arArticle['images']) > 0) {
					$productSuggestImage = reset($arArticle['images']);

					if(!empty($productSuggestImage['TMP'])) {
						$productUserSuggestion['IMPORT_IMAGES'] = array($productSuggestImage['TMP']);
					} elseif(!empty($productSuggestImage['SRC'])) {
						$productUserSuggestion['IMPORT_IMAGES'] = array($productSuggestImage['SRC']);
					}
				}

				if (!$productUserSuggestionIdenticalWithProductData) {
					$manufacturerDatabaseManagement->suggestProductUserData($hdbProduct['ID_HDB_PRODUCT'], $hdbProduct['HDB_TABLE'], $productUserSuggestion);
				}

			} else {
				$insertNewProduct = true;
			}
		}

		if ($insertNewProduct) {
			$arArticle["FK_MAN"] = ($arArticle["FK_MAN"] > 0 ? (int)$arArticle["FK_MAN"] : 0);
			// new Product
			$enabledFields = array_flip($b = $manufacturerDatabaseManagement->fetchProductTypeColumnsByTable('hdb_table_' . $kat_table));

			$productData = $arArticle;
			$productData = array_intersect_key($productData, $enabledFields);

			if(is_array($arArticle['images']) && count($arArticle['images']) > 0) {
				$productSuggestImage = reset($arArticle['images']);

				if(!empty($productSuggestImage['TMP'])) {
					$productSuggestImagePath = $productSuggestImage['TMP'];
				} elseif(!empty($productSuggestImage['SRC'])) {
					$productSuggestImagePath = $productSuggestImage['SRC'];
				}


				$hdbUploadDirectory = $manufacturerDatabaseManagement->getManufacturerDatabaseUploadDirectory();
				$hdbRelativeUploadDirectory = $manufacturerDatabaseManagement->getManufacturerDatabaseUploadDirectory(false);

				if(isset($productSuggestImagePath) && $productSuggestImagePath != "") {
					if(strpos($productSuggestImagePath, '/') === 0 && !(strpos($productSuggestImagePath, '/cache') === 0 )) {
						$productSuggestImagePath = $productSuggestImagePath;
					} else {
						$productSuggestImagePath = $ab_path.$productSuggestImagePath;
					}

					$hdbUploadFile = 'IMAGE_'.time().'_'.pathinfo($productSuggestImagePath, PATHINFO_FILENAME).'.'.pathinfo($productSuggestImagePath, PATHINFO_EXTENSION);
					copy($productSuggestImagePath, $hdbUploadDirectory.$hdbUploadFile);
					$productData['IMPORT_IMAGES'] = array($hdbRelativeUploadDirectory.$hdbUploadFile);
				}

			}

			$productData['FK_TABLE_DEF'] = $idTableDef;
			$productData['CONFIRMED'] = 8;
			$productData['FK_KAT'] = $arArticle['FK_KAT'];
			$productData['FK_MAN'] = $arArticle['FK_MAN'];
			$productData['PRODUKTNAME'] = $arArticle['PRODUKTNAME'];
			$productId = $manufacturerDatabaseManagement->saveProduct(NULL, 'hdb_table_' . $kat_table, $productData);
			
			$arArticle['FK_PRODUCT'] = $productId;

			return $arArticle;
		}


		return $arArticle;
	}
}

function callback_ad_add_fields(&$row) {
	global $article_data, $db, $langval;
	if (isset($article_data[$row["F_NAME"]]) && (!$row["IS_SPECIAL"])) {
		$row["IS_SET"] = 1;
		$row["TYPE_".$row["F_TYP"]] = 1;
		if ($row["F_TYP"] == "LIST") {
			if ($article_data[$row["F_NAME"]] == 0)
			$row["IS_SET"] = 0;
			$row["VALUE"] = $db->fetch_atom("SELECT V1
            FROM liste_values l
              LEFT JOIN string_liste_values s ON
                s.FK=l.ID_LISTE_VALUES AND s.S_TABLE='liste_values' AND
                s.BF_LANG=if(l.BF_LANG_LISTE_VALUES & ".$langval.", ".$langval.", 1 << floor(log(l.BF_LANG_LISTE_VALUES+0.5)/log(2)))
          WHERE l.FK_LISTE=".$row["FK_LISTE"]." AND l.ID_LISTE_VALUES=".$article_data[$row["F_NAME"]]);
		} else if ($row["F_TYP"] == "MULTICHECKBOX") {
			$ar_values = explode("x", trim($article_data[$row["F_NAME"]], "x"));
			$ar_names = $db->fetch_nar(
					"SELECT sl.V1 FROM `liste_values` l
								LEFT JOIN `string_liste_values` sl ON sl.S_TABLE='liste_values' AND sl.FK=l.ID_LISTE_VALUES
					            	AND sl.BF_LANG=if(l.BF_LANG_LISTE_VALUES & " . $langval . ", " . $langval . ", 1 << floor(log(l.BF_LANG_LISTE_VALUES+0.5)/log(2)))
								WHERE l.ID_LISTE_VALUES IN (".mysql_real_escape_string(implode(", ", $ar_values)).")  ORDER BY l.ORDER ASC");
			$row["VALUE"] = implode(", ", array_keys($ar_names));
		} else {
			$row["VALUE"] = $article_data[$row["F_NAME"]];
		}
	}
}
?>
