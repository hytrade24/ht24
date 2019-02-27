<?php

namespace Scs\Common\BillingBundle\Entity;

use Scs\Common\BillingBundle\Exception\SEPAException;


class SEPADirectDebitInitiation
{

	/** @var string */
	private $bic;

	/** @var string Glaeubiger Identifikationsnummer */
	private $creditorIdentifier;

	/** @var string */
	private $iban;

	/** @var string */
	private $messageId;

	/** @var string */
	private $name;

	/** @var \DateTime */
	private $requestDate;


	private function checkBic()
	{
		if (!$this->bic) {
			throw new SEPAException('creditor bic not set');
		}
	}

	/**
	 * @return string
	 */
	public function getBic()
	{
		$this->checkBic();
		return $this->bic;
	}

	/**
	 * @param string $bic
	 * @return $this
	 */
	public function setBic($bic)
	{
		$this->bic = $bic;
		$this->checkBic();
		return $this;
	}

	/**
	 * @throws SEPAException
	 */
	private function checkCreditorIdentifier()
	{
		if (!$this->creditorIdentifier) {
			throw new SEPAException('creditor identifier not set');
		}
	}

	/**
	 * @return string
	 */
	public function getCreditorIdentifier()
	{
		$this->checkCreditorIdentifier();
		return $this->creditorIdentifier;
	}

	/**
	 * @param string $creditorIdentifier
	 * @return $this
	 */
	public function setCreditorIdentifier($creditorIdentifier)
	{
		$this->creditorIdentifier = $creditorIdentifier;
		$this->checkCreditorIdentifier();
		return $this;
	}

	/**
	 * @throws SEPAException
	 */
	private function checkIban()
	{
		if (!$this->iban) {
			throw new SEPAException('creditor iban not set');
		}
	}

	/**
	 * @return string
	 */
	public function getIban()
	{
		$this->checkIban();
		return $this->iban;
	}

	/**
	 * @param string $iban
	 * @return $this
	 */
	public function setIban($iban)
	{
		$this->iban = $iban;
		$this->checkIban();
		return $this;
	}

	/**
	 * @throws SEPAException
	 */
	private function checkMessageId()
	{
		if (!$this->messageId) {
			throw new SEPAException('message id not set');
		}
	}

	/**
	 * @return string
	 */
	public function getMessageId()
	{
		return $this->messageId;
	}

	/**
	 * @param string $messageId
	 * @return $this
	 */
	public function setMessageId($messageId)
	{
		$this->messageId = $messageId;
		$this->checkMessageId();
		return $this;
	}

	/**
	 * @throws SEPAException
	 */
	private function checkName()
	{
		if (!$this->name) {
			throw new SEPAException('creditor name not set');
		}
		if (70 < mb_strlen($this->name)) {
			throw new SEPAException('creditor name is too long');
		}
	}

	/**
	 * @return string
	 */
	public function getName()
	{
		$this->checkName();
		return $this->name;
	}

	/**
	 * @param string $name
	 * @return $this
	 */
	public function setName($name)
	{
		$this->name = $name;
		$this->checkName();
		return $this;
	}

	/**
	 * @return \DateTime
	 */
	public function getRequestDate()
	{
		return $this->requestDate;
	}

	/**
	 * @param \DateTime $requestDate
	 * @return $this
	 */
	public function setRequestDate($requestDate)
	{
		$this->requestDate = $requestDate;
		return $this;
	}

}