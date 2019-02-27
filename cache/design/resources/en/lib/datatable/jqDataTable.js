/**
 * Created by Forsaken on 15.07.15.
 */

(function($, componentHandler) {
    "use strict";

    $.fn.dataTable = function(parameters, more1, more2, more3, more4) {
        if (typeof parameters === "string") {
            $(this).each(function() {
                if (typeof this.jqDataTable !== "undefined") {
                    switch(parameters) {
                        case "clearDataCache":
                            clearDataCache(this);
                            break;
                        case "executeAction":
                            var action = more1, targets = more2, actionParams = more3, callback = more4;
                            executeAction(this, action, actionParams, targets, callback);
                            break;
                        case "pageNext":
                            setPageNext(this);
                            break;
                        case "pagePrev":
                            setPagePrev(this);
                            break;
                        case "reload":
                            // Reload result set (starting at the first page)
                            queuePageChange(this, 0, true, false, 0);
                            break;
                        default:
                            break;
                    }
                }
            });
        } else {
            if (typeof parameters == "undefined") {
                parameters = {};
            }
            if (jQuery(this).length > 0) {
                jQuery(this).each(function() {
                    initDataTable(this, parameters);
                });
            }
        }
    };

    function initDataTable(jsTable, parameters) {
        var isInitialisation = false;
        if (typeof jsTable.jqDataTable == "undefined") {
            jsTable.jqDataTable = {
                ajaxUrl: false,
                ajaxPageQueue: [],
                ajaxPageQueueLock: false,
                callbackAction: false,
                callbackLoading: false,
                currentPage: 0,
                targetPage: 0,
                queuePageChangeTimer: false,
                dataCache: false,
                dataCacheEnabled: true,
                dataCachePreload: 2,
                elementContainer: false,
                elementPageLoadingBar: false,
                elementPageItemCount: false,
                elementPageItemFirst: false,
                elementPageItemLast: false,
                elementResultCount: false,
                elementSelectCount: false,
                resultCount: 0,
                selectable: false,
                selectionAdd: false,
                selectionSub: false,
                selectionKeys: false,
                tableUi: "default",
                queryOptions: false,
                queryOptionsInitial: false,
                filterAutoCommit: true,
                filterKeepSelection: false
            };
            isInitialisation = true;
        }
        var jqTable = jQuery(jsTable);
        var jqContainer = jqTable;
        if (typeof parameters.ajaxUrl != "undefined") {
            jsTable.jqDataTable.ajaxUrl = parameters.ajaxUrl;
        } else if (isInitialisation && jqTable.is("[data-ajax-url]")) {
            jsTable.jqDataTable.ajaxUrl = jqTable.attr("data-ajax-url");
        }
        if (typeof parameters.callbackAction == "function") {
            jsTable.jqDataTable.callbackAction = parameters.callbackAction;
        }
        if (typeof parameters.callbackLoading == "function") {
            jsTable.jqDataTable.callbackLoading = parameters.callbackLoading;
        }
        if (typeof parameters.dataCache != "undefined") {
            jsTable.jqDataTable.dataCacheEnabled = (parameters.dataCache ? true : false);
        } else if (isInitialisation && jqTable.is("[data-ajax-url]")) {
            jsTable.jqDataTable.dataCacheEnabled = (jqTable.attr("data-ajax-url") ? true : false);
        }
        if (typeof parameters.filterKeepSelection != "undefined") {
            jsTable.jqDataTable.filterKeepSelection = (parameters.filterKeepSelection ? true : false);
        }
        if (typeof parameters.resultCount != "undefined") {
            jsTable.jqDataTable.resultCount = parameters.resultCount;
        } else if (isInitialisation && jqTable.is("[data-ajax-url]")) {
            var resultCount = parseInt( jqTable.attr("data-result-count") );
            if (resultCount > 0) {
                jsTable.jqDataTable.resultCount = resultCount;
            }
        }
        if (typeof parameters.selectionAdd != "undefined") {
            jsTable.jqDataTable.selectionAdd = parameters.selectionAdd;
        }
        if (typeof parameters.selectionSub != "undefined") {
            jsTable.jqDataTable.selectionSub = parameters.selectionSub;
        }
        if (typeof parameters.elementContainer != "undefined") {
            jqContainer = jQuery( parameters.elementContainer );
            if (jqContainer.length > 0) {
                jsTable.jqDataTable.elementContainer = jqContainer;
            } else {
                jqContainer = jqTable;
            }
        } else if (isInitialisation && jqTable.is("[data-ajax-url]")) {
            jqContainer = jQuery( jqTable.attr("data-container") );
            if (jqContainer.length > 0) {
                jsTable.jqDataTable.elementContainer = jqContainer;
            } else {
                jqContainer = jqTable;
            }
        }
        if (isInitialisation) {
            if (jQuery(jsTable).is(".mdl-data-table")) {
                jsTable.jqDataTable.tableUi = "mdl";
            }
            var elementFilterForm = jqContainer.find("form[data-action=filter-apply]");
            if (elementFilterForm.length > 0) {
                jsTable.jqDataTable.filterAutoCommit = false;
                elementFilterForm.submit(function(e) {
                    e.preventDefault();
                    // Reload result set (starting at the first page)
                    queuePageChange(jsTable, 0, true, true, 100);
                });
            }
            var elementFilterCommit = jqContainer.find("a[data-action=filter-apply],button[data-action=filter-apply]");
            if (elementFilterCommit.length > 0) {
                jsTable.jqDataTable.filterAutoCommit = false;
                elementFilterCommit.click(function(e) {
                    e.preventDefault();
                    // Reload result set (starting at the first page)
                    queuePageChange(jsTable, 0, true, true, 100);
                });
            }
            var elementFilterReset = jqContainer.find("a[data-action=filter-reset],button[data-action=filter-reset]");
            if (elementFilterReset.length > 0) {
                elementFilterReset.click(function(e) {
                    // Reset query options
                    jsTable.jqDataTable.queryOptions = jQuery.extend({}, jsTable.jqDataTable.queryOptionsInitial);
                    // Reload result set (starting at the first page)
                    queuePageChange(jsTable, 0, true, true, 100);
                });
            }
            var elementPageLoadingBar = jqContainer.find("[data-value=page-loading-bar]");
            var elementPageItemCount = jqContainer.find("[data-value=page-item-count]");
            var elementPageItemFirst = jqContainer.find("[data-value=page-item-first]");
            var elementPageItemLast = jqContainer.find("[data-value=page-item-last]");
            var elementResultCount = jqContainer.find("[data-value=result-count]");
            var elementSelectCount = jqContainer.find("[data-value=select-count]");
            if (elementPageLoadingBar.length > 0) {
                jsTable.jqDataTable.elementPageLoadingBar = elementPageLoadingBar;
            }
            if (elementPageItemCount.length > 0) {
                jsTable.jqDataTable.elementPageItemCount = elementPageItemCount;
            }
            if (elementPageItemFirst.length > 0) {
                jsTable.jqDataTable.elementPageItemFirst = elementPageItemFirst;
            }
            if (elementPageItemLast.length > 0) {
                jsTable.jqDataTable.elementPageItemLast = elementPageItemLast;
            }
            if (elementResultCount.length > 0) {
                jsTable.jqDataTable.elementResultCount = elementResultCount;
            }
            if (elementSelectCount.length > 0) {
                jsTable.jqDataTable.elementSelectCount = elementSelectCount;
            }
            if (jsTable.jqDataTable.resultCount === 0) {
                var resultCount = parseInt( elementResultCount.html() );
                if (resultCount > 0) {
                    jsTable.jqDataTable.resultCount = resultCount;
                }
            }
            // Bind controls: Row checkboxes
            if (jsTable.jqDataTable.tableUi == "default") {
                var globalCheckbox = jQuery(jsTable).find("> thead > tr > th:first-child input[type=checkbox]");
                if (globalCheckbox.length > 0) {
                    jsTable.jqDataTable.selectable = true;
                    if (jsTable.jqDataTable.selectionAdd === false) {
                        jsTable.jqDataTable.selectionAdd = [];
                    }
                    globalCheckbox.change(function() {
                        setSelectedAll(jsTable);
                    });
                    jQuery(jsTable).find("> tbody > tr").each(function() {
                        var tr = this;
                        var selectKey = null;
                        if (tr.hasAttribute("data-select-key")) {
                            selectKey = tr.getAttribute("data-select-key");
                        } else if (tr.hasAttribute("data-select-keys")) {
                            selectKey = JSON.parse(tr.getAttribute("data-select-keys"));
                        }
                        if (selectKey !== null) {
                            jQuery(tr).find("> td:first-child input[type=checkbox]").change(function() {
                                setSelected(jsTable, selectKey, this.checked);
                            });
                        }
                    });
                }
            } else if (jsTable.jqDataTable.tableUi == "mdl") {
                if (jQuery(jsTable).is(".mdl-data-table--selectable")) {
                    jsTable.jqDataTable.selectable = true;
                    if (jsTable.jqDataTable.selectionAdd === false) {
                        jsTable.jqDataTable.selectionAdd = [];
                    }
                    jQuery(jsTable).find("> thead > tr > th:first-child .mdl-checkbox.is-upgraded").change(function() {
                        setSelectedAll(jsTable);
                    });
                    jQuery(jsTable).find("> tbody > tr").each(function() {
                        var tr = this;
                        var selectKey = null;
                        if (tr.hasAttribute("data-select-key")) {
                            selectKey = tr.getAttribute("data-select-key");
                        } else if (tr.hasAttribute("data-select-keys")) {
                            selectKey = JSON.parse(tr.getAttribute("data-select-keys"));
                        }
                        if (selectKey !== null) {
                            jQuery(tr).find("> td:first-child .mdl-checkbox.is-upgraded").change(function() {
                                setSelected(jsTable, selectKey, this.MaterialCheckbox.inputElement_.checked);
                            });
                        }
                    });
                }
            }
            // Bind controls: Sortable columns
            var jqSortableColumns = [];
            if (jsTable.jqDataTable.tableUi == "default") {
                jqSortableColumns = jQuery(jsTable).find("> thead > tr > th.sortable");
            } else if (jsTable.jqDataTable.tableUi == "mdl") {
                jqSortableColumns = jQuery(jsTable).find("> thead > tr > th.mdl-data-table__cell--sortable");
            }
            jqSortableColumns.each(function() {
                if (!jQuery(this).is("[data-sort-name]")) {
                    return;
                }
                jQuery(this).click(function(e) {
                    if (!jQuery(this).is("[data-sort-name]")) {
                        return;
                    }
                    // Prevent default browser action
                    e.preventDefault();
                    // Get name and current order of the field to be sorted
                    var fieldName = jQuery(this).attr("data-sort-name");
                    var fieldSortCurrent = (jQuery(this).is("[data-sort-dir]") ? jQuery(this).attr("data-sort-dir") : false);
                    // Cycle through: ASC/DESC/NONE
                    if (fieldSortCurrent === false) {
                        setFieldSortOrder(jsTable, fieldName, "ASC", this);
                    } else if (fieldSortCurrent == "ASC") {
                        setFieldSortOrder(jsTable, fieldName, "DESC", this);
                    } else {
                        setFieldSortOrder(jsTable, fieldName, false, this);
                    }
                });
            });

            // Bind controls: Filter inputs
            jqContainer.find("select[data-filter],input[data-filter]").change(function() {
                setWhereCondition(jsTable, jQuery(this).attr("data-filter"), jQuery(this).val());
            });
            // Bind controls: Action buttons
            if (jsTable.jqDataTable.selectable) {
                jqContainer.find("[data-action=selection-action]").click(function() {
                    executeAction(jsTable, jQuery(this).attr("data-selection-action"));
                });
            } else {
                jQuery(jsTable).find("tbody [data-action=selection-action]").click(function() {
                    var tr = jQuery(this).closest("tr")[0];
                    var selectKey = null;
                    if (tr.hasAttribute("data-select-key")) {
                        selectKey = tr.getAttribute("data-select-key");
                    } else if (tr.hasAttribute("data-select-keys")) {
                        selectKey = JSON.parse(tr.getAttribute("data-select-keys"));
                    }
                    if (selectKey !== null) {
                        executeAction(jsTable, jQuery(this).attr("data-selection-action"), undefined, [ selectKey ]);
                    }
                });
            }
            // Bind controls: Buttons
            jqContainer.find("[data-action=select-all]").click(function() { setSelectedAll(jsTable, true); });
            jqContainer.find("[data-action=select-none]").click(function() { setSelectedAll(jsTable, false); });
            jqContainer.find("select[data-action=page-item-count],radio[data-action=page-item-count]").change(function() { setLimit(jsTable, parseInt( jQuery(this).val() ) ); });
            jqContainer.find("a[data-action=page-item-count],button[data-action=page-item-count]").click(function() { setLimit(jsTable, parseInt( jQuery(this).attr("data-value") ) ); });
            jqContainer.find("[data-action=page-first]").click(function() { setPageFirst(jsTable); });
            jqContainer.find("[data-action=page-prev]").click(function() { setPagePrev(jsTable); });
            jqContainer.find("[data-action=page-next]").click(function() { setPageNext(jsTable); });
            jqContainer.find("[data-action=page-last]").click(function() { setPageLast(jsTable); });
            if (jQuery(jsTable).is("[data-query-options]")) {
                jsTable.jqDataTable.queryOptions = JSON.parse( jQuery(jsTable).attr("data-query-options") );
                jsTable.jqDataTable.queryOptionsInitial = jQuery.extend({}, jsTable.jqDataTable.queryOptions);
                onQueryOptionsUpdate(jsTable);
            } else {
                // Get query options by ajax call
                getQueryOptions(jsTable);
            }
        }
    }

    function cachePage(jsTable, pageIndex) {
        var pagesMax = Math.floor(jsTable.jqDataTable.resultCount / jsTable.jqDataTable.queryOptions.limit) + 1;
        if ((pageIndex < 0) || (pageIndex >= pagesMax)) {
            return false;
        }
        if ((jsTable.jqDataTable.dataCache !== false) && (typeof jsTable.jqDataTable.dataCache.pages[pageIndex] != "undefined")) {
            // Page already cached
            return true;
        }
        if (pageIndex == jsTable.jqDataTable.currentPage) {
            // Page already active
            cacheCurrentPage(jsTable);
            return true;
        }
        // Prevent caching twice
        jsTable.jqDataTable.dataCache.pages[pageIndex] = false;
        // Query page
        var queryOptions = jQuery.extend({}, jsTable.jqDataTable.queryOptions, true);
        queryOptions.offset = pageIndex * queryOptions.limit;
        var parameters = "action=getResults&queryOptions="+encodeURIComponent(JSON.stringify(queryOptions));
        var ajaxRequest = jQuery.post(jsTable.jqDataTable.ajaxUrl, parameters, function(result) {
            if (result.success) {
                // Initialize body
                var jqTableBody = jQuery(result.body);
                onQueryPageInitialize(jsTable, jqTableBody[0], false);
                // Write page into cache
                cacheStorePage(jsTable, pageIndex, jqTableBody);
            }
        });
        jsTable.jqDataTable.ajaxPageQueue.push(ajaxRequest);
        ajaxRequest.always(function() { cleanupAjaxPageQueue(jsTable); });
        return true;
    }

    function cacheStorePage(jsTable, pageIndex, jsTableBody) {
        if ((jsTable.jqDataTable.dataCacheEnabled === false) || (jsTable.jqDataTable.queryOptions === false)) {
            return false;
        }
        if (jsTable.jqDataTable.queryOptions.limit === null) {
            return false;
        }
        if (jsTable.jqDataTable.dataCache === false) {
            jsTable.jqDataTable.dataCache = {
                pages: {}
            };
        }
        if ((typeof jsTable.jqDataTable.dataCache.pages[ pageIndex ] != "undefined") &&
            (jsTable.jqDataTable.dataCache.pages[pageIndex] !== false)) {
            // Remove currently cached element
            jsTable.jqDataTable.dataCache.pages[ pageIndex ].remove();
        }
        jsTable.jqDataTable.dataCache.pages[ pageIndex ] = jQuery(jsTableBody);
        if (pageIndex == jsTable.jqDataTable.targetPage) {
            setPage(jsTable, pageIndex);
        }
        return true;
    }

    function cacheCurrentPage(jsTable) {
        return cacheStorePage(jsTable, jsTable.jqDataTable.currentPage, jQuery(jsTable).find("tbody"));
    }

    function cancelAjaxPageQueue(jsTable) {
        var ajaxRequest = false;
        for (var index = 0; index < jsTable.jqDataTable.ajaxPageQueue.length; index++) {
            ajaxRequest = jsTable.jqDataTable.ajaxPageQueue[index];
            if (typeof ajaxRequest != "undefined") {
                ajaxRequest.abort();
            }
        }
    }

    function cleanupAjaxPageQueue(jsTable) {
        if (jsTable.jqDataTable.ajaxPageQueueLock) {
            // Cleanup already running
            return false;
        }
        jsTable.jqDataTable.ajaxPageQueueLock = true;
        var ajaxRequest = false;
        while (jsTable.jqDataTable.ajaxPageQueue.length > 0) {
            // Get first request
            ajaxRequest = jsTable.jqDataTable.ajaxPageQueue[0];
            if (typeof ajaxRequest.status == "undefined") {
                // Query still pending
                break;
            }
            // Query done! Remove from queue
            jsTable.jqDataTable.ajaxPageQueue.shift();
        }
        jsTable.jqDataTable.ajaxPageQueueLock = false;
    }

    function clearDataCache(jsTable) {
        if (jsTable.jqDataTable.dataCache === false) {
            // Cache is empty
            return true;
        }
        // Cancel active ajax requests
        cancelAjaxPageQueue(jsTable);
        cleanupAjaxPageQueue(jsTable);
        // Remove page dom elements
        for (var pageIndex in jsTable.jqDataTable.dataCache.pages) {
            if (jsTable.jqDataTable.dataCache.pages[pageIndex] !== false) {
                // Do not remove currently active page
                if (pageIndex != jsTable.jqDataTable.currentPage) {
                    jsTable.jqDataTable.dataCache.pages[pageIndex].remove();
                }
                jsTable.jqDataTable.dataCache.pages[pageIndex] = false;
            }
        }
        jsTable.jqDataTable.dataCache = false;
        return true;
    }

    function executeAction(jsTable, action, parameters, targets, callback) {
        if (typeof targets == "undefined") {
            if ((jsTable.jqDataTable.selectionSub !== false) && (jsTable.jqDataTable.selectionKeys === false)) {
                // Substractive selection enabled, but selection keys not known yet.
                // TODO: User notification
                // TODO: Enqueue action for execution as soon as select keys are ready
                // Show loading bar (if not already visible)
                onLoadingStart(jsTable);
                return false;
            }
            if ((jsTable.jqDataTable.selectionAdd === false) && (jsTable.jqDataTable.selectionSub === false)) {
                // Nothing selected / selection disabled
                return false;
            }
        }
        var queryParameter = "action=executeAction&targetAction="+encodeURIComponent(action);
        if (typeof parameters != "undefined") {
            queryParameter += "&targetParameters="+encodeURIComponent(JSON.stringify(parameters));
        }
        if (typeof targets == "undefined") {
            if (jsTable.jqDataTable.selectionAdd !== false) {
                queryParameter += "&targetKeys=" + encodeURIComponent(JSON.stringify(jsTable.jqDataTable.selectionAdd));
            } else {
                var selectionKeys = jsTable.jqDataTable.selectionKeys;
                for (var index = selectionKeys.length - 1; index >= 0; index--) {
                    if (!isSelected(jsTable, selectionKeys[index])) {
                        selectionKeys.splice(index, 1);
                    }
                }
                queryParameter += "&targetKeys=" + encodeURIComponent(JSON.stringify(selectionKeys));
            }
        } else {
            queryParameter += "&targetKeys=" + encodeURIComponent(JSON.stringify(targets));
        }
        jQuery.post(jsTable.jqDataTable.ajaxUrl, queryParameter, function(result) {
            if (result.success) {
                onQueryActionComplete(jsTable, action, parameters, result);
                // Forward if requested
                if (typeof result.forward != "undefined") {
                    document.location.href = result.forward;
                }
                // Show popup if requested
                if (typeof result.popup != "undefined") {
                    window.open(result.popup, '_blank');
                }
            } else {
                onQueryActionFailed(jsTable, action, parameters, result.error);
            }
            if (typeof callback == "function") {
                callback(result.success);
            }
        });
    }

    function getQueryOptions(jsTable) {
        if (jsTable.jqDataTable.ajaxUrl === false) {
            return false;
        }
        jQuery.post(jsTable.jqDataTable.ajaxUrl, "action=getQueryOptions", function(result) {
            if (result.success) {
                jsTable.jqDataTable.queryOptions = result.options;
                jsTable.jqDataTable.queryOptionsInitial = jQuery.extend({}, jsTable.jqDataTable.queryOptions);
                onQueryOptionsUpdate(jsTable);
            }
        });
        return true;
    }

    function getSelectKeys(jsTable) {
        if (jsTable.jqDataTable.ajaxUrl === false) {
            return false;
        }
        if (jsTable.jqDataTable.selectionKeys !== false) {
            // Keys already requested
            return true;
        }
        jQuery.post(jsTable.jqDataTable.ajaxUrl, "action=getSelectKeys&queryOptions="+encodeURIComponent(JSON.stringify(jsTable.jqDataTable.queryOptions)), function(result) {
            if (result.success) {
                jsTable.jqDataTable.selectionKeys = result.keys;
                onQuerySelectKeys(jsTable);
            }
        });
        return true;
    }

    function isSelected(jsTable, selectKey) {
        if (jsTable.jqDataTable.selectionAdd !== false) {
            for (var index = 0; index < jsTable.jqDataTable.selectionAdd.length; index++) {
                if (isSelectKeyEqual(selectKey, jsTable.jqDataTable.selectionAdd[index])) {
                    return true;
                }
            }
            return false;
        } else if (jsTable.jqDataTable.selectionSub !== false) {
            for (var index = 0; index < jsTable.jqDataTable.selectionSub.length; index++) {
                if (isSelectKeyEqual(selectKey, jsTable.jqDataTable.selectionSub[index])) {
                    return false;
                }
            }
            return true;
        }
        return false;
    }

    function isSelectKeyEqual(keyA, keyB) {
        if ((typeof keyA == "object") && (typeof keyB == "object")) {
            if (keyA.length == keyB.length) {
                for (var index = 0; index < keyA.length; index++) {
                    if (keyA[index] != keyB[index]) {
                        // Element mismatch, keys are not identical!
                        return false;
                    }
                }
                return true;
            }
        } else {
            // Single key element, plain compare
            if (keyA == keyB) {
                return true;
            }
        }
        return false;
    }

    function onLoadingStart(jsTable) {
        if (jsTable.jqDataTable.elementPageLoadingBar !== false) {
            jsTable.jqDataTable.elementPageLoadingBar.css("visibility", "visible");
        }
        if (jsTable.jqDataTable.callbackLoading !== false) {
            jsTable.jqDataTable.callbackLoading(true);
        }
    }

    function onLoadingDone(jsTable) {
        if (jsTable.jqDataTable.elementPageLoadingBar !== false) {
            jsTable.jqDataTable.elementPageLoadingBar.css("visibility", "hidden");
        }
        if (jsTable.jqDataTable.callbackLoading !== false) {
            jsTable.jqDataTable.callbackLoading(false);
        }
    }

    function onPageChanged(jsTable) {
        var preloadCount = jsTable.jqDataTable.dataCachePreload;
        if (preloadCount > 0) {
            var pageMax = Math.floor(jsTable.jqDataTable.resultCount / jsTable.jqDataTable.queryOptions.limit);
            var pageCacheStart = (jsTable.jqDataTable.currentPage > preloadCount ? jsTable.jqDataTable.currentPage - preloadCount : 0);
            var pageCacheEnd = (jsTable.jqDataTable.currentPage <= (pageMax - preloadCount) ? jsTable.jqDataTable.currentPage + preloadCount : pageMax);
            for (var pageIndex = pageCacheStart; pageIndex <= pageCacheEnd; pageIndex++) {
                cachePage(jsTable, pageIndex);
            }
        }
        // Update UI
        var queryOptions = jsTable.jqDataTable.queryOptions;
        if (jsTable.jqDataTable.elementPageItemCount !== false) {
            jsTable.jqDataTable.elementPageItemCount.html( queryOptions.limit );
        }
        if (jsTable.jqDataTable.elementPageItemFirst !== false) {
            jsTable.jqDataTable.elementPageItemFirst.html( queryOptions.offset + 1 );
        }
        if (jsTable.jqDataTable.elementPageItemLast !== false) {
            if ((jsTable.jqDataTable.resultCount - queryOptions.offset) >= queryOptions.limit) {
                jsTable.jqDataTable.elementPageItemLast.html( queryOptions.offset + queryOptions.limit );
            } else {
                jsTable.jqDataTable.elementPageItemLast.html( jsTable.jqDataTable.resultCount );
            }
        }
        if (jsTable.jqDataTable.elementResultCount !== false) {
            jsTable.jqDataTable.elementResultCount.html( jsTable.jqDataTable.resultCount );
        }
        // Update pager buttons
        var jqContainer = jsTable.jqDataTable.elementContainer;
        if (queryOptions.offset > 0) {
            jqContainer.find("[data-action=page-first]").prop("disabled", false);
            jqContainer.find("[data-action=page-prev]").prop("disabled", false);
        } else {
            jqContainer.find("[data-action=page-first]").prop("disabled", true);
            jqContainer.find("[data-action=page-prev]").prop("disabled", true);
        }
        if ((queryOptions.offset + queryOptions.limit + 1) <= jsTable.jqDataTable.resultCount) {
            jqContainer.find("[data-action=page-next]").prop("disabled", false);
            jqContainer.find("[data-action=page-last]").prop("disabled", false);
        } else {
            jqContainer.find("[data-action=page-next]").prop("disabled", true);
            jqContainer.find("[data-action=page-last]").prop("disabled", true);
        }
        // Hide loading bar
        onLoadingDone(jsTable);
    }

    function onQueryActionComplete(jsTable, action, parameters, result) {
        // Execute callback
        if (jsTable.jqDataTable.callbackAction !== false) {
            if (jsTable.jqDataTable.callbackAction(jsTable, action, result, parameters)) {
                result.preventReload = true;
            }
        }
        // Hide loading bar
        onLoadingDone(jsTable);
        if ((typeof result.preventReload == "undefined") || (result.preventReload == false)) {
            reloadPage(jsTable);
        }
    }

    function onQueryActionFailed(jsTable, action, parameters, error) {
        // Hide loading bar
        onLoadingDone(jsTable);
        alert("[[ translation : general : dataTable.error.action.failed :: Aktion fehlgeschlagen: ]]"+error);
    }

    function onSelectionChanged(jsTable) {
        var globalCheckbox = null;
        if (jsTable.jqDataTable.tableUi == "default") {
            globalCheckbox = jsTable.querySelector("thead > tr > th:first-child input[type=checkbox]");
        } else if (jsTable.jqDataTable.tableUi == "mdl") {
            globalCheckbox = jsTable.querySelector("thead > tr > th:first-child .mdl-checkbox.is-upgraded");
        }
        if ((jsTable.jqDataTable.selectionSub !== false) && (jsTable.jqDataTable.selectionSub.length === 0) ||
            (jsTable.jqDataTable.selectionAdd !== false) && (jsTable.jqDataTable.selectionAdd.length >= jsTable.jqDataTable.resultCount)) {
            // All selected, select.
            if (jsTable.jqDataTable.tableUi == "default") {
                globalCheckbox.checked = true;
            } else if (jsTable.jqDataTable.tableUi == "mdl") {
                globalCheckbox.MaterialCheckbox.check();
            }
        } else {
            // Not all selected, deselect.
            if (jsTable.jqDataTable.tableUi == "default") {
                globalCheckbox.checked = false;
            } else if (jsTable.jqDataTable.tableUi == "mdl") {
                globalCheckbox.MaterialCheckbox.uncheck();
            }
        }
        if (jsTable.jqDataTable.selectionAdd !== false) {
            if (jsTable.jqDataTable.elementSelectCount !== false) {
                // Update ui elements displaying the number of selected items
                jsTable.jqDataTable.elementSelectCount.html( jsTable.jqDataTable.selectionAdd.length );
            }
            // Update container class depending on selection
            if (jsTable.jqDataTable.selectionAdd.length > 0) {
                jsTable.jqDataTable.elementContainer.addClass("items-selected");
            } else {
                jsTable.jqDataTable.elementContainer.removeClass("items-selected");
            }
        } else if (jsTable.jqDataTable.selectionSub !== false) {
            if (jsTable.jqDataTable.elementSelectCount !== false) {
                // Update ui elements displaying the number of selected items
                jsTable.jqDataTable.elementSelectCount.html( jsTable.jqDataTable.resultCount - jsTable.jqDataTable.selectionSub.length );
            }
            // Update container class depending on selection
            if (jsTable.jqDataTable.selectionSub.length < jsTable.jqDataTable.resultCount) {
                jsTable.jqDataTable.elementContainer.addClass("items-selected");
            } else {
                jsTable.jqDataTable.elementContainer.removeClass("items-selected");
            }
        }
    }

    function onQuerySelectKeys(jsTable) {
        if (jsTable.jqDataTable.resultCount != jsTable.jqDataTable.selectionKeys.length) {
            alert("Achtung! Das Ergebnis hat sich seit dem Aufruf der Seite geändert, daher sind neue Ergebnisse zur Auswahl hinzugekommen.")
            // TODO: Reload table result and get new result count
            // TODO: Clear queud actions
            // Do not execute any queud actions
            return;
        }
        // TODO: Execute queued actions
        // Hide loading bar
        onLoadingDone(jsTable);
        // Update selection state
        onSelectionChanged(jsTable);
    }

    function onQueryOptionsUpdate(jsTable) {
        // Convert empty arrays to empty objects
        if ((jsTable.jqDataTable.queryOptions.fields instanceof Array) && (jsTable.jqDataTable.queryOptions.fields.length === 0)) {
            jsTable.jqDataTable.queryOptions.fields = {};
        }
        if ((jsTable.jqDataTable.queryOptions.where instanceof Array) && (jsTable.jqDataTable.queryOptions.where.length === 0)) {
            jsTable.jqDataTable.queryOptions.where = {};
        }
        if ((jsTable.jqDataTable.queryOptions.group instanceof Array) && (jsTable.jqDataTable.queryOptions.group.length === 0)) {
            jsTable.jqDataTable.queryOptions.group = {};
        }
        if ((jsTable.jqDataTable.queryOptions.having instanceof Array) && (jsTable.jqDataTable.queryOptions.having.length === 0)) {
            jsTable.jqDataTable.queryOptions.having = {};
        }
        if ((jsTable.jqDataTable.queryOptions.sorting instanceof Array) && (jsTable.jqDataTable.queryOptions.sorting.length === 0)) {
            jsTable.jqDataTable.queryOptions.sorting = {};
        }
        // Initialize initial body
        onQueryPageInitialize(jsTable, jQuery(jsTable).children("tbody")[0], true);
        // Write page into cache
        cacheCurrentPage(jsTable);
        // Handle change event
        onPageChanged(jsTable);
    }

    function onQueryPageInitialize(jsTable, jsTableBody, isCached) {
        if (isCached) {
            // Reinitializing new page
            if (jsTable.jqDataTable.selectable) {
                // Update row checkboxes
                jQuery(jsTableBody).children("tr").each(function() {
                    var tr = this;
                    var selectKey = null;
                    if (tr.hasAttribute("data-select-key")) {
                        selectKey = tr.getAttribute("data-select-key");
                    } else if (tr.hasAttribute("data-select-keys")) {
                        selectKey = JSON.parse(tr.getAttribute("data-select-keys"));
                    }
                    if (selectKey !== null) {
                        var rowCheckbox = null;
                        if (jsTable.jqDataTable.tableUi == "default") {
                            rowCheckbox = this.querySelector("input[type=checkbox]");
                        } else if (jsTable.jqDataTable.tableUi == "mdl") {
                            rowCheckbox = this.querySelector(".mdl-checkbox.is-upgraded");
                        }
                        if (isSelected(jsTable, selectKey)) {
                            if (jsTable.jqDataTable.tableUi == "default") {
                                this.querySelector("input[type=checkbox]").checked = true;
                            } else if (jsTable.jqDataTable.tableUi == "mdl") {
                                this.querySelector(".mdl-checkbox.is-upgraded").MaterialCheckbox.check();
                                utilTriggerEvent(rowCheckbox.MaterialCheckbox.inputElement_, "change");
                            }
                        } else {
                            if (jsTable.jqDataTable.tableUi == "default") {
                                this.querySelector("input[type=checkbox]").checked = false;
                            } else if (jsTable.jqDataTable.tableUi == "mdl") {
                                this.querySelector(".mdl-checkbox.is-upgraded").MaterialCheckbox.uncheck();
                                utilTriggerEvent(rowCheckbox.MaterialCheckbox.inputElement_, "change");
                            }
                        }
                    }
                });
            }
        } else {
            // Initializing new page
            if (jsTable.jqDataTable.selectable) {
                // Initialize row checkboxes
                jQuery(jsTableBody).children("tr").each(function() {
                    var tr = this;
                    var selectKey = null;
                    if (tr.hasAttribute("data-select-key")) {
                        selectKey = tr.getAttribute("data-select-key");
                    } else if (tr.hasAttribute("data-select-keys")) {
                        selectKey = JSON.parse(tr.getAttribute("data-select-keys"));
                    }
                    var firstCell = this.querySelector('td');
                    if (firstCell) {
                        if (jsTable.jqDataTable.tableUi == "default") {
                            if (selectKey !== null) {
                                var jqCheckbox = jQuery(firstCell).find("input[type=checkbox]").first();
                                if (isSelected(jsTable, selectKey)) {
                                    jqCheckbox[0].checked = true;
                                }
                                jqCheckbox.change(function() {
                                    setSelected(jsTable, selectKey, this.checked);
                                });
                            }
                        } else if (jsTable.jqDataTable.tableUi == "mdl") {
                            var td = document.createElement('td');
                            var rowCheckbox = jsTable.MaterialDataTable.createCheckbox_(this);
                            if (selectKey !== null) {
                                if (isSelected(jsTable, selectKey)) {
                                    rowCheckbox.MaterialCheckbox.check();
                                    utilTriggerEvent(rowCheckbox.MaterialCheckbox.inputElement_, "change");
                                }
                                jQuery(rowCheckbox.MaterialCheckbox.inputElement_).change(function() {
                                    setSelected(jsTable, selectKey, this.checked);
                                });
                            }
                            td.appendChild(rowCheckbox);
                            this.insertBefore(td, firstCell);
                        }
                    }
                });
            }
        }
    }

    function reloadPage(jsTable, pagesChanged, queryChanged) {
        if (typeof pagesChanged == "undefined") {
            // Clear page cache by default
            pagesChanged = true;
        }
        setPage(jsTable, jsTable.jqDataTable.targetPage, pagesChanged, queryChanged);
    }

    function setLimit(jsTable, itemsPerPage) {
        if (jsTable.jqDataTable.queryOptions.limit == itemsPerPage) {
            // Option did not change!
            return true;
        }
        jsTable.jqDataTable.queryOptions.limit = itemsPerPage;
        // Reload result
        setPage(jsTable, 0, true);
    }

    function setWhereCondition(jsTable, fieldName, value) {
        if (typeof jsTable.jqDataTable.queryOptions.where[fieldName] != "undefined") {
            if (value === "") {
                // Filter being removed
                delete jsTable.jqDataTable.queryOptions.where[fieldName];
                if (jsTable.jqDataTable.filterAutoCommit) {
                    // Reload result set (starting at the first page)
                    queuePageChange(jsTable, 0, true, true);
                }
            } else if (value != jsTable.jqDataTable.queryOptions.where[fieldName]) {
                // Filter being changed
                jsTable.jqDataTable.queryOptions.where[fieldName] = value;
                if (jsTable.jqDataTable.filterAutoCommit) {
                    // Reload result set (starting at the first page)
                    queuePageChange(jsTable, 0, true, true);
                }
            }
        } else {
            if (value !== "") {
                // Filter being added
                jsTable.jqDataTable.queryOptions.where[fieldName] = value;
                if (jsTable.jqDataTable.filterAutoCommit) {
                    // Reload result set (starting at the first page)
                    queuePageChange(jsTable, 0, true, true);
                }
            }
        }
    }

    function setPage(jsTable, pageIndex, pagesChanged, queryChanged) {
        var pagesMax = Math.floor(jsTable.jqDataTable.resultCount / jsTable.jqDataTable.queryOptions.limit) + 1;
        if ((pageIndex < 0) || (pageIndex >= pagesMax)) {
            return false;
        }
        pagesChanged = (typeof pagesChanged == "undefined" ? false : pagesChanged);
        queryChanged = (typeof queryChanged == "undefined" ? false : queryChanged);
        if (queryChanged && jsTable.jqDataTable.selectable && !jsTable.jqDataTable.filterKeepSelection) {
            // Clear selection
            setSelectedAll(jsTable, false);
            // Clear select keys
            jsTable.jqDataTable.selectionKeys = false;
        }
        if (pagesChanged || queryChanged) {
            // Clear data cache
            clearDataCache(jsTable);
        } else if (pageIndex == jsTable.jqDataTable.currentPage) {
            // Page already active
            return false;
        } else {
            // Check cache
            if ((jsTable.jqDataTable.dataCache !== false) && (typeof jsTable.jqDataTable.dataCache.pages[pageIndex] != "undefined")) {
                if (jsTable.jqDataTable.dataCache.pages[pageIndex] === false) {
                    // Show loading bar (if not already visible)
                    onLoadingStart(jsTable);
                    // Show as soon as caching is done
                    jsTable.jqDataTable.targetPage = pageIndex;
                    return true;
                }
                // Restore page from cache
                jQuery(jsTable).children("tbody").detach();
                jsTable.jqDataTable.dataCache.pages[pageIndex].insertAfter( jQuery(jsTable).find("thead").first() );
                // Initialize page
                onQueryPageInitialize(jsTable, jQuery(jsTable).children("tbody")[0], true);
                // Update page index and query offset
                jsTable.jqDataTable.currentPage = pageIndex;
                jsTable.jqDataTable.targetPage = pageIndex;
                jsTable.jqDataTable.queryOptions.offset = pageIndex * jsTable.jqDataTable.queryOptions.limit;
                // Handle change event
                onPageChanged(jsTable);
                return true;
            }
        }
        // Show loading bar (if not already visible)
        onLoadingStart(jsTable);
        // Update target page
        jsTable.jqDataTable.targetPage = pageIndex;
        // Query page
        var queryOptions = jQuery.extend({}, jsTable.jqDataTable.queryOptions, true);
        queryOptions.offset = pageIndex * queryOptions.limit;
        var parameters = "action=getResults&queryOptions="+encodeURIComponent(JSON.stringify(queryOptions))+(queryChanged ? "&calcFoundRows=1" : "");
        var ajaxRequest = jQuery.post(jsTable.jqDataTable.ajaxUrl, parameters, function(result) {
            if (result.success) {
                if (queryChanged) {
                    // Update result count
                    jsTable.jqDataTable.resultCount = result.count;
                }
                // Insert new body
                jQuery(jsTable).find("tbody").detach();
                jQuery(result.body).insertAfter( jQuery(jsTable).find("thead").first() );
                // Update page index and query options
                jsTable.jqDataTable.currentPage = pageIndex;
                jsTable.jqDataTable.queryOptions = queryOptions;
                // Initialize page
                onQueryPageInitialize(jsTable, jQuery(jsTable).children("tbody")[0], false);
                // Cache new page
                cacheCurrentPage(jsTable);
                // Handle change event
                onPageChanged(jsTable);
            }
        });
        jsTable.jqDataTable.ajaxPageQueue.push(ajaxRequest);
        ajaxRequest.always(function() { cleanupAjaxPageQueue(jsTable); });
        return true;
    }

    function setPageFirst(jsTable) {
        setPage(jsTable, 0);
    }

    function setPageNext(jsTable) {
        setPage(jsTable, jsTable.jqDataTable.currentPage + 1);
    }

    function setPagePrev(jsTable) {
        setPage(jsTable, jsTable.jqDataTable.currentPage - 1);
    }

    function setPageLast(jsTable) {
        setPage(jsTable, Math.floor(jsTable.jqDataTable.resultCount / jsTable.jqDataTable.queryOptions.limit));
    }

    function setFieldSortOrder(jsTable, fieldName, fieldOrder) {
        var fieldHeader = jQuery(jsTable).find("> thead > tr > th[data-sort-name="+fieldName+"]");
        if (fieldHeader.length > 0) {
            // Remove existing icons
            if (jsTable.jqDataTable.tableUi == "default") {
                fieldHeader.find(".sort-dir").remove();
            } else if (jsTable.jqDataTable.tableUi == "mdl") {
                fieldHeader.find(".mdl-data-table__cell--sort-icon").remove();
            }
            if (fieldOrder !== false) {
                // Update data attribute
                fieldHeader.attr("data-sort-dir", fieldOrder);
                // Add new sort icon
                if (jsTable.jqDataTable.tableUi == "default") {
                    fieldHeader.prepend('<span class="sort-dir">'+(fieldOrder == "DESC" ? "&darr;" : "&uarr;")+'</i>');
                } else if (jsTable.jqDataTable.tableUi == "mdl") {
                    fieldHeader.prepend('<i class="material-icons mdl-data-table__cell--sort-icon">'+(fieldOrder == "DESC" ? "keyboard_arrow_down" : "keyboard_arrow_up")+'</i>');
                }
            } else {
                // Remove data attribute
                fieldHeader.attr("data-sort-dir", null);
            }
        }
        // Change sort order?
        for (var sortField in jsTable.jqDataTable.queryOptions.sorting) {
            if (sortField == fieldName) {
                if (jsTable.jqDataTable.queryOptions.sorting != fieldOrder) {
                    if (fieldOrder !== false) {
                        // Change sort order
                        jsTable.jqDataTable.queryOptions.sorting[sortField] = fieldOrder;
                    } else {
                        // Remove field from sort order
                        delete jsTable.jqDataTable.queryOptions.sorting[sortField];
                    }
                    // Reload result set (starting at the first page)
                    queuePageChange(jsTable, 0, true);
                }
                return true;
            }
        }
        // Add sort order!
        jsTable.jqDataTable.queryOptions.sorting[fieldName] = fieldOrder;
        // Reload result set (starting at the first page)
        queuePageChange(jsTable, 0, true);
        return true;
    }

    function setSelected(jsTable, selectKey, enabled) {
        if (jsTable.jqDataTable.selectionAdd !== false) {
            for (var index = 0; index < jsTable.jqDataTable.selectionAdd.length; index++) {
                if (isSelectKeyEqual(selectKey, jsTable.jqDataTable.selectionAdd[index])) {
                    if (enabled) {
                        // Already selected, nothing to do.
                        return true;
                    } else {
                        // Remove selection
                        jsTable.jqDataTable.selectionAdd.splice(index, 1);
                        // Update global checkbox
                        onSelectionChanged(jsTable);
                        return true;
                    }
                }
            }
            if (enabled) {
                // Add selection
                jsTable.jqDataTable.selectionAdd.push(selectKey);
                // Update global checkbox
                onSelectionChanged(jsTable);
            }
            return true;
        } else if (jsTable.jqDataTable.selectionSub !== false) {
            for (var index = 0; index < jsTable.jqDataTable.selectionSub.length; index++) {
                if (isSelectKeyEqual(selectKey, jsTable.jqDataTable.selectionSub[index])) {
                    if (enabled) {
                        // Remove deselection (select item)
                        jsTable.jqDataTable.selectionSub.splice(index, 1);
                        // Update global checkbox
                        onSelectionChanged(jsTable);
                        return true;
                    } else {
                        // Already deselected, nothing to do.
                        return true;
                    }
                }
            }
            if (!enabled) {
                // Add deselection (deselect item)
                jsTable.jqDataTable.selectionSub.push(selectKey);
                // Update global checkbox
                onSelectionChanged(jsTable);
            }
            return true;
        }
        return false;
    }

    function setSelectedAll(jsTable, enabled) {
        if (typeof enabled == "undefined") {
            // Toggle if no argument given
            if ((jsTable.jqDataTable.selectionSub !== false) && (jsTable.jqDataTable.selectionSub.length === 0) ||
                (jsTable.jqDataTable.selectionAdd !== false) && (jsTable.jqDataTable.selectionAdd.length >= jsTable.jqDataTable.resultCount)) {
                enabled = false;    // All selected, deselect.
            } else {
                enabled = true;     // Not all selected, select.
            }
        }
        if (enabled) {
            // Enable all checkboxes
            jsTable.jqDataTable.selectionAdd = false;
            jsTable.jqDataTable.selectionSub = [];
            // Get select keys
            getSelectKeys(jsTable);
        } else {
            // Disable all checkboxes
            jsTable.jqDataTable.selectionAdd = [];
            jsTable.jqDataTable.selectionSub = false;
        }
        // Update checkboxes
        onQueryPageInitialize(jsTable, jQuery(jsTable).children("tbody")[0], true);
        // Update global checkbox
        onSelectionChanged(jsTable)
    }

    function queuePageChange(jsTable, pageIndex, pagesChanged, queryChanged, changeDelay) {
        // Show loading bar (if not already visible)
        onLoadingStart(jsTable);
        // Default value for delay
        if (typeof changeDelay == "undefined") {
            changeDelay = 1000;
        }
        // Cancel pending page changes
        if (jsTable.jqDataTable.queuePageChangeTimer !== false) {
            window.clearTimeout(jsTable.jqDataTable.queuePageChangeTimer);
            jsTable.jqDataTable.queuePageChangeTimer = false;
        }
        // Create new timeout to change the page after the delay
        jsTable.jqDataTable.queuePageChangeTimer = window.setTimeout(function() {
            setPage(jsTable, pageIndex, pagesChanged, queryChanged);
        }, changeDelay);
    }

    function utilTriggerEvent(element, event) {
        if ("createEvent" in document) {
            var eventOnChange = document.createEvent("HTMLEvents");
            eventOnChange.initEvent(event, false, true);
            element.dispatchEvent(eventOnChange);
        } else {
            element.fireEvent("on"+event);
        }
    }

    if (typeof componentHandler != "undefined") {
        componentHandler.registerUpgradedCallback("MaterialDataTable", function(jsTable) {
            jQuery(jsTable).dataTable();
        });
    }

}(jQuery, (typeof componentHandler == "undefined" ? undefined : componentHandler)));
