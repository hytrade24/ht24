<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 19.08.15
 * Time: 09:21
 */

class Api_Ebay {
    
    const   SITE_AUSTRALIA = 15;
    const   SITE_AUSTRIA = 16;
    const   SITE_BELGIUM_DUTCH = 123;
    const   SITE_BELGIUM_FRENCH = 23;
    const   SITE_CANADA = 2;
    const   SITE_CANADA_FRENCH = 210;
    const   SITE_FRANCE = 71;
    const   SITE_GERMANY = 77;
    const   SITE_HONGKONG = 201;
    const   SITE_INDIA = 203;
    const   SITE_IRELAND = 205;
    const   SITE_ITALY = 101;
    const   SITE_MALAYSIA = 207;
    const   SITE_NETHERLANDS = 146;
    const   SITE_PHILIPPINES = 211;
    const   SITE_POLAND = 212;
    const   SITE_RUSSIA = 215;
    const   SITE_SINGAPORE = 216;
    const   SITE_SPAIN = 186;
    const   SITE_SWITZERLAND = 193;
    const   SITE_UK = 3;
    const   SITE_US = 0;
    
    const   CACHE_LIFETIME = 604800;    // 7 Tage

    private static $apiCompatLevel = 933;
    
    private static $apiLastError = null;

    private static function getApiDevId() {
        return $GLOBALS["nar_systemsettings"]["SYS"]["EBAY_DEV_ID"];
    }

    private static function getApiAppId() {
        return $GLOBALS["nar_systemsettings"]["SYS"]["EBAY_APP_ID"];
    }

    private static function getApiCertId() {
        return $GLOBALS["nar_systemsettings"]["SYS"]["EBAY_CERT_ID"];
    }

    private static function getApiSandbox() {
        return ($GLOBALS["nar_systemsettings"]["SYS"]["EBAY_SANDBOX"] ? true : false);
    }

    private static function getApiUrl($subdomain = "api", $filename = "api.dll") {
        return (self::getApiSandbox() ? "https://".$subdomain.".sandbox.ebay.com/ws/" : "https://".$subdomain.".ebay.com/ws/").$filename;
    }
    
    /**
     * @return DOMDocument
     */
    private static function createXmlRequest($requestAction, $requestNamespace, DOMElement &$xmlRequestAction = null) {
        $xmlRequest = new DOMDocument('1.0', 'utf-8');
        $xmlRequestAction = $xmlRequest->createElement($requestAction);
        $xmlRequestAction->setAttribute("xmlns", $requestNamespace);
        $xmlRequest->appendChild($xmlRequestAction);
        return $xmlRequest;
    }

    /**
     * @param DOMDocument $xmlRequest
     * @param DOMElement $xmlRequestAction
     * @param null $errorLanguage
     * @param null $messageId
     * @param null $version
     * @param null $warningLevel
     */
    private static function createXmlDefaultOptions(DOMDocument $xmlRequest, DOMElement $xmlRequestAction, $errorLanguage = null, $messageId = null, $version = null, $warningLevel = null) {
        if ($errorLanguage !== null) {
            $xmlParamErrorLanguage = $xmlRequest->createElement("ErrorLanguage", $errorLanguage);
            $xmlRequestAction->appendChild($xmlParamErrorLanguage);
        }
        if ($messageId !== null) {
            $xmlParamMessageID = $xmlRequest->createElement("MessageID", $messageId);
            $xmlRequestAction->appendChild($xmlParamMessageID);
        }
        if ($version !== null) {
            $xmlParamVersion = $xmlRequest->createElement("Version", $version);
            $xmlRequestAction->appendChild($xmlParamVersion);
        }
        if ($warningLevel !== null) {
            $xmlParamWarningLevel = $xmlRequest->createElement("WarningLevel", $warningLevel);
            $xmlRequestAction->appendChild($xmlParamWarningLevel);
        }
    }

    /**
     * @param DOMDocument   $xmlRequest
     * @param DOMElement    $xmlRequestAction
     * @param string        $ebayAuthToken
     */
    private static function createXmlRequesterCredentials(DOMDocument $xmlRequest, DOMElement $xmlRequestAction, $ebayAuthToken) {
        $xmlParamRequesterCredentials = $xmlRequest->createElement("RequesterCredentials");
        $xmlParamRequesterCredentialsToken = $xmlRequest->createElement("eBayAuthToken", $ebayAuthToken);
        $xmlParamRequesterCredentials->appendChild($xmlParamRequesterCredentialsToken);
        $xmlRequestAction->appendChild($xmlParamRequesterCredentials);
    }

    /**
     * @param DOMDocument   $xmlRequest
     * @param DOMElement    $xmlParentNode
     * @param array         $arNewChilds
     */
    private static function createXmlFromArray(DOMDocument $xmlRequest, DOMElement $xmlParentNode, $arNewChilds) {
        foreach ($arNewChilds as $fieldName => $fieldValue) {
            $xmlChild = $xmlRequest->createElement($fieldName);
            if (is_array($fieldValue)) {
                self::createXmlFromArray($xmlRequest, $xmlChild, $fieldValue);
            } else {
                $xmlChild->nodeValue = $fieldValue;
            }
            $xmlParentNode->appendChild($xmlChild);
        }
    }

    /**
     * @param DOMElement    $xmlElement
     * @return array
     */
    private static function getXmlAsArray(DOMElement $xmlElement) {
        $result = array();
        /**
         * @var DOMElement $childNode
         */
        foreach ($xmlElement->childNodes as $childNode) {
            if (($childNode->childNodes->length === 1) && ($childNode->childNodes->item(0) instanceof DOMText)) {
                $result[ $childNode->nodeName ] = $childNode->nodeValue;
            } else {
                $result[ $childNode->nodeName ] = self::getXmlAsArray($childNode);
            }
            /**
             * @var DOMNode $attribute
             */
            $attrCount = $childNode->attributes->length;
            for ($i = 0; $i < $attrCount; ++$i) {
                $attribute = $childNode->attributes->item($i);
                $result[ $childNode->nodeName."_".$attribute->name ] = $attribute->nodeValue;
            }
        }
        return $result;
    }
    
    /**
     * @param DOMNode       $xmlElement
     * @param string        $nodeName
     * @param int           $nodeIndex
     * @return DOMNode|null
     */
    private static function getXmlNode(DOMNode $xmlElement, $nodeName, $nodeIndex = 0) {
        $xmlNodeList = $xmlElement->getElementsByTagName($nodeName);
        if ($xmlNodeList->length > $nodeIndex) {
            return $xmlNodeList->item($nodeIndex);
        } else {
            return null;
        }
    }

    /**
     * @param DOMNode       $xmlElement
     * @param string        $nodeName
     * @param int           $nodeIndex
     * @return null|string
     */
    private static function getXmlNodeValue(DOMNode $xmlElement, $nodeName, $nodeIndex = 0) {
        $xmlNode = self::getXmlNode($xmlElement, $nodeName, $nodeIndex);
        if ($xmlNode !== null) {
            return $xmlNode->nodeValue;
        } else {
            return null;
        }
    }

    /**
     * @param DOMDocument   $xmlRequest
     * @param string        $apiCallName
     * @param int           $apiSiteId
     * @param string|null   $apiDevName
     * @param string|null   $apiAppName
     * @param string|null   $apiCertName
     * @return DOMDocument
     */
    private static function submitXmlRequest(DOMDocument $xmlRequest, $apiCallName, $apiSiteId, $apiDevName = null, $apiAppName = null, $apiCertName = null) {
        $ch = self::prepareXmlRequest($xmlRequest, $apiCallName, $apiSiteId, $apiDevName, $apiAppName, $apiCertName);
        $resultRaw = curl_exec($ch);
        $xmlResult = new DOMDocument('1.0', 'utf-8');
        $xmlResult->loadXML($resultRaw);
        return $xmlResult;
    }

    /**
     * @param DOMDocument   $xmlRequest
     * @param string        $apiCallName
     * @param int           $apiSiteId
     * @param string|null   $apiDevName
     * @param string|null   $apiAppName
     * @param string|null   $apiCertName
     * @return resource
     */
    private static function prepareXmlRequest(DOMDocument $xmlRequest, $apiCallName, $apiSiteId, $apiDevName = null, $apiAppName = null, $apiCertName = null) {
        $url = self::getApiUrl();
        $headers = array();
        $headers[] = "X-EBAY-API-COMPATIBILITY-LEVEL: ".self::$apiCompatLevel;
        $headers[] = "X-EBAY-API-CALL-NAME: ".$apiCallName;
        $headers[] = "X-EBAY-API-SITEID: ".$apiSiteId;
        if ($apiDevName !== null) {
            $headers[] = "X-EBAY-API-DEV-NAME: ".$apiDevName;
        }
        if ($apiAppName !== null) {
            $headers[] = "X-EBAY-API-APP-NAME: ".$apiAppName;
        }
        if ($apiCertName !== null) {
            $headers[] = "X-EBAY-API-CERT-NAME: ".$apiCertName;
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlRequest->saveXML());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        return $ch;
    }
    
    public static function fetchToken($sessionId, $errorLanguage = null, $messageId = null, $version = null, $warningLevel = null) {
        /**
         * Create request object
         * @var DOMDocument $xmlRequest
         * @var DOMElement  $xmlRequestAction
         */
        $xmlRequest = self::createXmlRequest("FetchTokenRequest", "urn:ebay:apis:eBLBaseComponents", $xmlRequestAction);
        // Add parameters
        $xmlParamRuName = $xmlRequest->createElement("SessionID", $sessionId);
        $xmlRequestAction->appendChild($xmlParamRuName);
        self::createXmlDefaultOptions($xmlRequest, $xmlRequestAction, $errorLanguage, $messageId, $version, $warningLevel);
        // Submit request
        $xmlResponse = self::submitXmlRequest($xmlRequest, "FetchToken", self::SITE_GERMANY, self::getApiDevId(), self::getApiAppId(), self::getApiCertId());
        // Check for result
        if (self::getXmlNodeValue($xmlResponse, "Ack") == "Success") {
            self::$apiLastError = null;
            $userAuthToken = self::getXmlNodeValue($xmlResponse, "eBayAuthToken");
            if ($userAuthToken !== null) {
                return $userAuthToken;
            }
        } else if (self::getXmlNodeValue($xmlResponse, "Ack") == "Failure") {
            self::$apiLastError = self::getXmlAsArray(self::getXmlNode($xmlResponse, "Errors"));
        }
        return false;
    }
    
    public static function getSessionId($ruName = null, $errorLanguage = null, $messageId = null, $version = null, $warningLevel = null) {
        if ($ruName === null) {
            $ruName = $GLOBALS["nar_systemsettings"]["SYS"]["EBAY_RU_NAME"];
        }
        /**
         * Create request object
         * @var DOMDocument $xmlRequest
         * @var DOMElement  $xmlRequestAction
         */
        $xmlRequest = self::createXmlRequest("GetSessionIDRequest", "urn:ebay:apis:eBLBaseComponents", $xmlRequestAction);
        // Add parameters
        $xmlParamRuName = $xmlRequest->createElement("RuName", $ruName);
        $xmlRequestAction->appendChild($xmlParamRuName);
        self::createXmlDefaultOptions($xmlRequest, $xmlRequestAction, $errorLanguage, $messageId, $version, $warningLevel);
        // Submit request
        $xmlResponse = self::submitXmlRequest($xmlRequest, "GetSessionID", self::SITE_GERMANY, self::getApiDevId(), self::getApiAppId(), self::getApiCertId());
        // Check for result
        if (self::getXmlNodeValue($xmlResponse, "Ack") == "Success") {
            self::$apiLastError = null;
            $sessionId = self::getXmlNodeValue($xmlResponse, "SessionID");
            if ($sessionId !== null) {
                return $sessionId;
            }
        } else if (self::getXmlNodeValue($xmlResponse, "Ack") == "Failure") {
            self::$apiLastError = self::getXmlAsArray(self::getXmlNode($xmlResponse, "Errors"));
        }
        return false;
    }
    
    public static function getApiAccessRules($userAuthToken = null, $errorLanguage = null, $messageId = null, $version = null, $warningLevel = null) {
        /**
         * Create request object
         * @var DOMDocument $xmlRequest
         * @var DOMElement  $xmlRequestAction
         */
        $xmlRequest = self::createXmlRequest("GetApiAccessRules", "urn:ebay:apis:eBLBaseComponents", $xmlRequestAction);
        // Add parameters
        if ($userAuthToken !== null) {
            self::createXmlRequesterCredentials($xmlRequest, $xmlRequestAction, $userAuthToken);
        }
        self::createXmlDefaultOptions($xmlRequest, $xmlRequestAction, $errorLanguage, $messageId, $version, $warningLevel);
        // Submit request
        $xmlResponse = self::submitXmlRequest($xmlRequest, "GetApiAccessRules", self::SITE_GERMANY, self::getApiDevId(), self::getApiAppId(), self::getApiCertId());
        // Check for result
        if (self::getXmlNodeValue($xmlResponse, "Ack") == "Success") {
            self::$apiLastError = null;
            return self::getXmlAsArray( self::getXmlNode($xmlResponse, "ApiAccessRule") );
        } else if (self::getXmlNodeValue($xmlResponse, "Ack") == "Failure") {
            self::$apiLastError = self::getXmlAsArray( self::getXmlNode($xmlResponse, "Errors") );
        }
        return false;
    }
    
    public static function getUser($userAuthToken = null, $errorLanguage = null, $messageId = null, $version = null, $warningLevel = null) {
        /**
         * Create request object
         * @var DOMDocument $xmlRequest
         * @var DOMElement  $xmlRequestAction
         */
        $xmlRequest = self::createXmlRequest("GetUserRequest", "urn:ebay:apis:eBLBaseComponents", $xmlRequestAction);
        // Add parameters
        if ($userAuthToken !== null) {
            self::createXmlRequesterCredentials($xmlRequest, $xmlRequestAction, $userAuthToken);
        }
        self::createXmlDefaultOptions($xmlRequest, $xmlRequestAction, $errorLanguage, $messageId, $version, $warningLevel);
        // Submit request
        $xmlResponse = self::submitXmlRequest($xmlRequest, "GetUser", self::SITE_GERMANY, self::getApiDevId(), self::getApiAppId(), self::getApiCertId());
        // Check for result
        if (self::getXmlNodeValue($xmlResponse, "Ack") == "Success") {
            self::$apiLastError = null;
            return self::getXmlAsArray( self::getXmlNode($xmlResponse, "User") );
        } else if (self::getXmlNodeValue($xmlResponse, "Ack") == "Failure") {
            self::$apiLastError = self::getXmlAsArray( self::getXmlNode($xmlResponse, "Errors") );
        }
        return false;
    }
    
    public static function getItem($itemId, $userAuthToken = null, $detailLevel = "ReturnSummary", $outputSelectors = array(), $errorLanguage = null, $messageId = null, $version = null, $warningLevel = null) {
        /**
         * Create request object
         * @var DOMDocument $xmlRequest
         * @var DOMElement  $xmlRequestAction
         */
        $xmlRequest = self::createXmlRequest("GetItemRequest", "urn:ebay:apis:eBLBaseComponents", $xmlRequestAction);
        // Add parameters
        if ($userAuthToken !== null) {
            self::createXmlRequesterCredentials($xmlRequest, $xmlRequestAction, $userAuthToken);
        }
        if ($itemId !== null) {
            self::createXmlFromArray($xmlRequest, $xmlRequestAction, array("ItemID" => $itemId));
        }
        if ($detailLevel !== null) {
            self::createXmlFromArray($xmlRequest, $xmlRequestAction, array("DetailLevel" => $detailLevel));
        }
        if (!empty($outputSelectors)) {
            foreach ($outputSelectors as $outputSelectorIndex => $outputSelectorValue) {
                self::createXmlFromArray($xmlRequest, $xmlRequestAction, array("OutputSelector" => $outputSelectorValue));
            }

        }
        self::createXmlDefaultOptions($xmlRequest, $xmlRequestAction, $errorLanguage, $messageId, $version, $warningLevel);
        // Submit request
        $xmlResponse = self::submitXmlRequest($xmlRequest, "GetItem", self::SITE_GERMANY, self::getApiDevId(), self::getApiAppId(), self::getApiCertId());
        // Check for result
        if (self::getXmlNodeValue($xmlResponse, "Ack") == "Success") {
            self::$apiLastError = null;
            return self::getXmlAsArray( self::getXmlNode($xmlResponse, "Item") );
        } else if (self::getXmlNodeValue($xmlResponse, "Ack") == "Failure") {
            self::$apiLastError = self::getXmlAsArray( self::getXmlNode($xmlResponse, "Errors") );
        }
        return false;
    }

    /**
     * @param int       $itemId
     * @param string    $userAuthToken
     * @param string    $detailLevel
     * @param array     $outputSelectors
     * @param null      $errorLanguage
     * @param null      $messageId
     * @param null      $version
     * @param null      $warningLevel
     * @return resource
     */
    public static function getItemRequest($itemId, $userAuthToken = null, $detailLevel = "ReturnSummary", $outputSelectors = array(), $errorLanguage = null, $messageId = null, $version = null, $warningLevel = null) {
        /**
         * Create request object
         * @var DOMDocument $xmlRequest
         * @var DOMElement $xmlRequestAction
         */
        $xmlRequest = self::createXmlRequest("GetItemRequest", "urn:ebay:apis:eBLBaseComponents", $xmlRequestAction);
        // Add parameters
        if ($userAuthToken !== null) {
            self::createXmlRequesterCredentials($xmlRequest, $xmlRequestAction, $userAuthToken);
        }
        if ($itemId !== null) {
            self::createXmlFromArray($xmlRequest, $xmlRequestAction, array("ItemID" => $itemId));
        }
        if ($detailLevel !== null) {
            self::createXmlFromArray($xmlRequest, $xmlRequestAction, array("DetailLevel" => $detailLevel));
        }
        if (!empty($outputSelectors)) {
            foreach ($outputSelectors as $outputSelectorIndex => $outputSelectorValue) {
                self::createXmlFromArray($xmlRequest, $xmlRequestAction, array("OutputSelector" => $outputSelectorValue));
            }

        }
        self::createXmlDefaultOptions($xmlRequest, $xmlRequestAction, $errorLanguage, $messageId, $version, $warningLevel);
        return self::prepareXmlRequest($xmlRequest, "GetItem", self::SITE_GERMANY, self::getApiDevId(), self::getApiAppId(), self::getApiCertId());
    }

    /**
     * @param array     $itemIds
     * @param string    $userAuthToken
     * @param string    $detailLevel
     * @param array     $outputSelectors
     * @param null      $errorLanguage
     * @param null      $messageId
     * @param null      $version
     * @param null      $warningLevel
     * @return array
     */
    public static function getItemListLive($itemIds, $userAuthToken = null, $detailLevel = "ReturnSummary", $outputSelectors = array(), $errorLanguage = null, $messageId = null, $version = null, $warningLevel = null) {
        return self::getItemList($itemIds, $userAuthToken, $detailLevel, $outputSelectors, $errorLanguage, $messageId, $version, $warningLevel, false);
    }

    /**
     * @param array     $itemIds
     * @param string    $userAuthToken
     * @param string    $detailLevel
     * @param array     $outputSelectors
     * @param null      $errorLanguage
     * @param null      $messageId
     * @param null      $version
     * @param null      $warningLevel
     * @return array
     */
    public static function getItemList($itemIds, $userAuthToken = null, $detailLevel = "ReturnSummary", $outputSelectors = array(), $errorLanguage = null, $messageId = null, $version = null, $warningLevel = null, $allowCache = true) {
        $curlQueue = new Api_CurlMultiQueue();
        $databaseCache = Api_DatabaseCacheStorage::getInstance();
        $arItems = array();
        $arItemsIndex = array();
        foreach ($itemIds as $itemIndex => $itemId) {
            // Store index / prepare result
            $arItems[$itemIndex] = null;
            $arItemsIndex[$itemId] = $itemIndex;
            // Check cache
            $jsonItemCached = ($allowCache ? $databaseCache->getContentByHash(sha1("ebay".$itemId."|".implode(",", $outputSelectors))) : null);
            if ($jsonItemCached === null) {
                // No cached version available, add request to queue
                $curlQueue->addRequest(
                  self::getItemRequest($itemId, $userAuthToken, $detailLevel, $outputSelectors, $errorLanguage, $messageId, $version, $warningLevel)
                );
            } else {
                $arItems[$itemIndex] = json_decode($jsonItemCached, true);
            }
        }
        // Execute requests
        if ($curlQueue->execute()) {
            // Get results
            $arRequests = $curlQueue->getRequestsDone();
            foreach ($arRequests as $itemIndex => $curlHandle) {
                // Get response
                $resultRaw = curl_multi_getcontent($curlHandle);
                $xmlResponse = new DOMDocument('1.0', 'utf-8');
                $xmlResponse->loadXML($resultRaw);
                // Check for result
                if (self::getXmlNodeValue($xmlResponse, "Ack") == "Success") {
                    self::$apiLastError = null;
                    $arItem = self::getXmlAsArray(self::getXmlNode($xmlResponse, "Item"));
                    $itemId = $arItem["ItemID"];
                    $itemIndex = $arItemsIndex[$itemId];
                    $arItems[$itemIndex] = $arItem;
                    // Write to cache
                    $databaseCache->addContent(sha1("ebay".$itemId."|".implode(",", $outputSelectors)), json_encode($arItem), time() + self::CACHE_LIFETIME);
                } else if (self::getXmlNodeValue($xmlResponse, "Ack") == "Failure") {
                    self::$apiLastError = self::getXmlAsArray(self::getXmlNode($xmlResponse, "Errors"));
                } else {
                    die("DEBUG2! " . $resultRaw . " / $itemIndex");
                }
            }
        }
        $curlQueue->cleanup();
        return $arItems;
    }
    
    public static function getMyeBaySellingIds($userAuthToken = null, $listType = "ActiveList", $outputSelectors = array(), $detailLevel = "ReturnSummary",
                                                $errorLanguage = null, $messageId = null, $version = null, $warningLevel = null) {
        // Get number of articles in the given list
        $articleListBrief = self::getMyeBaySelling($userAuthToken, array(
            $listType => array(
                "Include" => true, "Pagination" => array("EntriesPerPage" => 1)
            )
        ));
        $articleListCount = (int)$articleListBrief[$listType]["PaginationResult"]["TotalNumberOfEntries"];
        $articleListPerPage = 200;
        $articleListPages = floor($articleListCount / $articleListPerPage) + 1;
        $articleListConfig = array("Include" => true, "Pagination" => array("EntriesPerPage" => $articleListPerPage));
        // Create a request for each result page
        $curlMulti = curl_multi_init();
        $curlMultiConnections = array();
        for ($pageIndex = 0; $pageIndex < $articleListPages; $pageIndex++) {
            /**
             * Create request object
             * @var DOMDocument $xmlRequest
             * @var DOMElement $xmlRequestAction
             */
            $xmlRequest = self::createXmlRequest("GetMyeBaySellingRequest", "urn:ebay:apis:eBLBaseComponents", $xmlRequestAction);
            // Add parameters
            if ($userAuthToken !== null) {
                self::createXmlRequesterCredentials($xmlRequest, $xmlRequestAction, $userAuthToken);
            }
            $articleListConfig["Pagination"]["PageNumber"] = $pageIndex + 1;
            self::createXmlFromArray($xmlRequest, $xmlRequestAction, array($listType => $articleListConfig));
            if ($detailLevel !== null) {
                self::createXmlFromArray($xmlRequest, $xmlRequestAction, array("DetailLevel" => $detailLevel));
            }
            if (!empty($outputSelectors)) {
                foreach ($outputSelectors as $outputSelectorIndex => $outputSelectorValue) {
                    self::createXmlFromArray($xmlRequest, $xmlRequestAction, array("OutputSelector" => $outputSelectorValue));
                }

            }
            self::createXmlDefaultOptions($xmlRequest, $xmlRequestAction, $errorLanguage, $messageId, $version, $warningLevel);
            // Add request
            $curlMultiConnections[$pageIndex] = self::prepareXmlRequest($xmlRequest, "GetMyeBaySelling", self::SITE_GERMANY, self::getApiDevId(), self::getApiAppId(), self::getApiCertId());
            curl_multi_add_handle($curlMulti, $curlMultiConnections[$pageIndex]);
        }
        $arItems = array();
        // Execute requests
        do {
            $mrc = curl_multi_exec($curlMulti, $active);
            $info = curl_multi_info_read($curlMulti);
            if ($info !== false) {
                #var_dump($info);
            }
        } while ($mrc == CURLM_CALL_MULTI_PERFORM || $active);
        // Get results
        foreach ($curlMultiConnections as $pageIndex => $curlHandle) {
            // Get response
            $resultRaw = curl_multi_getcontent($curlHandle);
            $xmlResponse = new DOMDocument('1.0', 'utf-8');
            $xmlResponse->loadXML($resultRaw);
            // Check for result
            if (self::getXmlNodeValue($xmlResponse, "Ack") == "Success") {
                self::$apiLastError = null;
                $xmlResultLists = $xmlResponse->getElementsByTagName("ItemArray");
                /**
                 * @var DOMElement $xmlItem
                 * @var DOMElement $xmlItemArray
                 */
                foreach ($xmlResultLists as $xmlItemArray) {
                    foreach ($xmlItemArray->childNodes as $xmlItem) {
                        if ($xmlItem->nodeName !== "Item") continue;
                        $arItem = array_flatten(self::getXmlAsArray($xmlItem), true);
                        if (count($outputSelectors) == 1) {
                            $arItems[] = $arItem[ $outputSelectors[0] ];
                        } else {
                            $arItems[] = $arItem;
                        }
                    }
                }
            } else if (self::getXmlNodeValue($xmlResponse, "Ack") == "Failure") {
                self::$apiLastError = self::getXmlAsArray(self::getXmlNode($xmlResponse, "Errors"));
            }
            // Remove request handle
            curl_multi_remove_handle($curlMulti, $curlHandle);
        }
        curl_multi_close($curlMulti);
        return $arItems;
    }
    
    public static function getMyeBaySelling($userAuthToken = null, $arListConfigs, $detailLevel = "ReturnSummary",
                                            $errorLanguage = null, $messageId = null, $version = null, $warningLevel = null) {
        /**
         * Create request object
         * @var DOMDocument $xmlRequest
         * @var DOMElement  $xmlRequestAction
         */
        $xmlRequest = self::createXmlRequest("GetMyeBaySellingRequest", "urn:ebay:apis:eBLBaseComponents", $xmlRequestAction);
        // Define default list options
        $defaultListOptions = array("Include" => true);
        // Add parameters
        if ($userAuthToken !== null) {
            self::createXmlRequesterCredentials($xmlRequest, $xmlRequestAction, $userAuthToken);
        }
        foreach ($arListConfigs as $listIndex => $listConfig) {
            if (preg_match("/^[0-9]+$/", $listIndex)) {
                // Use default list config e.g.: $arListConfigs = array("ActiveList", "BidList")
                self::createXmlFromArray($xmlRequest, $xmlRequestAction, array($listConfig => $defaultListOptions));
            } else if (is_array($listConfig)) {
                // Use advanced list config e.g.: $arListConfigs = array("ActiveList" => array("Include" => true, ...) )
                self::createXmlFromArray($xmlRequest, $xmlRequestAction, array($listIndex => $listConfig));
            }
        }
        if ($detailLevel !== null) {
            self::createXmlFromArray($xmlRequest, $xmlRequestAction, array("DetailLevel" => $detailLevel));
        }
        self::createXmlDefaultOptions($xmlRequest, $xmlRequestAction, $errorLanguage, $messageId, $version, $warningLevel);
        // Submit request
        $xmlResponse = self::submitXmlRequest($xmlRequest, "GetMyeBaySelling", self::SITE_GERMANY, self::getApiDevId(), self::getApiAppId(), self::getApiCertId());
        // Check for result
        if (self::getXmlNodeValue($xmlResponse, "Ack") == "Success") {
            $arResult = array();
            $xmlResultLists = $xmlResponse->getElementsByTagName("ItemArray");
            /**
             * @var DOMElement $xmlItem
             * @var DOMElement $xmlItemArray
             */
            foreach ($xmlResultLists as $xmlItemArray) {
                $parentName = $xmlItemArray->parentNode->nodeName;
                $arResult[$parentName] = array(
                    "ItemArray"         => array(),
                    "PaginationResult"  => self::getXmlAsArray( self::getXmlNode($xmlItemArray->parentNode, "PaginationResult") )
                );
                foreach ($xmlItemArray->childNodes as $xmlItem) {
                    if ($xmlItem->nodeName !== "Item") continue;
                    $arResult[$parentName]["ItemArray"][] = self::getXmlAsArray($xmlItem);
                }
            }
            return $arResult;
        } else if ((self::getXmlNodeValue($xmlResponse, "Ack") == "Failure") || (self::getXmlNodeValue($xmlResponse, "Ack") == "Warning")) {
            self::$apiLastError = self::getXmlAsArray( self::getXmlNode($xmlResponse, "Errors") );
        }
        return false;
    }
    
    public static function getConsentUrl($ebaySession, $ebayRuName = null) {
        if ($ebayRuName === null) {
            $ebayRuName = $GLOBALS["nar_systemsettings"]["SYS"]["EBAY_RU_NAME"];
        }
        $url = self::getApiUrl("signin", "eBayISAPI.dll");
        $url .= "?SignIn&RuName=".urlencode($ebayRuName)."&SessID=".urlencode($ebaySession);
        return $url;
    }

    public static function getLastError() {
        return self::$apiLastError;
    }
    
}