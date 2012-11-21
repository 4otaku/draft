var Errors = {
	login_empty: 'Имя пользователя не может быть пустым.',
	login_incorrect: 'Имя пользователя не должно содержать символов кроме букв русского, ' +
		'английского языка, цифр, знаков подчеркивания и пробелов.',
	login_short: 'Имя пользователя должно быть не короче 4 символов.',
	login_long: 'Имя пользователя должно быть не длиннее 20 символов.',
	login_not_exist: 'Пользователя с таким именем не существует.',
	password_empty: 'Пароль не может быть пустым.',
	password_short: 'Пароль должен быть не короче 6 символов.',
	password_no_match: 'Введенные пароли не совпадают.',
	password_incorrect: 'Пароль введен неверно.',
	avatar_empty: 'Для регистрации обязательно добавить аватарку.',
	login_used: 'Имя пользователя уже занято.',
	410: 'Неверный адрес запроса'
}

function display_register_error(message) {
	display_error('register', message);
}
function display_login_error(message) {
	display_error('login', message);
}
function display_error(id, message) {
	var tab = $('#' + id);
	tab.find('.alert').remove();
	tab.prepend('<div class="alert alert-error">' +
		'<a class="close" data-dismiss="alert">×</a>' +
		message + '</div>');
}

function do_register_validation() {
	var valid = true, errors = [];

	var login = $('#register input[name=login]').val(),
		password = $('#register input[name=password]').val(),
		password2 = $('#register input[name=password2]').val();

	if (login.length == 0) {
		valid = false;
		errors.push(Errors.login_empty);
	} else if (!login.match(/^[a-zа-яё_\s\d]+$/i)) {
		valid = false;
		errors.push(Errors.login_incorrect);
	} else if (login.length < 4) {
		valid = false;
		errors.push(Errors.login_short);
	} else if (login.length > 20) {
		valid = false;
		errors.push(Errors.login_long);
	}

	if (password.length == 0) {
		valid = false;
		errors.push(Errors.password_empty);
	} else if (password.length < 6) {
		valid = false;
		errors.push(Errors.password_short);
	}

	if (password != password2) {
		valid = false;
		errors.push(Errors.password_no_match);
	}

	if ($('.ready-image input').length == 0) {
		valid = false;
		errors.push(Errors.avatar_empty);
	}

	if (valid) {
		$("#register .submit").removeClass('disabled');
	} else {
		$("#register .submit").addClass('disabled');
	}

	return errors;
}

function do_login_validation() {
	var valid = true, errors = [];

	var login = $('#login input[name=login]').val(),
		password = $('#login input[name=password]').val();

	if (login.length == 0) {
		valid = false;
		errors.push(Errors.login_empty);
	} else if (!login.match(/^[a-zа-яё_\s\d]+$/i)) {
		valid = false;
		errors.push(Errors.login_incorrect);
	} else if (login.length < 4) {
		valid = false;
		errors.push(Errors.login_short);
	} else if (login.length > 20) {
		valid = false;
		errors.push(Errors.login_long);
	}

	if (password.length == 0) {
		valid = false;
		errors.push(Errors.password_empty);
	} else if (password.length < 6) {
		valid = false;
		errors.push(Errors.password_short);
	}

	if (valid) {
		$("#login .submit").removeClass('disabled');
	} else {
		$("#login .submit").addClass('disabled');
	}

	return errors;
}

$(document).ready(function(){

	$('#register input').keyup(function(){
		do_register_validation();
	});

	$('#login input').keyup(function(){
		do_login_validation();
	});

	$("#register .submit").click(function(e){
		e.preventDefault();
		errors = do_register_validation();

		if (errors.length) {
			display_register_error(errors.join('<br />'));
		} else {
			$.get('/ajax_user/register', {
				avatar: $('.ready-image input').val(),
				login: $('#register input[name=login]').val(),
				password: $('#register input[name=password]').val()
			}, function(response) {
				if (response.success) {
					document.location.reload();
				} else {
					display_register_error(Errors[response.error]);
				}
			});
		}
	});

	$("#login .submit").click(function(e){
		e.preventDefault();
		errors = do_login_validation();

		if (errors.length) {
			display_login_error(errors.join('<br />'));
		} else {
			$.get('/ajax_user/login', {
				login: $('#login input[name=login]').val(),
				password: $('#login input[name=password]').val()
			}, function(response) {
				if (response.success) {
					document.location.reload();
				} else {
					display_login_error(Errors[response.error]);
				}
			});
		}
	});

	image_upload = new qq.FileUploaderBasic({
		button: document.getElementById('avatar'),
		action: '/ajax_user/upload',
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
		showMessage: function(message) {
			display_register_error(message);
		},
		onSubmit: function(id, file) {
			$(".processing-image").css('visibility', 'visible');
		},
		onComplete: function(id, file, response) {
			$(".processing-image").css('visibility', 'hidden');

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

				do_register_validation();
			}
		}
	});
});
