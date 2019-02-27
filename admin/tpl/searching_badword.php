<?php
/* ###VERSIONSBLOCKINLCUDE### */

  
$perpage=50;
if ($_POST['do']=='add_badword') 
{
	$words = array();
	$words = explode ("\n", $_POST['words']);
	
	  foreach($words as $i=>$row)
	   {
	   	$row=trim(strtolower($row));
	    if ($row<>'')
		  $db->querynow("insert into searchdb_badword_".$s_lang." (badword) values ('".$row."')");
	   }
} 

if ($_REQUEST['do']=='del') 
{
	$db->querynow("delete from searchdb_badword_".$s_lang." where ID_BADWORD =".$_REQUEST['ID_BADWORD']);
}
  $all = $db->fetch_atom("select count(*) from  searchdb_badword_".$s_lang."");
  
  $limit = ((($_REQUEST['npage'] ? $_REQUEST['npage'] : $_REQUEST['npage']=1)-1)*$perpage);
$ar_data = $db->fetch_table('select  * from searchdb_badword_'.$s_lang.' order by badword LIMIT '.$limit.','.$perpage);
  

  $tpl_content->addvar("npage",$_REQUEST['npage']);
  $tpl_content->addvar("all",$all);
  $tpl_content->addvar("pager", htm_browse($all, $_REQUEST['npage'], "index.php?page=".$tpl_content->vars['curpage']."&npage=", $perpage));
  $tpl_content->addlist('liste', $ar_data, 'tpl/de/searching_badword.row.htm');
  
   ?>