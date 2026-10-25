<?php

(new Page(function($data) {

	$app = GET('app', 'string', LIME_APP);
	$package = GET('package');

	$package = new ReflectionPackage($package, $app);

	// Ce refus sort en code 2, comme ceux de lime.php : un code 0 le ferait passer pour réussi auprès
	// de qui ne lit que le code de sortie.
	if($package->exists() === FALSE) {
		echo "Error: Package '".$package->getPackageName()."' does not exist.\n";
		exit(2);
	}

	$data->package = $package;

}))
	->cli('index', function($data) {

		$stats = [];

		foreach($data->package->getLangs() as $lang) {

			list($nFiles, $nMessages, $nWords) = language\StatsLib::count($data->package, $lang);

			$stats[$lang] = [
				'files' => $nFiles,
				'messages' => $nMessages,
				'words' => $nWords
			];

		}

		throw new JsonAction($stats);

	});
?>
