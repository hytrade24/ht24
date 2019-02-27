<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 30.10.15
 * Time: 09:18
 */

interface Api_Plugins_RestApi_ServerInterface {

    /**
     * Get the resource details.
     * @param   mixed   $resource
     * @param   array   $arOptions
     * @return  array|null
     */
    public function get($resource, $arOptions);

    /**
     * Create a resource / other non-idempotent operations
     * @param   mixed   $resource
     * @param   array   $arOptions
     * @return  bool
     */
    public function post($resource, $arOptions);

    /**
     * Save the given resource. If it already exists it will be updated.
     * @param   mixed   $resource
     * @param   array   $arOptions
     * @param   array   $arData
     * @return  bool
     */
    public function put($resource, $arOptions);

    /**
     * Delete the given resource.
     * @param   mixed   $resource
     * @param   array   $arOptions
     * @return  bool
     */
    public function delete($resource, $arOptions);

    /**
     * Get the available options for the given resource.
     * @param   mixed   $resource
     * @param   array   $arOptions
     * @return  mixed
     */
    public function options($resource, $arOptions);
    
}