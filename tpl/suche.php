<?php
/* ###VERSIONSBLOCKINLCUDE### */


#$SILENCE=false;
$ids = $ar_params[1];
$npage = ($ar_params[2] > 1 ? $ar_params[2] : 1);
$perpage = 10;

 function tagFilter($str)
 {
   $return = array( 'TITLE' => '', 'TEXT' => '');
   
   ### titel finden
   preg_match_all("|(<title)([^>]*)(>)([^<]*)|si", $str, $find);   
   $return['TITLE'] = $find[4][0];
   
   $return['TEXT'] = strip_tags(preg_replace("|(>)|si", "> ", preg_replace("|(<!DOCTYPE)(.*?)(/head)([^>]*)(>)|si", '', $str)));
   
   return $return;
 } // tagFilter()

if(!isset($ar_params[1]))
{

} // nichts Suchen
elseif($ar_params[1] <= 0)
{
  $tpl_content->addvar("noergs", 1);
} // Keine Ergebnisse
else
{
  $ar = $db->fetch1("select * from searchstring where ID_SEARCHSTRING=".$ids);
  $tpl_content->addvar("SEARCH", $ar['S_STRING']);
  $tpl_main->addvar("SEARCH", $ar['S_STRING']);  
  if(empty($ar['C_ROWS']))
  {
    $c_query = preg_replace("/(select)(.*?)(from)/si", "$1 count(*),sum(SCORE) as REL, concat(FK_ID,`FILE`) as UN_KEY $3", $ar['QUERY']);
	#die($c_query);
	$res = $db->querynow($c_query);
	#die(ht(dump($res)));
	$ergs = mysql_num_rows($res['rsrc']);
	$up = $db->querynow("update searchstring set C_ROWS=".(int)$ar['C_ROWS']=$ergs."
	   where ID_SEARCHSTRING=".$ids);
    if(!$up['rsrc'])
	  die(ht(dump($up)));
  }
  if($ar['C_ROWS'] < 1)
    $tpl_content->addvar("noergs", 1);
  #echo $ar['C_ROWS'];
  $all = $ar['C_ROWS'];
  $limit = ($npage-1)*$perpage;
  
  $res = $db->querynow($ar['QUERY']." LIMIT ".$limit.", ".$perpage);
  if(!$res['rsrc'])
    die(ht(dump($res)));
  $tmp=array();
  $i=0;
  while($row = mysql_fetch_assoc($res['rsrc']))
  {
    #echo ht(dump($row));
	$tpl_tmp = new Template("tpl/".$s_lang."/suche.row.".$row['S_TABLE'].".htm");
    switch($row['S_TABLE'])
	{
	  case "news": 	    
		$data = $db->fetch1("select t.ID_NEWS,FK_KAT,t.STAMP,t.IMG,t.IMGW,t.IMGH, s.V1, s.V2, k.LFT 
		    from `news` t 
			 left join string_c s on s.S_TABLE='news' and s.FK=t.ID_NEWS and s.BF_LANG=if(t.BF_LANG_C & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_C+0.5)/log(2)))
		     left join kat k on t.FK_KAT=k.ID_KAT
			 where ID_NEWS=".$row['FK_ID']."
		");
	    $data['kat_page'] = $db->fetch_atom("select n.IDENT
	      from modul2nav m
	      left join nav n on m.FK_NAV=n.ID_NAV
	      where m.FK=".$data['FK_KAT']);
	    if(!$data['kat_page'])
	    	$data['kat_page'] = 'news';
	  break;
	  case 'nav':
	   $data = $db->fetch1("select t.ID_NAV,t.IDENT, s.V1
		    from `nav` t 
			 left join string s on s.S_TABLE='nav' and s.FK=t.ID_NAV and s.BF_LANG=if(t.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG+0.5)/log(2)))
			 where ID_NAV=".$row['FK_ID']);
	   $data['PREV'] = @substr(html_entity_decode(strip_tags(preg_replace("/(\{)([a-z_]*)(\})/si", "", file_get_contents('tpl/'.$s_lang.'/'.$data['IDENT'].'.htm')))),0,250);
	  break;
	  case 'faq':
	   $data = $db->fetch1("select t.ID_FAQ,t.FK_FAQKAT, s.V1, s.T1
		    from `faq` t 
			 left join string_faq s on s.S_TABLE='faq' and s.FK=t.ID_FAQ and s.BF_LANG=if(t.BF_LANG_FAQ & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_FAQ+0.5)/log(2)))
			 where ID_FAQ=".$row['FK_ID']);
	   $data['T1'] = substr(strip_tags($data['T1']),0,255);
	  break;
	  default: die("Missing search- option for parameter ".$row['S_TABLE']);
	}
	$tpl_tmp->addvar("even", $i%2);
	$i++;
	$tpl_tmp->addvars($data);
	$tmp[] = $tpl_tmp;
  }
  $tpl_content->addvar("liste", $tmp);
  if($all > $perpage)
  {

	$tpl_content->addvar("pager", htm_browse($all, $npage, '/'.$ar_params[0]. ','. $ids. ',', $perpage));
  }
}


?>