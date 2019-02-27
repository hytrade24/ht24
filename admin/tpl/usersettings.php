<?php
/* ###VERSIONSBLOCKINLCUDE### */



 $where = '';
 if($_REQUEST['FK_SETTING_GROUP'])
   $where = "where FK_SETTING_GROUP=".$_REQUEST['FK_SETTING_GROUP'];

 $npage = ($_REQUEST['npage'] ? $_REQUEST['npage'] : 1);
 $perpage = 50;
 $limit = ($npage*$perpage)-$perpage;
 
 #echo $db->lang_select("usersetting");
 
 $tpl_content->addvar("FK_SETTING_GROUP", (int)$_REQUEST['FK_SETTING_GROUP']);
 
 $res = $db->querynow("select t.*, s.V1, s.V2, s.T1 
  from `usersetting` t 
   left join string_app s on s.S_TABLE='usersetting' 
    and s.FK=t.ID_USERSETTING 
	and s.BF_LANG=if(t.BF_LANG_APP & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_APP+0.5)/log(2)))
  
  ");
  
  $liste = array();
  $fk = NULL;
  $i=0;
  while($row = mysql_fetch_assoc($res['rsrc']))
  {
    $fk_neu = $row['FK_SETTING_GROUP'];
	if($fk_neu != $fk)
	{
	  $row['FK_GROUP'] = $db->fetch1("select s.V1 
       from `setting_group` t 
	    left join string_app s on s.S_TABLE='setting_group' 
	     and s.FK=t.ID_SETTING_GROUP 
	     and s.BF_LANG=if(t.BF_LANG_APP & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_APP+0.5)/log(2)))
	    where ID_SETTING_GROUP=".$fk_neu);
	  $row['SHOWIT'] = ($_REQUEST['FK_SETTING_GROUP'] ? 0 : 1);
	  if($_REQUEST['FK_SETTING_GROUP'])
	    $tpl_content->addvar("GRUPPE", $row['FK_GROUP']);	  
	}
	$fk=$fk_neu;
	$tpl = new Template("tpl/de/usersettings.row.htm");
	$default = $row['default_value'];
	
	switch($row['TYP'])
	{
	  case 'check':
	   $typ='<input type="checkbox">';
	   $default = ($row['DEFAULT_VALUE'] == 0 ? 'nein' : 'ja');	   
	  break;
	  case 'tpl_funktion':
	   $tmp = new Template("tpl/de/index.htm");
	   $tmp->tpl_text = $row['FORMAT'];
	   #die(ht(dump($tmp)));
	   $typ = $tmp->process();
	  break;
	  default: 
	  $typ='<input type="text">';	  
	}
	$row['TYP']=$typ;
	$row['DEFAULT_VALUE'] = $default;
	$row['even'] = $i&1;
	$tpl->addvars($row);
	$liste[] = $tpl;  
	$i++;
  }
  
  $tpl_content->addvar("liste", $liste);

?>