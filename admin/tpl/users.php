<?php
/* ###VERSIONSBLOCKINLCUDE### */


if(isset($_REQUEST['FK_USERGROUP']))
{
	//echo ht(dump($_POST));
}
else
{
	$tpl_content->addvar("FK_USERGROUP", false);
}

$sort = $_REQUEST['SORTORDER'];
$tpl_content->addvar("SORTORDER_".$sort, 1);

$isSearch = false;

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
		else {
			### ANZEIGEN DEAKTIVIEREN
			$ar_article_tables = $db->fetch_nar("SELECT T_NAME, 'FK_USER' as USERFIELD FROM `table_def`");
			foreach ($ar_article_tables as $table => $field) {
				$db->querynow("UPDATE `".mysql_escape_string($table)."` SET `STATUS`=2\n".
					"WHERE `".mysql_escape_string($field)."`=".(int)$id);
			}
			$db->querynow("UPDATE `ad_master` SET `STATUS`=2\n".
					"WHERE `".mysql_escape_string($field)."`=".(int)$id);
			### ---------------------
			if($ar_check['need_new']) {
			  	$tpl_content->addvar("ID_USER", $id);
			  	$tpl_content->addvar('need_new', 1);
			  	$tpl_content->addvar('msg', $ar_check['msg']);
			} elseif(isset($ar_check['deleted'])) {
				eventlog("warning", 'User gelöscht "'.$user_old.'"');
				$tpl_content->addvar('deleted', 1);
			}
		}
		$userDeleteParams = new Api_Entities_EventParamContainer(array(
			"ID_USER" => $id, "NAME" => $user_old
		));
		Api_TraderApiHandler::getInstance()->triggerEvent( Api_TraderApiEvents::USER_DELETE, $userDeleteParams );
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
if (!empty($_REQUEST['STAT_'])) {
    switch ($_REQUEST['STAT_']) {
        case 0:
            $_REQUEST['SHOW_STAT'] = 'locked';
            break;
        case 1:
            $_REQUEST['SHOW_STAT'] = 'active';
            break;
        case 2:
            $_REQUEST['SHOW_STAT'] = 'unconfirmed';
            break;
        default:
        case 3:
            $_REQUEST['SHOW_STAT'] = 'all';
            break;
    }
		$isSearch = true;
}
if (!empty($_REQUEST['SHOW_STAT'])) {
    switch ($_REQUEST['SHOW_STAT']) {
        case 'active':
            $_REQUEST['STAT_'] = 1;
            $where[] = "STAT = 1";
            break;
        case 'locked':
            $_REQUEST['STAT_'] = 2;
            $where[] = "STAT = 2";
            break;
        case 'unconfirmed':
            $_REQUEST['STAT_'] = 2;
            $where[] = "CODE IS NULL";
            $where[] = "STAT = 2";
            break;
        case 'email':
            $_REQUEST['STAT_'] = 2;
            $where[] = "CODE IS NOT NULL";
            $where[] = "STAT = 2";
            break;
        default:
        case 'all':
            $_REQUEST['STAT_'] = 3;
            break;
    }
    $tpl_content->addvar("SHOW_STAT_".$_REQUEST['SHOW_STAT'], 1);
		$isSearch = true;
}

if(!empty($_POST['USER']))
{
	if((int)$_POST['USER'] > 0)
	{
		$where[] = "ID_USER=".$_POST['USER'];
	}
	else
	{
		if(strstr($_REQUEST['USER'], "@"))
		{
			$_REQUEST['EMAIL_'] = $_POST['USER'];
		}
		else
		{
			$_REQUEST['NAME_'] = $_POST['USER'];
		}

		//$_REQUEST['NNAME_'] = $_POST['USER'];
	}
	$isSearch = true;
}

if ($_REQUEST['ID_USER_']) {
	$where[]=" u.ID_USER='".mysql_escape_string($_REQUEST['ID_USER_'])."'";
	$isSearch = true;
}
if ($_REQUEST['NAME_']) {
	$where[]=" u.NAME like '%".mysql_escape_string($_REQUEST['NAME_'])."%'";
	$isSearch = true;
}
if ($_REQUEST['EMAIL_']) {
	$where[]=" u.EMAIL like '%".mysql_escape_string($_REQUEST['EMAIL_'])."%'";
	$isSearch = true;
}
if ($_REQUEST['NNAME_']) {
	$where[]=" ( u.NACHNAME like '%".mysql_escape_string($_REQUEST['NNAME_'])."%' or u.VORNAME like '%".$_REQUEST['NNAME_']."%' ) ";
	$isSearch = true;
}
if ($_REQUEST['NOTIZEN_ADMIN_']) {
	// Suche innerhalb der Notizen / Tags
	$ar_hack = explode(" ", $_REQUEST['NOTIZEN_ADMIN_']);
	foreach ($ar_hack as $key => $value) {
		$ar_hack[$key] = str_replace(array('Ä','Ö','Ü','ä','ü','ö','ß','-'), array('Ae','Ue','Oe','ae','ue','oe','ss','_'), $value);
		$ar_hack[$key] = preg_replace("/[^\sa-z0-9_]/si", "", $value);
		if (strlen($value) > 3) {
		    $ar_hack[$key] = '*'.$value.'*';
		}
	}
	$search_text = implode($ar_hack);
	$where[]=" (MATCH (u.NOTIZEN_ADMIN) AGAINST ('".mysql_escape_string($search_text)."' IN BOOLEAN MODE))";
	$isSearch = true;
}
if($_REQUEST['FK_USERGROUP'])
{
	$where[] = "u.FK_USERGROUP = ".$_REQUEST['FK_USERGROUP'];
	$isSearch = true;
}

if($_REQUEST['openbill'])
{
	$having = "having `OPEN` > 0";
	$isSearch = true;
}

$where[] = "IS_VIRTUAL = 0";

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

$all2 = $db->fetch_atom("select count(*) from user WHERE IS_VIRTUAL = 0");  //Alle user im System

#$all = $db->fetch_atom("select count(distinct ID_USER) from user ".$join.$where); // Anzahl der User im System nach selektion


/*echo 'select distinct(u.ID_USER), u.EMAIL, u.VORNAME, u.NACHNAME, u.NAME,u.STAT,ll.ABBR ,CACHE
 from user u left join  lang ll on ll.ID_LANG=FK_LANG '.$join.'

 '.$where.'

 order by NAME LIMIT '.$limit.','.$perpage;*/
#
# Sortorder
#

if ($sort=='nz')
    $sortuser = ' STAMP_REG DESC';
elseif ($sort=='az')
    $sortuser = ' STAMP_REG ASC';
elseif ($sort=='nz')
    $sortuser = ' STAMP_REG DESC';
elseif ($sort=='lo')
    $sortuser = ' LASTACTIV DESC';
elseif ($sort=='aa')
    $sortuser = ' anzahl_ads DESC';
elseif ($sort=='idd')
    $sortuser = ' ID_USER DESC';
elseif ($sort=='ida')
    $sortuser = ' ID_USER ASC';
else
    $sortuser = ' ID_USER DESC';
            
if (array_key_exists("B2", $_POST)) {
	
	function arrayToCsv(array &$rows, $newline = "\n", $delimiter = ';', $enclosure = '"', $encloseAll = false, $nullValue = "") {
		$newline_preg = preg_quote($delimiter, '/');
		$delimiter_preg = preg_quote($delimiter, '/');
		$enclosure_preg = preg_quote($enclosure, '/');

		$output = array();
		foreach ($rows as $row) {
			$outputRow = array();
			foreach ($row as $field) {
				if ($field === null) {
					$outputRow[] = $nullValue;
				} else {
					if ($encloseAll || preg_match("/(?:".$newline_preg."|".$delimiter_preg."|".$enclosure_preg."|\s)/", $field)) {
						$outputRow[] = $enclosure . str_replace($enclosure, $enclosure . $enclosure, $field) . $enclosure;
					} else {
						$outputRow[] = $field;
					}
				}
			}
			$output[] = implode($delimiter, $outputRow);
		}
		
		return (!empty($output) ? implode($newline, $output).$newline : "");
	}
	
	$dataCsv = $db->fetch_table($q="
		SELECT
			(SELECT ars.V1 FROM `lookup` arl JOIN `string` ars ON ars.S_TABLE='lookup' AND ars.FK=arl.ID_LOOKUP
				AND ars.BF_LANG=if(arl.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(arl.BF_LANG+0.5)/log(2)))
				WHERE arl.ID_LOOKUP=u.LU_ANREDE) AS ANREDE,
			u.VORNAME, u.NACHNAME, u.NAME AS USERNAME, u.FIRMA, u.EMAIL, u.TEL, u.FAX, u.MOBIL,
			u.STRASSE, u.PLZ, u.ORT, 
			(SELECT sc.V1 FROM `country` c JOIN `string` sc ON sc.S_TABLE='country' AND sc.FK=c.ID_COUNTRY
				AND sc.BF_LANG=if(c.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(c.BF_LANG+0.5)/log(2)))
				WHERE c.ID_COUNTRY=u.FK_COUNTRY) AS LAND
		FROM `user` u
		LEFT JOIN `usergroup` g on g.ID_USERGROUP=u.FK_USERGROUP
		".$where."
		GROUP BY u.ID_USER");
	array_unshift($dataCsv, array( "ANREDE", "VORNAME", "NACHNAME", "USERNAME", "FIRMA", "EMAIL", "TEL", "FAX", "MOBIL", "STRASSE", "PLZ", "ORT", "LAND" ));
	
	header('Content-type: text/csv');
	header('Content-Disposition: attachment; filename="users.csv"');
	die( arrayToCsv($dataCsv, "\r\n") );
}


$data = $db->fetch_table($q = '
	select
        SQL_CALC_FOUND_ROWS
    	distinct(u.ID_USER),
    	ven.ID_VENDOR,
    	u.EMAIL,
    	u.VORNAME,
    	u.NACHNAME,
    	u.NAME,
    	u.RATING,
    	u.STAT,
    	u.CODE,
    	u.NOTIZEN_ADMIN as NOTIZEN,
        u.TOP_USER,
        u.PROOFED,
        u.TOP_SELLER,
    	MD5(u.PASS) as SIG,
    	ll.ABBR,
    	CACHE,
    	STAMP_REG,
    	LASTACTIV,
    	sg.V1 as USERGROUP,
    	sp.V1 as MEMBERSHIP_NAME,
    	u.FK_PACKET_RUNTIME,
    	(
    		SELECT
    			count(*)
    		FROM
    			`ad_master`
    		WHERE
    			FK_USER=u.ID_USER AND (STATUS)=1 AND DELETED = 0
    	) AS anzahl_ads,
    	(
    		SELECT
    		    SUM(it.QUANTITY*it.PRICE)
            FROM
                billing_invoice i
            LEFT JOIN billing_invoice_item it ON it.FK_BILLING_INVOICE = i.ID_BILLING_INVOICE
            WHERE
    			i.FK_USER=u.ID_USER
    			AND i.STATUS = 0
    	) AS `OPEN`,
    	(
    		SELECT
                SUM(it.QUANTITY*it.PRICE)
            FROM
                billing_invoice i
            LEFT JOIN billing_invoice_item it ON it.FK_BILLING_INVOICE = i.ID_BILLING_INVOICE
            WHERE
    			i.FK_USER=u.ID_USER
    			AND i.STATUS = 0
    			AND i.STAMP_DUE <= CURDATE()
    	) AS `FAELLIG`
  	from
  		user u
  	left join
  		lang ll on ll.ID_LANG=FK_LANG '.$join.'
  	left join
  		`usergroup` g on g.ID_USERGROUP=u.FK_USERGROUP
 	left join
 		`string_usergroup` sg on sg.FK=g.ID_USERGROUP
 		AND sg.S_TABLE=\'usergroup\'
 		AND sg.BF_LANG=if(sg.BF_LANG & ".$langval.", ".$langval.", 1 << floor(log(g.BF_LANG_USERGROUP+0.5)/log(2)))
 	left join packet_runtime pr ON pr.ID_PACKET_RUNTIME = u.FK_PACKET_RUNTIME
 	left join packet p ON p.ID_PACKET = pr.FK_PACKET
 	left join
		`string_packet` sp on sp.FK=p.ID_PACKET
		AND sp.S_TABLE=\'packet\'
		AND sp.BF_LANG=if(p.BF_LANG_PACKET & ".$langval.", ".$langval.", 1 << floor(log(p.BF_LANG_PACKET+0.5)/log(2)))
	left join
		`vendor` ven ON ven.FK_USER = u.ID_USER
  '.$where.'
  GROUP BY
  	u.ID_USER
  '.$having.'
  order by
  	'.$sortuser.'
  LIMIT '.$limit.','.$perpage);


function ischecked(&$row,$i){
	global $roles_;
	if (!is_array($roles_))
		return;
	if (in_array ($row['ID_ROLE'],$roles_))
		$row['CHK']='1';
}

$all = (int)$db->fetch_atom("SELECT FOUND_ROWS()");
$tpl_content->addvar('anzahluser', $all." von ".$all2);

$roles = $db->fetch_table("SELECT * FROM role where ID_ROLE>1");
$tpl_content->addlist('roles', $roles, 'tpl/de/users.rolerow.htm' ,'ischecked');


if (!empty($roles_))
	$roles_=urlencode(serialize($roles_));


if ($isSearch) {
	$tpl_content->addvar("SEARCH_RESULT", 1);
}

if ($_REQUEST['frompopup']==1) {
	$tpl_content->addvar("pager", htm_browse($all, $_REQUEST['npage'], "index.php?page=".$tpl_content->vars['curpage']."&frame=popup&frompopup=1&NAME_=".urlencode($_REQUEST['NAME_'])."&EMAIL_=".urlencode($_REQUEST['EMAIL_'])."&NNAME_=".urlencode($_REQUEST['NNAME_'])."&roles_=".$roles_."&STAT_=".$_REQUEST['STAT_']."&FK_USERGROUP=".$_REQUEST['FK_USERGROUP']."&SORTORDER=".$_REQUEST['SORTORDER']."&openbill=".$_REQUEST['openbill']."&npage=", $perpage));
	$tpl_content->addlist('liste', $data, 'tpl/de/users.row_popup.htm');
}
else {
	$tpl_content->addvar("pager", htm_browse($all, $_REQUEST['npage'], "index.php?page=".$tpl_content->vars['curpage']."&NAME_=".urlencode($_REQUEST['NAME_'])."&EMAIL_=".urlencode($_REQUEST['EMAIL_'])."&NNAME_=".urlencode($_REQUEST['NNAME_'])."&roles_=".$roles_."&STAT_=".$_REQUEST['STAT_']."&FK_USERGROUP=".$_REQUEST['FK_USERGROUP']."&SORTORDER=".$_REQUEST['SORTORDER']."&openbill=".$_REQUEST['openbill']."&npage=", $perpage));
	$tpl_content->addlist('liste', $data, 'tpl/de/users.row.htm');
}
$tpl_content->addvar("frompopup",$_REQUEST['frompopup']);

?>