<?php
class Error {
	public function index() {
		return array(
			"did_we_error" => true,
			"source" => "/api/error.php -> index();"
		);
	}
	
	public function error() {
		return array(
			"did_we_error" => true,
			"source" => "/api/error.php -> error()"
		);
	}
}

return new Error();

?>