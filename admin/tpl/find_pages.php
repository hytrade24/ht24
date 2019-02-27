<?php
/* ###VERSIONSBLOCKINLCUDE### */



$SILENCE=false;

require_once 'sys/lib.nestedsets.php';
$root = root('nav');
$tpl_content->addvar("ROOT", $root);
 
 if($root == 1)
 {
 	$path = $ab_path."tpl/de/";
 }
 else
 {
 	$path = $ab_path."admin/tpl/de/";
 }
 
 if(count($_POST))
 {
 	if(!empty($_POST['DATEI']))
 	{
 		$to_kick = array();
 		foreach($_POST['DATEI'] as $key => $datei)
 		{
 			$file = $path.$datei;
 			$to_kick[] = "rm -f ".$file."\n";
 			$php = str_replace(".htm", ".php", $file);
 			$php = str_replace("/tpl/de/", "/tpl/", $php); 			
 			if(file_exists($php))
 				$tto_kick = "rm -f ".$php."\n";
 		}	// foreach $_POST['DATEI']
 		if(count($to_kick))
 		{
 			die(implode("<br>", $to_kick));
 		}
 	}	// DATEI not empty
 }	// post
 
 $dir = scandir($path);
 unset($dir[0], $dir[1]);
 
 $new = array();
 
 for($i=0; $i<count($dir); $i++)
 {
 	$kickit=false;
 	$hack = explode(".", $dir[$i]);
 	$file = "'".sqlString($hack[0])."'";
 	
 	$ident = $db->fetch_atom("SELECT
 			`IDENT`
 		FROM
 			nav
 		WHERE
 			IDENT=".$file." and
 			ROOT=".$root);
 	if(preg_match("/(404)|(403)|(links\.htm)|(rechts\.htm)|(row\.htm)/si", $dir[$i]))
 	{
 		#echo "match: ".$dir[$i]."<br>";
 		$kickit=true;
 	}
 	if(!$ident && $kickit == false && !empty($dir[$i]))
 	{
 		#echo "kick: ".$dir[$i]."<br>";
 		$new[] = array('datei' => $dir[$i]);
 		//unset($dir[$i]);
 	}
 }
 
 if(!empty($new))
 {
 	$tpl_content->addlist("liste", $new, "tpl/de/find_pages.row.htm");
 }

?>