<?php

require_once __DIR__."/lib/Google/autoload.php";

class Api_Plugins_GoogleAPI_Plugin extends Api_TraderApiPlugin {
    
    protected $googleClients = array();

    function __destruct() {
        if (!array_key_exists("googleAuthTokens", $this->pluginConfiguration)) {
            $this->pluginConfiguration["googleAuthTokens"] = array();
        }
        $tokensChanged = false;
        foreach ($this->googleClients as $clientHash => $clientDetails) {
            /** @var Google_Client $clientObject */
            $clientObject = $clientDetails["client"];
            $clientAccessToken = $clientObject->getAccessToken();
            if ($clientAccessToken && ($clientAccessToken != $this->pluginConfiguration["googleAuthTokens"][$clientHash])) {
                $this->pluginConfiguration["googleAuthTokens"][$clientHash] = $clientAccessToken;
                $tokensChanged = true;
            }
        }
        if ($tokensChanged) {
            $this->saveConfiguration();
        }
    }

    /**
     * Defines the priority of the Plugin. (Higher number = more important)
     * @return int
     */
    static public function getPriority() {
        return 1;
    }

    /**
     * Register the events needed by your plugin within this function.
     * @return bool     False for error, plugin will not be loaded if an error occurs
     */
    public function registerEvents() {
        $this->registerEvent(Api_TraderApiEvents::AJAX_PLUGIN, "ajaxPlugin");
        return true;
    }
    
    public function ajaxPlugin(Api_Entities_EventParamContainer $params)
    {
        $jsonResult = array("success" => false);
        switch ($params->getParam("action")) {
            case "authGoogle":
                if (!array_key_exists("_googleApiAuthClient", $_SESSION)) {
                    die( $this->utilGetTemplate("oauth_error_client_unknown.htm")->process() );
                }
                // Get target client
                list($clientId, $clientSecret, $developerKey, $arScopes) = $_SESSION["_googleApiAuthClient"];
                unset($_SESSION["_googleApiAuthClient"]);
                // Authenticate
                $clientHash = $this->getGoogleClientHash($clientId, $clientSecret, $developerKey, $arScopes);
                $clientObject = $this->getGoogleClient($clientId, $clientSecret, $developerKey, $arScopes);
                // Get access token
                $accessToken = $clientObject->authenticate($_REQUEST["code"]);
                if (!is_array($this->pluginConfiguration["googleAuthTokens"])) {
                    $this->pluginConfiguration["googleAuthTokens"] = array();
                }
                $this->pluginConfiguration["googleAuthTokens"][$clientHash] = $accessToken;
                // Store into configuration
                $this->saveConfiguration();
                die( $this->utilGetTemplate("oauth_success.htm")->process() );
        }
        die(json_encode($jsonResult));
    }
    
    public function authenticateGoogleClient($clientId = null, $clientSecret = null, $developerKey = null, $arScopes = array(), &$googleAuthUrl = null) {
        $clientObject = $this->getGoogleClient($clientId, $clientSecret, $developerKey, $arScopes);
        $googleClientToken = $clientObject->getAccessToken();
        if (!$googleClientToken) {
            $_SESSION["_googleApiAuthClient"] = array($clientId, $clientSecret, $developerKey, $arScopes);
            $googleAuthUrl = $clientObject->createAuthUrl();
            return false;
        }
        return $clientObject;
    }

    public function deauthenticateGoogleClient($clientId, $clientSecret, $developerKey, $arScopes)
    {
        $clientHash = $this->getGoogleClientHash($clientId, $clientSecret, $developerKey, $arScopes);
        // Remove auth token
        if (array_key_exists("googleAuthTokens", $this->pluginConfiguration) && array_key_exists($clientHash, $this->pluginConfiguration["googleAuthTokens"])) {
            unset($this->pluginConfiguration["googleAuthTokens"][$clientHash]);
            $this->saveConfiguration();
        }
        // Remove active clients and services from cache
        if (array_key_exists($clientHash, $this->googleClients)) {
            unset($this->googleClients[$clientHash]);
        }
        return true;
    }

    /**
     * Returns a unique hash for the given client data.
     * @param string|null $clientId
     * @param string|null $clientSecret
     * @param string|null $developerKey
     * @return string
     */
    public function getGoogleClientHash($clientId = null, $clientSecret = null, $developerKey = null, $arScopes = array()) {
        return sha1($clientId."|".$clientSecret."|".$developerKey."|".implode("|", $arScopes));
    }

    /**
     * @param string|null   $clientId
     * @param string|null   $clientSecret
     * @param string|null   $developerKey
     * @return Google_Client
     */
    public function getGoogleClient($clientId = null, $clientSecret = null, $developerKey = null, $arScopes = array()) {
        $clientHash = $this->getGoogleClientHash($clientId, $clientSecret, $developerKey, $arScopes);
        if (!array_key_exists($clientHash, $this->googleClients)) {
            // Create new client
            $clientObject = new Google_Client();
            if ($clientId !== null) {
                $clientObject->setClientId($clientId);
            }
            if ($clientSecret !== null) {
                $clientObject->setClientSecret($clientSecret);
            }
            if ($developerKey !== null) {
                $clientObject->setDeveloperKey($developerKey);
            }
            if (!empty($arScopes)) {
                $clientObject->setScopes($arScopes);
            }
            if (is_array($this->pluginConfiguration["googleAuthTokens"]) && array_key_exists($clientHash, $this->pluginConfiguration["googleAuthTokens"])) {
                $clientObject->setAccessToken( $this->pluginConfiguration["googleAuthTokens"][$clientHash] );
            }
            $clientObject->setAccessType("offline");
            $clientObject->setRedirectUri( $this->getGoogleRedirectUrl() );
            $this->googleClients[$clientHash] = array(
                "client"    => $clientObject,
                "services"  => array()
            );
        }
        return $this->googleClients[$clientHash]["client"];
    }

    /**
     * @param string        $serviceName
     * @param string|null   $developerKey
     * @param string|null   $clientId
     * @param string|null   $clientSecret
     * @return mixed|bool
     */
    public function getGoogleService($serviceName, $clientId = null, $clientSecret = null, $developerKey = null, $arScopes = array()) {
        $clientHash = $this->getGoogleClientHash($clientId, $clientSecret, $developerKey, $arScopes);
        $clientObject = $this->getGoogleClient($clientId, $clientSecret, $developerKey, $arScopes);
        $serviceClass = "Google_Service_".$serviceName;
        if (!($clientObject instanceof Google_Client) || !array_key_exists($clientHash, $this->googleClients)) {
            // Failed to initialize client!
            return false;
        }
        if (!array_key_exists($serviceClass, $this->googleClients[$clientHash]["services"])) {
            // Create new service object
            $this->googleClients[$clientHash]["services"][$serviceClass] = new $serviceClass( $clientObject );
        }
        return $this->googleClients[$clientHash]["services"][$serviceClass];
    }
    
    public function getGoogleRedirectUrl() {
        $arSystemsettings = (array_key_exists("originalSystemSettings", $GLOBALS) ? $GLOBALS["originalSystemSettings"] : $GLOBALS["nar_systemsettings"]);
        return $arSystemsettings["SITE"]["SITEURL"].$arSystemsettings["SITE"]["BASE_URL"]
                ."index.php?pluginAjax=GoogleAPI&pluginAjaxAction=authGoogle";
    }
}