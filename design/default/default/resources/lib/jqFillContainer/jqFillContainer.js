/**
 * Created by Forsaken on 06.11.14.
 */

(function($) {
    "use strict";
    
    var documentFullyLoaded = false;
    jQuery(window).load(function() {
        documentFullyLoaded = true;
    });
    
    $.fn.fillContainer = function(parameters) {
        if (typeof parameters === "string") {
            $(this).each(function() {
                if (typeof this.jqFillContainer !== "undefined") {
                    var settings = this.jqFillContainer;
                    // TODO: Add actions
                }
            });
        } else {
            if (typeof parameters == "undefined") {
                parameters = {};
            }
            if (jQuery(this).length > 0) {
                var jsContainer = jQuery(this)[0];
                initFillHeight(jsContainer, parameters);
            }
        }
    };
    
    $.fn.fillContainer.getElementSize = function(element) {
        element = jQuery(element)[0];
        var computedStyle = window.getComputedStyle(element);
        var result = {
            blockHeight: parseFloat(computedStyle.height),
            blockWidth: parseFloat(computedStyle.width)
        };
        result.borderHeight = parseFloat(computedStyle.borderTopWidth) + parseFloat(computedStyle.borderBottomWidth);
        result.borderWidth = parseFloat(computedStyle.borderLeftWidth) + parseFloat(computedStyle.borderLeftWidth);
        result.paddingHeight = parseFloat(computedStyle.paddingTop) + parseFloat(computedStyle.paddingBottom);
        result.paddingWidth = parseFloat(computedStyle.paddingLeft) + parseFloat(computedStyle.paddingLeft);
        result.contentHeight = result.blockHeight - result.borderHeight - result.paddingHeight;
        result.contentWidth = result.blockWidth - result.borderWidth - result.paddingWidth;
        result.marginHeight = parseFloat(computedStyle.marginTop) + parseFloat(computedStyle.marginBottom);
        result.marginWidth = parseFloat(computedStyle.marginLeft) + parseFloat(computedStyle.marginLeft);
        result.spacingHeight = result.borderHeight + result.marginHeight;
        result.spacingWidth = result.borderWidth + result.marginWidth;
        result.realHeight = result.contentHeight + result.spacingHeight + result.paddingHeight;
        result.realWidth = result.contentWidth + result.spacingWidth + result.paddingWidth;
        return result;
    }

    function initFillHeight(container, settings) {
        if (settings.elementResize === false) {
            console.log("Please configure an 'elementResize' that will be resized to fill the container!");
            return false;
        }
        // Merge default settings with overrides and store within element
        settings = $.extend({
            elementResize: false,
            defaultHeight: false,
            defaultWidth: false,
            fillHeight: true,
            fillWidth: false,
            fillImages: false,
            fillOrientation: 'vertical',
            onInitialize: false,
            onUpdate: false,
            waitUntilLoaded: false
        }, settings);
        container.jqFillContainer = settings;
        if (settings.waitUntilLoaded) {
            if (documentFullyLoaded) {
                initFillFinish(container, settings);
            } else {
                jQuery(window).load(function() {
                    initFillFinish(container, settings);
                });
            }
        } else {
            var autoSize = ((settings.defaultHeight === false) || (settings.defaultWidth === false));
            initFillFinish(container, settings);
            // Update again after everything is loaded
            jQuery(window).load(function() {
                if (autoSize) {
                    initFillSize(container, settings);
                }
                updateFill(container);
            });
        }
    };

    function initFillSize(container, settings) {
        // Remove fixed size on container
        jQuery(container).css({ height: "auto", width: "auto" });
        // Get resizable item size
        var containerSize = $.fn.fillContainer.getElementSize(container);
        var elementSize = $.fn.fillContainer.getElementSize(settings.elementResize);
        // Get height/width of the container without the resizable element
        if (settings.orientation == "vertical") {
            settings.defaultHeight = containerSize.realHeight - elementSize.realHeight;
            settings.defaultWidth = containerSize.contentWidth;
        } else {
            settings.defaultHeight = containerSize.contentHeight;
            settings.defaultWidth = containerSize.realWidth - elementSize.realWidth;
        }
        //console.log("heightInit", "conR", containerSize.realHeight, "conC", containerSize.contentHeight, "el", elementSize.realHeight, "def", settings.defaultHeight);
    };

    function initFillFinish(container, settings) {
        // Store default size
        if ((settings.defaultHeight === false) || (settings.defaultWidth === false)) {
            initFillSize(container, settings);
        }
        // Get images original size
        if (settings.fillImages !== false) {
            getImageSize(jQuery(settings.elementResize).find(settings.fillImages));
        }
        // Bind window resize event
        jQuery(window).on("resize", function() {
            //console.log("sticky resize");
            updateFill(container);
        });
        if (settings.onInitialize !== false) {
            settings.onInitialize(container, settings);
        }
        updateFill(container);
    }
    
    function getImageSize(jqImages) {
        jQuery(jqImages).each(function() {
            var jsImage = this;
            jsImage.jqFillContainer = {
                imageWidth: jQuery(this).width(), imageHeight: jQuery(this).height()
            }
            // Avoid any css influence
            $("<img/>") 
                .attr("src", $(jsImage).attr("src"))
                .load(function() {
                    jsImage.jqFillContainer.imageWidth = this.width;
                    jsImage.jqFillContainer.imageHeight = this.height;
                });
        });
    }
    
    
    function updateFill(container) {
        var jsContainer = jQuery(container)[0];
        var settings = jsContainer.jqFillContainer;
        if (typeof settings === "undefined") {
            return false;
        }
        if (settings.onUpdate !== false) {
            settings.onUpdate(container, settings);
        }
        var elementSize = $.fn.fillContainer.getElementSize(settings.elementResize);
        if (settings.fillHeight) {
            var targetHeight = jQuery(container).height() - settings.defaultHeight;
            var elementHeight = elementSize.contentHeight;
            if (targetHeight !== elementHeight) {
                //console.log("height", "def", settings.defaultHeight, "new", targetHeight, "el", elementHeight);
                jQuery(settings.elementResize).height(targetHeight);
            }
        }
        if (settings.fillWidth) {
            var targetWidth = jQuery(container).width() - settings.defaultWidth;
            var elementWidth = elementSize.contentWidth;
            if (targetWidth !== elementWidth) {
                //console.log("width", settings.defaultWidth, targetWidth, elementWidth);
                jQuery(settings.elementResize).width(targetWidth);
            }
        }
        // Fill images?
        if (settings.fillImages !== false) {
            // Get new element size
            elementSize = $.fn.fillContainer.getElementSize(settings.elementResize);
            // Get images to be resized to fit the element
            var elementRatio = elementSize.contentWidth / elementSize.contentHeight;
            var elementImages = $(settings.elementResize).find(settings.fillImages);
            elementImages.each(function() {
                if (typeof this.jqFillContainer !== "undefined") {
                    var imageRatio = this.jqFillContainer.imageWidth / this.jqFillContainer.imageHeight;
                    //console.log("image", "rat", imageRatio, "width", this.jqFillContainer.imageWidth, "height", this.jqFillContainer.imageHeight);
                    // Element is wider than image?
                    if (elementRatio > imageRatio) {
                        // Scale by width
                        var imageHeight = elementSize.contentWidth / imageRatio;
                        var imageHeightOverflow = (imageHeight - elementSize.contentHeight)
                        jQuery(this).css({
                            "width": elementSize.contentWidth, "height": imageHeight,
                            "margin-top": "-" + (imageHeightOverflow / 2) + "px", "margin-left": "0px"
                        });
                    } else {
                        // Scale by height
                        var imageWidth = elementSize.contentHeight * imageRatio;
                        var imageWidthOverflow = (imageWidth - elementSize.contentWidth)
                        jQuery(this).css({
                            "width": imageWidth, "height": elementSize.contentHeight,
                            "margin-top": "0px", "margin-left": "-"+(imageWidthOverflow/2)+"px"
                        });
                    }
                }
            });
        }
    }

}(jQuery));