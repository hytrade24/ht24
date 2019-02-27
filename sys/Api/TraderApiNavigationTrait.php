<?php

trait Api_TraderApiNavigationTrait {
        
    protected $navRoot = null;
    protected $navArray = null;
    protected $navIdents = null;
    protected $navPermissions = null;
    protected $navIdNext = null;
    protected $navDirty = false;
    
    protected abstract function navModification($root);
    
    public function navLoad(Api_Entities_EventParamContainer $params) {
        // Initialize local variables
        $this->navRoot = $params->getParam("root");
        $this->navArray = $params->getParam("ar_nav");
        $this->navIdents = $params->getParam("nar_ident2nav");
        $this->navPermissions = $params->getParam("nar_pageallow");
        $this->navIdNext = $params->getParam("id_nav_next");
        $this->navDirty = false;
        // Apply navigation modifications
        $this->navModification( $params->getParam("root") );
        // Publish changed navigation
        if ($this->navDirty) {
            $params->setParam("ar_nav", $this->navArray);
            $params->setParam("nar_ident2nav", $this->navIdents);
            $params->setParam("nar_pageallow", $this->navPermissions);
            $params->setParam("id_nav_next", $this->navIdNext);
        }
        // Clear local variables
        $this->navRoot = null;
        $this->navArray = null;
        $this->navIdents = null;
        $this->navIdNext = null;
        $this->navDirty = false;
    }
    
    private function navActiveCheck() {
        if (($this->navRoot === null) || ($this->navArray === null) || ($this->navIdents === null) || ($this->navPermissions === null) || ($this->navIdNext === null)) {
            throw new Exception("Api_TraderApiNavigationTrait: Trying to modify navigation outside of the 'navModification' function!");
        }
        return true;
    }
    
    private function navPermissionIdent($rawIdent) {
        if ($this->navRoot == 2) {
            return "admin/".$rawIdent;
        }
        return $rawIdent;
    }
    
    private function navAdd($ident, $textTitle, $bVisibleInMenu = 1, $textHover = "", $metaTags = "", $layout = "", $alias = "", $fkInfoseite = 0, $bSystemPage = 0, $addPermission = true) {
        $this->navActiveCheck();
        $idNav = $this->navIdNext++;
        $this->navArray[$idNav] = array(
            "ID_NAV" => $idNav,
            "FK_INFOSEITE" => $fkInfoseite,
            "B_SYS" => $bSystemPage,
            "B_SSL" => 1,
            "B_VIS" => $bVisibleInMenu,
            "B_SEARCH" => 0,
            "ROOT" => $this->navRoot,
            "LFT" => 0,
            "RGT" => 0,
            "IDENT" => $ident,
            "ALIAS" => $alias,
            "FK_MODUL" => 0,
            "S_LAYOUT" => $layout,
            "V1" => $textTitle,
            "V2" => $textHover,
            "T1" => $metaTags,
            "kidcount" => 0,
            "is_last" => 0,
            "is_first" => 0,
            "level" => 0,
            "KIDS" => array(),
            "PARENT" => 0,
            "LABEL" => $textTitle,
            "LEVEL" => 0,
            "ident_path" => 0
        );
        if (!empty($ident)) {
            $this->navIdents[$ident] = $idNav;
            if ($addPermission) {
                $this->navPermissions[ $this->navPermissionIdent($ident) ] = 1; 
            }
        }
        if (!empty($alias)) {
            $this->navIdents[$alias] = $idNav;
            if ($addPermission) {
                $this->navPermissions[ $this->navPermissionIdent($alias) ] = 1; 
            }
        }
        $this->navDirty = true;
        return $idNav;
    }
    
    protected function navAddChildByIdent($identNavParent, $ident, $textTitle, $bVisibleInMenu = 1, $textHover = "", $metaTags = "", $layout = "", $alias = "", $fkInfoseite = 0, $bSystemPage = 0) {
        $this->navActiveCheck();
        if (!array_key_exists($identNavParent, $this->navIdents)) {
            throw new Exception("Api_TraderApiNavigationTrait->navAddChildByIdent: Parent element not found! (".$identNavParent.")");
        }
        $idParent = $this->navIdents[$identNavParent];
        return $this->navAddChildById($idParent, $ident, $textTitle, $bVisibleInMenu, $textHover, $metaTags, $layout, $alias, $fkInfoseite, $bSystemPage);
    }
    
    protected function navAddChildById($idNavParent, $ident, $textTitle, $bVisibleInMenu = 1, $textHover = "", $metaTags = "", $layout = "", $alias = "", $fkInfoseite = 0, $bSystemPage = 0) {
        $this->navActiveCheck();
        if (!array_key_exists($idNavParent, $this->navArray)) {
            throw new Exception("Api_TraderApiNavigationTrait->navAddChildById: Parent element not found! (".$idNavParent.")");
        }
        $idNav = $this->navAdd($ident, $textTitle, $bVisibleInMenu, $textHover, $metaTags, $layout, $alias, $fkInfoseite, $bSystemPage);
        $isFirst = 1;
        $isLast = 1;
        if (!empty($this->navArray[$idNavParent]["KIDS"])) {
            $isFirst = 0;
            // Update last child
            $idNavLastChild = $this->navArray[$idNavParent]["KIDS"][ count($this->navArray[$idNavParent]["KIDS"]) - 1 ];
            $this->navArray[$idNavLastChild]["is_last"] = 0;
        }
        // Update parent
        $this->navArray[$idNavParent]["KIDS"][] = $idNav;
        $this->navArray[$idNavParent]["kidcount"] = count($this->navArray[$idNavParent]["KIDS"]);
        // Update new nav element
        $this->navArray[$idNav]["level"] = $this->navArray[$idNavParent]["level"] + 1;
        $this->navArray[$idNav]["LEVEL"] = $this->navArray[$idNavParent]["LEVEL"] + 1;
        $this->navArray[$idNav]["is_first"] = $isFirst;
        $this->navArray[$idNav]["is_last"] = $isLast;
        $this->navArray[$idNav]["PARENT"] = $idNavParent;
    }
    
    protected function navAddBeforeByIdent($identNavAfter, $ident, $textTitle, $bVisibleInMenu = 1, $textHover = "", $metaTags = "", $layout = "", $alias = "", $fkInfoseite = 0, $bSystemPage = 0) {
        $this->navActiveCheck();
        if (!array_key_exists($identNavAfter, $this->navIdents)) {
            throw new Exception("Api_TraderApiNavigationTrait->navAddChildByIdent: Following element not found! (".$identNavAfter.")");
        }
        $idNavAfter = $this->navIdents[$identNavAfter];
        return $this->navAddBeforeById($idNavAfter, $ident, $textTitle, $bVisibleInMenu, $textHover, $metaTags, $layout, $alias, $fkInfoseite, $bSystemPage);
    }
    
    protected function navAddBeforeById($idNavAfter, $ident, $textTitle, $bVisibleInMenu = 1, $textHover = "", $metaTags = "", $layout = "", $alias = "", $fkInfoseite = 0, $bSystemPage = 0) {
        $this->navActiveCheck();
        if (!array_key_exists($idNavAfter, $this->navArray)) {
            throw new Exception("Api_TraderApiNavigationTrait->navAddBeforeById: Following element not found! (".$idNavAfter.")");
        }
        $idNavParent = $this->navArray[$idNavAfter]["PARENT"];
        $idNav = $this->navAdd($ident, $textTitle, $bVisibleInMenu, $textHover, $metaTags, $layout, $alias, $fkInfoseite, $bSystemPage);
        $indexNavAfter = array_search($idNavAfter, $this->navArray[$idNavParent]["KIDS"]);
        $isFirst = 0;
        if ($this->navArray[$idNavAfter]["is_first"]) {
            // Nav entry is no longer the first one
            $isFirst = 1;
            $this->navArray[$idNavAfter]["is_first"] = 0;
        }
        $isLast = 0;
        // Update parent
        array_splice($this->navArray[$idNavParent]["KIDS"], $indexNavAfter, 0, array($idNav));
        $this->navArray[$idNavParent]["kidcount"] = count($this->navArray[$idNavParent]["KIDS"]);
        // Update new nav element
        $this->navArray[$idNav]["level"] = $this->navArray[$idNavAfter]["level"];
        $this->navArray[$idNav]["LEVEL"] = $this->navArray[$idNavAfter]["LEVEL"];
        $this->navArray[$idNav]["is_first"] = $isFirst;
        $this->navArray[$idNav]["is_last"] = $isLast;
        $this->navArray[$idNav]["PARENT"] = $this->navArray[$idNavAfter]["PARENT"];
    }
    
    protected function navAddAfterByIdent($identNavBefore, $ident, $textTitle, $bVisibleInMenu = 1, $textHover = "", $metaTags = "", $layout = "", $alias = "", $fkInfoseite = 0, $bSystemPage = 0) {
        $this->navActiveCheck();
        if (!array_key_exists($identNavBefore, $this->navIdents)) {
            throw new Exception("Api_TraderApiNavigationTrait->navAddChildByIdent: Following element not found! (".$identNavBefore.")");
        }
        $idNavBefore = $this->navIdents[$identNavBefore];
        return $this->navAddAfterById($idNavBefore, $ident, $textTitle, $bVisibleInMenu, $textHover, $metaTags, $layout, $alias, $fkInfoseite, $bSystemPage);
    }
    
    protected function navAddAfterById($idNavBefore, $ident, $textTitle, $bVisibleInMenu = 1, $textHover = "", $metaTags = "", $layout = "", $alias = "", $fkInfoseite = 0, $bSystemPage = 0) {
        $this->navActiveCheck();
        if (!array_key_exists($idNavBefore, $this->navArray)) {
            throw new Exception("Api_TraderApiNavigationTrait->navAddBeforeById: Following element not found! (".$idNavBefore.")");
        }
        $idNavParent = $this->navArray[$idNavBefore]["PARENT"];
        $idNav = $this->navAdd($ident, $textTitle, $bVisibleInMenu, $textHover, $metaTags, $layout, $alias, $fkInfoseite, $bSystemPage);
        $indexNavBefore = array_search($idNavBefore, $this->navArray[$idNavParent]["KIDS"]);
        $isFirst = 0;
        $isLast = 0;
        if ($this->navArray[$idNavBefore]["is_last"]) {
            // Nav entry is no longer the last one
            $isLast = 1;
            $this->navArray[$idNavBefore]["is_last"] = 0;
        }
        // Update parent
        array_splice($this->navArray[$idNavParent]["KIDS"], $indexNavBefore+1, 0, array($idNav));
        $this->navArray[$idNavParent]["kidcount"] = count($this->navArray[$idNavParent]["KIDS"]);
        // Update new nav element
        $this->navArray[$idNav]["level"] = $this->navArray[$idNavBefore]["level"];
        $this->navArray[$idNav]["LEVEL"] = $this->navArray[$idNavBefore]["LEVEL"];
        $this->navArray[$idNav]["is_first"] = $isFirst;
        $this->navArray[$idNav]["is_last"] = $isLast;
        $this->navArray[$idNav]["PARENT"] = $this->navArray[$idNavBefore]["PARENT"];
    }
    
    protected function navHasPermissionByIdent($identRaw) {
        $ident = $this->navPermissionIdent($identRaw);
        return (array_key_exists($ident, $this->navPermissions) ? $this->navPermissions[$ident] > 0 : false);
    }
    
}