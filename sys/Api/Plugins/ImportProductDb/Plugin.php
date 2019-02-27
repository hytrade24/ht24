<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 08.09.14
 * Time: 16:04
 */

class Api_Plugins_ImportProductDb_Plugin extends Api_TraderApiPlugin {
    
    /**
     * Defines the priority of the Plugin. (Higher number = more important)
     * @return int
     */
    static public function getPriority() {
        return 0;
    }

    /**
     * Register the events needed by your plugin within this function.
     * @return bool     False for error, plugin will not be loaded if an error occurs
     */
    public function registerEvents() {
        $this->registerEvent( Api_TraderApiEvents::IMPORT_PRESET_CREATE, "importPresetCreate" );
        $this->registerEvent( Api_TraderApiEvents::IMPORT_SOURCE_CREATE, "importSourceCreate" );
        $this->registerEvent( Api_TraderApiEvents::IMPORT_PROCESS_DATASET, "importProcessDataset" ); 
        
        return true;
    }
    
    public function importPresetCreate(Ad_Import_Preset_AbstractPreset $preset) {
        $userLogin = new Api_Entities_User($GLOBALS['user']);
        if (!$userLogin->hasRoleByLabel("Admin")) {
            // Only allow administrators to import affiliate ads
            return;
        }
        $preset->setConfigurationOption('productImport', false);
        $preset->addConfigurationTemplate( $this->utilGetTemplate("importConfig.htm") );
    }
    
    public function importProcessDataset(Ad_Import_Process_Import_ImportDataset $dataset) {
        $productImport = $dataset->getPreset()->getConfigurationOption('productImport');
        if ($productImport) {
            $dataset->setValue("CONFIRMED", 1);
            $dataset->setValue("IMPORT_TASK", "PRODUCT_DB");
        }
        /*
        if ($dataset->getValue("AFFILIATE_LINK") !== null) {
            $dataset->setValue("AFFILIATE", 1);
            $dataset->setValue("AFFILIATE_FK_AFFILIATE", $dataset->getProcess()->getImportSource());
        }
        if ( ($dataset->getValue("FK_CURRENCY") !== null) && ($dataset->getValue("FK_CURRENCY") > 0) 
              && ($dataset->getValue("PREIS_IN_BASE_CURRENCY") !== null) 
              && ($dataset->getValue("PREIS_IN_BASE_CURRENCY") > 0) ) 
        {

	        $fk_currency = $dataset->getValue("FK_CURRENCY");

	        if ( is_null($this->currency_ratio) ) {
	        	$this->getCurrencyRatio( $fk_currency );
	        }

	        $price_in_base_currency = $dataset->getValue("PREIS_IN_BASE_CURRENCY");
	        $price_in_base_currency =(float) filter_var(
	        	$price_in_base_currency,
			    FILTER_SANITIZE_NUMBER_FLOAT,
			    FILTER_FLAG_ALLOW_FRACTION
	        );
	        $val = floatval( $this->currency_ratio ) * floatval($price_in_base_currency);

        	$dataset->setValue("PREIS",$val);
        }
        */
    }

}