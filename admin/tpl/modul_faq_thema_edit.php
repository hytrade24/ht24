<?php
/* ###VERSIONSBLOCKINLCUDE### */


#die(listtab($ar_query_log));
$id = $_REQUEST['ID_FAQ'];
if (count($_POST))
{
  recurse($_POST, '$value=trim($value)');
#echo ht(dump($_POST));
#echo dump($id), dump($fk_kat);die(var_dump($_POST));
  $err = array ();
  if (empty($_POST['V1']))
    $err[] = 'Titel fehlt.';
  if (!$_POST['T1'])
    $err[] = 'Text fehlt.';
  if (($ident = $_POST['V1']) && (int)$db->fetch_atom(
    "select count(*) from string_app where S_TABLE='faqkat' and V1='". mysql_escape_string($ident). "'"
    . ($id ? " and FK<>$id" : '')
  ))
    $err[] = 'Bezeichner '. stdHtmlentities($ident). 'ist schon vergeben.';
  if (!count($err))
  {
    include "sys/lib.cache.php";
    cache_faq();
  $id = $db->update('faq', $_POST);
    if (!$id)
    {
      $err[] = 'MySQL-Fehler:<br />'. stdHtmlentities($lastresult['str_query']). '<br /><b>'
        . stdHtmlentities($lastresult['str_error']). '</b>';
#      $tpl_content->addvars($_POST);
    }
    else
    {
	  ## Suchindex
	  require_once ("sys/lib.search.php");
	  $search = new do_search($s_lang,false);
	  $search->add_new_text($_POST['T2'].' '.$_POST['V1'],$id,'faq');      
	  
	  // Positionen defragmentieren
      if (!($fk_kat = $_REQUEST['FK_FAQKAT']))
        $fk_kat = $db->fetch_atom("select FK_FAQKAT from faq where ID_FAQ=". $id);
      $lastresult = $db->querynow("select count(*),max(POS) from faq
        where FK_FAQKAT=". $fk_kat." and POS>0");
      list($anz, $max) = mysql_fetch_row($lastresult['rsrc']);
#echo "defrag: $anz / $max<br />";
      include_once "sys/lib.cache.php";
      cache_faq();
      if ($anz!=$max)
      {
        $nar = $db->fetch_nar($db->lang_select('faq')."
          where FK_FAQKAT=". $fk_kat." and POS>0
          order by POS, ID_FAQ");
        $pp = 0;
        foreach($nar as $i=>$p)
          if ($p!=++$pp)
            $db->querynow("update faq set POS=$pp where ID_FAQ=$i");
      }
      // forward
#die();
      forward('index.php?frame=content&page=modul_faq_thema_edit'
        . ($_POST['new'] ? '&FK_FAQKAT='. $fk_kat : '&ID_FAQ='. $id));
    }
  }
}
if ($id)
  $data = $db->fetch1($db->lang_select('faq')." where ID_FAQ=$id");
if (!$data)
{
  $data = $db->fetch_blank('faq');
  $data['ID_FAQ'] = $id;
}

if (count($err))
{
  $tpl_content->addvar('err', implode('<br />', $err));
  $data = array_merge($data, $_POST);
  $fk_kat = $_REQUEST['FK_FAQKAT'];
}
elseif ($fk_kat = $_REQUEST['FK_FAQKAT'])
  $tpl_content->addvar('FK_FAQKAT', $fk_kat);
else
  $fk_kat = $data['FK_FAQKAT'];
#echo ht(dump($data));
$tpl_content->addvars($data);
$tpl_content->addvar('pos', $data['POS'] ? $data['POS'] :
  1+$db->fetch_atom("select max(POS) from faq where FK_FAQKAT=". $fk_kat)
);
?>