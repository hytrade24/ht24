<?php
/* ###VERSIONSBLOCKINLCUDE### */


/*
insert into perm (IDENT, LU_TYP, PKVALUE, DESCR)
select ifnull(t.ALIAS, t.IDENT), 9, t.ID_NAV, concat('Navigationspunkt ', s.V1)
from nav t
left join string s on s.S_TABLE='nav'  and s.FK=t.ID_NAV
              and s.BF_LANG=128
*/

require_once 'sys/lib.rolen_handling.php';

if ('rm' == $_REQUEST['do'])
  if (($id=$_REQUEST['ID_ROLE']) && ($id>3))
  {
  	del_role ($id); // Rolle löschen
  }
  elseif ($id=$_REQUEST['ID_PERM'])
  {
#    $db->query("delete from perm4page where FK_PERM=". $id);
    $db->query("delete from perm2role where FK_PERM=". $id);
    $db->query("delete from perm2user where FK_PERM=". $id);
    $db->submit();
    $db->delete('perm', $id);
  }

/*
  $res = $db->querynow("select
      ID_ROLE, o.LABEL OLABEL, o.DESCR ODESCR,
      ID_PERM, r.IDENT RIDENT, r.DESCR RDESCR,
      if (z.FK_PERM is null, 0, 1) CHK
    from role o, perm r
    join perm2role z on ID_ROLE=FK_ROLE and FK_PERM=ID_PERM
    order by ID_PERM, ID_ROLE");
*/


$ar_roles = $db->fetch_table('select * from role order by ID_ROLE');
$ar_perms = $db->fetch_table('select * from perm order by ID_PERM');

$nar_perm2role = $db->fetch_nar("select concat(FK_ROLE,'-',FK_PERM),BF_ALLOW from perm2role");

$ar_table = array ();
#die(dump($db->lang_select('lookup', 'ID_LOOKUP, LABEL')));
$nar_permtype = $db->fetch_nar("select ID_LOOKUP, VALUE from lookup  where art='PERM'");
#$lu_permtyp_table = array_search('dbtable', $nar_permtype); #xxx wird das noch gebraucht?
#die(ht(dump($nar_permtype)));
if (count($ar_roles))
{
  if (count($ar_perms))
  {
    $nar_ausnahmen = array ();
    $res = $db->querynow("select FK_PERM, FK_USER, u.NAME from perm2user
        left join user u on ID_USER=FK_USER
      where BF_GRANT or BF_REVOKE");
    while (list($fk_perm, $fk_user, $uname) = mysql_fetch_row($res['rsrc']))
      $nar_ausnahmen[$fk_perm][] = '<a href="index.php?page=perm2user&ID_USER='. $fk_user. '"
        title="Rechte f&uuml;r \''. stdHtmlentities($uname). '\' bearbeiten">'. stdHtmlentities($uname). '</a>';

    $ar_head = array ('<table class="liste" cellspacing="0"><tr>
  <th valign="bottom">Recht</th>
  <th valign="bottom">Typ</th>');
    foreach($ar_perms as $i=>$perm)
    {
      if (!($s_permtype = $nar_permtype[(int)$perm['LU_TYP']]))
        $s_permtype = '!undef!';
      $ar_table[] = array ('
</tr><tr'. ($i&1 ? ' class="zeile0"' : ' class="zeile1"') . '>
  <td nowrap style="text-align:left;" title="'. stdHtmlentities($perm['DESCR'])
        . '"><a href="'. $tpl_content->tpl_pageref('perm_edit'). '&ID_PERM='. $perm['ID_PERM']
        . '"><img src="gfx/btn.edit.gif" width="32" height="16" alt="Recht bearbeiten" border="0" /></a>'
        . '<a href="index.php?page='. $s_page_alias. '&do=rm&ID_PERM='. $perm['ID_PERM']. '"
          onClick="return confirm(\'Dieses Recht löschen?\');"><img src="gfx/btn.rm.gif" width="32" height="16" alt="Recht l&ouml;schen" border="0" /></a>&nbsp;&nbsp;&nbsp; '
        . stdHtmlentities($perm['IDENT']). '</td>
  <td>'. /**/ $s_permtype /*/ '<img src="gfx/permtype.'. $s_permtype. '.gif" width="16" height="16" />' /**/. '</td>');#xxx icons bauen?!
      foreach($ar_roles as $k=>$role)
      {
        if (!$i)
          $ar_head[] = '
  <th title="'. stdHtmlentities($role['DESCR'])
        . '" valign="bottom"><img src="tpl/role'. $role['ID_ROLE']. '.png"/><br><img src="gfx/dot.gif" width="40" height="10" alt="" border="0"><br>
		<a href="'. $tpl_content->tpl_pageref('role_edit'). '&ID_ROLE='. $role['ID_ROLE']
        . '"><img src="gfx/btn.edit.gif" width="32" height="16" alt="Rolle bearbeiten" border="0" /></a>'
        . (4>$role['ID_ROLE'] ? '' : '&nbsp;&nbsp;<a href="index.php?page='. $s_page_alias. '&do=rm&ID_ROLE='. $role['ID_ROLE']. '"
          onClick="return confirm(\'Diese Rolle löschen?\');"><img
          src="gfx/btn.rm.gif" width="32" height="16" alt="Rolle l&ouml;schen" border="0"/></a> ')
        .  '</th>';
        $val = $nar_perm2role[$role['ID_ROLE']. '-'. $perm['ID_PERM']];
        $ar_table[] = '
  <td align="center">'
#    . ($val & 16 ? 'S' : '-')
    . ($val & 8 ? 'D' : '-')
    . ($val & 4 ? 'E' : '-')
    . ($val & 2 ? 'C' : '-')
    . ($val & 1 ? 'R' : '-')
    . '</td>';
      }
      $ar_table[] = '
  <td>'. (($tmp = $nar_ausnahmen[$perm['ID_PERM']]) ? implode(', ', $tmp) : '--'). '&nbsp;</td>';
    }
    $ar_head[] = '
  <th valign="bottom">Ausnahmen</th>';
    $ar_table[] = '
</tr></table>';
    while ($tmp = array_pop($ar_head))
      array_unshift($ar_table, $tmp);
#    $tpl_content->addvar('colspan', 1+count($ar_role));
  }
  else // nur Rollen
  {
    $ar_table[] = '<table class="listtab"><tr>';
    foreach($ar_roles as $i=>$role) $ar_table[] = '
  <th title="'. stdHtmlentities($role['DESCR'])
        . '"><a href="'. $tpl_content->tpl_pageref('role_edit'). 'ID_ROLE='. $role['ID_ROLE']
        . '"><img src="gfx/btn.edit.gif" width="32" height="16" alt="Rolle bearbeiten" border="0" /></a>'
        . '<a href="index.php?nav='. $id_nav. '&do=rm&ID_ROLE='. $role['ID_ROLE']. '"
          onClick="return confirm(\'Diese Rolle löschen?\');"><img src="gfx/btn.rm.gif" width="16" height="16" alt="Rolle l&ouml;schen" border="0" /></a>&nbsp;'
        . stdHtmlentities($role['LABEL']). '</th>';
    $ar_table[] = '</tr></table>';
  }
}
else // nur Rechte
{
    $ar_table[] = '<table class="listtab">';
    foreach($ar_perms as $i=>$perm) $ar_table[] = '<tr'. ($i&1 ? '' : ' class="evenrow"') . '>
  <th title="'. stdHtmlentities($perm['DESCR'])
      . '"><a href="'. $tpl_content->tpl_pageref('perm_edit'). 'ID_PERM='. $perm['ID_PERM']
      . '"><img src="gfx/btn.edit.gif" width="32" height="16" alt="Recht bearbeiten" border="0" /></a>'
        . '<a href="index.php?nav='. $id_nav. '&do=rm&ID_PERM='. $role['ID_PERM']. '"
          onClick="return confirm(\'Dieses Recht löschen?\');"><img src="gfx/btn.rm.gif" width="16" height="16" alt="Recht l&ouml;schen" border="0" /></a>&nbsp;'
        . stdHtmlentities($perm['IDENT']). '</th>
</tr>';
    $ar_table[] = '</table>';
}
$tpl_content->addvar('table', $ar_table);
?>