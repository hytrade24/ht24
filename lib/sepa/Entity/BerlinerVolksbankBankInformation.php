<?php

namespace Scs\Common\BillingBundle\Entity;


/**
 * Bankspezifische Informationen zur Berliner Volksbank.
 *
 * @author Thomas Rudolph <rudolph@secamedia.de>
 * @since 2014.10.07
 */
class BerlinerVolksbankBankInformation implements SEPABankInformationInterface
{

	/**
	 * {@inheritdoc}
	 */
	public function getCollectionDateDays(SEPAPayment $payment)
	{
		// Immer 2 Tage fuer Eillastschrift eintragen (Berliner Volksbank)
		$days = 2;
		// Wenn Transaktion nicht aus Deutschland kommt
		if (false === strpos($payment->getIban(), 'DE'))
		{
			// mit Folgelastschrift arbeiten, Faelligkeit 3 Tage (Berliner Volksbank)
			$days = 3;
		}

		return $days;
	}

	/**
	 * {@inheritdoc}
	 */
	public function getLocalInstrumentCode(SEPAPayment $payment)
	{
		// Immer 2 Tage fuer Eillastschrift eintragen (Berliner Volksbank)
		$code = 'COR1';
		// Wenn Transaktion nicht aus Deutschland kommt
		if (false === strpos($payment->getIban(), 'DE'))
		{
			// mit Folgelastschrift arbeiten, Faelligkeit 3 Tage (Berliner Volksbank)
			// Normale Basislastschrift benutzen
			$code = 'CORE';
		}

		return $code;
	}

}
