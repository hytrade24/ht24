<?php
/* ###VERSIONSBLOCKINLCUDE### */


class AdVariantsManagement {
	private static $db;
	private static $instance = null;

	const STATUS_ENABLED = 1;
	const STATUS_DISABLED = 0;

	/**
	 * Singleton
	 *
	 * @param ebiz_db $db
	 * @return AdVariantsManagement
	 */
	public static function getInstance(ebiz_db $db) {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		self::setDb($db);

		return self::$instance;
	}

    public function generateAdVariantTable($idAd, $variants, $defaultAdData) {
        $db = $this->getDb();
        $variantCombinations = $this->getVariantCombinationArray($variants);

        $oldVariantTable = $this->getVariantTable($idAd);
        $hashTable = array();
        $oldFields = array();

        foreach($oldVariantTable as $key => $oldVariant) {
            if (($defaultAdData["FK_AD_VARIANT"] > 0) && ($defaultAdData["FK_AD_VARIANT"] == $oldVariant["ID_AD_VARIANT"])) {
                $oldVariant["IS_DEFAULT"] = 1;
            } else if (!$defaultAdData["FK_AD_VARIANT"]) {
                $defaultAdData["FK_AD_VARIANT"] = $oldVariant["ID_AD_VARIANT"];
                $oldVariant["IS_DEFAULT"] = 1;
            } else {
                $oldVariant["IS_DEFAULT"] = 0;
            }
            $hashValue = array();
            foreach($oldVariant['FIELDS'] as $fieldKey => $field) {
                $hashValue[$field['F_NAME']] = $field['F_NAME'].':'.$field['FK_LISTE_VALUES'];
                if(!in_array($field['F_NAME'], $oldFields)) {
                    $oldFields[] = $field['F_NAME'];
                }
            }
            ksort($hashValue);
            $hashTable[md5(implode(';', $hashValue))] = $oldVariant;
        }


        $fieldNameToIdMapping = $db->fetch_nar("SELECT f.F_NAME, f.ID_FIELD_DEF FROM field_def f LEFT JOIN kat2field kf ON f.ID_FIELD_DEF = kf.FK_FIELD LEFT JOIN ad_master a ON a.FK_KAT = kf.FK_KAT WHERE a.ID_AD_MASTER=".$idAd."");

        $db->querynow("DELETE v, v2v FROM ad_variant v, ad_variant2liste_values v2v WHERE v.FK_AD_MASTER = '".(int)$idAd."' AND v.ID_AD_VARIANT = v2v.FK_AD_VARIANT");

        foreach($variantCombinations as $key => $variant) {
            $hashValue = array();
            foreach($variant as $fieldKey => $field) {
                if(in_array($field['F_NAME'], $oldFields)) {
                    $hashValue[$field['F_NAME']] = $field['F_NAME'].':'.$field['LIST_VALUE'];
                }
            }
            ksort($hashValue);
            $hash = md5(implode(';', $hashValue));

            if(array_key_exists($hash, $hashTable)) {
                $menge = $hashTable[$hash]['MENGE'];
                $preis = $hashTable[$hash]['PREIS'];
                $status = $hashTable[$hash]['STATUS'];
                $is_default = $hashTable[$hash]['IS_DEFAULT'];
            } else {
                $status = self::STATUS_ENABLED;
                $menge = $defaultAdData['MENGE']?$defaultAdData['MENGE']:1;
                $preis = $defaultAdData['PREIS']?$defaultAdData['PREIS']:0;
                $is_default = ($hashTable[$hash]['IS_DEFAULT'] ? 1 : 0);
            }

            $variantId = $db->update("ad_variant", array(
                'FK_AD_MASTER' => $idAd,
                'STATUS' => $status,
                'MENGE' => $menge,
                'PREIS' => $preis
            ));

            if ($is_default) {
                $db->querynow("UPDATE `ad_master` SET FK_AD_VARIANT=".(int)$variantId." WHERE ID_AD_MASTER=".(int)$idAd);
            }

            foreach($variant as $fieldKey => $field) {
                $db->update("ad_variant2liste_values", array(
                    'FK_AD_VARIANT' => $variantId,
                    'FK_FIELD_DEF' => $fieldNameToIdMapping[$field['F_NAME']],
                    'F_NAME' => $field['F_NAME'],
                    'FK_LISTE_VALUES' => $field['LIST_VALUE']
                ));
            }
        }
    }

    public function generateAdVariantTableFromArray($arAd, $arVariantsFields) {
        $db = $this->getDb();
        $variantCombinations = $this->getVariantCombinationArray($arVariantsFields);

        $idNext = 1;
        $hashTable = array();
        $oldFields = array();
        if (is_array($arAd['_VARIANTS'])) {
            foreach($arAd['_VARIANTS'] as $index => $oldVariant) {
                if ($oldVariant["ID_AD_VARIANT"] > 0) {
                    $oldVariant["id"] = $oldVariant["ID_AD_VARIANT"];
                    if (($arAd["FK_AD_VARIANT"] > 0) && ($arAd["FK_AD_VARIANT"] == $oldVariant["ID_AD_VARIANT"])) {
                        $oldVariant["IS_DEFAULT"] = 1;
                    } else {
                        $oldVariant["IS_DEFAULT"] = 0;
                    }
                } else {
                    $oldVariant["id"] = $idNext;
                    $oldVariant["IS_DEFAULT"] = 0;
                }
                if ($oldVariant["id"] >= $idNext) {
                    $idNext = $oldVariant["id"] + 1;
                }
                if (!$arAd["FK_AD_VARIANT"]) {
                    $arAd["FK_AD_VARIANT"] = $oldVariant["id"];
                    $oldVariant["IS_DEFAULT"] = 1;
                }
                $hashValue = array();
                foreach($oldVariant['FIELDS'] as $fieldKey => $field) {
                    $hashValue[$field['F_NAME']] = $field['F_NAME'].':'.$field['LIST_VALUE'];
                    if(!in_array($field['F_NAME'], $oldFields)) {
                        $oldFields[] = $field['F_NAME'];
                    }
                }
                ksort($hashValue);
                $hashTable[md5(implode(';', $hashValue))] = $oldVariant;
            }
        }

        $fieldNameToIdMapping = $db->fetch_nar("
            SELECT
                f.F_NAME, f.ID_FIELD_DEF
            FROM field_def f
            LEFT JOIN kat2field kf ON f.ID_FIELD_DEF = kf.FK_FIELD
            WHERE kf.FK_KAT=".(int)$arAd["FK_KAT"]);

        $arVariantsNew = array();
        foreach($variantCombinations as $variantIndex => $variant) {
            $hashValue = array();
            foreach($variant as $fieldKey => $field) {
                if(in_array($field['F_NAME'], $oldFields)) {
                    $hashValue[$field['F_NAME']] = $field['F_NAME'].':'.$field['LIST_VALUE'];
                }
            }
            ksort($hashValue);
            $hash = md5(implode(';', $hashValue));

            $arVariantNew = array(
                'FK_AD_MASTER'  => 0,
                'STATUS'        => self::STATUS_ENABLED,
                'MENGE'         => ($arAd['MENGE'] ? $arAd['MENGE'] : 1),
                'PREIS'         => ($arAd['PREIS'] ? $arAd['PREIS'] : 0),
                'IS_DEFAULT'    => 0,
                'FIELDS'        => $variant
            );
            if(array_key_exists($hash, $hashTable)) {
                $arVariantNew['id'] = $hashTable[$hash]['id'];
                $arVariantNew['ID_AD_VARIANT'] = $hashTable[$hash]['ID_AD_VARIANT'];
                $arVariantNew['MENGE'] = $hashTable[$hash]['MENGE'];
                $arVariantNew['PREIS'] = $hashTable[$hash]['PREIS'];
                $arVariantNew['STATUS'] = $hashTable[$hash]['STATUS'];
                $arVariantNew['IS_DEFAULT'] = $hashTable[$hash]['IS_DEFAULT'];
                $arVariantNew['FIELDS'] = $variant;
                // Update
                $arVariantsNew[ $arVariantNew['id'] ] = $arVariantNew;
            } else {
                $arVariantsNew[$idNext++] = $arVariantNew;
            }
        }

        return $arVariantsNew;
    }

    public function getVariantTable($idAd) {
        global $langval;

        $db = $this->getDb();
        $result = array();

        $id_ad_variant_default = (int)$db->fetch_atom("SELECT FK_AD_VARIANT FROM `ad_master` WHERE ID_AD_MASTER=".(int)$idAd);
        $table = $db->fetch_table($q = "
			SELECT
				v.*,
				v2v.FK_FIELD_DEF,
				v2v.F_NAME,
				v2v.FK_LISTE_VALUES,
				f.FK_LISTE,
				f.FK_TABLE_DEF,
				s.V1 AS FIELD_V1,
				slv.V1 AS LISTE_VALUE_V1

			FROM ad_variant v
			JOIN ad_variant2liste_values v2v ON v2v.FK_AD_VARIANT = v.ID_AD_VARIANT
			JOIN `field_def` f ON v2v.FK_FIELD_DEF=f.ID_FIELD_DEF
			JOIN `string_field_def` s ON s.S_TABLE='field_def' AND s.FK=f.ID_FIELD_DEF AND s.BF_LANG=if(f.BF_LANG_FIELD_DEF & ".$langval.", ".$langval.", 1 << floor(log(f.BF_LANG_FIELD_DEF+0.5)/log(2)))
			JOIN `liste_values` lv ON v2v.FK_LISTE_VALUES=lv.ID_LISTE_VALUES
			JOIN `string_liste_values` slv ON slv.S_TABLE='liste_values' AND slv.FK=lv.ID_LISTE_VALUES AND slv.BF_LANG=if(lv.BF_LANG_LISTE_VALUES & ".$langval.", ".$langval.", 1 << floor(log(lv.BF_LANG_LISTE_VALUES+0.5)/log(2)))
			WHERE
				v.FK_AD_MASTER = '".(int)$idAd."'
		");

        foreach($table as $key => $value) {
            if(!array_key_exists($value['ID_AD_VARIANT'], $result)) {
                $value["IS_DEFAULT"] = ($value['ID_AD_VARIANT'] == $id_ad_variant_default ? 1 : 0);
                $result[$value['ID_AD_VARIANT']] = $value;
                unset($result[$value['ID_AD_VARIANT']]['FK_FIELD_DEF']);
                unset($result[$value['ID_AD_VARIANT']]['F_NAME']);
                unset($result[$value['ID_AD_VARIANT']]['FK_LISTE_VALUES']);
                unset($result[$value['ID_AD_VARIANT']]['FK_LISTE']);
                unset($result[$value['ID_AD_VARIANT']]['FK_TABLE_DEF']);
                unset($result[$value['ID_AD_VARIANT']]['FIELD_V1']);
                unset($result[$value['ID_AD_VARIANT']]['LISTE_VALUE_V1']);
            }

            $result[$value['ID_AD_VARIANT']]['FIELDS'][] = array(
                'FK_FIELD_DEF' => $value['FK_FIELD_DEF'],
                'F_NAME' => $value['F_NAME'],
                'FK_LISTE_VALUES' => $value['FK_LISTE_VALUES'],
                'FK_TABLE_DEF' => $value['FK_TABLE_DEF'],
                'FK_LISTE' => $value['FK_LISTE'],
                'FIELD_V1' => $value['FIELD_V1'],
                'LISTE_VALUE_V1' => $value['LISTE_VALUE_V1']
            );
        }

        return $result;
    }

    public function getVariantTableFromArray($arAd) {
        global $langval;

        $db = $this->getDb();
        $result = array();

        $id_ad_variant_default = (int)$arAd["FK_AD_VARIANT"];
        $table = $db->fetch_table($q = "
			SELECT
				v.*,
				v2v.FK_FIELD_DEF,
				v2v.F_NAME,
				v2v.FK_LISTE_VALUES,
				f.FK_LISTE,
				f.FK_TABLE_DEF,
				s.V1 AS FIELD_V1,
				slv.V1 AS LISTE_VALUE_V1

			FROM ad_variant v
			JOIN ad_variant2liste_values v2v ON v2v.FK_AD_VARIANT = v.ID_AD_VARIANT
			JOIN `field_def` f ON v2v.FK_FIELD_DEF=f.ID_FIELD_DEF
			JOIN `string_field_def` s ON s.S_TABLE='field_def' AND s.FK=f.ID_FIELD_DEF AND s.BF_LANG=if(f.BF_LANG_FIELD_DEF & ".$langval.", ".$langval.", 1 << floor(log(f.BF_LANG_FIELD_DEF+0.5)/log(2)))
			JOIN `liste_values` lv ON v2v.FK_LISTE_VALUES=lv.ID_LISTE_VALUES
			JOIN `string_liste_values` slv ON slv.S_TABLE='liste_values' AND slv.FK=lv.ID_LISTE_VALUES AND slv.BF_LANG=if(lv.BF_LANG_LISTE_VALUES & ".$langval.", ".$langval.", 1 << floor(log(lv.BF_LANG_LISTE_VALUES+0.5)/log(2)))
			WHERE
				v.FK_AD_MASTER = '".(int)$idAd."'
		");

        foreach($table as $key => $value) {
            if(!array_key_exists($value['ID_AD_VARIANT'], $result)) {
                $value["IS_DEFAULT"] = ($value['ID_AD_VARIANT'] == $id_ad_variant_default ? 1 : 0);
                $result[$value['ID_AD_VARIANT']] = $value;
                unset($result[$value['ID_AD_VARIANT']]['FK_FIELD_DEF']);
                unset($result[$value['ID_AD_VARIANT']]['F_NAME']);
                unset($result[$value['ID_AD_VARIANT']]['FK_LISTE_VALUES']);
                unset($result[$value['ID_AD_VARIANT']]['FK_LISTE']);
                unset($result[$value['ID_AD_VARIANT']]['FK_TABLE_DEF']);
                unset($result[$value['ID_AD_VARIANT']]['FIELD_V1']);
                unset($result[$value['ID_AD_VARIANT']]['LISTE_VALUE_V1']);
            }

            $result[$value['ID_AD_VARIANT']]['FIELDS'][] = array(
                'FK_FIELD_DEF' => $value['FK_FIELD_DEF'],
                'F_NAME' => $value['F_NAME'],
                'FK_LISTE_VALUES' => $value['FK_LISTE_VALUES'],
                'FK_TABLE_DEF' => $value['FK_TABLE_DEF'],
                'FK_LISTE' => $value['FK_LISTE'],
                'FIELD_V1' => $value['FIELD_V1'],
                'LISTE_VALUE_V1' => $value['LISTE_VALUE_V1']
            );
        }

        return $result;
    }

	public function copyAdVariantTableToAd($fromAdId, $toAdId) {
		$db = $this->getDb();
		$adVariants = $db->fetch_table("SELECT * FROM ad_variant WHERE FK_AD_MASTER = '".(int)$fromAdId."'");
		foreach($adVariants as $key => $adVariant) {
			$newAdVariantId = $db->update('ad_variant', array(
				'FK_AD_MASTER' => $toAdId,
				'STATUS' => $adVariant['STATUS'],
				'PREIS' => $adVariant['PREIS'],
				'MENGE' => $adVariant['MENGE']
			));

			$db->querynow("
				INSERT INTO
					ad_variant2liste_values (FK_AD_VARIANT, FK_FIELD_DEF, F_NAME, FK_LISTE_VALUES)
				SELECT
					'".$newAdVariantId."', avv.FK_FIELD_DEF, avv.F_NAME, avv.FK_LISTE_VALUES
				FROM ad_variant2liste_values avv
				WHERE
					avv.FK_AD_VARIANT = '".$adVariant['ID_AD_VARIANT']."'
			");
		}
	}

	public function updateVariant($variantId, $data) {
		$db = $this->getDb();

		$db->update('ad_variant', array(
			'ID_AD_VARIANT' => (int)$variantId,
			'MENGE' => 	(int)$data['MENGE'],
			'PREIS' => (float)str_replace(",", ".", $data['PREIS']),
			'STATUS' => ((bool)$data['STATUS'])?(self::STATUS_ENABLED):(self::STATUS_DISABLED)
		));
	}

	public function existVariant($idAd, $variantId) {
		return ($this->getDb()->fetch_atom("SELECT COUNT(*) as c FROM ad_variant WHERE ID_AD_VARIANT = '".(int)$variantId."' AND FK_AD_MASTER = '".(int)$idAd."' ") > 0);
	}

	public function isVariantCategory($idKat) {
		return ($this->getDb()->fetch_atom("SELECT COUNT(*) as c FROM kat2field k2f LEFT JOIN field_def f ON f.ID_FIELD_DEF = k2f.FK_FIELD WHERE k2f.FK_KAT = '".(int)$idKat."' AND f.F_TYP = 'VARIANT' ") > 0);
	}

	public function getVariantCombinationArray($felder) {
		$ar_kombinationen = array();
		$anzahl = 1;
		foreach ($felder as $feld => $ar_werte) {
			$anzahl *= count($ar_werte);
		}
		for ($i = 0; $i < $anzahl; $i++) {
			$ar_kombinationen[$i] = array();
		}
		while (!empty($felder)) {
			$fieldName = array_shift(array_keys($felder));
			$ar_werte = array_shift($felder);
			$anzahl = (count($ar_werte) == 0 ? 0 : $anzahl / count($ar_werte));
			$i = 0;
			while ($i < count($ar_kombinationen)) {
				$index = (floor($i / $anzahl) % count($ar_werte));
				$ar_kombinationen[$i][] = array('F_NAME' => $fieldName, 'LIST_VALUE' =>  $ar_werte[$index]);
				$i++;
			}
		}

		return $ar_kombinationen;
	}



	/**
	 * @param int $adId		Index of the ad
	 * @return Array		Array of all variant fields from the given ad (only if at least one variant is available)
	 */
	public function getAdVariantFieldsById($adId, $lang = null) {
		global $langval;
		if ($lang == null) {
			$lang = $langval;
		}
		global $langval;
		$ar_fields = self::$db->fetch_table("SELECT s.*, f.*
				FROM `ad_variant2liste_values` av2lv
				LEFT JOIN `ad_variant` av
					ON av.ID_AD_VARIANT=av2lv.FK_AD_VARIANT
				LEFT JOIN `field_def` f
					ON av2lv.FK_FIELD_DEF=f.ID_FIELD_DEF
				LEFT JOIN `string_field_def` s
					ON s.S_TABLE='field_def' AND s.FK=f.ID_FIELD_DEF
					AND s.BF_LANG=if(f.BF_LANG_FIELD_DEF & ".$lang.", ".$lang.", 1 << floor(log(f.BF_LANG_FIELD_DEF+0.5)/log(2)))
                LEFT JOIN `ad_master` am ON am.ID_AD_MASTER=av.FK_AD_MASTER
                LEFT JOIN `kat2field` kf ON kf.FK_KAT=am.FK_KAT AND kf.FK_FIELD=f.ID_FIELD_DEF
				WHERE av.FK_AD_MASTER=".(int)$adId." AND av.STATUS=1 AND kf.B_ENABLED=1
				GROUP BY av2lv.FK_FIELD_DEF");
		foreach ($ar_fields as $index => $ar_field) {
			$ar_fields[$index]["F_NAME"] = $ar_field['F_NAME'];
			$ar_fields[$index]["values"] = self::$db->fetch_table("SELECT t.*, s.V1, s.V2, s.T1
				FROM `ad_variant` v
				LEFT JOIN `ad_variant2liste_values` av2lv
					ON v.ID_AD_VARIANT=av2lv.FK_AD_VARIANT
				LEFT JOIN `liste_values` t
					ON t.ID_LISTE_VALUES=av2lv.FK_LISTE_VALUES AND t.FK_LISTE=".$ar_field['FK_LISTE']."
				LEFT JOIN `string_liste_values` s
					ON s.S_TABLE='liste_values' AND s.FK=t.ID_LISTE_VALUES
					AND s.BF_LANG=if(t.BF_LANG_LISTE_VALUES & ".$lang.", ".$lang.", 1 << floor(log(t.BF_LANG_LISTE_VALUES+0.5)/log(2)))
				WHERE
					av2lv.FK_FIELD_DEF=".$ar_field['ID_FIELD_DEF']." AND v.FK_AD_MASTER=".(int)$adId." AND v.STATUS=1
				GROUP BY
					t.ID_LISTE_VALUES
				ORDER BY
					t.ID_LISTE_VALUES");
		}
		return $ar_fields;
	}

	/**
	 * @param int $adVariantId	Index of the ad-variant
	 * @return Array			Details of the given variant
	 */
	public function getAdVariantDetailsById($adVariantId) {
		$ar_details = self::$db->fetch1("SELECT * FROM `ad_variant` WHERE ID_AD_VARIANT=".(int)$adVariantId);
		return (is_array($ar_details) ? $ar_details : array("ID_AD_VARIANT" => 0));
	}

	public function getAdVariantDetailsByAd($adId, $ar_fields) {
		$ar_fields_def = self::$db->fetch_table("SELECT f.*
				FROM `ad_variant` v
				LEFT JOIN `ad_variant2liste_values` vl
					ON vl.FK_AD_VARIANT=v.ID_AD_VARIANT
				LEFT JOIN `field_def` f
					ON f.ID_FIELD_DEF=vl.FK_FIELD_DEF
				WHERE v.FK_AD_MASTER=".(int)$adId."
				GROUP BY ID_FIELD_DEF");
		$ar_options = array();
		foreach ($ar_fields_def as $index => $ar_field) {
			if (isset($ar_fields[ $ar_field['F_NAME'] ])) {
				$ar_options[ $ar_field['ID_FIELD_DEF'] ] = $ar_fields[ $ar_field['F_NAME'] ];
			}
		}
		$ar_joins = array();
		$ar_where = array("v.FK_AD_MASTER=".(int)$adId);
		$index = 0;
		foreach ($ar_options as $id_field_def => $id_liste_values) {
			$index++;
			$ar_joins[] = "INNER JOIN `ad_variant2liste_values` vl".$index."\n".
						"	ON v.ID_AD_VARIANT=vl".(int)$index.".FK_AD_VARIANT\n".
						"		AND vl".(int)$index.".FK_FIELD_DEF=".(int)$id_field_def;
			$ar_where[] = "vl".$index.".FK_LISTE_VALUES=".$id_liste_values;
		}
		$query = "SELECT v.* FROM `ad_variant` v\n".implode("\n", $ar_joins)."\n".
			"WHERE ".implode("\n	AND ", $ar_where)."\n".
			"GROUP BY ID_AD_VARIANT";
		$ar_details = self::$db->fetch1($query);
		return (is_array($ar_details) ? $ar_details : array("ID_AD_VARIANT" => 0));
	}

	public function getVariantFieldsById($adVariantId) {
		return self::$db->fetch_nar("SELECT f.F_NAME, vl.FK_LISTE_VALUES
				FROM `ad_variant` v
				LEFT JOIN `ad_variant2liste_values` vl
					ON vl.FK_AD_VARIANT=v.ID_AD_VARIANT
				LEFT JOIN `field_def` f
					ON f.ID_FIELD_DEF=vl.FK_FIELD_DEF
				WHERE v.ID_AD_VARIANT=".(int)$adVariantId."
				GROUP BY f.ID_FIELD_DEF");
	}

	public function getAdVariantTextById($adVariantId, $lang = null) {
		global $langval;
		if ($lang == null) {
			$lang = $langval;
		}
		$ar_result = self::$db->fetch_table("SELECT
					f.ID_FIELD_DEF, sf.V1 as FIELD, sl.V1 as VALUE, t.ID_LISTE_VALUES
				FROM `ad_variant2liste_values` av2lv
				LEFT JOIN `ad_variant` av
					ON av.ID_AD_VARIANT=av2lv.FK_AD_VARIANT
				LEFT JOIN `field_def` f
					ON av2lv.FK_FIELD_DEF=f.ID_FIELD_DEF
				LEFT JOIN `string_field_def` sf
					ON sf.S_TABLE='field_def' AND sf.FK=f.ID_FIELD_DEF
					AND sf.BF_LANG=if(f.BF_LANG_FIELD_DEF & ".$lang.", ".$lang.", 1 << floor(log(f.BF_LANG_FIELD_DEF+0.5)/log(2)))
				LEFT JOIN `liste_values` t
					ON t.ID_LISTE_VALUES=av2lv.FK_LISTE_VALUES
				LEFT JOIN `string_liste_values` sl
					ON sl.S_TABLE='liste_values' AND sl.FK=t.ID_LISTE_VALUES
					AND sl.BF_LANG=if(t.BF_LANG_LISTE_VALUES & ".$lang.", ".$lang.", 1 << floor(log(t.BF_LANG_LISTE_VALUES+0.5)/log(2)))
				WHERE av.ID_AD_VARIANT=".(int)$adVariantId."
				GROUP BY av2lv.FK_FIELD_DEF");
		return $ar_result;
	}

	/**
	 * @return ebiz_db $db
	 */
	public function getDb() {
		return self::$db;
	}

	/**
	 * @param ebiz_db $db
	 */
	public function setDb(ebiz_db $db) {
		self::$db = $db;
	}

	public function setAdVariantQuantityById($id_ad_variant, $quantity) {
		self::$db->querynow("UPDATE `ad_variant` SET MENGE=".(int)$quantity." WHERE ID_AD_VARIANT=".(int)$id_ad_variant);
	}

	private function __construct() {
	}
	private function __clone() {
	}


}
?>
