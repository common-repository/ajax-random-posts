jQuery(function($){
	if ($("#ajaxrp").length == 0) return;
	$.ajax({
		url 	: "index.php",
		type	: "POST",
		data 	: "ajaxrp_action=get_posts&data=" + $("#ajaxrpData").val(),
		success	: function(msg){
			$("#ajaxrp").html(msg);
		}
	});
});