(function() {

	var ICONS;

	var icon_i = function(id) {
		return '<i class="fa icon-fw fa-fw icon-' + id + ' fa-' + id + '"></i>';
	}

	var icon_shortcode = function(id) {
		return '[icon name="' + id + '" class=""]';
	}

	var createControl = function(name, controlManager) {
		if (name != 'fontAwesomeGlyphSelect') return null;
		var listBox = controlManager.createListBox('fontAwesomeGlyphSelect', {
			title: 'Icons',
			onselect: function(v) {
				var editor = this.control_manager.editor;
				if (v) {
					editor.selection.setContent(icon_shortcode(v));
				}		
				return false;
			}
		});

		for (var i = 0; i < ICONS.length; i++) {
			var _id = ICONS[i];
			listBox.add(icon_i(_id) + ' ' + _id, _id);
		}

		return listBox;
	};

	tinymce.create('tinymce.plugins.FontAwesomeGlyphPlugin', {
		createControl: createControl
	});

	tinymce.PluginManager.add('font_awesome_glyphs', tinymce.plugins.FontAwesomeGlyphPlugin);

	var ICONS = bfa_vars.fa_icons.split(',');
})();