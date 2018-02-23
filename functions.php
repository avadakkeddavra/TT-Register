<?php 

	class Stat
	{
		public static $validated = 0;
		public static $total = 0;
		public static $writed = 0;
		public static $auth;
		public static $region = [];
		public static $tempData = [];

		public static function setValidated()
		{
			self::$validated++;
		}

		public static function setAuth($authenticate)
		{
			self::$auth = $authenticate;
		}

		public static function getAuthByKey($key)
		{
			return self::$auth[$key];
		}

		public static function setTotal($value)
		{
			self::$total = $value;
		}

		public static function getStat()
		{
			return ['total' => self::$total,'validated' => self::$validated,'writed' => self::$writed];
		}

		public static function setWrited()
		{	
			self::$writed++;
		}

		public static function error($status,$message)
		{
			return json_encode(['status' => $status,'message' => $message]);
		}

		public static function regions()
		{
			$row = 1;
			if(is_file(__DIR__."/files/region.csv"))
			{
				$handle = fopen(__DIR__."/files/region.csv", "r");
			}else{
				echo 'no such file';
			}


			while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
				$num = count($data);

				$row++;
				if($row > 2)
				{
					$params = [];
					for ($c=0; $c < $num; $c++) {
						$params[] = $data[$c];
					}
					self::$region[$params[0]] = $params;
				}

			}

		}

		public static function findInRegion($key)
		{
			if( isset(self::$region[$key]) )
			{
				$replacements = ['\'', '"','{','}','[',']',' ','city','district','region','='];
				$row =  explode(',',str_replace($replacements,'',self::$region[$key][1]));

				$data['city'] = trim($row[0]);
				$data['district'] = trim($row[1]);
				$data['region'] = trim($row[2]);

				return json_encode($data);
			}else{
				return 'Nothing';
			}

		}

		public static function setTempData($tempData)
		{
			self::$tempData = $tempData;
		}

		public static function getTempData()
		{
			return self::$tempData;
		}

	}

	function dd($data)
	{
		echo '<pre><code>';
		print_r($data);
		echo '</code></pre>';
	}

	function index($auth = null)
	{
		$data = parseFile();
		$authenticate = $auth;
		Stat::setAuth($auth);
		Stat::regions();

		foreach($data as $key => $item)
		{
			if($key > 80 && $key <= 180)
			{
				googleApiRequest($item,$auth);

			}

			if($key == 180){
				break;
			}

				
		}
		echo json_encode(['success'=>true,'stat' => Stat::getStat()]);
	}


	function uploadCSV()
	{
			$error = false;
		    $files = array();
		 
		    $uploaddir = __DIR__.'/files/'; 

		    if( ! is_dir( $uploaddir ) ) mkdir( $uploaddir, 0777 );
		 

		    foreach( $_FILES as $file ){
		        if( move_uploaded_file( $file['tmp_name'], $uploaddir . basename('reestr.csv') ) ){
		            $files[] = realpath( $uploaddir . 'reestr.csv' );
		        }
		        else{
		            $error = true;
		        }
		    }
		 
		    $data = $error ? array('error' => 'Ошибка загрузки файлов.') : array('files' => $files );
		 
		    echo json_encode( $data );
	}

	function parseFile()
	{
		$row = 1;
		if(is_file(__DIR__."/files/reestr.csv"))
		{
			$handle = fopen(__DIR__."/files/reestr.csv", "r");	
		}else{
			echo 'no such file';
		}
		
		
		while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
		    $num = count($data);
		    
		    $row++;
		    if($row > 4)
		    {
		    	$params = [];
		    	for ($c=0; $c < $num; $c++) {
		        	$params[] = $data[$c];
		    	}
		    	
		    	$arr[$row-5]['cityDesc']['city'] = $params[6];

		    	$arr[$row-5]['cityDesc']['region'] = $params[5];
				$arr[$row-5]['name'] = trim($params[0]); 
		    	$arr[$row-5]['externalId'] = trim($params[8]);
		    	$arr[$row-5]['address'] = 'вул. '.str_replace('вул. ', '', $params[7]);

		    	$arr[$row-5]['type'] = 0; 
		    			    	
		    }
		    
		}
		
		fclose($handle);
		Stat::setTotal($row);

		return $arr;
	}


	function requestScrup($data)
	{

		if( $curl = curl_init() ) {

		    curl_setopt($curl, CURLOPT_URL, 'https://sandbox.elpaysys.com/ts/terminal/source/add');
		    curl_setopt($curl, CURLOPT_RETURNTRANSFER,true);
		    curl_setopt($curl, CURLOPT_POST, true);
		    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data,JSON_UNESCAPED_UNICODE));
		    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
					            'Content-Type: application/json',
					            'X-Requested-With: XMLHttpRequest',
					            'Content-Length: ' . strlen(json_encode($data,JSON_UNESCAPED_UNICODE)),
            					'Accept: application/json, text/javascript, */*; q=0.01'
					 )
			);
		    $out = curl_exec($curl);

			   $data['sources'][0]['json_add'] = $out; 
			$out = json_decode($out);
			


			$responseScrup = scrupGet($data['sources'][0]);
			
			if(json_decode($responseScrup)->respStauts == 0)
			{
				$data['sources'][0]['id'] = json_decode($responseScrup)->source->id;
				
			}else{
				$data['sources'][0]['id'] = '';
			}
			$data['sources'][0]['json'] = $responseScrup;
			writeReport($data['sources'][0],$out->respStatus);
			
		    curl_close($curl);
		 }
		
 	}
	function googleApiRequest($data,array $auth = null)
	{	
			
			$requestParams = array(
					'authorization' => array(
							'login' => $auth['login'],
							'authCode' => md5($auth['password'])
						),
					'sources' => array(),
				);

			//AIzaSyCfzixXn3aD85-br3_ec18CFUxRvl9oAjo
			$address = $data['cityDesc']['region'].$data['cityDesc']['city'].$data['address'];

			$address = str_replace(' ','', $address);
			$url = 'https://maps.googleapis.com/maps/api/geocode/json?address='.$address.'&key='.$auth['api_key'].'&language=uk';

			  if( $curl = curl_init() ) {
			    curl_setopt($curl, CURLOPT_URL, $url);
			    curl_setopt($curl, CURLOPT_RETURNTRANSFER,true);
			    $out = curl_exec($curl);
			    $apiResponse = json_decode($out); 

			   	if($apiResponse->status == 'OK')
				{

					$fullAddress = explode(',',$apiResponse->results[0]->formatted_address);
					//$data['fullAddress'] = $fullAddress;

					$data['address'] = findStreet($fullAddress);
					
					$data['cityDesc'] = findAddress($fullAddress);
	
					
					$data['latitude'] = (float) $apiResponse->results[0]->geometry->location->lat;
					$data['longitude'] = (float) $apiResponse->results[0]->geometry->location->lng;



					$specifyCityDesc =  specifyScrupCity($data['cityDesc']);
					if($specifyCityDesc != false)
					{
						$data['cityDesc'] = $specifyCityDesc;
					}

				}else{

					$data['latitude'] = '';
					$data['longitude'] = '';

					$data = specifyAddress($data);
				}
					$data['terminalSerial'] = (int) 11600011;
					$data['note'] = '';
			    curl_close($curl);
			  }else{
			  		echo Stat::error('Failed','Нет подключения к cURL');
			  }
			  $requestParams['sources'][0] = $data;


		return requestScrup($requestParams);
		
	}

	function specifyAddress($data)
	{

		$key = explode(':',$data['externalId'])[0];

		$region = json_decode(Stat::findInRegion($key));

		if($region != '')
		{
			$data['cityDesc']['city'] = $region->city;
			$data['cityDesc']['district'] = $region->district;
			$data['cityDesc']['region'] = $region->region;



		}else{
			$address = str_replace(' ','',$data['cityDesc']['region'].'+'.$data['cityDesc']['city']);
			$googleResp = googleCheck($address);

			if($googleResp != false)
			{
				$data['cityDesc'] = $googleResp['address'];
				$data['latitude'] = $googleResp['geometry']['latitude'];
				$data['longitude'] = $googleResp['geometry']['longitude'];
			}
		}

		$specifyCityDesc =  specifyScrupCity($data['cityDesc']);
		if($specifyCityDesc != false)
		{
			$data['cityDesc'] = $specifyCityDesc;
		}
		return $data;

	}

	function googleCheck($address)
	{
		$url = 'https://maps.googleapis.com/maps/api/geocode/json?address='.$address.'&key='.Stat::getAuthByKey('api_key').'&language=uk';

		if( $curl = curl_init() ) {
			curl_setopt($curl, CURLOPT_URL, $url);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER,true);
			$out = curl_exec($curl);

			$apiResponse = json_decode($out);

			curl_close($curl);

			if($apiResponse->status == 'OK')
			{
				$responseAddress = $apiResponse->results[0]->address_components;
				$responseGeometry = $apiResponse->results[0]->geometry->location;

				$fullAddress = [];

				$fullAddress['city'] = $responseAddress[0]->short_name;
				$fullAddress['district'] = str_replace('район','',$responseAddress[1]->short_name);
				$fullAddress['region'] = str_replace('область','',$responseAddress[2]->short_name);

				$geometry['latitude'] = $responseGeometry->lat;
				$geometry['longitude'] = $responseGeometry->lng;



				return ['address' => $fullAddress,'geometry' => $geometry];
			}else{
				return false;
			}

		}
	}

	function specifyScrupCity($data)
	{
		$city = $data['city'];
		$request =  array(
			'authorization' => array(
				'login' => Stat::getAuthByKey('login'),
				'authCode' => md5(Stat::getAuthByKey('password'))
			),
			'cityTitlePart' => "$city"
		);

		if( $curl = curl_init() ) {

			curl_setopt($curl, CURLOPT_URL, 'https://sandbox.elpaysys.com/ts/dict/city/findByTitlePart');
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($request, JSON_UNESCAPED_UNICODE));
			curl_setopt($curl, CURLOPT_HTTPHEADER, array(
					'Content-Type: application/json',
					'X-Requested-With: XMLHttpRequest',
					'Content-Length: ' . strlen(json_encode($request, JSON_UNESCAPED_UNICODE)),
					'Accept: application/json, text/javascript, */*; q=0.01'
				)
			);

			$out = curl_exec($curl);
			$response = json_decode($out);

			curl_close($curl);


		}
		//dd($response);

		if($response->respStatus == 0)
		{

				$correctAddress = findSpecifyCityDesc($data,$response->citiesDesc);
				if($correctAddress != false)
				{
					return ['city' => $correctAddress->city,'district' => $correctAddress->district, 'region' => $correctAddress->region];
				}else{
					return false;
				}

		}else{

			if(strlen($city) > 2)
			{
				$data['city'] = mb_convert_encoding(substr($city,0,strlen($city)/2),'UTF-8');
				return specifyScrupCity($data);
			}else{
				return false;
			}
		}

	}

	function findSpecifyCityDesc($data,$response)
	{
		foreach($response as $city)
		{
			if($city->region == trim($data['region']) && $city->district == trim($data['district']))
			{
				 $needle = $city;
				 break;
			}
		}
		if($needle != null)
		{
			return $needle;
		}else{
			return false;
		}
	}


	function findAddress($fullAddress){

		foreach($fullAddress as $key =>  $region)
		{
			if(strpos($region,'область') !== false){
				$region = str_replace('область', '', $region);
				$data = ['city' => trim($fullAddress[$key-1]),'region' => trim($region)];
				break;
			}else{
				$data = ['city' => trim($fullAddress[1]),'region' => trim($fullAddress[2])];
				
			}
			
		}

		return $data;
	}

	function findStreet($fullAddress)
	{	

		foreach($fullAddress as $key => $street):
			
			if(		strpos($street,'вулиця') !== false || 
					strpos($street,'проспект') !== false || 
					strpos($street,'бульвар') !== false || 
					strpos($street,'провулок') !== false ||
					strpos($street,'площа') !== false ||
					strpos($street,'вул') !== false ||
					strpos($street,'пл') !== false ||
					strpos($street,'бульв') !== false ||
					strpos($street,'просп') !== false ||
					strpos($street,'пров') !== false 
			){
					$street = trim($street);

					if(preg_match("/^(.*) (\d+)$/", $street) === 1)
					{
						$address = $street;
						
					}elseif(preg_match("/(\d+)?\w$/", trim($fullAddress[$key+1])) === 1){
							$address = $street . $fullAddress[$key+1];

					}
					else{

						$address = $street;
					}
					break;
			 }
			
		endforeach;

		$address = str_replace('вулиця', 'вул.', $address);
		$address = str_replace('проспект', 'просп.', $address);
		$address = str_replace('бульвар', 'бульв.', $address);
		$address = str_replace('провулок', 'пров.', $address);
		$address = str_replace('площа', 'пл.', $address);
		
		return $address;
	}
	function writeReport($data,$status)
	{		

		$handle = fopen(__DIR__."/files/report.csv", "r");	
			$isEmpty = fgetcsv($handle, 1000, ",");
			$isEmpty = $isEmpty[0];
		fclose($handle);

		$fp = fopen(__DIR__."/files/report.csv", 'a');
			if($isEmpty == '')
			{
				$head = ['name','externalId','address','type','latitude','longitude','terminalSerial','note','json_add','id','json_get','region','city','validation','status','desc'];
				fputcsv($fp, $head);
			}

			$data['region'] = $data['cityDesc']['region'];
			$data['city'] = $data['cityDesc']['city'];
			unset($data['cityDesc']);

			if($data['latitude'] == '' && $data['longitude'] == ''){
				
				$data['validate'] = 'не пройдена';
			}else{

				$data['validate'] = 'Пройдена';
				Stat::setValidated();
			}

			$data['status'] = $status;
			
			switch ($status) {
				case 401:
					$data['desc'] = 'Помилка сервера';
					break;
				case 402:
					$data['desc'] = 'Формат запиту не вірний';
					break;
				case 403:
					$data['desc'] = 'Авторизація не пройдена';
					break;
				case 404:
					$data['desc'] = 'Не відомий термінал';
					break;
				case 406:
					$data['desc'] = 'Не вірне місто';
					break;
				case 407:
					$data['desc'] = 'Дублікат source_id';
					break;
				default:
					$data['desc'] = '';
					break;
			}

		    fputcsv($fp, $data);

		fclose($fp);
		Stat::setWrited();
	}


	function getView()
	{
		include __DIR__.'/view/home.html';
	}

	function scrupGet($data)
	{
		$data = '{
				  "authorization": {
				    "login": "'.Stat::getAuthByKey('login').'",
				    "authCode": "'.md5(Stat::getAuthByKey('password')).'"
				  },
				  "externalId": "'.$data['externalId'].'",
				  "terminalSerial": '.$data['terminalSerial'].'				  
				}';

			if( $curl = curl_init() ) {
			    curl_setopt($curl, CURLOPT_URL, 'https://sandbox.elpaysys.com/ts/terminal/source/getById');
			    curl_setopt($curl, CURLOPT_RETURNTRANSFER,true);
			    curl_setopt($curl, CURLOPT_POST, true);
			    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
			    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
						            'Content-Type: application/json',
						            'X-Requested-With: XMLHttpRequest',
	            					'Accept: application/json, text/javascript, */*; q=0.01'
						 )
				);
			    $out = curl_exec($curl);
			   return $out;
				    

			   curl_close($curl);
		}		  
}

?>