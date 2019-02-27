<?php
/**
 * ebiz-trader
 *
 * @copyright Copyright (c) 2012 ebiz-consult e.K.
 * @version 7.0.3
 */


/**
* Datenbank-Wrapper (MySQL)
*
* @global array $ar_query_log (int=>array (s. Methode submit))
* @session array $assoc_blank (tablename => assoc(field => definition))
* @global assoc $lastresult (s. Methode submit))
*/
define ('SQL_ERR_DUP_ENTRY', 1062);

/*
  DB wrapper MySQL3 - simple version

  this version is not fit for multi-db applications
  - always uses the last established mysql connection
  - always uses the currently selected database
    and a database name must be provided with initialisation
  - does not support table prefixes
*/

/**
* Datenbank-Wrapper (MySQL)
*
* @var rsrc rsrc_db MySQL link resource
* @var string str_dbname Datenbankname
* @private array q_queries Query-Queue fuer Methoden query/submit/rollback
*/
class ebiz_db
{
  var $rsrc_db, $str_dbname; // dummy variables, used in constructor only
  var $q_queries = array (); // used by query, submit/rollback
  /*
    constructor db($str_dbname,
      [$str_host=NULL, [$str_user=NULL, [$str_pass=NULL]]]
    )
    - establish connection to db host
      if any of host, user, pass is not given, use default settings from php.ini
    - select named db
  */
/**
* baut Datenbank-Verbindung auf und selektiert Datenbank
*
* @param string $str_dbname
* @param string $str_host=NULL
* @param string $str_user=NULL
* @param string $str_pass=NULL
*/
  function ebiz_db($str_dbname,
    $str_host=NULL, $str_user=NULL, $str_pass=NULL, $new_link = false)
  {
      if ($str_host === NULL) $str_host = ini_get("mysql.default_host");
      if ($str_user === NULL) $str_user = ini_get("mysql.default_user");
      if ($str_pass === NULL) $str_pass = ini_get("mysql.default_password");
      $this->rsrc_db = @mysql_connect($str_host, $str_user, $str_pass, $new_link, 128);
      $success = true;
      $lockFile = $GLOBALS["ab_path"]."dbError.lock";
      if (!$this->rsrc_db) {
          $success = false;
          $sendMail = true;
          if (file_exists($lockFile) && ((time() - filemtime($lockFile)) < 1800)) {
              // Only send error by email once every 30 minutes
              $sendMail = false;
          } else {
              // Create/Update lock file
              touch($lockFile);
          }
          myerr('cannot connect to db host', true, $sendMail);
      } else if ($this->str_dbname=$str_dbname) {
          $result = mysql_select_db($str_dbname, $this->rsrc_db);
          if (!$result) {
              $success = false;
              $sendMail = true;
              if (file_exists($lockFile) && ((time() - filemtime($lockFile)) < 1800)) {
                  // Only send error by email once every 30 minutes
                  $sendMail = false;
              } else {
                  // Create/Update lock file
                  touch($lockFile);
              }
              myerr('cannot select db'. mysql_error(), true, $sendMail);
          }
      }
      mysql_set_charset("utf8", $this->rsrc_db);
      mysql_query("set character_set_database=utf8", $this->rsrc_db);
      if ($success && file_exists($lockFile)) {
          unlink($lockFile);
      }
  }

// QUERIES ---------------------------------------------------------------------
/**
* Query an Queue anhÃ¤ngen
*
* @param string $str_sql
*/
  /* append query to queue */
  function query($str_sql)
  {
#echo "$str_sql<br />";
    $this->q_queries[] = $str_sql;
  }

/**
* Query direkt ausfÃ¼hren
*
* @param string $str_sql
* @return assoc (s. submit)
*/
  /* execute query and return assoc as described at submit() */
  function querynow($str_sql, $b_die=false, $cursorScroll = false)
  {
    $tmp = $this->q_queries;
    $this->q_queries = array ($str_sql);
    $ret = reset($this->submit(false, $cursorScroll));
    $this->q_queries = $tmp;
    if (DEV_DEBUG && !$ret['rsrc']) {
        if (!array_key_exists("DEV_DEBUG_FILE", $GLOBALS)) {
            $file_log = fopen(__DIR__."/../../dev/log/mysql.log", "w+");
        } else {
            $file_log = $GLOBALS["DEV_DEBUG_FILE"];
        }
        fwrite($file_log, var_export($ret, true)."\n");
    }
    if ($b_die && !$ret['rsrc'])
      if ($GLOBALS['SILENCE'])
        myerr(dump($ret), true);
      else
        die(ht(dump($ret)));
    return $ret;
  }

/**
* Query direkt ausfÃ¼hren
*
* @param string $str_sql
* @return assoc (s. submit)
*/

	function getfrom_tmp ($ident)
	{
		return $this->fetch_atom('select value from tmp_ where ident="'.$ident.'"');

	}

		function putinto_tmp ($ident,$value)
	{
		$this->querynow('insert into tmp_ (ident,value) values  ("'.$ident.'","'.$value.'"  )');
		if (!$ret['rsrc'])
			$this->querynow('update tmp_  set value="'.$value.'" where ident ="'.$ident.'"');
		return;

	}

/**
* Query-Queue leeren
*
* @return array (string Query-String)
*/
  /* returns current query queue, empties query queue */
  function rollback()
  {
    $res = $this->q_queries;
    $this->q_queries = array ();
    return $res;
  }

/**
* Queries ausfÃ¼hren
*
* arbeitet die Query-Queue ab und liefert eine Liste von assoziativen Arrays mit den Ergebnissen zurÃ¼ck.
* Ist $fl_ignoreerrors TRUE, wird ohne RÃ¼cksicht auf Fehlermeldungen durchgearbeitet;
* sonst wird bei einem Fehler abgebrochen; die nicht ausgefÃ¼hrten Queries erhalten den Status 'not executed'

* @param boole fl_ignoreerrors = false

* @return string str_query Query-String
* @return mixed rsrc: 'not executed': false; sonst RÃ¼ckgabe von mysql_query()
* @return uint int_result: false wenn 'not executed'; mysql_errno() bei Fehler; mysql_insert_id() nach update, mysql_affected_rows/mysql_num_rows sonst
* @return string str_error: bei Fehler mysql_error(), sonst Leerstring
* @return float flt_runtime: Laufzeit in Sekunden

* @global $lastresult enthÃ¤lt das Ergebnis der letzten ausgefÃ¼hrten Query
*/
  function submit($fl_ignoreerrors = false, $cursorScroll = false)
  /* submits queries in queue
      ($fl_ignoreerrors=false: while no error occurs)
    returns list of assocs:
      str_query => Query String
      rsrc =>
        not executed: false
        insert, update, delete: true/false
        else: result resource/false
      int_result =>
        failure: mysql_errno()
        not executed: false
        insert: mysql_insert_id()
        update, delete: no of affected rows
        else: no of selected rows
      str_error => mysql_error(); empty string on success or if not executed
      str_command => insert/update/create etc.
      flt_runtime => Laufzeit der Query

    global variable $lastresult contains result assoc of last query executed
  */
  {
    global $lastresult;
    $return = array ();
#echo '<hr />', ht(dump($this->q_queries));
    foreach ($this->q_queries as $i=>$str_sql)
    {
#echo ht($str_sql), '<br />';
      $str_sql = trim($str_sql);
      $row = array ('str_query'=>$str_sql);
      $t0 = microtime();
      if (version_compare(phpversion(), "7.0.0", ">=")) {
          $row['rsrc'] = mysql_query($str_lastquery = $str_sql, $this->rsrc_db, $cursorScroll);
      } else {
          $row['rsrc'] = mysql_query($str_lastquery = $str_sql, $this->rsrc_db);
      }
      $t1 = microtime();
      $row['flt_runtime'] = array_sum(explode(' ', $t1)) - array_sum(explode(' ', $t0));
      if ($row['rsrc']) // success
      {
#echo ht(dump($row));
        $str_command = substr(strtolower($str_sql), 0,
          strspn(strtolower($str_sql), 'abcdefghijklmnopqrstuvwxyz'));
        switch ($str_command)
        {
          case 'insert':
#echo 'insert';
            $row['int_result'] = mysql_insert_id($this->rsrc_db);
            break;
          case 'update':
#echo 'update';
          case 'delete':
#echo 'delete';
            $row['int_result'] = mysql_affected_rows($this->rsrc_db);
            break;
          case 'drop': case 'create': case 'alter': case 'set': case 'load': case 'truncate':
          case 'replace':case 'start':case 'rollback':case 'commit': case '':
#echo 'other';
            break;
          default:
#echo 'default';
            $row['int_result'] = mysql_num_rows($row['rsrc']);
            break;
        }
        $row['str_error'] = '';
        $row['str_command'] = $str_command;
#echo ht(dump($row)), '<hr />';
        $return[] = $ar_query_log[] = $lastresult = $row;
      }
      else // failure
      {
        $row['int_result'] = mysql_errno($this->rsrc_db);
        $row['str_error'] = mysql_error($this->rsrc_db);
#if (preg_match('/^'. preg_quote('update ebiz_bestellung_produkt set berechnet='). '\s*if\s*\(/', $str_sql)) echo ht(dump($row));
        $ar_query_log[] = $return[] = $lastresult = $row;
        if (!$fl_ignoreerrors)
        {
          for ($i++; $i<count($this->q_queries); $i++)
            $return[] = $ar_query_log[] = array (
              'str_command' => substr(strtolower($str_sql), 0,
                strspn(strtolower($str_sql), 'abcdefghijklmnopqrstuvwxyz')),
              'str_query'   => $this->q_queries[$i],
              'rsrc'        => false,
              'int_result'  => false,
              'str_error'   => 'not executed',
              'flt_runtime' => '-0.0',
            );
          break;
        }
      }
      $row['time'] = date('Y-m-d H:i:s');
      $GLOBALS['ar_query_log'][] = $row;
    }
//echo '<hr />', listtab($return), '<hr />';
    $this->q_queries = array ();
    return $return;
  } // function db::submit

// AUX FUNCTIONS ---------------------------------------------------------------
/**
* Tabellendefinition
* puffert Definitionen in globalem Array
*
* @global assoc $assoc_tabledefs
* @param string $str_tablename
* @return assoc (s. http://dev.mysql.com/doc/mysql/en/show-columns.html)
*/
  function getdef($str_tablename)
  {
    global $assoc_tabledefs;
#$assoc_tabledefs = array ();
    if (!($def=$assoc_tabledefs[$str_tablename]))
    {
# $def = $this->fetch_table("show fields from `$str_tablename`", 'Field');
      $def = array ();
      $res = $this->querynow("show fields from `$str_tablename`");
if (!$GLOBALS['SILENCE'] && !$res['rsrc']) die('db-getdef:'.ht(dump($res)));
      $tmp = $this->fetch_table($res['rsrc']);
      foreach($tmp as $row)
        $def[$row['Field']] = $row;
      $assoc_tabledefs[$str_tablename] = $def;
    }
    return $def;
  } // ebiz_db::getdef

// QUERY RESULT DATA -----------------------------------------------------------
/**
* Daten aus Query laden
*
* @param mixed $rsrc Result Resource oder Query-String
* @param string $str_keycol=NULL Feld, nach dem die Liste indiziert werden soll. Numerische Indizierung falls NULL
* @param uint $int_start=false hilfreich beim BlÃ¤ttern: Nummer des ersten zu liefernden Datensatzes
* @param uint $int_count=false max. Anzahl der zurÃ¼ckzuliefernden Zeilen
* @return array (assoc)
*/
  /*
    fetches a list of assocs from provided mysql result resource
    may be limited using start and count as with a mysql LIMIT clause.
    if start is given but count omitted, (start) rows from 0 on will be returned
      i.e. fetch_table($rsrc, $n) is the same as fetch_table($rsrc, 0, $n)
    does not reset the result pointer!
    result_type is the same as in mysql_fetch_array; but default is MYSQL_ASSOC
  */
  function fetch_table($rsrc, $str_keycol=NULL, $int_start=false, $int_count=false, $int_result_type = MYSQL_ASSOC) // other options: MYSQL_NUM or MYSQL_BOTH
  {
    if (is_string($rsrc))
    {
      $res = $this->querynow($rsrc);
      $rsrc = $res['rsrc'];
    }
    $return = array ();
    if (!$rsrc) {
    	if ($GLOBALS['SILENCE']) {
    		eventlog("error", "Error - MySQL Statement failed!", "Error message:\n".mysql_error()."\nFailed query: \n".$GLOBALS['lastresult']["str_query"]);
    	} else {
    		die('db-fetchtab:'.ht(dump($GLOBALS['lastresult'])));
    	}
    }
    if (false!==$int_start && false===$int_count)
    {
      $int_count = $int_start;
      $int_start = 0;
    }
    if ($int_start)
      mysql_data_seek($rsrc, $int_start);
    for ($i=0; (false===$int_count || $i<$int_count) && ($row = mysql_fetch_array ($rsrc, $int_result_type)); $i++)
      if ($str_keycol)
        $return[$row[$str_keycol]] = $row;
      else
        $return[] = $row;
#echo "########################################## $i <br>";
#var_dump($GLOBALS['lastresult']);echo '<hr />'; var_dump($rsrc); echo '<hr />';die(var_dump($return));
    return $return;
  } // ebiz_db::fetch_table

/**
* Hilfsfunktion fuer Methode update:
* - MTIME=now()
* - null
* - mysql_escape_string
* - Datums- und Zeitfunktionen (insbesondere "now()")
* - Automatische Werte-Umwandlung fuer SET_ und SER_
* @private
*/
  function update_convert($s_fieldname, &$data, &$def)
  {
    $val = (is_array ($data) ? $data[$s_fieldname] : $data);
    if (is_null($val))
    // Modification Stamps
      if ('MTIME'==$s_fieldname || 'MDATE'==$s_fieldname)
        return 'now()';
    // anderes NULL
      else
        return 'NULL';
    // Zahlen
    if ((is_numeric($val) || preg_match('/^(\d+|\d*\.\d+|\d+\.\d*)$/', $val)) && strpos($val, '0') !== 0)
      return "'".$val."'";
    // Datums- und Zeit-Funktionen
    if (preg_match('/^(date|time|datetime|timestamp)$/', $def[$s_fieldname]['Type']))
    {
      if (preg_match('/^\w+\(.*\)$/', $val))
        return $val;
    }
    // Set und Serialize
    elseif (is_array ($val))
      $val = (strncmp('SET_', $s_fieldname, 4)
        ? serialize($val)
        : implode(',', $val)
      );
    return "'". mysql_escape_string($val). "'";
  }

/**
* Datensatz erstellen oder aktualisieren - nur fÃ¼r Tabellen, die den DB-Konventionen folgen!
*
* Ermittelt automatisch den Namen der Spalte fÃ¼r den Primary Key,
* unterscheidet, ob insert und update nÃ¶tig ist, escaped die Daten.
* SprachabhÃ¤ngige Inhalte werden zu der aktuellen Sprache gespeichert.
*
* @param string $str_tablename Tabellenname
* @param assoc $data
* @global uint $langval Bitwert der aktuellen Sprache

* @global assoc $lastresult
* @return uint Wert des Primary Key
*/
  function update($str_tablename, $data)
  /*
    insert or update a row in table (tablename)
    returns primary key value, false on failure
    if primary key (column id_(tablename)) is set and not false, try update first
    if update fails or primary key is empty, insert

    result assoc as returned by submit is stored in global variable $lastresult

    2005-04-23: Feld BF_LANG und Tabelle `string` ebenfalls aktualisieren
    2005-05-31: BF_LANG_<WORD> --> string_<word>
  */
  {
    global $str_lastquery, $langval;
    $def = $this->getdef($str_tablename);
    $allkeys = array_keys($def);
    $str_pkname = (strncmp('nav', $str_tablename, 3) ? 'ID_'.strtoupper($str_tablename) : 'ID_NAV');
    if (preg_match('/^BF_LANG(_[\w_]+)?$/m', implode("\n", $allkeys), $match))
    {
      $s_langfield = $match[0];
      $s_langtbl = 'string'. strtolower($match[1]);
    }
    else
      $s_langfield = false;
    $b_setlang = $s_langfield
      && count(array_intersect(array_keys($data), $ar_strfields = array ('V1', 'V2', 'T1')));

#echo(ht(dump($data))),"<br /><b>$str_pkname</b><hr />";
    if(strlen($id = $data[$str_pkname]) && (int)$this->fetch_atom(
      "select count(*) from `$str_tablename` where `$str_pkname`='$id'"
    ))
    {
      $set = array ();
      foreach($data as $key=>$val) {
        if (!is_array($val) && ($key != $str_pkname) && ($s_langfield != $key) && in_array($key, $allkeys)) {
          $set[] = "`$key`=". $this->update_convert($key, $data, $def);
        }
      }
      if ($b_setlang)
        $set[] = "`$s_langfield`=`$s_langfield` | $langval";

#die("update `$str_tablename` set ". implode(',', $set). " where `$str_pkname`=$id");
      $dbres = $this->querynow($str_lastquery = "update `$str_tablename` set "
        . implode(',', $set). " where `$str_pkname`=$id");
#die(ht(dump($dbres)));
      $fl_insert = !$dbres['rsrc'];
      if ($dbres['rsrc'] === false) {
		eventlog("error", "Error - MySQL update failed! (\$db->update)", "Update failed: \n".$dbres["str_query"]);
      }
    }
    else
    {
      $fl_insert = true;
      #unset($data[$str_pkname]);
    }

    if ($fl_insert)
    {
      $keys = $vals = array ();
      foreach($data as $key=>$val) if ($s_langfield!=$key && in_array ($key, $allkeys))
      {
        $keys[] = "`$key`";
        $vals[] = $this->update_convert($key, $data, $def);
      }
      if ($b_setlang)
      {
        $keys[] = $s_langfield;
        $vals[] = $langval;
      }

      $dbres = $this->querynow($str_lastquery = "insert into `$str_tablename`\n  ("
        . implode(',', $keys). ")\n  values (". implode(',', $vals). ")");
#die(ht(dump($dbres)));
      $id = ($dbres['rsrc'] ? $dbres['int_result'] : false);
      if ($id === false) {
		eventlog("error", "Error - MySQL update failed! (\$db->update)", "Insert failed: \n".$dbres["str_query"]);
	  }
    }

    if ($id && $b_setlang)
    {
      $bak = $GLOBALS['lastresult'];
      if (!$fl_insert)
        $fl_insert = !(int)$this->fetch_atom("select count(*) from $s_langtbl
          where S_TABLE='$str_tablename' and FK=$id and BF_LANG=$langval");
      if ($fl_insert)
      {
        $keys = array ('S_TABLE', 'FK', 'BF_LANG');
        $vals = array ("'$str_tablename'", $id, $langval);
        foreach($ar_strfields as $dummy=>$k) if (!is_null($data[$k]))
          { $keys[] = $k; $vals[] = "'". mysql_escape_string($data[$k]). "'"; }
        $this->querynow("insert into `$s_langtbl`\n  ("
        . implode(',', $keys). ")\n  values (". implode(',', $vals). ")");
      }
      else
      {
        $set = array ();
        foreach($ar_strfields as $dummy=>$k) $set[] = $k. '='.
          (is_null($data[$k]) ? 'NULL' : "'". mysql_escape_string($data[$k]). "'");
        $up = $this->querynow("update $s_langtbl set ". implode(', ', $set). "
          where S_TABLE='$str_tablename' and FK=$id and BF_LANG=$langval");
        #echo ht(dump($up));die();
	  }
#echo ht(dump($bak)), ht(dump($GLOBALS['lastresult']));die();
      $GLOBALS['lastresult'] = $bak;
    }

    return $id;
  } // ebiz_db::update


/**
* Datensatz erstellen oder aktualisieren - fÃ¼r Tabellen mit mehrspaltigem Primary Key
*
* Falls kein Primary, aber ein Unique Key existiert, mÃ¼ssen die Spaltennamen
* als dritter Parameter angegeben werden.
* Fehlt dieser Parameter, werden die Felder des Primary Key automatisch ermittelt.
*
* Keine PK-Felder gefunden: NULL
* Keine Mehrsprachigkeit!
*
* @param string $str_tablename Tabellenname
* @param assoc $data
* @param $ar_pkfields = array()

* @global assoc $lastresult
* @return db_result assoc oder NULL (keine Daten oder keine Key-Felder)
*/
  function update_noid($s_table, $data, $ar_pkfields = array())
  {
    static $b_oldmysql = true;#NULL;xxx  submit() cant handle "on duplicate key update" results (yet)
    if (!$data || !is_array($data)) return NULL;
    if (is_null($b_oldmysql))
      $b_oldmysql = (function_exists('version_compare')
        ? version_compare(
          $this->fetch_atom("show variables like 'version'", 2),
          '4.1', '<')
        : strcmp($this->fetch_atom("show variables like 'version'", 2), '4.1') < 0
      );

    $def = $this->getdef($s_table);
    if (!$ar_pkfields)
    {
      foreach ($def as $tmp) if ('PRI'==$tmp['Key'])
        $ar_pkfields[] = $tmp['Field'];
      if (!$ar_pkfields) return NULL;
    }

    $sql = array();
    foreach($data as $k=>$v)
      $sql[$k] = update_convert($k, $data, $def);
    $s_insert = "insert into `$s_table` (`"
      . implode("`, `", array_keys($sql)). "`)
      values (". implode(', ', $sql). ")"
    ;

    $ar_update = array();
    foreach($sql as $k=>$v) if (!in_array($k, $ar_pkfields))
      $ar_update[] = "`$k`=$v";
    $s_update = "set ". implode(', ', $ar_update);
#die(dump($b_oldmysql));

    if ($b_oldmysql)
    {
      $res = $this->querynow($s_insert);
      if (!$res['rsrc'] && SQL_ERR_DUP_ENTRY==$res['int_result']) // 1062==duplicate entry
      {
        $ar_where = array();
        foreach($ar_pkfields as $k)
          $ar_where[] = "`$k`=". $sql[$k];
        $res = $this->querynow("update `$s_table` ". $s_update. "
          where ". implode(' and ', $ar_where));
#die(ht(dump($res)));
      }
    }
    else
      $res = $this->querynow($s_insert. "
        on duplicate key update ". $s_update);
    return $res;
  }


/**
* Datensatz loeschen
*
* @param string $s_table Tabellenname
* @param string $id ID-Wert

* @return uint Anzahl geloeschter Strings)
*/
  function delete($s_table, $id)
  {
    $s_pkey = (strncmp('nav', $s_table, 3) ? 'ID_'.strtoupper($s_table) : 'ID_NAV');

    $res = $this->querynow("delete from `". $s_table. "` where ". $s_pkey. "=". (int)$id);

		$ar = $this->getdef($s_table);
		$b_lang = false;

		foreACH($ar AS $key => $dummy)
		{
		  if(strstr($key, "BF_LANG"))
			{
			  $b_lang = $key;
				break;
			}
		}


		if ($b_lang)
    {
      //$res = $db->querynow("delete from string". strtolower($match[1]). " where S_TABLE='". $s_table. "' and FK=". (int)$id);
      //$n = $res['int_result'];

			$hack = explode ("_", $b_lang);
			$str_table = $hack[2];

			$res = $this->querynow("delete from string_". strtolower($str_table). " where S_TABLE='". $s_table. "' and FK=". (int)$id);
        $n = $res['int_result'];

			//$n = 1;
    }
    else
      $n = 0;
    return $n;
  }

/**
* leeren Datensatz laden
*
* lÃ¤dt die Tabellendefinition und erstellt einen Datensatz mit den Default-Werten
*
* @param string $str_tablename Tabellenname
* @global uint $langval Bitwert der aktuellen Sprache

* @global assoc $assoc_blank
* @return assoc
*/
  function fetch_blank($str_tablename)
  /* returns a blank row from named table using 'default' definitions
    false on error

    result assoc as returned by submit is stored in global variable $lastresult
  */
  {
    global $assoc_blank;
    if (!($row = $assoc_blank[$str_tablename]))
    {
      $res = $this->querynow("show fields from `$str_tablename`");
      if ($rsrc = $res['rsrc'])
      {
        $table = $this->fetch_table($rsrc);
        $row = array ();
#echo $str_tablename, ht(dump($table));
        foreach($table as $defrow)
          $row[$defrow['Field']] = (/**/'PRI'==$defrow['Key'] ? 0:/**/$defrow['Default']);
#echo '<hr />', ht(dump($row));
      }
      else
        return false;
    }
    return $row;
  } // ebiz_db::fetch_blank

/**
* genau einen Datensatz laden
*
* LÃ¤dt den ersten Datensatz, der von der Query geliefert wird
*
* @param string $str_query Query-String
* @return assoc
*/
  function fetch1($str_query)
  {
    $res = $this->querynow($str_query);
    if (!$res['rsrc']) {
    	if ($GLOBALS['SILENCE']) {
    		eventlog("error", "Error - MySQL Statement failed!", "Error message:\n".mysql_error()."\nFailed query: \n".$res["str_query"]);
    	} else {
    	    debug_print_backtrace();
    		die('db-fetch1:'.ht(dump($res)));
    	}
    }
    return mysql_fetch_assoc($res['rsrc']);
  } // ebiz_db::fetch1

/**
* genau einen Wert laden
*
* liefert das $n-te Feld des ersten Datensatzes
*
* @param string $str_query Query-String
* @param uint $n_col=1 Position (Spalte)
* @return string (bzw. mixed)
*/
  function fetch_atom($str_query, $n_col = 1)
  {
    $res = $this->querynow($str_query);
    if (!$res['rsrc']) {
    	if ($GLOBALS['SILENCE']) {
    		eventlog("error", "Error - MySQL Statement failed!", "Error message:\n".mysql_error()."\nFailed query: \n".$res["str_query"]);
    	} else {
    		die('db-fetch_atom:'.ht(dump($res)));
    	}
    }
    $row = mysql_fetch_row($res['rsrc']);
    return $row[$n_col-1];
  } // ebiz_db::fetch_atom

/**
* eindimensionales assoziatives Array (SchlÃ¼ssel => Wert) laden
*
* liefert ein assoziatives Array, die ersten beiden selektierten Spalten liefern SchlÃ¼ssel bzw. Werte.
* Insbesondere nÃ¼tzlich fÃ¼r Dropdowns u.Ã¤.
*
* @param string $str_query Query-String
* @param uint $n_keycol=1 Position (Spalte); wenn 0, entspricht das ganze fetch_col
* @param uint $n_vcol=2 Position (Spalte)
* @return assoc (feld1=>feld2)
*/
  function fetch_nar($str_query, $n_keycol=1, $n_vcol=2)
  {
    $res = $this->querynow($str_query);
    $ret = array ();
    if (!$res['rsrc'])
    {
      if (!$GLOBALS['SILENCE'])
        die('db-fetch_nar:'.ht(dump($res)));
      else
        echo '<!-- ', ht(dump($res)), ' -->';
    }
    else
      if ($n_keycol)
        while ($row = mysql_fetch_row($res['rsrc']))
          $ret[$row[$n_keycol-1]] = $row[$n_vcol-1];
      else
        while ($row = mysql_fetch_row($res['rsrc']))
          $ret[] = $row[$n_vcol-1];
    return $ret;
  } // ebiz_db::fetch_nar

/**
* genau einen Wert laden
*
* liefert das $n-te Feld des ersten Datensatzes
*
* @param string $str_query Query-String
* @param uint $n_col=1 Position (Spalte)
* @return string (bzw. mixed)
*/
  function fetch_col($str_query, $n_col = 1)
  {
    $res = $this->querynow($str_query);
    $ret = array();
    if (!$res['rsrc'])
    {
      if (!$GLOBALS['SILENCE'])
        die('db-fetch_col:'.ht(dump($res)));
      else
        echo '<!-- ', ht(dump($res)), ' -->';
    }
    while ($row = mysql_fetch_row($res['rsrc']))
      $ret[] = $row[$n_col-1];
    return $ret;
  } // ebiz_db::fetch_nar

/**
* Query fÃ¼r mehrsprachige DatensÃ¤tze erstellen
*
* ermittelt, ob die angegebene Tabelle mehrsprachige Daten enthÃ¤lt und baut eine entsprechende Query (gegebenenfalls mit join) auf
* Falls mehrsprachig, wird "LABEL" durch "V1" ersetzt, "*" durch "*, V1, V2, T1"
*
* @param string $s_table Tabellenname
* @param string $s_fields='*' Feldliste
* @param $b_force_langjoin=false Language-Join erzwingen
* @global uint $langval
* @return assoc (feld1=>feld2)
*/
  function lang_select($s_table, $s_fields = '*', $b_force_langjoin=false)
  {
    global $langval;
    if ($_SESSION && $_SESSION['lang_select'] && $_SESSION['lang_select'][$langval]
      && ($sql = $_SESSION['lang_select'][$langval][$s_table][$s_fields.($b_force_langjoin ? ',join': '')])
    );
    else
    {
      $sql = 'select '. $s_fields. ' from `'. $s_table. '` t ';
      if (preg_match('/^BF_LANG(_[\w_]+)?$/m', implode("\n", array_keys($this->getdef($s_table))), $match))
      {
        $s_langfield = $match[0];
        $ar_fields = explode(',', $s_fields);
        foreach($ar_fields as $i=>$s_tmp)
        {
          $s_tmp = trim($s_tmp);
          if ('*'==$s_tmp)
            $ar_fields[$i] = ' t.*, s.V1, s.V2, s.T1';
          elseif ('LABEL'==$s_tmp)
            $ar_fields[$i] = ' s.V1 LABEL';
          elseif ('count(*)'==$s_tmp)
            $ar_fields[$i] = $s_tmp;
          elseif (!preg_match('/^(\d+\b|\.|\(|\')*/', $s_tmp))
            $ar_fields[$i] = ' t.'. trim($s_tmp);
        }
        $s_tfields = implode(',', $ar_fields);
        if (preg_match('/\bt\.\*/', $s_tfields) && !preg_match('/\bs\.V1\b/', $s_tfields))
          $s_tfields .= ', s.V1';
        $s_tfields = str_replace('t.LABEL', 's.V1', $s_tfields);
        if ($b_force_langjoin || preg_match('/s\./', $s_tfields))
          $sql = "select ". $s_tfields. " from `". $s_table. "` t
              left join string". strtolower($match[1]). " s on s.S_TABLE='". $s_table. "' and s.FK=t.ID_"
            . (preg_match('/^nav/', $s_table) ? 'NAV' : strtoupper($s_table)). "
              and s.BF_LANG=if(t.". $s_langfield. " & $langval, $langval, 1 << floor(log(t.". $s_langfield. "+0.5)/log(2)))";
      }

      if ($b_force_langjoin)
        $s_fields .= ',join';
      if ($_SESSION)
        $_SESSION['lang_select'][$langval][$s_table][$s_fields] = $sql;
      else
        $GLOBALS['lang_select'][$langval][$s_table][$s_fields] = $sql;
    }
    return $sql;
  }


// LOCKS =======================================================================
/*
  0/false, wenn der User den Lock erhaelt/schon besitzt
  true, wenn ein Lock von einem anderen User existiert
  -1,      wennUser oder Lock-Ident fehlen
*/
  function lock($s_lock, $s_expire=NULL)
  {
    global $uid;
#echo "<b>lock($s_lock, $s_expire)</b><br />";
    if (!$uid || !($s_lock = trim($s_lock)))
      return -1;


    if (is_null($s_expire))
      $s_expire = $GLOBALS['nar_systemsettings']['SITE']['lock_expire'];



    if ($s_expire)
      $s_expire = 'date_add(now(), interval '. $s_expire. ')';




    $mu = reset(explode(' ', microtime()));
/**/
    $lock = $this->lock_read($s_lock);
    $s_lock = mysql_escape_string($s_lock);

    if (!$lock ^ ($lock['is_expired'] || $lock['FK_USER']==$uid))
    { // geht in Ordnung

      if ($lock)
      {

        $res = $this->querynow("update locks set FK_USER=". (int)$uid. ", STAMP_EXPIRE=". $s_expire. ", mu=". $mu. "
          where IDENT='". $s_lock. "'");
        return !($res['rsrc'] && $res['int_result']);
      }
      else
      {


        $res = $this->querynow("insert into locks (IDENT, FK_USER, STAMP_EXPIRE)
          values('". $s_lock. "', ". (int)$uid. ", ". $s_expire. ")");
        return !$res['rsrc'];
      }
    }
    else
      return true;
/*/
    $s_lock = mysql_escape_string($s_lock);
    // Lock gehoert aktuellem User?
    $res = $this->querynow("update locks set STAMP_EXPIRE=". $s_expire. ", mu=". $mu. "
      where IDENT='". $s_lock. "' and FK_USER=". (int)$uid);
#echo ht(dump($res));
    if ($res['str_error'])
      die(ht(dump($res)));
    elseif ($res['int_result']) // result = affected rows
      return false;
    // veraltete Locks loeschen
    $this->querynow("delete from locks where STAMP_EXPIRE<now()");
#echo ht(dump($GLOBALS['lastresult']));
    // Lock von anderem User?
    // neuen Lock erstellen
    $res = $this->querynow("insert into locks (IDENT, FK_USER, STAMP_EXPIRE)
      values('". $s_lock. "', ". (int)$uid. ", ". $s_expire. ")");
    // Primary Key auf IDENT verursacht SQL-Fehler, wenn der Lock schon existiert
    return !$res['rsrc'];
/**/
  }
/*
  function lock_use($s_lock)
  {
    if ($lock = $this->lock($s_lock))
      return false;
    else
    {
      $s_lock = mysql_escape_string($s_lock);
      $mu = reset(explode(' ', microtime()));
      $res = $this->querynow("update locks
        set STAMP_USE=now(), FK_USER_USE=". (int)$GLOBALS['uid']. ", mu=". $mu. "
        where IDENT='". $s_lock. "' and FK_USER=". (int)$GLOBALS['uid']);
      return ($res['rsrc'] && $res['int_result']);
    }
  }
*/
  function lock_read($s_lock)
  {
    return $this->fetch1("select *, STAMP_EXPIRE<now() as is_expired
      from locks where IDENT='". mysql_escape_string($s_lock). "'");
  }

// PERMISSIONS =================================================================
  function perm_check($s_perm, $bf_mask=255, $id_user=NULL)
  {
    global $db, $lastresult;
    if (!$bf_mask || !$s_perm) return 0;
    if (is_null($id_user)) $id_user = $GLOBALS['uid'];
    if (is_array ($s_perm))
/**/
  die('nix mehr navperm!');
/*/
      $s_where = "perm.LU_TYP=". $s_perm[0]
        . (is_null($s_perm[1]) ? '' : " and perm.PKVALUE=". $s_perm[1]);
/**/
    else
      $s_where = "perm.IDENT='". mysql_escape_string($s_perm). "'";

    $tmp = $db->fetch_atom(((int)$id_user
/*
SELECT s.FK_ROLE, s.BF_ALLOW, 255 &~ ifnull( BF_REVOKE, 0 ) , ifnull( BF_GRANT, 0 ), v.*
, BF_ALLOW & ifnull(~BF_REVOKE, 255) | ifnull(BF_GRANT,0)
FROM perm
LEFT JOIN role2user z ON z.FK_USER =1
LEFT JOIN perm2role s ON s.FK_PERM = ID_PERM
AND s.FK_ROLE = z.FK_ROLE
LEFT JOIN perm2user v ON v.FK_PERM = ID_PERM
AND v.FK_USER =1
WHERE perm.IDENT = 'news_all'
*/
/** /
      ? "select bit_or(BF_ALLOW) & ifnull(~BF_REVOKE, 255) | ifnull(BF_GRANT,0) from perm
        left join role2user z on z.FK_USER=". (int)$id_user. "
        left join perm2role s on s.FK_PERM=ID_PERM and s.FK_ROLE=z.FK_ROLE
        left join perm2user v on v.FK_PERM=ID_PERM and v.FK_USER=". (int)$id_user
/*/
      ? "select ifnull(BF_CHECK,0) from perm
        left join perm2user on FK_PERM=ID_PERM and FK_USER=". (int)$id_user
/**/
      : "select ifnull(BF_ALLOW,0) from perm
        left join perm2role on FK_PERM=ID_PERM and FK_ROLE=1"
    ). " where $s_where");
#echo ht(dump($lastresult)), dump($tmp);
    return ($tmp ? $bf_mask & (int)$tmp : $bf_mask);
  } // end ebiz_db::perm_check

  function perm_inherit($id_perm, $id_user)
  {
    global $db;
    static $nar_role2user = NULL;
    if (is_null($nar_role2user))
      $nar_role2user = array ();
    if (!($tmp = $nar_role2user[$id_user]))
      $tmp = $nar_role2user[$id_user] = array_values($this->fetch_nar(
        "select FK_ROLE, FK_ROLE from role2user where FK_USER=". $id_user
      ));
    $bf = (count($tmp)
      ? (int)$this->fetch_atom("select bit_or(BF_ALLOW) from perm2role
        where FK_PERM=". $id_perm. " and FK_ROLE in (". implode(', ', $tmp). ")")
      : 0
    );
    $res = $this->querynow("update perm2user set BF_INHERIT=". $bf. ", BF_CHECK=(". $bf. " | BF_GRANT) & ~BF_REVOKE
      where FK_PERM=". $id_perm. " and FK_USER=". $id_user);
#echo ht(dump($res));
    if (0==$res['int_result'])
#{
      $res = $this->querynow("insert into perm2user (FK_PERM, FK_USER, BF_INHERIT)
        values (". $id_perm. ", ". $id_user. ", ". $bf. ")");
#echo ht(dump($res));
#}
/**/
    if ($res['int_result'] && !$res['str_error'])
      $db->perm_push();
/*/
    if ($res['int_result'] && !$res['str_error'] && SESSION)
      $_SESSION['perm_update'] = true;
/**/
    return $res;
  }

  function perm_push()
  {
    $this->querynow("update perm2user set BF_CHECK=(BF_INHERIT | BF_GRANT) &~ BF_REVOKE");
    $this->querynow("delete from perm2user where BF_CHECK=0 and BF_INHERIT=0 and BF_GRANT=0 and BF_REVOKE=0");
    $this->querynow("delete from perm2role where BF_ALLOW=0");
  } // end ebiz_db::perm_push

  function perm_user($id_user, $id_perm=-1, $bf_grant=0, $bf_revoke=0)
  {
    if (!$id_user)
      return false;
    $res = $this->querynow("update perm2user set BF_GRANT=". $bf_grant. ", BF_REVOKE=". $bf_revoke. "
, BF_CHECK = (BF_INHERIT | ". $bf_grant. ") & ~". $bf_revoke. "
      where ". ($s_where = "FK_USER=". $id_user. ($id_perm>0 ? " and FK_PERM=". $id_perm : ''))
    );
    if ($id_perm>0 && !$res['int_result'])
      $res = $this->querynow("insert into perm2user (FK_USER, FK_PERM, BF_INHERIT, BF_GRANT, BF_REVOKE, BF_CHECK)
        select ". $id_user. ", p.ID_PERM, ifnull(bit_or(q.BF_ALLOW),0), ". $bf_grant. ", ". $bf_revoke. "
, (ifnull(bit_or(q.BF_ALLOW),0) | ". $bf_grant. ") &~ ". $bf_revoke. "
        from perm p
          left join perm2role q on q.FK_PERM=p.ID_PERM
          left join role2user v on q.FK_ROLE=v.FK_ROLE and v.FK_USER=". $id_user. ($id_perm>0 ? "
        where ID_PERM=$id_perm" : ''). "
        group by FK_PERM"
      );
    if ($res['int_result'] && !$res['str_error'])
      $this->perm_push();
    elseif ($res['str_error']) die(ht(dump($res)));
  } // end ebiz_db::perm_user

} // class db (mysql)

define ('PERM_READ', 1);
define ('PERM_CREATE', 2);
define ('PERM_EDIT', 4);
define ('PERM_DEL', 8);
#define ('PERM_SHOW', 16);


$ar_query_log = array ();
$lastresult = false; // last executed query result assoc (see method submit)

if (!$assoc_blank) {
    $assoc_blank = array ();
}
if (!$lang_select) {
  $lang_select = array ();
}

$_SESSION['assoc_blank'] = $assoc_blank;
$_SESSION['lang_select'] = $lang_select;


  function sqlString($str)
 {
   $str = trim($str);
   if(get_magic_quotes_gpc())
     $str = stripslashes($str);
   return mysql_real_escape_string($str);
 } // slstring()
?>