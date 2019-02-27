<?php
/* ###VERSIONSBLOCKINLCUDE### */


 
 $nummer = 1;
 
 if($_REQUEST['del'])
 {
    $id = (int)$_REQUEST['del'];
	$res=$db->querynow("delete from scriptview where FK_SCRIPT=".$id);
    $res = $db->querynow("update kommentar_script set FK=0 where FK=".$id);	   
	$res = $db->querynow("delete from  searchdb_index_de where S_TABLE='script' and FK_ID=".$id);
	$res = $db->querynow("delete from script where ID_SCRIPT=".$id);
	$res = $db->querynow("delete from script_work where ID_SCRIPT_WORK=".$id);
 } // del
 
 function lcheck(&$ar, $i)
 {
    global $db, $nummer;
	$ar['NUMMER']=$nummer;
	$nummer++;
	$ar_lang = $db->fetch_table("select l.ABBR from string_script s 
	 left join lang l on l.BITVAL=s.BF_LANG
	 where 
	  S_TABLE='script' and FK=".$ar['ID_SCRIPT_WORK']);  
    $ar_data['langs']='';
    for($k=0; $k<count($ar_lang); $k++)
        $ar_data[$i]['langs'] .= '<img src="'.$tpl_content->tpl_uri_baseurl('/gfx/lang.'.$ar[$k]['ABBR'].'.gif').'"> ';
 } // lcheck()
 
 $_REQUEST['what'] = "script";
 
 if(count($_POST) && !$_POST['ACTION'])
 {
   if($_POST['ALL'])
   {
     $_POST['RED_'] = 0;
	 $_POST['ADM_']=0;
   }
   if(!$_POST['RED_'] && !$_POST['ADM_'] && !$_POST['ALL'])
     $_POST['RED_']=0;
   if($_POST['_NAME'])
   {
     $user = $db->fetch_atom("select ID_USER from `user` where  `NAME`='".sqlString($_POST['_NAME'])."'");
	 if($user)
	   $_POST['FK_USER'] = $user;
   } // name
   $ser = array("SER_PARAMS" => serialize($_POST), "STAMP" => date('Y-m-d H:i:s', strtotime('+1 day')));
   $id_s = $db->update("search", $ser);
   die(forward("index.php?page=scripte&ID_S=".$id_s));
 } // post
 
 if($_REQUEST['ID_S'])
 {
   $db->querynow("delete from search where STAMP < now()");
   $tpl_content->addvar("S_ID", $_REQUEST['S_ID']);
   $ar_pre = $db->fetch_atom("select SER_PARAMS from search where ID_SEARCH=".$_REQUEST['ID_S']);
   $ar_pre = unserialize($ar_pre);
   #echo ht(dump($ar_pre));
 }
 else
 { 
   $ar_pre = array
   (
     'orderby' => "t.STAMP_UPDATE",
	 'updown' => 'DESC',
	 'SHOW' => 'UPDATES',
	 'kosten' => 'all',
	 'FK_USER' => NULL, 
	 'STR' => NULL,
	 'ALL' => 0,
   );
 } // keine suche
 
 
 $tpl_content->addvar("updown_".$ar_pre['updown'], 1);
 $tpl_content->addvar("SHOW_".$ar_pre['SHOW'], 1); 
 $tpl_content->addvar("kosten_".$ar_pre['kosten'], 1); 
 
 $orders = array
 (
   't.STAMP' => "Einstelldatum",
   't.STAMP_UPDATE' => "Update Datum",
   'u.`NAME`' => 'Username',
   'sk.V1' => "Kategorie"
 );
 
 $tpl_order = array();
 foreach($orders as $key => $value)
 {
   $selected = ($key == $ar_pre['orderby'] ? ' selected' : '');
   $tpl_order[] = '<option value="'.$key.'" '.$selected.'>'.stdHtmlentities($value).'</option>';
 } // for $orders
 $tpl_content->addvar("orders", implode("\n", $tpl_order));
 
 $where = array();

 switch($ar_pre['kosten'])
 {
   case 'no': $where[] = "t.COMMERCE = 0";
   break;
   case 'yes': $where[] = "t.COMMERCE = 1";
   break;
 } // swtch kosten 
 
 /*
 switch($ar_pre['SHOW'])
 {
   case 'UPDATES': $where[] = "t.OK =1";
   break;
   case 'SHOW': $where[] = "t.OK=3";
   break;
 } 
 */
 
 if($ar_pre['ID_SCRIPT'])
   $where[] = " ID_SCRIPT_WORK = ".(int)$ar_pre['ID_SCRIPT'];
 if($ar_pre['DEAD'])
   $where[] = " t.DEAD IS NOT NULL ";
 if($ar_pre['FK_KAT'])
   $where[] = "t.FK_KAT=".$ar_pre['FK_KAT'];
 if($ar_pre['FK_USER'])
   $where[] = "t.FK_USER=".(int)$ar_pre['FK_USER'];
 if($ar_pre['PAID'])
   $where[] = "t.PAID=1";
 if($ar_pre['STR'])
   $where[] = "s.V1 LIKE '%".sqlString($ar_pre['STR'])."%'";
 
 if($ar_pre['ALL'] == 1)
 {
   $where[] = " t.OK >= 0 ";
 } // alle
 else
 {
   if (isset($ar_pre['RED_']) || isset($ar_pre['ADM_']))
   {
	  if ($ar_pre['RED_'] or $ar_pre['ADM_']) 
	  {
		  $ok_ = $ar_pre['RED_'] + $ar_pre['ADM_'];
		  $where[] = " t.OK  = ".$ok_;
	  }
	  else
	    $where[] = "  t.OK  = 0";
   } 
   else
   {
     $ar_pre['RED_']=1;
     $where[] = "  t.OK  = 1"; 
   } 
 } // nicht alle
 $tpl_content->addvars($ar_pre);
 
 $npage = ($_REQUEST['npage'] ? $_REQUEST['npage'] : 1);
 $tpl_content->addvar("npage", $npage);
 $perpage = 30;
 $limit = ($perpage*$npage)-$perpage;
 
 function debug_joins($ar_querys, $repeat_count) {
   global $db;
   $result = array();
   $responses = array();
   foreach($ar_querys as $name => $query) {
     $result[$name] = array();
  	 $result[$name]["query"] = $query;
     $result[$name]["querytime"] = 0;
     $result[$name]["querycount"] = 0;
     for ($num = 1; $num <= $repeat_count; $num++) {
       $time_query = microtime(true);
       $responses[$name] = $db->fetch_table($query);
	   $time_query = round((microtime(true) - $time_query) * 1000, 5);
	   if ((!$result[$name]["querytime_min"]) || ($time_query < $result[$name]["querytime_min"])) {
	     $result[$name]["querytime_min"] = $time_query;
	   }
	   if ((!$result[$name]["querytime_max"]) || ($time_query > $result[$name]["querytime_max"])) {
	     $result[$name]["querytime_max"] = $time_query;
	   }
	   $result[$name]["querytime"] += $time_query;
	   $result[$name]["querycount"] += 1;
     }
   }
   return $result;
 }
 
 $liste = $db->fetch_table("select t.*, LEFT(t.LINK_DSC, 30) as shotlink, t.PCOUNT  as ckomm,
  ORG.OK as ONLINE, 
   if(t.OK&1,1,0) OK1, if (t.OK&2,1,0) OK2, 
  u.`NAME` as `UNAME`
  ,s.V1, s.V2, s.T1 
  ,sk.V1 as KV1
  from `script_work` t    
   left join script ORG on t.ID_SCRIPT_WORK=ORG.ID_SCRIPT
   left join `user` u on t.FK_USER=u.ID_USER
   left join string_script_work s on s.S_TABLE='script_work' 
    and s.FK=t.ID_SCRIPT_WORK and s.BF_LANG=if(t.BF_LANG_SCRIPT_WORK & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_SCRIPT_WORK+0.5)/log(2)))
   left join string_tree_script sk on sk.S_TABLE='tree_script' 
    and sk.FK=t.FK_KAT and sk.BF_LANG=if(sk.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(sk.BF_LANG+0.5)/log(2)))  
  ".(count($where) ? "where  
   ".implode(" and ", $where) : '')."
  group by t.ID_SCRIPT_WORK
  order by ".$ar_pre['orderby']." ".$ar_pre['updown']."
  LIMIT ".$limit.", ".$perpage."
  ");
 
//echo ht(dump($lastresult));
$query ="
 	SELECT 
 		count(t.ID_SCRIPT_WORK) 
 	FROM 
 		script_work t
 	LEFT JOIN 
 		string_script_work s ON t.ID_SCRIPT_WORK=s.FK
 			AND s.S_TABLE ='script_work'
 			AND s.BF_LANG = ".$langval."
 	".(count($where) ? " where  ".implode(" and ", $where) : '');

$all = $db->fetch_atom($query);  

 $tpl_content->addlist("liste", $liste, "tpl/de/scripte.row.htm"); //, 'lcheck');
 $pager = htm_browse($all, $npage, "index.php?page=scripte&ID_S=".(int)$_REQUEST['ID_S']."&npage=", $perpage);
 $tpl_content->addvar("pager", $pager);

 #echo ht(dump($where));

?>