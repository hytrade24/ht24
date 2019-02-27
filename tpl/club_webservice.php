<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once $ab_path.'sys/lib.ad_constraint.php';
require_once $ab_path."sys/lib.club.php";
$clubManagement = ClubManagement::getInstance($db);

$ar_settings = $db->fetch1("
	select
		*
	from
		club_shop us
	join
		club c on us.FK_CLUB=c.ID_CLUB
		and c.STATUS=1
	where
		us.HASH='".mysql_real_escape_string($_REQUEST['id'])."'");

if(!empty($ar_settings)) {
	#die(ht(dump($nar_systemsettings)));
	$tpl_content->addvar("URI", $tpl_content->tpl_uri_baseurl_full('/'));
	$tpl_content->addvar("SITENAME", $nar_systemsettings['SITE']['SITENAME']);
	$tpl_content->addvars($ar_settings);


	### Artikel
	$npage = ((int)$_REQUEST['n'] ? (int)$_REQUEST['n'] : 1);
	$perpage = $ar_settings['PERPAGE'];
	$limit = ($perpage*$npage)-$perpage;

	$ar_ads = $clubManagement->getAdsByClubId($ar_settings['FK_CLUB'], 0, $langval, $npage, $perpage);
	Rest_MarketplaceAds::extendAdDetailsList($ar_ads);

	$all = $db->fetch_atom("SELECT FOUND_ROWS()");

	$tpl_content->isTemplateRecursiveParsable = TRUE;
	$tpl_content->isTemplateCached = TRUE;
	$tpl_content->addlist('USER_ADS', $ar_ads, $ab_path.'tpl/'.$s_lang.'/club_webservice.row.htm', 'callback_misc_killbb');

	$pager = htm_browse($all, $npage, "index.php?page=club_webservice&frame=shop&id=".$_REQUEST['id']."&n=", $perpage);
	$tpl_content->addvar("pager", $pager);
} else {
	die("<p>Unable to load shop (".stdHtmlentities($_REQUEST['id']).")");
}

?>
