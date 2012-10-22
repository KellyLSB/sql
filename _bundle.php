<?php

namespace Bundles\SQL;
use Exception;
use e;

/**
 * Evolution SQL Bundle
 * @author Kelly Becker
 * @since Oct 22nd, 2012
 */
class Bundle {

	public function __callBundle($slug = 'default') {
		error_reporting(E_ALL);
		ini_set('display_errors', '1');
		return new Connection($slug);
	}

	public function _on_router_route($path) {
		include(e\site . '/tmp.html');
		e\Complete();
	}

}