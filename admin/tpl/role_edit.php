<?php
/* ###VERSIONSBLOCKINLCUDE### */


$id = (int)$_REQUEST['ID_ROLE'];
$err = array ();
if (count($_POST))
{

	$NAVDATE_tmp = time(); // Cache der Struktur im Admin-Bereich l&ouml;schen
	$db->putinto_tmp('NAVDATE',$NAVDATE_tmp);
	
  switch ($do = $_REQUEST['do'])
  {
    case 'sv':
      if (!($s = $_POST['LABEL'] = trim($_POST['LABEL'])))
        $err[] = 'kein Label angegeben';
      elseif (0<(int)$db->fetch_atom("select count(*) from role
        where LABEL='". mysql_escape_string($s). "'"
        . ($id ? ' and ID_ROLE<>'. $id : '')))
        $err[] = 'Dies Label ist bereits vergeben';
      else
      {
        $oldid = (int)$_POST['ID_ROLE'];
        $id = $db->update('role', $_POST);
        // senkrecht-png erstellen
        if ($im = @imagecreate($w = min(75,strlen($s)*imagefontwidth(3)), $h=imagefontheight (3)))
        {
          //$n_trans = imagecolortransparent ($im, imagecolorallocate($im, 204,204,204));
          $n_text = imagecolorallocate($im, 0,0,102);
          //imagefilledrectangle ($im, 0, 0, $w-1, $h-1, $n_trans);
	        imagefill($im, 0, 0,imagecolorallocate($im, 204,204,204));
          imagestring ($im, 3, 0, 0, $s, $n_text);
          if (function_exists('imagerotate'))
            $i2 = imagerotate($im, 90, 0);//$n_trans);
          imagedestroy ($im);
          if (file_exists('tpl/role'. $id. '.png')) {
              unlink('tpl/role'. $id. '.png');
          }
          imagepng($i2, 'tpl/role'. $id. '.png');
          imagedestroy ($i2);
        }
        // Cache neu schreiben
        if (!$oldid)
        {
          require_once 'sys/lib.perm_admin.php';
          if ($_POST['FK_ROLE_PARENT'] > 0) {
              // Set permissions from parent role
              $arParentPerm = pageperm_read_role($_POST['FK_ROLE_PARENT']);
              $result = pageperm2role_set($id, $arParentPerm);
	          perm2role_set($_POST['FK_ROLE_PARENT'],$id);
          } else {
              // Update permissions
              pageperm2role_rewrite($id);
          }
        }
        forward('index.php?nav='. $id_nav. '&ID_ROLE='. $id);
      }
      break;
    case 'rt':
      if (!$id) break;
      $ar_fkr = $nar_set = array ();
      if ($tmp = $_POST['chk'])
        foreach($tmp as $id_perm=>$val) if ($val = array_sum($val))
          $nar_set[$val][] = $id_perm;
      $ar_perm = $db->fetch_nar("select FK_PERM, FK_PERM from perm2role where FK_ROLE=". $id);
      $db->query("delete from perm2role where FK_ROLE=". $id);
      foreach($nar_set as $val=>$ar_id_perm)
      {
        $db->query("insert into perm2role (FK_ROLE, FK_PERM, BF_ALLOW)
          select ". $id. ", ID_PERM, ". $val. "
          from perm where ID_PERM in (". implode(', ', $ar_id_perm). ")");
        $ar_perm = array_unique(array_merge($ar_id_perm, $ar_perm));
      }
      $ar_user = $db->fetch_nar("select FK_USER,FK_USER from role2user where FK_ROLE=". $id);
      $db->submit();
#echo dump($ar_perm),'<br>',dump($ar_user);
      foreach($ar_perm as $dummy=>$id_perm)
        foreach($ar_user as $dummy=>$id_user)
          $db->perm_inherit($id_perm, $id_user);
      $db->perm_push();
#die(dump($_SESSION['perm_update']));
#die(ht(dump($db->q_queries)));
      forward('index.php?nav='. $id_nav. '&ID_ROLE='. $id);
      break;
  }
}

  function seri(&$row, $i){
  	$r[]=$row['ID_ROLE'];
  	//$row['roles_']=urlencode(serialize( $row['ID_ROLE']));
	$row['roles_']=$row['ID_ROLE'];
}

if ($id) {
  $role = $db->fetch1('SELECT * FROM role where ID_ROLE='. $id);
#  $role = $db->fetch1($db->lang_select('role'). ' where ID_ROLE='. $id);
#  $tpl_content->addlist('perms', $db->fetch_table("select r.*, z.FK_PERM, z.BF_ALLOW from `perm` r left join perm2role z on FK_PERM=ID_PERM and FK_ROLE=". $id), 'tpl/de/role_edit.row.htm');
$tpl_content->addlist('perms', $db->fetch_table("select r.*, z.FK_PERM, z.BF_ALLOW,u.VALUE from `perm` r left join perm2role z on FK_PERM=ID_PERM and FK_ROLE=".$id." left join lookup  u on r.LU_TYP=ID_LOOKUP"), 'tpl/de/role_edit.row.htm');

  }
  else {
    $allusers = $db->fetch_atom("select count(distinct ID_USER) from user "); // Anzahl der User im System nach selektion
    $tpl_content->addvar('anzahluser', $allusers);
    $ar_roles = $db->fetch_table('select r.*,count(FK_USER) as users from role r
left join role2user on FK_ROLE=r.ID_ROLE group by ID_ROLE
order by LABEL');
  # Default-Rolle aus DB holen, damit im Template per JavaScript gewarnt werden kann
    $ID_MODULOPTION = $db->fetch_atom("select ID_MODULOPTION from `moduloption` where OPTION_VALUE='DEFAULT_ROLE'");
    $DEFAULT_ROLE = $db->fetch_atom("select V1 from `string_opt` where S_TABLE='moduloption' AND FK=".$ID_MODULOPTION); # Default Rolle ermittelt
    $tpl_content->addvar("DEFAULT_ROLE",$DEFAULT_ROLE);
    $tpl_content->addvar("FK_ROLE_PARENT",$DEFAULT_ROLE);
	$tpl_content->addlist('roles', $ar_roles, 'tpl/de/role_edit.row_roles.htm','seri');
    $tpl_content->addlist('roles_options', $ar_roles, 'tpl/de/role_edit.row_roles_options.htm');
  }
  

  
if (!$role)
{
  $role = $db->fetch_blank('role');
#  if ($id) $role['ID_ROLE'] = $id;
}

if (count($err) && 'sv'==$do)
  $role = array_merge($role, $_POST);

$tpl_content->addvar('err', implode('<br />', $err));
$tpl_content->addvars($role);
#$tpl_content->addlist('perms', $db->fetch_table("select r.*, z.FK_PERM, z.BF_ALLOW from `perm` r  left join perm2role z on FK_PERM=ID_PERM and FK_ROLE=". $id), 'tpl/de/role_edit.row.htm');
?>