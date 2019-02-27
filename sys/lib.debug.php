<?php
/* ###VERSIONSBLOCKINLCUDE### */


if (!function_exists("dump")) {
    
  /**
  * puffert die Ausgabe von var_dump und entfernt überflüssige Zeilenumbrüche (diejenigen hinter =>)
  *
  * @param mixed $var
  * @return string
  */
  function dump($var)
  {
    ob_start();
    var_dump($var);
    $ret = preg_replace("/=>\s*/", '=>', ob_get_contents());
    ob_end_clean();
    return $ret;
  } // function dump

}

if (!function_exists("ht")) {
  
  /**
  * schickt den Parameter durch htmlentities und baut ein <pre>-Tag drum herum
  *
  * @param string $str
  * @return string
  */
  function ht($str)
  {
    return '<pre>'. stdHtmlentities($str). '</pre>';
  } // function ht
  
}

/**
* gibt eine Liste à la ebiz_db::fetch_table (siehe lib.db) tabellarisch aus
*
* @param array $data (int=>assoc)
* @return string
*/
  function listtab($data)
  {
    if (!is_array ($data) || !count($data))
      return ht(dump($data));
    foreach($data as $row)
      foreach($row as $k=>$v)
        $head[$k] = '
  <th>'. stdHtmlentities($k). '</th>';
    $ret = array ('<table border="1"><tr>', implode('', $head), '
</tr>');
    foreach($data as $row)
    {
      $line = array ();
      foreach($head as $k=>$v)
        $line[] = '
  <td>'. (is_null($row[$k])
          ? '<i>NULL</i>'
          : ht(dump($row[$k]))
        ). '</td>';
      $ret[] = '<tr>'. implode('', $line). '
</tr>';
    }
    $ret[] = '</table>';
    return implode('', $ret);
  }
?>