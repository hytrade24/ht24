<?php
/* ###VERSIONSBLOCKINLCUDE### */

$get_uid=$ar_params[2];
// Vendor
require_once 'sys/lib.vendor.php';
$vendorManagement = VendorManagement::getInstance($db);
$vendorManagement->setLangval($langval);
$isUserVendor = $vendorManagement->isUserVendorByUserId($get_uid);
if ($isUserVendor) {
    // Forward to vendor profile
    $firma = $db->fetch_atom("SELECT NAME FROM `vendor` WHERE FK_USER=".(int)$get_uid." AND STATUS = 1 AND MODERATED = 1");
    die(forward( $tpl_content->tpl_uri_action("view_user_vendor,".chtrans($firma).",".$get_uid) ));
} else {
    // Page not found!
    die(forward( $tpl_content->tpl_uri_baseurl("/system/404.htm") ));
}

require_once $ab_path.'sys/lib.ad_constraint.php';

function killbb(&$row,$i)
{
	$row['BESCHREIBUNG'] = substr(strip_tags(html_entity_decode($row['BESCHREIBUNG'])), 0, 250);
	$row['BESCHREIBUNG'] = preg_replace("#(\[)([^\s\]]*)(\])#si", "", $row['BESCHREIBUNG']);
}

// Einstellungen
$tpl_content->addvar("USE_HERSTELLER", $nar_systemsettings['MARKTPLATZ']['USE_PRODUCT_DB']);
$tpl_content->addvar("SHOW_RATING", $nar_systemsettings['MARKTPLATZ']['ALLOW_COMMENTS_RATED']);

$npage = ((int)$ar_params[5] ? (int)$ar_params[5] : 1);
$perpage = 10;
$limit = ($perpage*$npage)-$perpage;

$get_uid=$ar_params[2];
$view = ($ar_params[3] ? $ar_params[3] : "uebersicht");
$fk_kat = (int)$ar_params[3];

$tpl_content->addvar("active_shop", 1);

if ($get_uid > 0 ) {

	$data = $db->fetch1("select ID_USER as USER_ID_USER, CACHE, VORNAME as USER_VORNAME, NACHNAME as USER_NACHNAME, NAME as USER_NAME,CACHE as USER_CACHE from user where ID_USER=". $get_uid);
	if (empty($data)) {
		die(forward("/404.htm"));
	}

	#$SILENCE=false;
	$liste = $db->fetch_table("
    	SELECT
    		SQL_CALC_FOUND_ROWS
    		am.*,
    		am.ID_AD_MASTER AS ID_AD,
            am.TRADE AS product_trade,
    		LEFT(am.BESCHREIBUNG, 250) AS DSC,
    		s.V1 as KAT,
    		(SELECT m.NAME FROM `manufacturers` m WHERE m.ID_MAN=am.FK_MAN) as MANUFACTURER,
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
    $tpl_content->addvar("VENDOR_AD_COUNT", count($liste));


	$tpl_content->isTemplateRecursiveParsable = TRUE;
	$tpl_content->isTemplateCached = TRUE;
	$tpl_content->addlist('USER_ADS', $liste, $ab_path.'tpl/'.$s_lang.'/marktplatz.row.htm', 'killbb');
	$pager = htm_browse_extended($all, $npage, "view_user,".chtrans($data['USER_NAME']).",".$get_uid.",".$fk_kat.",,{PAGE}", $perpage);
	$tpl_content->addvar("pager", $pager);

	if($all > 0)
	{
		$kat_path = $ab_path.'cache/users/'.$data['CACHE'].'/'.$data['USER_ID_USER'];

		// Alle Kategorien
		$file_name = $kat_path.'/userkat_'.$fk_kat.'.'.$s_lang.'.tmp';
		$file = @filemtime($file_name);
		$now = time();
		$diff = (($now-$file)/60);
		$file = @file_get_contents($file_name);
		if($diff > 60 || !$file )
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
			$kats = $db->fetch_table( $q="
				select
					t.*,
					IF(sp.V1 IS NULL,s.V1,CONCAT(sp.V1,' > ',s.V1)) as V1,
					s.V2,
					s.T1,
					".(int)$get_uid." AS FK_USER,
					'".mysql_escape_string($data['USER_NAME'])."' AS NAME,
					".$fk_kat." AS CUR_KAT,
					(
						SELECT
							COUNT(ID_AD_MASTER)
						FROM
							ad_master
						WHERE
							ad_master.FK_USER=".$get_uid." AND
							ad_master.FK_KAT = t.ID_KAT
							AND ad_master.STATUS&3=1 AND (DELETED=0)
					)	AS ADS
				from
					`kat` t
				left join string_kat s
					on s.S_TABLE='kat' and s.FK=t.ID_KAT
					and s.BF_LANG=if(t.BF_LANG_KAT & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_KAT+0.5)/log(2)))
				left join kat tp
					on tp.ID_KAT=t.PARENT
				left join string_kat sp
					on sp.S_TABLE='kat' and sp.FK=tp.ID_KAT
					and sp.BF_LANG=if(tp.BF_LANG_KAT & ".$langval.", ".$langval.", 1 << floor(log(tp.BF_LANG_KAT+0.5)/log(2)))
				WHERE
					t.ID_KAT IN (".implode(",", $in).")
				ORDER BY
					s.V1");
			$tpl_tmp = new Template($ab_path.'tpl/de/empty.htm');
			$tpl_tmp->tpl_text = '{own_kats}';
			$tpl_tmp->addlist("own_kats", $kats, "tpl/".$s_lang."/view_user.kat.htm");
			file_put_contents($file_name, $tpl_tmp->process());
			@chmod($file_name, 0777);
		}

		// Top 50 Kategorien
		$file_name_top = $kat_path.'/userkat_'.$fk_kat.'.'.$s_lang.'.top.tmp';
		$file = @filemtime($file_name_top);
		$now = time();
		$diff = (($now-$file)/60);
		$file = @file_get_contents($file_name_top);
		if(($diff > $nar_systemsettings['CACHE']['LIFETIME_USER_CATEGORIES'] || !$file) && $all > 50)
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
			$kats = $db->fetch_table( "
				select
					t.*,
					IF(sp.V1 IS NULL,s.V1,CONCAT(sp.V1,' > ',s.V1)) as V1,
					s.V2,
					s.T1,
					".(int)$get_uid." AS FK_USER,
					'".mysql_escape_string($data['USER_NAME'])."' AS NAME,
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
					and s.BF_LANG=if(t.BF_LANG_KAT & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_KAT+0.5)/log(2)))
				left join kat tp
					on tp.ID_KAT=t.PARENT
				left join string_kat sp
					on sp.S_TABLE='kat' and sp.FK=tp.ID_KAT
					and sp.BF_LANG=if(tp.BF_LANG_KAT & ".$langval.", ".$langval.", 1 << floor(log(tp.BF_LANG_KAT+0.5)/log(2)))
				WHERE
					t.ID_KAT IN (".implode(",", $in).")
				ORDER BY ADS DESC
				LIMIT 50");


			$tpl_tmp = new Template($ab_path.'tpl/de/empty.htm');
			$tpl_tmp->tpl_text = '{own_kats}';
			$tpl_tmp->addlist("own_kats", $kats, "tpl/".$s_lang."/view_user.kat.htm");
			file_put_contents($file_name_top, $tpl_tmp->process());
			@chmod($file_name_top, 0777);
		}

		$tpl_content->addvar("own_kats", file_get_contents($file_name));
		if($all > 50) {
			$tpl_content->addvar("own_kats_top", file_get_contents($file_name_top));
		}

	}
	//end marktplatz

	$res = $db->fetch_table("
	select
		s.V1, s.FK
	from
		`kat` t
	left join
		string_kat s on s.S_TABLE='kat' and s.FK=t.ID_KAT
		and s.BF_LANG=if(t.BF_LANG_KAT & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_KAT+0.5)/log(2)))
	where
		t.B_VIS=1 and PARENT=1
		and ROOT=1
	order by
		s.V1");

	$tpl_content->addlist("liste_category_rows",$res,"tpl/".$s_lang."/category.row.left.htm");

	$tpl_content->addvar("noads",1); // keine Werbung
	$tpl_content->addvar("USER_ID_USER", $get_uid);
	$tpl_content->addvars($data);
} else {
	die(forward("/404.htm"));
} // if get_uid;

//$tpl_content->addvar("_URL", urlencode($_SERVER['REQUEST_URI']));
?>