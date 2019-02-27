(function ( $ ) {

    $.fn.fullBox = function(action) {
        if (typeof action == "undefined") {
            action = "init";
        }
        if (action == "init") {
            jQuery(this).each(function(e) {
                var designBox = this;
                // Bind load / resize event
                jQuery(window).resize(function (event) {
                    jQuery(designBox).fullBox("refresh");
                });
                jQuery(window).load(function (event) {
                    jQuery(designBox).fullBox("refresh");
                });
                // Refresh when nested tabs get changed
                $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
                    jQuery(designBox).fullBox("refresh");
                });
            });
        }
        if ((action == "refresh") || (action == "init")) {
            var designBoxSourceSelector = jQuery(this).attr("data-source-info");
            var designBoxSource = (typeof designBoxSourceSelector == "undefined" ? jQuery(this).parent() : jQuery(designBoxSourceSelector));
            var height = designBoxSource.outerHeight();
            jQuery(this).css({'height':height+'px'});
        }
    };
    
    
}( jQuery ));

jQuery(function() {
    jQuery("[data-display='fullbox']").fullBox();
});