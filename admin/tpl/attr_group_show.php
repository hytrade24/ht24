<?php
/* ###VERSIONSBLOCKINLCUDE### */


$id = (int)$_REQUEST['id'];
$item = $db->fetch1('select * from attr_group where ID_ATTR_GROUP='. $id);

$ar_attr = $db->fetch_table($db->lang_select('attr', '*, FK_ATTR_GROUP'). '
  left join attr2group z on t.ID_ATTR=z.FK_ATTR
  left join attr_group g on g.ID_ATTR_GROUP=z.FK_ATTR_GROUP
  where '. (int)$item['LFT']. ' between g.LFT and g.RGT
  group by ID_ATTR order by ID_ATTR');

if ($item)
{
  $tpl_content->addvars($item);
  $tpl_content->addlist('liste', $ar_attr, 'tpl/de/attr_group_show.row.htm');
}
else
  $tpl_content->addvar('ID_ATTR_GROUP', $id);
?>