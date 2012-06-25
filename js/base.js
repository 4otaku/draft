var Time = {
	diff: 0
};

$.get('/time.php', function(response) {
	Time.diff = new Date().getTime() - new Date(response.time * 1000).getTime();
});

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

function format_time(seconds) {
	if (seconds < 120) {
		return seconds + ' секунд';
	}

	var minutes = Math.round(seconds / 60, 2);
	return minutes + ' минуты';
}

$('#overlay .overlay_content .add_note').live('click', function(){
	var room = document.location.href.split('/')[4] || 0;
	$.post('/ajax/add_note',
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
	$.get('/ajax/delete_note', {id: $(this).attr('rel')});
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

	$("button.exit").click(function(){
		$.cookie("user", null, {path: '/'});
		document.location.reload();
	});
});
