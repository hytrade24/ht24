<?php
/* ###VERSIONSBLOCKINLCUDE### */

$newsManagement = Api_NewsManagement::getInstance($db);

include_once ("module/news_adv/ini.php");
#$page = (int)$ar_params[1];
  // TPL Init
  $tpl_mode = new Template("module/tpl/".$s_lang."/newsarchiv.htm");

  if (!($n_page=(int)$ar_params[4]))
    $n_page = 1;
  //echo ht(dump($ar_modul));
  $n_limit = $ar_modul['INT_LIMIT'];
  $n_ofs = ($n_page * $n_limit)-$n_limit;
  if (!($fk_kat = (int)$ar_params[1]))
    $fk_kat = (int)$ar_modul['FK'];
    $s_kat_label = $db->fetch_atom("select s.V1 from `kat` t
	  left join string_kat s on s.S_TABLE='kat'
	  and s.FK=t.ID_KAT
	  and s.BF_LANG=if(t.BF_LANG_KAT & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_KAT+0.5)/log(2)))
	  where ID_KAT=". $fk_kat);


//Ident lesen für die zurodnung der Seite




  $s_id_kat = implode(', ', $db->fetch_nar("select k.ID_KAT, k.ID_KAT
    from kat k, kat p
    where p.ID_KAT=". $fk_kat. "   and p.ROOT=2 and k.ROOT=2 and k.LFT between p.LFT and p.RGT"));

  $htm_browse = htm_browse(
    $db->fetch_atom('select count(*) from news where OK=3 and FK_KAT in ('. $s_id_kat. ')'),
    $n_page, '/'.$tpl_content->vars['ident_path'].'/'.$ar_params[0]. ','. $fk_kat. ','
      . $ar_params[2]. ',archiv,', $n_limit,$ar_modul_option['pages']);


  $news = $db->fetch_table("select IDENT,FK_NAV,t.*, s.V1, s.V2, s.T1,NAME as AUTORUNAME,concat(VORNAME,' ',NACHNAME) as AUTOR, NAME from news t
      left join string_c s on s.S_TABLE='news' and s.FK=t.ID_NEWS and s.BF_LANG=if(t.BF_LANG_C &  ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_C+0.5)/log(2)))
      left join user on ID_USER=FK_AUTOR
      left join modul2nav n on t.FK_KAT=n.FK and S_MODUL='news_adv' and n.DARSTELLUNG = 'news'
	  left join nav nn on FK_NAV=ID_NAV
      where OK=3 and FK_KAT in (". $s_id_kat. ")
  		GROUP BY t.ID_NEWS
		order by STAMP desc ,ID_NEWS DESC
		limit ". $n_ofs. ",". $n_limit);

//$news = $db->fetch_table("select * from news where OK=3 order by STAMP desc ,ID_NEWS DESC limit  ".$Startat.",".$MaxRow);

//echo "select * from news where OK=3 order by STAMP desc limit  ".$Startat.",".$MaxRow;

  $ym = '0000-00';
  $ar_liste = array ();
  foreach($news as $i=>$row)
  {
    $row['PREVIEW_TYPE'] = false; 
    $row['PREVIEW_TYPE_IMAGE'] = false; 
    $row['PREVIEW_TYPE_VIDEO'] = false; 
		$arPreviewElement = Api_NewsManagement::getInstance($db)->getPreviewElementData($row);
		if (is_array($arPreviewElement)) {
			$row = array_merge($row, array_flatten($arPreviewElement, true, "_", "PREVIEW_"));
		}
    
    $row['even']=$i%2;
	$ym_now = substr($row['STAMP'],0,7);
    $tpl_tmp = new Template('module/tpl/'. $s_lang. '/newsarchiv.row.htm', 'news');
    $tpl_tmp->addvar('i', $i);
    $tpl_tmp->addvars($row);
    $tpl_tmp->addvar("URL", $newsManagement->generateNewsUrl($row));
    if ($ym != $ym_now)
    {
      $ym = $ym_now;
      $tpl_tmp->addvar('monat', monthstr(substr($ym_now, 5)). ' '. substr($ym_now, 0, 4));
    }
    $ar_liste[] = $tpl_tmp;
  }
  $tpl_mode->addvar('liste', $ar_liste);
  $tpl_mode->addvar('browse', $htm_browse);
  $tpl_mode->addvar('KAT_LABEL', $s_kat_label);
  $tpl_modul->addvar("MODECODE", $tpl_mode);



//kat tree
?>