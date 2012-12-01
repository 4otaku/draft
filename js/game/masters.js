$('.game_ready .deck_side').hide();
$('.game_ready .booster_add').show();

Game.booster_count = {};

Fn.started = false;
Fn.switch_display = function(type, counter){
	this.do_display(type);
	if (type == 'ready' && !this.started) {
		this.started = true;
		play_sound('game_start');
	}
}
Fn.process_response = function(response) {
	this.process_ready(response);
	this.process_actions(response);

	if (!response.booster) {
		return;
	}

	$.each(response.booster, function(user, count){
		if (!Game.booster_count[user] || Game.booster_count[user] != count) {
			Game.booster_count[user] = count;
			if (Game.users[user].login) {
				$('body').trigger('message', 'Бустеров у ' +
					Game.users[user].login + ': ' + count);
			}

			if (user == User.id && count > 1) {
				$('.game_ready .deck_side').show();
			}
		}
	});
}
Fn.process_action_build = function(response) {}

$('.game_ready .booster_add').click(function(){
	if ($(this).is('.disabled')) {
		return
	}

	$(this).addClass('disabled');
	$(this).children('img').show();
	$(this).oneTime(60000, function(){
		$(this).removeClass('disabled');
	});

	var me = $(this);
	$.get('/ajax_game/add_booster', {id: Game.id}, function(response) {
		if (!response.success) {
			alert('Не удалось добавить бустер.');
			me.removeClass('disabled');
			me.children('img').hide();
		} else {
			get_cards_data(function(){
				me.children('img').hide();
				display_look(0, 1);
			});
		}
	});
});
