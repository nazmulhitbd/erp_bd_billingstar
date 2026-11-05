	
			tinymce.init({
				extended_valid_elements : "marquee",
				selector: "textarea",
				plugins: 
				[
					"advlist autolink autosave link image lists charmap print preview hr anchor pagebreak spellchecker",
					"searchreplace wordcount visualblocks visualchars code fullscreen insertdatetime media nonbreaking",
					"table contextmenu directionality emoticons template textcolor paste fullpage textcolor"
				],
				toolbar1: "newdocument code fullpage | bold italic underline strikethrough | alignleft aligncenter alignright alignjustify | styleselect formatselect fontselect fontsizeselect",
				toolbar2: "cut copy paste pastetext | searchreplace | bullist numlist | outdent indent blockquote | undo redo | link unlink anchor image media | inserttime | forecolor backcolor",
				toolbar3: "table | hr removeformat | subscript superscript | charmap emoticons | ltr rtl | visualchars visualblocks nonbreaking template pagebreak restoredraft preview",
				menubar: false,
				toolbar_items_size: 'small',
				height : '350px',
				style_formats: 
				[
					{title: 'Bold text', inline: 'b'},
					{title: 'Red text', inline: 'span', styles: {color: '#ff0000'}},
					{title: 'Red header', block: 'h1', styles: {color: '#ff0000'}},
					{title: 'Example 1', inline: 'span', classes: 'example1'},
					{title: 'Example 2', inline: 'span', classes: 'example2'},
					{title: 'Table styles'},
					{title: 'Table row 1', selector: 'tr', classes: 'tablerow1'}
				],
				templates: 
				[
					{title: 'Test template 1', content: '<b>Test 1</b>'},
					{title: 'Test template 2', content: '<em>Test 2</em>'}
				]
			});
			function umce(){tinyMCE.triggerSave()};
			function ut()
			{
				document.theForm.submit();
    				return false;
			};
		