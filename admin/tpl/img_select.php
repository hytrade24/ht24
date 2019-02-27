<?php
/* ###VERSIONSBLOCKINLCUDE### */


#echo $_SERVER['REQUEST_URI'];
// select
$s_chdir = '../';
  $tpl_content->addlist('liste',
    $db->fetch_table("select *,
      ". ($uid ? "if($uid=FK_USER, 1, 0)" : '0'). " letedit, '../' path
    from img where FK_USER". ($uid ? " in(0, $uid)" : '=0'). " and OK=3 order by ID_IMG"),
    'tpl/de/img_select.row.htm'
  );
#echo listtab($t);
  $tpl_content->addvar('sess', session_name().'='.session_id());
#echo $uid;
?>