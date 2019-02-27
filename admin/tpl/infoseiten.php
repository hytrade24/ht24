<?php
/* ###VERSIONSBLOCKINLCUDE### */

if ($_REQUEST['SHOWTYPE'] == 1 ) 
{
      $SHOWTYPE='KATS';
      $tpl_content->addvar('SHOWTYPE',1);
      $inuse="(select count(*) from kat where FK_INFOSEITE = ID_INFOSEITE group by FK_INFOSEITE)";
}
else    
{
     $SHOWTYPE='STD';
     $inuse="(select count(*) from nav where FK_INFOSEITE = ID_INFOSEITE group by FK_INFOSEITE)";
    $tpl_content->addvar('SHOWTYPE',0);
 }
 
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

 if(isset($_REQUEST['delete']))
 {
  include_once "sys/lib.cache.php";
  $id = $db->fetch_atom("select ID_KAT from kat where FK_INFOSEITE=".$_REQUEST['delete']);
  if(!empty($id))
    $tpl_content->addvar("err", "Infoseite konnte nicht gelöscht werden, da sie mindestens einer Kategorie zugeordnet wurde");
  else
  {
	### Zuordnungen aus der Nav Tabelle killen
	$query = "update nav set FK_INFOSEITE=NULL where FK_INFOSEITE=".$_REQUEST['delete'];
	$db->querynow($query);
	require_once 'sys/lib.cache.php';
	$NAVDATE_tmp = time();
	$db->putinto_tmp('NAVDATE',$NAVDATE_tmp);

	### Infoseite l�schen
	$db->delete("infoseite", $_REQUEST['delete']);
	$db->querynow("update nav set FK_INFOSEITE=0 where FK_INFOSEITE=".$_REQUEST['delete']);
  }
  update_infocache();
 }

 if(isset($_GET['from']) and $_GET['ID_INFOSEITE'] >0 )
 {
    if ($_GET['from']=='TXT')
        $to='HTML';
    else
        $to='TXT';

    $query = "UPDATE infoseite SET TXTTYPE='".$to."' WHERE  ID_INFOSEITE=".$_GET['ID_INFOSEITE'];
    $db->querynow($query);
     $tpl_content->addvar("ok","Einstellung geändert");
     $tpl_content->addvar("okID",$_GET['ID_INFOSEITE']);
 }

$tpl_content->addvar("SHOWTYPE",$_REQUEST['SHOWTYPE']);
if ($_REQUEST['LU_INFO_BEREICHE'] > 0)
    $where_BEREICH=" and LU_INFO_BEREICHE=".$_REQUEST['LU_INFO_BEREICHE'];
$tpl_content->addvar("LU_INFO_BEREICHE",$_REQUEST['LU_INFO_BEREICHE']);


$inunser="(select count(*) from nav where FK_INFOSEITE = ID_INFOSEITE group by FK_INFOSEITE)";

 $tpl_content->addlist("seiten",$db->fetch_table("select t.ID_INFOSEITE,t.B_SYS,t.TXTTYPE, s.V1, s.V2, s.T1, ".$inuse." as inuse
from `infoseite` t
left join string_info s on s.S_TABLE='infoseite' and s.FK=t.ID_INFOSEITE and s.BF_LANG=if(t.BF_LANG_INFO & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_INFO+0.5)/log(2)))
where t.USETYPE='".$SHOWTYPE."' ".$where_BEREICH."
ORDER BY s.V1") ,
    "tpl/de/infoseiten.row.htm","show_code");

?>