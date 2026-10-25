<?php
new Page()
	->cli('index', function($data) {

		$rows = match(GET('scope', 'string', 'css')) {
			'css' => \dev\MotionLib::getDeclarations(),
			'script' => \dev\MotionLib::getScriptDeclarations(),
		};

		echo implode("\t", \dev\MotionLib::COLUMNS)."\n";

		foreach($rows as $row) {
			echo implode("\t", array_map(fn($value) => str_replace(["\t", "\n"], ' ', (string)$value), $row))."\n";
		}

		throw new VoidAction();

	});
?>
