<?php

class Api_Plugins_TranslationsDynamic_Plugin extends Api_TraderApiPlugin {

    /**
     * Defines the priority of the Plugin. (Higher number = more important)
     * @return int
     */
    static public function getPriority() {
        return 0;
    }

    /**
     * Register the events needed by your plugin within this function.
     * @return bool     False for error, plugin will not be loaded if an error occurs
     */
    public function registerEvents() {
        // TODO: Register your events here
        $this->registerEvent(Api_TraderApiEvents::SYSTEM_CACHE_TRANSLATIONS, "systemCacheTranslations");
        return true;
    }
    
    private function extendTranslations($namespace, &$arTranslations, &$dirty) {
        switch ($namespace) {
            case "general":
                /*
                 * General translations
                 */
                $this->extendTranslations_add($arTranslations, $dirty, "date.days", "Tage");
                $this->extendTranslations_add($arTranslations, $dirty, "editor.image.insert.label", "Auswählen");
                $this->extendTranslations_add($arTranslations, $dirty, "statistic.access.profile", "Profilzugriffe");
                $this->extendTranslations_add($arTranslations, $dirty, "statistic.access", "Zugriffe");
                $this->extendTranslations_add($arTranslations, $dirty, "month", "Monat");
                break;
            case "marktplatz":
                /*
                 * Marketplace translations "marktplatz"
                 */
                break;
            case "marketplace":
                /*
                 * Marketplace translations "marketplace"
                 */
                $this->extendTranslations_add($arTranslations, $dirty, "ad.create.article.title", "Artikel-Bezeichnung");
                $this->extendTranslations_add($arTranslations, $dirty, "ad.create.category.title", "Kategorie");
                $this->extendTranslations_add($arTranslations, $dirty, "ad.create.confirm.title", "Eingaben bestätigen");
                $this->extendTranslations_add($arTranslations, $dirty, "ad.create.details.title", "Artikel-Details");
                $this->extendTranslations_add($arTranslations, $dirty, "ad.create.hdb.title", "Produktdatenbank");
                $this->extendTranslations_add($arTranslations, $dirty, "ad.create.images.assign.to.all.variants", "Alle");
                $this->extendTranslations_add($arTranslations, $dirty, "ad.create.location.title", "Artikel-Standort");
                $this->extendTranslations_add($arTranslations, $dirty, "ad.create.media.title", "Medien");
                $this->extendTranslations_add($arTranslations, $dirty, "ad.create.packet.title", "Anzeigenpaket");
                $this->extendTranslations_add($arTranslations, $dirty, "ad.create.payment.description", "Einstellgebühr für {COUNT} Anzeigen");
                $this->extendTranslations_add($arTranslations, $dirty, "ad.create.rent.price.missing", "Bitte geben Sie mindestens einen Mietpreis ein!");
                $this->extendTranslations_add($arTranslations, $dirty, "ad.create.sales.option.invalid", "Unzulässige Verkaufsoption!");
                $this->extendTranslations_add($arTranslations, $dirty, "ad.create.variants.title", "Artikel-Varianten");
                $this->extendTranslations_add($arTranslations, $dirty, "contact.request", "Kontaktanfrage");
                $this->extendTranslations_add($arTranslations, $dirty, "contact.request.hint", "Hinweis: Die ausstehenden Kontaktanfragen finden Sie unter 'Mein Account > Meine Einstellungen > Meine Kontakte'.");
                $this->extendTranslations_add($arTranslations, $dirty, "coupon.error.coupon.activated.but.not.compatible", "Der Gutschein wurde aktiviert, kann für die aktuelle Bestellung aber nicht verwendet werden. Sie können diesen in einer anderen Bestellung verwenden.");
                $this->extendTranslations_add($arTranslations, $dirty, "coupon.error.coupon.code.not.found", "Es konnte kein Gutschein für diesen Code gefunden werden");
                $this->extendTranslations_add($arTranslations, $dirty, "coupon.error.coupon.code.not.valid", "Der eingegebene Code ist leider ungültig");
                $this->extendTranslations_add($arTranslations, $dirty, "coupon.error.coupon.no.code", "Es wurde kein Code eingegeben");
                $this->extendTranslations_add($arTranslations, $dirty, "coupon.error.coupon.not.valud", "Der Gutschein ist leider nicht mehr gültig");
                $this->extendTranslations_add($arTranslations, $dirty, "coupon.error.coupon.type.not.found", "Es konnte kein Gutschein geladen werden");
                $this->extendTranslations_add($arTranslations, $dirty, "coupon.restriction.class.daterestriction.name", "An Zeitraum gebunden");
                $this->extendTranslations_add($arTranslations, $dirty, "coupon.restriction.class.individualcode.name", "Indivudeller Code");
                $this->extendTranslations_add($arTranslations, $dirty, "coupon.restriction.class.onlyoneperuser.name", "Auf einen Gutschein pro User limitiert");
                $this->extendTranslations_add($arTranslations, $dirty, "coupon.restriction.class.quantity.name", "Anzahl der Verwendung limitiert");
                $this->extendTranslations_add($arTranslations, $dirty, "coupon.restriction.class.usergroup.name", "Auf Benutzergruppen beschränkt");
                $this->extendTranslations_add($arTranslations, $dirty, "coupon.type.absolutevalue.descripion", "Gutschein Code {COUPON_CODE} vom {COUPON_DATE}");
                $this->extendTranslations_add($arTranslations, $dirty, "coupon.type.absolutevalue.name", "Absoluter Betrag");
                $this->extendTranslations_add($arTranslations, $dirty, "coupon.type.absolutevalue.onactivationmessage", "Ihnen wurde eine Gutschrift i.H.v. {CREDIT_AMOUNT} € zzgl. MwSt. in Ihrem Account hinterlegt.");
                $this->extendTranslations_add($arTranslations, $dirty, "coupon.type.percentagevalue.billingnote", "Gutschein {COUPON_CODE} vom {COUPON_DATE} für ");
                $this->extendTranslations_add($arTranslations, $dirty, "coupon.type.percentagevalue.name", "Prozentualer Wert");
                $this->extendTranslations_add($arTranslations, $dirty, "coupon.type.percentagevalue.onactivationmessage", "Der Gutschein wird mit der nächsten Buchung einer im Gutschein enthaltenen Leistung verrechnet");
                $this->extendTranslations_add($arTranslations, $dirty, "coupon.type.register.membership.descripion", "Gutschein Code {COUPON_CODE} vom {COUPON_DATE}");
                $this->extendTranslations_add($arTranslations, $dirty, "coupon.type.register.membership.name", "Spezielle Mitgliedschaft");
                $this->extendTranslations_add($arTranslations, $dirty, "coupon.type.register.membership.onactivationmessage", "Ihnen wurde die Mitgliedschaft {htm(MEMBERSHIP_NAME)} für die Registierung freigeschaltet.");
                $this->extendTranslations_add($arTranslations, $dirty, "import.field.import.affiliate.deeplink", "Affiliate-Deeplink");
                $this->extendTranslations_add($arTranslations, $dirty, "import.field.importask.displayname", "Import Anweisung");
                $this->extendTranslations_add($arTranslations, $dirty, "import.mapping.function.explode.name", "Zerteilen");
                $this->extendTranslations_add($arTranslations, $dirty, "import.mapping.function.listtablefieldmapfunction.name", "Wertzuordnung aus Listenfeld");
                $this->extendTranslations_add($arTranslations, $dirty, "import.mapping.function.mapfunction.name", "Wertzuordnung");                
                $this->extendTranslations_add($arTranslations, $dirty, "import.preset.delete.error", "Es ist ein Fehler beim Löschen aufgetreten");
                $this->extendTranslations_add($arTranslations, $dirty, "import.preset.delete.success", "Die Import Vorlage wurde erfolgreich gelöscht");
                $this->extendTranslations_add($arTranslations, $dirty, "import.preset.edit.error", "Es ist ein Fehler aufgetreten");
                $this->extendTranslations_add($arTranslations, $dirty, "import.preset.editor.map.error.fkkatempty", "Sie müssen dem Feld Zugeordnete Kategorie einen Wert zuweisen");
                $this->extendTranslations_add($arTranslations, $dirty, "import.preset.editor.map.error.produktnameempty", "Sie müssen dem Feld Produktname einen Wert zuweisen");
                $this->extendTranslations_add($arTranslations, $dirty, "import.preset.editor.setting.error.savefailed", "Die Vorlage konnte nicht gespeichert werden");
                $this->extendTranslations_add($arTranslations, $dirty, "import.preset.editor.type.eBay.error.no.account", "Es wurde noch kein eBay-Account zugeordnet!");
                $this->extendTranslations_add($arTranslations, $dirty, "import.preset.editor.type.error.fileurlnotfound", "Die angegebende Vorlagen Url konnte nicht geladen werden");
                $this->extendTranslations_add($arTranslations, $dirty, "import.preset.editor.type.error.nameempty", "Es wurde keine Vorlagen Bezeichnung angegeben");
                $this->extendTranslations_add($arTranslations, $dirty, "import.preset.editor.type.error.nofile", "Es wurde keine Vorlagen Datei ausgewählt");
                $this->extendTranslations_add($arTranslations, $dirty, "import.preset.editor.type.error.typeemtpy", "Es wurde kein Vorlagen Typ gewählt");
                $this->extendTranslations_add($arTranslations, $dirty, "import.preset.error.filestructurenotmatch", "Die Struktur der Import Datei stimmt nicht mit der Vorlagenstruktur überein");
                $this->extendTranslations_add($arTranslations, $dirty, "import.preset.init.ebay.col.itemid", "Preis");
                $this->extendTranslations_add($arTranslations, $dirty, "import.process.delete.success", "Der Import wurde erfolgreich gelöscht");
                $this->extendTranslations_add($arTranslations, $dirty, "import.process.import.importidentifier.exists", "Es existiert bereits ein Datensatz mit dem Identifikator {IMPORT_IDENTIFIER}");
                $this->extendTranslations_add($arTranslations, $dirty, "import.process.import.importidentifier.exists.blocked", "Es existiert bereits ein Datensatz mit dem Identifikator {IMPORT_IDENTIFIER} im Datenimport");
                $this->extendTranslations_add($arTranslations, $dirty, "import.process.import.importidentifier.not.exists", "Es existiert kein Datensatz mit dem Identifikator {IMPORT_IDENTIFIER}");
                $this->extendTranslations_add($arTranslations, $dirty, "import.process.import.nocategoryaccess.", "Sie haben keinen Zugriff auf die Kategorie {KAT_ID}");
                $this->extendTranslations_add($arTranslations, $dirty, "import.process.import.nopacket.", "Es wurde kein Paket mit ausreichendem Anzeigenvolumen gefunden");
                $this->extendTranslations_add($arTranslations, $dirty, "import.process.import.noruntime.", "Die gewählte Laufzeit {LU_LAUFZEIT} ist nicht verfügbar");
                $this->extendTranslations_add($arTranslations, $dirty, "import.process.import.start.alreadyonline.", "Der Artikel ist bereits online");
                $this->extendTranslations_add($arTranslations, $dirty, "import.process.run.debug", "Starte Import Aufgabe Prozess");
                $this->extendTranslations_add($arTranslations, $dirty, "import.process.run.debug.import", "Führe Import Aufgabe \"Import der Daten\" aus");
                $this->extendTranslations_add($arTranslations, $dirty, "import.process.run.debug.infra", "Führe Import Aufgabe \"Infrastruktur Erzeugung\" aus");
                $this->extendTranslations_add($arTranslations, $dirty, "import.process.run.debug.load", "Führe Import Aufgabe \"Daten Einlesen\" aus. Optionen: {OPTIONS}");
                $this->extendTranslations_add($arTranslations, $dirty, "import.process.run.debug.pre", "Import wurde gestartet");
                $this->extendTranslations_add($arTranslations, $dirty, "import.process.run.debug.transform", "Führe Import Aufgabe \"Daten transformieren\" aus");
                $this->extendTranslations_add($arTranslations, $dirty, "import.process.run.debug.validate", "Führe Import Aufgabe \"Daten validieren\" aus");
                $this->extendTranslations_add($arTranslations, $dirty, "import.process.run.validate.deleteinvalid", "Es wurden {COUNT} ungültige Datensätze entfernt");
                $this->extendTranslations_add($arTranslations, $dirty, "import.process.statusname.error", "Fehler");
                $this->extendTranslations_add($arTranslations, $dirty, "import.process.statusname.finish", "Import vollständig abgeschlossen");
                $this->extendTranslations_add($arTranslations, $dirty, "import.process.statusname.import", "Daten importieren");
                $this->extendTranslations_add($arTranslations, $dirty, "import.process.statusname.importready", "Daten importieren fertiggestellt");
                $this->extendTranslations_add($arTranslations, $dirty, "import.process.statusname.infrastructure", "Infrastruktur erzeugen");
                $this->extendTranslations_add($arTranslations, $dirty, "import.process.statusname.ini", "Initialisieren");
                $this->extendTranslations_add($arTranslations, $dirty, "import.process.statusname.load", "Daten einlesen");
                $this->extendTranslations_add($arTranslations, $dirty, "import.process.statusname.loadready", "Daten einlesen fertiggestellt");
                $this->extendTranslations_add($arTranslations, $dirty, "import.process.statusname.transform", "Daten transformieren");
                $this->extendTranslations_add($arTranslations, $dirty, "import.process.statusname.transformready", "Daten transformieren fertiggestellt");
                $this->extendTranslations_add($arTranslations, $dirty, "import.process.statusname.validate", "Daten überprüfen");
                $this->extendTranslations_add($arTranslations, $dirty, "import.process.statusname.validateready", "Daten überprüfen fertiggestellt");
                $this->extendTranslations_add($arTranslations, $dirty, "import.process.transform.failed.categorynotfound", "Tranformation des Datensatzes fehlgeschlagen.<br>Kategorie \"{KAT_NAME}\" wurde nicht gefunden");
                $this->extendTranslations_add($arTranslations, $dirty, "import.process.transform.failed.postvalidation", "<span class=\"text-error\">Transformation des Datensatzes {DATASET_NAME} {DATASET_IDENT} fehlgeschlagen</span><br>Es konnte keine Kategorie zugewiesen werden!");
                $this->extendTranslations_add($arTranslations, $dirty, "import.process.transform.nocategorymapping.", "Es wurde kein Kategoriefeld zugeordnet");
                $this->extendTranslations_add($arTranslations, $dirty, "import.source.delete.ads.success", "Alle Anzeigen aus dieser Import-Quelle wurden gelöscht.");
                $this->extendTranslations_add($arTranslations, $dirty, "import.source.delete.success", "Die Quelle wurde erfolgreich gelöscht");
                $this->extendTranslations_add($arTranslations, $dirty, "import.validation.error.value.not.accepted", "Der Wert des Feldes \"{FIELD_VALUE}\" ist kein gültiger Wert");
                $this->extendTranslations_add($arTranslations, $dirty, "invoice.period.performance", "Leistungszeitraum");
                $this->extendTranslations_add($arTranslations, $dirty, "manufacturer", "Hersteller");
                $this->extendTranslations_add($arTranslations, $dirty, "rent.message.date", "Gewünschter Zeitraum: {todate(FROM)} bis {todate(TO)}\n");
                $this->extendTranslations_add($arTranslations, $dirty, "rent.missing.from", "Bitte geben Sie das Start-Datum an!");
                $this->extendTranslations_add($arTranslations, $dirty, "rent.missing.to", "Bitte geben Sie das Rückgabe-Datum an!");
                $this->extendTranslations_add($arTranslations, $dirty, "select.please.choose", "Bitte wählen");
                $this->extendTranslations_add($arTranslations, $dirty, "statistic.amount", "Betrag");
                $this->extendTranslations_add($arTranslations, $dirty, "statistic.count", "Anzahl");
                $this->extendTranslations_add($arTranslations, $dirty, "statistic.count.invoices", "Anzahl Rechnungen");
                $this->extendTranslations_add($arTranslations, $dirty, "statistic.date", "Datum");
                $this->extendTranslations_add($arTranslations, $dirty, "statistic.invoices.values", "Rechnungsstatus letzte {DAYS} Tage");
                $this->extendTranslations_add($arTranslations, $dirty, "watchlist.list", "Liste");
                break;
        }
    }
    
    private function extendTranslations_add(&$arTranslations, &$dirty, $ident, $default) {
        if (!array_key_exists($ident, $arTranslations)) {
            $arTranslations[$ident] = $default;
            $dirty = true;
        }
    }
    
    public function systemCacheTranslations(Api_Entities_EventParamContainer $params) {
        $namespace = $params->getParam("namespace");
        $dirty = false;
        $arTranslations = $params->getParam("translations");
        $this->extendTranslations($namespace, $arTranslations, $dirty);
        if ($dirty) {
            $params->setParam("translations", $arTranslations);
        }
    }
}