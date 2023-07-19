<?php

namespace XModule\XHttpRouter\DataTypes;

abstract class xHttpMethodType {
	protected $_content_type = null;

	public function __construct() {
		if(!is_null($this->_content_type)) header("Content-Type: ".$this->_content_type);
	}

	abstract public function run();
}