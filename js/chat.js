$.extend(Chat, {
	users: {},
	messages: {
		temp: {},
		broken: {}
	}
});

function add_user(name, id, silent) {
	silent = silent || false;

	if (!Chat.users[id]) {
		var md5 = $.md5(name);

		var parts = [md5[0] + md5[1], md5[2] + md5[3], md5[4] + md5[5]];
		$.each(parts, function(key, value) {
			value = Math.floor((parseInt(value, 16) / 2)).toString(16);
			if (value.length == 1) {
				value = 0 + value;
			}
			parts[key] = value;
		});

		Chat.users[id] = {
			color: parts.join(''),
			name: name,
			present: !silent
		};
	} else if (!silent) {
		Chat.users[id].present = true;
	}

	if (!silent) {
		redo_user_list();
	}
}

function add_message(text, id_user, id, time) {
	var chat = $(".chat_messages");

	time = time ? new Date(time * 1000) : new Date();

	text = '<span style="color: #' +
		Chat.users[id_user].color+';">' +
		'<span class="message_time">(' + time.format('HH:MM:ss') + ')</span> ' +
		'<span class="message_name">' + Chat.users[id_user].name + '</span>' +
		':</span> ' + text;

	var message = $('<div class="message_default">' +
		text + '</div>');

	chat.append(message);
	chat.prop('scrollTop', chat.prop('scrollHeight'));

	if (Chat.inactive) {
		Chat.count_new++;
		$('title').html('*' + Chat.count_new + ' ' + Chat.title);
	}

	if (!id) {
		var temp_id = $.md5(((1+Math.random())*0x10000)|0);
		Chat.messages.temp[temp_id] = message;

		return temp_id;
	} else {
		Chat.messages[id] = message;

		return id;
	}
}

function add_system_message(text) {
	var chat = $(".chat_messages");

	chat.append('<div class="message_system">' +
		text + '</div>');
	chat.prop('scrollTop', chat.prop('scrollHeight'));
}

function redo_user_list() {
	var html = '';

	$.each(Chat.users, function(key, value) {
		if (value.present) {
			html += '<span class="chat_user"><span class="chat_user_color" style="color: #'
				+ value.color + ';">&#9632;</span>' +
				'<span class="chat_user_name">' + value.name + '</span>' +
				'</span> ';
		}
	});

	$(".chat_user_list").html(html);
	set_sizes();
}

function send_message() {
	var val = $.trim($('.chat_form textarea').val());

	if (val == '') {
		return;
	}

	val = val.replace(/&/g, '&amp;')
		.replace(/>/g, '&gt;')
		.replace(/</g, '&lt;')
		.replace(/\n\r?/g, '<br />');

	var id = add_message(val, User.id);
	$('.chat_form textarea').val('');

	$.get('/ajax/add_message', {
		room: Chat.room,
		text: val
	}, function(response) {
		if (response.success) {
			Chat.messages[response.id] = Chat.messages.temp[id];
		} else {
			Chat.messages.broken[id] = Chat.messages.temp[id];
			Chat.messages.temp[id].removeClass('message_default')
				.addClass('message_system');
			add_system_message('Не удалось отправить сообщение, пожалуйста попробуйте снова.');
		}
		delete Chat.messages.temp[id];
	});
}

function get_chat_data(params) {
	params = params || {};

	if (Chat.getting) {
		return;
	}
	Chat.getting = true;
	setTimeout(function(){
		Chat.getting = false;
	}, 10000);

	if (params.first_load) {
		Chat.title = $('title').html();
		Chat.count_new = 0;
	}

	$.get('/ajax/get_messages', $.extend(params, {
		room: Chat.room
	}), function(response) {
		Chat.getting = false;
		if (response.success) {
			var ids = {};
			$.each(response.presense, function(key, item) {
				if (!Chat.users[item.id] || !Chat.users[item.id].present) {
					add_user(item.login, item.id);
					add_system_message(item.login + ' вошел в комнату.');
				}
				ids[item.id] = true;
			});
			$.each(Chat.users, function(key, item) {
				if (item.present && !ids[key] && key != User.id) {
					Chat.users[key].present = false;
					redo_user_list();
					add_system_message(item.name + ' покинул комнату.');
				}
			});

			$.each(response.message, function(key, item) {
				if (!Chat.messages[item.id]) {
					if (item.id_user == User.id) {
						for (key in Chat.messages.temp) {
							if (Chat.messages.temp.hasOwnProperty(key)) {
								return;
							}
						}
					}
					if (!Chat.users[item.id_user]) {
						add_user(item.login, item.id_user, true);
					}
					add_message(item.text, item.id_user, item.id, item.time);
				}
			});

			$('body').trigger('draft_change', response.last_draft_change);

			remove_splash('chat');
		}
	});
}

$('.chat_form button').click(function(){
	send_message();
});

$('.chat .message_name, .chat .chat_user_name').live('click', function(){
	$('.chat_form textarea').val($('.chat_form textarea').val() + $(this).html());
});

$('.chat_form textarea').keydown(function(e){
	if (e.keyCode == 13 && e.ctrlKey) {
		send_message();
	}
});

$('body').bind('message', function(e, text){
	add_system_message(text);
});

add_user(User.name, User.id);
$('.chat_form textarea').val('');

$('.chat').everyTime(Chat.freq, function(){
	get_chat_data();
});

get_chat_data({first_load: true});

$(window).focus(function(){
	$('title').html(Chat.title);
	Chat.count_new = 0;
	Chat.inactive = false;
});

$(window).blur(function(){
	Chat.inactive = true;
});
