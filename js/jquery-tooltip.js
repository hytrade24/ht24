
/* ###VERSIONSBLOCKINLCUDE### */

var jq_tooltip_cur = new Array();

function HideAllTooltips() {
	while (jq_tooltip_cur.length > 0) {
		var jq_tooltip_old = jq_tooltip_cur.shift(); 
		jq_tooltip_old.clearQueue().stop().hide();
	}
}

function EnableTooltip(element, tooltip, ajax_source) {
	var jq_element = $(element);
	var jq_tooltip = $(tooltip);
	if ((jq_element.length == 0) || (jq_tooltip.length == 0)) return;
	var	offset = 8;
	if (ajax_source) {
		jq_element.ajax = true;
	} else {
		jq_element.ajax = false;
	}
	jq_element.active = false;
	jq_element.ajax_content = "";
	jq_element.ajax_done = false;
	jq_element.ajax_url = ajax_source;
	jq_element.ajax_loading = false;
	// Mouseover event
	jq_element.mouseover(function(e) {
		while (jq_tooltip_cur.length > 0) {
			var jq_tooltip_old = jq_tooltip_cur.shift(); 
			jq_tooltip_old.hide();
		}
		if (jq_element.ajax && jq_element.ajax_done) {
			// Inhalt bereits geladen
			jq_tooltip.html(jq_element.ajax_content);
		} else if (jq_element.ajax && !jq_element.ajax_loading) {
			// Tooltip über AJAX nachladen
			jq_element.ajax_loading = true;
			$.ajax({
				url:		jq_element.ajax_url,
				type: 		'GET',
				success:	function(content) {
					// Erfolg
					jq_element.ajax_done = true;
					jq_element.ajax_content = content;
					jq_tooltip.html(content);
				},
				complete:	function() {
					// Ajax request abgeschlossen (nicht zwingend erfolgreich)
					jq_element.ajax_loading = false;
				}
			});
		}
		// Tooltip einblenden
		var window_width 	= $(window).width();
		var window_height 	= $(window).height();
		var position_left	= e.pageX - $(window).scrollLeft();
		var position_top	= e.pageY - $(window).scrollTop();
		var offset_left		= (position_left < (window_width / 2) ? offset : - offset - jq_tooltip.outerWidth());
		var offset_top		= (position_top < (window_height / 2) ? offset : - offset - jq_tooltip.outerHeight());
		jq_tooltip.css({
			position:	"fixed",
			left:		(position_left+offset_left)+"px",
			top:		(position_top+offset_top)+"px"
		});
		if (jq_tooltip.css("display") == "none") {
			jq_tooltip.css("opacity", 1);
			jq_tooltip.fadeIn();
		}
		jq_tooltip_cur.push(jq_tooltip);
	});
	// Mousemove event
	jq_element.mousemove(function(e) {
		// Tooltip verschieben
		var window_width 	= $(window).width();
		var window_height 	= $(window).height();
		var position_left	= e.pageX - $(window).scrollLeft();
		var position_top	= e.pageY - $(window).scrollTop();
		var offset_left		= (position_left < (window_width / 2) ? offset : - offset - jq_tooltip.outerWidth());
		var offset_top		= (position_top < (window_height / 2) ? offset : - offset - jq_tooltip.outerHeight());
		jq_tooltip.css({
			position:	"fixed",
			left:		(position_left+offset_left)+"px",
			top:		(position_top+offset_top)+"px"
		});
	});
	// Mouseout event
	jq_element.mouseout(function() {
		// Tooltip(s) ausblenden
		HideAllTooltips();
	});
	// Hide when moving the cursor on the tooltip
	jq_tooltip.mousemove(function(e) {
		// Tooltip(s) ausblenden
		HideAllTooltips();
	});
}