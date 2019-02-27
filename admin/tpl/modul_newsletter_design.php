<?php
/* ###VERSIONSBLOCKINLCUDE### */



 if($_REQUEST['ok'] == 1)
   $tpl_content->addvar("ok", 1);

 $code = false;

 if(count($_POST))
 {
   $err = array();
   $fp = @fopen($file_name = $ab_path."cache/newsletterdesign.htm", "w");
   if(!$fp)
     $err[] = "Konnte File nicht zum Schreiben öffnen";
   else
   {
     if(!strstr($_POST['DESIGN'], "{NEWSLETTER}"))
	   $err[] = "Der Platzhalter {NEWSLETTER} fehlt!";
     else
	 {
	   $write = @fwrite($fp, $_POST['DESIGN']);
	   if(!$write)
	     $err[] = "Konnte Design nicht schreiben!";
	   else
	   {
	     fclose($fp);
	     chmod($file_name, 0777);
		 forward("index.php?page=modul_newsletter_design&ok=1");
		 die();
	   } // geschrieben
	 } // nl ist drin
   } // schreiben geht
   if(!empty($err))
   {
     $tpl_content->addvar("err", implode("<br>", $err));
   } // fehler
   $code = $_POST['DESIGN'];
 }  // post

 $tpl_content->addvar("NL", "{NEWSLETTER}");

 if(!$code)
   $code = @file_get_contents($ab_path."cache/newsletterdesign.htm");

 if(!$code)
 {
   $code = '<html>
<head>
<title>newsletterdesign</title>
</head>
<body>
{NEWSLETTER}
</body>
</html>';
 }

 $tpl_content->addvar("DESIGN", $code);

?>