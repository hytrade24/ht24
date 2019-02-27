<?php
/* ###VERSIONSBLOCKINLCUDE### */



 ### CHANGES
 
 /*
   31.01.2008 [Schmalle] Code für Templates direkt auf der Seite anzeigen
 */


function show_code(&$row, $i)
{ global $db,$tpl_content;
    $row['CODE'] = "{content_page(".stdHtmlentities($row['V1']).")}";

    $ar = $db->fetch_table("select l.ABBR from string_info s
	 left join lang l on l.BITVAL=s.BF_LANG
	 where
	  S_TABLE='infoseite' and FK=".$row['ID_INFOSEITE']);

    for($k=0; $k<count($ar); $k++)
        $row['langs'] .= '<img src="'.$tpl_content->tpl_uri_baseurl('/gfx/lang.'.$ar[$k]['ABBR'].'.gif').'"> ';

} // show_code()

 $tpl_content->addvar('V2', $s_lang);

 if(count($_POST))
 {
  include_once "sys/lib.cache.php";
  $tpl_content->addvars($_POST);
  if(empty($_POST['V1']))
    $err[] = "Bitte geben Sie der Infoseite einen Namen!";
  if(count($err))
    $tpl_content->addvar("err", $err);
  else
  {
   $id = $db->update("infoseite", $_POST);
   $tpl_content->addvar('msg', 'Speichervorgang erfolgreich');
   update_infocache();
   
   forward("index.php?page=infoseiten_edit&ID_INFOSEITE=".$id);
  }  
 }
 else
 {
  if(isset($_REQUEST['ID_INFOSEITE']))
  {
   #echo $db->lang_select("infoseite");
   $ar = $db->fetch1("select t.*, s.V1, s.V2, s.T1 from `infoseite` t left join string_info s on s.S_TABLE='infoseite' and s.FK=t.ID_INFOSEITE and s.BF_LANG=if(t.BF_LANG_INFO & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_INFO+0.5)/log(2))) 
      left join nav n on t.IDENT=n.IDENT and n.ROOT=1
	 where ID_INFOSEITE=".$_REQUEST['ID_INFOSEITE']);
   
    if ($ar['TXTTYPE']=='HTML')
        $ar['bTXTTYPE']=1;
     else
        $ar['bTXTTYPE']=0;
        
    if ($ar['USETYPE']=='STD')
        $ar['bUSETYPE']=1;
     else
        $ar['bUSETYPE']=0;

   $tpl_content->addvars($ar);
    
   $tpl_content->addvar("CODE", "{content_page(".stdHtmlentities($ar['V1']).")}");

      $tpl_content->addlist("seiten",$db->fetch_table("select t.ID_INFOSEITE,t.B_SYS,t.TXTTYPE, s.V1, s.V2, s.T1
from `infoseite` t
left join string_info s on s.S_TABLE='infoseite' and s.FK=t.ID_INFOSEITE and s.BF_LANG=if(t.BF_LANG_INFO & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_INFO+0.5)/log(2)))
where t.LU_INFO_BEREICHE='".$ar['LU_INFO_BEREICHE']."'
ORDER BY s.V1") ,
          "tpl/de/infoseiten_edit.row.htm","show_code");

  } 
  
  else {
    $tpl_content->addvar("bTXTTYPE", 1);
    $tpl_content->addvar("bUSETYPE", 1);
  }
 }

?>
