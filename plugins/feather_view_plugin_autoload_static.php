<?php
/*
自动加载动态资源插件
*/
class Feather_View_Plugin_Autoload_Static extends Feather_View_Plugin_Abstract{
	private $initedMap = false;
	private $map = array();
	private $commonMap = array();
	private $domain;
	private $cache_dir;

	protected function initialize(){
		if($domain = $this->getOption('domain')){
			$this->domain = $domain;
		}else{
			$this->domain = '';
		}

		$this->cache_dir = $this->getOption('cache_dir');
	}

	private function initMap(){
		if(!$this->initedMap){
			//合并map表
			foreach((array)$this->getOption('resources') as $resource){
				$resource = require($resource);
				$this->map = array_merge($this->map, $resource['map']);

				if(!empty($resource['commonMap'])){
					$this->commonMap = array_merge($this->commonMap, $resource['commonMap']);
				}
			}

			$this->initedMap = true;
		}
	}

	//获取页面所有的静态资源
	private function getResources($path){
		$selfMap = isset($this->map[$path]) ? $this->map[$path] : array();

		if(isset($selfMap['components'])){
			$componentsMap = array();

			foreach($selfMap['components'] as $components){
				$componentsMap = array_merge_recursive($componentsMap, $this->getResources($components));
			}

			return array_merge_recursive($componentsMap, $selfMap);
		}

		return $selfMap;
	}

	//获取静态资源正确的url
	private function getUrl($resources, $returnHash = false, $withDomain = true, &$hash = array(), &$pkgHash = array()){
		$tmp = array();
		$maps = $this->map;

		foreach($resources as $v){
			//如果存在
			if(isset($maps[$v])){
				$info = $maps[$v];

				//如果未查找过
				if(!isset($hash[$v])){
					//如果pack
					if(isset($info['pkg'])){
						$name = $info['pkg'];
						
						//如果pkg未查找过
						if(!isset($pkgHash[$name])){
							$pkg = $maps[$name];
							//缓存
							$url = $hash[$v] = $pkgHash[$name] = $withDomain ? $this->domain . $pkg['url'] : $pkg['url'];

							//如果pkg有deps，并且不是mod，说明多个非mod文件合并，需要同时加载他们中所有的文件依赖，防止页面报错
							if(isset($pkg['deps']) && !isset($info['isMod'])){
								$tmp = array_merge($tmp, $this->getUrl($pkg['deps'], $returnHash, $withDomain, $hash, $pkgHash));
							}
						}else{
							$url = $hash[$v] = $pkgHash[$name];
						}

						//如果自己有deps，并且是mod，则可以不通过pkg加载依赖，只需要加载自己的依赖就可以了，mod为延迟加载。
						if(isset($info['deps']) && isset($info['isMod'])){
							$tmp = array_merge($tmp, $this->getUrl($info['deps'], $returnHash, $withDomain, $hash, $pkgHash));
						}
					}else{
						$url = $hash[$v] = $withDomain ? $this->domain . $info['url'] : $info['url'];

						//如果自己有deps，没打包，直接加载依赖
						if(isset($info['deps'])){
							$tmp = array_merge($tmp, $this->getUrl($info['deps'], $returnHash, $withDomain, $hash, $pkgHash));
						}
					}
				}else{
					$url = $hash[$v];
				}
			}else{
				$url = $v;
			}

			$tmp[] = $url;
		}

		return !$returnHash ? array_unique($tmp) : $hash;
	}

	private function getRequireMD($deps){
		$hash = $this->getUrl($deps, true, false);
		$mapResult = array();
		$depsResult = array();
		$maps = $this->map;

		foreach($hash as $key => $value){
			if(!isset($mapResult[$value])){
				$mapResult[$value] = array();
			}

			$mapResult[$value][] = $key;

			if(isset($maps[$key])){
				$info = $maps[$key];

				if(isset($info['deps']) && isset($info['isMod'])){
					$depsResult[$key] = $info['deps'];
				}
			}
		}

		foreach($mapResult as $k => &$v){
			$v = array_values(array_unique($v));
		}
		
		return array('map' => $mapResult, 'deps' => $depsResult);
	}

	//执行主程
	public function exec($path, $content = '', $view){
		$view->set('FEATHER_STATIC_DOMAIN', $this->domain);

		$path = '/' . ltrim($path, '/');
		$cache = null;

		if($this->cache_dir){
			$md5path = rtrim($this->cache_dir, '/') . '/' . md5($path) . '.php';

			if(is_file($md5path)){
				$cache = @require($md5path);
			}
		}

		if(!$cache){
			$this->initMap();

			//拿到当前文件所有的map信息
			$selfMap = $this->getResources($path);

			if(!isset($selfMap['isPagelet'])){
				$selfMap = array_merge_recursive($this->commonMap, $selfMap);
			}

			$headJsInline = array();

			if(isset($selfMap['deps'])){
				$config = $this->getRequireMD($selfMap['deps']);
				$config['domain'] = $this->domain;
				$headJsInline[] = 'require.mergeConfig(' . self::jsonEncode($config) . ')';
			}
		
			$cache = array(
				'FEATHER_USE_HEAD_SCRIPTS' => array(
					'inline' => $headJsInline
				),
		        'FEATHER_USE_SCRIPTS' => array(),
				'FEATHER_USE_STYLES' => array()
			);

			if(isset($selfMap['headJs'])){
				$cache['FEATHER_USE_HEAD_SCRIPTS']['outline'] = $this->getUrl($selfMap['headJs']);
			}

			if(isset($selfMap['bottomJs'])){
				$cache['FEATHER_USE_SCRIPTS']['outline'] = $this->getUrl($selfMap['bottomJs']);
			}

			if(isset($selfMap['css'])){
				$cache['FEATHER_USE_STYLES']['outline'] = $this->getUrl($selfMap['css']);
			}

			//如果需要设置缓存
		    if($this->cache_dir){
		   		$output = var_export($cache, true);
		    	$date = date('Y-m-d H:i:s');
		    	file_put_contents($md5path, "<?php\r\n/*\r\ndate: {$date}\r\nfile: {$path}\r\n*/return {$output};");
		    }
		}

		//设置模版值
		$view->set($cache);

		return $content;
	}

	private static function jsonEncode($v){
    	return str_replace('\\', '', json_encode($v));
	}
}