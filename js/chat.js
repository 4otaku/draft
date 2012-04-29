$.extend(Chat, {
	users: {},
	messages: {}
});

function add_user(name, id) {
	var md5 = $.md5(name);

	var parts = [md5[0] + md5[1], md5[2] + md5[3], md5[4] + md5[5]];
	$.each(parts, function(key, value) {
		value = Math.floor((parseInt(value, 16) / 2)).toString(16);
		parts[key] = value;
	});

	Chat.users[id] = {
		color: parts.join(''),
		name: name
	};

	redo_user_list();
}

function add_message(text, id) {
	var cl = id ? 'default' : 'system';

	text = id ? '<span class="message_name" style="color: #' +
		Chat.users[id].color+';">' +
		Chat.users[id].name + ':</span> ' + text
		: text;

	$(".chat_messages").append('<div class="message_' + cl + '">' +
		text + '</div>');
}

function redo_user_list() {
	var html = '';

	$.each(Chat.users, function(key, value) {
		html += '<span class="chat_user"><span class="chat_user_color" style="background-color: #'
			+ value.color + ';"> </span><span class="chat_user_name">' + value.name + '</span></span>';
	});

	$(".chat_user_list").html(html);
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

	add_message(val, User.id);
	$('.chat_form textarea').val('');

	$.get('/ajax/add_message', {
		room: Chat.room,
		text: val
	});
}

function get_chat_data() {
	$.get('/ajax/get_messages', {
		room: Chat.room
	}, function(response) {
		if (response.success) {
			var ids = {};
			$.each(response.presense, function(key, item) {
				if (!Chat.users[item.id]) {
					add_user(item.login, item.id);
					add_message(item.login + ' вошел в комнату.');
				}
				ids[item.id] = true;
			});
			$.each(Chat.users, function(key, item) {
				if (!ids[key] && key != User.id) {
					delete Chat.users[key];
					redo_user_list();
					add_message(item.name + ' покинул комнату.');
				}
			});
		}
	});
}

$('.chat_form button').click(function(){
	send_message();
});

$('.chat_form textarea').keydown(function(e){
	if (e.keyCode == 13 && e.ctrlKey) {
		send_message();
	}
});

add_user(User.name, User.id);
$('.chat_form textarea').val('');

$('.chat').everyTime(Chat.freq, function(){
	get_chat_data();
});

get_chat_data();
