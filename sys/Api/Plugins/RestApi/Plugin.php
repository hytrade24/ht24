<?php

class Api_Plugins_RestApi_Plugin extends Api_TraderApiPlugin {

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
        $this->registerEvent( Api_TraderApiEvents::URL_PROCESS_PAGE, "urlProcessPage" );
        return true;
    }
    
    public function urlProcessPage(Api_Entities_URL $url) {
        $urlPrefix = "/rest/";
        $urlTarget = "/".ltrim($url->getPath(), "/");
        if ((strpos($urlTarget, $urlPrefix) === 0) && $this->utilCheckAuthorisation()) {
            $urlRest = str_replace($urlPrefix, "/", $urlTarget);
            $result = false;
            /** @var Api_Plugins_RestApi_ServerAbstract $restService */
            $restService = $this->getRestService($urlRest);
            if ($restService instanceof Api_Plugins_RestApi_ServerInterface) {
                switch ($_SERVER["REQUEST_METHOD"]) {
                    case "GET":
                        $result = $restService->get($urlRest, $_GET);
                        break;
                    case "POST":
                        $result = $restService->post($urlRest, $_POST);
                        break;
                    case "PUT":
                        $result = $restService->put($urlRest, $_POST);
                        break;
                    case "DELETE":
                        $result = $restService->delete($urlRest, $_POST);
                        break;
                }
                $this->utilSetHttpStatus( $restService->getStatusCode(), $restService->getStatusMessage() );
            }
            header("Content-Type: application/json");
            die(json_encode($result));
        }
    }
    
    public function getRestService($resourceUrl) {
        $arPath = explode("/", ltrim($resourceUrl, "/"));
        while (!empty($arPath)) {
            $strPath = $this->utilGetRestClass($arPath);
            $strClass = "Api_Plugins_RestApi_Server_".$strPath;
            if (class_exists($strClass)) {
                return new $strClass();
            }
            array_pop($arPath);
        }
        return null;
    }
    
    private function utilCheckAuthorisation() {
        global $db, $uid;
        $roleName = "Admin";
        if ($uid > 0) {
            $userHasRole = (int)$db->fetch_atom("
                SELECT count(*) FROM `role2user` ru
                JOIN `role` r ON r.ID_ROLE=ru.FK_ROLE AND FK_USER=".$uid." 
                WHERE r.LABEL='".mysql_real_escape_string($roleName)."'");
            return ($userHasRole > 0);
        } else {
            $userName = $_SERVER["PHP_AUTH_USER"];
            $userPass = $_SERVER["PHP_AUTH_PW"];
            $userCheck = $db->fetch1("SELECT ID_USER, PASS, SALT FROM `user` WHERE NAME='".mysql_real_escape_string($userName)."'");
            if (is_array($userCheck) && pass_compare($userPass, $userCheck['PASS'], $userCheck['SALT'])) {
                // User login successful, check rights
                $userHasRole = (int)$db->fetch_atom("
                    SELECT count(*) FROM `role2user` ru
                    JOIN `role` r ON r.ID_ROLE=ru.FK_ROLE AND FK_USER=".$userCheck["ID_USER"]." 
                    WHERE r.LABEL='".mysql_real_escape_string($roleName)."'");
                return ($userHasRole > 0);
            }
        }
        return false;
    }
    
    private function utilGetRestClass($arPath) {
        $strPath = "";
        foreach ($arPath as $pathIndex => $pathPart) {
            $strPath .= ucfirst(strtolower( preg_replace("/[^a-z0-9]*/i", "", $pathPart) ));
        }
        return $strPath;
    }
    
    private function utilSetHttpStatus($statusCode, $defaultText) {
        switch ($statusCode) {
            default:
                header("HTTP/1.0 ".$statusCode." ".$defaultText);
                break;
            case 200:
                header("HTTP/1.0 200 OK");
                break;
            case 404:
                header("HTTP/1.0 404 Not found");
                break;
        }
    }
}