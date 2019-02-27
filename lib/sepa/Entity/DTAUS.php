<?php

namespace Scs\Common\BillingBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;


class DTAUS
{

	/** @var \DateTime */
	private $date;

	/** @var string */
	private $ownerBankNumber;

	/** @var string */
	private $ownerDebitNumber;

	/** @var string */
	private $ownerName;

	/** @var ArrayCollection */
	private $payments;

	/** @var string */
	private $referenceNumber;

	/** @var \DateTime */
	private $requestDate;


	public function __construct()
	{
		$this->payments = new ArrayCollection();
	}

	/**
	 * @param \DateTime $date
	 * @return $this
	 */
	public function setDate(\DateTime $date)
	{
		$this->date = $date;
		return $this;
	}

	/**
	 * @return \DateTime
	 */
	public function getDate()
	{
		return $this->date;
	}

	/**
	 * @param string $ownerBankNumber
	 * @return $this
	 */
	public function setOwnerBankNumber($ownerBankNumber)
	{
		$this->ownerBankNumber = $ownerBankNumber;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getOwnerBankNumber()
	{
		return $this->ownerBankNumber;
	}

	/**
	 * @param string $ownerDebitNumber
	 * @return $this
	 */
	public function setOwnerDebitNumber($ownerDebitNumber)
	{
		$this->ownerDebitNumber = $ownerDebitNumber;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getOwnerDebitNumber()
	{
		return $this->ownerDebitNumber;
	}

	/**
	 * @param string $ownerName
	 * @return $this
	 */
	public function setOwnerName($ownerName)
	{
		$this->ownerName = $ownerName;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getOwnerName()
	{
		return $this->ownerName;
	}

	/**
	 * @param ArrayCollection $payments
	 * @return $this
	 */
	public function setPayments($payments)
	{
		$this->payments = $payments;
		return $this;
	}

	/**
	 * @return ArrayCollection
	 */
	public function getPayments()
	{
		return $this->payments;
	}

	/**
	 * @param DTAUSPayment $payment
	 * @return $this
	 */
	public function addPayment(DTAUSPayment $payment)
	{
		$this->payments->add($payment);
		return $this;
	}

	/**
	 * @param string $referenceNumber
	 * @return $this
	 */
	public function setReferenceNumber($referenceNumber)
	{
		$this->referenceNumber = $referenceNumber;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getReferenceNumber()
	{
		return $this->referenceNumber;
	}

	/**
	 * @param \DateTime $requestDate
	 * @return $this
	 */
	public function setRequestDate(\DateTime $requestDate)
	{
		$this->requestDate = $requestDate;
		return $this;
	}

	/**
	 * @return \DateTime
	 */
	public function getRequestDate()
	{
		return $this->requestDate;
	}

}