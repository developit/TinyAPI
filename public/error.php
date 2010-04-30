<?php

class Error {
	public static function index() {
		return array(
			"result" => FALSE,
			"message" => "Routing error: method not found."
		);
	}
}

return Error;

?>