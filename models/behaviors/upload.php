<?php
require_once(dirname(dirname(dirname(__FILE__))).'/config/path.php');
uses('folder', 'file');
App::import('Component', 'ImageKit.Image');

class UploadBehavior extends ModelBehavior {

	var $fileRoot = null;
	var $defaultOptions = array(
		'dir' => null,
		'allowedMime' => array(
			'image/jpg', 'image/jpeg', 'image/pjpeg',
			'image/gif', 'image/png',
		),
		'allowedExt' => array('jpg', 'jpeg', 'png', 'gif'),
		'max_size' => 2097152,
		'ext' => null,
	);
	
	var $validate = array(
		'FieldName' => array(
			'rule' => array('uploadCheckFieldName'),
			'message' => 'フォームエラー'
		),
		'Dir' => array(
			'rule' => array('uploadCheckDir'),
			'message' => 'ディレクトリエラー'
		),
		'UploadError' => array(
			'rule' => array('uploadCheckUploadError'),
			'message' => 'アップロードの際にエラーになりました'
		),
		'MaxSize' => array(
			'rule' => array('uploadCheckMaxSize'),
			'message' => 'ファイルサイズが大きすぎます'
		),
		'InvalidMime' => array(
			'rule' => array('uploadCheckInvalidMime'),
			'message' => '許可されていないファイルタイプです'
		),
		'InvalidExt' => array(
			'rule' => array('uploadCheckInvalidExt'),
			'message' => '許可されていない拡張子です'
		),
	);

	var $config = array();
	var $_data = array();
	
	function setup(&$model, $config=array())
	{
		$this->fileRoot = Configure::read('ImageKit.root');
		
		foreach ($config as $field => $options) {
			if (!is_array($options)) {
				$field = $options;
				$options = array();
			}
			// Check if given field exists
			if(!$model->hasField($field)) {
				trigger_error('UploadBehavior Error: The field "'.$config['field'].'" doesn\'t exists in the model "'.$model->name.'".', E_USER_WARNING);
			}

			if (isset($options['allowedMime']) && !is_array($options['allowedMime'])) {
				$options['allowedMime'] = array($options['allowedMime']);
			}
			//
			if (isset($options['allowedExt']) && !is_array($options['allowedExt'])) {
				$options['allowedExt'] = array($options['allowedExt']);
			}

			$options = Set::merge($this->defaultOptions, $options);
			//
			if ($options['dir']) {
				$options['dir'] = ltrim($options['dir'], DS);
				$options['dir'] = rtrim($options['dir'], DS).DS;
			} else {
				$options['dir'] = Inflector::underscore($model->name).DS;
			}
			//
			if ($options['max_size']) {
				$options['max_size'] =
					$this->__sizeToBytes($options['max_size']);
			}

			$this->config[$model->alias][$field] = $options;

			$validate = $this->validate;
			if (!empty($model->validate[$field])) {
				if (isset($model->validate[$field]['rule'])) {
					$model->validate[$field] =
						array($model->validate[$field]);
				}
				$validate = Set::merge($validate, $model->validate[$field]);
			}
			$model->validate[$field] = $validate;
		}
	}

	function __sizeToBytes($size)
	{
		if(is_numeric($size)) return $size;
		if(!preg_match('/^[1-9][0-9]* (kb|mb|gb|tb)$/i', $size)){
			trigger_error('MeioUploadBehavior Error: The max_size option format is invalid.', E_USER_ERROR);
			return 0;
		}
		list($size, $unit) = explode(' ',$size);
		if(strtolower($unit) == 'kb') return $size*1024;
		if(strtolower($unit) == 'mb') return $size*1048576;
		if(strtolower($unit) == 'gb') return $size*1073741824;
		if(strtolower($unit) == 'tb') return $size*1099511627776;
		trigger_error('MeioUploadBehavior Error: The max_size unit is invalid.', E_USER_ERROR);
		return 0;
	}
	
	function uploadCheckFieldName(&$model, $data)
	{
		foreach($data as $field => $value){
			if (!isset($this->config[$model->alias][$field])) {
				trigger_error('UploadBehavior Error: The field "'.$field.'" wasn\'t declared as part of the UploadBehavior in model "'.$model->name.'".', E_USER_WARNING);
				return false;
			}
		}
		return true;
	}

	function uploadCheckDir(&$model, $data)
	{
		foreach($data as $field => $value) {
			$config = $this->config[$model->alias][$field];
			$dir = $this->fileRoot.$config['dir'];
			// Check if directory exists and create it if required
			if (!is_dir($dir)) {
				$Folder =& new Folder;
				if ($Folder->mkdir($dir)) {
					trigger_error('UploadBehavior Error: The directory '.$dir.' does not exist and cannot be created.', E_USER_WARNING);
					return false;
				}
			}

			// Check if directory is writable
			if (!is_writable($dir)) {
				trigger_error('UploadBehavior Error: The directory '.$config['dir'].' isn\'t writable.', E_USER_WARNING);
				return false;
			}
		}
		return true;
	}
	
	function uploadCheckUploadError(&$model, $data)
	{
		foreach($data as $field => $value){
			$_data = $this->_data[$model->alias][$field];
			if(!empty($_data['name']) && $_data['error'] > 0){
				return false;
			}
		}
		return true;
	}
	
	function uploadCheckMaxSize(&$model, $data)
	{
		foreach($data as $field => $value){
			$config = $this->config[$model->alias][$field];
			$_data = $this->_data[$model->alias][$field];
			if (!empty($_data['name']) && $_data['size'] > $config['max_size']) {
				return false;
			}
		}
		return true;
	}
	
	function uploadCheckInvalidMime(&$model, $data)
	{
		foreach($data as $field => $value){
			$config = $this->config[$model->alias][$field];
			$_data = $this->_data[$model->alias][$field];
			if (!empty($_data['name']) && $config['allowedMime'] && !in_array($_data['type'], $config['allowedMime'])) {
				return false;
			}
		}
		return true;
	}
	
	function uploadCheckInvalidExt(&$model, $data)
	{
		foreach($data as $field => $value){
			$config = $this->config[$model->alias][$field];
			$_data = $this->_data[$model->alias][$field];
			if (!empty($_data['name']) && $config['allowedExt']) {
				$File = new File($_data['name']);
				$ext = low($File->ext());
				if (!in_array($ext, $config['allowedExt'])) {
					return false;
				}
			}
		}
		return true;
	}
	
	function _upload(&$model, $field)
	{
		$data = $this->_data[$model->alias][$field];
		$file = $model->data[$model->alias][$field];
		$file = strtr($file, '/', DS);
		$config = $this->config[$model->alias][$field];
		if ($file) {
			if (!move_uploaded_file($data['tmp_name'], $this->fileRoot.$file)) {
				trigger_error('UploadBehavior Error: The file '.$file.' can\'t upload.', E_USER_WARNING);
				return false;
			}
			chmod($this->fileRoot.$file, 0666);
		
			// 拡張子指定のときは縮小して保存
			if ($config['ext']) {
				$Image = new ImageComponent;
				$Image->set($this->fileRoot.$file);
				$Image->reduce(500, 500);
				$Image->output($this->fileRoot.$file, $config['ext']);
			}
		}
		return $file;
	}
	
	function beforeValidate(&$model) {
		$config = $this->config[$model->alias];
		foreach ($config as $field => $options) {
			if (isset($model->data[$model->alias][$field])) {
				$data = $this->_data[$model->alias][$field] =
					$model->data[$model->alias][$field];
				$model->data[$model->alias][$field] = '';
				
				if (!empty($data['name'])) {
					$model->data[$model->alias][$field]
						= $this->_makeFilePath($model, $field);
				}
			}
		}
		return true;
	}
	
	function _makeFilePath(&$model, $field) {
		$config = $this->config[$model->alias][$field];
		$data = $this->_data[$model->alias][$field];

		$file = $config['dir'].low($data['name']);
		$File = new File($this->fileRoot.$file);
		$file = str_replace($File->name().'.', String::uuid().'.', $file);
		if ($config['ext']) {
			$file = str_replace('.'.$File->ext(), '.'.$config['ext'], $file);
		}
		$file = strtr($file, DS, '/');
		return $file;
	}
	
	function beforeSave(&$model, $created) {
		foreach ($this->config[$model->alias] as $field => $options) {
			if (isset($model->data[$model->alias][$field])) {
				$_data = $this->_data[$model->alias];
				// 画像のみ削除
				if (!empty($_data[$field]['remove'])) {
					if (!$this->_remove($model, $field)) {
						return false;
					}
				}
				if ($model->data[$model->alias][$field]) {
					if (!$this->_upload($model, $field)) {
						return false;
					}
					// 更新の場合は、以前のファイルを削除
					if (!$created) {
						if (!$this->_remove($model, $field)) {
							return false;
						}
					}
				} else {
					unset($model->data[$model->alias][$field]);
				}
			}
		}
		return true;
	}
	
	function _remove(&$model, $field)
	{
		$id = $model->id;
		if (isset($model->data[$model->alias][$model->primaryKey])) {
			$id = $model->data[$model->alias][$model->primaryKey];
		}
		$file = $model->field($field, array($model->primaryKey => $id));
		$file = $this->fileRoot.$file;
		if (is_file($file)) {
			if (!@unlink($file)) {
				trigger_error('UploadBehavior Error: The file '.$file.' can\'t remove.', E_USER_WARNING);
				return false;
			}
		}
		return true;
	}

	function beforeDelete(&$model) {
		foreach ($this->config[$model->alias] as $field => $options) {
			if (!$this->_remove($model, $field)) {
				return false;
			}
		}
		return true;
	}
}
?>