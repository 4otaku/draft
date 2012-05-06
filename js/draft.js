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
	$('.draft_start .loader').show();
	$('.draft_start').addClass('disabled');
	this.starting = true;

	$.get('/ajax/start_draft', {id: Draft.id, user: ids}, function(response) {
		$('.draft_start .loader').hide();
		$('.draft_start').removeClass('disabled');
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
		}
	});
}

function display_start(time) {
	$('#counter').appendTo('.draft_start');
	CounterInit(Math.ceil((time.getTime() - (new Date()).getTime()) / 1000));

	switch_display('start');

	get_base_data();
}

function get_base_data() {
	$.get('/ajax/get_draft_user', {id: Draft.id}, function(response) {
		if (!response.success || !response.user) {
			return;
		}

		var found = false;
		$.each(response.user, function(key, user) {
			if (User.id == user.id) {
				found = true;
			}
		});
		if (!found) {
			document.location.href = '/';
		}
	});

	$.get('/ajax/get_draft_card', {id: Draft.id}, function(response) {
		console.log(response);
	});
}

function switch_display(type) {
	$('.draft_base:not(.draft_info):not(.draft_'+type+')').hide();
	$('.draft_'+type).show();
}

$('body').everyTime(1500, get_draft_data);

if (Draft.state == 0) {
	switch_display('waiting_start');
} else {
	get_base_data();
}
