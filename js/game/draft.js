$('.game_info .pick_time').html(format_time(Game.pick_time));
$('.game_info .pause_time').html(format_time(Game.pause_time));

Fn.switch_display = function(type, counter){
	if (counter) {
		counter_init(counter);
		$('#counter').show();
		$('.left button').hide();
	} else {
		$('#counter').hide();
		$('.left button').show();
	}
	
	this.do_display(type);
}
Fn.process_response = function(response) {
	this.process_ready(response);
	this.process_actions(response);	

	var picked = response.action.picked;
	$('.game_user').css('text-decoration', 'none');

	if (picked) {
		$.each(picked, function(key, value){
			$('.game_user_' + value.id_user).css('text-decoration', 'underline');
		});
	}
	
	if (!response.forced) {
		return;
	}

	$.each(response.forced, function(key, value) {
		var key = value.id_user + '-' + value.pick + '-' + value.order;

		if (Game.forced[key]) {
			return;
		}

		Game.forced[key] = value;

		if (value.id_card) {
			var msg = 'Вы зазевались на ' + (parseInt(value.pick) + 1) + ' пике ';
			msg += value.order + '-го бустера, и схватили случайную карту. ';
			msg += 'Вам достался "'+Game.card[value.id_card].name+'".';
		} else {
			var msg = Game.users[value.id_user].login + ' зазевался на ';
			msg += (parseInt(value.pick) + 1) + ' пике ' + value.order;
			msg += '-го бустера, и схватил случайную карту.';
		}

		$('body').trigger('message', msg);
	});	
}
Fn.process_action_start = function(time, data) {
	display_start(time);
}
Fn.process_action_look = function(time, data) {
	if (Game.got_cards) {
		display_look(time, 0);
	} else {
		get_base_data(function(){display_look(time, 0);});
	}
}
Fn.process_action_pick = function(time, data) {
	display_pick(time, data);
}

function display_start(time) {
	Fn.switch_display('start', Math.ceil((time.getTime() - (new Date()).getTime() + Time.diff) / 1000));
	play_sound('game_start');

	get_base_data();
}

function display_pick(time, number) {
	play_sound(number % 15 == 1 ? 'booster_start' : 'booster_pass');

	Game.pick = number;

	$('.game_pick .loader').show();
	$('.game_pick .cards').hide();
	$('.display_card').hide();
	$('.game_pick .cards').removeClass('picking').removeClass('picked');
	$('.game_pick .cards img').removeClass('picking').removeClass('picked');

	Fn.switch_display('pick', Math.ceil((time.getTime() - (new Date()).getTime() + Time.diff) / 1000));
	Game.picking = false;

	$.get('/ajax_game/get_pick', {id: Game.id, number: number}, function(response){
		if (!response.success || !response.cards) {
			return;
		}

		$('.game_pick .cards img').attr('src', '');
		$('.game_pick .cards img').hide();
		$.each(response.cards, function(id, card) {
			$('.game_pick .cards .card_' + (id + 1) + ' img')
				.attr('src', Game.card[card.id_card].small.src)
				.data('id', card.id).show();
		});

		$('.game_pick .loader').hide();
		$('.game_pick .cards').fadeIn();
	});
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

$('.game_pick .cards img').hover(function(){
	var src = $(this).attr('src').replace('/small/', '/full/');
	$('.display_card img').attr('src', src);
	$('.display_card').show();
}, function(){
	$('.display_card').hide();
});

$('.game_pick .cards img').click(function(){
	if (Game.picking || !Game.pick) {
		return;
	}

	if (!$(this).attr('src') || !$(this).data('id')) {
		return;
	}

	Game.picking = true;
	$('body').css('cursor', 'progress');
	$(this).addClass('picking');
	$('.game_pick .cards').addClass('picking');

	var me = this;

	$.get('/ajax_game/pick',
		{id: Game.id, number: Game.pick, card: $(this).data('id')},
		function(response) {
			if (!response.success) {
				$(me).removeClass('picking');
				$('.game_pick .cards').removeClass('picking');
				Game.picking = false;
			} else {
				$(me).addClass('picked');
				$('.game_pick .cards').addClass('picked');
			}
			$('body').css('cursor', 'default');
	});
});
