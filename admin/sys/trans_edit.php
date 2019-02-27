<?php
/* ###VERSIONSBLOCKINLCUDE### */


// POST (save)
if (count($_POST) && 'sv'==$do && $fk = (int)$_POST['id'])
{
  $s_ftable = $_POST['tbl'];
  $bak = $langval;
  $langval = $_POST['editlang'];
  $data = array (
    'ID_'. strtoupper($s_ftable) => $id,
    'V1'=> $_POST['V1'],
    'V2'=> $_POST['V2'],
    'T1'=> $_POST['T1']
  );
#die(ht(dump($data)));
  $db->update($s_ftable, $data);
  $langval = $bak;
}

?>