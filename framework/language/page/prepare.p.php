<?php
/**
 * Run prepare
 *
 * @return Action
 */
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

		// Create a new instance of the prepare package
		$libPrepare = new language\PrepareLib($data->package);

		// First actions (integrity, moves...)
		$check = $libPrepare->prePrepare();

		if($check === FALSE) {
			throw new JsonAction(['errors' => $libPrepare->getErrors()]);
		}

		$libPrepare->prepare();

		throw new JsonAction([
			 'errors' => $libPrepare->getErrors(),
			 'stats' => $libPrepare->getStats()]
		);

	});
?>
