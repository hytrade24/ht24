
/* ###VERSIONSBLOCKINLCUDE### */

(function($) {
    var getMapPinIcon = function(color, width, height) {
        if (typeof width == "undefined") {
            width = 32;
        }
        if (typeof height == "undefined") {
            height = 32;
        }
        return {
            scaledSize: { width: width, height: height },
            url: "http://maps.google.com/mapfiles/ms/icons/"+color+"-dot.png"
        };
    }

    var optionsDefault = {
        autoRefresh: true,
        ident: "none",
        limit: 50,
        lazyReload: 500,
        searchForm: null,
        icons: {
            default: getMapPinIcon("red"),
            active: getMapPinIcon("yellow")
        },
        map: {
            mapTypeControl: false,
            panControl: false,
            zoom: 4,
            zoomMax: 15,
            zoomControlOptions: { position: 4 },
            center: { lat: 0, lng: 0 }
        }
    };

    mapInit = function(mapContainerJs, options) {
        // Get options
        if (typeof options != "undefined") {
            options = jQuery.merge({}, optionsDefault, options);
        } else {
            options = optionsDefault;
        }
        // Get options from data attributes
        var mapContainer = jQuery(mapContainerJs);
        if (mapContainer.is("[data-ident]")) {
            options.ident = mapContainer.attr("data-ident");
        }
        if (mapContainer.is("[data-limit]")) {
            options.limit = mapContainer.attr("data-limit");
        }
        if (mapContainer.is("[data-search-form]")) {
            options.searchForm = mapContainer.attr("data-search-form");
        }
        if (mapContainer.is("[data-map-zoom-max]") && (mapContainer.attr("data-map-zoom-max") > 0)) {
            options.map.zoomMax = mapContainer.attr("data-map-zoom-max");
        }
        // Initialize map
        var mapObject = new google.maps.Map(mapContainerJs, options.map);
        // Bind objects and options
        mapContainerJs.jqMapAjax = {
            controlAutoRefresh: null,
            controlMaximize: null,
            controlSearchForm: null,
            controlList: null,
            controlListPager: null,
            dirty: true,
            listOptions: { addBounds: false },
            isAutoPanning: false,
            isResizing: false,
            markerBounds: null,
            maximized: false,
            googleMap: mapObject,
            googleMapInfoWindow: new google.maps.InfoWindow({ content: 'Loading...' }),
            googleMapMarkers: [],
            lazyReloadTimer: null,
            options: options
        };
        // Check for linked search form
        if (options.searchForm !== null) {
            mapContainerJs.jqMapAjax.controlSearchForm = jQuery(options.searchForm);
            mapContainerJs.jqMapAjax.controlSearchForm.ebizSearch("submit", mapSearchUpdate.bind(mapContainerJs));
        }
        // Check for extra controls within outer element
        var mapContainerOuter = mapContainer.parent();
        var mapControlMaximize = mapContainerOuter.find("[data-action=map-ajax-maximize],[data-action=map-ajax-minimize]");
        if (mapControlMaximize.length > 0) {
            mapContainerJs.jqMapAjax.controlMaximize = mapControlMaximize;
            mapControlMaximize.on("click", function(event) {
                mapMaximizeToggle(mapContainerJs, mapContainerOuter);
            });
        }
        var mapControlAutoRefresh = mapContainerOuter.find("[data-action=map-ajax-autorefresh]");
        if (mapControlAutoRefresh.length > 0) {
            mapContainerJs.jqMapAjax.controlAutoRefresh = mapControlAutoRefresh;
            mapControlAutoRefresh.on("change", function(event) {
                mapSetAutoRefresh(mapContainerJs, jQuery(this).prop("checked"));
            });
        }
        var mapControlList = mapContainerOuter.find("[data-content=map-ajax-list]");
        if (mapControlList.length > 0) {
            mapContainerJs.jqMapAjax.controlList = mapControlList;
        }
        var mapControlListPager = mapContainerOuter.find("[data-content=map-ajax-list-pager]");
        if (mapControlListPager.length > 0) {
            mapContainerJs.jqMapAjax.controlListPager = mapControlListPager;
        }
        // Bind google map events
        google.maps.event.addListener(mapObject, 'bounds_changed', function() {
            if (mapContainerJs.jqMapAjax.isAutoPanning || mapContainerJs.jqMapAjax.isResizing) {
                // Map change not initiated by user! Dont trigger refresh!
                return;
            }
            if (mapContainerJs.jqMapAjax.maximized && mapContainerJs.jqMapAjax.options.autoRefresh) {
                // USer changed the map bounds, update results
                mapRefreshLazy(mapContainerJs);
            }
        });
        google.maps.event.addListener(mapObject, 'idle', function() {
            mapIdle(mapContainerJs);
        });
        if (mapContainerJs.jqMapAjax.dirty) {
            mapLoadPage(mapContainerJs, mapContainerJs.jqMapAjax.listOptions);
        }
    };

    mapSearchUpdate = function(searchForm) {
        var mapContainerJs = this;
        if (!mapContainerJs.jqMapAjax.maximized) {
            // Leave search form as it is, if map is not maximized
            return true;
        }
        var category = parseInt( jQuery(searchForm).ebizSearch("searchCategory") );
        var hash = jQuery(searchForm).ebizSearch("searchHash");
        if ((typeof category == "number") && (typeof hash == "string")) {
            var resultCount = jQuery(searchForm).ebizSearch("searchResultCount");
            mapContainerJs.jqMapAjax.options.ident = "marktplatz,"+category+",,"+hash;
            mapLoadPage(mapContainerJs, { addBounds: false }, resultCount);
        }
        return false;
    };

    mapCenter = function(mapContainerJs) {
        if (mapContainerJs.jqMapAjax.markerBounds !== null) {
            mapContainerJs.jqMapAjax.isResizing = true;
            mapContainerJs.jqMapAjax.googleMap.fitBounds( mapContainerJs.jqMapAjax.markerBounds );
        }
    };

    mapMaximizeToggle = function(mapContainerJs, mapContainerOuter) {
        var mapObject = mapContainerJs.jqMapAjax.googleMap;
        mapContainerJs.jqMapAjax.isResizing = true;
        mapContainerJs.jqMapAjax.maximized = !mapContainerJs.jqMapAjax.maximized;
        if (mapContainerJs.jqMapAjax.maximized) {
            mapContainerOuter.addClass("maximized");
            jQuery("body").addClass("modal-open");
        } else {
            mapContainerOuter.removeClass("maximized");
            jQuery("body").removeClass("modal-open");
        }
        window.setTimeout(function() {
            google.maps.event.trigger(mapObject, "resize");
            mapCenter(mapContainerJs);
        });
    };

    mapIdle = function(mapContainerJs, param) {
        mapContainerJs.jqMapAjax.isAutoPanning = false;
        mapContainerJs.jqMapAjax.isResizing = false;
    };

    mapClearMarkers = function(mapContainerJs, param) {
        // Clear markers
        for (var i = 0; i < mapContainerJs.jqMapAjax.googleMapMarkers.length; i++) {
            mapContainerJs.jqMapAjax.googleMapMarkers[i].setMap(null);
        }
        mapContainerJs.jqMapAjax.googleMapMarkers = [];
        // Clear list view
        if (mapContainerJs.jqMapAjax.controlList !== null) {
            mapContainerJs.jqMapAjax.controlList.html("");
            mapContainerJs.jqMapAjax.controlList.scrollTop(0);
        }
        if (mapContainerJs.jqMapAjax.controlListPager !== null) {
            mapContainerJs.jqMapAjax.controlListPager.html("");
        }
    };

    mapLoadPage = function(mapContainerJs, param, resultCount) {
        mapContainerJs.jqMapAjax.listOptions = param;
        var options = mapContainerJs.jqMapAjax.options;
        var mapObject = mapContainerJs.jqMapAjax.googleMap;
        var mapPageOffset = (typeof param != "undefined" && typeof param.offset != "undefined" ? parseInt(param.offset) : 0);
        var mapPageParams = "mapIdent="+encodeURIComponent(options.ident)+"&limit="+encodeURIComponent(options.limit)+"&offset="+mapPageOffset;
        if (typeof resultCount != "undefined") {
            mapPageParams += "&resultCount=".resultCount;
        }
        if (param.addBounds) {
            var mapBounds = mapObject.getBounds();
            var mapBoundsNE = mapBounds.getNorthEast();
            var mapBoundsSW = mapBounds.getSouthWest();
            mapPageParams +=
                "&latMin="+encodeURIComponent(mapBoundsNE.lat())+"&lngMin="+encodeURIComponent(mapBoundsNE.lng())+
                "&latMax="+encodeURIComponent(mapBoundsSW.lat())+"&lngMax="+encodeURIComponent(mapBoundsSW.lng());
        }
        jQuery(mapContainerJs).parent().addClass("loading");
        if (mapContainerJs.jqMapAjax.controlList !== null) {
            mapContainerJs.jqMapAjax.controlList.parent().addClass("loading");
        }
        window.setTimeout(function() {
            // Start loading after UI update
            jQuery.post("index.php?pluginAjax=GeoRegion&pluginAjaxAction=getMarkers", mapPageParams, function(result) {
                mapClearMarkers(mapContainerJs);
                mapLoadMarkers(mapContainerJs, result, param.addBounds);
                jQuery(mapContainerJs).parent().removeClass("loading");
                if (mapContainerJs.jqMapAjax.controlList !== null) {
                    mapContainerJs.jqMapAjax.controlList.parent().removeClass("loading");
                }
            });
        });
    };

    mapHighlightMarker = function(mapContainerJs, marker, highlighted, updateZIndex, updateListScroll) {
        if (highlighted) {
            marker.setIcon(mapContainerJs.jqMapAjax.options.icons.active);
            if (updateZIndex) {
                marker.setZIndex(10);
            }
            marker.row.addClass("map-hover");
        } else {
            marker.setIcon(marker.iconDefault);
            if (updateZIndex) {
                marker.setZIndex(1);
            }
            marker.row.removeClass("map-hover");
        }
        // Move map to marker (if not visible)
        var mapBounds = mapContainerJs.jqMapAjax.googleMap.getBounds();
        if (!mapBounds.contains(marker.getPosition())) {
            mapBounds.extend(marker.getPosition());
            mapContainerJs.jqMapAjax.isAutoPanning = true;
            mapContainerJs.jqMapAjax.googleMap.fitBounds(mapBounds);
            mapContainerJs.jqMapAjax.markerBounds = mapBounds;
        }
        if ((typeof updateListScroll != "undefined") && updateListScroll) {
            // Scroll list to entry (if not visible)
            var jqItem = jQuery(marker.row);
            var jqItemList = jQuery(mapContainerJs.jqMapAjax.controlList).parent();
            var scrollOffset = jqItemList.scrollTop();
            var scrollContentMax = jqItemList.height();
            var scrollBoxMin = jqItem.position().top;
            var scrollBoxMax = scrollBoxMin + jqItem.height();
            if (scrollBoxMin < 0) {
                jqItemList.scrollTop( scrollOffset + scrollBoxMin );
            } else if (scrollBoxMax > scrollContentMax) {
                jqItemList.scrollTop( scrollOffset + (scrollBoxMax - scrollContentMax + 16) );
            }
        }
    };

    mapLoadMarkers = function(mapContainerJs, result, limitedByBounds) {
        if (typeof limitedByBounds == "undefined") {
            limitedByBounds = false;
        }
        var options = mapContainerJs.jqMapAjax.options;
        var mapObject = mapContainerJs.jqMapAjax.googleMap;
        var mapInfoWindow = mapContainerJs.jqMapAjax.googleMapInfoWindow;
        var mapBounds = null;
        var resultMarker, resultRow;
        for (var i = 0; i < result.list.length; i++) {
            if (mapContainerJs.jqMapAjax.controlList !== null) {
                resultRow = mapContainerJs.jqMapAjax.controlList.append(result.list[i].row).children().last();
            } else {
                resultRow = null;
            }
            resultMarker = new google.maps.Marker({
                position: new google.maps.LatLng(result.list[i].position.lat, result.list[i].position.lng),
                map: mapObject,
                title: result.list[i].title,
                icon: options.icons.default,
                iconDefault: options.icons.default,
                info: result.list[i].marker,
                row: resultRow,
                zIndex: 1
            });
            google.maps.event.addListener(resultMarker, 'click', function() {
                mapInfoWindow.close();
                mapInfoWindow.setContent(this.info);
                // Show window!
                mapContainerJs.jqMapAjax.isAutoPanning = true;
                mapInfoWindow.open(mapObject, this);
            });
            if (resultRow !== null) {
                // Result list hover
                resultRow[0].googleMapMarker = resultMarker;
                resultRow.on("mouseover", function() {
                    mapHighlightMarker(mapContainerJs, this.googleMapMarker, true, true);
                });
                resultRow.on("mouseout", function() {
                    mapHighlightMarker(mapContainerJs, this.googleMapMarker, false, true);
                });
                // Map hover
                var currentRow = resultRow;
                google.maps.event.addListener(resultMarker, 'mouseover', function() {
                    mapHighlightMarker(mapContainerJs, this, true, false, true);
                });
                google.maps.event.addListener(resultMarker, 'mouseout', function() {
                    mapHighlightMarker(mapContainerJs, this, false, false, true);
                });
            }
            if (!limitedByBounds) {
                if (mapBounds === null) {
                    mapBounds = new google.maps.LatLngBounds(resultMarker.position, resultMarker.position);
                } else {
                    mapBounds.extend(resultMarker.position);
                }
            }
            mapContainerJs.jqMapAjax.googleMapMarkers.push(resultMarker);
        }
        if (mapBounds !== null) {
            var mapBoundsNE = mapBounds.getNorthEast();
            var mapBoundsSW = mapBounds.getSouthWest();
            var mapBoundsCenter = mapBounds.getCenter();
            var scaleLat = Math.abs(mapBoundsNE.lat() - mapBoundsSW.lat());
            var scaleLon = Math.abs(mapBoundsNE.lng() - mapBoundsSW.lng());
            var scaleMin = 100 / Math.pow(2, mapContainerJs.jqMapAjax.options.map.zoomMax);
            if (scaleLat < scaleMin) {
                mapBounds.extend( new google.maps.LatLng(mapBoundsCenter.lat() - (scaleMin / 2), mapBoundsCenter.lng()) );
                mapBounds.extend( new google.maps.LatLng(mapBoundsCenter.lat() + (scaleMin / 2), mapBoundsCenter.lng()) );
            }
            if (scaleLon < scaleMin) {
                mapBounds.extend( new google.maps.LatLng(mapBoundsCenter.lat(), mapBoundsCenter.lng() - (scaleMin / 2)) );
                mapBounds.extend( new google.maps.LatLng(mapBoundsCenter.lat(), mapBoundsCenter.lng() + (scaleMin / 2)) );
            }
        }
        if (!limitedByBounds && (mapBounds !== null)) {
            mapContainerJs.jqMapAjax.isAutoPanning = true;
            mapObject.fitBounds(mapBounds);
            mapContainerJs.jqMapAjax.markerBounds = mapBounds;
        } else {
            mapContainerJs.jqMapAjax.markerBounds = mapObject.getBounds();
        }
        if (mapContainerJs.jqMapAjax.controlListPager !== null) {
            mapContainerJs.jqMapAjax.controlListPager.html(result.pager);
            mapContainerJs.jqMapAjax.controlListPager.find("[data-action=map-ajax-list-page-btn]").on("click", function() {
                mapContainerJs.jqMapAjax.listOptions.offset = parseInt( jQuery(this).attr("data-offset") );
                mapLoadPage(mapContainerJs, mapContainerJs.jqMapAjax.listOptions, result.count);
            });
        }
        mapContainerJs.jqMapAjax.dirty = false;
    };

    mapRefresh = function(mapContainerJs, param) {
        mapLoadPage(mapContainerJs, { addBounds: true });
    };

    mapRefreshLazy = function(mapContainerJs, param) {
        mapContainerJs.jqMapAjax.dirty = true;
        if (mapContainerJs.jqMapAjax.options.lazyReload > 0) {
            // Reload after a specific delay without having further refresh requests
            if (mapContainerJs.lazyReloadTimer !== null) {
                window.clearTimeout(mapContainerJs.lazyReloadTimer);
                mapContainerJs.lazyReloadTimer = null;
            }
            mapContainerJs.lazyReloadTimer = window.setTimeout(function() {
                mapRefresh(mapContainerJs, param);
            }, mapContainerJs.jqMapAjax.options.lazyReload);
        } else {
            // Lazy reloading disabled
            mapRefresh(mapContainerJs, param);
        }
        console.log("TODO: Map refresh queued!");
    };

    mapSetAutoRefresh = function(mapContainerJs, param) {
        if (param) {
            mapContainerJs.jqMapAjax.options.autoRefresh = true;
        } else {
            mapContainerJs.jqMapAjax.options.autoRefresh = false;
        }
        if (mapContainerJs.jqMapAjax.controlAutoRefresh !== null) {
            mapContainerJs.jqMapAjax.controlAutoRefresh.prop("checked", mapContainerJs.jqMapAjax.options.autoRefresh);
        }
    };

    $.fn.mapAjax = function(actionOrOptions, actionParam) {
        if (typeof actionOrOptions == "undefined") {
            actionOrOptions = "init";
        }
        if (typeof actionOrOptions == "object") {
            // Init with options
            jQuery(this).each(function() {
                mapInit(this, actionOrOptions);
            });
        } else if (actionOrOptions == "init") {
            // Manually initialize
            jQuery(this).each(function() {
                mapInit(this, actionParam);
            });
        } else if (actionOrOptions == "refresh") {
            jQuery(this).each(function () {
                mapRefresh(this, actionParam);
            });
        } else if (actionOrOptions == "auto-refresh") {
            jQuery(this).each(function () {
                mapSetAutoRefresh(this, actionParam);
            });
        }
    };

})(jQuery);

ebizGoogleMapCallback(function() {
    jQuery("[data-display='map-ajax']").mapAjax();
});