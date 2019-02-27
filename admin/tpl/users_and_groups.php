<?php
/* ###VERSIONSBLOCKINLCUDE### */


$SILENCE=false;
$id = $_REQUEST['ID_USER'];
if ('rm'==$_REQUEST['do'])
{
  if ($id>1 && $id != $uid)
  {
    $user_old = $db->fetch_atom("select `NAME` from `user`where ID_USER=".$id);
	$_REQUEST['ID_NEW'] = $_REQUEST['FK_AUTOR'];
	unset($_REQUEST['FK_AUTOR'],$_REQUEST['NAME_']);
	$ar_check = delete_user($id,$_REQUEST);
	if(!$ar_check['deleteable'])
	  $tpl_content->addvar('err', $ar_check['err']);
	else
	{
	  if($ar_check['need_new'])
	  {
	    $tpl_content->addvar("ID_USER", $id);
		$tpl_content->addvar('need_new', 1);
		$tpl_content->addvar('msg', $ar_check['msg']);
	  }
	  elseif(isset($ar_check['deleted']))
	  {
	    eventlog("warning", 'User gelöscht "'.$user_old.'"');
		$tpl_content->addvar('deleted', 1);
	  }
	}
	#die(ht(dump($ar_check))); 	
	#$db->query('delete from `user` where ID_USER='. $id);
    #$db->query('delete from role2user where FK_USER='. $id);
    #$db->query('delete from perm2user where FK_USER='. $id);
    #$db->query('delete from pageperm2user where FK_USER='. $id);
    #die(ht(dump($db->q_queries)));
    #$db->submit();
    #forward('index.php?nav='. $id_nav, 2);
  }
  else
    $tpl_content->addvar('err', 'Dieser User kann nicht gel&ouml;scht werden.');
}


//if (empty($_POST['STAT_']) and empty($_GET['STAT_']))  $_REQUEST['STAT_']=0; else $_REQUEST['STAT_']=1;
if ($_REQUEST['STAT_']==1) 
  $where[]=" STAT = 1"; 
elseif ($_REQUEST['STAT_']=='0') 
  $where[]=" STAT = 0"; 
elseif ($_REQUEST['STAT_']=='2') 
  $where[]=" STAT = 2";   
else 
  $_REQUEST['STAT_']='3';

if ($_REQUEST['NAME_']) $where[]=" NAME like '%".$_REQUEST['NAME_']."%'";
if ($_REQUEST['EMAIL_']) $where[]=" EMAIL like '%".$_REQUEST['EMAIL_']."%'";
if ($_REQUEST['NNAME_']) $where[]=" ( NACHNAME like '%".$_REQUEST['NNAME_']."%' or VORNAME like '%".$_REQUEST['NNAME_']."%' ) ";
if (is_array ($where)) $where=' where '.implode(' and ',$where);



if (is_array ($roles_=$_POST['roles_'])) 
		$join=' join role2user  z on z.FK_USER=ID_USER and  z.FK_ROLE in ('.implode(' , ',$roles_).') ';
elseif (!empty($_GET['roles_'])) {
	if (!is_numeric($_GET['roles_'])) {
		$roles_=unserialize ($_GET['roles_']);
		$join=' join role2user  z on z.FK_USER=ID_USER and  z.FK_ROLE in ('.implode(' , ',$roles_).') ';
	}
	else{
		$join=' join role2user  z on z.FK_USER=ID_USER and  z.FK_ROLE ='.$_GET['roles_'];
		$roles_[]=$_GET['roles_'];
	}
}


if ($_REQUEST) 
   $tpl_content->addvars($_REQUEST);

$perpage = 25; // Elemente pro Seite
$limit = ((($_REQUEST['npage'] ? $_REQUEST['npage'] : $_REQUEST['npage']=1)-1)*$perpage);

$all2 = $db->fetch_atom("select count(*) from user ");  //Alle user im System

$all = $db->fetch_atom("select count(distinct ID_USER) from user ".$join.$where); // Anzahl der User im System nach selektion
$tpl_content->addvar('anzahluser', $all." von ".$all2);

  function getroles(&$row, $i) // callback fuer addlist
  {
    $row['roles'] = implode(', ', $GLOBALS['db']->fetch_nar(
      "select ID_ROLE, LABEL from role, role2user
        where ID_ROLE=FK_ROLE and FK_USER=". $row['ID_USER']. "
        order by ID_ROLE"
    ));
	
	 $path='../'.$GLOBALS['nar_systemsettings']['SITE']['USER_PATH'].$row['CACHE']."/".$row['ID_USER']."/stats_user_counters.php";
 
	 if (file_exists($path))
	 { 
	 	include ($path);
		$row = array_merge($row, $userc);
	 }
	 
  }

/*echo 'select distinct(u.ID_USER), u.EMAIL, u.VORNAME, u.NACHNAME, u.NAME,u.STAT,ll.ABBR ,CACHE
  from user u left join  lang ll on ll.ID_LANG=FK_LANG '.$join.'
 
  '.$where.'    
 
  order by NAME LIMIT '.$limit.','.$perpage;*/


  $data = $db->fetch_table('select
    	distinct(u.ID_USER), u.EMAIL, u.VORNAME, u.NACHNAME, u.NAME, u.RATING, u.STAT, ll.ABBR, 
    	CACHE, STAMP_REG, LASTACTIV, sg.V1 as USERGROUP,
    	(SELECT count(*) FROM `ad_master` WHERE FK_USER=u.ID_USER AND STATUS&3=\'1\') as anzahl_ads
  	from user u
  		left join lang ll on ll.ID_LANG=FK_LANG '.$join.'
  		left join `usergroup` g on
        g.ID_USERGROUP=u.FK_USERGROUP
 			left join `string_usergroup` sg on
        sg.FK=g.ID_USERGROUP AND sg.S_TABLE=\'usergroup\' AND
        sg.BF_LANG=if(sg.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(g.BF_LANG_USERGROUP+0.5)/log(2)))
  '.$where.'    
 
  order by STAMP_REG LIMIT '.$limit.','.$perpage);

function ischecked(&$row,$i){
global $roles_;
	if (!is_array($roles_))
		return;
	if (in_array ($row['ID_ROLE'],$roles_)) 
		$row['CHK']='1';
}


  $roles = $db->fetch_table("SELECT * FROM role where ID_ROLE>1");
  $tpl_content->addlist('roles', $roles, 'tpl/de/users.rolerow.htm' ,'ischecked');
  
  if (!empty($roles_))
	$roles_=urlencode(serialize($roles_));



if ($_REQUEST['frompopup']==1) {
	$tpl_content->addvar("pager", htm_browse($all, $_REQUEST['npage'], "index.php?page=".$tpl_content->vars['curpage']."&frame=popup&frompopup=1&NAME_=".urlencode($_REQUEST['NAME_'])."&EMAIL_=".urlencode($_REQUEST['EMAIL_'])."&NNAME_=".urlencode($_REQUEST['NNAME_'])."&roles_=".$roles_."&STAT_=".$_REQUEST['STAT_']."&npage=", $perpage));
	$tpl_content->addlist('liste', $data, 'tpl/de/users.row_popup.htm', 'getroles');
}
else {
	$tpl_content->addvar("pager", htm_browse($all, $_REQUEST['npage'], "index.php?page=".$tpl_content->vars['curpage']."&NAME_=".urlencode($_REQUEST['NAME_'])."&EMAIL_=".urlencode($_REQUEST['EMAIL_'])."&NNAME_=".urlencode($_REQUEST['NNAME_'])."&roles_=".$roles_."&STAT_=".$_REQUEST['STAT_']."&npage=", $perpage));
	$tpl_content->addlist('liste', $data, 'tpl/de/users.row.htm', 'getroles');
}
$tpl_content->addvar("frompopup",$_REQUEST['frompopup']);

?>