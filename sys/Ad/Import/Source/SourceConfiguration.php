<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 26.06.15
 * Time: 14:04
 */

class Ad_Import_Source_SourceConfiguration {
    
    protected   $arConfigurationTemplates;
    
    function __construct() {
        $this->arConfigurationTemplates = array();
    }
    
    public function addConfigurationTemplate($template) {
        $this->arConfigurationTemplates[] = $template;
    }
    
    public function clearConfigurationTemplates() {
        $this->arConfigurationTemplates = array();        
    }
    
    public function getConfigurationTemplates() {
        return $this->arConfigurationTemplates;
    }
    
}