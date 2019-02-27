<?php
/* ###VERSIONSBLOCKINLCUDE### */


$id = (int)$_REQUEST['ID_PERM'];
$err = array ();
if (count($_POST))
{
  switch ($do = $_REQUEST['do'])
  {
    case 'sv':
      if (!($s = $_POST['IDENT'] = trim($_POST['IDENT'])))
        $err[] = 'kein Bezeichner angegeben';
      elseif (0<(int)$db->fetch_atom("select count(*) from `perm`
        where IDENT='". mysql_escape_string($s). "'"
        . ($id ? ' and ID_PERM<>'. $id : '')))
        $err[] = 'Dieser Bezeichner ist bereits vergeben';
      else
      {
        $id = $db->update('perm', $_POST);
        forward('index.php?page=perm_edit&ID_PERM='. $id);
      }
      break;
    case 'rt':
/**/
      if (!$id) break;
      $ar_fkr = $nar_set = array ();
      if ($tmp = $_POST['chk'])
        foreach($tmp as $id_role=>$val) if ($val = array_sum($val))
          $nar_set[$val][] = $id_role;
      $ar_role = $db->fetch_nar("select FK_ROLE, FK_ROLE from perm2role where FK_PERM=". $id);
      $db->query("delete from perm2role where FK_PERM=". $id);
      foreach($nar_set as $val=>$ar_id_role)
      {
        $db->query("insert into perm2role (FK_ROLE, FK_PERM, BF_ALLOW)
          select ID_ROLE, ". $id. ", ". $val. "
          from role where ID_ROLE in (". implode(', ', $ar_id_role). ")");
        $ar_role = array_unique(array_merge($ar_id_role, $ar_role));
      }
#die(ht(dump($db->q_queries)));
      $db->submit();
      if (count($ar_role))
      {
        $ar_user = $db->fetch_nar("select FK_USER, FK_USER from role2user
          where FK_ROLE in (". implode(', ', $ar_role). ")");
        foreach($ar_user as $dummy=>$id_user)
          $db->perm_inherit($id, $id_user);
      }
      $db->perm_push();
      forward('index.php?nav='. $id_nav. '&ID_PERM='. $id);
      break;
/*/
      if (!$id) break;
      $s_fkr = (is_array ($tmp = $_POST['chk']) ? implode(', ', $tmp) : '');
      $db->query("delete from perm2role where FK_PERM=$id". ($s_fkr ? " and FK_ROLE not in ($s_fkr)" : ''));
      if ($s_fkr)
        $db->query("insert into perm2role (FK_ROLE, FK_PERM) select ID_ROLE, $id
          from role where ID_ROLE in ($s_fkr)");
#die(ht(dump($db->q_queries)));
      $db->submit();
      forward('index.php?nav='. $id_nav. '&ID_PERM='. $id);
      break;
/**/
  }
}

//$ar_perms = $db->fetch_table('select * from perm where ID_PERM='. $id);

if ($id) {
 	$role=$db->fetch1('select * from perm where ID_PERM='. $id);
 	$tpl_content->addlist('roles', $db->fetch_table("select o.*, z.FK_PERM, BF_ALLOW from role o left join perm2role z on FK_ROLE=ID_ROLE and FK_PERM=". $id), 'tpl/de/perm_edit.row.htm');
 }
 else {
 	$role=$db->fetch_blank('perm');
 	$tpl_content->addlist('roles', $db->fetch_table("select * from perm"), 'tpl/de/perm_edit.showrow.htm');
 }

//$role = ($id ? $db->fetch1('select * from perm where ID_PERM='. $id)  : $db->fetch_blank('perm') );


if (count($err) && 'sv'==$do)
  $role = array_merge($role, $_POST);

$tpl_content->addvar('err', implode('<br />', $err));
$tpl_content->addvars($role);
//$tpl_content->addvar('lu_typ_dbtable', (int)$db->fetch_atom("select ID_LOOKUP from lookup where art='PERM' and VALUE='dbtable'"));
#echo ht(dump($x));
?>