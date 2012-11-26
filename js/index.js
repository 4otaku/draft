init_sizes();

var Game = {
	last_time: null
};

function do_get_game(callback, scope) {
	callback = callback || function(){};
	scope = scope || this;

	$.get('/ajax_game_list/get', function(response) {
		var ids = {};
		$.each(response.data, function(key, item){
			ids[item.id] = true;

			if (Game[item.id]) {
				if (Game[item.id].state != item.state) {
					Game[item.id] = item;
					Game[item.id].update_state = true;
				}
				return;
			}

			Game[item.id] = item;
			Game[item.id].update_state = true;
			var cls = 'game_example_' + item.type;
			var selector = '.' + cls;
			var object = $(selector).clone().attr('id', 'game-' + item.id);
			var booster = item.booster.split(',');
			object.find('.name').html(item.login);
			if (booster[0]) {
				object.find('.booster_1').html(booster[0]);
			}
			if (item.start != null) {
				var start = new Date(item.start * 1000);
				object.find('.timestart').html(start.format('dd.mm.yyyy HH:MM'));
			} else {
				object.find('.start').hide();
			}
			if (booster[1]) {
				object.find('.booster_2').html(booster[1]);
			}
			if (booster[2]) {
				object.find('.booster_3').html(booster[2]);
			}
			if (booster[3]) {
				object.find('.booster_4').html(booster[3]);
			}
			if (booster[4]) {
				object.find('.booster_5').html(booster[4]);
			}
			if (booster[5]) {
				object.find('.booster_6').html(booster[5]);
			}
			object.find('.pick_time').html(format_time(item.pick_time));
			object.find('.pause_time').html(format_time(item.pause_time));
			object.find('.join').attr('href', '/game/' + item.id);
			object.find('.unjoin_going').click(function(){
				if (confirm('Вы уверены, что хотите отказаться от дальнейшего участия в этом драфте?')) {
					$.get('/ajax_game_list/leave', {id: item.id});
					object.remove();
				}
			});
			object.prependTo('.left_wrapper').slideDown(1500).removeClass(cls);

			$('body').trigger('message', (item.type == '2' ? 'Силед' : 'Драфт') + ' №' +
				item.id + ' (' + booster.join(', ') +') добавлен.');
		});

		$.each(Game, function(key, item) {
			if (!ids[key] && key != 'last_time') {
				delete Game[key];
				if ($('#game-' + key).length > 0) {
					$('#game-' + key).slideUp(1500);

					$('body').trigger('message', (item.type == '2' ? 'Силед' : 'Драфт') +
						' №' + item.id + ' (' + item.booster.replace(/,/g,', ') +') удален.');
				}
			}

			if (item.update_state) {
				var object = $('#game-' + key);

				if (item.state == 0) {
					if (item.id_user == User.id) {
						object.find('.delete').show().click(function(){
							if (confirm('Вы уверены, что хотите удалить драфт?')) {
								object.slideUp(1500);
								$.get('/ajax_game_list/delete', {id: item.id});
							}
						});
					}
				} else if (item.state == 1) {
					if (item.presense > 0) {
						object.find('.join_going').show();
						object.find('.unjoin_going').show();
					} else {
						object.find('.join_going').hide();
						object.find('.unjoin_going').hide();
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

function hide_game_loader() {
	this.find('.game-actions button').show();
	this.find('.game-actions .loader').hide();
	this.find('.game_cancel').click();
}

function send_create_data(form) {
	var parent = form.parents('.game_add'),
		request = form.find('select, input[type=text], input[type=hidden]').serialize();

	parent.find('.game-actions button').hide();
	parent.find('.game-actions .loader').show();

	var date = new Date();
	request += '&utc=' + date.getTimezoneOffset();

	$.get('/ajax_game_list/add?' + request, function(response) {
		do_get_game(hide_game_loader, parent);
	});
}

$(document).ready(function(){

	$('body').bind('game_change', function(e, time){
		time = time ? new Date(time * 1000) : new Date();
		if (!Game.last_time || Game.last_time < time) {
			Game.last_time = time;
			do_get_game();
		}
	}).trigger('game_change');

	$('#timestart_game').timepicker();
	$('#timestart_sealed').timepicker();
	$('#timestart_masters').timepicker();

	$('.game_add .btn.game_form_show').click(function(){
		var parent = $(this).parents('.game_add');
		var type = $('#game_type :selected').val();
		parent.children('.control-group.add').hide();
		parent.children('.game_form_' + type).show();
	});

	$('.game_add .game_cancel, .game_add .sealed_cancel').click(function(){
		var parent = $(this).parents('.game_add');
		parent.children('.form-horizontal').hide();
		parent.children('.control-group.add').show();
	});

	$('.selected').attr('selected', 'selected');

	$('.game_add .game_create').click(function(){
		send_create_data($(this).parents('.form-horizontal'));
	});
});
