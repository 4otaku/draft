var Draft = {
	last_time: null
};

function setSizes() {
	var containerHeight = $(window).height();
	var usersHeight = $('.chat_users').height() + 18;
	$(".content").height(containerHeight - 40);
	$(".chat").height(containerHeight - 240);
	$(".left_wrapper").height(containerHeight - 140);
	$(".chat_messages").height(containerHeight - 268 - usersHeight);
}

$(window).resize(function() { setSizes();});

function do_get_draft(callback, scope) {
	callback = callback || function(){};
	scope = scope || this;

	$.get('/ajax/get_draft', function(response) {
		$.each(response.data, function(key, item){
			if (Draft[item.id]) {
				return;
			}

			Draft[item.id] = item;
			var object = $('.draft_example').clone();
			object.prependTo('.left_wrapper').slideDown(1500)
				.removeClass('draft_example');
		});

		callback.call(scope);
	});
}

function hide_draft_loader() {
	this.find('.draft-actions button').show();
	this.find('.draft-actions .loader').hide();
	this.find('.draft_cancel').click();
}

$(document).ready(function(){
	setSizes();

	$('body').bind('last_draft', function(e, time){
		time = new Date(time * 1000);
		if (!Draft.last_time || Draft.last_time < time) {
			Draft.last_time = time;
			do_get_draft();
		}
	});

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
			do_get_draft(hide_draft_loader, parent);
		});
	});
});
