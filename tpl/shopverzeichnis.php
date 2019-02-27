<?php
/* ###VERSIONSBLOCKINLCUDE### */



/**
 * @author ebiz-consult
 * @copyright 2011
 */
#Seitenzähler
$curpage = ($ar_params[1] ? $ar_params[1] : $ar_params[1]=1);
$perpage = $nar_systemsettings['MARKTPLATZ']['INDEX_SHOPVIEWS']; // Elemente pro Seite
$limit = (($curpage-1)*$perpage);

$sort = '';
$sort_direction = '';
$view_type = '';

if ( isset($ar_params[5]) ) {
	if ( $ar_params[5] == "BOX" ) {
		$view_type = 'BOX';
		$tpl_content->addvar("VIEW_TYPE_BOX",1);
		$tpl_content->addvar("VIEW_TYPE","BOX");
	}
	else if ( $ar_params[5] == "LIST" ) {
		$view_type = 'LIST';
		$tpl_content->addvar("VIEW_TYPE_LIST",1);
		$tpl_content->addvar("VIEW_TYPE","LIST");
	}
}
else {
	$view_type = 'BOX';
	$tpl_content->addvar("VIEW_TYPE_BOX",1);
	$tpl_content->addvar("VIEW_TYPE","BOX");
}

if ( isset($ar_params[3]) && isset($ar_params[4]) ) {
	$sort = $ar_params[3];
	$sort_direction = $ar_params[4];
}
else {
	$sort .= 'STAMP_REG';
	$sort_direction = 'DESC';
}

$sql = "SELECT * 
			FROM `option` 
			WHERE `plugin` = 'USER' 
			AND (`typ` = 'SHOW_SEARCH_MASK_USEROVERVIEW' OR `typ` = 'SHOW_MAP_IN_USEROVERVIEW')";

$result = $db->fetch_table( $sql );
$user_options = array();
foreach ( $result as $row ) {
	if ( $row["typ"] == "SHOW_MAP_IN_USEROVERVIEW" ) {
		$user_options["SHOW_MAP_IN_USEROVERVIEW"] = intval($row["value"]);
	}
	else if ( $row["typ"] == "SHOW_SEARCH_MASK_USEROVERVIEW" ) {
		$user_options["SHOW_SEARCH_MASK_USEROVERVIEW"] = intval($row["value"]);
	}
}

$where = array();
$sqlWhere = '';
$searchHash = null;

if ( $user_options["SHOW_SEARCH_MASK_USEROVERVIEW"] == 1 ) {
	$tpl_content->addvar("SHOW_SEARCH_MASK_USEROVERVIEW",1);

	if ( isset($ar_params[2]) ) {

		$sql = 'SELECT *
				FROM searchstring a
				WHERE a.QUERY = "'.$ar_params[2].'"';
		$result = $db->fetch1( $sql );

		if ( $result != false ) {
			$where_vals = unserialize( $result["S_STRING"] );
			$searchHash = $result["QUERY"];
			$tpl_content->addvar("searchHash",$searchHash);
			foreach ( $where_vals as $index => $row ) {
				$tpl_content->addvar("SEARCH_".$index,$row);

                if ( $index == "SEARCH_NAME" ) {
                    $where['SEARCH_NAME'] = " (NAME LIKE '%" . mysql_real_escape_string($row) . "%'
                                           OR  FIRMA LIKE '%" . mysql_real_escape_string($row) . "%'
                                           OR  VORNAME LIKE '%" . mysql_real_escape_string($row) . "%'
                                           OR  NACHNAME LIKE '%" . mysql_real_escape_string($row) . "%')";
                }

				if ( $index == "FK_COUNTRY" ) {
					$where['FK_COUNTRY'] = " FK_COUNTRY = " . (int)$row;
				}
				else if ( $index == "PLZ" ) {
					$where['PLZ'] = " PLZ LIKE '" . mysql_real_escape_string($row) . "%'";
				}
				else if ( $index == "ORT" ) {
					$where['ORT'] = " ORT LIKE '" . mysql_real_escape_string($row) . "%'";
				}
			}
			$where = " AND ". implode(" AND ", $where);

			if(isset($where_vals['LATITUDE']) && $where_vals['LATITUDE'] != "" && isset($where_vals['LONGITUDE']) && $where_vals['LONGITUDE'] != "" && isset($where_vals['LU_UMKREIS']) && $where_vals['LU_UMKREIS'] != "" ) {
				$radius = 6368;

				$rad_b = $where_vals['LATITUDE'];
				$rad_l = $where_vals['LONGITUDE'];

				$rad_l = $rad_l / 180 * M_PI;
				$rad_b = $rad_b / 180 * M_PI;

				$sqlWhere .= " AND ((
                        " . $radius . " * SQRT(ABS(2*(1-cos(RADIANS(user.LATITUDE)) *
                         cos(" . $rad_b . ") * (sin(RADIANS(user.LONGITUDE)) *
                         sin(" . $rad_l . ") + cos(RADIANS(user.LONGITUDE)) *
                         cos(" . $rad_l . ")) - sin(RADIANS(user.LATITUDE)) * sin(" . $rad_b . "))))
                    ) <= " . $db->fetch_atom("select `value` from lookup where ID_LOOKUP =" . $where_vals['LU_UMKREIS'])."
                    )";
			}
		}
	}
}

$sql = "select SQL_CALC_FOUND_ROWS
                            VORNAME as USER_VORNAME, 
                            NACHNAME as USER_NACHNAME, 
                            NAME as USER_NAME, 
                            FIRMA as USER_FIRMA, 
                            CACHE as USER_CACHE, 
                            STAMP_REG as USER_STAMP_REG, 
                            LASTACTIV as USER_LASTACTIV, 
                            URL as USER_URL, 
                            STRASSE as USER_STRASSE , 
                            PLZ as USER_PLZ, 
                            ORT as USER_ORT, 
                            ID_USER as USER_ID_USER, 
                            UEBER as USER_UEBER, 
                            ROUND(RATING) as USER_lastrate,
                            TIMESTAMPDIFF(YEAR,GEBDAT,CURDATE()) as USER_age, 
                            TEL as USER_TEL,
                            FK_LANG,
                            URL,
                            UEBER,
                            RATING,
                            FK_USERGROUP,
                            TOP_USER as USER_TOP_USER,
                            PROOFED as USER_PROOFED,
                            TOP_SELLER as USER_TOP_SELLER,
                            (SELECT slang.V1 FROM `string` slang WHERE slang.FK=FK_LANG
    			             AND slang.BF_LANG='".$langval."' and S_TABLE = 'country' LIMIT 1) as LAND
                    from user 
                    left join usersettings on ID_USER=FK_USER
                    where  STAT = 1 AND IS_VIRTUAL = 0 AND LU_SHOWCONTAC IN('ALL'". ( $uid > 0 ? ", 'USER'" : "" ) . ")";
if ( !empty($where) ) {
	$sql .= $where;
} else {
    $where = '';
}
$sql .= ' ' . $sqlWhere . ' ';
$sql .= " order by ".$sort . " " . $sort_direction ." LIMIT ".$limit.",".$perpage;

$tpl_content->addvar("CUR_".$sort."_".$sort_direction,1);
$tpl_content->addvar("SORT",$sort);
$tpl_content->addvar("SORT_DIR",$sort_direction);

$googleMaps = null;

$shops= $db->fetch_table( $sql ); // Userdaten lesen
$tpl_content->addvar("SHOPS_COUNT", count($shops) );

if (isset($ar_params[5]) && $ar_params[5] == "LIST") {
	$tpl_content->addlist("liste", $shops, "tpl/".$s_lang."/shopverzeichnis.row_list.htm");
}
else {
	$tpl_content->addlist("liste", $shops, "tpl/".$s_lang."/shopverzeichnis.row_box.htm");
}


$search_query = '';

if ( $user_options["SHOW_MAP_IN_USEROVERVIEW"] == 1 ) {

	include_once $ab_path . 'sys/lib.map.php';
	$googleMaps = GoogleMaps::getInstance();

	$search_query = "select
                        group_concat(r.json) as json
                        from (
                            select
                                concat('{',
                                    'ID:', user.ID_USER,
                                    ',LONGITUDE:', user.LONGITUDE,
                                    ',LATITUDE:', user.LATITUDE,
                                '}') as json
                                FROM
					                user 
			                    LEFT JOIN 
			                    	usersettings 
			                    ON user.ID_USER = usersettings.FK_USER
			                    WHERE  
			                    	user.STAT = 1 
			                    	AND 
			                    	user.IS_VIRTUAL = 0 
			                    " . $where . " " . $sqlWhere .  " 
                        ) as r";

	if ( $searchHash == null ) {
		if ( !$googleMaps->cacheFileExists('useroverview', 'all')
		     || $googleMaps->isExpired('useroverview', 'all') || true ) {
			$db->querynow('set session group_concat_max_len=4294967295');
			$data = $db->fetch_atom($search_query);

			$googleMaps->generateCacheFile('useroverview', 'all', "[".$data."]", false);

			$tpl_content->addvar("MAP_IDENT", "all");
		}

		$tpl_content->addvar('SHOW_MAP', true);
	}
	else if ( $searchHash != null ) {
		if (!$googleMaps->cacheFileExists('useroverview', 'uo'.$searchHash)
		    || $googleMaps->isExpired('useroverview', 'uo'.$searchHash) || true) {
			$db->querynow('set session group_concat_max_len=4294967295');
			$data = $db->fetch_atom($search_query);

			$googleMaps->generateCacheFile('useroverview', 'uo'.$searchHash, "[".$data."]", false);

			$tpl_content->addvar("MAP_IDENT", "uo".$searchHash);
		}

		$tpl_content->addvar('SHOW_MAP', true);
	}
}

#Seitenzähler
$all = $db->fetch_atom("
  		SELECT
  			FOUND_ROWS()");
            
$tpl_content->addvar("ALL_ADS", $all);
$tpl_content->addvar(
	"pager",
	htm_browse_extended(
		$all,
		$ar_params[1],
		"shopverzeichnis,{PAGE},".$searchHash.",".$sort.",".$sort_direction.",".$view_type,
		$perpage
	)
);
     
?>