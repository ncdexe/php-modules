<?php

/**
** Внутренняя функция, которая используя cURL обращается к целевой странице (параметр 1),
** получает HTML код и парсит его в поиске текстового блока (параметр 2). После нахождения
** найденная строка проверяется regexp'ом (параметр 3) и в случае совпадения возвращается 
** часть строки соответствующая регулярному выражению. Во всех остальных случаях - false. 
**/
function getPicId ($uri, $keyword = 'og:image', $regExpRule = '#content="(.*?)"#i') {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $uri);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 2.0.50727)');
	$data = curl_exec($ch);
	curl_close($ch);

	$data = explode("\n", $data);
	$countlines = count($data);
	
	for ($curline = 0, $rightline = 0; $curline <= $countlines; $curline++) {
		$pos = strpos($data[$curline], $keyword);
		if ($pos !== false) {
			$rightline = $curline;
			break;
		}
	}

	if ($rightline != 0) {
		preg_match($regExpRule, $data[$rightline], $output);
		if (isset($output[1]) && $output[1] != '') {$output = $output[1];} else {$output = false;}
	} else {
		$output = false;
	}
	
return $output;
}

/**
** Внутренняя функция, которая запускается в случае если материнская ф-я getVideoPreview, не
** смогла получить превью. В качестве параметра(1) передается URI-страницы с видео. В случае успеха
** возвращается URI картинки-превью, в противном случае возвращается URI - картинки - заглушки.
**/
function getSmartPic ($uri) {
	$dummy = 'http://vesti.kz/static/i/logo-new.png'; //заглушка, которая выдается если не удалось получить превью
	$picid = getPicId ($uri);
	if ($picid) {
		return $picid;
	} else {
		$picid = getPicId ($uri, 'canonical', '#href="(.*?)"#i');
		if ($picid) {
			$picid = getPicId ($picid);
			if ($picid) {return $picid;} else {return $dummy;}
		} else {return $dummy;}
	}
}

/**
** Функция возвращающая превью для видео размещенном на видео-хостинге. В качестве параметра(1)
** передается URI-страницы с видео. В случае успеха возвращается URI картинки-превью, в противном
** случае возвращается URI - картинки - заглушки.
**/
function getVideoPreview($uri) {
	$regexp = array(
/*Идентификатор сервиса*/		/*Паттерн извлекающий видео-ID из URI сервиса*/							/*Страница на которой можно получить превью*/
		'youtu' 	=> array('#(\.be/|/embed/|/v/|/watch\?v=)([A-Za-z0-9_-]{5,11})#', 					'http://img.youtube.com/vi/###/0.jpg'),
		'kiwi' 		=> array('#(/watch/|/v2/)([A-Za-z0-9_-]{5,20})#', 									'http://kiwi.kz/watch/###/'),
		'vimeo' 	=> array('#http://(?:\w+.)?(vimeo).com/(?:video/|moogaloop\.swf\?clip_id=)(\w+)#i', 'http://vimeo.com/###'),
		'rutu' 		=> array('#rutube\.ru(/video/)([A-Za-z0-9_-]{6,})#', 								'http://rutube.ru/video/###/'),
		'rutube' 	=> array('#rutube\.ru(/video/embed/)([A-Za-z0-9_-]{6,})#', 							'http://rutube.ru/video/embed/###'),
		'mir' 		=> array('#(/images/uploaded/)([.A-Za-z0-9_-]{6,})#', 								'http://mir24.tv/media/images/uploaded/###'),
		'mir24' 	=> array('#(/video/)([A-Za-z0-9_-]{6,})#',											'http://mir24.tv/video/###'),
		'tu' 		=> array('#tu\.tv(/videos/)([A-Za-z0-9_-]{6,})#', 									'http://tu.tv/videos/###'),
		'tv' 		=> array('#tu\.tv(/iframe/)([A-Za-z0-9_-]{6,})#', 									'http://tu.tv/iframe/###/'),
		'meta' 		=> array('#(meta\.ua/)(\d*?\.video)#i', 											'http://video.meta.ua/###'),
		'khl' 		=> array('#(khl\.ru/)(.*?)($|\?)#i', 												'http://video.khl.ru/###'),
		'vk.com' 	=> array('#vk\.com/(.*?)(video-.*?)($|%)#i', 										'http://vk.com/###'),
		'vk' 		=> array('#(vk\.com/video_ext\.php)(.*?)$#i', 										'http://vk.com/video_ext.php###')
	);
	
	foreach ($regexp as $key => $val) {
		$pos = mb_strpos($uri, $key);
		if ($pos !== false) {
			if (preg_match($val[0], $uri, $matches)) {break;}
		} 
	}

	if (isset($matches[2]) && $matches[2] != '') {
		switch ($key) {
			case 'youtu':
			case 'mir':
				$output = preg_replace("/###/", $matches[2], $regexp[$key][1]);
				break;
				
			case 'kiwi':
			case 'vimeo':
			case 'rutu':
			case 'mir24':
			case 'tu':
			case 'meta':
			case 'khl':
			case 'vk.com':
				$picid = getPicId (preg_replace("/###/", $matches[2], $regexp[$key][1]));
				if ($picid) {$output = $picid;} else {$output = false;}
				break;
				
			case 'rutube':
			case 'tv':
				$picid = getPicId (preg_replace("/###/", $matches[2], $regexp[$key][1]), 'canonical', '#href="(.*?)"#i');
				if ($picid) {
					$picid = getPicId ($picid, 'og:image', '#content="(.*?)"#i');
					if ($picid) {$output = $picid;} else {$output = false;}
				} else {$output = false;}
				break;
				
			case 'vk':
				$picid = getPicId (preg_replace("/###/", $matches[2], $regexp[$key][1]), 'player_thumb', '#src="(.*?)"#i');
				if ($picid) {$output = $picid;} else {$output = false;}
				break;
				
			default: break;
		}
	} else {
		$output = false;
	}
	if ($output === false) {$output = getSmartPic($uri);}
	
return $output;
}





echo getVideoPreview('http://player.vimeo.com/video/53799367'); //пример запроса
