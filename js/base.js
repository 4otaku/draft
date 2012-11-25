$(".disabled").live('click', function(e){
	e.preventDefault();
});

function set_sizes() {
	var containerHeight = $(window).height();
	var usersHeight = $('.chat_users').height() + 18;
	$(".content").height(containerHeight - 40);
	$(".chat_loader").height(containerHeight - 240);
	$(".chat").height(containerHeight - 240);
	$(".left_wrapper").height(containerHeight - 140);
	$(".chat_messages").height(containerHeight - 268 - usersHeight);
}

function init_sizes() {
	$(window).resize(set_sizes);
	$(document).ready(set_sizes);
	$('.chat_messages').bind('message_add', set_sizes);
}

function play_sound(sound) {
	var setting = 'play_on_' + sound;
	if (!User.settings.play_music || !User.settings[setting]) {
		return false;
	}

	var soundsMap = {
		message: 'message',
		user_enter: 'enter',
		user_leave: 'leave',
		highlight: 'highlight',
		game_message: 'message',
		user_game_enter: 'enter',
		user_game_leave: 'leave',
		game_start: 'start',
		booster_start: 'booster',
		booster_pass: 'booster'
	};

	if (soundsMap[sound]) {
		$.playSound('/sound/' + soundsMap[sound] + '.wav');
		return true;
	}

	return false;
}

function sync_on_off_music_button() {
	if (typeof User == 'undefined') return;

	if (User.settings.play_music) {
		$('.music_on').hide();
		$('.music_off').show();
	} else {
		$('.music_on').show();
		$('.music_off').hide();
	}
}

function write_setting(setting) {
	var value = User.settings[setting];
	$.post('/ajax_setting/set', {setting: setting, value: value});
}

function format_time(seconds) {
	if (seconds < 120) {
		return seconds + ' секунд';
	}

	var minutes = Math.round(seconds / 60, 2);
	return minutes + ' минуты';
}

$('#overlay .overlay_content .add_note').live('click', function(){
	var room = document.location.href.split('/')[4] || 0;
	$.post('/ajax_note/add',
		{room: room, text: 	$('#overlay .overlay_content textarea').val()},
		function(response){
			if (response.success) {
				$('#overlay .close').click();
				$('#overlay .overlay_content').html();
			}
	});
});
$('.note_more').live('click', function(){
	var parent = $(this).parents('.note');
	parent.find('.note_header').hide();
	parent.find('.note_text').show();
});
$('.note_remove').live('click', function(){
	$.get('/ajax_note/delete', {id: $(this).attr('rel')});
	$(this).parents('.note').remove();
});

$(document).ready(function(){
	$("button.faq").click(function(){
		document.location.href = '/faq/';
	});

	$("button.index").click(function(){
		document.location.href = '/';
	});

	$("button.todo").click(function(){
		document.location.href = '/todo/';
	});

	$("button.info").overlay({
		mask: '#688A08',
		onBeforeLoad: function() {
			var room = document.location.href.split('/')[4] || 0;
			$('#overlay .overlay_content').load('/info/' + room);
		}
    });
	$("button.settings").overlay({
		mask: '#688A08'
    });

	$("button.exit").click(function(){
		$.cookie("user", null, {path: '/'});
		document.location.reload();
	});

	$('.music_on, .music_off').click(function(){
		User.settings.play_music = 1 - User.settings.play_music;
		write_setting('play_music');
		sync_on_off_music_button();
	});

	sync_on_off_music_button();

	$('.setting input').change(function() {
		var value =  $(this).is(':checked') - 0;
		var name = $(this).attr('name');
		User.settings[name] = value;
		write_setting(name);
	});

	$(".checked").attr('checked', true);
	$(".not_checked").attr('checked', false);

	if ($(".volume").length) {
		$(".volume").rangeinput({
			change: function(e, i) {
				User.settings.volume = i;
				write_setting('volume');
			}
		});
	}

	// Костыль для совместимости rangeinput и draggable
	document.ondragstart = function () {
		return !!$('.ui-draggable').length;
	};
});
