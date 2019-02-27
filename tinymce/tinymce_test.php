<html>
<head>
	<title>Ajax File Manager</title>
	<script language="javascript" type="text/javascript" src="jscripts/tiny_mce/tiny_mce.js"></script>
	<script language="javascript" type="text/javascript" src="jscripts/general.js"></script>
	<script language="javascript" type="text/javascript">
		tinyMCE.init({
			mode : "exact",
			elements : "ajaxfilemanager",
			theme : "advanced",
			plugins : "advimage,advlink,media,contextmenu",
			theme_advanced_buttons1_add_before : "newdocument,separator",
			theme_advanced_buttons1_add : "fontselect,fontsizeselect",
			theme_advanced_buttons2_add : "separator,forecolor,backcolor,liststyle",
			theme_advanced_buttons2_add_before: "cut,copy,separator,",
			theme_advanced_buttons3_add_before : "",
			theme_advanced_buttons3_add : "media",
			theme_advanced_toolbar_location : "top",
			theme_advanced_toolbar_align : "left",
			extended_valid_elements : "hr[class|width|size|noshade]",
			file_browser_callback : "ajaxfilemanager",
			paste_use_dialog : false,
			theme_advanced_resizing : true,
			theme_advanced_resize_horizontal : true,
			apply_source_formatting : true,
			force_br_newlines : true,
			force_p_newlines : false,	
			relative_urls : true
		});

		function ajaxfilemanager(field_name, url, type, win) {
			var ajaxfilemanagerurl = "/tinymce/ajaxfilemanager/ajaxfilemanager.php?editor=tinymce";
			switch (type) {
				case "image":
					break;
				case "media":
					break;
				case "flash": 
					break;
				case "file":
					break;
				default:
					return false;
			}
            tinyMCE.activeEditor.windowManager.open({
             file : ajaxfilemanagerurl,
             title : 'My File Browser',
             width : 720,  // Your dimensions may differ - toy around with them!
             height : 500,
             resizable : "yes",
             inline : "yes",  // This parameter only has an effect if you use the inlinepopups plugin!
             close_previous : "no"
            }, {
            window : win,
            input : field_name
            });
            return false;
		}

	</script>
</head>
<body>
	<textarea id="ajaxfilemanager" name="ajaxfilemanager" style="width: 100%; height: 6000px"><h1>Ajax File/Image Manager Plugin</h1>
	<p>
		<b>Author:</b> Logan Cai<br>
		<b>Website:</b> <a href="http://www.phpletter.com">http://www.phpletter.com</a>
		<b>Forum:</b> <a href="http://www.phpletter.com/forum">http://www.phpletter.com/forum</a>
	</p>
		
	</textarea>
</body>
</html>