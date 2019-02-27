<?php

namespace Scs\Common\BillingBundle\Tests\Util;

use Scs\Common\BillingBundle\Entity\BerlinerVolksbankBankInformation;
use Scs\Common\BillingBundle\Entity\SEPADirectDebitInitiation;
use Scs\Common\BillingBundle\Entity\SEPAPayment;
use Scs\Common\BillingBundle\Util\SEPAXmlCreator;


/**
 * Testet die SEPAXmlCreator Klasse.
 *
 * @author Thomas Rudolph <rudolph@secamedia.de>
 * @since 2014.10.07
 */
class SEPAXmlCreatorTest extends \PHPUnit_Framework_TestCase
{

	public function testGenerateXml()
	{
		$creditorIdentifier = 'T35T';
		$date = new \DateTime('+5days');
		$ddInitiationBic = 'BELADEBEXXX';
		$ddInitiationIban = 'DE46100500001234567890';
		$ddInitiationName = 'ddInitiationName';
		$creator = new SEPAXmlCreator();
		$creator->setBankInformation(new BerlinerVolksbankBankInformation());
		$ddInitiation = new SEPADirectDebitInitiation();
		$ddInitiation->setCreditorIdentifier($creditorIdentifier)
			->setName($ddInitiationName)
			->setMessageId(1)
			->setIban($ddInitiationIban)
			->setBic($ddInitiationBic)
			->setRequestDate($date);
		$creator->setDdInitation($ddInitiation);

		$payment = new SEPAPayment();
		$paymentBic = 'BEVODEBBXXX';
		$paymentIban = 'DE79100900002345678901';
		$paymentName = 'paymentName';
		$paymentPurpose = 'paymentPurpose';
		$payment->setName($paymentName)
			->setMandateId(1)
			->setMandateDate(new \DateTime())
			->setAmount(100.0)
			->setIban($paymentIban)
			->setBic($paymentBic)
			->setEndToEndId(1)
			->setPurpose($paymentPurpose);
		$creator->addPayment($payment);

		$xml = $creator->generateXml();
		echo '<pre>';
		var_dump( $xml );
		echo '</pre>';
		/*$this->assertNotEmpty($xml);
		$this->assertNotFalse(strpos($xml, $creditorIdentifier));
		$this->assertNotFalse(strpos($xml, $date->format('Y-m-d')));
		$this->assertNotFalse(strpos($xml, $ddInitiationBic));
		$this->assertNotFalse(strpos($xml, $ddInitiationIban));
		$this->assertNotFalse(strpos($xml, $ddInitiationName));
		$this->assertNotFalse(strpos($xml, $paymentBic));
		$this->assertNotFalse(strpos($xml, $paymentIban));
		$this->assertNotFalse(strpos($xml, $paymentName));
		$this->assertNotFalse(strpos($xml, $paymentPurpose));*/
	}

}
