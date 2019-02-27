<?php
/* ###VERSIONSBLOCKINLCUDE### */


 
 $id = $db->fetch_atom("select ID_MODUL from modul where IDENT='galerie'");

 if(count($_POST))
 {
    $up = $db->querynow("update modul set B_VIS=".($_POST['B_VIS'] ? 1 : 0)."
	   where IDENT='galerie'");
	
	$res = $db->querynow("select t.OPTION_VALUE, ID_MODULOPTION from `moduloption` t        
      where FK_MODUL=".$id);
    if(!$res['rsrc'])
      die(ht(dump($res))); 
    $ar_ini = $ini = array();
	while($row = mysql_fetch_assoc($res['rsrc']))
	{
	  $db->update("moduloption", array("ID_MODULOPTION" => $row['ID_MODULOPTION'], "V1" => $_POST[$row['OPTION_VALUE']]));
	  $ar_ini[] = "\t'".$row['OPTION_VALUE']."' => '".$_POST[$row['OPTION_VALUE']]."'";
	}
	
	$ini[] = "<?php\n\$ar_modul_option = array(\n";
	$ini[] = implode(",\n", $ar_ini);
	$ini[] = "\n);\n?>";
	
	$fp = @fopen("../module/galerie/ini.php", "w");
	if(!$fp)
	  die("Could not open ini File");
    $write = @fwrite($fp, implode($ini));
	if(!$write)
	  die("Could not write ini File");	
    @fclose($fp);
	$tpl_content->addvar("written", 1);
 }
 
 $res = $db->querynow("select t.OPTION_VALUE, s.V1 from `moduloption` t 
   left join string_opt s on s.S_TABLE='moduloption' and s.FK=t.ID_MODULOPTION and s.BF_LANG=if(t.BF_LANG_OPT & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_OPT+0.5)/log(2)))
  where FK_MODUL=".$id);
 if(!$res['rsrc'])
   die(ht(dump($res)));
 
 while($row=mysql_fetch_assoc($res['rsrc']))
 {
   #echo ht(dump($row));
   switch($row['OPTION_VALUE'])
   {
     case 'MOD' : $tpl_content->addvar("MOD", $row['V1']);
  	 break;
  	 case 'FK_BILDFORMAT' : $tpl_content->addvar("FK_BILDFORMAT", $row['V1']);
  	 break;
  	 case 'FK_BILDFORMAT_PROFIL' : $tpl_content->addvar("FK_BILDFORMAT_PROFIL", $row['V1']);
  	 break;	 
  	 case 'DIR' : $tpl_content->addvar("DIR", $row['V1']);
  	 break;
   }
 }
 
 $tpl_content->addvar("B_VIS", $test = $db->fetch_atom("select B_VIS from modul where IDENT='galerie'"));
 $tpl_content->addvar("UPLOADPATH",$nar_systemsettings['SITE']['PATH_UPLOADS']);
 #echo $test;
 
?>