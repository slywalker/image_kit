<?php
if (!Configure::read('ImageKit.root')) {
	Configure::write('ImageKit.root', ROOT.DS.'origin'.DS);
}
if (!Configure::read('ImageKit.thumbUrl')) {
	Configure::write('ImageKit.thumbUrl', 'http://localhost/cake/thumb/');
}
?>