<?php

if (!class_exists('XMLFormatter')) {
	class XMLFormatter {
		public static $charset = 'utf-8';
		public static $root_node_name = 'response';
		
		public static function format($data) {
			if (class_exists('API') && is_callable(array('API','setHeader'))) {
				API::setHeader('Content-Type: application/xml; charset=' . self::$charset, true);
			}
			$type_hinting = !!preg_match('/(true|1|yes|on)/sim',strval($_REQUEST['typehinting']));
			$xml = "<?xml version=\"1.0\" encoding=\"".strtoupper(self::$charset)."\"?>";
			$xml .= self::node($data, self::$root_node_name, $type_hinting);
			return $xml;
		}
		
		private static function node(&$node=NULL, $name='unnamed', $type_hinting=FALSE, $level=0) {
			$xml = "\n";
			$name_singular = preg_replace('/s$/sim','',$name);
			if (is_string($node)) {
				// string literal
				$xml .= "<".$name.($type_hinting?(' type="string"'):'').">" . htmlspecialchars($node,ENT_QUOTES,self::$charset) . "</".$name.">";
			}
			elseif (is_numeric($node)) {
				// number
				$xml .= "<".$name.($type_hinting?(' type="number"'):'').">" . $node . "</".$name.">";
			}
			elseif (is_bool($node)) {
				// boolean
				$xml .= "<".$name.($type_hinting?(' type="boolean"'):'').">" . ($node===TRUE?"true":"false") . "</".$name.">";
			}
			elseif (is_null($node)) {
				// null
				$xml .= "<".$name.($type_hinting?(' type="null"'):'')." />";
			}
			elseif (is_array($node)) {
				$assoc = (is_array($node) && (0 !== count(array_diff_key($node, array_keys(array_keys($node)))) || count($node)==0));
				// self-closing empties
				if (empty($node)) {
					$xml .= "<".$name.($type_hinting?(' type="'.($assoc?'object':'array').'"'):'')." />";
				}
				else {
					if ($assoc) {
						// assoc treated same as object
						$xml .= "<".$name.($type_hinting?(' type="object"'):'').">";
						foreach ($node as $key=>$value) {
							$xml .= str_replace("\n", "\n\t", self::node($value, $key, $type_hinting, $level+1));
						}
						$xml .= "\n</".$name.">";
					}
					else {
						// arrays become a plural node with singular children
						$xml .= "<".$name_singular."s".($type_hinting?(' type="array"'):'').">";
						foreach ($node as $value) {
							$xml .= str_replace("\n", "\n\t", self::node($value, $name_singular, $type_hinting, $level+1));
						}
						$xml .= "\n</".$name_singular."s>";
					}
				}
			}
			elseif (is_object($node)) {
				// self-closing empties
				if (empty($node)) {
					$xml .= "<".$name.($type_hinting?(' type="object"'):'')." />";
				}
				else {
					// object becomes singular node with key-named children
					$xml .= "<".$name.($type_hinting?(' type="object"'):'').">";
					foreach ($node as $key=>$value) {
						//echo $key . ' :: ' . var_export($value,TRUE) . "\n\n";
						$xml .= "\t" . str_replace("\n", "\n\t", self::node($value, $key, $type_hinting, $level+1));
					}
					$xml .= "\n</".$name.">";
				}
			}
			return $xml;
		}
	}
}