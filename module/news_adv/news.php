<?php
/* ###VERSIONSBLOCKINLCUDE### */

$newsManagement = Api_NewsManagement::getInstance($db);

// TPL Init
$tpl_mode = new Template("module/tpl/" . $s_lang . "/news.htm");
$data = array();

/* Start Browser */
$npage = ((int)$ar_params[6] ? $ar_params[6] : 1);
$perpage = $ar_modul['INT_LIMIT'];
$limit = (($perpage * $npage) - $perpage);
$showArtikel = false;

#die(ht(dump($ar_params)));

$where_fk = "";
$fk_kat = 0;
$kat_url = "";
$fk_kat = (int)($ar_params[4] ? $ar_params[4] : $ar_modul["FK"]);
if ($fk_kat) {

	$ar_kat = $db->fetch1("SELECT k.LFT, k.RGT, ks.V1, ks.V2, ks.T1 FROM kat k LEFT JOIN string_kat ks on ks.S_TABLE='kat'
					and ks.FK=k.ID_KAT
					and ks.BF_LANG=if(k.BF_LANG_KAT & " . $langval . ", " . $langval . ", 1 << floor(log(k.BF_LANG_KAT+0.5)/log(2))) WHERE k.ID_KAT = '".(int)$fk_kat."'");
    
    if (empty($ar_kat)) {
      header("HTTP/1.0 404 Not Found");
      $tpl_content->LoadText("tpl/".$s_lang."/404.htm");
      return;
    }
	
	if ($ar_kat['LFT'] > 1) {
		//$where_fk = " AND FK_KAT=" . $fk_kat;
		$kat_url = $ar_params[5];
		$katname = $ar_kat["V1"];
		$ar_modul['LFT'] = $ar_kat['LFT'];
		$ar_modul['RGT'] = $ar_kat['RGT'];
		$tpl_mode->addvar('ID_KAT', $fk_kat);
		$tpl_mode->addvar('KATNAME', $katname);
		$tpl_content->addvar('ID_KAT', $fk_kat);
		$tpl_content->addvar('KATNAME', $katname);
	}

    if (!empty($ar_kat['T1'])) {
        $tpl_main->vars['metatags'] = $ar_kat['T1'];
    }
}

if ($id = (int)$ar_params[1]) {
	$findrow = $db->fetch1('SELECT NEWSNUMBER,CEIL(NEWSNUMBER/' . $perpage . ') as pagenumber
		FROM news WHERE OK=3 AND ID_NEWS=' . $id . $where_fk);

	$npage = $findrow['pagenumber'];

	//echo $npage;
	$perpage = $ar_modul['INT_LIMIT'];
	$limit = (($perpage * $npage) - $perpage);

	$res = $db->querynow("select t.*, s.V1, s.V2, s.T1,u.NAME,
			concat(m.VORNAME,' ',m.NACHNAME) as AUTOR, m.NAME as AUTORUNAME,
			m.CACHE,
			k.ID_KAT, ks.V1 as KATNAME,
			nav.IDENT
			from `news` t
			left join string_c s on s.S_TABLE='news' and s.FK=t.ID_NEWS and s.BF_LANG=if(t.BF_LANG_C & " . $langval . ", " . $langval . ", 1 << floor(log(t.BF_LANG_C+0.5)/log(2)))
			LEFT JOIN user u ON FK_USER=u.ID_USER
			LEFT JOIN kat k ON FK_KAT=ID_KAT
			left join
			string_kat ks on ks.S_TABLE='kat'
				and ks.FK=k.ID_KAT
				and ks.BF_LANG=if(k.BF_LANG_KAT & " . $langval . ", " . $langval . ", 1 << floor(log(k.BF_LANG_KAT+0.5)/log(2)))" . "

			LEFT JOIN user m ON t.FK_AUTOR=m.ID_USER
			LEFT JOIN modul2nav m2n on t.FK_KAT=m2n.FK and S_MODUL='news_adv' and m2n.DARSTELLUNG = 'news'
			LEFT JOIN nav on m2n.FK_NAV=nav.ID_NAV
			WHERE OK=3
			" . $where_fk . "
			AND NEWSNUMBER >=" . $findrow['NEWSNUMBER'] . "
			AND ( k.LFT >= " . $ar_modul['LFT'] . " AND k.RGT <= " . $ar_modul['RGT'] . " )
			GROUP BY t.ID_NEWS
			ORDER BY STAMP DESC ,ID_NEWS DESC LIMIT " . $perpage);


	while ($row = mysql_fetch_assoc($res['rsrc'])) {
		$row['PREVIEW_TYPE'] = false; 
		$row['PREVIEW_TYPE_IMAGE'] = false; 
		$row['PREVIEW_TYPE_VIDEO'] = false; 
		$arPreviewElement = Api_NewsManagement::getInstance($db)->getPreviewElementData($row);
		if (is_array($arPreviewElement)) {
			$row = array_merge($row, array_flatten($arPreviewElement, true, "_", "PREVIEW_"));
		}
		if ($row['IMG'] == NULL) {
			$row['IMG'] = "uploads/images/kat/" . $row['FK_KAT'] . ".jpg";
			$row['IMGW'] = "auto";
			$row['IMGH'] = "auto";
		}
		$data[] = $row;
	}

	// Dem Template "sagen", dass definitiv ein Artikel ausgewählt wurde
	// Wird für verschiedene Layouts benötigt!
	$tpl_mode->addvar("artikel_show", 1);
	$showArtikel = true;

} else {

	$tpl_mode->addvar("artikel_show", 0);
	/*gel�scht berni 31.01.09
		$all = $db->fetch_atom("select t.*, s.V1, s.V2, s.T1, u.NAME,LFT, concat(m.VORNAME,' ',m.NACHNAME) as AUTOR , m.NAME as AUTORUNAME  from `news` t
				left join string_c s on s.S_TABLE='news' and s.FK=t.ID_NEWS and s.BF_LANG=if(t.BF_LANG_C & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_C+0.5)/log(2)))".'
				LEFT JOIN user u ON FK_USER=ID_USER
				LEFT JOIN kat k ON FK_KAT=ID_KAT
				LEFT JOIN user m ON t.FK_AUTOR=m.ID_USER
				WHERE OK=3 AND
				( k.LFT >= '.$ar_modul['LFT'].' AND k.RGT <= '.$ar_modul['RGT'].' )
				ORDER BY STAMP DESC ,ID_NEWS DESC');
		*/


	$res = $db->querynow($query = "
		select SQL_CALC_FOUND_ROWS
			t.*, s.V1, s.V2, s.T1, u.NAME,k.LFT,
			concat(m.VORNAME,' ',m.NACHNAME) as AUTOR ,
			m.NAME as AUTORUNAME, m.CACHE,
			ks.V1 as KATNAME,
			nav.IDENT
		from
			`news` t
		left join
			string_c s on s.S_TABLE='news'
				and s.FK=t.ID_NEWS
				and s.BF_LANG=if(t.BF_LANG_C & " . $langval . ", " . $langval . ", 1 << floor(log(t.BF_LANG_C+0.5)/log(2)))" . '
		LEFT JOIN
			user u ON FK_USER=ID_USER
		LEFT JOIN
			kat k ON FK_KAT=ID_KAT
		left join
			string_kat ks on ks.S_TABLE="kat"
				and ks.FK=k.ID_KAT
				and ks.BF_LANG=if(k.BF_LANG_KAT & ' . $langval . ", " . $langval . ", 1 << floor(log(k.BF_LANG_KAT+0.5)/log(2)))" . '
		LEFT JOIN
			user m ON t.FK_AUTOR=m.ID_USER
		LEFT JOIN modul2nav m2n on t.FK_KAT=m2n.FK and S_MODUL="news_adv" and m2n.DARSTELLUNG = "news"
	  	LEFT JOIN nav on m2n.FK_NAV=nav.ID_NAV
		WHERE
			OK=3
			' . $where_fk . '
			AND( k.LFT >= ' . $ar_modul['LFT'] . ' AND k.RGT <= ' . $ar_modul['RGT'] . ' )
			GROUP BY t.ID_NEWS
			ORDER BY STAMP DESC ,ID_NEWS DESC
			LIMIT ' . $limit . ', ' . $perpage);

	$all = $db->fetch_atom("
  		SELECT
  			FOUND_ROWS()");

	#die(ht(dump($lastresult)));

	while ($row = mysql_fetch_assoc($res['rsrc'])) {
		$row['PREVIEW_TYPE'] = false; 
		$row['PREVIEW_TYPE_IMAGE'] = false; 
		$row['PREVIEW_TYPE_VIDEO'] = false; 
		$arPreviewElement = Api_NewsManagement::getInstance($db)->getPreviewElementData($row);
		if (is_array($arPreviewElement)) {
			$row = array_merge($row, array_flatten($arPreviewElement, true, "_", "PREVIEW_"));
		}
		if ($row['IMG'] == NULL) {
			$row['IMG'] = "uploads/images/kat/" . $row['FK_KAT'] . ".jpg";
			$row['IMGW'] = "auto";
			$row['IMGH'] = "auto";
		}
		$data[] = $row;
	}
	$showArtikel = false;

	// Modul
	$modulNav = $db->fetch_atom("
		SELECT
			s.V1
		FROM nav n
		LEFT JOIN string s on s.S_TABLE='nav' AND s.FK = n.ID_NAV AND s.BF_LANG=if(n.BF_LANG & " . $langval . ", " . $langval . ", 1 << floor(log(n.BF_LANG+0.5)/log(2)))
		WHERE
			n.ID_NAV = '".$ar_modul['FK_NAV']."'
			AND n.IDENT != 'news'
	");
	if($modulNav != "") {
		$tpl_content->addvar('MODULNAVNAME', $modulNav);
	}

}

if ($data) {

	foreach ($data as $dataIndex => $dataRow) {
		$data[$dataIndex]["URL"] = $newsManagement->generateNewsUrl($dataRow);
	}

	if($showArtikel == true) {

		$data0 = array_shift($data);
		$id = $data0['ID_NEWS'];

		$nar_tplglobals['newstitle'] = $data0['V1'];

		
		if ($data0["LINKS"] == "<!-- Link 9999 -->") {
			$data0["LINKS"] = "";
		}
		
		// Vendor?
		require_once 'sys/lib.vendor.php';
		$vendorManagement = VendorManagement::getInstance($db);
		$isUserVendor = $vendorManagement->isUserVendorByUserId($data0['FK_AUTOR']);
		$tpl_content->addvar("USER_IS_VENDOR", $isUserVendor);

		### views

        $db->querynow("INSERT INTO newsview (FK_NEWS,STAMP,VIEWS,FK_USER) VALUES (".mysql_escape_string($id).",NOW(),1,".$data0['FK_AUTOR'].")
  							ON DUPLICATE KEY UPDATE VIEWS=VIEWS+1");
		$db->querynow("update news set hitcount=hitcount+1 where ID_NEWS=" . $id);

		// Kommentare neu
		include "ini.php";
		$tpl_content->addvar("comments_enabled", ($ar_modul_option['comment'] ? TRUE : FALSE));

		// verwandte anzeige (keywords)
		$ar_keys = $db->fetch_nar("SELECT FK_NEWS_KEY,FK_NEWS_KEY FROM news2key
			WHERE FK_NEWS=" . $id);
		$s_keys = implode(', ', $ar_keys);
		$s_where = "1";
		//$s_where = "(ID_NEWS>" . $id . " and STAMP<'" . $data0['STAMP'] . "') or (ID_NEWS<" . $id . " and STAMP<='" . $data0['STAMP'] . "')";
		$ar_news = $db->fetch_table
			($s_keys ? "
				select
					t.*, s.V1, s.V2, s.T1, count(*) as kwcount, nav.IDENT
				from `news` t
				left join string_c s on s.S_TABLE='news' and s.FK=t.ID_NEWS and s.BF_LANG=if(t.BF_LANG_C & " . $langval . ", " . $langval . ", 1 << floor(log(t.BF_LANG_C+0.5)/log(2)))
				LEFT JOIN modul2nav m2n on t.FK_KAT=m2n.FK and S_MODUL='news_adv' and m2n.DARSTELLUNG = 'news'
				LEFT JOIN nav on m2n.FK_NAV=nav.ID_NAV
				LEFT OUTER JOIN news2key k ON k.FK_NEWS=ID_NEWS WHERE k.FK_NEWS_KEY IN (" . $s_keys . ") AND (" . $s_where . ") AND t.OK = 3
				GROUP BY
					ID_NEWS
				ORDER BY kwcount DESC, STAMP DESC, ID_NEWS DESC
				limit 5"
			: "
				select
					t.*, s.V1, s.V2, s.T1, nav.IDENT
				from `news` t
				left join string_c s on s.S_TABLE='news' and s.FK=t.ID_NEWS and s.BF_LANG=if(t.BF_LANG_C & " . $langval . ", " . $langval . ", 1 << floor(log(t.BF_LANG_C+0.5)/log(2)))
				LEFT JOIN modul2nav m2n on t.FK_KAT=m2n.FK and S_MODUL='news_adv' and m2n.DARSTELLUNG = 'news'
				LEFT JOIN nav on m2n.FK_NAV=nav.ID_NAV
	  			WHERE
					(" . $s_where . ")
					AND t.OK = 3
				GROUP BY t.ID_NEWS
				ORDER BY STAMP DESC, ID_NEWS DESC
				limit 5
		");


		$tpl_mode->addlist('verwandt', $ar_news, 'module/tpl/' . $s_lang . '/news.ref.htm');
		$tpl_mode->addvars($data0);
	}

	$tpl_mode->addlist('liste', $data, 'module/tpl/' . $s_lang . '/news.row.htm');

	$pager = htm_browse_extended($all, $npage, $tpl_content->vars['curpage'].",,,,".$fk_kat.",".$tpl_content->vars['curpage'].",{PAGE}", $perpage, 6);
	$tpl_content->addvar("npage", $npage);
	$tpl_mode->addvar("pager", $pager);
}
$tpl_mode->addvar("all", $all);
$tpl_modul->addvar("MODECODE", $tpl_mode);
?>