(function ( $ ) {

    function collapse(element, settings) {    
        if (typeof settings.callbackBeforeCollapse === "function") {
            settings.callbackBeforeCollapse(element, settings);
        }
        if (settings.hideChildren) {
            jQuery(element).children().show();
        }
        jQuery(element).children(settings.buttonCollapse).hide();
        jQuery(element).children(settings.buttonExpand).hide();
        if (jQuery(element).prop('scrollHeight') > settings.maxHeight) {
            // Content does require expanding
            jQuery(element).children(settings.buttonExpand).show();
            // Adjust height
            if (settings.css) {
                jQuery(element).css({
                    "max-height": settings.maxHeight+"px",
                    "overflow": "hidden"
                });
            }
            if (settings.hideChildren) {
                var jqChild = jQuery(element).children().not(settings.buttonCollapse).not(settings.buttonExpand).last();
                while ((jqChild.length > 0) && (jQuery(element).prop('scrollHeight') > settings.maxHeight)) {
                    jqChild.hide();
                    jqChild = jqChild.prev();
                }
            }
        }
        if (typeof settings.expandClass === "string") {
            jQuery(element).removeClass(settings.expandClass);
        }
        if (typeof settings.callbackAfterCollapse === "function") {
            settings.callbackAfterCollapse(element, settings);
        }
    }

    function expand(element, settings) {
        if (typeof settings.callbackBeforeExpand === "function") {
            settings.callbackBeforeExpand(element, settings);
        }
        if (settings.hideChildren) {
            jQuery(element).children().show();
        }
        jQuery(element).children(settings.buttonCollapse).hide();
        jQuery(element).children(settings.buttonExpand).hide();    
        if (settings.css) {
            jQuery(element).css({
                "max-height": settings.expandedMaxHeight,
                "overflow": settings.expandedOverflow
            });
        }
        if (typeof settings.expandClass === "string") {
            jQuery(element).addClass(settings.expandClass);
        }
        if (jQuery(element).prop('scrollHeight') > settings.maxHeight) {
            // Content does require expanding
            jQuery(element).children(settings.buttonCollapse).show();
        }
        if (typeof settings.callbackAfterExpand === "function") {
            settings.callbackAfterExpand(element, settings);
        }
    }

    $.fn.autoCollapse = function(parameters) {
        if (typeof parameters === "string") {
            jQuery(this).each(function() {
                if (typeof this.jqAutoCollapse !== "undefined") {
                    var settings = this.jqAutoCollapse;
                    if (parameters == "collapse") {
                        collapse(this, settings);
                    }
                    if (parameters == "expand") {
                        expand(this, settings);
                    }
                }
            });
        } else {
            if (typeof parameters == "undefined") {
                parameters = {};
            }
            jQuery(this).each(function() {
                var settings = jQuery.extend({
                    buttonCollapse: ".btn-collapse",
                    buttonExpand: ".btn-expand",
                    buttonsSetWidth: false,
                    callbackBeforeCollapse: false,
                    callbackBeforeExpand: false,
                    callbackAfterCollapse: false,
                    callbackAfterExpand: false,
                    css: true,
                    expanded: false,
                    expandedMaxHeight: "inherit",
                    expandedOverflow: "inherit",
                    expandClass: null,
                    hideChildren: false,
                    maxHeight: null
                }, parameters);
                if (settings.maxHeight === null) {
                    settings.maxHeight = jQuery(this).outerHeight(true);
                }
                if (jQuery(this).is("[data-collapse-scroll]")) {
                    settings.expandedMaxHeight = jQuery(this).attr("data-collapse-scroll");
                    settings.expandedOverflow = "auto";
                }
                this.jqAutoCollapse = settings;
                if (settings.buttonsSetWidth) {
                    var elementWidth = jQuery(this).innerWidth();
                    var jqButton = jQuery(this).children(settings.buttonCollapse);
                    if (jqButton.length > 0) {
                        var buttonSpacing = jqButton.outerWidth() - jqButton.innerWidth();
                        jqButton.css("width", (elementWidth-buttonSpacing)+"px");
                    }
                    jqButton = jQuery(this).children(settings.buttonExpand);
                    if (jqButton.length > 0) {
                        var buttonSpacing = jqButton.outerWidth() - jqButton.innerWidth();
                        jqButton.css("width", (elementWidth-buttonSpacing)+"px");
                    }
                }
                if (typeof settings.expanded == "function") {
                    collapse(this, settings);
                    settings.expanded = settings.expanded(this);
                    if (settings.expanded) {
                        expand(this, settings);
                    }
                } else {
                    if (settings.expanded) {
                        expand(this, settings);
                    } else {
                        collapse(this, settings);
                    }
                }
            });
        }
    };

}( jQuery ));