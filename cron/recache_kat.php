<?php
/* ###VERSIONSBLOCKINLCUDE### */



global $db;
$tree_name = $GLOBALS['SYSNAME']; // <-- kommt aus der cronjob.php
$tree = trim(str_replace("kat_", "", $tree_name));

$tree_small = strtolower($tree);
$tree_big = strtoupper($tree);

#echo ("small: ".$tree_small." BIG: ".$tree_big."\n");

	function suchen($level, $ar)
	{
	  $ret = array();
	  $break = 2;
	  $c=0;
	  $start = true;
	  if($level == 0)
	    ;//echo dump($ar);
	  foreach ($ar as $key => $value)
	  {

		if($value['PARENT'] == $level)
		{
		  if(empty($value) || !$value['VISIBILITY'])
		  {
			continue;
		  }
		  $c++;
		  $br = 0;
		  if($start)
		    $value['START']=1;
		  if($c == $break)
		  {
		    $value['BREAK']=1;
			$br=$c;
			$c=0;
		  }

		  $ret[] = $value;
		  $start = false;
		}
	  } // for
	  if(!empty($ret))
	  {
	    $all = count($ret);
		$last = $all-1;
		$ret[$last]['END']=1;
		$ret[$last]['BREAK'] = NULL;
		$min  = $break-$c;
		$ret[$last]['ROWSPAN'] = ($min != $break ? $min : 0);
	  } // ret ist gefüllt
	  #echo "alle :: ".$all." cc :: ".$cc."\n";
	 # echo "Aufruf fuer ID: ".$level." macht ".count($ret)." Kids\n";
	  return $ret;
	} //

	function addlinkpath(&$row, $i)
	{
      global $db;

	  /* Geht auch nicht zu viel RAM
	  if($row['ID_TREE_SCRIPT'] > 22222222222222222222222222222222222222220)
	  {
	     $mykids=array();
		 $mykids[] = $row['ID_TREE_SCRIPT'];
		 $res = $db->querynow("select ID_TREE_SCRIPT from tree_script where `PARENT`=".$row['ID_TREE_SCRIPT']);
		 while($row2=mysql_fetch_row($res))
		   $mykids[] = $row2[0];
		 echo $row['V1']."\n";
		 #die(dump($mykids));
		 $row['scount'] = $db->fetch_atom("select count(*) from script where
		   OK=3 and FK_KAT in (".implode(",", $mykids).")");
	  } // script

	  */

	  $ident=false;
	  foreach($row as $key => $value)
	  {
	    if(strstr($key, "ID_"))
		{
		  $ident = $key;
		  break;
		}
	  }
	  $hack = explode("_", $ident);
	  $tree_small = strtolower($hack[2]);
	  $tree_big = $hack[2];
	  if(!$tree_small)
	    return "";
	  //echo "small: ".$tree_small."\n";
	  $baum = new baum($tree_small);
	  $baum->readPath($row['ID_TREE_'.$tree_big]);
	  $ar = array_reverse($baum->ar_path_all);
	  #$ar[] = $row;
	  $c=count($ar);
	  $path=array();
	  $n=0;
	  for($i=$c; $i>=0; $i--)
	  {
	    // wenn Elternelement wieder rein soll,
		// muss aus der 2 eine 3 werden
		if($n == 2)
		  break;
		$path[] = $ar[$i]['V1'];
		$n++;
	  } // for path
	  $path = array_reverse($path);
	  $row['LINKPATH'] = implode(" ", $path);
	 #echo dump($row);
	} // addlinkpath

	function addlinkpath2(&$row, $i)
	{
      //echo dump($row);
	  $ident=false;
	  foreach($row as $key => $value)
	  {
	    if(strstr($key, "ID_"))
		{
		  $ident = $key;
		  #echo $ident."\n";
		  break;
		}
	  }
	  $hack = explode("_", $ident);

	  $tree_small = strtolower($hack[2]);
	  if(!$tree_small)
	    return "";
	  $tree_big = $hack[2];
	  #echo "FKT linkpath2: ".$ident." \$baum = new baum($tree_small);\n";
	  $baum = new baum($tree_small);
	  $baum->readPath($row['ID_TREE_'.$tree_big]);
	  $ar = array_reverse($baum->ar_path_all);
	  #$ar[] = $row;
	  $c=count($ar);
	  $path=array();
	  $n=0;
	  for($i=$c-1; $i>=0; $i--)
	  {
	    // wenn Elternelement wieder rein soll,
		// muss aus der 2 eine 3 werden
	    if($n == 2)
		  break;
		$path[] = $ar[$i]['V1'];
		$n++;
	  } // for path
	  $path = array_reverse($path);
	  $row['LINKPATH'] = implode(" ", $path);
	} // addlinkpath2()



  ### Cache Funktionen holen
  include_once $ab_path.'admin/sys/lib.baum.php';

  $res = $db->querynow("select * from lang where B_PUBLIC=1");
  while($row = mysql_fetch_assoc($res['rsrc']))
  {

	$GLOBALS['langval'] = $row['BITVAL'];
	$GLOBALS['s_lang'] = $row['ABBR'];
	#echo "\$baum = new baum($tree_small);\n";
	$baum = new baum($tree_small);
    $baum->readTree(0);
	$tmp = array();
    #echo dump($baum->ar_baum_all);
	for($i=0; $i<count($baum->ar_baum_all); $i++)
	{
	  //$baum->ar_baum_all[$i]['tree_small'] = $tree_small;
	  //$baum->ar_baum_all[$i]['tree_big'] = $tree_big;
	  $tmp[$baum->ar_baum_all[$i]['ID_TREE_'.$tree_big]] = $baum->ar_baum_all[$i];
	} // for gesamter Baum

	### Baum nach ID Sortiert durchlaufen
	$level = 0;
	$ar=array();
	#echo dump($tmp);
	$n=0;
	foreach($tmp as $key => $array)
	{
	    if($array['VISIBILITY'] != 1)
		  continue;
		$kids = suchen($key, $tmp);
	    #die(dump($kids));
		$array['is_current']=1;

		### ariane Faden
		$baum->ar_path_all = array();
		$baum->ar_path = array();

		$baum->readPath($key);
		$baum->ar_path_all = array_reverse($baum->ar_path_all);
		$c = count($baum->ar_path_all);
		$baum->ar_path_all[$c-1]['is_current']=1;
#die(dump($baum));
		$tpl = new Template($ab_path."tpl/".$row['ABBR']."/cache.kat_ariane.".$tree_small.".htm");
		$html = $tpl->tpl_text;
	    #die(dump($tpl));
		$tpl->addlist("liste", $baum->ar_path_all, $ab_path."tpl/".$row['ABBR']."/cache.kat_ariane.".$tree_small.".row.htm", "addlinkpath2");
	    $fp = fopen($file_name = $ab_path."cache/kats/".$row['ABBR'].".".$tree_small.".ariane.".$array['ID_TREE_'.$tree_big].".htm", "w");
	    fwrite($fp, $db_ariane = $tpl->process());
	    fclose($fp);
	    chmod($file_name, 0777);

		$hack = explode("{liste}", $html);
		#die(dump($hack));
		$db_ariane = str_replace($hack[0], "", $db_ariane);
		$db_ariane = trim(str_replace($hack[1], "", $db_ariane));
		if(substr($db_ariane, 0, 1) == "/")
		  $db_ariane = substr($db_ariane, 1);

		### ariane in DB ablegen
		$ins=mysql_query($query_x= "update string_tree_".$tree_small."
		  set T1='".sqlString($db_ariane)."' where FK=".$key." and S_TABLE='tree_".$tree_small."'
		  and BF_LANG=".$row['BITVAL']."
		  ");


	  if(!empty($kids))
	  {
	    $tpl = new Template($ab_path."tpl/".$row['ABBR']."/cache_kats.".$tree_small.".htm");
		$tpl->addlist("liste", $kids, $ab_path."tpl/".$row['ABBR']."/cache_kats.".$tree_small.".row.htm","addlinkpath");
	    $fp = fopen($file_name = $ab_path."cache/kats/".$row['ABBR'].".".$tree_small.".".$key.".htm", "w");
	    fwrite($fp, $tpl->process());
	    fclose($fp);
	    chmod($file_name, 0777);

	  }
	  else
	  {
	    $fp = fopen($file_name = $ab_path."cache/kats/".$row['ABBR'].".".$tree_small.".".$key.".htm", "w");
		@fwrite($fp, "<!-- empty -->");
		@fclose();
	    chmod($file_name, 0777);
		//touch($ab_path."cache/kats/".$row['ABBR'].".".$tree_small.".".$key.".htm");
	  }
	  if($level == 0)
	  {
	    $kids = suchen(0, $tmp);
		#echo dump($kids);
		if(!empty($kids))
	    {
		  #echo "kids nicht leer: ".$level."\n";
		  #$ar[$level] = $kids;

	      $tpl = new Template($ab_path."tpl/".$row['ABBR']."/cache_kats.".$tree_small.".htm");
		  $tpl->addlist("liste", $kids, $ab_path."tpl/".$row['ABBR']."/cache_kats.".$tree_small.".row.htm","addlinkpath");
	      $fp = fopen($file_name = $ab_path."cache/kats/".$row['ABBR'].".".$tree_small.".0.htm", "w");
	      fwrite($fp, $tpl->process());
	      fclose($fp);
	      chmod($file_name, 0777);
	    }
	  } // level 0
	  $level=$key;
	} // zweite for über $tmp

	#echo dump($ar);
	### Abbruch um Anzeige besser lesen zu können
	//break;
  } // while sprachen

?>