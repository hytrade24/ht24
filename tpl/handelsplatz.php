<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path.'sys/lib.ad_constraint.php';

#$SILENCE=false;


### altes Zeug
function killbb(&$row,$i)
{
	$row['BESCHREIBUNG'] = substr(strip_tags($row['BESCHREIBUNG']), 0, 250);
	$row['BESCHREIBUNG'] = preg_replace("#(\[)([^\s\]]*)(\])#si", "", $row['BESCHREIBUNG']);
}

/*
 * Top Anzeigen
 */

$max_ads = $nar_systemsettings['MARKTPLATZ']['INDEX_NEWADS'];
$max_top = $nar_systemsettings['MARKTPLATZ']['INDEX_TOPADS'];

$file_name = $ab_path.'cache/marktplatz/start_handelsplatz.'.$s_lang.'.htm';
$file = @filemtime($file_name);
$now = time();
$diff = (($now-$file)/60);
$file = @file_get_contents($file_name);
$rewrite_new = false;
if($diff > 5 || !$file)
{
	$rewrite_new = true;
	#echo "caching ... Filetime: ".$diff." Minutes";
	$liste = $db->fetch_table("
		SELECT
			a.*,
			a.ID_AD_MASTER as ID_AD,
			a.BESCHREIBUNG AS DSC,
			m.NAME AS MANUFACTURER,
			i.SRC_THUMB AS IMG_DEFAULT_SRC_THUMB,
			i.SRC AS IMG_DEFAULT_SRC,
             a.TRADE AS product_trade,
			(SELECT sk.V1 FROM `string_kat` sk WHERE sk.FK=a.FK_KAT and sk.S_TABLE='kat'
    			AND sk.BF_LANG=if(sk.BF_LANG & ".$langval.", ".$langval.", 128) LIMIT 1) as KAT
		FROM
			`ad_master` a
		LEFT JOIN
			manufacturers m on a.FK_MAN=m.ID_MAN
		LEFT JOIN
			ad_images i on i.FK_AD=a.ID_AD_MASTER
			AND i.IS_DEFAULT = 1
		WHERE
			a.B_TOP=1
			AND a.STATUS&3=1 AND (a.DELETED=0)
		ORDER BY
			RAND()
		LIMIT
			".$max_top);
	#echo ht(dump($lastresult));
	$found = count($liste);
	if($found)
	{
		$tpl = new Template($ab_path.'tpl/'.$s_lang.'/cache_index_new_ads.htm');
		$tpl->isTemplateRecursiveParsable = TRUE;
		$tpl->addvars($tpl_main->vars);
		$tpl->addlist('new_ads', $liste, $ab_path.'tpl/'.$s_lang.'/marktplatz.row_box.htm','killbb');
		$write = $tpl->process();
		@file_put_contents($file_name, $write);
		@chmod($file_name, 0777);
		$tpl_content->addvar('new_topads', $tpl_content->process_text($write));
	}
	else
	{
		@file_put_contents($file_name, '<!-- no top ads -->');
		@chmod($file_name, 0777);
	}
}
else
{
	$tpl_content->addvar('new_topads', $tpl_content->process_text($file));
}

/**
 * Neue Anzeigen
 */
if($found)
{
	//$max_ads = 8+((int)$found-$max_ads);
}
else
{
	//$max_ads = 12;
}

$file_name = $ab_path.'cache/marktplatz/start_handelsplatz_neu.'.$s_lang.'.htm';
$file_name_pager = $ab_path.'cache/marktplatz/start_handelsplatz_pager.'.$s_lang.'.htm';
$file = @file_get_contents($file_name);
if(!$file || $rewrite_new == true)
{
	$liste = $db->fetch_table("
		SELECT
			SQL_CALC_FOUND_ROWS
			a.*,
			a.ID_AD_MASTER as ID_AD,
			a.BESCHREIBUNG AS DSC,
			m.NAME AS MANUFACTURER,
			i.SRC_THUMB,
			i.SRC,
             a.TRADE AS product_trade,
			(SELECT sk.V1 FROM `string_kat` sk WHERE sk.FK=a.FK_KAT and sk.S_TABLE='kat'
    			AND sk.BF_LANG=if(sk.BF_LANG & ".$langval.", ".$langval.", 128) LIMIT 1) as KAT
		FROM
			`ad_master` a
		LEFT JOIN
			manufacturers m on a.FK_MAN=m.ID_MAN
		LEFT JOIN
			ad_images i on i.FK_AD=a.ID_AD_MASTER
			AND i.IS_DEFAULT = 1
		WHERE
			a.STATUS&3=1 AND (a.DELETED=0)
		ORDER BY
			a.STAMP_START DESC
		LIMIT
			".$max_ads);
	$all = $db->fetch_atom("SELECT FOUND_ROWS()");
	#echo ht(dump($lastresult));
	$tpl = new Template($ab_path.'tpl/'.$s_lang.'/cache_index_new_ads.htm');
	$tpl->isTemplateRecursiveParsable = TRUE;
	$tpl->addvars($tpl_main->vars);
	$tpl->addlist('new_ads', $liste, $ab_path.'tpl/'.$s_lang.'/marktplatz.row_box.htm','killbb');
	$write = $tpl->process();
	@file_put_contents($file_name, $write);
	@chmod($file_name, 0777);
	$tpl_content->addvar('new_ads', $tpl_content->process_text($write));

	### pager
	$pager = htm_browse($all, 1, '/alle-anzeigen,', 20);
	$tpl_content->addvar("pager", $pager);
	@file_put_contents($file_name_pager, $pager);
	@chmod($file_name_pager, 0777);
}
else
{
	$tpl_content->addvar('pager', @file_get_contents($file_name_pager));
	$tpl_content->addvar('new_ads', $tpl_content->process_text($file));
}
?>