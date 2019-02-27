<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 23.07.15
 * Time: 09:48
 */

class Api_Entities_PacketFeatures {

    const COLUMN_TYPE_NAME = 0;
    const COLUMN_TYPE_DESCRIPTION = 1;
    const COLUMN_TYPE_PACKET = 2;
    const COLUMN_TYPE_RUNTIME = 3;
    const COLUMN_TYPE_BUTTON = 4;
    const COLUMN_TYPE_CUSTOM = 5;
    
    private $usergroup;
    private $featuresRegister;
    private $featuresAdmin;
    
    public function __construct($usergroupId = NULL) {
        $this->usergroup = $usergroupId;
        $this->featuresRegister = array();
        $this->featuresAdmin = array();
    }
    
    public function addFeatureRegister($featureLabel, $featureIdent, $columnType = COLUMN_TYPE_CUSTOM, $columnTemplateText = NULL) {
        $this->featuresRegister[$featureIdent] = array(
            "ident"     => $featureIdent,
            "V1"        => Translation::readTranslation("marketplace", "register.packet.feature.".$featureIdent, null, array(), $featureLabel),
            "TYP_N"     => $columnType,
            "TPL_TEXT"  => $columnTemplateText
        );
    }
    
    public function addFeatureAdmin($featureLabel, $featureIdent, Template $configurationTemplate) {
        $this->featuresAdmin[$featureIdent] = array(
            "ident"     => $featureIdent,
            "label"     => $featureLabel,
            "template"  => $configurationTemplate
        );
    }
    
    public function getFeaturesRegister() {
        return $this->featuresRegister;
    }
    
    public function getFeatureRegister($featureIdent) {
        if (array_key_exists($featureIdent, $this->featuresRegister)) {
            return $this->featuresRegister[$featureIdent];
        } else {
            return NULL;
        }
    }
    
    public function getFeaturesAdmin() {
        return $this->featuresAdmin;
    }
    
    public function getFeatureAdmin($featureIdent) {
        if (array_key_exists($featureIdent, $this->featuresAdmin)) {
            return $this->featuresAdmin[$featureIdent];
        } else {
            return NULL;
        }
    }
    
    public function getUsergroupId() {
        return $this->usergroup;
    }
    
    public function setUsergroupId($usergroupId) {
        $this->usergroup = $usergroupId;
    }
    
}