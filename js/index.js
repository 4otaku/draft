function setSizes() {
	var containerHeight = $(window).height();
	var usersHeight = $('.chat_users').height() + 18;
	$(".content").height(containerHeight - 40);
	$(".chat").height(containerHeight - 240);
	$(".chat_messages").height(containerHeight - 268 - usersHeight);
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

	$('.selected').attr('selected', 'selected');

	$('.draft_add .draft_create').click(function(){
		var parent = $(this).parents('.draft_add'),
			request = parent.children('.form-horizontal').find('select').serialize();

		parent.find('.draft-actions button').hide();
		parent.find('.draft-actions .loader').show();

		$.get('/ajax/add_draft?' + request, function(response) {
			console.log(response);

			parent.find('.draft-actions button').show();
			parent.find('.draft-actions .loader').hide();
			parent.find('.draft_cancel').click();
		});
	});
});
