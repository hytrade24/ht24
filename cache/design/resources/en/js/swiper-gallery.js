
/* ###VERSIONSBLOCKINLCUDE### */

function swiperGallerySlider(elements, extraCss, swiperOptions) {
    var index = 0;
    var html = "";
    if ((typeof extraCss == "undefined") || (extraCss == "")) {
        extraCss = "";
    } else {
        extraCss = " "+extraCss;
    }
    var swiperOptionsMerged = {
        slidesPerView:'auto',
        watchActiveIndex:true,
        centeredSlides:true,
        initialSlide: 0,
        pagination:'.pagination',
        paginationClickable:true,
        resizeReInit:true,
        mouseWheelControl: true,
        mouseWheelDelta: 0,
        mouseWheelThreshold: 1,
        keyboardControl:true,
        grabCursor:true,
        simulateTouch:false
    };
    if (typeof swiperOptions != "undefined") {
        for (var attr in swiperOptions) {
            swiperOptionsMerged[attr] = swiperOptions[attr];
        }
    }
    jQuery(elements).each(function() {
        var imageUrl = jQuery(this).attr("href");
        var imageDesc = jQuery(this).attr("data-title");
        html +=
            '<div class="swiper-slide" data-index="' + index + '">' +
                '<div class="inner">' +
                    '<div class="image-container">' +
                        (typeof imageDesc != "undefined" ? '<div class="description">' + imageDesc + '</div>' : '') +
                        '<img src="' + imageUrl + '" />' +
                    '</div>' +
                '</div>' +
            '</div>';
        index++;
    });
    var loading = 
        '<div class="design-gallery-loading-wrapper">' +
            '<div class="design-gallery-loading">' +
                '<div class="design-gallery-loading-text">' +
                    '<span class="design-gallery-loading-text-back">' +
                            '<img src="/cache/design/resources/de/images/logo.png" alt="Loading...">' +
                            '<div class="design-gallery-loading-text-fill">' +
                                '<img src="/cache/design/resources/de/images/logo.png" alt="Loading...">' +
                            '</div>' +
                    '</span>' +
                '</div>' +
            '</div>' +
        '</div>';
    var buttonsAbsolute = '<a data-action="close">Close</a>';
    var buttonsLeft = '';
    var buttonsRight = '';
    if (index > 1) {
        buttonsLeft =
            '<div class="visible-desktop">' +
                '<a data-action="prevSlide" class="btn btn-default">Previous image</a> ' +
            '</div>';
        buttonsRight =
            '<div class="visible-desktop">' +
                '<a data-action="nextSlide" class="btn btn-default">Next image</a>' +
            '</div>';
    }
    html =
        '<div class="swiper-container'+extraCss+'">' + loading +
            '<div class="buttonsAbsolute">' + buttonsAbsolute + '</div>' +
            '<div class="buttonsLeft">' + buttonsLeft + '</div>' +
            '<div class="buttonsRight">' + buttonsRight + '</div>' +
            '<div class="pagination"></div>' +
            '<div class="swiper-wrapper">' +
            html +
            '</div>' +
            '</div>';
    var swiperGalleryContainer = jQuery("#swiperGalleryContainer");
    if (swiperGalleryContainer.length == 0) {
        jQuery("body").append('<div id="swiperGalleryContainer"></div>');
        swiperGalleryContainer = jQuery("#swiperGalleryContainer");
        // Close by escape
        jQuery(document).keyup(function(e) {
            if ((e.which == 27) && (swiperGalleryContainer.is(":visible"))) {
                swiperGallerySliderHide()
            }
        });
        // Close by browser back
        jQuery(window).bind("hashchange", function(e) {
            if ((document.location.hash != "#gallery") && swiperGalleryContainer.is(":visible")) {
                swiperGallerySliderHide()
            }
        });
        // Resize fix
        jQuery(window).resize(function() {
            swiperResizeFix("#swiperGalleryContainer");
        });
    }
    // Show container
    document.location.hash = "#gallery";
    swiperGalleryContainer.show();
    // Initialize swiper
    var swiperObject = swiperGalleryContainer.html(html).find(".swiper-container").swiper(swiperOptionsMerged);
    // Show loading notice until images are loaded
    jQuery(".design-gallery-loading-wrapper").loadImages({
        eventLoaded: function(container, settings) {
            jQuery(".design-gallery-loading-wrapper").loadImages("finish");
        },
        eventDone: function(container, settings) {
            // Ensure the correct slide is active
            swiperObject.swipeTo(swiperOptionsMerged.initialSlide);
        },
        images: "#swiperGalleryContainer img",
        loadingBar: ".design-gallery-loading-text-fill",
        finishManually: true
    });
    // Resize fix
    swiperResizeFix("#swiperGalleryContainer");
    // Switch slide by clicking on it
    swiperGalleryContainer.find(".swiper-slide").click(function(event) {
        var slideIndex = jQuery(this).attr("data-index");
        if (swiperObject.activeIndex == slideIndex) {
            if (event.offsetX < jQuery(this).width() / 2) {
                // Clicked on active slide (left half), show prev
                swiperObject.swipePrev();
            } else {
                // Clicked on active slide (right half), show next
                swiperObject.swipeNext();
            }
        } else {
            // Clicked on inactive slide, show clicked slide
            swiperObject.swipeTo(slideIndex);
        }
    });
    if (swiperOptionsMerged.mouseWheelControl) {
        // Mousewheel navigation
        swiperGalleryContainer.on("mousewheel", function (event) {
            event.preventDefault();
            var jsEvent = event.originalEvent;
            var deltaNormalized = 0;
            if (jsEvent.detail) {
                if (deltaNormalized) {
                    deltaNormalized = (jsEvent.wheelDelta / jsEvent.detail / 40 * jsEvent.detail > 0 ? 1 : -1);
                } else {
                    deltaNormalized = -jsEvent.detail / 3;
                }
            } else {
                deltaNormalized = jsEvent.wheelDelta / 120;
            }
            swiperOptionsMerged.mouseWheelDelta += deltaNormalized;
            if (Math.abs(swiperOptionsMerged.mouseWheelDelta) >= swiperOptionsMerged.mouseWheelThreshold) {
                swiperOptionsMerged.mouseWheelDelta = 0;
                if (event.originalEvent.wheelDelta < 0) {
                    swiperObject.swipeNext();
                }
                if (event.originalEvent.wheelDelta > 0) {
                    swiperObject.swipePrev();
                }
            }
        });
    }
    // Buttons for prev/next slide and closing
    swiperGalleryContainer.find("[data-action=prevSlide]").click(function() {
        swiperObject.swipePrev();
    });
    swiperGalleryContainer.find("[data-action=nextSlide]").click(function() {
        swiperObject.swipeNext();
    });
    swiperGalleryContainer.find("[data-action=close]").click(function() {
        swiperGallerySliderHide()
    });
}

function swiperGallerySliderHide() {
    var swiperGalleryContainer = jQuery("#swiperGalleryContainer");
    swiperGalleryContainer.hide();
    document.location.hash = "";
}

function swiperResizeFix(selector) {
    var width = jQuery(window).width();
    jQuery(selector).find(".swiper-slide").css("max-width", width+"px");
}

function swiperGalleryAutoload(selector) {
    var index = 0;
    jQuery(selector).each(function() {
        var slideIndex = index;
        jQuery(this).click(function(e) {
            swiperGallerySlider(selector, "", { initialSlide: slideIndex });
            e.preventDefault();
            return false;
        });
        index++;
    });
}

jQuery(function() {
    swiperGalleryAutoload("a[rel^=lightbox-gallery]");
});


(function($) {
    "use strict";

    $.fn.swiperGallery = function (parameters) {
        swiperGalleryAutoload(this);
    };
})(jQuery);