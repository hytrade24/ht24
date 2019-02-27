<?php
/* ###VERSIONSBLOCKINLCUDE### */


function do_query($sql, $b_die = true) {
	global $dbobj;
	#die(ht(dump($dbobj)));
	$res = $dbobj->querynow ( $sql );
	if ($b_die && ! $res ['rsrc'])
		die ( ht ( dump ( $res ) ) );
	return $res ['rsrc'];
}
/*/
  function do_query($sql, $b_die=true)
  {
    $rsrc = mysql_query($sql);
    if (!$rsrc && $b_die) die('<b>'. stdHtmlentities($sql). '</b><br />'. mysql_error());
    return $rsrc;
  }
/**/
/*
mysql_connect();
mysql_select_db('test');
$rsrc = do_query("show fields from formgen");
echo stdHtmlentities(mysql_error());
while ($row = mysql_fetch_assoc($rsrc))
{
  if (preg_match('/^(\w+)(\((.*)\))?( (.*))?$/', $row['Type'], $ar_match))
    array_shift($ar_match);
  echo '<b>', $row['Type'], ':</b><br />';#, implode(', ', $ar_match), '<br />';
  echo $ar_match[0], ': ',
    (preg_match_all("/'([^']*)'/", $ar_match[2], $m) ? implode(', ', $m[1]) : $ar_match[2]),
    ($ar_match[4] ? ' ('. $ar_match[4]. ')' : ''),
    '<br />'
  ;
}
die();
*/
session_start ();
$n_navroot = 2;
$s_cachepath = '../cache/';
require_once '../inc.app.php';
require_once 'sys/lib.kernel.php';
nocache ();
$uid = session_init ();
require_once 'inc.all.php';
maqic_unquote_gpc ();
$dbobj = new ebiz_db ( $db_name, $db_host, $db_user, $db_pass );
unset ( $db_user );
unset ( $db_pass );
#die(ht(dump($dbobj)));
function unset_tbl() {
	$GLOBALS ['s_tbl'] = $_SESSION ['tbl'] = false;
}
function unset_db() {
	unset_tbl ();
	$GLOBALS ['s_db'] = $_SESSION ['db'] = false;
	$_SESSION ['ar_tables'] = array ();
}
function unset_fields() {
	$_SESSION ['ar_fields'] = $_SESSION ['xtables'] = array ();
}
unset_fields ();

function loadfields($s_tbl) {
	if ($ar = $_SESSION ['ar_tables'] [$s_tbl])
		return ($_SESSION ['xtables'] [$s_tbl] ? 2 : 1);
	$rsrc = do_query ( "show fields from `$s_tbl`" );
	if (mysql_error ())
		return - 1;
	while ( $row = mysql_fetch_assoc ( $rsrc ) )
		$_SESSION ['ar_fields'] [$s_tbl] [] = $row;
	return 1;
}

function seek_fk($s_xtbl, $s_ftbl) {
	$ar = array ();
	if (loadfields ( $s_ftbl ) > 0) {
		$s_pcre = '/^FK_' . strtoupper ( $s_ftbl ) . '(_\w+|\d+)$/';
		foreach ( $_SESSION ['ar_fields'] [$s_ftbl] as $row )
			if (preg_match ( $s_pcre, $row ['Field'] ))
				$ar [] = $s_field;
	}
	return $ar;
}

function seek_ftable($s) {
	preg_match ( '/^' . $s . '.*$/im', implode ( "\n", $_SESSION ['ar_tables'] ), $tmp );
	return ($tmp ? $tmp [0] : $s_ftbl);
}

$_SESSION ['db'] = $db_name;
$_GET ['db'] = '';
if ('root' == $_GET ['mode'])
	$_SESSION ['tbl'] = $s_tbl = $_GET ['tbl'] = '';
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title>eak form generator</title>
<style type="text/css">
em {
	color: orange;
	font-weight: bold;
	font-style: normal;
}
</style>
</head>
<body>
<?php
/*
  mysql_connect();
  if ('root' == ($s_mode = $_GET['mode']))
    unset_db();
  if ($s_db = $_GET['db'])
  {
    unset_tbl();
    $_SESSION['db'] = $s_db;
  }
  else
*/
$s_db = $_SESSION ['db'];

if ($s_db && $s_tbl = $_GET ['tbl']) {
	#    mysql_select_db($s_db);
	$_SESSION ['tbl'] = $s_tbl;
	loadfields ( $s_tbl );
	if (! $_SESSION ['xtables'] [$s_tbl]) {
		$_SESSION ['xtables'] [$s_tbl] = true;
		// possible cross tables
		#echo '<pre>'; var_dump($_SESSION['ar_tables']); echo '</pre>';
		if (! $_SESSION ['ar_tables'])
			while ( list ( $s_tbl ) = mysql_fetch_row ( $rsrc ) )
				$_SESSION ['ar_tables'] [] = $s_tbl;
		foreach ( $_SESSION ['ar_tables'] as $s_xtbl ) {
			if (! strstr ( $s_xtbl, $s_tbl ))
				continue;
			if (! preg_match ( '/^' . $s_tbl . '2(\w+)$/', $s_xtbl, $ar_match ))
				if (! preg_match ( '/^(\w+)2' . $s_tbl . '$/', $s_xtbl, $ar_match ))
					continue;
			if (! seek_fk ( $s_ftbl, $s_tbl ) || ! seek_fk ( $s_xtbl, $s_ftbl = $ar_match [1] ))
				continue;
			$ar_fields [] = array ('Field' => 'FK_' . strtoupper ( $s_ftbl ) . '[]', 'Type' => 'xtable', 'Null' => 'YES', 'Key' => 'X', 'Default' => NULL );
		}
	}
} else
	$s_tbl = $_SESSION ['tbl'];

$s_self = basename ( __FILE__ );
if ($s_db) {
	$_SESSION ['ar_tables'] = array_unique ( $_SESSION ['ar_tables'] );
	sort ( $_SESSION ['ar_tables'] );
	mysql_select_db ( $s_db );
	if ($s_tbl) {
		// transform sql field definition --> input definition
		foreach ( $_SESSION ['ar_fields'] [$s_tbl] as $row ) {
			$s_name = $row ['Field'];
			echo '<b>', $s_name, '</b>: ';
			if ('ID_' . strtoupper ( $s_tbl ) == $s_name || 'PRI' == $row ['Key']) {
				echo 'PK<br />';
				$_POST [$s_name] = array ('type' => 'hidden', 'maxlen' => $s_size + ($b_unsigned ? 0 : 1), 'check' => ($b_unsigned ? 'u' : '') . 'int(' . $s_size . ')' );
			} else {
				preg_match ( '/^(\w+)(\((.*)\))?( (.*))?$/', $row ['Type'], $tmp );
				list ( $s_dummy, $s_type, $s_dummy, $s_size, $s_dummy, $s_options ) = $tmp;
				echo $s_type, ' (', $s_size, ') ', $s_options, '<br />';
				switch ($s_type) {
					case 'varchar' :
						// Namen checken (MAIL, URL, PATH, FN_) --> Funktion
						if (! $_POST [$s_name]) {
							$_POST [$s_name] ['type'] = 'text';
							$_POST [$s_name] ['maxlen'] = $s_size;
							if (preg_match ( '/(^E?MAIL|^B?CC$|^FROM$)/i', $s_name ))
								$_POST [$s_name] ['check'] = 'validate_mail';
							elseif (preg_match ( '/IMG/i', $s_name ))
								$_POST [$s_name] ['type'] = 'img';
							elseif (preg_match ( '/(PATH|^FN_)/i', $s_name ))
								$_POST [$s_name] ['type'] = 'file';
							elseif (preg_match ( '/URL/i', $s_name )) {
								$_POST [$s_name] ['check'] = 'validate_url';
								$_POST [$s_name] ['pre'] = 'http://';
							}
						}
						break;
					case 'char' :
						if (! $_POST [$s_name]) {
							$_POST [$s_name] ['type'] = 'text';
							$_POST [$s_name] ['maxlen'] = $s_size;
						}
						break;
					case 'bigint' :
					case 'tinyint' :
					case 'smallint' :
					case 'mediumint' :
					case 'int' :
						$b_unsigned = preg_match( '/unsigned/i', $s_options );
						if (! $_POST [$s_name]) {
							$_POST [$s_name] = array ('type' => 'text', 'maxlen' => $s_size + ($b_unsigned ? 0 : 1), 'check' => ($b_unsigned ? 'u' : '') . 'int(' . $s_size . ')' );
							#echo '<h1>huhu</h1>';
							preg_match ( '/^(\w\w|B)_(.*)$/i', $s_name, $tmp );
							$s_ftbl = strtolower ( $tmp [2] );
							switch ($s_prefix = strtoupper ( $tmp [1] )) {
								case 'ID' :
								case 'FK' :
									$_POST [$s_name] ['type'] = 'select';
									$_POST [$s_name] ['ftable'] = seek_ftable ( $s_ftbl );
									if ('ID' == $s_prefix)
										$_POST [$s_name] ['fieldname'] = $s_name;
									break;
								case 'BV' :
								case 'BF' :
									if (preg_match( '/^LANG(_[A-Z_]+)?$/i', $s_ftbl )) {
										$_POST [$s_name] ['type'] = 'string';
										$_POST [$s_name] ['ftable'] = seek_ftable ( 'string' . substr ( $s_ftbl, 4 ) );
									} else {
										$_POST [$s_name] ['type'] = ('BV' == $s_prefix ? 'radio' : 'check');
										$_POST [$s_name] ['ftable'] = seek_ftable ( $s_ftbl );
									}
									break;
								default :
									// nop
									break;
							}
						}
						//
						break;
					case 'text' :
					case 'tinytext' :
					case 'mediumtext' :
					case 'longtext' :
						$_POST [$s_name] = array ();
						// Namen checken (SER_, SET_)
						if (preg_match( '/^SER_/i', $s_name ))
							$s_name;
						echo '<em>text</em><br />';
						//
						break;
					case 'float' :
					case 'double' :
					case 'decimal' :
						echo '<em>float</em><br />';
						//
						break;
					case 'timestamp' :
						echo '<em>float</em><br />';
						//
						break;
					case 'year' :
						echo '<em>float</em><br />';
						//
						break;
					case 'tinyblob' :
					case 'blob' :
					case 'mediumblob' :
					case 'longblob' :
						echo '<em>float</em><br />';
						// Namen checken (IMG_)
						break;
					case 'enum' :
						echo '<em>enum</em><br />';
						preg_match_all ( "/'([^']*)'/", $s_size, $tmp );
						$ar_opts = $tmp [1];
						//
						break;
					case 'set' :
						echo '<em>set</em><br />';
						preg_match_all ( "/'([^']*)'/", $s_size, $tmp );
						$ar_opts = $tmp [1];
						//
						break;
					case 'xtable' :
						echo '<em>xtable</em><br />';
						//
						break;
					default :
						echo '<em>date/time</em><br />';
						if (preg_match( '/^date/', $s_type ))
							// datedrop; <=> abfragen
							;
						if (preg_match( '/time$/', $s_type ))
							// timedrop
							;
						break;
				} // end switch
			}
			echo php_dump ( $_POST [$s_name] ), '<br />';
		} // end foreach
		echo '<hr><a href="', $s_self, '?mode=root">andere Tabelle w&auml;hlen</a>';
	} else // select table
{
		echo '<h1>Tabelle w&auml;hlen</h1>';
		$rsrc = do_query ( "show tables" );
		while ( list ( $s_tbl ) = mysql_fetch_row ( $rsrc ) ) {
			$_SESSION ['ar_tables'] [] = $s_tbl;
			echo '
<a href="', $s_self, '?tbl=', htmlentities ( $s_tbl ), '">', htmlentities ( $s_tbl ), "</a><br />\n";
		}
	}
} else // select database
{
	unset_fields ();
	echo '<h1>Datenbank w&auml;hlen</h1>';
	$rsrc = do_query ( "show databases" );
	while ( list ( $s_db ) = mysql_fetch_row ( $rsrc ) )
		echo '
<a href="', $s_self, '?db=', $s_db, '">', $s_db, "</a><br />\n";
}
?>
</body>
</html>
