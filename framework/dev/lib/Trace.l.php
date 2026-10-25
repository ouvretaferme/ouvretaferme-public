<?php
namespace dev;

/**
 * Trace management
 */
class TraceLib {

	/**
	 * Display a trace for a HTTP client
	 *
	 * @param array|Collection $backtrace
	 * @param bool $hideVoid Hide lines with void call
	 * @return string
	 */
	public static function getHttp($backtrace, bool $hideVoid = FALSE): string {

		$text = '<ul class="dev-error-trace">';

		foreach($backtrace as $position => $entry) {

			$file = str_replace(LIME_DIRECTORY, '', $entry['file'] ?? '?');
			$line = $entry['line'] ?? '?';
			$object = self::getClassFunction($backtrace, $position);

			if($hideVoid and $object === '(void)') {
				continue;
			}

			$text .= '<li>'.$object.' in <b>'.$file.'</b> on line <b>'.$line.'</b></li>'."\n";

		}

		$text .= '</ul>';

		return $text;

	}

	/**
	 * Display a trace for a CLI client
	 *
	 * @param array|Collection $backtrace
	 * @param bool $hideVoid Hide lines with void call
	 * @return string
	 */
	public static function getCli($backtrace, bool $hideVoid = FALSE): string {

		$text = '';

		foreach($backtrace as $position => $entry) {

			$file = str_replace(LIME_DIRECTORY, '', $entry['file'] ?? '?');
			$line = $entry['line'] ?? '?';
			$object = self::getClassFunction($backtrace, $position + 1);

			if($hideVoid and $object === '(void)') {
				continue;
			}

			$text .= '   * '.$object.' in '.bold($file).' on line '.bold($line)."\n";

		}

		return $text;

	}

	protected static function getClassFunction($backtrace, int $position): string {

		if(
			isset($backtrace[$position]) and
			(isset($backtrace[$position]['class']) or isset($backtrace[$position]['function']))
		) {

			$content = '';

			if(isset($backtrace[$position]['class'])) {
				$content .= $backtrace[$position]['class'].'::';
			}
			if(isset($backtrace[$position]['function'])) {
				$content .= $backtrace[$position]['function'].'('.self::getArguments($backtrace[$position]).')';
			}

			return $content;

		} else {
			return '(void)';
		}

	}

	/**
	 * The arguments of a call, as `ErrorPhpLib::buildTrace()` described them.
	 *
	 * Only the `arguments` key is read, never the `args` of a raw `debug_backtrace()`: the SQL and
	 * cache panels pass one straight from PHP, and it carries the values themselves.
	 */
	protected static function getArguments($entry): string {

		$arguments = $entry['arguments'] ?? NULL;

		if($arguments === NULL) {
			return '';
		}

		return implode(', ', unserialize($arguments));

	}

}
?>
