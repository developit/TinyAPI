<?php

class SubExample {
	/*
	 *	This method is called for the following URL:
	 *		[api-root]/example/subExample/
	 */
	public static function index() {
		return array(
			"hello" => TRUE
		);
	}
	
	/*
	 *	This method is called for the following URL:
	 *		[api-root]/example/subExample/anotherSubExample/
	 */
	public static function anothersubexample() {
		return array(
			"hello" => TRUE
		);
	}
	
	/*
	 *	Underscores in mapped method names are mapped to "/".
	 *	This method could also have been placed at: ./anothersubexample.php::fourth()
	 */
	public static function anothersubexample_fourth() {
		return array(
			"hello" => TRUE
		);
	}
	
	/*
	 *	Methods declared as private are not mapped to a URL.
	 *	This is also true for methods named with two or more consecutive underscores.
	 *		(logical, since __construct and __destruct should not be mapped to URLs)
	 */
	private static function privateMethod() {
		// this method is private and not accessible outside of this class.
	}
}

return SubExample;

?>