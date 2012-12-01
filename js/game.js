var Time = {
	diff: 0
};
var Fn = {
	switch_display: function(type, counter) {
		this.do_display(type);
	},
	do_display: function(type) {
		sync_on_off_music_button();
		$('.game_base:not(.game_info):not(.game_'+type+')').hide();
		$('.game_'+type).show();
	},
	process_ready: function(response){
		if (response.ready) {
			var need_opponents_refresh = false;
			$.each(response.users, function(key, value){
				var user = value.id_user;
				if (Game.opponents[user]) {
					return;
				}

				Game.opponents[user] = Game.users[user];
				$('body').trigger('message', Game.opponents[user].login +
					' собрал колоду и готов играть.');
				need_opponents_refresh = true;
			});

			if (need_opponents_refresh) {
				refresh_opponents();
			}

			if (!Game.deck) {
				if (Game.got_cards) {
					build_deck(response.deck);
				} else {
					get_base_data(function(){build_deck(response.deck);});
				}
				this.switch_display('ready');
			}
		}
	},
	process_actions: function(response){
		if (!response.action) {
			return;
		}

		var type = response.action.type;
		var time = new Date(response.action.time * 1000);

		if (type == Game.current_action &&
			time.getTime() == Game.current_action_time.getTime()) {

			return;
		}

		Game.current_action = type;
		Game.current_action_time = time;

		var fn = 'process_action_' + type;
		if (this[fn]) {
			this[fn].call(this, time, response.action.data);
		}
	},
	process_action_build: function(time, data) {
		if (Game.got_cards) {
			display_look(time, 1);
		} else {
			get_base_data(function(){display_look(time, 1);});
		}
	},
	process_response: function(response) {
		this.process_ready(response);
		this.process_actions(response);
	}
};

init_sizes();

if (Game.start > 0) {
	var start = new Date(Game.start * 1000);
	$('.game_info .utc_date').html(start.format('dd.mm.yyyy HH:MM'));
} else {
	$('.game_info .utc_date').html('Не указано');
}

$('.game_start_button').click(function(){
	if (this.starting) {
		return;
	}

	var ids = '', ask = '', count = 0, game_users = {}, me = this;
	$.each(Chat.users, function(key, value){
		if (value.present) {
			count++;
			ids += key + ',';
			ask += value.name + ', ';
			game_users[key] = value;
		}
	});
	var confirm_text = 'Вы хотите начать драфт следующим составом: ' +
		ask.substring(0, ask.length - 2) + ' (участников: ' + count + ')?';
	if (confirm(confirm_text)) {
		$('.game_start_button img').show();
		$('.game_start_button').addClass('disabled');
		this.starting = true;

		$.get('/ajax_game/start', {id: Game.id, user: ids}, function(response) {
			$('.game_start_button img').hide();
			$('.game_start_button').removeClass('disabled');
			me.starting = false;

			if (response.success) {
				Game.users = game_users;
			}
		});
	}
});

function get_game_data() {
	if (Game.getting) {
		return;
	}
	Game.getting = true;
	$.ajax({
		url: '/ajax_game/get_data',
		data: {id: Game.id},
		error: function(response) {
			Game.getting = false;
		},
		success: function(response) {
			Game.getting = false;
			if (!response.success) {
				return;
			}

			Fn.process_response(response);
		}
	});
}

function display_look(time, build) {
	$('.game_look .loader').show();
	$('.game_look .card_pool').hide().children(':not(h2)').remove();

	if (!build) {
		Fn.switch_display('look', Math.ceil((time.getTime() - (new Date()).getTime() + Time.diff) / 1000));
	} else {
		Fn.switch_display('look');
	}

	$.get('/ajax_game/get_deck', {id: Game.id, add_land: build}, function(response){
		if (!response.success || !response.cards) {
			return;
		}

		var card_pool = {};
		var deck = [];

		$.each(response.cards, function(id, card) {
			var count = card.count;
			var card = Game.card[card.id_card];

			if (!card_pool[card.color]) {
				card_pool[card.color] = [];
			}

			card_pool[card.color].push({id: card.id, name: card.name,
				image: card.full, count: count});

			deck.push({id: card.id, name: card.name,
				image: card.full, count: 0});
		});

		$.each(card_pool, function(id, dev_null) {
			card_pool[id].sort(function(a, b){
				if (a.count != b.count) {
					return a.count < b.count;
				}

				return a.name.localeCompare(b.name);
			});
		});

		insert_card_pool(card_pool, 'M', 'Multicolor');
		insert_card_pool(card_pool, 'W', 'White');
		insert_card_pool(card_pool, 'G', 'Green');
		insert_card_pool(card_pool, 'R', 'Red');
		insert_card_pool(card_pool, 'B', 'Black');
		insert_card_pool(card_pool, 'U', 'Blue');
		insert_card_pool(card_pool, 'A', 'Artifact');
		insert_card_pool(card_pool, 'L', 'Land');

		$.each(card_pool, function(id, dev_null) {
			insert_card_pool(card_pool, id, id);
		});

		if (build) {
			$('.game_look .card_pool h2').show();
			$('.add_card').show();
			Game.deck_building = true;
			deck.sort(function(a, b){return a.name.localeCompare(b.name);});
			insert_deck(deck);
		}

		$('.game_look .loader').hide();
		$('.game_look .card_pool').slideDown();
		if (build) {
			$('.game_look .deck').slideDown();
		}
	});
}

function insert_card_pool(data, index, name) {
	if (!data[index]) {
		return;
	}

	var header = $('<div/>').addClass('card_pool_color_header').html(name);
	var div = $('<div/>').addClass('card_pool_color').append(header);

	$.each(data[index], function(id, item){
		var span = $('<span/>').data('item', item).bind('compile', function(){
			var item = $(this).data('item');
			$(this).html(item.count + ' x ' + item.name);
		}).addClass('card_pool-' + item.id).addClass('hover_card').trigger('compile');
		var row = $('<div/>').addClass('card_pool_row').append(span);
		div.append(row);
	});

	$('.game_look .card_pool').append(div);

	delete data[index];
}

function insert_deck(data) {
	$.each(data, function(id, item){
		var div = $('<div/>').data('item', item).bind('compile', function(e, display){
			var item = $(this).data('item');
			$(this).html('<span class="drag_card">' + item.count + ' x ' + item.name + '</span>' +
				' <span class="remove_card">&#9746;</span>');

			if (item.count == 0) {
				$(this).appendTo('.buffer');
			} else if (display) {
				$(this).appendTo('.slot:last .items');
				$(this).draggable('destroy');

				$(this).draggable({
					axis: 'x',
					containment: '.slot_holder',
					handle: '.drag_card',
					cursor: 'move',
					stop: function(e) {
						var left = parseInt($(this).css('left'));
						$(this).css('left', '0px');
						if (left > 300) {
							var shift = 2;
						} else if (left > 100) {
							var shift = 1;
						} else if (left < -300) {
							var shift = -2;
						} else if (left < -100) {
							var shift = -1;
						} else {
							return;
						}
						var newParent = $('.slot').index($(this).parent().parent()) + shift;
						if (newParent > 2 || newParent < 0) {
							return;
						}

						$(this).appendTo($('.slot_holder .slot').eq(newParent).children('.items'));
						check_slot_height();
					}
				});
			}
			check_slot_height();
		}).addClass('deck-' + item.id).addClass('hover_card').trigger('compile');
		$('.deck .buffer').append(div);
	});
}

function get_base_data(callback) {
	callback = callback || function(){};

	$.get('/time.php', function(response) {
		Time.diff = new Date().getTime() - new Date(response.time * 1000).getTime();
	});

	$.get('/ajax_game/get_user', {id: Game.id}, function(response) {
		if (!response.success || !response.user) {
			return;
		}

		var found = false, users = [];
		$.each(response.user, function(key, user) {
			if (User.id == user.id) {
				found = true;
			}

			Game.users[user.id] = user;

			if (user.signed_out == '0') {
				var md5 = $.md5(user.login);

				var parts = [md5[0] + md5[1], md5[2] + md5[3], md5[4] + md5[5]];
				$.each(parts, function(key, value) {
					value = Math.floor((parseInt(value, 16) / 2)).toString(16);
					parts[key] = value;
				});
				var color = parts.join('');
			} else {
				var color = 'BBBBBB';
			}
			users.push('<span style="color: #'+ color + ';" ' +
				'class="game_user game_user_' + user.id + '">' + user.login + '</span>');
		});
		if (!found) {
			document.location.href = '/';
		}

		$(".participants").html('Участвуют: ' + users.join(', ') + '.');
	});

	get_cards_data(callback);
}

function get_cards_data(callback) {
	$.get('/ajax_game/get_card', {id: Game.id}, function(response) {
		if (!response.success || !response.cards) {
			return;
		}

		$.each(response.cards, function(id, card){
			card.id = id;
			Game.card[id] = card;
			Game.card[id].small = new Image();
			Game.card[id].full = new Image();
			Game.card[id].small.src = '/images/small' + card.image;
			Game.card[id].full.src = '/images/full' + card.image;
		});

		Game.got_cards = true;

		callback.call(this);
	});
}

if (Game.state == 0) {
	Fn.switch_display('waiting_start');
	$('body').everyTime(1500, get_game_data);
} else {
	get_base_data(function(){
		$('body').everyTime(1500, get_game_data);
	});
}

$('.hover_card').live({
	mouseover: function(){
		var image = $(this).data('item').image;
		$('.display_card img').attr('src', image.src);
		$('.display_card').show();
	},
	mouseout: function(){
		$('.display_card').hide();
	}
});

$('.card_pool_row span').live({
	click: function(){
		if (!Game.deck_building) {
			return;
		}
		var item = $(this).data('item');
		if (!item.count) {
			return;
		}
		item.count--;
		var target = $('.deck-' + item.id), item_target = target.data('item');
		item_target.count++;

		$(this).data('item', item).trigger('compile');
		target.data('item', item_target).trigger('compile', item_target.count == 1);
		check_create_button();
	}
});

$('.remove_card').live({
	click: function(){
		var parent = $(this).parent();
		var item = parent.data('item');
		if (!item.count) {
			return;
		}
		item.count--;
		var target = $('.card_pool-' + item.id), item_target = target.data('item');
		item_target.count++;

		parent.data('item', item).trigger('compile');
		target.data('item', item_target).trigger('compile');
		check_create_button();
	}
});

$('.deck_finish').click(function(){
	if ($(this).is('.disabled')) {
		return;
	}

	$('.game_look .loader').show();
	$('.game_look .card_pool').hide();
	$('.game_look .deck').hide();

	var cards = [];
	$('.slot .items > div').each(function(){
		var item = $(this).data('item');
		for (var i = 0; i < item.count; i++) {
			cards.push(item.id);
		}
	});

	$.get('/ajax_game/set_deck', {id: Game.id, c: cards}, function(response) {
		if (!response.success) {
			$('.game_look .loader').hide();
			$('.game_look .card_pool').show();
			$('.game_look .deck').show();
			alert('Не удалось создать колоду.');
		} else {
			Game.deck = false;
		}
	});
});

$('.game_ready .deck_side').click(function(){
	display_look(0, 1);
});

$('.challenge button').click(function(){
	var opponent = $('.challenge .opponents').val();
	if (!opponent || !Game.users[opponent]) {
		return;
	}

	var win = window.open('', 'Дуэль');

	win.document.write('<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">' +
		'<html>' +
			'<head>' +
				'<meta content="text/html; charset=UTF-8" http-equiv="Content-Type" />' +
			'</head>' +
		'<body onLoad="document.getElementById(\'form\').submit()">' +
			'<form action="http://www.mtg.ru/play/start.phtml" method="POST" id="form">' +
				'<input type="hidden" name="Player" value="' + Game.users[User.id].nickname + '">' +
				'<input type="hidden" name="Player_Avatar" value="http://mtgdraft.ru/images/avatar/' + User.avatar + '.jpg">' +
				'<input type="hidden" name="Oponent" value="' + Game.users[opponent].nickname + '">' +
				'<input type="hidden" name="Oponent_Avatar" value="http://mtgdraft.ru/images/avatar/' + Game.users[opponent].avatar + '.jpg">' +
				'<input type="hidden" name="Lang" value="EN">' +
				'<textarea style="display:none;" name="Deck">' + Game.decklist + '</textarea>' +
			'</form>' +
		'</body>' +
	'</html>');
	win.document.close();
});

function check_slot_height() {
	var height = 150;

	$('.slot').each(function(){
		height = Math.max(height, $(this).children('.items').height());
	});

	$('.slot').each(function(){
		$(this).css('min-height', height + 'px');
	});
}

function check_create_button() {
	var count = 0, type_count = 0;

	$('.slot .items > div').each(function(){
		count += $(this).data('item').count;
		type_count++;
	});

	if (count >= 40 && type_count <= 50) {
		$('.deck_finish').removeClass('disabled');
	} else {
		$('.deck_finish').addClass('disabled');
	}

	$('.count_cards .count').html(count);
}

function build_deck(cards) {
	Game.deck = {}, Game.side = {};
	$.each(cards, function(key, card){
		if (card.deck == 0) {

			if (!Game.side[card.id_card]) {
				Game.side[card.id_card] = 0;
			}
			Game.side[card.id_card]++;

		} else {

			if (!Game.deck[card.id_card]) {
				Game.deck[card.id_card] = 0;
			}
			Game.deck[card.id_card]++;
		}
	});

	var list = [];
	$.each(Game.deck, function(id, count) {
		list.push(count + ' ' + Game.card[id].name);
	});

	Game.decklist = list.join('\n');
}

function refresh_opponents() {
	$('.challenge .opponents').children().remove();

	$.each(Game.opponents, function(id, opponent) {
		$('.challenge .opponents').append('<option value="' +
			opponent.id + '">' + opponent.login + '</option>');
	});
}
