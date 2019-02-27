<?php

namespace Scs\Common\BillingBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;


class SEPABatchBooking
{

	/** @var \DateTime */
	private $collectionDate;

	/** @var string */
	private $localInstrumentCode;

	/** @var ArrayCollection */
	private $payments;

	/** @var string */
	private $sequenceType;


	public function __construct()
	{
		$this->payments = new ArrayCollection();
	}

	/**
	 * @param \DateTime $collectionDate
	 * @return $this
	 */
	public function setCollectionDate(\DateTime $collectionDate)
	{
		$this->collectionDate = $collectionDate;
		return $this;
	}

	/**
	 * @return \DateTime
	 */
	public function getCollectionDate()
	{
		return $this->collectionDate;
	}

	/**
	 * @param string $localInstrumentCode
	 * @return $this
	 */
	public function setLocalInstrumentCode($localInstrumentCode)
	{
		$this->localInstrumentCode = $localInstrumentCode;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getLocalInstrumentCode()
	{
		return $this->localInstrumentCode;
	}

	/**
	 * @return ArrayCollection
	 */
	public function getPayments()
	{
		return $this->payments;
	}

	/**
	 * @param ArrayCollection $payments
	 */
	public function setPayments($payments)
	{
		$this->payments = $payments;
	}

	/**
	 * @param SEPAPayment $payment
	 * @return $this
	 */
	public function addPayment(SEPAPayment $payment)
	{
		$this->payments->add($payment);
		return $this;
	}

	/**
	 * @param string $sequenceType
	 * @return $this
	 */
	public function setSequenceType($sequenceType)
	{
		$this->sequenceType = $sequenceType;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getSequenceType()
	{
		return $this->sequenceType;
	}

	/**
	 * Gibt einen Schluessel fuer den Sammelauftrag zurueck, bestehend aus
	 * Datum (Y-m-d), dem SEPA-Typ und dem Buchungstyp, getrennt mit Punkten.
	 * Beispiel: 2014-02-18.CORE.RCUR
	 *
	 * @return string
	 */
	public function getKey()
	{
		return $this->collectionDate->format('Y-m-d').'.'.
			$this->localInstrumentCode.'.'.
			$this->sequenceType;
	}

}