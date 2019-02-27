<?php

require_once dirname(__FILE__) . '/adapter/AffiliateAdapterInterface.php';
require_once dirname(__FILE__) . '/adapter/AbstractAffiliateAdapter.php';

class Affiliate_AffiliateFactory {

    /**
     * @static
     * @param $affiliateAdapter
     * @param null $params
     * @return Affiliate_Adapter_AffiliateAdapterInterface
     * @throws Exception
     */
    static public function factory($affiliateAdapter, $params = null) {

        $filename = dirname(__FILE__) . '/adapter/' . ($affiliateAdapter) . '/' . ($affiliateAdapter) . 'Adapter.php';
        $affiliateAdapterName = 'Affiliate_Adapter_'.$affiliateAdapter.'_'.$affiliateAdapter.'Adapter';

        if (!file_exists($filename)) {
            throw new Exception('Affiliate Adapter not found');
        }
        require_once $filename;

        $obj = new $affiliateAdapterName($params);
        if (!($obj instanceof Affiliate_Adapter_AffiliateAdapterInterface)) {
            throw new Exception();
        }

		$obj->setAdapterName($affiliateAdapter);

        return $obj;
    }
}