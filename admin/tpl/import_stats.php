<?php

$perpage = 20; // Elemente pro Seite
$offset = ((($_REQUEST['npage'] ? $_REQUEST['npage'] : $_REQUEST['npage']=1)-1)*$perpage);

$sql = 'SELECT SQL_CALC_FOUND_ROWS a.*, b.*, CONCAT(u.VORNAME," ",u.NACHNAME) as FULL_NAME, 
			u.NAME, v.NAME as VENDOR_FIRMA,
			TIME_TO_SEC(TIMEDIFF(b.STAMP_UPDATE,b.STAMP_CREATE))/60 as DURATION
			FROM import_source a
			LEFT JOIN import_process b
			ON a.ID_IMPORT_SOURCE = b.FK_IMPORT_SOURCE
			LEFT JOIN user u
			ON u.ID_USER = a.FK_USER
			LEFT JOIN vendor v
			ON v.FK_USER = u.ID_USER
			ORDER BY b.STAMP_CREATE DESC
			LIMIT ' . $perpage. '
			OFFSET ' . $offset;

$data = $db->fetch_table( $sql );

$total_rows = $db->fetch_atom("SELECT FOUND_ROWS()");

$tpl_content->addlist('liste', $data, 'tpl/'.$s_lang.'/import_stats.row.htm');

$all_templates_sql = 'SELECT a.*, CONCAT(b.VORNAME," ",b.NACHNAME) as FULL_NAME,
						b.NAME
						FROM import_preset a
						INNER JOIN user b
						ON a.FK_USER = b.ID_USER';

$import_templates = $db->fetch_table( $all_templates_sql );

$tpl_content->addlist('import_templates_list', $import_templates, 'tpl/'.$s_lang.'/import_stats.template.htm' );

$tpl_content->addvar("pager", htm_browse($total_rows, $_REQUEST['npage'], "index.php?page=".$tpl_content->vars['curpage']."&npage=", $perpage));