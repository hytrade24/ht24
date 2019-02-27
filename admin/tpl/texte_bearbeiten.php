<?php
/* ###VERSIONSBLOCKINLCUDE### */


  require_once 'sys/lib.banner.php';
  register('trgfile', 'mime', 'layer', 'dir', 'img', 'savedb');
  
// CUSTOM COLOR IMG ============================================================
  
  $image = ($dir && strlen($img) ? $images[$dir][$img] : NULL);
  #die($path_source."/".$image['path']);
  if (!file_exists($path_source."/".$image['path'])) $image = NULL;
  if ($image)
  {
    $_SESSION['dir'] = $dir;
	$_SESSION['img'] = $img;
  }
  
#echo ht(dump($_SESSION));
  register_array('fontfile','fontsize','ofsx','ofsy','color','string');

// Bild aus Datenbank ==========================================================
  if($fmt && $db)
  {
    $ar_imgdata = $db->fetch_table("select * from bannergenerator where name='".$_GET['fmt']."'"); # Datenbank nach aktuellem Bild abfragen
	$anz_layer = $db->fetch_atom("select count(layer) from bannergenerator where name='".$_GET['fmt']."'"); # Anzahl Layer ermitteln
	#echo $anz_layer."<br />";
	#echo ht(dump($ar_imgdata));
	
	// Falls das vorher ausgewählte Bild mehr Layer hatte, als das jetzige, werden die Überschüssigen Layer nicht
	// überschrieben, daher: unset
	rmlayer();

	for($i=0; $i<$anz_layer; $i++) // Layer aus Datenbankquery holen
	{
	  $_SESSION['fontfile'][$i] = $ar_imgdata[$i]['fontfile'];
	  $_SESSION['fontsize'][$i] = $ar_imgdata[$i]['fontsize'];
	  $_SESSION['ofsx'][$i] = $ar_imgdata[$i]['ofsx'];
	  $_SESSION['ofsy'][$i] = $ar_imgdata[$i]['ofsy'];
	  $_SESSION['color'][$i] = $ar_imgdata[$i]['fontcolor'];
	  $_SESSION['string'][$i] = $ar_imgdata[$i]['string'];
	}
	forward('index.php?page=texte_bearbeiten&layer=0#t0'); // Keine Ahnung wieso, aber ein "refresh" behebt manche Anzeigebugs
  }
  #echo ht(dump($_SESSION));

// Layers ======================================================================
  $ar = array('fontfile','fontsize','ofsx','ofsy','color','string');
  $default = array(reset(array_keys($fonts)),20,10,20,'000000','');


  $anz = count($color);
  $nlayer=$_GET['n'];
  if (isset($nlayer)) switch ($_GET['do'])
  {
    case 'mk':
      $anz = $layer = $_SESSION['layer'] = $nlayer;
      $anz++;
	  #echo ht(dump($ar));
      foreach ($ar as $i=>$n)
      {
        ${$n}[$layer] = $default[$i];
		#echo $n;echo "<br >";echo ht(dump($$n));echo "<br >";#die();
        $_SESSION[$n] = $$n;
      } 
#echo $nlayer;echo '<pre>'; var_dump($_SESSION); echo '</pre><hr>';
#echo ht(dump($_SESSION)); echo $nlayer;
      break;
    case 'rm':
      foreach ($ar as $n)
      {
        array_splice($$n, $nlayer, 1);
        $_SESSION[$n] = $$n;
      }
      $anz--;
      if ($layer>=$nlayer)
        $layer = --$_SESSION['layer'];
      break;
    case 'dn':
      if ($nlayer) foreach ($ar as $n)
      {
        $tmp = array_splice($$n, $nlayer, 1);
        array_splice($$n, $nlayer-1, 0, $tmp);
        $_SESSION[$n] = $$n;
      }
      $layer = --$_SESSION['layer'];
      break;
    case 'up':
      if ($nlayer<$anz-1) foreach($ar as $n)
      {
        $tmp = array_splice($$n, $nlayer, 1);
        array_splice($$n, $nlayer+1, 0, $tmp);
        $_SESSION[$n] = $$n;
      }
      $nlayer++;
      break;
  } // switch
  elseif ('reset'==$_GET['do'])
  {
	rmlayer();
    $layer = 0; // for parent.reload
	forward('index.php?lang='.$lang.'&page=texte_bearbeiten');
  }
  elseif (isset($_POST['ofs_x']))
  {
    $ofsx[$layer] = $_SESSION['ofsx'][$layer] = $_POST['ofs_x'];
    $ofsy[$layer] = $_SESSION['ofsy'][$layer] = $_POST['ofs_y'];
  }

  // Wird nur benötigt, wenn Bilder als Datei gespeichert werden sollen
  // target type
  /* if (!$mime)
    $mime = $images[$dir][$img]['type'];
  $ar_trgtypes = array();
  $ar_tmp = array('gif', 'png', 'jpeg');
  foreach ($ar_tmp as $ext)
    if (function_exists('image'.$ext))
      $ar_trgtypes[] = $ext;
  if (!in_array($mime, $ar_trgtypes))
    $mime = $ar_trgtypes[0];
  $_SESSION['mime'] = $mime; */

// EDIT ========================================================================
  // default values
  if (!$color[$layer]) $color[$layer] = '000000';
  if (!(float)$fontsize[$layer]) $fontsize[$layer] = 20;
  if (is_null($ofsx[$layer])) $ofsx[$layer]=10;
  if (is_null($ofsy[$layer])) $ofsy[$layer]=20;
  #echo ht(dump($_SESSION)); echo "<br>"; echo $layer;

// PREVIEW =====================================================================
  $ar_fmt = explode('x', $dir);
#var_dump($ar_fmt);
if ($image) { 
  $tpl_content->addvar('image_selected',1);
  
  $tpl_content->addvar('fmt0', $ar_fmt['0']);
  $tpl_content->addvar('fmt1', $ar_fmt['1']);
  
  $tpl_content->addvar('sess',$sess);
  
  $tpl_content->addvar('time', time());
  
  $tpl_content->addvar('dir', $dir);
  
  $tpl_content->addvar('trgfile', $trgfile);
  
 // Nur wenn Bilder als Datei gespeichert werden sollen
  /*
  foreach ($ar_trgtypes as $ext)
    $output_trgtypes .= '
    <option '. ($ext==$mime ? 'selected ' : ''). 'value="'. $ext. '">.'.
      $ext. '</option>' ;
  
  $tpl_content->addvar('output_trgtypes', $output_trgtypes);
  */
  $tpl_content->addvar('anz', $anz);
  $tpl_content->addvar('layer', (int)$layer);

for ($i=$anz-1; $i>=0; $i--)
{
  $for_anz .= '
<tr bgcolor="#FFFFFF"><a name="t'. $i. '">
  <td>'. ($i+1) . '</td>
  <td nowrap>
    <table border="0" cellpadding="0" cellspacing="0">
	  <tr>
        <td rowspan="2">
		  <a href="index.php?lang='.$lang.'&page=texte_bearbeiten&layer='.$i.'#t'.$i.'">
		  <img src="gfx/ed.gif" alt="bearbeiten" border="0"></a>
		</td>
        <td>
		  '. ($i<$anz-1 ? '<a href="index.php?lang='.$lang.'&page=texte_bearbeiten&'. $sess. '&n='. $i. '&do=up#t'. ($i+1). '">
		  <img src="gfx/up.gif" width="7" height="4"
          alt="Text nach oben verschieben" border="0"></a>' : '<img src="shim.gif" width="7" height="4">'). '
		</td>
    <td rowspan="2">
	  &nbsp;&nbsp;&nbsp;<a onClick="return confirm(\'Diesen Text löschen?\')"
	  href="index.php?lang='.$lang.'&page=texte_bearbeiten&'. $sess. '&n='. $i. '&do=rm&t='.time().'#t'. ($i ? $i-1 : $i+1). '">
	  <img src="gfx/btn.del.gif" alt="l&ouml;schen" border="0"></a>
	</td>
  </tr>
  <tr>
    <td>'. ($i ? '<a href="index.php?lang='.$lang.'&page=texte_bearbeiten&'. $sess. '&n='. $i. '&do=dn#t'. ($i-1). '">
	  <img src="gfx/dn.gif" width="7" height="4" alt="Text nach unten verschieben" border="0"></a>'
	  : '<img src="shim.gif" width="7" height="4">'). '
	</td>
  </tr>
</table>
</td>
<td style="background-color:#'. $color[$i]. '">#'. $color[$i]. '</td>
<td nowrap>'. stdHtmlentities(shortstr($string[$i])). '</td>
<td>'. $ofsx[$i]. '</td>
<td>'. $ofsy[$i]. '</td>
<td nowrap>'. $fontsize[$i]. 'px / '. stdHtmlentities($fonts[$fontfile[$i]]). '</td>
</a>
</tr>';

if ($i==$layer)
{
  $for_anz .= '
<tr><td colspan="7" bgcolor="#cfcfcf"><h2>Text '. ((int)$layer+1) .' bearbeiten</h2></td></tr>
<tr>
  <td colspan="7">
    
    <table border="0" style="border:1px solid black;">
      <form method="post" action="index.php?lang='.$lang.'">
        <input type="hidden" name="layer" value="'. (int)$layer .'">
		<input type="hidden" name="page" value="texte_bearbeiten">
        <tr>
		  <tr>
            <th>Text:&nbsp;</th>
            <td><input type="text" name="string['.(int)$layer.']" value="'. stdHtmlentities($string[$layer]) .'"></td>
          </tr>
          <th>Schriftart:&nbsp;</th>
          <td><select name="fontfile['.$layer.']">';
  
    foreach ($fonts as $str_file=>$str_name)
	$for_anz .= '<option '. ($str_file==$fontfile[$layer] ? 'selected ' : ''). 'value="'. $str_file.
      '">'. stdHtmlentities($str_name). '</option>';

$for_anz .= ' </select>
              <b>Schriftgr&ouml;&szlig;e: </b>
			  <input type="text" maxlength="4" size="3" name="fontsize['.(int)$layer.']" value="'. (float)$fontsize[$layer] .'"> px
          </td>
        </tr>
		<tr>
          <th>Textfarbe:&nbsp;</th>
          <td>
		    <table border="0" cellpadding="2" cellspacing="0">
			  <tr>
                <td>#</td>
                <td style="background-color:#'. $color[$layer] .'">
				  <input type="text" id="color" maxlength="6" name="color['. (int)$layer .']" value="'. $color[$layer] .'"
                  onBlur="if (this.value.match(/[0-9a-fA-F]{6}/))this.parentElement.style.backgroundColor=\'#\'+this.value;">&nbsp;&nbsp;
				</td>
<script language="JavaScript" type="text/javascript"><!--
var editorfenster=false;
var win_ie_ver = parseFloat(navigator.appVersion.split("MSIE")[1]);
if (navigator.userAgent.indexOf(\'Mac\')        >= 0) { win_ie_ver = 0; }
if (navigator.userAgent.indexOf(\'Windows CE\') >= 0) { win_ie_ver = 0; }
if (navigator.userAgent.indexOf(\'Opera\')      >= 0) { win_ie_ver = 0; }
if (win_ie_ver >= 5.5) 
{
//  window.name = "sp";
  function colorpick()
  {
    var col=
      showModalDialog("tpl/de/color.htm", document.getElementById(\'color\').value,
        "resizable: no; help: no; status: no; scroll: no;");
    if (col != null)
    {
      el = document.getElementById(\'color\');
      el.value=col;
      el.parentElement.style.backgroundColor=\'#\'+col;
    }
/*
     pickwin = window.open(\'color.html\',\'ebgcolor\',
      \'height=180,width=260,resizable=yes,status=yes,dependent=yes\');
*/
  }
  document.write(\'<td><a href="javascript:colorpick();"><img src="gfx/col.gif" width="18" height="18" alt="Farbe w&auml;hlen" border="0"></a></td>\');
}
//--></script>
              </tr>
			</table>
            als Hex-Code angeben (keine Farbnamen!)
          </td>
        </tr>
		<tr>
          <th>Position:&nbsp;</th>
          <td>
            <b>X</b>=<input type="text" id="ofsx" name="ofsx['.(int)$layer.']" maxlength="3" size="3" value="'. (int)$ofsx[$layer] .'"> Abstand von links<br>
            <b>Y</b>=<input type="text" id="ofsy" name="ofsy['.(int)$layer.']" maxlength="3" size="3" value="'. (int)$ofsy[$layer] .'"> Abstand von oben
          </td>
        </tr>
		<tr>
          <td>&nbsp;</td>
          <td><input type="submit" value="&Auml;nderungen &uuml;bernehmen"></td>
        </tr>
	  </form>
	</table>
  </td>

</tr>';
} # end if (EDIT) ==============================================================
} # end loop
} else { $tpl_content->addvar('image_selected',0); } 
$tpl_content->addvar('for_anz', $for_anz);
?>