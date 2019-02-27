<?php
/* ###VERSIONSBLOCKINLCUDE### */


$root = 1;
$path = (1 == $root ? '../design/' : '');
$design = $nar_systemsettings['SITE']['TEMPLATE'];
if ((!isset($_REQUEST['sel_lang']) || $_REQUEST['sel_lang'] == '')) {
    $sel_lang = $s_lang;
    $_REQUEST['sel_lang'] = $s_lang;
} else {
    $sel_lang = $_REQUEST['sel_lang'];
}


 if(count($_POST))
 {
   $err=array();
   $tpl_content->addvars($_POST);
   /*jan - 02.02.07
	 if(empty($_POST['FK_MODUL']))
     $_POST['FK_MODUL'] = NULL;
	
	ende */
   if(empty($_POST['SYS_NAME']))
     $err[] = "Bitte System- Namen angeben";
   if(empty($_POST['V1']))
     $err[] = "Bitte Betreff der mail angeben!";
   if(empty($_POST['T1']))
     $err[] = "Bitte Mailtext angeben!";
   if(count($err))
     $tpl_content->addvar("err", implode("<br />", $err));
   else
   {
     $id = $db->update("mailvorlage", $_POST);
	 if(empty($_POST['ID_MAILVORLAGE']))
	   $tpl_content->addvar("ID_MAILVORLAGE", $id);
     // Save HTML
     if ($root == 1) {
       $filename = $ab_path . 'design/'. $design . '/' . $sel_lang . '/mail/' . $_REQUEST['SYS_NAME'] . '.htm';

       if(!is_dir($ab_path . 'design/'. $design)) { mkdir($ab_path . 'design/'. $design); chmod($ab_path . 'design/'. $design, 0777); }
       if(!is_dir($ab_path . 'design/'. $design . '/' . $sel_lang)) { mkdir($ab_path . 'design/'. $design . '/' . $sel_lang); chmod($ab_path . 'design/'. $design . '/' . $sel_lang, 0777); }
       if(!is_dir($ab_path . 'design/'. $design . '/' . $sel_lang . '/mail')) { mkdir($ab_path . 'design/'. $design . '/' . $sel_lang . '/mail'); chmod($ab_path . 'design/'. $design . '/' . $sel_lang . '/mail', 0777); }
     } else {
       $filename = $path . 'tpl/' . $sel_lang . '/' . $_REQUEST['SYS_NAME'] . '.htm';
     }

     if(!($_POST['HTML'] == "" && !file_exists($filename))) {
       /*
       $arMatches = array();
       if (preg_match_all("/<img[^<>]+src=('[^']+'|\"[^\"]+\")[^<>]+>/", $_POST['HTML'], $arMatches)) {
           $urlBase = $GLOBALS['nar_systemsettings']['SITE']['SITEURL'].$GLOBALS['nar_systemsettings']['SITE']['BASE_URL'];
           foreach ($arMatches[1] as $imageIndex => $imageUrlRaw) {
               $imageUrl = trim($imageUrlRaw, '"\'');
               if (strpos($imageUrl, $urlBase) === 0) {
                   $urlRelative = substr($imageUrl, strlen($urlBase));
                   var_dump($urlRelative);
               } else {
                   var_dump($imageUrl);
               }
           }
           die(var_dump($arMatches));
       }
       */

       file_put_contents($filename, $_POST['HTML']);

       $cacheTemplate = new CacheTemplate();
       $cacheTemplate->cacheFile('mail/' . (($sel_lang == 'default')?'de':$sel_lang) . '/' . $_REQUEST['SYS_NAME'] . '.htm');
     }
   }
 }
 else
 {
   if(!empty($_REQUEST['ID_MAILVORLAGE']))
   {
    $ar = $db->fetch1("select t.*,s.V1,s.T1
        from `mailvorlage` t
        left join string_mail s on s.S_TABLE='mailvorlage' and s.FK=t.ID_MAILVORLAGE
            and s.BF_LANG=if(t.BF_LANG_MAIL & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_MAIL+0.5)/log(2)))
        left join modul m on FK_MODUL=ID_MODUL
        where ID_MAILVORLAGE=".$_REQUEST['ID_MAILVORLAGE']);
        $tpl_content->addvars($ar);

    // Get HTML-Template
    if($root == 1) {
       $filename = $path . $design . '/'. $sel_lang .'/mail/' . $ar['SYS_NAME'] . '.htm';
    } else {
       $filename = $path . 'mail/' . $sel_lang . '/' . $ar['SYS_NAME'] . '.htm';
    }

    $file = @file_get_contents($filename);
    $tpl_content->addvar('filename', $filename);
    $tpl_content->addvar('HTML', $file);
   }
 }


$selectedNotificationId = ($ar['FK_MAILVORLAGE_NOTIFICATION_GROUP'])?$ar['FK_MAILVORLAGE_NOTIFICATION_GROUP']:$_POST['FK_MAILVORLAGE_NOTIFICATION_GROUP'];
$liste_notification_groups = $db->fetch_table($q = "
	SELECT
		g.*,
		'".$selectedNotificationId."' as SELECTED_NOTIFICATION_GROUP
	FROM mailvorlage_notification_group g
	ORDER BY g.DESCRIPTION
");

$tpl_content->addlist("notification_groups", $liste_notification_groups, "tpl/de/emailvorlage_edit.row_notification_group.htm");

?>
