jQuery(document).ready(function() {
	jQuery(".howto").first().show();
	jQuery( ".navitem" ).click(function() {
		var target = jQuery(this).attr('id');
		jQuery(".tabdown").find(".howto").hide();
		jQuery(".tabdown").find("."+target).show();
	});
});