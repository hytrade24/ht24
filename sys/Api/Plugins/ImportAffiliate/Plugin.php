<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 08.09.14
 * Time: 16:04
 */

class Api_Plugins_ImportAffiliate_Plugin extends Api_TraderApiPlugin {
    
    const DELETE_ACTION_DISABLE = 1;
    const DELETE_ACTION_UNAVAILABLE = 2;
    const DELETE_ACTION_DELETE = 3;

    private $currency_ratio = null;

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
        $this->registerEvent( Api_TraderApiEvents::IMPORT_GET_FIELDS, "importGetFields" );
        $this->registerEvent( Api_TraderApiEvents::IMPORT_PRESET_CREATE, "importPresetCreate" );
        $this->registerEvent( Api_TraderApiEvents::IMPORT_SOURCE_CREATE, "importSourceCreate" );
        $this->registerEvent( Api_TraderApiEvents::IMPORT_PROCESS_LOAD, "importProcessLoad" );
        $this->registerEvent( Api_TraderApiEvents::IMPORT_PROCESS_DATASET, "importProcessDataset" ); 
        
        return true;
    }
    
    public function importGetFields(Ad_Import_Preset_AbstractPreset $preset) {
        if ($preset->getConfigurationOption('affiliateImport')) {
            // Create additional fields
            $fieldAffiliateLink = Ad_Import_Preset_Mapping_TableField_ImportAffiliateLink::getInstance("artikel_master", "AFFILIATE_LINK");
            $fieldAffiliateLink->postHook();
            $preset->addCustomTableField($fieldAffiliateLink);

            $fieldISOBaseCurrencySymbol = Ad_Import_Preset_Mapping_TableField_ImportISOBaseCurrencySymbol::getInstance(
            	"artikel_master",
		        "FK_CURRENCY"
            );
            $fieldISOBaseCurrencySymbol->postHook();
            $preset->addCustomTableField($fieldISOBaseCurrencySymbol);

	        $fieldPriceInBaseCurrency = Ad_Import_Preset_Mapping_TableField_ImportPriceInBaseCurrency::getInstance(
	        	"artikel_master",
		        "PREIS_IN_BASE_CURRENCY"
	        );
	        $fieldPriceInBaseCurrency->postHook();
	        $preset->addCustomTableField($fieldPriceInBaseCurrency);
        }
    }
    
    public function importPresetCreate(Ad_Import_Preset_AbstractPreset $preset) {
        $userLogin = new Api_Entities_User($GLOBALS['user']);
        if (!$userLogin->hasRoleByLabel("Admin")) {
            // Only allow administrators to import affiliate ads
            return;
        }
        $preset->setConfigurationOption('affiliateImport', false);
        $preset->setConfigurationOption('affiliateUser', $userLogin->getId());
        $preset->setConfigurationOption('affiliateDeleteAction', 1);
        $preset->addConfigurationTemplate( $this->utilGetTemplate("importConfig.htm") );
    }
    
    public function importSourceCreate(Ad_Import_Source_SourceConfiguration $sourceConfig) {
        $userLogin = new Api_Entities_User($GLOBALS['user']);
        if (!$userLogin->hasRoleByLabel("Admin")) {
            // Only allow administrators to import affiliate ads
            return;
        }
        $sourceConfig->addConfigurationTemplate( $this->utilGetTemplate("importSourceConfig.htm") );
    }
    
    public function importProcessLoad(Ad_Import_Process_Process $importProcess) {
        $importSource = Ad_Import_Source_SourceManagement::getInstance($GLOBALS["db"])->fetchCachedById( $importProcess->getImportSource() );
        if ($importSource instanceof Ad_Import_Source_Source) {
            $targetUserId = $importSource->getOption("targetUser");
            if ($targetUserId > 0) {
                $importProcess->setConfigurationOption("targetUser", $targetUserId);
            }
        }
    }

    public function getCurrencyRatio($id_currency) {
    	global $db;

    	$query = 'SELECT c.RATIO_FROM_DEFAULT
    	FROM currency c
    	WHERE c.ID_CURRENCY = ' . $id_currency;

    	$this->currency_ratio = $db->fetch_atom( $query );
    }
    
    public function importProcessDataset(Ad_Import_Process_Import_ImportDataset $dataset) {
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
    }

}