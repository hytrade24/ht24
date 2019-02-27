<?php
/* ###VERSIONSBLOCKINLCUDE### */


// tables `pageperm` ===========================================================
/*
  $id_role, array ('page'=>1/0)
  -1, array ($id_role => array (page => 1/0))
*/
function pageperm2role_rewrite($id_role=-1, $ar = NULL, $b_push = true)
{
  global $db, $ab_path;

  if ($id_role<0)
    $id_role = $db->fetch_nar("select ID_ROLE, ID_ROLE from role");
  if (is_array ($id_role))
  {
    foreach($id_role as $id)
      pageperm2role_rewrite($id, NULL, false);
    $s_id = ' in ('. implode(', ', $id_role). ')';
  }
  else
  {
    if (!$ar)
      $ar = array_values($db->fetch_nar("select
          if(n.ROOT=1, n.IDENT, concat('admin/', n.IDENT)),
          concat('\\'', if(n.ROOT=1, n.IDENT, concat('admin/', n.IDENT)), '\\' => 1'), p.IDENT
        from nav n
        left join pageperm2role p on p.IDENT=if(n.ROOT=1, n.IDENT, concat('admin/', n.IDENT))
          and p.FK_ROLE=". $id_role. "
        where LFT>1 and n.IDENT>''
        having p.IDENT is null"));
#echo '<hr />', ht(dump($GLOBALS[lastresult])), dump($id_role), ht(dump($ar));

    // role-permission cache aktualisieren
    $s_code = '<?php $nar_pageperm['. $id_role. '] = array ('. implode(', ', $ar). '); ?>';
#echo '<hr>update Role '. $id_role. ': '. stdHtmlentities($s). '<br />'; #die();
    $fp = fopen($filename = $ab_path.'cache/pageperm.'. $id_role. '.php', 'w');
#echo dump($fp);
    fputs($fp, $s_code);
    fclose($fp);
    // Kann Warnungen verursachen wenn der Besitzer der Datei nicht der Benutzer ist, mit dem php ausgefÃ¼hrt wird.
    @chmod($filename, 0777);
    $s_id = '='. $id_role;
  }

  // betroffene User-Datensaetze NULLen
  if ($b_push)
  {
    $ar = $db->fetch_nar("select distinct FK_USER, FK_USER from role2user where FK_ROLE". $s_id);
#echo implode(', ', $ar);
    $db->querynow("update user set SER_PAGEPERM=NULL where ID_USER in (". implode(', ', $ar). ")");
  }
#die();
}

function perm2role_set($prev_id_role,$id_role) {
	global $db;

	if ( $prev_id_role > 0 && $id_role > 0 ) {
		$sql = 'SELECT * 
				FROM perm2role a
				WHERE a.FK_ROLE = ' . $prev_id_role;

		$result = $db->fetch_table( $sql );

		foreach ( $result as $index => $row ) {
			$db->update(
				"perm2role",
				array(
					"FK_ROLE"   =>  $id_role,
					"FK_PERM"   =>  $row["FK_PERM"],
					"BF_ALLOW"  =>  $row["BF_ALLOW"]
				)
			);
		}

	}
}

function pageperm2role_set($id_role, &$ar_perms, $b_uncache=true)
{
  global $db;
#echo "<hr /><b>pageperm2role_set(role=$id_role, uncache=$b_uncache) perms=", ht(dump($ar_perms)), '</b>';
#die(ht(dump($id_role)));
  if ($id_role<0)
  {
    $ar_upd = array ();
    foreach($ar_perms as $id=>$ar)
      if (pageperm2role_set($id, $ar, false))
        $ar_upd[] = $id;
    $s_upd = (count($ar_upd) ? " in (". implode(', ', $ar_upd). ")" : false);
  }
  else
  {
    $s_upd = "=". $id_role;
    $n_chg = false;
    $ar_cp = array ();
    foreach($ar_perms as $s_ident=>$val)
{
#echo "$id_role/$s_ident : $val<br />";
      if ((int)$val)
      {
        $ar_cp[] = "'". str_replace("'", "\\'", $s_ident). "' => 1";
        $result = $db->querynow("delete from pageperm2role where FK_ROLE=". $id_role. " and IDENT='". $s_ident. "'");
        $n_chg += $result['int_result']; // false = no affected rows = no change
      }
      else
      {
        $result = $db->querynow("insert into pageperm2role (FK_ROLE, IDENT) values (". $id_role. ", '". $s_ident. "')");
        $n_chg += $result['rsrc']; // false = duplicate entry = no change
      }
}
#    if ($n_chg)
      pageperm2role_rewrite($id_role);#, $ar_cp);
    return !!$n_chg;
  }
  if ($b_uncache && $s_upd)
  {
    $ar_users = $db->fetch_nar("select distinct FK_USER, FK_USER from role2user
      where FK_ROLE". $s_upd);
    if (count($ar_users))
    {
      $db->querynow("update user set SER_PAGEPERM=NULL where ID_USER in ("
        . implode(', ', $ar_users). ")");
      return true;
    }
    return false;
  }
  return !!$s_upd;
}

/**
  c_val: +,-,0
*/
function pageperm2user_set($id_user, $s_ident, $c_val='0')
{
  global $db;
  if (!$s_ident) return false;
  $sql_ident = mysql_escape_string($s_ident);
  $n_val = 0;
  switch ($c_val)
  {
    case '+':
      $n_val = 1;
      // fallthrough
    case '-':
      $result = $db->querynow("update pageperm2user set B_OVR=". $n_val. " where IDENT='". $sql_ident. "' and FK_USER=". $id_user);
      if (!$result['int_result'])
        $result = $db->querynow("insert into pageperm2user (IDENT, FK_USER, B_OVR)
          values ('". $sql_ident. "', ". $id_user. ", ". $n_val. ")");
      $b_upd = $result['rsrc'];
      break;
    case '0':
      $result = $db->querynow("delete from pageperm2user where IDENT='". $sql_ident. "' and FK_USER=". $id_user);
      $b_upd = $result['int_result'];
      break;
    default:
      $b_upd = false;
  }
  if ($b_upd)
    $db->querynow("update user set SER_PAGEPERM=NULL where ID_USER=". $id_user);
  return $b_upd;
}
// tables `katperm` ============================================================
/*
  $id_role, array (id_kat=>1/0)
  -1, array ($id_role => array (id_kat => 1/0))
*/
function katperm2role_rewrite($id_role=-1, $ar = NULL, $b_push = true)
{
  global $db, $ab_path;

  if ($id_role<0)
    $id_role = $db->fetch_nar("select ID_ROLE, ID_ROLE from role");
  if (is_array ($id_role))
  {
    foreach($id_role as $id)
      katperm2role_rewrite($id, NULL, false);
    $s_id = ' in ('. implode(', ', $id_role). ')';
  }
  else
  {
    if (!$ar)
      $ar = array_values($db->fetch_nar("select
          ID_KAT,
          concat(ID_KAT, ' => 1'), FK_KAT
        from kat n
        left join katperm2role p on p.FK_KAT=ID_KAT
          and p.FK_ROLE=". $id_role. "
        where ID_KAT>''
        having FK_KAT is null"));
#echo '<hr />', ht(dump($GLOBALS[lastresult])), dump($id_role), ht(dump($ar));
#die(ht(dump($ar)));
    // role-permission cache aktualisieren
    $s_code = '<?php $nar_katperm['. $id_role. '] = array ('. implode(', ', $ar). '); ?>';
#echo '<hr>update Role '. $id_role. ': '. stdHtmlentities($s). '<br />'; #die();
    $fp = fopen($filename = $ab_path.'cache/katperm.'. $id_role. '.php', 'w');
#echo dump($fp);
    fputs($fp, $s_code);
    fclose($fp);
    chmod($filename, 0777);
    $s_id = '='. $id_role;
  }

  // betroffene User-Datensaetze NULLen
  if ($b_push)
  {
    $ar = $db->fetch_nar("select distinct FK_USER, FK_USER from role2user where FK_ROLE". $s_id);
#echo implode(', ', $ar);
    $db->querynow("update user set SER_KATPERM=NULL where ID_USER in (". implode(', ', $ar). ")");
  }
#die();
}

function katperm2role_set($id_role, &$ar_perms, $b_uncache=true)
{
  global $db;
#echo "<hr /><b>katperm2role_set(role=$id_role, uncache=$b_uncache) perms=", ht(dump($ar_perms)), '</b>';
#die(ht(dump($id_role)));
  if ($id_role<0)
  {
    $ar_upd = array ();
    foreach($ar_perms as $id=>$ar)
      if (katperm2role_set($id, $ar, false))
        $ar_upd[] = $id;
    $s_upd = (count($ar_upd) ? " in (". implode(', ', $ar_upd). ")" : false);
  }
  else
  {
    $s_upd = "=". $id_role;
    $n_chg = false;
    $ar_cp = array ();
    foreach($ar_perms as $id_cat=>$val)
{
#echo "$id_role/$id_cat : $val<br />";
      if ((int)$val)
      {
        $ar_cp[] = "$id_cat) => 1";
        $result = $db->querynow("delete from katperm2role where FK_ROLE=". $id_role. " and FK_KAT=". $id_cat);
        $n_chg += $result['int_result']; // false = no affected rows = no change
      }
      else
      {
        $result = $db->querynow("insert into katperm2role (FK_ROLE, FK_KAT) values (". $id_role. ", ". $id_cat. ")");
        $n_chg += $result['rsrc']; // false = duplicate entry = no change
      }
}
#    if ($n_chg)
      katperm2role_rewrite($id_role);#, $ar_cp);
    return !!$n_chg;
  }
  if ($b_uncache && $s_upd)
  {
    $ar_users = $db->fetch_nar("select distinct FK_USER, FK_USER from role2user
      where FK_ROLE". $s_upd);
    if (count($ar_users))
    {
      $db->querynow("update user set SER_KATPERM=NULL where ID_USER in ("
        . implode(', ', $ar_users). ")");
      return true;
    }
    return false;
  }
  return !!$s_upd;
}

function kat_cache_rewrite()
{
  global $db, $nest, $root, $ab_path, $s_lang;
  $langs = $db->fetch_table("select * from lang ");
  for ($k=0; $k<count($langs); $k++)
  {
    $langval = $langs[$k]['BITVAL'];
    $root_id = (int)$db->fetch_atom("select ID_KAT from kat
      where ROOT=". $root. " and LFT=1");

    $res = $nest->nestSelect('', '', ' max(u.LFT) as P_LFT,', true);
    $nar_kids = array ();
    $nar_lft2id = array ();  // 1 => $root_id
	if(mysql_num_rows($res) > 0) {
		while ($row = mysql_fetch_assoc($res))
		  $nar_lft2id[(int)$row['LFT']] = (int)$row['ID_KAT'];
		mysql_data_seek($res, 0);
		while ($row = mysql_fetch_assoc($res))
		  $nar_kids[(int)$nar_lft2id[$row['P_LFT']]][] = $row['ID_KAT'];
		mysql_data_seek($res, 0);
	}
	$ar_dump = array (0 => array ('KIDS' => $nar_kids[0] ? $nar_kids[0] : array ()));
	$ar_tpl = array();
	while ($row = mysql_fetch_assoc($res))
	{
	  $row['KIDS'] = (($tmp = $nar_kids[$row['ID_KAT']]) ? $tmp : array ());
	  $parent_id = (int)$nar_lft2id[$row['P_LFT']];
	  $row['PARENT'] = ($root_id == $parent_id ? 0 : $parent_id);
	  $row['LABEL'] = $row['V1'];
	  $row['LEVEL'] = $row['level'];
	  $row['B_VIS'] = !!$row['B_VIS'];
	  $ar_dump[$row['ID_KAT']] = $row;
	  if($row['B_VIS'])
	  {
		$ar_tpl[] = $row;
	  }

	}

    $tpl = new Template(CacheTemplate::getHeadFile('tpl/'.$s_lang.'/empty.htm'));
    $tpl->tpl_text = '<ul>{liste}</ul>';
    $tpl->addlist("liste", $ar_tpl, $ab_path."tpl/".$langs[$k]['ABBR']."/artikelkat.row.htm");
    file_put_contents($filename = $ab_path."cache/kats/artikelkat.".$langs[$k]['ABBR'].".htm", $tpl->process());
    chmod($filename, 0777);
    $s_code = "<". "?php \$ar_nav = ". php_dump($ar_dump). "; ?". ">";
#die(ht(dump($s_code)));
    $fp = fopen($filename = $ab_path.'cache/kat'. $root. '.'. $langs[$k]['ABBR']. '.php', 'w')
      or die("Filesystem error");
    @fwrite($fp, $s_code);
    fclose($fp);
    chmod($filename, 0777);
   # die("STOP");
  } // for languages
}
?>