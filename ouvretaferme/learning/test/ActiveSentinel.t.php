<?php
namespace learning;

/**
 * L'interrupteur des formations (lot ouverture-6659) : `LearningSetting::$active` ouvre tout,
 * `LearningSetting::isActiveAnywhere()` dit qu'une structure au moins est ouverte. Le reste du
 * package passe par la structure (`Structure::acceptLearning()`, `Course::acceptLearning()`), et
 * c'est ce qui borne une structure absente de `LearningSetting::STRUCTURE_BETA`.
 *
 * Une lecture ajoutée de l'interrupteur décide seule de ce qu'elle ouvre, sans passer par la
 * structure : un écran gardé par `isActiveAnywhere()` s'ouvre à tout le parc dès que la liste porte
 * une structure, un écran gardé par `$active` reste fermé à la structure listée. Ce contrôle oblige
 * chaque lecture à être déclarée ici, avec ce qu'elle garde et pourquoi ce n'est pas la structure
 * qui répond.
 *
 * Les tests sont écartés : ils posent l'interrupteur pour jouer la production.
 *
 * Lancement : dev/test package=learning file=ActiveSentinel
 */
class ActiveSentinelTest extends \dev\SentinelTest {

	const RULE = 'lecture de l\'interrupteur des formations';

	/**
	 * Le seul fichier où `self::$active` désigne l'interrupteur.
	 */
	const SETTING = 'ouvretaferme/learning/Learning.s.php';

	/**
	 * Les lectures, par fichier : leur nombre, et ce qu'elles gardent.
	 */
	const DECLARED = [
		'base/structure/module/Structure.e.php' => [1, 'Structure::acceptLearning(), la question posée à la structure : ouverte à tous, ou listée'],
		'ouvretaferme/learning/Learning.s.php' => [1, 'isActiveAnywhere(), qui la compose avec la liste'],
		'ouvretaferme/learning/module/Course.e.php' => [3, 'une formation de l\'équipe, sans structure, ne suit que l\'ouverture à tous ; le catalogue public, qui mêle les structures, aussi ; les offres du catalogue public, que rien n\'encaisse hors ouverture à tous'],
		'ouvretaferme/learning/module/Supervisor.e.php' => [1, 'un encadrant de l\'équipe, sans structure, ne s\'écrit que là où une formation de l\'équipe s\'écrit, à l\'ouverture à tous'],
		'ouvretaferme/learning/lib/Course.l.php' => [1, 'le menu du compte, qui paraît à tous quand tout est ouvert, et sinon selon les structures du compte'],
		'ouvretaferme/learning/lib/DateEnrollment.l.php' => [1, 'les crons balaient toute la base : bornés aux formations des structures listées tant que tout n\'est pas ouvert'],
		'ouvretaferme/learning/lib/Review.l.php' => [1, 'la notification d\'un avis neuf, qui ne part que catalogue public ouvert ; fermé, seul le compteur de la boite des avis du réseau le dit'],
		'ouvretaferme/learning/lib/Share.l.php' => [1, 'le cron des annonces balaie toute la base : borné aux formations des structures listées tant que tout n\'est pas ouvert, comme les crons des dates'],
		'ouvretaferme/learning/lib/User.o.php' => [1, 'l\'observateur de connexion, sans structure sous la main ; les seules lignes sans compte sont celles des pages publiques ouvertes'],
		'ouvretaferme/learning/page/mine.p.php' => [2, '« Mes formations », page d\'un compte, qui ne liste que ce que acceptRead() laisse lire ; son bouton vers le catalogue public, qui ne paraît qu\'avec lui'],
		'ouvretaferme/learning/page/course.p.php' => [1, 'le lien du menu de la fiche vers le catalogue public, qui ne suit pas la structure de la formation lue'],
		'ouvretaferme/learning/page/cron/dateEnrollment.p.php' => [2, 'le rappel et l\'invitation à l\'avis, dont les requêtes sont bornées par DateEnrollmentLib'],
		'ouvretaferme/learning/page/cron/share.p.php' => [1, 'l\'annonce des formations partagées, dont la requête est bornée par ShareLib'],
		'ouvretaferme/learning/page/cron/course.p.php' => [1, 'la marque des formations dont une date vient de finir, recalculée sans borne de structure : elle ne montre que les modes de séances déjà publiées, et n\'ouvre rien'],
		'ouvretaferme/learning/page/catalog.p.php' => [1, 'le catalogue public, qui mêle les formations de toutes les structures'],
		'ouvretaferme/learning/page/account.p.php' => [1, 'le compte pour se former, créé sans structure depuis le catalogue public ou une de ses formations, fermés hors ouverture à tous'],
		'ouvretaferme/learning/page/admin/catalog.p.php' => [1, 'l\'administration du catalogue public'],
		'ouvretaferme/learning/page/admin/course.p.php' => [1, 'l\'administration des formations de l\'équipe'],
		'ouvretaferme/learning/page/admin/review.p.php' => [1, 'la modération des avis, qui n\'existent qu\'au catalogue public'],
		'ouvretaferme/learning/page/admin/supervisor.p.php' => [1, 'l\'administration des encadrants de l\'équipe'],
		'ouvretaferme/learning/page/admin/theme.p.php' => [1, 'les thèmes du catalogue public'],
		'base/user/view/signUp.v.php' => [2, 'le bloc « Autre », qui s\'ouvre aussi pour lui, et « Je veux seulement me former », qui mène à /formations/compte puis au catalogue public, fermés hors ouverture à tous'],
		'ouvretaferme/main/ui/Admin.u.php' => [1, 'l\'onglet de l\'administration, qui suit ses pages'],
		'ouvretaferme/main/view/Base.php' => [1, 'le lien public « Formations », vers le catalogue public'],
		'ouvretaferme/main/view/index.v.php' => [1, 'l\'accueil connecté, qui mène au catalogue public'],
	];

	/**
	 * Les lignes qui lisent `LearningSetting::$active` ou `LearningSetting::isActiveAnywhere()`, et
	 * `self::$active` dans le seul fichier de l'interrupteur.
	 *
	 * @param array<int, array{0: int|string, 1: string, 2: int}> $tokens
	 * @return int[]
	 */
	public static function scan(array $tokens, bool $isSetting): array {

		$lines = [];

		foreach($tokens as $i => $token) {

			$class = ltrim($token[1], '\\');

			if(
				in_array($class, ['LearningSetting', 'learning\\LearningSetting'], TRUE) === FALSE and
				($isSetting === FALSE or $class !== 'self')
			) {
				continue;
			}

			if(($tokens[$i + 1][0] ?? NULL) !== T_DOUBLE_COLON) {
				continue;
			}

			$member = $tokens[$i + 2] ?? [NULL, NULL, NULL];

			if(
				($member[0] === T_VARIABLE and $member[1] === '$active') or
				($member[0] === T_STRING and $member[1] === 'isActiveAnywhere')
			) {
				$lines[] = $token[2];
			}

		}

		return $lines;

	}

	public function testDetector(): void {

		$source = <<<'PHP'
			<?php
			$a = \learning\LearningSetting::$active;
			$b = LearningSetting::isActiveAnywhere();
			$c = self::$active;
			$d = LearningSetting::STRUCTURE_BETA;
			$e = self::$activeByDay;
			// LearningSetting::$active dans un commentaire
			$f = OtherSetting::$active;
			PHP;

		$tokens = \dev\SentinelLib::getTokens($source);

		$this->assertEquals([2, 3], self::scan($tokens, FALSE), 'hors du fichier de l\'interrupteur');
		$this->assertEquals([2, 3, 4], self::scan($tokens, TRUE), 'dans le fichier de l\'interrupteur');

	}

	public function testActive(): void {

		foreach($this->getPhpTokens() as $file => $tokens) {

			if(
				str_ends_with($file, '.t.php') or
				str_contains('/'.$file, '/test/')
			) {
				continue;
			}

			$this->addFound(self::RULE, $file, self::scan($tokens, $file === self::SETTING));

		}

		$this->assertRatchet(self::RULE, array_map(fn(array $declared) => $declared[0], self::DECLARED), 'lecture de l\'interrupteur à déclarer dans DECLARED avec ce qu\'elle garde, ou à remplacer par acceptLearning() sur la structure');

	}

}
?>
