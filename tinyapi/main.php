<?php

// TinyAPI Framework
//     -- CORE --

class API {
	public static $config = array(			// global configuration
			'method_dir'		=> '',		// methods are in the root folder by default
			'method_file_ext'	=> '.php',
			'method_file_pre'	=> '',
			'directory_separator' => null,
			'formatters'		=> array()
		),
		$output			= array(),			// where structured output is stored until finish();
		$headers		= array(),			// HTTP headers, lower-cased
		$default_content_type = 'text/plain',
		$default_format	= 'json',			// why of course
		$basepath		= '',				// path to this file
		$include_path	= '',
		$log_messages	= array();			// stores log message
	
	function __construct($args=NULL) {
		self::init();
	}
	
	
	public static function init() {
		global $tinyapi_config;
		
		$new_include_path = (function_exists('get_include_path') ? get_include_path() : ini_get('include_path'));
		$new_include_path = dirname(__FILE__) . PATH_SEPARATOR . dirname(dirname(__FILE__)) . PATH_SEPARATOR . $new_include_path;
		function_exists('set_include_path') ? set_include_path( $new_include_path ) : ini_set('include_path', $new_include_path);
		self::$include_path = $new_include_path;
		
		self::$config['directory_separator'] = self::oreo(DIRECTORY_SEPARATOR, '/');
		
		self::$basepath = self::getBasePath();
		
		if (isset($tinyapi_config)) {
			self::loadConfig($tinyapi_config);
		}
		
		$request_uri = $_SERVER['REQUEST_URI'];
		if (strpos($request_uri,'?')!==FALSE) {
			$request_uri = substr($request_uri, 0, strpos($request_uri,'?'));
		}
		
		$response = self::exec(self::getRelativeUrl($request_uri));
		if ($response!==FALSE) {
			self::$output = $response;
		}
		else {
			self::setHeader('HTTP/1.0 404 Not Found');
			$error_response = self::exec('error');
			if ($error_response!==FALSE) {
				self::$output = $error_response;
			}
			else {
				self::$output = array(
					'message' => 'An unhandled exception occured.',
					'error_code' => 0
				);
			}
		}
		
		self::finish();
	}
	
	
	/*
	 *	Execute the method matching a URL or method name. Optionally override $_GET and $_POST. If either overrides are set, both are overwritten.
	 *	@param method			A URL relative to TinyAPI's installed root. This is how the method is found.
	 *	@param override_get		Optional associative array of GET parameters. Overwrites $_GET while executing the routed method.
	 *	@param override_post	Optional associative array of POST parameters. Overwrites $_POST while executing the routed method.
	 */
	public static function exec($method='', $override_get=NULL, $override_post=NULL) {
		if (strpos($method, '?')!==FALSE) {
			$method = substr($method, 0, strpos($method, '?'));
		}
		
		$orig_method = $method;
		
		if (strlen($method)===0) {
			self::log('API->exec(NULL) >> Required parameter $method not defined.');
			return FALSE;
		}
		
		// remove parent references
		$method = preg_replace('/(?:([^\/]*)\/)?\.\./sim', '$1', strtolower($method));
		// remove non - alpha-numerics
		$method = preg_replace('/[^a-z0-9\/]+/sim', '_', $method);
		// multiple slashes
		$method = preg_replace('/\/+/sim', '/', $method);
		
		if (substr($method, 0, 1)==="/") {
			$method = substr($method, 1);
		}
		if (substr($method, -1, 1)==="/") {
			$method = substr($method, 0, -1);
		}
		
		//$method_dir = substr($method, 0, strrpos($method, '/'));
		$method_dir = dirname($method);
		if ($method_dir==='.') {
			$method_dir = '';
		}
		elseif (substr($method_dir, -1, 1)==='/') {
			$method_dir = substr($method_dir, 0, -1);
		}
		$method_file = basename($method);
		
		//die($method_dir);
		
		$dir = self::$config['method_dir'];
		$pre = self::$config['method_file_pre'];
		$ext = self::$config['method_file_ext'];
		
		if (substr($dir, -1, 1)!=='/') {
			$dir .= '/';
		}
		if (substr($dir, 0, 2)==='./') {
			$dir = realpath(dirname(__FILE__)) . substr($dir, 1);
		}
		
		//echo $dir.$method_dir.$pre.$method_file.$ext . "\n\n";
		
		$method_dirs = explode('/', $method);
		
		$method_name = 'index';
		
		while (strlen($method_dir)>0 && !is_file($dir.$method_dir.'/'.$pre.$method_file.$ext)) {
			if ($method_name==='index') {
				$method_name = $method_file;
			}
			else {
				$method_name = $method_file . '_' . $method_name;
			}
			if (strrpos($method_dir, '/')===FALSE) {
				$method_file = $method_dir;
				$method_dir = '';
			}
			else {
				$method_file = substr($method_dir, strrpos($method_dir, '/')+1);
				$method_dir = substr($method_dir, 0, strrpos($method_dir, '/'));
			}
		}
		
		
		if ($method_dir.$method_file==='tinyapi') {
			return FALSE;
		}
		
		
		if (strlen($method_dir)>0 && substr($method_dir, -1, 1)!=='/') {
			$method_dir .= "/";
		}
		
		$filename = $dir.$method_dir.$pre.$method_file.$ext;
		
		
		$strict_return = FALSE;
		$return_value = NULL;
		
		if (is_file($filename)) {
			// pull in the code
			$code = file_get_contents($filename);
			
			$file_include_path = realpath(dirname($filename)) . PATH_SEPARATOR . self::$include_path;
			if (function_exists('set_include_path')) {
				set_include_path($file_include_path);
			}
			else {
				ini_set('include_path', $file_include_path);
			}
			
			
			if (is_array($override_get) || is_array($override_post)) {
				$previous_request_params = array_merge(array(), $_REQUEST);
				foreach($_REQUEST as $key=>$value) {
					unset($_REQUEST[$key]);
				}
				$previous_get_params = array_merge(array(), $_GET);
				foreach($_GET as $key=>$value) {
					unset($_GET[$key]);
				}
				$previous_post_params = array_merge(array(), $_POST);
				foreach($_POST as $key=>$value) {
					unset($_POST[$key]);
				}
				if (is_array($override_get)) {
					foreach ($override_get as $key=>$val) {
						$_REQUEST[$key] = $val;
						$_GET[$key] = $val;
					}
				}
				if (is_array($override_post)) {
					foreach ($override_post as $key=>$val) {
						$_REQUEST[$key] = $val;
						$_POST[$key] = $val;
					}
				}
			}
			
			
			//echo ini_get('include_path') . "\n\n";
			
			// dynamically execute it, removing PHP start/end tags (they would cause syntax errors)
			$class = eval(preg_replace("/^\s*?(<\?(php)?)?(.+)(\?>)?$/sim", "$3", $code));
			
			if (function_exists('get_include_path')) {
				set_include_path(self::$include_path);
			}
			else {
				ini_get('include_path', self::$include_path);
			}
			
			//echo ini_get('include_path') . "\n\n";
			
			
			$is_class = is_string($class);		// || class_exists($class);
			
			//self::log('$is_class='.strval($is_class).', method_exists($class, $method_name)='.strval(method_exists($class, $method_name)).' //');
			self::log($class . ' :: ' . $method_name);
			
			// check if the method is available statically
			if ($is_class && method_exists($class, $method_name) && is_callable(array($class, $method_name))) {
				$strict_return = TRUE;
				$return_value = self::oreo(call_user_func(array($class, $method_name)), array());
			}
			// else, instance the class
			elseif ($is_class) {
				$class = new $class();
			}
			
			if ($class && $strict_return!==TRUE) {
				// check if the requested method exists
				if (method_exists($class, $method_name)) {
					$strict_return = TRUE;
					$return_value = self::oreo($class->$method_name(), array());
				}
				// check if an error method exists
				else if (method_exists($class, 'error')) {
					$strict_return = TRUE;
					$return_value = self::oreo($class->error(), array());
				}
			}
			
			
			if (is_array($override_get) || is_array($override_post)) {
				// reset REQUEST
				foreach($_REQUEST as $key=>$value) {
					unset($_REQUEST[$key]);
				}
				foreach($previous_request_params as $key=>$value) {
					$_REQUEST[$key] = $value;
				}
				// reset GET
				foreach($_GET as $key=>$value) {
					unset($_GET[$key]);
				}
				foreach($previous_get_params as $key=>$value) {
					$_GET[$key] = $value;
				}
				// reset POST
				foreach($_POST as $key=>$value) {
					unset($_POST[$key]);
				}
				foreach($previous_post_params as $key=>$value) {
					$_POST[$key] = $value;
				}
			}
		}
		
		return $strict_return===TRUE ? $return_value : FALSE;
	}
	
	
	/*
	 *	Format the final response body and send output with headers.
	 */
	public static function finish() {
		$body = '';
		
		if (isset(self::$config['format_override'])) {
			$user_format = self::$config['format_override'];
		}
		else {
			//$user_format = self::oreo(self::arrayValue(self::$config,'format')!==NULL, self::arrayValue($_REQUEST,'format'));
			$user_format = isset($_REQUEST['format']) ? $_REQUEST['format'] : self::$config['format'];
		}
		
		$format = self::oreo($user_format, self::$default_format);
		
		if (!is_array(self::$output) && !is_object(self::$output)) {
			self::$output = array(
				"" => self::$output
			);
		}
		
		
		$format_lower = strtolower($format);
		$formatter_found = FALSE;
		// Custom formatters.
		if (is_array(self::arrayValue(self::$config, 'formatters'))) {
			foreach (self::$config['formatters'] as $search_format=>$search_value) {
				if (strtolower($search_format)===$format_lower) {
					// Try CustomResponseFormatter::format($output, $format)
					if (class_exists($search_value) && method_exists($search_value, 'format') && is_callable(array($search_value, 'format'))) {
						$body = call_user_func(array($search_value, 'format'), self::$output, $format);
						$formatter_found = TRUE;
					}
					// Try customResponseFormatter($output, $format)
					elseif (method_exists($search_value)) {
						$body = call_user_func($search_value, self::$output, $format);
						$formatter_found = TRUE;
					}
					else {
						self::log('API -> Unrecognized formatter. Must be the name of a class with static method format(), or a function name.');
					}
				}
			}
		}
		
		if ($formatter_found!==TRUE) {
			switch ($format_lower) {
				case "json":
				case "jsonp":
					$is_empty = TRUE;
					foreach (self::$output as $k=>$v) {
						$is_empty = FALSE;
						break;
					}
					
					if ($is_empty===TRUE) {
						$body = self::json_encode((object) self::$output);
					}
					else {
						$body = self::json_encode(self::$output);
					}
					
					$callback = self::oreo(
						self::arrayValue($_GET,'callback',TRUE),
						self::arrayValue($_GET,'jsonp_callback',TRUE)
					);
					
					if (isset($callback)) {
						// set the correct header for JSONp
						if (empty(self::$headers['content-type'])) {
							self::$headers['content-type'] = 'text/javascript';
						}
						
						// optional trailing 'id' parameter
						$idt = '';
						if (isset($_GET['jsonp_id'])) {
							$idt = ',\'' . addslashes($_GET['jsonp_id']) . '\'';
						}
						
						// callback parameter as json-string
						// FORMAT:  callback( String json , [String id] )
						if (strval(self::arrayValue($_GET,'jsonp_string',TRUE))==='true' || strval(self::arrayValue($_GET,'jsonp_string',TRUE))==='1') {
							$body = self::formatJsonpCallback($callback) . '("' . addslashes($body) . '"' . $idt . ');';
						}
						// jsonp + try..catch
						// FORMAT:  callback( (Object) json , [Error parseError] , [String id] )
						else if (strval(self::arrayValue($_GET,'jsonp_try',TRUE))==='true' || strval(self::arrayValue($_GET,'jsonp_try',TRUE))==='1') {
							$body = '(function(j,e){try{j=' . $body . '}catch(a){e=a;}' . self::formatJsonpCallback($callback) . '(j,e' . $idt . ');})();';
						}
						// 'normal' jsonp
						// FORMAT:  callback( Object json , [String id] )
						else {
							$body = self::formatJsonpCallback($callback) . '(' . $body . $idt . ');';
						}
						
						/*
						if (isset($_GET['jsonp_notify'])) {
							$body = '(function(a){try{' . $body .'}catch(e){a=e.message;}'. self::formatJsonpCallback($_GET['jsonp_notify']) . '(!a,a);})()';
						}
						*/
					}
					else {
						// set the correct header so WebKit doens't complain
						if (empty(self::$headers['content-type'])) {
							self::$headers['content-type'] = 'application/x-javascript';
						}
					}
					break;
				
				// note: XML response format is still in alpha
				case "xml":
					$xml_root_name = self::oreo($_REQUEST['xml_root_name'], 'response');
					if (!function_exists('array_to_xml')) {
						require_once 'lib/array_to_xml.inc.php';
					}
					if (function_exists('array_to_xml')) {
						$body = '<?xml version="1.0" encoding="UTF-8" ?'.'>\n<'.$xml_root_name.'>' . array_to_xml(self::$output) . '\n</'.$xml_root_name.'>';
					}
					else {
						self::log("Could not locate lib/array_to_xml.inc.php (required for the XML output format).");
					}
					break;
				
				default:
					$body = var_export(self::$output, TRUE);
					if (empty(self::$headers['content-type'])) {
						self::$headers['content-type'] = 'text/plain';
					}
					self::$headers['x-tinyapi-format'] = 'php-serialized';
			}
		}
		
		
		// automatically set Content-Length if not already set
		if (empty(self::$headers['content-length'])) {
			self::$headers['content-length'] = strlen($body);
		}
		if (empty(self::$headers['content-type'])) {
			self::$headers['content-type'] = self::$default_content_type;
		}
		if (empty(self::$headers['status'])) {
			self::$headers['status'] = 200;
		}
		
		
		if (!headers_sent()) {
				// If the status header does not include a message, use the third 
				// header() parameter so that the message is auto-generated by PHP.
			$status_code = NULL;
			$skip_status = FALSE;
			if (isset(self::$headers['status']) && strpos(strval(self::$headers['status']), ' ')===FALSE) {
				$status_code = intval(self::$headers['status']);
				$skip_status = TRUE;
			}
			
			// send response headers
			foreach (self::$headers as $h_key=>$h_value) {
				if ($h_key==='status') {
					// for custom status code/description pairs
					if ($skip_status===FALSE) {
						header($h_value);
					}
				}
				else {
					if (is_array($h_value)) {
						foreach ($h_value as $v) {
							header( ucwords($h_key) . ': ' . preg_replace('/\r\n(\t)?/sm', '\r\n\t', $v), TRUE, $status_code );
							$status_code = NULL;
						}
					}
					else {
						header( ucwords($h_key) . ': ' . preg_replace('/\r\n(\t)?/sm', '\r\n\t', $h_value), FALSE, $status_code );
					}
					$status_code = NULL;
				}
			}
			// if no headers were set, an integer status will not have been sent, so it must be forced.
			if (isset($status_code)) {
				header('x', FALSE, $status_code);
			}
		}
		
		// send response body
		echo $body;
		
		exit;
	}
	
	
	public static function setHeader($header='', $replace=TRUE) {
		if (is_array($header)) {
			foreach($header as $h) {
				self::setHeader($h, $replace);
			}
		}
		elseif (isset($header)) {
			$lc = strtolower($header);
			if (substr($lc, 0, 5)==='http/') {
				$key = 'status';
				//$value = preg_replace('/^http\/[0-9\.]+\s*(.*)\s*$/sim', '$1', $header);
				$value = $header;
				// if no spaces, use status code only
				if (strpos($value, ' ')===FALSE) {
					$value = intval($value);
				}
			}
			else {
				$key = substr($lc, 0, strpos($lc, ':'));
				$value = preg_replace('/^[^\:]+\:\s*(.*)\s*$/sim', '$1', $header);
			}
			
			// mimick PHP's automatic redirect status
			if ($key==='location' && empty(self::$headers['status'])) {
				self::$headers['status'] = 302;
			}
			
			if ($value==='') {
				unset(self::$headers[$key]);
			}
			elseif ($replace===FALSE && isset(self::$headers[$key]) && $key!=='status') {
				if (is_array(self::$headers[$key])) {
					self::$headers[$key] []= $value;
				}
				else {
					self::$headers[$key] = array(self::$headers[$key], $value);
				}
			}
			else {
				self::$headers[$key] = $value;
			}
		}
	}
	
	/*
	 *	Load a configuration array (or object). Only overwrites values specified.
	 */
	public static function loadConfig($new_config=array()) {
		if (is_array($new_config) || is_object($new_config)) {
			foreach ($new_config as $key=>$value) {
				if (is_array(self::arrayValue(self::$config,$key)) && is_array($value)) {
					self::$config[$key] = array_merge(self::$config[$key], $value);
				}
				else {
					self::$config[$key] = $value;
				}
			}
		}
	}
	
	/*
	 *	Return an encoded JSON string
	 */
	public static function json_encode($obj) {
		if (function_exists('json_encode')) {
			return json_encode($obj);
		}
		else {
			if (is_null($obj)) {
				return 'null';
			}
			if (is_bool($obj)) {
				return $obj===TRUE ? 'true' : 'false';
			}
			if (is_scalar($obj)) {
				if (is_float($obj)) {
					// Always use "." for floats.
					return floatval(str_replace(",", ".", strval($obj)));
				}
				if (is_string($obj)) {
					static $jsonReplaces = array(
						array("\\", "/", "\n", "\t", "\r", "\b", "\f", '"'),
						array('\\\\', '\\/', '\\n', '\\t', '\\r', '\\b', '\\f', '\"')
					);
					return '"' . str_replace($jsonReplaces[0], $jsonReplaces[1], $obj) . '"';
				}
				else {
					return $obj;
				}
			}
			$isList = true;
			for ($i=0, reset($obj); $i<count($obj); $i++, next($obj)) {
				if (key($obj)!==$i) {
					$isList = false;
					break;
				}
			}
			$result = array();
			if ($isList) {
				foreach ($obj as $v) {
					$result []= self::json_encode($v);
				}
				return '[' . join(',', $result) . ']';
			}
			else {
				foreach ($obj as $k=>$v) {
					$result []= self::json_encode($k) . ':' . self::json_encode($v);
				}
				return '{' . join(',', $result) . '}';
			}
		}
	}
	
	/*
	 *	Returns the first non-empty argument
	 */
	public static function oreo() {
		$args = func_get_args();
		for ($x=0; $x<count($args); $x++) {
			if (isset($args[$x]) && !(is_string($args[$x]) && strlen($args[$x])===0)) {
				return $args[$x];
			}
		}
		return $args[count($args)-1];
	}
	
	
	/*
	 *	Get the corresponding array value for a key if it exists, else NULL
	 */
	public static function arrayValue($array=NULL, $key=NULL, $strict=FALSE) {
		if (!isset($array) || !isset($key)) {
			return NULL;
		}
		if ($strict===TRUE) {
			if (is_array($array)) {
				if (array_key_exists($key, $array)) {
					return $array[$key];
				}
			}
			elseif (is_object($array)) {
				if (function_exists('property_exists')) {
					return property_exists($array, $key) ? $array->$key : NULL;
				}
				else {
					return array_key_exists($array, $key) ? $array->$key : NULL;
				}
			}
		}
		else {
			if (is_array($array) && isset($array[$key])) {
				return $array[$key];
			}
			elseif (is_object($array) && isset($array->$key)) {
				return $array->$key;
			}
		}
		return NULL;
	}
	
	
	private static function log($message='', $type='error') {
		error_log($message);
		self::$log_messages []= array(
			'message'	=> self::oreo($message, ''),
			'timestamp'	=> microtime(true),
			'type'		=> strtolower(self::oreo($type, 'error'))
		);
	}
	
	
	private static function parseUrl($url='') {
		return parse_url($url);
	}
	
	
	private static function getBasePath() {
		$origname = preg_replace('/(\/)[a-z0-9]+\.[a-z0-9]{1,9}$/sim','$1', substr($_SERVER['SCRIPT_FILENAME'], strlen($_SERVER['DOCUMENT_ROOT'])));
		return self::oreo(self::arrayValue(self::$config,'base_uri'), $origname);
	}
	
	
	/*
	 *	Remove the base API path from the beginning of a URL if it is present.
	 */
	private static function getRelativeUrl($url='') {
		$base = self::getBasePath();
		
		if (substr($base, -1, 1)!=='/') {
			$base .= '/';
		}
		
		for ($p=strlen($base); $p>0; $p--) {
			if (substr($url, 0, $p)===substr($base, 0, $p)) {
				$url = substr($url, $p);
				break;
			}
		}
		
		return $url;
	}
	
	
	private static function formatJsonpCallback($callback='') {
		$callback = preg_replace('/^((window|top|parent|self)\.)*/sm', '', $callback);
		return preg_replace('/[^a-z0-9_\.\$]/sim', '', $callback);
	}
}


if (!is_array($tinyapi_config) || API::arrayValue($tinyapi_config, 'autoinit')!==FALSE) {
	API::init();
}


?>