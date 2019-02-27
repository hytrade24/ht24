/**
 * Created by Forsaken on 06.11.14.
 */

(function($) {
    "use strict";

    var dragElement = false;
    var dragTarget = false;
    var dragLocation = false;

    function moveDragElement() {
        if ((dragElement !== false) && (dragTarget !== false) && (dragLocation !== false)) {
            var jqElement = jQuery(dragElement);
            var jqTarget = jQuery(dragTarget);
            var location = dragLocation;
            if (location == "before") {
                jqElement.insertBefore(jqTarget);
            } else if (location == "after") {
                jqElement.insertAfter(jqTarget);
            }
        }
        setDragElement(false);
        setDragTarget(false);
    }

    function setDragElement(newElement) {
        if (dragElement !== false) {
            var container = jQuery(dragElement).parent()[0];
            $(dragElement).removeClass(container.jqSortable.cssDragged);
        }
        dragElement = newElement;
        if (newElement !== false) {
            var container = jQuery(newElement).parent()[0];
            $(newElement).addClass(container.jqSortable.cssDragged);
        }
        return true;
    }

    function setDragTarget(newTarget) {
        if (dragElement == newTarget) {
            return false;
        }
        if (dragTarget === newTarget) {
            return true;
        }
        if (dragTarget !== false) {
            var container = jQuery(dragTarget).parent()[0];
            $(dragTarget).removeClass(container.jqSortable.cssHoverBefore).removeClass(container.jqSortable.cssHoverAfter);
        }
        dragTarget = newTarget;
        dragLocation = false;
        return true;
    }

    function updateDragTarget(posX, posY) {
        if (dragTarget === false) {
            return false;
        }
        var container = jQuery(dragTarget).parent()[0];
        var settings = container.jqSortable;
        if (settings.orientation == "vertical") {
            var height = jQuery(dragTarget).outerHeight();
            if ((posY / height) < 0.5) {
                dragLocation = "before";
                $(dragTarget).addClass(container.jqSortable.cssHoverBefore).removeClass(container.jqSortable.cssHoverAfter);
            } else {
                dragLocation = "after";
                $(dragTarget).removeClass(container.jqSortable.cssHoverBefore).addClass(container.jqSortable.cssHoverAfter);
            }
        } else {
            var width = jQuery(dragTarget).outerWidth();
            if ((posX / width) < 0.5) {
                dragLocation = "before";
                $(dragTarget).addClass(container.jqSortable.cssHoverBefore).removeClass(container.jqSortable.cssHoverAfter);
            } else {
                dragLocation = "after";
                $(dragTarget).removeClass(container.jqSortable.cssHoverBefore).addClass(container.jqSortable.cssHoverAfter);
            }
        }
        return true;
    }

    function eventDragStart(event) {
        console.log("drag start");
        setDragElement(this);
        event.originalEvent.dataTransfer.setData("text/plain", "jqSortableElement");
    }

    function eventDragEnter(event) {
        console.log("drag enter");
        setDragTarget(this);
        updateDragTarget(event.originalEvent.offsetX, event.originalEvent.offsetY);
    }

    function eventDragOver(event) {
        console.log("drag over");
        if (setDragTarget(this)) {
            event.preventDefault();
            updateDragTarget(event.originalEvent.offsetX, event.originalEvent.offsetY);
        }
    }

    function eventDragLeave(event) {
        console.log("drag leave");
        setDragTarget(false);
    }

    function eventDragEnd(event) {
        console.log("drag end");
        setDragElement(false);
    }

    function eventDrop(event) {
        console.log("drop");
        moveDragElement();
    }

    function initSortable(container, settings) {
        // Merge default settings with overrides and store within element
        settings = $.extend({
            cssContainer: "sortableList",
            cssDragged: "sortableDragged",
            cssHoverBefore: "sortableDropBefore",
            cssHoverAfter: "sortableDropAfter",
            elements: "*",
            orientation: "vertical"
        }, settings);
        container.jqSortable = settings;
        // Bind drag & drop events
        var jqElements = jQuery(container).children(settings.elements);
        jqElements.on("dragstart", eventDragStart);
        jqElements.on("dragenter", eventDragEnter);
        jqElements.on("dragover", eventDragOver);
        jqElements.on("dragleave", eventDragLeave);
        jqElements.on("dragend", eventDragEnd);
        jqElements.on("drop", eventDrop);
        if (settings.cssContainer !== false) {
            // Add container css class
            jQuery(container).addClass(settings.cssContainer);
        }
    }

    $.fn.sortable = function(parameters) {
        if (typeof parameters === "string") {
            $(this).each(function() {
                if (typeof this.jqSortable !== "undefined") {
                    var settings = this.jqSortable;
                    // TODO: Add actions
                }
            });
        } else {
            if (typeof parameters == "undefined") {
                parameters = {};
            }
            $(this).each(function() {
                initSortable(this, parameters);
            });
        }
    };

}(jQuery));