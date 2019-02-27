<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 14.11.16
 * Time: 18:15
 */

interface Api_Tree {

  /**
   * Add new child node (after the last child)
   * @param int|null  $parentId         Id of the parent element
   * @param array     $arData           Array containing additional data of the node
   * @return int                        Id of the new node
   */
  public function addChild($parentId, $arData);

  /**
   * Add new node before the given target
   * @param int       $nodeId           Id of the reference node to insert before
   * @param array     $arData           Array containing additional data of the node
   * @return int                        Id of the new node
   */
  public function addBefore($nodeId, $arData);

  /**
   * Add new node after the given target
   * @param int       $nodeId           Id of the reference node to insert after
   * @param array     $arData           Array containing additional data of the node
   * @return int                        Id of the new node
   */
  public function addAfter($nodeId, $arData);

  /**
   * Delete the given node from the tree
   * @param int       $nodeId           Id of the node to be deleted
   * @param bool      $deleteChilds     Delete the child nodes as well?
   * @return bool                       True on success, false otherwise
   */
  public function delete($nodeId, $deleteChilds = true);  

  /**
   * Delete all childs of the given node
   * @param int       $nodeId           Id of the node to delete the childs from
   * @return bool                       True on success, false otherwise
   */
  public function deleteChilds($nodeId);

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
  public function getChildsQuery($parentId, &$arJoins, &$arWhere, &$arOrder, &$arHaving, $recursive = false, $tablePrefix = "t", $rootId = 1);
  
  /**
   * Get a list of all child ids of the given parent in the correct sort order
   * @param int       $parentId         Id of the parent node
   * @param bool      $recursive        Recursively get all nested childs as well?
   * @return array
   */
  public function fetchChildIds($parentId, $recursive = false);

  /**
   * Get a list of all childs (all fields) of the given parent in the correct sort order
   * @param int       $parentId         Id of the parent node
   * @param bool      $recursive        Recursively get all nested childs as well?
   * @return array
   */
  public function fetchChilds($parentId, $recursive = false);
  
  /**
   * Move the given node inside of the target node (after the last child)
   * @param int       $nodeId           Id of the node to be moved
   * @param int|null  $targetId         Id of the target node to move inside (null for root) 
   * @param bool      $moveChilds       Move the child nodes as well?
   * @return bool
   */
  public function moveInto($nodeId, $targetId, $moveChilds = true);
  
  /**
   * Move the given node before the target node
   * @param int       $nodeId           Id of the node to be moved
   * @param int       $targetId         Id of the target node to move before 
   * @param bool      $moveChilds       Move the child nodes as well?
   * @return bool
   */
  public function moveBefore($nodeId, $targetId, $moveChilds = true);
  
  /**
   * Move the given node after the target node
   * @param int       $nodeId           Id of the node to be moved
   * @param int       $targetId         Id of the target node to move before 
   * @param bool      $moveChilds       Move the child nodes as well?
   * @return bool
   */
  public function moveAfter($nodeId, $targetId, $moveChilds = true);

  /**
   * Move all child nodes outside of the given parent (insert before parent)
   * @param int       $nodeId           Id of the parent node
   * @return mixed
   */
  public function moveChildsBefore($nodeId);

  /**
   * Move all child nodes outside of the given parent (insert after parent)
   * @param int       $nodeId           Id of the parent node
   * @return mixed
   */
  public function moveChildsAfter($nodeId);
  
}