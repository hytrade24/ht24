<?php
/* ###VERSIONSBLOCKINLCUDE### */



  if (isset($_GET['do']))
  {
    if ($_GET['do'] == 'delete')
    {
      if (!(int)$db->fetch_atom("select B_SYS from kat_option where ID_KAT_OPTION=".(int)$_GET['ID_D']))
      {
        $lastresult = $db->querynow("delete from kat_option where ID_KAT_OPTION=".(int)$_GET['ID_D']);
	      if (!$lastresult['str_error'])
    	    $tpl_content->addvar('msg', 1);
    	  else
	        $tpl_content->addvar('err', 'Datenbankfehler! Option konnte nicht gelöscht werden');
      }
      else
        $tpl_content->addvar('err', "Sicherheitsverletzung! Diese Option kann nicht gelöscht werden!");
    }
  }

  $tpl_content->addlist('liste', $db->fetch_table($db->lang_select("kat_option")."
    order by ROOT, V1"), 'tpl/de/katoptions.row.htm');
?>
