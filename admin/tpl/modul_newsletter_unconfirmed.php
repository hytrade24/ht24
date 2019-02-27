<?php
/* ###VERSIONSBLOCKINLCUDE### */


$n_perpage = 25;

$s_where = 'FK_USER is NULL and STAMP is not null';

if ($_POST['chk'])
{
  $s_dowhere = $s_where .' and ID_NL_RECP in ('. implode(', ', $_POST['chk']). ')';
  switch($_REQUEST['do'])
  {
    case 'rm':
      $db->querynow("delete from nl_recp where ". $s_dowhere);
      break;
    case 'ok':
      $db->querynow("update nl_recp set STAMP=NULL where ". $s_dowhere);
      break;
    default:
      // nop
      break;
  }
}


$tpl_content->addvar('NLCONFIRMTIMEOUT', $nar_systemsettings['SITE']['NL_CONFIRM_TIMEOUT']);

if ($n_count = (int)$db->fetch_atom("select count(*) from nl_recp where ". $s_where))
{
  $n_page = max(1, (int)$_REQUEST['npage']);
  $n_ofs = ($n_page - 1) * $n_perpage;
  $ar_data = $db->fetch_table('select *, STAMP<now() as B_EXPIRED, g.ABBR as S_LANG
  from nl_recp
    left join lang g on g.BITVAL=LANGVAL
  where '. $s_where. '
    order by STAMP asc
    limit '. $n_ofs. ', '. $n_perpage);

  $tpl_content->addlist('liste', $ar_data, 'tpl/de/modul_newsletter_unconfirmed.row.htm');
  if ($n_count>$n_perpage)
  {
    /*
    $ar_browse = browse($n_count, $n_ofs, $n_perpage);
    list($n_ofs, $n_perpage) = array_shift($ar_browse);
    $tpl_content->addlist('browse', $ar_browse, 'skin/browse.item.htm');
    */
    $tpl_content->addvar("browse", htm_browse($n_count, $n_page, "index.php?page=".$tpl_content->vars['curpage']."&npage=", $n_perpage));
    $tpl_content->addvar('ofs', $n_ofs);
  }
}
?>