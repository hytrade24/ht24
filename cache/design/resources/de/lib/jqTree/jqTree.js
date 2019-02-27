/**
 * Created by Jens Niedling
 */

(function ( $ ) {
    "use strict";

    var defaultConfiguration = {
        acceptFiles: true,
        allowOffline: true,
        ajaxUrl: false,
        autoExpand: false,
        cssTree: 'jqTree',
        cssNodeDragOver: 'jqTree-drag-over',
        cssNodeDragOverBefore: 'jqTree-drag-before',
        cssNodeDragOverAfter: 'jqTree-drag-after',
        cssNodeExpanded: 'expanded',
        cssNodeExpandable: 'expandable',
        cssNodeSelected: 'selected',
        cssNodeLevel: 'level',
        cssOffline: 'offline',
        delayDoubleClick: 400,
        liveUpdate: false,
        mode: 'list',
        menu: false,
        menuMultiple: true,
        htmlOffline: "<h3>Offline</h3><p>You are offline! Please reconnect to the internet!</p>",
        storageOffline: 'jqTree/default',
        onNodeOpen: false,
        onNodeMenu: false,
        onNodeInit: false,
        onNodeSelect: false,
        tableColumns: [ "label" ],
        tableColumnLabels: [ "Name" ],
        touchMaxTapDuration: 400,
        treeIdent: 'default',
        updateChildsOnMove: false,
        readOnly: false,
		defaultValue: '',
        noAjaxNodeLoading: false,
        htmlNodes: {}
    };

    var dragNodes = false;
    var dragTarget = false;
    var dragTargetPosition = false;

    /**
     * Initialize tree element(s)
     * @param parameter
     * @returns {fn}
     */
    $.fn.jqTree = function (parameter, parameterMore) {
        var targetElements = this;
        var index;
        if (typeof parameter == "string") {
            for (index = targetElements.length - 1; index >= 0; index--) {
                var jsTree = targetElements[index];
                $.fn.jqTree.executeCommand(jsTree, parameter, parameterMore);
            }
            return;
        } else {
            if (typeof parameter != "object") {
                parameter = {};
            }
            var settings = jQuery.extend({}, defaultConfiguration, parameter);
            // Initialize tree(s)
            for (index = targetElements.length - 1; index >= 0; index--) {
                var jsTree = targetElements[index];
                initTree(jsTree, settings);
            }
            if (settings.allowOffline) {
                jQuery(window).on("beforeunload", function(event) {
                    for (var index = targetElements.length - 1; index >= 0; index--) {
                        var jsTree = targetElements[index];
                        storeTree(jsTree, settings);
                    }
                });
            }
            // Hide menu
            if (settings.menu !== false) {
                jQuery(settings.menu).hide();
                jQuery(document).click(function(event) {
                    jQuery(settings.menu).hide();
                });
            }
        }
    };

    /**
     * Javascript command-api for performing actions to a jqTree element
     * @param tree          The jqTree element
     * @param command       The command to be executed
     * @param parameters    The commands parameters
     */
    $.fn.jqTree.executeCommand = function(tree, command, parameters) {
        var jsTree = checkTree(tree);
        if ((jsTree === false) && (typeof parameters === "undefined")) {
            parameters = checkNode(tree);
            if (parameters !== false) {
                jsTree = parameters.jqTree.tree;
            }
        }
        if (jsTree !== false) {
            var treeSettings = jsTree.jqTree.settings;
            switch (command) {
                case 'collapse':
                case 'collapseNode':
                    var jsNode = checkNode(parameters);
                    if (jsNode !== false) {
                        collapseNode(jsNode);
                    }
                    break;
                case 'collapseAll':
                    if (treeSettings.mode == "list") {
                        jQuery(jsTree).find("li > ul").parent().each(function() {
                            collapseNode(this);
                        });
                    } else if (treeSettings.mode == "table") {
                        jQuery(jsTree).children("tbody").children().each(function() {
                            collapseNode(this);
                        });
                    }
                    break;
                case 'expandAll':
                    if (treeSettings.mode == "list") {
                        jQuery(jsTree).find("li > ul").parent().each(function() {
                            expandNode(this, true);
                        });
                    } else if (treeSettings.mode == "table") {
                        jQuery(jsTree).children("tbody").children().each(function() {
                            expandNode(this, true);
                        });
                    }
                    break;
                case 'expand':
                case 'expandNode':
                    var jsNode = checkNode(parameters);
                    if (jsNode !== false) {
                        expandNode(jsNode);
                    }
                    break;
                case 'toggle':
                case 'toggleNode':
                    var jsNode = checkNode(parameters);
                    if (jsNode !== false) {
                        toggleNode(jsNode);
                    }
                    break;
                case 'refresh':
                    ajaxReadTreeChilds(jsTree, jsTree);
                    break;
                case 'remove':
                case 'removeNode':
                    var jqNode = jQuery(tree).find("[data-id="+parameters.id+"]");
                    if (jqNode.length > 0) {
                        removeNode(jsTree, jqNode[0]);
                    }
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
        console.log(new Date().toLocaleString(), "[jqTree|"+type+"]", message);
    }

    /**
     * Poll the ajax-url for events
     * @param tree          The jqTree element
     * @param jsonResponse  (optional) The previous response from the server
     */
    function ajaxNodeJsEventPoll(tree, jsonResponse) {
        var jsTree = checkTree(tree);
        if (jsTree === false) {
            return;
        }
        var settings = jsTree.jqTree.settings;
        if (settings.ajaxUrl === false) {
            return;
        }
        if (typeof jsonResponse != "undefined") {
            var response = JSON.parse(jsonResponse);
            if (response !== false) {
                // Poll successful, process events
                for (var eventIndex = 0; eventIndex < response.events.length; eventIndex++) {
                    ajaxNodeJsEvent(jsTree, response.events[eventIndex]);
                }
            } else {
                // Poll failed! Delay retry by 5 seconds
                window.setTimeout(function() {
                    ajaxNodeJsEventPoll(jsTree);
                }, 5000);
                return;
            }
        }
        // Poll failed! Delay retry by 5 seconds
        jsTree.jqTree.ajaxEventRequest = jQuery.post(settings.ajaxUrl, "jqTreeAction=pollEvents", function(queryResponse) {
            ajaxNodeJsEventPoll(jsTree, queryResponse);
        });
        if (jsTree.jqTree.ajaxEventInterval === false) {
            // Restart polling on failure
            jsTree.jqTree.ajaxEventInterval = window.setInterval(function() {
                if (jsTree.jqTree.ajaxEventRequest !== false) {
                    if (jsTree.jqTree.ajaxEventRequest.readyState === 0) {
                        // Connection lost, restart!
                        ajaxNodeJsEventPoll(jsTree);
                    }
                }
            }, 1000);
        }
    }

    /**
     * This function is called for each received event in order to process it
     * @param jsTree        The jqTree element
     * @param event         The event as javascript-object { ident: 'example', params: { foo: bar } }
     */
    function ajaxNodeJsEvent(jsTree, event) {
        switch (event.ident) {
            case 'removeNode':
                $.fn.jqTree.executeCommand(jsTree, "removeNode", { id: event.params.id });
                break;
            case 'refreshNode':
                ajaxReadTreeNode(jsTree, jQuery(jsTree).find('[data-id='+event.params.id+']'), event.params.recursive);
                break;
            case 'refreshChildren':
                if (event.params.indexOf("/") > -1) {
                    // Refresh everything
                    ajaxReadTreeChilds(jsTree, jsTree);
                } else {
                    // Refresh the childs of the listed nodes if present
                    jQuery(jsTree).find("li").each(function() {
                        if (isTreeNode(this) && (event.params.indexOf(this.jqTree.settings.id) > -1)) {
                            ajaxReadTreeChilds(jsTree, this);
                        }
                    });
                }
                break;
        }
    }

    /**
     * Send the command to move one or more node to the ajax-url
     * @param nodes             The nodes to be moved
     * @param nodeTarget        The target node
     * @param position          Where to insert the node(s) (inside/before/after
     * @param successCallback   Javscript-Function that is called on successful server response
     */
    function ajaxMoveNodes(nodes, nodeTarget, position, successCallback) {
        var settings = nodeTarget.jqTree.tree.jqTree.settings;
        var nodesSubmitted = [];
        var nodeIdsParam = [];
        for (var nodeIndex = 0; nodeIndex < nodes.length; nodeIndex++) {
            var jsNode = nodes[nodeIndex];
            // Only submit the top-level nodes, all others are moved anyway
            if (jQuery(jsNode).parents("."+settings.cssNodeSelected).length === 0) {
                nodesSubmitted.push(jsNode);
                nodeIdsParam.push("node[]="+encodeURIComponent(nodes[nodeIndex].jqTree.settings.id));
            }
        }
        if (settings.ajaxUrl !== false) {
            var nodeTargetId = nodeTarget.jqTree.settings.id;
            jQuery.post(settings.ajaxUrl, "jqTreeAction=move&tree="+encodeURIComponent(settings.treeIdent)+
                "&"+nodeIdsParam.join("&")+"&target="+encodeURIComponent(nodeTargetId)+
                "&position="+encodeURIComponent(position), function(result) {
                var failedNodes = [];
                for (var nodeIndex = 0; nodeIndex < nodesSubmitted.length; nodeIndex++) {
                    var nodeId = nodesSubmitted[nodeIndex].jqTree.settings.id;
                    if (result.results[nodeId].success) {
                        successCallback(nodesSubmitted[nodeIndex], nodeTarget, position, result.results[nodeId]);
                    } else {
                        failedNodes.push({
                            id: nodeId,
                            result: result.results[nodeId]
                        });
                    }
                }
            });
        } else {
            for (var nodeIndex = 0; nodeIndex < nodes.length; nodeIndex++) {
                successCallback(nodes[nodeIndex], nodeTarget, position, true);
            }
        }
    }

    /**
     * Read/refresh the root-elements of the given tree from the ajax-url
     * @param tree
     */
    function ajaxReadTree(tree) {
        var jsTree = checkTree(tree);
        if ((jsTree !== false) && (jsTree.jqTree.settings.ajaxUrl !== false)) {
            // Reset content
            if (jsTree.jqTree.settings.mode == "list") {
                jQuery(jsTree).html("");
            } else if (jsTree.jqTree.settings.mode == "table") {
                var labels = jsTree.jqTree.settings.tableColumnLabels;
                var tableHeaderHtml = "<tr>\n";
                for (var labelIndex = 0; labelIndex < labels.length; labelIndex++) {
                    tableHeaderHtml += "  <th>"+labels[labelIndex]+"</th>\n";
                }
                tableHeaderHtml += "</tr>\n";
                jQuery(jsTree).html("<thead>\n"+tableHeaderHtml+"</thead><tbody></tbody>");
            }
            // Read root
            ajaxReadTreeChilds(jsTree, jsTree);
        }
    }

    /**
     * Read the target node
     * @param tree          The jqTree element
     * @param node          The node to be read
     * @param nodeStates    A javascript object containing the states of the child-nodes by node-id (expanded/selected)
     */
    function ajaxReadTreeNode(tree, node, recursive, nodeStates) {
        if (!navigator.onLine) {
            return false;
        }
        var jsTree = checkTree(tree);
        var jsNode = checkNode(node);
        if ((jsTree === false) || (jsNode === false)) {
            return false;
        }
        var treeSettings = jsTree.jqTree.settings;
        var nodeId = jsNode.jqTree.settings.id;
        // Keep previously extended nodes in mind
        if (typeof recursive == "undefined") {
            recursive = true;
        }
        // Keep previously extended nodes in mind
        if (typeof nodeStates == "undefined") {
            nodeStates = {};
        }
        // Store state of the current node
        if (typeof nodeStates[nodeId] == "undefined") {
            nodeStates[nodeId] = {
                expanded: jQuery(jsNode).hasClass(treeSettings.cssNodeExpanded),
                selected: jQuery(jsNode).hasClass(treeSettings.cssNodeSelected)
            };
        } else {
            nodeStates[nodeId].expanded = jQuery(jsNode).hasClass(treeSettings.cssNodeExpanded);
            nodeStates[nodeId].selected = jQuery(jsNode).hasClass(treeSettings.cssNodeSelected);
        }
        if (recursive) {
            // Store current state of child nodes
            var jqChildren = false;
            if (treeSettings.mode == "list") {
                jqChildren = jQuery(jsNode).find("li");
            } else if (treeSettings.mode == "table") {
                if (jQuery(jsNode).is("table")) {
                    jqChildren = jQuery(jsNode).children("tbody").children();
                } else {
                    jqChildren = getNode(jsNode, treeSettings);
                }
            }
            jqChildren.each(function() {
                if (isTreeNode(this)) {
                    var nodeId = this.jqTree.settings.id;
                    if (typeof nodeStates[nodeId] == "undefined") {
                        nodeStates[nodeId] = {
                            expanded: jQuery(this).hasClass(treeSettings.cssNodeExpanded),
                            selected: jQuery(this).hasClass(treeSettings.cssNodeSelected)
                        };
                    } else {
                        nodeStates[nodeId].expanded = jQuery(this).hasClass(treeSettings.cssNodeExpanded);
                        nodeStates[nodeId].selected = jQuery(this).hasClass(treeSettings.cssNodeSelected);
                    }
                }
            });
            // Remove all child nodes
            if (treeSettings.mode == "list") {
                jQuery(jsNode).children("ul").html("");
            } else if (treeSettings.mode == "table") {
                jQuery(jsTree).children("tbody").children("tr[data-parent="+jsNode.jqTree.settings.id+"]").remove();
            }
        }
        // Send information about additional columns
        var paramColumns = "";
        for (var columnIndex = 0; columnIndex < treeSettings.tableColumns.length; columnIndex++) {
            paramColumns += "&columns[]="+encodeURIComponent(treeSettings.tableColumns[columnIndex]);
        }
        // Read nodes
        jQuery.post(treeSettings.ajaxUrl, "jqTreeAction=read&tree="+encodeURIComponent(treeSettings.treeIdent)+"&node="+encodeURIComponent(nodeId)+paramColumns, function(result) {
            if (result.success) {
                replaceNode(jsTree, jsNode, result.node, nodeStates);
            }
        });
    }

    /**
     * Read the child-elements of a tree or tree-node
     * @param tree              The jqTree element
     * @param parent            The parent node of which the childs should be read
     * @param nodeStates        A javascript object containing the states of the child-nodes by node-id (expanded/selected)
     * @param expandChildren    Set to true in order to auto-expand new expandable nodes (otherwise this will fallback to the corresponding tree setting)
     * @returns {boolean}
     */
    function ajaxReadTreeChilds(tree, parent, nodeStates, expandChildren) {
        if (!navigator.onLine) {
            return false;
        }
        var jsTree = checkTree(tree);
        if (jsTree === false) {
            return false;
        }
        var treeSettings = jsTree.jqTree.settings;
        var jsParent = jQuery(parent)[0];
        var parentId = "root";
        if (isTreeNode(jsParent)) {
            // Read child element of an existing node
            parentId = jsParent.jqTree.settings.id;
        }
        // Keep previously extended nodes in mind
        if (typeof nodeStates === "undefined") {
            nodeStates = {};
        }
        // Store current state of tree nodes
        var jqChildren = false;
        if (treeSettings.mode == "list") {
            jqChildren = jQuery(jsParent).find("li");
        } else if (treeSettings.mode == "table") {
            if (jQuery(jsParent).is("table")) {
                jqChildren = jQuery(jsParent).children("tbody").children();
            } else {
                jqChildren = getNode(jsParent, treeSettings).slice(1);
            }
        }
        jqChildren.each(function() {
            var jsChildNode = checkNode(this);
            if (jsChildNode !== false) {
                var nodeId = jsChildNode.jqTree.settings.id;
                if (typeof nodeStates[nodeId] == "undefined") {
                    nodeStates[nodeId] = {
                        expanded: jQuery(jsChildNode).hasClass(treeSettings.cssNodeExpanded),
                        selected: jQuery(jsChildNode).hasClass(treeSettings.cssNodeSelected)
                    };
                } else {
                    nodeStates[nodeId].expanded = jQuery(jsChildNode).hasClass(treeSettings.cssNodeExpanded);
                    nodeStates[nodeId].selected = jQuery(jsChildNode).hasClass(treeSettings.cssNodeSelected);
                }
            }
        });
        // Remove all child nodes
        jqChildren.remove();
        // Send information about additional columns
        var paramColumns = "";
        for (var columnIndex = 0; columnIndex < treeSettings.tableColumns.length; columnIndex++) {
            paramColumns += "&columns[]="+encodeURIComponent(treeSettings.tableColumns[columnIndex]);
        }
        // Read nodes
        var ajaxPost = "jqTreeAction=readChilds&tree="+encodeURIComponent(treeSettings.treeIdent)+"&parent="+encodeURIComponent(parentId)+paramColumns;
        if (treeSettings.autoExpand) {
            ajaxPost += "&recursive=1";
        }
        jQuery.post(treeSettings.ajaxUrl, ajaxPost, function(result) {
            if (result.success) {
                addNodes(tree, parent, result.nodes, nodeStates, expandChildren);
            }
        });
    }

    /**
     * Add one or more child-nodes
     * @param tree              The jqTree element
     * @param parent            The parent node
     * @param nodes             The nodes that should be added
     * @param nodeStates        A javascript object containing the states of the child-nodes by node-id (expanded/selected)
     * @param expandChildren    Set to true in order to auto-expand new expandable nodes (otherwise this will fallback to the corresponding tree setting)
     */
    function addNodes(tree, parent, nodes, nodeStates, expandChildren) {
        var treeSettings = tree.jqTree.settings;
        var nodeLevel = parent.jqTree.level + 1;
        if (treeSettings.mode == "list") {
            var jqListElement = jQuery(parent).children("ul");
            if (jqListElement.length === 0) {
                jqListElement = jQuery(parent).append('<ul></ul>').children().last();
            }
            for (var childIndex = 0; childIndex < nodes.length; childIndex++) {
                var node = nodes[childIndex];
                var jqNodeElement = jqListElement.append('<li class="'+node.class+'"><a draggable="true">'+node.label+'</a></li>').children().last();
                initNode(tree, jqNodeElement[0], node, nodeStates, nodeLevel, expandChildren);
            }
        } else if (treeSettings.mode == "table") {
            var jqPrevRow = jQuery(parent);
            for (var childIndex = 0; childIndex < nodes.length; childIndex++) {
                var node = nodes[childIndex];
                var nodeHtml = '<tr class="'+node.class+' '+treeSettings.cssNodeLevel+nodeLevel+'" data-id="'+node.id+'" data-parent="'+parent.jqTree.settings.id+'">';
                for (var columnIndex = 0; columnIndex < treeSettings.tableColumns.length; columnIndex++) {
                    if (columnIndex === 0) {
                        nodeHtml += '<td><a draggable="true">' + node[ treeSettings.tableColumns[columnIndex] ] + '</a></td>';
                    } else {
                        nodeHtml += '<td>' + node[ treeSettings.tableColumns[columnIndex] ] + '</td>';
                    }
                }
                nodeHtml += '</tr>';
                if (jqPrevRow.is("tr")) {
                    jqPrevRow = jqPrevRow.after(nodeHtml).next();
                } else if (jqPrevRow.is("table")) {
                    if (jqPrevRow.children("tbody").length == 1) {
                        jqPrevRow = jqPrevRow.children("tbody").append(nodeHtml).children().last();
                    } else {
                        jqPrevRow = jqPrevRow.append(nodeHtml).children("tbody").children().last();
                    }
                }
                initNode(tree, jqPrevRow[0], node, nodeStates, nodeLevel, expandChildren);
            }
        }
    }

    /**
     * Replace the given node by a new node from the given settings
     * @param tree
     * @param node
     * @param nodeSettings
     * @param nodeStates
     */
    function replaceNode(tree, node, nodeSettings, nodeStates) {
        var treeSettings = tree.jqTree.settings;
        var nodeLevel = node.jqTree.level;
        var parentId = jQuery(node).attr("data-parent");
        if (treeSettings.mode == "list") {
            var jqChildNodes = jQuery(node).children("ul").detach();
            jQuery(node).replaceWith('<li class="'+nodeSettings.class+'"><a draggable="true">'+nodeSettings.label+'</a></li>');
            var jqNewNode = jQuery(tree).find("[data-id="+nodeSettings.id+"]");
            initNode(tree, jqNewNode[0], nodeSettings, nodeStates, nodeLevel);
            jqNewNode.append(jqChildNodes);
        } else if (treeSettings.mode == "table") {
            var jqNewNode = jQuery(parent);
            var nodeHtml = '<tr class="'+nodeSettings.class+' '+treeSettings.cssNodeLevel+nodeLevel+'" data-id="'+nodeSettings.id+'" data-parent="'+parentId+'">';
            for (var columnIndex = 0; columnIndex < treeSettings.tableColumns.length; columnIndex++) {
                if (columnIndex === 0) {
                    nodeHtml += '<td><a draggable="true">' + nodeSettings[ treeSettings.tableColumns[columnIndex] ] + '</a></td>';
                } else {
                    nodeHtml += '<td>' + nodeSettings[ treeSettings.tableColumns[columnIndex] ] + '</td>';
                }
            }
            nodeHtml += '</tr>';
            jQuery(node).replaceWith(nodeHtml);
            var jqNewNode = jQuery(tree).find("[data-id="+nodeSettings.id+"]");
            initNode(tree, jqNewNode[0], nodeSettings, nodeStates, nodeLevel);
        }
    }

    /**
     * Collapse the given tree-node
     * @param node
     * @returns {boolean}
     */
    function collapseNode(node) {
        if (!isTreeNode(node)) {
            return false;
        }
        var jsNode = checkNode(node);
        if ((jsNode === false) || !jsNode.jqTree.settings.expandable) {
            return false;
        }
        var treeSettings = jsNode.jqTree.tree.jqTree.settings;
        // Remove css class
        jQuery(jsNode).removeClass(treeSettings.cssNodeExpanded);
        if (treeSettings.mode == "table") {
            // Hide children manually in table mode
            getNode(jsNode, treeSettings).hide().first().show();
        }
        return true;
    }

    /**
     * Expand the given tree-node
     * @param node
     * @returns {boolean}
     */
    function expandNode(node, recursive, nodeStates) {
        if (!isTreeNode(node)) {
            return false;
        }
        var jsNode = checkNode(node);
        if ((jsNode === false) || !jsNode.jqTree.settings.expandable) {
            return false;
        }
        if (jsNode.jqTree.settings.expanded) {
            // Node already expanded
            return true;
        }
        if (typeof recursive === "undefined") {
            recursive = false;
        }
        var treeSettings = jsNode.jqTree.tree.jqTree.settings;
        jQuery(jsNode).addClass(treeSettings.cssNodeExpanded);
        if (treeSettings.mode == "list") {
            var jqListElement = jQuery(jsNode).children("ul");
            if (jqListElement.length === 0) {
                ajaxReadTreeChilds(jsNode.jqTree.tree, jsNode, nodeStates, recursive);
            } else {
                if (recursive) {
                    jqListElement.children().each(function() {
                        expandNode(this, true, nodeStates);
                    });
                }
            }
        } else if (treeSettings.mode == "table") {
            var jqChildren = jQuery(jsNode.jqTree.tree).children("tbody").children("tr[data-parent="+jsNode.jqTree.settings.id+"]");
            if (jqChildren.length === 0) {
                ajaxReadTreeChilds(jsNode.jqTree.tree, jsNode, nodeStates, recursive);
            } else{
                jqChildren.show();
                if (recursive) {
                    jqChildren.each(function() {
                        expandNode(this, true, nodeStates);
                    });
                }
            }
        }
        return true;
    }

    /**
     * Toggle the expanded-state of a node
     * @param node
     * @returns {*|boolean}
     */
    function toggleNode(node) {
        if (isTreeNodeExpanded(node)) {
            return collapseNode(node);
        } else {
            return expandNode(node);
        }
    }

    /**
     * Select a tree-node
     * @param node              The node that should be selected
     * @param doRowselect       Does the user want to row-select? (shift-key)
     * @param doMultiselect     Does the user want to multi-select? (ctrl-key)
     * @returns {boolean}
     */
    function selectNode(node, doRowselect, doMultiselect) {
        // Apply default settings
        if (typeof doRowselect == "undefined") {
            doRowselect = false;
        }
        if (typeof doMultiselect == "undefined") {
            doMultiselect = false;
        }
        // Get required elements and settings
        var jsNode = checkNode(node);
        if (jsNode === false) {
            return false;
        }
        var jsTree = jsNode.jqTree.tree;
        var treeSettings = jsTree.jqTree.settings;
        if (!doRowselect && !doMultiselect) {
            if (treeSettings.onNodeSelect !== false) {
                if (treeSettings.onNodeSelect(jsNode) === false) {
                    // Prevent selection
                    return false;
                }
            }
            // Single select; Remove current selection
            jQuery(jsTree).find("."+treeSettings.cssNodeSelected).removeClass(treeSettings.cssNodeSelected);
            // Select new node
            jQuery(jsNode).addClass(treeSettings.cssNodeSelected).find("li").addClass(treeSettings.cssNodeSelected);
        } else if (doRowselect) {
            // TODO: Recursive row-select (over multiple levels)
            // Row select; Select nearest selected node
            var jqNodesBefore = jQuery(jsNode).prevUntil("."+treeSettings.cssNodeSelected);
            if ((jqNodesBefore.length > 0) && (jqNodesBefore.prev().hasClass(treeSettings.cssNodeSelected))) {
                jqNodesBefore.addClass(treeSettings.cssNodeSelected).find("li").addClass(treeSettings.cssNodeSelected);
            }
            var jqNodeAfter = jQuery(jsNode).nextUntil("."+treeSettings.cssNodeSelected);
            if ((jqNodeAfter.length > 0) && (jqNodeAfter.next().hasClass(treeSettings.cssNodeSelected))) {
                jqNodeAfter.addClass(treeSettings.cssNodeSelected).find("li").addClass(treeSettings.cssNodeSelected);
            }
            // Select the clicked node
            jQuery(jsNode).addClass(treeSettings.cssNodeSelected).find("li").addClass(treeSettings.cssNodeSelected);
        } else {
            // Multi select; Toggle node selection
            if (jQuery(jsNode).hasClass(treeSettings.cssNodeSelected)) {
                jQuery(jsNode).removeClass(treeSettings.cssNodeSelected);
                jQuery(jsNode).find("."+treeSettings.cssNodeSelected).removeClass(treeSettings.cssNodeSelected);
                jQuery(jsNode).parents("."+treeSettings.cssNodeSelected).removeClass(treeSettings.cssNodeSelected);
            } else {
                jQuery(jsNode).addClass(treeSettings.cssNodeSelected).find("li").addClass(treeSettings.cssNodeSelected);
            }
        }
        return true;
    }

    /**
     * Initialize a tree-node
     * @param tree              The jqTree element
     * @param node              The html element of the new node
     * @param nodeSettings      The settings of the new node as javscript object
     * @param nodeStates        A javascript object containing the states of the child-nodes by node-id (expanded/selected)
     * @param nodeLevel         The nest-level of the tree node (1 = root level, 2 = first child, ...)
     * @param expandChildren    Set to true in order to auto-expand new expandable nodes (otherwise this will fallback to the corresponding tree setting)
     * @returns {boolean}
     */
    function initNode(tree, node, nodeSettings, nodeStates, nodeLevel, expandChildren) {
        var jsTree = checkTree(tree);
        if (jsTree === false) {
            return false;
        }
        var jsNode = jQuery(node)[0];
        jsNode.jqTree = {
            level: nodeLevel,
            type: 'node',
            tree: jsTree,
            settings: nodeSettings
        };
        var treeSettings = jsTree.jqTree.settings;
        if (typeof expandChildren === "undefined") {
            expandChildren = treeSettings.autoExpand;
        }
        var expand = nodeSettings.expandable && expandChildren;
        var select = false;
        if ( (typeof nodeStates != "undefined") && (typeof nodeStates[nodeSettings.id] != "undefined") ) {
            // Restore stored node state
            var nodeState = nodeStates[nodeSettings.id];
            expand = nodeState.expanded;
            select = nodeState.selected;
        }
		if(treeSettings.defaultValue != '' && treeSettings.defaultValue == nodeSettings.id)  {
			select = true;
		}

        // Add or remove "expandable" css class
        if (nodeSettings.expandable) {
            jQuery(jsNode).addClass(treeSettings.cssNodeExpandable);
        } else {
            jQuery(jsNode).removeClass(treeSettings.cssNodeExpandable);
            jQuery(jsNode).removeClass(treeSettings.cssNodeExpanded);
        }
        var jsNodeLink = jQuery(jsNode).find("a").first()[0];
        jsNodeLink.jqTree = { node: jsNode };
        // Set id as data attribute
        jQuery(jsNode).attr("data-id", nodeSettings.id);
        if (treeSettings.readOnly) {
            // Bind simple mouse events (skip those required for editing)
            jQuery(jsNodeLink)
                .on("click", eventTreeNodeClick)
                .on("doubleclick", eventTreeNodeDoubleClick);
        } else {
            // Bind simple mouse events
            jQuery(jsNodeLink)
                .on("click", eventTreeNodeClick)
                .on("doubleclick", eventTreeNodeDoubleClick)
                .on("contextmenu", eventTreeNodeRightClick);
            // Bind drag & drop events
            jQuery(jsNodeLink)
                .on("dragstart", eventTreeNodeDragStart)
                .on("dragover", eventTreeNodeDragOver)
                .on("dragleave", eventTreeNodeDragLeave)
                .on("dragend", eventTreeNodeDragEnd)
                .on("drop", eventTreeNodeDrop);
            // Bind touch events
            jQuery(jsNodeLink)
                .on("touchstart", eventTreeNodeTouchStart)
                .on("touchend", eventTreeNodeTouchEnd)
                .on("touchmove", eventTreeNodeTouchMove)
                .on("touchcancel", eventTreeNodeTouchCancel);
        }
        // Check if children are provided
        if ((typeof nodeSettings.children !== "undefined") && (nodeSettings.children !== false)) {
            addNodes(tree, node, nodeSettings.children, nodeStates, expandChildren);
        }
        // Set desired expand-/select-state
        if (expand) {
            // Expand the node
            expandNode(jsNode, nodeStates, expandChildren);
        }
        if (select) {
            // Select the node
            selectNode(jsNode, false, false);
        }
        // Trigger callback if set
        if (treeSettings.onNodeInit !== false) {
            treeSettings.onNodeInit(node, jsNode.jqTree.settings);
        }
    }

    /**
     * Initialize a tree element
     * @param tree          The html element of the new tree
     * @param settings      The trees settings as javascript object
     */
    function initTree(tree, settings) {
        var jsTree = jQuery(tree)[0];
        jsTree.jqTree = {
            ajaxEventRequest: false,
            ajaxEventInterval: false,
            level: 0,
            type: 'tree',
            settings: jQuery.extend({}, settings)
        };
        jQuery(jsTree).addClass("jqTree");


        if(settings.noAjaxNodeLoading == true && settings.htmlNodes.length > 0) {
            addNodes(jsTree, jsTree, settings.htmlNodes);
        } else {
            if (navigator.onLine) {
                // Read nodes
                ajaxReadTree(jsTree);
                if (settings.liveUpdate) {
                    // Handle events
                    ajaxNodeJsEventPoll(jsTree);
                }
            } else {
                var restored = false;
                if (settings.allowOffline) {
                    var treeJson = localStorage.getItem(settings.storageOffline);
                    if (treeJson !== null) {
                        var treeObject = JSON.parse(treeJson);
                        if (importNode(jsTree, jsTree, treeObject.css, treeObject.settings, treeObject.children)) {
                            // Update settings
                            jsTree.jqTree.settings = jQuery.extend(jsTree.jqTree.settings, treeObject.settings);
                            // Set success (prevent offline notice)
                            restored = true;
                        }
                    }
                }
                if (!restored) {
                    jQuery(jsTree).addClass(settings.cssOffline).html(settings.htmlOffline);
                }
            }
        }
    }

    /**
     * Import node from serialized format
     * @param tree          The jqTree element
     * @param node          The parent node/tree used for importing
     * @param cssParent     CSS-Class(es) that are added to the parent node/tree after importing the child nodes
     * @param settings      The serialized settings of the parent node as javascript object
     * @param children      The serialized child nodes as javascript object
     * @returns {boolean}
     */
    function importNode(tree, node, cssParent, settings, children) {
        var jsTree = checkTree(tree);
        var jsNode = checkNode(node);
        if ((jsTree === false) || (jsNode === false)) {
            return false;
        }
        // Add children
        if (children.length > 0) {
            var jqListElement = jQuery(jsNode).children("ul");
            if (jqListElement.length === 0) {
                jqListElement = jQuery(jsNode).append('<ul></ul>').children().last();
            }
            for (var childIndex = 0; childIndex < children.length; childIndex++) {
                var child = children[childIndex];
                var jqNodeElement = jqListElement.append('<li class="'+child.css+'"><a draggable="true">'+child.settings.label+'</a></li>').children().last();
                initNode(jsTree, jqNodeElement[0], child.settings, child.level);
                importNode(tree, jqNodeElement[0], child.css, child.settings, child.children);
            }
        }
        // Apply css classes
        jQuery(jsNode).attr("class", cssParent);
        return true;
    }

    /**
     * Export a node including children into serialized format (yet a javascript object)
     * @param node          The node/tree that should be exported
     * @returns {*}
     */
    function exportNode(node) {
        var jsNode = checkNode(node);
        if (jsNode === false) {
            return false;
        }
        var node = {
            css: jQuery(jsNode).attr("class"),
            children: [],
            settings: jsNode.jqTree.settings
        };
        // Add children
        jQuery(jsNode).children("ul").children("li").each(function() {
            node.children.push( exportNode(this) );
        });
        return node;
    }

    /**
     * Store the given tree into the html5 local storage
     * @param tree          The jqTree object
     * @returns {boolean}
     */
    function storeTree(tree) {
        var jsTree = checkTree(tree);
        if (jsTree === false) {
            return false;
        }
        if (isTree(jsTree)) {
            var settings = jsTree.jqTree.settings;
            if (settings.allowOffline && (jQuery(jsTree).children("ul").length > 0)) {
                var treeObject = exportNode(jsTree);
                var treeJson = JSON.stringify(treeObject);
                window.localStorage.setItem(settings.storageOffline, treeJson);
            }
        }
    }

    /**
     * Get the node including all children as jquery array
     * @param node
     * @param treeSettings  (optional)
     * @returns {boolean}
     */
    function getNode(node, treeSettings) {
        var jsNode = checkNode(node);
        if (jsNode === false) {
            return false;
        }
        if (typeof treeSettings == "undefined") {
            treeSettings = jsNode.jqTree.tree.jqTree.settings;
        }
        if (treeSettings.mode == "list") {
            return jQuery(jsNode);
        } else if (treeSettings.mode == "table") {
            var jsTree = jsNode.jqTree.tree;
            var jqResult = jQuery(node);
            jQuery(jsTree).children("tbody").children("tr[data-parent="+jsNode.jqTree.settings.id+"]").each(function() {
                jqResult = jqResult.add( getNode(this, treeSettings) );
            });
            return jqResult;
        }
    }

    /**
     * Remove a node from the tree
     * @param tree      The jqTree element
     * @param node      The tree node to be removed
     */
    function removeNode(tree, node) {
        var jsTree = checkTree(tree);
        if (jsTree !== false) {
            var treeSettings = jsTree.jqTree.settings;
            var jqNodes = getNode(node, treeSettings);
            jqNodes.remove();
        }
    }

    function updateNodeLevels(node, level) {
        var jsNode = checkNode(node);
        if (jsNode !== false) {
            var jsTree = jsNode.jqTree.tree;
            var treeSettings = jsTree.jqTree.settings;
            if (typeof level == "undefined") {
                level = jsNode.jqTree.level;
            } else {
                jQuery(jsNode).removeClass(treeSettings.cssNodeLevel+jsNode.jqTree.level);
                jsNode.jqTree.level = level;
                jQuery(jsNode).addClass(treeSettings.cssNodeLevel+level);
            }
            if (treeSettings.mode == "list") {
                jQuery(jsTree).children("ul").children("li").each(function() {
                    updateNodeLevels(this, level + 1);
                });
            } else if (treeSettings.mode == "table") {
                jQuery(jsTree).children("tbody").children("tr[data-parent="+jsNode.jqTree.settings.id+"]").each(function() {
                    updateNodeLevels(this, level + 1);
                });
            }
        }
    }

    /* CHECKS */

    /**
     * Checks if the given element is a jqTree element and return it as javascript-object if true
     * @param element
     * @returns {*}
     */
    function checkTree(element) {
        if (isTree(element)) {
            return jQuery(element)[0];
        }
        return false;
    }

    /**
     * Checks if the given element is a jqTree node element and return it as javascript-object if true
     * @param element
     * @returns {*}
     */
    function checkNode(element) {
        if (isTreeNode(element)) {
            return jQuery(element)[0];
        }
        return false;
    }

    /**
     * Checks if the given element is a jqTree element
     * @param element
     * @returns {boolean}
     */
    function isTree(element) {
        var jsElement = jQuery(element)[0];
        if ((typeof jsElement.jqTree != "undefined") && (jsElement.jqTree.type == "tree")) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Checks if the given element is a jqTree node element
     * @param element
     * @returns {boolean}
     */
    function isTreeNode(element) {
        var jsElement = jQuery(element)[0];
        if ((typeof jsElement != "undefined") && (typeof jsElement.jqTree != "undefined") && (jsElement.jqTree.type == "node")) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Checks if the given element is a expanded jqTree node element
     * @param node
     * @returns {boolean}
     */
    function isTreeNodeExpanded(node) {
        var jsNode = checkNode(node);
        if ((jsNode !== false) && jQuery(jsNode).is("."+jsNode.jqTree.tree.jqTree.settings.cssNodeExpanded)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Checks if the currently dragged node(s) is/are dropable on the given target
     * @param targetNode
     * @returns {boolean}
     */
    function isTreeNodeDropable(targetNode) {
        if (dragNodes === false) {
            return false;
        }
        var jsTargetNode = jQuery(targetNode)[0];
        var treeSettings = (isTree(jsTargetNode) ? jsTargetNode.jqTree.settings : jsTargetNode.jqTree.tree.jqTree.settings);
        for (var dragNodeIndex = 0; dragNodeIndex < dragNodes.length; dragNodeIndex++) {
            var dragNode = dragNodes[dragNodeIndex];
            if (dragNode == jsTargetNode) {
                // Trying to drag selection on a selected node
                return false;
            }
            if (treeSettings.mode == "list") {
                if (jQuery.contains(dragNode, jsTargetNode)) {
                    // Trying to drag a node inside itself
                    return false;
                }
            } else if (treeSettings.mode == "table") {
                if (!isTreeNodeDropableTable(dragNode, jsTargetNode)) {
                    // Trying to drag a node inside itself
                    return false;
                }
            }
            // Check if target node accepts the drag node
            var nodeSettings = jsTargetNode.jqTree.settings;
            if ((typeof nodeSettings.accept != "undefined") && (nodeSettings.accept !== false) && (nodeSettings.accept.trim() !== "")) {
                var typesAccepted = nodeSettings.accept.trim().split(/\s+/);
                var dragTypes = jQuery(dragNodes).attr('class').split(/\s+/);
                if (!utilArrayContains(typesAccepted, dragTypes)) {
                    // Target does not accept any of the node's types
                    return false;
                }
            } else {
                // Target does not accept anything at all
                return false;
            }
        }
        // All dragged nodes can be dropped inside the target!
        return true;
    }

    /**
     * Recursively checks if the target node is a child of the drag node when jqTree is in table-mode
     * @param jsDragNode
     * @param jsTargetNode
     * @returns {boolean}
     */
    function isTreeNodeDropableTable(jsDragNode, jsTargetNode) {
        jQuery(jsDragNode).parent().children("tr[data-parent="+ jsDragNode.jqTree.settings.id +"]").each(function() {
            if (this == jsTargetNode) {
                return false;
            }
            if (!isTreeNodeDropableTable(this, jsTargetNode)) {
                return false;
            }
        });
        return true;
    }

    /* EVENTS */

    /**
     * This function is called when the user clicks/taps on a tree node
     * @param node              The node that was clicked/tapped
     * @param clickPosition     Position of the click as object { x: 42, y: 42 }
     * @param doRowselect       Does the user want to row-select? (shift-key)
     * @param doMultiselect     Does the user want to multi-select? (ctrl-key)
     */
    function eventSpecialTreeNodeClick(node, clickPosition, doRowselect, doMultiselect) {
        var jsNode = checkNode(node);
        if (jsNode !== false) {
            if (typeof jsNode.jqTree.lastClick != "undefined") {
                var clickDelay = new Date().getTime() - jsNode.jqTree.lastClick;
                if (clickDelay < jsNode.jqTree.tree.jqTree.settings.delayDoubleClick) {
                    eventSpecialTreeNodeOpen(jsNode);
                    return;
                }
            }
            if (jsNode.jqTree.settings.expandable && (clickPosition.x <= 20)) {
                // Expand/collapse node
                toggleNode(jsNode);
            } else {
                // Select node
                selectNode(jsNode, doRowselect, doMultiselect);
            }
            jsNode.jqTree.lastClick = new Date().getTime();
        }
    }

    /**
     * This function is called when the user wants to open a node (double-click/-tap)
     * @param node              The node that was clicked/tapped
     */
    function eventSpecialTreeNodeOpen(node) {
        var jsNode = checkNode(node);
        if (jsNode !== false) {
            var treeSettings = jsNode.jqTree.tree.jqTree.settings;
            if (treeSettings.onNodeOpen !== false) {
                // Execute defined callback
                treeSettings.onNodeOpen(node);
            }
        }
    }

    /**
     * This function is callen when the user wants to open a nodes menu (right-click/hold touch)
     * @param node
     * @param parent
     * @param left
     * @param top
     */
    function eventSpecialTreeNodeMenu(node, parent, left, top) {
        var jsNode = checkNode(node);
        if (jsNode !== false) {
            if (typeof parent == "undefined") {
                parent = jQuery(jsNode).find("a").first();
            }
            var treeSettings = jsNode.jqTree.tree.jqTree.settings;
            // Make sure the dragged node is selected
            if (!treeSettings.menuMultiple || !jQuery(jsNode).hasClass(treeSettings.cssNodeSelected)) {
                selectNode(jsNode, false, false);
            }
            // Attach and show menu
            var treeMenu = jQuery(treeSettings.menu);
            if (treeMenu.length > 0) {
                if ((treeSettings.onNodeMenu === false) || (treeSettings.onNodeMenu(node, node.jqTree.settings, treeMenu))) {
                    treeMenu.detach().insertAfter(parent).attr("data-id", jsNode.jqTree.settings.id).show();
                    if ((typeof left !== "undefined") && (typeof top !== "undefined")) {
                        treeMenu.css({ left: left, top: top });
                    }
                }
            }
        }
    }

    /**
     * This function is called when the user starts to drag a node
     * @param node              The node that was dragged
     * @param doRowselect       Does the user want to row-select? (shift-key)
     * @param doMultiselect     Does the user want to multi-select? (ctrl-key)
     */
    function eventSpecialTreeNodeDrag(node, doRowselect, doMultiselect) {
        if (typeof doRowselect == "undefined") {
            doRowselect = false;
        }
        if (typeof doMultiselect == "undefined") {
            doMultiselect = false;
        }
        var jsNode = checkNode(node);
        if (jsNode !== false) {
            var jsTree = jsNode.jqTree.tree;
            var treeSettings = jsTree.jqTree.settings;
            // Make sure the dragged node is selected
            if (!jQuery(jsNode).hasClass(treeSettings.cssNodeSelected)) {
                selectNode(jsNode, doRowselect, doMultiselect);
            }
            // Get selected nodes
            if (treeSettings.mode == "list") {
                dragNodes = jQuery(jsTree).find("li."+treeSettings.cssNodeSelected);
            } else if (treeSettings.mode == "table") {
                dragNodes = jQuery(jsTree).find("tr."+treeSettings.cssNodeSelected);
            }
            // Ensure there are no empty drags
            if (dragNodes.length === 0) {
                dragNodes = false;
            }
        }
    }

    /**
     * This function is called when the user stops dragging a node
     */
    function eventSpecialTreeNodeDragStop() {
        if (dragTarget !== false) {
            var treeSettings = dragTarget.jqTree.tree.jqTree.settings;
            jQuery(dragTarget).parent().children()
                .removeClass(treeSettings.cssNodeDragOver)
                .removeClass(treeSettings.cssNodeDragOverBefore)
                .removeClass(treeSettings.cssNodeDragOverAfter);
            dragTarget = false;
        }
        dragNodes = false;
    }

    /**
     * This function is called when the user hovers a node while dragging
     * @param node          The node that the user is over
     * @param y             Y-Position of the cursor/touch
     * @param height        Height of the node
     * @returns {boolean}
     */
    function eventSpecialTreeNodeDragOver(node, y, height) {
        var jsTargetNode = jQuery(node)[0];
        var jsParent = false;
        var treeSettings = jsTargetNode.jqTree.tree.jqTree.settings;
        if (treeSettings.mode == "list") {
            jsParent = jQuery(node).parent().parent()[0];
        } else if (treeSettings.mode == "table") {
            jsParent = jQuery(node).prev();
            if (jsParent.length === 0) {
                jsParent = jQuery(node).parents("table").first()[0];
            }
        }
        for (var dragNodeIndex = 0; dragNodeIndex < dragNodes.length; dragNodeIndex++) {
            var dragNode = dragNodes[dragNodeIndex];
            if (dragNode == jsTargetNode) {
                // Do not allow dragging selection on a selected node
                return false;
            }
        }
        dragTarget = false;
        if (isTreeNodeDropable(jsTargetNode)) {
            dragTarget = jsTargetNode;
            dragTargetPosition = "append";
        }
        if (isTreeNodeDropable(jsParent)) {
            var tolerance = (dragTarget !== false ? 0.2 : 0.5) * height;
            if (y < tolerance) {
                // Upper edge
                dragTarget = jsTargetNode;
                dragTargetPosition = "before";
            } else if (y > (height - tolerance)) {
                // Lower edge
                dragTarget = jsTargetNode;
                dragTargetPosition = "after";
            }
        }
        if (dragTarget !== false) {
            // Remove classes
            jQuery(node).parent().children()
                .removeClass(treeSettings.cssNodeDragOver)
                .removeClass(treeSettings.cssNodeDragOverBefore)
                .removeClass(treeSettings.cssNodeDragOverAfter);
            switch (dragTargetPosition) {
                case "append":
                    jQuery(node).addClass(treeSettings.cssNodeDragOver);
                    break;
                case "before":
                    jQuery(node).addClass(treeSettings.cssNodeDragOverBefore)
                        .prev().addClass(treeSettings.cssNodeDragOverAfter);
                    break;
                case "after":
                    jQuery(node).addClass(treeSettings.cssNodeDragOverAfter)
                        .next().addClass(treeSettings.cssNodeDragOverBefore);
                    break;
            }
        }
        return (dragTarget !== false);
    }

    /**
     * This function is called when the user leaves a node with the cursor/touch while dragging
     * @param node      The node the user moved out
     */
    function eventSpecialTreeNodeDragLeave(node) {
        var jsTargetNode = jQuery(node)[0];
        var treeSettings = jsTargetNode.jqTree.tree.jqTree.settings;
        dragTarget = false;
        jQuery(node).parent().children()
            .removeClass(treeSettings.cssNodeDragOver)
            .removeClass(treeSettings.cssNodeDragOverBefore)
            .removeClass(treeSettings.cssNodeDragOverAfter);
    }

    /**
     * This function is called when the user drops one or more nodes on another
     * @returns {boolean}
     */
    function eventSpecialTreeNodeDrop() {
        if ((dragNodes !== false) && (dragTarget !== false)) {
            eventSpecialTreeNodeMove(dragNodes, dragTarget, dragTargetPosition);
            return true;
        } else {
            return false;
        }
    }

    /**
     * This function is called when a node should be moved
     * @param nodes         The node(s) that should be moved
     * @param target        Where to move the node
     * @param position      Move the node(s) inside/before/after the target? ("append"/"before"/"after")
     * @returns {boolean}
     */
    function eventSpecialTreeNodeMove(nodes, target, position) {
        if ((nodes !== false) && (target !== false)) {
            ajaxMoveNodes(nodes, target, position, eventSpecialTreeNodeMoveDOM);
            return true;
        }
        return false;
    }

    /**
     * This function is called when a node was successfully moved and the html-dom should be updated
     * @param nodes         The node(s) that should be moved
     * @param target        Where to move the node
     * @param position      Move the node(s) inside/before/after the target? ("append"/"before"/"after")
     * @param details       Details about the moved node from the server (e.g. a new id for filesystem-based trees)
     */
    function eventSpecialTreeNodeMoveDOM(node, target, position, details) {
        var jsNode = jQuery(node)[0];
        var treeSettings = jsNode.jqTree.tree.jqTree.settings;
        var jsParent = false;
        if (treeSettings.mode == "list") {
            jsParent = jQuery(jsNode).parent().parent()[0];
        } else if (treeSettings.mode == "table") {
            if (jQuery(jsNode).prev().length === 0) {
                jsParent = jQuery(jsNode).parent().parent()[0];
            } else {
                jsParent = jQuery(jsNode).prev()[0];
            }

        }
        // Remove old level class
        jQuery(jsNode).removeClass(treeSettings.cssNodeLevel+jsNode.jqTree.level);
        // Move node
        var jqNode = getNode(jsNode, treeSettings);
        switch (position) {
            case "append":
                // Move node inside target
                if (treeSettings.mode == "list") {
                    var jqTargetList = jQuery(target).children("ul");
                    if (jqTargetList.length === 0) {
                        // Add child container
                        jqTargetList = jQuery(target).append('<ul></ul>').children().last();
                    }
                    jqTargetList.append( jqNode.detach() );
                } else if (treeSettings.mode == "table") {
                    jQuery(target).after( jqNode.detach() );
                }
                // Update parent
                jQuery(jsNode).attr("data-parent", target.jqTree.settings.id);
                // Make node expandable
                target.jqTree.settings.expandable = true;
                jQuery(target).addClass(treeSettings.cssNodeExpandable);
                // Expand target
                expandNode(target);
                // Update level
                jsNode.jqTree.level = target.jqTree.level + 1;
                break;
            case "before":
                // Move node before target
                jQuery(target).before( jqNode.detach() );
                // Update parent
                jQuery(jsNode).attr("data-parent", jQuery(target).attr("data-parent"));
                // Update level
                jsNode.jqTree.level = target.jqTree.level;
                break;
            case "after":
                // Move node after target
                jQuery(target).after( jqNode.detach() );
                // Update parent
                jQuery(jsNode).attr("data-parent", jQuery(target).attr("data-parent"));
                // Update level
                jsNode.jqTree.level = target.jqTree.level;
                break;
        }
        if (typeof details.id != "undefined") {
            // Update node id
            jsNode.jqTree.settings.id = details.id;
        }
        // Add new level class
        jQuery(jsNode).addClass(treeSettings.cssNodeLevel+jsNode.jqTree.level);
        if (treeSettings.mode == "list") {
            // Cleanup previous parent
            if (isTreeNode(jsParent)) {
                var jqChildren = jQuery(jsParent).children("ul");
                if ((jqChildren.length == 1) && (jqChildren.children("li").length === 0)) {
                    // Node does not have any children anymore!
                    jsParent.jqTree.settings.expandable = false;
                    jQuery(jsParent).removeClass(treeSettings.cssNodeExpandable);
                    jQuery(jsParent).removeClass(treeSettings.cssNodeExpanded);
                    // Remove empty child container
                    jqChildren.remove();
                }
            }
            if (treeSettings.updateChildsOnMove) {
                // Update subtree
                if (jQuery(jsNode).children("ul").length > 0) {
                    ajaxReadTreeChilds(jsNode.jqTree.tree, jsNode);
                }
            } else {
                updateNodeLevels(jsNode);
            }
        } else if (treeSettings.mode == "table") {
            // Cleanup previous parent
            var jqParentChilds = jQuery(jsParent.jqTree.tree).children("tbody").children("tr[data-parent="+jsParent.jqTree.settings.id+"]");
            if (jqParentChilds.length === 0) {
                // Node does not have any children anymore!
                jsParent.jqTree.settings.expandable = false;
                jQuery(jsParent).removeClass(treeSettings.cssNodeExpandable);
                jQuery(jsParent).removeClass(treeSettings.cssNodeExpanded);
            }
            if (treeSettings.updateChildsOnMove) {
                var jqChildren = jQuery(jsNode.jqTree.tree).children("tbody").children("tr[data-parent="+jsNode.jqTree.settings.id+"]");
                if (jqChildren.length > 0) {
                    // Update subtree
                    ajaxReadTreeChilds(jsNode.jqTree.tree, jsNode);
                }
            } else {
                // Update node-levels of child nodes
                updateNodeLevels(jsNode);
            }
        }
    }

    /**
     * onclick-event for tree nodes
     * @param event
     */
    function eventTreeNodeClick(event) {
        event.preventDefault();
        var offset = jQuery(this).offset();
        var position = {
            x: event.pageX - offset.left,
            y: event.pageY - offset.top,
            height: jQuery(this).height(),
            width: jQuery(this).width()
        };
        eventSpecialTreeNodeClick( this.jqTree.node, position, event.shiftKey, event.ctrlKey );
    }

    /**
     * ondoubleclick-event for tree nodes
     * @param event
     */
    function eventTreeNodeDoubleClick(event) {
        event.preventDefault();
        eventSpecialTreeNodeOpen( this.jqTree.node );
    }

    /**
     * contextmenu-event for tree nodes
     * @param event
     */
    function eventTreeNodeRightClick(event) {
        var jsNode = checkNode(this.jqTree.node);
        if ((jsNode !== false) && (jsNode.jqTree.settings.menu !== false)) {
            event.preventDefault();
            eventSpecialTreeNodeMenu( jsNode, this, event.originalEvent.layerX+"px", event.originalEvent.layerY+"px" );
        }
    }

    /**
     * dragstart-event for tree nodes
     * @param event
     */
    function eventTreeNodeDragStart(event) {
        event.originalEvent.dataTransfer.setData("text/plain", "jqTreeNode");
        eventSpecialTreeNodeDrag( this.jqTree.node );
    }

    /**
     * dragover-event for tree nodes
     * @param event
     */
    function eventTreeNodeDragOver(event) {
        if (dragNodes !== false) {
            var y = event.originalEvent.layerY;
            var height = jQuery(event.originalEvent.currentTarget).height();
            var dragAccepted = eventSpecialTreeNodeDragOver(this.jqTree.node, y, height);
            if (dragAccepted) {
                event.preventDefault();
            }
        }
    }

    /**
     * dragleave-event for tree nodes
     * @param event
     */
    function eventTreeNodeDragLeave(event) {
        eventSpecialTreeNodeDragLeave( this.jqTree.node );
    }

    /**
     * dragend-event for tree nodes
     * @param event
     */
    function eventTreeNodeDragEnd(event) {
        eventSpecialTreeNodeDragStop();
    }

    /**
     * drop-event for tree nodes
     * @param event
     */
    function eventTreeNodeDrop(event) {
        if (eventSpecialTreeNodeDrop()) {
            event.preventDefault();
        }
        eventSpecialTreeNodeDragStop();
    }

    /**
     * touchstart-event for tree nodes
     * @param event
     */
    function eventTreeNodeTouchStart(event) {
        event.preventDefault();
        var jsNode = this.jqTree.node;
        eventSpecialTreeNodeDrag( jsNode );
        if (dragNodes !== false) {
            jsNode.jqTree.touchStart = new Date().getTime();
            jsNode.jqTree.touchX = event.originalEvent.touches[0].pageX;
            jsNode.jqTree.touchY = event.originalEvent.touches[0].pageY;
        }
    }

    /**
     * touchmove-event for tree nodes
     * @param event
     */
    function eventTreeNodeTouchMove(event) {
        if (dragNodes !== false) {
            var x = event.originalEvent.touches[0].pageX;
            var y = event.originalEvent.touches[0].pageY;
            var relY = false;
            var jsTree = this.jqTree.node.jqTree.tree;
            var jqNode = false;
            jQuery(jsTree).find('a:visible').each(function() {
                if ((typeof this.jqTree !== "undefined") && (typeof this.jqTree.node !== undefined)) {
                    // check if is inside boundaries
                    var targetPosition = jQuery(this).offset();
                    if (!((x <= targetPosition.left) || (x >= targetPosition.left + jQuery(this).outerWidth()) ||
                        (y <= targetPosition.top)  || (y >= targetPosition.top + jQuery(this).outerHeight()))) {
                        jqNode = this.jqTree.node;
                        relY = y - targetPosition.top;
                    }
                }
            });
            if (jqNode !== false) {
                if ((dragTarget !== false) && (dragTarget != jqNode[0])) {
                    eventSpecialTreeNodeDragLeave( dragTarget );
                }
                var dragAccepted = eventSpecialTreeNodeDragOver(jqNode, relY, jQuery(jqNode).height());
                if (dragAccepted) {
                    event.preventDefault();
                }
            }
        }
    }

    /**
     * touchend-event for tree nodes
     * @param event
     */
    function eventTreeNodeTouchEnd(event) {
        if (eventSpecialTreeNodeDrop()) {
            event.preventDefault();
        } else if (dragNodes !== false) {
            var jsNode = this.jqTree.node;
            if (typeof jsNode.jqTree.touchStart != "undefined") {
                var touchEnd = new Date().getTime();
                var touchDuration = touchEnd - jsNode.jqTree.touchStart;
                jsNode.jqTree.touchStart = undefined;
                var treeSettings = jsNode.jqTree.tree.jqTree.settings;
                if (touchDuration < treeSettings.touchMaxTapDuration) {
                    if ((typeof jsNode.jqTree.touchLast !== "undefined") && ((jsNode.jqTree.touchLast + treeSettings.touchMaxDoubleTapDuration) > touchEnd)) {
                        // Double tap
                        eventSpecialTreeNodeOpen( jsNode );
                    } else {
                        // Single tap
                        var offset = jQuery(this).offset();
                        var position = {
                            x: jsNode.jqTree.touchX - offset.left,
                            y: jsNode.jqTree.touchY - offset.top,
                            height: jQuery(jsNode).height(),
                            width: jQuery(jsNode).width()
                        };
                        eventSpecialTreeNodeClick( jsNode, position );
                    }
                    jsNode.jqTree.touchLast = touchEnd;
                }
            }
        }
        eventSpecialTreeNodeDragStop();
    }

    /**
     * touchcancel-event for tree nodes
     * @param event
     */
    function eventTreeNodeTouchCancel(event) {
        eventSpecialTreeNodeDragStop();
    }

    /**
     * Checks if one of the values in "arrayValues" is within "arraySearch"
     * @param array arraySearch
     * @param array arrayValues
     */
    function utilArrayContains(arraySearch, arrayValues) {
        var indexValue;
        var indexSearch;
        for (indexValue = 0; indexValue < arrayValues.length; indexValue++) {
            for (indexSearch = 0; indexSearch < arraySearch.length; indexSearch++) {
                if (arrayValues[indexValue] == arraySearch[indexSearch]) {
                    return true;
                }
            }
        }
        return false;
    }

}( jQuery ));
