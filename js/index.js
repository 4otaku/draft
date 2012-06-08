init_sizes();

var Draft = {
	last_time: null
};

function do_get_draft(callback, scope) {
	callback = callback || function(){};
	scope = scope || this;

	$.get('/ajax/get_draft', function(response) {
		var ids = {};
		$.each(response.data, function(key, item){
			ids[item.id] = true;

			if (Draft[item.id]) {
				if (Draft[item.id].state != item.state) {
					Draft[item.id] = item;
					Draft[item.id].update_state = true;
				}
				return;
			}

			Draft[item.id] = item;
			Draft[item.id].update_state = true;
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

			if (item.update_state) {
				var object = $('#draft-' + key);

				if (item.state == 0) {
					if (item.id_user == User.id) {
						object.find('.delete').show().click(function(){
							if (confirm('Вы уверены, что хотите удалить драфт?')) {
								object.slideUp(1500);
								$.get('/ajax/delete_draft', {id: item.id});
							}
						});
					}
				} else if (item.state == 1) {
					if (item.presense > 0) {
						object.find('.join_going').show();
					} else {
						object.find('.join_going').hide();
					}
				}

				object.find('.wrapper_state').hide();
				object.find('.wrapper_state_' + item.state).show();

				item.update_state = false;
			}
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

	$('body').bind('draft_change', function(e, time){
		time = time ? new Date(time * 1000) : new Date();
		if (!Draft.last_time || Draft.last_time < time) {
			Draft.last_time = time;
			do_get_draft();
		}
	}).trigger('draft_change');

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
