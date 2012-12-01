Fn.started = false;
Fn.switch_display = function(type, counter){
	this.do_display(type);
	if (type == 'look' && !this.started) {
		this.started = true;
		play_sound('game_start');
	}
}
