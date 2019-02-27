<?php
/* ###VERSIONSBLOCKINLCUDE### */



 $nummer = 1;

 function kill_tags(&$row, $i)
 {
   global $nummer, $search_arr;
   $row['KOMMENTAR_PARSED'] = strip_tags($row['KOMMENTAR_PARSED']);
   $row['id_comment'] = $row['ID_KOMMENTAR_'.strtoupper($search_arr['KOMM'])];

     $row['NUMMER'] = $nummer;
     $nummer++;
 }

 $search_arr = array();
 if($_GET['search_string'])
 {
   $temp = urldecode($_GET['search_string']);
   $temp =  unserialize($temp);

   foreach($temp as $key => $value)
   {
     $search_arr[$key] = $value;
   }
 }
 elseif($_POST)
 {
   $search_arr['KOMM'] = $_POST['KOMM'];
   $search_arr['ORDERBY'] = $_POST['ORDERBY'];
   $search_arr['updown'] = $_POST['updown'];
   $search_arr['FK_AUTOR'] = $_POST['FK_AUTOR'];
   $search_arr['NAME_'] = $_POST['NAME_'];
   $search_arr['SEARCH'] = $_POST['SEARCH'];
   $search_arr['FK'] = $_POST['FK'];
 }
 else
 {
   $search_arr['KOMM'] = ($_REQUEST['KOMM'] ? $_REQUEST['KOMM'] : "news");
   $search_arr['ORDERBY'] = "STAMP";
   $search_arr['updown'] = "DESC";
   $search_arr['NAME_'] = NULL;
   $search_arr['SEARCH'] = NULL;
   $search_arr['FK'] = $_REQUEST['FK'];
 }

 $table = ($search_arr['KOMM'] ? $search_arr['KOMM'] : 'news');
 $npage = ($_REQUEST['npage'] ? $_REQUEST['npage'] : 1);
 $perpage = 50;
 $limit = (($npage*$perpage)-$perpage);

 $tpl_content->addvars($search_arr);
 //print_r($_REQUEST);
 $tpl_content->addvar('KOMM_'.$search_arr['KOMM'], 1);
 $tpl_content->addvar('ORDERBY_'.$search_arr['ORDERBY'], 1);
 $tpl_content->addvar('updown_'.$search_arr['updown'], 1);

 if($search_arr)
 {
   $where_arr = array();

   if($search_arr['NAME_'])
   {
     if($search_arr['FK_AUTOR'])
       $where_arr[] = "k.FK_USER = ".$search_arr['FK_AUTOR'];
	 else
	   $where_arr[] = "u.NAME = '".$search_arr['NAME_']."'";
   }

   if($search_arr['SEARCH'])
     $where_arr[] = "k.KOMMENTAR_PARSED like '%".sqlString($search_arr['SEARCH'])."%'";

   if($search_arr['FK'] && (is_numeric($search_arr['FK']) || ($search_arr['KOMM'] == "handbuch")))
     $where_arr[] = "k.FK = '".$search_arr['FK']."'";

   if($search_arr['ORDERBY'])
   {
     if($search_arr['ORDERBY'] == 'NAME')
       $orderby = 'u.'.$search_arr['ORDERBY'];
     elseif(($search_arr['ORDERBY'] == 'ARTIKEL') && $search_arr['KOMM'] != "handbuch")
       $orderby = 't.ID_'.strtoupper($table);
	 elseif(($search_arr['ORDERBY'] == 'ARTIKEL') && $search_arr['KOMM'] == "handbuch")
	   $orderby = 'k.FK';
     else
       $orderby = 'k.'.$search_arr['ORDERBY'];
   }
   else
     $orderby = NULL;

   if(!empty($where_arr))
     $where = "where ".implode(" and ", $where_arr);


	  $query = "select u.NAME as USRNAME, left(k.KOMMENTAR_PARSED, 200) as KOMMENTAR_PARSED, s.V1 as ARTIKEL, k.STAMP,
  									k.FK_USER, k.FK, k.ID_KOMMENTAR_NEWS, t.ID_NEWS
  			 from kommentar_news k
 			left join user u on u.ID_USER = k.FK_USER
			left join news t on k.FK = t.ID_NEWS
			left join string_c s on s.S_TABLE='news' and s.FK=k.FK and s.BF_LANG=if(t.BF_LANG_C & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_C+0.5)/log(2)))
			".$where."
			".($orderby ? "order by ".$orderby." ".$search_arr['updown'] : "")."
			limit ".$limit.", ".$perpage;

  //die(ht(dump($query)));
  $data = $db->fetch_table($query);

  $all = $db->fetch_atom("select count(*) from kommentar_".$table." k
 			left join user u on u.ID_USER = k.FK_USER
			".$where);

  $tpl_content->addlist('liste', $data, 'tpl/de/kommentare.row.htm', 'kill_tags');
  $tpl_content->addvar("pager", htm_browse($all, $_REQUEST['npage'], "index.php?page=kommentare&search_string=".urlencode(serialize($search_arr))."&npage=", $perpage));
 } // if $_POST

?>