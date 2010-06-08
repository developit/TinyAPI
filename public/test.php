<?php

/**
 *	Test TinyAPI
 */
if (!class_exists('Test')) {
	class Test {
		public static function index() {
			return array(
				"methods" => array(
					"subcall1" => array(
						"description" => "Test API subcall that uses API::exec() to fake URL-based method calls",
						"url" => "subcall1"
					),
				)
			);
		}
		
		/**	
		 *	Test API subcall that uses API::exec() to fake URL-based method calls
		 */
		public static function subcall1() {
			return array(
				"test_from_call" => "success",
				"call_before" => array(
					"_GET" => $_GET,
					"_POST" => $_POST,
					"_REQUEST" => $_REQUEST
				),
				"subcalls" => array(
					"unmodified" => API::exec("test/subcall1/testcall"),
					"with_get" => API::exec("test/subcall1/testcall", array("1_get_key_1"=>"1_get_value_1","1_get_key_2"=>"1_get_value_2")),
					"with_post" => API::exec("test/subcall1/testcall", NULL, array("2_post_key_1"=>"2_post_value_1","2_post_key_2"=>"2_post_value_2")),
					"with_both" => API::exec("test/subcall1/testcall", array("3_get_key_1"=>"3_get_value_1","3_get_key_2"=>"3_get_value_2"), array("3_post_key_1"=>"3_post_value_1","3_post_key_2"=>"3_post_value_2")),
				),
				"call_after" => array(
					"_GET" => $_GET,
					"_POST" => $_POST,
					"_REQUEST" => $_REQUEST
				)
			);
		}
		
		/**	
		 *	The method subcalled by subcall1()
		 */
		public static function subcall1_testcall() {
			return array(
				"test_from_subcall" => "success",
				"_GET" => $_GET,
				"_POST" => $_POST,
				"_REQUEST" => $_REQUEST
			);
		}
	}
}

return 'Test';