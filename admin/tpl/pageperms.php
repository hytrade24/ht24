<?php
/* ###VERSIONSBLOCKINLCUDE### */


/*
  $ar_pages = array_keys($db->fetch_nar("select
    if (ROOT=1, IDENT, concat('admin/', IDENT)), ROOT
  from nav where LFT>1 and IDENT>'' group by 1 order by ROOT, 1"));
*/

if (!isset($_REQUEST['sroot_']))
	{
		$where=" and root = 1";
		$titelheader='&Ouml;ffentlich';
	}	
elseif ($_REQUEST['sroot_']>0)
	{
		$where=" and root = ".$_REQUEST['sroot_'];
		if ($_REQUEST['sroot_']==1)
			$titelheader='&Ouml;ffentlich';
		else
			$titelheader='Administration';
	}
elseif ($_REQUEST['sroot_']==0)
	{
		$where="";
		$titelheader='&Ouml;ffentlich und Administration';
	}

	  $tpl_content->addvar('titelheader', $titelheader);
	  
    /*
	$ar_pages = array_keys($db->fetch_nar("select
    if (ROOT=1, IDENT, concat('admin/', IDENT)), ROOT
  from nav where LFT>1 and IDENT>''  ".$where." group by 1 order by ROOT, 1"));

   $ar_pages = array_keys($db->fetch_nar("select if (ROOT=1, nav.IDENT, concat('admin/', nav.IDENT)), ROOT,nav.IDENT,count(p.FK_USER) as anzahl from nav 
left join pageperm2user p on nav.IDENT=p.IDENT
where LFT>1 and nav.IDENT>'' ".$where." group by 1 order by ROOT, 1 "));
  */
  

  
  $ar_roles = $db->fetch_nar("select ID_ROLE, LABEL from role order by ID_ROLE");

if (count($_POST))
{

	$NAVDATE_tmp = time(); // Cache der Struktur im Admin-Bereich l&ouml;schen
	$db->putinto_tmp('NAVDATE',$NAVDATE_tmp);
	
  require_once 'sys/lib.perm_admin.php';
  pageperm2role_set(-1, $_POST['perm']);
#die('done');
  forward('index.php?page='. $s_page);
}

  $ar_head = array ();
  $nar_deny = array ();
  foreach($ar_roles as $id=>$s_label)
  {
    $nar_deny[$id] = array ();
    $ar_head[] = '
  <th title="'. stdHtmlentities($s_label). '"><img src="tpl/role'. $id. '.png" /></th>';
  }
  $tpl_content->addvar('roles', $ar_head);

  $lastresult = $db->querynow("select FK_ROLE, IDENT from pageperm2role");
  while (list($fk_role, $s_ident) = mysql_fetch_row($lastresult['rsrc']))
    $nar_deny[$fk_role][] = $s_ident;
/*
  $db->querynow("select p.IDENT, p.B_OVR, u.NAME from pageperm2user p
    left join `user` u on u.ID_USER=p.FK_USER
    order by 3");
  $nar_extra = array ();
  while (list($s_tmp, $b_val, $s_user) = mysql_fetch_row($lastresult['rsrc']))
    $nar_extra[$s_tmp][$b_val][] = $s_user;
*/
#die(ht(dump($nar_deny)));
  $s_pageref = $tpl_content->tpl_pageref('pageperm2user'). '&frame=popup&ident=';
  $ar_liste = array ();
  $n_perm_pageperm = $db->perm_check('admin_pageperm');
  $i = 0;
  
  $lastresult = $db->querynow("select if (ROOT=1, nav.IDENT, concat('admin/', nav.IDENT)) as s_page, ROOT,nav.IDENT,count(p.FK_USER) as anzahl from nav 
left join pageperm2user p on nav.IDENT=p.IDENT
where LFT>1 and nav.IDENT>'' ".$where." group by 1 order by ROOT, 1 ");
  while ($row = mysql_fetch_array($lastresult['rsrc'])) {
  
  //foreach($ar_pages as $s_page)

    $s_rowref = $s_pageref. rawurlencode($row['s_page']);
    $ar_liste[] = '<tr'. ($i&1 ? ' class="zeile1"' : ''). '>
  <td align="left">'.$row['s_page'].'&nbsp;</td>';
#echo $n_perm_pageperm;
    if ($n_perm_pageperm & PERM_EDIT)
      foreach($ar_roles as $id=>$s_label) $ar_liste[] = '
    <input type="hidden" name="perm['. $id. ']['. $row['s_page']. ']" value="0" />
    <td align="center"><input class="nob" type="checkbox" name="perm['. $id. ']['. $row['s_page']. ']" '
          . (in_array ($row['s_page'], $nar_deny[$id]) ? '' : 'checked '). 'value="1" /></td>';
    elseif ($n_perm_pageperm & PERM_READ)
      foreach($ar_roles as $id=>$s_label) $ar_liste[] = '
    <td align="center">'. (in_array ($row['s_page'], $nar_deny[$id]) ? '-' : '&radic;'). '</td>';
    else
      $ar_liste[] = str_repeat('<td align="center">?</td>', count($ar_roles));
    if ($tmp = $nar_extra[$row['s_page']] && ($n_perm_pageperm & PERM_READ))
    {
      $ar_liste[] = '
  <td>'. (($x = $tmp[1]) ? '+ '. implode(', ', $x) : '&nbsp;'). '</td>';
      $ar_liste[] = '
  <td>'. (($x = $tmp[0]) ? '- '. implode(', ', $x) : '&nbsp;'). '</td>';
    }
    else
      $ar_liste[] = '';
  
  $_tmp_delme='';
  if ($row['anzahl'] > 0)
	 $_tmp_delme='<td><img src="gfx/achtung.gif" width="16" height="16" alt="" border="0"></td><td><b>'.$row['anzahl'].'</b></td>';
	else
 $_tmp_delme='<td></td><td></td>';
    if ($n_perm_pageperm & PERM_EDIT)
      $ar_liste[] = '
  <td><a href="'. $s_rowref. '" target="eakpop"
    onClick="popup(640,480).location.href=\''. $s_rowref. '\';return false;"><img
    src="gfx/btn.edit.gif" width="32" height="16" border="0" alt="Ausnahmen bearbeiten" /></a> </td>'.$_tmp_delme.'
</tr>';
    else
      $ar_liste[] = '
    <td>&nbsp;</td><td>&nbsp;</td>  <td>&nbsp;1</td></tr>';
    $i++;
  }
  $tpl_content->addvar('liste', $ar_liste);
  $tpl_content->addvar('rolecount', count($ar_roles));
#  $dp = diropen('tpl
?>