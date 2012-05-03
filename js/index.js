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
		var ids = {};
		$.each(response.data, function(key, item){
			ids[item.id] = true;

			if (Draft[item.id]) {
				return;
			}

			Draft[item.id] = item;
			var object = $('.draft_example').clone().attr('id', 'draft-' + item.id);
			var booster = item.booster.split(',');
			object.find('.name').html(item.login);
			if (booster[0]) {
				object.find('.booster_1').html(booster[0]);
			}
			if (booster[1]) {
				object.find('.booster_2').html(booster[1]);
			}
			if (booster[2]) {
				object.find('.booster_3').html(booster[2]);
			}
			object.find('.pick_time').html(format_time(item.pick_time));
			object.find('.pause_time').html(format_time(item.pause_time));
			object.find('.join').attr('href', '/draft/' + item.id);
			if (item.id_user == User.id) {
				object.find('.delete').show().click(function(){
					if (confirm('Вы уверены, что хотите удалить драфт?')) {
						object.slideUp(1500);
						$.get('/ajax/delete_draft', {id: item.id});
					}
				});
			}
			object.prependTo('.left_wrapper').slideDown(1500)
				.removeClass('draft_example');

			$('body').trigger('message', 'Драфт №' +
				item.id + ' (' + booster.join(', ') +') добавлен.');

		});


		$.each(Draft, function(key, item) {
			if (!ids[key] && key != 'last_time') {
				delete Draft[key];
				if ($('#draft-' + key).length > 0) {
					$('#draft-' + key).slideUp(1500);

					$('body').trigger('message', 'Драфт №' + item.id +
						' (' + item.booster.replace(/,/g,', ') +') удален.');
				}
			}
		});

		callback.call(scope);
	});
}

function format_time(seconds) {
	if (seconds < 120) {
		return seconds + ' секунд';
	}

	var minutes = Math.round(seconds / 60, 2);
	return minutes + ' минуты';
}

function hide_draft_loader() {
	this.find('.draft-actions button').show();
	this.find('.draft-actions .loader').hide();
	this.find('.draft_cancel').click();
}

$(document).ready(function(){
	setSizes();

	$('body').bind('draft_change', function(e, time){
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
