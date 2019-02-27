<?php
/* ###VERSIONSBLOCKINLCUDE### */



$check = false;

if((int)$_REQUEST['FK_SOLD'])
{
	$ar_check = $db->fetch1("
		SELECT
			FK_USER,
			FK_USER_VK
		FROM
			ad_sold
		WHERE
			ID_AD_SOLD=".(int)$_REQUEST['FK_SOLD']);
	if($ar_check['FK_USER'] == $uid || $ar_check['FK_USER_VK'] == $uid)
	{
		$check = true;
	}
}
if($check)
{
	$tpl_content->addvars($_REQUEST);
	$ar_data = $db->fetch1("
		SELECT
			`NAME` AS `USER`,
			FIRMA,
			VORNAME,
			NACHNAME,
			STRASSE,
			PLZ,
			ORT,
			s.V1 AS LAND,
			TEL,
			FAX,
			MOBIL,
			EMAIL
		FROM
			`user`
		left join
			string s on s.S_TABLE='country'
			and s.FK=user.FK_COUNTRY
			and s.BF_LANG=if(".$langval." & ".$langval.", ".$langval.", 1 << floor(log(".$langval."+0.5)/log(2)))
		WHERE
			ID_USER=".(int)$_REQUEST['FK_USER']);
	foreach($ar_data as $key => $value)
	{
		$tpl_content->addvar($key, $value);
	}
}


?>