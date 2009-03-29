<?php
require_once('image.php');

$originUrls = array(
	dirname(dirname(__FILE__)).'/origin/',
	'http://origin.iqhp.net/',
	'http://adweb3.kir.jp/iqhp/origin/',
);

if (!isset($_GET['url']) || empty($_GET['url'])) {
	error404();
}
$requestUrl = $_GET['url'];

$parseUrl = explode('/', $requestUrl);
$countParams = count($parseUrl);
if ($countParams === 6) {
	$params = array(
		'type'=>$parseUrl[0],
		'width'=>$parseUrl[1],
		'height'=>$parseUrl[2],
		'quality'=>$parseUrl[3],
		'path'=>$parseUrl[4].'/'.$parseUrl[5],
	);
} elseif ($countParams === 3) {
	$params = array(
		'type'=>$parseUrl[0],
		'path'=>$parseUrl[1].'/'.$parseUrl[2],
	);
} else {
	error404();
}

$requireTypes = array('trim', 'reduce', 'origin');
if (!in_array($params['type'], $requireTypes)) {
	error404();
}

$originPath = 'origin/'.$params['path'];
if (!file_exists($originPath)) {
	$isNotExists = true;
	foreach ($originUrls as $originUrl) {
		if ($data = @file_get_contents($originUrl.$params['path'])) {
			mkdir_deep($originPath);
			file_put_contents($originPath, $data);
			$isNotExists = false;
			break;
		}
	}
	if ($isNotExists) {
		error404();
	}
}


if ($params['type'] !== 'origin') {
	$Image = new Image;
	$Image->set($originPath);
	$Image->{$params['type']}(
		intval($params['width']), intval($params['height']));
	mkdir_deep($requestUrl);
	$Image->output($requestUrl, intval($params['quality']));
	$Image->destroy();
}

header('Content-type: image/jpeg');
@readfile($requestUrl);

/*
 * ディレクトリ作成
 */
function mkdir_deep($path)
{
	$dirs = explode('/', dirname($path));
	$path = '';
	foreach ($dirs as $dir) {
		$path .= $dir;
		if (!is_dir($path)) {
			@mkdir($path);
			@chmod($path, 0777);
		}
		$path .= '/';
	}
}

/*
 * 404 Not Found
 */
function error404()
{
	header('HTTP/1.1 404 Not Found');
	exit;
}
?>