<?php
/* ###VERSIONSBLOCKINLCUDE### */



 if(count($_POST))
 {
  $tpl_content->addvars($_REQUEST);
  $css = @file_get_contents('../skin/'.$_REQUEST['CSSFILE']);
  if(!$css)
    $tpl_content->addvar('err', 'File not exists, or could not be read!');
  preg_match_all("%(\.)(.*?)(\{)(.*?)(\})%si", $css, $ar);
  $code = array ();
  $code[] = '<?xml version="1.0" encoding="utf-8" ?>';
  $code[] = "\r\n<Styles>\r\n";
  for ($i=0; $i<count($ar[2]); $i++)
  {
   if(!preg_match("/^[a-z0-9]*$/si", trim($ar[2][$i])))
     ; //echo $ar[2][$i]."<br />";
   else
   {
  $ar[4][$i] = str_replace("\n", '', trim($ar[4][$i]));
  $ar[4][$i] = str_replace("\r", '', trim($ar[4][$i]));
  $ar[2][$i] = trim($ar[2][$i]);
  $code[] = '<Style name="'.$ar[2][$i].'" element="span">
    <Attribute name="style" value="'.$ar[4][$i].'" />
  </Style>';
  $code[] = '<Style name="'.$ar[2][$i].'" element="p">
    <Attribute name="style" value="'.$ar[4][$i].'" />
  </Style>';
  $code[] = '<Style name="'.$ar[2][$i].'" element="td">
    <Attribute name="style" value="'.$ar[4][$i].'" />
  </Style>';
  $code[] = '<Style name="'.$ar[2][$i].'" element="th">
    <Attribute name="style" value="'.$ar[4][$i].'" />
  </Style>';
  $code[] = '<Style name="'.$ar[2][$i].'" element="table">
    <Attribute name="style" value="'.$ar[4][$i].'" />
  </Style>';
  $code[] = '<Style name="'.$ar[2][$i].'" element="div">
    <Attribute name="style" value="'.$ar[4][$i].'" />
  </Style>';
   }
  }
  $code[] = '</Styles>';
  $p = @fopen('../fckeditor/fckstyles.xml', 'w+');
  if(!$p)
    $tpl_content->addvar('err', 'Could not open XML File');
  else
  {
    $write = fwrite($p, implode($code));
    if(!$write)
    $tpl_content->addvar('err', 'Could not write XML File');
  else
      $tpl_content->addvar('msg', 'File written!');
  }
  @fclose($p);
  #echo ht(dump($code));
  #echo ht(dump($ar));
 }
 else
   $tpl_content->addvar('CSSFILE', 'ebiz.css');

?>