<?php

namespace XModule\XHttpRouter;

use XModule\XHttpRouter\DataTypes\xHttpMethodType;
use XModule\XHttpRouter\DataTypes\xMethodNotFoundType;

class XHttpRouter {

	private ?xHttpMethodType $method      = null;
	private string           $request_uri = "";
	private array  $method_locations = [];
	private array $debug = [];

	public function __construct() {
		if (isset($_SERVER["REQUEST_URI"])) {
			$this->request_uri = rtrim($_SERVER["REQUEST_URI"],'/');
			if($_SERVER["REQUEST_URI"]=='/')$this->request_uri = '/';
		}
	}

	public function get_method(): xHttpMethodType {
		$prefix_list = $this->get_best_prefix();

		$default_method_class =  ($prefix_list[0]??['data'=>[[],xHttpMethodType::class]])["data"][2];

		foreach ($prefix_list as $location ) {
			if(!is_null($this->method)) continue;
			[$method_class, $options] = $this->find_method($location);
			if(!is_null($method_class)) {
				try {
					$create_object = new $method_class();
					if($create_object instanceof xHttpMethodType) {
						if(isset($options['path_options'])) {
							$create_object->__set_path_options($options['path_options']);
						}
						$this->method = $create_object;
					}
				} catch (\Exception $e){}
			}
		}

		if(is_null($this->method)) $this->method = new $default_method_class;

		$this->debug["class_method"] =	 get_class($this->method);
		$this->debug["default_method_class"] =	 $default_method_class;
		$this->debug["best_prefix"] =	 $prefix_list;
		$this->debug["request_uri"] =    $this->request_uri;

		return $this->method;
	}

	public function _get_debug(): array {
		return $this->debug;
	}

	public function add_location( string $location, string $namespace, string $prefix = '/', $method_not_found = xMethodNotFoundType::class ) {
		$this->method_locations[ $prefix ] = [ $location, $namespace, $method_not_found ];
	}

	private function find_method($location) {
		$scan_list = $this->scan_location($location['data'][0]);
		$this->debug["scan_location"][$location["prefix"]] = $scan_list;

		$request_uri = $this->request_uri;
		$variants = [];
		$best_similar = [0, '/', null];
		foreach ($scan_list as $file) {
			$path = strtolower(rtrim($location["prefix"],'/').$file);
			$this->debug["file"][] = $path.' - '.substr($path,0,strlen($request_uri));
			$similar = $this->similar($request_uri, $path);
			$similar_request = substr($request_uri,0,$similar);
			$path_vars = [];
			$_path = explode('/',str_replace('.php','',$path));
			for ($i=0;$i<count($_path);$i++) {
				$__tmp = [];
				for($j=0;$j<=$i;$j++) { $__tmp[]=$_path[$j]; }
				$__ver = implode('/',$__tmp);
				if(strlen($__ver)>0)$path_vars[] = $__ver;
			}
			$this->debug["__similar_check"] [] = $similar_request .' === '.implode(' | ',$path_vars);
//			if(substr($path,0,strlen($similar_request)) == strtolower($similar_request) ) {
			if(in_array(strtolower($similar_request), $path_vars )) {
				if($best_similar[0] < $similar) $best_similar = [$similar, $similar_request, $file];
				$this->debug["__similar"] [] = substr($path,0,strlen($similar_request)).' == '.strtolower($similar_request) .' === '.implode(' | ',$path_vars) ;
			}
			$this->debug["__compare_request_uri"] [] = substr($path,0,strlen($request_uri)) . ' == '. strtolower($request_uri);
			if(substr($path,0,strlen($request_uri)) == strtolower($request_uri) ) {
				$_last_path = substr($path,strlen($request_uri));
				$this->debug["__compare_request_uri"] [] = ' ----- ' . $_last_path;
				if( in_array($_last_path, ['.php','/index.php','index.php']))
					$variants[] = [$location['data'][1].str_replace('/','\\',substr($file,0,-4)), []];
			}
		}
		if($best_similar[0] > strlen($location["prefix"])) {
			$variants[] = [$location['data'][1].str_replace('/','\\',substr($best_similar[2],0,-4)),[
				"path_options" => explode('/',trim(str_replace($best_similar[1], '',$request_uri),'/'))
			]];
		}

		$this->debug["v_request_uri"][$location["prefix"]] = $request_uri;
		$this->debug["variants"][$location["prefix"]] = $variants;
		return $variants[0] ?? [null,null];
	}


	private function get_best_prefix(): array {
		$prefix_list = [];
		foreach($this->method_locations as $prefix => $data) {
			if (strtolower(substr($this->request_uri,0, strlen($prefix))) == strtolower($prefix)) {
				$prefix_list[] = ["prefix" => $prefix, "data" => $data];
			}
		}
		usort($prefix_list, function($a, $b) {
			if(strlen($a["prefix"]) == strlen($b["prefix"]) )	 return 0;
			return (strlen($a["prefix"]) > strlen($b["prefix"])) ? -1 : 1;
		});

		return $prefix_list;
	}

	private function similar($str1 , $str2): int {
		$len = min(strlen($str1),strlen($str2)); $c=0;
		for($i=0;$i<$len;$i++) if($str1[$i] == $str2[$i]) $c++; else return $c;
		return $c;
	}

	private function scan_location ($target): array {
		$result = [];
		foreach(scandir($target) as $filename) {
			if ($filename[0] === '.') continue;
			$filePath = $target . DIRECTORY_SEPARATOR . $filename;
			if (is_dir($filePath)) {
				foreach ( $this->scan_location($filePath) as $childFilename) {
					$result[] = '/' . $filename . $childFilename;
				}
			} else $result[] = '/'.$filename;
		}
		return $result;
	}
}