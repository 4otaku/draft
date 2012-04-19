function setSizes() {
   var containerHeight = $(window).height();
   $(".content").height(containerHeight - 40);
}

$(window).resize(function() { setSizes();});

$(document).ready(function(){
	setSizes();

	$('.draft_add .btn-large').click(function(){
		$(this).hide();
		$(this).parent().children('.form-horizontal').show();
	});

	$('.draft_add .draft_cancel').click(function(){
		var parent = $(this).parents('.draft_add');
		parent.children('.form-horizontal').hide();
		parent.children('.btn-large').show();
	});
});
