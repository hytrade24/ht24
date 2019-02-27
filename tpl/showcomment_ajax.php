<?php

$SILENCE = false;

$id = ($_GET['id'] ? (int)$_GET['id'] : 0);
$table = ($_GET['kat'] ? $_GET['kat'] : 0);

if($id)
{
  $list_res = $db->fetch_table("select k.FK, k.ID_KOMMENTAR_".strtoupper($table)." as ID_KOMMENTAR, k.FK_USER, left(k.KOMMENTAR_PARSED, 200) as VORSCHAU, k.STAMP, 
  				u.NAME as USERNAME, n.OK
  			 from kommentar_".strtolower($table)." k 
 			left join user u on u.ID_USER = k.FK_USER
			left join ".strtolower($table)." n on n.ID_".strtoupper($table)." = k.FK
			where k.FK = ".(int)$id."
			order by k.STAMP");
			
  $tpl_content->addlist("liste", $list_res, "tpl/".$s_lang."/showcomment_ajax.row.htm");
  
}

?>