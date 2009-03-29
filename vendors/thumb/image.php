<?php
class Image {
	private $data = null; //イメージデータ
	private $src = array();

	/*
	 * イメージファイルのプロパティを取得しデータを格納する
	 * @param string $path
	 * @return boolean Success
	 */
	function set($path)
	{
		// 入力ファイルのプロパティを定義
		if (!$size = @getimagesize($path)) {
			return false;
		}
		// 1pxの画像は処理しない
		if ($size['bits'] == 1) {
			return false;
		}
		$this->src['width']  = $size[0];
		$this->src['height'] = $size[1];
		$this->src['type']   = $size[2];

		$data = '';
		switch ($this->src['type']) {
			case IMAGETYPE_JPEG :
				$data = @imagecreatefromjpeg($path);
				break;
			case IMAGETYPE_GIF :
				$data = @imagecreatefromgif($path);
				break;
			case IMAGETYPE_PNG :
				$data = @imagecreatefrompng($path);
				break;
			default:
				break;
		}
		if (!$data) {
			return false;
		}
		$this->data = $data;
		return true;
	}

	/*
	 * 縮小したイメージデータと置き換える
	 * @param integer $width
	 * @param integer $height
	 * @return boolean Success
	 */
	function reduce($width, $height)
	{
		if (!$this->data || !$this->src) {
			return false;
		}
		if ($this->src['width'] < $width && $this->src['height'] < $height) {
			return false;
		}
		$raitoW = $width / $this->src['width'];
		$raitoH = $height / $this->src['height'];
		$raito = ($raitoW < $raitoH) ? $raitoW : $raitoH;
		$w = $this->src['width'] * $raito;
		$h = $this->src['height'] * $raito;

		$data = imagecreatetruecolor($w, $h);
		imagecopyresampled($data, $this->data, 0, 0, 0, 0,
			$w, $h, $this->src['width'], $this->src['height']);
		imagedestroy($this->data);
		$this->data = $data;
		return true;
	}

	/*
	 * 縮小トリミングしたイメージデータと置き換える
	 * @param integer $width
	 * @param integer $height
	 * @return boolean Success
	 */
	function trim($width, $height)
	{
		if (!$this->data || !$this->src) {
			return false;
		}

		$raitoW = $width / $this->src['width'];
		$raitoH = $height / $this->src['height'];
		$raito = ($raitoW > $raitoH) ? $raitoW : $raitoH;
		$w = $width / $raito;
		$h = $height / $raito;
		$x = (($this->src['width'] - $w) > 0) ?
			($this->src['width'] - $w) / 2 : 0;
		$y = (($this->src['height'] - $h) > 0) ?
			($this->src['height'] - $h) / 2 : 0;

		$data = imagecreatetruecolor($width, $height);
		imagecopyresampled($data, $this->data, 0, 0,
			$x, $y, $width, $height, $w, $h);
		imagedestroy($this->data);
		$this->data = $data;
		return true;
	}

	/*
	 * イメージデータをファイルに出力
	 * @param string $path
	 * @param integer $type IMAGETYPE_JPEG or IMAGETYPE_GIF or IMAGETYPE_PNG
	 * @param integer $quality
	 * @return boolean Success
	 */
	function output($path, $quality = 100, $type = null)
	{
		if (!$this->data || !$this->src) {
			return false;
		}
		if (!$type) {
			$type = $this->src['type'];
		}
		switch ($type) {
			case IMAGETYPE_JPEG :
				imagejpeg($this->data, $path, $quality);
				break;
			case IMAGETYPE_GIF :
				imagetruecolortopalette($this->data, 1, 256);
				imagegif($this->data, $path);
				break;
			case IMAGETYPE_PNG :
				imagepng($this->data, $path, $quality);
				break;
			default:
				return false;
		}
		@chmod($path, 0666);
		return true;
	}

	/*
	 * イメージデータを破棄
	 * @return boolean Success
	 */
	function destroy()
	{
		return imagedestroy($this->data);
	}
}
?>