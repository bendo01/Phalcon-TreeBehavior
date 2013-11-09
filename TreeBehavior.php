<?php

/**
 * Tree behavior class
 * 
 * 
 * PHP 5
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice
 *
 * @category   Behavior
 * @package    Model.Behavior.Tree
 * @author     Benny L.E.P <bendo01@gmail.com>
 * @license    http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace xPDPT\Models\Behavior;

use Phalcon\Mvc\Model\Behavior;
use Phalcon\Mvc\Model\BehaviorInterface;

/**
 * Tree Behavior
 * Enables a model object to act as a node-based tree. Using Modified Preorder Tree Traversal
 * @see     http://en.wikipedia.org/wiki/Tree_traversal
 * @author  Benny L.E.P <bendo01@gmail.com>
 * @package Model.Behavior.Tree
 * @see     http://mikehillyer.com/articles/managing-hierarchical-data-in-mysql/
 * @see     http://www.phpclasses.org/package/5169-PHP-Manipulate-a-tree-node-structure-stored-in-MySQL.html
 * @see     http://www.sitepoint.com/hierarchical-data-database/
 * @see     http://www.phpclasses.org/browse/file/26163.html
 * @license http://opensource.org/licenses/gpl-3.0.html GNU General Public License version 3 (GPLv3)
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */

class TreeBehavior extends Behavior implements BehaviorInterface
{
    /**
     * Temporary node holder
     * @var object
     */
    protected $node;
    
    /**
     * Temporary $properties holder
     * @var array 
     */
    protected $properties;

    public function notify($eventType, $model)
    {
        switch ($eventType) {
            case 'afterDelete':
                $this->removeNodeWithoutChildren($model);
                break;
            case 'afterUpdate':
                $this->rebuildTree($model, 1, null);
                break;
            case 'beforeCreate':
                $this->setNodeProperties($model);
                break;
            default:
        }
    }

    public function missingMethod($model, $method, $arguments = array())
    {
        if ($method == 'startUp') {
            return $this->startUp($model);
        }

        if ($method == 'getChildren') {
            return $this->getChildren($model, $arguments[0], $arguments[1], $arguments[2]);
        }
        
        if ($method == 'getChildrenCount') {
            return $this->getChildrenCount($model, $arguments[0]);
        }
        
        if ($method == 'getDescendantsCount') {
            return $this->getDescendantsCount($model, $arguments[0]);
        }
        
        if ($method == 'getParent') {
            return $this->getParent($model, $arguments[0]);
        }
        
        if ($method == 'getSelectables') {
            if (empty($arguments[0])) {
                $arguments[0] = '-';
            }
            return $this->getSelectables($model, $arguments[0]);
        }
        
        if ($method == 'getAllRoot') {
            return $this->getAllRoot($model, $arguments[0]);
        }
        
        if ($method == 'getTree') {
            return $this->getTree($model);
        }
        
        if ($method == 'rebuildTree') {
            return $this->rebuildTree($model, 1, null);
        }

        if($method == 'getSubtree') {
            return $this->getSubtree($model, $arguments[0]);
        }
        if($method == 'moveLeft') {
            return $this->moveLeft($model, $arguments[0]);
        }
        if($method == 'moveRight') {
            return $this->moveRight($model, $arguments[0]);
        }
    }

    /**
     * 
     * @param string $stringSetterGetter
     * @param string $key
     * @return string
     */
    public function changeToFunctionString($stringSetterGetter = 'get', $key = 'id')
    {
        return $stringSetterGetter.ucfirst($key);
    }

    /**
     * initialiase variable node from table data
     * @param type $model
     * @return boolean
     */
    public function startUp($model)
    {
        $results = null;
        if (!isset($this->properties)) {
            $this->properties = $model->columnMap();
        }

        if (!isset($this->node)) {
            $results = $model->find(
                array(
                    'order' => 'lft ASC'
                )
            );
            $this->node = array();
        }

        if (!empty($results)) {
            foreach ($results as $result) {
                $this->node[$result->getId()] = $result;
            }
        }
        return true;
    }

    /**
     * comparing two node based on left properties
     * @param object $a
     * @param object $b
     * @return object
     */
    public static function cmpLeft($a, $b)
    {
        return $a->lft > $b->lft;
    }

    /**
     * sorting node based on left properties
     * @return boolean
     */
    public function reOrderLookUpArray()
    {
        usort($this->node, array($this,"cmpLeft"));
        return true;
    }

    /**
     * get node children, it can be returning array of node object, or array of array nodes
     * @param object $model
     * @param int $id
     * @param boolean $childrenOnly
     * @param boolean $typeObject
     * @return array
     */
    public function getChildren($model, $id = null, $childrenOnly = false, $typeObject = true)
    {
        $this->startUp($model);
        $parentHasIn = false;
        $arrKeys = $model->columnMap();
        $returnArray = array();
        $children = array();
        // if parent node exists in the lookup array OR we're looking for the topmost nodes
        if (!empty($id) && !empty($this->node[$id])) {
            foreach ($this->node as $node) {
                // node's "left" is higher than parent node's "left"
                // node's "left" is smaller than parent node's "right"
                if (($node->getParentId() == $id) && ($this->node[$id]->getLft() < $node->getLft()) && ($node->getLft() < $this->node[$id]->getRght())) {
                    if (!$parentHasIn && !$childrenOnly) {
                        $children[] = $this->node[$id];
                        $parentHasIn = true;
                    }
                    $children[] = $node;
                }
            }
        }
        $returnArray = $children;
        if(!$typeObject) {
            $returnArray = array();
            if (!empty($children)) {
                $i=0;
                foreach ($children as $child) {
                    foreach ($arrKeys as $key => $value) {
                        $returnArray[$i][$value] = $child->{$this->changeToFunctionString('get', $value)}();
                    }
                    $i++;
                }
            }
        }
        return $returnArray;
    }
    
    /**
     * get children node based on parentId
     * @param object $model
     * @param int $parentId
     * @return object
     */
    public function getChildrenBasedOnParentId($model, $parentId = null)
    {
        $arrKeys = $this->columnMap();
        $returnArray = array();
        $children = array();
        $this->node = $model->find(
                array(
                    'conditions' => 'parentId is null'
                )
        );

        if (!empty($parentId)) {
            $this->node = $model->find(
                array(
                    'conditions' => 'parentId = '.$parentId
                )
            );
        }

        if (!empty($this->node)) {
            foreach ($this->node as $node) {
                // node's "left" is higher than parent node's "left"
                // node's "left" is smaller than parent node's "right"
                if (($node->parentId == $parentId)) {
                    $children[] = $node;
                }
            }
        }

        if (!empty($children)) {
            $i=0;
            foreach ($children as $child) {
                foreach ($arrKeys as $key => $value) {
                    $returnArray[$i][$value] = $child->{$value};
                }
                $i++;
            }
        }
        return $returnArray;
    }
    
    /**
     * count children of node
     * @param object $model
     * @param int $id
     * @return int
     */
    public function getChildrenCount($model, $id = null)
    {
        $result = 0;
        $this->startUp($model);
        if (!empty($this->node) && !empty($id)) {
            $result = 0;
            foreach ($this->node as $node) {
                if ($node->getParentId() == $id) {
                    $result++;
                }
            }
        }
        return $result;
    }
    
    /**
     * count descendat of node
     * @param object $model
     * @param int $id
     * @return int
     */
    public function getDescendantsCount($model, $id = null)
    {
        $result = 0;
        $this->startUp($model);
        if (!empty($this->node) && !empty($id) && !empty($this->node[$id])) {
            $result = ($this->node[$id]->getRght() - $this->node[$id]->getLft() - 1) / 2;
        }
        return $result;
    }
    
    /**
     * get parent node
     * @param object $model
     * @param int $id
     * @return object
     */
    public function getParent($model, $id = null)
    {
        $node = null;
        if (!empty($id)) {
            $result = $model->findFirst($id);
            $node = $model->findFirst($result->getParentId());
        }
        return $node;
    }
    
    /**
     * get node path
     * @param object $model
     * @param int $id
     * @return array object
     */
    public function getPath($model, $id = null)
    {
        $parents = array();
        $this->startUp($model);
        if (!empty($id) && !empty($this->node[$id])) {
            foreach ($this->node as $node) {
                if( ($node->getLft() < $this->node[$id]->getLft()) && ($node->getRght() > $this->node[$id]->getRght())) {
                    $parents[] = $node;
                }
            }
        }
        return $parents;
    }
    
    /**
     * generate separator for getSelectables function
     * @param string $name
     * @param int $count
     * @param char $separator
     * @return string
     */
    public function generateSeparator($name = null, $count = 0, $separator = '-')
    {
        $returnStr = $name;
        if(!empty($name) && $count > 0) {
            $tempStr = '';
            for($i=0; $i<$count; $i++) {
                $tempStr.=$separator;
            }
            $returnStr = $tempStr.$name;
        }
        return $returnStr;
    }
    
    /**
     * generate list for input form 
     * @param object $model
     * @param char $separator
     * @return array
     */
    public function getSelectables($model, $separator = '-')
    {
        $this->startUp($model);
        $returnArray = array();
        foreach ($this->node as $node) {
            $returnArray[$node->getId()] = $this->generateSeparator($node->getName(), count($this->getPath($model, $node->getId())), $separator);
        }
        return $returnArray;
    }

    /**
     * get all root type node in table data
     * @param object $model
     * @param boolean $typeObject
     * @return array object
     */
    public function getAllRoot($model, $typeObject = true)
    {
        $returnArray = null;
        $arrKeys = $model->columnMap();
        $rootArr = $model->find(
            array(
                'conditions' => 'parentId is null',
                'order' => 'lft ASC'
            )
        );

        $returnArray = $rootArr;
        
        if(!$typeObject) {
            $returnArray = null;
            if (!empty($rootArr)) {
                $i=0;
                foreach ($rootArr as $root) {
                    foreach ($arrKeys as $key => $value) {
                        $returnArray[$i][$value] = $root->{$this->changeToFunctionString('get', $value)}();
                    }
                    $i++;
                }
            }
        }
        return $returnArray;
    }


    public function getLastRoot($model, $typeObject = true)
    {
        $node = null;
        $rootArr = $this->getAllRoot($model, $typeObject);
        if (!empty($rootArr[count($rootArr) -1])) {
            $node = $rootArr[count($rootArr) -1];
        }
        return $node;
    }
    
    /**
     * get subtree of node
     * @param object $model
     * @param array $childrenData
     * @return array
     */
    public function getSubtree($model, $childrenData = array())
    {
        if(!empty($childrenData)) {
            $i = 0;
            foreach ($childrenData as $childData) {
                if ($this->getChildrenCount($model, $childData['id']) > 0 ) {
                    $childrenData[$i]['children'] = $this->getChildren($model, $childData['id'], true, false);
                    $childrenData[$i]['children'] = $this->getSubtree($model, $childrenData[$i]['children']);
                }
                $i++;
            }
        }
        return $childrenData;
    }

    /**
     * get tree from table data
     * @param object $model
     * @return array
     */
    public function getTree($model)
    {
        $roots = $this->getAllRoot($model, false);
        if (!empty($roots)) {
            $i=0;
            foreach ($roots as $root) {
                if ($this->getChildrenCount($model, $root['id']) > 0 ) {
                    $roots[$i]['children'] = $this->getChildren($model, $root['id'], true, false);
                    $roots[$i]['children'] = $this->getSubtree($model, $roots[$i]['children']);
                }
                $i++;
            }    
        }
        return $roots;
    }
    
    /**
     * get children of node for rebuildTree function
     * @param object $model
     * @param int $parentId
     * @return object
     */
    public function getChildForRebuildTree($model, $parentId = null)
    {
        $returnArray = null;
        $arrKeys = $model->columnMap();
        $returnArray = array();
        $children = null;

        if (!empty($parentId)) {
            $children = $model->find(
                array(
                    'conditions' => 'parentId = '.$parentId,
                    'order' => 'id'
                )
            );

            $returnArray = array();
            if (!empty($children)) {
                $i=0;
                foreach ($children as $child) {
                    foreach ($arrKeys as $key => $value) {
                        $returnArray[$i][$value] = $child->{$this->changeToFunctionString('get', $value)}();
                    }
                    $i++;
                }
            }
        }
        else {
            $children = $model->find(
                array(
                    'conditions' => 'parentId is null',
                    'order' => 'id'
                )
            );

            $returnArray = array();
            if (!empty($children)) {
                $i=0;
                foreach ($children as $child) {
                    foreach ($arrKeys as $key => $value) {
                        $returnArray[$i][$value] = $child->{$this->changeToFunctionString('get', $value)}();
                    }
                    $i++;
                }
            }
        }
        return $returnArray;
    }
    
    /**
     * check if node has children for function rebuildTree
     * @param object $model
     * @param int $parentId
     * @return boolean
     */
    public function hasChildrenForRebuildTree($model, $parentId = null)
    {
        $returnBoolean = false;
        $count = count($this->getChildForRebuildTree($model, $parentId));
        if ($count > 0) {
            $returnBoolean = true;
        }
        return $returnBoolean;
    }
    
    /**
     * Rebuild Tree based on parentId
     * @param object $model
     * @param int $counter
     * @param int $parentId
     * @return int
     */
    public function rebuildTree($model, $counter = 1, $parentId = null)
    {
        $limit = 999;
        $children = $this->getChildForRebuildTree($model, $parentId);
        $hasChildren = (bool)$children;

        if ($parentId !== null) {
            if ($hasChildren) {
                $model->getDi()->get('db')->query('UPDATE '.$model->getSchema().'.'.$model->getSource().' SET lft = '.$counter.' WHERE id = '.$parentId);
                $counter++;
            }
            else {
                $model->getDi()->get('db')->query('UPDATE '.$model->getSchema().'.'.$model->getSource().' SET lft = '.$counter.', rght = '.($counter+1).' WHERE id = '.$parentId);
                $counter += 2;
            }
        }

        while ($children) {
            foreach ($children as $row) {
                $counter = $this->rebuildTree($model, $counter, $row['id']);
            }
            if (count($children) !== $limit) {
                break;
            }
            $children = $this->getChildForRebuildTree($model, $parentId);
        }

        if ($parentId !== null && $hasChildren) {
            $model->getDi()->get('db')->query('UPDATE '.$model->getSchema().'.'.$model->getSource().' SET rght = '.$counter.' WHERE id = '.$parentId);
            $counter++;
        }
        return $counter;
    }

    /**
     * set lft value and rght value of node before admiting to table
     * @param object $model Model of the new node
     * @return boolean true
     */
    public function setNodeProperties($model)
    {
        $lft = 1;
        $rght = 2;
        $rootType = true;

        // check if node if root type or not
        if (!empty($model->getParentId())) {
            $rootType = false;
        }

        $model->lft = $lft;
        $model->rght = $rght;

        //check if not table empty
        if ($model->count() > 0) {
            if ($rootType) {
                $lastNode = $model->findFirst(
                    array(
                        'conditions' => 'parentId is null',
                        'order'=>'rght DESC',
                        'limit'=>1
                    )
                );
                if (!empty($lastNode)) {
                    $model->setLft($lastNode->getRght() + 1);
                    $model->setRght($lastNode->getRght() + 2);    
                }
            }
            else {
                $parentHasChildren = false;
                if ($this->getChildrenCount($model, $model->getParentId()) > 0) {
                    $parentHasChildren = true;
                }
                
                if ($parentHasChildren) {
                    
                    $lastNode = $model->findFirst(
                        array(
                            'conditions' => 'parentId = '.$model->getParentId(),
                            'order' => 'lft ASC',
                            'limit' => 1
                        )
                    );
                    $model->setLft($lastNode->getRght()+1);
                    $model->setRght($lastNode->getRght()+2);
                    $model->getDi()->get('db')->query('UPDATE '.$model->getSchema().'.'.$model->getSource().' SET rght = rght+2 WHERE rght > '.$lastNode->getRght());
                    $model->getDi()->get('db')->query('UPDATE '.$model->getSchema().'.'.$model->getSource().' SET lft = lft+2 WHERE lft > '.$lastNode->getRght());
                    
                }
                else {
                    
                    $lastNode = $model->findFirst(
                        array(
                            'conditions' => 'id = '.$model->getParentId(),
                            'order' => 'rght DESC',
                            'limit' => 1
                        )
                    );
                    //set value node
                    $model->setLft($lastNode->getLft()+1);
                    $model->setRght($lastNode->getLft()+2);
                    $model->getDi()->get('db')->query('UPDATE '.$model->getSchema().'.'.$model->getSource().' SET rght = rght+2 WHERE rght > '.$lastNode->getLft());
                    $model->getDi()->get('db')->query('UPDATE '.$model->getSchema().'.'.$model->getSource().' SET lft = lft+2 WHERE lft > '.$lastNode->getLft());
                }   
            }
        }
        return true;
    }

    /**
     * Deletes a node an all it's children
     * @param integer $id id of the node to delete
     * @return boolean true
     */
    public function removeNodeWithChildren($model)
    {
        if ($model->count() > 0) {
            //$node = $model->findFirst($model->getId());
            $round = round((($model->getRght() - $model->getLft()) + 1));
            $model->getDi()->get('db')->query('DELETE FROM '.$model->getSchema().'.'.$model->getSource().' WHERE lft BETWEEN '.$model->getLft().' AND '.$model->getRght());
            $model->getDi()->get('db')->query('UPDATE '.$model->getSchema().'.'.$model->getSource().' SET lft = lft - '.$round.' WHERE lft >'.$model->getRght());
            $model->getDi()->get('db')->query('UPDATE '.$model->getSchema().'.'.$model->getSource().' SET rght = rght - '.$round.' WHERE rght >'.$model->getRght());
        }

        return true;
    }

    /**
     * Deletes a node and increases the level of all children by one
     * @param integer $id id of the node to delete
     * @return boolean true
     */
    public function removeNodeWithoutChildren($model)
    {
        if ($model->count() > 0) {
            if (empty($model->getParentId())) {
                $model->getDi()->get('db')->query('UPDATE '.$model->getSchema().'.'.$model->getSource().' SET parent_id = NULL WHERE parent_id = '.$model->getId());
            }
            else {
                $model->getDi()->get('db')->query('UPDATE '.$model->getSchema().'.'.$model->getSource().' SET parent_id = '.$model->getParentId().' WHERE parent_id = '.$model->getId());
            }
            $model->getDi()->get('db')->query('UPDATE '.$model->getSchema().'.'.$model->getSource().' SET lft = lft - 1, rght = rght - 1 WHERE lft BETWEEN '.$model->getLft().' AND '.$model->getRght());
            $model->getDi()->get('db')->query('UPDATE '.$model->getSchema().'.'.$model->getSource().' SET lft = lft - 2 WHERE lft > '.$model->getLft());
            $model->getDi()->get('db')->query('UPDATE '.$model->getSchema().'.'.$model->getSource().' SET rght = rght - 2 WHERE rght > '.$model->getRght());
        }
        return true;
    }

    /**
     * Gets the id of a node depending on it's lft value
     * @param integer $lft lft value of the node
     * @return integer id of the node
     */
    public function getIdLeft($model, $lft)
    {
        $returnedId = null;
        $node = $model->findFirst(
            array(
                'conditions' => 'lft = '.$lft
            )
        );

        if (!empty($node)) {
            $returnedId = $node->getId();
        }
        return $returnedId;
    }

    /**
     * Gets the id of a node depending on it's rgt value
     * @param integer $rgt rgt value of the node
     * @return integer id of the node
     */
    public function getIdRight($model, $rght)
    {
        $returnedId = null;
        $node = $model->findFirst(
            array(
                'conditions' => 'rght = '.$rght
            )
        );
        if (!empty($node)) {
            $returnedId = $node->getId();
        }
        return $returnedId;
    }

    /**
     * Moves a node one position to the left staying in the same level
     * @param $nodeId id of the node to move
     * @return boolean true
     */
    public function moveLeft($model ,$id)
    {
        $node = $model->findFirst($id);
        if (!empty($node->getId())) {
            $brotherId = $this->getIdRight($model, $node->getLft() - 1);
            if (!empty($brotherId)) {
                $idsNotToMove = array();
                $strSQL = '';
                $brotherNode = $model->findFirst($brotherId);
                $nodeSize = $node->getRght() - $node->getLft() + 1;
                $brotherSize = $brotherNode->getRght() - $brotherNode->getLft() + 1;
                $resultNotToMove = $model->find(
                    array(
                        'conditions'=>'lft BETWEEN '.$node->getLft().' AND '.$node->getRght()
                    )
                );
                foreach ($resultNotToMove as $result) {
                    $idsNotToMove[] = $result->getId();
                }
                $model->getDi()->get('db')->query('UPDATE '.$model->getSchema().'.'.$model->getSource().' SET lft = lft - '.$brotherSize.', rght = rght - '.$brotherSize.' WHERE lft BETWEEN '.$node->getLft().' AND '.$node->getRght());
                $strSQL = 'UPDATE '.$model->getSchema().'.'.$model->getSource().' SET lft = lft + '.$nodeSize.', rght = rght + '.$nodeSize.' WHERE lft BETWEEN '.$brotherNode->getLft().' AND '.$brotherNode->getRght();
                foreach ($idsNotToMove as $idNotToMove) {
                    $strSQL.=' AND id !='.$idNotToMove;
                }
                $model->getDi()->get('db')->query($strSQL);
            }
        }
        return true;
    }

    /**
     * Moves a node one position to the right staying in the same level
     * @param $nodeId id of the node to move
     * @return boolean true
     */
    public function moveRight($model ,$id)
    {
        $node = $model->findFirst($id);
        if (!empty($node->getId())) {
            $brotherId = $this->getIdLeft($model, $node->getRght() + 1);
            
            if (!empty($brotherId)) {
                $idsNotToMove = array();
                $strSQL = '';
                $brotherNode = $model->findFirst($brotherId);
                $nodeSize = $node->getRght() - $node->getLft() + 1;
                $brotherSize = $brotherNode->getRght() - $brotherNode->getLft() + 1;
                $resultNotToMove = $model->find(
                    array(
                        'conditions'=>'lft BETWEEN '.$node->getLft().' AND '.$node->getRght()
                    )
                );
                foreach ($resultNotToMove as $result) {
                    $idsNotToMove[] = $result->getId();
                }
                $model->getDi()->get('db')->query('UPDATE '.$model->getSchema().'.'.$model->getSource().' SET lft = lft + '.$brotherSize.', rght = rght + '.$brotherSize.' WHERE lft BETWEEN '.$node->getLft().' AND '.$node->getRght());
                $strSQL = 'UPDATE '.$model->getSchema().'.'.$model->getSource().' SET lft = lft - '.$nodeSize.', rght = rght - '.$nodeSize.' WHERE lft BETWEEN '.$brotherNode->getLft().' AND '.$brotherNode->getRght();
                foreach ($idsNotToMove as $idNotToMove) {
                    $strSQL.=' AND id !='.$idNotToMove;
                }
                $model->getDi()->get('db')->query($strSQL);
            }
        }
        return true;
    }
}
?>
