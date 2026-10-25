<?php
namespace dev;


/**
 * Redis test
 */
class CacheRedisTest extends CacheTest {

	public function init() {
		$this->cache = \Cache::redis();
		$this->cache->flush();
	}

	public function testAppend() {

	}

	public function testPrepend() {

	}

}

?>
