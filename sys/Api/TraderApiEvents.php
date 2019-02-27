<?php
/**
 * Created by PhpStorm.
 * User: jens
 * Date: 08.09.14
 * Time: 15:53
 */

/**
 * Class Api_TraderApiEvents
 * A simple class containing a list of all available Events (hopefully) including a short description.
 */
class Api_TraderApiEvents {

    /**
     * Called when an ajax call related to a plugin was sent ($_REQUEST['pluginAjax'] == pluginName)
     */
    const AJAX_PLUGIN = "AJAX_PLUGIN";

    /**
     * Called when checking for errors on the admin welcome page (e.g. missing configurations)
     */
    const ADMIN_PAGE_RENDER = "ADMIN_PAGE_RENDER";

    /**
     * Called when checking for errors on the admin welcome page (e.g. missing configurations)
     */
    const ADMIN_WELCOME_ERROR = "ADMIN_WELCOME_ERROR";

    /**
     * Called when checking for pending actions
     */
    const ADMIN_WELCOME_TODO = "ADMIN_WELCOME_TODO";

    /**
     * Called when editing a product from the product-database
     */
    const ADMIN_PRODUCTDB_EDIT = "ADMIN_PRODUCTDB_EDIT";

    /**
     * Called when editing a product from the product-database (after submitting - before saving)
     */
    const ADMIN_PRODUCTDB_EDIT_SUBMIT = "ADMIN_PRODUCTDB_EDIT_SUBMIT";

    /**
     * Called when editing a product from the product-database (after saving)
     */
    const ADMIN_PRODUCTDB_EDIT_SAVED = "ADMIN_PRODUCTDB_EDIT_SAVED";

    /**
     * Called when a new user submits the registration (not called when creating a user as admin!!)
     * Can produce errors and prevent a successful registration.
     */
    const USER_REGISTER_CHECK = "USER_REGISTER_CHECK";

    /**
     * Called when a new user registers (not called when creating a user as admin!!)
     */
    const USER_REGISTER = "USER_REGISTER";

    /**
     * Called when a new user was create (register/created by admin)
     */
    const USER_NEW = "USER_NEW";

    /**
     * Called when checking user profile data
     */
    const USER_PROFILE_CHECK = "USER_PROFILE_CHECK";
    
    /**
     * Called when a user changes the profile data
     */
    const USER_PROFILE_CHANGE = "USER_PROFILE_CHANGE";

    /**
     * Called when a user is deleted
     */
    const USER_DELETE = "USER_DELETE";

    /**
     * Called when a user visists the "My Account"/"my-pages" page
     */
    const USER_VIEW_HOME = "USER_VIEW_HOME";

    /**
     * Called when showing the users account settings
     */
    const USER_SETTINGS = "USER_SETTINGS";

    /**
     * Called when saving the users account settings
     */
    const USER_SETTINGS_SUBMIT = "USER_SETTINGS_SUBMIT";
    
    /**
     * Called when the execution of the cronjob(s) is done (not relating to a specific cronjob; called after cron/cronjob.php is finished)
     */
    const CRONJOB_DONE = "CRONJOB_DONE";

    /**
     * Called when a new invoice is created
     */
    const INVOICE_CREATE = "INVOICE_CREATE";

    /**
     * Called when a invoice is set paid
     */
    const INVOICE_PAY = "INVOICE_PAY";

    /**
     * Called when a invoice is set unpaid
     */
    const INVOICE_UNPAY = "INVOICE_UNPAY";

    /**
     * Called when a invoice is canceled
     */
    const INVOICE_CANCEL = "INVOICE_CANCEL";

    /**
     * Called when a invoice item is canceled
     */
    const INVOICE_ITEM_CANCEL = "INVOICE_ITEM_CANCEL";

	/**
	 * Called when a billable item is canceled
	 */
	const EVENT_BILLABLEITEM_CANCEL = "BILLABLEITEM_CANCEL";

    /**
     * Called when a invoice is set paid
     */
    const INVOICE_OVERDUE = "INVOICE_OVERDUE";

    /**
     * Called when a invoice is being corrected/modified
     */
    const INVOICE_CORRECTION = "INVOICE_CORRECTION";
    
    /**
     * Called when a invoice is dunning (first notification)
     */
    const INVOICE_DUNNING_LEVEL_1 = "INVOICE_DUNNING_LEVEL_1";

    /**
     * Called when a invoice is dunning (second notification)
     */
    const INVOICE_DUNNING_LEVEL_2 = "INVOICE_DUNNING_LEVEL_2";

    /**
     * Called when a invoice is dunning (third notification)
     */
    const INVOICE_DUNNING_LEVEL_3 = "INVOICE_DUNNING_LEVEL_3";

    /**
     * Called when a new transaction for an invoice is created
     */
    const INVOICE_TRANSACTION_CREATE = "INVOICE_TRANSACTION_CREATE";

    /**
     * Called when a credit is applied to an invoice
     */
    const INVOICE_TRANSACTION_APPLY_CREDIT = "INVOICE_TRANSACTION_APPLY_CREDIT";

    /**
     * Called when billable items are written into a new invoice
     */
    const INVOICE_AUTOMATICBILLING_RUN_CREATE_INVOICE = "INVOICE_AUTOMATICBILLING_RUN_CREATE_INVOICE";

    /**
     * Called when the list of fields for importing is generated.
     * Allows to add further custom fields that can be mapped for the import.
     */
    const IMPORT_GET_FIELDS = "IMPORT_GET_FIELDS";

    /**
     * Called when selecting ads while importing from ebay.
     * Allows to display further information e.g. about the cost of importing those ads.
     */
    const IMPORT_EBAY_INIT = "IMPORT_EBAY_INIT";

    /**
     * Called when mapping categories while importing from ebay.
     * Allows to display further information e.g. about the cost of importing those ads.
     */
    const IMPORT_EBAY_CATEGORIES = "IMPORT_EBAY_CATEGORIES";

    /**
     * Called when editing the selected ads while importing from ebay.
     * Allows to display further information e.g. about the cost of importing those ads.
     */
    const IMPORT_EBAY_EDIT = "IMPORT_EBAY_EDIT";

    /**
     * Called when creating a new import preset.
     * Allows to manipulate the available/default options for example.
     */
    const IMPORT_PRESET_CREATE = "IMPORT_PRESET_CREATE";

    /**
     * Called when creating a new import preset.
     * Allows to manipulate the available/default options for example.
     */
    const IMPORT_SOURCE_CREATE = "IMPORT_SOURCE_CREATE";

    /**
     * Called after loading an import process.
     * Allows to manipulate the configuration of the given import process before usage.
     */
    const IMPORT_PROCESS_LOAD = "IMPORT_PROCESS_LOAD";

    /**
     * Called before a dataset is inserted/updated/deleted in the database.
     * Allows to manipulate the data of imported ads.
     */
    const IMPORT_PROCESS_DATASET = "IMPORT_PROCESS_DATASET";

    /**
     * Called after loading the navigation structure, allowing to modify it.
     */
    const NAV_LOAD = "NAV_LOAD";

    /**
     * Called when a new invoice for an packet is created.
     */
    const PACKET_NEW_INVOICE = "PACKET_NEW_INVOICE";

    /**
     * Called when a packet timed out and is being extended.
     */
    const PACKET_RENEW = "PACKET_RENEW";

    /**
     * Called when a packet was enabled.
     */
    const PACKET_ENABLED = "PACKET_ENABLED";

    /**
     * Called when a packet was disabled.
     */
    const PACKET_DISABLED = "PACKET_DISABLED";

    /**
     * Called when initializing the steps
     */
    const STEPS_GENERAL_INIT = "STEPS_GENERAL_INIT";
    
    /**
     * Called when rendering a step
     */
    const STEPS_GENERAL_RENDER = "STEPS_GENERAL_RENDER";

    /**
     * Called when preparing the scripts needed for all steps
     */
    const STEPS_GENERAL_SCRIPTS = "STEPS_GENERAL_SCRIPTS";
    
    /**
     * Called when submitting a step
     */
    const STEPS_GENERAL_SUBMIT = "STEPS_GENERAL_SUBMIT";
    
    /**
     * Called when loading an entity from the database
     */
    const STEPS_GENERAL_DB_LOAD = "STEPS_GENERAL_DB_LOAD";
    
    /**
     * Called when saving an entity to the database
     */
    const STEPS_GENERAL_DB_SAVE = "STEPS_GENERAL_DB_SAVE";

    /**
     * Called when showing the confirmation page for creating an article.
     */
    const MARKETPLACE_AD_CONFIRM = "MARKETPLACE_AD_CONFIRM";

    /**
     * Called when validating an article field while creating a new marketplace article.
     */
    const MARKETPLACE_AD_FIELD_VALIDATE = "MARKETPLACE_FIELD_VALIDATE";

    /**
     * Called when initializing the steps for creating a new article
     */
    const MARKETPLACE_AD_CREATE_INIT_STEPS = "MARKETPLACE_AD_CREATE_INIT_STEPS";
    
    /**
     * Called when rendering a step for creating a new article
     */
    const MARKETPLACE_AD_CREATE_RENDER_STEP = "MARKETPLACE_AD_CREATE_RENDER_STEP";

    /**
     * Called when generating the redirect url after creating an article
     */
    const MARKETPLACE_AD_CREATE_FINISH_URL = "MARKETPLACE_AD_CREATE_FINISH_URL";

    /**
     * Called before a marketplace article is created.
     */
    const MARKETPLACE_AD_CREATE = "MARKETPLACE_AD_CREATE";

    /**
     * Called when a marketplace article was created.
     */
    const MARKETPLACE_AD_CREATED = "MARKETPLACE_AD_CREATED";

    /**
     * Called before a marketplace article is being updated.
     */
    const MARKETPLACE_AD_UPDATE = "MARKETPLACE_AD_UPDATE";
    
    /**
     * Called when a marketplace article was updated.
     */
    const MARKETPLACE_AD_UPDATED = "MARKETPLACE_AD_UPDATED";
    
    /**
     * Called when an import process was started.
     */
    const MARKETPLACE_AD_IMPORT_START = "MARKETPLACE_AD_IMPORT_START";
    
    /**
     * Called when marketplace articles were imported.
     */
    const MARKETPLACE_AD_IMPORT_LIVE = "MARKETPLACE_AD_IMPORT_LIVE";
    
    /**
     * Called when marketplace articles were imported with the testing option enabled.
     */
    const MARKETPLACE_AD_IMPORT_TEST = "MARKETPLACE_AD_IMPORT_TEST";

    /**
     * Called when an import process was finished.
     */
    const MARKETPLACE_AD_IMPORT_FINISH = "MARKETPLACE_AD_IMPORT_FINISH";
    
    /**
     * Called when a marketplace article is being enabled. (created/enabled/imported/...)
     */
    const MARKETPLACE_AD_ENABLE = "MARKETPLACE_AD_ENABLE";

    /**
     * Called when a marketplace article is being enabled. (timeout/deleted/disabled/sold out/...)
     */
    const MARKETPLACE_AD_DISABLE = "MARKETPLACE_AD_DISABLE";

    /**
     * Called when the geolocation of a marketplace article changed.
     */
    const MARKETPLACE_AD_LOCATION_UPDATE = "MARKETPLACE_AD_LOCATION_UPDATE";

    /**
     * Submitting a step while creating an article
     */
    const MARKETPLACE_AD_CREATE_SUBMIT_STEP = "MARKETPLACE_AD_CREATE_SUBMIT_STEP";
    
    /**
     * Rendering list of article images
     */
    const MARKETPLACE_AD_CREATE_RENDER_IMAGES = "MARKETPLACE_AD_CREATE_RENDER_IMAGES";
    
    /**
     * Rendering list of article downloads
     */
    const MARKETPLACE_AD_CREATE_RENDER_DOWNLOADS = "MARKETPLACE_AD_CREATE_RENDER_DOWNLOADS";
    
    /**
     * Rendering list of article videos
     */
    const MARKETPLACE_AD_CREATE_RENDER_VIDEOS = "MARKETPLACE_AD_CREATE_RENDER_VIDEOS";
    
    /**
     * Called when a marketplace article is being enabled. (created/enabled/imported/...)
     */
    const MARKETPLACE_AD_CREATE_INPUT_FIELDS = "MARKETPLACE_AD_CREATE_INPUT_FIELDS";

    /**
     * Called when loading the input fields while creating/editing an article
     */
    const MARKETPLACE_AD_CREATE_INPUT_FIELDS_LOAD = "MARKETPLACE_AD_CREATE_INPUT_FIELDS_LOAD";

    /**
     * Called when a fields are submitted while creating/editing a .
     */
    const MARKETPLACE_AD_CREATE_SUBMIT_FIELDS = "MARKETPLACE_AD_CREATE_SUBMIT_FIELDS";

    /**
     * Called when asking for shipping options
     */
    const MARKETPLACE_AD_CREATE_SHIPPING = "MARKETPLACE_AD_CREATE_SHIPPING";

    /**
     * Called when the datatable for querying ads was requested.
     */
    const MARKETPLACE_AD_GET_DATATABLE = "MARKETPLACE_AD_GET_DATATABLE";

    /**
     * Called when the datatable for querying products was requested. (hdb)
     */
    const MARKETPLACE_AD_GET_DATATABLE_PRODUCT = "MARKETPLACE_AD_GET_DATATABLE_PRODUCT";

    /**
   	 * Called when ad search form is generated
   	 */
   	const MARKETPLACE_AD_SEARCH_FORM = "MARKETPLACE_AD_SEARCH_FORM";
    
    /**
   	 * Called when ad search form is generated
   	 */
   	const MARKETPLACE_AD_SEARCH_QUERY = "MARKETPLACE_AD_SEARCH_QUERY";
    
    /**
   	 * Called when ad search result url is generated
   	 */
   	const MARKETPLACE_AD_SEARCH_URL = "MARKETPLACE_AD_SEARCH_URL";
    
    /**
   	 * Called when ad search result url is generated
   	 */
   	const MARKETPLACE_AD_DETAILS = "MARKETPLACE_AD_DETAILS";

    /**
     * Called when ad search database is updated
     */
    const MARKETPLACE_AD_SEARCHDB_UPDATE = "MARKETPLACE_AD_SEARCHDB_UPDATE";

    /**
     * Called when outputting shipping cost
     */
    const MARKETPLACE_AD_SHIPPING_DISPLAY = "MARKETPLACE_AD_SHIPPING_DISPLAY";

    /**
     * Called when an article was updated or added to the cart
     */
    const MARKETPLACE_CART_ARTICLE_UPDATE = "MARKETPLACE_CART_ARTICLE_UPDATE";
    
    /**
     * Called when calculating the shipping cost for the cart items
     */
    const MARKETPLACE_CART_ARTICLE_SHIPPING = "MARKETPLACE_CART_ARTICLE_SHIPPING";
    
    /**
     * Called when calculating the shipping cost for the cart items
     */
    const MARKETPLACE_CART_ARTICLES_GROUP = "MARKETPLACE_CART_ARTICLES_GROUP";

    /**
     * Called when checking out the article cart
     */
    const MARKETPLACE_CART_VIEW = "MARKETPLACE_CART_VIEW";

    /**
     * Called when checking out the article cart
     */
    const MARKETPLACE_CART_CHECKOUT = "MARKETPLACE_CART_CHECKOUT";

    /**
     * Called when checking out the article cart was successful done
     */
    const MARKETPLACE_CART_CHECKOUT_SUCCESS = "MARKETPLACE_CART_CHECKOUT_SUCCESS";

    /**
     * Called when grouping the orders when buying
     */
    const MARKETPLACE_ORDER_GROUP = "MARKETPLACE_ORDER_GROUP";

    /**
     * Called when querying the sales according to the search options
     */
    const MARKETPLACE_ORDER_SALES_SEARCH_QUERY = "MARKETPLACE_ORDER_SALES_SEARCH_QUERY";

    /**
     * Called when querying the items of specific orders
     */
    const MARKETPLACE_ORDER_SALES_SEARCH_QUERY_ITEMS = "MARKETPLACE_ORDER_SALES_SEARCH_QUERY_ITEMS";

    /**
     * Called when generating the search form for sold items
     */
    const MARKETPLACE_ORDER_SALES_SEARCH_FORM = "MARKETPLACE_ORDER_SALES_SEARCH_FORM";

    /**
     * Called when the template for editing a marketplace category is rendered (admin)
     */
    const MARKETPLACE_CATEGORY_EDIT_TEMPLATE = "MARKETPLACE_CATEGORY_EDIT_TEMPLATE";

    /**
     * Called when a marketplace category was edited
     */
    const MARKETPLACE_CATEGORY_UPDATED = "MARKETPLACE_CATEGORY_UPDATED";

    /**
     * Called before the ads for the marketplace list are queried, allowing to modify the query parameters.
     */
    const MARKETPLACE_LIST_QUERY = "MARKETPLACE_LIST_QUERY";

    /**
     * Called before the ads for the marketplace list are rendered.
     */
    const MARKETPLACE_LIST_POST_PROCESSING = "MARKETPLACE_LIST_POST_PROCESSING";

    /**
     * Called before rendering the default view of a marketplace category
     */
    const MARKETPLACE_VIEW = "MARKETPLACE_VIEW";

    /**
     * Called when a users membership changed.
     */
    const MEMBERSHIP_CHANGED = "MEMBERSHIP_CHANGED";
    
    /**
     * Called when advanced features of memberships are shown.
     */
    const MEMBERSHIP_OTHER_FEATURES = "MEMBERSHIP_OTHER_FEATURES";
    
    /**
     * Called when advanced features of memberships are shown for configuration in the admin backend.
     */
    const MEMBERSHIP_OTHER_FEATURES_ADMIN = "MEMBERSHIP_OTHER_FEATURES_ADMIN";
    
    /**
     * Called when advanced features of packets are shown.
     */
    const PACKET_OTHER_FEATURES = "PACKET_OTHER_FEATURES";
    
    /**
     * Called when advanced features of packets are shown for configuration in the admin backend.
     */
    const PACKET_OTHER_FEATURES_ADMIN = "PACKET_OTHER_FEATURES_ADMIN";
    
    /**
     * Called when clearing/refreshing all cache files.
     */
    const SYSTEM_CACHE_ALL = "SYSTEM_CACHE_ALL";
    
    /**
     * Called when clearing/refreshing all template files.
     */
    const SYSTEM_CACHE_TEMPLATES = "SYSTEM_CACHE_TEMPLATES";
    
    /**
     * Called when clearing/refreshing all translation files.
     */
    const SYSTEM_CACHE_TRANSLATIONS = "SYSTEM_CACHE_TRANSLATIONS";

    /**
     * Called when a plugin function is invoked (within template: {plugin(MyPlugin,MyFunction,Param1,Param2)} )
     */
    const TEMPLATE_PLUGIN_FUNCTION = "TEMPLATE_PLUGIN_FUNCTION";

    /**
     * Called before initializing the frame template
     */
    const TEMPLATE_SETUP_FRAME = "TEMPLATE_SETUP_FRAME";

    /**
     * Called before initializing the content template
     */
    const TEMPLATE_SETUP_CONTENT = "TEMPLATE_SETUP_CONTENT";
    
    /**
   	 * Called when processing an url (for detecting the language)
   	 */
   	const URL_PROCESS_LANGUAGE = "URL_PROCESS_LANGUAGE";

    /**
     * Called when processing an url (for detecting target page)
     */
    const URL_PROCESS_PAGE = "URL_PROCESS_PAGE";

    /**
     * Called when generating an url
     */
    const URL_GENERATE = "URL_GENERATE";

    /**
     * Called before writing out an url
     */
    const URL_OUTPUT = "URL_OUTPUT";

    /**
     * Called when rendering the present videos for a vendory entry
     */
    const VENDOR_RENDER_VIDEOS = "VENDOR_RENDER_VIDEOS";

	/**
	 * Called when the datatable for querying ads was requested.
	 */
	const VENDOR_AD_GET_DATATABLE = "VENDOR_AD_GET_DATATABLE";
    
    /**
     * Called when generating the upload form for videos
     */
    const VIDEO_UPLOAD_INPUT = "VIDEO_UPLOAD_INPUT";
}