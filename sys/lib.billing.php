<?php
/* ###VERSIONSBLOCKINLCUDE### */


  /**
   * Interface for creating bills.
   * 
   * @package Categories
   * @subpackage Public
   */  
  interface Billing {
    /**
     * Generates a bill for the element with $id.
     *
     * @param   int     $id       ID of the element to create a bill for.
     * 
     * @return  array   An array with all data relavant to create a bill.
     */
    public function getBill($id);
  }
?>