<?php
namespace dev;

/**
 * Package management
 */
class PackageLib {

	public function __construct(string $request) {

		if(strpos($request, 'dev/') === 0) {
			throw new \Exception('Not compatible with request \''.$request.'\'');
		}

	}

	/**
	 * Build package.c.php for the current package
	 *
	 */
	public function buildIndex() {

		$content = [];
		$content[] = '<?php';

		$list = $this->getList();

		$content[] = 'Package::setList([';

		foreach($list as $package => $app) {
			$content[] = '	\''.$package.'\' => \''.$app.'\',';
		}

		$content[] = ']);';
		$content[] = '';

		$observers = $this->getObservers($list);

		$content[] = 'Package::setObservers([';

		foreach($observers as $type => $observersByType) {

			$content[] = '	\''.$type.'\' => [';

			foreach($observersByType as $packageEvent => $methods) {

				$content[] = '		\''.$packageEvent.'\' => [';

				foreach($methods as $method => $packagesObserver) {

					$line = [];
					foreach($packagesObserver as $packageObserver) {
						$line[] = '\''.$packageObserver.'\'';
					}

					$content[] = '			\''.$method.'\' => ['.implode(', ', $line).'],';

				}

				$content[] = '		],';

			}

			$content[] = '	],';

		}

		$content[] = ']);';
		$content[] = '';

		$apis = $this->getApis($list);

		$content[] = 'ApiRoute::autoload(';
			$content[] = '	function() {';

			foreach($apis as $api) {
				$content[] = '		\ApiRoute::expose(\''.$api.'\');';
			}

			$content[] = ' }';
		$content[] = ');';

		$content[] = '?>';

		$file = \Lime::getPath().'/package.c.php';

		// Le saut de ligne final est celui que le fichier garde quand on y écrit à la main : sans lui, chaque
		// régénération le retire et la suivante écrite à la main le remet, dans le diff de lots sans rapport.
		$newContent = implode("\n", $content)."\n";
		$currentContent = file_get_contents($file);

		if($newContent !== $currentContent) {

			file_put_contents($file, $newContent);

			$this->dispatch();

		}

	}

	/**
	 * Get a list of packages
	 *
	 * @return array
	 */
	protected function getList(): array {

		$apps = \Lime::getApps();
		$list = ['main' => LIME_APP];

		foreach($apps as $app) {

			$packages = new \ReflectionApp($app)->getPackages();

			foreach($packages as $package) {

				$packageName = $package->getPackageName();

				if($packageName !== 'main') {

					$pathPage = \Lime::getPath().'/main/page/'.$packageName;

					if(is_dir($pathPage)) {
						throw new \Exception('Conflit between package '.$packageName.' and page directory '.$pathPage);
					}

					$pathView = \Lime::getPath().'/main/view/'.$packageName;

					if(is_dir($pathView)) {
						throw new \Exception('Conflit between package '.$packageName.' and view directory '.$pathView);
					}

					$list[$packageName] = $app;

				}

			}

		}

		// Register new package list
		\Package::setList($list);
		ksort($list);

		return $list;

	}

	/**
	 * Returns an array with all observers found
	 *
	 * @param array $list List of packages
	 * @return array
	 */
	protected function getObservers(array $list): array {

		$observers = [
			'lib' => [],
			'ui' => []
		];

		foreach($list as $package => $app) {

			foreach(['lib', 'ui'] as $type) {

				$paths = glob(\Package::getPath($package).'/'.$type.'/*.o.php');

				$basenames = array_map(function($path) {
					$basename = basename($path);
					return strstr($basename, '.', TRUE);
				}, $paths);

				foreach($basenames as $basename) {

					$eventPackage = lcfirst($basename);

					try {

						$reflection = new \ReflectionClass('\\'.$package.'\\'.$basename.'Observer'.ucfirst($type));

						$eventMethods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

						foreach($eventMethods as $eventMethod) {

							$eventMethodName = $eventMethod->getName();

							if(strlen($eventMethodName) > 1 and $eventMethodName !== 'ui') {
								$observers[$type][$eventPackage][$eventMethodName][] = $package;
							}

						}

					}
					catch(\Exception $e) {
					}

				}

			}

		}

		return $observers;

	}

	/**
	 * Returns an array with all API found
	 *
	 * @param array $list List of packages
	 * @return array
	 */
	protected function getApis(array $list): array {

		$apis = [];

		foreach($list as $package => $app) {

			$paths = glob(\Package::getPath($package).'/api/*.a.php');

			$apis = array_merge($apis, array_map(function($path) use ($package) {
				return $package.'\\'.strstr(basename($path), '_', TRUE);
			}, $paths));

		}

		return $apis;

	}

	/**
	 * Build route.c.php for the current package
	 *
	 */
	public function buildRoute() {

		$content = [];
		$content[] = '<?php';

		$routes = $this->getRoutes();

		$content[] = 'Route::register([';

		foreach($routes as $method => $pages) {

			$content[] = '	\''.$method.'\' => [';

			foreach($pages as $name => $info) {

				$chunk = explode('/', trim($name, '/'));
				$lastKey = count($chunk) - 1;

				if($chunk[$lastKey] === '') {
					$chunk[$lastKey] = '/';
				}

				$content[] = '		\''.$name.'\' => [';
				$content[] = '			\'request\' => \''.$info['package'].'/'.$info['file'].'\',';
				$content[] = '			\'priority\' => '.$info['priority'].',';
				$content[] = '			\'route\' => [\''.implode('\', \'', $chunk).'\'],';
				$content[] = '		],';

			}

			$content[] = '	],';

		}
		$content[] = ']);';
		$content[] = '?>';

		$file = \Lime::getPath().'/route.c.php';

		$newContent = implode("\n", $content);
		$currentContent = file_get_contents($file);

		if($newContent !== $currentContent) {

			file_put_contents($file, $newContent);

			$this->dispatch();

		}

	}

	/**
	 * Returns an array with all routes found
	 *
	 * @return array
	 */
	protected function getRoutes(): array {

		foreach(\Package::getList() as $package => $app) {

			$reflection = new \ReflectionPackage($package, $app);

			$reflection->browse('page/', '.p.php', function($path) {
				\Page::includeFile($path);
			});

		}

		$routes = [
			'GET' => [],
			'POST' => [],
			'DELETE' => [],
			'PUT' => [],
			'HEAD' => []
		];

		foreach(\Page::all() as $path => $pages) {

			foreach($pages as $page) {

				$name = $page['name'];

				if($name === '') {
					trigger_error('Empty page name in \''.$path.'\'', E_USER_WARNING);
					continue;
				}

				if($name[0] === '/') {

					foreach($page['request'] as $request) {
						if(isset($routes[$request][$name]) === FALSE or $routes[$request][$name]['priority'] > $page['priority']) {
							$routes[$request][$name] = [
								'package' => $page['package'],
								'priority' => $page['priority'],
								'file' => \Package::getFileFromPath($path),
							];
							ksort($routes[$request]);
						}
					}

				}

			}

		}

		ksort($routes);
		return $routes;

	}

	/**
	 * Builds asset.c.php and returns the version it holds
	 */
	public static function buildAsset(): string {

		$version = self::getAssetVersion();

		$content = [];
		$content[] = '<?php';
		$content[] = 'Asset::setVersion(\''.$version.'\');';
		$content[] = '?>';

		$file = \Lime::getPath().'/asset.c.php';

		$newContent = implode("\n", $content);
		$currentContent = is_file($file) ? file_get_contents($file) : NULL;

		if($newContent !== $currentContent) {

			file_put_contents($file, $newContent);

		}

		return $version;

	}

	/**
	 * Calcule un checksum global du contenu de tous les fichiers JS / CSS / image
	 *
	 * @return string
	 */
	protected static function getAssetVersion(): string {

		$checksums = [];

		foreach(\Lime::getApps() as $app) {

			$packages = new \ReflectionApp($app)->getPackages();

			foreach($packages as $package) {

				$package->browse(['static/js', 'static/css', 'static/image'], [NULL, NULL, NULL], function($file) use(&$checksums) {

					$path = $file->getPathname();

					if(
						$file->isFile() and
						str_starts_with($file->getFilename(), '.') === FALSE and
						strpos($path, '/.min/') === FALSE
					) {
						$checksums[$path] = crc32(file_get_contents($path));
					}

				});

			}

		}

		ksort($checksums);

		return dechex(crc32(implode('', $checksums)));

	}

	private function dispatch() {

		$dispatch = \Lime::getSiblings();

		if($dispatch) {

			foreach($dispatch as $host) {

				file_get_contents('http://'.$host.'/');

			}

		}

	}


}
?>
