<?php
/* ###VERSIONSBLOCKINLCUDE### */


 // Name der Webseite ermitteln
  if(!$_REQUEST['IDENT']) // nix ausgewaehlet, dann default
	  $_REQUEST['IDENT']='module';
    $nav_current = $db->fetch1("
    select  s.V1, s.V2, s.T1 from nav t left 
    join string s on s.S_TABLE='nav' and s.FK=t.ID_NAV and s.BF_LANG=if(t.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG+0.5)/log(2))) 
    where IDENT='".$_REQUEST['IDENT']."'");
    $tpl_content->addvar('forpage', $nav_current['V1']);

 if($_REQUEST['frompopup']==1) 
   $tpl_content->addvar('frompopup', 1);

 if(!$_REQUEST['do']=='edit') // Baum nur laden wenn nicht EDIT
  {
		  require_once 'sys/lib.nestedsets.php'; // Nested Sets
		  $root=2; // 1=Public Node, 2=Admin Node
		  
		  
		  $help_filename="../cache/helpnavi".$_REQUEST['frompopup'].".php";
		if (file_exists($help_filename)) {
			$open = @fopen($help_filename, "r");
			$contents = fread ($open, filesize ($help_filename));
		}
		else
		{
		
		  $nest = new nestedsets('nav', $root, 1);
		  $res = $nest->nestSelect('', '', '', false);
		  $ar = $db->fetch_table($res);
		  
			$open = @fopen($help_filename, "w");
			$tpl_tmp = new Template('tpl/de/empty.htm');
			
			 if($_REQUEST['frompopup']==1) 
				   $tpl_tmp->addvar('frompopup', 1);
				   
			$tpl_tmp->addvar('empty', tree_show_nested($ar, 'tpl/de/hilfe_edit.row.htm',NULL,false));
			fwrite($open, $tpl_tmp->process());
			fclose($open);
		 	chmod($help_filename, 0777);
		}
		  
		  
		  
		  /*
		  if ($_SESSION['navhelp'.$_REQUEST['frompopup']]<>'')
		  	{
				//$tpl_content->addvar('baum', unserialize ($_SESSION['navhelp']));
				$tpl_content->tpl_text=$_SESSION['navhelp'.$_REQUEST['frompopup']];
			 }
		   else
		   	{
					$tpl_content->addvar('baum', tree_show_nested($ar, 'tpl/de/hilfe_edit.row.htm',NULL,false));
				$_SESSION['navhelp'.$_REQUEST['frompopup']]=$tpl_content->process();
			  //$_SESSION['navhelp']= serialize (tree_show_nested($ar, 'tpl/de/hilfe_edit.row.htm',NULL,false));
			  }
			  
			  */
		  //$tpl_content->addvar('baum', tree_show_nested($ar, 'tpl/de/hilfe_edit.row.htm',NULL,false));
		   $helptext = $db->fetch1("select t.*, s.V1, s.V2, s.T1 from `hilfe` t left join string_hilfe s on s.S_TABLE='hilfe' and s.FK=t.ID_HILFE and s.BF_LANG=if(t.BF_LANG_HILFE & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_HILFE+0.5)/log(2))) where IDENT='".$_REQUEST['IDENT']."'");
		   $tpl_content->addvar('ID_HILFE', $helptext['ID_HILFE']);
		   $tpl_content->addvar('helptext', addnoparse($helptext['T1']));
	} 
else
{

	  if($_POST['do'])
		  {
		  	$_REQUEST['T1'] = $_POST['helptext'];

			$id = $db->update('hilfe', $_REQUEST);
			
			#die(ht(dump($db)));
			
			if($_REQUEST['frompopup']==1)
			  forward('index.php?page=hilfenavi&IDENT='. $_REQUEST['IDENT'] .'&frompopup=1&frame=popup');
			else
			  forward('index.php?page=hilfenavi&IDENT='. $_REQUEST['IDENT']);
		  }

		  $helptext = $db->fetch1("select t.*,s.T1 from `hilfe` t left join string_hilfe s on s.S_TABLE='hilfe' and s.FK=t.ID_HILFE and s.BF_LANG=if(t.BF_LANG_HILFE & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_HILFE+0.5)/log(2))) where IDENT='".$_REQUEST['IDENT']."'");
		  

  $tpl_content->addvar('ID_HILFE', $helptext['ID_HILFE']);
  $tpl_content->addvar('helptext', $helptext['T1']);
  $tpl_content->addvar('do', $_REQUEST['do']);
 }
 
   $tpl_content->addvar('IDENT', $_REQUEST['IDENT']);

?>