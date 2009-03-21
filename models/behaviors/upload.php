<?php
define('UPLOAD_BEHAVIOR_FILE_ROOT', ROOT.DS.'origin'.DS);

uses('folder', 'file');
App::import('Component', 'ImageKit.Image');

class UploadBehavior extends ModelBehavior {

	var $fileRoot = UPLOAD_BEHAVIOR_FILE_ROOT;
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
		'FieldName' => array(
			'rule' => array('uploadCheckFieldName'),
			'message' => 'フォームエラー'
		),
	);

	var $config = array();
	
	var $Folder = null;
	
	function setup(&$model, $config=array())
	{
		$this->Folder =& new Folder;
		foreach ($config as $field => $options) {
			if (!is_array($options)) {
				$field = $options;
				$options = array();
			}
			// Check if given field exists
			if(!$model->hasField($field)) {
				trigger_error(
					'UploadBehavior Error: The field "' . $config['field'] .
					'" doesn\'t exists in the model "' . $model->name . '".',
					 E_USER_WARNING);
			}
			//
			if (!empty($options['dir'])) {
				$options['dir'] = ltrim($options['dir'], DS);
				$options['dir'] = rtrim($options['dir'], DS) . DS;
			} else {
				$options['dir'] = Inflector::underscore($model->name) . DS;
			}
			//
			if (!empty($options['max_size'])) {
				$options['max_size'] =
					$this->sizeToBytes($options['max_size']);
			}
			//
			if (!empty($options['allowedMime'])) {
				if (!is_array($options['allowedMime'])) {
					$options['allowedMime'] =
						array($options['allowedMime']);
				}
				$options['allowedMime'] = array_merge(
					$this->defaultOptions['allowedMime'],
					$options['allowedMime']);
			}
			//
			if (!empty($options['allowedExt'])) {
				if (!is_array($options['allowedExt'])) {
					$options['allowedExt'] = array($options['allowedExt']);
				}
				$options['allowedExt'] = array_merge(
					$this->defaultOptions['allowedExt'],
					$options['allowedExt']);
			}

			$this->config[$field] =
				Set::merge($this->defaultOptions, $options);
			$model->validate[$field] = $this->validate;
		}
	}

	function sizeToBytes($size)
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
			if (!is_array($value)) {
				return false;
			}
			if (isset($this->config[$field])) {
				return true;
			} else {
				$this->log('UploadBehavior Error: The field "'.$field.'" wasn\'t declared as part of the UploadBehavior in model "'.$model->name.'".');
				return false;
			}
		}
		return true;
	}

	function uploadCheckDir(&$model, $data)
	{
		foreach($data as $field => $value) {
			$dir = $this->fileRoot . $this->config[$field]['dir'];
			// Check if directory exists and create it if required
			if (!is_dir($dir)) {
				if (!$this->Folder->mkdir($dir)) {
					trigger_error(
						'UploadBehavior Error: The directory ' .
						$dir .
						' does not exist and cannot be created.',
						E_USER_WARNING);
					return false;
				}
			}

			// Check if directory is writable
			if (!is_writable($dir)) {
				trigger_error(
					'UploadBehavior Error: The directory ' .
					$this->config[$field]['dir'].' isn\'t writable.',
					E_USER_WARNING);
				return false;
			}
		}
		return true;
	}
	
	function uploadCheckUploadError(&$model, $data)
	{
		foreach($data as $field => $value){
			if(!empty($value['name']) && $value['error'] > 0){
				return false;
			}
		}
		return true;
	}
	
	function uploadCheckMaxSize(&$model, $data)
	{
		foreach($data as $field => $value){
			if (!isset($this->config[$field])) {
				trigger_error(
					'UploadBehavior Error: The Config ' .
					$field.' isn\'t exsists.',
					E_USER_WARNING);
				return false;
			}
			$max_size = $this->config[$field]['max_size'];
			if (!empty($value['name']) && $value['size'] > $max_size) {
				return false;
			}
		}
		return true;
	}
	
	function uploadCheckInvalidMime(&$model, $data)
	{
		foreach($data as $field => $value){
			if (!isset($this->config[$field]['allowedMime'])) {
				trigger_error(
					'UploadBehavior Error: The Config ' .
					$field.' isn\'t exsists.',
					E_USER_WARNING);
				return false;
			}
			$allowedMime = $this->config[$field]['allowedMime'];
			if (!empty($value['name']) && !empty($allowedMime) && 
				!in_array($value['type'], $allowedMime)) {
				return false;
			}
		}
		return true;
	}
	
	function uploadCheckInvalidExt(&$model, $data)
	{
		foreach($data as $field => $value){
			if (!isset($this->config[$field])) {
				trigger_error(
					'UploadBehavior Error: The Config ' .
					$field.' isn\'t exsists.',
					E_USER_WARNING);
				return false;
			}
			$allowedExt = $this->config[$field]['allowedExt'];
			if (!empty($value['name']) && !empty($allowedExt)) {
				$File = new File($value['name']);
				$ext = low($File->ext());
				$result = false;
				foreach ($allowedExt as $extension) {
					if ($ext === $extension) {
						$result = true;
					}
				}
				return $result;
			}
		}
		return true;
	}
	
	function _upload(&$model, $field)
	{
		$data = $model->data[$model->alias][$field];
		$file = $this->config[$field]['dir'] . low($data['name']);
		$File = new File($this->fileRoot . $file);
		$file = str_replace(
			$File->name() . '.',
			String::uuid() . '.',
			$file);
		if (!empty($this->config[$field]['ext'])) {
			$file = str_replace(
				'.' . $File->ext(), 
				'.' . $this->config[$field]['ext'], 
				$file);
		}
		
		if (!move_uploaded_file($data['tmp_name'], $this->fileRoot . $file)) {
			trigger_error(
				'UploadBehavior Error: The file ' .
				$file.' can\'t upload.',
				E_USER_WARNING);
			return false;
		}
		chmod($this->fileRoot . $file, 0666);
		
		// 拡張子指定のときは縮小して保存
		if ($this->config[$field]['ext']) {
			$Image = new ImageComponent;
			$Image->set($this->fileRoot . $file);
			$Image->reduce(500, 500);
			$Image->output($this->fileRoot . $file,
				$this->config[$field]['ext']);
		}
		return $file;
	}
	
	function beforeSave(&$model) {
		foreach ($this->config as $field => $options) {
			if (isset($model->data[$model->alias][$field])) {
				$value = '';
				$data = $model->data[$model->alias][$field];
				if (!empty($data['name'])) {
					if (isset($data[$model->primaryKey])) {
						if (!$this->_remove($model, $field)) {
							return false;
						}
					}
					$value = $this->_upload($model, $field);
					if (!$value) {
						return false;
					}
				}
				if (!empty($data['remove'])) {
					if (!empty($model->data[$model->alias]['id'])) {
						if (!$this->_remove($model, $field)) {
							return false;
						}
					}
				}
				$value = strtr($value, DS, '/');
				$model->data[$model->alias][$field] = $value;
				if (!empty($model->data[$model->alias][$model->primaryKey])) {
					if (empty($data['name']) && empty($data['remove'])) {
						unset($model->data[$model->alias][$field]);
					}
				}
			}
		}
	}
	
	function _remove(&$model, $field)
	{
		$id = $model->id;
		if (isset($model->data[$model->alias]['id'])) {
			$id = $model->data[$model->alias]['id'];
		}
		$file = $model->field($field, array('id' => $id));
		$file = $this->fileRoot . $file;
		if (is_file($file)) {
			if (!@unlink($file)) {
				trigger_error(
					'UploadBehavior Error: The file ' .
					$file.' can\'t remove.',
					E_USER_WARNING);
				return false;
			}
		}
		return true;
	}

	function beforeDelete(&$model) {
		foreach ($this->config as $field => $options) {
			if (!$this->_remove($model, $field)) {
				return false;
			}
		}
		return true;
	}
}
?>