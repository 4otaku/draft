$(".disabled").live('click', function(e){
	e.preventDefault();
});

function set_sizes() {
	var containerHeight = $(window).height();
	var usersHeight = $('.chat_users').height() + 18;
	$(".content").height(containerHeight - 40);
	$(".chat").height(containerHeight - 240);
	$(".left_wrapper").height(containerHeight - 140);
	$(".chat_messages").height(containerHeight - 268 - usersHeight);
}

function init_sizes() {
	$(window).resize(set_sizes);
	$(document).ready(set_sizes);
}

function format_time(seconds) {
	if (seconds < 120) {
		return seconds + ' секунд';
	}

	var minutes = Math.round(seconds / 60, 2);
	return minutes + ' минуты';
}

$(document).ready(function(){
	$(".faq").click(function(){
		document.location.href = '/faq/';
	});

	$(".index").click(function(){
		document.location.href = '/';
	});

	$(".todo").click(function(){
		document.location.href = '/todo/';
	});

	$(".exit").click(function(){
		$.cookie("user", null, {path: '/'});
		document.location.reload();
	});
});
