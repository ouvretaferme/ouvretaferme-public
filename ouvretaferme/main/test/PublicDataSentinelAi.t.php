<?php
namespace main;

/**
 * Test-sentinelle : le code publié ne porte aucune donnée réelle (chantier open-source, lot 2).
 *
 * Le dépôt public est un export du dépôt de l'équipe, privé des chemins de
 * `MainSetting::EXPORT_PRIVATE_PATHS`. Tout le reste part tel quel, historique d'export compris : un
 * SIRET, une adresse ou un identifiant de structure écrit dans un test ou un commentaire est publié le
 * jour où il est commité, et ne se retire plus. Ce test lit tout ce qui sera publié et refuse trois
 * formes de donnée réelle :
 *
 * - un nombre de 9 ou 14 chiffres dont la clé de Luhn est juste, c'est-à-dire un SIREN ou un SIRET
 *   possible, sauf la série fictive `0000000…` et les nombres déclarés dans FICTIVE_NUMBERS ;
 * - une adresse électronique hors des domaines réservés aux exemples (`example.org`, `exemple.fr`,
 *   `….example`) et de `ouvretaferme.org`, sauf les fichiers déclarés dans EMAIL_FILES ;
 * - `structure=<n>` ou `structure_<n>` qui ne désigne ni une structure du décor
 *   (`StructureSetting::FIXTURE_STRUCTURES`) ni un identifiant déclaré dans STRUCTURE_IDS.
 *
 * Chaque déclaration est aussi confrontée au code : une valeur déclarée que plus aucun fichier ne
 * porte, ou un chemin privé qui ne désigne plus aucun fichier, échoue et demande le retrait de la
 * déclaration. C'est le code qui fait foi.
 *
 * Elle refuse aussi le prénom ou le nom d'un auteur de commit du dépôt, que l'équipe écrit en citant
 * une décision : « décidé le 2026-10-16 » se suffit. Les noms se lisent dans `git log`, pour que ce
 * fichier publié n'en écrive aucun.
 *
 * Ce que la lecture ne voit pas, et qui reste à la charge de la relecture : un nom de personne hors
 * des auteurs, un nom de structure réelle, un SIREN écrit avec des espaces ou concaténé, un identifiant de structure passé
 * autrement qu'en `structure=` ou `structure_`, et la forme dérivée de `.claude/` que publiera le kit.
 *
 * Lancement : dev/test package=main file=PublicDataSentinelAi
 */
class PublicDataSentinelAiTest extends \Test {

	/**
	 * Dépendances tierces, qui gardent leurs propres mentions, et fichiers que git suit sans qu'ils
	 * soient du code de Ouvretaferme.
	 */
	const THIRD_PARTY = ['/vendor/', '/node_modules/', '/static/lib/'];

	/**
	 * Formats compressés, dont le flux peut contenir n'importe quelle suite de caractères sans
	 * qu'aucun octet nul ne la trahisse au début du fichier.
	 */
	const BINARY = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'ico', 'woff', 'woff2', 'ttf', 'otf', 'eot', 'zip', 'gz', 'mp4', 'webm', 'mp3'];

	/**
	 * Nombres à clé de Luhn juste qui ne désignent aucune entreprise réelle autre que l'éditeur.
	 */
	const FICTIVE_NUMBERS = [
		'12345678900015' => 'suite croissante, SIRET de démonstration des tests',
		'134217728' => 'une taille en octets, 2 puissance 27',
		'999999998' => 'un identifiant de verrou fabriqué par le test',
		'643717713' => 'une constante de l\'algorithme MD5',
		'102075736' => 'SIREN de la SAS OTF, éditeur, publié dans les CGU',
		'10207573600011' => 'SIRET de la SAS OTF, éditeur, publié dans les CGU',
		// Les intercommunalités que geo.api.gouv.fr rend pour les lieux du décor et des tests du lieu de
		// production : des collectivités, publiées au répertoire national des EPCI, jamais une personne.
		'200067825' => 'SIREN de la communauté d\'agglomération Gap-Tallard-Durance',
		'200069177' => 'SIREN de la communauté de communes Mond\'Arverne Communauté',
		'241501055' => 'SIREN de la communauté de communes Sumène-Artense',
		'242010056' => 'SIREN de la communauté d\'agglomération du Pays Ajaccien',
		'200054781' => 'SIREN de la Métropole du Grand Paris',
	];

	/**
	 * Fichiers dont les adresses ne désignent personne.
	 */
	const EMAIL_FILES = [
		'INSTALL.md' => 'l\'adresse de clonage SSH de GitHub',
		'base/user/test/AdminLogAi.t.php' => 'une adresse de placeholder',
		'base/user/test/LoginAttemptAi.t.php' => 'une adresse de placeholder, éprouvée en casse mêlée',
		'framework/dev/test/Filter.t.php' => 'des adresses fabriquées pour éprouver la syntaxe',
		'framework/util/lib/CrawlerDetect.php' => 'l\'auteur de la bibliothèque tierce, sous licence MIT',
		'framework/util/lib/Fixtures/AbstractProvider.php' => 'l\'auteur de la bibliothèque tierce, sous licence MIT',
		'framework/util/lib/Fixtures/Crawlers.php' => 'l\'auteur de la bibliothèque tierce, sous licence MIT',
		'framework/util/lib/Fixtures/Exclusions.php' => 'l\'auteur de la bibliothèque tierce, sous licence MIT',
		'framework/util/lib/Fixtures/Headers.php' => 'l\'auteur de la bibliothèque tierce, sous licence MIT',
	];

	/**
	 * Identifiants de structure écrits hors du décor.
	 */
	const STRUCTURE_IDS = [
		1 => 'les bases de la démo, demo_structure_1 et 2',
		2 => 'les bases de la démo, demo_structure_1 et 2',
		7 => 'la structure de référence des tests, sans donnée attachée hors base',
	];

	/**
	 * Lignes qui nomment un auteur du dépôt, par fichier, dans les fichiers qu'une autre session tenait
	 * en cours, ou que l'amont a changés, le 25/10, et dans deux sources 3D dont `ModelSentinel` et `UploadSkillSentinel` suivent
	 * l'empreinte, qui se redressent avec leur prochaine cuisson : le compte ne peut que baisser.
	 */
	const AUTHOR_NAME_LINES = [
		'base/scene/model/courgette.fruits.js' => 1,
		'base/scene/static/js/avatar.js' => 1,
		'base/user/lib/UserAvatar.l.php' => 1,
		'base/user/static/js/avatar.js' => 1,
		'framework/core/Action.php' => 1,
		'framework/dev/lib/Minify.l.php' => 1,
		'framework/dev/page/minify.p.php' => 1,
		'framework/dev/test/ClockSentinel.t.php' => 1,
		'framework/dev/test/CssSentinel.t.php' => 2,
		'framework/storage/lib/Driver.l.php' => 1,
		'internal/data/AnonymizeAi.s.php' => 2,
		'ouvretaferme/design/test/InlineStyleSentinelAi.t.php' => 1,
		'ouvretaferme/design/ui/Design.u.php' => 1,
		'ouvretaferme/design/ui/DevAi.u.php' => 1,
		'ouvretaferme/learning/lib/Community.l.php' => 1,
		'ouvretaferme/learning/lib/Course.l.php' => 2,
		'ouvretaferme/learning/lib/Share.l.php' => 1,
		'ouvretaferme/learning/module/Course.e.php' => 4,
		'ouvretaferme/learning/module/Date.e.php' => 1,
		'ouvretaferme/learning/page/course.p.php' => 2,
		'ouvretaferme/learning/test/CatalogCircuit.t.php' => 1,
		'ouvretaferme/learning/test/CourseEditionTabs.t.php' => 2,
		'ouvretaferme/learning/test/CourseExternal.t.php' => 3,
		'ouvretaferme/learning/test/CourseHead.t.php' => 2,
		'ouvretaferme/learning/test/CourseMenu.t.php' => 1,
		'ouvretaferme/learning/test/Date.t.php' => 2,
		'ouvretaferme/learning/test/ProductionAccess.t.php' => 1,
		'ouvretaferme/learning/test/ShareMembers.t.php' => 1,
		'ouvretaferme/learning/ui/Catalog.u.php' => 2,
		'ouvretaferme/learning/ui/Course.u.php' => 20,
		'ouvretaferme/learning/ui/Date.u.php' => 2,
		'ouvretaferme/main/static/css/component.css' => 6,
		'production/map/static/css/scene.css' => 1,
		'production/map/static/js/scene.js' => 4,
		'production/planning/lib/TaskDay.l.php' => 1,
		'production/planning/test/TaskAction.t.php' => 1,
	];

	const EMAIL_DOMAIN = '/(^|[.-])(example|exemple)\.[a-z]{2,}$|\.example$|(^|\.)ouvretaferme\.org$/i';

	public function testPrivatePathsMatchFiles(): void {

		$stale = [];

		foreach(MainSetting::EXPORT_PRIVATE_PATHS as $pattern) {

			$matched = FALSE;

			foreach(array_keys($this->requireTracked()) as $file) {
				if(MainSetting::isExportPrivate($file, [$pattern])) {
					$matched = TRUE;
					break;
				}
			}

			if($matched === FALSE) {
				$stale[] = $pattern;
			}

		}

		$this->assertEquals([], $stale, 'chemin privé qui ne désigne plus aucun fichier suivi, le retirer de MainSetting::EXPORT_PRIVATE_PATHS : '.implode(' · ', $stale));

	}

	public function testNoRealCompanyNumber(): void {

		$found = [];
		$used = [];

		foreach($this->getPublished() as $file => $lines) {

			foreach($lines as $number => $line) {

				preg_match_all('/(?<![0-9A-Za-z_.])([0-9]{14}|[0-9]{9})(?![0-9A-Za-z_])/', $line, $matches);

				foreach($matches[1] as $value) {

					if(self::isLuhn($value) === FALSE or count(array_unique(str_split($value))) === 1 or str_starts_with($value, '0000000')) {
						continue;
					}

					if(isset(self::FICTIVE_NUMBERS[$value])) {
						$used[$value] = TRUE;
					} else {
						$found[] = $file.':'.$number.' ('.$value.')';
					}

				}

			}

		}

		$this->assertEquals([], $found, 'SIREN ou SIRET possible sur un chemin publié, le remplacer par un numéro de la série fictive 0000000… (clé de Luhn juste), ou le déclarer dans FICTIVE_NUMBERS avec la raison pour laquelle il ne désigne personne : '.implode(' · ', $found));
		$this->assertEquals([], array_keys(array_diff_key(self::FICTIVE_NUMBERS, $used)), 'nombre déclaré dans FICTIVE_NUMBERS que plus aucun fichier publié ne porte, retirer la déclaration');

	}

	public function testNoRealEmail(): void {

		$found = [];
		$used = [];

		foreach($this->getPublished() as $file => $lines) {

			foreach($lines as $number => $line) {

				preg_match_all('/[A-Za-z0-9._%+-]+@([A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,})/', $line, $matches, PREG_SET_ORDER);

				foreach($matches as [$email, $domain]) {

					if(preg_match(self::EMAIL_DOMAIN, $domain) > 0) {
						continue;
					}

					if(isset(self::EMAIL_FILES[$file])) {
						$used[$file] = TRUE;
					} else {
						$found[] = $file.':'.$number.' ('.$email.')';
					}

				}

			}

		}

		$this->assertEquals([], $found, 'adresse électronique sur un domaine réel dans un chemin publié, la remplacer par une adresse en @example.org, ou déclarer le fichier dans EMAIL_FILES avec la raison pour laquelle ses adresses ne désignent personne : '.implode(' · ', $found));
		$this->assertEquals([], array_keys(array_diff_key(self::EMAIL_FILES, $used)), 'fichier déclaré dans EMAIL_FILES qui ne porte plus d\'adresse hors des domaines réservés, retirer la déclaration');

	}

	public function testNoRealStructureId(): void {

		$found = [];
		$used = [];

		foreach($this->getPublished() as $file => $lines) {

			foreach($lines as $number => $line) {

				preg_match_all('/(?<![A-Za-z0-9])structure[=_]([0-9]+)(?![0-9])/', $line, $matches);

				foreach($matches[1] as $id) {

					$id = (int)$id;

					if(in_array($id, \structure\StructureSetting::FIXTURE_STRUCTURES, TRUE)) {
						continue;
					}

					if(isset(self::STRUCTURE_IDS[$id])) {
						$used[$id] = TRUE;
					} else {
						$found[] = $file.':'.$number.' (structure '.$id.')';
					}

				}

			}

		}

		$this->assertEquals([], $found, 'identifiant de structure réelle sur un chemin publié, citer une structure du décor par sa constante FixtureAiLib, ou déclarer l\'identifiant dans STRUCTURE_IDS avec la raison pour laquelle il n\'expose rien : '.implode(' · ', $found));
		$this->assertEquals([], array_keys(array_diff_key(self::STRUCTURE_IDS, $used)), 'identifiant déclaré dans STRUCTURE_IDS que plus aucun fichier publié ne porte, retirer la déclaration');

	}

	/**
	 * Les lignes de chaque fichier publié, numérotées à partir de 1, sans les fichiers binaires.
	 *
	 * @return array<string, array<int, string>>
	 */
	public function testNoAuthorName(): void {

		$pattern = '/(?<!\\p{L})('.implode('|', array_map(fn($name) => preg_quote($name, '/'), $this->getAuthorNames())).')(?!\\p{L})/u';

		$found = [];

		foreach($this->getPublished() as $file => $lines) {

			$count = count(preg_grep($pattern, $lines));

			if($count > 0) {
				$found[$file] = $count;
			}

		}

		$new = [];
		$stale = [];

		foreach($found as $file => $count) {
			if($count > (self::AUTHOR_NAME_LINES[$file] ?? 0)) {
				$new[] = $file.' ('.$count.' lignes)';
			}
		}

		foreach(self::AUTHOR_NAME_LINES as $file => $count) {
			if(($found[$file] ?? 0) < $count) {
				$stale[] = $file.' ('.($found[$file] ?? 0).' lignes)';
			}
		}

		$this->assertEquals([], $new, 'nom d\'un auteur du dépôt dans un fichier publié, citer la décision par sa date sans son auteur (« décidé le 2026-10-16 ») : '.implode(' · ', $new));
		$this->assertEquals([], $stale, 'nom d\'auteur retiré, baisser AUTHOR_NAME_LINES au compte trouvé : '.implode(' · ', $stale));

	}

	/**
	 * Prénoms et noms des auteurs de commit, écrits avec et sans accents. Un identifiant (`dmathieu`,
	 * `MaximeOh`) ne s'écrit pas en prose et reste hors du motif.
	 */
	private function getAuthorNames(): array {

		$output = [];
		$status = 0;
		exec('cd '.escapeshellarg(\dev\SentinelLib::getRoot()).' && git log --format=%an 2>/dev/null | sort -u', $output, $status);

		if($status !== 0 or $output === []) {
			throw new \Exception('git est indisponible : sans les auteurs du dépôt, leurs noms ne se cherchent pas');
		}

		$names = [];

		foreach($output as $author) {
			foreach(preg_split('/\s+/', $author) as $word) {
				if(preg_match('/^\p{Lu}\p{Ll}{2,}$/u', $word)) {
					$names[$word] = TRUE;
					$names[preg_replace('/\p{Mn}/u', '', \Normalizer::normalize($word, \Normalizer::FORM_D))] = TRUE;
				}
			}
		}

		return array_keys($names);

	}

	private function getPublished(): array {

		static $published = NULL;

		if($published === NULL) {

			$published = [];

			foreach(array_keys($this->requireTracked()) as $file) {

				if(
					MainSetting::isExportPrivate($file) or
					self::isThirdParty($file) or
					in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), self::BINARY, TRUE)
				) {
					continue;
				}

				$path = \dev\SentinelLib::getRoot().'/'.$file;

				if(is_file($path) === FALSE) {
					continue;
				}

				$content = file_get_contents($path);

				if(str_contains(substr($content, 0, 8000), "\0")) {
					continue;
				}

				$lines = explode("\n", $content);
				$published[$file] = array_combine(range(1, count($lines)), $lines);

			}

		}

		return $published;

	}

	private function requireTracked(): array {

		$tracked = \dev\SentinelLib::getTracked();

		if($tracked === NULL) {
			throw new \Exception('git est indisponible : sans la liste des fichiers suivis, ce qui sera publié ne se lit pas');
		}

		return $tracked;

	}

	private static function isThirdParty(string $file): bool {

		foreach(self::THIRD_PARTY as $directory) {
			if(str_contains('/'.$file, $directory)) {
				return TRUE;
			}
		}

		return str_ends_with($file, 'composer.lock');

	}

	private static function isLuhn(string $value): bool {

		$sum = 0;

		foreach(array_reverse(str_split($value)) as $position => $digit) {

			$digit = (int)$digit;

			if($position % 2 === 1) {
				$digit *= 2;
				if($digit > 9) {
					$digit -= 9;
				}
			}

			$sum += $digit;

		}

		return $sum % 10 === 0;

	}

}
?>
