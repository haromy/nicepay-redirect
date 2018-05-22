jQuery(document).ready(function() {
	jQuery(".navitem").first().addClass("active");
	jQuery(".howto").first().show();
	jQuery( ".navitem" ).click(function() {
		var target = jQuery(this).attr('id');
		jQuery(".headnav").find(".active").removeClass("active");
		jQuery(".tabdown").find(".howto").hide();
		jQuery(".headnav").find("#"+target).addClass("active");
		jQuery(".tabdown").find("."+target).show();

	});
});