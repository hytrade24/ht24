<?php
/* ###VERSIONSBLOCKINLCUDE### */



if($_REQUEST['act'] == 'del') {
	include 'sys/lib.import_export_filter.php';
	include 'sys/lib.import.php';
	$import = new import();
	$import->delete_filter($_REQUEST['ID_IMPORT_FILTER']);
}

$query = "select
				t.*,
				s.V1,
				s.V2,
				s.T1
			from
				`import_filter` t
			left join
				string_app s on s.S_TABLE='import_filter'
				and s.FK=t.ID_IMPORT_FILTER
				and s.BF_LANG=if(t.BF_LANG_APP & ".$langval.", ".$langval.", 1 << floor(log(t.BF_LANG_APP+0.5)/log(2)))
			ORDER by
				IDENT ASC";

$tpl_content->addlist("liste", $db->fetch_table($query), "tpl/de/importfilter.row.htm");

?>