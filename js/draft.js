init_sizes();

$('.draft_info .pick_time').html(format_time(Draft.pick_time));
$('.draft_info .pause_time').html(format_time(Draft.pause_time));

$('.draft_start').click(function(){
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

}

$('.body').everyTime(1000, function(){
	get_draft_data();
});
