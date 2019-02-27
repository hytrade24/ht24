<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path.'sys/lib.ad_constraint.php';

function killbb(&$row,$i)
{
	$row['BESCHREIBUNG'] = substr(strip_tags($row['BESCHREIBUNG']), 0, 250);
	$row['BESCHREIBUNG'] = preg_replace("#(\[)([^\s\]]*)(\])#si", "", $row['BESCHREIBUNG']);
}

$npage = ((int)$ar_params[5] ? (int)$ar_params[5] : 1);
$perpage = 10;
$limit = ($perpage*$npage)-$perpage;

$get_uid=$ar_params[2];
$view = ($ar_params[3] ? $ar_params[3] : "uebersicht");
$fk_kat = (int)$ar_params[3];

function checkview($checkthis,$setthis) {
	global $tpl_content,$uid;

	switch($checkthis)
	{
		case 'ALL':
			$tpl_content->addvar($setthis,true);
			break;
		case 'USER':
			if ($uid>0){
				$tpl_content->addvar($setthis,true);
			} else {
				$tpl_content->addvar($setthis,false);
			}
			break;
		case 'CONTACT':
			$tpl_content->addvar($setthis,true);
			break;
		default:
			$tpl_content->addvar($setthis,false);
			break;
	}
}

if ($get_uid > 0 ) {

	$data = $db->fetch1("select VORNAME,NACHNAME,NAME,CACHE,STAMP_REG,LASTACTIV,URL,STRASSE,PLZ,ORT,LU_PROFESSION,ID_USER,UEBER, ROUND(RATING) as lastrate,TIMESTAMPDIFF(YEAR,GEBDAT,CURDATE()) as age,TEL from user where ID_USER=". $get_uid); // Userdaten lesen
	include_once ($GLOBALS['nar_systemsettings']['SITE']['USER_PATH'].$data['CACHE']."/".$get_uid."/useroptions.php");
	checkview($useroptions['LU_SHOWCONTAC'],"showcontact");
	checkview($useroptions['LU_SHOWKOMPETENZ'],"showkomp");
	$tpl_content->addvar("ALLOW_CONTACS",$useroptions['ALLOW_CONTACS']);
	checkview('USER',"isuser");
	$nar_tplglobals['newstitle'] = $data['NAME'];

	if($data['ID_USER'] != $uid) {
		$res = $db->querynow("update user_views set `VIEWS`=`VIEWS`+1 where
		    FK_USER=".$data['ID_USER']." and STAMP=CURDATE()");
		if(!$res['int_result'])
		$res = $db->querynow("insert into user_views set `VIEWS`=1, FK_USER=".$data['ID_USER'].", STAMP=CURDATE()");
	} // nicht der eigene user

	#$SILENCE=false;
	$liste = $db->fetch_table("
    	SELECT
    		SQL_CALC_FOUND_ROWS
    		am.*,
    		am.ID_AD_MASTER AS ID_AD,
    		LEFT(am.BESCHREIBUNG, 250) AS DSC,
    		s.V1 as KAT,
    		sc.V1 as LAND,
    		i.SRC AS IMG_DEFAULT_SRC,
    		i.SRC_THUMB AS IMG_DEFAULT_SRC_THUMB
    	FROM
    		ad_master am
    	LEFT JOIN
			string_kat s on s.S_TABLE='kat'
			and s.FK=am.FK_KAT
			and s.BF_LANG=if(".$langval." & ".$langval.", ".$langval.", 1 << floor(log(".$langval."+0.5)/log(2)))
    	LEFT JOIN
			string sc on sc.S_TABLE='country'
			and sc.FK=am.FK_COUNTRY
			and sc.BF_LANG=if(".$langval." & ".$langval.", ".$langval.", 1 << floor(log(".$langval."+0.5)/log(2)))
		LEFT JOIN
			ad_images i ON am.ID_AD_MASTER = i.FK_AD AND i.IS_DEFAULT=1
		WHERE
    		am.FK_USER=".$get_uid."
    		AND am.STATUS&3 = 1 AND (am.DELETED=0)
    		".($fk_kat ? 'AND am.FK_KAT = '.$fk_kat : '')."
    	ORDER BY
    		B_TOP_LIST DESC,
    		STAMP_START DESC
    	LIMIT
    		".$limit.", ".$perpage);
	$all = $db->fetch_atom("SELECT FOUND_ROWS()");

	Rest_MarketplaceAds::extendAdDetailsList($liste);

	$tpl_content->isTemplateRecursiveParsable = TRUE;
	$tpl_content->isTemplateCached = TRUE;
	$tpl_content->addlist('USER_ADS', $liste, $ab_path.'tpl/'.$s_lang.'/marktplatz.row.htm', 'killbb');
	$pager = htm_browse($all, $npage, "/view_user,".urlencode($data['NAME']).",".$get_uid.",".$fk_kat.",,", $perpage);
	$tpl_content->addvar("pager", $pager);

	if($all > 0)
	{
		$in = array(0);
		$res = $db->querynow("
			SELECT
				FK_KAT
			FROM
				ad_master
			WHERE
				FK_USER=".(int)$get_uid."
				AND STATUS&3=1 AND (DELETED=0)
			GROUP BY
				FK_KAT");
		while($row = mysql_fetch_row($res['rsrc']))
		{
			$in[] = $row[0];
		}
		$kats = $db->fetch_table("
			select
				t.*,
				s.V1,
				s.V2,
				s.T1,
				".(int)$get_uid." AS FK_USER,
				'".$data['NAME']."' AS NAME,
				".$fk_kat." AS CUR_KAT,
				(
					SELECT
						COUNT(ID_AD_MASTER)
					FROM
						ad_master
					WHERE
						ad_master.FK_USER=".$get_uid." AND
						ad_master.FK_KAT = t.ID_KAT
						AND ad_master.STATUS&3=1 AND (ad_master.DELETED=0)
				)	AS ADS
			from
				`kat` t
			left join string_kat s
				on s.S_TABLE='kat' and s.FK=t.ID_KAT
				and s.BF_LANG=if(t.BF_LANG_KAT & 128, 128, 1 << floor(log(t.BF_LANG_KAT+0.5)/log(2)))
			WHERE
				t.ID_KAT IN (".implode(",", $in).")
			ORDER BY
				s.V1");
		$tpl_content->addlist("own_kats", $kats, "tpl/".$s_lang."/view_user.kat.htm");
	}
	//end marktplatz

	// bewertungen
	$ratings = $db->fetch_table("
    	SELECT r.*, s.STAMP_BOUGHT,
    		(SELECT NAME FROM `user` WHERE ID_USER=r.FK_USER_FROM) as USERNAME_FROM
    		FROM `ad_sold_rating` r
    			LEFT JOIN `ad_sold` s ON r.FK_AD_SOLD=s.ID_AD_SOLD
    	WHERE r.FK_USER=".$get_uid."
    		ORDER BY s.STAMP_BOUGHT DESC");
	$tpl_content->addlist("USER_RATINGS", $ratings, $ab_path.'tpl/'.$s_lang.'/profil_dev.row_rating.htm');
	// end bewertungen

	$tpl_content->addvar("t_".$view, 1);
	$tpl_content->addvar("UID", $uid);
	if ($data['ID_USER'] < 1)
	$data = $db->fetch_blank('user');

	$tpl_content->addvars($data);
	//Kommentare lesen
} // if get_uid;

$tpl_content->addvar("_URL", urlencode($_SERVER['REQUEST_URI']));
?>
