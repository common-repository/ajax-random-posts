jQuery(function($){
	$(".toggler").click(function(e){
		$(this).next(".toggled").slideToggle();
	});
});