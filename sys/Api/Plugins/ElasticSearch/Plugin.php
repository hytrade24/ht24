<?php

class Api_Plugins_ElasticSearch_Plugin extends Api_TraderApiPlugin {

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
        $this->registerEvent(Api_TraderApiEvents::MARKETPLACE_AD_UPDATED, "marketplaceAdUpdated");
        // TODO: Register your events here
        return true;
    }
    
    public function marketplaceAdUpdated(Api_Entities_EventParamContainer $parameters) {
        return $this->updateSearchIndexForArticle( $parameters->getParam("id") );
    }
    
    private function updateSearchIndexGlobal() {
        
    }
    
    private function updateSearchIndexForArticle($articleId) {
        
    }
}