<?php

$orderId = (int)$ar_params[1];

// Plugin event
$eventMarketCartCheckout = new Api_Entities_EventParamContainer(array(
	"orderId"		=> $orderId,
    "pluginHtml"    => ""
));
Api_TraderApiHandler::getInstance()->triggerEvent(Api_TraderApiEvents::MARKETPLACE_CART_CHECKOUT_SUCCESS, $eventMarketCartCheckout);
if ($eventMarketCartCheckout->isDirty()) {
	$tpl_content->addvar("PLUGIN_HTML", $eventMarketCartCheckout->getParam("pluginHtml"));
}