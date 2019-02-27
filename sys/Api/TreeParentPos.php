<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 14.11.16
 * Time: 17:52
 */

class Api_TreeParentPos implements Api_Tree {

  protected $db;
  protected $table;
  protected $primaryKey;
  protected $fieldParent;
  protected $fieldSort;
  protected $fieldRoot;
  
  function __construct(ebiz_db $db, $table, $fieldParent = "FK_PARENT", $fieldSort = "SORT_INDEX", $fieldRoot = null, $primaryKey = null) {
    $this->db = $db;
    $this->table = $table;
    $this->primaryKey = ($primaryKey !== null ? $primaryKey : "ID_".strtoupper($table));
    $this->fieldParent = $fieldParent;
    $this->fieldSort = $fieldSort;
    $this->fieldRoot = $fieldRoot;
  }

  /**
   * Add a new node with the given parent and sort index
   * @param int|null    $parentId
   * @param int         $sortIndex
   * @param array       $arData
   * @return bool|int
   */
  private function add($parentId, $sortIndex, $arData, $rootId = 1) {
    // Prepare insert
    $arData[ $this->fieldParent ] = $parentId;
    $arData[ $this->fieldSort ] = $sortIndex;
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
  
  private function deleteNodes($arNodeIds) {
    if (empty($arNodeIds)) {
      return true;
    }
    $result = $this->db->querynow("
        DELETE FROM `".mysql_real_escape_string($this->table)."`
        WHERE `".mysql_real_escape_string($this->primaryKey)."` IN (".implode(", ", $arNodeIds).")");
    return $result["rsrc"];
  }
  
  private function fetchPosition($nodeId, $includeChildCount = false) {
    return $this->db->fetch1("
      SELECT
        ".(!$includeChildCount ? "" : "
          (
            SELECT COUNT(*) FROM `".mysql_real_escape_string($this->table)."` c 
            WHERE c.`".mysql_real_escape_string($this->fieldParent)."`=p.`".mysql_real_escape_string($this->primaryKey)."`
          ) AS CHILD_COUNT"
        )."
        p.`".mysql_real_escape_string($this->fieldParent)."` AS FK_PARENT,
        p.`".mysql_real_escape_string($this->fieldSort)."` AS SORT_INDEX,
        ".($this->fieldRoot !== null ? "p.`".mysql_real_escape_string($this->fieldRoot)."` AS ROOT" : "1 AS ROOT")."
      FROM `".mysql_real_escape_string($this->table)."` p
      WHERE p.`".mysql_real_escape_string($this->primaryKey)."`=".(int)$nodeId);
  }
  
  private function getChildIdsSorted($parentId, $recursive = false, $rootId = 1) {
    $arWhere = array();
    // Add root condition?
    if ($parentId === null) {
      if ($this->fieldRoot !== null) {
        $arWhere[] = "`".mysql_real_escape_string($this->fieldRoot)."`=".(int)$rootId;
      }
      $arWhere[] = "`".mysql_real_escape_string($this->fieldParent)."` IS NULL";
    } else {
      $arWhere[] = "`".mysql_real_escape_string($this->fieldParent)."`=".(int)$parentId;
    }
    // Get direct child ids in correct sort order 
    $arChilds = $this->db->fetch_col("
      SELECT `".mysql_real_escape_string($this->primaryKey)."`
      FROM `".mysql_real_escape_string($this->table)."`
      ".(!empty($arWhere) ? "WHERE ".implode(" AND ", $arWhere) : "")."
      ORDER BY `".mysql_real_escape_string($this->fieldSort)."` ASC");
    if ($recursive && !empty($arChilds)) {
      // Add childs recursively
      for ($childIndex = count($arChilds) - 1; $childIndex >= 0; $childIndex--) {
        array_splice($arChilds, $childIndex+1, 0, $this->getChildIdsSorted( $arChilds[$childIndex], true ));
      }
    }
    return $arChilds;
  }
  
  private function getChildIdsUnsorted($parentIds, $recursive = false, $rootId = 1) {
    $arWhere = array();
    // Add root condition?
    // Get child ids without obeying sort order 
    if (is_array($parentIds)) {
      $parentIdsEscaped = array();
      foreach ($parentIds as $parentIndex => $parentId) {
        $parentIdsEscaped[] = (int)$parentId;
      }
      $arWhere[] = "`".mysql_real_escape_string($this->fieldParent)."` IN (".implode(", ", $parentIdsEscaped).")";
    } else {
      if ($parentIds === null) {
        if ($this->fieldRoot !== null) {
          $arWhere[] = "`".mysql_real_escape_string($this->fieldRoot)."`=".(int)$rootId;
        }
        $arWhere[] = "`".mysql_real_escape_string($this->fieldParent)."` IS NULL";
      } else {
        $arWhere[] = "`".mysql_real_escape_string($this->fieldParent)."`=".(int)$parentIds;
      }
    }
    $arChilds = $this->db->fetch_col("
      SELECT `".mysql_real_escape_string($this->primaryKey)."`
      FROM `".mysql_real_escape_string($this->table)."`
      ".(!empty($arWhere) ? "WHERE ".implode(" AND ", $arWhere) : ""));
    if (!empty($arChilds) && $recursive) {
      $arChilds = array_merge($arChilds, $this->getChildIdsUnsorted($arChilds));
    }
    return $arChilds;
  }
  
  private function getNextChildSortIndex($parentId, $rootId = 1) {
    // Calculate desired sort index
    $sortIndexMax = $this->db->fetch_atom("
      SELECT MAX(`".mysql_real_escape_string($this->fieldSort)."`) 
      FROM `".mysql_real_escape_string($this->table)."`
      WHERE `".mysql_real_escape_string($this->fieldParent)."`".($parentId !== null ? "=".(int)$parentId : " IS NULL")).
        ($this->fieldRoot !== null ? " AND `".mysql_real_escape_string($this->fieldRoot)."`=".(int)$rootId : "");
    return ($sortIndexMax === null ? 1 : $sortIndexMax + 1);
  }
  
  private function increaseChildSort($parentId, $sortIndexStart = 1, $amount = 1, $rootId = 1) {
    $result = $this->db->querynow("
      UPDATE `".mysql_real_escape_string($this->table)."`
      SET `".mysql_real_escape_string($this->fieldSort)."`=`".mysql_real_escape_string($this->fieldSort)."` + ".(int)$amount."
      WHERE `".mysql_real_escape_string($this->fieldParent)."`".($parentId !== null ? "=".(int)$parentId : " IS NULL")."
        AND `".mysql_real_escape_string($this->fieldSort)."`>=".(int)$sortIndexStart).
        ($this->fieldRoot !== null ? " AND `".mysql_real_escape_string($this->fieldRoot)."`=".(int)$rootId : "");
    return $result["rsrc"];
  }
  
  private function decreaseChildSort($parentId, $sortIndexStart = 1, $amount = 1, $rootId = 1) {
    $result = $this->db->querynow("
      UPDATE `".mysql_real_escape_string($this->table)."`
      SET `".mysql_real_escape_string($this->fieldSort)."`=`".mysql_real_escape_string($this->fieldSort)."` - ".(int)$amount."
      WHERE `".mysql_real_escape_string($this->fieldParent)."`".($parentId !== null ? "=".(int)$parentId : " IS NULL")."
        AND `".mysql_real_escape_string($this->fieldSort)."`>=".(int)$sortIndexStart).
        ($this->fieldRoot !== null ? " AND `".mysql_real_escape_string($this->fieldRoot)."`=".(int)$rootId : "");
    return $result["rsrc"];
  }
  
  private function updatePosition($nodeId, $parentId, $sortIndex) {
    $result = $this->db->querynow("
      UPDATE `".mysql_real_escape_string($this->table)."`
      SET 
        `".mysql_real_escape_string($this->fieldParent)."`=".($parentId !== null ? (int)$parentId : "NULL").",
        `".mysql_real_escape_string($this->fieldSort)."`=".(int)$sortIndex."
      WHERE `".mysql_real_escape_string($this->primaryKey)."`=".(int)$nodeId);
    return $result["rsrc"];
  }
  
  private function updateParent($parentIdOld, $parentIdNew, $sortIndexOffset = 0) {
    $result = $this->db->querynow("
      UPDATE `".mysql_real_escape_string($this->table)."`
      SET 
        `".mysql_real_escape_string($this->fieldParent)."`=".($parentIdNew !== null ? (int)$parentIdNew : "NULL").
        ($sortIndexOffset == 0 ? "" : ",
          `".mysql_real_escape_string($this->fieldSort)."`=`".mysql_real_escape_string($this->fieldSort)."`".($sortIndexOffset >= 0 ? " + ".(int)$sortIndexOffset : " - ".abs((int)$sortIndexOffset)))."
      WHERE `".mysql_real_escape_string($this->fieldParent)."`".($parentIdOld !== null ? "=".(int)$parentIdOld : " IS NULL"));
    return $result["rsrc"];
  }

  /**
   * Add new child node (after the last child)
   * @param int|null $parentId Id of the parent element
   * @param array $arData Array containing additional data of the node
   * @return int                        Id of the new node
   */
  public function addChild($parentId, $arData, $rootId = 1)
  {
    // Add new node
    return $this->add($parentId, $this->getNextChildSortIndex($parentId, $rootId), $arData, $rootId);
  }

  /**
   * Add new node before the given target
   * @param int $nodeId Id of the reference node to insert before
   * @param array $arData Array containing additional data of the node
   * @return int                        Id of the new node
   */
  public function addBefore($nodeId, $arData, $rootId = 1)
  {
    // Get parent and sort index of target node
    $arTarget = $this->fetchPosition($nodeId);
    // Update sort order of following nodes
    if (!$this->increaseChildSort($arTarget["FK_PARENT"], $arTarget["SORT_INDEX"], 1, $rootId)) {
      return false;
    }
    // Add new node
    return $this->add($arTarget["FK_PARENT"], $arTarget["SORT_INDEX"], $arData, $rootId);
  }

  /**
   * Add new node after the given target
   * @param int $nodeId Id of the reference node to insert after
   * @param array $arData Array containing additional data of the node
   * @return int                        Id of the new node
   */
  public function addAfter($nodeId, $arData, $rootId = 1)
  {
    // Get parent and sort index of target node
    $arTarget = $this->fetchPosition($nodeId);
    // Update sort order of following nodes
    if (!$this->increaseChildSort($arTarget["FK_PARENT"], $arTarget["SORT_INDEX"]+1, 1, $rootId)) {
      return false;
    }
    // Add new node
    return $this->add($arTarget["FK_PARENT"], $arTarget["SORT_INDEX"]+1, $arData, $rootId);
  }

  /**
   * Delete the given node from the tree
   * @param int $nodeId Id of the node to be deleted
   * @param bool $deleteChilds Delete the child nodes as well?
   * @return bool                       True on success, false otherwise
   */
  public function delete($nodeId, $deleteChilds = true)
  {
    // Get parent and sort index of moving node
    $arNode = $this->fetchPosition($nodeId);
    // Update sort order of following nodes (remove from parent)
    if (!$this->decreaseChildSort($arNode["FK_PARENT"], $arNode["SORT_INDEX"]+1, 1, $arNode["ROOT"])) {
      return false;
    }
    // Delete node (and childs if requested)
    if ($deleteChilds) {
      $arChildIds = $this->getChildIdsUnsorted($nodeId, true, $arNode["ROOT"]);
      $arChildIds[] = $nodeId;
      return $this->deleteNodes( $arChildIds );
    } else {
      if (!$this->moveChildsBefore($nodeId)) {
        return false;
      }
      return $this->deleteNodes( array($nodeId) );
    }
  }

  /**
   * Delete all childs of the given node
   * @param int $nodeId Id of the node to delete the childs from
   * @return bool                       True on success, false otherwise
   */
  public function deleteChilds($nodeId, $rootId = 1)
  {
    return $this->deleteNodes( $this->getChildIdsUnsorted($nodeId, true, $rootId) );
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
    // Generate query parts
    if ($recursive) {
      $arChildIds = $this->fetchChildIds($parentId, $recursive, $rootId);
      $arWhere[] = ($tablePrefix !== null ? $tablePrefix."." : "")."`".mysql_real_escape_string($this->primaryKey)."` IN (".implode(", ", $arChildIds).")";
    } else {
      $arWhere[] = ($tablePrefix !== null ? $tablePrefix."." : "")."`".mysql_real_escape_string($this->fieldParent)."`=".(int)$parentId;
      $arOrder[] = ($tablePrefix !== null ? $tablePrefix."." : "")."`".mysql_real_escape_string($this->fieldSort)."` ASC";
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
    return $this->getChildIdsSorted($parentId, $recursive, $rootId);
  }

  /**
   * Get a list of all childs (all fields) of the given parent in the correct sort order
   * @param int $parentId Id of the parent node
   * @param bool $recursive Recursively get all nested childs as well?
   * @return array
   */
  public function fetchChilds($parentId, $recursive = false, $rootId = 1)
  {
    if ($this->getChildsQuery($parentId, $arJoins, $arWhere, $arOrder, $arHaving, $recursive, null, $rootId)) {
      $query = "
        SELECT *
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
    // Get parent and sort index of moving node
    $arNode = $this->fetchPosition($nodeId);
    // Update sort order of following nodes (remove from old parent)
    if (!$this->decreaseChildSort($arNode["FK_PARENT"], $arNode["SORT_INDEX"]+1, 1, $arNode["ROOT"])) {
      return false;
    }
    // Move node into new parent
    return $this->updatePosition($nodeId, $targetId, $this->getNextChildSortIndex($targetId, $arNode["ROOT"]));
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
    // Get parent and sort index of moving node
    $arNode = $this->fetchPosition($nodeId);
    // Update sort order of following nodes (remove from old parent)
    if (!$this->decreaseChildSort($arNode["FK_PARENT"], $arNode["SORT_INDEX"]+1, 1, $arNode["ROOT"])) {
      return false;
    }
    // Get parent and sort index of target node
    $arTarget = $this->fetchPosition($targetId);
    // Update sort order of following nodes (add to new parent)
    if (!$this->increaseChildSort($arTarget["FK_PARENT"], $arTarget["SORT_INDEX"], 1, $arNode["ROOT"])) {
      return false;
    }
    // Move node before target node
    return $this->updatePosition($nodeId, $targetId, $arTarget["SORT_INDEX"]);
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
    // Get parent and sort index of moving node
    $arNode = $this->fetchPosition($nodeId);
    // Update sort order of following nodes (remove from old parent)
    if (!$this->decreaseChildSort($arNode["FK_PARENT"], $arNode["SORT_INDEX"]+1, 1, $arNode["ROOT"])) {
      return false;
    }
    // Get parent and sort index of target node
    $arTarget = $this->fetchPosition($targetId);
    // Update sort order of following nodes (add to new parent)
    if (!$this->increaseChildSort($arTarget["FK_PARENT"], $arTarget["SORT_INDEX"]+1, 1, $arNode["ROOT"])) {
      return false;
    }
    // Move node after target node
    return $this->updatePosition($nodeId, $targetId, $arTarget["SORT_INDEX"]+1);
  }

  /**
   * Move all child nodes outside of the given parent (insert before parent)
   * @param int $nodeId Id of the parent node
   * @return mixed
   */
  public function moveChildsBefore($nodeId)
  {
    // Get parent and sort index of moving node
    $arNode = $this->fetchPosition($nodeId, true);
    // Update sort order of the parent and all following nodes (add to new parent)
    if (!$this->increaseChildSort($arNode["FK_PARENT"], $arNode["SORT_INDEX"], $arNode["CHILD_COUNT"], $arNode["ROOT"])) {
      return false;
    }
    // Update parent and sort index of childs
    return $this->updateParent($nodeId, $arNode["FK_PARENT"], $arNode["SORT_INDEX"]-1);
  }

  /**
   * Move all child nodes outside of the given parent (insert after parent)
   * @param int $nodeId Id of the parent node
   * @return mixed
   */
  public function moveChildsAfter($nodeId)
  {
    // Get parent and sort index of moving node
    $arNode = $this->fetchPosition($nodeId, true);
    // Update sort order of the parent and all following nodes (add to new parent)
    if (!$this->increaseChildSort($arNode["FK_PARENT"], $arNode["SORT_INDEX"]+1, $arNode["CHILD_COUNT"], $arNode["ROOT"])) {
      return false;
    }
    // Update parent and sort index of childs
    return $this->updateParent($nodeId, $arNode["FK_PARENT"], $arNode["SORT_INDEX"]);
  }
  
  public function updateSorting(Api_TreeNestedSet $tree, $sortIndexStep = 1, $rootId = 1) {
    $sortIndex = $sortIndexStep;
    foreach ($tree->fetchChildIds(null, true, $rootId) as $nodeIndex => $nodeId) {
        $this->db->querynow("
            UPDATE `".mysql_real_escape_string($this->table)."`
            SET `".mysql_real_escape_string($this->fieldSort)."`=".(int)$sortIndex."
            WHERE `".mysql_real_escape_string($this->primaryKey)."`=".(int)$nodeId);
        $sortIndex += $sortIndexStep;
    }
    return true;
  }
}