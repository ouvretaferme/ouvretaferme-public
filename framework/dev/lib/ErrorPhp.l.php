<?php
namespace dev;

/**
 * Error library
 */
class ErrorPhpLib {

	/**
	 * Replaces the value of a parameter holding a secret. Serialized like any other value, the
	 * administration screen passing them all to `unserialize()`.
	 */
	const HIDDEN_VALUE = '(hidden)';

	/**
	 * Names whose value authenticates its bearer or opens a route. `key` also catches `keyword`,
	 * which costs one unreadable parameter where missing an `apiKey` costs an account. `session`
	 * catches the field a form posted outside ajax carries from a tab with its own session.
	 */
	const SECRET_NAME = '/password|passwd|secret|token|key|hash|signature|credential|session/i';

	private static $count = 0;
	private static $list = [];

	private static $mode = LIME_ENV;

	/**
	 * Create an exception from a PHP error
	 * If set to TRUE, error is not saved in the database
	 *
	 * @var bool
	 */
	public static $createExceptionFromError = FALSE;

	/**
	 * Do nothing from a PHP error
	 *
	 * @var bool
	 */
	public static $doNothingFromError = FALSE;

	/**
	 * Save errors in the database ?
	 *
	 * @var bool
	 */
	public static $saveError = TRUE;

	/**
	 * Override user that triggered the error
	 *
	 * @var bool
	 */
	public static $overrideUser = NULL;

	/**
	 * Change error handler mode
	 * Default is LIME_ENV
	 *
	 */
	public static function setMode(string $mode) {
		self::$mode = $mode;
	}

	/**
	 * Specify if we have to create an exception from a PHP error
	 */
	public static function createExceptionFromError($status) {
		self::$createExceptionFromError = $status;
	}

	/**
	 * Specify if we have to do nothing from a PHP error
	 */
	public static function doNothingFromError($status) {
		self::$doNothingFromError = $status;
	}

	public static function saveError($status) {
		self::$saveError = $status;
	}

	/**
	 * Handle an error or an exception
	 * See handleException() and handleError() for more details
	 *
	 */
	public static function handle(...$arguments) {

		switch(func_num_args()) {

			// Exception
			case 1 :
				return ErrorPhpLib::handleException(...$arguments);

			// Error
			case 5 :
				return ErrorPhpLib::handleError(...$arguments);

		}

	}

	/**
	 * Display a backtrace as an error
	 */
	public static function handleFromBacktrace(array $backtrace, string $message = '') {

		return ErrorPhpLib::handleError(Error::UNEXPECTED, NULL, (string)$message, $backtrace[0]['file'], $backtrace[0]['line'], array_slice($backtrace, 1));

	}

	/**
	 * Handle an exception
	 */
	public static function handleException(\Throwable $e): void {

		if($e instanceof \IntegrityException) {
			self::handleIntegrity($e->getMessage(), $e->getData());
			return;
		}

		$eError = new Error([
			'message' => $e->getMessage(),
			'line' => $e->getLine(),
			'file' => $e->getFile(),
			'class' => get_class($e),
			'code' => $e->getCode(),
			'method' => \Route::getRequestMethod(),
			'type' => Error::EXCEPTION,
			'device' => \util\DeviceLib::get(),
			'referer' => mb_substr(SERVER('HTTP_REFERER'), 0, 255)
		]);
		self::buildMore($eError);

		$cErrorTrace = self::buildTrace($eError, $e->getTrace());
		$cErrorParameter = self::buildParameter($eError);

		if(self::$saveError) {
			self::handleProduction($eError, $cErrorTrace, $cErrorParameter);
		}

		if(self::$mode !== 'prod' or \Route::getRequestedWith() === 'cli') {
			self::handleDebug($eError, $cErrorTrace, $cErrorParameter);
		}

	}


	/**
	 * Handle an integrity error
	 */
	public static function handleIntegrity(string $message, array $data) {

		$eError = new Error([
			'message' => $message,
			'data' => $data,
			'type' => Error::INTEGRITY,
		]);

		self::buildMore($eError);

		if(self::$saveError) {
			self::handleProduction($eError, new \Collection(), new \Collection());
		}

		if(self::$mode !== 'prod' or \Route::getRequestedWith() === 'cli') {
			self::handleDebug($eError, new \Collection(), new \Collection());
		}

	}

	/**
	 * Handle a PHP error
	 */
	public static function handleError(string $type, ?int $code, string $message, ?string $file = NULL, ?string $line = NULL, ?array $trace = NULL, bool $deprecated = FALSE) {

		// Exclude errors with iconv(), getimagesize()
		if(
			strpos($message, 'iconv()') !== FALSE or
			strpos($message, 'getimagesize()') !== FALSE
		) {
			return;
		}

		if(self::$doNothingFromError) {
			return;
		}

		if(self::$createExceptionFromError) {
			throw new \Exception($message.' in '.$file.' on line '.$line);
		}

		$eError = new Error([
			'message' => $message,
			'line' => $line,
			'class' => NULL,
			'file' => $file === NULL ? NULL : substr($file, strlen(LIME_DIRECTORY)),
			'code' => self::getCode($code),
			'method' => \Route::getRequestMethod(),
			'type' => $type,
			'device' => \util\DeviceLib::get(),
			'referer' => SERVER('HTTP_REFERER'),
			'deprecated' => $deprecated
		]);

		if($deprecated) {
			$eError['status'] = Error::CLOSE;
		} else {
			$eError['status'] = Error::OPEN;
		}

		self::buildMore($eError);

		if($trace === NULL) {
			$trace = array_slice(debug_backtrace(FALSE, 50), 3);
		}

		$cErrorTrace = self::buildTrace($eError, $trace);
		$cErrorParameter = self::buildParameter($eError);

		if(self::$saveError) {
			self::handleProduction($eError, $cErrorTrace, $cErrorParameter);
		}

		if(
			(self::$mode !== 'prod' or \Route::getRequestedWith() === 'cli') and
			$code !== E_ERROR
		) {
			self::handleDebug($eError, $cErrorTrace, $cErrorParameter);
		}

	}

	/**
	 * Get an error code from a PHP error code
	 */
	public static function getCode(?int $phpCode = NULL): string {

		switch($phpCode) {

			case E_ERROR :
			case E_CORE_ERROR :
				return 'Fatal';

			case E_USER_WARNING :
			case E_CORE_WARNING :
			case E_WARNING :
				return 'Warning';

			case E_USER_NOTICE :
			case E_NOTICE :
			case E_DEPRECATED :
				return 'Notice';

			default :
				return 'Other error';

		}

	}

	/**
	 * Delete old errors
	 *
	 */
	public static function clean() {

		$eError = Error::model()
			->select(['id', 'createdAt'])
			->where('createdAt < NOW() - INTERVAL '.DevSetting::ERROR_KEEP.' DAY')
			->sort(['createdAt' => SORT_DESC])
			->get();

		if($eError->notEmpty()) {

			ErrorParameter::model()
				->where('error <= '.$eError['id'])
				->delete();

			ErrorTrace::model()
				->where('error <= '.$eError['id'])
				->delete();

			Error::model()
				->where('id <= '.$eError['id'])
				->delete();

		}

	}

	/**
	 * Returns number of errors found
	 *
	 * @return int;
	 */
	public static function countErrors(): int {
		return self::$count;
	}

	/**
	 * Returns a list of errors found
	 *
	 * @return int;
	 */
	public static function listErrors(): array {
		return self::$list;
	}

	private static function handleProduction(Error $eError, \Collection $cErrorTrace, \Collection $cErrorParameter) {

		if(self::$doNothingFromError) {
			return;
		}

		self::$count++;

		if(DevSetting::ERROR_SAVE_MAX !== NULL) {

			if(self::$count > DevSetting::ERROR_SAVE_MAX) {
				return;
			}

		}

		try {

			Error::model()->insert($eError);

			if($cErrorTrace->notEmpty()) {
				ErrorTrace::model()->insert($cErrorTrace);
			}

			if($cErrorParameter->notEmpty()) {
				ErrorParameter::model()->insert($cErrorParameter);
			}

		}
		catch(\Exception $e) {

			self::saveError(FALSE);
			self::handle($e);
			self::saveError(TRUE);

		}

	}

	private static function handleDebug(Error $eError, \Collection $cErrorTrace, \Collection $cErrorParameter) {
		self::$count++;

		self::debug($eError, $cErrorTrace);

	}

	/**
	 * Write error text
	 *
	 * En HTTP, l'écho part dans le corps de la réponse : une réponse ajax cesse d'être du JSON, et
	 * ajax.js n'affiche plus aucune erreur de formulaire, seulement le panneau de développement. handle()
	 * ne passe pas ici en production, donc un formulaire muet en dev ne dit rien de la production.
	 */
	protected static function debug(Error $eError, \Collection $cErrorTrace) {

		self::$list[] = [$eError, $cErrorTrace];

		if(\Route::getRequestedWith() === 'cli') {
			echo self::debugCli($eError, $cErrorTrace);
		} else {
			echo self::debugHttp($eError, $cErrorTrace);
		}

	}

	public static function debugCli(Error $eError, \Collection $cErrorTrace): string {

		switch($eError['type']) {

			case Error::PHP :
				$title = $eError['code'];
				break;
			case Error::EXCEPTION :
				$title = 'PHP EXCEPTION';
				break;
			case Error::UNEXPECTED :
				$title = 'UNEXPECTED ACTION';
				break;
			case Error::INTEGRITY :
				$title = 'INTEGRITY ERROR';
				break;
		}

		$message = $eError['message'] ?: 'No message';

		$size = 100 - strlen($title) - 2;

		$h = "+".str_repeat('-', floor($size / 2))." ".$title." ".str_repeat('-', ceil($size / 2))."+\n";

		$h .= "  ".bold($message)."\n";

		if($eError['file'] and $eError['line']) {
			$h .= " ".str_repeat('-', 100)."\n";
			$h .= "  File: ".bold($eError['file'])." on line ".bold($eError['line'])."\n";
		}

		if($cErrorTrace->notEmpty()) {

			$h .= " ".str_repeat('-', 100)."\n";
			$h .= "  Stack trace:\n";
			$h .= str_replace("\n", "\n", rtrim(TraceLib::getCli($cErrorTrace)))."\n";

		}

		if($eError['data']) {
			$h .= " ".str_repeat('-', 100)."\n";
			foreach(self::flattenData($eError['data']) as $key => $value) {
				$h .= "  ".$key.": ".bold($value)."\n";
			}
		}

		$h .= "+".str_repeat('-', 100)."+\n";

		return $h;

	}

	public static function debugHttp(Error $eError, \Collection $cErrorTrace): string {

		if(\Route::getRequestedWith() === 'http') {

			\Asset::css('dev', 'dev.css');
			// Une erreur peut précéder le gabarit, et Asset sert les scripts dans l'ordre de leur première inscription.
			\Asset::js('util', 'lime.js');
			\Asset::js('dev', 'dev.js');

		}

		$h = '<div class="dev-error-php">'."\n";
		$h .= '<h2>';
		switch($eError['type']) {
			case Error::PHP :
				$h .= $eError['code'];
				break;
			case Error::EXCEPTION :
				$h .= $eError['class'];
				break;
		}
		$h .= '</h2>'."\n";

		$message = $eError['message'] ?: '<i>No message</i>';

		$h .= '<p>'.encode($message).'</p>'."\n";

		if($eError['file'] or $eError['line']) {
			$h .= '<div class="dev-error-php-place">File: <b>'.$eError['file'].'</b> on line <b>'.$eError['line'].'</b></div>'."\n";
		}

		if($cErrorTrace->notEmpty()) {
			$h .= '<h5>Stack trace:</h5>'."\n";
			$h .= TraceLib::getHttp($cErrorTrace);
		}

		if($eError['data']) {
			$h .= '<h5>Data:</h5>'."\n";
			$h .= self::getData($eError['data']);
		}

		$h .= '</div>'."\n";

		return $h;

	}

	public static function getData(array $data): string {

		$text = '<ul class="dev-error-trace">';

			foreach(self::flattenData($data) as $key => $value) {

				$text .= '<li>'.encode($key).': <b>'.encode($value).'</b></li>'."\n";

			}

		$text .= '</ul>';

		return $text;

	}

	/**
	 * Les données à plat, une ligne par valeur, chaque clé portant son chemin.
	 *
	 * Le rendu précédent ne lisait qu'un niveau et imprimait « array » pour le reste, ce qui masquait
	 * entièrement les données d'un contrôle d'intégrité, dont chaque anomalie est un tableau.
	 */
	public static function flattenData(array $data, string $prefix = ''): array {

		$lines = [];

		foreach($data as $key => $value) {

			$path = ($prefix === '') ? (string)$key : $prefix.'.'.$key;

			if(is_array($value)) {

				if($value === []) {
					$lines[$path] = '[]';
				} else {
					$lines += self::flattenData($value, $path);
				}

			} else {
				$lines[$path] = self::formatDataValue($value);
			}

		}

		return $lines;

	}

	protected static function formatDataValue(mixed $value): string {

		if($value === NULL) {
			return 'NULL';
		} else if($value === TRUE) {
			return 'TRUE';
		} else if($value === FALSE) {
			return 'FALSE';
		} else if(is_scalar($value)) {
			return (string)$value;
		} else if($value instanceof \Element) {
			return get_class($value).' #'.($value['id'] ?? '?');
		} else if(is_object($value)) {
			return get_class($value);
		} else {
			return gettype($value);
		}

	}

	protected static function buildTrace(Error $eError, array $trace): \Collection {

		switch($eError['class']) {

			case 'ElementException' :
				$trace = array_slice($trace, 1);
				break;

		}

		$cErrorTrace = new \Collection();

		foreach($trace as $value) {

			$arguments = self::buildArgs((array)($value['args'] ?? []));

			$cErrorTrace[] = [
				'error' => $eError,
				'file' => $value['file'] ?? NULL,
				'line' => $value['line'] ?? NULL,
				'function' => $value['function'] ?? NULL,
				'class' => $value['class'] ?? NULL,
				'arguments' => $arguments ? serialize($arguments) : NULL,
			];

		}

		return $cErrorTrace;

	}

	protected static function buildArgs(array $arguments): array {

		return array_map(self::describeArgument(...), $arguments);

	}

	/**
	 * What an argument was, never what it held.
	 *
	 * A trace is kept thirty days (`DevSetting::ERROR_KEEP`), read in clear in the administration
	 * screen and carried by every backup: a password or a token passed as an argument would sit
	 * there. Its type and its size answer what a diagnosis asks (which argument was null, empty, or
	 * a collection of zero) and cannot leak anything.
	 */
	private static function describeArgument(mixed $argument): string {

		if(is_object($argument)) {
			return get_class($argument);
		}

		if(is_array($argument)) {
			return 'array('.count($argument).')';
		}

		if(is_string($argument)) {
			return 'string('.strlen($argument).')';
		}

		return get_debug_type($argument);

	}

	protected static function buildParameter(Error $eError): \Collection {

		$cErrorParameter = new \Collection();

		foreach(['get' => $_GET, 'post' => $_POST, 'cookie' => $_COOKIE] as $type => $array) {

			foreach($array as $key => $value) {

				$cErrorParameter[] = [
					'error' => $eError,
					'type' => $type,
					'name' => $key,
					'value' => serialize(self::hideSecret($type, $key, $value)),
				];

			}

		}

		return $cErrorParameter;

	}

	/**
	 * Replaces the value of a parameter that authenticates its bearer.
	 *
	 * Every cookie is hidden rather than a list of names: a diagnosis needs to know that a cookie
	 * was there, never what it held, and an authentication cookie added later is covered in advance.
	 * The value is replaced and not dropped, an absent parameter and an emptied one not telling the
	 * reader the same thing.
	 */
	public static function hideSecret(string $type, string $name, mixed $value): mixed {

		if($type === 'cookie' or preg_match(self::SECRET_NAME, $name) === 1) {
			return self::HIDDEN_VALUE;
		}

		return $value;

	}

	/**
	 * Replaces the value of each secret argument of the URLs a text carries.
	 *
	 * A message quotes the URL that failed, signature included (`pdf.js` reports the page it could
	 * not load), and the request is a URL itself: hiding the GET parameters is not enough while
	 * their values are still written in clear in these two columns.
	 */
	public static function hideSecretInUrls(string $text): string {

		return preg_replace_callback('/([?&])([^=&?\s"\'<>]+)=([^&\s"\'<>#]*)/', function(array $match) {

			if(preg_match(self::SECRET_NAME, $match[2]) === 1) {
				return $match[1].$match[2].'='.self::HIDDEN_VALUE;
			}

			return $match[0];

		}, $text);

	}

	/**
	 * Override user
	 */
	public static function overrideUser(\user\User $eUser) {
		self::$overrideUser = $eUser;
	}

	/**
	 * Builds additional information for the devError
	 */
	protected static function buildMore(Error $eError) {

		// Get server name
		$eError['server'] = php_uname('n');

		// Get browser
		$eError['browser'] = \util\HttpLib::getBrowserString();

		// Get request
		$eError['request'] = SERVER('HTTP_HOST').LIME_REQUEST;

		// Get app
		$eError['app'] = last(\Lime::getApps());

		// Get version
		$eError['tag'] = exec('git rev-parse --abbrev-ref HEAD');
		$eError['modeVersion'] = SERVER('HTTP_X_APP_VERSION', '?string', NULL);

		// Get module information
		$position = strpos($eError['message'], "|{");

		if($position !== FALSE) {
			$eError->add((array)json_decode(substr($eError['message'], $position+1, strlen($eError['message'])), TRUE));
			$eError['message'] = substr($eError['message'], 0, $position);
		}

		$eError['message'] = self::hideSecretInUrls($eError['message']);
		$eError['request'] = self::hideSecretInUrls($eError['request']);

		if(self::$overrideUser !== NULL) {
			$eError['user'] = self::$overrideUser;
		} else {

			$eUser = \user\ConnectionLib::getOnline();

			if($eUser->notEmpty()) {
				$eError['user'] = $eUser;
			}

		}

		$eError['data'] ??= [];

	}

	/**
	 * Display a 404 error
	 */
	public static function notFound(): string {

		$file = \Page::getOriginalPath();
		$target = first(\Page::getLastPages())[1];

		switch(\Route::getRequestedWith()) {

			case 'cli' :
				$methods = ['cli', 'cron', 'get', 'http'];
				break;

			default :
				$methods = [strtolower(\Route::getRequestMethod()), 'http'];
				break;

		}

		if(\Route::getRequestedWith() === 'cli') {
			return self::notFoundCli($file, $target, $methods);
		} else {
			return self::notFoundHttp($file, $target, $methods);
		}

	}

	protected static function notFoundHttp(string $file, string $target, array $methods): string {

		if(LIME_ENV !== 'dev') {
			return '';
		}

		$exists = is_file($file);

		$h = '';

		$h .= '<div class="dev-error-php">';
		$h .= '<h2>404 Not Found</h2>';
		$h .= '<ul>
			<li>Access: <b>'.\Route::getRequestedWith().'</b></li>
			<li>Request: <b>'.LIME_REQUEST.'</b></li>
			<li>Request method: <b>'.\Route::getRequestMethod().'</b></li>
		</ul>';

		$h .= '<h4>File tested:</h4>';

		$label = $exists ? '<span class="label label-success"><b>found</b></span>' : '<span class="label label-danger"><b>not found</b></span>';

		$h .= '<ul>
			<li>'.$label.' <b>'.$file.'</b></li>
		</ul>';

		if($exists) {

			$h .= '<h4>Compatible declarations for HTTP:</h4>';
			$h .= '<ul>';

			foreach($methods as $method) {

				if($target === 'index') {
					$h .= '<li><span class="label label-danger"><b>not found</b></span> Page::<b>'.$method.'</b>(<i>callback</i>)</li>';
				} else {
					$h .= '<li><span class="label label-danger"><b>not found</b></span> Page::<b>'.$method.'</b>(\'<b>'.$target.'</b>\', <i>callback</i>)</li>';
				}

			}

			$h .= '</ul>';

		}

		$h .= '</div>';

		return $h;

	}

	protected static function notFoundCli(string $file, string $target, array $methods): string {

		$title = '404 Not Found';
		$exists = is_file($file);

		$size = 100 - strlen($title) - 2;

		$h = "+".str_repeat('-', floor($size / 2))." ".$title." ".str_repeat('-', ceil($size / 2))."+\n";

		$h .= "  Access: ".bold(\Route::getRequestedWith())."\n";
		$h .= "  Request: ".bold(LIME_REQUEST)."\n";
		$h .= "  Request method: ".bold(\Route::getRequestMethod())."\n";

		$h .= " ".str_repeat('-', 100)."\n";

		$h .= "  ";
		$h .= bold($exists ? '[found]' : '[not found]');
		$h .= " ".$file."\n";


		if($exists) {

			$h .= " ".str_repeat('-', 100)."\n";

			$h .= "  Compatible declarations for CLI:\n";

			foreach($methods as $method) {

				if($target === 'index') {
					$h .= '   * '.bold('[not found]').' Page::'.$method.'([callback])'."\n";
				} else {
					$h .= '   * '.bold('[not found]').' Page::'.$method.'(\''.$target.'\', [callback])'."\n";
				}

			}

		}

		$h .= "+".str_repeat('-', 100)."+\n";

		return $h;

	}

}
?>
