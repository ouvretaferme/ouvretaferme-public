<?php
Package::setList([
	'aggregate' => 'internal',
	'aid' => 'production',
	'api' => 'framework',
	'block' => 'base',
	'communication' => 'internal',
	'core' => 'framework',
	'dashboard' => 'base',
	'data' => 'internal',
	'design' => 'ouvretaferme',
	'dev' => 'framework',
	'editor' => 'framework',
	'entry' => 'base',
	'example' => 'framework',
	'gallery' => 'production',
	'game' => 'ouvretaferme',
	'hr' => 'base',
	'language' => 'framework',
	'learning' => 'ouvretaferme',
	'main' => 'ouvretaferme',
	'map' => 'production',
	'media' => 'base',
	'note' => 'base',
	'notification' => 'base',
	'planning' => 'production',
	'plant' => 'production',
	'product' => 'base',
	'queue' => 'framework',
	'scene' => 'base',
	'securing' => 'internal',
	'security' => 'internal',
	'sequence' => 'production',
	'series' => 'production',
	'session' => 'framework',
	'storage' => 'framework',
	'structure' => 'base',
	'toolbox' => 'production',
	'user' => 'base',
	'util' => 'framework',
	'website' => 'ouvretaferme',
	'work' => 'production',
]);

Package::setObservers([
	'lib' => [
		'user' => [
			'logIn' => ['learning', 'session'],
			'canUpdate' => ['main'],
			'sendVerifyEmail' => ['main'],
			'signUpCreate' => ['main'],
			'close' => ['main'],
			'logOut' => ['session'],
		],
		'lime' => [
			'loadConf' => ['media'],
		],
		'queue' => [
			'getConsumers' => ['notification'],
		],
		'dev' => [
			'integrity' => ['queue', 'structure'],
			'getStuck' => ['queue'],
			'getVersion' => ['structure'],
		],
		'api' => [
			'keyCreate' => ['securing'],
			'keyRevoke' => ['securing'],
			'applicationAuthorize' => ['securing'],
			'applicationRevoke' => ['securing'],
		],
		'planning' => [
			'getDue' => ['toolbox'],
			'getDueStructures' => ['toolbox'],
			'describeDue' => ['toolbox'],
		],
		'structure' => [
			'closeStructure' => ['website'],
		],
	],
	'ui' => [
		'api' => [
			'getAdminDevLinks' => ['aid'],
		],
		'dev' => [
			'apiLabels' => ['hr', 'learning', 'map', 'planning', 'plant', 'product', 'sequence', 'series', 'structure', 'toolbox', 'user', 'work'],
			'apiScopes' => ['learning'],
			'apiFields' => ['learning', 'map', 'plant', 'product', 'sequence', 'series', 'structure', 'toolbox', 'user', 'work'],
			'apiRoutes' => ['learning', 'planning', 'plant', 'product', 'series', 'work'],
		],
		'planning' => [
			'getDue' => ['toolbox'],
			'getDueTitle' => ['toolbox'],
		],
	],
]);

ApiRoute::autoload(
	function() {
		\ApiRoute::expose('hr\Absence');
		\ApiRoute::expose('hr\Contract');
		\ApiRoute::expose('hr\Presence');
		\ApiRoute::expose('hr\Teammate');
		\ApiRoute::expose('hr\WorkingTime');
		\ApiRoute::expose('learning\Block');
		\ApiRoute::expose('learning\Chapter');
		\ApiRoute::expose('learning\Course');
		\ApiRoute::expose('learning\DateEnrollment');
		\ApiRoute::expose('learning\Date');
		\ApiRoute::expose('learning\Faq');
		\ApiRoute::expose('learning\Lesson');
		\ApiRoute::expose('learning\Phase');
		\ApiRoute::expose('map\Bed');
		\ApiRoute::expose('map\Plot');
		\ApiRoute::expose('map\Zone');
		\ApiRoute::expose('planning\Reminder');
		\ApiRoute::expose('plant\Family');
		\ApiRoute::expose('plant\Forecast');
		\ApiRoute::expose('plant\Plant');
		\ApiRoute::expose('plant\Size');
		\ApiRoute::expose('plant\Tag');
		\ApiRoute::expose('plant\Variety');
		\ApiRoute::expose('product\Category');
		\ApiRoute::expose('product\Product');
		\ApiRoute::expose('product\Stock');
		\ApiRoute::expose('product\Unit');
		\ApiRoute::expose('sequence\Crop');
		\ApiRoute::expose('sequence\Flow');
		\ApiRoute::expose('sequence\Sequence');
		\ApiRoute::expose('sequence\Slice');
		\ApiRoute::expose('series\Comment');
		\ApiRoute::expose('series\Cultivation');
		\ApiRoute::expose('series\Harvest');
		\ApiRoute::expose('series\Place');
		\ApiRoute::expose('series\Series');
		\ApiRoute::expose('series\Slice');
		\ApiRoute::expose('series\Task');
		\ApiRoute::expose('series\Timesheet');
		\ApiRoute::expose('structure\Configuration');
		\ApiRoute::expose('structure\Invite');
		\ApiRoute::expose('structure\Member');
		\ApiRoute::expose('structure\Production');
		\ApiRoute::expose('structure\Role');
		\ApiRoute::expose('structure\Structure');
		\ApiRoute::expose('structure\System');
		\ApiRoute::expose('toolbox\EquipmentReading');
		\ApiRoute::expose('toolbox\Equipment');
		\ApiRoute::expose('toolbox\Gauge');
		\ApiRoute::expose('toolbox\MaintenanceDone');
		\ApiRoute::expose('toolbox\Maintenance');
		\ApiRoute::expose('toolbox\Rain');
		\ApiRoute::expose('toolbox\WaterMeter');
		\ApiRoute::expose('toolbox\WaterReading');
		\ApiRoute::expose('user\User');
		\ApiRoute::expose('work\Action');
		\ApiRoute::expose('work\Category');
		\ApiRoute::expose('work\Method');
		\ApiRoute::expose('work\PhytoProduct');
		\ApiRoute::expose('work\PhytoUsage');
		\ApiRoute::expose('work\Supplier');
		\ApiRoute::expose('work\Tool');
 }
);
?>
