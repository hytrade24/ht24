<?php
/* ###VERSIONSBLOCKINLCUDE### */



/*
 * 	Hier werdn alle Tabellen definiert, die einen FK auf einen User haben.
	3 Listen

	$ar_update = Liste mit allen Tabellen, die ver�ndert werden m�ssen
		-> Den FK �ndern
	$ar_delete = Liste mit allen Tabellen, aus denen der User gel�scht werden muss
	$ar_nodel = Wenn in diesen Tabellen ein Eintrag vorhaden ist,
		kann der User nicht gel�scht werden

	Aufbau der Listen als Array

	array ( 'tabelle' => 'feld' )

	Tabellen mit 2 betroffenen Feldern doppelt angeben !!!
 */

$ar_update = array(
	'galerie' => 'FK_USER',
	'img' => 'FK_USER',
	'comment' => 'FK_USER_OWNER',
	'comment' => 'FK_USER',
	'news' => 'FK_USER',
	'news' => 'FK_AUTOR',
);

$ar_delete = array(
	'ad_agent'		=> 'FK_USER',
	'ad_likes'		=> 'FK_USER',
	'ad_reminder'	=> 'FK_USER',
	'ad_search'		=> 'FK_USER',
	'ad_reminder'	=> 'FK_USER',

	'comment_ipcheck' => 'FK_USER',
	'eventlog' => 'FK_USER',

	'locks' => 'FK_USER',
	'mail' => 'FK_USER_FROM',
	'mail' => 'FK_USER_TO',
	'nl_recp' => 'FK_USER',
	'pageperm2user' => 'FK_USER',
	'perm2user' => 'FK_USER',
	'report' => 'FK_USER',

	'role2user' => 'FK_USER',
	'usersettings' => 'FK_USER',
	'user' => 'ID_USER',
	'user2img' => 'FK_USER',
	'user_views' => 'FK_USER',
	'useronline' => 'ID_USER',
	'usersettings' => 'FK_USER',
    'user_authentication' => 'FK_USER'
);

$ar_nodel = array
(
	/*
	 * Je nach Projekt hier die Tabellen der Buchhaltungsdaten eintragen
	 * wenn welche vorhanden sind versteht sich.
	 */
	'purchased_news' => 'FK_USER'
);

?>
