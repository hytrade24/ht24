<?php
/* ###VERSIONSBLOCKINLCUDE### */

interface Payment_Adapter_PaymentAdapterInterface {

    public function init(array $paymentObject);
    public function buttonOrder();
    public function prepare();
    public function prepareOrder();
    public function doPayment();
    public function verifyPayment();
    public function successPayment();
    public function pendingPayment();
    public function cancelPayment();

	public function configurationEditUserConfiguration();
	public function configurationEditSellerConfiguration();
    public function configurationEditAdminConfiguration();
	public function configurationSaveUserConfiguration($config);
	public function configurationSaveSellerConfiguration($config);
    public function configurationSaveAdminConfiguration($config);
    public function configurationIsUserConfigurationAllowed();

	public function setAdapterName($adapterName);
	public function getAdapterName();

	public function getPaymentObject();

    public function eventBeforeInvoiceCreate($invoiceRawData);
}