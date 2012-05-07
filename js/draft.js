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
		if (!response.success || !response.action) {
			return;
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

	$('.draft_pick .loader').show();
	$('.draft_pick .cards').hide();
	switch_display('pick');

	$.get('/ajax/get_draft_pick', {id: Draft.id, number: number}, function(response){
		if (!response.success || !response.cards) {
			return;
		}

		$.each(response.cards, function(id, card){
			$('.draft_pick .cards .card_' + (id + 1) + ' img').attr('src', Draft.card[card].small.src);
		});

		$('.draft_pick .loader').hide();
		$('.draft_pick .cards').show();
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

			var md5 = $.md5(user.login);

			var parts = [md5[0] + md5[1], md5[2] + md5[3], md5[4] + md5[5]];
			$.each(parts, function(key, value) {
				value = Math.floor((parseInt(value, 16) / 2)).toString(16);
				parts[key] = value;
			});
			users.push('<span style="color: #'+ parts.join('') + ';">' + user.login + '</span>');
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
