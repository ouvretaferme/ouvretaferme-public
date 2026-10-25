<?php
namespace scene;

/**
 * Le skill Claude qu'un utilisateur télécharge pour préparer le modèle 3D d'un profil de téléversement
 * (`avatar`, `decor`) : le dossier `upload/<profil>/`, ses règles `upload/<profil>.json`, et les fichiers
 * communs aux profils, dont le validateur `upload/upload.mjs`, que le serveur emploie lui-même
 * (`UploadLib::run()`). L'utilisateur juge donc son fichier avec le code qui le jugera à l'envoi, et les
 * règles qu'il lit sont celles du serveur.
 */
class UploadSkillLib {

	/**
	 * Les fichiers de `upload/` que portent les skills de tous les profils, rangés à la racine de l'archive : le
	 * validateur, l'écriture d'un .glb, le rendu sans navigateur, le crochet qui fait lire three à node, et l'export
	 * depuis Blender.
	 */
	const array COMMON = ['upload.mjs', 'glb.mjs', 'rendu.mjs', 'resolve.mjs', 'exporter.py'];

	/**
	 * Ce que les scripts du skill d'un profil chargent du socle des scènes, chemin dans l'archive vers chemin sous
	 * `base/scene/` : three, la fabrique de l'avatar ou du bâtiment et ses choix, et le lecteur du format cuit. node ne
	 * charge un module ES qu'en `.mjs` hors d'un package.json qui le déclare : l'archive les renomme, sans copie dans le
	 * dépôt.
	 */
	const array LIBRARIES = [
		'avatar' => [
			'lib/avatar.mjs' => 'static/js/avatar.js',
			'lib/catalog.json' => 'static/avatar/catalog.json',
			'lib/model.mjs' => 'static/js/model.js',
			'lib/three.mjs' => 'static/lib/three-0.170.0.module.min.js',
			'lib/three.LICENSE' => 'static/lib/three-0.170.0.LICENSE',
		],
		'decor' => [
			'lib/BufferGeometryUtils.mjs' => 'static/lib/three-0.170.0.BufferGeometryUtils.js',
			'lib/building.mjs' => 'static/js/building.js',
			'lib/choices.json' => 'static/building/choices.json',
			'lib/geometry.mjs' => 'static/js/geometry.js',
			'lib/model.mjs' => 'static/js/model.js',
			'lib/three.mjs' => 'static/lib/three-0.170.0.module.min.js',
			'lib/three.LICENSE' => 'static/lib/three-0.170.0.LICENSE',
		],
	];

	/**
	 * Les libellés que l'archive ajoute au catalogue, sous sa clé `labels`, chemin dans l'archive vers chemin sous
	 * `base/scene/`. Le catalogue de l'éditeur ne nomme une entrée que par sa clé, où `foulard` et `fichu` se confondent,
	 * et les libellés de l'éditeur sont des mots d'écran traduits : ceux-ci décrivent la pièce à qui écrit une fiche
	 * sans voir l'éditeur.
	 */
	const array LABELS = [
		'avatar' => [
			'lib/catalog.json' => 'upload/avatar.labels.json',
		],
	];

	public static function getName(string $profile): string {
		return $profile.'-ouvretaferme';
	}

	/**
	 * Les fichiers du skill, chemin dans l'archive vers chemin sur le disque, dans un ordre fixe pour
	 * que deux téléchargements rendent la même archive.
	 */
	public static function getFiles(string $profile): array {

		// Les règles se lisent avant qu'un chemin soit bâti sur le nom du profil : getRules() refuse un nom
		// qui ne désigne pas un fichier de règles du package
		UploadLib::getRules($profile);

		$upload = LIME_DIRECTORY.'/base/scene/upload/';
		$directory = $upload.$profile.'/';
		$name = self::getName($profile);

		$files = [];

		foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $entry) {
			$files[$name.'/'.substr($entry->getPathname(), strlen($directory))] = $entry->getPathname();
		}

		$files[$name.'/'.$profile.'.json'] = $upload.$profile.'.json';

		foreach(self::COMMON as $common) {
			$files[$name.'/'.$common] = $upload.$common;
		}

		foreach(self::LIBRARIES[$profile] ?? [] as $path => $source) {
			$files[$name.'/'.$path] = LIME_DIRECTORY.'/base/scene/'.$source;
		}

		ksort($files);

		return $files;

	}

	/**
	 * L'archive zip du skill, fabriquée à chaque demande : elle suit sans geste les règles et le
	 * validateur du serveur, qu'aucun fichier cuit ne pourrait laisser en retard.
	 *
	 * Écrite par Phar et non par ZipArchive, comme `help\RunLib::getArchive()` : l'extension zip n'est
	 * pas installée partout, Phar l'est avec PHP, et n'écrit qu'un fichier d'extension .zip. Les entrées
	 * sont compressées, ce qui demande zlib : three seul pèse 700 Ko, l'archive 2,1 Mo sans compression.
	 */
	public static function getArchive(string $profile): string {

		$files = self::getFiles($profile);

		// tempnam() crée le fichier qu'il nomme, et Phar ne lit et n'écrit qu'un nom en .zip : les deux se retirent.
		$base = tempnam(sys_get_temp_dir(), 'skill');
		$file = $base.'.zip';

		try {

			$archive = new \PharData($file);

			$name = self::getName($profile);

			foreach($files as $path => $source) {
				$archive->addFromString($path, self::getContent($profile, substr($path, strlen($name) + 1), $source));
			}

			$archive->compressFiles(\Phar::GZ);

			unset($archive);

			return file_get_contents($file);

		} finally {
			unlink($file);
			unlink($base);
		}

	}

	/**
	 * Le contenu d'un fichier de l'archive : celui du disque, ou pour un catalogue, le catalogue et ses libellés.
	 */
	public static function getContent(string $profile, string $path, string $source): string {

		$content = file_get_contents($source);

		if(isset(self::LABELS[$profile][$path]) === FALSE) {
			return $content;
		}

		$catalog = json_decode($content, TRUE, flags: JSON_THROW_ON_ERROR);
		$catalog['labels'] = json_decode(file_get_contents(LIME_DIRECTORY.'/base/scene/'.self::LABELS[$profile][$path]), TRUE, flags: JSON_THROW_ON_ERROR);

		return json_encode($catalog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";

	}

}
?>
