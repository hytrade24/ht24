<?php
/* **************************************************************************************************************************************************************************************** */
/* UIDvalidationAustria																																			VERSION: 1.1.6 / 2016-03-01	*/
/*		ueberprueft eine UID beim oestereichischen FinanzOnline (oder EU VIES) webservice																									*/
/* Copyright (c) 	Schultz IT Solutions    http://www.schultz.ch           			  																							    	*/
/* **************************************************************************************************************************************************************************************** */
/* **************************************************************************************************************************************************************************************** */
/* **************************************************************************************************************************************************************************************** */
/* USAGE:																																	      											*/
/* **************************************************************************************************************************************************************************************** */
/* INCLUDE THE CLASS INTO YOUR SCRIPT AND CREATE THE UIDvaliationAustria INSTANCE						      																				*/
//		require_once(USE_CORRECT_PATH_TO_SCRIPT."class.uid-validation-austria.php");
//		$UIDvalidationAustria = new UIDvalidationAustria($referenznummer);	
/* **************************************************************************************************************************************************************************************** */
/* IF REQUIRED, EVALUATE THE USERS HOME COUNTRY															      																				*/
//		$resultCountryEvaluation = $UIDvalidationAustria->evaluateCountry($country, $domainname);
//		if ($resultCountryEvaluation)
//		{
//			// React on the evaluation, if it returns AT LEAST TWO consistant "proofs of country"
//		}
//		else
//		{
//			// React on the evaluation, if it returns ONLY ONE "proof of country"
//		}
/* **************************************************************************************************************************************************************************************** */
/* GET VAT RATES FOR THE NAMED COUNTRY																	      																				*/
//		$VATrates = $UIDvalidationAustria->getVATrates($country);
/* **************************************************************************************************************************************************************************************** */
/* CALL THE VALIDATION WITH PARAMETERS: Referenznummer, ISOCountrycode, UIDtoValidate, Validationlevel, validateATU [, Name, Address]						      							*/
//		$validationResult = $UIDvalidationAustria->validateUID ( $referenznummer, $ISOcountrycode, $uid2validate, $validationlevel, $validateATU , $name, $address);	
/* **************************************************************************************************************************************************************************************** */
/* NOTES:																													      															*/
/*		referenznummer:																																										*/
/* 			Die Kundennummer des Händlers bei der Schultz IT Solutions (im Format 123-45678:xxxxxxxxxxxx)																					*/
/*		validation levels: 																								      																*/
/* 			1 => einfaches Bestätigungsverfahren, 													      																					*/
/* 			2 => qualifiziertes Bestätigungsverfahren, 														      																			*/
/*		validateATU: 																						      																			*/
/* 			0 => oesterreichische UIDs werden nicht geprueft													      																		*/
/* 			1 => auch oesterreichische UIDs werden geprueft														      																		*/
/*		parameters NAME and ADDRESS:																																						*/
/* 			are required for validation levels 2																																			*/
/* 												      																																		*/
/* REACT ON THE RESULTING ARRAY				
//		$VATrateStandard	= $validationResult["EUmemberstateStandardVATrate"];
//		$VATrateReduced		= $validationResult["EUmemberstateReducedVATrate"];	
/*																																      														*/
// 		switch ($validationResult["OverallValidationResult"]) 																	      														 
// 		{				
//			Result 00 bis 09: Resultat fuer die Validierung einer EU UID																										      														 
// 			case 0:			//	0  => UNKNOWN																						      													 
// 							$reaction = "Validierung konnte NICHT durchgefuehrt werden - UST aufschlagen!";	
// 							break;																									      													 
// 			case 1:			//  1  => OK, UID Nummer ist gueltig (EU AUSLAND)														      													 
//							$reaction = "Validierung erfolgreich - EU Unternehmen mit gueltiger UID, UST nicht aufschlagen!";				      											 
// 							break;																											      											 
// 			case 2:			//  2  => OK, UID Nummer ist gueltig (INLAND)																	      											 
// 							$reaction = "Validierung erfolgreich - AT Unternehmen mit gueltiger UID, UST aufschlagen!";						      											 
// 							break;																											      											 
// 			case 5:			//  5  => NOT OK, UID Nummer ist nicht gueltig																	      											 
// 							$reaction = "Validierung erfolgreich - UID Nummer ungueltig - UST aufschlagen!";								      											 
// 							break;																											      											 
// 			case 6:			//  6  => NOT OK, UID Nummer ist gueltig, aber Stufe-2 Validierung war ungenuegend							      												 
// 							$reaction = "Validierung erfolgreich - UID Nummer gueltig - Stufe-2 Validierung ungenuegend - UST aufschlagen!";												 
// 							break;																									      													 
// 			case 8:			//  8  => NOT NEEDED or NOT REQUESTED (Inland oder EU ohne UID)											      													 
// 							$reaction = "Validierung nicht durchgeführt - EU Privatperson o. AT Unternehmen - UST aufschlagen!";			      											 
// 							break;																										      												 
// 			case 9:			//  9  => NOT NEEDED (Ausland ausserhalb EU)																      												 
// 							$reaction = "Validierung nicht durchgefuehrt - Export - UST nicht aufschlagen!";							      												 
// 							break;																										      												 
//			-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
//			Result 10 bis 19: Resultat fuer die Validierung einer Drittstaaten UID	(UST ist nicht aufzuschlagen, unabhaengig vom Ergebnis der UID Validierung)																									      														 
// 			case 10:		//	10  => UNKNOWN																						      													 
// 							$reaction = "Validierung der Drittstaaten Unternehmens UID konnte NICHT durchgefuehrt werden!";							      													 
// 							break;																									      													 
// 			case 11:		//  11  => OK, UID Nummer ist gueltig (Ausland ausserhalb EU)														      													 
//							$reaction = "Validierung erfolgreich - UID des Drittstaaten Unternehmens ist gueltig!";				      											 
// 							break;																											      											 
// 			case 15:		//  15  => NOT OK, UID Nummer ist nicht gueltig																	      											 
// 							$reaction = "Validierung erfolgreich - UID des Drittstaaten Unternehmens ist ungueltig!";								      											 
// 							break;																											      											 
// 			case 16:		//  16  => NOT OK, UID Nummer ist gueltig, aber Stufe-2 Validierung war ungenuegend							      												 
// 							$reaction = "Validierung erfolgreich - UID des Drittstaaten Unternehmens ist gueltig - Stufe2 Validierung ungenuegend!";												 
// 							break;																									      													 
// 		}																																      												 
/* **************************************************************************************************************************************************************************************** */
/* 																																		      												*/
/* **************************************************************************************************************************************************************************************** */
class UIDvalidationAustria 
{
/* **************************************************************************************************************************************************************************************** */
/* **************************************************************************************************************************************************************************************** */
	/**
	 * Method to automatically construct the instance and initialize the values
	 * @param	
	 * @return  
	 */
	function __construct()
	{
		$functionArguments	=	func_get_args(); 
		$referenznummer		=	$functionArguments[0];
		$validationResult	=	array();
		$otherError = error_get_last(); if ($otherError && $otherError['message'] != '')	{ $validationResult['notUIDvalidationAustriaRelatedError']	= $otherError['message']; }	
		@trigger_error('');
		$initialize = UIDvalidationAustria::initialize($referenznummer, $validationResult);
	} 	
/* **************************************************************************************************************************************************************************************** */
/* MAIN method										                  											   			      												     		*/
/* **************************************************************************************************************************************************************************************** */
	/**
	 * Method to perform the validation.
	 *
	 * @param	string	$referenznummer		SITS Referenznummer des Haendlers (default EMPTY)
	 * @param	string	$countryOfuid		Countrycode of the UID (default "AT" austria)
	 * @param	string	$uid2validate		The UID to be validated (default EMPTY) by reference to return the sanitized value to the calling script
	 * @param	integer	$validationlevel	Validation level (1 or 2, default 1)
	 * @param	integer	$validateATU		Validation of austrian UIDs (0 or 1, default 1)
	 * @param	string	$name				Company name of the UID (default EMPTY)
	 * @param	string	$address			Address of the UID (default EMPTY)
	 * @param	string	$domainname			Internet domain name (to be used for WHOIS country evaluation)
	 *
	 * @return  array	$validationResult	Result of validation (all elements of the validation)
	 *					$validationResult["OverallValidationResult"]		0  => UNKNOWN
	 *					$validationResult["OverallValidationResult"]		1  => OK, EU AUSLAND
	 *					$validationResult["OverallValidationResult"]		2  => OK, INLAND
	 *					$validationResult["OverallValidationResult"]		5  => NOT OK
	 *					$validationResult["OverallValidationResult"]		6  => NOT OK, UID OK, aber Adresse stimmt nicht überein
	 *					$validationResult["OverallValidationResult"]		8  => NOT NEEDED, EU-Privatperson oder AT Unternehmen
	 *					$validationResult["OverallValidationResult"]		9  => NOT NEEDED, Export ausserhalb EU
	 *					$validationResult["OverallValidationResult"]		10  => NonEUcountry, status UNKNOWN
	 *					$validationResult["OverallValidationResult"]		11  => NonEUcountry, OK
	 *					$validationResult["OverallValidationResult"]		15  => NonEUcountry, NOT OK
	 *					$validationResult["OverallValidationResult"]		16  => NonEUcountry, NOT OK, UID OK, aber Adresse stimmt nicht überein
	 */
	static function validateUID($referenznummer = "", $countryOfuid="AT", &$uid2validate="", $validationlevel = 1, $validateATU = 1, $name = "", $address = "")
	{
		$validationResult	=	array("uid2validate" => $uid2validate, "countryOfuid" => $countryOfuid, "OverallValidationResult" => 0, "EUmemberstateStandardVATrate" => 20, "EUmemberstateReducedVATrate" => 10, "validationlevel" => $validationlevel, "validateATU" => $validateATU, "name" => $name, "address" => $address);
		$initialize = UIDvalidationAustria::initialize($referenznummer, $validationResult);
		$sanitizeInputResult = UIDvalidationAustria::sanitizeInput($countryOfuid, $uid2validate, $validationlevel, $validateATU, $name, $address, $validationResult);

		if (UIDvalidationAustria::validationNeeded($uid2validate, $countryOfuid, $name, $address, $validationlevel, $validateATU, $validationResult))
		{
			$validationResult["validationNeeded"]	= 1;
			if ($validationResult["ExportOutsideEU"] AND $validationResult["ValidateNonEUcountry"])
			{	// Validate UIDs from countries outside EU
				$validateNonEUcountryUID = UIDvalidationAustria::validateNonEUcountryUID($referenznummer, $countryOfuid, $uid2validate, $validationlevel, $name, $address, $validationResult);
				if ($validateNonEUcountryUID === FALSE)
				{
					$validationResult["ValidateNonEUcountryOK"]	= 0;
				}
				else
				{
					$validationResult["ValidateNonEUcountryOK"]	= 1;
					// OK, aber was jetzt???
				}
			}
			else
			{
				$austrianServiceSessionID = UIDvalidationAustria::austrianServiceOK($validationResult);
				if ($austrianServiceSessionID === FALSE)
				{
					$validationResult["austrianServiceOK"]	= 0;
					// Call european VIES webservice, because FinanzOnline service could not provide validation
					$validationResult["VIESvalidationNeeded"]	= 1;
					$callVIESwebserviceResult = UIDvalidationAustria::callVIESwebservice($uid2validate, $countryOfuid, $name, $address, $validationlevel, $validateATU, $validationResult);
				}
				else
				{
					$validationResult["austrianServiceOK"]	= 1;
					$validationResult["austrianServiceSessionID"]	= $austrianServiceSessionID;
					$uidAbfrageResult = UIDvalidationAustria::austrianServiceUIDabfrage($austrianServiceSessionID, $uid2validate, $countryOfuid, $name, $address, $validationlevel, $validateATU, $validationResult );
					if ($uidAbfrageResult === FALSE)
					{
						$validationResult["austrianServiceuidAbfrageErfolgreich"]	= 0;
						// Call european VIES webservice, because FinanzOnline service could not provide validation 
						$validationResult["VIESvalidationNeeded"]	= 1;
						$callVIESwebserviceResult = UIDvalidationAustria::callVIESwebservice($uid2validate, $countryOfuid, $name, $address, $validationlevel, $validateATU, $validationResult);
					}
					else
					{
						$validationResult["austrianServiceuidAbfrageErfolgreich"]	= 1;
						$validationResult["austrianServiceuidAbfrageErgebnis"]	= $uidAbfrageResult;
						$austrianServiceuidAbfrageReturncode = $validationResult["austrianServiceuidAbfrageErgebnis"]->rc;
						$validationResult["austrianServiceuidAbfrageReturncodeMessage"]	= $GLOBALS["UIDvalidationATglobalvars"]["arr_FinanzOnline_returncodes"][$austrianServiceuidAbfrageReturncode];
						if ( $GLOBALS["UIDvalidationATglobalvars"]["arr_FinanzOnline_RC2VIES"][$austrianServiceuidAbfrageReturncode] == 1)
						{	// Call european VIES webservice, because FinanzOnline service could not provide validation 
							$validationResult["VIESvalidationNeeded"]	= 1;
							$callVIESwebserviceResult = UIDvalidationAustria::callVIESwebservice($uid2validate, $countryOfuid, $name, $address, $validationlevel, $validateATU, $validationResult);
						}
						else
						{
							if ($austrianServiceuidAbfrageReturncode == 0)
							{
								if($validationlevel == 2)
								{ 
									$NameAndAddressValidationResult  = $validationResult["austrianServiceuidAbfrageErgebnis"]->name .  " " ;
									$NameAndAddressValidationResult .= $validationResult["austrianServiceuidAbfrageErgebnis"]->adrz1 .  " " ;
									$NameAndAddressValidationResult .= $validationResult["austrianServiceuidAbfrageErgebnis"]->adrz2 .  " " ;
									$NameAndAddressValidationResult .= $validationResult["austrianServiceuidAbfrageErgebnis"]->adrz3 .  " " ;
									$NameAndAddressValidationResult .= $validationResult["austrianServiceuidAbfrageErgebnis"]->adrz4 .  " " ;
									$NameAndAddressValidationResult .= $validationResult["austrianServiceuidAbfrageErgebnis"]->adrz5 .  " " ;
									$NameAndAddressValidationResult .= $validationResult["austrianServiceuidAbfrageErgebnis"]->adrz6 .  " " ;
									$validateNameAndAddressResult = UIDvalidationAustria::validateNameAndAddress($name, $address, $NameAndAddressValidationResult, $validationResult);
									if ($validateNameAndAddressResult)
									{
										if ($countryOfuid == $GLOBALS["UIDvalidationATglobalvars"]["countryInland"])
										{
											$validationResult["OverallValidationResult"] = 2;
										}
										else
										{
											$validationResult["OverallValidationResult"] = 1;
										}
									}
									else
									{
										if ($validationResult["NameCongruenceCheckProzent"] >  $GLOBALS["UIDvalidationATglobalvars"]["Stufe2UntergrenzeProzent"])
										{
											$validationResult["OverallValidationResult"] = 6;
										}
										else
										{
											$validationResult["OverallValidationResult"] = 5;
										}
									}
								}
								else
								{
									if ($countryOfuid == $GLOBALS["UIDvalidationATglobalvars"]["countryInland"])
									{
										$validationResult["OverallValidationResult"] = 2;
									}
									else
									{
										$validationResult["OverallValidationResult"] = 1;
									}
								}
							}
						}
					}
					$validationResult["austrianServiceLogout"] = UIDvalidationAustria::austrianServiceLogout($austrianServiceSessionID, $validationResult);
				}
			}
		}
		else
		{
			$validationResult["validationNeeded"]	= 0;
			if ($validationResult["ExportOutsideEU"] == 1)
			{
				$validationResult["OverallValidationResult"] = 9;
			}
			else
			{
				$validationResult["OverallValidationResult"] = 8;
			}
		}
		$validationResult["writeLogfile"]	= UIDvalidationAustria::writeLogfile($validationResult);
		return $validationResult;
	}
/* **************************************************************************************************************************************************************************************** */
/* **************************************************************************************************************************************************************************************** */
	/**
	 * Method to download documents from the DataBox
	 */
	static function austrianServiceDataboxAbfrage($referenznummer = "")
	{
		$validationResult	=	array();
		$initialize = UIDvalidationAustria::initialize($referenznummer, $validationResult);
		
		$arrDaysOfWeek		= array("Mon" => "MO", "Tue" => "DI", "Wed" => "MI", "Thu" => "DO","Fri" => "FR","Sat" => "SA","Sun" => "SO");
		$today				= new DateTime(date("Y-m-d H:i:s"));
		$todayDayOfWeek		= $today->format('D');	
		$todayDayOfMonth	= $today->format('d');	
		$todayMonth			= $today->format('m');	
		$getDataboxDocuments = FALSE;	
		
		switch ($GLOBALS["UIDvalidationATglobalvars"]["finanzOnline_DataboxFrequenz"])
		{
		case "NO" :		// Abfrage niemals 
			$getDataboxDocuments = FALSE;
			break;
		case "TG" :		// Abfrage taeglich
			$getDataboxDocuments = TRUE;
			break;
		case "MO" : 	// Abfrage immer MONTAGS
		case "DI" : 	// Abfrage immer DIENSTAGS
		case "MI" : 	// Abfrage immer MITTWOCHS
		case "DO" : 	// Abfrage immer DONNERSTAGS
		case "FR" : 	// Abfrage immer FREITAGS
		case "SA" : 	// Abfrage immer SAMSTAGS
		case "SO" : 	// Abfrage immer SONNTAGS
			if ($arrDaysOfWeek[$todayDayOfWeek] == $GLOBALS["UIDvalidationATglobalvars"]["finanzOnline_DataboxFrequenz"]) {$getDataboxDocuments = TRUE;}
			break;
		case "QU" : 	// Abfrage immer AM ZWEITEN KALENDERTAG DES ERSTEN MONATS IM QUARTAL
			if ($todayDayOfMonth == "02" && ($todayMonth == "01" || $todayMonth == "04"  || $todayMonth == "07" || $todayMonth == "10") ) {$getDataboxDocuments = TRUE;}
			break;
		default : 		// Abfrage immer Xten KALENDERTAG DES MONATS
			if ($todayDayOfMonth == $GLOBALS["UIDvalidationATglobalvars"]["finanzOnline_DataboxFrequenz"]) {$getDataboxDocuments = TRUE;}
		}
		
		$validationResult["DataboxAbfrageFrequenz"] = $GLOBALS["UIDvalidationATglobalvars"]["finanzOnline_DataboxFrequenz"] . " ## " . $todayDayOfWeek  . " ## " . $todayDayOfMonth  . " ## " ;
		if ($getDataboxDocuments)
		{
			$validationResult["DataboxAbfrage"] = "1";
			$austrianServiceSessionID = UIDvalidationAustria::austrianServiceOK($validationResult);
			if ($austrianServiceSessionID === FALSE)
			{
				$validationResult["austrianServiceOK"]	= 0;
			}
			else
			{
				$validationResult["austrianServiceOK"]	= 1;
				$validationResult["austrianServiceSessionID"]	= $austrianServiceSessionID;
			
				$DataboxExtAbfrageResult = UIDvalidationAustria::austrianServiceDataboxExt($austrianServiceSessionID, $validationResult );
				if ($DataboxExtAbfrageResult === FALSE)
				{
					$validationResult["DataboxExtAbfrageErfolgreich"]	= 0;
				}
				else
				{
					$validationResult["DataboxExtAbfrageErfolgreich"]	= 1;
					if (isset($DataboxExtAbfrageResult->Result))
					{
						if (is_array($DataboxExtAbfrageResult->Result))
						{
							foreach ($DataboxExtAbfrageResult->Result as $ResultObject)
							{
								$AbfrageResults[] = $ResultObject;
							}
						}
						else
						{
							$AbfrageResults[0] = $DataboxExtAbfrageResult->Result;
						}
						$validationResult["DataboxDokumentVorhanden"] = 1;
						$arr_anhang_filepfad = array();
						foreach ($AbfrageResults as $AbfrageResult)
						{
							$DataboxEntryResult = UIDvalidationAustria::austrianServiceDataboxEntry($austrianServiceSessionID, $AbfrageResult, $validationResult );
							if ($DataboxEntryResult === FALSE)
							{
								$validationResult["DataboxEntryErfolgreich"] = 0;
							}
							else
							{
								$validationResult["DataboxEntryErfolgreich"] = 1;
								// Erzeuge File aus DataboxEntryResult
								if ($handle = fopen($GLOBALS["UIDvalidationATglobalvars"]["validationLogfilePath"].DIRECTORY_SEPARATOR.$AbfrageResult->filebez, "a")) 
								{
									$inhalt =  base64_decode($DataboxEntryResult->Result) . " \n";
									if (fwrite($handle, $inhalt))
									{ 
										$arr_anhang_filepfad[] = $GLOBALS["UIDvalidationATglobalvars"]["validationLogfilePath"].DIRECTORY_SEPARATOR.$AbfrageResult->filebez;
									} 
									else 
									{ 
										$validationResult["DataboxEntryError"]	= "Kann in die Datei ".$GLOBALS["UIDvalidationATglobalvars"]["validationLogfilePath"].DIRECTORY_SEPARATOR.$AbfrageResult->filebez." nicht schreiben!"; 
										$validationResult["DataboxEntryContent"]	= $inhalt; 
									} 
									fclose($handle);
								}
								else
								{
									$validationResult["DataboxEntryError"]	= "Kann in die Datei ".$GLOBALS["UIDvalidationATglobalvars"]["validationLogfilePath"].DIRECTORY_SEPARATOR.$AbfrageResult->filebez." nicht oeffnen!"; 
								}
							}
						}
						$emailSendResult = UIDvalidationAustria::sendEmail($arr_anhang_filepfad, $validationResult);
						if ($emailSendResult === FALSE)
						{
							$validationResult["EmailSendErfolgreich"] = 0;
						}
						else
						{
							$validationResult["EmailSendErfolgreich"] = 1;
							// Databox File löschen					
							foreach ($arr_anhang_filepfad as $file2unlink) {unlink($file2unlink);}
						}
					}
					else
					{
						$validationResult["DataboxDokumentVorhanden"] = 0;
						if ($GLOBALS["UIDvalidationATglobalvars"]["DataBoxAbfrageLeermeldung"])
						{
							$arr_anhang_filepfad = array();
							$emailSendResult = UIDvalidationAustria::sendEmail($arr_anhang_filepfad, $validationResult);
							if ($emailSendResult === FALSE)
							{
								$validationResult["EmailSendErfolgreich"] = 0;
							}
							else
							{
								$validationResult["EmailSendErfolgreich"] = 1;
							}
						}
						else
						{
								$validationResult["EmailLeermeldungSend"] = 0;
						}
					}
				}
				$validationResult["austrianServiceLogout"] = UIDvalidationAustria::austrianServiceLogout($austrianServiceSessionID, $validationResult);
			}
		}	
		else
		{
			$validationResult["DataboxAbfrage"] = "0";
		}

		if ($GLOBALS["UIDvalidationATglobalvars"]["DataBoxAbfrageLogging"])
		{
			$validationResult["writeLogfile"]	= UIDvalidationAustria::writeLogfile($validationResult);
		}
		return $validationResult;
	}
/* **************************************************************************************************************************************************************************************** */
/* 										                  											   			      												     		*/
/* **************************************************************************************************************************************************************************************** */
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
/* **************************************************************************************************************************************************************************************** */
/* austrian FinanzOnline service methods	  		                 					 										      											      		*/
/* **************************************************************************************************************************************************************************************** */
	/**
	 * Method to check availability of austrian FinanzOnline service.
	 * @param	array	$validationResult	all elements of the validation as members of an array
	 * @return	boolean	FALSE				in case of an error
	 * @return	string	SessionID			FinanzOnline Session ID
	 */
	static function austrianServiceOK(&$validationResult)
	{
		if ($GLOBALS["UIDvalidationATglobalvars"]["finanzOnline_TeilnehmerID"] != '' AND $GLOBALS["UIDvalidationATglobalvars"]["finanzOnline_BenutzerID"] != '' AND $GLOBALS["UIDvalidationATglobalvars"]["finanzOnline_BenutzerPIN"] != '' )
		{
			// Aufruf Session Webservice / Login
			$result = FALSE;
			$soapClientSessionOptions = array 
			(
				'trace' => 1,
				'exceptions' => 1,
				'cache_wsdl' => WSDL_CACHE_NONE
			);
			$soapClientLoginOptions =	(object) array
			(
				'tid' =>	$GLOBALS["UIDvalidationATglobalvars"]["finanzOnline_TeilnehmerID"] , 
				'benid' =>	$GLOBALS["UIDvalidationATglobalvars"]["finanzOnline_BenutzerID"] , 
				'pin' =>	$GLOBALS["UIDvalidationATglobalvars"]["finanzOnline_BenutzerPIN"] 
			);
			try {	$soapClient1 = new SoapClient($GLOBALS["UIDvalidationATglobalvars"]["soapClientSessionURI"], $soapClientSessionOptions);	} 
			catch (SoapFault $exception) 														{	UIDvalidationAustria::handleErrors($errorType = 'SoapFault',	$exception,			$validationResultMember = "soapFault_austrianServiceLogin", $validationResult);	} 		
			catch (Exception $otherException)													{	UIDvalidationAustria::handleErrors($errorType = 'Exception',	$otherException,	$validationResultMember = "soapFault_austrianServiceLogin", $validationResult); return FALSE; } 		
			$otherError = error_get_last(); if ($otherError && $otherError['message'] != '')	{	UIDvalidationAustria::handleErrors($errorType = 'otherError',	$otherError,		$validationResultMember = "soapFault_austrianServiceLogin", $validationResult); return FALSE; }	
			try	{ $result = $soapClient1->Login($soapClientLoginOptions);	} 
			catch (SoapFault $exception) 						{	UIDvalidationAustria::handleErrors($errorType = 'SoapFault',	$exception,			$validationResultMember = "soapFault_austrianServiceLogin", $validationResult);	} 		
			catch (Exception $otherException)					{	UIDvalidationAustria::handleErrors($errorType = 'Exception',	$otherException,	$validationResultMember = "soapFault_austrianServiceLogin", $validationResult); return FALSE; } 		
			$otherError = error_get_last(); if ($otherError && $otherError['message'] != '')	{	UIDvalidationAustria::handleErrors($errorType = 'otherError',	$otherError,		$validationResultMember = "soapFault_austrianServiceLogin", $validationResult); return FALSE; }	
			if (isset($result) AND is_object($result) AND isset($result->Result)  ) { return $result->Result;} else {	return FALSE; }
		}
		else
		{
			return FALSE; 
		}
	}
/* **************************************************************************************************************************************************************************************** */
	/**
	 * Method to logout from austrian FinanzOnline service.
	 * @param	string $austrianServiceSessionID (from Login method)
	 * @param	array	$validationResult	all elements of the validation as members of an array
	 * @return	boolean	FALSE				in case of an error
	 * @return	string	SessionID			FinanzOnline Session ID
	 */
	static function austrianServiceLogout($austrianServiceSessionID, &$validationResult)
	{	// Aufruf Session Webservice / Logout
		$result = FALSE;
		$soapClientLogoutOptions =	(object) array
		(
			'id' =>	$austrianServiceSessionID, 
			'tid' => $GLOBALS["UIDvalidationATglobalvars"]["finanzOnline_TeilnehmerID"] , 
			'benid' => $GLOBALS["UIDvalidationATglobalvars"]["finanzOnline_BenutzerID"]
		);
		try { $soapClient3 = new SoapClient($GLOBALS["UIDvalidationATglobalvars"]["soapClientSessionURI"]); } 
		catch (SoapFault $exception) 						{	UIDvalidationAustria::handleErrors($errorType = 'SoapFault',	$exception,			$validationResultMember = "soapFault_austrianServiceLogout", $validationResult);	} 		
		catch (Exception $otherException)					{	UIDvalidationAustria::handleErrors($errorType = 'Exception',	$otherException,	$validationResultMember = "soapFault_austrianServiceLogout", $validationResult); return FALSE; } 		
		$otherError = error_get_last(); if ($otherError && $otherError['message'] != '')	{	UIDvalidationAustria::handleErrors($errorType = 'otherError',	$otherError,		$validationResultMember = "soapFault_austrianServiceLogout", $validationResult); return FALSE; }	
		try	{ $result = $soapClient3->Logout($soapClientLogoutOptions);} 
		catch (SoapFault $exception) 						{	UIDvalidationAustria::handleErrors($errorType = 'SoapFault',	$exception,			$validationResultMember = "soapFault_austrianServiceLogout", $validationResult);	} 		
		catch (Exception $otherException)					{	UIDvalidationAustria::handleErrors($errorType = 'Exception',	$otherException,	$validationResultMember = "soapFault_austrianServiceLogout", $validationResult); return FALSE; } 		
		$otherError = error_get_last(); if ($otherError && $otherError['message'] != '')	{	UIDvalidationAustria::handleErrors($errorType = 'otherError',	$otherError,		$validationResultMember = "soapFault_austrianServiceLogout", $validationResult); return FALSE; }	
		if (is_object($result) AND isset($result->Result)  ) { return $result->Result;} else { return FALSE; }
	}
/* **************************************************************************************************************************************************************************************** */
	/**
	 * Method to validate an UID against the austrian FinanzOnline service.
	 * @param	string	$austrianServiceSessionID (from Login method)
	 * @param	string	$uid2validate		The UID to be validated (default EMPTY)
	 * @param	string	$countryOfuid		Countrycode of the UID (default "AT" austria)
	 * @param	string	$name				Company name of the UID (default EMPTY)
	 * @param	string	$address			Address of the UID (default EMPTY)
	 * @param	integer	$validationlevel	Validation level (1 or 2, default 1)
	 * @param	integer	$validateATU		Validation of austrian UIDs (0 or 1, default 1)
	 * @param	array	$validationResult	all elements of the validation as members of an array
	 * @return	boolean	FALSE				in case of an error
	 * @return	object	soapResult			Standard object with result details
	 */
	static function austrianServiceUIDabfrage($austrianServiceSessionID ="", &$uid2validate, &$countryOfuid, &$name, &$address, &$validationlevel, &$validateATU, &$validationResult)
	{
		// Aufruf UID-Abfrage Webservice 
		$result = FALSE;
		$soapClientAbfrageOptions =	(object) array
		(
			'sessionid'	=> $austrianServiceSessionID , 
			'tid'		=> $GLOBALS["UIDvalidationATglobalvars"]["finanzOnline_TeilnehmerID"] , 
			'benid'		=> $GLOBALS["UIDvalidationATglobalvars"]["finanzOnline_BenutzerID"] , 
			'uid_tn'	=> $GLOBALS["UIDvalidationATglobalvars"]["firmaUID"] , 
			'uid'		=> $countryOfuid.$uid2validate , 
			'stufe'		=> $validationlevel 
		);
		try	{ $soapClient2 = new SoapClient($GLOBALS["UIDvalidationATglobalvars"]["soapClientAbfrageURI"]); } 
		catch (SoapFault $exception) 						{	UIDvalidationAustria::handleErrors($errorType = 'SoapFault',	$exception,			$validationResultMember = "soapFault_austrianServiceUIDabfrage", $validationResult);	} 		
		catch (Exception $otherException)					{	UIDvalidationAustria::handleErrors($errorType = 'Exception',	$otherException,	$validationResultMember = "soapFault_austrianServiceUIDabfrage", $validationResult); return FALSE; } 		
		$otherError = error_get_last(); if ($otherError && $otherError['message'] != '')	{	UIDvalidationAustria::handleErrors($errorType = 'otherError',	$otherError,		$validationResultMember = "soapFault_austrianServiceUIDabfrage", $validationResult); return FALSE; }	
		try	{ $result = $soapClient2->uidAbfrage($soapClientAbfrageOptions); } 
		catch (SoapFault $exception) 						{	UIDvalidationAustria::handleErrors($errorType = 'SoapFault',	$exception,			$validationResultMember = "soapFault_austrianServiceUIDabfrage", $validationResult);	} 		
		catch (Exception $otherException)					{	UIDvalidationAustria::handleErrors($errorType = 'Exception',	$otherException,	$validationResultMember = "soapFault_austrianServiceUIDabfrage", $validationResult); return FALSE; } 		
		$otherError = error_get_last(); if ($otherError && $otherError['message'] != '')	{	UIDvalidationAustria::handleErrors($errorType = 'otherError',	$otherError,		$validationResultMember = "soapFault_austrianServiceUIDabfrage", $validationResult); return FALSE; }	
		if (is_object($result) ) { return $result;} else { return FALSE; }
	}
/* **************************************************************************************************************************************************************************************** */
	/**
	 * Method to get an array of objects from austrian FinanzOnline service Databox.
	 * @param	string $austrianServiceSessionID (from Login method)
	 * @param	array	$validationResult	The complete result of the validation

	 * @return	boolean	FALSE				in case of an error
	 * @return	object	soapResult			Standard object with result details
	 */
	static function austrianServiceDataboxExt($austrianServiceSessionID ="", &$validationResult)
	{	// Aufruf UID-Abfrage Webservice 
		$result = FALSE;
		$soapClientDataBoxExtOptions =	(object) array
		(
			'tid'		=> $GLOBALS["UIDvalidationATglobalvars"]["finanzOnline_TeilnehmerID"] , 
			'benid'		=> $GLOBALS["UIDvalidationATglobalvars"]["finanzOnline_BenutzerID"] , 
			'id'		=> $austrianServiceSessionID , 
			'art'		=> 'P' 
		);
		try {	$soapClient5 = new SoapClient($GLOBALS["UIDvalidationATglobalvars"]["soapClientDataboxURI"]);	} 
		catch (SoapFault $exception) 														{	UIDvalidationAustria::handleErrors($errorType = 'SoapFault',	$exception,			$validationResultMember = "soapFault_austrianServiceDataboxExt", $validationResult);	} 		
		catch (Exception $otherException)													{	UIDvalidationAustria::handleErrors($errorType = 'Exception',	$otherException,	$validationResultMember = "soapFault_austrianServiceDataboxExt", $validationResult); return FALSE; } 		
		$otherError = error_get_last(); if ($otherError && $otherError['message'] != '')	{	UIDvalidationAustria::handleErrors($errorType = 'otherError',	$otherError,		$validationResultMember = "soapFault_austrianServiceDataboxExt", $validationResult); return FALSE; }	
		try	{ $result = $soapClient5->getDataboxExt($soapClientDataBoxExtOptions);	} 
		catch (SoapFault $exception) 						{	UIDvalidationAustria::handleErrors($errorType = 'SoapFault',	$exception,			$validationResultMember = "soapFault_austrianServiceDataboxExt", $validationResult);	} 		
		catch (Exception $otherException)					{	UIDvalidationAustria::handleErrors($errorType = 'Exception',	$otherException,	$validationResultMember = "soapFault_austrianServiceDataboxExt", $validationResult); return FALSE; } 		
		$otherError = error_get_last(); if ($otherError && $otherError['message'] != '')	{	UIDvalidationAustria::handleErrors($errorType = 'otherError',	$otherError,		$validationResultMember = "soapFault_austrianServiceDataboxExt", $validationResult); return FALSE; }	
		return $result;
	}
/* **************************************************************************************************************************************************************************************** */
	/**
	 * Method to get an document from austrian FinanzOnline service Databox.
	 * @param	string $austrianServiceSessionID (from Login method)
	 * @param	array	$AbfrageResult		Array mit File-Infos der Databox Dokumente
	 * @param	array	$validationResult	The complete result of the validation

	 * @return	boolean	FALSE				in case of an error
	 * @return	object	soapResult			Standard object with result details
	 */
	static function austrianServiceDataboxEntry($austrianServiceSessionID ="", &$AbfrageResult, &$validationResult)
	{	// 
		$result = FALSE;
		$soapClientDataBoxEntryOptions =	(object) array
		(
			'tid'		=> $GLOBALS["UIDvalidationATglobalvars"]["finanzOnline_TeilnehmerID"] , 
			'benid'		=> $GLOBALS["UIDvalidationATglobalvars"]["finanzOnline_BenutzerID"] , 
			'id'		=> $austrianServiceSessionID , 
			'sid'		=> $AbfrageResult->sid  ,
			'appl'		=> $AbfrageResult->appl ,
			'applkey'	=> $AbfrageResult->applkey ,
			'fileart'	=> $AbfrageResult->fileart 
		);
		try {	$soapClient6 = new SoapClient($GLOBALS["UIDvalidationATglobalvars"]["soapClientDataboxURI"]);	} 
		catch (SoapFault $exception) 														{	UIDvalidationAustria::handleErrors($errorType = 'SoapFault',	$exception,			$validationResultMember = "soapFault_austrianServiceDataboxEntry", $validationResult);	} 		
		catch (Exception $otherException)													{	UIDvalidationAustria::handleErrors($errorType = 'Exception',	$otherException,	$validationResultMember = "soapFault_austrianServiceDataboxEntry", $validationResult); return FALSE; } 		
		$otherError = error_get_last(); if ($otherError && $otherError['message'] != '')	{	UIDvalidationAustria::handleErrors($errorType = 'otherError',	$otherError,		$validationResultMember = "soapFault_austrianServiceDataboxEntry", $validationResult); return FALSE; }	
		try	{ $result = $soapClient6->getDataboxEntry($soapClientDataBoxEntryOptions);	} 
		catch (SoapFault $exception) 						{	UIDvalidationAustria::handleErrors($errorType = 'SoapFault',	$exception,			$validationResultMember = "soapFault_austrianServiceDataboxEntry", $validationResult);	} 		
		catch (Exception $otherException)					{	UIDvalidationAustria::handleErrors($errorType = 'Exception',	$otherException,	$validationResultMember = "soapFault_austrianServiceDataboxEntry", $validationResult); return FALSE; } 		
		$otherError = error_get_last(); if ($otherError && $otherError['message'] != '')	{	UIDvalidationAustria::handleErrors($errorType = 'otherError',	$otherError,		$validationResultMember = "soapFault_austrianServiceDataboxEntry", $validationResult); return FALSE; }	
		return $result;
	}
/* **************************************************************************************************************************************************************************************** */
/* **************************************************************************************************************************************************************************************** */
/* **************************************************************************************************************************************************************************************** */
/* European Union VIES service methods				                  											 			      												       		*/
/* Quelle: http://ec.europa.eu/taxation_customs/vies/																		      															*/
/* **************************************************************************************************************************************************************************************** */
	/**
	 * Method to call european VIES webservice, because FinanzOnline service could not provide validation
	 * @param	string	$uid2validate		The UID to be validated (default EMPTY)
	 * @param	string	$countryOfuid		Countrycode of the UID (default "AT" austria)
	 * @param	string	$name				Company name of the UID (default EMPTY)
	 * @param	string	$address			Address of the UID (default EMPTY)
	 * @param	integer	$validationlevel	Validation level (1 or 2, default 1)
	 * @param	integer	$validateATU		Validation of austrian UIDs (0 or 1, default 0)
	 * @param	array	$validationResult	all elements of the validation as members of an array
	 * @return	void
	 */
	static function callVIESwebservice(&$uid2validate, &$countryOfuid, &$name, &$address, &$validationlevel, &$validateATU, &$validationResult)
	{
		$uidVIESAbfrageResult = UIDvalidationAustria::VIESServiceUIDabfrage($uid2validate, $countryOfuid, $name, $address, $validationlevel, $validateATU, $validationResult);
		if ($uidVIESAbfrageResult === FALSE)
		{
			$validationResult["VIESServiceOK"]	= 0;
			// Call (by CURL) european VIES website validation page, because VIES service could not provide validation 
			$validationResult["VIESwebsitevalidationNeeded"]	= 1;
			$uidVIESwebsiteResult = UIDvalidationAustria::VIESwebsiteUIDabfrage($uid2validate, $countryOfuid, $name, $address, $validationlevel, $validateATU, $validationResult);
			if ($uidVIESwebsiteResult['curlExecSuccessful'] == 0)
			{
				$validationResult["VIESwebsiteOK"]	= 0;
			}
			else
			{
				$validationResult["VIESwebsiteOK"]	= 1;
				if ($uidVIESwebsiteResult['UIDvalid'] == 1)
				{
					if ($countryOfuid == $GLOBALS["UIDvalidationATglobalvars"]["countryInland"])
					{
						$validationResult["OverallValidationResult"] = 2;
					}
					else
					{
						$validationResult["OverallValidationResult"] = 1;
					}
				}
				else
				{
					if ($validationResult["NameCongruenceCheckProzent"] >  $GLOBALS["UIDvalidationATglobalvars"]["Stufe2UntergrenzeProzent"])
					{
						$validationResult["OverallValidationResult"] = 6;
					}
					else
					{
						$validationResult["OverallValidationResult"] = 5;
					}
				}
			}
		}
		else
		{
			$validationResult["VIESServiceOK"]	= 1;
			$validationResult["VIESServiceAbfrageErgebnis"]	= $uidVIESAbfrageResult;
			if ($uidVIESAbfrageResult->valid == 1)
			{
				if($validationlevel == 2)
				{ 
					$NameAndAddressValidationResult = $uidVIESAbfrageResult->name .  " " . $uidVIESAbfrageResult->address ;
					$validateNameAndAddressResult = UIDvalidationAustria::validateNameAndAddress($name, $address, $NameAndAddressValidationResult, $validationResult);
					if ($validateNameAndAddressResult)
					{
						if ($countryOfuid == $GLOBALS["UIDvalidationATglobalvars"]["countryInland"])
						{
							$validationResult["OverallValidationResult"] = 2;
						}
						else
						{
							$validationResult["OverallValidationResult"] = 1;
						}
					}
					else
					{
						if ($validationResult["NameCongruenceCheckProzent"] >  $GLOBALS["UIDvalidationATglobalvars"]["Stufe2UntergrenzeProzent"])
						{
							$validationResult["OverallValidationResult"] = 6;
						}
						else
						{
							$validationResult["OverallValidationResult"] = 5;
						}
					}
				} 
				else
				{				
					if ($countryOfuid == $GLOBALS["UIDvalidationATglobalvars"]["countryInland"])
					{
						$validationResult["OverallValidationResult"] = 2;
					}
					else
					{
						$validationResult["OverallValidationResult"] = 1;
					}
				}
			}
			else
			{
				$validationResult["OverallValidationResult"] = 5;
			}
		}
		return;
	}
/* **************************************************************************************************************************************************************************************** */
	/**
	 * Method to validate an UID against the EU VIES service.
	 * @param	string	$uid2validate		The UID to be validated (default EMPTY)
	 * @param	string	$countryOfuid		Countrycode of the UID (default "AT" austria)
	 * @param	string	$name				Company name of the UID (default EMPTY)
	 * @param	string	$address			Address of the UID (default EMPTY)
	 * @param	integer	$validationlevel	Validation level (1 or 2, default 1)
	 * @param	integer	$validateATU		Validation of austrian UIDs (0 or 1, default 0)
	 * @param	array	$validationResult	all elements of the validation as members of an array
	 * @return	boolean	FALSE				in case of an error
	 * @return	object	soapResult			Standard object with result details
	 */
	static function VIESServiceUIDabfrage(&$uid2validate, &$countryOfuid, &$name, &$address, &$validationlevel, &$validateATU, &$validationResult)
	{	// Aufruf UID-Abfrage Webservice 
		$result = FALSE;
		$soapClientVIESAbfrageOptions =	(object) array
		(
			'countryCode'	=> $countryOfuid,
			'vatNumber'		=> $uid2validate
		);
		try	{ $soapClient4 = new SoapClient($GLOBALS["UIDvalidationATglobalvars"]["soapClientVIESAbfrageURI"]); } 
		catch (SoapFault $exception) 						{	UIDvalidationAustria::handleErrors($errorType = 'SoapFault',	$exception,			$validationResultMember = "soapFault_VIESServiceUIDabfrage", $validationResult);	} 		
		catch (Exception $otherException)					{	UIDvalidationAustria::handleErrors($errorType = 'Exception',	$otherException,	$validationResultMember = "soapFault_VIESServiceUIDabfrage", $validationResult); return FALSE; } 		
		$otherError = error_get_last(); if ($otherError && $otherError['message'] != '')	{	UIDvalidationAustria::handleErrors($errorType = 'otherError',	$otherError,		$validationResultMember = "soapFault_VIESServiceUIDabfrage", $validationResult); return FALSE; }	
		try { $result = $soapClient4->checkVat($soapClientVIESAbfrageOptions); } 
		catch (SoapFault $exception) 						{	UIDvalidationAustria::handleErrors($errorType = 'SoapFault',	$exception,			$validationResultMember = "soapFault_VIESServiceUIDabfrage", $validationResult);	} 		
		catch (Exception $otherException)					{	UIDvalidationAustria::handleErrors($errorType = 'Exception',	$otherException,	$validationResultMember = "soapFault_VIESServiceUIDabfrage", $validationResult); return FALSE; } 		
		$otherError = error_get_last(); if ($otherError && $otherError['message'] != '')	{	UIDvalidationAustria::handleErrors($errorType = 'otherError',	$otherError,		$validationResultMember = "soapFault_VIESServiceUIDabfrage", $validationResult); return FALSE; }	
		if (is_object($result) ) { return $result;} else { return FALSE; }
	}
/* **************************************************************************************************************************************************************************************** */
	/**
	 * Method to validate an UID against the EU VIES online webpage.
	 * 	we use this method, if 
	 *		NEITHER the austrian FinanzOnline webservice 
	 * 		NOR the EU VIES webservice 
	 *	were able to provide a valid answer
	 * @param	string	$uid2validate		The UID to be validated (default EMPTY)
	 * @param	string	$countryOfuid		Countrycode of the UID (default "AT" austria)
	 * @param	string	$name				Company name of the UID (default EMPTY)
	 * @param	string	$address			Address of the UID (default EMPTY)
	 * @param	integer	$validationlevel	Validation level (1 or 2, default 1)
	 * @param	integer	$validateATU		Validation of austrian UIDs (0 or 1, default 0)
	 * @param	array	$validationResult	all elements of the validation as members of an array
	 * @return	array	curlResult			array with result details
	 */
	static function VIESwebsiteUIDabfrage(&$uid2validate, &$countryOfuid, &$name, &$address, &$validationlevel, &$validateATU, &$validationResult)
	{
		$curlPOSTURL = $GLOBALS["UIDvalidationATglobalvars"]["webpageVIESAbfrageURI"];
		$curlPOSTVARS = "memberStateCode=$countryOfuid&requestedMsCode=$countryOfuid&number=$uid2validate";   
		$curlObject = curl_init($curlPOSTURL); 
		curl_setopt($curlObject, CURLOPT_HEADER,0);
		curl_setopt($curlObject, CURLOPT_POST, 1);
		curl_setopt($curlObject, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curlObject, CURLOPT_POSTFIELDS, $curlPOSTVARS); 
		$curlResponse = curl_exec($curlObject);
		curl_close($curlObject);
		$VIESwebsiteAbfrageResults['curlExecDone']	= 1 ;
		preg_match('/invalid VAT number/i', $curlResponse, $matchesUngueltig);
		if ( isset($matchesUngueltig[0])) 
		{
			$VIESwebsiteAbfrageResults['curlExecSuccessful'] = 1;
			$VIESwebsiteAbfrageResults['UIDvalid'] = 0;
		}
		else
		{
			preg_match('/Yes, valid VAT number/i', $curlResponse, $matchesGueltig);
			if ( isset($matchesGueltig[0])) 
			{
				$VIESwebsiteAbfrageResults['curlExecSuccessful'] = 1;
				if($validationlevel == 2)
				{ 
					$curlResponse1 = str_ireplace(array("\n","\r","\t"),array("","",""),$curlResponse );
					$trimming = array("<td>", "</td>");
					$nameValidationResultpos1 = stripos ($curlResponse1, '<td class="labelStyle">Name</td>' );
					$nameValidationResultpos2 = stripos ($curlResponse1,'</tr>',$nameValidationResultpos1);
					$nameValidationResult = str_replace($trimming, "", substr ($curlResponse1,$nameValidationResultpos1+32,$nameValidationResultpos2-$nameValidationResultpos1-32));
					$addrValidationResultpos1 = stripos ($curlResponse1, '<td class="labelStyle">Address</td>' );
					$addrValidationResultpos2 = stripos ($curlResponse1,'</tr>',$addrValidationResultpos1);
					$addrValidationResult = str_replace("<br />", " ", str_replace($trimming, "", substr ($curlResponse1,$addrValidationResultpos1+32,$addrValidationResultpos2-$addrValidationResultpos1-32)));
					$NameAndAddressValidationResult = $nameValidationResult .  " " . $addrValidationResult ;
					$validateNameAndAddressResult = UIDvalidationAustria::validateNameAndAddress($name, $address, $NameAndAddressValidationResult, $validationResult);
					if ($validateNameAndAddressResult)
					{
						$VIESwebsiteAbfrageResults['UIDvalid'] = 1;
					}
					else
					{
						$VIESwebsiteAbfrageResults['UIDvalid'] = 0;
					}
				} 
				else
				{				
					$VIESwebsiteAbfrageResults['UIDvalid'] = 1;
				}
			}
			else
			{
				$VIESwebsiteAbfrageResults['curlExecSuccessful'] = 0;
			} 
		} 
		return $VIESwebsiteAbfrageResults;
	}
/* **************************************************************************************************************************************************************************************** */
/* **************************************************************************************************************************************************************************************** */
/* nonEU country service methods				                  											 			      												       		*/
/* **************************************************************************************************************************************************************************************** */
/* **************************************************************************************************************************************************************************************** */
	/**
	 */
	static function validateNonEUcountryUID(&$referenznummer, &$countryOfuid, &$uid2validate, &$validationlevel, &$name, &$address, &$validationResult) 
	{
		$returnCode = FALSE;
		switch ($countryOfuid)
		{
		case "CH" : // Switzerland UID Validation
			$validateCHUID = UIDvalidationAustria::validateCHUID($referenznummer, $countryOfuid, $uid2validate, $validationlevel, $name, $address, $validationResult);
			if ($validateCHUID === FALSE)
			{
				$validationResult["ValidateForeigUID"] = 0;	
				$validationResult["OverallValidationResult"] = 10;
				$validateForeignUID = FALSE;
			}
			else
			{
				$validationResult["ValidateForeigUID"] = 1;	
				if (count ($validateCHUID) == 0 )
				{
					$validationResult["ForeigUIDvalid"] = 0;
					$validationResult["OverallValidationResult"] = 15;
				}
				else
				{
					$validationResult["ForeigUIDvalid"] = 1;
					$validationResult["OverallValidationResult"] = 11;
					$validateForeignUID = array 
					(
						'name'		=>  $validateCHUID->GetByUIDResult->organisationType->organisation->contact->address->postalAddress->organisation->organisationName  ,
						'address'	=>  $validateCHUID->GetByUIDResult->organisationType->organisation->contact->address->postalAddress->addressInformation->street . " " .
										$validateCHUID->GetByUIDResult->organisationType->organisation->contact->address->postalAddress->addressInformation->houseNumber . " " .
										$validateCHUID->GetByUIDResult->organisationType->organisation->contact->address->postalAddress->addressInformation->town . " " .
										$validateCHUID->GetByUIDResult->organisationType->organisation->contact->address->postalAddress->addressInformation->swissZipCode . " " .
										$validateCHUID->GetByUIDResult->organisationType->organisation->contact->address->postalAddress->addressInformation->country->countryIdISO2 . " ",
					);
				}
			}
			break;
		//case "US" : // USA UID Validation
		//	$validateForeignUID = UIDvalidationAustria::validateUSUID($referenznummer, $countryOfuid, $uid2validate, $validationlevel, $name, $address, $validationResult);
		//	break;
		default :	// DEFAULT (do nothing)
			$validateForeignUID = FALSE;
		}
		
		if ($validationResult["OverallValidationResult"] == 11 AND $validationlevel == 2 AND $name != '' AND $address != '')
		{
			$NameAndAddressValidationResult = $validateForeignUID['name'] . " " . $validateForeignUID['address'];
			$validateNameAndAddressResult = UIDvalidationAustria::validateNameAndAddress($name, $address, $NameAndAddressValidationResult, $validationResult);
			if ($validateNameAndAddressResult)
			{
					$validationResult["OverallValidationResult"] = 11;

			}
			else
			{
					$validationResult["OverallValidationResult"] = 10;

			}
		}
		return ;
	}
/* **************************************************************************************************************************************************************************************** */
/* CH UID Validation service method				                  											 			      												       		*/
/* **************************************************************************************************************************************************************************************** */
	/**
	 */
	static function validateCHUID(&$referenznummer, &$countryOfuid, &$uid2validate, &$validationlevel, &$name, &$address, &$validationResult) 
	{
		$result = FALSE;
		$soapClientCHUIDAbfrageURI = $GLOBALS["UIDvalidationATglobalvars"]["soapClientCHUIDAbfrageURI"];
		$soapClientCHUIDOptions =	new stdClass;
		$soapClientCHUIDOptions->uid	=	new stdClass;
		$soapClientCHUIDOptions->uid->uidOrganisationIdCategorie = substr($uid2validate,0,3);
		$soapClientCHUIDOptions->uid->uidOrganisationId = substr($uid2validate,3,9);
		try	{ $soapClientCH = new SoapClient($soapClientCHUIDAbfrageURI); } 
		catch (SoapFault $exception) 						{	UIDvalidationAustria::handleErrors($errorType = 'SoapFault',	$exception,			$validationResultMember = "soapFault_CHUIDabfrage", $validationResult);	} 		
		catch (Exception $otherException)					{	UIDvalidationAustria::handleErrors($errorType = 'Exception',	$otherException,	$validationResultMember = "soapFault_CHUIDabfrage", $validationResult); return FALSE; } 		
		$otherError = error_get_last(); if ($otherError && $otherError['message'] != '')	{	UIDvalidationAustria::handleErrors($errorType = 'otherError',	$otherError,		$validationResultMember = "soapFault_CHUIDabfrage", $validationResult); return FALSE; }	
		try { $result = $soapClientCH->GetByUID($soapClientCHUIDOptions); } 
		catch (SoapFault $exception) 						{	UIDvalidationAustria::handleErrors($errorType = 'SoapFault',	$exception,			$validationResultMember = "soapFault_CHUIDabfrage", $validationResult);	} 		
		catch (Exception $otherException)					{	UIDvalidationAustria::handleErrors($errorType = 'Exception',	$otherException,	$validationResultMember = "soapFault_CHUIDabfrage", $validationResult); return FALSE; } 		
		$otherError = error_get_last(); if ($otherError && $otherError['message'] != '')	{	UIDvalidationAustria::handleErrors($errorType = 'otherError',	$otherError,		$validationResultMember = "soapFault_CHUIDabfrage", $validationResult); return FALSE; }	
		if (is_object($result) ) { return $result;} else { return FALSE; }
	}
/* **************************************************************************************************************************************************************************************** */
/* **************************************************************************************************************************************************************************************** */
/* **************************************************************************************************************************************************************************************** */
/* **************************************************************************************************************************************************************************************** */
/* **************************************************************************************************************************************************************************************** */
/* **************************************************************************************************************************************************************************************** */
/* **************************************************************************************************************************************************************************************** */
/* **************************************************************************************************************************************************************************************** */
/* **************************************************************************************************************************************************************************************** */
/* GENERAL methods									                          											      																*/
/* **************************************************************************************************************************************************************************************** */
	/**
	 * Method to initialize the global variables
	 *
	 * @param	array	$validationResult	The complete result of the validation
	 *
	 * @return  boolean	$result				Initization successful TRUE or FALSE
	 */
	static function initialize($referenznummer = '', &$validationResult)
	{
		/*
		require ("/kunden.uid-validation-austria.php");
		if (array_key_exists($referenznummer, $arr_kunden)) 																			//
		{																																//
			$GLOBALS["UIDvalidationATglobalvars"]["firmaName"]						= $arr_kunden[$referenznummer]["firmaName"];									// Ihre eigene Firma (fuer UID Abfragen)			//
			$GLOBALS["UIDvalidationATglobalvars"]["firmaAdresse"]					= $arr_kunden[$referenznummer]["firmaAdresse"];									// Ihre eigene Adresse (fuer UID Abfragen)	//
			$GLOBALS["UIDvalidationATglobalvars"]["firmaUID"]						= $arr_kunden[$referenznummer]["firmaUID"];										// Ihre eigene UID									//
			$GLOBALS["UIDvalidationATglobalvars"]["finanzOnline_TeilnehmerID"]		= $arr_kunden[$referenznummer]["finanzOnline_TeilnehmerID"];					// Ihre eigene TeilnehmerID bei FinanzOnline		//
			$GLOBALS["UIDvalidationATglobalvars"]["finanzOnline_BenutzerID"]		= $arr_kunden[$referenznummer]["finanzOnline_BenutzerID"];						// FinanzOnline BenutzerID des Webservice Users		//
			$GLOBALS["UIDvalidationATglobalvars"]["finanzOnline_BenutzerPIN"]		= $arr_kunden[$referenznummer]["finanzOnline_BenutzerPIN"];						// FinanzOnline BenutzerPIN des Webservice Users	//
			$GLOBALS["UIDvalidationATglobalvars"]["Stufe2UntergrenzeProzent"]		= $arr_kunden[$referenznummer]["Stufe2UntergrenzeProzent"];						// Mindestprozentsatz Stufe 2 fuer Status 6			//
			$GLOBALS["UIDvalidationATglobalvars"]["Stufe2erfolgreichProzent"]		= $arr_kunden[$referenznummer]["Stufe2erfolgreichProzent"];						// Mindestprozentsatz Stufe 2 fuer Status 1 od. 2	//
			$GLOBALS["UIDvalidationATglobalvars"]["finanzOnline_DataboxFrequenz"]	= $arr_kunden[$referenznummer]["finanzOnline_DataboxFrequenz"];					// Abfragefrequenz für UID Protokolle						//
			$GLOBALS["UIDvalidationATglobalvars"]["DataBoxAbfrageLogging"]			= $arr_kunden[$referenznummer]["DataBoxAbfrageLogging"];						// Eintrag im UIDprotokoll fuer Databox Abfrage				//
			$GLOBALS["UIDvalidationATglobalvars"]["DataBoxAbfrageLeermeldung"]		= $arr_kunden[$referenznummer]["DataBoxAbfrageLeermeldung"];					// Emails mit Leermeldung (keine Protokolle) senden			//
			$GLOBALS["UIDvalidationATglobalvars"]["finanzOnline_EmailAdresse"]		= $arr_kunden[$referenznummer]["finanzOnline_EmailAdresse"];					// Ihre eigene Email Adresse (fuer UID Protokolle)			//
		}
		*/
		// if "general configuration validationLogfilePath is NOT SET, put the logfile into the current directory
		if ($GLOBALS["UIDvalidationATglobalvars"]["validationLogfilePath"] == '')	{ $GLOBALS["UIDvalidationATglobalvars"]["validationLogfilePath"] = dirname(__FILE__); }
		/************************************************************************************************************************************************************************************/
		// WSDL definitions																												      												//
		$GLOBALS["UIDvalidationATglobalvars"]["soapClientSessionURI"]		= "https://finanzonline.bmf.gv.at/fon/services/SessionWSI/wsdl/SessionWSIService.wsdl";	// Webadresse des WSDL Service fuer FO Session		//
		$GLOBALS["UIDvalidationATglobalvars"]["soapClientAbfrageURI"]		= "https://finanzonline.bmf.gv.at/fon/ws/uidAbfrageService.wsdl";						// Webadresse des WSDL Service fuer FO UID Abfrage	//     
		$GLOBALS["UIDvalidationATglobalvars"]["soapClientDataboxURI"]		= "https://finanzonline.bmf.gv.at/fon/services/DataboxWSI/wsdl/DataboxWSIService.wsdl";	// Webadresse des WSDL Service fuer FO Databox		//	
		$GLOBALS["UIDvalidationATglobalvars"]["finanzOnline_Zertifikat"]	= "finanzonline.bmf.gv.at.pem";															// Filename des FinanzOnline Serverzertifikates		//
		$GLOBALS["UIDvalidationATglobalvars"]["finanzOnline_ZertifikatPath"]	= "";																				//													//
		$GLOBALS["UIDvalidationATglobalvars"]["soapClientVIESAbfrageURI"]	= "http://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl";						// Webadresse des WSDL Service fuer EU VIES Abfrage //
		$GLOBALS["UIDvalidationATglobalvars"]["webpageVIESAbfrageURI"]		= "http://ec.europa.eu/taxation_customs/vies/viesquer.do";								// Webadresse der EU VIES Website 					//
		//										   http://ec.europa.eu/taxation_customs/vies/viesquer.do?memberStateCode=AT&requestedMsCode=AT&number=U57966222								//
		$GLOBALS["UIDvalidationATglobalvars"]["soapClientCHUIDAbfrageURI"]	= "https://www.uid-wse.admin.ch/V3.0/PublicServices.svc?wsdl";							// Webadresse des WSDL Service fuer CH UIDs			//
		/************************************************************************************************************************************************************************************/
		$GLOBALS["UIDvalidationATglobalvars"]["arr_FinanzOnline_returncodes"] = array( 
		// Quelle: https://www.bmf.gv.at/EGovernment/FINANZOnline/InformationenfrSoft_3165/_start.htm || Version 5 per 06.02.2013
			0		=> 'Die UID des Erwerbers ist gueltig.',
			1		=> 'Die UID des Erwerbers ist nicht gueltig.',
			3		=> 'Die UID-Nummer des Antragstellers ist ungueltig.',
			4		=> 'Die UID-Nummer des Erwerbers ist falsch.',
			5		=> 'Die UID-Nummer des Antragstellers ist ungueltig.',
			6		=> 'Der angegebene Mitgliedstaat ist derzeit nicht erreichbar.',
			7		=> 'Der angegebene Mitgliedstaat ist derzeit nicht erreichbar.',
			8		=> 'Anfragen sind derzeit nicht moeglich.',
			9		=> 'Name und Anschrift des Erwerbers sind derzeit nicht ermittelbar.',
			101		=> 'Die UID des Antragstellers muss mit ´ATU´ beginnen.',
			102		=> 'Bei ´Stufe´ ist nur ´1´ oder ´2´ zulaessig.',
			103		=> 'Die angefragte UID-Nummer kann im FinanzOnline nur in Stufe 1 bestaetigt werden, da diese UID-Nummer zu einer Unternehmensgruppe (Umsatzsteuergruppe) gehoert. Aus technischen Gruenden werden aus Tschechien keine Firmendaten angezeigt. Für eine gueltige Stufe 2 Abfrage ist es daher erforderlich, dass Sie sich unter http://adisreg.mfcr.cz die Daten der CZ-Umsatzsteuergruppe aufrufen und kontrollieren, ob das angefragte Unternehmen auch tatsaechlich zu dieser Gruppe gehoert. Bitte bewahren Sie den Ausdruck dieser Anfrage in Ihren Unterlagen als Beleg gemaess § 132 BAO auf. Fuer jede Anfrage Stufe 2 ist sowohl das Bestaetigungsverfahren in Stufe 1 im FinanzOnline als auch das Gruppenregister im anderen Mitgliedsstaat laut o.a. Link zu konsultieren. Im Falle von Fragen wenden Sie sich bitte an Ihr zustaendiges Finanzamt.',
			104		=> 'Die angefragte UID-Nummer kann im FinanzOnline nur in Stufe 1 bestaetigt werden, da diese UID-Nummer zu einer Unternehmensgruppe (Umsatzsteuergruppe) gehoert. Aus technischen Gruenden werden aus der Slowakei keine Firmendaten angezeigt. Für eine gueltige Stufe 2 Abfrage ist es daher erforderlich, dass Sie sich unter http://www.drsr.sk die Daten der SK-Umsatzsteuergruppe aufrufen und kontrollieren, ob das angefragte Unternehmen auch tatsaechlich zu dieser Gruppe gehoert. Bitte bewahren Sie den Ausdruck dieser Anfrage in Ihren Unterlagen als Beleg gemaess § BAO auf. Fuer jede Anfrage Stufe 2 ist sowohl das Bestaetigungsverfahren in Stufe 1 im FinanzOnline als auch das Gruppenregister im anderen Mitgliedsstaat laut o.a. Link zu konsultieren. Im Falle von Fragen wenden Sie sich bitte an Ihr zustaendiges Finanzamt.',
			105		=> 'Die UID-Nummer ist ueber FinanzOnline einzeln abzufragen.',
			999		=> 'Nicht alle erforderlichen Parameter wurden angegeben.',
			1511	=> 'Der angegebene Mitgliedstaat ist derzeit nicht erreichbar.',
			9999	=> 'unbekannter Returncode'
		);
		$GLOBALS["UIDvalidationATglobalvars"]["arr_FinanzOnline_RC2VIES"] = array( 
			0		=> 0,
			1		=> 0,
			3		=> 0,
			4		=> 0,
			5		=> 0,
			6		=> 1,	// request VIES validation
			7		=> 1,	// request VIES validation
			8		=> 1,	// request VIES validation
			9		=> 1,	// request VIES validation
			101		=> 0,
			102		=> 0,
			103		=> 0,
			104		=> 0,
			105		=> 0,
			999		=> 0,
			1511	=> 1,	// request VIES validation
			9999	=> 0
		);
		/************************************************************************************************************************************************************************************/
		$GLOBALS["UIDvalidationATglobalvars"]["arr_OverallResultMessage"] = array( 
			0		=> 'Validierung konnte NICHT durchgefuehrt werden - UST aufschlagen!',
			1		=> 'Validierung erfolgreich - EU Unternehmen mit gueltiger UID, UST nicht aufschlagen!',
			2		=> 'Validierung erfolgreich - AT Unternehmen mit gueltiger UID, UST aufschlagen!',
		//	3		=> '',
		//	4		=> '',
			5		=> 'Validierung erfolgreich - UID Nummer ungueltig - UST aufschlagen!',
			6		=> 'Validierung erfolgreich - UID Nummer gueltig - Stufe-2 Validierung ungenuegend - UST aufschlagen!',
		//	7		=> '',
			8		=> 'Validierung nicht durchgeführt - EU Privatperson oder AT Unternehmen - UST aufschlagen!',
			9		=> 'Validierung nicht durchgefuehrt - Export - UST nicht aufschlagen!',

			10		=> 'Validierung der Drittstaaten Unternehmens UID konnte NICHT durchgefuehrt werden!',
			11		=> 'Validierung erfolgreich - UID des Drittstaaten Unternehmens ist gueltig!',
		//	12		=> '',
		//	13		=> '',
		//	14		=> '',
			15		=> 'Validierung erfolgreich - UID des Drittstaaten Unternehmens ist ungueltig!',
			16		=> 'Validierung erfolgreich - UID des Drittstaaten Unternehmens ist gueltig - Stufe2 Validierung ungenuegend!',
		//	17		=> '',
		//	18		=> '',
		//	19		=> '',
		);
		/************************************************************************************************************************************************************************************/
		$setEUmemberstates = UIDvalidationAustria::setEUmemberstates();
		/************************************************************************************************************************************************************************************/
		return TRUE;
	}
/* **************************************************************************************************************************************************************************************** */
	/**
	 * Method to check the need for validation.
	 * Validation is only nessessary if the country is member of the EU (exception: Austria!) and the UID is provided
	 *
	 * @param	string	$uid2validate		The UID to be validated (default EMPTY)
	 * @param	string	$countryOfuid		Countrycode of the UID (default "AT" austria)
	 * @param	string	$name				Company name of the UID (default EMPTY)
	 * @param	string	$address			Address of the UID (default EMPTY)
	 * @param	integer	$validationlevel	Validation level (1 or 2, default 1)
	 * @param	integer	$validateATU		Validation of austrian UIDs (0 or 1, default 0)
	 * @param	array	$validationResult	all elements of the validation as members of an array
	 *
	 * @return  boolean	$result				Validation nessessary TRUE or FALSE
	 */
	static function validationNeeded(&$uid2validate, &$countryOfuid, &$name, &$address, &$validationlevel, &$validateATU, &$validationResult)
	{
		$validation_needed = FALSE;
		if (array_key_exists($countryOfuid, $GLOBALS["UIDvalidationATglobalvars"]["arr_eu_memberstates"])) 
		{ 	// Country is member of EU
			$validationResult["ExportOutsideEU"]	= 0;
			$validationResult["EUmemberstateStandardVATrate"]	= $GLOBALS["UIDvalidationATglobalvars"]["arr_eu_memberstates"][$countryOfuid][1];
			$validationResult["EUmemberstateReducedVATrate"]	= $GLOBALS["UIDvalidationATglobalvars"]["arr_eu_memberstates"][$countryOfuid][2];
			if ($countryOfuid != $GLOBALS["UIDvalidationATglobalvars"]["countryInland"] OR ($countryOfuid == $GLOBALS["UIDvalidationATglobalvars"]["countryInland"] and $validateATU == 1))
			{ 	// Country is not Austria (or is Austria and validation is requested anyway) 
				if ($uid2validate > '')
				{	// UID not empty!
					$validation_needed = TRUE;
				}
			}
		}
		else
		{
			$validationResult["ExportOutsideEU"]	= 1;
			if ($uid2validate != "")
			{
				if (array_key_exists($countryOfuid, $GLOBALS["UIDvalidationATglobalvars"]["validateNonEUcountries"]) AND ($GLOBALS["UIDvalidationATglobalvars"]["validateNonEUcountries"][$countryOfuid] === TRUE ) )
				{
					$validationResult["ValidateNonEUcountry"]	= 1;
					$validation_needed = TRUE;
				}
			}
		}
		return $validation_needed;
	}
/* **************************************************************************************************************************************************************************************** */
	/**
	 * Method to sanitize the input parameters
	 *
	 * @param	string	$countryOfuid		Countrycode of the UID (default "AT" austria)
	 * @param	string	$uid2validate		The UID to be validated (default EMPTY)
	 * @param	integer	$validationlevel	Validation level (1 or 2, default 1)
	 * @param	integer	$validateATU		Validation of austrian UIDs (0 or 1, default 0)
	 * @param	string	$name				Company name of the UID (default EMPTY)
	 * @param	string	$address			Address of the UID (default EMPTY)
	 * @param	array	$validationResult	The complete result of the validation
	 *
	 * @return  boolean	$result				Sanitization successful TRUE or FALSE
	 */
	static function sanitizeInput(&$countryOfuid, &$uid2validate, &$validationlevel, &$validateATU, &$name, &$address, &$validationResult)
	{
		if ($validationlevel == 1 OR $validationlevel == 2) { /* Level OK */ } else {$validationlevel = 1;}
		if ($validateATU == 0 OR $validateATU == 1) { /* validate ATU OK */ } else {$validateATU = 0;}
		
		if ($countryOfuid == 'UK') {$countryOfuid = 'GB';}
		//if ($countryOfuid == 'GR') {$countryOfuid = 'EL';}
		//if ($countryOfuid == 'HU') {$countryOfuid = 'HR';}
		//if ($countryOfuid == 'IE') {$countryOfuid = 'IR';}
		//if ($countryOfuid == 'PL') {$countryOfuid = 'PO';}
		$uid2validate =	str_replace (array(' ', ',', '.', '-', '_'), '', $uid2validate);	
		//
		if (array_key_exists($countryOfuid, $GLOBALS["UIDvalidationATglobalvars"]["validateNonEUcountries"]) AND ($GLOBALS["UIDvalidationATglobalvars"]["validateNonEUcountries"][$countryOfuid] === TRUE ) )
		{
			switch ($countryOfuid)
			{
			case "CH" : // sanitize Switzerland UID
				break;
			//case "US" : // sanitize USA UID
			//	break;
			default :	// DEFAULT (do nothing)
			}
		}
		else
		{
			if	(substr ($uid2validate,0,2) == $countryOfuid ) 
			{
				$validationResult["uidWithLeadingPrefix"] = $uid2validate;
				$uid2validate = substr($uid2validate,2);
			}
			else
			{
				$uidPraefix = $countryOfuid;
				if  ($countryOfuid == 'GR') {$uidPraefix = 'EL';}
				$validationResult["uidWithLeadingPrefix"] = $uidPraefix . $uid2validate;
			}
		}
		return TRUE;
	}
/* **************************************************************************************************************************************************************************************** */
	/**
	 * Method to validate company name and address
	 * 
	 * jedes Element von Name und Adresse (getrennt durch BLANK) wird auf Vorhandensein geprüft
	 * wenn mehr als (siehe $GLOBALS["UIDvalidationATglobalvars"]["Stufe2erfolgreichProzent"])  % der Elemente übereinstimmen, wird TRUE zurückgeliefert
	 
$GLOBALS["UIDvalidationATglobalvars"]["Stufe2UntergrenzeProzent"]		// Mindestprozentsatz Stufe 2 fuer Status 6, 16			//
$GLOBALS["UIDvalidationATglobalvars"]["Stufe2erfolgreichProzent"]		// Mindestprozentsatz Stufe 2 fuer Status 1, 2, 11, 12	//
 
	 * 
	 * @param	string	$name				Company name of the UID (default EMPTY)
	 * @param	string	$address			Address of the UID (default EMPTY)
	 * @param	string	$nameandaddressvalidationresult			Name and Address from the validation result
	 * @param	array	$validationResult	The complete result of the validation
	 *
	 * @return  boolean	$return				Name and Address congruent: TRUE or FALSE
	 */
	static function validateNameAndAddress(&$name, &$address, $NameAndAddressValidationResult, &$validationResult)
	{	
		$sonderzeichen=array(	"Ã¤" => "ä", "Ã¶" => "ö", "Ã¼" => "ü", "Ã„" => "Ä", "Ã–" => "Ö", "Ãœ" => "Ü", 
								"ÃŸ" => "ß", "Ã©" => "Ë",	 
								"\n" => " ", "\r" => " ",
								"-"  => " ", "/"  => " ", ","  => " ", ";"  => " "	);

		$NameAndAddressProvided = $name . " " . $address;
		$arr_nameaddrProvided = explode(" ", strtolower(strtr($NameAndAddressProvided, $sonderzeichen)));
		$arr_nameaddrValidationResult = explode(" ", strtolower(strtr($NameAndAddressValidationResult, $sonderzeichen)));
		$elements_found = $elements = 0;
		if (count ($arr_nameaddrValidationResult) > 0 )
		{
			foreach ($arr_nameaddrValidationResult as $value)
			{
				if ($value != '')
				{
					$elements += 1;
					if (in_array($value, $arr_nameaddrProvided)) 
					{
						$elements_found += 1;
					}
				}
			}
			if ($elements > 0) {$result = $elements_found / $elements * 100;} else {$result = 0;}
		}
		else
		{
			$result = 0;
		}
		$validationResult["NameCongruenceCheckProzent"] = $result;
		$validationResult["NameCongruenceCheckData"] = array( "NameAdressProvided" => $arr_nameaddrProvided,  "NameAdressValidationResult" => $arr_nameaddrValidationResult);
		if ($result > $GLOBALS["UIDvalidationATglobalvars"]["Stufe2erfolgreichProzent"])	{ return TRUE;	} 
		else 																				{ return FALSE;	}
	}
/* **************************************************************************************************************************************************************************************** */
/* **************************************************************************************************************************************************************************************** */
/* **************************************************************************************************************************************************************************************** */
	/**
	 * Method to evaluate the user's country
	 * Notwendig fuer EU Personen
	 * siehe: https://www.bmf.gv.at/steuern/selbststaendige-unternehmer/umsatzsteuer/Leistungsortregel_Neu_2015.html
	 * @return  boolean Success TRUE or FALSE
	 */
	static function evaluateCountry($country = '', $domainname = '')
	{	
		$result = FALSE;
		
		$CountryEvaluation	= array();
		
		$CountryEvaluation["methods"]	= array();
		$CountryEvaluation["methods"]["inputParameter"]	= $country;
		$CountryEvaluation["status"]	= array();

		UIDvalidationAustria::getCountryFromIPbyGEOpluginNet($CountryEvaluation);
		UIDvalidationAustria::getCountryFromBrowserLanguage($CountryEvaluation);
		UIDvalidationAustria::getCountryFromWHOISdomainowner($CountryEvaluation, $domainname);

		$successfulMethods = 0;
		foreach ($CountryEvaluation['methods'] as $CountryEvaluationMethod => $MethodResultCountry)
		{
			if ($MethodResultCountry == $country)
			{
				$successfulMethods = $successfulMethods +1;
			}
		}
		if ($successfulMethods > 1)
		{
			$result = TRUE;
		}
		return $result;
	}
/* **************************************************************************************************************************************************************************************** */
/* **************************************************************************************************************************************************************************************** */
/* **************************************************************************************************************************************************************************************** */
	/**
	 * Method to get the ISO country code from the user's IP address, utilising geoplugin.net service
	 * Quelle: http://snipplr.com/view/72171/
	 * @param	
	 * @return  void
	 */
	static function getCountryFromIPbyGEOpluginNet(&$CountryEvaluation)
	{	
		$CountryEvaluation["methods"]["GEOpluginNet"]	= '-';
		if (isset ($_SERVER['HTTP_CLIENT_IP'])			) {$client	= $_SERVER['HTTP_CLIENT_IP'];}			else {	$client		= 'unknown';}
		if (isset ($_SERVER['HTTP_X_FORWARDED_FOR'])	) {$forward	= $_SERVER['HTTP_X_FORWARDED_FOR'];}	else {	$forward	= 'unknown';}
		if (isset ($_SERVER['REMOTE_ADDR'])				) {$remote	= $_SERVER['REMOTE_ADDR'];}				else {	$remote		= 'unknown';}

		if		( filter_var ( $client,		FILTER_VALIDATE_IP )	)	{ $ip = $client;	}
		elseif	( filter_var ( $forward,	FILTER_VALIDATE_IP )	)	{ $ip = $forward;	}
		else 															{ $ip = $remote;	}
		$CountryEvaluation["status"]["GEOpluginNetIPused"]	= $ip;
		try	{ $geopluginResult	= 	file_get_contents("http://www.geoplugin.net/json.gp?ip=".$ip); } 
		catch (Exception $exception) 
		{ 
			$CountryEvaluation["status"]["GEOpluginNetsuccessful"]	= 0;
			$CountryEvaluation["status"]["GEOpluginNeterror"]	= "errormessage = ( ". str_replace(array("\r\n", "\n", "\r"), " ", $exception->message) ." )";
		}
		if (!$geopluginResult)	{ /* geoPlugin did not work... */}
		else
		{
			$ip_data = json_decode ($geopluginResult);
			if($ip_data && $ip_data->geoplugin_countryName != null)
			{
				$CountryEvaluation["status"]["GEOpluginNetsuccessful"]	= 1;
				$CountryEvaluation["methods"]["GEOpluginNet"]	= $ip_data->geoplugin_countryCode;
			}
		}
		return ;
	}
/* **************************************************************************************************************************************************************************************** */
/* **************************************************************************************************************************************************************************************** */
/* **************************************************************************************************************************************************************************************** */
	/**
	 * Method to get country from user's browser primary language acceptance
	 * @return  boolean Success TRUE or FALSE
	 */
	static function getCountryFromBrowserLanguage(&$CountryEvaluation)
	{	
		$CountryEvaluation["methods"]["BrowserPrimaryLanguageAccepted"] = '-';
		$httpAcceptLanguage	=	$_SERVER["HTTP_ACCEPT_LANGUAGE"];
		preg_match_all('~([\w-]+)(?:[^,\d]+([\d.]+))?~', strtolower($httpAcceptLanguage), $matches, PREG_SET_ORDER);
		foreach($matches as $match) 
		{
			list($a, $b) = explode('-', $match[1]) + array('', '');
			$value = isset($match[2]) ? (float) $match[2] : 1.0;
			if ($value == 1.0 )
			{
				$CountryEvaluation["methods"]["BrowserPrimaryLanguageAccepted"] = strtoupper($b);
			}
		}
		return;
	}
/* **************************************************************************************************************************************************************************************** */
/* **************************************************************************************************************************************************************************************** */
/* **************************************************************************************************************************************************************************************** */
	/**
	 * Method to get country from WHOIS info about the domain owner of a certain internet domain
	 * @return  boolean Success TRUE or FALSE
	 */
	static function getCountryFromWHOISdomainowner(&$CountryEvaluation, $domainname = '')
	{	
		$CountryEvaluation["status"]["WHOISdomainname"] = $domainname;
		$CountryEvaluation["methods"]["WHOISdomainowner"] = '-';
// funktioniert derzeit noch nicht!!!	
return;	
/*	
		$whoisServers = array ("whois.iana.org");
		$validationResult["evaluateCountry"]["country"]["WHOISdomainOwner"] = '-';
		if ($validationResult["domainname"] != '')
		{
			$domainname =  trim( strtolower( $validationResult["domainname"] ) );
			$arrProtocolDomain = explode ('://', $domainname);
			if (isset($arrProtocolDomain[1]) )
			{
				$domain = $arrProtocolDomain[1];
			}
			else
			{
				$domain = $arrProtocolDomain[0];
			}
			$arrDomainParts = explode ('.', $domain);
			$numDomainParts = count($arrDomainParts);
			$tdl = $arrDomainParts[$numDomainParts - 1];
			$receiveFromIANA = '[WHOIS Output BEGINNT]';
			$fp = @fsockopen($whoisServers[0], 43, $errno, $errstr, 20) or die("Socket Error " . $errno . " - " . $errstr);
			if ($fp) 
			{
				// The data we're sending
				$sendTo = $tdl."\r\n";
				fwrite($fp, $sendTo);
			
				while (($buffer = fgets($fp, 1024)) !== false) 
				{
					$receiveFromIANA .= $buffer;
				}
				if (!feof($fp)) 
				{
					echo "Fehler: unerwarteter fgets() Fehlschlag\n";
				}
				fclose($fp);
			}				
			$receiveFromIANA .= '[WHOIS Output ENDET]';
			$queryString1   = "whois:";
			$pos1 = strpos($receiveFromIANA, $queryString1);
			$queryString2   = "status:";
			$pos2 = strpos($receiveFromIANA, $queryString2);
			if ($pos1 === false && $pos2 === false) { /* konnte nicht gefunden werden...* / }
			else {$whoisServers[$tdl] = trim(substr($receiveFromIANA,$pos1+6,$pos2-$pos1-6 ));}

			$receiveFrom = '[WHOIS Output BEGINNT]';
			$fp = @fsockopen($whoisServers[$tdl], 43, $errno, $errstr, 20) or die("Socket Error " . $errno . " - " . $errstr);
			if ($fp) 
			{
				// The data we're sending
				$sendTo = $domain."\r\n";
				fwrite($fp, $sendTo);
			
				while (($buffer = fgets($fp, 1024)) !== false) 
				{
					$receiveFrom .= $buffer;
				}
				if (!feof($fp)) 
				{
					echo "Fehler: unerwarteter fgets() Fehlschlag\n";
				}
				fclose($fp);
			}				
			$receiveFrom .= '[WHOIS Output ENDET]';
		}
*/	
	}
/* **************************************************************************************************************************************************************************************** */
/* **************************************************************************************************************************************************************************************** */
/* **************************************************************************************************************************************************************************************** */
	/**
	 * Method to get the ISO country code from ...
	 * @param	
	 * @return  void
	 */
	static function getCountryFromWHATEVER(&$CountryEvaluation)
	{	
		return;
	}

/* **************************************************************************************************************************************************************************************** */
/* **************************************************************************************************************************************************************************************** */
/* **************************************************************************************************************************************************************************************** */
	/**
	 * Method to get the VAT rates for a specified EU member country
	 *
	 * @param	string	$country			ISO code for country to ask the VAT rates from
	 *
	 * @return  array	$vatrates			VAT rates for specified country (rates will be ZEOR, if country is not an EU memberstate)
	 */
	static function getVATrates(&$country)
	{
		$setEUmemberstates = UIDvalidationAustria::setEUmemberstates();
		$vatRates = array();
		$vatRates['country']	= $country;
		$vatRates['standard']	= 0;
		$vatRates['reduced']	= 0;
		if (array_key_exists($country, $GLOBALS["UIDvalidationATglobalvars"]["arr_eu_memberstates"])) 
		{ 	// Country is member of EU
			$vatRates['standard']	= $GLOBALS["UIDvalidationATglobalvars"]["arr_eu_memberstates"][$country][1];
			$vatRates['reduced']	= $GLOBALS["UIDvalidationATglobalvars"]["arr_eu_memberstates"][$country][2];
			if (isset ($GLOBALS["UIDvalidationATglobalvars"]["arr_eu_memberstates"][$country][3]) )
			{
				$vatRates['reduced_2']	= $GLOBALS["UIDvalidationATglobalvars"]["arr_eu_memberstates"][$country][3];
			}
			if (isset ($GLOBALS["UIDvalidationATglobalvars"]["arr_eu_memberstates"][$country][4]) )
			{
				$vatRates['reduced_3']	= $GLOBALS["UIDvalidationATglobalvars"]["arr_eu_memberstates"][$country][4];
			}
		}
		return $vatRates;
	}
/* **************************************************************************************************************************************************************************************** */
/* **************************************************************************************************************************************************************************************** */
/* **************************************************************************************************************************************************************************************** */
	/**
	 * Method to set EU member states and their VAT rates
	 *
	 * @param	string	$country			ISO code for country to ask the VAT rates from
	 *
	 * @return  array	$vatrates			VAT rates for specified country (rates will be ZEOR, if country is not an EU memberstate)
	 */
	static function setEUmemberstates()
	{
		if (!isset ($GLOBALS["UIDvalidationATglobalvars"]["arr_eu_memberstates"]) )
		{
			/***************************************************************************************************************************************************************************** */
			$GLOBALS["UIDvalidationATglobalvars"]["arr_eu_memberstates"] = array( 
			// QUELLE: http://ec.europa.eu/taxation_customs/tedb/taxSearch.html per 13.07.2015  (advanced search: Type of TAX = VAT)
			// Zusaetzliche Quellen: http://www.vatlive.com/vat-rates/european-vat-rates/eu-vat-rates/
			// Zusaetzliche Quellen: http://publications.europa.eu/code/de/de-370100.htm per 01.03.2013
			// Zusaetzliche Quellen: http://portal.wko.at/wk/format_detail.wk?angid=1&stid=730695&dstid=0&titel=EU-Beitritt%2CKroatiens%2Cam%2C1.7.2013
			//  ISO				Ländername (Beitrittsjahr)				VATstd,	VATred,	VATred2,	VATred3
				'BE' => array ('Belgien (1952)',						21.00,	12.00,	06.00						),
				'BG' => array ('Bulgarien (2007)',						20.00,	09.00								),
				'DK' => array ('D&auml;nemark (1973)',					25.00										),
				'DE' => array ('Deutschland (1952)',					19.00,	07.00								),
				'EE' => array ('Estland (2004)',						20.00,	09.00								),
				'FI' => array ('Finnland (1995)',						24.00,	14.00,	10.00						),
				'FR' => array ('Frankreich (1952)',						20.00,	10.00,	05.50,		02.10			),
				'GR' => array ('Griechenland (1981)',					23.00,	13.00,	06.00						),	// ISO code	//'EL' => array ('Griechenland (1981)',// EU code
				'HR' => array ('Kroatien (2013)',						25.00,	13.00,	05.00						),
				'IE' => array ('Irland (1973)',							23.00,	13.50,	09.00,      04.80			),	// ISO Code	//'IR' => array ('Irland (1973)',// EU Code
				'IT' => array ('Italien (1952)',						22.00,	10.00,	04.00						),
				'LV' => array ('Lettland (2004)',						21.00,	12.00								),
				'LT' => array ('Litauen (2004)',						21.00,	09.00,	05.00						),
				'LU' => array ('Luxemburg (1952)',						17.00,	14.00,  08.00,      03.00			),
				'MT' => array ('Malta (2004)',							18.00,	07.00,  05.00 						),
				'NL' => array ('Niederlande (1952)',					21.00,	06.00								),
				'PL' => array ('Polen (2004)',							23.00,	08.00,	05.00						),	// ISO Code	//'PO' => array ('Polen (2004)',// EU Code
				'PT' => array ('Portugal (1986)',						23.00,	13.00,  06.00						),
				'RO' => array ('Rum&auml;nien (2007)',					20.00,	09.00,	05.00						),
				'SE' => array ('Schweden (1995)',						25.00,	12.00,	06.00						),
				'SK' => array ('Slowakei (2004)',						20.00,	10.00								),
				'SI' => array ('Slowenien (2004)',						22.00,	09.50								),
				'ES' => array ('Spanien (1986)',						21.00,	10.00,	04.00						),
				'CZ' => array ('Tschechische Republik (2004)',			21.00,	15.00,  10.00						),
				'HU' => array ('Ungarn (2004)',							27.00,	18.00,	05.00						),	// ISO Code	//'HR' => array ('Ungarn (2004)',// EU code
				'GB' => array ('Vereinigtes K&ouml;nigreich (1973)',	20.00,	05.00								),	// EU code
				'UK' => array ('Vereinigtes K&ouml;nigreich (1973)',	20.00,	05.00								),	// ISO Code sanitized to GB
				'CY' => array ('Zypern (2004)',							19.00,	09.00,	05.00						),
// Lösung suchen:	'PT-20' => array ('Autonome Region Azoren (PT)',		18.00,	09.00,	04.00						),
			/* Bewerberlaender:  
				'ME' => array ('Montenegro (????)',						00.00,	00.00								),
				'IS' => array ('Island (????)',,						00.00,	00.00								),
				'MK' => array ('Ehem. jug. Republik Mazedonien (????)',	00.00,	00.00								),
				'RS' => array ('Serbien (????)',						00.00,	00.00								),
				'TR' => array ('T&uuml;rkei (????)',					00.00,	00.00								),
			*/
			// INLAND !!!!	
				'AT' => array ('&Ouml;sterreich (1995)',				20.00,  13.00,  10.00						),
			);
			/****************************************************************************************************************************************************************************** */		
			$currentDate = date('Y-m-d h:i:s');
			// ACHTUNG: Zuweisungen und Semikolon!
			//if ($currentDate > '2016-01-01 00:00:00')
			//{
			//	$GLOBALS["UIDvalidationATglobalvars"]["arr_eu_memberstates"]['RO'] = array ('Rum&auml;nien (2007)',			20.00,	09.00,	05.00	);
			//}
			/****************************************************************************************************************************************************************************** */		
		}
		else
		{
			// EU memberstates array already exists!
		}
	}
/* **************************************************************************************************************************************************************************************** */
/* **************************************************************************************************************************************************************************************** */
	/**
	 * Method to write the logfile for this validation.
	 * @param	array	$validationResult	The complete result of the validation
	 * @return  boolean	$return				Logfile write successful: 1 (TRUE) or 0 (FALSE)
	 */
	static function writeLogfile(&$validationResult)
	{	
		// open the logfile for writing/appending (create it if it does not exist)
		$validationLogfile = $GLOBALS["UIDvalidationATglobalvars"]["validationLogfilePath"].DIRECTORY_SEPARATOR.substr($GLOBALS["UIDvalidationATglobalvars"]["firmaUID"],2).".uid-validation-austria.log";
		if ($handle = fopen($validationLogfile, "a")) 
		{
			$arr_validationResultsString = array();
			foreach ($validationResult as $key => $value)
			{
				if (!is_object($value)) 
				{
					if (is_array($value))	
					{ 
						$stringified = '';
						foreach ($value as $innerkey => $innervalue)
						{
							if (is_array($innervalue))
							{
								$stringified .= '|<'; 
								foreach ($innervalue as $nextinnerkey => $nextinnervalue)
								{
									$stringified .= '|{'. $nextinnerkey.":".str_replace (array("\n","\r")," ", $nextinnervalue).'}|'; 
								}
								$stringified .= '>|'; 
							}
							else
							{
								$stringified .= '|<'. $innerkey.":".str_replace (array("\n","\r")," ", $innervalue).'>|'; 
							}
						}
						$value = $stringified;
					}				
					$arr_validationResultsString[] = "[".$key.":".str_replace (array("\n","\r")," ", $value) ."]";
				}
				else
				{
					foreach ($value as $objkey => $objvalue)
					{
						$arr_validationResultsString[] = "[".$key." ".$objkey.":". str_replace (array("\n","\r")," ",$objvalue) ."]";
					}
				}
			}
			$validationResultsString = implode(" | ", $arr_validationResultsString);
			$zeile = date("Y.m.d H:i:s") . " ValidationResults:  ". $validationResultsString . " \n";
			if (fwrite($handle, $zeile)){ $return = 1; } 
			else { $validationResult["LogfileError"]	= "Kann in die Datei $validationLogfile nicht schreiben!"; $return = 0; } 
			fclose($handle);
		}
		else
		{
			$validationResult["LogfileError"]	= "Kann in die Datei $validationLogfile nicht oeffnen!"; 
			$return = 0;
		}
		return $return;
	} 
/* **************************************************************************************************************************************************************************************** */
/* **************************************************************************************************************************************************************************************** */
	/**
	 * Method to act on occured errors.
	 * @param	array	$validationResult	The complete result of the validation
	 * @return  boolean	$return				
	 */
	static function handleErrors($errorType = '', $errorInstance, $validationResultMember = '', &$validationResult)
	{	
		switch ($errorType) 																	      														 
		{				
		case 'SoapFault' :		
			if ( (isset ($errorInstance->detail) AND is_object ($errorInstance->detail) ) and (isset ($errorInstance->detail->detail) ) ) {$detail =  $errorInstance->detail->detail;} else {$detail = '';} 
			$validationResult[$validationResultMember] = "faultcode =".$errorInstance->faultcode." / faultstring = ( ". str_replace(array("\r\n", "\n", "\r"), " ", $errorInstance->faultstring) ." ) / faultDetailString = ( ". str_replace(array("\r\n", "\n", "\r"), " ", $detail) ." )" ; 	
			break;																									      													 
		case 'Exception' :														      													 
			$validationResult[$validationResultMember] = "nonSoapfault Error = (". str_replace(array("\r\n", "\n", "\r"), " ", $errorInstance->getMessage()) . " )" ; 	
			break;																									      													 
		case 'PHPMailer' :														      													 
			$validationResult[$validationResultMember] = "PHPMailer Error = (". str_replace(array("\r\n", "\n", "\r"), " ", $errorInstance->getMessage()) . " )" ; 	
			break;																									      													 
		case 'otherError' :	
			$otherErrorString = ' Type='.$errorInstance['type'].' ## Message='.$errorInstance['message'].' ## File='.$errorInstance['file'].' ## Line='.$errorInstance['line'].' '; 
			$validationResult[$validationResultMember] = "other Error = (". str_replace(array("\r\n", "\n", "\r"), " ", $otherErrorString) . " )" ; 
			@trigger_error('');
			break;
		}
		return;
	} 
/* **************************************************************************************************************************************************************************************** */
/* **************************************************************************************************************************************************************************************** */
	/**
	 * Method to send an email (needs individualisation on each implementation)
	 * Used in: static function austrianServiceDataboxAbfrage
	 * @param	array	$validationResult	The complete result of the validation
	 * @return  boolean	$return				Email send successful: 1 (TRUE) or 0 (FALSE)
	 */
	static function sendEmail(&$arr_anhang_filepfad, &$validationResult)
	{	
		// IN CASE YOU HAVE TIMEOUT TROUBLE SENDING EMAILS WITH A LOT OF ATTACHMENTS...
		// only for test purposes			
		//ini_set('max_execution_time', 600); 			
		// PLEASE DO NOT SET TO INFINITIVE!!!!
		
		$subjectText = iconv('UTF-8', 'windows-1252', "UID Bestätigungen für " . $GLOBALS["UIDvalidationATglobalvars"]["firmaName"] );
		if (count ($arr_anhang_filepfad) > 0 )
		{
			$bodyTextPart1 = iconv('UTF-8', 'windows-1252', "Als Attachment dieser E-Mail erhalten Sie die Bestätigungsprotokolle der UID Validierungen aus FinanzOnline für" );
		}
		else
		{
			$bodyTextPart1 = iconv('UTF-8', 'windows-1252', "Seit dem letzten E-Mail Versand von Bestätigungsprotokollen der UID Validierungen aus FinanzOnline sind keine neuen Protokolle abrufbar für" );
		}
		$bodyTextPart2 = iconv('UTF-8', 'windows-1252', "
Diese Bestätigungsprotokolle gelten (gemäß Rz 4357 UStR) als Beleg und sind in elektronischer Form - oder als Ausdruck -  gemäß § 132 BAO aufzubewahren.

Für Fragen stehen wir Ihnen gerne zur Verfügung.		

Mit freundlichen Grüßen
Schultz IT Solutions
E-Mail: it-solutions@schultz.ch	");
		$bodyText = "Sehr geehrte Damen und Herren,
".$bodyTextPart1."

".iconv('UTF-8', 'windows-1252', $GLOBALS["UIDvalidationATglobalvars"]["firmaName"] )	." 

".iconv('UTF-8', 'windows-1252', $GLOBALS["UIDvalidationATglobalvars"]["firmaAdresse"] )." 

".$bodyTextPart2."";
		require_once($GLOBALS["UIDvalidationATglobalvars"]["smtpMailFunctionPfad"]);
		switch ($GLOBALS["UIDvalidationATglobalvars"]["smtpMailFunction"])
		{
		case 'ZEND_SMTP' : // ZEND Server mail transport SMTP
			//require_once		($GLOBALS["UIDvalidationATglobalvars"]["smtpMailFunctionPfad"].'Mail/Transport/Smtp.php'); // 'Zend/'
			$finfo =			new finfo(FILEINFO_MIME);

			$config = array('auth' => 'login',
							'username' =>	$GLOBALS["UIDvalidationATglobalvars"]["smtpMailAuthUser"],
							'password' =>	$GLOBALS["UIDvalidationATglobalvars"]["smtpMailAuthPassword"],
							'port' =>		$GLOBALS["UIDvalidationATglobalvars"]["smtpMailserverPort"],
							'ssl' =>		'ssl');
			$transport =		new Zend_Mail_Transport_Smtp($GLOBALS["UIDvalidationATglobalvars"]["smtpMailserver"], $config);
			$mail =				new Zend_Mail();
			$mail->setFrom($GLOBALS["UIDvalidationATglobalvars"]["smtpMailFromaddress"], $GLOBALS["UIDvalidationATglobalvars"]["smtpMailFromaddress"]);
			$mail->addTo($GLOBALS["UIDvalidationATglobalvars"]["finanzOnline_EmailAdresse"], $GLOBALS["UIDvalidationATglobalvars"]["finanzOnline_EmailAdresse"]);
			$mail->setSubject($subjectText);
			$mail->setBodyText($bodyText);
			foreach($arr_anhang_filepfad AS $anhang_filepfad)
			{
				$file = $mail->createAttachment(implode("",file($anhang_filepfad)), $finfo->file($anhang_filepfad),Zend_Mime::DISPOSITION_INLINE,Zend_Mime::ENCODING_BASE64);
				$file->filename = basename($anhang_filepfad);
			}
			$mail->send($transport);
			break;
		case 'PHP_MAILER' : // PHP MAILER Funktion
			$mail = new PHPMailer();
			$mail->IsSMTP();
			$mail->Host			= $GLOBALS["UIDvalidationATglobalvars"]["smtpMailserver"];
			$mail->Port			= $GLOBALS["UIDvalidationATglobalvars"]["smtpMailserverPort"];
			$mail->SMTPAuth		= true; 
			$mail->Username		= $GLOBALS["UIDvalidationATglobalvars"]["smtpMailAuthUser"];
			$mail->Password		= $GLOBALS["UIDvalidationATglobalvars"]["smtpMailAuthPassword"];
			//$mail->From		= $GLOBALS["UIDvalidationATglobalvars"]["smtpMailFromaddress"];
			//$mail->FromName	= $GLOBALS["UIDvalidationATglobalvars"]["smtpMailFromaddress"];
			$mail->SetFrom($GLOBALS["UIDvalidationATglobalvars"]["smtpMailFromaddress"],$GLOBALS["UIDvalidationATglobalvars"]["smtpMailFromaddress"]);
			$mail->Subject		= $subjectText;
			$mail->Body			= $bodyText;
			$mail->AddAddress( $GLOBALS["UIDvalidationATglobalvars"]["finanzOnline_EmailAdresse"] );
/* TEST */	$mail->AddBCC('webmaster@schultz.ch');	/* END TEST */
			foreach($arr_anhang_filepfad AS $anhang_filepfad)
			{
				$mail->AddAttachment( $anhang_filepfad , basename($anhang_filepfad) );
			}
			return $mail->Send();		
			break;
		default :	// Unbekannte Mail funktion - Fehler!
			$validationResult["MailSendResult"] = "Unbekannte Mail Funktion konfiguriert - EMail wurde nicht versendet!";
		}
	}
/* **************************************************************************************************************************************************************************************** */
/* **************************************************************************************************************************************************************************************** */
	/**
	 * Method to resend currently unsent Databox documents
	 */
	static function retrysendUnsentDataboxDocuments($referenznummer = "")
	{
		$validationResult	=	array();
		$initialize = UIDvalidationAustria::initialize($referenznummer, $validationResult);
		$arr_FilesInLogfilePath = /*array_diff(*/ scandir( $GLOBALS["UIDvalidationATglobalvars"]["validationLogfilePath"] ) /*, array('..', '.'))*/;
		
		$arr_anhang_filepfad = array();
					
		foreach ($arr_FilesInLogfilePath as $filebez)
		{
			// MIUID_ATU57966222_20140302.xml
			if ( substr($filebez,0,5) == 'MIUID'  &&  substr($filebez,6,11) == $GLOBALS["UIDvalidationATglobalvars"]["firmaUID"]   && substr($filebez,-4,4) == '.xml' )
			{
				$arr_anhang_filepfad[] = $GLOBALS["UIDvalidationATglobalvars"]["validationLogfilePath"].DIRECTORY_SEPARATOR.$filebez;
			}
		}

		$emailSendResult = UIDvalidationAustriaExtendedServices::sendEmail($arr_anhang_filepfad, $validationResult);
		
		if ($emailSendResult === FALSE)
		{
			$validationResult["EmailSendErfolgreich"] = 0;
		}
		else
		{
			$validationResult["EmailSendErfolgreich"] = 1;
			// Databox File löschen					
			foreach ($arr_anhang_filepfad as $file2unlink) {unlink($file2unlink);}
		}
		if ($GLOBALS["UIDvalidationATglobalvars"]["DataBoxAbfrageLogging"])
		{
			$validationResult["writeLogfile"]	= UIDvalidationAustria::writeLogfile($validationResult);
		}
		return $validationResult;
	}
/* **************************************************************************************************************************************************************************************** */
/* **************************************************************************************************************************************************************************************** */
}
/* **************************************************************************************************************************************************************************************** */
/* END class UIDvalidationAustria			                   														      															 		*/
/* **************************************************************************************************************************************************************************************** */
