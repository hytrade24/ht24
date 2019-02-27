<?php

namespace Scs\Common\BillingBundle\Exception;

use Exception;


class DTAUSParseException extends \Exception
{

	public function __construct($message = "", $code = 0, Exception $previous = null)
	{
		if (!$message)
		{
			$message = 'Error while parsing DTAUS data';
		}
		parent::__construct($message, $code, $previous);
	}

}