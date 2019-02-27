<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 08.09.14
 * Time: 16:04
 */

class Api_Plugins_VendorEntry_Plugin extends Api_TraderApiPlugin {

    /**
     * Defines the priority of the Plugin. (Higher number = more important)
     * @return int
     */
    static public function getPriority() {
        return 10;
    }
    
    /**
     * Register the events needed by your plugin within this function.
     * @return bool     False for error, plugin will not be loaded if an error occurs
     */
    public function registerEvents() {
        // Packet events
        $this->registerEvent( Api_TraderApiEvents::PACKET_ENABLED, "packetChanged");
        $this->registerEvent( Api_TraderApiEvents::PACKET_DISABLED, "packetChanged");
        $this->registerEvent( Api_TraderApiEvents::PACKET_OTHER_FEATURES, "packetOtherFeatures");
        $this->registerEvent( Api_TraderApiEvents::PACKET_OTHER_FEATURES_ADMIN, "packetOtherFeaturesAdmin");
        $this->registerEvent( Api_TraderApiEvents::MEMBERSHIP_OTHER_FEATURES, "membershipOtherFeatures");
        $this->registerEvent( Api_TraderApiEvents::MEMBERSHIP_OTHER_FEATURES_ADMIN, "membershipOtherFeaturesAdmin");
        // Template events
        $this->registerEvent( Api_TraderApiEvents::TEMPLATE_SETUP_CONTENT, "templateSetupContent");
        return true;
    }
    
    protected function updateVendorEntryAvailability($userId) {
        $vendorEntryAvailable = $this->isVendorEntryAvailability($userId);
        if (!$vendorEntryAvailable) {
            $this->db->querynow("UPDATE `vendor` SET STATUS=0 WHERE FK_USER=".(int)$userId);
        }
    }
    
    protected function isVendorEntryAvailability($userId) {
        $vendorEntryAvailable = false;
        $arPacketIds = $this->db->fetch_col("
          SELECT ID_PACKET_ORDER FROM `packet_order`
		  WHERE `TYPE` IN ('COLLECTION', 'MEMBERSHIP') AND FK_COLLECTION IS NULL AND FK_USER=".(int)$userId."
		    AND (STATUS&1) = 1
		  ORDER BY STAMP_START DESC");
        if (!empty($arPacketIds)) {
            require_once $GLOBALS["ab_path"]."sys/packet_management.php";
            $packetManagement = PacketManagement::getInstance($this->db);
            foreach ($arPacketIds as $packetOrderIndex => $packetOrderId) {
                $packetOrder = $packetManagement->order_get($packetOrderId);
                $packetOrderOptions = $packetOrder->getPacketOptions();
                if (array_key_exists("vendorEntry", $packetOrderOptions) && array_key_exists("AVAILABLE", $packetOrderOptions["vendorEntry"])
                    && ($packetOrderOptions["vendorEntry"]["AVAILABLE"])) {
                    $vendorEntryAvailable = true;
                    break;
                }
            }
        }
        return $vendorEntryAvailable;
    }
    
    public function packetChanged(PacketOrderBase $packetChanged) {
        $arOptions = $packetChanged->getPacketOptions();
        if (array_key_exists("vendorEntry", $arOptions) && array_key_exists("AVAILABLE", $arOptions["vendorEntry"])
            && ($arOptions["vendorEntry"]["AVAILABLE"])) {
            $this->updateVendorEntryAvailability($packetChanged->getUserId());
        }
    }

    public function packetOtherFeatures(Api_Entities_PacketFeatures $features) {
        $features->addFeatureRegister("Anbieterverzeichnis", "vendorEntry", Api_Entities_PacketFeatures::COLUMN_TYPE_CUSTOM, $this->utilGetTemplateRaw("register.feature.htm"));
    }

    public function packetOtherFeaturesAdmin(Api_Entities_PacketFeatures $features) {
        $features->addFeatureAdmin("Anbieterverzeichnis", "vendorEntry", $this->utilGetTemplate("admin.feature.htm"));
    }

    public function membershipOtherFeatures(Api_Entities_MembershipFeatures $features) {
        $features->addFeatureRegister("Anbieterverzeichnis", "vendorEntry", Api_Entities_PacketFeatures::COLUMN_TYPE_CUSTOM, $this->utilGetTemplateRaw("register.feature.htm"));
    }

    public function membershipOtherFeaturesAdmin(Api_Entities_MembershipFeatures $features) {
        $features->addFeatureAdmin("Anbieterverzeichnis", "vendorEntry", $this->utilGetTemplate("admin.feature.htm"));
    }
    
    public function templateSetupContent(Api_Entities_EventParamContainer $params) {
        $pageName = $params->getParam("name");
        if (strpos($pageName, "my-vendor") === 0) {
            $userId = $GLOBALS["uid"];
            $vendorEntryAvailable = $this->isVendorEntryAvailability($userId);
            if (!$vendorEntryAvailable) {
                $params->setParam("replaceContent", $this->utilGetTemplate("vendor-disabled.htm")->process());
            }
        }
    }
}