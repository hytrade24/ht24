<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 14.11.16
 * Time: 17:52
 */

class Api_TreeNestedSet implements Api_Tree {

  protected $db;
  protected $table;
  protected $primaryKey;
  protected $fieldLeft;
  protected $fieldRight;
  protected $fieldLevel;
  protected $fieldRoot;
  
  function __construct(ebiz_db $db, $table, $fieldLeft = "NS_LEFT", $fieldRight = "NS_RIGHT", $fieldLevel = "NS_LEVEL", $fieldRoot = null, $primaryKey = null) {
    $this->db = $db;
    $this->table = $table;
    $this->primaryKey = ($primaryKey !== null ? $primaryKey : "ID_".strtoupper($table));
    $this->fieldLeft = $fieldLeft;
    $this->fieldRight = $fieldRight;
    $this->fieldLevel = $fieldLevel;
    $this->fieldRoot = $fieldRoot;
  }
  
  /**
   * Add a new node with the given parent and sort index
   * @param int|null    $parentId
   * @param int         $sortIndex
   * @param array       $arData
   * @return bool|int
   */
  private function addPos($position, $level, $arData, $rootId = 1, $skipValuesUpdate = false)
  {
    // Make room for the new child
    if (!$skipValuesUpdate && !$this->changeNodeValues($position, null, 2, $rootId)) {
      return false;
    }
    // Prepare insert
    $arData[ $this->fieldLeft ] = $position;
    $arData[ $this->fieldRight ] = $position+1;
    $arData[ $this->fieldLevel ] = $level;
    if ($this->fieldRoot !== null) {
      $arData[ $this->fieldRoot ] = $rootId;
    }
    if (array_key_exists($this->primaryKey, $arData)) {
      unset($arData[ $this->primaryKey ]);
    }
    // Insert element
    $idElement = $this->db->update($this->table, $arData);
    if ($idElement > 0) {
      return $idElement;
    } else {
      return false;
    }
  }  
  
  private function fetchPosition($nodeId) {
    return $this->db->fetch1("
      SELECT
        p.`".mysql_real_escape_string($this->fieldLeft)."` AS `LEFT`,
        p.`".mysql_real_escape_string($this->fieldRight)."` AS `RIGHT`,
        ".($this->fieldLevel !== null ? "p.`".mysql_real_escape_string($this->fieldLevel)."` AS `LEVEL`," : "")."
        ".($this->fieldRoot !== null ? "p.`".mysql_real_escape_string($this->fieldRoot)."` AS `ROOT`" : "1 AS `ROOT`")."
      FROM `".mysql_real_escape_string($this->table)."` p
      WHERE p.`".mysql_real_escape_string($this->primaryKey)."`=".(int)$nodeId);
  }
  
  private function getNextRootPosition($rootId = 1) {
    // Calculate desired sort index
    $positionMax = $this->db->fetch_atom("
      SELECT MAX(`".mysql_real_escape_string($this->fieldRight)."`) 
      FROM `".mysql_real_escape_string($this->table)."`
      ".($this->fieldRoot !== null ? "WHERE `".mysql_real_escape_string($this->fieldRoot)."`=".(int)$rootId : ""));
    return ($positionMax === null ? 1 : $positionMax + 1);
  }
  
  private function changeNodeValue($nodeId, $left, $right) {
    $result = $this->db->querynow("
      UPDATE `".mysql_real_escape_string($this->table)."`
      SET `".mysql_real_escape_string($this->fieldLeft)."`=".(int)$left.", `".mysql_real_escape_string($this->fieldRight)."`=".(int)$right."
      WHERE `".mysql_real_escape_string($this->primaryKey)."`=".(int)$nodeId);
    if (!$result["rsrc"]) {
      return false;
    }
    return true;
  }
  
  private function changeNodeValues($positionMin= null, $positionMax = null, $offset = 2, $rootId = 1) {
    $arWhereLeft = array();
    $arWhereRight = array();
    if ($positionMin !== null) {
      $arWhereLeft[] = "`".mysql_real_escape_string($this->fieldLeft)."`>=".(int)$positionMin;
      $arWhereRight[] = "`".mysql_real_escape_string($this->fieldRight)."`>=".(int)$positionMin;
    }
    if ($positionMax !== null) {
      $arWhereLeft[] = "`".mysql_real_escape_string($this->fieldLeft)."`<=".(int)$positionMax;
      $arWhereRight[] = "`".mysql_real_escape_string($this->fieldRight)."`<=".(int)$positionMax;
    }
    if ($this->fieldRoot !== null) {
      $arWhereLeft[] = "`".mysql_real_escape_string($this->fieldRoot)."`=".(int)$rootId;
      $arWhereRight[] = "`".mysql_real_escape_string($this->fieldRoot)."`=".(int)$rootId;
    }
    $result = $this->db->querynow("
      UPDATE `".mysql_real_escape_string($this->table)."`
      SET `".mysql_real_escape_string($this->fieldLeft)."`=`".mysql_real_escape_string($this->fieldLeft)."` ".($offset > 0 ? "+ ".(int)$offset : "- ".(int)$offset)."
      ".(!empty($arWhereLeft) ? "WHERE ".implode("\n     AND", $arWhereLeft) : ""));
    if (!$result["rsrc"]) {
      return false;
    }
    $result = $this->db->querynow("
      UPDATE `".mysql_real_escape_string($this->table)."`
      SET `".mysql_real_escape_string($this->fieldRight)."`=`".mysql_real_escape_string($this->fieldRight)."` ".($offset > 0 ? "+ ".(int)$offset : "- ".(int)$offset)."
      ".(!empty($arWhereLeft) ? "WHERE ".implode("\n     AND", $arWhereRight) : ""));
    if (!$result["rsrc"]) {
      return false;
    }
    return true;
  }
  
  /**
   * Add new child node (after the last child)
   * @param int|null $parentId Id of the parent element
   * @param array $arData Array containing additional data of the node
   * @return int                        Id of the new node
   */
  public function addChild($parentId, $arData, $rootId = 1)
  {
    if ($parentId === null) {
      // Insert node
      return $this->addPos($this->getNextRootPosition($rootId), 1, $arData, $rootId, true);
    } else {
      // Get parent position
      $arParent = $this->fetchPosition($parentId, $rootId);
      // Insert node
      return $this->addPos($arParent["RIGHT"], $arParent["LEVEL"]+1, $arData, $rootId);
    }
  }

  /**
   * Add new node before the given target
   * @param int $nodeId Id of the reference node to insert before
   * @param array $arData Array containing additional data of the node
   * @return int                        Id of the new node
   */
  public function addBefore($nodeId, $arData, $rootId = 1)
  {
      // Get parent position
      $arNode = $this->fetchPosition($nodeId, $rootId);
      // Insert node
      return $this->addPos($arNode["LEFT"]+1, $arNode["LEVEL"], $arData, $rootId);
  }

  /**
   * Add new node after the given target
   * @param int $nodeId Id of the reference node to insert after
   * @param array $arData Array containing additional data of the node
   * @return int                        Id of the new node
   */
  public function addAfter($nodeId, $arData, $rootId = 1)
  {
      // Get parent position
      $arNode = $this->fetchPosition($nodeId, $rootId);
      // Insert node
      return $this->addPos($arNode["RIGHT"]+1, $arNode["LEVEL"], $arData, $rootId);
  }

  /**
   * Delete the given node from the tree
   * @param int $nodeId Id of the node to be deleted
   * @param bool $deleteChilds Delete the child nodes as well?
   * @return bool                       True on success, false otherwise
   */
  public function delete($nodeId, $deleteChilds = true)
  {
    // Get node position
    $arNode = $this->fetchPosition($nodeId);
    if ($deleteChilds) {
      $result = $this->db->querynow("
          DELETE FROM `".mysql_real_escape_string($this->table)."`
          WHERE `".mysql_real_escape_string($this->fieldLeft)."` BETWEEN ".(int)$arNode["LEFT"]." AND ".(int)$arNode["RIGHT"]."
            ".($this->fieldRoot !== null ? "AND `".mysql_real_escape_string($this->fieldRoot)."`=".(int)$arNode["ROOT"] : ""));
      if (!$result["rsrc"]) {
        return false;
      }
      if (!$this->changeNodeValues($arNode["RIGHT"]+1, null, ($arNode["LEFT"] - $arNode["RIGHT"]))) {
        return false;
      }
    } else {
      $result = $this->db->querynow("
          DELETE FROM `".mysql_real_escape_string($this->table)."`
          WHERE `".mysql_real_escape_string($this->primaryKey)."`=".(int)$nodeId);
      if (!$result["rsrc"]) {
        return false;
      }
      if (!$this->changeNodeValues($arNode["RIGHT"]+1, null, -1, $arNode["ROOT"])) {
        return false;
      }
      if (!$this->changeNodeValues($arNode["LEFT"]+1, null, -1, $arNode["ROOT"])) {
        return false;
      }
    }
    return true;
  }

  /**
   * Delete all childs of the given node
   * @param int $nodeId Id of the node to delete the childs from
   * @return bool                       True on success, false otherwise
   */
  public function deleteChilds($nodeId)
  {
    // Get node position
    $arNode = $this->fetchPosition($nodeId);
    // Delete childs
    $result = $this->db->querynow("
        DELETE FROM `".mysql_real_escape_string($this->table)."`
        WHERE `".mysql_real_escape_string($this->fieldLeft)."` BETWEEN ".((int)$arNode["LEFT"]+1)." AND ".((int)$arNode["RIGHT"]-1)."
          ".($this->fieldRoot !== null ? "AND `".mysql_real_escape_string($this->fieldRoot)."`=".(int)$arNode["ROOT"] : ""));
    if (!$result["rsrc"]) {
      return false;
    }
    if (!$this->changeNodeValues($arNode["LEFT"]+1, null, ($arNode["LEFT"] - $arNode["RIGHT"] + 2), $arNode["ROOT"])) {
      return false;
    }
    return true;
  }

  /**
   * Get all query parts for selecting the child nodes
   * @param $arFields
   * @param $arJoins
   * @param $arWhere
   * @param $arOrder
   * @param $arHaving
   * @param string $tablePrefix
   * @return mixed
   */
  public function getChildsQuery($parentId, &$arJoins, &$arWhere, &$arOrder, &$arHaving, $recursive = false, $tablePrefix = null, $rootId = 1)
  {
    // Initialize variables (if required)
    if (!is_array($arJoins)) $arJoins = array();
    if (!is_array($arWhere)) $arWhere = array();
    if (!is_array($arOrder)) $arOrder = array();
    if (!is_array($arHaving)) $arHaving = array();
    $tablePrefixFull = ($tablePrefix !== null ? $tablePrefix."." : "");
    // Generate query parts
    if ($parentId === null) {
      $arNode = null;
      // Add root condition
      if ($this->fieldRoot !== null) {
        $arWhere[] = ($tablePrefix !== null ? $tablePrefix."." : "")."`".mysql_real_escape_string($this->fieldRoot)."`=".(int)$rootId;
      }
    } else {
      // Get node position
      $arNode = $this->fetchPosition($parentId);
      // Add root condition
      if ($this->fieldRoot !== null) {
        $arWhere[] = $tablePrefixFull."`".mysql_real_escape_string($this->fieldRoot)."`=".(int)$arNode["ROOT"];
      }
      $arWhere[] = $tablePrefixFull."`".mysql_real_escape_string($this->fieldLeft)."`>".(int)$arNode["LEFT"];
      $arWhere[] = $tablePrefixFull."`".mysql_real_escape_string($this->fieldRight)."`<".(int)$arNode["RIGHT"];
    }
    $arOrder[] = $tablePrefixFull."`".mysql_real_escape_string($this->fieldLeft)."` ASC";
    // Only get immediate childs?
    if (!$recursive) {
      $targetLevel = ($arNode === null ? 1 : $arNode["LEVEL"]+1);
      if ($this->fieldLevel !== null) {
        // Use existing level information
        $arWhere[] = $tablePrefixFull."`".mysql_real_escape_string($this->fieldLevel)."`=".(int)$targetLevel;
      } else {
        // Join level by
        $joinPrefix = ($tablePrefix !== null ? $tablePrefix."_p" : "p");
        $arJoins[] = "
          JOIN `".mysql_real_escape_string($this->table)."` ".$joinPrefix." ON 
            ".$joinPrefix.".`".mysql_real_escape_string($this->fieldLeft)."`<".$tablePrefixFull."`".mysql_real_escape_string($this->fieldLeft)."`
              AND ".$joinPrefix.".`".mysql_real_escape_string($this->fieldRight)."`>".$tablePrefixFull."`".mysql_real_escape_string($this->fieldRight)."`
              ".($this->fieldRoot !== null ? "AND ".$joinPrefix.".`".mysql_real_escape_string($this->fieldRoot)."`=".$tablePrefixFull."`".mysql_real_escape_string($this->fieldRoot)."`" : "");
        $arGroup[] = $tablePrefixFull."`".mysql_real_escape_string($this->primaryKey)."`"; 
        $arHaving[] = "COUNT(".$joinPrefix.".`".mysql_real_escape_string($this->primaryKey)."`)=".((int)$targetLevel - 1);
      }
    }
    return true;
  }

  /**
   * Get a list of all child ids of the given parent in the correct sort order
   * @param int $parentId Id of the parent node
   * @param bool $recursive Recursively get all nested childs as well?
   * @return array
   */
  public function fetchChildIds($parentId, $recursive = false, $rootId = 1)
  {
    if ($this->getChildsQuery($parentId, $arJoins, $arWhere, $arOrder, $arHaving, $recursive, "t", $rootId)) {
      $query = "
        SELECT t.`".mysql_real_escape_string($this->primaryKey)."`
        FROM `".mysql_real_escape_string($this->table)."` t
        ".(!empty($arJoins) ? implode("\n     ", $arJoins) : "")."
        ".(!empty($arWhere) ? "WHERE ".implode(" AND ", $arWhere) : "")."
        ".(!empty($arGroup) ? "GROUP BY ".implode(", ", $arGroup) : "")."
        ".(!empty($arHaving) ? "HAVING ".implode(" AND ", $arHaving) : "")."
        ".(!empty($arOrder) ? "ORDER BY " . implode(", ", $arOrder) : "");
      return $this->db->fetch_col($query);
    }
    return false;
  }

  /**
   * Get a list of all childs (all fields) of the given parent in the correct sort order
   * @param int $parentId Id of the parent node
   * @param bool $recursive Recursively get all nested childs as well?
   * @return array
   */
  public function fetchChilds($parentId, $recursive = false, $rootId = 1)
  {
    if ($this->getChildsQuery($parentId, $arJoins, $arWhere, $arOrder, $arHaving, $recursive, "t", $rootId)) {
      $query = "
        SELECT t.*
        FROM `".mysql_real_escape_string($this->table)."`
        ".(!empty($arJoins) ? implode("\n     ", $arJoins) : "")."
        ".(!empty($arWhere) ? "WHERE ".implode(" AND ", $arWhere) : "")."
        ".(!empty($arGroup) ? "GROUP BY ".implode(", ", $arGroup) : "")."
        ".(!empty($arHaving) ? "HAVING ".implode(" AND ", $arHaving) : "")."
        ".(!empty($arOrder) ? "ORDER BY " . implode(", ", $arOrder) : "");
      return $this->db->fetch_table($query);
    }
    return false;
  }

  /**
   * Move the given node inside of the target node (after the last child)
   * @param int $nodeId Id of the node to be moved
   * @param int|null $targetId Id of the target node to move inside (null for root)
   * @param bool $moveChilds Move the child nodes as well?
   * @return bool
   */
  public function moveInto($nodeId, $targetId, $moveChilds = true)
  {
    // Get node position
    $arNode = $this->fetchPosition($nodeId);
    // Get target position
    $arTarget = $this->fetchPosition($targetId);
    // Error checks
    if (($arTarget["LEFT"] >= $arNode["LEFT"]) && ($arTarget["RIGHT"] <= $arNode["RIGHT"])) {
      throw new Exception("[Api_TreeNestedSet/moveInto] Target node is equal/child of moving node!");
    }
    // Make room within tree
    if (!$this->changeNodeValues($arTarget["RIGHT"], null, ($arNode["RIGHT"] - $arNode["LEFT"]), $arNode["ROOT"])) {
      return false;
    }
    // Move node to target position
    if (!$this->changeNodeValues($arNode["LEFT"], $arNode["RIGHT"], $arTarget["RIGHT"] - $arNode["LEFT"])) {
      return false;
    }
    // TODO: Update level
    // Clear space from original position
    if (!$this->changeNodeValues($arNode["LEFT"], null, ($arNode["LEFT"] - $arNode["RIGHT"]), $arNode["ROOT"])) {
      return false;
    }
    return true;
  }

  /**
   * Move the given node before the target node
   * @param int $nodeId Id of the node to be moved
   * @param int $targetId Id of the target node to move before
   * @param bool $moveChilds Move the child nodes as well?
   * @return bool
   */
  public function moveBefore($nodeId, $targetId, $moveChilds = true)
  {
    // Get node position
    $arNode = $this->fetchPosition($nodeId);
    // Get target position
    $arTarget = $this->fetchPosition($targetId);
    // Error checks
    if (($arTarget["LEFT"] >= $arNode["LEFT"]) && ($arTarget["RIGHT"] <= $arNode["RIGHT"])) {
      throw new Exception("[Api_TreeNestedSet/moveInto] Target node is equal/child of moving node!");
    }
    // Make room within tree
    if (!$this->changeNodeValues($arTarget["LEFT"], null, ($arNode["RIGHT"] - $arNode["LEFT"]), $arNode["ROOT"])) {
      return false;
    }
    // Move node to target position
    if (!$this->changeNodeValues($arNode["LEFT"], $arNode["RIGHT"], $arTarget["LEFT"] - $arNode["LEFT"])) {
      return false;
    }
    // TODO: Update level
    // Clear space from original position
    if (!$this->changeNodeValues($arNode["LEFT"], null, ($arNode["LEFT"] - $arNode["RIGHT"]), $arNode["ROOT"])) {
      return false;
    }
    return true;
  }

  /**
   * Move the given node after the target node
   * @param int $nodeId Id of the node to be moved
   * @param int $targetId Id of the target node to move before
   * @param bool $moveChilds Move the child nodes as well?
   * @return bool
   */
  public function moveAfter($nodeId, $targetId, $moveChilds = true)
  {
    // Get node position
    $arNode = $this->fetchPosition($nodeId);
    // Get target position
    $arTarget = $this->fetchPosition($targetId);
    // Error checks
    if (($arTarget["LEFT"] >= $arNode["LEFT"]) && ($arTarget["RIGHT"] <= $arNode["RIGHT"])) {
      throw new Exception("[Api_TreeNestedSet/moveInto] Target node is equal/child of moving node!");
    }
    // Make room within tree
    if (!$this->changeNodeValues($arTarget["RIGHT"] + 1, null, ($arNode["RIGHT"] - $arNode["LEFT"]), $arNode["ROOT"])) {
      return false;
    }
    // Move node to target position
    if (!$this->changeNodeValues($arNode["LEFT"], $arNode["RIGHT"], $arTarget["RIGHT"] + 1 - $arNode["LEFT"])) {
      return false;
    }
    // TODO: Update level
    // Clear space from original position
    if (!$this->changeNodeValues($arNode["LEFT"], null, ($arNode["LEFT"] - $arNode["RIGHT"]), $arNode["ROOT"])) {
      return false;
    }
    return true;
  }

  /**
   * Move all child nodes outside of the given parent (insert before parent)
   * @param int $nodeId Id of the parent node
   * @return mixed
   */
  public function moveChildsBefore($nodeId)
  {
    // Get node position
    $arNode = $this->fetchPosition($nodeId);
    // Do childs exist?
    if (($arNode["RIGHT"] - $arNode["LEFT"]) <= 1) {
      return true;
    }
    // Move childs
    if (!$this->changeNodeValues($arNode["LEFT"] + 1, $arNode["RIGHT"] - 1, -1, $arNode["ROOT"])) {
      return false;
    }
    // TODO: Update levels
    // Move parent
    if (!$this->changeNodeValue($nodeId, $arNode["RIGHT"], $arNode["RIGHT"] + 1)) {
      return false;
    }
    return true;
  }

  /**
   * Move all child nodes outside of the given parent (insert after parent)
   * @param int $nodeId Id of the parent node
   * @return mixed
   */
  public function moveChildsAfter($nodeId)
  {
    // Get node position
    $arNode = $this->fetchPosition($nodeId);
    // Do childs exist?
    if (($arNode["RIGHT"] - $arNode["LEFT"]) <= 1) {
      return true;
    }
    // Move childs
    if (!$this->changeNodeValues($arNode["LEFT"] + 1, $arNode["RIGHT"] - 1, 1, $arNode["ROOT"])) {
      return false;
    }
    // TODO: Update levels
    // Move parent
    if (!$this->changeNodeValue($nodeId, $arNode["LEFT"], $arNode["LEFT"] + 1)) {
      return false;
    }
    return true;
  }
}