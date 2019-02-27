<?php
/* ###VERSIONSBLOCKINLCUDE### */


/*
echo "Schmalle sagt mir ich soll folgendes hier reinschreiben:<br /> \"Nicht zu definierendes Zeugs in Zeile 4-8\" ;)";
$result=mysql_query("SHOW COLUMNS FROM ads LIKE 'area' ");
if(mysql_num_rows($result)>0){
$row=mysql_fetch_row($result);
$options=explode("','",preg_replace("/(enum|set)\('(.+?)'\)/","\\2",$row[1]));
}
*/
  if(count($_POST))
  {
    $err = array(); // Fehlersammelannahmestelle
	if($_POST['adname']=="")
	  $err[] = "Sie müssen einen Bannernamen angeben";
	
	if($_POST['banner']=="")
	  $err[] = "Bannercode fehlt";
	
	if(!$_POST['FK_KAT'])
	  $err[] = "Wählen Sie eine Kategorie aus (wenigstens \"Adserver\")";

      date_implode ($_POST,'DATE_START');
      date_implode ($_POST,'DATE_END');

	if(!count($err))
	{
	 	if (!$_REQUEST['ID_ADS']>0)
	  		$_POST['STAMP'] = date("Y-m-d");

		if ( !isset($_POST["top"]) ) {
			$_POST["top"] = "0";
		}

	  $id=$db->update('ads', $_POST);
	  forward('index.php?nav='. $id_nav. '&ID_ADS='. $id, 2);
	}
	else
	{
	  $tpl_content->addvar('err', implode('<br />',$err));
	  $tpl_content->addvars($_POST);
	}
  
  }
  
  elseif ($_REQUEST['ID_ADS']>0) {
  	$ar_data = $db->fetch1('select *, DATE_END,DATEDIFF(DATE_END,now()) as ADRUNTIME from ads where ID_ADS='.$_REQUEST['ID_ADS']);
	 $tpl_content->addvars($ar_data);
  }

//die(ht(dump($options)));
?>