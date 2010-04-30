<?php

// Tiny API Framework
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
		$headers		= array(			// HTTP headers, lower-cased
			'content-type'	=> 'text/plain'
		),
		$default_format	= 'json',			// why of course
		$basepath		= '',				// path to this file
		$log_messages	= array();			// stores log message
	
	function __construct($args=NULL) {
		self::init();
	}
	
	
	public static function init() {
		global $tinyapi_config;
		
		self::$config['directory_separator'] = self::oreo(DIRECTORY_SEPARATOR, '/');
		
		self::$basepath = self::getBasePath();
		
		if (isset($tinyapi_config)) {
			self::loadConfig($tinyapi_config);
		}
		
		$response = self::exec(self::getRelativeUrl($_SERVER['REQUEST_URI']));
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
	 *	Execute the method matching a URL or method name.
	 */
	public static function exec($method='') {
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
		
		$method_dir = substr($method, 0, strrpos($method, '/'));
		$method_file = basename($method);
		
		
		$dir = self::$config['method_dir'];
		$pre = self::$config['method_file_pre'];
		$ext = self::$config['method_file_ext'];
		
		if (substr($dir, -1, 1)!=='/') {
			$dir .= '/';
		}
		if (substr($dir, 0, 1)==='/') {
			$dir = substr($dir, 1);
		}
		
		$method_dirs = explode('/', $method);
		
		$method_name = 'index';
		
		while (strlen($method_dir)>0 && !is_file($dir.$method_dir.'/'.$pre.$method_file.$ext)) {
			if ($method_name==='index') {
				$method_name = $method_file;
			}
			else {
				$method_name .= '_'.$method_file;
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
		
		if (is_file($filename)) {
			
			// pull in the code
			$code = file_get_contents($filename);
			
			// dynamically execute it, removing PHP start/end tags (they would cause syntax errors)
			$class = eval(preg_replace("/^\s*?(<\?(php)?)?(.+)(\?>)?$/sim", "$3", $code));
			
			$is_class = is_string($class);
			
			// check if the method is available statically
			if ($is_class && method_exists($class, $method_name) && is_callable(array($class, $method_name))) {
				return self::oreo(call_user_func(array($class, $method_name)), array());
			}
			// else, instance the class
			elseif ($is_class) {
				$class = new $class();
			}
			
			if ($class) {
				// check if the requested method exists
				if (method_exists($class, $method_name)) {
					return self::oreo($class->$method_name(), array());
				}
				// check if an error method exists
				else if (method_exists($class, 'error')) {
					return self::oreo($class->error(), array());
				}
			}
		
		}
		
		return FALSE;
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
			$user_format = self::oreo(self::$config['format'], $_REQUEST['format']);
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
		if (is_array(self::$config['formatters'])) {
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
						$body = json_encode((object) self::$output);
					}
					else {
						$body = json_encode(self::$output);
					}
					
					$callback = self::oreo($_GET['callback'], $_GET['jsonp_callback']);
					
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
						if (strval($_GET['jsonp_string'])==='true' || strval($_GET['jsonp_string'])==='1') {
							$body = self::formatJsonpCallback($callback) . '("' . addslashes($body) . '"' . $idt . ');';
						}
						// jsonp + try..catch
						// FORMAT:  callback( (Object) json , [Error parseError] , [String id] )
						else if (strval($_GET['jsonp_try'])==='true' || strval($_GET['jsonp_try'])==='1') {
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
			}
		}
		
		
		// automatically set Content-Length if not already set
		if (empty(self::$headers['content-length'])) {
			self::$headers['content-length'] = strlen($body);
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
					// for custom statu code/description pairs
					if ($skip_status===FALSE) {
						header($h_value);
					}
				}
				else {
					if (is_array($h_value)) {
						foreach ($h_value as $v) {
							header( ucwords($h_key) . ': ' . preg_replace('/\r\n(\t)?/sm', '\r\n\t', $h_value), TRUE, $status_code );
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
				if (is_array(self::$config[$key]) && is_array($value)) {
					array_merge(self::$config[$key], $value);
				}
				else {
					self::$config[$key] = $value;
				}
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
		return self::oreo(self::$config['base_uri'], $origname);
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


if (!is_array($tinyapi_config) || $tinyapi_config["autoinit"]!==FALSE) {
	API::init();
}

?>