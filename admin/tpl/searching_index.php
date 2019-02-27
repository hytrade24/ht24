<?php
/* ###VERSIONSBLOCKINLCUDE### */

global $delme;
$delme = array();
  
function format_size(&$row, $i) 
{ 
global $delme;
	if($row['Data_length'] >= 1073741824) { $row['Data_length']= round(($row['Data_length'] / 1073741824), 2) . "GB"; } 
	elseif($row['Data_length'] >= 1048576) { $row['Data_length']= round(($row['Data_length'] / 1048576), 2) . "MB"; } 
	elseif($row['Data_length'] >= 1024) { $row['Data_length']= round(($row['Data_length'] / 1024), 2) . " KB"; } 
	else { $row['Data_length']= $row['Data_length'] . " Byte"; } 
    
    if (substr ($row['Name'],0,14) =='searchdb_index' ) {
        $delme[]=$row;
    }
} // format_size()

$tpl_content->addvar('slang', $s_lang);

if ($_REQUEST['do']=='build_index') 
{
  $tpl_content->addvars($_REQUEST);  
  $tpl_content->addvar('indexing', 1);
}
else 
{
  $liste = $db->fetch_table("SHOW TABLE STATUS LIKE 'searchdb_%'",'Name');
  if (($_REQUEST['tablename']<>'') and ($_REQUEST['do']=='truncate')) 
  {

    $dellang =  substr ($_REQUEST['tablename'],15);
    #$db->querynow("truncate searchdb_badword_".$dellang);
    $db->querynow("delete from searchdb_index_".$dellang." where S_TABLE not in ('club','vendor')");
    $db->querynow("delete from searchdb_words_".$dellang." where ID_WORDS not in (select FK_WORDS from searchdb_index_".$dellang.")");
    #$db->querynow("truncate searchdb_words_".$dellang);
    
    forward('index.php?nav='. $id_nav, 2);
  }
  $tpl_content->addlist('liste', $liste, 'tpl/de/searching_index.row.htm','format_size');
  $tpl_content->addlist('delmebuttons', $delme, 'tpl/de/searching_index_del.row.htm','format_size');
  
  include "sys/lib.search.php";
  $search = new do_search($s_lang, false);
  $search->spiderDirs();
  
  $ar = array();
  foreach($search->spider_dirs as $key => $value)
    $ar[] = array('DIR' => $value, 'NEW' => NULL);
  foreach($search->new_dirs as $key => $value)
    $ar[] = array('DIR' => $value, 'NEW' => 1);	 

 if(count($ar))
   $tpl_content->addlist("liste_spider", $ar, "tpl/de/searching_index.filerow.htm");

} // no POST


 


?>