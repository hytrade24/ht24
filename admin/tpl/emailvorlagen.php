<?php

global $sel_lang;
if ((!isset($_REQUEST['sel_lang']) || $_REQUEST['sel_lang'] == '')) {
    $sel_lang = $s_lang;
    $_REQUEST['sel_lang'] = $s_lang;
} else {
    $sel_lang = $_REQUEST['sel_lang'];
}

/* ###VERSIONSBLOCKINLCUDE### */
 function show_code(&$row, $i)
 {
    global $db,$tpl_content, $sel_lang;

    $ar = $db->fetch_table("select l.ABBR from string_mail s
	  left join lang l on l.BITVAL=s.BF_LANG
	  where
	  S_TABLE='mailvorlage' and FK=".$row['ID_MAILVORLAGE']);

    for($k=0; $k<count($ar); $k++)
      $row['langs'] .= '<img src="'.$tpl_content->tpl_uri_baseurl('/gfx/lang.'.$ar[$k]['ABBR'].'.gif').'"> ';


    // Get HTML-Template
    $filename = $GLOBALS['ab_path']."design/".$GLOBALS['nar_systemsettings']['SITE']['TEMPLATE'].'/'.$sel_lang.'/mail/'.$row['SYS_NAME'].'.htm';
    $row['HTML_AVAILABLE'] = (file_exists($filename) ? 1 : 0);
 }


 if($_REQUEST['do'] == "del")
   $db->delete("mailvorlage", $_REQUEST['ID_MAILVORLAGE']);

 $liste = $db->fetch_table("select t.*,m.LABEL as MODNAME, s.V1
   from `mailvorlage` t
    left join string_mail s on s.S_TABLE='mailvorlage' and s.FK=t.ID_MAILVORLAGE
	  and s.BF_LANG=if(t.BF_LANG_MAIL & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_MAIL+0.5)/log(2)))
    left join modul m on FK_MODUL=ID_MODUL order by FK_MODUL,SYS_NAME
	");

 $tpl_content->addlist("liste", $liste, "tpl/de/emailvorlagen.row.htm","show_code");

?>
