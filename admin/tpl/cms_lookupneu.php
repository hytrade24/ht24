<?php
/* ###VERSIONSBLOCKINLCUDE### */



 $nar_art = $db->fetch_nar("select art,art from lookup group by art order by art");
 $ar = array ();
 foreach ($nar_art as $v) $ar[] = '
   <option '. ($v==$_REQUEST['art'] ? 'selected="selected" ' : ''). 'value="'. $v. '">'. $v. '</option>';
 $tpl_content->addvar('opts_art', $ar);				
 
 $err = array();
 
 if(count($_POST))
 {
  
   if($_POST['VALUE'] != "")
   {
    
     if($_POST['V1'])
	 {
	  
	   if($_POST['artneu'])
	   {
	    
	      if(in_array($_POST['artneu'], $nar_art))
		  {
			$ask = 0;
	      }
		  else
		  {
		    
			#$data = $db->fetch_nar("select art,value from lookup");
			
		    $_POST['art'] = $_POST['artneu'];
	        $tpl_content->addvar('hinweis', 1);
			$tpl_content->addvars($_POST);
			$ask = 1;
		  }
	    
	   }
	   else
	     $ask = 0;
      
     }
     else
       $err[] = "Sie müssen ein Label angeben!";
   }
   else
     $err[] = "Sie müssen einen Wert angeben!";
 }
 
 
 if(count($err))
 {
   $err = implode('<br />', $err);
   $tpl_content->addvar('err', $err);
   $tpl_content->addvars($_POST);
 }
 elseif(count($_POST))
 {
   if($ask)
   {
     if($_POST['ja'])
	 {
	   unset($_POST['artneu']);
	   unset($_POST['page']);
	   unset($_POST['ja']);
	   $check = $db->fetch_atom("select ID_LOOKUP from lookup where `art` ='".mysql_escape_string($_POST['art'])."'
	       and `VALUE`= '".mysql_escape_string($_POST['VALUE'])."'");
	   if(!$check)
	   {
	     $db->update('lookup',$_POST);
	     //echo "DB Update ausgeführt MIT CHECK";
	     $tpl_content->addvar('ok', 1);
	     $tpl_content->addvar('content', implode('</td><td>', $_POST));
	   }
	   else
	   {
	     $tpl_content->addvar("err", "Diesen Lookup gibt es schon! Gehen Sie auf die Lookups Seite und editieren Sie den bestehenden Lookup, um Veränderungen vorzunehmen.");
	   }
	 }
   }
   else
   {
     unset($_POST['artneu']);
	 unset($_POST['page']);
	 unset($_POST['ja']);
	 $check = $db->fetch_atom("select ID_LOOKUP from lookup where `art` ='".mysql_escape_string($_POST['art'])."'
	     and `VALUE`= '".mysql_escape_string($_POST['VALUE'])."'");
	 if(!$check)
	 {
	   $db->update('lookup',$_POST);
	   //echo "DB Update ausgeführt OHNE CHECK";
	   $tpl_content->addvar('ok', 1);
	   $tpl_content->addvar('content', implode('</td><td>', $_POST));
	 }
	 else
	 {
	   $tpl_content->addvar("err", "Diesen Lookup gibt es schon! Gehen Sie auf die Lookups Seite und editieren Sie den bestehenden Lookup, um Veränderungen vorzunehmen.");
	 }
   }
 }

?>