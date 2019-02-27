<?php
/* ###VERSIONSBLOCKINLCUDE### */


	//anzahl der unterschiedlichen Buchstaben
	$anzahl = $db->fetch_atom('select count(distinct LEFT(UPPER(NAME),1)) as anzahl from manufacturers order by NAME');
	$zeichenold='';
	
	$manufacturers = $db->fetch_table('select NAME,UPPER(LEFT(NAME,1)) as zeichen, "1" as level from manufacturers order by NAME');

	
	$alphaList=array();
	$i=1;
	foreach ($manufacturers as $index){
		if ($zeichenold <> $index['zeichen'] ) {			
			$alphaList[$i][] = array(
			"NAME" => $index['zeichen'],
			"level" => 0);
		}
			$alphaList[$i][] = array(
			"NAME" => $index['NAME'],
			"level" => 1);
		$zeichenold = $index['zeichen'];
	}
	
	$tpl_content->addlist("liste", $alphaList[$i], "tpl/".$s_lang."/hersteller.row.htm");
	
	
	



?>