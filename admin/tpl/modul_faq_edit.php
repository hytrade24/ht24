<?php
/**
 * ebiz-trader
 *
 * @copyright Copyright (c) 2012 ebiz-consult e.K.
 * @version 7.2.1
 */


/* Wird eine Kategorie unsichtbar gemacht, wird ihre Position einfach auf null gesetzt. Eine Sortierung ist dann nicht mehr möglich, da sich sichtbar/unsichtbar nur an
   POS=0 oder nicht 0 orientiert.
   Spalte "VIS" tinyint(1), not null, default: 0 habe ich bereits in Tabelle hinzugefügt. Ich finde aber nicht, an welcher Stelle im Code do=v1 bzw v0 abgearbeitet wird...
*/

if ('faq'==$_REQUEST['what'])
{
  $id_faq = $_REQUEST['id'];
  $id = $db->fetch_atom("select FK_FAQKAT from faq where ID_FAQ=$id_faq");
  handle_move_request('faq', NULL, 1, 'FK_FAQKAT');
}
else
{
  handle_move_request('faqkat', NULL, 1, '1');
  if (!($id = $_REQUEST['ID_FAQKAT']))
    $id = $_REQUEST['id'];
}

  switch ($do = $_REQUEST['do'])
  {
    case 'rmt':
	### Suchindex aufräumen
	require_once ("sys/lib.search.php");
	$search = new do_search('de',false);
	$search->delete_article_from_searchindex($_REQUEST['ID_FAQ'],'faq');	
      #die("Script angehalten! Zeile 18. Strings müssen gelöscht werden!");
    
	$id_faq = $_REQUEST['ID_FAQ'];
    $db->delete("faq",$id_faq);
      break;
    case 'rmk':
      #die("Script angehalten! Zeile 23. Strings müssen gelöscht werden!");
	### Suchindex aufräumen
	require_once ("sys/lib.search.php");
	$search = new do_search('de',false);
   
	$ar_del = $db->fetch_table("select * from faq where FK_FAQKAT=".$id);
    for ($i=0; $i<count($ar_del); $i++)
    {
      $search->delete_article_from_searchindex($ar_del[$i]['ID_FAQ'],'faq'); 
	  $ar_tmp[] = $ar_del[$i]['ID_FAQ'];
    }
    #die(ht(dump($ar_tmp)));
    if(count($ar_tmp))
      $db->querynow("delete from string_faq where S_TABLE='faq' and FK in(".implode(',',$ar_tmp).")");
    $db->querynow("delete from faq where FK_FAQKAT=$id");
      $db->query("delete from string_faq where S_TABLE='faqkat' and FK=".$id);
      $db->query("delete from faqkat where ID_FAQKAT=".$id);
    $db->submit();
    #die(ht(dump($lastresult)));
  break;
    case 'sv':      
	  $err = array ();
      $s_label = $_POST['V1'] = trim($_POST['V1']);
      if (!$s_label)
        $err[] = 'Titel fehlt.';
      elseif ((int)$db->fetch_atom("select count(*) from string_faq
        where S_TABLE='faqkat' and V1='". mysql_escape_string($s_label). "'". ($id ? ' and FK<>'. $id : '')))
        $err[] = 'Titel mu&szlig; eindeutig sein.';
      if (!count($err))
      {
        #$next = $db->fetch_atom("select count(*) from faq")+1;
		#$_POST['POS']=$next;
        if (empty($_POST["FK_NAV"])) {
            $_POST["FK_NAV"] = null;
        }
		$id = $db->update('faqkat', $_POST);			
        if (!$id)
          $err[] = 'DB-Fehler:<br />'. ht($lastresult['str_query']). '<br /><b>'
            . ht($lastresult['str_error']). '</b>';
      }
      break;
  }

// caching
if(isset($_REQUEST['do']))
{
 include "sys/lib.cache.php";
 cache_faq();
}
// caching end

$arNavList = $db->fetch_table($q=$db->lang_select('nav')."where FK_MODUL=9 order by LFT");

  if (count($err))
  {
    $tpl_content->addvar('err', implode('<br />', $err));
    if (!$id)
      $tpl_content->addvars($_POST);
  }

  $res = $db->querynow($q=$db->lang_select('faqkat', " *,
    count(f.ID_FAQ) as topiccount,
    IF(t.FK_NAV IS NULL, 'Alle Seiten', (SELECT V1 FROM `string` WHERE S_TABLE='nav' AND FK=t.FK_NAV AND BF_LANG=128)) AS NAV_V1, 
    sum(if(f.POS>0,1,0)) as activecount").
  " left join faq f on FK_FAQKAT=ID_FAQKAT
  group by ID_FAQKAT
  order by POS, ID_FAQKAT");
#echo ht(dump($res));
  $ar_liste = array ();
  for ($i=0; $row = mysql_fetch_assoc($res['rsrc']); $i++)
  {
    $b_edit = $id==$row['ID_FAQKAT'];
    $tpl_tmp = new Template( 'tpl/de/modul_faq_edit.'
      . ($b_edit ? 'edit':''). 'row.htm', 'faqkat');
    if ($b_edit)
    {
      $subres = $db->querynow($db->lang_select('faq')."where FK_FAQKAT=$id order by POS,ID_FAQ");
      #die(ht(dump($lastresult)));
      $ar_tmp = array ();
      for ($k=0; $subrow = mysql_fetch_assoc($subres['rsrc']); $k++)
      {
        $tpl_sub = new Template('tpl/de/modul_faq_edit.topicrow.htm');
        $tpl_sub->addvar('i', $k);
        $tpl_sub->addvar('even', 1-($k&1));
        $tpl_sub->addvar('islast', $k+1==$subres['int_result']);
        $tpl_sub->addvars($subrow);
        $ar_tmp[] = $tpl_sub;
      }
      $tpl_tmp->addvar('topiclist', $ar_tmp);
      
      // Target pages
      $ar_tmp = array ();
      foreach ($arNavList as $navIndex => $navRow) {
        $tpl_sub = new Template('tpl/de/modul_faq_edit.navrow.htm');
        $tpl_sub->addvar('i', $navIndex);
        $tpl_sub->addvar('even', 1-($navIndex&1));
        $tpl_sub->addvar('islast', $navIndex+1==$navres['int_result']);
        $tpl_sub->addvar('SELECTED', ($row["FK_NAV"] == $navRow["ID_NAV"]));
        $tpl_sub->addvars($navRow);
        $ar_tmp[] = $tpl_sub;
      }
      $tpl_tmp->addvar('navlist', $ar_tmp);
      
/*
      $tpl_tmp->addlist('topiclist',
        $ar_tmp,
        'tpl.admin/faq.topicrow.htm'
      );
*/
      if (count($err))
        $row = array_merge($row, $_POST);
    }
    $tpl_tmp->addvar('i', $i);
    $tpl_tmp->addvar('even', 1-($i&1));
    $tpl_tmp->addvar('islast', $i+1==$res['int_result']);
    #die(print_r($row));
  $tpl_tmp->addvars($row);
    $ar_liste[] = $tpl_tmp;
  }
  $tpl_content->addvar('liste', $ar_liste);
#echo ht(dump($lastresult));
  // tree
#ob_end_flush();echo "<hr><b>$parent</b>", ht(dump($nar_children)), '<hr>';ob_start();
  $navtree = ($nar_children[0] ? shownav(0) : '');

#die(ht(dump($ar_unused)));
  // template
  $tpl_content->addlist('navlist_new', $arNavList, 'tpl/de/modul_faq_edit.navrow.htm');
  $tpl_content->addvar('maxlevel', $maxlevel);
  $tpl_content->addvar('navtree', $navtree);
  $tpl_content->addvar('unused', $ar_unused);
?>