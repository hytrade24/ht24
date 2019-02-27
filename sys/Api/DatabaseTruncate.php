<?php
/**
 * Created by jens
 * Date: 30.09.15
 * Time: 18:03
 */

class Api_DatabaseTruncate {

    public static $arOptions = array(
        "DEL_ADS", "DEL_REQUESTS", "DEL_NEWS", "DEL_CLUBS", "DEL_JOBS", "DEL_EVENTS", "DEL_VENDOR", "DEL_ADVERTISEMENT",
        "DEL_COMMENTS", "DEL_CHAT", "DEL_WATCHLIST", "DEL_INVOICE", "DEL_GEOLOCATION", "DEL_EVENTLOG", "DEL_NAVBACKUPS",
        "DEL_MAN", "DEL_USER", "DEL_KAT", "DEL_COUPONS", "DEL_IMPORTS", "DEL_STATISTICS", "DEL_ADMIN_CONTENT", "DEL_INSTALLER"
    );

    public static function resetConfiguration($database) {
        // E-Mail bestätigung aktivieren
        self::database_query($database, "UPDATE `option` SET value=1 WHERE plugin='USER' AND typ='REGCONFIRM'");
        // Hersteller-/Produktdatenbank deaktivieren
        self::database_query($database, "UPDATE `option` SET value=0 WHERE plugin='MARKTPLATZ' AND typ='USE_PRODUCT_DB'");
        // Kostenloses einstellen deaktivieren
        self::database_query($database, "UPDATE `option` SET value=0 WHERE plugin='MARKTPLATZ' AND typ='FREE_ADS'");
        self::database_query($database, "UPDATE `option` SET value=0 WHERE plugin='MARKTPLATZ' AND typ='FREE_JOBS'");
        self::database_query($database, "UPDATE `option` SET value=0 WHERE plugin='MARKTPLATZ' AND typ='FREE_NEWS'");
        // Moderation von Anzeigen/Events/Anbietern/Gesuchen deaktivieren
        self::database_query($database, "UPDATE `option` SET value=0 WHERE plugin='MARKTPLATZ' AND typ='MODERATE_ADS'");
        self::database_query($database, "UPDATE `option` SET value=0 WHERE plugin='MARKTPLATZ' AND typ='MODERATE_EVENTS'");
        self::database_query($database, "UPDATE `option` SET value=0 WHERE plugin='MARKTPLATZ' AND typ='MODERATE_VENDORS'");
        self::database_query($database, "UPDATE `option` SET value=1 WHERE plugin='MARKTPLATZ' AND typ='REQUEST_AUTO_APPROVE'");
        // Warenkorb, Verkauf und Provisionierung aktivieren
        self::database_query($database, "UPDATE `option` SET value=1 WHERE plugin='MARKTPLATZ' AND typ='USE_CART'");
        self::database_query($database, "UPDATE `option` SET value=1 WHERE plugin='MARKTPLATZ' AND typ='BUYING_ENABLED'");
        self::database_query($database, "UPDATE `option` SET value=1 WHERE plugin='MARKTPLATZ' AND typ='USE_PROV'");
        // Einstellungen zum Handeln
        self::database_query($database, "UPDATE `option` SET value=96 WHERE plugin='MARKTPLATZ' AND typ='TRADE_MAX_HOURS'");
        // eBay Zugangsdaten leeren
        self::database_query($database, "UPDATE `option` SET value='' WHERE plugin='SYS' AND typ='EBAY_APP_ID'");
        self::database_query($database, "UPDATE `option` SET value='' WHERE plugin='SYS' AND typ='EBAY_CERT_ID'");
        self::database_query($database, "UPDATE `option` SET value='' WHERE plugin='SYS' AND typ='EBAY_DEV_ID'");
        self::database_query($database, "UPDATE `option` SET value='' WHERE plugin='SYS' AND typ='EBAY_RU_NAME'");
        // Google-Maps API-Key leeren
        self::database_query($database, "UPDATE `option` SET value='' WHERE plugin='SYS' AND typ='MAP_API'");
        // Backtrace für Eventlog deaktivieren
        self::database_query($database, "UPDATE `option` SET value=0 WHERE plugin='SITE' AND typ='ERROR_BACKTRACE'");
        // Debugging optionen deaktivieren
        self::database_query($database, "UPDATE `option` SET value=0 WHERE plugin='CACHE' AND typ='TEMPLATE_AUTO_REFRESH'");
        self::database_query($database, "UPDATE `option` SET value=0 WHERE plugin='SITE' AND typ='TEMPLATE_COMMENTS'");
        self::database_query($database, "UPDATE `option` SET value=0 WHERE plugin='SITE' AND typ='TEMPLATE_DEBUG'");
        // Test E-Mail Adresse entfernen
        self::database_query($database, "UPDATE `option` SET value='' WHERE plugin='EMAIL' AND typ='DELIVERY_ADDRESS'");
        // SMTP Zugangsdaten entfernen
        self::database_query($database, "UPDATE `option` SET value='localhost' WHERE plugin='EMAIL' AND typ='SMTP_HOST'");
        self::database_query($database, "UPDATE `option` SET value='' WHERE plugin='EMAIL' AND typ='SMTP_PASS'");
        self::database_query($database, "UPDATE `option` SET value='25' WHERE plugin='EMAIL' AND typ='SMTP_PORT'");
        self::database_query($database, "UPDATE `option` SET value='' WHERE plugin='EMAIL' AND typ='SMTP_USER'");
        self::database_query($database, "UPDATE `option` SET value='sendmail' WHERE plugin='EMAIL' AND typ='TRANSPORT_TYPE'");
    }

    public static function truncate($options, $database, $leaveDemoData = false) {
        $ar_actions = array();
        $ar_tables_truncate = array();
        // Anzeigen löschen?
        if (in_array("DEL_ADS", $options)) {
            self::truncate_MarketplaceAds($ar_actions, $ar_tables_truncate, $database);
        }
        // Gesuche löschen?
        if (in_array("DEL_REQUESTS", $options)) {
            self::truncate_MarketplaceRequests($ar_actions, $ar_tables_truncate, $database);
        }
        // News löschen?
        if (in_array("DEL_NEWS", $options)) {
            self::truncate_NewsArticles($ar_actions, $ar_tables_truncate, $database, ($leaveDemoData ? 4 : 0));
        }
        // Gruppen löschen?
        if (in_array("DEL_CLUBS", $options)) {
            self::truncate_Clubs($ar_actions, $ar_tables_truncate, $database);
        }
        // Stellenangebote löschen?
        if (in_array("DEL_JOBS", $options)) {
            self::truncate_Jobs($ar_actions, $ar_tables_truncate, $database);
        }
        // Veranstaltungen löschen?
        if (in_array("DEL_EVENTS", $options)) {
            self::truncate_Events($ar_actions, $ar_tables_truncate, $database);
        }
        // Alle Einträge im Anbieterverzeichnis löschen?
        if (in_array("DEL_VENDOR", $options)) {
            self::truncate_Vendors($ar_actions, $ar_tables_truncate, $database);
        }
        // Alle gebuchten Werbebanner löschen?
        if (in_array("DEL_ADVERTISEMENT", $options)) {
            self::truncate_Advertisements($ar_actions, $ar_tables_truncate, $database);
        }
        // Kommentare?
        if (in_array("DEL_COMMENTS", $options)) {
            self::truncate_Comments($ar_actions, $ar_tables_truncate, $database);
        }
        // Konversationen/Nachrichten löschen?
        if (in_array("DEL_CHAT", $options)) {
            self::truncate_Chats($ar_actions, $ar_tables_truncate, $database);
        }
        // Merklisten löschen?
        if (in_array("DEL_WATCHLIST", $options)) {
            self::truncate_Watchlists($ar_actions, $ar_tables_truncate, $database);
        }
        // Alle Rechnungen löschen?
        if (in_array("DEL_INVOICE", $options)) {
            self::truncate_Invoices($ar_actions, $ar_tables_truncate, $database);
        }
        // Alle GeoLocation-/Standort-Einträge löschen?
        if (in_array("DEL_GEOLOCATION", $options)) {
            self::truncate_GeoLocations($ar_actions, $ar_tables_truncate, $database);
        }
        // Alle GeoLocation-/Standort-Einträge löschen?
        if (in_array("DEL_COUPONS", $options)) {
            self::truncate_Coupons($ar_actions, $ar_tables_truncate, $database);
        }
        // Alle Eventlog-Einträge löschen?
        if (in_array("DEL_EVENTLOG", $options)) {
            self::truncate_EventLogs($ar_actions, $ar_tables_truncate, $database);
        }
        // Alle Navigations-Backups löschen?
        if (in_array("DEL_NAVBACKUPS", $options)) {
            self::truncate_NavBackups($ar_actions, $ar_tables_truncate, $database);
        }
        // Alle Hersteller und Produkte löschen?
        if (in_array("DEL_MAN", $options)) {
            self::truncate_Manufacturers($ar_actions, $ar_tables_truncate, $database);
        }
        // Alle Benutzer ausser den Admin löschen?
        if (in_array("DEL_USER", $options)) {
            self::truncate_Users($ar_actions, $ar_tables_truncate, $database);
        }
        // Alle Kategorien und Tabellen löschen?
        if (in_array("DEL_KAT", $options)) {
            self::truncate_MarketplaceCategories($ar_actions, $ar_tables_truncate, $database);
        }
        // Alle Imports löschen?
        if (in_array("DEL_IMPORTS", $options)) {
            self::truncate_MarketplaceImports($ar_actions, $ar_tables_truncate, $database);
        }
        // Alle Statistiken löschen?
        if (in_array("DEL_STATISTICS", $options)) {
            self::truncate_Statistics($ar_actions, $ar_tables_truncate, $database);
        }
        // Admin-Inhalte löschen? (Gebuchte Pakete & Mitgliedschaften)
        if (in_array("DEL_ADMIN_CONTENT", $options)) {
            self::truncate_AdminContent($ar_actions, $ar_tables_truncate, $database);
        }
        // Datenbank für die Erstellung des Installers vorbereiten 
        if (in_array("DEL_INSTALLER", $options)) {
            self::truncate_Installer($ar_actions, $ar_tables_truncate, $database);
        }
        
        /**
         * Alle ausgewählten Tabellen leeren
         */
        for($i=0; $i<count($ar_tables_truncate); $i++)
        {
            self::database_query($database, "TRUNCATE ".$ar_tables_truncate[$i]);
        }
        return $ar_actions;
    }

    protected static function truncate_MarketplaceAds(&$ar_actions, &$ar_tables_truncate, $database) {
        $ar_actions[] = 'DEL_ADS';
        // Folgende Tabellen leeren:
        $ar_tables_articles = self::database_fetch_nar($database, "SELECT T_NAME FROM `table_def`", false, 1);
        $ar_tables_truncate = array_merge($ar_tables_truncate, $ar_tables_articles, array(
            'account',
            'ad2payment_adapter',
            'ad_agent',
            'ad_agent_temp',
            'ad_availability',
            'ad_availability_block',
            'ad_availability_event',
            'ad_images',
            'ad_images_variants',
            'ad_likes',
            'ad_master',
            'ad_log',
            'ad_order',
            'ad_reminder',
            'ad_request',
            'ad_search',
            'ad_search_offer',
            'ad_sold',
            'ad_sold_rating',
            'ad_upload',
            'ad_variant',
            'ad_variant2liste_values',
            'ad_video',
            'ads_stats',
            'article_stats',
            'searchdb_index_de',
            'searchdb_index_en',
            'searchdb_words_de',
            'searchdb_words_en',
            'searchstring',
            'trade',
            'trade_ad',
            'verstoss'
            //'article',
            //'invoice',
            //'p_import',
        ));
        // Kommentare zu Anzeigen löschen
        self::database_query($database, "DELETE FROM `comment` WHERE `TABLE` = 'ad_master'");
        // Uploads zu Anzeigen löschen
        self::database_query($database, "DELETE FROM `media_image` WHERE `TABLE` = 'ad_master'");
        self::database_query($database, "DELETE FROM `media_upload` WHERE `TABLE` = 'ad_master'");
        self::database_query($database, "DELETE FROM `media_video` WHERE `TABLE` = 'ad_master'");
    }

    protected static function truncate_MarketplaceRequests(&$ar_actions, &$ar_tables_truncate, $database) {
        $ar_actions[] = 'DEL_REQUESTS';
        // Folgende Tabellen leeren:
        $ar_tables_truncate = array_merge($ar_tables_truncate, array(
            'ad_request'
        ));
        // Kommentare zu Gesuchen
        self::database_query($database, "DELETE FROM `comment` WHERE `TABLE` = 'ad_request'");
    }

    protected static function truncate_NewsArticles(&$ar_actions, &$ar_tables_truncate, $database, $offset = 0) {
        $ar_actions[] = 'DEL_NEWS';
        // Folgende Tabellen leeren:
        $ar_news = self::database_fetch_nar($database, "SELECT ID_NEWS FROM `news` LIMIT ".(int)$offset.", 18446744073709551615", false, 1);
        if (!empty($ar_news)) {
            self::database_query($database, "DELETE FROM `news` WHERE ID_NEWS IN (".implode(", ", $ar_news).")");
            // String-Tabelle bereinigen
            self::database_query($database, "DELETE FROM `string_c` WHERE `S_TABLE` = 'news' AND FK IN (".implode(", ", $ar_news).")");
            // Kommentare zu Gesuchen
            self::database_query($database, "DELETE FROM `comment` WHERE `TABLE` = 'news' AND FK IN (".implode(", ", $ar_news).")");
        }
        $ar_tables_truncate = array_merge($ar_tables_truncate, array(
            'news2key',
            'newsview',
            'news_key'
        ));
    }

    protected static function truncate_Clubs(&$ar_actions, &$ar_tables_truncate, $database) {
        $ar_actions[] = 'DEL_CLUBS';
        // Folgende Tabellen leeren:
        $ar_tables_truncate = array_merge($ar_tables_truncate, array(
            'club',
            'club2user',
            'club_category',
            'club_gallery',
            'club_gallery_video',
            'club_invite',
            'club_member_request',
            'club_shop',
            'string_club',
            'club_discussion',
            'club_discussion_comment'
        ));
        // Kommentare zu Gesuchen
        self::database_query($database, "DELETE FROM `calendar_event` WHERE `FK_REF_TYPE` = 'club'");
        self::database_query($database, "DELETE FROM `comment` WHERE `TABLE` = 'club'");
    }

    protected static function truncate_Jobs(&$ar_actions, &$ar_tables_truncate, $database) {
        $ar_actions[] = 'DEL_JOBS';
        // Folgende Tabellen leeren:
        $ar_tables_truncate = array_merge($ar_tables_truncate, array(
            'job',
            'job2key',
            'job_key',
            'string_job'
        ));
        // Kommentare zu Veranstaltungen
        self::database_query($database, "DELETE FROM `comment` WHERE `TABLE` = 'job'");
    }

    protected static function truncate_Events(&$ar_actions, &$ar_tables_truncate, $database) {
        $ar_actions[] = 'DEL_EVENTS';
        // Folgende Tabellen leeren:
        $ar_tables_truncate = array_merge($ar_tables_truncate, array(
            'calendar_event',
            'calendar_event_gallery',
            'calendar_event_gallery_video',
            'calendar_event_signup'
        ));
        // Kommentare zu Veranstaltungen
        self::database_query($database, "DELETE FROM `comment` WHERE `TABLE` = 'calendar_event'");
        // Uploads zu Veranstaltungen löschen
        self::database_query($database, "DELETE FROM `media_image` WHERE `TABLE` = 'calendar_event'");
        self::database_query($database, "DELETE FROM `media_upload` WHERE `TABLE` = 'calendar_event'");
        self::database_query($database, "DELETE FROM `media_video` WHERE `TABLE` = 'calendar_event'");
    }

    protected static function truncate_Vendors(&$ar_actions, &$ar_tables_truncate, $database) {
        $ar_actions[] = 'DEL_VENDOR';
        // Folgende Tabellen leeren:
        $ar_tables_truncate = array_merge($ar_tables_truncate, array(
            'vendor',
            'vendor_category',
            'vendor_gallery',
            'vendor_gallery_video',
            'vendor_place',
            'vendor_homepage',
            'string_vendor',
            'string_vendor_place'
        ));
        // Uploads zu Anbietern (Anbieter-Homepage) löschen
        self::database_query($database, "DELETE FROM `media_image` WHERE `TABLE` = 'vendor_homepage'");
        self::database_query($database, "DELETE FROM `media_upload` WHERE `TABLE` = 'vendor_homepage'");
        self::database_query($database, "DELETE FROM `media_video` WHERE `TABLE` = 'vendor_homepage'");
    }

    protected static function truncate_Advertisements(&$ar_actions, &$ar_tables_truncate, $database) {
        $ar_actions[] = 'DEL_ADVERTISEMENT';
        // Folgende Tabellen leeren:
        $ar_tables_truncate = array_merge($ar_tables_truncate, array(
            'advertisement_kat',
            'advertisement_kat_price',
            'advertisement_stat',
            'advertisement_user',
            'advertisement_view'
        ));
    }

    protected static function truncate_Comments(&$ar_actions, &$ar_tables_truncate, $database) {
        $ar_actions[] = 'DEL_COMMENTS';
        // Folgende Tabellen leeren:
        $ar_tables_truncate = array_merge($ar_tables_truncate, array(
            'comment',
            'comment_ipcheck',
            'comment_stats'
        ));
    }

    protected static function truncate_Chats(&$ar_actions, &$ar_tables_truncate, $database) {
        $ar_actions[] = 'DEL_CHAT';
        // Folgende Tabellen leeren:
        $ar_tables_truncate = array_merge($ar_tables_truncate, array(
            'chat',
            'chat_message',
            'chat_user',
            'chat_user_read_message',
            'chat_user_virtual'
        ));
        exec("rm ".$GLOBALS["ab_path"]."filestorage/invoice/*");
    }

    protected static function truncate_Watchlists(&$ar_actions, &$ar_tables_truncate, $database) {
        $ar_actions[] = 'DEL_WATCHLIST';
        // Folgende Tabellen leeren:
        $ar_tables_truncate = array_merge($ar_tables_truncate, array(
            'watchlist',
            'watchlist_user'
        ));
    }

    protected static function truncate_Invoices(&$ar_actions, &$ar_tables_truncate, $database) {
        $ar_actions[] = 'DEL_INVOICE';
        // Folgende Tabellen leeren:
        $ar_tables_truncate = array_merge($ar_tables_truncate, array(
            'ad_invoice',
            'billing_billableitem',
            'billing_creditnote',
            'billing_invoice',
            'billing_invoice_item',
            'billing_invoice_export',
            'billing_invoice_transaction',
            'billing_sales',
            'packet_order_billableitem',
            'packet_order_invoice',
            'packet_order_usage',
            'user_account_pincode_export'
        ));
        
    }

    protected static function truncate_GeoLocations(&$ar_actions, &$ar_tables_truncate, $database) {
        $ar_actions[] = 'DEL_GEOLOCATION';
        // Folgende Tabellen leeren:
        $ar_tables_truncate = array_merge($ar_tables_truncate, array(
            'geolocation',
            'geo_blocks',
            'geo_location',
            'geo_region',
            'geo_status'
        ));
    }

    protected static function truncate_EventLogs(&$ar_actions, &$ar_tables_truncate, $database) {
        $ar_actions[] = 'DEL_EVENTLOG';
        // Folgende Tabellen leeren:
        $ar_tables_truncate = array_merge($ar_tables_truncate, array(
            'eventlog',
            'worklist'
        ));
    }

    protected static function truncate_NavBackups(&$ar_actions, &$ar_tables_truncate, $database) {
        $ar_actions[] = 'DEL_NAVBACKUPS';
        // Folgende Tabellen leeren:
        $ar_tables_truncate = array_merge($ar_tables_truncate, array(
            'nav_backup'
        ));
    }

    protected static function truncate_Manufacturers(&$ar_actions, &$ar_tables_truncate, $database) {
        $ar_actions[] = 'DEL_MAN';
        // Folgende Tabellen leeren:
        $ar_tables_truncate = array_merge($ar_tables_truncate, array(
            'manufacturers',
            'man_group',
            'man_group_category',
            'man_group_mapping',
            'product',
            'string_product',
            'string_man_group'
        ));
        // Article-Table based
        $res = self::database_query($database, "
            SELECT
                ID_TABLE_DEF,
                T_NAME
            FROM
                table_def");
        while($row = mysql_fetch_assoc($res['rsrc']))
        {
            self::database_query($database, "TRUNCATE TABLE hdb_table_".$row['T_NAME']);
        }
    }

    protected static function truncate_Users(&$ar_actions, &$ar_tables_truncate, $database) {
        $ar_actions[] = 'DEL_USER';
        self::database_query($database, "DELETE FROM role2user WHERE FK_USER <> 1");
        self::database_query($database, "DELETE FROM user2img WHERE FK_USER <> 1");
        self::database_query($database, "DELETE FROM user2setting WHERE FK_USER <> 1");
        self::database_query($database, "DELETE FROM user2payment_adapter WHERE FK_USER <> 1");
        self::database_query($database, "DELETE FROM usercontent WHERE FK_USER <> 1");
        self::database_query($database, "DELETE FROM usersettings WHERE FK_USER <> 1");
        self::database_query($database, "DELETE FROM user_shop WHERE FK_USER <> 1");
        self::database_query($database, "DELETE FROM user_views WHERE FK_USER <> 1");
        self::database_query($database, "DELETE FROM user WHERE ID_USER <> 1");
        self::database_query($database, "DELETE FROM packet_order WHERE FK_USER <> 1");
        self::database_query($database, "DELETE FROM `packet_order_billableitem`
                        WHERE FK_PACKET_ORDER NOT IN (SELECT ID_PACKET_ORDER FROM `packet_order`)");
        self::database_query($database, "DELETE FROM `packet_order_invoice`
                        WHERE FK_PACKET_ORDER NOT IN (SELECT ID_PACKET_ORDER FROM `packet_order`)");
        self::database_query($database, "DELETE FROM `packet_order_usage`
                        WHERE ID_PACKET_ORDER NOT IN (SELECT ID_PACKET_ORDER FROM `packet_order`)");
        self::database_query($database, "DELETE FROM packet_membership_upgrade WHERE FK_USER <> 1");
        self::database_query($database, "DELETE FROM watchlist WHERE FK_USER <> 1");
        self::database_query($database, "DELETE FROM watchlist_user WHERE FK_USER <> 1");
        self::database_query($database, "DELETE FROM perm2user WHERE FK_USER <> 1");
        // Folgende Tabellen leeren:
        $ar_tables_truncate = array_merge($ar_tables_truncate, array(
            'mail',
            'my_msg',
            'my_msg_body',
            'nl_recp',
            'useronline',
            'user_contact',
            'user_shop',
            'user_views',
            'sales_code'
        ));
    }

    protected static function truncate_MarketplaceCategories(&$ar_actions, &$ar_tables_truncate, $database) {
        $ar_actions[] = 'DEL_KAT';
        $ar_lists = array(0);
        // Alle Master-Felder holen (?)
        $res = self::database_query($database, "SELECT FK_LISTE FROM field_def WHERE FK_TABLE_DEF=1 AND FK_LISTE>0");
        while($row = mysql_fetch_assoc($res['rsrc'])) {
            $ar_lists[] = $row['FK_LISTE'];
        }
        if (!empty($ar_lists)) {
            $ar_lists_values = array();
            $res = self::database_query($database, "SELECT ID_LISTE_VALUES FROM liste_values
				WHERE FK_LISTE NOT IN (".implode(",", $ar_lists).")");
            while($row = mysql_fetch_assoc($res['rsrc'])) {
                $ar_lists_values[] = $row['ID_LISTE_VALUES'];
            }
            if (!empty($ar_lists_values)) {
                // Alle Felder löschen die keine Master-Felder sind (?)
                self::database_query($database, "
					DELETE FROM
						string_liste_values
					WHERE
						FK IN(".implode(",", $ar_lists_values).")");
            }
            self::database_query($database, "
				DELETE FROM
					liste_values
				WHERE
					FK_LISTE NOT IN(".implode(",", $ar_lists).")");
            self::database_query($database, "DELETE FROM
				liste
			WHERE
				ID_LISTE NOT IN(".implode(",", $ar_lists).")");
        }
        // Liste aller Marktplatz-Kategorien erstellen (ROOT 1)
        $ar_kats = self::database_fetch_nar($database, "SELECT ID_KAT FROM `kat` WHERE ROOT=1 AND ID_KAT<>1", false, 1);
        // Passend für die "IN" abfrage formatieren
        $id_kat_list = "(".implode(",", $ar_kats).")";
        // Alle Marktplatz-Kategorien löschen
        self::database_query($database, "DELETE FROM kat WHERE ID_KAT IN ".$id_kat_list);
        self::database_query($database, "DELETE FROM string_kat WHERE FK NOT IN (SELECT ID_KAT FROM `kat`)");

        ### Tables and FIELDS
        $id_table_nodel = self::database_fetch_nar($database, "
			SELECT
				ID_TABLE_DEF
			FROM
				table_def
			WHERE
				T_NAME IN ('artikel_master', 'vendor_master')", 0, 1);
        if($id_table_nodel)
        {
            $ar_fiedl_def = array();
            $res = self::database_query($database, "
				SELECT
					ID_FIELD_DEF
				FROM
					field_def
				WHERE
					FK_TABLE_DEF NOT IN (".implode(", ", $id_table_nodel).")");
            while($row = mysql_fetch_assoc($res['rsrc']))
            {
                $ar_fiedl_def[] = $row['ID_FIELD_DEF'];
            }
            if(count($ar_fiedl_def))
            {
                self::database_query($database, "
					DELETE FROM
						field2group
					WHERE FK_FIELD_DEF IN(".implode(",", $ar_fiedl_def).")");
                self::database_query($database, "
					DELETE FROM
						field_def
					WHERE ID_FIELD_DEF IN (".implode(",", $ar_fiedl_def).")");
                self::database_query($database, "
					DELETE FROM
						string_field_def
					WHERE FK  NOT IN (SELECT ID_FIELD_DEF FROM `field_def`)");
            }
            $ar_tables = array();
            $ar_table_names = array();
            $res = self::database_query($database, $q="
				SELECT
					ID_TABLE_DEF,
					T_NAME
				FROM
					table_def
				WHERE ID_TABLE_DEF NOT IN (".implode(", ", $id_table_nodel).")");
            while($row = mysql_fetch_assoc($res['rsrc']))
            {
                $ar_tables[] = $row['ID_TABLE_DEF'];
                $ar_table_names[] = $row['T_NAME'];
            }
            // Table fields and definitions
            if(!empty($ar_tables))
            {
                self::database_query($database, "
					DELETE FROM
						field_group
					WHERE FK_TABLE IN(".implode(",", $ar_tables).")");
                self::database_query($database, "
					DELETE FROM
						table_def
					WHERE ID_TABLE_DEF IN(".implode(",", $ar_tables).")");
                for($i=0; $i<count($ar_table_names); $i++)
                {
                    self::database_query($database, "DROP TABLE ".$ar_table_names[$i]);
                    self::database_query($database, "DROP TABLE hdb_table_".$ar_table_names[$i]);
                }
            }
            self::database_query($database, "
					DELETE FROM
						string_app
					WHERE S_TABLE='table_def' AND FK NOT IN (SELECT ID_TABLE_DEF FROM `table_def`)");
        }
        // Artikel-Tabellen entfernen (falls noch vorhanden)
        $arArticleTables = self::database_fetch_nar($database, "SHOW TABLES LIKE 'artikel_%'", false, 1);
        foreach ($arArticleTables as $tableIndex => $tableName) {
            if ($tableName == "artikel_master") {
                continue;
            }
            self::database_query($database, "DROP TABLE ".$tableName);
            self::database_query($database, "DROP TABLE hdb_table_".$tableName);
        }
        // Feld-Einstellungen entfernen
        self::database_query($database, "DELETE FROM kat2field WHERE FK_KAT>1");
        // Folgende Tabellen leeren:
        $ar_tables_truncate = array_merge($ar_tables_truncate, array(
            'kat_copy',
            'kat_restore',
            'kat_undo',
            'role2kat'
        ));
    }

    protected static function truncate_MarketplaceImports(&$ar_actions, &$ar_tables_truncate, $database) {
        $ar_actions[] = 'DEL_IMPORTS';
        // Folgende Tabellen leeren:
        $ar_tables_truncate = array_merge($ar_tables_truncate, array(
            'import_file',
            'import_filter',
            'import_preset',
            'import_process',
            'import_settings',
            'import_source'
        ));
    }

    protected static function truncate_Coupons(&$ar_actions, &$ar_tables_truncate, $database) {
        $ar_actions[] = 'DEL_COUPONS';
        // Folgende Tabellen leeren:
        $ar_tables_truncate = array_merge($ar_tables_truncate, array(
            'coupon',
            'coupon_code',
            'coupon_code_usage',
            'coupon_restriction'
        ));
    }

    protected static function truncate_Statistics(&$ar_actions, &$ar_tables_truncate, $database) {
        $ar_actions[] = 'DEL_STATISTICS';        
        // Folgende Tabellen leeren:
        $ar_tables_truncate = array_merge($ar_tables_truncate, array(
          'ads_stats',
          'article_stats',
          'newsview',
          'user_views',
          'log_views_clicks',
          'log_views_clicks_deduce_result_per_day'
        ));
    }

    protected static function truncate_AdminContent(&$ar_actions, &$ar_tables_truncate, $database) {
        $ar_actions[] = 'DEL_ADMIN_CONTENT';       
        self::database_query($database, "DELETE FROM billing_billableitem WHERE FK_USER=1");
        self::database_query($database, "DELETE FROM billing_invoice WHERE FK_USER=1");
        self::database_query($database, "DELETE FROM billing_invoice_item WHERE FK_BILLING_INVOICE NOT IN (SELECT ID_BILLING_INVOICE FROM billing_invoice)"); 
        self::database_query($database, "DELETE FROM billing_invoice_transaction WHERE FK_BILLING_INVOICE NOT IN (SELECT ID_BILLING_INVOICE FROM billing_invoice)"); 
        self::database_query($database, "DELETE FROM packet_order WHERE FK_USER=1 AND STAMP_START>'2017-07-13'");
        self::database_query($database, "DELETE FROM packet_order_billableitem WHERE FK_PACKET_ORDER NOT IN (SELECT ID_PACKET_ORDER FROM packet_order)");
        self::database_query($database, "DELETE FROM packet_order_invoice WHERE FK_PACKET_ORDER NOT IN (SELECT ID_PACKET_ORDER FROM packet_order)");
        self::database_query($database, "DELETE FROM packet_order_usage WHERE FK_PACKET_ORDER NOT IN (SELECT ID_PACKET_ORDER FROM packet_order)");
        self::database_query($database, "DELETE FROM packet_membership_upgrade WHERE FK_USER=1"); 
    }
    
    protected static function truncate_Installer(&$ar_actions, &$ar_tables_truncate, $database) {
        $ar_actions[] = 'DEL_ADMIN_CONTENT';
        self::database_query($database, "UPDATE `infoseite` SET BF_LANG_INFO=128");
        self::database_query($database, "DELETE FROM `string_info` WHERE BF_LANG=64");
        self::database_query($database, "
            INSERT INTO `kat2field` (`FK_KAT`, `FK_FIELD`, `B_ENABLED`, `B_NEEDED`, `B_SEARCHFIELD`) VALUES (1, 22, 1, 1, 1);
            INSERT INTO `kat2field` (`FK_KAT`, `FK_FIELD`, `B_ENABLED`, `B_NEEDED`, `B_SEARCHFIELD`) VALUES (1, 334, 1, 1, 1);
            INSERT INTO `kat2field` (`FK_KAT`, `FK_FIELD`, `B_ENABLED`, `B_NEEDED`, `B_SEARCHFIELD`) VALUES (1, 478, 1, 1, 1);
            INSERT INTO `kat2field` (`FK_KAT`, `FK_FIELD`, `B_ENABLED`, `B_NEEDED`, `B_SEARCHFIELD`) VALUES (1, 521, 1, 0, 1);
            INSERT INTO `kat2field` (`FK_KAT`, `FK_FIELD`, `B_ENABLED`, `B_NEEDED`, `B_SEARCHFIELD`) VALUES (1, 1595, 1, 0, 1);");
    }

    protected static function database_fetch_nar($database, $query, $n_keycol=1, $n_vcol=2) {
        if (class_exists("ebiz_db") && ($database instanceof ebiz_db)) {
            return $database->fetch_nar($query, $n_keycol, $n_vcol);
        } else {
            $res = mysql_query($query, $database);
            $ret = array();
            if (!$res) {
                die('database_fetch_nar failed! Query:'.$query);
            } else {
                if ($n_keycol) {
                    while ($row = mysql_fetch_row($res)) {
                        $ret[$row[$n_keycol-1]] = $row[$n_vcol-1];
                    }
                } else {
                    while ($row = mysql_fetch_row($res)) {
                        $ret[] = $row[$n_vcol-1];
                    }
                }
            }
            return $ret;
        }
    }

    protected static function database_fetch_atom($database, $query, $n_col = 1) {
        if (class_exists("ebiz_db") && ($database instanceof ebiz_db)) {
            return $database->fetch_atom($query);
        } else {
            $res = mysql_query($query, $database);
            if (!$res) {
                die('database_fetch_atom failed! Query:'.$query);
            }
            $row = mysql_fetch_row($res);
            return $row[$n_col-1];
        }
    }


    protected static function database_query($database, $query) {
        if (class_exists("ebiz_db") && ($database instanceof ebiz_db)) {
            return $database->querynow($query);
        } else {
            return array(
                "rsrc" => mysql_query($query, $database)
            );
        }
    }

}