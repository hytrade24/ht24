<?php

namespace Scs\Common\BillingBundle\Entity;

use Scs\Common\BillingBundle\Exception\SEPAException;


class SEPAPayment
{

	/** @var float */
	private $amount;

	/** @var string */
	private $iban;

	/** @var string */
	private $bic;

	/** @var integer */
	private $mandateId;

	/** @var \DateTime */
	private $mandateDate;

	/** @var string */
	private $name;

	/** @var string */
	private $purpose;

	/** @var string */
	private $endToEndId;


	/**
	 * @throws SEPAException
	 */
	private function checkAmount()
	{
		if (!$this->amount)
		{
			$message = 'no amount set';
			if ($this->endToEndId)
			{
				$message .= sprintf(' for id: %d', $this->endToEndId);
			}
			throw new SEPAException($message);
		}
	}

	/**
	 * @return float
	 */
	public function getAmount()
	{
		$this->checkAmount();
		return $this->amount;
	}

	/**
	 * @param float $amount
	 * @return $this
	 */
	public function setAmount($amount)
	{
		$this->amount = $amount;
		$this->checkAmount();
		return $this;
	}

	private function checkIban()
	{
		if (!$this->iban)
		{
			$message = 'debtor iban not set';
			if ($this->endToEndId)
			{
				$message .= sprintf(' for id: %d', $this->endToEndId);
			}
			throw new SEPAException($message);
		}
		if (34 < strlen($this->iban))
		{
			$message = sprintf('debtor iban too long: "%s"', $this->iban);
			if ($this->endToEndId)
			{
				$message .= sprintf(' (id: %d)', $this->endToEndId);
			}
			throw new SEPAException($message);
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
	private function checkBic()
	{
		if (!(8 == strlen($this->bic) or 11 == strlen($this->bic)))
		{
			$message = sprintf('not a valid debtor bic: "%s"', $this->bic);
			if ($this->endToEndId)
			{
				$message .= sprintf(' (id: %d)', $this->endToEndId);
			}
			throw new SEPAException($message);
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
	private function checkMandateId()
	{
		if (!$this->mandateId)
		{
			$message = 'mandate id not set';
			if ($this->endToEndId)
			{
				$message .= sprintf(' for id: %d', $this->endToEndId);
			}
			throw new SEPAException($message);
		}
	}

	/**
	 * @return int
	 */
	public function getMandateId()
	{
		$this->checkMandateId();
		return $this->mandateId;
	}

	/**
	 * @param int $mandateId
	 * @return $this
	 */
	public function setMandateId($mandateId)
	{
		$this->mandateId = $mandateId;
		$this->checkMandateId();
		return $this;
	}

	private function checkMandateDate()
	{
		if (!($this->mandateDate instanceof \DateTime))
		{
			$message = 'mandate date not instance of DateTime';
			if ($this->endToEndId)
			{
				$message .= sprintf(' for id: %d', $this->endToEndId);
			}
			throw new SEPAException($message);
		}
	}

	/**
	 * @return \DateTime
	 */
	public function getMandateDate()
	{
		$this->checkMandateDate();
		return $this->mandateDate;
	}

	/**
	 * @param \DateTime $mandateDate
	 * @return $this
	 */
	public function setMandateDate(\DateTime $mandateDate)
	{
		$this->mandateDate = $mandateDate;
		return $this;
	}

	/**
	 * @throws SEPAException
	 */
	private function checkName()
	{
		if (!$this->name)
		{
			$message = 'debtor name not set';
			if ($this->endToEndId)
			{
				$message .= sprintf(' for id: %d', $this->endToEndId);
			}
			throw new SEPAException($message);
		}
		if (70 < mb_strlen($this->name))
		{
			$message = sprintf('debtor name too long: "%s"', $this->name);
			if ($this->endToEndId)
			{
				$message .= sprintf(' (id: %d)', $this->endToEndId);
			}
			throw new SEPAException($message);
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

	private function checkPurpose()
	{
		if (140 < mb_strlen($this->purpose))
		{
			$message = sprintf('purpose too long: "%s"', $this->purpose);
			if ($this->endToEndId)
			{
				$message .= sprintf(' (id: %d)', $this->endToEndId);
			}
			throw new SEPAException($message);
		}
	}

	/**
	 * @return string
	 */
	public function getPurpose()
	{
		$this->checkPurpose();
		return $this->purpose;
	}

	/**
	 * @param string $purpose
	 * @return $this
	 */
	public function setPurpose($purpose)
	{
		$this->purpose = $purpose;
		$this->checkPurpose();
		return $this;
	}

	/**
	 * @throws SEPAException
	 */
	private function checkEndToEndId()
	{
		if (35 < mb_strlen($this->endToEndId))
		{
			throw new SEPAException(sprintf('EndToEndId too long: "%s"', $this->endToEndId));
		}
	}

	/**
	 * @return string
	 */
	public function getEndToEndId()
	{
		return $this->endToEndId;
	}

	/**
	 * @param string $endToEndId
	 * @return $this
	 */
	public function setEndToEndId($endToEndId)
	{
		$this->endToEndId = $endToEndId;
		$this->checkEndToEndId();
		return $this;
	}

}