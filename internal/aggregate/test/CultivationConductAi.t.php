<?php
namespace aggregate;

/**
 * Ce qu'une conduite doit réunir pour s'afficher, et ce que le groupe en dit.
 *
 * Deux de ces règles protègent l'anonymat du parc et non la justesse d'un chiffre : une conduite
 * s'affiche à partir de cinq structures, un objectif de rendement à partir de cinq cultures. Les
 * baisser ou les contourner ne casse rien de visible — l'écran reste plausible, simplement il
 * désigne une ferme derrière une pratique rare. C'est la raison d'éprouver les deux sens : le cas
 * au seuil doit passer, celui juste en dessous doit disparaître.
 *
 * La largeur ouverte se résout ici et non dans la page : la plus proche de la liste pour une
 * largeur donnée, la plus pratiquée sinon. Et ce qui sort pour les vues d'ensemble, l'histogramme et
 * son axe, ne se rattache à aucune culture.
 *
 * Aucune base : CultivationConductAiLib ne lit ni n'écrit rien, il travaille sur la collection que
 * CultivationCropPlanAiLib lui passe. Les cultures sont donc construites à la main, ce qui permet
 * de poser exactement le cas à un de part et d'autre de chaque seuil.
 *
 * Lancement : dev/test package=aggregate file=CultivationConductAi
 */
class CultivationConductAiTest extends \Test {

	/**
	 * Une culture en planche, réduite à ce qui fait une conduite.
	 */
	private function getCultivation(int $structureId, array $values = []): Cultivation {

		return new Cultivation($values + [
			'structure' => new \structure\Structure(['id' => $structureId]),
			'use' => Cultivation::BED,
			'mode' => Cultivation::OPEN_FIELD,
			'bedWidth' => 80,
			'rows' => 2,
			'plantSpacing' => 40.0,
			'density' => 5.0,
			'yieldExpected' => NULL,
			'mainUnit' => Cultivation::KG,
		]);

	}

	/**
	 * Autant de cultures identiques que de structures, une par structure.
	 */
	private function getCollection(int $structures, array $values = []): \Collection {

		$cCultivation = new \Collection();

		for($structureId = 1; $structureId <= $structures; $structureId++) {
			$cCultivation[] = $this->getCultivation($structureId, $values);
		}

		return $cCultivation;

	}

	/**
	 * Toutes les conduites de la largeur ouverte, celles que la vue experte montre.
	 */
	private function getAll(array $conducts): array {
		return $conducts['map']['conducts'];
	}

	/**
	 * Des cultures de plusieurs conduites, une structure chacune, les structures d'une conduite
	 * ne recoupant jamais celles d'une autre. `$conducts` associe à chaque jeu de valeurs son
	 * nombre de structures.
	 */
	private function getConductsCollection(array $conducts): \Collection {

		$cCultivation = new \Collection();
		$structureId = 0;

		foreach($conducts as [$structures, $values]) {

			for($index = 0; $index < $structures; $index++) {
				$cCultivation[] = $this->getCultivation(++$structureId, $values);
			}

		}

		return $cCultivation;

	}

	public function testConductBelowStructureFloorIsLeftOut(): void {

		$floor = CultivationAiSetting::CONDUCT_STRUCTURES_MIN;

		$atFloor = CultivationConductAiLib::getConducts($this->getCollection($floor), 80);
		$belowFloor = CultivationConductAiLib::getConducts($this->getCollection($floor - 1), 80);

		$this->assertEquals(1, count($this->getAll($atFloor)), 'la conduite au seuil doit s\'afficher');
		$this->assertEquals($floor, $this->getAll($atFloor)[0]['structures']);

		$this->assertEquals([], $this->getAll($belowFloor), 'une structure de moins, et la conduite disparaît');
		$this->assertEquals([], $belowFloor['modes']);
		$this->assertEquals(0, $belowFloor['widths'][75]['structures']);

	}

	/**
	 * Une structure qui pratique deux conduites de la même largeur ne compte qu'une fois pour
	 * cette largeur. Additionner les effectifs des conduites rendrait un nombre plus grand que le parc.
	 */
	public function testStructuresOfAGroupAreCountedOnce(): void {

		$floor = CultivationAiSetting::CONDUCT_STRUCTURES_MIN;

		$cCultivation = new \Collection();

		for($structureId = 1; $structureId <= $floor; $structureId++) {
			$cCultivation[] = $this->getCultivation($structureId, ['rows' => 2]);
			$cCultivation[] = $this->getCultivation($structureId, ['rows' => 3, 'density' => 7.5]);
		}

		$conducts = CultivationConductAiLib::getConducts($cCultivation, 80);

		$this->assertEquals(2, count($this->getAll($conducts)), 'les deux conduites passent le seuil');
		$this->assertEquals($floor * 2, array_sum(array_column($this->getAll($conducts), 'structures')));
		$this->assertEquals($floor, $conducts['widths'][75]['structures'], 'la largeur ne compte chaque structure qu\'une fois');

	}

	public function testYieldBelowCultivationFloorIsSilent(): void {

		$structures = CultivationAiSetting::CONDUCT_STRUCTURES_MIN;
		$floor = CultivationAiSetting::CONDUCT_YIELD_CULTIVATIONS_MIN;

		// Les cultures se répartissent sur le nombre de structures qu'il faut pour que la
		// conduite existe : le seuil de rendement se compte en cultures, pas en structures.
		$getCollection = function(int $yields) use($structures, $floor): \Collection {

			$cCultivation = new \Collection();

			for($index = 0; $index < $floor + 1; $index++) {
				$cCultivation[] = $this->getCultivation($index % $structures + 1, [
					'yieldExpected' => ($index < $yields) ? 6.0 : NULL,
				]);
			}

			return $cCultivation;

		};

		$belowFloor = CultivationConductAiLib::getConducts($getCollection($floor - 1), 80);

		$this->assertEquals(NULL, $this->getAll($belowFloor)[0]['yieldExpected'], 'sous le seuil, l\'objectif se tait');

		$atFloor = CultivationConductAiLib::getConducts($getCollection($floor), 80);

		$this->assertEquals(6.0, $this->getAll($atFloor)[0]['yieldExpected']);

	}

	/**
	 * L'objectif est écarté sous le 10e et au-dessus du 90e centile avant la médiane, au rang
	 * `floor(part × n)` de la mercuriale. Vingt et un objectifs : les rangs bornes sont le 3e et
	 * le 19e, la faute de frappe à 100 kg/m² sort, et la médiane passe de 5 (vingt et une
	 * valeurs) à 3 (vingt valeurs). Le compte rendu se fait après l'écartement, le seuil avant.
	 */
	public function testYieldOutsideDecilesIsLeftOut(): void {

		$yields = array_merge(array_fill(0, 10, 1.0), array_fill(0, 10, 5.0), [100.0]);

		$cCultivation = new \Collection();

		foreach($yields as $index => $yield) {
			$cCultivation[] = $this->getCultivation($index + 1, ['yieldExpected' => $yield]);
		}

		$conduct = $this->getAll(CultivationConductAiLib::getConducts($cCultivation, 80))[0];

		$this->assertEquals(3.0, $conduct['yieldExpected'], 'la valeur au-dessus du 90e centile ne doit pas compter');
		$this->assertEquals(20, $conduct['yieldCultivations'], 'le compte se fait après l\'écartement');

	}

	/**
	 * La même part s'écarte des deux côtés : à dix objectifs, un en bas et un en haut. Un rang
	 * `floor(0,9 × n)` pris pour borne haute retombait sur le dernier et n'écartait rien.
	 */
	public function testYieldIsTrimmedOnBothSides(): void {

		$yields = [0.5, 6.0, 6.0, 6.0, 6.0, 6.0, 6.0, 6.0, 6.0, 60.0];

		$cCultivation = new \Collection();

		foreach($yields as $index => $yield) {
			$cCultivation[] = $this->getCultivation($index + 1, ['yieldExpected' => $yield]);
		}

		$conduct = $this->getAll(CultivationConductAiLib::getConducts($cCultivation, 80))[0];

		$this->assertEquals(8, $conduct['yieldCultivations'], 'le plus bas et le plus haut sont écartés');

		$cCultivation = new \Collection();

		foreach(array_slice($yields, 0, 9) as $index => $yield) {
			$cCultivation[] = $this->getCultivation($index + 1, ['yieldExpected' => $yield]);
		}

		$conduct = $this->getAll(CultivationConductAiLib::getConducts($cCultivation, 80))[0];

		$this->assertEquals(9, $conduct['yieldCultivations'], 'sous dix objectifs, rien n\'est écarté');

	}

	/**
	 * L'espèce garde l'unité où elle annonce le plus d'objectifs, et les autres n'entrent pas
	 * dans la médiane : mêler des bottes et des kilos rendrait un nombre qui n'est celui de
	 * personne. Les objectifs en kilos sont posés sur une autre largeur, pour vérifier que le
	 * compte se fait toutes largeurs confondues.
	 */
	public function testYieldKeepsTheMostAnnouncedUnit(): void {

		$floor = CultivationAiSetting::CONDUCT_YIELD_CULTIVATIONS_MIN;

		$cCultivation = new \Collection();

		for($index = 1; $index <= $floor; $index++) {
			$cCultivation[] = $this->getCultivation($index, ['yieldExpected' => 12.0, 'mainUnit' => Cultivation::UNIT]);
			$cCultivation[] = $this->getCultivation($index, ['yieldExpected' => 40.0, 'mainUnit' => Cultivation::KG]);
		}

		$cCultivation[] = $this->getCultivation($floor + 1, ['bedWidth' => 100, 'yieldExpected' => 40.0, 'mainUnit' => Cultivation::UNIT]);

		$conducts = CultivationConductAiLib::getConducts($cCultivation, 80);

		$this->assertEquals(Cultivation::UNIT, $conducts['yieldUnit']);
		$this->assertEquals(12.0, $this->getAll($conducts)[0]['yieldExpected'], 'les objectifs en kilos ne doivent pas entrer dans la médiane');
		$this->assertEquals($floor, $this->getAll($conducts)[0]['yieldCultivations']);

		$cCultivation[] = $this->getCultivation($floor + 1, ['bedWidth' => 100, 'yieldExpected' => 40.0, 'mainUnit' => Cultivation::KG]);
		$cCultivation[] = $this->getCultivation($floor + 2, ['bedWidth' => 100, 'yieldExpected' => 40.0, 'mainUnit' => Cultivation::KG]);

		$this->assertEquals(Cultivation::KG, CultivationConductAiLib::getConducts($cCultivation, 80)['yieldUnit'], 'l\'autre largeur doit faire pencher le compte');

	}

	/**
	 * À égalité, et sans aucun objectif, l'unité est la première de l'enum.
	 */
	public function testYieldUnitTieFallsOnTheFirst(): void {

		$this->assertEquals(Cultivation::KG, CultivationConductAiLib::getConducts($this->getCollection(CultivationAiSetting::CONDUCT_STRUCTURES_MIN), 80)['yieldUnit']);

		$cCultivation = new \Collection([
			$this->getCultivation(1, ['yieldExpected' => 6.0, 'mainUnit' => Cultivation::BUNCH]),
			$this->getCultivation(2, ['yieldExpected' => 6.0, 'mainUnit' => Cultivation::UNIT]),
		]);

		$this->assertEquals(Cultivation::UNIT, CultivationConductAiLib::getConducts($cCultivation, 80)['yieldUnit']);

	}

	/**
	 * Les identifiants et les valeurs qui ont servi à compter ne sortent pas du Lib, par aucune
	 * des trois entrées qui rendent des conduites ou en dérivent : les rendre offrirait à
	 * n'importe quel écran de quoi défaire l'anonymat du seuil en recoupant deux espèces.
	 */
	public function testStructureIdentifiersDoNotLeave(): void {

		$conducts = CultivationConductAiLib::getConducts(
			$this->getCollection(CultivationAiSetting::CONDUCT_STRUCTURES_MIN, ['yieldExpected' => 6.0]),
			80
		);

		$forbidden = ['structureIds', 'densityValues', 'yieldValues'];

		$found = array_merge(
			array_intersect($forbidden, array_keys($this->getAll($conducts)[0])),
			array_intersect($forbidden, array_keys($conducts['modes'][Cultivation::OPEN_FIELD]['top'][0])),
			array_intersect($forbidden, array_keys($conducts['bands']['modes'][Cultivation::OPEN_FIELD]))
		);

		$this->assertEquals([], $found, 'clés rendues qui ne devraient pas l\'être : '.implode(', ', $found));

	}

	/**
	 * Toutes les largeurs réunissent les conduites de chacune dans les bandes, et dans les
	 * bandes seulement : les cartes comparent des rangs et des écartements, qui ne se
	 * transposent pas d'une largeur à l'autre. Sans aucune conduite, rien ne s'ouvre.
	 */
	public function testAllWidthsFeedOnlyTheBands(): void {

		$floor = CultivationAiSetting::CONDUCT_STRUCTURES_MIN;

		$cCultivation = $this->getConductsCollection([
			[$floor, ['bedWidth' => 80]],
			[$floor + 1, ['bedWidth' => 100, 'density' => 8.0]],
		]);

		$conducts = CultivationConductAiLib::getConducts($cCultivation, CultivationAiSetting::BED_WIDTH_ALL);

		$this->assertEquals(CultivationAiSetting::BED_WIDTH_ALL, $conducts['bedWidth']);
		$this->assertEquals([], $conducts['modes'], 'toutes les largeurs ne doivent pas porter de cartes');
		$this->assertEquals(2 * $floor + 1, $conducts['bands']['modes'][Cultivation::OPEN_FIELD]['cultivations']);
		$this->assertEquals(2, $conducts['bands']['modes'][Cultivation::OPEN_FIELD]['conducts']);

		$this->assertEquals(NULL, CultivationConductAiLib::getConducts($this->getCollection($floor - 1), CultivationAiSetting::BED_WIDTH_ALL)['bedWidth']);

	}

	/**
	 * Les largeurs d'un groupe se mettent en commun : une conduite pratiquée par des fermes à
	 * 75 et d'autres à 80 n'en fait qu'une, qui passe le seuil à leur somme. Sa planche se
	 * dessine sur la plus large du groupe, que la majorité de ses cultures soit à 75 ou à 80.
	 */
	public function testWidthsOfAGroupArePooled(): void {

		$floor = CultivationAiSetting::CONDUCT_STRUCTURES_MIN;

		$conducts = CultivationConductAiLib::getConducts($this->getConductsCollection([
			[$floor - 2, ['bedWidth' => 75]],
			[2, ['bedWidth' => 80, 'density' => 6.0]],
		]), 80);

		$this->assertEquals(75, $conducts['bedWidth']);
		$this->assertEquals([75, 80], $conducts['widths'][75]['bedWidths']);
		$this->assertEquals(1, count($this->getAll($conducts)), 'les deux largeurs ne doivent faire qu\'une conduite');
		$this->assertEquals($floor, $this->getAll($conducts)[0]['structures']);
		$this->assertEquals(80, $this->getAll($conducts)[0]['bedWidth'], 'la planche se dessine sur la plus large du groupe, même quand la majorité est à 75');

	}

	/**
	 * Les plans d'un groupe se dessinent tous sur la même planche, la plus large du groupe :
	 * une conduite à 75 cm et une autre à 80 cm ne changent pas la référence des cotes d'une
	 * carte à l'autre (Discord, 2026-09-25, cartes à 40 cm puis 38 cm entre les bords).
	 */
	public function testGroupDrawsEveryConductOnItsWidestBed(): void {

		$floor = CultivationAiSetting::CONDUCT_STRUCTURES_MIN;

		$conducts = CultivationConductAiLib::getConducts($this->getConductsCollection([
			[$floor, ['bedWidth' => 75]],
			[$floor, ['bedWidth' => 80, 'rows' => 3]],
		]), 75);

		$this->assertEquals([80, 80], array_column($this->getAll($conducts), 'bedWidth'), 'une conduite pratiquée à 75 cm se dessine aussi sur 80 cm');

		$all = CultivationConductAiLib::getConducts($this->getConductsCollection([
			[$floor, ['bedWidth' => 75]],
		]), CultivationAiSetting::BED_WIDTH_ALL);

		$this->assertEquals([], $all['modes'], 'aucune carte sur l\'onglet Toutes');

	}

	/**
	 * Une largeur donnée ouvre le groupe de la largeur de la liste la plus proche, la plus
	 * étroite à égale distance, qu'il porte des conduites ou non (2026-09-21).
	 */
	public function testWidthIsResolvedToTheNearestListed(): void {

		$cCultivation = $this->getCollection(CultivationAiSetting::CONDUCT_STRUCTURES_MIN);

		foreach([80 => 75, 85 => 75, 90 => 75, 110 => 100, 200 => 120, 1 => 75, 100 => 100] as $requested => $expected) {
			$conducts = CultivationConductAiLib::getConducts($cCultivation, $requested);
			$this->assertEquals($expected, $conducts['bedWidth'], $requested.' cm doit ouvrir '.$expected.' cm');
		}

	}

	/**
	 * Une largeur de la liste qui ne porte rien reste ouverte : basculer seul sur une autre ferait
	 * lire ses densités sous le titre de celle qu'on a demandée.
	 */
	public function testEmptyListedWidthIsHeld(): void {

		$conducts = CultivationConductAiLib::getConducts($this->getCollection(CultivationAiSetting::CONDUCT_STRUCTURES_MIN), 120);

		$this->assertEquals(120, $conducts['bedWidth']);
		$this->assertEquals([], $conducts['modes']);
		$this->assertEquals([], $this->getAll($conducts));
		$this->assertEquals(1, $conducts['widths'][75]['conducts'], 'la largeur qui porte la conduite reste proposée');

	}

	/**
	 * Sans largeur demandée ni réglée, la plus pratiquée en structures, la plus étroite à
	 * égalité ; aucune quand l'espèce n'a pas de conduite.
	 */
	public function testWithoutWidthTheMostPractisedOpens(): void {

		$floor = CultivationAiSetting::CONDUCT_STRUCTURES_MIN;

		$wider = CultivationConductAiLib::getConducts($this->getConductsCollection([
			[$floor, ['bedWidth' => 80]],
			[$floor + 1, ['bedWidth' => 100]],
		]), NULL);

		$this->assertEquals(100, $wider['bedWidth'], 'la largeur la plus pratiquée doit s\'ouvrir');

		$tie = CultivationConductAiLib::getConducts($this->getConductsCollection([
			[$floor, ['bedWidth' => 100]],
			[$floor, ['bedWidth' => 80]],
		]), NULL);

		$this->assertEquals(75, $tie['bedWidth'], 'à égalité, la plus étroite');

		$none = CultivationConductAiLib::getConducts($this->getCollection($floor - 1), NULL);

		$this->assertEquals(NULL, $none['bedWidth'], 'sans conduite, aucune largeur ne s\'ouvre');

	}

	/**
	 * Les cultures d'une largeur hors liste ne se rangent sous aucune : une densité ne se
	 * transpose pas d'une largeur à l'autre.
	 */
	public function testCultivationOutsideTheListIsRangedNowhere(): void {

		$conducts = CultivationConductAiLib::getConducts(
			$this->getCollection(CultivationAiSetting::CONDUCT_STRUCTURES_MIN, ['bedWidth' => 85]),
			85
		);

		$this->assertEquals(array_keys(CultivationAiSetting::BED_WIDTHS), array_keys($conducts['widths']));
		$this->assertEquals([], $this->getAll($conducts), 'le groupe 75 - 80 ouvert ne doit pas ramasser les cultures de 85');

		foreach($conducts['widths'] as $bedWidth => $width) {
			$this->assertEquals(0, $width['conducts'], 'la largeur '.$bedWidth.' ne doit porter aucune conduite');
		}

	}

	/**
	 * Trois cartes par mode, les plus pratiquées, plein champ d'abord même quand le sous abri
	 * est plus pratiqué ; le total dit sur combien.
	 */
	public function testTopThreeOfEachMode(): void {

		$floor = CultivationAiSetting::CONDUCT_STRUCTURES_MIN;

		$conducts = CultivationConductAiLib::getConducts($this->getConductsCollection([
			[$floor, ['rows' => 1]],
			[$floor + 3, ['rows' => 2]],
			[$floor + 1, ['rows' => 3]],
			[$floor + 2, ['rows' => 4]],
			[$floor + 9, ['rows' => 2, 'mode' => Cultivation::GREENHOUSE]],
		]), 80);

		$this->assertEquals([Cultivation::OPEN_FIELD, Cultivation::GREENHOUSE], array_keys($conducts['modes']));

		$openField = $conducts['modes'][Cultivation::OPEN_FIELD];

		$this->assertEquals(CultivationAiSetting::CONDUCT_TOP, count($openField['top']));
		$this->assertEquals([2, 4, 3], array_column($openField['top'], 'rows'), 'les plus pratiquées, dans l\'ordre');
		$this->assertEquals(4, $openField['total']);

		$this->assertEquals(1, $conducts['modes'][Cultivation::GREENHOUSE]['total']);
		$this->assertEquals($floor + 9, $conducts['peak'], 'le plus grand effectif, tous modes confondus');

	}

	/**
	 * L'écran ne dit que plein champ et sous abri, chacun dans sa couleur : une culture en mode
	 * mixte n'entre dans aucune conduite.
	 */
	public function testMixedModeIsLeftOut(): void {

		$conducts = CultivationConductAiLib::getConducts(
			$this->getCollection(CultivationAiSetting::CONDUCT_STRUCTURES_MIN, ['mode' => Cultivation::MIX]),
			80
		);

		$this->assertEquals([], $this->getAll($conducts));
		$this->assertEquals(0, $conducts['widths'][75]['structures']);

	}

	/**
	 * Une bande par mode qui a des conduites, sur un axe commun. Chaque barre de
	 * l'histogramme est un intervalle de la grille et porte les cultures des conduites
	 * dont la médiane y tombe ; l'échelle couvre la plus haute. La grille découpe l'axe en
	 * intervalles égaux et passe par chaque graduation.
	 */
	public function testBandsFollowTheModes(): void {

		$floor = CultivationAiSetting::CONDUCT_STRUCTURES_MIN;

		$conducts = CultivationConductAiLib::getConducts($this->getConductsCollection([
			[$floor, ['rows' => 2, 'density' => 5.0]],
			[$floor, ['rows' => 3, 'density' => 7.5]],
		]), 80);

		$bands = $conducts['bands'];

		$this->assertEquals([Cultivation::OPEN_FIELD], array_keys($bands['modes']), 'un mode sans conduite n\'a pas de bande');

		$band = $bands['modes'][Cultivation::OPEN_FIELD];

		$this->assertEquals(2, $band['conducts']);
		$this->assertEquals($floor * 2, $band['cultivations']);
		$this->assertEquals(6.25, $band['density']);

		$axis = $bands['axis'];
		$values = array_column($axis['grid'], 'value');
		$intervals = count($values) + 1;
		$step = ($axis['max'] - $axis['min']) / $intervals;

		$this->assertEquals(2, count($band['bars']), 'une barre par intervalle qui porte une conduite');

		foreach($band['bars'] as $index => $bar) {

			$density = [5.0, 7.5][$index];

			$this->assertEquals($floor, $bar['cultivations']);
			$this->assertTrue($bar['from'] <= $density and $density < $bar['to'], $density.' hors de sa barre');
			$this->assertEquals(round($step, 6), round($bar['to'] - $bar['from'], 6), 'une barre fait un intervalle de la grille');
			$this->assertTrue(in_array($bar['from'], array_merge([$axis['min']], $values)), 'une barre commence sur un trait de la grille');

		}

		$this->assertTrue($band['scale']['max'] >= $floor and end($band['scale']['ticks']) === $band['scale']['max']);

		$this->assertTrue($intervals >= CultivationAiSetting::CONDUCT_GRID_MIN and $intervals <= CultivationAiSetting::CONDUCT_GRID_MAX, $intervals.' intervalles');

		foreach($values as $index => $value) {
			$this->assertEquals(round($axis['min'] + ($index + 1) * $step, 6), $value);
		}

		$this->assertEquals($axis['ticks'], array_column(array_filter($axis['grid'], fn(array $line) => $line['tick']), 'value'));

		$this->assertTrue($bands['axis']['min'] <= 5.0 and $bands['axis']['max'] >= 7.5, 'l\'axe doit encadrer les deux conduites');

	}

	/**
	 * Deux conduites dont les médianes tombent dans le même intervalle font une seule
	 * barre, qui porte la somme de leurs cultures.
	 */
	public function testConductsInOneIntervalShareTheirBar(): void {

		$floor = CultivationAiSetting::CONDUCT_STRUCTURES_MIN;

		$conducts = CultivationConductAiLib::getConducts($this->getConductsCollection([
			[$floor, ['rows' => 2, 'density' => 5.0]],
			[$floor + 1, ['rows' => 3, 'density' => 5.01]],
			[$floor, ['rows' => 4, 'density' => 9.0]],
		]), 80);

		$bars = $conducts['bands']['modes'][Cultivation::OPEN_FIELD]['bars'];

		$this->assertEquals([$floor * 2 + 1, $floor], array_column($bars, 'cultivations'));

	}

	/**
	 * L'échelle verticale finit sur une graduation qui couvre la plus haute barre, au pas
	 * rond le plus fin qui tient dans le nombre de graduations permis.
	 */
	public function testScaleCoversTheHighestBar(): void {

		$getScale = new \ReflectionMethod(CultivationConductAiLib::class, 'getScale');

		foreach([
			1 => [1],
			3 => [1, 2, 3],
			4 => [1, 2, 3, 4],
			5 => [2, 4, 6],
			45 => [20, 40, 60],
			305 => [100, 200, 300, 400],
		] as $top => $ticks) {

			$scale = $getScale->invoke(NULL, $top);

			$this->assertEquals(['max' => end($ticks), 'ticks' => $ticks], $scale, 'plus haute barre à '.$top);
			$this->assertTrue(count($scale['ticks']) <= CultivationAiSetting::CONDUCT_SCALE_TICKS);

		}

	}

	/**
	 * Sur des axes d'échelles différentes, l'axe ne dépasse pas son nombre de graduations, et
	 * la grille garde un nombre d'intervalles dans ses bornes et un trait sur chaque
	 * graduation. Un pas de grille de 1 sous des graduations de 2,5 en manquerait une sur deux.
	 */
	public function testGridFallsOnEveryTick(): void {

		$getAxis = new \ReflectionMethod(CultivationConductAiLib::class, 'getAxis');
		$getGrid = new \ReflectionMethod(CultivationConductAiLib::class, 'getGrid');

		foreach([[2.0, 8.0], [1.6, 3.6], [70.0, 160.0], [0.3, 0.45], [10.0, 22.0], [1.0, 1.1], [3.0, 300.0], [55.0, 265.0]] as $densities) {

			$axis = $getAxis->invoke(NULL, $densities);
			$grid = $getGrid->invoke(NULL, $axis);

			$intervals = count($grid) + 1;
			$label = implode('-', $densities).' : '.$intervals.' intervalles';

			$this->assertTrue($intervals >= CultivationAiSetting::CONDUCT_GRID_MIN and $intervals <= CultivationAiSetting::CONDUCT_GRID_MAX, $label);
			$this->assertEquals($axis['ticks'], array_column(array_filter($grid, fn(array $line) => $line['tick']), 'value'), $label);
			$this->assertTrue(count($axis['ticks']) <= CultivationAiSetting::CONDUCT_AXIS_TICKS, $label.', '.count($axis['ticks']).' graduations');

		}

	}

	/**
	 * L'axe se borne sur les médianes des conduites : une densité extrême saisie par une seule
	 * structure, dans une conduite ou hors de toute conduite, ne le déplace pas. Bornée sur les
	 * valeurs brutes, elle deviendrait une valeur lisible à l'écran.
	 */
	public function testAxisIgnoresAnIsolatedDensity(): void {

		$floor = CultivationAiSetting::CONDUCT_STRUCTURES_MIN;

		$reference = CultivationConductAiLib::getConducts($this->getCollection($floor), 80);

		$inConduct = $this->getCollection($floor);
		$inConduct[] = $this->getCultivation($floor + 1, ['density' => 500.0]);

		$outOfConduct = $this->getCollection($floor);
		$outOfConduct[] = $this->getCultivation($floor + 1, ['rows' => 5, 'density' => 500.0]);

		$this->assertEquals($reference['bands']['axis'], CultivationConductAiLib::getConducts($inConduct, 80)['bands']['axis'], 'dans la conduite');
		$this->assertEquals($reference['bands']['axis'], CultivationConductAiLib::getConducts($outOfConduct, 80)['bands']['axis'], 'hors de toute conduite');

	}

	/**
	 * La grille de la vue experte range ses lignes et ses colonnes par valeur croissante, sans
	 * doublon, quel que soit l'ordre de pratique.
	 */
	public function testMapAxesAreSorted(): void {

		$floor = CultivationAiSetting::CONDUCT_STRUCTURES_MIN;

		$conducts = CultivationConductAiLib::getConducts($this->getConductsCollection([
			[$floor + 2, ['rows' => 4, 'plantSpacing' => 30.0]],
			[$floor + 1, ['rows' => 2, 'plantSpacing' => 40.0]],
			[$floor, ['rows' => 4, 'plantSpacing' => 40.0, 'mode' => Cultivation::GREENHOUSE]],
		]), 80);

		$this->assertEquals([2, 4], $conducts['map']['rows']);
		$this->assertEquals([30.0, 40.0], $conducts['map']['spacings']);
		$this->assertEquals(3, count($conducts['map']['conducts']));

	}

	/**
	 * La légende de la grille ne nomme que les modes qu'elle montre, dans l'ordre des bandes.
	 */
	public function testMapNamesOnlyItsModes(): void {

		$floor = CultivationAiSetting::CONDUCT_STRUCTURES_MIN;

		$conducts = CultivationConductAiLib::getConducts($this->getCollection($floor), 80);

		$this->assertEquals([Cultivation::OPEN_FIELD], $conducts['map']['modes']);

		$conducts = CultivationConductAiLib::getConducts($this->getConductsCollection([
			[$floor + 1, ['mode' => Cultivation::GREENHOUSE]],
			[$floor, ['rows' => 3]],
		]), 80);

		$this->assertEquals([Cultivation::OPEN_FIELD, Cultivation::GREENHOUSE], $conducts['map']['modes']);

	}

	public function testCultivationInABlockHasNoConduct(): void {

		$conducts = CultivationConductAiLib::getConducts(
			$this->getCollection(CultivationAiSetting::CONDUCT_STRUCTURES_MIN, ['use' => Cultivation::BLOCK]),
			80
		);

		$this->assertEquals([], $this->getAll($conducts));

	}

	/**
	 * Une conduite se dessine, donc elle demande ses mesures : le plan ne se devine pas à
	 * deux sur trois, et une culture incomplète sort avant même d'être comptée dans un seuil.
	 */
	public function testConductWithAMissingMeasureIsLeftOut(): void {

		foreach(['rows', 'plantSpacing', 'density'] as $property) {

			$conducts = CultivationConductAiLib::getConducts(
				$this->getCollection(CultivationAiSetting::CONDUCT_STRUCTURES_MIN, [$property => NULL]),
				80
			);

			$this->assertEquals([], $this->getAll($conducts), 'sans '.$property.', la conduite ne doit pas s\'afficher');
			$this->assertEquals(0, $conducts['widths'][75]['structures'], 'sans '.$property.', le groupe 75 - 80 ne doit compter personne');

		}

	}

}
?>
