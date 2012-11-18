/**
 * @author Alexander Manzyuk <admsev@gmail.com>
 * Copyright (c) 2012 Alexander Manzyuk - released under MIT License
 * https://github.com/admsev/jquery-play-sound
 * Usage: $.playSound('http://example.org/sound.mp3');
 */

(function($){
	$('body').append('<span id="playSound"></span>');

	$.extend({
		playSound: function(){
			var volume = (typeof User != 'undefined') ? User.settings.volume : 50;
			$('#playSound').html("<embed src='"+arguments[0]+"' volume='" + volume +
				"' hidden='true' autostart='true' loop='false'>");
		}
	});

})(jQuery);