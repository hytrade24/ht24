/**
 * Created by Forsaken on 06.11.14.
 */

(function($) {
    "use strict";
    
    $.fn.loadImages = function(parameters) {
        if (typeof parameters === "string") {
            $(this).each(function() {
                if (typeof this.jqLoadImages !== "undefined") {
                    var settings = this.jqLoadImages;
                    switch (parameters) {
                        case "finish":
                            loadingDone(this, settings)
                            break;
                    }
                }
            });
        } else {
            if (typeof parameters == "undefined") {
                parameters = {};
            }
            var jsContainer = jQuery(this)[0];
            initLoadImages(jsContainer, parameters);
        }
    };
    
    function isImageLoaded(jsImage) {
        if (!jsImage.conplete || (typeof img.naturalWidth != "undefined" && jsImage.naturalWidth == 0)) {
            return false;
        }
        return true;
    }

    function initLoadImages(container, settings) {
        // Merge default settings with overrides and store within element
        settings = $.extend({
            container: container,
            eventLoaded: false,
            eventDone: false,
            finishManually: false,
            fadeIn: 400,
            fadeOut: 400,
            images: false,
            imagesCount: false,
            loadingProgress: 0,
            loadingBar: false,
            loadingWidth: false
        }, settings);
        // Check required options 
        if (settings.images === false) {
            console.log("Please configure 'images' that will be waited for to load!");
            return false;
        } else if (typeof settings.images === "string") {
            settings.image = jQuery(settings.images);
        }
        settings.imagesCount = settings.image.length;
        if (settings.loadingBar !== false) {
            if (settings.loadingWidth === false) {
                settings.loadingWidth = jQuery(container).find(settings.loadingBar).parent().innerWidth();
            }
        }
        // Initialize
        container.jqLoadImages = settings;
        // Update state
        loadingUpdate(container, settings);
        if (settings.fadeIn !== false) {
            jQuery(container).fadeIn(settings.fadeIn);
        } else {
            jQuery(container).show();
        }
        // Update images
        jQuery(settings.images).each(function() {
            if (isImageLoaded(this)) {
                loadingDoneImage(container, settings, this);
            } else {
                jQuery(this).load(function() {
                    loadingDoneImage(container, settings, this);
                });
            }
        });
    };

    function loadingDoneImage(container, settings, image) {
        settings.images = jQuery(settings.images).not(image);
        if (settings.images.length > 0) {
            // Update progress
            var imagesLoaded = settings.imagesCount - settings.images.length;
            settings.loadingProgress = (imagesLoaded * 100 / settings.imagesCount);
            loadingUpdate(container, settings);
        } else {
            settings.loadingProgress = 100;
            loadingUpdate(container, settings);
            if (settings.eventLoaded !== false) {
                settings.eventLoaded(container, settings);
            }
            if (!settings.finishManually) {
                loadingDone(container, settings);
            }
        }
    }

    function loadingDone(container, settings) {
        if (settings.fadeOut !== false) {
            jQuery(container).fadeOut(settings.fadeOut);
        } else {
        jQuery(container).hide();
    }
        if (settings.eventDone !== false) {
            settings.eventDone(container, settings);
        }
    }

    function loadingUpdate(container, settings) {
        jQuery(container).find(settings.loadingBar).css({
            'overflow': "hidden",
            'width': settings.loadingProgress+"%"
        });
    }

}(jQuery));