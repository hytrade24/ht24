<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path.'sys/lib.ad_constraint.php';

function killbb(&$row,$i)
{
	$row['DSC'] = preg_replace("#(\[)([^\s\]]*)(\])#si", "", $row['DSC']);
	$row['DSC'] = substr(strip_tags($row['DSC']), 0, 250);
	$row['DSC'] = preg_replace("#(\[)([^\s\]]*)(\])#si", "", $row['DSC']);
	$row['DSC'] = str_replace("&nbsp;", ' ', $row['DSC']);
	$row['DSC'] = str_replace("&nbsp", ' ', $row['DSC']);
}

$ar_settings = $db->fetch1("
	select
		*
	from
		user_shop us
	join
		user u on us.FK_USER=u.ID_USER
		and u.STAT=1
	where
		us.HASH='".mysql_real_escape_string($_REQUEST['id'])."'");
if(!empty($ar_settings)) {
	#die(ht(dump($nar_systemsettings)));
	$tpl_content->addvar("URI", $nar_systemsettings['SITE']['SITEURL']);
	$tpl_content->addvar("SITENAME", $nar_systemsettings['SITE']['SITENAME']);
	$tpl_content->addvars($ar_settings);
	### Artikel
	$npage = ((int)$ar_params[5] ? (int)$ar_params[5] : 1);
	$perpage = $ar_settings['PERPAGE'];
	$limit = ($perpage*$npage)-$perpage;

	$liste = $db->fetch_table("
    	SELECT
    		SQL_CALC_FOUND_ROWS
    		am.*,
    		am.ID_AD_MASTER AS ID_AD,
    		am.BESCHREIBUNG AS DSC,
    		s.V1 as KAT,
    		sc.V1 as LAND,
    		i.SRC AS IMG_DEFAULT_SRC,
    		i.SRC_THUMB AS IMG_DEFAULT_SRC_THUMB,
    		m.NAME as MANUFACTURER
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
		LEFT JOIN `manufacturers` m ON m.ID_MAN=am.FK_MAN
		WHERE
    		am.FK_USER=".$ar_settings['FK_USER']."
    		AND am.STATUS&3 = 1 AND (am.DELETED=0)
    	ORDER BY
    		B_TOP_LIST DESC,
    		STAMP_START DESC
    	LIMIT
    		".$perpage);
	$all = $db->fetch_atom("SELECT FOUND_ROWS()");
	Rest_MarketplaceAds::extendAdDetailsList($liste);

	$tpl_content->isTemplateRecursiveParsable = TRUE;
	$tpl_content->isTemplateCached = TRUE;
	$tpl_content->addlist('USER_ADS', $liste, $ab_path.'tpl/'.$s_lang.'/user_shop.row.htm', 'killbb');
	//$pager = htm_browse($all, $npage, "/view_user,".urlencode($data['NAME']).",".$get_uid.",".$fk_kat.",,", $perpage);
	//$tpl_content->addvar("pager", $pager);
} else {
	die("<p>Unable to load shop (".stdHtmlentities($_REQUEST['id']).")");
}

?>