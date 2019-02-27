<?php

$return_data = array();
$where = array();
$sqlWhere = '';
$where_vals = array();

if ( isset($_POST['SEARCH_NAME']) && $_POST['SEARCH_NAME'] != "" ) {
    $where['SEARCH_NAME'] = " (NAME LIKE '%" . mysql_real_escape_string($_POST['SEARCH_NAME']) . "%'
                           OR  FIRMA LIKE '%" . mysql_real_escape_string($_POST['SEARCH_NAME']) . "%'
                           OR  VORNAME LIKE '%" . mysql_real_escape_string($_POST['SEARCH_NAME']) . "%'
                           OR  NACHNAME LIKE '%" . mysql_real_escape_string($_POST['SEARCH_NAME']) . "%')";
    $where_vals['SEARCH_NAME'] = $_POST["SEARCH_NAME"];
}

if ( isset($_POST['FK_COUNTRY']) && $_POST['FK_COUNTRY'] != "" ) {
	$where['FK_COUNTRY'] = " FK_COUNTRY = " . (int)$_POST["FK_COUNTRY"];
	$where_vals['FK_COUNTRY'] = $_POST["FK_COUNTRY"];
}
if ( isset($_POST['ZIP']) && $_POST["ZIP"] != "" ) {
	$where['PLZ'] = " PLZ LIKE '" . mysql_real_escape_string($_POST["ZIP"]) . "%'";
	$where_vals['PLZ'] = $_POST["ZIP"];
}
if ( isset($_POST['CITY']) && $_POST["CITY"] != "" ) {
	$where['ORT'] = " ORT LIKE '" . mysql_real_escape_string($_POST["CITY"]) . "%'";
	$where_vals['ORT'] = $_POST["CITY"];
}

if(isset($_POST['LATITUDE']) && $_POST['LATITUDE'] != "" && isset($_POST['LONGITUDE']) && $_POST['LONGITUDE'] != "" && isset($_POST['LU_UMKREIS']) && $_POST['LU_UMKREIS'] != "" ) {
	$where_vals['LATITUDE'] = mysql_real_escape_string($_POST["LATITUDE"]);
	$where_vals['LONGITUDE'] = mysql_real_escape_string($_POST["LONGITUDE"]);
	$where_vals['LU_UMKREIS'] = mysql_real_escape_string($_POST["LU_UMKREIS"]);

	$radius = 6368;

	$rad_b = $_POST['LATITUDE'];
	$rad_l = $_POST['LONGITUDE'];

	$rad_l = $rad_l / 180 * M_PI;
	$rad_b = $rad_b / 180 * M_PI;

	$sqlWhere .= " AND ((
                        " . $radius . " * SQRT(ABS(2*(1-cos(RADIANS(user.LATITUDE)) *
                         cos(" . $rad_b . ") * (sin(RADIANS(user.LONGITUDE)) *
                         sin(" . $rad_l . ") + cos(RADIANS(user.LONGITUDE)) *
                         cos(" . $rad_l . ")) - sin(RADIANS(user.LATITUDE)) * sin(" . $rad_b . "))))
                    ) <= " . $db->fetch_atom("select `value` from lookup where ID_LOOKUP =" . $_POST['LU_UMKREIS'])."
                    )";
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
                    if ( count($where) != 0 ) {
                    	$sql .= " AND " . implode(" AND ",$where);
                    }
                    $sql .= ' ' . $sqlWhere;
$shops = $db->fetch_table( $sql );

if ( !empty($where) && !empty($shops) ) {
	//............
	$lifetime = time() + (60 * 60 * 24);
	$paramSer = serialize($where_vals);
	$hash = substr(md5("shopverzeichnis ".$paramSer), 0, 15);
	//............

	$sql = 'SELECT *
				FROM searchstring a
				WHERE a.QUERY = "'.$hash.'"';
	$result = $db->fetch_table( $sql );

	if ( count($result) == 0  ) {
		$ar = array(
			'QUERY' => $hash,
			'LIFETIME' => date("Y-m-d H:i:s", $lifetime),
			'S_STRING' => $paramSer,
			'S_WHERE' => ""
		);
		$id = $db->update('searchstring',$ar);
		$return_data['HASH'] = $ar['QUERY'];
	}
	else {
		$return_data['HASH'] = $result[0]['QUERY'];
	}
}

$return_data['COUNT'] = count($shops);
$return_data['shops'] = $shops;
/*$return_data = array(
	'COUNT'   =>  count($shops),
	'shops'     =>  $shops
);*/


die(json_encode($return_data));