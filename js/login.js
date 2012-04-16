$(document).ready(function(){

	image_upload = new qq.FileUploaderBasic({
		button: document.getElementById('avatar'),
		action: '/ajax/upload',
		autoSubmit: true,
		multiple: false,
		allowedExtensions: ['jpg', 'jpeg', 'gif', 'png'],
		sizeLimit: 5*1024*1024,
		// messages
		messages: {
			typeError: "{file} не является картинкой. Разрешены только {extensions}.",
			sizeError: "{file} слишком большой, максимальный размер файла {sizeLimit}.",
			emptyError: "{file} пуст, выберите другой файл.",
			onLeave: "Файлы загружаются на сервер, если вы закроете страницу, то загрузка прервется."
		},
		showMessage: function(message){
			alert(message);
		},
		onSubmit: function(id, file) {
			$(".processing-image").show();
			$('#error').html('');
		},
		onComplete: function(id, file, response) {
/*
			if (!response.success) {
				var error = response.data.error;

				if (error == 'filetype') {
					$('#error').html('<b>Ошибка! Выбранный вами файл не является картинкой.</b>');
				} else if (error == 'maxsize') {
					$('#error').html('<b>Ошибка! Выбранный вами файл превышает 2 мегабайт.</b>');
				} else {
					$('#error').html('<b>Неизвестная ошибка.</b>');
				}
			} else {
				response = response.data;

				$('#transparent td').html('<div style="background-image: url('+response['image']+');" class="left right20"><img class="cancel" src="'+window.config.image_dir+'/cancel.png"><input type="hidden" name="image" value="'+response['data']+'"></div>');
				$("#transparent td img.cancel").click(function(){
					$(this).parent().remove();
				});
			} */
		}
	});

	$("#transparent td img.cancel").click(function(){
		$(this).parent().remove();
	});
});
