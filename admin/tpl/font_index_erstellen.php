<?php
/* ###VERSIONSBLOCKINLCUDE### */


require_once 'banner_inc.php';

$ar_fonts = array();
$ar_allstyles = array('bold','italic','underline','outline','shadow','condensed','extended',
#  'res8', 'res9', 'res10', 'res11', 'res12', 'res13', 'res14', 'res15'
);
if ($dp = @opendir($path_fonts))
{
  while ($fn = readdir($dp))
    if ('.ttf'==strrchr(strtolower($fn),'.'))
    {
      $err = false;
      $fp = fopen("$path_fonts/$fn", 'rb');
      $info = array();

      // how many tables
      fseek($fp, 4);
      $numTables = tblread($fp, 'USHORT');
      $lowestRecPPEM = tblread($fp, 'USHORT');
      fseek($fp, 4, SEEK_CUR);

      // find 'head' and 'name' tables in table index, read offset and length
      $left2find = 2;
      while($numTables-- && $left2find)
      {
        $tag = fread($fp, 4);
        if ('head'==$tag || 'name'==$tag)
        {
          tblread($fp, 4);
          ${"ofs_$tag"} = tblread($fp, 'ULONG');
          ${"len_$tag"} = tblread($fp, 'ULONG');
          $left2find--;
        }
        else
          fseek($fp, 12, SEEK_CUR);
      }

      // read magic number and macStyle from 'head' table
      fseek($fp, $ofs_head+12);
      $hex_magic = sprintf('%08x', tblread($fp, 'ULONG'));
      fseek($fp, 32, SEEK_CUR);
      $macStyle = tblread($fp, 'USHORT');

      // jump to name table
      fseek($fp, $ofs_name+2);
      $ofs_tmp = min(48, 12*(tblread($fp, 'USHORT')-1));
      $ofs_storage = tblread($fp, 'USHORT');
      // read string from 5th record
      fseek($fp, 8+$ofs_tmp, SEEK_CUR);
      if ($len_string = tblread($fp, 'USHORT'))
      {
        $ofs_string = tblread($fp, 'USHORT');
        fseek($fp, $ofs_name + $ofs_storage + $ofs_string);
        $info['name'] = tblread($fp, $len_string);
      }
      else
        $info['name'] = NULL;
      fclose($fp);

      $v = $macStyle;
      $ar_styles = array();
      for($i=0; $i<count($ar_allstyles); $i++,$v=$v>>1)
        if ($v&1) $ar_styles[] = $ar_allstyles[$i];
      $info['styles'] = (count($ar_styles) ? implode(', ', $ar_styles) : '<i>none</i>');

      $info['minsize'] = $lowestRecPPEM;

      if ($hex_magic!='5f0f3cf5')
        $err = "magic number mismatch (expected 5f0f3cf5, found $hex_magic)";
      else
      {
        $fn = substr($fn,0,-4);
        $s = trim($info['name']);
        $s = (strlen($s) ? $s : $fn);
        $ar_fonts[$s] = "'$fn'=>'$s'";
        if (count($info))
        {
          $err = array();
          foreach($info as $k=>$v) $err[] = "$k: <b>$v</b>";
          $err = implode('; ', $err);
        }
        else
          $err = '<span class="error">ok</span>';
      }
      $tpl_content->addvar("error", "<b>$fn</b> - $err<br>");
    }
  closedir($dp);
}
ksort($ar_fonts);

if (!count($ar_fonts))
  $tpl_content->addvar("arfonts", '<span class="error">Keine Schriftarten gefunden</span><br>');
write_section("$str_indexfile.php", 'fonts', "\$fonts = array(\n  ". implode(",\n  ", $ar_fonts). "\n);");

?>