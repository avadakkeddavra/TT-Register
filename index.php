<?php 
	require_once __DIR__.'/functions.php';


	if(!empty($_POST))
	{
		if($_POST['type'] == 'upload'){

			uploadCSV();

		}elseif($_POST['type'] == 'start'){

			$auth = [
				'login' => $_POST['login'],
				'password' => $_POST['password'],
				'api_key' => $_POST['api_key']
			];
			index($auth);
		}
		
	}else{
		getView();
	}
	
 ?>