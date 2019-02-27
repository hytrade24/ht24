<?php
/* ###VERSIONSBLOCKINLCUDE### */

/**
 *
 * @Version 1.0
 * @author Jens Niedling
 * @package Marketplace
 *
 * @uses ebiz_db
 */
require_once $ab_path.'sys/lib.ad_variants.php';

class ad_agent {
	static function CheckAd($ar_ad) {
		global $db, $ab_path;

		$fk_user = ($ar_ad["FK_USER"] > 0 ? (int)$ar_ad["FK_USER"] : 0);
		$fk_man = ($ar_ad["FK_MAN"] > 0 ? (int)$ar_ad["FK_MAN"] : 0);
		$query = "
      	SELECT
      		a.*, k1.KAT_TABLE
      	FROM
      		`ad_agent` a
      	LEFT JOIN
      		`kat` k1 ON k1.ID_KAT=SEARCH_KAT
      	LEFT JOIN
      		`kat` k2 ON k2.ID_KAT=" . $ar_ad["FK_KAT"]
				. "
      	WHERE
      	    ( a.CREATED_AT < a.LIFE_CYCLE_ENDS ) AND
      		((a.SEARCH_KAT IS NULL) OR (k2.LFT BETWEEN k1.LFT and k1.RGT)) AND
      		((a.SEARCH_USER IS NULL) OR (a.SEARCH_USER = ".$fk_user.")) AND
      		((a.SEARCH_MAN IS NULL) OR (a.SEARCH_MAN = ".$fk_man."))";
		$matches = $db->fetch_table($query);
		//@file_put_contents($ab_path."checkad.txt", $query);

		if (count($matches) > 0) {
			// Article matched an user's ad-agent settings
			foreach ($matches as $index => $ar_agent) {
				ad_agent::CheckAdEx($ar_ad, $ar_agent, $ar_agent["FK_USER"]);
			}
		}
	}

	static function CheckAdEx($ar_ad, $ar_agent, $id_user) {
		global $db;

		$ar_search = unserialize($ar_agent["SEARCH_ARRAY"]);
		$ar_fields = array();
		$id_table = $db->fetch_atom("SELECT ID_TABLE_DEF FROM `table_def` WHERE T_NAME='".mysql_escape_string($ar_ad["AD_TABLE"])."'");
		// Text suche
		if (!empty($ar_search['PRODUKTNAME'])) {
			if (($ar_ad['FK_MAN'] > 0) && empty($ar_ad["MANUFACTURER"])) {
				$ar_ad["MANUFACTURER"] = $db->fetch_atom("SELECT NAME FROM `manufacturers` WHERE ID_MAN=".(int)$ar_ad['FK_MAN']);
			}
			if (strpos(strtolower($ar_ad["MANUFACTURER"]." ".$ar_ad["PRODUKTNAME"]), strtolower($ar_search['PRODUKTNAME'])) === false) {
				return false;
			}
			unset($ar_search['PRODUKTNAME']);
		}
		// Standard Suchfelder
		if (is_array($ar_search['PREIS'])) {
			// Von / Bis
			if (array_key_exists("VON", $ar_search['PREIS'])) {
				$ar_search['PVON'] = $ar_search['PREIS']['VON'];
			}
			if (array_key_exists("BIS", $ar_search['PREIS'])) {
				$ar_search['PBIS'] = $ar_search['PREIS']['BIS'];
			}
			unset($ar_search['PREIS']);
		} else if (is_numeric($ar_search['PREIS'] = str_replace(",", ".", $ar_search['PREIS']))) {
			if ($ar_ad["PREIS"] > $ar_search['PREIS']) {
				return false;
			}
			unset($ar_search['PREIS']);
		}
		if (is_numeric($ar_search['PBIS'] = str_replace(",", ".", $ar_search['PBIS']))) {
			if ($ar_ad["PREIS"] > $ar_search['PBIS']) {
				return false;
			}
		}
		if (is_numeric($ar_search['PVON'] = str_replace(",", ".", $ar_search['PVON']))) {
			if ($ar_ad["PREIS"] < $ar_search['PVON']) {
				return false;
			}
		}
		if ($ar_search['FK_COUNTRY'] > 0) {
			if ($ar_search['FK_COUNTRY'] != $ar_ad['FK_COUNTRY']) {
				return false;
			}
		}
		if ($ar_search['LU_UMKREIS'] > 0 && $ar_search['LONGITUDE'] > 0 && $ar_search['LATITUDE'] > 0) {
			$radius = 6368;
			$rad_l1 = $ar_search['LONGITUDE'] / 180 * M_PI;
			$rad_b1 = $ar_search['LATITUDE'] / 180 * M_PI;
			$rad_l2 = $ar_ad['LONGITUDE'] / 180 * M_PI;
			$rad_b2 = $ar_ad['LATITUDE'] / 180 * M_PI;
			$range = $db->fetch_atom("select `value` from lookup where ID_LOOKUP =".$ar_search['LU_UMKREIS']);
			$distance = sqrt(abs(2 * (1 - cos($rad_l2)) * cos($rad_b1) * (sin($rad_l2)) * sin($rad_l1)
					+ cos($rad_l2) * cos($rad_l1) - sin($rad_b2) * sin($rad_b1)));
			if ($distance > $range) {
				return false;
			}
		}
		// Kategorieabhängige Suchfelder
		$res = $db->querynow("
			SELECT
				F_NAME,
				IS_SPECIAL,
				F_TYP
			FROM
				field_def
			WHERE
				FK_TABLE_DEF=".$id_table."
				AND (
						B_SEARCH IN(1,2)
					)");
		while($row = mysql_fetch_assoc($res['rsrc'])) {
			if((int)$row['IS_SPECIAL'] === 1) {
				continue;
			}
			$ar_fields[trim($row['F_NAME'])] = $row;
		}

		$adVariantManagement = AdVariantsManagement::getInstance($db);

		foreach($ar_search as $field => $value) {

			if(strstr($field, '_VON_d'))
			{
				date_implode($ar_search, $von = str_replace('_d', '', $field));
				date_implode($ar_search, $bis = str_replace('VON', 'BIS', $von));
				unset($ar_search[$von.'_d'], $ar_search[$von.'_m'], $ar_search[$von.'_y']);
				unset($ar_search[$bis.'_d'], $ar_search[$bis.'_m'], $ar_search[$bis.'_y']);

				$new = str_replace("_VON", '', $von);
				$ar_search[$new]['VON'] = $ar_search[$von];
				$ar_search[$new]['BIS'] = $ar_search[$bis];
			}
			if (is_array($value) && (count($value) == 2) && $ar_fields[$field]['F_TYP'] != 'VARIANT') {
				if (!empty($value["VON"]) || !empty($value["BIS"])) {
					$ar_search[$field] = array(
								"VON" => $value["VON"],
								"BIS" => $value["BIS"]
					);
				}
				if (!empty($value[0]) || !empty($value[1])) {
					$ar_search[$field] = array(
								"VON" => $value[0],
								"BIS" => $value[1]
					);
				}
			}
		}
		foreach ($ar_fields as $name => $ar_field) {
			if (isset($ar_search[$name]) && ($ar_search[$name] !== "")) {
				$value = $ar_search[$name];
				if (is_array($value)) {
					if ($ar_field['F_TYP'] == 'DATE') {
						if($value['VON'] != $value['BIS']) {
							$time_from = strtotime($value['VON']);
							$time_to = strtotime($value['BIS']);
							$time_ad = strtotime($ar_ad[$name]);
							if (($time_ad < $time_from) || ($time_ad > $time_to)) {
								//die("DATE/".$name);
								return false;
							}
						}
						if (strpos($ar_ad[$name], $value) === false) {
							return false;
						}
					} else if (($ar_field['F_TYP'] == 'INT') || ($ar_field['F_TYP'] == 'FLOAT')) {
						if($value['VON'] != $value['BIS']) {
							if (($ar_ad[$name] < $value['VON']) || ($ar_ad[$name] > $value['BIS'])) {
								//die("VB_NUM/".$name);
								return false;
							}
						}
					} else if ($ar_field['F_TYP'] == 'VARIANT') {

						$variantWhere = array();
						foreach($value as $valueKey => $listeValue) {
							$variantWhere[] = " av2lv.FK_LISTE_VALUES = '".mysql_real_escape_string($listeValue)."' ";
						}

						$existVariant = $db->fetch_atom($q = "
							SELECT
								COUNT(*) c
							FROM ad_variant av
							LEFT JOIN ad_variant2liste_values av2lv ON av2lv.FK_AD_VARIANT = av.ID_AD_VARIANT
							WHERE
								av2lv.F_NAME = '".mysql_real_escape_string($name)."'
								AND (".implode(" OR ", $variantWhere).")
								AND av.MENGE > 0
								AND av.STATUS = '".AdVariantsManagement::STATUS_ENABLED."'
								AND av.FK_AD_MASTER = '".(int)$ar_ad["ID_AD_MASTER"]."'
						");

						if($existVariant == 0) {
							return false;
						}
					} else if ($ar_field['F_TYP'] == 'MULTICHECKBOX') {

						$matching = false;
						$ad_selection = strtotime($ar_ad[$name]);
						foreach ($value as $search_selection_index => $search_selection) {
							if (strpos($ad_selection, $search_selection)) {
								$matching = true;
							}
						}
						if (!$matching) {
							return false;
						}

					}
				} else {
					if ($ar_field['F_TYP'] == 'TEXT') {
						if (strpos($ar_ad[$name], $value) === false) {
							//die("TEXT/".$name." $value != ".$ar_ad[$name]);
							return false;
						}
					} else {
						if ($value != $ar_ad[$name]) {
							//die("NUMBER/".$name." $value != ".$ar_ad[$name]);
							return false;
						}
					}
				}
			}
		}

		// Treffer!
		$db->update("ad_agent_temp",
			array("FK_AD_AGENT" => $ar_agent["ID_AD_AGENT"],
					"FK_USER" => $id_user,
					"FK_ARTICLE" => $ar_ad["ID_AD_MASTER"],
					"FK_KAT" => $ar_ad["FK_KAT"]));
		return true;
	}

	function GetSearchDescription($ar_search, $bf_lang = 128) {
		global $db;
		$id_kat = (int)$ar_search["FK_KAT"];
		$table = "ad_master";
		if ($id_kat > 1) {
			$table = $db->fetch_atom("SELECT KAT_TABLE FROM `kat` WHERE ID_KAT=" . $id_kat);
		} elseif ($id_kat = 1) {
			$table = 'artikel_master';
		}
		$ignoreFields = array("LATITUDE", "LONGITUDE");
		$text = array();
		foreach ($ar_search as $key => $value) {
			// Exceptions for displaying search parameters
			if (in_array($key, $ignoreFields) || (($key == "FK_KAT") && ($value == 1))) {
				continue;   // Do not display if searching in root category (meaning all categories)
			}
			// Format value
			if (!empty($value)) {
				$ar_field = $db->fetch1("
			      	SELECT
						f.*,
			      		sf.V1 as NAME
			      	FROM `table_def` t
			      		LEFT JOIN `field_def` f  ON f.FK_TABLE_DEF=t.ID_TABLE_DEF
			      		LEFT JOIN `kat2field` kf ON kf.FK_FIELD=f.ID_FIELD_DEF AND kf.FK_KAT=" . $id_kat . "
			      		LEFT JOIN `string_field_def` sf ON sf.S_TABLE='field_def' AND sf.FK=f.ID_FIELD_DEF
			            	AND sf.BF_LANG=if(f.BF_LANG_FIELD_DEF & " . $bf_lang . ", " . $bf_lang . ", 1 << floor(log(f.BF_LANG_FIELD_DEF+0.5)/log(2)))
					WHERE t.T_NAME='" . mysql_escape_string($table) . "'
						AND f.F_NAME='" . mysql_escape_string($key) . "'
						AND (f.IS_MASTER=1 OR kf.B_ENABLED=1)
					LIMIT 1");
				if (!empty($ar_field)) {
					$ar_values = array($value);
					if (is_array($value)) {
						$ar_values = $value;
					}
					$value_text = array();
					foreach ($ar_values as $index => $curValue) {
						if (empty($curValue)) {
							continue;
						}
						if (($ar_field["F_NAME"] == "FK_KAT") && ($curValue > 1)) {
							$value_text[] = $db->fetch_atom(
								"SELECT sk.V1 FROM `kat` k
								LEFT JOIN `string_kat` sk ON sk.S_TABLE='kat' AND sk.FK=k.ID_KAT
					            	AND sk.BF_LANG=if(k.BF_LANG_KAT & " . $bf_lang
								. ", " . $bf_lang
								. ", 1 << floor(log(k.BF_LANG_KAT+0.5)/log(2)))
								WHERE k.ID_KAT=" . (int)$curValue);
						} else if (($ar_field["F_NAME"] == "FK_COUNTRY") && ($curValue > 0)) {
							$value_text[] = $db->fetch_atom(
								"SELECT sc.V1 FROM `country` c
								LEFT JOIN `string` sc ON sc.S_TABLE='country' AND sc.FK=c.ID_COUNTRY
					            	AND sc.BF_LANG=if(c.BF_LANG & " . $bf_lang
								. ", " . $bf_lang
								. ", 1 << floor(log(c.BF_LANG+0.5)/log(2)))
								WHERE c.ID_COUNTRY=" . (int)$curValue);
						} else if (($ar_field["F_NAME"] == "FK_MAN") && ($curValue > 0)) {
							$value_text[] = $db->fetch_atom("SELECT NAME FROM `manufacturers` WHERE ID_MAN=" . (int)$curValue);
						} else if ($ar_field["F_TYP"] == "LIST" || $ar_field["F_TYP"] == "VARIANT") {
							$value_text[] = $db->fetch_atom(
									"SELECT sl.V1 FROM `liste_values` l
								LEFT JOIN `string_liste_values` sl ON sl.S_TABLE='liste_values' AND sl.FK=l.ID_LISTE_VALUES
					            	AND sl.BF_LANG=if(l.BF_LANG_LISTE_VALUES & "
									. $bf_lang . ", " . $bf_lang
									. ", 1 << floor(log(l.BF_LANG_LISTE_VALUES+0.5)/log(2)))
								WHERE l.ID_LISTE_VALUES=" . (int)$curValue);
						} else if ($ar_field["F_TYP"] == "CHECKBOX") {
							if ($curValue > 0) {
								$value_text[] = "Ja";
							} else {
								$value_text[] = "Nein";
							}
						} else if ($ar_field["F_TYP"] == "DATE") {
							$value_text[] = date("d.m.Y", strtotime($curValue));
						} else if ($ar_field["F_TYP"] == "MULTICHECKBOX") {
							$value_text[] = $db->fetch_atom(
									"SELECT sl.V1 FROM `liste_values` l
                                LEFT JOIN `string_liste_values` sl ON sl.S_TABLE='liste_values' AND sl.FK=l.ID_LISTE_VALUES
                                    AND sl.BF_LANG=if(l.BF_LANG_LISTE_VALUES & "
									. $bf_lang . ", " . $bf_lang
									. ", 1 << floor(log(l.BF_LANG_LISTE_VALUES+0.5)/log(2)))
                                            WHERE l.ID_LISTE_VALUES=" . (int)$curValue);
						} else if ($ar_field["F_TYP"] == "MULTICHECKBOX_AND") {
							$value_text[] = $db->fetch_atom(
									"SELECT sl.V1 FROM `liste_values` l
								LEFT JOIN `string_liste_values` sl ON sl.S_TABLE='liste_values' AND sl.FK=l.ID_LISTE_VALUES
					            	AND sl.BF_LANG=if(l.BF_LANG_LISTE_VALUES & "
									. $bf_lang . ", " . $bf_lang
									. ", 1 << floor(log(l.BF_LANG_LISTE_VALUES+0.5)/log(2)))
								WHERE l.ID_LISTE_VALUES=" . (int)$curValue);
						} else {
							$value_text[] = $curValue;
						}
					}
					if (!empty($value_text)) {
						$text[] = "<dt>" . htmlspecialchars($ar_field["NAME"]) . "</dt>" .
							"<dd>" . htmlspecialchars(implode(" - ", $value_text)) . "</dd>";
					}
				} else {
					if ($key == "AVAILABILITY") {
						// Get translated strings
						$query = "SELECT
							l.VALUE, s.V1
						FROM `lookup` l
						LEFT JOIN `string` s ON s.S_TABLE='lookup' AND s.FK=l.ID_LOOKUP
							AND s.BF_LANG=if(l.BF_LANG & " . $bf_lang . ", " . $bf_lang . ", 1 << floor(log(l.BF_LANG+0.5)/log(2)))
						WHERE l.art='AVAIL'";
						$arStrings = $db->fetch_nar($query);
						$text[] = "<dt>" . htmlspecialchars($arStrings['availability']) . "</dt>" .
							"<dd>" . htmlspecialchars($value['FROM']) . " - " . htmlspecialchars($value['TO']) . "</dd>";
					}
				}
			}
		}
		return "<dl class=\"dl-horizontal ad-agent-params\">" . implode("\n", $text)
		. "</dl>";
	}

}

?>