<?php
/* ###VERSIONSBLOCKINLCUDE### */



$query_base = "
		SELECT
			u.*,
			(SELECT count(*) FROM `advertisement_kat` k WHERE k.FK_ADVERTISEMENT_USER=u.ID_ADVERTISEMENT_USER)
				AS NUM_KATS,
			((DATEDIFF(STAMP_END,STAMP_START)+1) * u.PRICE) as PRICE_SUM,
			s.V1 as AD_NAME 
		FROM
			`advertisement_user` u
		LEFT JOIN
			`advertisement` a ON
			a.ID_ADVERTISEMENT=u.FK_ADVERTISEMENT
		LEFT JOIN
			`string_advertisement` s ON
			s.S_TABLE='advertisement' AND s.FK=a.ID_ADVERTISEMENT AND
			s.BF_LANG=if(a.BF_LANG_ADVERTISEMENT & ".$langval.", ".$langval.", 1 << floor(log(a.BF_LANG_ADVERTISEMENT+0.5)/log(2)))
		WHERE";

$ar_ads = $db->fetch_table($query_base."
			u.FK_USER=".$uid." AND u.DONE=1
		ORDER BY
			u.STAMP_START");

$tpl_content->addlist("liste", $ar_ads, "tpl/".$s_lang."/advertisement_list.row.htm");

?>