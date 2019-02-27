<?php 
/* **************************************************************************************************************************************************************************************** */
/* UIDvalidationAustria																																			VERSION: 1.1.6 / 2016-03-01	*/
/*		ueberprueft eine UID beim oestereichischen FinanzOnline (oder EU VIES) webservice																									*/
/* Copyright (c) 	 Schultz IT Solutions    http://www.schultz.ch           			  																							    	*/
/* **************************************************************************************************************************************************************************************** */
// GLOBALE KONFIGURATIONEN je Installation																																					//
$GLOBALS["UIDvalidationATglobalvars"]["smtpMailFunction"]				= "PHP_MAILER";											// MAIL Funktion											//
$GLOBALS["UIDvalidationATglobalvars"]["smtpMailFunctionPfad"]			= "";													// 															//
$GLOBALS["UIDvalidationATglobalvars"]["smtpMailAuthUser"]				= "";													// SMTP Authorisierter User									//
$GLOBALS["UIDvalidationATglobalvars"]["smtpMailAuthPassword"]			= "";													// Passwort für SMTP User									//
$GLOBALS["UIDvalidationATglobalvars"]["smtpMailserverPort"]				= "";													// 															//
$GLOBALS["UIDvalidationATglobalvars"]["smtpMailserver"]					= "";													// 															//
$GLOBALS["UIDvalidationATglobalvars"]["smtpMailFromaddress"]			= "";													// 															//
$GLOBALS["UIDvalidationATglobalvars"]["validationLogfile"]				= "";													// Logfile der UID-Validierungen							//
$GLOBALS["UIDvalidationATglobalvars"]["validationLogfilePath"]			= __DIR__ . "/logfiles";								// Logdirectory												//
$GLOBALS["UIDvalidationATglobalvars"]["validateNonEUcountries"]			= array ("CH" => TRUE, "AQ" => FALSE);					// UIDs in Nicht-EU Staaten pruefen							//
$GLOBALS["UIDvalidationATglobalvars"]["countryInland"]					= 'AT';													// Laenderkennzeichen fuer INLAND							//
/********************************************************************************************************************************************************************************************/
$arr_kunden = array	(																																										//
	/****************************************************************************************************************************************************************************************/
	/****************************************************************************************************************************************************************************************/
	/****************************************************************************************************************************************************************************************/
	"710-00000:AAAAAAAAAAAA" => array ( // 																																					//
		"firmaName"													=>	"", 													// Ihre eigene Firma (fuer UID Abfragen)					//
		"firmaAdresse"												=>	"", 													// Ihre eigene Adresse (fuer UID Abfragen)					//
		"firmaUID"													=>	"", 													// Ihre eigene UID											//
		"finanzOnline_TeilnehmerID"									=>	"", 													// Ihre eigene TeilnehmerID bei FinanzOnline				//
		"finanzOnline_BenutzerID"									=>	"", 													// FinanzOnline BenutzerID des Webservice Users				//
		"finanzOnline_BenutzerPIN"									=>	"", 													// FinanzOnline BenutzerPIN des Webservice Users			//
		"Stufe2UntergrenzeProzent"									=>	15.00 ,													// Mindestprozentsatz Stufe 2 fuer Status 6					//
		"Stufe2erfolgreichProzent"									=>	60.00 ,													// Mindestprozentsatz Stufe 2 fuer Status 1 od. 2			//
		"finanzOnline_DataboxFrequenz"								=>	"DO", 													// Abfragefrequenz für UID Protokolle						//
		"DataBoxAbfrageLogging"										=>	FALSE,													// Eintrag im UIDprotokoll fuer Databox Abfrage				//
		"DataBoxAbfrageLeermeldung"									=>	TRUE,													// Emails mit Leermeldung (keine Protokolle) senden			//
		"finanzOnline_EmailAdresse"									=>	"",														// Ihre eigene Email Adresse (fuer UID Protokolle)			//
		),																														//															//
);																																//															//
/********************************************************************************************************************************************************************************************/
/********************************************************************************************************************************************************************************************/
