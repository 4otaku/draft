$(document).ready(function(){

	image_upload = new qq.FileUploaderBasic({
		button: document.getElementById('avatar'),
		action: '/ajax/upload',
		autoSubmit: true,
		multiple: false,
		allowedExtensions: ['jpg', 'jpeg', 'gif', 'png'],
		sizeLimit: 5*1024*1024,
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
		},
		onComplete: function(id, file, response) {
			$(".processing-image").hide();

			if (!response.success) {
				var error = response.error;

				if (error == 5 || error == 20) {
					this.showMessage('Ошибка! Выбранный вами файл не является картинкой.');
				} else if (error == 10) {
					this.showMessage('Ошибка! Выбранный вами файл превышает 5 мегабайт.');
				} else {
					this.showMessage('Неизвестная ошибка.');
				}
			} else {

				$('.ready-image').css('background-image', 'url(/images/avatar/'+response.thumb+'.jpg)')
					.html('<input type="hidden" name="image" value="'+response.thumb+'" /></div>');
			}
		}
	});
});
