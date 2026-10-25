<?php
namespace scene;

/**
 * Les skills Claude de téléversement, un par profil (`upload/avatar/`, `upload/decor/`), sont téléchargés tels quels par
 * l'utilisateur, hors du dépôt et sans personne pour les relire. Cette sentinelle tient l'inventaire de ce qui part :
 * - leurs modèles sont tirés de ce que le site monte et des règles par `upload/samples.mjs`, qui relève l'empreinte des
 *   sources lues dans `upload/<profil>.samples.json` : une source retouchée sans régénération laisse au skill des modèles
 *   d'un autre corps ou d'un autre cadre. La source fait foi, les modèles se régénèrent ;
 * - la table des refus de chaque SKILL.md cite chaque code de `refusals` de ses règles, et aucun autre ;
 * - leurs textes et leurs scripts (`.md`, `.py`, `.mjs` du dossier et des fichiers communs de `UploadSkillLib::COMMON`),
 *   écrits pour le Claude de l'utilisateur, ne citent rien du dépôt. La règle ne couvre pas `upload.mjs`, le code même
 *   du serveur, dont les commentaires parlent du serveur ;
 * - chaque dossier ne porte que des fichiers déclarés, chacun avec sa raison de partir.
 *
 * Le comportement (régénération en mémoire, contenu de l'archive, jugement du serveur) est dans `UploadSkillAi`.
 * Aucune écriture, aucune base ouverte : elle ne lit que des fichiers du package.
 *
 * Lancement : dev/test package=scene file=UploadSkillSentinel
 */
class UploadSkillSentinelTest extends \Test {

	const string UPLOAD = LIME_DIRECTORY.'/base/scene/upload/';

	/**
	 * Les fichiers de `upload/<profil>/`, tous dans l'archive téléchargée de ce profil.
	 */
	const array FILES = [
		'avatar' => [
			'SKILL.md' => "le skill lui-même, que lit le Claude de l'utilisateur",
			'composer.mjs' => "un avatar monté des entrées du catalogue de l'éditeur, écrit en .glb, pour qui n'a pas Blender",
			'corps.glb' => "le corps nu de l'avatar livré, d'où l'utilisateur part, écrit par samples.mjs",
			'poser.py' => "la mise en pose de la scène Blender, du calcul même du site, pour voir une pièce qui en traverse une autre",
			'reference.md' => "le détail que SKILL.md cite : repères, pièces de corps.glb, poses, couleurs, cas particuliers",
			'rendre.mjs' => "les images d'un .glb, au repos et dans chaque pose, avec les normales, l'éclairage et la caméra de l'éditeur, sans Blender",
			'exemples/attendu.json' => "le jugement d'upload.mjs pour chaque exemple, qui éprouve node et le dossier chez l'utilisateur, écrit par samples.mjs",
			'exemples/conforme.glb' => "la composition par défaut de l'éditeur, la forme d'un modèle accepté pour qui n'a pas Blender, écrite par samples.mjs",
			'exemples/refus-armature.glb' => "un refus `rig`, l'erreur que fait un personnage exporté avec son armature, écrit par samples.mjs",
			'exemples/refus-os.glb' => "un refus `bone`, l'erreur d'une pièce ajoutée sans nom d'os, écrit par samples.mjs",
			'exemples/refus-sol.glb' => "un refus `ground`, l'erreur d'un modèle levé du sol, écrit par samples.mjs",
		],
		'decor' => [
			'SKILL.md' => "le skill lui-même, que lit le Claude de l'utilisateur",
			'composer.mjs' => "un bâtiment monté d'une fiche de l'éditeur du décor ou du cadre des bâtiments, écrit en .glb, pour qui n'a pas Blender",
			'reference.md' => "le détail que SKILL.md cite : repères, fiches, couleurs, budget, pièces, ancres, cas particuliers",
			'rendre.mjs' => "les images d'un .glb de face, de trois-quarts, de dos et de dessus, avec les normales, l'éclairage et la caméra de l'éditeur du décor, sans Blender",
			'exemples/attendu.json' => "le jugement d'upload.mjs pour chaque exemple, qui éprouve node et le dossier chez l'utilisateur, écrit par samples.mjs",
			'exemples/conforme.glb' => "le bâtiment de fiche.json composé, la forme d'un modèle accepté, écrit par samples.mjs",
			'exemples/fiche.json' => "une fiche de l'éditeur du décor, le modèle d'une fiche à écrire, écrite par samples.mjs",
			'exemples/refus-piece.glb' => "un refus `piece`, l'erreur d'un objet ajouté sans nom de pièce, écrit par samples.mjs",
			'exemples/refus-sol.glb' => "un refus `ground`, l'erreur d'un bâtiment levé du sol, écrit par samples.mjs",
			'exemples/refus-texture.glb' => "un refus `texture`, l'erreur d'un matériau qui lit une image, écrit par samples.mjs",
		],
	];

	/**
	 * Ce qui, dans un texte que lit le Claude de l'utilisateur (`.md`, `.py`, `.mjs`), désignerait le dépôt ou son
	 * fonctionnement interne à un inconnu.
	 */
	const array INTERNAL = [
		'base/' => "un chemin du dépôt",
		'.claude' => "les règles de travail de l'équipe",
		'.p.php' => "une page du serveur",
		'.l.php' => "une bibliothèque du serveur",
		'structure_' => "le nom d'une base de structure",
		'LIME_' => "une constante du cadriciel",
		'bwrap' => "le bac à sable du serveur",
	];

	public function testFingerprints(): void {

		foreach(array_keys(self::FILES) as $profile) {

			$samples = json_decode(file_get_contents(self::UPLOAD.$profile.'.samples.json'), TRUE, flags: JSON_THROW_ON_ERROR);

			$this->assertTrue(count($samples['sources']) >= 5, "upload/".$profile.".samples.json ne relève plus les sources des modèles du skill : la sentinelle ne sait plus ce qu'elle compare");

			$stale = [];

			foreach($samples['sources'] as $source => $hash) {
				if(is_file(LIME_DIRECTORY.'/base/scene/'.$source) === FALSE or sha1_file(LIME_DIRECTORY.'/base/scene/'.$source) !== $hash) {
					$stale[] = $source;
				}
			}

			$this->assertEquals([], $stale, "sources retouchées depuis la dernière écriture des modèles du skill ".$profile.", la source fait foi et les modèles se refont par `node base/scene/upload/samples.mjs`, qui part avec elle : ".implode(', ', $stale));

		}

	}

	public function testRefusals(): void {

		foreach(array_keys(self::FILES) as $profile) {

			$rules = UploadLib::getRules($profile);

			preg_match_all('/^\| `([a-z]+)` \|/m', file_get_contents(self::UPLOAD.$profile.'/SKILL.md'), $matches);
			$cited = $matches[1];

			$missing = array_diff($rules['refusals'], $cited);
			$unknown = array_diff($cited, $rules['refusals']);

			$this->assertEquals([], array_values($missing), "refus des règles ".$profile." absents de la table des refus de son SKILL.md, à y expliquer avec leur correction : ".implode(', ', $missing));
			$this->assertEquals([], array_values($unknown), "refus du SKILL.md ".$profile." que ses règles ne connaissent plus, à retirer de sa table : ".implode(', ', $unknown));

		}

	}

	public function testInternal(): void {

		$texts = [];

		foreach(self::FILES as $profile => $files) {
			foreach(array_keys($files) as $file) {
				$texts[] = $profile.'/'.$file;
			}
		}

		// upload.mjs est le code même du serveur, ses commentaires parlent du serveur
		foreach(array_diff(UploadSkillLib::COMMON, ['upload.mjs']) as $file) {
			$texts[] = $file;
		}

		$found = [];

		foreach($texts as $file) {

			if(in_array(pathinfo($file, PATHINFO_EXTENSION), ['md', 'py', 'mjs'], TRUE) === FALSE) {
				continue;
			}

			$text = file_get_contents(self::UPLOAD.$file);

			foreach(self::INTERNAL as $pattern => $reason) {
				if(str_contains($text, $pattern)) {
					$found[] = $file.' : '.$pattern.' ('.$reason.')';
				}
			}

		}

		$this->assertEquals([], $found, "le skill part chez l'utilisateur et cite le dépôt, à réécrire sans : ".implode(', ', $found));

	}

	public function testFiles(): void {

		foreach(self::FILES as $profile => $declared) {

			$directory = self::UPLOAD.$profile.'/';
			$files = [];

			foreach(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $entry) {
				$files[] = substr($entry->getPathname(), strlen($directory));
			}

			$undeclared = array_diff($files, array_keys($declared));
			$gone = array_diff(array_keys($declared), $files);

			$this->assertEquals([], array_values($undeclared), "fichiers de upload/".$profile."/ que l'archive du skill emporterait sans raison déclarée. La déclaration fait foi : le fichier sort du dossier, et seul un fichier qui doit partir chez l'utilisateur se déclare dans FILES, avec sa raison, avant d'y entrer : ".implode(', ', $undeclared));
			$this->assertEquals([], array_values($gone), "fichiers déclarés dans FILES qui n'existent plus dans upload/".$profile."/, à retirer de la déclaration : ".implode(', ', $gone));

		}

	}

}
?>
