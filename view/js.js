$(document).ready(function(){
	$('#file').on('change',function(){
		var file = this.files;
		console.log(file);
		var $this = $(this);
		var data = new FormData;

		data.append('file',file[0]);
		data.append('type','upload');
		$.ajax({
			url: '/',
	        type: 'POST',
	        data: data,
	        cache: false,
	        dataType: 'json',
	        processData: false, 
	        contentType: false, 
	        success: function( response ){
 				console.log(response);
 				if(!response.error)
 				{
 					$this.next().text(file[0].name);
 					$('span.status').removeClass('error');
 					$('span.status').text('ok');
 				}else{
 					$('span.status').addClass('error');
 					$('span.status').text(response.error);
 				}
 				
 			}
		})
	});

	$('#create').on('click',function(){
		var login = $('#login').val();
		var password = $('#password').val();
		var apikey = $('#api_key').val();
		//
		if(login != '', password != '' && apikey != ''){
			$('.preloader').fadeIn();
			$('#login').addClass('error');
			$('#password').addClass('error');
			$('#api_key').addClass('error');
			$.ajax({
				url:'/',
				data:{type:'start',login:login,password:password,api_key:apikey},
				dataType:'json',
				type:'POST',
				success:function(response){

					if(response.success == 1)
					{
						$('.writed').text(response.stat.writed);
						$('.total').text(response.stat.total);
						$('.validated').text(response.stat.validated);
						$('.preloader').fadeOut();
						$('.stat').css({
							opacity:1,
							transform:'scale(1)'
						})
					}
				}
			});
		}else{
			$('#login').addClass('error');
			$('#password').addClass('error');
			$('#api_key').addClass('error');
		}
	});
	$('#new').on('click',function(){
		$('.stat').css({
			opacity:0,
			transform:'scale(0)'
		});
		$('form')[0].reset();
		$('.file_name').text('');
	})
});

