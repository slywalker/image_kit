<?php
class ImageHelper extends AppHelper {
	public $helpers = array('Html');

	function originUrl($path)
	{
		return Configure::read('ThumbUrl').'origin/'.$path;
	}
	
	function origin($path, $options = array())
	{
		$url = $this->originUrl($path);
		return $this->Html->image($url, $options);
	}
	
	function thumbUrl($path, $options = array())
	{
		$params = array(
			'type'=>'reduce',
			'width'=>500,
			'height'=>500,
			'quality'=>100,
			'path'=>null,
		);

		$types = array('trim', 'reduce');
		if (isset($options['type'])) {
			if (in_array($options['type'], $types)) {
				$params['type'] = $options['type'];
			}
		}
		if (isset($options['width'])) {
			$params['width'] = $options['width'];
		}
		if (isset($options['height'])) {
			$params['height'] = $options['height'];
		}
		if (isset($options['quality'])) {
			$params['quality'] = $options['quality'];
		}
		
		if ($params['type'] && $params['width'] && $params['height']) {
			$params['path'] = $path;
			$path = implode('/', $params);
			return Configure::read('ThumbUrl').$path;
		}
		return false;
	}

	function thumb($path, $options = array())
	{
		$url = $this->thumbUrl($path, $options);
		if (!$url) {
			return null;
		}
		
		$unsetParams = array('type', 'width', 'height', 'quality');
		foreach ($unsetParams as $key) {
			if (array_key_exists($key, $options)) {
				unset($options[$key]);
			}
		}
		return $this->Html->image($url, $options);
	}
}
?>