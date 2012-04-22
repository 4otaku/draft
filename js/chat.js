var Chat = {
	users: {},
	messages: {}
};

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

function redo_user_list() {
	var html = '';

	$.each(Chat.users, function(key, value) {
		html += '<span class="chat_user"><span class="chat_user_color" style="background-color: #'
			+ value.color + ';"> </span><span class="chat_user_name">' + value.name + '</span></span>';
	});

	$(".chat_user_list").html(html);
}

add_user(User.name, User.id);
