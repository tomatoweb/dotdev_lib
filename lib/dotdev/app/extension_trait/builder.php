<?php
/*****
 * Version 1.0.2018-11-21
**/
namespace dotdev\app\extension_trait;

use \tools\error as e;
use \tools\helper as h;
use \tools\event;
use \leafo\lessc;
use \dpupius\phpclosure;

trait builder {

	public function builder_compile_preset(){

		// abort if there is no build configuration given
		if(!$this->env_get('preset:build')) return !e::logtrigger('Env key not given: preset:build');

		// define path to build file
		$build_file = $this->env_get('preset:build:path_cachefile').'/build_'.$this->env_get('preset:build:version').'.php';

		// check if build file exists
		$build_file_given = file_exists($build_file);

		// load last updatekey
		$build_updatekey = $build_file_given ? include($build_file) : null;

		// check forced recompiling, when updatekey is already up to date !! Some browsers need CTRL+F5 to empty cache and reload sources
		if(h::gR('force_recompile') and $build_updatekey === $this->env_get('preset:build:updatekey')){

			// simply unset build file to force recompiling in every way
			`rm -fR $build_file`;
			$build_updatekey = null;
			}

		// compare updatekey to detect if build has to be updated
		if($build_updatekey !== $this->env_get('preset:build:updatekey')){

			// define path infos
			$path_source = $this->env_get('preset:build:path_source');
			$path_result = $this->env_get('preset:build:path_result').'/'.$this->env_get('preset:build:version');
			$path_temp = $this->env_get('preset:build:path_result').'/'.sha1($this->env_get('preset:build:version').$this->env_get('preset:build:updatekey'));

			// if we already have this temporary directory, another process seem to build the preset
			if(file_exists($path_temp)){

				// wait for concurrent process to finish
				for($i = 1; $i <= 3; $i++){

					// sleep 1 second
					sleep(1);

					// check if build file exists
					$build_file_given = file_exists($build_file);

					// load last updatekey
					$build_updatekey = $build_file_given ? include($build_file) : null;

					// if updatekey is now up to date, return success
					if($build_updatekey === $this->env_get('preset:build:updatekey')) return true;
					}
				}

			// else
			else{

				// create temp path
				@mkdir($path_temp, 0755, true);
				}

			// check if there are LESS files to compile
			if(!empty($this->env_get('preset:build:less_file'))){

				// take LESS files
				$list = $this->env_get('preset:build:less_file');

				// if it is only one file, contain it in an array
				if(!is_array($list)) $list = [$list];

				// run each LESS file to compile it
				foreach($list as $file){

					// match path and filename (or skip entry)
					if(!preg_match('/^(.*)\/([^\/]*)\.less$/', $file, $match)) continue;

					// create corresponding directory in temp path
					if(!file_exists($path_temp.$match[1])) @mkdir($path_temp.$match[1], 0755, true);

					// compile LESS and save to css file
					$less_file = $path_source.$file;
					$less_import_path = $this->env_get('preset:build:path_source').'/'.$this->env_get('preset:build:less_import_dir');
					$css_file = $path_temp.$match[1].'/'.$match[2].'.css';
					$this->builder_compile_less($css_file, $less_file, $less_import_path);
					}
				}

			// check if there are JS files to compile
			if(!empty($this->env_get('preset:build:js_file'))){

				// take JS files
				$list = $this->env_get('preset:build:js_file');

				// if it is only one file, contain it in an array
				if(!is_array($list)) $list = [$list];

				// run each JS file to compile it
				foreach($list as $file){

					// match path and filename (or skip entry)
					if(!preg_match('/^(.*)\/([^\/]*)\.js$/', $file, $match)) continue;

					// create corresponding directory in temp path
					if(!file_exists($path_temp.$match[1])) @mkdir($path_temp.$match[1], 0755, true);

					// compile JS (using closure)
					$js_target_file = $path_temp.$file;
					$js_source_file = $path_source.$file;
					$this->builder_compile_js($js_target_file, $js_source_file, $this->env_get('preset:build:js_use_closure'));
					}
				}

			// check if there a directories for simply copying
			if(!empty($this->env_get('preset:build:copy'))){

				// each entry
				foreach($this->env_get('preset:build:copy') as $link){

					// recursive copy its files and directories
					$this->builder_recursive_copy($path_source.$link, $path_temp.$link);
					}
				}

			// finally move generated build path
			if(file_exists($path_result)) `rm -fR $path_result`;
			`mv $path_temp $path_result`;

			// create build file
			if(!file_put_contents($build_file, '<?php return '.h::encode_php($this->env_get('preset:build:updatekey')).';')){

				// log error on failure
				return !e::logtrigger('Cannot write preset build file: '.$build_file);
				}
			}

		// return success
		return true;
		}

	public function builder_compile_less($minfile, $source, $import_dir = null, $force_recompile = true){

		// if file isn't compiled yet or should be compiled again
		if(!file_exists($minfile) or $force_recompile){

			// init less compiler
			$less = new lessc;
			$less->setFormatter("compressed");

			// define import dir, if given
			if(!empty($import_dir)){
				if(is_string($import_dir)) $import_dir = [$import_dir];
				$less->setImportDir($import_dir);
				}

			// define less variables
			$less_var = [
				'pcdnurl' => "'".$this->builder_pcdn_url()."'",
				];

			// if presets defines some additional values (as an associative array)
			if($this->env_is('preset:build:less_var', '~assoc/a')){

				// append values
				$less_var += $this->env_get('preset:build:less_var');
				}

			// set less variables
			$less->setVariables($less_var);

			// compile less to css
			$data = $less->compileFile($source);

			// save minified version
			$min_done = file_put_contents($minfile, $data);

			// on error
			if(!$min_done){
				e::logtrigger('Builder cannot save minified version of: '.$minfile);
				return false;
				}

			// create and save gzip version
			$gz_done = file_put_contents($minfile.".gz", gzencode($data, 6));

			// on error
			if(!$gz_done){
				e::logtrigger('Builder cannot save .gz version of: '.$minfile);
				return false;
				}
			}

		// return filename (as success)
		return $minfile;
		}

	public function builder_compile_js($minfile, $sources, $use_closure = false, $force_recompile = true){

		// if file isn't compiled yet or should be compiled again
		if(!file_exists($minfile) or $force_recompile){

			// define single source as array (multiples sources are allowed)
			if(!is_array($sources)) $sources = [$sources];

			// js data
			$data = '';

			// for each source
			foreach($sources as $k => $v){

				// define filename
				$file = is_string($k) ? $k : $v;

				// define use of closure for that file
				if(!$use_closure) $v = false;

				// if closure is allowed
				if(!is_bool($v) or $v){

					// init new closure compiler
					$closure = new phpclosure;
					$closure->hideDebugInfo();
					$closure->simpleMode();
					$closure->add($file);

					// compile and append minified js data
					$data .= $closure->_compile()."\n\n";
					}

				// else
				else {

					// simply append content of js file
					$data .= file_get_contents($file)."\n\n";
					}
				}

			// save minified version
			$min_done = file_put_contents($minfile, $data);

			// on error
			if(!$min_done){
				e::logtrigger('Builder cannot save minified version of: '.$minfile);
				return false;
				}

			// create and save gzip version
			$gz_done = file_put_contents($minfile.".gz", gzencode($data, 6));

			// on error
			if(!$gz_done){
				e::logtrigger('Builder cannot save .gz version of: '.$minfile);
				return false;
				}
			}

		// return filename (as success)
		return $minfile;
		}

	public function builder_gzip_file($source){
		if(!file_exists($source)) return false;
		elseif(file_exists($source.'.gz') or filesize($source) < 1024) return true; // Nur gzip auf Dateien mit mindestens 1KB anwenden
		return file_put_contents($source.'.gz', gzencode(file_get_contents($source), 6)) !== false;
		}

	public function builder_recursive_copy($dir, $to){

		// abort, if dir is not an dir
		if(!is_dir($dir)) return false;

		// abort, if target directory could not be created
		if(!is_dir($to) and !mkdir($to, 0755, true)) return false;

		// for each entry in directory
		foreach(scandir($dir) as $link){

			// skip hidden and php files
			if($link[0] === '.' or substr($link, -4) === '.php') continue;

			// for directories
			if(is_dir($dir.'/'.$link)){

				// recursively call
				$this->builder_recursive_copy($dir.'/'.$link, $to.'/'.$link);
				continue;
				}

			// define source and target
			$source = $dir.'/'.$link;
			$target = $to.'/'.$link;

			// copy file
			$copy = copy($source, $target);

			// on error
			if(!$copy) return !e::logtrigger('Builder cannot copy '.$source.' to '.$target);

			// for specific file types
			if(preg_match('/^.+\.([^\.]+)$/', $target, $match) and in_array($match[1], ['css','js','html','json','eot','svg','ttf','woff'])){

				// create .gz version
				$gz_done = $this->builder_gzip_file($target);

				// on error
				if(!$gz_done) return !e::logtrigger('Builder cannot create .gz version of: '.$target);
				}
			}
		}

	public function builder_url($file = null){

		// return url
		return $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['SERVER_NAME'].$this->env_get('preset:build:url_prefix').'/'.$this->env_get('preset:build:version').($file ? $file.'?'.$this->env_get('preset:build:updatekey') : '');
		}

	public function builder_css_url($file = null){

		// if there is no file defined, take first LESS entry from preset as a CSS file
		if($file === null){
			$file = $this->env_get('preset:build:less_file');
			if(is_array($file)) $file = array_shift($file);
			}

		// abort if not string
		if(!is_string($file)) return '';

		// convert
		if(substr($file, -5) == '.less') $file = substr($file, 0, -5).'.css';

		// return url
		return $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['SERVER_NAME'].$this->env_get('preset:build:url_prefix').'/'.$this->env_get('preset:build:version').$file.'?'.$this->env_get('preset:build:updatekey');
		}

	public function builder_js_url($file = null){

		// if there is no file defined, take first LESS entry from preset as a CSS file
		if($file === null){
			$file = $this->env_get('preset:build:js_file');
			if(is_array($file)) $file = array_shift($file);
			}

		// abort if not string
		if(!is_string($file)) return '';

		// return url
		return $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['SERVER_NAME'].$this->env_get('preset:build:url_prefix').'/'.$this->env_get('preset:build:version').$file.'?'.$this->env_get('preset:build:updatekey');
		}

	public function builder_cdn_url($sub_url = ''){

		// define cache
		static $server_url;

		// if url not loaded yet
		if($server_url === null){

			// define default
			$server_url = 'https://cdn.dotdev.de';

			// define config file
			$config_file = $_SERVER['ENV_PATH'].'/config/service/mtcdn/server.php';

			// if config file is given, load content to cache
			if(is_file($config_file)) $server_url = include($config_file);
			}

		// return cached server_url + sub_url
		return $server_url.($sub_url ? ($sub_url[0] == '/' ? '' : '/').$sub_url : '');
		}

	public function builder_pcdn_url($sub_url = null){

		// define cache
		static $server_url;

		// if server url wasn't loaded before
		if($server_url === null){

			// load server url
			$server_url = $this->env_get('domain:portal_cdn');

			// if no protocol detected
			if(!in_array(substr($server_url, 0, strpos($server_url, '://')), ['http','https'])){

				// prepend protocol to server url
				$server_url = $_SERVER['REQUEST_SCHEME'].'://'.$server_url;
				}
			}

		// return complete url
		return $server_url.'/'.$this->env_get('nexus:projectname').'/'.$this->env_get('preset:ID').($sub_url ? $sub_url.'?'.$this->env_get('preset:build:updatekey') : '');
		}

	}
