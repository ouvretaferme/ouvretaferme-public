<?php
namespace dev;

class DevSetting extends \Settings {

	public static bool $minify = FALSE;
	const MINIFY_DIRECTORY = LIME_DIRECTORY.'/.min';

	// Path to PHP
	const PHP = 'php';
	// Lifetime in seconds of permanent crons
	// May be 300/600/900/1200/1800/3600/7200/10800/14400/21600/28800/43200/86400
	const CRON_PERMANENT_LIFETIME = 3600;
	const CRON_SAVE_DIRECTORY = '/conf/crontab';

	// Number of errors saved by page
	const ERROR_SAVE_MAX = 10;
	const ERROR_KEEP = 30;

	const QUERY_SLOW_MS = 50;
	const QUERY_SAVE_MAX = 20;
	const QUERY_KEEP = 14;

	// Compile version number for CSS / JS files
	public static bool $featureCompileCodeVersion = TRUE;
	// Compile Image version number
	public static bool $featureCompileImageVersion = TRUE;
}

?>
