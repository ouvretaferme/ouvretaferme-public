<?php
namespace series;

/**
 * Test-sentinelle : une semaine ISO se rattache à son mois par une seule règle.
 *
 * `\util\DateLib::convertWeeksToMonths()` rend le mois du **jeudi** de la semaine, c'est-à-dire
 * celui qui en porte le plus de jours. C'est la règle de `build()` (`Cultivation.e.php`, posé
 * dans `properties()`, donc jouée aussi par l'API), du réalisé et de l'affichage.
 * `CultivationLib::updateHarvestPeriodExpected()` rattachait la semaine à son **lundi**, et
 * écrit hors `build()` : 56 des 574 semaines ISO de 2020 à 2030 changent de mois selon la règle
 * lue, et 381 cultures portaient l'écart avant la reprise du lot L0.
 *
 * Le défaut ne lève rien et ne se voit pas : un écran qui pose le prévu à côté du réalisé les
 * décale d'un mois une fois sur dix, avec deux chiffres crédibles. Celui qui rouvrira le sujet
 * travaillera dans un autre package, des mois plus tard, sans raison de lire ce fichier — d'où
 * un inventaire plutôt qu'une phrase. La règle avait d'ailleurs déjà été écrite dans
 * `CLAUDE.md`, avec un chiffre que plus aucune lecture ne retrouve : la forme « règle écrite »
 * n'a pas tenu.
 *
 * Il tient trois inventaires, chacun dans les deux sens :
 *
 * 1. les appelants de `convertWeeksToMonths()`, qui ne doivent pas disparaître au profit d'un
 *    calcul local ;
 * 2. les sites qui manipulent une semaine autrement, sur l'union des formes rencontrées dans le
 *    dépôt (`date('Y-m'|'n'|'m', …)` sur une expression de semaine, décalage au jeudi,
 *    `monday this week`, `YEARWEEK()` en SQL), chacun déclaré avec la raison pour laquelle il
 *    est admis ;
 * 3. la règle elle-même, parce que les deux premiers resteraient verts si `DateLib` basculait
 *    au lundi — tout le dépôt l'appelle, et c'est exactement ce que le test croirait bon.
 *
 * Les commentaires sont retirés avant l'analyse : une raison qui cite une forme ne doit pas
 * compter comme un site.
 *
 * Lancement : dev/test package=series file=WeekMonthSentinelAi
 */
class WeekMonthSentinelAiTest extends \Test {

	/**
	 * Les formes, autres que `DateLib`, par lesquelles le dépôt touche à une semaine.
	 *
	 * Les deux premières ne comptent que sur une ligne qui parle de semaine : un format de mois
	 * et un décalage de trois jours sont partout dans le dépôt et ne disent rien du sujet — sur
	 * les cinq sites qu'ils rendaient sans cette borne, trois dataient une vente de décor.
	 * ⚠️ Une semaine tenue par une variable qui ne se nomme pas ainsi échappe donc à l'analyse :
	 * c'est le trou de ce test, et il est ici plutôt que tu. **Il a un occupant connu**, trouvé à
	 * la relecture du lot L0 : `series/ui/PlaceGardenAi.u.php:1860-1861` nomme le mois d'un bloc
	 * de jardin par `date('n', $minTs)`, où `$minTs` vient de `PlaceUi::positionToWeek()` avec un
	 * décalage nul, c'est-à-dire le **lundi** de la semaine. Trois lignes séparent la semaine du
	 * mois, aucune ne porte le mot : aucun motif ne peut l'atteindre sans remonter tout le dépôt.
	 * Il ne figure pas dans `OTHERS`, qui n'inventorie que ce que l'analyse trouve — une entrée
	 * qu'elle ne peut pas voir s'y lirait comme une déclaration périmée. Ce site n'écrit rien en
	 * base, il pose un libellé ; le corriger appartient au chantier de l'assolement.
	 */
	const PATTERNS = [
		'month-of-week' => ["/date\(\s*'(Y-m|Y-n|n|m)'/", TRUE],
		'thursday-shift' => ['/\+\s*3\s+DAYS?/i', TRUE],
		'monday-shift' => ['/monday this week/i', FALSE],
		'sql-yearweek' => ['/YEARWEEK\s*\(/', FALSE],
	];

	/** Le mot qui rattache une ligne au sujet, pour les motifs qui l'exigent. */
	const WEEK_WORDS = '/week|semaine/i';

	/**
	 * Les appelants de `\util\DateLib::convertWeeksToMonths()`, par fichier, avec leur nombre.
	 *
	 * Le nombre est déclaré pour qu'un appel retiré au profit d'un calcul local se voie : c'est
	 * exactement le chemin par lequel la divergence était née.
	 */
	const CONVERTED = [
		'series/module/Cultivation.e.php' => [1, 'replaceSet() de harvestWeeksExpected : la règle de build(), et de l\'API avec lui'],
		'series/lib/Cultivation.l.php' => [2, 'updateHarvestPeriodExpected() après la reprise du lot L0, et le recalcul de la fenêtre de récolte'],
		'series/lib/Series.l.php' => [3, 'décalage et copie d\'une série : les mois suivent les semaines déplacées, y compris quand la copie décalée passe du mois à la semaine'],
		'sequence/lib/Crop.l.php' => [2, 'le mois d\'une semaine de flux, pris une semaine à la fois'],
		'planning/lib/TaskYear.l.php' => [1, 'l\'Année des interventions : le mois en gras au-dessus des colonnes et la première semaine de chaque mois, rangée au mois de son jeudi'],
		'toolbox/lib/Composition.l.php' => [1, 'l\'axe de la grille des paniers, une colonne par semaine de la saison, rangée au mois de son jeudi'],
	];

	/**
	 * Les sites qui manipulent une semaine sans passer par `DateLib`, avec leur raison.
	 *
	 * Une entrée dit pourquoi ce site n'a pas à appeler `convertWeeksToMonths()`. Deux raisons
	 * seulement sont recevables : il ne dérive aucun mois, ou il dérive le même que `DateLib`
	 * sur une valeur que la fonction ne sait pas prendre.
	 */
	const OTHERS = [
		'series/lib/Task.l.php' => [
			'month-of-week' => [1, 'récolte réalisée : le mois du jeudi de doneWeek, même règle que DateLib, sur une semaine seule et non sur une liste'],
			'thursday-shift' => [1, 'le décalage qui porte ce mois-là'],
			'sql-yearweek' => [1, 'reconstruit la semaine ISO d\'un pointage en SQL, aucun mois dérivé'],
		],
		'series/ui/Task.u.php' => [
			'month-of-week' => [1, 'affichage : le mois du jeudi, même règle que DateLib, rendu en numéro de mois et non en Y-m'],
			'thursday-shift' => [1, 'le décalage qui porte ce mois-là'],
		],
		'series/lib/Cultivation.l.php' => [
			'monday-shift' => [2, 'cale une borne de campagne sur son lundi pour avancer de semaine en semaine par le calendrier : une borne de semaine, aucun mois dérivé'],
		],
		'amap/lib/DistributionHolder.l.php' => [
			'monday-shift' => [2, 'rythme des dates d\'un producteur : compte les semaines d\'un lundi à l\'autre, pour qu\'une distribution avancée dans sa semaine garde son rang, aucun mois dérivé'],
		],
		'series/ui/Planning.u.php' => [
			// Le seul site qui refuse de trancher, et il a raison de le faire : le filtre par
			// mois du planning annuel doit atteindre une semaine à cheval depuis ses deux mois.
			'month-of-week' => [2, 'pose les deux mois que touche la semaine dans data-months, une appartenance et non un rattachement'],
		],
		'selling/lib/SchoolAi.l.php' => [
			'sql-yearweek' => [2, 'agrège les ventes d\'une semaine ISO sur la date de son lundi, pour la ferme école puis pour le décompte de la duplication, aucun mois dérivé'],
		],
		'selling/lib/Dashboard.l.php' => [
			'sql-yearweek' => [2, 'bilan et chiffre de la semaine de l\'accueil : groupe les ventes livrées par semaine ISO, en sélection puis en groupement, et n\'en rend que le numéro de semaine, aucun mois dérivé'],
		],
		'learning/lib/Usage.l.php' => [
			'monday-shift' => [2, 'bilan d\'une formation : rattache une date déjà lue au lundi de sa semaine, jumeau PHP du WEEKDAY() de getStepSql(), et cale le début de la période sur son lundi pour que chaque semaine soit entière, aucun mois dérivé'],
		],
	];

	/** Les fichiers PHP du produit, indexés par leur chemin depuis le package. */
	private function getFiles(): array {

		$files = [];

		foreach(\Package::getList() as $package => $app) {

			$path = \Package::getPath($package);

			if(is_dir($path) === FALSE) {
				continue;
			}

			$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));

			foreach($iterator as $file) {

				$pathname = $file->getPathname();

				if(
					str_ends_with($file->getFilename(), '.php') === FALSE or
					str_contains($pathname, '/nodejs/') or
					str_contains($pathname, '/perso/') or
					// Un décor a le droit de fabriquer une date comme il veut : ce qui est en
					// jeu est ce que le produit écrit en base, pas ce qu'un test lui donne.
					str_contains($pathname, '/test/')
				) {
					continue;
				}

				$files[$package.'/'.substr($pathname, strlen($path) + 1)] = $pathname;

			}

		}

		ksort($files);

		return $files;

	}

	/** Le code d'un fichier, commentaires retirés, ligne à ligne et numérotées depuis 1. */
	private function getLines(string $path): array {

		$lines = [];
		$line = 1;

		foreach(token_get_all(file_get_contents($path)) as $token) {

			if(is_array($token) === FALSE) {
				$lines[$line] = ($lines[$line] ?? '').$token;
				continue;
			}

			$text = ($token[0] === T_COMMENT or $token[0] === T_DOC_COMMENT)
				? str_repeat("\n", substr_count($token[1], "\n"))
				: $token[1];

			$line = $token[2];

			foreach(explode("\n", $text) as $index => $part) {
				$lines[$line + $index] = ($lines[$line + $index] ?? '').$part;
			}

			$line += substr_count($text, "\n");

		}

		return $lines;

	}

	/**
	 * Les manipulations de semaine d'un fichier, par motif et par ligne.
	 *
	 * @return array<string, int[]>
	 */
	private function scan(string $path): array {

		$found = [];

		foreach($this->getLines($path) as $number => $code) {

			foreach(static::PATTERNS as $pattern => [$regexp, $needsWeek]) {

				if(preg_match($regexp, $code) === 0) {
					continue;
				}

				if($needsWeek and preg_match(static::WEEK_WORDS, $code) === 0) {
					continue;
				}

				$found[$pattern][] = $number;

			}

		}

		return $found;

	}

	/** Les appels à `convertWeeksToMonths()` d'un fichier, par ligne. */
	private function scanConverted(string $path): array {

		$found = [];

		foreach($this->getLines($path) as $number => $code) {

			if(str_contains($code, 'DateLib::convertWeeksToMonths(')) {
				$found[] = $number;
			}

		}

		return $found;

	}

	/**
	 * Toute manipulation de semaine hors `DateLib` est déclarée, avec sa raison.
	 */
	public function testEveryOtherDerivationIsDeclared(): void {

		$undeclared = [];
		$files = 0;

		foreach($this->getFiles() as $relative => $path) {

			$files++;

			$declared = static::OTHERS[$relative] ?? [];

			foreach($this->scan($path) as $pattern => $lines) {

				$count = array_key_exists($pattern, $declared) ? $declared[$pattern][0] : 0;

				if(count($lines) > $count) {
					$undeclared[] = $relative.':'.implode(',', $lines).' · '.$pattern.' · '.count($lines).' trouvée(s), '.$count.' déclarée(s)';
				}

			}

		}

		// Sans plancher, une analyse qui ne lirait plus rien rendrait une liste vide, donc vert.
		$this->assertTrue($files > 500, 'le balayage a lu '.$files.' fichiers, il en lisait plus de 500');

		$this->assertEquals([], $undeclared, 'rattachement d\'une semaine non déclaré : '.implode(' | ', $undeclared));

	}

	/**
	 * Toute déclaration correspond encore à un site réel.
	 *
	 * Sans ce sens-là, une entrée survit au code qu'elle couvrait et couvre demain un site que
	 * personne n'a relu.
	 */
	public function testEveryDeclarationStillMatches(): void {

		$files = $this->getFiles();
		$stale = [];

		foreach(static::OTHERS as $relative => $patterns) {

			if(array_key_exists($relative, $files) === FALSE) {
				$stale[] = $relative.' · fichier introuvable';
				continue;
			}

			$found = $this->scan($files[$relative]);

			foreach($patterns as $pattern => [$count, $reason]) {

				$real = count($found[$pattern] ?? []);

				if($real < $count) {
					$stale[] = $relative.' · '.$pattern.' · '.$count.' déclarée(s), '.$real.' trouvée(s)';
				}

			}

		}

		foreach(static::CONVERTED as $relative => [$count, $reason]) {

			if(array_key_exists($relative, $files) === FALSE) {
				$stale[] = $relative.' · fichier introuvable';
				continue;
			}

			$real = count($this->scanConverted($files[$relative]));

			if($real !== $count) {
				$stale[] = $relative.' · convertWeeksToMonths · '.$count.' déclaré(s), '.$real.' trouvé(s)';
			}

		}

		$this->assertEquals([], $stale, 'déclaration dont le site a disparu ou changé de compte : '.implode(' | ', $stale));

	}

	/**
	 * Tout appel à `convertWeeksToMonths()` est déclaré.
	 */
	public function testEveryConvertedCallIsDeclared(): void {

		$undeclared = [];

		foreach($this->getFiles() as $relative => $path) {

			$lines = $this->scanConverted($path);

			if($lines === []) {
				continue;
			}

			$count = array_key_exists($relative, static::CONVERTED) ? static::CONVERTED[$relative][0] : 0;

			if(count($lines) > $count) {
				$undeclared[] = $relative.':'.implode(',', $lines).' · '.count($lines).' appel(s), '.$count.' déclaré(s)';
			}

		}

		$this->assertEquals([], $undeclared, 'appel à convertWeeksToMonths() non déclaré : '.implode(' | ', $undeclared));

	}

	/**
	 * La règle que tout le dépôt appelle est toujours celle du jeudi.
	 *
	 * Les trois inventaires resteraient verts si `DateLib` basculait au lundi : ils comptent
	 * des sites, pas des résultats. C'est ici que la règle se prouve.
	 */
	public function testConvertWeeksToMonthsStillReturnsThursdayMonth(): void {

		$diverging = [];
		$several = [];
		$weeks = 0;

		for($year = 2020; $year <= 2030; $year++) {

			$last = (int)date('W', strtotime($year.'-12-28'));

			for($number = 1; $number <= $last; $number++) {

				$week = sprintf('%d-W%02d', $year, $number);
				$weeks++;

				$months = \util\DateLib::convertWeeksToMonths([$week]);

				if(count($months) !== 1) {
					$several[] = $week.' → '.implode(',', $months);
					continue;
				}

				$thursday = date('Y-m', strtotime($week.' + 3 DAYS'));

				if($months[0] !== $thursday) {
					$diverging[] = $week.' → '.$months[0].', jeudi en '.$thursday;
				}

			}

		}

		$this->assertEquals(574, $weeks, 'les semaines ISO de 2020 à 2030 se comptent à 574, le balayage en a vu '.$weeks);
		$this->assertEquals([], $several, 'une semaine rendue sur deux mois, donc plus de règle du tout : '.implode(' | ', $several));
		$this->assertEquals([], $diverging, 'convertWeeksToMonths() ne rend plus le mois du jeudi : '.implode(' | ', $diverging));

	}

}
?>
