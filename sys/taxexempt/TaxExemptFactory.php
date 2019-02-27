<?php

require_once dirname(__FILE__) . '/adapter/TaxExemptAdapterInterface.php';
require_once dirname(__FILE__) . '/adapter/AbstractTaxExemptAdapter.php';

class TaxExempt_TaxExemptFactory {

    /**
     * @static
     * @param $taxExemptAdapter
     * @param null $params
     * @return TaxExempt_Adapter_TaxExemptAdapterInterface
     * @throws Exception
     */
    static public function factory($taxExemptAdapter, $params = null) {

        $filename = dirname(__FILE__) . '/adapter/' . ($taxExemptAdapter) . '/' . ($taxExemptAdapter) . 'Adapter.php';
        $affiliateAdapterName = 'TaxExempt_Adapter_'.$taxExemptAdapter.'_'.$taxExemptAdapter.'Adapter';

        if (!file_exists($filename)) {
            throw new Exception('Tax Exempt Adapter not found');
        }
        require_once $filename;

        $obj = new $affiliateAdapterName($params);
        if (!($obj instanceof TaxExempt_Adapter_TaxExemptAdapterInterface)) {
            throw new Exception();
        }

		$obj->setAdapterName($taxExemptAdapter);
		$obj->init();

        return $obj;
    }
}