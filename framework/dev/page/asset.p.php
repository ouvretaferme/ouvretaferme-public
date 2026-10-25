<?php

/**
 * Regenerate asset.c.php, the cache-busting key appended to every CSS, JS and image URL
 * in production (Asset::getVersion()). Its value is a crc32 of the content of every
 * static/js, static/css and static/image file, see PackageLib::getAssetVersion().
 *
 * The file is not rebuilt on each dev request anymore: dev never reads it (Route::loadConf()
 * only loads it outside dev), so every workstation was rewriting a file it did not use, with
 * a value of its own. This page is now its only writer, called by .github/workflows/asset.yml
 * when an asset changes on main.
 *
 * Usage: php framework/lime.php -a ouvretaferme -e dev dev/asset
 */
new Page()
	->cli('index', function($data) {

		$version = \dev\PackageLib::buildAsset();

		echo \Lime::getPath().'/asset.c.php written (version '.$version.').'."\n";

	});
?>
