<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2015-07-02
 * Time: 오후 5:52
 */
class ZtreeGenerator{

    /**
     * update object to EasyTreeNode Style
     */
    function SetZTreeNode($Node, $id_name = 'id', $text_name = 'text'){
        $Node->id = $Node->{$id_name};
        $Node->name = $Node->{$text_name};
        return $Node;
    }
    /**
     * Get RootNodes
     * @param $Arrays
     * @return array
     */
    function getRootNodes($Arrays){
        // Get RootNodes
        $lRootNodes = array();
        foreach ($Arrays as $Array) {
            $lZTreeNodeObj = new ZtreeNode($Array);
            $lZTreeNodeObj->iconSkin ='icon01';
            $lZTreeNodeObj = $this->SetZTreeNode($lZTreeNodeObj,'id','label');
            if(is_null($lZTreeNodeObj->parent_id)){
                $lRootNodes[] = $lZTreeNodeObj;
            }
        }
        // Add Genereal Node
        $lZTreeNodeObjGeneral = new ZtreeNode(array("id"=>0, "user_id"=>61, "label"=>"General", "parent_id" => NULL, "iconSkin" => "icon01", "editable" => false));
        $lZTreeNodeObjGeneral = $this->SetZTreeNode($lZTreeNodeObjGeneral,'id','label');
        array_unshift($lRootNodes, $lZTreeNodeObjGeneral);
        return $lRootNodes;
    }
    /**
     * get ChiliNodes(Recursive function)
     * @param $aParentNodes
     * @param $Arrays
     * @return mixed
     */
    function getLeafNodes($aParentNodes,$Arrays){
        // Set ChildNodes
        foreach ($aParentNodes as $lParentNode) {
            foreach($Arrays as $Array){
                $lZTreeNodeObj = new ZtreeNode($Array);
                $lZTreeNodeObj->iconSkin = 'icon02';
                $lZTreeNodeObj = $this->SetZTreeNode($lZTreeNodeObj,'id','label');
                if($lParentNode->id == $lZTreeNodeObj->parent_id && !is_null($lZTreeNodeObj->parent_id)){
                    $lParentNode->children[] = $lZTreeNodeObj;
                    $this->getLeafNodes($lParentNode->children, $Arrays);
                }
            }
        }
        return $aParentNodes;
    }
    /**
     * @param $Arrays
     * @return EasyTreeNode array
     */
    function getZtreeNodes($Arrays){
        $RootNodes = $this->getRootNodes($Arrays);           // extract root nodes to reduce memory use
        $ZtreeNodes = $this->getLeafNodes($RootNodes, $Arrays);   // generate tree nodes with children
        return $ZtreeNodes;
    }

}
