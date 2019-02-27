<?php
/* ###VERSIONSBLOCKINLCUDE### */



$ids='';
$ar_id = $db->fetch_table("select ID_MODUL from modul where IDENT='news' or IDENT='news_adv'"); 
$tmp=array();
for($i=0;$i<count($ar_id); $i++)
  $tmp[] = $ar_id[$i]['ID_MODUL'];

$ids = implode(",", $tmp);

if(isset($_REQUEST['id']))
{
  $mod = $db->fetch1("select m.* from nav n
    left join modul m on FK_MODUL=ID_MODUL
	where ID_NAV=".$_REQUEST['id']);
  $tpl_content->addvar("edit", $_REQUEST['id']);
  include "../module/".$mod['IDENT']."/ar_skins.php";
}

$ar_modtype = array
(
  "news" => array("name" => "news", "value" => "News Standard"),
  "news_adv" => array("name" => "news_adv",  "value" => "News Advanced")
);
if(count($_POST))
{
#echo ht(dump($_POST));
  $check = $db->fetch_atom("select FK_NAV from modul2nav where FK_NAV =".$_REQUEST['FK_NAV']);
  if(!$check)
    $db->querynow("insert into modul2nav set FK_NAV=".$_REQUEST['FK_NAV'].",
	    FK=0,S_MODUL='".$_POST['S_MODUL']."',SKIN='".$_REQUEST['SKIN']."'"); //die(ht(dump($lastresult)));
  $db->querynow("update modul2nav set FK=".$_POST['ID_KAT'].",SKIN='".$_POST['SKIN']."' 
     ,S_MODUL='".$_POST['S_MODUL']."'
	 where FK_NAV=".$_REQUEST['FK_NAV']);
#echo ht(dump($lastresult));
}
$ar = $db->fetch_table("select m.*,k.ROOT as KROOT,t.*, s.V1, s.V2, s.T1, st.V1 as KATEGORIE
       ,mo.IDENT as S_MODUL from nav t
	   left join modul2nav m on t.ID_NAV=m.FK_NAV
	   left join modul mo on t.FK_MODUL=mo.ID_MODUL
	   left join kat k on k.ID_KAT=m.FK and ( S_MODUL='news' or S_MODUL='news_adv' )
	   left join string s on s.S_TABLE='nav' and s.FK=t.ID_NAV 
	     and s.BF_LANG=if(t.BF_LANG & 128, 128, 1 << floor(log(t.BF_LANG+0.5)/log(2)))
	   left join string_kat st on st.S_TABLE='kat' and st.FK=k.ID_KAT 
	     and st.BF_LANG=if(k.BF_LANG_KAT & 128, 128, 1 << floor(log(k.BF_LANG_KAT+0.5)/log(2)))	  
	  where FK_MODUL IN (".$ids.")");
#echo ht(dump($ar));
$tmp = array();
for($i=0; $i<count($ar); $i++)
{
  $tpl_tmp = new Template("tpl/de/news2nav.row.htm");
  if($ar[$i]['ID_NAV'] == $id)
  {
	$ar[$i]['ID_KAT']=$ar[$i]['FK'];
	if($ar[$i]['SKIN'])
	  $ar_modskins[$ar[$i]['SKIN']]['selected']="selected";
	if($ar[$i]['S_MODUL'])
	  $ar_modtype[$ar[$i]['S_MODUL']]['selected']="selected";
	$tpl_tmp->addlist("modskins", $ar_modskins, "tpl/de/modulskin.row.htm");
	$tpl_tmp->addlist("MODULTYPEN", $ar_modtype, "tpl/de/modulskin.row.htm");
  }
  $tpl_tmp->addvars($ar[$i]);  
  $tmp[] = $tpl_tmp;
}

$tpl_content->addvar("liste", $tmp);

?>
