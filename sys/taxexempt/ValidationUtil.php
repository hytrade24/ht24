<?php
class TaxExempt_ValidationUtil {

	static function checkUstId($ustid) {
		$u = strtoupper(preg_replace('~\W~', '', $ustid));
		$country = substr($u, 0, 2);
		switch ($country) {
			case 'AT':
				return (preg_match('~^ATU[A-Z0-9]{8,8}$~', $u) !== 0);
				break;
			case 'BE':
				return (preg_match('~^BE0[0-9]{9,9}$~', $u) !== 0);
				break;
			case 'BG':
				return (preg_match('~^BG[0-9]{9,10}$~', $u) !== 0);
				break;
			case 'CY':
				return (preg_match('~^CY[A-Z0-9]{9,9}$~', $u) !== 0);
				break;
			case 'CZ':
				return (preg_match('~^CZ[0-9]{8,10}$~', $u) !== 0);
				break;
			case 'DE':
				return (preg_match('~^DE[0-9]{9,12}$~', $u) !== 0);
				break;
			case 'DK':
				return (preg_match('~^DK[0-9]{8,8}$~', $u) !== 0);
				break;
			case 'EE':
				return (preg_match('~^EE[0-9]{9,9}$~', $u) !== 0);
				break;
			case 'EL':
				return (preg_match('~^EL[0-9]{9,9}$~', $u) !== 0);
				break;
			case 'ES':
				return (preg_match('~^ES[A-Z0-9]{1,1}[0-9]{7,7}[A-Z0-9]{1,1}$~', $u) !== 0);
				break;
			case 'FI':
				return (preg_match('~^FI[0-9]{8,8}$~', $u) !== 0);
				break;
			case 'FR':
				return (preg_match('~^FR[A-Z0-9]{2,2}[0-9]{9,9}$~', $u) !== 0);
				break;
			case 'GB':
				$result = (preg_match('~^GB[0-9]{9,9}$~', $u) !== 0);
				if ($result) return TRUE;
				$result = (preg_match('~^GB[0-9]{12,12}$~', $u) !== 0);
				if ($result) return TRUE;
				$result = (preg_match('~^GBGD[0-9]{3,3}$~', $u) !== 0);
				if ($result) return TRUE;

				return (preg_match('~^GBHA[0-9]{3,3}$~', $u) !== 0);
				break;
			case 'HU':
				return (preg_match('~^HU[0-9]{8,8}$~', $u) !== 0);
				break;
			case 'IE':
				return (preg_match('~^IE[A-Z0-9]{8,8}$~', $u) !== 0);
				break;
			case 'IT':
				return (preg_match('~^IT[0-9]{11,11}$~', $u) !== 0);
				break;
			case 'LT':
				$result = (preg_match('~^LT[0-9]{9,9}$~', $u) !== 0);
				if ($result) return TRUE;

				return (preg_match('~^LT[0-9]{12,12}$~', $u) !== 0);
				break;
			case 'LU':
				return (preg_match('~^LU[0-9]{8,8}$~', $u) !== 0);
				break;
			case 'LV':
				return (preg_match('~^LV[0-9]{11,11}$~', $u) !== 0);
				break;
			case 'MT':
				return (preg_match('~^MT[0-9]{8,8}$~', $u) !== 0);
				break;
			case 'NL':
				return (preg_match('~^NL[A-Z0-9]{9,9}B[A-Z0-9]{2,2}$~', $u) !== 0);
				break;
			case 'PL':
				return (preg_match('~^PL[0-9]{10,10}$~', $u) !== 0);
				break;
			case 'PT':
				return (preg_match('~^MT[0-9]{9,9}$~', $u) !== 0);
				break;
			case 'RO':
				return (preg_match('~^RO[0-9]{2,10}$~', $u) !== 0);
				break;
			case 'SE':
				return (preg_match('~^SE[0-9]{12,12}$~', $u) !== 0);
				break;
			case 'SI':
				return (preg_match('~^SI[0-9]{8,8}$~', $u) !== 0);
				break;
			case 'SK':
				return (preg_match('~^SK[0-9]{10,10}$~', $u) !== 0);
				break;
		}

		return FALSE;
	}
}
