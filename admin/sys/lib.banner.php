<?php
/* ###VERSIONSBLOCKINLCUDE### */

  // path to template image directory (relative or absolute)
  $path_source = '../'.$GLOBALS['nar_systemsettings']['SITE']['PATH_UPLOADS'].'/bildergenerator/vorlagen';

  // path to output image directory
  $path_target = '../'.$GLOBALS['nar_systemsettings']['SITE']['PATH_UPLOADS'].'/bildergenerator/fertige';

  // path to font directory (relative or absolute)
  $path_fonts = 'fonts';

  // backup inc.index on execution of an index script?
  $fl_backup = false;

  // style of cursor around selected image
  // options: rainbow, silver, static
  // style classes s0 and s1 define 'static' view
  $cursor = 'rainbow'; # other options: silver, static

  // change these settings only if you know what you do!
  define ('FAIL', '<span class="error">fail</span>');
  $str_indexfile = '../cache/fonts';

// initialization
  session_start();
  $sess = session_name().'='.session_id();
  $images = $fonts = array();
  @include "../cache/$str_indexfile.php";

// cursor colors
$ar_cursor = array(
  'silver'=>array(67, "
    '#ffffff','#eeeeee','#dddddd','#cccccc',
    '#bbbbbb','#aaaaaa','#999999','#888888',
    '#777777','#666666','#555555','#444444',
    '#333333','#222222','#111111','#000000',
    '#111111','#222222','#333333','#444444',
    '#555555','#666666','#777777','#888888',
    '#999999','#aaaaaa','#bbbbbb','#cccccc',
    '#dddddd','#eeeeee','#ffffff'
  "),
  'rainbow'=>array(125, "
    '#ff0000', '#ff2300', '#ff4600', '#ff6900', 
    '#ff8c00', '#ff9e00', '#ffb100', '#ffc400', 
    '#ffd700', '#cbd40c', '#98d219', '#65cf25', 
    '#32cd32', '#259965', '#196698', '#0c33cb', 
    '#0000ff', '#2500f4', '#4a00e9', '#6f00de', 
    '#9400d3', '#ae009e', '#c90069', '#e40034'
  ")
);

$ar_types = array(1=>'gif',2=>'jpeg',3=>'png');

function get_vorlagen() {
global $path_source,$ar_types,$tpl_content;


#========================================================= Bilder

  $q_dirs = array($path_source);
  $images = array();

  foreach ($ar_types as $i=>$ext)
    if (!function_exists('imagecreatefrom'.$ext))
      unset($ar_types[$i]);

  if (count($ar_types))
  {
    $tmp = implode(', ', $ar_types);
    $ar_typeids = array_keys($ar_types);
	
    while ($str_dirname = array_shift($q_dirs))
    {
      if(@opendir($str_dirname))
	  {
	    $dp = @opendir($str_dirname);
	  }
      if ($dp)
      {
        while ($str_direntry = readdir($dp))
        {
          $str_path = "$str_dirname/$str_direntry";
          if ('..'==$str_direntry || '.'==$str_direntry || strstr($str_direntry, "thumb_"))
            /* nop */;
          elseif (is_dir($str_path))
          {
            $q_dirs[] = $str_path;
          }
          elseif (($ar_data = @getimagesize($str_path, $ar_info)) &&
            is_array($ar_data) && in_array($ar_data[2], $ar_typeids))
          {
            list($w, $h, $type) = $ar_data;
            $str_type = $ar_types[$type];
            $ani = '';
            for($i=0; $i<4; $i++) unset($ar_data[$i]);
            unset($ar_data['mime']);
            $ar_data['path'] = substr($str_path,1+strlen($path_source));
            $ar_data['type'] = $str_type;
            $images["{$w}x{$h}$ani"][] = $ar_data;
  
          }
        }
        closedir($dp);
      }
    } // end while
  }

function pathcmp($a,$b) { 
	return strcmp($a['path'], $b['path']); 
}

uksort($images, 'nxncmp');
$ar0 = array();
foreach($images as $str_format=>$ar_files)
{
  usort($ar_files, 'pathcmp');
  $ar1 = array();
  foreach($ar_files as $ar_data)
  {
    $ar2 = array();
    foreach($ar_data as $k=>$v)
      $ar2[] = "'$k'=>". (is_int($v) ? $v : "'$v'");
    $ar1[] = "\n    array(". implode(', ', $ar2). ')';
  }
  $ar0[] = "\n  '$str_format'=>array(". implode(',', $ar1). "\n  )";
}
if (!count($ar0))
  $tpl_content->addvar("noimgfound", '<span class="error">Keine Bilder gefunden</span><br>');
else
   $tpl_content->addvar("bilder", '<h2>... Bilder geladen ...');

  //  echo implode(',', $ar0); 

eval ("\$images = array(". implode(',', $ar0). "\n);");
//echo ht(dump($images ));
	return $images;
}

  function rmlayer()
  {
    unset($_SESSION['fontfile']);
	unset($_SESSION['fontsize']);
	unset($_SESSION['ofsx']);
	unset($_SESSION['ofsy']);
	unset($_SESSION['color']);
	unset($_SESSION['string']);
  }
  
  function hex2rgb($hex)
  {
        $int = hexdec($hex);
		return array("red" => 0xFF & ($int >> 0x10),
		        "green" => 0xFF & ($int >> 0x8),
		        "blue" => 0xFF & $int);
  }

  function register()
  {
    $args = func_get_args();
    foreach($args as $v)
    {
      if (isset($_POST[$v]))
        $_SESSION[$v] = $vv = trim($_POST[$v]);
      else
      if (isset($_GET[$v]))
        $_SESSION[$v] = $vv = trim($_GET[$v]);
      else
        $vv = $_SESSION[$v];
      $GLOBALS[$v] = $vv;
    }
  }

  function register_array()
  {
    $args = func_get_args();
    foreach($args as $v)
    {
      $len = 0;
      if (!is_array($s = $_SESSION[$v]) || !count($s)) $s = array();
      else $len = 1+max(array_keys($s));
      if (!is_array($g = $_GET[$v]) || !count($g)) $g = array();
      else $len = max($len, 1+max(array_keys($g)));
      if (!is_array($p = $_POST[$v]) || !count($p)) $p = array();
      else $len = max($len, 1+max(array_keys($p)));
      $vv = array();
      for($i=0;$i<$len;$i++)
        if (!is_null($v2 = $p[$i]))
          $vv[$i] = $v2;
        else
        if (!is_null($v2 = $g[$i]))
          $vv[$i] = $v2;
        else
          $vv[$i] = $s[$i];
      $_GLOBALS[$v] = $_SESSION[$v] = $vv;
    }
  }

  function msg($msg, $ok=NULL)
  {
    #echo $msg;
    if (!is_null($ok)) $void= ' - '. ($ok ? 'ok' : FAIL);
    #echo "<br>\n";
    return $ok;
  }

  function write_section2($str_filename, $str_sectionname, $str_data)
  {
    global $fl_backup;
  
      $text = implode('', file($str_filename));
      if ($fp = msg("<span class=\"strong\">open <i>str_filename</i> for write</span>",
        @fopen($str_filename, 'w')))
      {
        $expr_section = preg_quote($str_sectionname, '/');
#die(var_dump($expr_section));
        $str_expr = '/((^|\r|\n)#'. $expr_section. '(?=[\r\n])).*((\r|\n)#\/'. $expr_section. '(\r|\n|$))/s';
		if (preg_match($str_expr, $text))
#;else echo('<span class="error">ouch</span><br>');if(1)
        {
          #echo "replacing section '$str_sectionname'<br>";
          $reg_what = preg_quote($str_sectionname, '/');
          $text = preg_replace($str_expr, '$1'."\n$str_data".'$3', $text);
		  
        }
        else
        {
          #echo "creating section '$str_sectionname'<br>";
          $text = preg_replace("/((\r|\n)\#\/\*(\r|\n|$))/s", "\n#$str_sectionname\n$str_data\n#/$str_sectionname".'$1', $text);
        }
		echo $text;
        fputs($fp, $text);
        fclose($fp);
      }
   
  }

  function str2dec($str, $fl_signed=false)
  {
    $n = strlen($str);
    for($i=$v=0; $i<strlen($str); $i++)
      $v = $v*256 + ord(substr($str,$i));
    if ($fl_signed && ($v & (1<<($i-1))))
      $v-=(1<<$i);
    return $v;
  }
  function str2hex($str)
  {
    $v='';
    for($i=0; $i<strlen($str); $i++)
      $v .= sprintf('%02x', ord(substr($str,$i,1)));
    return $v;
  }
  function tblread($fp, $type)
  {
    if (is_int($type))
      return fread($fp, $type);
    switch (strtoupper($type))
    {
      case 'BYTE':
        return str2dec(fread($fp,1));
      case 'CHAR':
        return str2dec(fread($fp,1), 1);
      case 'USHORT':
      case 'UFWORD':
        return str2dec(fread($fp,2));
      case 'SHORT':
      case 'FWORD':
        return str2dec(fread($fp,2), 1);
      case 'ULONG':
        return str2dec(fread($fp,4));
      case 'LONG':
        return str2dec(fread($fp,4),1);
      case 'FIXED':
        $a = str2dec(fread($fp,2), 1);
        $b = str2dec(fread($fp,2));
        return $a + (double)($b / 65536);
      case 'F2DOT14':
        $v = str2dec(fread($fp,2));
        $a = $v>>14;
        $b = $v &~ (3<<14);
        return $a + (double)($b / (1<<14));
      case 'LONGDATETIME':
        $s = fread($fp, 8);
        /*xxx*/
        return $s;
      case 'FUNIT':
      default:
        die ("tblread: unknown type '$type'");
    }
  }

  function myround($x, $n=0)
  {
    $f = pow(10,$n);
    return floor(0.5 + $x*$f)/$f;
  }

  function shortstr($str, $len=50)
  {
    return (strlen($str)>$len
      ? substr($str, 0, $len-4). ' ...'
      : $str
    );
  }

  function nxncmp($a,$b)
  {
    preg_match('/^(\d+)x(\d+)([^\d]?.*)/', $a, $aa);
    preg_match('/^(\d+)x(\d+)([^\d]?.*)/', $b, $bb);
    if ($c = (int)$aa[1] - (int)$bb[1]) return $c;
    if ($c = (int)$aa[2] - (int)$bb[2]) return $c;
    return strcmp($aa[3], $bb[3]);
  }
?>