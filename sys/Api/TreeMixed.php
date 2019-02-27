<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 14.11.16
 * Time: 17:52
 */

class Api_TreeMixed implements Api_Tree {

  protected $db;
  protected $table;
  protected $primaryKey;
  protected $fieldParent;
  protected $fieldSort;
  protected $fieldLeft;
  protected $fieldRight;
  protected $fieldLevel;
  protected $fieldRoot;
  
  protected $treeNS;
  protected $treePP;
  
  function __construct(ebiz_db $db, $table, $fieldParent = "FK_PARENT", $fieldSort = "SORT_INDEX", $fieldLeft = "NS_LEFT", $fieldRight = "NS_RIGHT", $fieldLevel = "NS_LEVEL", $fieldRoot = null, $primaryKey = null) {
    $this->db = $db;
    $this->table = $table;
    $this->primaryKey = ($primaryKey !== null ? $primaryKey : "ID_".strtoupper($table));
    $this->fieldParent = $fieldParent;
    $this->fieldSort = $fieldSort;
    $this->fieldLeft = $fieldLeft;
    $this->fieldRight = $fieldRight;
    $this->fieldLevel = $fieldLevel;
    $this->fieldRoot = $fieldRoot;
    // Initialize type trees
    $this->treeNS = new Api_TreeNestedSet($db, $this->table, $this->fieldLeft, $this->fieldRight, $this->fieldLevel, $this->fieldRoot, $this->primaryKey);
    $this->treePP = new Api_TreeParentPos($db, $this->table, $this->fieldParent, $this->fieldSort, $this->fieldRoot, $this->primaryKey);
  }
  
  private function updateTreeNestedSet($rootId = 1, $parentId = null, $level = 0, &$left = 0, &$right = 1) {
    $parentLeft = $left;
    // Update childs
    $arNodeIds = $this->treePP->fetchChildIds($parentId, false, $rootId);
    if (!empty($arNodeIds)) {
      $left = $right;
      $right = $left + 1;
      foreach ($arNodeIds as $nodeIndex => $nodeId) {
        $this->updateTreeNestedSet($rootId, $nodeId, $level + 1, $left, $right);
        $left = $right + 1;
        $right = $left + 1;
      }
      $right = $left;
    }
    // Update parent
    if ($parentId !== null) {
      $parentRight = $right;
      $result = $this->db->querynow("
        UPDATE `".mysql_real_escape_string($this->table)."`
        SET `".mysql_real_escape_string($this->fieldLeft)."`=".(int)$parentLeft.", `".mysql_real_escape_string($this->fieldRight)."`=".(int)$parentRight.
          ($this->fieldLevel !== null ? ",\n        `".mysql_real_escape_string($this->fieldLevel)."`=".(int)$level : "")."
        WHERE `".mysql_real_escape_string($this->primaryKey)."`=".(int)$parentId);
      return $result["rsrc"];
    } else {
      return true;
    }
  }
  
  private function updateTreeSortIndex($parentId = null, $recursive = false, $rootId = 1) {
    // Update childs
    $arNodeIds = $this->treeNS->fetchChildIds($parentId, false, $rootId);
    foreach ($arNodeIds as $nodeIndex => $nodeId) {
      $this->db->querynow("
        UPDATE `".mysql_real_escape_string($this->table)."`
        SET `".mysql_real_escape_string($this->fieldSort)."`=".((int)$nodeIndex + 1)."
        WHERE `".mysql_real_escape_string($this->primaryKey)."`=".(int)$nodeId);
      if ($recursive) {
        $this->updateTreeSortIndex($nodeId, true, $rootId);
      }
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
    if ($this->treePP->addChild($parentId, $arData, $rootId)) {
      $this->updateTreeNestedSet($rootId);
      return true;
    }
    return false;
  }

  /**
   * Add new node before the given target
   * @param int $nodeId Id of the reference node to insert before
   * @param array $arData Array containing additional data of the node
   * @return int                        Id of the new node
   */
  public function addBefore($nodeId, $arData, $rootId = 1)
  {
    if ($this->treePP->addBefore($nodeId, $arData, $rootId)) {
      $this->updateTreeNestedSet($rootId);
      return true;
    }
    return false;
  }

  /**
   * Add new node after the given target
   * @param int $nodeId Id of the reference node to insert after
   * @param array $arData Array containing additional data of the node
   * @return int                        Id of the new node
   */
  public function addAfter($nodeId, $arData, $rootId = 1)
  {
    if ($this->treePP->addAfter($nodeId, $arData, $rootId)) {
      $this->updateTreeNestedSet($rootId);
      return true;
    }
    return false;
  }

  /**
   * Delete the given node from the tree
   * @param int $nodeId Id of the node to be deleted
   * @param bool $deleteChilds Delete the child nodes as well?
   * @return bool                       True on success, false otherwise
   */
  public function delete($nodeId, $deleteChilds = true, $rootId = 1)
  {
    if ($this->treeNS->delete($nodeId, $deleteChilds)) {
      $this->updateTreeNestedSet($rootId);
      $this->updateTreeSortIndex();
      return true;
    }
    return false;
  }

  /**
   * Delete all childs of the given node
   * @param int $nodeId Id of the node to delete the childs from
   * @return bool                       True on success, false otherwise
   */
  public function deleteChilds($nodeId, $rootId = 1)
  {
    if ($this->treeNS->deleteChilds($nodeId)) {
      $this->updateTreeNestedSet($rootId);
      $this->updateTreeSortIndex();
      return true;
    }
    return false;
  }

  /**
   * Get all query parts for selecting the child nodes
   * @param $parentId
   * @param $arJoins
   * @param $arWhere
   * @param $arOrder
   * @param $arHaving
   * @param bool $recursive
   * @param int $rootId
   * @param string $tablePrefix
   * @return mixed
   */
  public function getChildsQuery($parentId, &$arJoins, &$arWhere, &$arOrder, &$arHaving, $recursive = false, $tablePrefix = null, $rootId = 1)
  {
    if ($recursive) {
      return $this->treeNS->getChildsQuery($parentId, $arJoins, $arWhere, $arOrder, $arHaving, $recursive, $tablePrefix, $rootId);
    } else {
      return $this->treePP->getChildsQuery($parentId, $arJoins, $arWhere, $arOrder, $arHaving, $recursive, $tablePrefix, $rootId);
    }
  }

  /**
   * Get a list of all child ids of the given parent in the correct sort order
   * @param int $parentId Id of the parent node
   * @param bool $recursive Recursively get all nested childs as well?
   * @return array
   */
  public function fetchChildIds($parentId, $recursive = false, $rootId = 1)
  {
    if ($recursive) {
      return $this->treeNS->fetchChildIds($parentId, true, $rootId);
    } else {
      return $this->treePP->fetchChildIds($parentId, false, $rootId);
    }
  }

  /**
   * Get a list of all childs (all fields) of the given parent in the correct sort order
   * @param int $parentId Id of the parent node
   * @param bool $recursive Recursively get all nested childs as well?
   * @return array
   */
  public function fetchChilds($parentId, $recursive = false, $rootId = 1)
  {
    if ($recursive) {
      return $this->treeNS->fetchChilds($parentId, true, $rootId);
    } else {
      return $this->treePP->fetchChilds($parentId, false, $rootId);
    }
  }

  /**
   * Move the given node inside of the target node (after the last child)
   * @param int $nodeId Id of the node to be moved
   * @param int|null $targetId Id of the target node to move inside (null for root)
   * @param bool $moveChilds Move the child nodes as well?
   * @return bool
   */
  public function moveInto($nodeId, $targetId, $moveChilds = true, $rootId = 1)
  {
    if ($this->treePP->moveInto($nodeId, $targetId, $moveChilds)) {
      $this->updateTreeNestedSet($rootId);
      return true;
    }
    return false;
  }

  /**
   * Move the given node before the target node
   * @param int $nodeId Id of the node to be moved
   * @param int $targetId Id of the target node to move before
   * @param bool $moveChilds Move the child nodes as well?
   * @return bool
   */
  public function moveBefore($nodeId, $targetId, $moveChilds = true, $rootId = 1)
  {
    if ($this->treePP->moveBefore($nodeId, $targetId, $moveChilds)) {
      $this->updateTreeNestedSet($rootId);
      return true;
    }
    return false;
  }

  /**
   * Move the given node after the target node
   * @param int $nodeId Id of the node to be moved
   * @param int $targetId Id of the target node to move before
   * @param bool $moveChilds Move the child nodes as well?
   * @return bool
   */
  public function moveAfter($nodeId, $targetId, $moveChilds = true, $rootId = 1)
  {
    if ($this->treePP->moveAfter($nodeId, $targetId, $moveChilds)) {
      $this->updateTreeNestedSet($rootId);
      return true;
    }
    return false;
  }

  /**
   * Move all child nodes outside of the given parent (insert before parent)
   * @param int $nodeId Id of the parent node
   * @return mixed
   */
  public function moveChildsBefore($nodeId, $rootId = 1)
  {
    if ($this->treePP->moveChildsBefore($nodeId)) {
      $this->updateTreeNestedSet($rootId);
      return true;
    }
    return false;
  }

  /**
   * Move all child nodes outside of the given parent (insert after parent)
   * @param int $nodeId Id of the parent node
   * @return mixed
   */
  public function moveChildsAfter($nodeId, $rootId = 1)
  {
    if ($this->treePP->moveChildsAfter($nodeId)) {
      $this->updateTreeNestedSet($rootId);
      return true;
    }
    return false;
  }

  public function debug()
  {
    $this->updateTreeSortIndex();
  }
}