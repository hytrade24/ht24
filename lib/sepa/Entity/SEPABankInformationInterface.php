<?php

namespace Scs\Common\BillingBundle\Entity;


/**
 * Gibt fuer eine Bank spezifische Informationen fuer die SEPA-XML Datei zurueck.
 *
 * @author Thomas Rudolph <rudolph@secamedia.de>
 * @since 2014.10.07
 */
interface SEPABankInformationInterface
{

	/**
	 * Gibt die Anzahl der Tage zurueck die fuer diese Zahlung gewartet werden muss.
	 *
	 * @param SEPAPayment $payment
	 * @return int
	 */
	public function getCollectionDateDays(SEPAPayment $payment);

	/**
	 * Gibt den Code fuer die Lastschriftart zur angegebenen Zahlung zurueck.
	 *
	 * @param SEPAPayment $payment
	 * @return string
	 */
	public function getLocalInstrumentCode(SEPAPayment $payment);

}
