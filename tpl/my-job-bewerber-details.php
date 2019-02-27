<?php
/* ###VERSIONSBLOCKINLCUDE### */


 //$SILENCE = false;
 $id = ($_REQUEST["id"] ? (int)$_REQUEST["id"] : false);
 $full = ($_REQUEST["full"] ? (int)$_REQUEST["full"] : false);
 
 require_once '../inc.server.php'; 
 require_once '../sys/lib.db.mysql.php';  // DB Wrapper class db
 require_once '../sys/ajax/JsHttpRequest.php';
 $JsHttpRequest =& new JsHttpRequest("windows-1251");

 $db = new ebiz_db($db_name, $db_host, $db_user, $db_pass);

 if($id)
 {
   $ar_user = $db->fetch1("SELECT u.ID_USER, u.NAME, u.CACHE, u.PLZ, u.ORT, u.TEL, u.MOBIL, u.STRASSE, 
    TIMESTAMPDIFF(YEAR,u.GEBDAT,CURDATE()) as AGE, u.STAMP_REG, u.URL, l.ABBR,
 	s.V1 as PROFESSION, u.RATING, c.* FROM user u 
	LEFT JOIN competence c ON u.ID_USER = c.FK_USER
	LEFT JOIN lang l ON l.ID_LANG = u.FK_LANG
	left join string s on s.FK=u.LU_PROFESSION AND S_TABLE='lookup' WHERE u.ID_USER=$id");
   $ar_competencelist = $db->fetch_table("SELECT COLUMN_NAME as Field,COLUMN_COMMENT as beschreibung
  FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = 'competence'");
   $comp_list = array();
   $ar_user['KOMPETENZEN_WERTE'] = '';
   foreach($ar_competencelist as $ar_values) {
	 $skill = $ar_user[$ar_values["Field"]];
	 if (($skill > 0) && ($ar_values["Field"] != "FK_USER") && (strncmp($ar_values["Field"] , "NODSP", 5) != 0)) {
	   $comp_list[] = $ar_values["Field"];
	   $ar_user['KOMPETENZEN_WERTE'] .= '<img src="'.$ab_baseurl.'gfx/stars_'.$skill.'.png"><br>';
	 }
   }
   $ar_user['KOMPETENZEN'] = implode('<br>', $comp_list);
	 
   $GLOBALS['_RESULT'] = array(
      "user"   => $id,
      "comp"   => $ar_user['KOMPETENZEN'],
	  "compv"  => $ar_user['KOMPETENZEN_WERTE'],
    );
 } // id // no post

?>