<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 01.09.14
 * Time: 14:31
 */

interface Export_Xml_ExportAdapterInterface {

    /**
     * Add header / opening tags to the xml document
     * @return bool     true for success, false for failure.
     */
    function documentInitialize();

    /**
     * Close tags opened on documentInitialize()
     * @return bool     true for success, false for failure.
     */
    function documentFinish();

    function getDatabaseQuery($arFilter = array());

    /**
     * Reads all objects and writes them into the xml file
     * @param int   $limit
     * @param int   $offset
     * @param array $arFilter
     * @return bool     true for success, false for failure.
     */
    function process($arFilter = array(), $limit = null, $offset = null);

    /**
     * Query all objects
     * @param array $arFilter
     * @param int   $limit
     * @param int   $offset
     * @return bool     true for success, false for failure.
     */
    function queryData($arFilter = array(), $limit = null, $offset = null);

    /**
     * Reads the one element for the export e.g. from the database
     * @return bool     true for success, false for failure.
     */
    function readObject();

    /**
     * Process the result from readObject and transform it for the xml export
     * @return bool     true for success, false for failure.
     */
    function transformObject();

    /**
     * Write the transformed object into the xml document
     * @return bool     true for success, false for failure.
     */
    function writeObject();

}