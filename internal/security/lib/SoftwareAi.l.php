<?php
namespace security;

/**
 * Le registre des logiciels dont OTF dépend, et la lecture de la version de chacun.
 *
 * La version se lit au moment de la veille, sur la machine qui la fait tourner : en production,
 * c'est la version servie, que le dépôt ne connaît pas (le serveur s'installe à la main, le dépôt
 * ne fige que l'image de développement). Les bibliothèques versionnées dans le dépôt se lisent
 * dans leur fichier de verrou ou dans le nom du fichier servi.
 *
 * `SoftwareSentinelAi` refuse un logiciel que le code appelle sans entrée ici, et une entrée que
 * plus rien n'appelle : un binaire lancé par `exec()`, un fichier de verrou, une bibliothèque
 * servie au navigateur.
 */
class SoftwareAiLib {

	/**
	 * Le logiciel reçoit une entrée qu'un inconnu contrôle (une requête, un fichier déposé, un
	 * document rendu) : une faille y est atteignable depuis l'extérieur.
	 */
	const EXPOSED = 'exposed';

	/**
	 * Le logiciel ne traite que ce que OTF lui donne : une faille y compte un cran de moins.
	 */
	const INTERNAL = 'internal';

	/**
	 * Les logiciels dont la version se lit à part, avec leur produit sur endoflife.date (`eol`,
	 * fin de support et dernière version), et leur source de versions de sécurité (`security`).
	 * Une fin de support ne se suit que pour un logiciel installé hors du système : quand le paquet
	 * source `package` est installé, la distribution le corrige jusqu'à sa propre fin de support, et
	 * il se suit avec les autres paquets du système.
	 */
	const SOFTWARES = [
		// Le produit est la distribution elle-même, lue dans /etc/os-release (`ubuntu` en production).
		'system' => ['name' => 'Système', 'exposure' => self::EXPOSED, 'eol' => NULL],
		'php' => ['name' => 'PHP', 'exposure' => self::EXPOSED, 'eol' => 'php', 'security' => 'php'],
		'mysql' => ['name' => 'MySQL', 'exposure' => self::INTERNAL, 'eol' => 'mysql', 'package' => 'mysql-8.0'],
		'redis' => ['name' => 'Redis', 'exposure' => self::INTERNAL, 'eol' => 'redis', 'package' => 'redis'],
		// Convertit les modèles 3D que les utilisateurs téléversent (base/scene/upload/upload.mjs).
		'node' => ['name' => 'Node.js', 'exposure' => self::EXPOSED, 'eol' => 'nodejs', 'security' => 'node'],
		// Rend en PDF des pages qui portent ce que les utilisateurs ont saisi.
		'chrome' => ['name' => 'Chrome', 'exposure' => self::EXPOSED, 'eol' => 'chrome'],
	];

	/**
	 * Les paquets du système dont une faille est atteignable depuis l'extérieur : le serveur web,
	 * PHP, et ce qui lit un fichier déposé ou une réponse distante. Les autres paquets comptent un
	 * cran de moins. Nom du paquet source, préfixe compris pour une famille (`php8.4`).
	 */
	const EXPOSED_PACKAGES = [
		'nginx', 'openssl', 'curl', 'libxml2', 'libxslt', 'zlib', 'openssh',
		'ghostscript', 'imagemagick', 'librsvg', 'libjpeg-turbo', 'libpng1.6', 'libwebp', 'tiff', 'freetype', 'libheif', 'libde265', 'poppler',
		'php',
	];

	/**
	 * Chaque binaire que le code lance, et le logiciel qui le porte. Le paquet du système se suit
	 * avec tous les autres paquets installés ; seuls `node` et `google-chrome` viennent d'ailleurs.
	 * NULL dit pourquoi un binaire ne se suit pas.
	 */
	const BINARIES = [
		'gs' => 'paquet ghostscript',
		'rsvg-convert' => 'paquet librsvg',
		'goaccess' => 'paquet goaccess',
		'certbot' => 'paquet certbot',
		'mysql' => 'paquet mysql',
		'mysqldump' => 'paquet mysql',
		'node' => 'node',
		'google-chrome' => 'chrome',
		'php' => 'php',
		'git' => 'paquet git',
		'tar' => 'paquet tar',
		'file' => 'paquet file',
		'grep' => 'paquet grep',
		'find' => 'paquet findutils',
		'xargs' => 'paquet findutils',
		'cp' => 'paquet coreutils',
		'rm' => 'paquet coreutils',
		'wc' => 'paquet coreutils',
		'cut' => 'paquet coreutils',
		'timeout' => 'paquet coreutils',
		'env' => 'paquet coreutils',
		// La veille elle-même, qui lit les paquets installés et ceux qui attendent une mise à jour.
		'dpkg-query' => 'paquet dpkg',
		'apt-get' => 'paquet apt',
		'bash' => 'paquet bash',
		'sudo' => 'paquet sudo',
		'service' => 'paquet init-system-helpers',
		'resolvectl' => 'paquet systemd',
		'scp' => 'paquet openssh',
		'xgettext' => 'paquet gettext',
		'msgfmt' => 'paquet gettext',
		// Binaire compilé et versionné dans le dépôt, sans version lisible ni source de failles :
		// il ne reçoit que des images déjà décodées par Imagick.
		'jpeg-recompress' => NULL,
	];

	/**
	 * Les fichiers de verrou, et l'exposition de leurs bibliothèques. Chaque paquet verrouillé se
	 * suit, dépendances comprises.
	 */
	const LOCKS = [
		// Le serveur OAuth et les jetons de l'API publique.
		'framework/api/composer.lock' => ['ecosystem' => 'Packagist', 'exposure' => self::EXPOSED],
		'framework/util/composer.lock' => ['ecosystem' => 'Packagist', 'exposure' => self::INTERNAL],
		'ouvretaferme/main/nodejs/package-lock.json' => ['ecosystem' => 'npm', 'exposure' => self::INTERNAL],
	];

	/**
	 * Les bibliothèques servies au navigateur, leur paquet npm, et le motif qui lit leur version
	 * dans le fichier qui les sert, ou la raison pour laquelle un fichier ne se suit pas à part.
	 * Elles s'exécutent chez l'utilisateur : exposées.
	 */
	const ASSETS = [
		'base/scene/static/lib/three-0.170.0.module.min.js' => ['package' => 'three', 'pattern' => '/three-([0-9.]+)\.module/'],
		'base/scene/static/lib/three-0.170.0.BufferGeometryUtils.js' => 'module de three, publié et corrigé avec lui',
		'base/scene/static/lib/three-0.170.0.CSS2DRenderer.js' => 'module de three, publié et corrigé avec lui',
		'base/scene/static/lib/three-0.170.0.MapControls.js' => 'module de three, publié et corrigé avec lui',
		'base/scene/static/lib/three-0.170.0.OrbitControls.js' => 'module de three, publié et corrigé avec lui',
		'base/scene/static/lib/three-0.170.0.LICENSE' => 'licence, sans code',
		'framework/util/static/lib/Sortable-1.11.0.js' => ['package' => 'sortablejs', 'pattern' => '/Sortable-([0-9.]+)\.js/'],
		'framework/util/static/lib/bootstrap-icons-1.13.1.svg' => 'icônes en SVG, sans script',
		'ouvretaferme/main/static/lib/chart-4.5.1.umd.min.js' => ['package' => 'chart.js', 'pattern' => '/chart-([0-9.]+)\.umd/'],
		'production/map/static/lib/turf-7.2.0.min.js' => ['package' => '@turf/turf', 'pattern' => '/turf-([0-9.]+)\.min/'],
	];

	/**
	 * Les bibliothèques chargées depuis un CDN, dont la version se lit dans le fichier qui les
	 * appelle. La liste des adresses tierces est tenue par `ThirdPartyAssetSentinel`.
	 */
	const CDN = [
		'production/map/ui/Mapbox.u.php' => ['package' => 'mapbox-gl', 'pattern' => '#mapbox-gl-js/v([0-9.]+)/#'],
	];

	/**
	 * Les éléments que la veille écarte, après lecture, jusqu'à une date : une faille sans objet
	 * pour OTF, une fin de support assumée. La clé est l'identifiant de la faille (`UBUNTU-CVE-…`,
	 * `GHSA-…`) ou celui du constat (`eol:mysql`). Passé `until`, l'élément revient.
	 *
	 * @var array<string, array{until: string, reason: string}>
	 */
	const ACCEPTED = [
	];

	/**
	 * Les paquets sources du noyau, sous Debian et sous Ubuntu (`linux`, `linux-signed-hwe-6.8`…).
	 * OSV rattache à chacun des milliers de failles (4 633 pour `linux` 6.1.174-1 le 21/10/2026),
	 * que la veille ne peut pas lire une à une chaque matin, et c'est la mise à jour qui compte.
	 */
	const KERNEL_PATTERN = '/^linux(-signed|-meta)?(-hwe-[0-9.]+)?$/';

	public static function isKernel(string $source): bool {
		return preg_match(self::KERNEL_PATTERN, $source) === 1;
	}

	public static function getExposure(string $package): string {

		foreach(self::EXPOSED_PACKAGES as $exposed) {
			if($package === $exposed or str_starts_with($package, $exposed)) {
				return self::EXPOSED;
			}
		}

		return self::INTERNAL;

	}

	public static function isAccepted(string $key): bool {
		return isset(self::ACCEPTED[$key]) and self::ACCEPTED[$key]['until'] >= \Clock::date();
	}

	/**
	 * Le système (identifiant et version de `/etc/os-release`), et l'écosystème sous lequel OSV
	 * range ses paquets. NULL hors d'un système que OSV connaît.
	 *
	 * @return array{id: string, version: string, ecosystem: ?string}|null
	 */
	public static function getSystem(): ?array {

		if(is_readable('/etc/os-release') === FALSE) {
			return NULL;
		}

		$values = parse_ini_file('/etc/os-release', scanner_mode: INI_SCANNER_RAW);

		if($values === FALSE or isset($values['ID'], $values['VERSION_ID']) === FALSE) {
			return NULL;
		}

		$id = $values['ID'];
		$version = trim($values['VERSION_ID'], '"');

		return [
			'id' => $id,
			'version' => $version,
			'ecosystem' => match($id) {
				'ubuntu' => 'Ubuntu:'.$version.(str_contains($values['VERSION'] ?? '', 'LTS') ? ':LTS' : ''),
				'debian' => 'Debian:'.$version,
				default => NULL,
			},
		];

	}

	/**
	 * Les paquets du système qui ont une mise à jour en attente, par paquet source, avec la version
	 * source installée et ce qui dit que la mise à jour corrige une faille : elle vient du dépôt de
	 * sécurité de la distribution. C'est sous le paquet source que les distributions publient leurs
	 * failles.
	 *
	 * La liste vient d'une simulation d'`apt-get upgrade`, qui ne demande aucun droit et n'écrit
	 * rien : elle ne voit que ce que les listes d'apt connaissent, d'où leur date, que la veille
	 * signale quand elles ne se rafraîchissent plus.
	 *
	 * @return array{packages: array<string, array{installed: string, security: bool}>, listsAt: ?string}
	 */
	public static function getSystemUpgrades(): array {

		$listsAt = is_dir('/var/lib/apt/lists') ? self::getNewestModification('/var/lib/apt/lists') : NULL;

		$sources = [];

		foreach(explode("\n", (string)shell_exec('dpkg-query -W -f=\'${db:Status-Abbrev}\t${binary:Package}\t${Package}\t${source:Package}\t${source:Version}\n\' 2>/dev/null')) as $line) {

			$fields = explode("\t", $line);

			if(count($fields) === 5 and str_starts_with($fields[0], 'ii')) {
				$sources[$fields[1]] = ['source' => $fields[3], 'version' => $fields[4]];
				$sources[$fields[2]] = ['source' => $fields[3], 'version' => $fields[4]];
			}

		}

		$packages = [];

		// « Inst libssl3t64 [3.0.13-0ubuntu3.4] (3.0.13-0ubuntu3.5 Ubuntu:24.04/noble-updates, Ubuntu:24.04/noble-security [amd64]) »
		foreach(explode("\n", (string)shell_exec('apt-get -s -o Debug::NoLocking=1 upgrade 2>/dev/null')) as $line) {

			if(preg_match('/^Inst (\S+) (?:\[[^\]]*\] )?\((\S+) ([^\[]*)/', $line, $match) !== 1 or isset($sources[$match[1]]) === FALSE) {
				continue;
			}

			$source = $sources[$match[1]];

			$packages[$source['source']] = [
				'installed' => $source['version'],
				'security' => (($packages[$source['source']]['security'] ?? FALSE) or str_contains($match[3], '-security')),
			];

		}

		return ['packages' => $packages, 'listsAt' => $listsAt];

	}

	/**
	 * Le paquet source est-il installé par le système ? Un logiciel qu'il apporte se met à jour
	 * avec lui, et la distribution le corrige jusqu'à sa propre fin de support.
	 */
	public static function isSystemPackage(string $source): bool {

		$output = (string)shell_exec('dpkg-query -W -f=\'${db:Status-Abbrev}\t${source:Package}\n\' 2>/dev/null');

		return preg_match('/^ii\S*\s*\t'.preg_quote($source, '/').'$/m', $output) === 1;

	}

	/**
	 * Une mise à jour du noyau ou d'une bibliothèque chargée au démarrage n'agit qu'au redémarrage
	 * suivant, et Ubuntu le signale par ce fichier.
	 */
	public static function isRebootRequired(): bool {
		return is_file('/var/run/reboot-required');
	}

	/**
	 * La policy d'ImageMagick du dépôt (`ouvretaferme/main/conf/imagemagick/policy.xml`) vit dans
	 * `/etc`, qu'une réinstallation du serveur rend à celle de la distribution, laquelle lit le SVG.
	 * Le SVG éprouvé ne cite aucune ressource : le lire ne fait rien d'autre que le décoder.
	 */
	public static function isImageMagickPolicyMissing(): bool {

		try {
			new \Imagick()->readImageBlob('<svg xmlns="http://www.w3.org/2000/svg" width="4" height="4"></svg>');
			return TRUE;
		} catch(\ImagickException) {
			return FALSE;
		}

	}

	private static function getNewestModification(string $directory): ?string {

		$newest = NULL;

		foreach(glob($directory.'/*') as $file) {
			$newest = max($newest ?? 0, (int)filemtime($file));
		}

		return ($newest === NULL) ? NULL : date('Y-m-d', $newest);

	}

	/**
	 * La version installée de chaque logiciel de `SOFTWARES`, NULL quand elle ne se lit pas sur
	 * cette machine.
	 *
	 * @return array<string, ?string>
	 */
	public static function getVersions(): array {

		$versions = [];

		foreach(array_keys(self::SOFTWARES) as $key) {

			try {
				$versions[$key] = self::readVersion($key);
			} catch(\Throwable) {
				$versions[$key] = NULL;
			}

		}

		return $versions;

	}

	private static function readVersion(string $key): ?string {

		return match($key) {
			'system' => self::getSystem()['version'] ?? NULL,
			'php' => PHP_VERSION,
			'mysql' => self::extractVersion((string)new \Database('security')->selectUnique('SELECT VERSION()')),
			'redis' => \Cache::redis()->getClient()->info('server')['redis_version'] ?? NULL,
			'node' => self::extractVersion((string)shell_exec('node --version 2>/dev/null')),
			'chrome' => self::extractVersion((string)shell_exec('google-chrome --version 2>/dev/null')),
		};

	}

	/**
	 * Le premier numéro de version d'une sortie (`v22.23.2`, `Chromium 150.0.7871.181`,
	 * `8.0.46-0ubuntu0.24.04.1`).
	 */
	public static function extractVersion(string $output): ?string {

		if(preg_match('/[0-9]+(\.[0-9]+)+/', $output, $match) === 1) {
			return $match[0];
		}

		return NULL;

	}

	/**
	 * Les bibliothèques versionnées dans le dépôt, par écosystème de OSV : celles des fichiers de
	 * verrou, puis celles servies au navigateur.
	 *
	 * @return array<int, array{ecosystem: string, package: string, version: string, exposure: string, source: string}>
	 */
	public static function getLibraries(): array {

		$libraries = [];

		foreach(self::LOCKS as $path => $lock) {

			$content = json_decode((string)file_get_contents(LIME_DIRECTORY.'/'.$path), TRUE, flags: JSON_THROW_ON_ERROR);

			$packages = match($lock['ecosystem']) {
				'Packagist' => self::readComposerLock($content),
				'npm' => self::readNpmLock($content),
			};

			foreach($packages as [$name, $version]) {
				$libraries[] = [
					'ecosystem' => $lock['ecosystem'],
					'package' => $name,
					// Composer garde l'étiquette du dépôt (`v5.4.0`), que `version_compare()` ne range
					// pas avec les versions publiées par OSV (`5.4.1`).
					'version' => ltrim($version, 'v'),
					'exposure' => $lock['exposure'],
					'source' => $path,
				];
			}

		}

		foreach(self::ASSETS as $path => $asset) {

			if(is_string($asset) or preg_match($asset['pattern'], $path, $match) !== 1) {
				continue;
			}

			$libraries[] = ['ecosystem' => 'npm', 'package' => $asset['package'], 'version' => $match[1], 'exposure' => self::EXPOSED, 'source' => $path];

		}

		foreach(self::CDN as $path => $cdn) {

			if(preg_match($cdn['pattern'], (string)file_get_contents(LIME_DIRECTORY.'/'.$path), $match) !== 1) {
				continue;
			}

			$libraries[] = ['ecosystem' => 'npm', 'package' => $cdn['package'], 'version' => $match[1], 'exposure' => self::EXPOSED, 'source' => $path];

		}

		return $libraries;

	}

	/**
	 * @return array<int, array{0: string, 1: string}>
	 */
	private static function readComposerLock(array $content): array {

		$packages = [];

		foreach([...$content['packages'], ...$content['packages-dev']] as $package) {
			$packages[] = [$package['name'], $package['version']];
		}

		return $packages;

	}

	/**
	 * Le verrou npm v3 range chaque paquet installé sous son chemin dans `node_modules`,
	 * dépendances transitives comprises, et un même paquet peut y figurer sous deux versions ; la
	 * clé vide est le projet lui-même.
	 *
	 * @return array<int, array{0: string, 1: string}>
	 */
	private static function readNpmLock(array $content): array {

		$packages = [];

		foreach($content['packages'] as $path => $package) {

			if($path === '') {
				continue;
			}

			$packages[] = [substr($path, strrpos($path, 'node_modules/') + strlen('node_modules/')), $package['version']];

		}

		return $packages;

	}

}
?>
