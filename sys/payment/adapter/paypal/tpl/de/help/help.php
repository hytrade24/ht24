<?php
/* ###VERSIONSBLOCKINLCUDE### */

require_once '../../../../../../../api.php';
require_once $ab_path . 'sys/lib.payment.adapter.php';
require_once $ab_path . 'sys/payment/PaymentFactory.php';

$template = new Template("tpl/de/empty.htm");

$paymentAdapterManagement = PaymentAdapterManagement::getInstance($db);
$paymentAdapter = $paymentAdapterManagement->fetchByAdapterName("PayPal");

$config = $paymentAdapterManagement->fetchConfigurationById($paymentAdapter['ID_PAYMENT_ADAPTER']);

?>
<!DOCTYPE html>
<html>
	<head>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
		<link rel="stylesheet" type="text/css" href="<?php echo $template->tpl_uri_resource('/lib/bootstrap/css/bootstrap.css'); ?>" />

		<script src="<?php echo $template->tpl_uri_resource('/lib/jquery/jquery.js'); ?>"></script>

		<script type="text/javascript" src="<?php echo $template->tpl_uri_baseurl('/js/slimbox2.js'); ?>"></script>
		<link rel="stylesheet" type="text/css" href="<?php echo $template->tpl_uri_baseurl('/js/slimbox/css/slimbox2.css'); ?>" />
	</head>
	<body>
		<div class="container">
			<div class="row-fluid">
				<div class="span6 offset3">

					<h1>Anleitung zum Integrieren Ihres PayPal Accounts</h1>

					<p>
						Auf dieser Seite erklären wir Ihnen in vier kurzen Schritten, wie Sie Ihren PayPal Account einfach und sicher als Marktplatzverkäufer
						integrieren.
					</p>

					<ul class="media-list">
						<li class="media">
							<a class="pull-left lightbox-gallery" href="<?php echo $template->tpl_thumbnail('"/sys/payment/adapter/paypal/tpl/de/help/1.jpg",800,800'); ?>">
								<img class="media-object" src="<?php echo $template->tpl_thumbnail('"/sys/payment/adapter/paypal/tpl/de/help/1.jpg",64,64'); ?>">
							</a>

							<div class="media-body">
								<h4 class="media-heading">Schritt 1: Login bei PayPal</h4>
								Loggen Sie sich mit Ihrem PayPal Account auf https://www.paypal.com ein. Sollten Sie noch keinen PayPal Account
								besitzen, registrieren Sie sich bitte zuerst als Geschäftskunde bei PayPal und folgen im Anschluss dieser Anleitung.
							</div>
						</li>

						<li class="media">
							<a class="pull-left lightbox-gallery" href="<?php echo $template->tpl_thumbnail('"/sys/payment/adapter/paypal/tpl/de/help/2.jpg",800,800'); ?>">
								<img class="media-object" src="<?php echo $template->tpl_thumbnail('"/sys/payment/adapter/paypal/tpl/de/help/2.jpg",64,64'); ?>">
							</a>

							<div class="media-body">
								<h4 class="media-heading">Schritt 2: Menüpunkt API Zugriff</h4>
								Nach erfolgreichen Login, navigierien Sie bitte im Hauptmenü auf <b>Mein Profil -> mehr</b> und wählen dort auf der linken Seite den
								Unterpunkt <strong>Verkäufer/Händler</strong>. Es erscheint eine Liste mit weiteren Konfigurationsbereichen in der Sie
								<strong>API-Zugriff aktualisieren</strong> anklicken.
							</div>
						</li>

						<li class="media">
							<a class="pull-left lightbox-gallery" href="<?php echo $template->tpl_thumbnail('"/sys/payment/adapter/paypal/tpl/de/help/3.jpg",800,800'); ?>">
								<img class="media-object" src="<?php echo $template->tpl_thumbnail('"/sys/payment/adapter/paypal/tpl/de/help/3.jpg",64,64'); ?>">
							</a>

							<div class="media-body">
								<h4 class="media-heading">Schritt 3: API-Genehmigung erteilen</h4>
								Es erscheinen zwei Optionen, API Genehmigung erteilen und API Signatur erstellen. Bitte wählen Sie an dieser Stelle
								<strong>API-Genehmigung erteilen</strong>.
							</div>
						</li>

						<li class="media">
							<a class="pull-left lightbox-gallery" href="<?php echo $template->tpl_thumbnail('"/sys/payment/adapter/paypal/tpl/de/help/4.jpg",800,800'); ?>">
								<img class="media-object" src="<?php echo $template->tpl_thumbnail('"/sys/payment/adapter/paypal/tpl/de/help/4.jpg",64,64'); ?>">
							</a>

							<div class="media-body">
								<h4 class="media-heading">Schritt 4: Berechtigung vergeben</h4>
								Tragen Sie im Folgenden den API Benutzernamen des Marktplatzbetreibers ein. Dieser lautet: <br>
								<strong><?php echo $config['ApiUser']; ?></strong>
								<br>
								Im letzten Schritt werden Sie nach Genehmigungen gefragt, die Sie dem Marktplatzbetreiber einräumen. Aktivieren Sie
								<em>ausschließlich</em>: <br>
								- Verwenden der Express-Kaufabwicklung für Ihre Zahlungsvorgänge<br>
								- Abrufen von Informationen zu einer einzelnen Transaktion<br>
								<br>
								und bestätigen Sie den Prozess mit Hinzufügen.

							</div>
						</li>
					</ul>
					<p>
						Tragen Sie abschließend im Eingabefeld <i>PayPal-Account</i> in der Konfiguration Ihrer akzeptierten
						Zahlungsweisen im Marktplatz Ihre PayPal E-Mail Adresse z.B. <strong>maxmustermann@example.com</strong>
						ein.
					</p>
				</div>
			</div>
		</div>
	</body>
</html>