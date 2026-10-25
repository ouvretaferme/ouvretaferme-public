<?php
namespace api;

class RateLimit extends RateLimitElement {

	public function canRead(): bool {
		return TRUE;
	}

}
?>