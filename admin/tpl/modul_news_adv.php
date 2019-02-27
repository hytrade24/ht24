<?php
/* ###VERSIONSBLOCKINLCUDE### */



$tpl_content->addvar("IDM", $id = $db->fetch_atom("select ID_MODUL from modul where IDENT='news_adv'")); 

### Alle Seiten mit diesem Modul als Liste
$tpl_content->addlist("liste", $ar=$db->fetch_table($db->lang_select("nav")."
   where FK_MODUL=".$id), "tpl/de/modul_news_adv.row.htm");

//zeigt baum an wenn keine zuweisung für die Sitemap getroffen wurde!
if (!$ar) 
{ 
	require_once 'sys/lib.nestedsets.php'; // Nested Sets
	$root=1;
	$nest = new nestedsets('nav', $root, 1);
	$res = $nest->nestSelect('', '', ((int)!$nest->tableLock). ' as no_move,', true);
	$ar = $db->fetch_table($res);
	$top = $db->fetch_atom("select ID_NAV from nav where ROOT=". $root. " and LFT=1");
	$tpl_content->addvar('ID_NAV_ROOT', $top); 
	$tpl_content->addvar('baum', tree_show_nested($ar, 'tpl/de/nav_edit.row.htm',NULL,false));
}

if(isset($_REQUEST['id']))
{
  $mod = $db->fetch1("select m.* from nav n
    left join modul m on FK_MODUL=ID_MODUL
	where ID_NAV=".$_REQUEST['id']);
  $tpl_content->addvar("edit", $_REQUEST['id']);
}

if(count($_POST))
{
#echo ht(dump($_POST));
  $check = $db->fetch_atom("select FK_NAV from modul2nav where FK_NAV =".$_REQUEST['FK_NAV']);
  if(!$check)
    $db->querynow("insert into modul2nav set FK_NAV=".$_REQUEST['FK_NAV'].",
	    FK=0,S_MODUL='".$_POST['S_MODUL']."',SKIN='".$_REQUEST['SKIN']."'"); //die(ht(dump($lastresult)));
  $db->querynow("update modul2nav set FK=".$_POST['ID_KAT']." 
     ,S_MODUL='news_adv',SKIN=NULL,INT_LIMIT=".(int)$_POST['INT_LIMIT']."
	 where FK_NAV=".$_REQUEST['FK_NAV']);
#echo ht(dump($lastresult));
}
$ar = $db->fetch_table("select m.*,k.ROOT as KROOT,t.*, s.V1, s.V2, s.T1, st.V1 as KATEGORIE
       ,mo.IDENT as S_MODUL,k.ID_KAT from nav t
	   left join modul2nav m on t.ID_NAV=m.FK_NAV
	   left join modul mo on t.FK_MODUL=mo.ID_MODUL
	   left join kat k on k.ID_KAT=m.FK and ( S_MODUL='news' or S_MODUL='news_adv' )
	   left join string s on s.S_TABLE='nav' and s.FK=t.ID_NAV 
	     and s.BF_LANG=if(t.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG+0.5)/log(2)))
	   left join string_kat st on st.S_TABLE='kat' and st.FK=k.ID_KAT 
	     and st.BF_LANG=if(k.BF_LANG_KAT & ".$langval.", ".$langval.", 1 << floor(log(k.BF_LANG_KAT+0.5)/log(2)))	  
	  where FK_MODUL = ".$id);
#echo ht(dump($ar));
$tmp = array();
include "module/news_adv.php";
for($i=0; $i<count($ar); $i++)
{
  $ar[$i]['DARSTELLUNG'] = (empty($ar[$i]['DARSTELLUNG']) ? NULL : $ar_darstellung[$ar[$i]['DARSTELLUNG']]);
  $tpl_tmp = new Template("tpl/de/modul_news_adv.row.htm");
    $tpl_tmp->addvar('i',$i);  
  $tpl_tmp->addvars($ar[$i]);  
  $tmp[] = $tpl_tmp;
}

$tpl_content->addvar("liste", $tmp);

?>
