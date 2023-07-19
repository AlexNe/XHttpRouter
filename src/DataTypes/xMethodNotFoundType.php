<?php

namespace XModule\XHttpRouter\DataTypes;

class xMethodNotFoundType extends xHttpMethodType {

	public function run(){
		echo '"not found"';
	}
}