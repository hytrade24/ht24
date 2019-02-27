<?php


interface Affiliate_Adapter_AffiliateAdapterInterface {

	public function init($affiliate);
	public function cleanUp();
	public function import();
}