<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once 'sys/lib.ads.php';
require_once $ab_path.'sys/lib.ad_constraint.php';

function killbb(&$row,$i)
{
	//$row['DSC'] = strip_tags($row['DSC']);
	$row['BESCHREIBUNG'] = substr(strip_tags(html_entity_decode($row['BESCHREIBUNG'])), 0, 250);
	$row['BESCHREIBUNG'] = preg_replace("#(\[)([^\s\]]*)(\])#si", "", $row['BESCHREIBUNG']);
}

/**
 *
 * @param array $row
 * @param int $i
 * @return array
 */
function getRow(&$row, $i) {
	global $ab_path, $s_lang;

    $path = AdManagment::getAdCachePath($row['ID_AD'], false);
    $path .= '/row.'.$s_lang.'.htm';

	$row['HTML'] = file_get_contents($path);
	$row['HTML'] = str_replace('{RUNTIME}', $row['RUNTIME'], $row['HTML']);
}

if (isset($_REQUEST['SEARCH_AJAX'])) {
  header('Content-type: application/json');
  $json_result = array();

  $string_org = $_REQUEST['SEARCH_AJAX'];
  $string = str_replace(array('Ä','Ö','Ü','ä','ö','ü','ß','-'), array('Ae','Oe','Ue','ae','oe','ue','ss','_'), $string_org);
  $string = preg_replace("/[^\sa-z0-9_]/si", "", $string);

	$offers=array();
  $res = $db->querynow("
  	SELECT
  		ID_AD_SEARCH_OFFER, SEARCHTEXT
  	FROM
  		`ad_search_offer`
  	WHERE
  		SEARCHTEXT LIKE '".mysql_escape_string($string_org)."%'
  	ORDER BY
  		HITS DESC
  	LIMIT
  		6");
  while($row = mysql_fetch_row($res['rsrc'])) {
  	$text = str_replace(array('Ae','Ue','Oe','ae','ue','oe'), array('Ä','Ö','Ü','ä','ü','ö'), $row[1]);
	$offers[$row[0]] = $text;
  }

  if (count($offers) > 0) {
    $json_result['offers'] = $offers;
  } else {
    $json_result['fail'] = true;
  }

  die(json_encode($json_result));
}

if(isset($_REQUEST['SEARCH_PROXY'])) {

	$search = AdManagment::generateSearchString( array_merge($_GET, $_POST) );

	die(forward($tpl_content->tpl_uri_action('marktplatz,'.$search['ID_KAT'].',Suchergebniss,'.$search['HASH'])));
}

if(isset($_REQUEST['GET_PRODUCTS']))
{
	$ar_liste_product = $db->fetch_table("
    	SELECT
    		p.ID_PRODUCT as VALUE,
    		s.V1 as TEXT
    	FROM product p
    	LEFT JOIN string_product s
    		ON s.S_TABLE='product' and s.FK=p.ID_PRODUCT
			and s.BF_LANG=if(p.BF_LANG_PRODUCT & ".$langval.", ".$langval.", 1 << floor(log(".$langval."+0.5)/log(2)))
		WHERE
			p.FK_MAN=".(int)$_REQUEST['GET_PRODUCTS']." AND
			p.CONFIRMED=1
		ORDER BY s.V1
	");
	$tpl_products_html = file_get_contents('tpl/'.$s_lang.'/kat_left_2.product.htm');
	$tpl_content->addvar("FK_MAN", ($_REQUEST['GET_PRODUCTS'] > 0 ? $_REQUEST['GET_PRODUCTS'] : 0));
	$tpl_content->addvar("FK_PRODUCT", $_REQUEST["select"]);
	$tpl_content->addlist('liste_product', $ar_liste_product, 'tpl/'.$s_lang.'/kat_left_2.row_option_product.htm');
	$result = $tpl_content->process_text($tpl_products_html, true);

  	header('Content-type: application/json');
    die(json_encode(array(
    	"code"	=> $result
    )));
}

if(isset($_REQUEST['GET_PRODUCTS_JSON']))
{
	require_once $ab_path."sys/lib.hdb.php";
	$manufacturerDatabaseManagement = ManufacturerDatabaseManagement::getInstance($db);
	$results = $manufacturerDatabaseManagement->searchProductsByMan($_REQUEST['GET_PRODUCTS_JSON']);
	
	$ar_names = array();
	foreach ($results->results as $resultIndex => $resultEntry) {
		$ar_names[] = $resultEntry["PRODUKTNAME"];
	}
	
  	header('Content-type: application/json');
    die(json_encode(array(
    	"list"	=> $ar_names
    )));
}
if($_REQUEST['AGENT'])
{
	$ar_agent = $db->fetch1("
		SELECT
			*
		FROM
			ad_agent
		WHERE
			ID_AD_AGENT=".(int)$_REQUEST['AGENT']."
			AND FK_USER=".$uid);
	if(!empty($ar_agent))
	{
		$lifetime = time()+(60*60*24);
		$hash = md5(microtime());
		$hash = substr($hash, 0, 15);

        if ($ar_agent["SEARCH_KAT"] > 0) {
            // Kategorie-Suche
            $ar = array(
                'S_STRING' => $ar_agent["SEARCH_ARRAY"],
                'S_WHERE' => $ar_agent["SEARCH_WHERE"],
                'S_LANG' => $s_lang,
                'LIFETIME' => date('Y-m-d H:i:s', $lifetime),
                'QUERY' => $hash,
            );
            $id_search = $db->update("searchstring", $ar);
            // Forward
            die(forward($tpl_content->tpl_uri_action("marktplatz,".$ar_agent["SEARCH_KAT"].",Suchergebniss,".$hash)));
        } else {
            // Globale Suche (Derzeit nicht in verwendung)
            $ar = array(
                'S_STRING' => 'ADAGENT',
                'S_WHERE' => $ar_agent["SEARCH_ARRAY"],
                'S_LANG' => $s_lang,
                'LIFETIME' => date('Y-m-d H:i:s', $lifetime),
                'QUERY' => $hash,
            );
            $id_search = $db->update("searchstring", $ar);
            // Forward
            die(forward($tpl_content->tpl_uri_action("artikel-suche,".$hash.",1")));
        }
	}
}
if(isset($_REQUEST['SEARCH']) || isset($_REQUEST['LATITUDE']) || isset($_REQUEST['LONGITUDE']))
{
	$string = urldecode($_REQUEST['SEARCH']);
	$string = $string_complete = trim($string);

    if($string == '') {
        $string = '*';
    }

	#die($string);

	$string = generateFulltextSearchstring($string);
	$ad_id = false;

	if(preg_match("/[0-9]{1,}/s", $string))
	{
		$ad_id = $string;
	}	// nach ID suchen

	if(strlen($string) < 3 && !empty($string) && $string !== '*')
	{
		$tpl_content->addvar("err", 1);
	}
	else
	{
		$db->querynow("
			DELETE FROM
				searchstring
			WHERE
				LIFETIME < NOW()");

		$lifetime = time()+(60*60*24);
		$hash = md5(microtime());
		$hash = substr($hash, 0, 15);

		$ar = array
			(
				'S_STRING' => $string."§§§".$string_complete,
				'S_LANG' => $s_lang,
				'LIFETIME' => date('Y-m-d H:i:s', $lifetime),
				'QUERY' => $hash,
				'S_WHERE' => serialize($_REQUEST),
			);

		$id_search = $db->update("searchstring", $ar);
		die(forward("/artikel-suche,".$hash.",1.htm"));
	}

}	// neue Suche
else
{
	if($hash = $ar_params[1])
	{
		$ar = $db->fetch1("
			SELECT
				*
			FROM
				searchstring
			WHERE
				`QUERY`='".$hash."'");

		$npage = (int)$ar_params[2];
		$perpage = 20;
		$limit = ($npage*$perpage)-$perpage;
		$string = strtolower($ar['S_STRING']);
		$hack = explode("§§§", $string);
		$string = $hack[0];
		$string_org = $hack[1];

		// Sortierung
	    $sort_by = ($ar_params[3] ? $ar_params[3] : "RUNTIME");
	    $sort_dir = ($ar_params[4] ? $ar_params[4] : "DESC");

	    $sort_fields = array("PRODUKTNAME", "ZIP", "PREIS", "RUNTIME");
	    $sort_directions = array("DESC", "ASC");
	    $sort_vars = array();
	    $sort = array();

	    foreach($sort_fields as $index => $field)
	      $sort_vars["SORT_".$field] = "DESC";

	    if (in_array($sort_by, $sort_fields) && in_array($sort_dir, $sort_directions)) {
	      $sort[] = $sort_by." ".$sort_dir;
	      $sort_vars["SORT_DIRECTION"] = strtolower($sort_dir);
	      $sort_vars["SORT_BY_".$sort_by] = ($sort_dir == "DESC" ? 1 : 2);
	      $sort_vars["SORT_".$sort_by] = ($sort_dir == "DESC" ? "ASC" : "DESC");
	    } else {
	      $sort_vars["SORT_DIRECTION"] = "asc";
	      $sort_vars["SORT_BY_RUNTIME"] = 2;
	      $sort_vars["SORT_RUNTIME"] = "DESC";
	      $sort[] = "RUNTIME ASC";
	    }

	    $tpl_content->addvars($sort_vars);
	    $tpl_content->addvar("HASH", $hash);
	    $tpl_content->addvar("PAGE", $npage);


	    if(preg_match("/^[0-9]{1,}$/s", $string))
		{
			$query = "
				SELECT
					SQL_CALC_FOUND_ROWS
					ad_search.FK_AD,
			 		ad_search.AD_TABLE,
					ad_master.*,
					ad_master.BESCHREIBUNG AS DSC,
					ad_master.ID_AD_MASTER AS ID_AD,
					sk.V1 as KAT,
					sc.V1 as LAND,
					i.SRC AS SRC,
					i.SRC_THUMB
				FROM
					ad_search
				JOIN
					ad_master ON ad_master.ID_AD_MASTER=ad_search.FK_AD
					AND ad_master.STATUS&3=1 AND (ad_master.DELETED=0)
				LEFT JOIN
					string_kat sk ON sk.FK=ad_master.FK_KAT AND sk.S_TABLE='kat'
					AND sk.BF_LANG=if(".$langval." & ".$langval.", ".$langval.", 1 << floor(log(".$langval."+0.5)/log(2)))
				LEFT JOIN
					string sc ON sc.FK=ad_master.FK_COUNTRY AND sc.S_TABLE='country'
					AND sc.BF_LANG=if(".$langval." & ".$langval.", ".$langval.", 1 << floor(log(".$langval."+0.5)/log(2)))
				LEFT JOIN
					ad_images i ON ad_master.ID_AD_MASTER = i.FK_AD AND i.IS_DEFAULT=1
				WHERE
					ad_search.LANG='".$ar['S_LANG']."'
					AND ad_master.ID_AD_MASTER = ".$string."
				LIMIT
					".$limit.",
					".$perpage;
		}	// nach ID suchen
	    else
	    {
	    	if((($ar['S_STRING'] == 'ADAGENT') || ($ar['S_STRING'] == '§§§')) && !empty($ar['S_WHERE']))
	    	{
	    		$s_where = array('ad_master.ID_AD_MASTER <> 0');
	    		$tmp = unserialize($ar['S_WHERE']);
	    		if($tmp['SEARCH_KAT'])
	    		{
	    			$s_where[] = 'ad_master.FK_KAT='.$tmp['SEARCH_KAT'];
	    		}
	    		if($tmp['SEARCH_MAN'])
	    		{
	    			$s_where[] = 'ad_master.FK_MAN='.$tmp['SEARCH_MAN'];
	    		}
	    		if($tmp['SEARCH_USER'])
	    		{
	    			$s_where[] = 'ad_master.FK_USER='.$tmp['SEARCH_USER'];
	    		}

	    		$query = "
					SELECT
						SQL_CALC_FOUND_ROWS
						ad_search.FK_AD,
				 		ad_search.AD_TABLE,
						ad_master.*,
						ad_master.BESCHREIBUNG AS DSC,
						ad_master.ID_AD_MASTER AS ID_AD,
						sk.V1 as KAT,
						sc.V1 as LAND,
						i.SRC AS SRC,
						i.SRC_THUMB
					FROM
						ad_search
					JOIN
						ad_master ON ad_master.ID_AD_MASTER=ad_search.FK_AD
						AND ad_master.STATUS&3=1 AND (ad_master.DELETED=0)
					LEFT JOIN
						string_kat sk ON sk.FK=ad_master.FK_KAT AND sk.S_TABLE='kat'
						AND sk.BF_LANG=if(".$langval." & ".$langval.", ".$langval.", 1 << floor(log(".$langval."+0.5)/log(2)))
					LEFT JOIN
						string sc ON sc.FK=ad_master.FK_COUNTRY AND sc.S_TABLE='country'
						AND sc.BF_LANG=if(".$langval." & ".$langval.", ".$langval.", 1 << floor(log(".$langval."+0.5)/log(2)))
					LEFT JOIN
						ad_images i ON ad_master.ID_AD_MASTER = i.FK_AD AND i.IS_DEFAULT=1
					WHERE
						ad_search.LANG='".$ar['S_LANG']."'
						AND ".implode("\nAND ", $s_where)."
					GROUP BY ad_master.ID_AD_MASTER
					ORDER BY
						ad_master.STAMP_START DESC
					LIMIT
						".$limit.",
						".$perpage;

	    	} else {
	    		$ar_s_where = @unserialize($ar['S_WHERE']);
	    		if(!empty($ar_s_where)) {
	    			$a = $ar_s_where;
	    			$GLOBALS['SEARCHED'] = $a;
	    			#echo ht(dump($a));
	    			$w = array();
	    			if(is_numeric($a['PBIS'] = str_replace(",", ".", $a['PBIS']))) {
	    				$w[] = 'ad_master.PREIS <= '.$a['PBIS'];
	    			}
	    			if(is_numeric($a['PVON'] = str_replace(",", ".", $a['PVON']))) {
	    				$w[] = 'ad_master.PREIS >= '.$a['PVON'];
	    			}
	    			if($a['FK_KAT']) {
	    				$kat = $db->fetch1("select LFT, RGT from kat where ID_KAT=".$a['FK_KAT']);
	    				$in = array(0);
	    				$r = $db->querynow("
	    					select
	    						ID_KAT
	    					from
	    						kat
	    					where
	    						LFT >= ".$kat['LFT']."
	    						AND RGT <= ".$kat['RGT']."
	    						and ROOT = 1");
	    				while($row = mysql_fetch_assoc($r['rsrc'])) {
	    					$in[] = $row['ID_KAT'];
	    				}
	    				$w[] = "ad_master.FK_KAT in(".implode(",", $in).")";
	    			}
	    			if($a['LONGITUDE'] > 0 && $a['LATITUDE'] > 0) {
	    				$radius = 6368;
						$rad_b = $a['LATITUDE'];
						$rad_l = $a['LONGITUDE'];
						$rad_l = $rad_l / 180 * M_PI;
						$rad_b = $rad_b / 180 * M_PI;

						$w[] = "(
					 		 	".$radius." * SQRT(ABS(2*(1-cos(RADIANS(ad_master.LATITUDE)) *
								 cos(".$rad_b.") * (sin(RADIANS(ad_master.LONGITUDE)) *
								 sin(".$rad_l.") + cos(RADIANS(ad_master.LONGITUDE)) *
								 cos(".$rad_l.")) - sin(RADIANS(ad_master.LATITUDE)) * sin(".$rad_b."))))
							) <= ".$db->fetch_atom("select `value` from lookup where ID_LOOKUP =".$a['LU_UMKREIS']);
	    			}
	    			if($a['FK_COUNTRY'] > 0) {
	    				$w[] = "ad_master.FK_COUNTRY IN (".$a['FK_COUNTRY'].",0)";
	    			}
					if($a['FK_MAN'] > 0) {
						$w[] = "ad_master.FK_MAN = '".(int)$a['FK_MAN']."'";
					}
	    			#echo ht(dump($w));
	    			if(count($w)) {
	    				$s_where = 'and '.implode("\nand ", $w);
	    			}
	    		} else {
	    			$s_where = '';
	    		}


				$query = "
					SELECT
						SQL_CALC_FOUND_ROWS
						ad_search.FK_AD,
				 		ad_search.AD_TABLE,
						".((!empty($string) && $string !== '*') ? "MATCH (ad_search.STEXT) AGAINST ('".$string."' IN BOOLEAN MODE) AS REL," : "")."
						ad_master.*,
						ad_master.ID_AD_MASTER AS ID_AD,
						m.NAME AS MANUFACTURER,
						ad_master.BF_CONSTRAINTS,
						sk.V1 as KAT,
						sc.V1 as LAND,
						i.SRC AS IMG_DEFAULT_SRC,
						i.SRC_THUMB AS IMG_DEFAULT_SRC_THUMB
					FROM
						ad_search
					JOIN
						ad_master ON ad_master.ID_AD_MASTER=ad_search.FK_AD
						AND ad_master.STATUS&3=1 AND (ad_master.DELETED=0)
					LEFT JOIN
						manufacturers m on ad_master.FK_MAN=m.ID_MAN
					LEFT JOIN
						string_kat sk ON sk.FK=ad_master.FK_KAT AND sk.S_TABLE='kat'
						AND sk.BF_LANG=if(".$langval." & ".$langval.", ".$langval.", 1 << floor(log(".$langval."+0.5)/log(2)))
					LEFT JOIN
						string sc ON sc.FK=ad_master.FK_COUNTRY AND sc.S_TABLE='country'
						AND sc.BF_LANG=if(".$langval." & ".$langval.", ".$langval.", 1 << floor(log(".$langval."+0.5)/log(2)))
					LEFT JOIN
						ad_images i ON ad_master.ID_AD_MASTER = i.FK_AD AND i.IS_DEFAULT=1
					WHERE
						ad_search.LANG='".$ar['S_LANG']."'
						".((!empty($string) && $string !== '*')? "AND (MATCH (ad_search.STEXT) AGAINST ('".$string."' IN BOOLEAN MODE))" : "")."
						".$s_where."
					GROUP BY ad_master.ID_AD_MASTER
					ORDER BY
						".((!empty($string) && $string !== '*') ? "REL DESC," : "")."
						STAMP_START DESC
					LIMIT
						".$limit.",
						".$perpage;

				//var_dump($query);
	    	}
	    }
		//echo($query."<hr>");

		$liste = $db->fetch_table($query);
		Rest_MarketplaceAds::extendAdDetailsList($liste);
		$all = (int)$db->fetch_atom("SELECT FOUND_ROWS()");
		if (!empty($liste)) {
			$search_offer = $db->fetch1("SELECT * FROM `ad_search_offer` WHERE SEARCHTEXT='".mysql_escape_string($string_org)."'");
			if (empty($search_offer)) {
				// Suchvorschlag speichern
				$db->querynow("INSERT INTO `ad_search_offer`
									(`SEARCHTEXT`, `BF_LANG`, `HITS`)
								VALUES
									('".mysql_escape_string($string_org)."', ".$langval.", 1)");
			} else {
				$search_offer["HITS"] += 1;
				$db->update("ad_search_offer", $search_offer);
			}
		}
#echo ht(dump($lastresult));
		$tpl_content->isTemplateRecursiveParsable = TRUE;
		$tpl_content->isTemplateCached = TRUE;
		$tpl_content->addvar("CURRENCY_DEFAULT", $nar_systemsettings['MARKTPLATZ']['CURRENCY']);
		$tpl_content->addlist("liste", $liste, "tpl/".$s_lang."/marktplatz.row.htm", "killbb");
		#echo ht(dump($lastresult));
		$pager = htm_browse_extended($all, $npage, "artikel-suche,".$hash.",{PAGE}", $perpage);
        $tpl_content->addvar("pager", $pager);
        $tpl_content->addvar("all", $all);

        $ar_s_where = unserialize($ar['S_WHERE']);
		if (is_array($ar_s_where)) {
			$tpl_content->addvars($ar_s_where);

			$opts = array();
			$res = $db->querynow("
				select
					s.V1, s.FK
				from
					`kat` t
				left join
					string_kat s on s.S_TABLE='kat' and s.FK=t.ID_KAT
					and s.BF_LANG=if(t.BF_LANG_KAT & " . $langval . ", " . $langval . ", 1 << floor(log(t.BF_LANG_KAT+0.5)/log(2)))
				where
					t.B_VIS=1 and PARENT=1
					and ROOT=1
				order by
					s.V1");
			while ($row = mysql_fetch_assoc($res['rsrc'])) {
				$selected = $ar_s_where['FK_KAT'] == $row['FK'] ? ' selected="selected"' : '';
				$opts[] = '<option value="' . $row['FK'] . '" ' . $selected . '>' . stdHtmlentities($row['V1']) . '</option>';
			}
			$tpl_content->addvar("katopts", implode("\n", $opts));

			// Hersteller
			$settings_product_db = $nar_systemsettings['MARKTPLATZ']['USE_PRODUCT_DB'];

			if ($settings_product_db) {
				$cacheFile = $ab_path . "cache/marktplatz/sbox_manufacture_" . $s_lang . ".htm";
				$cacheFileLifeTime = $nar_systemsettings['CACHE']['LIFETIME_CATEGORY'];
				$modifyTime = @filemtime($cacheFile);
				$diff = ((time() - $modifyTime) / 60);

				if (($diff > $cacheFileLifeTime) || !file_exists($cacheFile)) {
					$ar_liste_man = $db->fetch_table("
						SELECT
							ID_MAN as VALUE,
							NAME as TEXT
						FROM
							`manufacturers`
						WHERE
							CONFIRMED=1
						ORDER BY
							NAME");
					$tpl_tmp = new Template($ab_path . "tpl/de/empty.htm");
					$tpl_tmp->tpl_text = '{liste_man}';
					$row_tmp = '';
					foreach ($ar_liste_man as $key => $man) {
						$row_tpl_tmp = new Template('tpl/' . $s_lang . '/kat_left_2.row_option_man.htm');
						$row_tpl_tmp->addvars($man);
						$row_tmp .= $row_tpl_tmp->process();
					}

					$tpl_tmp->addvar('liste_man', $row_tmp);
					$tpl_tmp->isTemplateRecursiveParsable = TRUE;
					$cacheContent = $tpl_tmp->process();

					file_put_contents($cacheFile, $cacheContent);
				}

				$tplListeMan = @file_get_contents($cacheFile);
				$tpl_content->addvar('liste_man', $tpl_content->process_text($tplListeMan));
				$tpl_content->addvar("USE_PRODUCT_DB", $settings_product_db);
			}
		}

	}
}

?>