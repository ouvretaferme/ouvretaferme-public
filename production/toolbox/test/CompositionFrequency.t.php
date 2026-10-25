<?php
namespace toolbox;

/**
 * La fréquence choisie sur une espèce (lot L9, planche v2 tranchée le 26/09) : ce que la
 * génération en fait (`CompositionLib::applyFrequencies()`, `propose()`), la fréquence courante que
 * la grille donne aux autres (`getFrequencyCurrent()`), et le geste qui l'écrit, par `build()` et le
 * Lib, jusqu'au panneau « Régler une espèce » et à sa roue pleine.
 *
 * La règle s'éprouve sur des relevés construits à la main, comme `CompositionRuleTest` : un relevé
 * réel ne garantit pas des fenêtres assez longues pour qu'un rythme s'y lise, et un test qui dépend
 * de la nuit précédente ne dit rien de la règle. Le geste s'éprouve sur un plan écrit par le départ
 * sur la saison 2099, qu'aucune structure n'emploie.
 *
 * Isolation : une transaction par méthode, annulée en tearDown().
 *
 * Lancement : dev/test package=toolbox file=CompositionFrequency
 */
class CompositionFrequencyTest extends \Test {

	const SEASON = 2026;

	/**
	 * Les bornes du nombre de lignes d'avant r11, 4 et 10, que ce fichier passe à la règle : ses
	 * décomptes épinglés ont été mesurés avec elles. Les bornes réglées par formule ont leurs propres
	 * tests (`CompositionRuleTest::testLinesBounds()`).
	 */
	const LINES_MIN = 4;
	const LINES_MAX = 10;

	/**
	 * L'écart admis entre la part des semaines où une espèce réglée est servie et son rythme : les
	 * 7 points que la planche a mesurés sur neuf réglages de la structure 7.
	 */
	const RHYTHM_GAP = 0.07;

	/**
	 * Dix espèces dont les parts somment aux quatre lignes du panier, celles de
	 * `CompositionRuleTest::testDebtFollowsFrequency()` : de la plus servie à la plus rare.
	 */
	const SHARES = [1 => 0.9, 2 => 0.8, 3 => 0.6, 4 => 0.5, 5 => 0.4, 6 => 0.3, 7 => 0.2, 8 => 0.15, 9 => 0.1, 10 => 0.05];

	/**
	 * L'espèce seule de son organe (lot r44) : la plus rare des dix, le seul fruit d'un relevé de
	 * feuilles et de racines (`getSourceOrgans()`). C'est la Courge de l'hiver, que le plancher
	 * d'organes appelle chaque semaine.
	 */
	const PLANT_ALONE = 10;

	/**
	 * Les deux variantes de l'espèce 1 (`getSourceVariants()`), hors des ids de `SHARES`.
	 */
	const VARIANT_EARLY = 11;
	const VARIANT_LATE = 12;

	const ORGANS = [
		1 => \plant\Plant::LEAF,
		2 => \plant\Plant::ROOT,
		3 => \plant\Plant::LEAF,
		4 => \plant\Plant::ROOT,
		5 => \plant\Plant::LEAF,
		6 => \plant\Plant::ROOT,
		7 => \plant\Plant::LEAF,
		8 => \plant\Plant::ROOT,
		9 => \plant\Plant::LEAF,
		10 => \plant\Plant::FRUIT,
	];

	/**
	 * L'empreinte des propositions sans fréquence choisie (`getProposalsWithoutFrequency()`), mesurée
	 * sur la règle d'avant le lot r44 (`55ad6f4a3`).
	 */
	const PROPOSALS_BEFORE = [
		'sans organe à 4 €' => '187a4558e76a223f4966ed46b7803a9d',
		'organes à 4 €' => '1dfc5f120415d12382bacbaed8614eb3',
		'organes à 6 €' => '6c24f99d5ebc116c6b96308c0223d51d',
		'dérivée à 6 €' => '4ab3f1caaf2d425533b0d05a3265fdb3',
	];

	/**
	 * Ce que le plafond d'une fréquence choisie laisse servir sur quarante semaines de suite à une
	 * espèce que le plancher appelle chaque semaine : une semaine sur deux, sur trois, sur cinq, et
	 * trois semaines sur quatre.
	 */
	const SERVED_AT_CEILING = [
		CompositionSpecies::BASE => 30,
		CompositionSpecies::MEDIUM => 20,
		CompositionSpecies::LOW => 14,
		CompositionSpecies::DIVERSIFICATION => 8,
	];

	private \user\User $eUserInitial;

	/**
	 * Le prix de la première taille du plan écrit par le départ : un prix écrit plutôt que la médiane
	 * des paniers partagés, pour que le test ne lise pas l'agrégé.
	 */
	const PRICE = 16.0;

	public function setUp(): void {

		CompositionPlan::model()->beginTransaction();

		$this->eUserInitial = \user\ConnectionLib::getOnline();

	}

	public function tearDown(): void {

		\user\ConnectionLib::setOnline($this->eUserInitial);

		CompositionPlan::model()->rollBack();

	}

	/**
	 * Sans fréquence choisie, le relevé revient tel quel, et la proposition est celle d'avant le lot,
	 * octet pour octet : sans le paramètre, avec une liste vide, et avec une fréquence posée sur une
	 * espèce qui n'est candidate d'aucune semaine. Le pendant : une vraie fréquence change la
	 * proposition, sans quoi l'égalité ne prouverait rien.
	 */
	public function testWithoutFrequencyUnchanged(): void {

		$weeks = range(5, 44);
		$source = $this->getSource($weeks);

		$proposal = CompositionLib::propose(4.0, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $source, [], []);

		$this->assertEquals($source, CompositionLib::applyFrequencies(4.0, self::LINES_MIN, self::LINES_MAX, $weeks, self::SEASON, NULL, $source, [], []), 'relevé sans fréquence');
		$this->assertEquals($source, CompositionLib::applyFrequencies(4.0, self::LINES_MIN, self::LINES_MAX, $weeks, self::SEASON, NULL, $source, [], [99 => CompositionSpecies::BASE]), 'relevé avec une fréquence hors des candidates');

		$this->assertEquals(serialize($proposal), serialize(CompositionLib::propose(4.0, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $source, [], [], 0, [])), 'proposition avec une liste vide');
		$this->assertEquals(serialize($proposal), serialize(CompositionLib::propose(4.0, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $source, [], [], 0, [99 => CompositionSpecies::BASE])), 'proposition avec une fréquence hors des candidates');
		$this->assertDiffer(serialize($proposal), serialize(CompositionLib::propose(4.0, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $source, [], [], 0, [10 => CompositionSpecies::BASE])), 'une vraie fréquence change la proposition');

	}

	/**
	 * Une espèce réglée tient son rythme à 7 points près, qu'on la monte ou qu'on la descende : la
	 * plus servie mise en diversification, la plus rare en base, et les deux du milieu dans les deux
	 * autres fréquences. Les espèces qui ne sont pas réglées cèdent ce que la réglée prend, et
	 * reprennent ce qu'elle laisse : le panier garde ses quatre lignes.
	 */
	public function testChosenHoldsRhythm(): void {

		$weeks = range(5, 44);
		$source = $this->getSource($weeks);

		$served = $this->countServed(CompositionLib::propose(4.0, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $source, [], []));

		$far = [];

		foreach([[1, CompositionSpecies::DIVERSIFICATION], [1, CompositionSpecies::LOW], [10, CompositionSpecies::BASE], [10, CompositionSpecies::MEDIUM], [4, CompositionSpecies::LOW], [6, CompositionSpecies::BASE], [5, CompositionSpecies::DIVERSIFICATION]] as [$plantId, $frequency]) {

			$proposal = CompositionLib::propose(4.0, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $source, [], [], 0, [$plantId => $frequency]);
			$servedChosen = $this->countServed($proposal);

			$rate = $servedChosen[$plantId] / count($weeks);
			$target = CompositionLib::getFrequencyTarget($frequency);

			if(abs($rate - $target) > self::RHYTHM_GAP) {
				$far[] = $plantId.' en '.$frequency.' servie '.$servedChosen[$plantId].' fois sur '.count($weeks);
			}

			$others = array_sum(array_diff_key($served, [$plantId => TRUE]));
			$othersChosen = array_sum(array_diff_key($servedChosen, [$plantId => TRUE]));

			if($servedChosen[$plantId] > $served[$plantId]) {
				$this->assertTrue($othersChosen < $others, $plantId.' en '.$frequency.' : les autres cèdent ('.$others.' puis '.$othersChosen.')');
			} else {
				$this->assertTrue($othersChosen > $others, $plantId.' en '.$frequency.' : les autres reprennent ('.$others.' puis '.$othersChosen.')');
			}

			foreach($proposal as $week => $basket) {
				$this->assertEquals(4, count($basket['lines']), 'quatre lignes en semaine '.$week);
			}

		}

		$this->assertEquals([], $far, 'espèces réglées à plus de 7 points de leur rythme : '.implode(', ', $far));

	}

	/**
	 * Une fréquence réglée tient son rythme sur un relevé modulé par la saison des ventes (lot L8),
	 * comme sur le relevé plat : la modulation passe avant `applyFrequencies()`, qui remplace la part
	 * des espèces réglées et ramène les autres à l'échelle.
	 *
	 * Les poids vont de 0,5 à 2, le cœur de ceux de la table de dev (médiane 1,34, quartiles 0,73 et
	 * 2,39 le 2026-09-26). ⚠️ Au-delà, le rythme dérive : sur ce relevé de dix espèces
	 * dont les parts somment aux quatre lignes, des poids de 0,1 à 6 font servir les sept réglages
	 * de 7,5 à 17 points au-dessus de leur rythme. `getShares()` plafonne à 1 une espèce non réglée
	 * et rend ce qu'elle laisse aux autres, réglées comprises, ce que la formule de
	 * `applyFrequencies()` ne prévoit pas. Sur la base de dev, aucune part modulée ne dépasse 0,74 et
	 * la part à l'année la plus haute est de 0,44 : le plafond n'y mord pas, et c'est le script
	 * avant/après du lot qui mesure les réglages sur des plans réels.
	 *
	 * Selon la saison, la proposition est celle du relevé modulé, et elle diffère de celle du relevé
	 * plat, sans quoi le contrôle ne prouverait rien.
	 */
	public function testChosenHoldsRhythmOnSeasonalSource(): void {

		$weeks = range(5, 44);
		$sourceFlat = $this->getSource($weeks);
		$source = $this->getSourceSeasonal($sourceFlat, [0.5, 0.6, 0.8, 1.0, 1.2, 1.5, 2.0, 1.5, 1.2, 1.0, 0.8, 0.6]);

		$this->assertEquals($source, CompositionLib::applyFrequencies(4.0, self::LINES_MIN, self::LINES_MAX, $weeks, self::SEASON, NULL, $source, [], []), 'sans réglage, le relevé modulé revient tel quel');
		$this->assertDiffer(
			serialize(CompositionLib::propose(4.0, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $sourceFlat, [], [])),
			serialize(CompositionLib::propose(4.0, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $source, [], [])),
			'la saison des ventes change la proposition'
		);

		$far = [];

		foreach([[1, CompositionSpecies::DIVERSIFICATION], [1, CompositionSpecies::LOW], [10, CompositionSpecies::BASE], [10, CompositionSpecies::MEDIUM], [4, CompositionSpecies::LOW], [6, CompositionSpecies::BASE], [5, CompositionSpecies::DIVERSIFICATION]] as [$plantId, $frequency]) {

			$servedChosen = $this->countServed(CompositionLib::propose(4.0, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $source, [], [], 0, [$plantId => $frequency]));

			$rate = $servedChosen[$plantId] / count($weeks);

			if(abs($rate - CompositionLib::getFrequencyTarget($frequency)) > self::RHYTHM_GAP) {
				$far[] = $plantId.' en '.$frequency.' servie '.$servedChosen[$plantId].' fois sur '.count($weeks);
			}

		}

		$this->assertEquals([], $far, 'espèces réglées à plus de 7 points de leur rythme sur un relevé modulé : '.implode(', ', $far));

	}

	/**
	 * Une part modulée qui dépasse 1 est plafonnée par `getShares()` après la mise à l'échelle :
	 * aucune espèce n'est attendue plus d'une fois dans un panier. Des poids de 0,1 à 6, pour que
	 * des parts dépassent 1 avant la mise à l'échelle.
	 */
	public function testSharesCappedOnSeasonalSource(): void {

		$weeks = range(5, 44);
		$source = $this->getSourceSeasonal($this->getSource($weeks), [0.1, 0.3, 0.6, 1.0, 1.5, 2.0, 3.0, 4.0, 6.0, 2.5, 0.8, 0.2]);

		$over = array_filter(array_merge(...array_values($source['frequency']['byWeek'])), fn(float $share) => $share > 1.0);

		if($over === []) {
			$this->assertFail('le relevé modulé ne porte aucune part au-dessus de 1 : le contrôle ne verrait rien');
			return;
		}

		$getShares = new \ReflectionMethod(CompositionLib::class, 'getShares');

		$found = [];

		foreach($weeks as $week) {

			foreach($getShares->invoke(NULL, $week, array_keys(self::SHARES), 4, $source) as $plantId => $share) {
				if($share > 1.0 + 1e-9) {
					$found[] = $plantId.' en semaine '.$week.' ('.round($share, 3).')';
				}
			}

		}

		$this->assertEquals([], $found, 'parts au-dessus de 1 après la mise à l\'échelle : '.implode(', ', $found));

	}

	/**
	 * Une fréquence choisie est un plafond (lot r44, retour du 01/10 sur une Courge réglée
	 * à 1 semaine sur 2 et servie neuf semaines de suite) : l'espèce seule de son organe, que le
	 * plancher d'organes appelle chaque semaine, n'est jamais servie deux semaines de suite à 1 sur 2,
	 * ni plus d'une fois sur trois et sur cinq semaines, ni quatre semaines de suite à 3 sur 4. Sur un
	 * panier de quatre lignes et sur un panier de six, où le plancher joue.
	 *
	 * Le plafond est atteint et pas seulement respecté : le décompte est celui d'une espèce servie
	 * dès qu'elle peut l'être (`SERVED_AT_CEILING`), sans quoi une espèce jamais servie passerait le
	 * contrôle. Les semaines où elle attend, le plancher est relâché pour son organe, qui n'a pas
	 * d'autre candidate.
	 */
	public function testChosenIsCeiling(): void {

		$weeks = range(5, 44);
		$source = $this->getSourceOrgans($weeks);

		foreach([4.0, 6.0] as $price) {

			foreach(self::SERVED_AT_CEILING as $frequency => $servedExpected) {

				$proposal = CompositionLib::propose($price, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $source, [], [], 0, [self::PLANT_ALONE => $frequency]);
				$case = $frequency.' à '.$price.' €';

				$this->assertEquals(count($weeks), count($proposal), $case.' : un panier chaque semaine');

				$breaks = $this->getCeilingBreaks($proposal, self::PLANT_ALONE, $frequency);
				$this->assertEquals([], $breaks, $case.' : semaines servies au-delà du plafond : '.implode(', ', $breaks));

				$this->assertEquals($servedExpected, count($this->getServedWeeks($proposal, self::PLANT_ALONE)), $case.' : semaines servies');

				$lean = [];
				$kept = [];

				foreach($proposal as $week => $basket) {

					if(count($basket['lines']) < 4) {
						$lean[] = $week;
					}

					if(
						in_array(self::PLANT_ALONE, array_column($basket['lines'], 'species'), TRUE) === FALSE and
						in_array(\plant\Plant::FRUIT, $basket['relaxedFloor'], TRUE) === FALSE
					) {
						$kept[] = $week;
					}

				}

				$this->assertEquals([], $lean, $case.' : semaines à moins de quatre lignes : '.implode(', ', $lean));
				$this->assertEquals([], $kept, $case.' : semaines sans le fruit où le plancher n\'est pas relâché : '.implode(', ', $kept));

			}

		}

	}

	/**
	 * Le plafond tient aussi quand aucune candidate n'est espacée, là où la semaine garde son ordre
	 * entier plutôt que de rester sans panier (`CompositionLib::getOrderSpaced()`).
	 *
	 * Une espèce réglée et seule candidate n'a de panier que les semaines où son plafond la laisse
	 * entrer : les autres semaines n'ont pas de panier, comme une semaine sans candidate. Avec une
	 * seconde espèce qui n'a rien choisi, chaque semaine a son panier : la seconde revient malgré son
	 * écart, par le repli, et la réglée ne revient pas.
	 */
	public function testCeilingHoldsWithoutSpacedCandidate(): void {

		$weeks = range(5, 16);
		$source = $this->getSource($weeks);

		foreach(array_keys(self::SHARES) as $plantId) {

			if(in_array($plantId, [9, self::PLANT_ALONE], TRUE) === FALSE) {
				foreach($weeks as $week) {
					unset($source['availability'][$week][$plantId]);
				}
			}

		}

		$sourceAlone = $source;

		foreach($weeks as $week) {
			unset($sourceAlone['availability'][$week][9]);
		}

		$proposal = CompositionLib::propose(1.0, 5, 1, 1, $weeks, NULL, self::SEASON, $sourceAlone, [], [], 0, [self::PLANT_ALONE => CompositionSpecies::MEDIUM]);

		$this->assertEquals([5, 7, 9, 11, 13, 15], array_keys($proposal), 'seule candidate à 1 semaine sur 2 : un panier une semaine sur deux');
		$this->assertEquals(count($weeks), count(CompositionLib::propose(1.0, 5, 1, 1, $weeks, NULL, self::SEASON, $sourceAlone, [], [])), 'seule candidate sans réglage : un panier chaque semaine');

		$proposal = CompositionLib::propose(1.0, 5, 1, 1, $weeks, NULL, self::SEASON, $source, [], [], 0, [self::PLANT_ALONE => CompositionSpecies::DIVERSIFICATION]);

		$this->assertEquals($weeks, array_keys($proposal), 'deux candidates : un panier chaque semaine');
		$this->assertEquals([], $this->getCeilingBreaks($proposal, self::PLANT_ALONE, CompositionSpecies::DIVERSIFICATION), 'la réglée tient son plafond');

		$servedOther = $this->getServedWeeks($proposal, 9);
		$followed = array_filter($servedOther, fn(int $week) => in_array($week + 1, $servedOther, TRUE));

		$this->assertTrue($followed !== [], 'l\'espèce sans réglage revient d\'une semaine à la suivante par le repli');

	}

	/**
	 * Le plafond compte ce qu'une taille héritera de sa base (`CompositionLib::getServedKnown()`) :
	 * une espèce ajoutée la veille d'une semaine où la base la sert ferait deux semaines de suite sans
	 * que le tirage du lendemain puisse y revenir, une ligne héritée ne partant pas.
	 *
	 * La base sert le fruit une semaine sur deux, puis une semaine sur trois. La taille dérivée
	 * compte quatre lignes ou plus et n'a pas d'autre fruit : son plancher l'appelle les semaines où
	 * la base ne le sert pas, et il est relâché.
	 */
	public function testCeilingCountsInherited(): void {

		$weeks = range(5, 24);
		$source = $this->getSourceOrgans($weeks);

		foreach([[CompositionSpecies::MEDIUM, 2], [CompositionSpecies::LOW, 3]] as [$frequency, $period]) {

			$base = [];
			$servedBase = [];

			foreach($weeks as $week) {

				$plants = [1, 2];

				if(($week - 6) % $period === 0) {
					$plants[] = self::PLANT_ALONE;
					$servedBase[] = $week;
				}

				$base[$week] = array_map(fn(int $plantId) => [
					'species' => $plantId,
					'unit' => 1,
					'quantity' => 1.0,
					'unitPrice' => 1.0,
					'organ' => self::ORGANS[$plantId],
					'step' => ['step' => 0.1, 'minimum' => 0.1],
				], $plants);

			}

			$proposal = CompositionLib::propose(6.0, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $source, [], [], 0, [self::PLANT_ALONE => $frequency], $base);

			$this->assertEquals($servedBase, $this->getServedWeeks($proposal, self::PLANT_ALONE), $frequency.' : le fruit n\'est servi que les semaines où la base le sert');

			$added = 0;

			foreach($proposal as $week => $basket) {
				$added += count($basket['lines']) - count($base[$week]);
			}

			$this->assertTrue($added > 0, $frequency.' : la taille dérivée ajoute des espèces');

			// Le pendant : sans réglage, la taille dérivée ajoute le fruit hors des semaines de sa base,
			// sans quoi l'égalité ci-dessus ne prouverait rien.
			$proposalFree = CompositionLib::propose(6.0, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $source, [], [], 0, [], $base);

			$this->assertDiffer($servedBase, $this->getServedWeeks($proposalFree, self::PLANT_ALONE), $frequency.' : sans réglage, le fruit est ajouté hors des semaines de la base');

		}

	}

	/**
	 * Sans fréquence choisie, la proposition est celle d'avant le lot r44, octet pour octet : les
	 * empreintes ont été mesurées sur la règle de `55ad6f4a3`, avant que le plafond n'existe, sur le
	 * relevé sans organe, sur le relevé à organes où le plancher et les écarts de `FREQUENCY_GAPS`
	 * jouent, et sur une taille dérivée d'une base.
	 *
	 * Le pendant de `testWithoutFrequencyUnchanged()`, qui compare des appels entre eux : celui-ci
	 * compare à la règle d'avant.
	 *
	 * Les empreintes ont changé une fois sans que la proposition change (r76, le 04/10/2026) : une
	 * ligne porte `species` au lieu de `plant`. La clé renommée en `plant` sur chaque ligne, les
	 * quatre propositions rendaient encore les empreintes de `55ad6f4a3`, à l'octet près.
	 */
	public function testWithoutFrequencyPinned(): void {

		foreach($this->getProposalsWithoutFrequency() as $case => $proposal) {
			$this->assertEquals(self::PROPOSALS_BEFORE[$case], md5(serialize($proposal)), $case);
		}

	}

	/**
	 * Les lignes d'une même espèce, l'espèce nue et ses variantes (r76), se partagent chaque semaine
	 * la place de cette espèce au prorata de leur part (décidé le 04/10/2026, sur la
	 * carotte de la structure 7). Une seule d'entre elles entre au panier (`isTwinRefused()`) : sans
	 * partage, chacune recevait une part pleine, la dette de celles qui perdaient montait sans fin, et
	 * une variante à la saison tardive, partie de zéro, ne rattrapait jamais celles qui couraient
	 * depuis le printemps. Elle n'entrait qu'une fois sur sa saison, la dernière semaine.
	 *
	 * Trois lignes de l'espèce 1 : l'espèce nue, qui suit sa part de la communauté, et deux variantes
	 * en base, dont la seconde n'est en saison que sur les quinze dernières semaines. Sur celles-ci,
	 * les deux variantes réglées pareil sont servies à parts voisines, et l'espèce ne prend jamais
	 * deux lignes d'un même panier.
	 */
	public function testVariantsShareTheirPlant(): void {

		$weeks = range(5, 44);
		$weeksLate = range(30, 44);
		$source = $this->getSourceVariants($weeks);

		$windows = array_fill_keys(array_keys($source['units']), $weeks);
		$windows[self::VARIANT_LATE] = $weeksLate;

		$proposal = CompositionLib::propose(4.0, 5, self::LINES_MIN, self::LINES_MAX, $weeks, $windows, self::SEASON, $source, [], [], 0, [
			self::VARIANT_EARLY => CompositionSpecies::BASE,
			self::VARIANT_LATE => CompositionSpecies::BASE,
		]);

		$twice = [];

		foreach($proposal as $week => $basket) {

			$lines = array_intersect(array_column($basket['lines'], 'species'), [1, self::VARIANT_EARLY, self::VARIANT_LATE]);

			if(count($lines) > 1) {
				$twice[] = $week;
			}

		}

		$this->assertEquals([], $twice, 'semaines à deux lignes de la même espèce : '.implode(', ', $twice));

		$servedEarly = count(array_intersect($this->getServedWeeks($proposal, self::VARIANT_EARLY), $weeksLate));
		$servedLate = count($this->getServedWeeks($proposal, self::VARIANT_LATE));

		$this->assertTrue(abs($servedEarly - $servedLate) <= 2, 'sur la saison tardive, la variante du printemps est servie '.$servedEarly.' fois et la variante tardive '.$servedLate.' fois');

	}

	/**
	 * Les seuils de la fréquence courante, pris juste au-dessus et juste en dessous de chacun, et
	 * l'espèce qui n'est candidate d'aucune semaine.
	 */
	public function testFrequencyCurrent(): void {

		$this->assertEquals(CompositionSpecies::BASE, CompositionLib::getFrequencyCurrent(5, 8), '62,5 %');
		$this->assertEquals(CompositionSpecies::MEDIUM, CompositionLib::getFrequencyCurrent(124, 200), '62 %');
		$this->assertEquals(CompositionSpecies::MEDIUM, CompositionLib::getFrequencyCurrent(83, 200), '41,5 %');
		$this->assertEquals(CompositionSpecies::LOW, CompositionLib::getFrequencyCurrent(82, 200), '41 %');
		$this->assertEquals(CompositionSpecies::LOW, CompositionLib::getFrequencyCurrent(53, 200), '26,5 %');
		$this->assertEquals(CompositionSpecies::DIVERSIFICATION, CompositionLib::getFrequencyCurrent(52, 200), '26 %');
		$this->assertEquals(CompositionSpecies::DIVERSIFICATION, CompositionLib::getFrequencyCurrent(0, 0), 'jamais candidate');

	}

	/**
	 * Le choix « Selon la saison » poste une valeur vide, que `build()` rend en `NULL` : le schéma
	 * suffit, sans contrôle propre, pour une espèce nue. Une variante le refuse (r76 lot 2,
	 * `frequency.seasonal`, `CompositionSpeciesAddTest::testAddVariant()`). Une valeur hors de l'enum
	 * est refusée sous `frequency.check`, qui a son message.
	 */
	public function testBuild(): void {

		$eCompositionSpecies = new CompositionSpecies(['frequency' => CompositionSpecies::LOW, 'name' => NULL]);

		foreach(['' => NULL, CompositionSpecies::BASE => CompositionSpecies::BASE, CompositionSpecies::DIVERSIFICATION => CompositionSpecies::DIVERSIFICATION] as $posted => $expected) {

			$fw = new \FailWatch();

			$properties = new \PropertyList('update');
			$eCompositionSpecies->build(['frequency'], ['frequency' => (string)$posted], $properties);

			$this->assertFalse($fw->ko(), 'valeur '.$posted.' admise');
			$this->assertEquals(['frequency'], $properties->getBuilt(), 'valeur '.$posted.' construite');
			$this->assertEquals($expected, $eCompositionSpecies['frequency'], 'valeur '.$posted);

		}

		$fw = new \FailWatch();

		$eCompositionSpecies->build(['frequency'], ['frequency' => 'weekly']);

		$this->assertTrue($fw->has('CompositionSpecies::frequency.check'), 'valeur hors de l\'enum');
		$this->assertTrue(AlertUi::getError('CompositionSpecies::frequency.check') !== NULL, 'message du refus');

	}

	/**
	 * Le geste écrit la fréquence et recompose tout de suite les semaines non corrigées de toutes les
	 * tailles : l'espèce mise en base tient son rythme dans chacune, une semaine corrigée garde ses
	 * lignes. L'écran le dit à la roue pleine, au picto, « Dans les paniers 3 semaines sur 4, comme
	 * vous l'avez choisi », et dans la tuile du panneau « Régler une espèce » ; l'espèce voisine garde
	 * sa roue vide et dit ce que la grille lui sert, selon la saison. Revenir à la saison rend la
	 * proposition de départ.
	 */
	public function testUpdateRecomposes(): void {

		$eCompositionPlan = $this->createPlan();

		if($eCompositionPlan->empty()) {
			return;
		}

		$cCompositionFormula = CompositionFormulaLib::getByPlan($eCompositionPlan);

		$linesBefore = $this->getLinesByFormula($cCompositionFormula, CompositionSpeciesLib::getByPlan($eCompositionPlan));

		// L'espèce la moins servie de la première taille parmi celles qui sont candidates au moins
		// dix semaines : c'est elle qu'on monte en base.
		$eCompositionPlanScreen = CompositionPlanLib::getForScreen($eCompositionPlan['structure'], NULL);

		$eCompositionSpecies = new CompositionSpecies();

		foreach($eCompositionPlanScreen['eCompositionFormula']['grid'] as ['species' => $rows]) {

			foreach($rows as $row) {

				$eCompositionSpeciesRow = $row['eCompositionSpecies'];

				if($eCompositionSpeciesRow->empty() or $eCompositionSpeciesRow['candidate'] < 10) {
					continue;
				}

				if($eCompositionSpecies->empty() or $eCompositionSpeciesRow['served'] / $eCompositionSpeciesRow['candidate'] < $eCompositionSpecies['served'] / $eCompositionSpecies['candidate']) {
					$eCompositionSpecies = $eCompositionSpeciesRow;
				}

			}

		}

		if($eCompositionSpecies->empty() or $eCompositionSpecies['served'] / $eCompositionSpecies['candidate'] > 0.5) {
			$this->assertFail('le relevé de dev n\'a aucune espèce peu servie : le contrôle ne verrait rien');
			return;
		}

		// Une semaine corrigée de la première taille, qui doit garder ses lignes.
		$eCompositionWeekAmended = CompositionWeekLib::getByFormula($cCompositionFormula->first())->first();
		$eCompositionWeekAmended['amended'] = TRUE;
		CompositionWeekLib::update($eCompositionWeekAmended, ['amended']);

		$gridBefore = $this->getGridRows(new CompositionUi()->getProposal($eCompositionPlanScreen));

		$fw = new \FailWatch();

		$properties = new \PropertyList('update');
		$eCompositionSpecies->build(['frequency'], ['frequency' => CompositionSpecies::BASE], $properties);

		$this->assertFalse($fw->ko(), 'fréquence construite');

		CompositionSpeciesLib::update($eCompositionSpecies, $properties->getBuilt());

		$this->assertEquals([$eCompositionSpecies['id'] => CompositionSpecies::BASE], CompositionSpeciesLib::getFrequenciesByPlan($eCompositionPlan), 'fréquence écrite');

		$linesAfter = $this->getLinesByFormula($cCompositionFormula, CompositionSpeciesLib::getByPlan($eCompositionPlan));

		$this->assertEquals($linesBefore[$cCompositionFormula->first()['id']][$eCompositionWeekAmended['week']], $linesAfter[$cCompositionFormula->first()['id']][$eCompositionWeekAmended['week']], 'semaine corrigée intacte');

		$eCompositionPlanScreen = CompositionPlanLib::getForScreen($eCompositionPlan['structure'], NULL);

		$eCompositionSpeciesScreen = $this->findSpecies($eCompositionPlanScreen, $eCompositionSpecies['id']);

		$rate = $eCompositionSpeciesScreen['served'] / $eCompositionSpeciesScreen['candidate'];

		$this->assertTrue($rate > $eCompositionSpecies['served'] / $eCompositionSpecies['candidate'], 'l\'espèce montée est plus servie ('.$eCompositionSpecies['served'].' puis '.$eCompositionSpeciesScreen['served'].' sur '.$eCompositionSpeciesScreen['candidate'].')');

		$html = new CompositionUi()->getProposal($eCompositionPlanScreen);

		// Une fréquence recompose les paniers de toutes les tailles : d'autres espèces que celle réglée
		// entrent ou sortent des semaines, et la réponse du geste (`doQuickFrequency`) rend donc les
		// paniers entiers et non le seul rang de l'espèce (lot r7).
		$gridAfter = $this->getGridRows($html);
		$rowsChanged = array_keys(array_diff_assoc($gridAfter, $gridBefore));
		$rowsOther = array_values(array_diff($rowsChanged, ['toolbox-composition-grid-species-'.$eCompositionSpecies['id']]));

		$this->assertTrue(in_array('toolbox-composition-grid-species-'.$eCompositionSpecies['id'], $rowsChanged, TRUE), 'le rang de l\'espèce réglée change');
		$this->assertTrue(count($rowsOther) > 0, 'la fréquence change aussi le rang d\'autres espèces ('.count($rowsOther).' sur '.count($gridAfter).')');

		$xpath = new \DOMXPath($this->getDocument($html));

		$picto = fn(int $plantId) => $xpath->query('//tr[@id="toolbox-composition-grid-species-'.$plantId.'"]//*[@class="toolbox-composition-grid-frequency"]')->item(0);

		// Chaque choix du menu de la fréquence poste directement le quick de la fréquence, avec l'espèce,
		// la structure et la taille lue, ses clés dans l'ordre que fixe `setQuickPosts()`, et la valeur
		// qu'il écrit.
		$pictoNode = $picto($eCompositionSpecies['id']);
		$menu = fn(\DOMNode $node) => $xpath->query('following-sibling::div[@class="dropdown-list"][1]', $node)->item(0);
		$choices = fn(\DOMNode $node) => iterator_to_array($xpath->query('a[contains(concat(" ", @class, " "), " dropdown-item ")]', $menu($node)));

		$choice = $choices($pictoNode)[2];

		$this->assertEquals(
			['/@module/toolbox/CompositionSpecies/doQuick', (string)$eCompositionSpeciesScreen['id'], (string)$eCompositionPlan['structure']['id'], (string)$eCompositionPlanScreen['eCompositionFormula']['id'], 'frequency', CompositionSpecies::MEDIUM],
			[
				$choice->getAttribute('data-ajax'),
				$choice->getAttribute('post-id'),
				$choice->getAttribute('post-structure'),
				$choice->getAttribute('post-formula'),
				$choice->getAttribute('post-property'),
				$choice->getAttribute('post-frequency'),
			],
			'un choix poste le quick de la fréquence'
		);
		$this->assertEquals('', $choices($pictoNode)[0]->getAttribute('post-frequency'), '« Selon la saison » poste une fréquence vide');

		// Les attributs d'un `util-quick` sont communs à tous les éléments et survivent d'un rendu au
		// suivant : le menu pose les siens, et ne poste pas la semaine d'une case rendue avant lui.
		CompositionLine::setQuickAttribute('week', 22);
		CompositionLine::setQuickAttribute('origin', 'synthesis');

		$choiceAlone = $this->getDocument(CompositionSpeciesUi::getGridFrequency($eCompositionSpeciesScreen, $eCompositionPlanScreen['eCompositionFormula']))->getElementsByTagName('a')->item(1);

		$this->assertEquals(['frequency', '', ''], [$choiceAlone->getAttribute('post-property'), $choiceAlone->getAttribute('post-week'), $choiceAlone->getAttribute('post-origin')], 'le menu rendu seul ne poste ni semaine ni origine reçues d\'un autre geste');

		$eCompositionSpecies->build(['frequency'], ['frequency' => '']);

		CompositionSpeciesLib::update($eCompositionSpecies, ['frequency']);

		$this->assertEquals([], CompositionSpeciesLib::getFrequenciesByPlan($eCompositionPlan), 'fréquence rendue');

		$linesBack = $this->getLinesByFormula($cCompositionFormula, CompositionSpeciesLib::getByPlan($eCompositionPlan));

		foreach($linesBefore as $formulaId => $weeks) {
			$this->assertEquals($weeks, $linesBack[$formulaId], 'proposition de départ retrouvée dans la taille '.$formulaId);
		}

	}

	/**
	 * Ce que le picto d'une espèce et son champ de fréquence disent d'elle (retour du 26/09, verdict de
	 * la planche r22). Selon la saison, les deux donnent le même chiffre, ce que la grille sert
	 * réellement, et « Dans aucun panier » quand elle ne la sert jamais ; réglée, le picto donne le
	 * rythme choisi, quoi que la grille serve.
	 */
	public function testFrequencyText(): void {

		$eCompositionSpecies = new CompositionSpecies([
			'frequency' => NULL,
			'served' => 3,
			'candidate' => 9,
			'seasonalOffered' => TRUE,
		]);

		$this->assertEquals('Dans les paniers 3 semaines sur 9, selon la saison', CompositionSpeciesUi::getFrequency($eCompositionSpecies), 'le picto, selon la saison');

		$eCompositionSpecies['served'] = 0;

		$this->assertEquals('Dans aucun panier, selon la saison', CompositionSpeciesUi::getFrequency($eCompositionSpecies), 'le picto, jamais servie');

		$eCompositionSpecies['frequency'] = CompositionSpecies::LOW;

		$this->assertEquals('Dans les paniers 1 semaine sur 3, comme vous l\'avez choisi', CompositionSpeciesUi::getFrequency($eCompositionSpecies), 'le picto, réglée, même jamais servie');

	}

	/**
	 * Le champ de la fréquence, celui du panneau de l'espèce (le picto de la grille ouvre un menu
	 * depuis le lot r34c) : « Selon la saison » en tête, coché tant que l'espèce n'a rien choisi, avec
	 * ce que la grille sert d'elle, puis les quatre fréquences, chacune dite par son rythme et non par
	 * le nom de la règle (verdict de la planche r22). Une fois une fréquence choisie, c'est elle qui
	 * est cochée, et « Selon la saison » dit ce qu'on retrouverait en y revenant, qui est ce que la
	 * grille servait avant.
	 */
	public function testPanelField(): void {

		$eCompositionPlan = $this->createPlan();

		if($eCompositionPlan->empty()) {
			return;
		}

		$eCompositionPlanScreen = CompositionPlanLib::getForScreen($eCompositionPlan['structure'], NULL);
		$eCompositionSpeciesScreen = $eCompositionPlanScreen['eCompositionFormula']['grid'][0]['species'][0]['eCompositionSpecies'];

		$eCompositionSpecies = CompositionSpeciesLib::getById($eCompositionSpeciesScreen['id'], CompositionSpecies::getSelection() + [
			'plan' => CompositionPlan::getSelection(),
		]);

		CompositionSpeciesLib::loadFrequency($eCompositionSpecies, NULL);

		$this->assertEquals([$eCompositionSpeciesScreen['served'], $eCompositionSpeciesScreen['candidate']], [$eCompositionSpecies['served'], $eCompositionSpecies['candidate']], 'ce que la grille sert');

		$xpath = new \DOMXPath($this->getDocument(new \util\FormUi()->dynamicField($eCompositionSpecies, 'frequency')));

		$this->assertEquals('', $xpath->query('//input[@name="frequency"][@checked]')->item(0)->getAttribute('value'), 'selon la saison, coché');

		$eCompositionSpecies['frequency'] = CompositionSpecies::DIVERSIFICATION;
		CompositionSpeciesLib::update($eCompositionSpecies, ['frequency']);

		CompositionSpeciesLib::loadFrequency($eCompositionSpecies, NULL);

		$this->assertEquals([$eCompositionSpeciesScreen['served'], $eCompositionSpeciesScreen['candidate']], [$eCompositionSpecies['served'], $eCompositionSpecies['candidate']], 'ce qu\'on retrouverait');

		$xpath = new \DOMXPath($this->getDocument(new \util\FormUi()->dynamicField($eCompositionSpecies, 'frequency')));

		$this->assertEquals(CompositionSpecies::DIVERSIFICATION, $xpath->query('//input[@name="frequency"][@checked]')->item(0)->getAttribute('value'), 'fréquence choisie cochée');

	}

	/**
	 * Un plan écrit par le départ sur une structure qui n'en a pas, en 2099 : deux tailles, à `PRICE`
	 * et à une fois et demie ce prix, toute l'année, toutes les espèces proposables cochées. La
	 * structure est celle d'un administrateur, que le test connecte : le panneau de l'espèce ne se rend
	 * qu'à qui gère la structure (`assertUpdate`). Vide, avec un échec qui le dit, quand la base de dev
	 * n'a personne à connecter.
	 */
	private function createPlan(): CompositionPlan {

		$eMember = \structure\Member::model()
			->select(['user' => new \Sql('m1.user', 'element'), 'structure' => new \Sql('m1.structure', 'element')])
			->join(\structure\Role::model(), 'm1.role = m2.id')
			->join(CompositionPlan::model(), 'm1.structure = m3.structure', 'LEFT')
			->where('m2.admin', TRUE)
			->where('m1.status', \structure\Member::IN)
			->where('m1.structureStatus', \structure\Member::ACTIVE)
			->where('m3.id IS NULL')
			->sort(['m1.structure' => SORT_ASC])
			->get();

		if($eMember->empty()) {
			$this->assertFail('aucune structure sans plan n\'a d\'administrateur : le contrôle ne verrait rien');
			return new CompositionPlan();
		}

		\user\ConnectionLib::setOnline($eMember['user']);

		$eStructure = \structure\StructureLib::getById($eMember['structure']['id']);

		$eCompositionPlan = CompositionPlanLib::getForStart($eStructure, 2099);
		$eCompositionPlan['weeks'] = $eCompositionPlan['seasonWeeks'];
		$eCompositionPlan['reference'] = CompositionPlan::COMMUNITY;
		$eCompositionPlan['climate'] = NULL;

		$eCompositionPlan['cCompositionSpecies']->map(fn(CompositionSpecies $e) => $e['status'] = CompositionSpecies::NATURAL);

		$eCompositionPlan['cCompositionFormula'] = new \Collection([
			new CompositionFormula(['plan' => $eCompositionPlan, 'name' => 'Le panier', 'price' => self::PRICE, 'baskets' => 10, 'gap' => 5, 'version' => 0]),
			new CompositionFormula(['plan' => $eCompositionPlan, 'name' => 'Le grand panier', 'price' => self::PRICE * 1.5, 'baskets' => 10, 'gap' => 5, 'version' => 0]),
		]);

		CompositionPlanLib::create($eCompositionPlan);

		$eCompositionPlan = CompositionPlanLib::getByStructure($eStructure);
		$eCompositionPlan['structure'] = $eStructure;

		return $eCompositionPlan;

	}

	/**
	 * Les espèces de chaque semaine de chaque taille, `[id de taille => [semaine => [id d'espèce, …]]]`.
	 */
	private function getLinesByFormula(\Collection $cCompositionFormula, \Collection $cCompositionSpecies): array {

		$lines = [];

		foreach($cCompositionFormula as $eCompositionFormula) {

			foreach(CompositionWeekLib::getByFormulaWithLines($eCompositionFormula, $cCompositionSpecies) as $eCompositionWeek) {

				$speciesIds = $eCompositionWeek['cCompositionLine']->getColumnCollection('species')->getIds();
				sort($speciesIds);

				$lines[$eCompositionFormula['id']][$eCompositionWeek['week']] = $speciesIds;

			}

		}

		return $lines;

	}

	private function findSpecies(CompositionPlan $eCompositionPlan, int $speciesId): CompositionSpecies {

		foreach($eCompositionPlan['eCompositionFormula']['grid'] as ['species' => $rows]) {

			foreach($rows as $row) {

				if($row['species']['id'] === $speciesId) {
					return $row['eCompositionSpecies'];
				}

			}

		}

		return new CompositionSpecies();

	}

	/**
	 * Le contenu de chaque rang d'espèce de la grille, `[id du rang => html]`, les `id` de champ que
	 * `FormUi` tire au hasard neutralisés.
	 */
	private function getGridRows(string $html): array {

		$xpath = new \DOMXPath($this->getDocument(preg_replace('/field-[0-9a-f]{13}/', 'field-X', $html)));

		$rows = [];

		foreach($xpath->query('//tr[starts-with(@id, "toolbox-composition-grid-species-")]') as $node) {
			$rows[$node->getAttribute('id')] = $node->ownerDocument->saveHTML($node);
		}

		return $rows;

	}

	private function getDocument(string $html): \DOMDocument {

		// L'analyseur HTML de libxml ne connaît pas les balises SVG des vignettes, et le dit en
		// avertissements, que le cadriciel lève.
		$libxmlErrors = libxml_use_internal_errors(TRUE);

		$document = new \DOMDocument();
		$document->loadHTML('<?xml encoding="utf-8"?>'.$html);

		libxml_clear_errors();
		libxml_use_internal_errors($libxmlErrors);

		return $document;

	}

	/**
	 * Les propositions sans fréquence choisie que `testWithoutFrequencyPinned()` épingle, par cas.
	 * `$class` est la règle qui les compose : celle du dépôt, ou une copie de la règle d'un autre
	 * commit quand on remesure les empreintes.
	 */
	public function getProposalsWithoutFrequency(string $class = CompositionLib::class): array {

		$weeks = range(5, 44);
		$source = $this->getSource($weeks);
		$sourceOrgans = $this->getSourceOrgans($weeks);

		$base = [];

		foreach($weeks as $week) {

			$base[$week] = array_map(fn(int $plantId) => [
				'species' => $plantId,
				'unit' => 1,
				'quantity' => 1.0,
				'unitPrice' => 1.0,
				'organ' => self::ORGANS[$plantId],
				'step' => ['step' => 0.1, 'minimum' => 0.1],
			], ($week % 2 === 0) ? [1, 2, self::PLANT_ALONE] : [1, 2]);

		}

		return [
			'sans organe à 4 €' => $class::propose(4.0, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $source, [], []),
			'organes à 4 €' => $class::propose(4.0, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $sourceOrgans, [], []),
			'organes à 6 €' => $class::propose(6.0, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $sourceOrgans, [], []),
			'dérivée à 6 €' => $class::propose(6.0, 5, self::LINES_MIN, self::LINES_MAX, $weeks, NULL, self::SEASON, $sourceOrgans, [], [], 0, [], $base),
		];

	}

	/**
	 * Les semaines où la proposition sert l'espèce, dans l'ordre.
	 */
	private function getServedWeeks(array $proposal, int $plantId): array {

		$served = [];

		foreach($proposal as $week => $basket) {

			if(in_array($plantId, array_column($basket['lines'], 'species'), TRUE)) {
				$served[] = $week;
			}

		}

		return $served;

	}

	/**
	 * Les semaines où l'espèce est servie au-delà du plafond de sa fréquence `[servies, sur]`
	 * (`CompositionLib::FREQUENCIES`) : déjà servie `servies` fois dans les `sur - 1` numéros de
	 * semaine précédents. Écrit sans la règle, pour ne pas la vérifier par elle-même.
	 */
	private function getCeilingBreaks(array $proposal, int $plantId, string $frequency): array {

		[$served, $weeks] = CompositionLib::FREQUENCIES[$frequency];

		$servedWeeks = $this->getServedWeeks($proposal, $plantId);
		$breaks = [];

		foreach($servedWeeks as $week) {

			$before = array_filter($servedWeeks, fn(int $weekServed) => ($weekServed < $week and $weekServed > $week - $weeks));

			if(count($before) >= $served) {
				$breaks[] = $week;
			}

		}

		return $breaks;

	}

	private function countServed(array $proposal): array {

		$served = array_fill_keys(array_keys(self::SHARES), 0);

		foreach($proposal as $basket) {

			foreach(array_column($basket['lines'], 'species') as $plantId) {
				$served[$plantId]++;
			}

		}

		return $served;

	}

	/**
	 * Un relevé construit, celui de `CompositionRuleTest::getSource()` : les dix espèces de `SHARES`,
	 * disponibles toutes les semaines données, dans l'unité 1 à la quantité 1 et à 1 €, classées par
	 * leur part à l'année, sans organe ni saison.
	 */
	private function getSource(array $weeks): array {

		$source = [
			'availability' => [],
			'seasons' => [],
			'units' => [],
			'prices' => [],
			'frequency' => ['byWeek' => [], 'byYear' => []],
			'organs' => [],
			'cabbages' => [],
			'plants' => [],
			'speciesBare' => [],
		];

		foreach(self::SHARES as $plantId => $share) {

			$source['units'][$plantId] = ['unit' => 1, 'quantity' => 1.0, 'minimum' => NULL, 'step' => 0.1];
			$source['frequency']['byYear'][$plantId] = $share;
			$source['organs'][$plantId] = NULL;

			foreach($weeks as $week) {

				$source['availability'][$week][$plantId] = 10;
				$source['prices'][$week][$plantId][1] = [
					'contributors' => 10,
					'median' => 1.0,
					'low' => 1.0,
					'high' => 1.0,
				];

			}

		}

		return $source;

	}

	/**
	 * Le relevé construit, servi comme la source d'un plan (`CompositionLib::getSourceByPlan()`) :
	 * chaque espèce sous son propre id, plus deux variantes de l'espèce 1, `VARIANT_EARLY` et
	 * `VARIANT_LATE`, qui lisent ses prix et sa disponibilité. Comme dans la source réelle, une
	 * variante n'a pas de part de la communauté (`projectSource()`).
	 */
	private function getSourceVariants(array $weeks): array {

		$source = $this->getSource($weeks);

		foreach(array_keys(self::SHARES) as $plantId) {
			$source['plants'][$plantId] = $plantId;
			$source['speciesBare'][$plantId] = $plantId;
		}

		foreach([self::VARIANT_EARLY, self::VARIANT_LATE] as $speciesId) {

			$source['plants'][$speciesId] = 1;
			$source['units'][$speciesId] = $source['units'][1];
			$source['organs'][$speciesId] = NULL;

			foreach($weeks as $week) {
				$source['prices'][$week][$speciesId] = $source['prices'][$week][1];
			}

		}

		return $source;

	}

	/**
	 * Le relevé construit, où chaque espèce porte un organe (`ORGANS`) : cinq feuilles, quatre racines
	 * et un seul fruit, le plus rare. Le plafond et le plancher d'organes y jouent.
	 */
	private function getSourceOrgans(array $weeks): array {

		$source = $this->getSource($weeks);
		$source['organs'] = self::ORGANS;

		return $source;

	}

	/**
	 * Le relevé construit, modulé par une saison des ventes synthétique : les douze poids donnés,
	 * décalés d'un mois d'une espèce à la suivante pour que les pleines saisons ne tombent pas
	 * ensemble. La modulation est celle de la source réelle
	 * (`aggregate\CompositionSourceAiLib::applySeasonality()`), appelée par réflexion.
	 */
	private function getSourceSeasonal(array $source, array $weights): array {

		$seasonality = [];

		foreach(array_keys(self::SHARES) as $plantId) {
			for($month = 1; $month <= 12; $month++) {
				$seasonality[$plantId][$month] = $weights[($plantId + $month) % 12];
			}
		}

		$source['frequency'] = new \ReflectionMethod(\aggregate\CompositionSourceAiLib::class, 'applySeasonality')->invoke(NULL, $source['frequency'], $seasonality);

		return $source;

	}

}
?>
