<?php


namespace Scs\Common\BillingBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Scs\Common\BillingBundle\Tests;

class ScsCommonBillingBundle extends Bundle
{
    function __construct()
    {
        //include ("vendor/autoload.php");
        $a = new SEPAXmlCreatorTest();
        $a->testGenerateXml();

    }
}
