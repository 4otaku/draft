init_sizes();

$('.draft_info .pick_time').html(format_time(Draft.pick_time));
$('.draft_info .pause_time').html(format_time(Draft.pause_time));

$('.draft_start_button').click(function(){
	if (this.starting) {
		return;
	}

	var ids = '', draft_users = {}, me = this;
	$.each(Chat.users, function(key, value){
		ids += key + ',';
		draft_users[key] = value;
	});
	$('.draft_start_button .loader').show();
	$('.draft_start_button').addClass('disabled');
	this.starting = true;

	$.get('/ajax/start_draft', {id: Draft.id, user: ids}, function(response) {
		$('.draft_start_button .loader').hide();
		$('.draft_start_button').removeClass('disabled');
		me.starting = false;

		if (response.success) {
			Draft.users = draft_users;
		}
	});
});

function get_draft_data() {
	$.get('/ajax/get_draft_data', {id: Draft.id}, function(response) {
		if (!response.success || !response.action || !response.forced) {
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
			display_look(time);
		} else if (type == 'build') {
			display_build();
		} else {
			display_pick(time, type.replace('pick_', '') - 0);
		}
	});
}

function display_start(time) {
	$('#counter').appendTo('.draft_start');
	CounterInit(Math.ceil((time.getTime() - (new Date()).getTime()) / 1000));

	switch_display('start');

	get_base_data();
}

function display_pick(time, number) {
	$('#counter').prependTo('.draft_pick');
	CounterInit(Math.ceil((time.getTime() - (new Date()).getTime()) / 1000));

	Draft.pick = number;

	$('.draft_pick .loader').show();
	$('.draft_pick .cards').hide();
	$('.display_card').hide();
	$('.draft_pick .cards').removeClass('picking').removeClass('picked');
	$('.draft_pick .cards img').removeClass('picking').removeClass('picked');
	switch_display('pick');
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

function display_look(time) {
	$('#counter').prependTo('.draft_look');
	CounterInit(Math.ceil((time.getTime() - (new Date()).getTime()) / 1000));

	$('.draft_look .loader').show();
	$('.draft_look .deck').hide();

	$.get('/ajax/get_draft_deck', {id: Draft.id}, function(response){
		if (!response.success || !response.cards) {
			return;
		}

		Draft.deck = {};

		$.each(response.cards, function(id, card) {
			var count = card.count;
			var card = Draft.card[card.id_card];

			if (!Draft.deck[card.color]) {
				Draft.deck[card.color] = [];
			}

			Draft.deck[card.color].push({id: card.id, name: card.name,
				image: card.full, count: count});
		});

		$.each(Draft.deck, function(id, color) {
			Draft.deck[id].sort(function(a, b){
				if (a.count != b.count) {
					return a.count < b.count;
				}

				return a.name.localeCompare(b.name);
			});
		});

		console.log(Draft.deck);

		$('.draft_look .loader').hide();
		$('.draft_look .deck').fadeIn();
	});

	switch_display('look');
}

function display_build(time) {
	switch_display('build');
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

			var md5 = $.md5(user.login);

			var parts = [md5[0] + md5[1], md5[2] + md5[3], md5[4] + md5[5]];
			$.each(parts, function(key, value) {
				value = Math.floor((parseInt(value, 16) / 2)).toString(16);
				parts[key] = value;
			});
			users.push('<span style="color: #'+ parts.join('') + ';" ' +
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

function switch_display(type) {
	$('.draft_base:not(.draft_info):not(.draft_'+type+')').hide();
	$('.draft_'+type).show();
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
