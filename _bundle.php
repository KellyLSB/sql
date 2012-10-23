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
		ini_set('memory_limit', '-1');
		return new Connection($slug);
	}

	public function _on_router_route($path) {
		include(e\site . '/tmp.html');
		$result = $this->__callBundle()
			->newList("members.account")
			->joins("RIGHT JOIN `cms.page` ON `cms.page`.`id` = `members.account`.`id`")
			//->where('`members.account`.`id` = ?', 1);
			->order('`members.account`.`id`', 'DESC')
			->limit(5,5);

		//dump($result);
		//dump($result->buildQuery());
		dump($result->all());
		foreach($result as $r) {
			var_dump($r);echo("<br /><Br />");
		}

		e\Complete();
	}

}