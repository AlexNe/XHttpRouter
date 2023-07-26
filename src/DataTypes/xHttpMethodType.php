<?php

namespace XModule\XHttpRouter\DataTypes;

abstract class xHttpMethodType {
	protected $_content_type = null;
	protected array $_path_options  = [];

	public function __construct() {
		if(!is_null($this->_content_type)) header("Content-Type: ".$this->_content_type);
	}

	abstract public function run();

	public function __set_path_options($options) {
		$this->_path_options = $options;
	}

	protected function getOption(int $index) {
		return $this->_path_options[$index] ?? null;
	}
}