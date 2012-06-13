init_sizes();

$('.draft_info .pick_time').html(format_time(Draft.pick_time));
$('.draft_info .pause_time').html(format_time(Draft.pause_time));

$('.draft_start_button').click(function(){
	if (this.starting) {
		return;
	}

	var ids = '', ask = '', count = 0, draft_users = {}, me = this;
	$.each(Chat.users, function(key, value){
		if (value.present) {
			count++;
			ids += key + ',';
			ask += value.name + ', ';
			draft_users[key] = value;
		}
	});
	var confirm_text = 'Вы хотите начать драфт следующим составом: ' +
		ask.substring(0, ask.length - 2) + ' (участников: ' + count + ')?';
	if (confirm(confirm_text)) {
		$('.draft_start_button img').show();
		$('.draft_start_button').addClass('disabled');
		this.starting = true;

		$.get('/ajax/start_draft', {id: Draft.id, user: ids}, function(response) {
			$('.draft_start_button img').hide();
			$('.draft_start_button').removeClass('disabled');
			me.starting = false;

			if (response.success) {
				Draft.users = draft_users;
			}
		});
	}
});

function get_draft_data() {
	if (Draft.getting) {
		return;
	}
	Draft.getting = true;
	setTimeout(function(){
		Draft.getting = false;
	}, 10000);
	$.get('/ajax/get_draft_data', {id: Draft.id}, function(response) {
		Draft.getting = false;
		if (!response.success) {
			return;
		}

		if (response.ready) {
			var need_opponents_refresh = false;
			$.each(response.users, function(key, value){
				var user = value.id_user;
				if (Draft.opponents[user]) {
					return;
				}

				Draft.opponents[user] = Draft.users[user];
				$('body').trigger('message', Draft.opponents[user].login +
					' собрал колоду и готов играть.');
				need_opponents_refresh = true;
			});

			if (need_opponents_refresh) {
				refresh_opponents();
			}

			if (!Draft.deck) {
				build_deck(response.deck);
				display_ready();
			}
		}

		if (!response.action || !response.forced) {
			return;
		}

		$.each(response.forced, function(key, value) {
			var key = value.id_user + '-' + value.pick;

			if (Draft.forced[key]) {
				return;
			}

			Draft.forced[key] = value;

			if (value.id_card) {
				var msg = 'Вы зазевались на ' + (parseInt(value.pick) + 1) + ' пике ';
				msg += value.order + '-го бустера, и схватили случайную карту. ';
				msg += 'Вам достался "'+Draft.card[value.id_card].name+'".';
			} else {
				var msg = Draft.users[value.id_user].login + ' зазевался на ';
				msg += (parseInt(value.pick) + 1) + ' пике ' + value.order;
				msg += '-го бустера, и схватил случайную карту.';
			}

			$('body').trigger('message', msg);
		});

		var picked = response.action.picked;
		$('.draft_user').css('text-decoration', 'none');

		if (picked) {
			$.each(picked, function(key, value){
				$('.draft_user_' + value.id_user).css('text-decoration', 'underline');
			});
		}

		var type = response.action.type;
		var time = new Date(response.action.time * 1000);

		if (type == Draft.current_action &&
			time.getTime() == Draft.current_action_time.getTime()) {

			return;
		}

		Draft.current_action = type;
		Draft.current_action_time = time;

		if (type == 'start') {
			display_start(time);
		} else if (type == 'look') {
			display_look(time, 0);
		} else if (type == 'build') {
			display_look(time, 1);
		} else {
			display_pick(time, type.replace('pick_', '') - 0);
		}
	});
}

function display_start(time) {
	switch_display('start', Math.ceil((time.getTime() - (new Date()).getTime()) / 1000));

	get_base_data();
}

function display_pick(time, number) {

	Draft.pick = number;

	$('.draft_pick .loader').show();
	$('.draft_pick .cards').hide();
	$('.display_card').hide();
	$('.draft_pick .cards').removeClass('picking').removeClass('picked');
	$('.draft_pick .cards img').removeClass('picking').removeClass('picked');

	switch_display('pick', Math.ceil((time.getTime() - (new Date()).getTime()) / 1000));
	Draft.picking = false;

	$.get('/ajax/get_draft_pick', {id: Draft.id, number: number}, function(response){
		if (!response.success || !response.cards) {
			return;
		}

		$('.draft_pick .cards img').attr('src', '');
		$('.draft_pick .cards img').hide();
		$.each(response.cards, function(id, card) {
			$('.draft_pick .cards .card_' + (id + 1) + ' img')
				.attr('src', Draft.card[card.id_card].small.src)
				.data('id', card.id).show();
		});

		$('.draft_pick .loader').hide();
		$('.draft_pick .cards').fadeIn();
	});
}

function display_look(time, build) {
	$('.draft_look .loader').show();
	$('.draft_look .drafted').hide().children(':not(h2)').remove();

	if (!build) {
		switch_display('look', Math.ceil((time.getTime() - (new Date()).getTime()) / 1000));
	} else {
		switch_display('look');
	}

	$.get('/ajax/get_draft_deck', {id: Draft.id, add_land: build}, function(response){
		if (!response.success || !response.cards) {
			return;
		}

		var drafted = {};
		var deck = [];

		$.each(response.cards, function(id, card) {
			var count = card.count;
			var card = Draft.card[card.id_card];

			if (!drafted[card.color]) {
				drafted[card.color] = [];
			}

			drafted[card.color].push({id: card.id, name: card.name,
				image: card.full, count: count});

			deck.push({id: card.id, name: card.name,
				image: card.full, count: 0});
		});

		$.each(drafted, function(id, dev_null) {
			drafted[id].sort(function(a, b){
				if (a.count != b.count) {
					return a.count < b.count;
				}

				return a.name.localeCompare(b.name);
			});
		});

		insert_drafted(drafted, 'M', 'Multicolor');
		insert_drafted(drafted, 'W', 'White');
		insert_drafted(drafted, 'G', 'Green');
		insert_drafted(drafted, 'R', 'Red');
		insert_drafted(drafted, 'B', 'Black');
		insert_drafted(drafted, 'U', 'Blue');
		insert_drafted(drafted, 'A', 'Artifact');
		insert_drafted(drafted, 'L', 'Land');

		$.each(drafted, function(id, dev_null) {
			insert_drafted(drafted, id, id);
		});

		if (build) {
			$('.draft_look .drafted h2').show();
			$('.add_card').show();
			Draft.deck_building = true;
			deck.sort(function(a, b){return a.name.localeCompare(b.name);});
			insert_deck(deck);
		}

		$('.draft_look .loader').hide();
		$('.draft_look .drafted').slideDown();
		if (build) {
			$('.draft_look .deck').slideDown();
		}
	});
}

function display_ready() {
	switch_display('ready');
}

function insert_drafted(data, index, name) {
	if (!data[index]) {
		return;
	}

	var header = $('<div/>').addClass('drafted_color_header').html(name);
	var div = $('<div/>').addClass('drafted_color').append(header);

	$.each(data[index], function(id, item){
		var span = $('<span/>').data('item', item).bind('compile', function(){
			var item = $(this).data('item');
			$(this).html(item.count + ' x ' + item.name);
		}).addClass('drafted-' + item.id).addClass('hover_card').trigger('compile');
		var row = $('<div/>').addClass('drafted_row').append(span);
		div.append(row);
	});

	$('.draft_look .drafted').append(div);

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

	$.get('/ajax/get_draft_user', {id: Draft.id}, function(response) {
		if (!response.success || !response.user) {
			return;
		}

		var found = false, users = [];
		$.each(response.user, function(key, user) {
			if (User.id == user.id) {
				found = true;
			}

			Draft.users[user.id] = user;

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
				'class="draft_user draft_user_' + user.id + '">' + user.login + '</span>');
		});
		if (!found) {
			document.location.href = '/';
		}

		$(".participants").html('Участвуют: ' + users.join(', ') + '.');
	});

	$.get('/ajax/get_draft_card', {id: Draft.id}, function(response) {
		if (!response.success || !response.cards) {
			return;
		}

		$.each(response.cards, function(nul, card){
			Draft.card[card.id] = card;
			Draft.card[card.id].small = new Image();
			Draft.card[card.id].full = new Image();
			Draft.card[card.id].small.src = '/images/small' + card.image;
			Draft.card[card.id].full.src = '/images/full' + card.image;
		});

		callback.call(this);
	});
}

function switch_display(type, counter) {
	if (counter) {
		counter_init(counter);
		$('#counter').show();
		$('.info').hide();
	} else {
		$('#counter').hide();
		$('.info').show();
	}

	$('.draft_base:not(.draft_info):not(.draft_'+type+')').hide();
	$('.draft_'+type).show();
}

function counter_init(seconds) {
	if (!$('#counter').data('started')) {
		$('#counter').countDown({
			targetOffset: {
				day: 0, month: 0, year: 0,
				hour: 0, min: 0, sec: seconds
			}
		});
		$('#counter').data('started', true);
	} else {

		$('#counter').stopCountDown();
		$('#counter').setCountDown({
			targetOffset: {
				day: 0, month: 0, year: 0,
				hour: 0, min: 0, sec: seconds
			}
		});
		$('#counter').startCountDown();
	}
}

if (Draft.state == 0) {
	switch_display('waiting_start');
	$('body').everyTime(1500, get_draft_data);
} else {
	get_base_data(function(){
		$('body').everyTime(1500, get_draft_data);
	});
}

$('.draft_pick .cards img').hover(function(){
	var src = $(this).attr('src').replace('/small/', '/full/');
	$('.display_card img').attr('src', src);
	$('.display_card').show();
}, function(){
	$('.display_card').hide();
});

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

$('.drafted_row span').live({
	click: function(){
		if (!Draft.deck_building) {
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
		var target = $('.drafted-' + item.id), item_target = target.data('item');
		item_target.count++;

		parent.data('item', item).trigger('compile');
		target.data('item', item_target).trigger('compile');
		check_create_button();
	}
});

$('.draft_pick .cards img').click(function(){
	if (Draft.picking || !Draft.pick) {
		return;
	}

	if (!$(this).attr('src') || !$(this).data('id')) {
		return;
	}

	Draft.picking = true;
	$('body').css('cursor', 'progress');
	$(this).addClass('picking');
	$('.draft_pick .cards').addClass('picking');

	var me = this;

	$.get('/ajax/draft_pick',
		{id: Draft.id, number: Draft.pick, card: $(this).data('id')},
		function(response) {
			if (!response.success ) {
				$(me).removeClass('picking');
				$('.draft_pick .cards').removeClass('picking');
				Draft.picking = false;
			} else {
				$(me).addClass('picked');
				$('.draft_pick .cards').addClass('picked');
			}
			$('body').css('cursor', 'default');
	});
});

$('.deck_finish').click(function(){
	if ($(this).is('.disabled')) {
		return;
	}

	$('.draft_look .loader').show();
	$('.draft_look .drafted').hide();
	$('.draft_look .deck').hide();

	var cards = [];
	$('.slot .items > div').each(function(){
		var item = $(this).data('item');
		for (var i = 0; i < item.count; i++) {
			cards.push(item.id);
		}
	});

	$.get('/ajax/set_draft_deck', {id: Draft.id, c: cards}, function(response) {
		if (!response.success) {
			$('.draft_look .loader').hide();
			$('.draft_look .drafted').show();
			$('.draft_look .deck').show();
			alert('Не удалось создать колоду.');
		}
	});
});

$('.challenge button').click(function(){
	var opponent = $('.challenge .opponents').val();
	if (!opponent || !Draft.users[opponent]) {
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
				'<input type="hidden" name="Player" value="' + User.name + '">' +
				'<input type="hidden" name="Player_Avatar" value="http://mtgdraft.ru/images/avatar/' + User.avatar + '.jpg">' +
				'<input type="hidden" name="Oponent" value="' + Draft.users[opponent].login + '">' +
				'<input type="hidden" name="Oponent_Avatar" value="http://mtgdraft.ru/images/avatar/' + Draft.users[opponent].avatar + '.jpg">' +
				'<input type="hidden" name="Lang" value="EN">' +
				'<textarea style="display:none;" name="Deck">' + Draft.decklist + '</textarea>' +
			'</form>' +
		'</body>' +
	'</html>');
	win.document.close();
});

function check_slot_height() {
	var height = 100;

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

	if (count >= 40 && type_count <= 25) {
		$('.deck_finish').removeClass('disabled');
	} else {
		$('.deck_finish').addClass('disabled');
	}

	$('.count_cards .count').html(count);
}

function build_deck(cards) {
	Draft.deck = {}, Draft.side = {};
	$.each(cards, function(key, card){
		if (card.deck == 0) {

			if (!Draft.side[card.id_card]) {
				Draft.side[card.id_card] = 0;
			}
			Draft.side[card.id_card]++;

		} else {

			if (!Draft.deck[card.id_card]) {
				Draft.deck[card.id_card] = 0;
			}
			Draft.deck[card.id_card]++;
		}
	});

	var list = [];
	$.each(Draft.deck, function(id, count) {
		list.push(count + ' ' + Draft.card[id].name);
	});

	Draft.decklist = list.join('\n');
}

function refresh_opponents() {
	$('.challenge .opponents').children().remove();

	$.each(Draft.opponents, function(id, opponent) {
		$('.challenge .opponents').append('<option value="' +
			opponent.id + '">' + opponent.login + '</option>');
	});
}
