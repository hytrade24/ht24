<?php
/* ###VERSIONSBLOCKINLCUDE### */


#unset ($_SESSION['msg']);
  $id = $_REQUEST['ID_USER'];

#echo ht(dump($_POST));
  if (count($_POST))
  {
/*
#chk[{ID_PERM}][{val}]
    $db->perm_user($id);
    $sum_g = $sum_r = $last_id = 0;
    if ($_POST['chk']) foreach($_POST['chk'] as $id_perm=>$nar)
      foreach($nar as $val=>$where) if ($val && '0'!=$where)
    {
      if ($last_id != $id_perm)
      {
        if ($last_id)
          $db->perm_user($id, $last_id, $sum_g, $sum_r);
        $sum_g = $sum_r = 0;
        $last_id = $id_perm;
      }
      switch($where) {
        case 'G': case '+': $sum_g += (int)$val; break;
        case 'R': case '-': $sum_r += (int)$val; break;
      }
    }
    if ($last_id)
      $db->perm_user($id, $last_id, $sum_g, $sum_r);
*/
    if ($id)
    {
      $s_roles = ($_POST['roles'] ? implode(', ', $_POST['roles']) : false);
      $db->query("delete from role2user where FK_USER=$id");
      if ($s_roles)
        $db->query('insert into role2user (FK_USER, FK_ROLE)
          select '. $id. ', ID_ROLE from role where ID_ROLE in ('. $s_roles. ')');
      $db->query('update user set SER_PAGEPERM=null, SER_KATPERM=null where ID_USER='. $id);
#$ar_query_log = array ();
      $db->submit(true);
#echo listtab($ar_query_log);
    }
  }
  if ($id && ($id_perm = $_REQUEST['ID_PERM']) && ($n_val = $_REQUEST['val']))
  {
    switch ($do = $_REQUEST['do'])
    {
      case '+':
        $s_set = 'BF_GRANT=BF_GRANT|'. $val.', BF_REVOKE=BF_REVOKE&~'. $val. ', BF_CHECK=BF_CHECK|'. $val;
        break;
      case '0':
        $s_set = 'BF_GRANT=BF_GRANT&~'. $val.', BF_REVOKE=BF_REVOKE&~'. $val. ', BF_CHECK=(BF_CHECK&~'. $val. ')|(BF_INHERIT&'. $val. ')';
        break;
      case '-':
        $s_set = 'BF_GRANT=BF_GRANT&~'. $val.', BF_REVOKE=BF_REVOKE|'. $val. ', BF_CHECK=BF_CHECK&~'. $val;
        break;
    }
    $db->querynow("update perm2user set ". $s_set. " where FK_USER=". $id. " and FK_PERM=". $id_perm);
	#echo "drin ---";
#echo ht(dump($lastresult));
  }
#    update perm2user set
#      BF_GRANT=BF_GRANT

  $tpl_content->addvars($db->fetch1("select * from user where ID_USER=". $id));

  $ar_data = $db->fetch_table("select p.ID_PERM, p.IDENT,
      ifnull(v.BF_GRANT, 0) BF_GRANT, ifnull(v.BF_REVOKE, 0) BF_REVOKE,
      ifnull(v.BF_INHERIT,0) BF_INHERIT, ifnull(v.BF_CHECK,0) BF_CHECK,
      ifnull(bit_or(s.BF_ALLOW), 0) BF_INHERIT_NOW
    from perm p
      left join role2user on role2user.FK_USER=". $id. "
      left join perm2role s on s.FK_ROLE=role2user.FK_ROLE
      left join perm2user v on v.FK_USER=". $id. " and v.FK_PERM=ID_PERM
    group by ID_PERM order by ID_PERM");
#echo ht(dump($lastresult)), listtab($ar_data);
//  $tpl_content->addlist('perms', $ar_data, 'tpl/de/perm2user.row.htm');

  // evtl. Vererbung korrigieren
  $n_updates = 0;
  foreach ($ar_data as $i=>$row)
  {
    if ($row['BF_INHERIT'] != $row['BF_INHERIT_NOW'])
    {
	 #echo "drin 1<br>";
      $res = $db->perm_inherit($row['ID_PERM'], $id);
      if ($res['int_result'] && !$res['str_error']) {
	  	$n_updates++;
			 #echo "drin 2<br>";
			 }
#if ($res) echo ht(dump($res));
    }
    if ($row['BF_CHECK'] != ($x = ($row['BF_INHERIT_NOW'] | $row['BF_GRANT']) &~ $row['BF_REVOKE'])) {
      $db->perm_inherit($row['ID_PERM'], $id);
	  #echo "drin 3<br>";
	  }
#      $b_upd = true;
  }
  $tpl_content->addvar('b_upd', $b_upd);
  if ($n_updates && !count($_POST))
    $tpl_content->addvar('err', 'Rechte-Vererbung: '. $n_updates. ' Datens&auml;tze korrigiert.');

if ($id)
{
  $roles = $db->fetch_table("select o.ID_ROLE, o.LABEL, if(z.FK_ROLE is null, 0, 1) CHK
    from role o
      left join role2user z on z.FK_ROLE=o.ID_ROLE and z.FK_USER=". $id . "
    where ID_ROLE>1");
#echo ht(dump($lastresult)), listtab($roles);
  $tpl_content->addlist('roles', $roles, 'tpl/de/user_edit.rolerow.htm');
}



$spezielle_rechte = $db->fetch_table("select q.ident,q.FK_USER, ifnull(q.B_OVR, -1) B_OVR , 
count(v.FK_ROLE) as anzahl_darf_nicht ,count(r.FK_ROLE) as anzahl_user_drin ,
if(count(r.FK_ROLE) >0, 1, 0) INHERIT

from pageperm2user as q


left join pageperm2role v on q.IDENT=v.IDENT 
left join role2user r on r.FK_ROLE=v.FK_ROLE and r.FK_USER=q.FK_USER

where q.FK_USER = ". $id . "
group by IDENT");
if (count($spezielle_rechte))
  $tpl_content->addlist('speziall', $spezielle_rechte , 'tpl/de/perm2user.spezialrow.htm');


    $ar_data = $db->fetch_table("select p.ID_PERM, p.IDENT,
      ifnull(v.BF_GRANT, 0) BF_GRANT, ifnull(v.BF_REVOKE, 0) BF_REVOKE,
      ifnull(v.BF_INHERIT,0) BF_INHERIT, ifnull(v.BF_CHECK,0) BF_CHECK,
      ifnull(bit_or(s.BF_ALLOW), 0) BF_INHERIT_NOW
    from perm p
      left join role2user on role2user.FK_USER=". $id. "
      left join perm2role s on s.FK_ROLE=role2user.FK_ROLE
      left join perm2user v on v.FK_USER=". $id. " and v.FK_PERM=ID_PERM
    group by ID_PERM order by ID_PERM");

  $tpl_content->addlist('perms', $ar_data, 'tpl/de/perm2user.row.htm');
#echo dump($_SESSION['perm_update']);

?>