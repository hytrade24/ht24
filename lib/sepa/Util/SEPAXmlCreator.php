<?php

namespace Scs\Common\BillingBundle\Util;

use Scs\Common\BillingBundle\Entity\SEPABankInformationInterface;
use Scs\Common\BillingBundle\Entity\SEPABatchBooking;
use Scs\Common\BillingBundle\Entity\SEPADirectDebitInitiation;
use Scs\Common\BillingBundle\Entity\SEPAPayment;
use Scs\Common\BillingBundle\Exception\SEPAException;


class SEPAXmlCreator
{

	/**
	 * Enthaelt Informationen spezifisch zur Bank.
	 *
	 * @var SEPABankInformationInterface
	 */
	private $bankInformation;

	/** @var SEPADirectDebitInitiation */
	private $ddInitation;

	/** @var SEPAPayment[] */
	private $payments;

	/** @var \DateTime */
	private $now;

	/** @var \DOMDocument */
	private $xml;

	/** @var \DOMXPath */
	private $xpath;


	public function __construct()
	{
		$this->now = new \DateTime();
	}

	/**
	 * @param mixed $node
	 * @param string $name
	 * @param null $value
	 * @return \DOMElement
	 */
	private function _createAndAppend($node, $name, $value = null)
	{
		$element = $this->xml->createElement($name, $value);
		if ($node instanceof \DOMNode) {
			$node->appendChild($element);
		} else {
			$this->_appendNodeOnXPath($node, $element);
		}
		return $element;
	}

	private function _appendNodeOnXPath($path, \DOMNode $node)
	{
		$nodes = $this->xpath->query($path);
		if (!$nodes->length) {
			return false;
		}

		$item = $nodes->item(0);
		$item->appendChild($node);
		return true;
	}

	/**
	 * Errechnet das Faelligkeitsdatum, so kurz wie moeglich
	 *
	 * @param integer $days
	 * @return \DateTime
	 */
	private function _calcNextRequestCollectionDate($days)
	{
		$collectionDate = clone $this->now;
		$dayInterval = new \DateInterval('P1D');
		do
		{
			$collectionDate->add($dayInterval);
			if (GermanHolidays::isWeekend($collectionDate) or GermanHolidays::isHoliday($collectionDate))
			{
				continue;
			}
			$days--;
		} while (0 < $days);
		return $collectionDate;
	}

	/**
	 * Gibt an, mit welchen SEPA Konditionen die Zahlung gebucht wird, um gleiche Typen in einer Sammellastschrift
	 * zu vereinen.
	 *
	 * @since 2014.10.07 Lastschrift Code und Anzahl der zu wartenden Tage wird aus Bankinformation ausgelesen
	 * @param SEPAPayment $payment
	 * @return SEPABatchBooking
	 */
	private function _determineSepaType(SEPAPayment $payment)
	{
		$days = $this->bankInformation->getCollectionDateDays($payment);
		$batch = new SEPABatchBooking();
		$batch->setLocalInstrumentCode($this->bankInformation->getLocalInstrumentCode($payment))
			->setSequenceType('RCUR')
			->setCollectionDate($this->_calcNextRequestCollectionDate($days));

		return $batch;
	}

	private function _generateBatchBooksXml(SEPABatchBooking $type)
	{
		$controlSum = 0;
		/** @var SEPAPayment $payment */
		$payments = $type->getPayments();
		foreach ($payments as $payment) {
			$controlSum += $payment->getAmount();
		}

		// PmtInf (Payment Instruction)
		$paymentInstruction = $this->_createAndAppend('//Document/CstmrDrctDbtInitn', 'PmtInf');
		// PmtInfId (Payment Information Identification)
		$this->_createAndAppend($paymentInstruction, 'PmtInfId', $type->getKey());
		// PmtMtd (Payment Method) [DD = DirectDebit - Lastschrift]
		$this->_createAndAppend($paymentInstruction, 'PmtMtd', 'DD');
		// BtchBookg (Batch Booking)
		$this->_createAndAppend($paymentInstruction, 'BtchBookg', 'true');
		// NbOfTxs (Number Of Transactions)
		$this->_createAndAppend($paymentInstruction, 'NbOfTxs', $payments->count());
		// CtrlSum (Control Sum)
		$this->_createAndAppend($paymentInstruction, 'CtrlSum', $controlSum);
		// PmtTpInf (PaymentTypeInformation)
		$paymentTypeInformation = $this->_createAndAppend($paymentInstruction, 'PmtTpInf');
		// SvcLvl (Service Level)
		$serviceLevel = $this->_createAndAppend($paymentTypeInformation, 'SvcLvl');
		// Cd (Service Level Code) [Nur SEPA zulaessig]
		$this->_createAndAppend($serviceLevel, 'Cd', 'SEPA');
		// LclInstrm (Local Instrument)
		$localInstrument = $this->_createAndAppend($paymentTypeInformation, 'LclInstrm');
		// Cd (Local Instrument Code) [CORE = Basis-, B2B = Firmenlastschrift, COR1 = Eillastschrift]
		$this->_createAndAppend($localInstrument, 'Cd', $type->getLocalInstrumentCode());
		// Berliner Volksbank (Erst- und Einmallastschrift = 6, Folgelastschrift = 3 Tage)
		// SeqTp (Sequence Type) [FRST = Erst-, RCUR = Folge-, OOFF = Einmal-, FNAL = letztmalige Lastschrift]
		$this->_createAndAppend($paymentTypeInformation, 'SeqTp', $type->getSequenceType());
		// ReqdColltnDt (Requested Collection Date)
		$date = $type->getCollectionDate();
		if ($this->ddInitation->getRequestDate() instanceof \DateTime and $this->ddInitation->getRequestDate() > $date)
		{
			$date = $this->ddInitation->getRequestDate();
		}
		$this->_createAndAppend($paymentInstruction, 'ReqdColltnDt', $date->format('Y-m-d'));
		// Cdtr (Creditor)
		$creditor = $this->_createAndAppend($paymentInstruction, 'Cdtr');
		// Nm (Name)
		$this->_createAndAppend($creditor, 'Nm', str_replace('&', '&amp;', $this->ddInitation->getName()));
		// CdtrAcct (Creditor Account)
		$creditorAccount = $this->_createAndAppend($paymentInstruction, 'CdtrAcct');
		// Id (Identification)
		$identification = $this->_createAndAppend($creditorAccount, 'Id');
		// IBAN (Creditor IBAN)
		$this->_createAndAppend($identification, 'IBAN', $this->ddInitation->getIban());
		// CdtrAgt (Creditor Agent)
		$creditorAgent = $this->_createAndAppend($paymentInstruction, 'CdtrAgt');
		// FinInstnId (Creditor Financial Institution Identification)
		$financialInstitutionIdentification = $this->_createAndAppend($creditorAgent, 'FinInstnId');
		// BIC (Creditor BIC)
		$this->_createAndAppend($financialInstitutionIdentification, 'BIC', $this->ddInitation->getBic());
		// CdtrSchmeId (Creditor Scheme Identification)
		$creditorSchemeIdentification = $this->_createAndAppend($paymentInstruction, 'CdtrSchmeId');
		// Id (Creditor Scheme Identification)
		$schemeIdentification = $this->_createAndAppend($creditorSchemeIdentification, 'Id');
		// PrvtId (Private Identification)
		$privateIdentification = $this->_createAndAppend($schemeIdentification, 'PrvtId');
		// Othr (Other Identification)
		$otherIdentification = $this->_createAndAppend($privateIdentification, 'Othr');
		// Id (Identification)
		$this->_createAndAppend($otherIdentification, 'Id', $this->ddInitation->getCreditorIdentifier());
		// SchmeNm (Scheme Name)
		$schemeName = $this->_createAndAppend($otherIdentification, 'SchmeNm');
		// Prtry (Property [Immer mit SEPA belegen])
		$this->_createAndAppend($schemeName, 'Prtry', 'SEPA');

		foreach ($type->getPayments() as $payment) {
			$this->_generatePaymentXml($payment);
		}
	}

	private function _generatePaymentXml(SEPAPayment $payment)
	{
		$nodes = $this->xpath->query('//Document/CstmrDrctDbtInitn/PmtInf');
		if (!$nodes->length)
		{
			return;
		}
		$paymentInstruction = $nodes->item($nodes->length -1);
		// DrctDbtTxInf (Direct Debit Transaction Information)
		$directDebitTransactionInformation = $this->_createAndAppend($paymentInstruction, 'DrctDbtTxInf');
		// PmtId (Payment Identification)
		$paymentIdentification = $this->_createAndAppend($directDebitTransactionInformation, 'PmtId');
		// EndToEndId (End To End Identification)
		$this->_createAndAppend($paymentIdentification, 'EndToEndId', $payment->getEndToEndId()?:'NOTPROVIDED');
		// InstdAmt (Instructed Amount)
		$instructedAmount = $this->_createAndAppend($directDebitTransactionInformation, 'InstdAmt', $payment->getAmount());
		$instructedAmount->setAttribute('Ccy', 'EUR');
		// DrctDbtTx (Direct Debit Transaction)
		$directDebitTransaction = $this->_createAndAppend($directDebitTransactionInformation, 'DrctDbtTx');
		// MndtRltdInf (Mandate Related Information)
		$mandateRelatedInformation = $this->_createAndAppend($directDebitTransaction, 'MndtRltdInf');
		// MndtId (Mandate Identification)
		$this->_createAndAppend($mandateRelatedInformation, 'MndtId', $payment->getMandateId());
		// DtOfSgntr (Date Of Signature)
		$this->_createAndAppend($mandateRelatedInformation, 'DtOfSgntr', $payment->getMandateDate()->format('Y-m-d'));
		// DbtrAgt (Debtor Agent)
		$debtorAgent = $this->_createAndAppend($directDebitTransactionInformation, 'DbtrAgt');
		// FinInstnId (Debtor Financial Institution Identification)
		$debtorFinancialInstitutionIdentification = $this->_createAndAppend($debtorAgent, 'FinInstnId');
		// BIC (Debtor BIC)
		$this->_createAndAppend($debtorFinancialInstitutionIdentification, 'BIC', $payment->getBic());
		// Dbtr (Debtor)
		$debtor = $this->_createAndAppend($directDebitTransactionInformation, 'Dbtr');
		// Nm (Debtor Name)
		$this->_createAndAppend($debtor, 'Nm', str_replace('&', '&amp;', $payment->getName()));
		// DbtrAcct (Debtor Account)
		$debtorAccount = $this->_createAndAppend($directDebitTransactionInformation, 'DbtrAcct');
		// Id (Identification)
		$debtorAccountIdentification = $this->_createAndAppend($debtorAccount, 'Id');
		// IBAN (Debtor IBAN)
		$this->_createAndAppend($debtorAccountIdentification, 'IBAN', $payment->getIban());
		// RmtInf (Remittance Information)
		$remittanceInformation = $this->_createAndAppend($directDebitTransactionInformation, 'RmtInf');
		// Ustrd (Unstructured = Verwendungszweck)
		$this->_createAndAppend($remittanceInformation, 'Ustrd', $payment->getPurpose());
	}

	private function _setXPathValue($path, $value)
	{
		$nodes = $this->xpath->query($path);
		if (!$nodes->length) {
			return false;
		}

		$node = $nodes->item(0);
		$node->nodeValue = $value;
		return true;
	}

	public function addPayment(SEPAPayment $transaction)
	{
		$this->payments[] = $transaction;
	}

	/**
	 * @throws SEPAException
	 * @return string
	 */
	public function generateXml()
	{
		if (empty($this->payments)) {
			throw new SEPAException('no payments present');
		}
		if (is_null($this->bankInformation)) {
			throw new SEPAException('no bank informations set');
		}

		// Zahlungen in einzelne Sammelauftraege aufteilen
		$amountSum = 0;
		/** @var SEPABatchBooking[] $types */
		$types = array();
		foreach ($this->payments as $payment) {
			$type = $this->_determineSepaType($payment);
			if (!isset($types[$type->getKey()]))
			{
				$types[$type->getKey()] = $type;
			}
			$types[$type->getKey()]->addPayment($payment);
			$amountSum += $payment->getAmount();
		}

		$this->xml = new \DOMDocument('1.0', 'utf-8');
		$this->xpath = new \DOMXPath($this->xml);
		// Document
		$document = $this->_createAndAppend($this->xml, 'Document');
		$document->setAttribute('xmlns', 'urn:iso:std:iso:20022:tech:xsd:pain.008.002.02');
		$document->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
		$document->setAttribute('xsi:schemaLocation', 'urn:iso:std:iso:20022:tech:xsd:pain.008.002.02 pain.008.002.02.xsd');
		// CstmrDrctDbtInitn (Customer Direct Debit Initiation)
		$customerDirectDebitInitiation = $this->_createAndAppend($document, 'CstmrDrctDbtInitn');
		// GrpHdr (Group Header)
		$groupHeader = $this->_createAndAppend($customerDirectDebitInitiation, 'GrpHdr');
		// MsgId (Message Id)
		$this->_createAndAppend($groupHeader, 'MsgId', $this->ddInitation->getMessageId());
		// CreDtTm (Creation Date Time)
		$this->_createAndAppend($groupHeader, 'CreDtTm', $this->now->format('c'));
		// NbOfTxs (Number Of Transactions)
		$this->_createAndAppend($groupHeader, 'NbOfTxs', count($this->payments));
		// CtrlSum (Control Sum)
		$this->_createAndAppend($groupHeader, 'CtrlSum', $amountSum);
		// InitgPty (Initiating Party)
		$initiatingParty = $this->_createAndAppend($groupHeader, 'InitgPty');
		// Nm (Name)
		$this->_createAndAppend($initiatingParty, 'Nm', str_replace('&', '&amp;', $this->ddInitation->getName()));

		// Alle Sammellastschriften erstellen
		foreach ($types as $type)
		{
			$this->_generateBatchBooksXml($type);
		}

		return $this->xml->saveXML();
	}

	/**
	 * @return \DateTime
	 */
	public function getNow()
	{
		return $this->now;
	}

	/**
	 * Gibt fuer die Zahlung das Datum zurueck, an dem es von der Bank verbucht wird.
	 *
	 * @since 2014.10.07
	 * @param SEPAPayment $payment
	 * @throws SEPAException
	 * @return \DateTime
	 */
	public function getPaymentRequestDate(SEPAPayment $payment)
	{
		if (is_null($this->bankInformation)) {
			throw new SEPAException('no bank informations set');
		}

		return $this->_calcNextRequestCollectionDate($this->bankInformation->getCollectionDateDays($payment));
	}

	/**
	 * Setzt die Bankspezifischen Informationen.
	 *
	 * @param SEPABankInformationInterface $bankInformation
	 * @return $this
	 */
	public function setBankInformation(SEPABankInformationInterface $bankInformation)
	{
		$this->bankInformation = $bankInformation;
		return $this;
	}

	/**
	 * @param SEPADirectDebitInitiation $ddInitation
	 * @return $this
	 */
	public function setDdInitation(SEPADirectDebitInitiation $ddInitation)
	{
		$this->ddInitation = $ddInitation;
		return $this;
	}

}