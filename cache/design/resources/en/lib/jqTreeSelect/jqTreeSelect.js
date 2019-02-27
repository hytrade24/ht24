/**
 * Created by Jens Niedling
 */

(function ( $ ) {
    "use strict";

    var defaultConfiguration = {
        ajaxUrl: false,
        cssTreeSelect: 'jqTreeSelect',
        cssTreeSelectDropdown: 'jqTreeSelectDropdown',
        selectOnlyNodes: true,
        name: "",
		defaultValue: "",
        searchCallback: function(searchText, node) {
            return (node.label.toLowerCase().indexOf(searchText.toLowerCase()) !== -1);
        },
        treeSettings: {	}
    };

    var dragNodes = false;
    var dragTarget = false;
    var dragTargetPosition = false;

    /**
     * Initialize tree element(s)
     * @param parameter
     * @returns {fn}
     */
    $.fn.jqTreeSelect = function (parameter, parameterMore) {
        var targetElements = this;
        var index;
        if (typeof parameter == "string") {
            for (index = targetElements.length - 1; index >= 0; index--) {
                var jsTreeSelect = targetElements[index];
                executeCommand(jsTreeSelect, parameter, parameterMore);
            }
            return;
        } else {
            if (typeof parameter != "object") {
                parameter = {};
            }
            var settings = jQuery.extend({}, defaultConfiguration, parameter);
            // Initialize tree(s)
            for (index = targetElements.length - 1; index >= 0; index--) {
                var jsTreeSelect = targetElements[index];
                initTreeSelect(jsTreeSelect, settings);
            }
        }
    };

    /**
     * Javascript command-api for performing actions to a jqTree element
     * @param treeSelect    The jqTree element
     * @param command       The command to be executed
     * @param parameters    The commands parameters
     */
    function executeCommand(treeSelect, command, parameters) {
        var jsTreeSelect = checkTreeSelect(treeSelect);
        log(command, jsTreeSelect);
        if (jsTreeSelect !== false) {
            var settings = jsTreeSelect.jqTreeSelect;
            switch (command) {
				case 'open':
					jQuery(".jqTreeSelect.open").removeClass("open");
                    jQuery(jsTreeSelect).addClass("open");
                    break;
                case 'close':
                    jQuery(jsTreeSelect).removeClass("open");
                    break;
                case 'toggle':
					if(jQuery(jsTreeSelect).is('.open')) {
						executeCommand(treeSelect, 'close', parameters);
					} else {
						executeCommand(treeSelect, 'open', parameters);
					}
                    break;
                case 'select':
                    jQuery(jsTreeSelect).children("input[type=text]").val(parameters.label);
                    jQuery(jsTreeSelect).children("input[type=hidden]").val(parameters.id);
                    jQuery(jsTreeSelect).jqTreeSelect("close");
                    break;
            }
        }
    };

    /**
     * Add a log-message with timestamp into the browsers console
     * @param type
     * @param message
     */
    function log(type, message) {
        console.log(new Date().toLocaleString(), "[jqTreeSelect|"+type+"]", message);
    }

    /**
     * Check if the element is a jqTreeSelect
     * @param element
     * @returns {*}
     */
    function checkTreeSelect(element) {
        var jsElement = jQuery(element)[0];
        if (typeof jsElement.jqTreeSelect !== "undefined") {
            return jsElement;
        }
        return false;
    }

    /**
     * Initialize jqTreeSelect element
     * @param jsTree
     * @param settings
     */
    function initTreeSelect(jsTreeSelect, settings) {
        // Read setting-attributes
        if (jQuery(jsTreeSelect).is("[data-name]")) {
            settings.name = jQuery(jsTreeSelect).attr("data-name");
        }
		if (jQuery(jsTreeSelect).is("[data-defaultvalue]")) {
			settings.defaultValue = jQuery(jsTreeSelect).attr("data-defaultvalue");
			settings.treeSettings.defaultValue = settings.defaultValue;

		}
        // Set tree settings
        if (settings.ajaxUrl !== false) {
            settings.treeSettings.ajaxUrl = settings.ajaxUrl;
        }
        settings.treeSettings.autoExpand = false;
        settings.treeSettings.onNodeSelect = function(jsNode) {
            if (typeof jsNode.jqTree !== "undefined") {
                if (!settings.selectOnlyNodes || !jsNode.jqTree.settings.expandable) {
                    var nodeId = jsNode.jqTree.settings.id;
                    var nodeLabel = jsNode.jqTree.settings.label;
                    jQuery(jsTreeSelect).jqTreeSelect("select", { id: nodeId, label: nodeLabel });
                    return true;
                } else if (jsNode.jqTree.settings.expandable) {
                    jQuery(jsNode).jqTree("toggle");
                    return false;
                }
            }
        };
        settings.treeSettings.readOnly = true;
        // Log init call
        // Prepare container element
        jsTreeSelect.jqTreeSelect = settings;
        jQuery(jsTreeSelect).addClass(settings.cssTreeSelect).html("");
        // Add hidden input
        jQuery(jsTreeSelect).append('<input type="hidden" name="'+settings.name+'" />');
        // Add select container
        jQuery(jsTreeSelect).append('<input type="text" />');
        jQuery(jsTreeSelect).children("input[type=text]").on("keyup", function() {
            updateSearch(jsTreeSelect, jQuery(this).val());
        });
        // Add tree container
        jQuery(jsTreeSelect).append('<div class="'+settings.cssTreeSelectDropdown+'"></div>');
        jQuery(jsTreeSelect).append('<div></div>');
        jQuery(jsTreeSelect).children().last().jqTree(settings.treeSettings);
        // Bind events
        jQuery(jsTreeSelect).children("."+settings.cssTreeSelectDropdown).click(function(event) {
            jQuery(jsTreeSelect).jqTreeSelect("toggle");
        });


    }

    function updateSearch(jsTreeSelect, text) {
        var settings = jsTreeSelect.jqTreeSelect;
        if (settings.searchCallback !== false) {
            var jsInputValue = jQuery(jsTreeSelect).children("input[type=hidden]");
            jsInputValue.val("");
            jQuery(jsTreeSelect).children().last().find("li[data-id]").each(function() {
                if (typeof this.jqTree !== "undefined") {
                    if (settings.searchCallback(text, this.jqTree.settings)) {
                        jQuery(this).show().parents("li[data-id]").show();
                    } else {
                        jQuery(this).hide();
                    }
                }
                console.log("Filter: "+text);
            });
            jQuery(jsTreeSelect).jqTreeSelect("open");
        }
    }

}( jQuery ));
