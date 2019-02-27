<?php

class Api_Plugins_RestApi_Server_MarketplaceAds extends Api_Plugins_RestApi_ServerAbstract {

    /**
     * Get the resource details.
     * @param   mixed $resource
     * @param   array $arOptions
     * @return  array|null
     */
    public function get($resource, $arOptions)
    {
        if ($resource == "/marketplace/ads") {
            $this->setStatus(200, "OK");
            return Rest_Users::getUserList();
        } else if (preg_match("/^\/marketplace\/ads\/([0-9]+)$/", $resource, $arMatch)) {
            $userId = (int)$arMatch[1];
            if ($userId > 0) {
                $arUser = Rest_Users::getUser(array("ID_USER" => $userId));
                if (is_array($arUser)) {
                    $this->setStatus(200, "OK");
                    return $arUser;
                }
            }
        }
        $this->setStatus(404, "Not found");
        return null;
    }

    /**
     * Create a resource / other non-idempotent operations
     * @param   mixed $resource
     * @param   array $arOptions
     * @return  bool
     */
    public function post($resource, $arOptions)
    {
        // TODO: Implement post() method.
        if (false) {
            $this->setStatus(201, "Created");
        } else {
            $this->setStatus(409, "Conflict");
        }
    }

    /**
     * Save the given resource. If it already exists it will be updated.
     * @param   mixed $resource
     * @param   array $arOptions
     * @param   array $arData
     * @return  bool
     */
    public function put($resource, $arOptions)
    {
        // TODO: Implement put() method.
        if (false) {
            $this->utilSetHttpStatus(200, "OK");
        } else {
            $this->utilSetHttpStatus(404, "Not found");
        }
    }

    /**
     * Delete the given resource.
     * @param   mixed $resource
     * @param   array $arOptions
     * @return  bool
     */
    public function delete($resource, $arOptions)
    {
        // TODO: Implement delete() method.
        if (false) {
            $this->utilSetHttpStatus(200, "OK");
        } else {
            $this->utilSetHttpStatus(404, "Not found");
        }
    }

    /**
     * Get the available options for the given resource.
     * @param   mixed $resource
     * @param   array $arOptions
     * @return  mixed
     */
    public function options($resource, $arOptions)
    {
        // TODO: Implement options() method.
        if (false) {
            $this->utilSetHttpStatus(200, "OK");
        } else {
            $this->utilSetHttpStatus(404, "Not found");
        }
    }
}