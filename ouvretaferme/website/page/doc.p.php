<?php
new Page()
	->get('/doc/communication:website', fn($data) => throw new ViewAction($data));
?>
