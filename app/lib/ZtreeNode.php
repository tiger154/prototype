<?php
/**
 * For Ztree node Model Object
 * User: Administrator
 * Date: 2015-07-02
 * Time: 오후 5:56
 */

class ZtreeNode extends Cast{
    public $checked = false;
    public $children = null;
    public $chkDisabled=false;
    public $click=null;
    public $halfCheck=false;
    public $icon=null;
    public $iconClose=null;
    public $iconOpen=null;
    public $iconSkin=null;
    public $isHidden=null;
    public $isParent=null;
    public $name=null;
    public $nocheck=false;
    public $open=false;
    public $target=null;
    public $url=null;
    public $rowData=null;
    public $editable=true; // added by queeraz to control each node's editable 2015.06.06

}