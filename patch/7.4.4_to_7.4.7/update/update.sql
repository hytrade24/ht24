ALTER TABLE `option`
	CHANGE COLUMN `beschreibung` `beschreibung` TEXT NULL DEFAULT NULL AFTER `default_value`;
	
CREATE TABLE `kat_statistic` (
	`ID_KAT` INT(11) NOT NULL,
	`STAMP` DATETIME NOT NULL,
	`ADCOUNT` INT(11) NULL DEFAULT '0',
	PRIMARY KEY (`ID_KAT`)
)
COLLATE='utf8_general_ci'
ENGINE=MEMORY;

INSERT INTO `crontab` (`EINMALIG`, `FEHLER`, `SYSNAME`, `ERLEDIGT`, `PRIO`, `DSC`, `FIRST`, `LAST`, `EINHEIT`, `ALL_X`, `DATEI`, `FUNKTION`, `FK`, `CODE`) 
	VALUES (NULL, 0, NULL, NULL, 1, 'Aktualisierung der Kategorie-Statistiken (Artikel-Anzahl etc.)', '2017-06-02 00:00:00', '2017-06-02 15:54:02', 'minute', 15, 'cron/kat_stats.php', '', NULL, NULL);

ALTER TABLE `ads`
	ADD COLUMN `DATE_START` DATE NOT NULL DEFAULT '0000-00-00' AFTER `STAMP`,
	ADD COLUMN `DATE_END` DATE NOT NULL DEFAULT '0000-00-00' AFTER `DATE_START`,
	DROP INDEX `aktiv`,
	ADD INDEX `aktiv` (`aktiv`, `DATE_START`, `DATE_END`);

update ads set DATE_START = '2017-01-01', DATE_END='2022-01-01';

UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nWith AutoRefresh, changes to templates are automatically detected and corresponding cache files are updated. This should be dispensed with in the product application")
	WHERE `plugin`="CACHE" AND `typ`="TEMPLATE_AUTO_REFRESH";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nLifetime of the caches for the category tree in minutes")
	WHERE `plugin`="CACHE" AND `typ`="LIFETIME_CATEGORY";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nValidity (in minutes) of the cache for new ads on the home page")
	WHERE `plugin`="CACHE" AND `typ`="LIFETIME_INDEX_ADS";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nValidity (in minutes) of the top vendor cache on the home page")
	WHERE `plugin`="CACHE" AND `typ`="LIFETIME_INDEX_VENDOR";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nPeriod of validity of the cache for the categories of a group")
	WHERE `plugin`="CACHE" AND `typ`="LIFETIME_CLUB_CATEGORIES";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nPeriod of validity of the cache for the categories of a user / provider")
	WHERE `plugin`="CACHE" AND `typ`="LIFETIME_USER_CATEGORIES";
	
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nMethod for e-mailing. Possible values: sendmail")
	WHERE `plugin`="EMAIL" AND `typ`="TRANSPORT_TYPE";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nPath to sendmail (for transport type sendmail)")
	WHERE `plugin`="EMAIL" AND `typ`="SENDMAIL_PATH";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nDo you want to send e-mails in HTML format?")
	WHERE `plugin`="EMAIL" AND `typ`="USE_HTML";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nFor test mode. All e-mails are sent exclusively to the specified address")
	WHERE `plugin`="EMAIL" AND `typ`="DELIVERY_ADDRESS";
	
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nPath to the pictures of the galleries")
	WHERE `plugin`="GALERIE" AND `typ`="IMAGEPATH";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nMaximum size for the gallery pictures")
	WHERE `plugin`="GALERIE" AND `typ`="MAXIMGSIZE";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nMaximum width / height for preview images")
	WHERE `plugin`="GALERIE" AND `typ`="MAXTHUMBSIZE";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nBackground color for thumbnails in RGB separated by dots")
	WHERE `plugin`="GALERIE" AND `typ`="THUMBBG";
	
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nRuntime for jobs in days")
	WHERE `plugin`="jobs" AND `typ`="runtime";
	
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nWould you like to use the shopping cart function?")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="USE_CART";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nEnables or disables the free setting of ads.")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="FREE_ADS";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nSets the number of free images for an ad.")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="FREE_IMAGES";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nUploadable uploads in one ad")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="FREE_UPLOADS";
	
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nAllowed file types (comma-separated)")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="UPLOAD_TYPES";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nEnables or disables the ability to buy / sell items")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="BUYING_ENABLED";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nUse manufacturer and product database.")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="USE_PRODUCT_DB";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nActivate sales commission")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="USE_PROV";
	
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nAllow HTML (Design) in ads")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="ALLOW_HTML";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nNew ads on the home page")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="INDEX_NEWADS";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nNew top ads on the homepage")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="INDEX_TOPADS";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nNew users on the home page")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="INDEX_NEW_USERS";	
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nMaximum number of offers for a trade")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="TRADE_BID_COUNT";	
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nMaximum number of own price proposals for a trade process.")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="TRADE_BID_USER_COUNT";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nValidity of an offer in hours")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="TRADE_MAX_HOURS";
	
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nNumber of entries in the shop directory")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="INDEX_SHOPVIEWS";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nNext reminder x days after the previous send.")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="INVOICE_DAYS_REPEAT";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nPayment term x days after invoicing. (This is followed by the first reminder)")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="INVOICE_DAYS_REMIND";	
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nCharacter / Abbreviation of default currency")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="CURRENCY";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nAutomatic confirmation of messages")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="CHAT_AUTO_APPROVE";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nAutomatic confirmation of requests")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="REQUEST_AUTO_APPROVE";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nDuration in days that a request is active.")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="REQUEST_RUNTIME_DAYS";
	
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nID of the standard payment adapter")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="INVOICE_STD_PAYMENT_ADAPTER";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nDays of the month on which automatic billing is to be executed")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="INVOICE_DAYS_AUTOMATIC_BILLING";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nDays after the date on which the first reminder is sent")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="INVOICE_DAYS_DUNNING_LEVEL_1";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nDays after the due date, at which the second reminder is sent")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="INVOICE_DAYS_DUNNING_LEVEL_2";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nDays after the date on which the third reminder is sent")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="INVOICE_DAYS_DUNNING_LEVEL_3";
	
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nEnables or disables the free setting of ads.")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="FREE_JOBS";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nEnables or disables the free setting of ads.")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="FREE_NEWS";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nFree usable videos in one ad")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="FREE_VIDEOS";
	
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nRestrict visually visible ads (e.g., B2B)")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="AD_CONSTRAINTS";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nNumber of entries in club member list")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="CLUB_MEMBERVIEWS";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nDefault view in article listing (BOX or LIST)")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="LISTING_STD_VIEW";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nNumber of items to be displayed below an item")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="INDEX_CROSSSEL";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nWhich tax rate should be used by default?")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="TAX_DEFAULT";
	
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nAllow comments on ads")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="ALLOW_COMMENTS_AD";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nAllow comments to vendors")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="ALLOW_COMMENTS_VENDOR";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nAllow comments on clubs")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="ALLOW_COMMENTS_CLUB";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nEnable advanced settings for top ads.")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="EXTENDED_TOP_ADS";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nAllow comments on events")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="ALLOW_COMMENTS_EVENT";
	
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nIf this option is enabled, the Google Map will only appear in the search. If you want the map to be displayed when searching and listing, disable this option.")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="SHOW_MAP_SEARCH_EVENT";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nIf this option is enabled, the Google Map will only appear in the search. If you want the map to be displayed when searching and listing, disable this option.")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="SHOW_MAP_SEARCH_VENDOR";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nMap at the suppliers")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="SHOW_MAP_VENDOR";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nMap at events")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="SHOW_MAP_EVENT";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nView the map in general")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="SHOW_MAP_ALL";
	
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nAllowed HTML tags within the group description.")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="HTML_ALLOWED_TAGS_GROUP";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nAllowed HTML tags within the vendor description.")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="HTML_ALLOWED_TAGS_VENDOR";
	
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nEnable this option if you do not want to charge certain foreign users for VAT")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="INVOICE_TAX_EXEMPT_ENABLE";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nID of the tax rate to be applied (i.d.R. 0%)")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="INVOICE_TAX_EXEMPT_TAX_ID";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nAdapter for checking foreign VAT IDs")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="INVOICE_TAX_EXEMPT_ADAPTER";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nYour UST-ID")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="INVOICE_TAX_EXEMPT_USTID";
	
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nNumber of days that a user is informed before an ad expires")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="ADS_DAYS_REMIND";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nAllows unregistered users to buy items")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="BUYING_UNREGISTERED";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nShow conversion to other currency")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="CURRENCY_CONVERSION";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nUse location in ads")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="USE_ARTICLE_LOCATION";
	
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nYou should first check the displays")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="MODERATE_ADS";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nShould events be checked first?")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="MODERATE_EVENTS";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nAre vendors to be checked first?")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="MODERATE_VENDORS";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nDuration in days the display is displayed as \"New\"")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="DAYS_ADS_NEW";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nDo you want to save bills automatically as a PDF file?")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="INVOICE_SAVE_PDF";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nShould invoices be automatically sent to the recipient by e-mail?")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="INVOICE_MAIL_PDF";
	
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nActivate the basic price")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="USE_ARTICLE_BASEPRICE";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nEAN Enable number searches")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="USE_ARTICLE_EAN";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nBasic groups (general, groups, nogroup, location) displayed in the search mask")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="SEARCH_BASE_GROUPS";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nWould you like to activate coupons on the portal?")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="COUPON_ENABLED";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nShould it be possible to offer ads for rent?")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="ENABLE_RENT";
	
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nAllows anonymous users to comment on comments.")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="ANONYMOUS_RATINGS";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nAllow comments with rating")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="ALLOW_COMMENTS_RATED";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nHide contact details for sales as long as the sale has not been confirmed")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="HIDE_CONTACT_INFO";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nContact details of the sender for messages")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="CHAT_SHOW_CONTACT";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nHow long should be waited with the debiting")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="SEPADAY";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nHow long should wait to send e-mail for pincode")
	WHERE `plugin`="MARKTPLATZ" AND `typ`="PINCODE_EMAIL";

UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nURL to FACEBOOK including HTTP")
	WHERE `plugin`="NETWORKS" AND `typ`="FACEBOOK";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nActivate Twitter")
	WHERE `plugin`="NETWORKS" AND `typ`="TWITTER_AKTIV";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nTwitter Member key")
	WHERE `plugin`="NETWORKS" AND `typ`="TWITTER_CONSUMER_KEY";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nSecret to the Twitter key")
	WHERE `plugin`="NETWORKS" AND `typ`="TWITTER_CONSUMER_SECRET";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nTwitter Token")
	WHERE `plugin`="NETWORKS" AND `typ`="TWITTER_USER_TOKEN";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nTwitter Token Secret")
	WHERE `plugin`="NETWORKS" AND `typ`="TWITTER_USER_SECRET";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nBit.ly Service enabled")
	WHERE `plugin`="NETWORKS" AND `typ`="TWITTER_USE_BITLY";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nBit.ly Login")
	WHERE `plugin`="NETWORKS" AND `typ`="TWITTER_BITLY_LOGIN";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nBit.ly Secret")
	WHERE `plugin`="NETWORKS" AND `typ`="TWITTER_BITLY_SECRET";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nApplication number of your Facebook app")
	WHERE `plugin`="NETWORKS" AND `typ`="FACEBOOK_APP_ID";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nApplication secret code of your Facebook app")
	WHERE `plugin`="NETWORKS" AND `typ`="FACEBOOK_APP_SECRET";

UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nEmails sent at once")
	WHERE `plugin`="NEWSLETTER" AND `typ`="mailperrun";
	
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nActivate the affiliate plugin")
	WHERE `plugin`="PLUGIN" AND `typ`="AFFILIATE";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nLicense key of the affiliate plugin")
	WHERE `plugin`="PLUGIN" AND `typ`="AFFILIATE_KEY";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nActivate the Collmex plugin")
	WHERE `plugin`="PLUGIN" AND `typ`="COLLMEX";
	
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nEnable RSS Yes / No")
	WHERE `plugin`="RSS" AND `typ`="RSS_AKTIV";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nThe title of your RSS feed")
	WHERE `plugin`="RSS" AND `typ`="RSS_TITEL";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nThe description of your RSS feed")
	WHERE `plugin`="RSS" AND `typ`="RSS_DSC";
	
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nPath to user settings images etc")
	WHERE `plugin`="SITE" AND `typ`="USER_PATH";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nPrint used templates as an HTML comment?")
	WHERE `plugin`="SITE" AND `typ`="TEMPLATE_COMMENTS";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nAssignment page -> content_xxx caching?")
	WHERE `plugin`="SITE" AND `typ`="cache_subtpl";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nTime span after which a table lock expires.")
	WHERE `plugin`="SITE" AND `typ`="lock_expire";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nPath for uploads from the media database")
	WHERE `plugin`="SITE" AND `typ`="MEDIAPATH";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nUse Mod_rewrite?")
	WHERE `plugin`="SITE" AND `typ`="MOD_REWRITE";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nAfter which time does a newsletter order have to be confirmed?")
	WHERE `plugin`="SITE" AND `typ`="NL_CONFIRM_TIMEOUT";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nUpload path")
	WHERE `plugin`="SITE" AND `typ`="PATH_UPLOADS";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nThis is the side name")
	WHERE `plugin`="SITE" AND `typ`="SITENAME";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nURL to website")
	WHERE `plugin`="SITE" AND `typ`="SITEURL";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nHow is www to be implemented (langselect)")
	WHERE `plugin`="SITE" AND `typ`="std_country";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nForum-Integration for vBulletin")
	WHERE `plugin`="SITE" AND `typ`="FORUM_VB";
	
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nAddress of the website incl. protocol e.g. \"http://www.example.com\"")
	WHERE `plugin`="SITE" AND `typ`="BASE_URL";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nTemplate to be used")
	WHERE `plugin`="SITE" AND `typ`="TEMPLATE";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nUsed templates as debug output")
	WHERE `plugin`="SITE" AND `typ`="TEMPLATE_DEBUG";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nIf this option is enabled, users must confirm received comments before they become visible.")
	WHERE `plugin`="SITE" AND `typ`="COMMENT_CONFIRM";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nUse SSL encryption")
	WHERE `plugin`="SITE" AND `typ`="USE_SSL";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nUse SSL encryption for all pages")
	WHERE `plugin`="SITE" AND `typ`="USE_SSL_GLOBAL";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nOutput the translation codes in the template instead of the translation.")
	WHERE `plugin`="SITE" AND `typ`="TEMPLATE_TRANSLATION_DEBUG";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nTranslation tool to translate the ebiz-trader")
	WHERE `plugin`="SITE" AND `typ`="TEMPLATE_TRANSLATION_TOOL";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nDomain used for Cookies (Login, Shopping Cart etc.). For example: example.com, marktplatz.example.com")
	WHERE `plugin`="SITE" AND `typ`="COOKIE_DOMAIN";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nEnable social media login")
	WHERE `plugin`="SITE" AND `typ`="SOCIALMEDIA_LOGIN";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nBacktrace for errors with logging.")
	WHERE `plugin`="SITE" AND `typ`="ERROR_BACKTRACE";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nDo you want to display a warning for the use of cookies?")
	WHERE `plugin`="SITE" AND `typ`="COOKIE_WARNING";
	
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nThis email address is used to contact the customer")
	WHERE `plugin`="SUPPORT" AND `typ`="COOKIE_WARNING";

UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nPath to convert from ImageMagick")
	WHERE `plugin`="SYS" AND `typ`="PATH_CONVERT";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nMax number of pixels for pictures (upload)")
	WHERE `plugin`="SYS" AND `typ`="MAXPIXEL";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nIs the region to be read for addresses? (E.g., country> state> county> ...)")
	WHERE `plugin`="SYS" AND `typ`="MAP_REGIONS";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nDev-Id of the eBay-Application for the marketplace")
	WHERE `plugin`="SYS" AND `typ`="EBAY_DEV_ID";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nApp-Id of the eBay-Application for the marketplace")
	WHERE `plugin`="SYS" AND `typ`="EBAY_APP_ID";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nCert-Id of the eBay-Application for the marketplace")
	WHERE `plugin`="SYS" AND `typ`="EBAY_CERT_ID";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nDetermines if the eBay import should run in sandbox mode")
	WHERE `plugin`="SYS" AND `typ`="EBAY_SANDBOX";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nRu-Name of the eBay-Application for the marketplace")
	WHERE `plugin`="SYS" AND `typ`="EBAY_RU_NAME";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nGoogle API-Key")
	WHERE `plugin`="SYS" AND `typ`="MAP_API";
	
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nMaximum height for user logos")
	WHERE `plugin`="USER" AND `typ`="MAXLOGOHEIGHT";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nMaximum width for user logos")
	WHERE `plugin`="USER" AND `typ`="MAXLOGOWIDTH";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nDo new users have to be activated by the admin?")
	WHERE `plugin`="USER" AND `typ`="REGCHECK";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nDo new users need to activate your account via email?")
	WHERE `plugin`="USER" AND `typ`="REGCONFIRM";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nTo send an e-mail to Admin at registrations?")
	WHERE `plugin`="USER" AND `typ`="SEND_REGADMINMAIL";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nHTML Editor for Users'")
	WHERE `plugin`="USER" AND `typ`="USEEDITOR";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nMaximum number of pictures in the provider gallery")
	WHERE `plugin`="USER" AND `typ`="VENDOR_GALLERY_MAX_IMAGES";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nDuration in days after a provider receives an info e-mail if it was not active")
	WHERE `plugin`="USER" AND `typ`="VENDOR_DAYS_WITHOUT_LOGIN_INFO";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nDuration in days after a provider profile is deactivated if it was not active")
	WHERE `plugin`="USER" AND `typ`="VENDOR_DAYS_WITHOUT_LOGIN_DEL";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nNumber of allowed pictures / videos of clubs")
	WHERE `plugin`="USER" AND `typ`="CLUB_GALLERY_MAX_IMAGES";
UPDATE `option` SET beschreibung=CONCAT(beschreibung,"\r\nMaximum number of images in an event")
	WHERE `plugin`="USER" AND `typ`="EVENT_GALLERY_MAX_IMAGES";
	
	
INSERT INTO `option` (`plugin`, `typ`, `value`, `beschreibung`, `format`) 
	VALUES ('SITE', 'CONTACT_PHONE', '', 'Ihre Telefonnummer für Kunden\r\nYour telephone number for customers', 'text');
INSERT INTO `option` (`plugin`, `typ`, `value`, `beschreibung`, `format`) 
	VALUES ('SITE', 'CONTACT_FACEBOOK', '', 'URL ihrer Facebook-Seite\r\nURL of your Facebook page', 'text');
INSERT INTO `option` (`plugin`, `typ`, `value`, `beschreibung`, `format`) 
	VALUES ('SITE', 'CONTACT_GOOGLE', '', 'URL ihrer Google+ Seite\r\nURL of your Google+ page', 'text');
INSERT INTO `option` (`plugin`, `typ`, `value`, `beschreibung`, `format`) 
	VALUES ('SITE', 'CONTACT_TWITTER', '', 'URL ihrer Twitter-Seite\r\nURL of your Twitter page', 'text');
INSERT INTO `option` (`plugin`, `typ`, `value`, `beschreibung`, `format`) 
	VALUES ('SITE', 'CONTACT_YOUTUBE', '', 'URL ihrem Youtube-Kanal\r\nURL of your Youtube channel', 'text');
	
UPDATE `option` SET `format`='list sendmail smtp' 
	WHERE  `plugin`='EMAIL' AND `typ`='TRANSPORT_TYPE';
	
INSERT INTO `option` (`plugin`, `typ`, `value`, `default_value`, `beschreibung`, `format`) 
	VALUES ('EMAIL', 'SMTP_HOST', 'localhost', 'localhost', 'Host-Name für den SMTP-Versand\r\nHost name for SMTP mailing', 'text');
	
INSERT INTO `option` (`plugin`, `typ`, `value`, `default_value`, `beschreibung`, `format`) 
	VALUES ('EMAIL', 'SMTP_PORT', '25', '25', 'Port für den SMTP-Versand\r\nPort for SMTP mailing', 'text');
	
INSERT INTO `option` (`plugin`, `typ`, `value`, `default_value`, `beschreibung`, `format`) 
	VALUES ('EMAIL', 'SMTP_ENCRYPTION', 'ssl', 'ssl', 'Verschlüsselung für den SMTP-Versand\r\nEncryption for SMTP mailing', 'list ssl tls none');
	
INSERT INTO `option` (`plugin`, `typ`, `value`, `default_value`, `beschreibung`, `format`) 
	VALUES ('EMAIL', 'SMTP_USER', '', '', 'Benutzername für den SMTP-Versand\r\nUsername for SMTP mailing', 'text');
	
INSERT INTO `option` (`plugin`, `typ`, `value`, `default_value`, `beschreibung`, `format`) 
	VALUES ('EMAIL', 'SMTP_PASS', '', '', 'Passwort für den SMTP-Versand\r\nPassword for SMTP mailing', 'text');