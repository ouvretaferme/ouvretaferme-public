<?php
namespace design;

/**
 * Test-sentinelle : chaque convention d'interface du skill `ui` nomme sa démonstration sur la page
 * du design system, et cette démonstration existe.
 *
 * Une convention écrite sans rien à regarder se relit comme une opinion : celui qui dessine un
 * écran la découvre en recette, quand l'utilisateur la redresse. La piste 3 de la problématique
 * des retours d'interface (retenue le 2026-09-30) demande donc que chaque convention de
 * la section « Conventions de l'interface » de `.claude/skills/ui/SKILL.md` nomme l'identifiant de
 * sa démonstration : une classe du design system (`util-*`, `badge*`) quand elle porte sur un
 * composant, une ancre `#convention-<nom>` posée dans le balisage de la page quand elle porte sur
 * un comportement, qui se montre par un avant et un après et non par une classe.
 *
 * Une convention est tenue pour démontrée dès qu'un identifiant qu'elle nomme entre accents graves
 * se trouve sur la page. Mesuré sur `origin/main` à `d7f3203fd` le 2026-10-01 : 28 conventions,
 * 7 démontrées, 21 sans démonstration, déclarées dans `NOT_DEMONSTRATED`. La liste ne peut que
 * descendre : une convention nouvelle arrive avec sa démonstration.
 *
 * Ce qu'elle ne voit pas, et qui reste à la relecture :
 * - si la démonstration montre bien la convention : une classe citée en passant suffit à la tenir
 *   pour démontrée, et « La hiérarchie des éléments se lit sans les lire » l'est par le seul
 *   `badge` de ses gestes d'appoint ;
 * - le contre-exemple annoté de la vue `?agents`, qu'aucune forme ne distingue d'une annotation.
 *
 * Lancement : dev/test package=design file=ConventionDemonstrationSentinelAi
 */
class ConventionDemonstrationSentinelAiTest extends \Test {

	const SKILL = '.claude/skills/ui/SKILL.md';

	const PAGE = 'ouvretaferme/design/ui/Design.u.php';

	const SECTION = 'Conventions de l\'interface';

	/**
	 * Les conventions sans démonstration, par leur titre en gras, avec ce qui reste à montrer.
	 * « Comportement » appelle une ancre `#convention-<nom>` et un avant et un après sur la page,
	 * « forme » une démonstration sur le composant que la convention nomme alors.
	 */
	const NOT_DEMONSTRATED = [
		'Un geste ne déplace jamais le défilement' => 'comportement : un défilement qui saute ne se voit qu\'au geste et à la réponse du serveur, ni une page statique ni une capture ne le montrent ; reste à la relecture dans Chrome',
		'Le survol se neutralise sous les petits écrans.' => 'comportement : le socle neutralise le survol par body[data-touch] ou @media (hover), selon l\'appareil et non la largeur ; une capture n\'en montre rien, et le titre parle de petits écrans',
		'Le vocabulaire est celui du produit' => 'forme : aucune, la convention porte sur des mots ; à sortir de la section ou à démontrer par un lexique',
	];

	/**
	 * Le plafond de `NOT_DEMONSTRATED`, qui suit la liste à la baisse et ne se relève pas.
	 */
	const NOT_DEMONSTRATED_MAX = 3;

	private function read(string $file): string {

		$path = LIME_DIRECTORY.'/'.$file;

		$this->assertTrue(is_file($path), "le fichier ".$file." existe encore");

		return (string)file_get_contents($path);

	}

	/**
	 * Les conventions d'une section, par leur titre : chaque puce de premier niveau qui ouvre par
	 * du gras, avec tout son texte, sous-puces comprises.
	 * @return array<string, string>
	 */
	public static function getConventions(string $skill, string $section): array {

		if(preg_match('/\n## '.preg_quote($section, '/').'\n(.*?)(?=\n## |\z)/s', $skill, $matches) !== 1) {
			return [];
		}

		preg_match_all('/^- \*\*(.+?)\*\*(.*?)(?=^- \*\*|\z)/ms', $matches[1], $bullets, PREG_SET_ORDER);

		$conventions = [];

		foreach($bullets as [$text, $title]) {
			$conventions[preg_replace('/\s+/', ' ', $title)] = $text;
		}

		return $conventions;

	}

	/**
	 * Les identifiants de démonstration qu'un texte nomme entre accents graves : classes du design
	 * system et ancres de convention.
	 * @return string[]
	 */
	public static function getIdentifiers(string $text): array {

		preg_match_all('/`([^`]+)`/', $text, $spans);
		preg_match_all('/(?<![a-zA-Z0-9_-])(?:util-[a-z0-9-]*[a-z0-9]|badge(?:-[a-z0-9]+)*|#convention-[a-z0-9-]*[a-z0-9])(?![a-z0-9-])/', join("\n", $spans[1]), $matches);

		return array_values(array_unique($matches[0]));

	}

	/**
	 * Un identifiant existe sur la page quand elle écrit la classe en clair, ou qu'elle pose
	 * l'ancre dans un attribut `id`.
	 */
	public static function isOnPage(string $identifier, string $page): bool {

		if(str_starts_with($identifier, '#')) {
			return str_contains($page, 'id="'.substr($identifier, 1).'"');
		}

		return (preg_match('/(?<![a-zA-Z0-9_-])'.preg_quote($identifier, '/').'(?![a-z0-9-])/', $page) === 1);

	}

	/**
	 * @return string[] les titres des conventions dont aucun identifiant n'est sur la page
	 */
	public static function findNotDemonstrated(array $conventions, string $page): array {

		$found = [];

		foreach($conventions as $title => $text) {

			$onPage = array_filter(self::getIdentifiers($text), fn(string $identifier) => self::isOnPage($identifier, $page));

			if($onPage === []) {
				$found[] = $title;
			}

		}

		return $found;

	}

	public function testDetectors(): void {

		$skill = <<<'MD'

			## Avant

			- **Hors section**, sans identifiant.

			## Conventions de l'interface

			Une phrase d'introduction.

			- **Une forme démontrée** par `util-shown`, et `util-gone` qui n'existe plus.
			- **Un comportement
			  démontré** par son ancre `#convention-shown`.
			- **Une forme sans rien**.
			- **Une classe disparue** : `util-gone`.
			  - **Une sous-puce** qui nomme `util-shown-not`.
			- **Une ancre absente** : `#convention-gone`, et util-shown écrit sans accents graves.
			- **Une marque** en `badge badge-shown`.

			## Après

			- **Hors section aussi**.
			MD;

		$page = '<div class="util-shown badge-shown"></div><div id="convention-shown" class="util-shown-more"></div>';

		$conventions = self::getConventions(str_replace("\t", '', $skill), self::SECTION);

		$this->assertEquals(['Une forme démontrée', 'Un comportement démontré', 'Une forme sans rien', 'Une classe disparue', 'Une ancre absente', 'Une marque'], array_keys($conventions));
		$this->assertEquals(['Une forme sans rien', 'Une classe disparue', 'Une ancre absente'], self::findNotDemonstrated($conventions, $page));

	}

	public function testEveryConventionIsDemonstrated(): void {

		$conventions = self::getConventions($this->read(self::SKILL), self::SECTION);

		$this->assertTrue(count($conventions) >= 20, "la section « ".self::SECTION." » de ".self::SKILL." ne se lit plus (".count($conventions)." conventions lues, 28 le 2026-10-01) : aucune convention ne paraîtrait sans démonstration");

		$found = self::findNotDemonstrated($conventions, $this->read(self::PAGE));

		$missing = array_values(array_diff($found, array_keys(self::NOT_DEMONSTRATED)));

		$this->assertEquals([], $missing, "conventions du skill ui sans démonstration sur la page du design system (le skill fait foi pour la convention : écrire sa démonstration dans ".self::PAGE.", puis nommer dans la convention, entre accents graves, la classe util-* ou l'ancre #convention-<nom> qui la porte ; NOT_DEMONSTRATED ne reçoit rien de neuf) : ".join(' · ', $missing));

		$stale = array_values(array_diff(array_keys(self::NOT_DEMONSTRATED), $found));

		$this->assertEquals([], $stale, "conventions déclarées dans NOT_DEMONSTRATED qui sont maintenant démontrées, ou que le skill ui ne porte plus sous ce titre (retirer la déclaration et baisser NOT_DEMONSTRATED_MAX ; un titre reformulé garde sa déclaration sous son nouveau titre) : ".join(' · ', $stale));

		$this->assertEquals(self::NOT_DEMONSTRATED_MAX, count(self::NOT_DEMONSTRATED), "NOT_DEMONSTRATED porte ".count(self::NOT_DEMONSTRATED)." conventions et NOT_DEMONSTRATED_MAX vaut ".self::NOT_DEMONSTRATED_MAX." : le plafond suit la liste à la baisse et ne se relève jamais, une convention nouvelle arrive avec sa démonstration");

	}

}
?>
