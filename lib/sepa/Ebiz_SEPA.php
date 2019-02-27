<?php
/**
 * Created by PhpStorm.
 * User: shafaatbinjaved
 * Date: 3/3/2017
 * Time: 11:01 AM
 */

use Scs\Common\BillingBundle\Entity\BerlinerVolksbankBankInformation;
use Scs\Common\BillingBundle\Entity\SEPADirectDebitInitiation;
use Scs\Common\BillingBundle\Entity\SEPAPayment;
use Scs\Common\BillingBundle\Util\SEPAXmlCreator;

include ("vendor/autoload.php");

require_once ("Util/SEPAXmlCreator.php");
require_once ("Entity/BerlinerVolksbankBankInformation.php");
require_once ("Entity/SEPADirectDebitInitiation.php");
require_once ("Entity/SEPAPayment.php");

class Ebiz_SEPA {

    private $creditorIdentifier;
    private $requestDate;
    private $ddInitiationBic;
    private $ddInitiationIban;
    private $ddInitiationName;
    private $mandateId;
    private $msgId;

    private $paymentBic;
    private $paymentIban;
    private $paymentName;
    private $paymentPurpose;
    private $endToEndId;
    private $dtOfSgntr;

    private $requestedAmount;

    private $creator;

    function __construct()
    {
    }

    function initiatorDetails( $creditorIdentifier, $reqDate, $ddInitiationName, $ddInitiatorBIC, $ddInitiatorIBAN, $msgId ) {
        $this->creator = new SEPAXmlCreator();
        $this->creator->setBankInformation(new BerlinerVolksbankBankInformation());
        $this->creditorIdentifier = $creditorIdentifier;//'T35T';
        $this->requestDate = new \DateTime($reqDate);//new \DateTime('+5days');
        $this->ddInitiationName = $ddInitiationName;
        $this->ddInitiationBic = $ddInitiatorBIC;//'BELADEBEXXX';
        $this->ddInitiationIban = $ddInitiatorIBAN;//'DE46100500001234567890';
        $this->msgId = $msgId;
    }

    function setInitiator() {
        $ddInitiation = new SEPADirectDebitInitiation();
        $ddInitiation->setCreditorIdentifier($this->creditorIdentifier)
            ->setName($this->ddInitiationName)
            ->setMessageId($this->msgId)//->setMessageId(1)
            ->setIban($this->ddInitiationIban)
            ->setBic($this->ddInitiationBic)
            ->setRequestDate($this->requestDate);
        $this->creator->setDdInitation($ddInitiation);
    }

    function addPayment() {
        $payment = new SEPAPayment();
        $payment->setName($this->paymentName)
            ->setMandateId($this->mandateId)
            ->setMandateDate(new \DateTime($this->dtOfSgntr))
            ->setAmount($this->requestedAmount)
            ->setIban($this->paymentIban)
            ->setBic($this->paymentBic)
            ->setEndToEndId($this->endToEndId)//(1)
            ->setPurpose($this->paymentPurpose);
        $this->creator->addPayment($payment);
    }

    function paymentDetails($bic, $iban, $payName, $payPurpose, $reqAmount, $endToEndId, $mandateId, $dtOfSgntr) {
        $this->paymentBic = $bic;//'BEVODEBBXXX';
        $this->paymentIban = $iban;//'DE79100900002345678901';
        $this->paymentName = $payName;//'paymentName';
        $this->paymentPurpose = $payPurpose;//'paymentPurpose';
        $this->requestedAmount = $reqAmount;
        $this->endToEndId = $endToEndId;
        $this->mandateId = $mandateId;
        $this->dtOfSgntr = $dtOfSgntr;
    }

    function execute() {
        return $this->creator->generateXml();
    }
}