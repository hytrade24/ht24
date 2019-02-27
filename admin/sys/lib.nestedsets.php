<?php
/* ###VERSIONSBLOCKINLCUDE### */



class nestedsets {

  /** @var ebiz_db $db */
  var $db = NULL, $s_table = NULL, $n_root = NULL;
  // int Value. Will be used if the class has to protect some data temporary
  var $n_swaproot = 99;
  // set true, to lock all editing functions.
  // donÂ´t set it to true, if you donÂ´t know how to handle locks!
  var $lockTable = false;
  // Will be set to true, if you request a lock and a lock by somebody else already exists.
  var $tableLock = false;
  // if lock is requested, this holds all data from the current lock.
  var $tableLockData = NULL;
  // Will be used by lock- System. Error will be saved here, if a table is locked.
  var $errMsg;
  // Language to use  (berni)
  var $showlang;
/*
  Nested Sets Class by B. Schmalenberger
  Current Version 1.0
  Changelog
   2005-05-18 :: Class create
   2005-05-19 :: New Function nestArray
   2005-05-20 :: Table locking
   2005-06-27 :: nodes may be objects; subcalls for insert and delete
                  if object, data must be in $node->ar_data
   2005-07-01 :: altered locking

  USAGE / README
   To use this class xou need a table with following fields
    ID_TABLE
  ROOT
  LFT
  RGT
   Be sure the ID is named like the table.
   ROOT, LFT and RGT should be integer field like BIGINT
   The ID field must be primary and set auto increment!
 */

  function nestedsets($s_table, $n_root, $b_lock=false, $db=NULL)
  { global $langval;
  /*
    constructor
    can be used to handle table locks.
      Params
    None
    NOTICE
      if youÂ´ve forgotten to define a query function, the script will die here.
    It will die too, if thereÂ´s no function or method with the name $this->queryFunv
  */

  	$this->showlang=$langval;
    $this->db = (is_null($db) ? $GLOBALS['db'] : $db);
    $this->s_table = $s_table;
    $this->n_swaproot = 128 + ($this->n_root = (int)$n_root);
    $this->lockTable = $b_lock;
    if ($b_lock)
    {
      $tmp = $this->db->lock_read($s_table. '.'. $n_root);
      if ($tmp)
        $this->tableLockData = $tmp;
      if ($this->db->lock($s_table. '.'. $n_root))
        $this->tableLock = ($tmp ? $tmp['FK_USER'] != $GLOBALS['uid'] : true);
    }
    return $this->tableLock;
  } // nestedSets

 ### Database Section
  function query($str)
  {
  /*
   Params:
    $str = String transmitted to Database ( SQL )
   NOTICE
    You have to define a function  to submit the query.
  The function has to return the MySQL Resource ID
  It could be helpfull, if the function treturns the insert ID after an insert
  Think about databse connection etc. Before using this class.
  The function should "die", if a query failed
  */
    $res = $this->db->querynow($str, false, true);
    //echo ht(dump($str));
    if (!empty($res['str_error']))
      die(ht(dump($res)));
    //print_r($GLOBALS['lastresult']);
    if ($res['str_command'] == "insert") {
        return $res['int_result'];
    }
    return $res['rsrc'];
  }

  function getNode($ID)
  {
   /*
    Function
   Selects all params of current node
  Params
   $this->s_table = The database table you use
   $ID = Id of the current node
   */
    $tab = strtoupper($this->s_table);
    $res = $this->query(
      $this->db->lang_select($this->s_table)
      . " where ID_".$tab." = ".$ID
    );
    return mysql_fetch_assoc($res);
  }

  function nestInsert($ID,$ar=array ())
  {
   /*
    Function
   The function will create a new child in your NS Tree
  Params
   $this->s_table = The table you use now
   $ID = Parent ID
        $ar = array or object includes parent data.
          if empty, data will be selected from database
          if object, method "insert" will be called after nest insert
    */
    if ($this->lockTable && $this->tableLock)
    {
      $this->errMsg = "Table-Lock-Error";
      return false;
    }
    $tab = strtoupper($this->s_table);
    if (empty($ar))
      $ar = $this->getNode( $ID);

    if(is_object($ar))
      $ar_data = &$ar->ar_data;
    else
      $ar_data = &$ar;

   //die(print_r($ar));
   $this->query("UPDATE ".$this->s_table."
        SET LFT =  LFT + 2
        WHERE ROOT  =  ".$this->n_root."
         AND LFT >  ".$ar_data['LFT']."
         AND RGT >= ".$ar_data['RGT']);
   $this->query("UPDATE ".$this->s_table."
        SET RGT =  RGT + 2
        WHERE ROOT = ".$this->n_root."
         AND RGT >= ".$ar_data['RGT']);

/*$ins = $this->query("update ".$this->s_table."
       set LFT=".$ar_data['RGT'].",
     RGT = ".($ar_data['RGT']+1).",
     ROOT=2
     where ID_NAV=".$_REQUEST['TOMOVE']); */
    if ($this->s_table == "kat") {
        $ins = $this->query("INSERT INTO ".$this->s_table."
          ( ROOT, LFT, RGT, PARENT )
          VALUES ( ".$this->n_root.", ".$ar_data['RGT'].", ".($ar_data['RGT']+1).", ".(int)$ID." )");
    } else {
        $ins = $this->query("INSERT INTO ".$this->s_table."
          ( ROOT, LFT, RGT )
          VALUES ( ".$this->n_root.", ".$ar_data['RGT'].", ".($ar_data['RGT']+1)." )");
    }

    if (is_numeric($ins))
    {
      if(is_object($ar) && is_subclass_of($ar, 'nest_object'))
        $ar->insert($ins);
      return $ins;
    }
   return true;
  } // <-- nestInsert()


    function nestInsertAfter($ID,$ar=array ())
    {
        /*
         Function
        The function will create a new child in your NS Tree
       Params
        $this->s_table = The table you use now
        $ID = Parent ID
             $ar = array or object includes parent data.
               if empty, data will be selected from database
               if object, method "insert" will be called after nest insert
         */
        if ($this->lockTable && $this->tableLock)
        {
            $this->errMsg = "Table-Lock-Error";
            return false;
        }
        $tab = strtoupper($this->s_table);
        if (empty($ar))
            $ar = $this->getNode( $ID);

        if(is_object($ar))
            $ar_data = &$ar->ar_data;
        else
            $ar_data = &$ar;

        //die(print_r($ar));
        # Prepare inserting the nodes
        $this->query("UPDATE ".$this->s_table." SET LFT=IF(LFT>".$ar_data['RGT'].",LFT+2,LFT), RGT=RGT+2 WHERE (RGT > ".$ar_data['RGT'].") AND ROOT=".$this->n_root);

        /*$ins = $this->query("update ".$this->s_table."
               set LFT=".$ar_data['RGT'].",
             RGT = ".($ar_data['RGT']+1).",
             ROOT=2
             where ID_NAV=".$_REQUEST['TOMOVE']); */
        $ins = $this->query("INSERT INTO ".$this->s_table."
      ( ROOT, LFT, RGT )
      VALUES ( ".$this->n_root.", ".($ar_data['RGT']+1).", ".($ar_data['RGT']+2)." )");

        if (is_numeric($ins))
        {
            if(is_object($ar) && is_subclass_of($ar, 'nest_object'))
                $ar->insert($ins);
            return $ins;
        }
        return true;
    } // <-- nestInsert()

  function nestQuery($s_where='', $s_joins='', $s_morefields='', $b_extended=false)
  {
    if ($b_extended)
	$s_morefields .= "
      round((t.RGT-t.LFT-1)/2,0) AS kidcount,
      ((min(u.RGT)-t.RGT-(t.LFT>1))/2) <= 0 AS is_last,
      (( (t.LFT-max(u.LFT)<=1) )) AS is_first,";
    return
      str_replace(' from ', ' from `'. $this->s_table. '` u, ',
        $this->db->lang_select($this->s_table, '*,'. $s_morefields. "count(*) as level"))
        . $s_joins. "
      WHERE t.ROOT = ". $this->n_root. " AND u.ROOT = ". $this->n_root. "
        AND t.LFT BETWEEN u.LFT AND u.RGT and t.LFT<>u.LFT ". $s_where. "
      GROUP BY t.LFT order by LFT";
  }

  function nestSelect($s_where='', $s_joins='', $s_morefields='', $b_extended=false)
  {

   /*
    Function
   The function selects the tree and returns the resource id.
  Params
   $n_start = Start LFT value. Deault is 0, to read the complete Tree
   $s_where = Additional where clause. Be careful on using :-)
   $s_joins = additional Joins
   $$s_morefields = additional Select to select other fields
   $b_extended: true -> weitere Informationen ermitteln, die beim Zeichnen des Baums hilfreich sind:
     - P_LFT und P_RGT: LFT/RGT des Parent-Knoten
     - kidcount: Anzahl der Nachkommen
     - is_last: Knoten hab keinen rechten (unteren) Nachbarn
     - is_first: Knoten hab keinen linken (oberen) Nachbarn
   */
#     max(zwei.LFT) as P_LFT, min(zwei.RGT) as P_RGT,
    return $this->query($this->nestQuery($s_where, $s_joins, $s_morefields, $b_extended));;
  } // <-- nestSelect()

  function nestMoveUp($id,$ar=array ())
  {
  /*
   Function
    The function moves nodes one position up.
  Child nodes will be moved too
   Params
    $this->s_table = Table you are using
  $id = id of node you want to move
  $ar = ar of node to be moved.
   if empty data will be selected from database.
  */
    if ($this->lockTable && $this->tableLock)
    {
      $this->errMsg = "Table-Lock-Error";
      return false;
    }
    $tab = strtoupper($this->s_table);
    if (empty($ar))
      $ar = $this->getNode($id);

    if(is_object($ar))
      $ar_data = &$ar->ar_data;
    else
      $ar_data = &$ar;

    $res=$this->query("select * from ".$this->s_table."
      where ROOT=". $this->n_root. "
        and ( RGT = ".($ar_data['LFT']-1)." )");
    $parent = mysql_fetch_assoc($res);

    if ($parent !== false) {
		$diff = (( $ar_data['RGT'] - ($ar_data['LFT']-$parent['LFT']))+1)-$parent['LFT'];

	    ### Updating children of the parent
	    $this->query("UPDATE ".$this->s_table."
	      SET ROOT = ".$this->n_swaproot.", LFT =  LFT + ".$diff.", RGT = RGT + ".$diff."
	      WHERE ROOT = ".$this->n_root." AND LFT > ".$parent['LFT']." AND RGT < ".$parent['RGT']);

	    ### updateing own children
	    $this->query("UPDATE ".$this->s_table."
	      SET LFT = LFT - ".($ar_data['LFT']-$parent['LFT']).", RGT = RGT - ".($ar_data['LFT']-$parent['LFT'])."
	      WHERE ROOT  =  ".$this->n_root." AND LFT > ".$ar_data['LFT']." AND RGT < ".$ar_data['RGT']);

	    ### updating current node
	    $this->query("UPDATE ".$this->s_table."
	      SET LFT =  ".$parent['LFT'].", RGT = RGT - ".($ar['LFT']-$parent['LFT'])."
	      WHERE ID_".$tab." = ".$ar_data['ID_'.$tab]);

	    ### updating parent
	    $rechts_neu = ( $ar_data['RGT'] - ($ar_data['LFT']-$parent['LFT']) ) +1;
	    $this->query("UPDATE ".$this->s_table."
	      SET LFT = ".$rechts_neu." , RGT = ".$ar_data['RGT']."
	      WHERE ID_".$tab." =  ".$parent['ID_'.$tab]);

	    ### Unmask protected data
	    $this->query("update ".$this->s_table."
	      set ROOT = ".$this->n_root."
	      where ROOT = ".$this->n_swaproot);
    } else {
    	// Ist letztes Kind-Element! Kein verschieben möglich!!
    	die("Element kann nicht weiter nach oben verschoben werden!");
    }
  } // <-- moveUp()

    function nestMoveAfter($id, $idTarget, $ar=array())
    {
        /*
         Function
          The function moves nodes into another node as a child.
        Child nodes will be moved too
         Params
          $this->s_table = Table you are using
        $id = id of node you want to move
        $ar = ar of node to be moved.
         if empty data will be selected from database.
        */
        if ($this->lockTable && $this->tableLock)
        {
            $this->errMsg = "Table-Lock-Error";
            return false;
        }
        $tab = strtoupper($this->s_table);
        if (empty($ar))
            $ar = $this->getNode($id);

        if(is_object($ar))
            $ar_data = &$ar->ar_data;
        else
            $ar_data = &$ar;

        $res = $this->query("SELECT * FROM ".$this->s_table." WHERE ID_".$tab."=".$idTarget);
        $parent = mysql_fetch_assoc($res);

        if ($parent !== false) {
            $diff = $ar_data['RGT'] - $ar_data['LFT'] + 1;
            # Prepare inserting the nodes
            $this->query("UPDATE ".$this->s_table." SET LFT=IF(LFT>".$parent['RGT'].",LFT+".$diff.",LFT), RGT=RGT+".$diff." WHERE (RGT > ".$parent['RGT'].") AND ROOT=".$this->n_root);
            if ($ar_data['LFT'] > $parent['RGT']) {
				$ar_data['LFT'] += $diff;
				$ar_data['RGT'] += $diff;
			}
            # Move node and childs
            $delta = $parent['RGT'] - $ar_data['LFT'] + 1;
            $this->query("UPDATE ".$this->s_table." SET LFT=LFT+".$delta.", RGT=RGT+".$delta." WHERE (LFT BETWEEN ".$ar_data['LFT']." AND ".$ar_data['RGT'].") AND ROOT=".$this->n_root);
            # Remove node(s) from old position
            $this->query("UPDATE ".$this->s_table." SET LFT=IF(LFT>".$ar_data['RGT'].",LFT-".$diff.",LFT), RGT=RGT-".$diff." WHERE (RGT > ".$ar_data['RGT'].") AND ROOT=".$this->n_root);
        } else {
            // Ist letztes Kind-Element! Kein verschieben möglich!!
            die("Ziel nicht gefunden!");
        }
    } // <-- moveInto()

    function nestMoveInto($id, $idTarget, $ar=array())
    {
        /*
         Function
          The function moves nodes into another node as a child.
        Child nodes will be moved too
         Params
          $this->s_table = Table you are using
        $id = id of node you want to move
        $ar = ar of node to be moved.
         if empty data will be selected from database.
        */
        if ($this->lockTable && $this->tableLock)
        {
            $this->errMsg = "Table-Lock-Error";
            return false;
        }
        $tab = strtoupper($this->s_table);
        if (empty($ar))
            $ar = $this->getNode($id);

        if(is_object($ar))
            $ar_data = &$ar->ar_data;
        else
            $ar_data = &$ar;

        $res = $this->query("SELECT * FROM ".$this->s_table." WHERE ID_".$tab."=".$idTarget);
        $parent = mysql_fetch_assoc($res);

        if ($parent !== false) {
            $diff = $ar_data['RGT'] - $ar_data['LFT'] + 1;
            # Prepare inserting the nodes
            $this->query("UPDATE ".$this->s_table." SET LFT=IF(LFT>".$parent['RGT'].",LFT+".$diff.",LFT), RGT=RGT+".$diff." WHERE (RGT >= ".$parent['RGT'].") AND ROOT=".$this->n_root);
            if ($ar_data['LFT'] > $parent['RGT']) {
				$ar_data['LFT'] += $diff;
				$ar_data['RGT'] += $diff;
			}
            # Move node and childs
            $delta = $parent['RGT'] - $ar_data['LFT'];
            $this->query("UPDATE ".$this->s_table." SET LFT=LFT+".$delta.", RGT=RGT+".$delta." WHERE (LFT BETWEEN ".$ar_data['LFT']." AND ".$ar_data['RGT'].") AND ROOT=".$this->n_root);
            # Remove node(s) from old position
            $this->query("UPDATE ".$this->s_table." SET LFT=IF(LFT>".$ar_data['RGT'].",LFT-".$diff.",LFT), RGT=RGT-".$diff." WHERE (RGT > ".$ar_data['RGT'].") AND ROOT=".$this->n_root);
        } else {
            // Ist letztes Kind-Element! Kein verschieben möglich!!
            die("Ziel nicht gefunden!");
        }
    } // <-- moveInto()

  function nestMoveLeft($id,$ar=array ())
  {
    /*
     Function
      Moves a node and itÂ´s children one position left
     Params
      $this->s_table = Table you are using
    $id = id of node you want to move
    $ar = ar of node to be moved.
     if empty data will be selected from database
    */
    if ($this->lockTable && $this->tableLock)
    {
      $this->errMsg = "Table-Lock-Error";
      return false;
    }
    $tab = strtoupper($this->s_table);
    if (empty($ar))
      $ar = $this->getNode( $id);

    if(is_object($ar))
      $ar_data = &$ar->ar_data;
    else
      $ar_data = &$ar;

    // Count own childres
    $children = (int)(($ar_data['RGT']-1)-$ar_data['LFT'])/2;
    $res = $this->query("select * from ".$this->s_table."
      where ROOT=". $this->n_root. " and LFT < ".$ar_data['LFT']." and RGT > ".$ar_data['RGT']. "
      group by ID_".$tab."
      order by LFT desc
      limit 1");
    $parent = mysql_fetch_assoc($res);
#echo '<b>node</b>', ht(dump($ar)), '<br />';echo '<b>kids</b> = ', $children, '<br />';echo '<b>parent</b>', ht(dump($parent)), '<br />';die();

    if ($parent['LFT'] > 1) {
	    // Mask own children
	    $this->query("update ".$this->s_table."
	      SET ROOT = ".$this->n_swaproot."
	      where ROOT=". $this->n_root. " and LFT > ".$ar_data['LFT']." and RGT < ".$ar_data['RGT']);

	    // ParentÂ´s children
	    $this->query("update ".$this->s_table."
	      SET LFT = LFT-".(($children+1)*2).", RGT = RGT-".(($children+1)*2)."
	      where ROOT=". $this->n_root. " and LFT > ".$ar_data['LFT']." and RGT < ".$parent['RGT']);

	    // parent
	    $this->query("update ".$this->s_table."
	      set RGT = RGT-".(($children+1)*2)."
	      where ID_".$tab." = ".$parent['ID_'.$tab]);

	    // THIS node
        if ($this->s_table == "kat") {
            $this->query("update ".$this->s_table."
              SET 
                LFT=".($parent['RGT']-(($children+1)*2)+1).", 
                RGT=".($parent['RGT']-(($children+1)*2)+($children*2)+2).",
                PARENT=".$parent["PARENT"]."
              where ID_".$tab." = ".$ar_data['ID_'.$tab]);
        } else {
            $this->query("update ".$this->s_table."
              SET LFT=".($parent['RGT']-(($children+1)*2)+1).", RGT=".($parent['RGT']-(($children+1)*2)+($children*2)+2)."
              where ID_".$tab." = ".$ar_data['ID_'.$tab]);
        }

	    // This children
	    $diff = (int)($parent['RGT']-(($children+1)*2)+1)-$ar_data['LFT'];
	    $this->query("update ".$this->s_table."
	      set ROOT = ".$this->n_root.", LFT = LFT + ".$diff.", RGT = RGT + ".$diff."
	      where ROOT=". $this->n_swaproot. " and LFT > ".$ar_data['LFT']." and RGT < ".$ar_data['RGT']);
		#die(listtab($GLOBALS['ar_query_log']));
    } else {
    	// Ist letztes Kind-Element! Kein verschieben möglich!!
    	die("Element kann nicht weiter nach links verschoben werden!");
    }
  } // <-- nestMoveLeft()

  function nestMoveRight($id,$ar=array ())
  {
    /*
     Function
    Moves a node and itÂ´s children one position right
     Params
      $this->s_table = Table you are using
    $id = id of node you want to move
    $ar = ar of node to be moved.
     if empty data will be selected from database
    */
    if ($this->lockTable && $this->tableLock)
    {
      $this->errMsg = "Table-Lock-Error";
      return false;
    }
    $tab = strtoupper($this->s_table);

    if (empty($ar))
      $ar = $this->getNode( $id);

    if(is_object($ar))
      $ar_data = &$ar->ar_data;
    else
      $ar_data = &$ar;

    // Own Children
    $children = (int)(($ar_data['RGT']-1)-$ar_data['LFT'])/2;

    $res = $this->query("select * from ".$this->s_table."
      where ROOT=". $this->n_root. " and RGT = ".($ar_data['LFT']-1));

    $parent = mysql_fetch_assoc($res);

    if ($parent !== false) {
	    $parent_children = (($parent['RGT']-1)-$parent['LFT'])/2;

	    $diff = ($ar_data['LFT']-($parent['LFT']+1));

	    $parent_rgt = $parent['RGT']+(($children+1)*2);
	    $self_lft   = ($parent['LFT']+($parent_children*2)+1);
	    $self_rgt   = $self_lft+($children*2)+1;

	    // own children
	    $this->query("update ".$this->s_table."
	      set LFT = LFT-1, RGT = RGT-1
	      where ROOT=". $this->n_root. " and LFT > ".$ar_data['LFT']." and RGT < ".$ar_data['RGT']);

	    // Parent
	    $this->query("update ".$this->s_table."
	      set RGT = ".$parent_rgt."
	      where ID_".$tab." = ".$parent['ID_'.$tab]);

	    // this node
	    if ($this->s_table == "kat") {
            $this->query("update ".$this->s_table."
              set 
                LFT = ".$self_lft.", 
                RGT = ".$self_rgt.",
                PARENT = ".$parent['ID_'.$tab]."
              where ID_".$tab." = ".$ar_data['ID_'.$tab]);
        } else {
            $this->query("update ".$this->s_table."
              set LFT = ".$self_lft.", RGT = ".$self_rgt."
              where ID_".$tab." = ".$ar_data['ID_'.$tab]);
        }
    } else {
    	// Ist letztes Kind-Element! Kein verschieben möglich!!
    	die("Element kann nicht weiter nach rechts verschoben werden!");
    }
  } // <-- moveIn()

  function nestMoveDown( $id, $ar = array ())
  {
    /*
     Function
    Moves a node and itÂ´s children one position down
     Params
      $this->s_table = Table you are using
    $id = id of node you want to move
    $ar = ar of node to be moved.
     if empty data will be selected from database
    */
    if ($this->lockTable && $this->tableLock)
    {
      $this->errMsg = "Table-Lock-Error";
      return false;
    }

    $tab = strtoupper($this->s_table);
    if (empty($ar))
      $ar = $this->getNode( $id);

    if(is_object($ar))
      $ar_data = &$ar->ar_data;
    else
      $ar_data = &$ar;

    $res=$this->query("select * from ".$this->s_table." where ROOT=". $this->n_root. " and LFT=".($ar_data['RGT']+1));
    $parent = mysql_fetch_assoc($res);

    if ($parent !== false) {
    	$this->nestMoveUp( $parent['ID_'.$tab],$parent);
    } else {
    	// Ist letztes Kind-Element! Kein verschieben möglich!!
    	die("Element kann nicht weiter nach unten verschoben werden!");
    }
  } // <-- moveDown()

  // delete
  function nestDel( $id, $tree=0, $ar=array ())
  {
    /*
     Function
      Deletes a node with or without children
     Params
      $this->s_table = Table you are using
    $id = id of node you want to move
    $tree = flag ( 1 or 0 )
     set 1 to delete children too.
     Set 0 to delete only this node, and move children
        $ar = array or object of node to be moved.
          if empty, data will be selected from database
          if object, method "delete" will be called after nestDel
    */
    if ($this->lockTable && $this->tableLock)
    {
      $this->errMsg = "Table-Lock-Error";
      return false;
    }
    $tab = strtoupper($this->s_table);
    if (empty($ar))
      $ar = $this->getNode( $id);

    if(is_object($ar))
      $ar_data = &$ar->ar_data;
    else
      $ar_data = &$ar;

    if ($tree == 1)
    {
      $delnodes = ($ar_data['RGT']-($ar_data['LFT']-1))/2;
      $this->query("delete from ".$this->s_table."
        where ROOT=". $this->n_root. " and LFT >= ".$ar_data['LFT']." and RGT <= ".$ar_data['RGT']);
    }
    else
    {
      $delnodes = 1;
      $this->query("delete from ".$this->s_table." where ID_".$tab." = ".$ar_data['ID_'.$tab]);
      $this->query("update ".$this->s_table."
        set LFT = LFT -1, RGT = RGT -1
        where ROOT=". $this->n_root. " and LFT > ".$ar_data['LFT']." and RGT < ".$ar_data['RGT']);
    }

    // Nodes after this position
    $this->query("update ".$this->s_table."
      set LFT = LFT -".($delnodes*2).", RGT = RGT -".($delnodes*2)."
      where ROOT=". $this->n_root. " and RGT > ".$ar_data['RGT']." and LFT > ".$ar_data['LFT']);

    // nodes before this pos.
    $this->query("update ".$this->s_table." set
         RGT = RGT - ".($delnodes*2)."
       where ROOT=". $this->n_root. " and RGT > ".$ar_data['RGT']." and LFT < ".$ar_data['LFT']);
   } // <-- nestDel

   ### Print Functions

  function nestArray($res)
  {
    /*
     Function
      Returns an array with iformation about available
    Move and delete functions.
     Params
      $res = Resource ID to a nested sets select
    */
    $ar = $tree = array (); $i=0;
    while ($row = mysql_fetch_assoc($res))
    {
      $i++;
      if ($row['RGT'] -1 != $row['LFT'])
        $tree[$row['level']] = $row['RGT'];
      if ($i%2)
        $row['even'] = 1;
      else
        $row['even'] = 0;
      if ($row['level'] > 1)
      {
        if ($row['LFT'] > 2 && ($row['LFT']-1) != $last['LFT'])
          $row['UP']=1;
        if ($row['RGT']+1 != $tree[$row['level']-1])
          $row['DOWN']=1;
        if ($row['level'] > 2)
          $row['LEFT']=1;
        if ($last['level'] != 1 &&  $row['LFT']-1 != $last['LFT'])
          $row['RIGHT']=1;
        if ($row['level'] > 1)
        {
          if ($row['LFT']+1 < $row['RGT'])
            $row['DELTREE']=1;
          $row['DEL']=1;
        }
      } // nicht die root
      $ar[] = $last = $row;
    }

    return $ar;
  } // nestArray()

  function validate()
  {
    $s_sqladd = ' from '. $this->s_table . ' where ROOT='. $this->n_root. ' ';
    // min(LFT)==1, max(LFT)==count(*)*2
    $tmp = $this->db->fetch1('select
      count(*) as ANZ, max(RGT) R_MAX, min(LFT) L_MIN'. $s_sqladd);
    if ($tmp['ANZ']*2!=$tmp['R_MAX'])
    {
      $this->errMsg = 'count='. $tmp['ANZ']. ', max(RGT)='. $tmp['R_MAX'];
      return false;
    }
    if (1!=$tmp['L_MIN'])
    {
      $this->errMsg = 'min(LFT)='. $tmp['L_MIN'];
      return false;
    }
    // LFT eindeutig
    $tmp = $this->query('select LFT, count(*) ANZ'. $s_sqladd. 'group by LFT having ANZ>1');
    if ($n = mysql_num_rows($tmp))
    {
      $this->errMsg = $n. 'x multiple LFT';
      return false;
    }
    // RGT eindeutig
    $tmp = $this->query('select RGT, count(*) ANZ'. $s_sqladd. 'group by RGT having ANZ>1');
    if ($n = mysql_num_rows($tmp))
    {
      $this->errMsg = $n. 'x multiple RGT';
      return false;
    }
    // RGT-LFT ungerade
    $tmp = $this->query('select (RGT-LFT)&1 ODD'. $s_sqladd.' having ODD=0');
    if ($n = mysql_num_rows($tmp))
    {
      $this->errMsg = $n. 'x RGT-LFT%2=0';
      return false;
    }
    // RGT>LFT
    $tmp = $this->query('select LFT'. $s_sqladd. ' and RGT<=LFT');
    if ($n = mysql_num_rows($tmp))
    {
      $this->errMsg = $n. 'x RGT<=LFT';
      return false;
    }

    $s_sqladd = ' from '. $this->s_table . ' a, '. $this->s_table . ' b
      where a.ROOT='. $this->n_root. ' and b.ROOT='. $this->n_root. ' and a.LFT<>b.LFT';
    // LFT/RGT eindeutig
    $tmp = $this->query('select a.LFT, b.RGT '. $s_sqladd. ' and a.LFT=b.RGT');
    if ($n = mysql_num_rows($tmp))
    {
      $this->errMsg = $n. 'x a.LFT=b.RGT';
      return false;
    }

    return true;
  }

}  // <-- end of class

function pathicon($path)
{
  return '
  <td width="19" height="17"><img src="gfx/path.'. $path. '.png" width="19" height="17"></td>';
}

function tree_show_nested($data, $s_tplpath, $s_callbackfn=NULL, $s_actions=true, $id_marker=0, $s_pkey='ID_NAV', $add_fields=array())
{

  $n_hide = 0;

  $ar_res = array ();
  $nar_leveldone = array (false);
  if (($n = (int)$data[0]['level'])>1)
    for ($x=1; $x<$n; $x++)
      $nar_leveldone[$x] = true;

  $maxlevel = 0;
  foreach($data as $i=>$row)
  {
    $row['actions'] = $s_actions;
    $row['visible'] =
      (int)$row['B_VIS']
      &&
      ($row['level']==1 || $n_hide<$row['RGT']
    );
    if (!$row['B_VIS'] && $n_hide<$row['RGT'])
      $n_hide = $row['RGT'];
    if ($row['level']>$maxlevel)
      $maxlevel = $row['level'];
    if ($s_callbackfn) $s_callbackfn($row);

    $ar_path = array ();
    for ($x=1; $x<$row['level']; $x++)
      $ar_path[] = pathicon(($nar_leveldone[$x] ? 0 : 3));
    $ar_path[] = pathicon(($row['haskids'] ? 256 : 0)+(!$row['is_last'] ? 1+2+8 : 1+8));
    $ar_path[] = pathicon(($row['kidcount'] ? 2+4+16 : 4+16));
    $nar_leveldone[$row['level']] = $row['is_last'];

    $tpl_tmp = new Template($s_tplpath);
    $tpl_tmp->addvar('marker', $b_marker = $id_marker && $row[$s_pkey]==$id_marker);
    $tpl_tmp->addvar('even', ($b_marker ? 'x' : 1-($i&1)));
    #$tpl_tmp->addvar('even', 1-($i&1));
    $tpl_tmp->addvar('path', $ar_path);
    $tpl_tmp->addvars($row);
  	if(!empty($add_fields)) {
    	$felder=array();
    	foreach($add_fields as $id_filter => $ident) {
    		$felder[] = '<td style="padding-left: 12px;" title="'.$ident.' ID">'.$row['FK_'.$ident].'</td>';
    	}
    	$tpl_tmp->addvar("add_columns", implode("\n", $felder));
    } else {
    	$tpl_tmp->addvar('add_columns', '');
    }
    $ar_res[] = $tpl_tmp;
  }

  for ($i=0; $i<count($ar_res); $i++)
    $ar_res[$i]->addvar('maxlevel', $maxlevel);

  return $ar_res;
}

function root($s_table, $default=1)
// Root aus Session, $default wenn nicht vorhanden)
{
  $s_field = 'ROOT_'. strtoupper($s_table);
  if ($root = $_REQUEST['ROOT'])
    $_SESSION[$s_field] = $root;
  elseif ($root = $_SESSION[$s_field])
    $_REQUEST['ROOT'] = $root;
  else
    $root = $_REQUEST['ROOT'] =$_SESSION[$s_field] = ($s_table == 'kat' ? 2 : $default);
  return $root;
}

?>
