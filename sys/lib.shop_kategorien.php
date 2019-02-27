<?php
/* ###VERSIONSBLOCKINLCUDE### */


  /**
   * Class for managing categories in a tree-view.
   *
   * <ul>
   * <li><b>Access management / User locks</b>
   *  <ul>
   *   <li>{@link tree_lock()}</li>
   *   <li>{@link tree_unlock()}</li>
   *   <li>{@link tree_lock_valid()}</li>
   *  </ul>
   * </li>
   * <li><b>Basic tree functions</b>
   *  <ul>
   *   <li>{@link tree_create_cache()}</li>
   *   <li>{@link tree_get()}</li>
   *   <li>{@link tree_get_parent()}</li>
   *  </ul>
   * </li>
   * <li><b>Nested-set functions</b>
   *  <ul>
   *   <li>{@link tree_check_nestedset()}</li>
   *   <li>{@link tree_create_nestedset()}</li>
   *  </ul>
   * </li>
   * <li><b>Element functions</b>
   *  <ul>
   *   <li>{@link element_create()}</li>
   *   <li>{@link element_read()}</li>
   *   <li>{@link element_has_childs()}</li>
   *   <li>{@link element_is_child()}</li>
   *   <li>{@link element_get_targets()}</li>
   *   <li>{@link element_get_childs()}</li>
   *   <li>{@link element_get_childs_cached()}</li>
   *   <li>{@link element_move()}</li>
   *   <li>{@link element_update()}</li>
   *   <li>{@link element_delete()}</li>
   *  </ul>
   * </li>
   * <li><b>Backups</b>
   *  <ul>
   *   <li>{@link tree_backup_create()}</li>
   *   <li>{@link tree_backup_restore()}</li>
   *   <li>{@link tree_backup_preview()}</li>
   *   <li>{@link tree_backup_delete()}</li>
   *   <li>{@link tree_backup_get_tree()}</li>
   *   <li>{@link tree_backup_get_fields()}</li>
   *   <li>{@link tree_backup_get_desc()}</li>
   *   <li>{@link tree_backup_list()}</li>
   *  </ul>
   * </li>
   * <li><b>Caching</b>
   *  <ul>
   *   <li>{@link tree_cache_elements()}</li>
   *   <li>{@link tree_create_cache()}</li>
   *  </ul>
   * </li>
   * <li><b>Undo functions</b>
   *  <ul>
   *   <li>{@link undo_apply_step()}</li>
   *   <li>{@link undo_clear_all_actions()}</li>
   *   <li>{@link undo_get_step()}</li>
   *   <li>{@link undo_get_steps()}</li>
   *   <li>{@link undo_preview_step()}</li>
   *  </ul>
   * </li>
   * </ul>
   *
   * @package Categories
   * @subpackage Admin
   */
  class TreeCategories {
    /**
     * Most recent error message or false if no error occured.
     *
     * @var string|bool
     */
    public $error = false;

    /**
     *Shall the page be reloaded?
     *
     * @var bool
     */
    public $reload = false;

    /**
     * The ID of the last updated or inserted element.
     *
     * @var int
     */
    public $updateid;

    /**
     * The ID of the last backup inserted within this instance.
     *
     * @see tree_backup_create()
     *
     * @var int
     */
    public $backupid;

    /**
     * ID of the user that currently has exclusive access to the tree.
     *
     * @var int
     */
    public $lock_user;

    /**
     * A time() value of when the current user initiated the lock.
     *
     * @var int
     */
    private $lock_stamp = 0;

    /**
     * A time() value of when the user's lock expires.
     *
     * @var int
     */
    public $lock_expire = 0;

    /**
     * The mysql table name where the tree is saved.
     *
     * @var string
     */
    private $table;

    /**
     * ID of the most top entry
     *
     * @var int
     */
    private $id_root;

    /**
     * Array that holds all nodes that have been queried (and not changed afterwards) in the current instance.
     *
     * @var array
     */
    private $cache_nodes;

    /**
     * The id of the root to display.
     *
     * @var int
     */
    private $root;


    /**
     * Initialize a new class
     *
     * @return object TreeCategories  New instance of this class
     */
    function __construct($table, $root) {
      global $db, $uid;

      $this->ar_tree = array();
      // Tabelle und Root-Knoten merken
      $this->table = $table;
      $this->root = $root;
      // Cache für Elemente initialisieren
      $this->cache_nodes = array();
      // Sperrung überprüfen
      $lock = $db->fetch1("SELECT FK_USER, STAMP_UPDATE, STAMP_EXPIRE FROM `lock` WHERE IDENT='".$this->table.$this->root."'");
      if (!$lock) $this->lock_user = false;
      else if ((strtotime($lock["STAMP_EXPIRE"]) < time()) && ($lock["FK_USER"] != $uid)) {
        // Ausgelaufene Sperren entfernen
        $this->lock_user = false;
        $db->querynow("DELETE FROM `lock` WHERE IDENT='".$this->table.$this->root."' AND FK_USER=".$lock["FK_USER"]);
      } else {
        $this->lock_user = $lock["FK_USER"];
        $this->lock_stamp = strtotime($lock["STAMP_UPDATE"]);
        $this->lock_expire = strtotime($lock["STAMP_EXPIRE"]);
      }
    }

    /**
     * Apply default category settings if undefined
     */
    private function applyDefaultKatOptions(&$options) {
        // Apply default options
        if (!is_array($options)) $options = array();
        if (!$options['SALES']) $options['SALES'] = array(0, 1, 2);
        return $options;
    }

    /**
     * Checks if the tree is locked by the active user. (if he is allowed to apply changes)
     *
     * @return bool     True if lock is valid, false otherwise
     */
    public function tree_lock_valid() {
      global $db, $uid;
      if ($this->lock_user == false) {
        // Nicht gesperrt
        $this->error = "ERR_NOT_LOCKED";
        $this->reload = true;
        return false;
      }
      if ($this->lock_user != $uid) {
        // Gesperrt von einem anderen benutzer
        $this->error = "ERR_ALREADY_LOCKED";
        return false;
      }
      if ($this->lock_user == $uid) {
        if ($this->lock_expire < time()) {
          // Timeout (5 Min ohne Aktion)
          $this->error = "ERR_LOCK_TIMEOUT";
          $this->reload = true;
          return false;
        }

        return true;
      }
      // Unbekannter Fehler!
      $this->error = "ERR_UNKNOWN";
      return false;
    }

    /**
     * Locks the tree for all other users. (gain exclusive access)
     *
     * @return bool     True if successfull, false otherwise.
     */
    public function tree_lock() {
      global $db, $uid;

      if (($this->lock_user != false) && ($this->lock_user != $uid)) {
        // Baum ist bereits von einem anderen Benutzer gesperrt
        $lock_stamp = false;
        $this->error = "ERR_ALREADY_LOCKED";
        return false;
      } else if (($this->lock_user != false) && ($this->lock_user == $uid)) {
        // Eigene Sperre bereits aktiv
        $this->lock_stamp = time();
        $this->lock_expire = time() + 300;
        $lastresult = $db->querynow("UPDATE `lock` SET STAMP_UPDATE='".date('Y-m-d H:i:s', $this->lock_stamp)."',
                                         STAMP_EXPIRE='".date('Y-m-d H:i:s', $this->lock_expire)."'
                         WHERE FK_USER=".$uid." AND IDENT='".$this->table.$this->root."'");
        if (!$lastresult['str_error']) {
          $this->lock_user = $uid;
          return true;
        } else {
          $this->error = "ERR_LOCK_FAILED";
          $this->lock_stamp = 0;
          $this->lock_expire = 0;
          $this->lock_user = false;
          return false;
        }
      } else {
        // Baum nicht gesperrt, Sperre hinzu
        $this->lock_stamp = time();
        $this->lock_expire = time() + 300;
        $lastresult = $db->querynow("INSERT INTO `lock` (FK_USER, IDENT, STAMP_UPDATE, STAMP_EXPIRE) VALUES
              (".$uid.",'".$this->table.$this->root."', '".date('Y-m-d H:i:s', $this->lock_stamp)."', '".
              date('Y-m-d H:i:s', $this->lock_expire)."')");
        if (!$lastresult['str_error']) {
          $this->lock_user = $uid;
          return true;
        } else {
          $this->error = "ERR_LOCK_FAILED";
          $this->lock_user = false;
          return false;
        }
      }
    }

    /**
     * Unlocks the tree so that other users can lock and edit it.
     *
     * @return bool  True if successfull, false otherwise.
     */
    public function tree_unlock() {
      global $db, $uid;

      if (($this->lock_user != false) && ($this->lock_user != $uid)) {
        // Ein anderer User hat den Baum gesperrt
        $this->error = "ERR_ALREADY_LOCKED";
        $this->reload = true;
        return false;
      } else if ($this->lock_user == $uid) {
        // Eigene Sperre, entfernen
        $this->lock_user = false;
        $db->querynow("DELETE FROM `lock` WHERE FK_USER=".$uid." AND IDENT='".$this->table.$this->root."'");
        return true;
      }
      // Baum ist nicht gesperrt
      return true;
    }

    /**
     * Stores the current tree in a new backup
     *
     * @param   string    $description  (optional) Description for the backup's reason/state.
     * @param   string    $username     (optional) Name of the User that created the Backup. Leave blank for 'Automatic'.
     *
     * @return  bool      True on success, false otherwise.
     */
    public function tree_backup_create($description = "", $username = "Automatic") {
      global $db, $langval;

      $prev_langval = $langval;

      // Kompletten baum in den cache laden
      $this->tree_cache_elements();

      $ar_backup = $this->cache_nodes;
      // Texte verschieben
      foreach ($ar_backup as $id_node => $data) {
        $langval = 128;
        $text = array();
        $text["T1"] = $data["T1"];
        $text["V1"] = $data["V1"];
        $text["V2"] = $data["V2"];

        $ar_backup[$id_node]["backup_text"] = array();
        $ar_backup[$id_node]["backup_text"][$langval] = $text;

        while ($langval > 1) {
          if ($langval & $data["BF_LANG_KAT"]) {
            $data_lang = $this->element_read($id_node, $langval);

            $text_lang = array();
            $text_lang["T1"] = $data_lang["T1"];
            $text_lang["V1"] = $data_lang["V1"];
            $text_lang["V2"] = $data_lang["V2"];

            $ar_backup[$id_node]["backup_text"][$langval] = $text_lang;
          }
          $langval /= 2;
        }

        unset($ar_backup[$id_node]["T1"]);
        unset($ar_backup[$id_node]["V1"]);
        unset($ar_backup[$id_node]["V2"]);
      }

      $langval = $prev_langval;

      $backup = array();
      $backup["DATA"] = serialize($ar_backup);
      $backup["DATA_FIELDS"] = serialize($db->fetch_table("SELECT * FROM `".$this->table."2field`"));
      $backup["DESCRIPTION"] = $description;
      $backup["CREATED_BY"] = $username;

      if ($this->backupid = $db->update($this->table."_restore", $backup)) {
        return true;
      }
      return false;
    }

    /**
     * Restores a recently saved backup
     *
     * @param   int   $id               Backup-ID to be restored
     * @param   bool  $backup_current   True if the current tree should be backed-up before restoring.
     *
     * @return  bool        True on success, false otherwise.
     */
    public function tree_backup_restore($id, $backup_current = true) {
      global $db, $langval;

      if ($backup_current) {
        // Vorhandenen baum sichern
        if (!$this->tree_backup_create("[RESTORE] Backup of previous tree.")) {
          return false;
        }
      }

      // Multilinguale Strings zum Baum
      $db->querynow("DELETE FROM `string_".$this->table."`
        WHERE S_TABLE='".$this->table."' AND FK IN
          (SELECT ID_KAT FROM `".$this->table."` WHERE ROOT=".$this->root.")");
      // Kompletten Baum löschen
      $db->querynow("DELETE FROM `".$this->table."2field` WHERE FK_KAT IN
          (SELECT ID_KAT FROM `".$this->table."` WHERE ROOT=".$this->root.")");
      $db->querynow("DELETE FROM `".$this->table."` WHERE ROOT=".$this->root);

      // Backup einspielen
      $backup = $this->tree_backup_get_tree($id);
      $backup_fields = $this->tree_backup_get_fields($id);

      foreach ($backup as $id_node => $data) {
        $node_text = $data["backup_text"];
        unset($data["backup_text"]);
        foreach($data as $key => $value) {
          if (empty($value) && ((string)$value !== "0"))
            unset($data[$key]);
          else {
            if (is_string($value))
              $data[$key] = "'".mysql_escape_string($value)."'";
            else
              $data[$key] = "'".$value."'";
          }
        }
        $insert_keys = array_keys($data);
        $insert_values = array_values($data);
        $query = "INSERT INTO `".$this->table."` (".implode(",", $insert_keys).") ".
          "VALUES (".implode(",", $insert_values).");";
        $db->querynow($query);
        $prev_langval = $langval;
        foreach($node_text as $lang => $data_text) {
          $langval = $lang;
          $data_text["ID_KAT"] = $id_node;
          $db->update($this->table, $data_text);
        }
        $langval = $prev_langval;
      }
      foreach ($backup_fields as $index => $data) {
        $db->update($this->table."2field", $data);
      }
      $this->undo_clear_all_actions();
      if ($backup_current)
        $this->undo_add_action("RESTORE", $this->backupid, $id, 0);
      return true;
    }

    /**
     * Generates a Nested-Set from the given backup for previewing.
     *
     * @param   int   $id   ID of the backup to preview.
     *
     * @return  array       Array with the Nested-Set of the backup.
     */
    public function tree_backup_preview($id) {
      global $db, $langval;
      $backup = $this->tree_backup_get_tree($id);

      foreach ($backup as $id_node => $data) {
        $backup[$id_node] = array_merge($backup[$id_node], $data["backup_text"][$langval]);
        unset($backup[$id_node]["backup_text"]);
        if ($data["PARENT"] == 0) {
          $this->id_root = $id_node;
        }
      }

      $this->cache_nodes = $backup;

      $root = $this->tree_get_parent();
      $title = strtoupper($backup[$root]["V1"]);

      $nestedset_ar = array();
      $nestedset_ar[] = array(
      		"id" 		=> $root,
      		"title"		=> strtoupper($backup[$root]["V1"]),
      		"childs"	=> $this->node_create_nestedset($root)
      );
      $ar_tree = array();
      $this->tree_create_nestedset_from_array($nestedset_ar, $ar_tree);
      return $ar_tree;
    }

    /**
     * Delete a backup by ID
     *
     * @param   int   $id   ID of the Backup
     *
     * @return  bool      True on success, false otherwise.
     */
    public function tree_backup_delete($id) {
      global $user, $db;
      if ($id > 0) {
        if ($db->querynow("DELETE FROM `".$this->table."_restore` WHERE ID_BACKUP=".(int)$id.
              " AND (CREATED_BY='".mysql_escape_string($user["NAME"])."' OR CREATED_BY='Automatic')"))
          return true;
      }
      return false;
    }

    /**
     * Return the specified backup
     *
     * @param   int   $id   ID of the Backup
     * @return  array       An Array with the database-entries of the backup.
     */
    public function tree_backup_get_tree($id) {
      global $db;

      $backup = $db->fetch_atom("SELECT DATA FROM `".$this->table."_restore` WHERE ID_BACKUP=".(int)$id);

      return unserialize($backup);
    }

    /**
     * Return the specified backup
     *
     * @param   int   $id   ID of the Backup
     * @return  array       An Array with the database-entries of the backup.
     */
    public function tree_backup_get_fields($id) {
      global $db;

      $backup = $db->fetch1("SELECT * FROM `".$this->table."_restore` WHERE ID_BACKUP=".(int)$id);

      return unserialize($backup["DATA_FIELDS"]);
    }

    /**
     * Return the specified backup's description
     *
     * @param   int   $id   ID of the backup
     * @return  string      Description of the backup
     */
    public function tree_backup_get_desc($id) {
      global $db;

      return $db->fetch1("SELECT STAMP,DESCRIPTION FROM `".$this->table."_restore` WHERE ID_BACKUP=".(int)$id);
    }

    /**
     * Lists all available Backups
     *
     * @return array        Raw database table with all backups.
     */
    public function tree_backup_list() {
      global $db;

      return $db->fetch_table("SELECT * FROM `".$this->table."_restore` ORDER BY STAMP DESC");
    }

    /**
     * Creates a cache file of the current tree.
     *
     * @return bool
     */
    public function tree_create_cache() {
      return true;
    }

    /**
     * Checks the tree for consistence.
     *
     * @param   array   $ns
     * @return  bool    True if the nested-set tree seems valid.
     */
    public function tree_check_nestedset($ns) {
      if (($ns[0]["LFT"] != 1) || ($ns[0]["RGT"] != count($ns)*2)) {
        $this->error = "ERR_BROKEN_TREE";
        return false;
      }
      return true;
    }

    /**
     * Creates Nested-Set from current tree.
     *
     * @return bool  True if successfull, false otherwise.
     */
    public function tree_create_nestedset($sort = "order") {
      global $db;
      $nestedset_ar = array();

      $root = $this->tree_get_parent();
      $node = $this->element_read($root);
      if (!$root) {
        // Root-Knoten nicht gefunden
        $this->error = "ERR_ROOT_NOT_FOUND";
        return false;
      }

      $nestedset_ar = array();
      $nestedset_ar[] = array(
      		"id" 		=> $root,
      		"title"		=> strtoupper($node["V1"]),
      		"childs"	=> $this->node_create_nestedset($root, $sort)
      );

      $nestedset = array();
      // $nestedset_ar als Nested-Set nach -> $nestedset

      $this->tree_create_nestedset_from_array($nestedset_ar, $nestedset);
      if (!$this->tree_check_nestedset($nestedset)) {
        $this->error = "ERR_BROKEN_TREE";
        return false;
      }

      $this->tree_create_cache();
      $this->undo_cleanup_actions();

      while ($entry = array_pop($nestedset)) {
        if ($updateid = $db->update($this->table, $entry))
          unset($this->cache_nodes[$updateid]);
      }
      return true;
    }

    /**
     * Creates an array with update-arrays for the nested-set structure.
     *
     * @param array     $ns_array   Array with the ID's of all elements (value = array with child-id's)
     * @param array     $ns         Reference to the array where the update arrays shall be appended.
     * @param int       $left       Initial left-value.
     */
    private function tree_create_nestedset_from_array($ns_array, &$ns, &$left = 1, $level = 0) {
      $order = 0;
      foreach ($ns_array as $title=>$node) {
        $ns_element = array();
        $ns_element["ID_KAT"] = $node["id"];
        $ns_element["LFT"] = $left++;
        $ns_element["LEVEL"] = $level;
        $ns_element["ORDER_FIELD"] = ($order+=10);
        $ns_id = array_push($ns, $ns_element);
        if (!empty($node["childs"])) {
          // Unterknoten vorhanden
          $nestedset_childs = $this->tree_create_nestedset_from_array($node["childs"], $ns, $left, $level + 1);
        }
        $ns[$ns_id-1]["RGT"] = $left++;
      }
    }

    /**
     * Recursive function for saving the whole tree into an array
     *
     * @param   int     $id_parent  ID of the node where do begin
     *
     * @return  array   Array with all categorys nested like in the tree.
     */
    private function node_create_nestedset($id_parent, $sort = "order") {
      global $db;

      $childs = $db->fetch_table("SELECT ID_KAT, ORDER_FIELD FROM `".$this->table."`
                                    WHERE ROOT=".$this->root." AND B_VIS=1 AND PARENT=".$id_parent);
      $childs_order = array(
      	"id" => array(),
      	"title" => array(),
      	"order" => array()
      );

      foreach ($childs as $i=>$element) {
        $id_kat = $element["ID_KAT"];
        $node = $this->element_read($id_kat);
        $title = strtoupper($node["V1"]);
        $childs_order["id"][] = $id_kat;
        $childs_order["title"][] = $title;
        $childs_order["order"][] = $node["ORDER_FIELD"];
      }

      if ($sort == "order") {
      	array_multisort($childs_order["order"], $childs_order["title"], $childs_order["id"]);
      } else {
      	array_multisort($childs_order[$sort], $childs_order["order"], $childs_order["id"]);
      }

      $nestedset = array();
	  foreach ($childs_order["id"] as $index => $id_kat) {
        $nestedset[] = array(
        	"id"		=> $id_kat,
        	"order"     => $childs_order["order"][$index],
        	"title"		=> $childs_order["title"][$index],
        	"childs"	=> $this->node_create_nestedset($id_kat, $sort)
        );
	  }
      return $nestedset;
    }

    /**
     * Recursive function for saving the whole tree into an array woking only with the cache
     *
     * @param   int     $id_parent  ID of the node where do begin
     *
     * @return  array   Array with all categorys nested like in the tree.
     */
    private function node_create_nestedset_cached($id_parent) {
      global $db;

      $nestedset = array();
      $childs = $this->element_get_childs_cached($id_parent);

      $childs_order = array(
      	"id" => array(),
      	"title" => array(),
      	"order" => array()
      );

      foreach ($childs as $i=>$element) {
        $id_kat = $element["ID_KAT"];
        $node = $this->element_read($id_kat);
        $title = strtoupper($node["V1"]);
        $childs_order["id"][] = $id_kat;
        $childs_order["title"][] = $title;
        $childs_order["order"][] = $node["ORDER_FIELD"];
      }
	  array_multisort($childs_order["order"], $childs_order["title"], $childs_order["id"]);

      $nestedset = array();
	  foreach ($childs_order["id"] as $index => $id_kat) {
        $nestedset[] = array(
        	"id"		=> $id_kat,
        	"order"     => $childs_order["order"][$index],
        	"title"		=> $childs_order["title"][$index],
        	"childs"	=> $this->node_create_nestedset_cached($id_kat)
        );
      }
      return $nestedset;
    }

    /**
     * Reads all entries within current root into cache
     *
     * @param   int     $bf_lang    (optional) Target language
     *
     * @return  bool    Could the tree be read?
     */
    public function tree_cache_elements($bf_lang = false) {
      global $db, $langval;
      // Clear cache
      $this->cache_nodes = array();
      if ($bf_lang == false) $bf_lang = $langval;
      $nodes = $db->fetch_table("SELECT el.*, s.T1, s.V1, s.V2 FROM `".$this->table."` el
        LEFT JOIN `string_".$this->table."` s ON s.S_TABLE='".$this->table."' AND s.FK=el.ID_KAT
          AND s.BF_LANG=if(el.BF_LANG_KAT & ".$bf_lang.", ".$bf_lang.", 1 << floor(log(el.BF_LANG_KAT+0.5)/log(2)))
        WHERE ROOT=".$this->root."
        ORDER BY el.ORDER_FIELD");
      if (!empty($nodes)) {
        // Cache node if no other langugage is given
        for ($i = 0; $i < count($nodes); $i++) {
          if ((int)$nodes[$i]["PARENT"] == 0) {
            $this->id_root = $nodes[$i]["ID_KAT"];
          }
          $this->cache_nodes[$nodes[$i]["ID_KAT"]] = $nodes[$i];
        }
      } else {
        $this->error = "ERR_PARENT_NOT_FOUND";
        $this->reload = true;
        return false;
      }
    }

    /**
     * Returns the whole tree (read from $ns if given) for display via tree_show_nestedset().
     *
     * @param   array   $ns
     *
     * @return  array
     */
    public function tree_get($ns = false) {
      $root = $this->tree_get_parent();
      $parent = $this->element_read($root);

      if (!$root) {
        // Root-Knoten nicht gefunden
        $this->error = "ERR_ROOT_NOT_FOUND";
        return false;
      }

      $editable = 0;

      if ($ns == false) {
        // Gespeicherten Baum ausgeben

        // Nicht bearbeitbar da nicht live
        $editable = 1;
		$nestedset_ar = array();
		$nestedset_ar[] = array(
			"id" 		=> $root,
			"title"		=> $parent["V1"],
			"childs"	=> $this->node_create_nestedset($root)
		);

        $ns = array();
        // $nestedset_ar als Nested-Set nach -> $nestedset
        $this->tree_create_nestedset_from_array($nestedset_ar, $ns);
      }
      $result = array();
      $parents = array();
      $parent = $ns[0];
      $parent["kidcount"] = ($parent["RGT"] - $parent["LFT"] - 1) / 2;
      $parent["haskids"] = ($parent["kidcount"] > 0 ? true : false);
      $parent["is_first"] = 1; $parent["is_last"] = 1; $parent["level"] = 0;
      //$parent["childs_done"] = 0;
      for ($i = 1; $i < count($ns); $i++) {
        $ns[$i]["editable"] = $editable;
        $ns[$i]["kidcount"] = ($ns[$i]["RGT"] - $ns[$i]["LFT"] - 1) / 2;
        $ns[$i]["haskids"] = ($ns[$i]["kidcount"] > 0 ? true : false);
        $ns[$i]["is_first"] = (($parent["LFT"]+1) == $ns[$i]["LFT"] ? 1 : 0);
        $ns[$i]["is_last"] = 0;
        $ns[$i]["index"] = $i-1;
        $ns[$i]["level"] = $parent["level"]+1;

        //echo("ELE (".count($parents).")".$ns[$i]["ID_KAT"]." | ".$ns[$i]["RGT"]." -> ".$parent["RGT"]."<br />");
        if ($ns[$i]["RGT"] == ($parent["RGT"]-1)) {
          $ns[$i]["is_last"] = 1;
          $parent = array_pop($parents);
          while ((count($parents) > 1) && ($parents[count($parents)-1]) && ($parent["RGT"] == ($parents[count($parents)-1]["RGT"]-1))) {
            //echo("PAR2 (".count($parents).")".$parent["ID_KAT"]." | ".$parent["RGT"]." -> ".$parents[count($parents)-1]["RGT"]."<br />");
            $result[$parent["index"]]["is_last"] = 1;
            $parent = array_pop($parents);
          }
        }
        $result[$i-1] = array_merge($this->element_read($ns[$i]["ID_KAT"]), $ns[$i]);

        if ($ns[$i]["kidcount"] > 0) {
          // Es folgen Kind-Elemente dieses Knotens
          $parents[] = $parent;
          $parent = $ns[$i];
        }
      }
      //die(ht(dump($result)));
      return $result;
    }

    /**
     * Get the id of the top-level element
     *
     * @param  int $id   (optional) ID of the element you want to know the parent id of.
     *
     * @return int      ID of the top-level or parent node (if $id is given).
     */
    public function tree_get_parent($id = false) {
      global $db;
      if (!$id) {
        // Root-ID ermitteln
        if (!isset($this->id_root))
          $this->id_root = $db->fetch_atom("SELECT ID_KAT FROM `".$this->table."` WHERE ROOT=".$this->root." AND PARENT=0");
        return $this->id_root;
      } else {
        // Parent eines Elements ermitteln
        return $db->fetch_atom("SELECT PARENT FROM `".$this->table."` WHERE ROOT=".$this->root." AND ID_KAT=".$id);
      }
    }

    /**
     * Returns a nested-set array where $changes were applied.
     *
     * @return array|bool  Array with the changed nested-set.
     */
    private function tree_apply_changes($changes) {
      global $db;

      foreach ($changes as $id => $change) {
        $change["ID_KAT"] = $id;
        if ($updateid = $db->update($this->table, $change))
          unset($this->cache_nodes[$updateid]);
      }

      return $this->tree_create_nestedset();
    }

    /**
     * Returns a nested-set array where $changes were applied.
     *
     * @return array|bool  Array with the tree matching for output via tree_show_nested(), false otherwise.
     */
    public function tree_preview_changes($changes) {
      global $db;
      $nestedset_ar = array();

      // Kompletten baum in den cache laden
      $this->tree_cache_elements();
      // Änderungen vorcachen
      foreach ($changes as $id => $data) {
        if (isset($this->cache_nodes[$id])) {
          $this->cache_nodes[$id] = array_merge($this->cache_nodes[$id], $data);
        } else {
          // New node
          $this->cache_nodes[$id] = $data;
        }
      }

      $root = $this->tree_get_parent();
      $node = $this->element_read($root);
      if (!$root) {
        // Root-Knoten nicht gefunden
        $this->error = "ERR_ROOT_NOT_FOUND";
        return false;
      }
      $nestedset_ar = array();
      $nestedset_ar[] = array(
      		"id" 		=> $root,
      		"title"		=> $node["V1"],
      		"childs"	=> $this->node_create_nestedset_cached($root)
      );

      $nestedset = array();
      // $nestedset_ar als Nested-Set nach -> $nestedset
      $this->tree_create_nestedset_from_array($nestedset_ar, $nestedset);
      if (!$this->tree_check_nestedset($nestedset)) {
        $this->error = "ERR_BROKEN_TREE";
        return false;
      }
      return $this->tree_get($nestedset);
    }

    /**
     * Check if $id_child is a child of $id_parent.
     *
     * @param   int         $id_child   ID of the node that maybe a child of $id_parent.
     * @param   int         $id_parent  ID of the parent node which maybe above $id_child.
     * @return  bool        True if $id_child is a child of $id_parent. False otherwise.
     */
    public function element_is_child($id_child, $id_parent) {
      $parent = $this->element_read($id_parent);
      $child = $this->element_read($id_child);
      if (($child["LFT"] > $parent["LFT"]) && ($child["RGT"] < $parent["RGT"]))
        return true;
      return false;
    }

    /**
     * Get the number of objects (e.g. articles) that are saved for this category.
     *
     * @param   int   $id   ID of the category.
     * @return  int         Number of objects assigned to this category.
     */
    public function element_get_entries($id) {
      global $db;
      $element = $this->element_read($id);
      $num_entries = $db->fetch_atom("SELECT count(*) FROM `".$element["KAT_TABLE"]."` WHERE FK_KAT IN
          (SELECT ID_KAT FROM `".$this->table."` WHERE LFT BETWEEN ".$element["LFT"]." AND ".$element["RGT"].")");
      return $num_entries;
    }

    /**
     * Checks if a category is in use.
     *
     * @param   int   $id   ID of the category.
     * @return  bool        True if there are objects (/articles) within this node, false otherwise.
     */
    public function element_is_used($id) {
      if ($this->element_get_entries($id) > 0)
        return true;
      return false;
    }

    /**
     * Checks if the specified element has got child-elements
     *
     * @param   int   $id
     *
     * @return  bool  True if it has, false otherwise.
     */
    public function element_has_childs($id) {
      global $db;
      if ($db->fetch_atom("SELECT count(*) FROM `".$this->table."` WHERE ROOT=".$this->root." AND PARENT=".$id) > 0)
        return true;
      return false;
    }

    /**
     * Reads all direct childs of $id from cache. May not contain all childs if cache is not complete!
     *
     * @param   int     $id     ID of the parent element
     *
     * @return  array   An array with all child-elements.
     */
    public function element_get_childs_cached($id) {
      $childs = array();
      foreach ($this->cache_nodes as $id_kat => $data) {
        if ($data["PARENT"] == $id) {
          $childs[] = $data;
        }
      }
      return $childs;
    }

    /**
     * Reads all direct childs of $id from cache.
     *
     * @param   int     $id     ID of the parent element
     *
     * @return  array   An array with all child-elements.
     */
    public function element_get_childs($id) {
      global $db;
      $child_nodes = $db->fetch_table("SELECT ID_KAT, KAT_TABLE FROM `".$this->table."`
                                    WHERE ROOT=".$this->root." AND B_VIS=1 AND PARENT=".$id."
        							ORDER BY ORDER_FIELD");
      $childs = array();
      foreach ($child_nodes as $index => $data) {
        $childs[] = $this->element_read($data["ID_KAT"]);
      }
      return $childs;
    }

    /**
     * Returns the whole element including multilingual strings V1 & V2.
     * Tries to get it from cache first!
     *
     * @param int $id         Element to be read
     * @param int $bf_lang    (optional) Target language
     *
     * @return array  An array with the complete content
     */
    public function element_read($id, $bf_lang = false) {
      if (!isset($id)) {
        $this->error = "ERR_MISSING_PARAMS";
        $this->reload = true;
        return false;
      }
      if (isset($this->cache_nodes[$id]) && !$bf_lang) return $this->cache_nodes[$id];
      global $db, $langval;
      if ($bf_lang == false) $bf_lang = $langval;
      $node = $db->fetch1("SELECT el.*, s.T1, s.V1, s.V2 FROM `".$this->table."` el
        LEFT JOIN `string_".$this->table."` s ON s.S_TABLE='".$this->table."' AND s.FK=el.ID_KAT
          AND s.BF_LANG=if(el.BF_LANG_KAT & ".$bf_lang.", ".$bf_lang.", 1 << floor(log(el.BF_LANG_KAT+0.5)/log(2)))
        WHERE ROOT=".$this->root." AND B_VIS=1 AND ID_KAT=".$id."
        ORDER BY el.ORDER_FIELD");
      if ($node) {
      	// Split T1 Field into T1 (Keywords) and META (Meta-Tags)
      	list($node["T1"], $node["META"]) = explode("||||", $node["T1"]);
        // Unserialize options
        $node["OPTIONS"] = $this->applyDefaultKatOptions(unserialize($node["SER_OPTIONS"]));
        $node = array_merge( $node, array_flatten($node["OPTIONS"], false, "_", "OPTIONS_"), array_flatten($node["OPTIONS"], true, "_", "OPTIONS_") );
        // Cache node if no other langugage is given
        if ($bf_lang == $langval) $this->cache_nodes[$id] = $node;
        return $node;
      } else {
        $this->error = "ERR_PARENT_NOT_FOUND";
        $this->reload = true;
        return false;
      }
    }

    /**
     * Returns the ID's of all elements that the specified element can be moved in.
     *
     * @param   int   $id     Element to be moved
     *
     * @return array  An array with the ID's of all valid move targets.
     */
    public function element_get_targets($id) {
      global $db;

      $root = $db->fetch_atom("SELECT ID_KAT FROM `".$this->table."`
                                  WHERE ROOT=".$this->root." AND PARENT=0");
      $cur_parent = $db->fetch_atom("SELECT PARENT FROM `".$this->table."`
                                  WHERE ROOT=".$this->root." AND ID_KAT=".$id);
      $node = $this->element_read($id);
      if (!$root) {
        // Root-Knoten nicht gefunden
        $this->error = "ERR_ROOT_NOT_FOUND";
        return false;
      }

      $targets = array();
      if ($cur_parent != $root)
        $targets[] = $root;
      $childs = $db->fetch_table("SELECT ID_KAT, KAT_TABLE FROM `".$this->table."`
                                    WHERE ROOT=".$this->root." AND PARENT=".$root);

      foreach ($childs as $i=>$element) {
        $id_kat = $element["ID_KAT"];
        $id_parent = $element["PARENT"];
        if (($id_kat != $id) && ($id_parent != $id) && ($element["KAT_TABLE"] == $node["KAT_TABLE"])) {
          if ($node["PARENT"] != $id_kat)
            $targets[] = (int)$id_kat;
          $this->element_get_child_targets($id, $id_kat, $targets);
        }
      }
      return $targets;
    }

    /**
     * Get possible move targets for $id into $id_child. Recursive.
     *
     * @param int     $id         ID of the element that is moved
     * @param int     $id_child   ID of the element that shall be checked for valid targets.
     * @param array   $targets    A reference to an array where all matching element-id's are appended.
     */
    private function element_get_child_targets($id, $id_child, &$targets) {
      global $db;
      $node = $this->element_read($id);

      $childs = $db->fetch_table("SELECT ID_KAT, KAT_TABLE FROM `".$this->table."`
                                    WHERE ROOT=".$this->root." AND PARENT=".$id_child);

      foreach ($childs as $i=>$element) {
        $id_kat = $element["ID_KAT"];
        $id_parent = $element["PARENT"];
        if (($id_kat != $id) && ($id_parent != $id) && ($element["KAT_TABLE"] == $node["KAT_TABLE"])) {
          if ($node["PARENT"] != $id_kat)
            $targets[] = (int)$id_kat;
          $this->element_get_child_targets($id, $id_kat, $targets);
        }
      }
    }

    /**
     * Returns the ID's of all elements that the specified element can be moved before.
     *
     * @param   int   $id     Element to be moved
     *
     * @return array  An array with the ID's of all valid move targets.
     */
    public function element_get_targets_sort($id) {
      global $db;

      $root = $db->fetch_atom("SELECT ID_KAT FROM `".$this->table."`
                                  WHERE ROOT=".$this->root." AND PARENT=0");
      $cur_parent = $db->fetch_atom("SELECT PARENT FROM `".$this->table."`
                                  WHERE ROOT=".$this->root." AND ID_KAT=".$id);
      $node = $this->element_read($id);
      if (!$root) {
        // Root-Knoten nicht gefunden
        $this->error = "ERR_ROOT_NOT_FOUND";
        return false;
      }

      $targets = array();
      $childs = $db->fetch_table("SELECT ID_KAT, KAT_TABLE, PARENT FROM `".$this->table."`
                                    WHERE ROOT=".$this->root." AND PARENT=".$cur_parent);
      foreach ($childs as $i=>$element) {
      	$id_kat = $element["ID_KAT"];
        $id_parent = $element["PARENT"];
        if (($id_parent == $root) && ($id_kat != $id)) {
            // Root!
            $targets[] = (int)$id_kat;
        } else {
            $ar_parent = $this->element_read($id_parent);
            if (($id_kat != $id) && (empty($ar_parent["KAT_TABLE"]) || ($ar_parent["KAT_TABLE"] == $node["KAT_TABLE"]))) {
                if (!empty($id_parent)) {
                    $targets[] = (int)$id_kat;
                    $this->element_get_child_targets_sort($id, $id_kat, $targets);
                }
            }
        }
      }
      return $targets;
    }

    /**
     * Get possible move targets for $id before $id_child. Recursive.
     *
     * @param int     $id         ID of the element that is moved
     * @param int     $id_child   ID of the element that shall be checked for valid targets.
     * @param array   $targets    A reference to an array where all matching element-id's are appended.
     */
    private function element_get_child_targets_sort($id, $id_child, &$targets) {
      global $db;
      $node = $this->element_read($id);

      $childs = $db->fetch_table("SELECT ID_KAT, KAT_TABLE, PARENT FROM `".$this->table."`
                                    WHERE ROOT=".$this->root." AND PARENT=".$id_child);

      foreach ($childs as $i=>$element) {
      	$id_kat = $element["ID_KAT"];
        $id_parent = $element["PARENT"];
      	$ar_parent = $this->element_read($id_parent);
        if (($id_kat != $id) && (empty($ar_parent["KAT_TABLE"]) || ($ar_parent["KAT_TABLE"] == $node["KAT_TABLE"]))) {
          $targets[] = (int)$id_kat;
          $this->element_get_child_targets_sort($id, $id_kat, $targets);
        }
      }
    }

    /**
     * Creates a new element as child of $parent.
     *
     * @param int       $parent       ID of the parent element
     * @param array     $data         Array with all properties
     *
     * @return bool   True if successfull, false otherwise.
     */
    public function element_create($parent, $data, $allowIds = false) {
      global $db;

      if (!is_array($data)) {
        $this->error = "ERR_MISSING_PARAMS";
        return false;
      }

      if (!$this->tree_lock_valid()) {
        return false;
      }

      $parent_node = $db->fetch1("SELECT * FROM `".$this->table."` WHERE ROOT=".$this->root." AND ID_KAT=".$parent);
      if (!$allowIds) {
      	unset($parent_node["ID_KAT"]);
      }
      $new_node = array_merge($parent_node, $data);
      $new_node["PARENT"] = $parent;
      $new_node["ROOT"] = $this->root;

      if ($this->updateid = $db->update($this->table, $new_node)) {
        return true;
      } else {
        $this->error = "ERR_INSERT_FAILED";
        return false;
      }
    }

    /**
     * Updates the element with $data.
     * If $inerhit is true the updates are also made to all child-elements.
     *
     * @param int     $id               The ID of the element thath shall be updated
     * @param array   $data             Array with all properties that shall be updated
     * @param bool    $inherit          Apply these changes to all child-elements
     *
     * @return bool  True if successfull, false otherwise.
     */
    public function element_update($id, $data, $inherit = false) {
      if (!isset($id)) {
        $this->error = "ERR_MISSING_PARAMS";
        return false;
      }

      global $db;
      $original_data = $this->element_read($id);
      $datanew = $data;
      $datanew["ID_KAT"] = $id;
      $datanew["FK_INFOSEITE"] = ($data["FK_INFOSEITE"] > 0 ? (int)$data["FK_INFOSEITE"] : null);
      if ($data["KATHEAD"] != "DOONE") {
      	$query = "UPDATE `kat` SET FK_INFOSEITE=".($datanew["FK_INFOSEITE"] == null ? "NULL" : $datanew["FK_INFOSEITE"])."\n".
      			"WHERE LFT BETWEEN ".(int)$original_data["LFT"]." AND ".(int)$original_data["RGT"]."\n".
      			"	AND ROOT=".(int)$original_data["ROOT"];
      	if ($data["KATHEAD"] == "DOFILL") {
      		$query .= " AND FK_INFOSEITE IS NULL";
      	}
      	$db->querynow($query);
      }
      if ($this->updateid = $db->update($this->table, $datanew)) {
        unset($this->cache_nodes[$this->updateid]);
        if (!$inherit && isset($data["KAT_TABLE"]) &&
            ($original_data["KAT_TABLE"] != $data["KAT_TABLE"])) {
          // Tabelle wurde geändert und soll nicht auf Kind-Elemente übertragen werden, zwingen!
          $datanew = array();
          $datanew["KAT_TABLE"] = $data["KAT_TABLE"];
          $data = $datanew;
          $inherit = true;
        }
        if ($inherit) {
          unset($data["ID_KAT"]);
          unset($data["PARENT"]);
          $childs = $db->fetch_table("SELECT ID_KAT FROM `".$this->table."` WHERE ROOT=".$this->root." AND PARENT=".$id);
          for ($i = 0; $i < count($childs); $i++) {
            if (!$this->element_update($childs[$i]["ID_KAT"], $data, true)) {
              $this->error = "ERR_UPDATE_FAILED";
              return false;
            }
          }
        }
        return true;
      } else {
        $this->error = "ERR_UPDATE_FAILED";
        return false;
      }
    }

    /**
     * Moves an element within the tree.
     * If $keep_childs is defined and false child elements are not moved with their parent
     * but moved one level up.
     *
     * @param  int    $id             ID of the element that shall be moved
     * @param  int    $id_parent      ID of the parent element
     * @param  bool   $keep_childs    (optional) Also move all child-elements
     *
     * @return bool   True if successfull, false otherwise.
     */
    public function element_move($id, $id_target, $inherit_childs = true) {
      global $db;

      $source = $this->element_read($id);
      $target = $this->element_read($id_target);
      if ((!$target) || $this->element_is_child($id_target, $id)) {
        $this->error = "ERR_INVALID_TARGET";
        return false;
      }
      if (($source["KAT_TABLE"] != $target["KAT_TABLE"]) && ($id_target != $this->tree_get_parent())) {
        $this->error = "ERR_TABLE_MISMATCH";
        return false;
      }

      $new = array("ID_KAT" => $id, "PARENT" => $id_target);
      if ($this->updateid = $db->update($this->table, $new)) {
        unset($this->cache_nodes[$this->updateid]);
        if (!$inherit_childs) {
          // Kind-Elemente eine Ebene aufwärts verschieben
          $db->querynow("UPDATE `".$this->table."` SET PARENT=".$source["PARENT"]."
            WHERE ROOT=".$this->root." AND PARENT=".$source["ID_KAT"]);
        } else {
          $this->undo_add_action("MOVE", $id, $id_target, $source["PARENT"]);
        }
        $this->reload = true;
        return true;
      } else {
        $this->error = "ERR_INSERT_FAILED";
        return false;
      }
    }

    /**
     * Moves an element within the tree.
     * If $keep_childs is defined and false child elements are not moved with their parent
     * but moved one level up.
     *
     * @param  int    $id             ID of the element that shall be moved
     * @param  int    $id_parent      ID of the parent element
     * @param  bool   $keep_childs    (optional) Also move all child-elements
     *
     * @return bool   True if successfull, false otherwise.
     */
    public function element_move_into($id, $id_target, $inherit_childs = true) {
      global $db;

      $source = $this->element_read($id);
      $target = $this->element_read($id_target);
      if ((!$target) || $this->element_is_child($id_target, $id)) {
        $this->error = "ERR_INVALID_TARGET";
        return false;
      }
      if (($source["KAT_TABLE"] != $target["KAT_TABLE"]) && ($id_target != $this->tree_get_parent())) {
        $this->error = "ERR_TABLE_MISMATCH";
        return false;
      }

      $new = array(
      	"ID_KAT" => $id,
      	"PARENT" => $id_target,
      	"ORDER_FIELD" => 0
      );
      if ($this->updateid = $db->update($this->table, $new)) {
        unset($this->cache_nodes[$this->updateid]);
        if (!$inherit_childs) {
          // Kind-Elemente eine Ebene aufwärts verschieben
          $db->querynow("UPDATE `".$this->table."` SET PARENT=".$source["PARENT"].", ORDER_FIELD=".$source["ORDER_FIELD"]."
            WHERE ROOT=".$this->root." AND PARENT=".$source["ID_KAT"]);
        } else {
          $this->undo_add_action("MOVE", $id, $id_target, $source["PARENT"]);
        }
        $this->reload = true;
        return true;
      } else {
        $this->error = "ERR_INSERT_FAILED";
        return false;
      }
    }

    /**
     * Moves an element within the tree.
     * If $keep_childs is defined and false child elements are not moved with their parent
     * but moved one level up.
     *
     * @param  int    $id             ID of the element that shall be moved
     * @param  int    $id_parent      ID of the parent element
     * @param  bool   $keep_childs    (optional) Also move all child-elements
     *
     * @return bool   True if successfull, false otherwise.
     */
    public function element_move_before($id, $id_target, $inherit_childs = true) {
      global $db;

      $source = $this->element_read($id);
      $target = $this->element_read($id_target);
      if ((!$target) || $this->element_is_child($id_target, $id)) {
        $this->error = "ERR_INVALID_TARGET";
        return false;
      }
      $target_parent = $this->element_read($target["PARENT"]);
      if (!empty($target_parent["KAT_TABLE"]) && ($source["KAT_TABLE"] != $target_parent["KAT_TABLE"]) &&
      		($target["PARENT"] != $this->tree_get_parent())) {
        $this->error = "ERR_TABLE_MISMATCH";
        return false;
      }

      $new = array(
      	"ID_KAT" => $id,
      	"PARENT" => $target_parent["ID_KAT"],
      	"ORDER_FIELD" => ($target["ORDER_FIELD"]-1)
      );
      if ($this->updateid = $db->update($this->table, $new)) {
        unset($this->cache_nodes[$this->updateid]);
        if (!$inherit_childs) {
          // Kind-Elemente eine Ebene aufwärts verschieben
          $db->querynow("UPDATE `".$this->table."` SET PARENT=".$source["PARENT"].", ORDER_FIELD=".$source["ORDER_FIELD"]."
            WHERE ROOT=".$this->root." AND PARENT=".$source["ID_KAT"]);
        } else {
          $this->undo_add_action("MOVE", $id, $target_parent["ID_KAT"], $source["PARENT"]);
        }
        $this->reload = true;
        return true;
      } else {
        $this->error = "ERR_INSERT_FAILED";
        return false;
      }
    }

    /**
     * Deletes an element from tree.
     * If $keep_childs is set to true child-elements will NOT be deleted
     * but moved one level up.
     *
     * @param  int   $id              The ID of the element thath shall be updated
     * @param  bool  $inherit_childs  (optional) Also delete all child-elements?
     *
     * @return bool  True if successfull, false otherwise.
     */
    public function element_delete($id, $inherit_childs = true) {
      global $db;

      $source = $this->element_read($id);
      if (!$db->querynow("DELETE FROM `".$this->table."` WHERE ID_KAT=".$id)) {
        $this->error = "ERR_ELEMENT_NOT_FOUND";
        return false;
      }
      unset($this->cache_nodes[$id]);
      if ($inherit_childs) {
        // Kind-Elemente auch löschen
        $childs = $db->fetch_table("SELECT ID_KAT FROM `".$this->table."` WHERE ROOT=".$this->root." AND PARENT=".$id);
        for ($i = 0; $i < count($childs); $i++)
          if (!$this->element_delete($childs[$i]["ID_KAT"], $inherit_childs)) {
            $this->error = "ERR_ELEMENT_NOT_FOUND";
            return false;
          }
      } else {
        // Kind-Elemente eine Ebene aufwärts verschieben
        $db->querynow("UPDATE `".$this->table."` SET PARENT=".$source["PARENT"]."
          WHERE ROOT=".$this->root." AND PARENT=".$id);
        // Cache nicht mehr gültig!
        $this->cache_nodes = array();
      }
      $this->reload = true;
      return true;
    }

    /**
     * Adds an action to undo list
     *
     * @param string    $action
     * @param int       $id
     * @param int       $id_parent
     * @param int       $id_parent_prev
     *
     * @return bool     Success?
     */
    private function undo_add_action($action, $id, $id_parent, $id_parent_prev) {
      global $db, $uid;
      $undo_node = array();
      $undo_node["ACTION"]          = mysql_escape_string($action);
      $undo_node["FK_KAT"]          = (int)$id;
      $undo_node["FK_PARENT"]       = (int)$id_parent;
      $undo_node["FK_PARENT_PREV"]  = (int)$id_parent_prev;
      $undo_node["FK_USER"]         = (int)$uid;
      if ($db->update($this->table."_undo", $undo_node)) {
        return true;
      } else {
        $this->error = "ERR_UNDO_ADD";
        return false;
      }
    }

    /**
     * Removes all undo-steps.
     *
     */
    public function undo_clear_all_actions() {
      global $db;
      $db->querynow("DELETE FROM `".$this->table."_undo`");
    }

    /**
     * Removes undo-steps that aren't applicable anymore.
     *
     */
    private function undo_cleanup_actions() {
      global $db;
      $elements_locked = array();
      $undo_nodes = $db->fetch_table("SELECT * FROM `".$this->table."_undo` ORDER BY ID_UNDO DESC");
      for ($i = 0; $i < count($undo_nodes); $i++) {
        $node = $undo_nodes[$i];
        foreach ($elements_locked as $id => $lock) {
          if (($node["FK_PARENT"] == $id) ||
              (!$this->element_read($id)) ||
              ($this->element_is_child($node["FK_PARENT"], $id)) ||
              ($this->element_is_child($node["FK_PARENT_PREV"], $id))) {
            $db->querynow("DELETE FROM `".$this->table."_undo` WHERE ID_UNDO=".$node["ID_UNDO"]);
          }
        }
        if ($node["ACTION"] == "MOVE") {
          // Element wurde verschoben
          $elements_locked[$node["FK_PARENT"]] = true;
          $elements_locked[$node["FK_PARENT_PREV"]] = true;
        }
      }
    }

    /**
     * Previews the undo-action with $id
     *
     * @param   int   $id   ID of the undo-action
     *
     * @return  array     The tree with applied changes for output via tree_show_nested().
     */
    public function undo_preview_step($id) {
      $changes = array();
      $step = $this->undo_get_step($id);
      if ($step["ACTION"] == 'MOVE') {
        // Revert change
        $node_moved = $step["FK_KAT"];
        $node_moved_from = $step["FK_PARENT_PREV"];
        $changes[$node_moved]["PARENT"] = $node_moved_from;
        $changes[$node_moved]["HIGHLIGHT"] = "NEW";
        // Keep showing previous position
        $changes[$node_moved."CUR"] = $this->element_read($node_moved);
        $changes[$node_moved."CUR"]["ID_KAT"] = $node_moved."CUR";
        $changes[$node_moved."CUR"]["PARENT"] = $step["FK_PARENT"];
        $changes[$node_moved."CUR"]["HIGHLIGHT"] = "DEL";
      }
      return $this->tree_preview_changes($changes);
    }

    /**
     * Applies the changes of undo-action with $id
     *
     * @param   int   $id   ID of the undo-action
     *
     * @return bool     True if undo-action was applied sucessfully
     */
    public function undo_apply_step($id) {
      global $db, $uid;

      $changes = array();
      $step = $this->undo_get_step($id);

      // Nur eigene Undo-Schritte erlauben
      if ($step["FK_USER"] != $uid)
        return false;

      if ($step["ACTION"] == 'MOVE') {
        $node_moved = $step["FK_KAT"];
        $node_moved_from = $step["FK_PARENT_PREV"];
        $changes[$node_moved]["PARENT"] = $node_moved_from;
      }

      $this->tree_backup_create("[UNDO ".$step["ACTION"]."] Kategorie '".$step["KAT_NAME"]."'".
        " in '".$step["PARENT_PREV_NAME"]."' verschoben. (von ".$step["PARENT_NAME"].")");

      if ($this->tree_apply_changes($changes)) {
        $db->querynow("DELETE FROM `".$this->table."_undo` WHERE ID_UNDO=".(int)$id);
        return true;
      }
      return false;
    }

    /**
     * Get a single undo-step from database.
     *
     * @return  array   All data + element-names of the undo-step
     */
    public function undo_get_step($id) {
      global $db;
      $step = $db->fetch1("SELECT * FROM `".$this->table."_undo` WHERE ID_UNDO=".(int)$id);
      $node = $this->element_read($step["FK_KAT"]);
      $node_parent = $this->element_read($step["FK_PARENT"]);
      $node_parent_prev = $this->element_read($step["FK_PARENT_PREV"]);
      $step["KAT_NAME"] = $node["V1"];
      $step["PARENT_NAME"] = $node_parent["V1"];
      $step["PARENT_PREV_NAME"] = $node_parent_prev["V1"];
      return $step;
    }

    /**
     * Get all undo-step from database.
     *
     * @return  array   All data + element-names of available undo-steps
     */
    public function undo_get_steps() {
      global $db, $uid;
      $steps = $db->fetch_table("SELECT * FROM `".$this->table."_undo` WHERE FK_USER=".$uid." ORDER BY STAMP DESC");
      for ($i = 0; $i < count($steps); $i++) {
        if ($steps[$i]["ACTION"] == "MOVE") {
          $node = $this->element_read($steps[$i]["FK_KAT"]);
          $node_parent = $this->element_read($steps[$i]["FK_PARENT"]);
          $node_parent_prev = $this->element_read($steps[$i]["FK_PARENT_PREV"]);
          $steps[$i]["KAT_NAME"] = $node["V1"];
          $steps[$i]["PARENT_NAME"] = $node_parent["V1"];
          $steps[$i]["PARENT_PREV_NAME"] = $node_parent_prev["V1"];
        }
        if ($steps[$i]["ACTION"] == "RESTORE") {
          $backup_desc = $this->tree_backup_get_desc($steps[$i]["FK_PARENT"]);
          $steps[$i]["BACKUP_STAMP"] = $backup_desc["STAMP"];
          $steps[$i]["BACKUP_NAME"] = $backup_desc["DESCRIPTION"];
        }
      }
      return $steps;
    }
  }
?>