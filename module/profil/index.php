<?php
/* ###VERSIONSBLOCKINLCUDE### */



 if(count($_POST))
 {
   $tpl_content->addvars($_POST);
   include "inc.profile_check.php";
   profile_check($uid);
   
   if(!empty($_POST['pass1']))
   {
     if(strlen($_POST['pass1']) < 6)
	   $err[] = "Das Passwort muss mind. 6 Zeichen lang sein!";
	 if($_POST['pass1'] != $_POST['pass2'])
	   $err[] = "Die Passwortwiederholung ist nicht korrekt";
   }
   
   date_implode ($_POST,'GEBDAT');
   $_POST['DIR'] = "uploads/user/profilbilder";
   
   if($_FILES['LOGO']['tmp_name']) 
   {
     if(!is_dir("uploads/user/profilbilder"))
       $err[] = "Das angegebene Verzeichnis existiert nicht. Kontaktieren Sie bitte den Seitensupport oder -administrator.";
     else
	 {
	   $_POST['DIR'] = "user/profilbilder";
	   include "module/galerie/ini.php";
       include "sys/lib.media.php";
	   $name = $db->fetch_atom("select LOGO from user where ID_USER=".$uid);
       $up = handle_img($_FILES['LOGO'],$db->fetch1("select * from bildformat where ID_BILDFORMAT=".$ar_modul_option['FK_BILDFORMAT_PROFIL']),$_POST); 
       #die(ht(dump($up)));
       if(is_array($up))
	   {
	     $_POST['LOGO'] = $up['IMG']['file'];	
		 $_POST['LOGO_W'] = $up['IMG']['width'];
		 $_POST['LOGO_H'] = $up['IMG']['height'];
		 if(!empty($name))
		   @unlink($name);
         #echo ht(dump($up))."<hr />".ht(dump($_POST));
	   }
	 }
   }
   
   if (count($err))
   {
     $tpl_content->addvar('err', implode('<br />', $err));
     $data = array_merge($data, $_POST);
   }   
   else
   {
     if ($_POST['pass1']) {
         $salt = pass_generate_salt();
         $_POST['SALT'] = $salt;
         $_POST['PASS'] = pass_encrypt($_POST['pass1'], $salt);
     }
	 $db->update("user", $_POST); 
	 #echo ht(dump($lastresult));
	 $tpl_content->addvar("msg", 1);
   }
 }
 
 $ar_data = $db->fetch1("select * from user where ID_USER=".$uid);
 $tpl_content->addvars($ar_data);

?>
