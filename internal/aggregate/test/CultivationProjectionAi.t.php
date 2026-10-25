<?php
namespace aggregate;

/**
 * Ce que la projection nocturne écarte, et ce qu'un passage interrompu laisse derrière lui.
 *
 * La page de la bibliothèque ne voit que les lignes déposées par CultivationProjectionAiLib : une
 * ligne qui aurait dû être écartée est servie à tout le monde pendant une nuit, sans rien à l'écran
 * pour le signaler. Quatre écarts sont éprouvés, chacun avec son pendant retenu, pour qu'un contrôle
 * qui ne trouve rien ne passe pas pour réussi :
 *
 * 1. une structure fermée, ou qui n'a pas consenti au partage, ne contribue pas ;
 * 2. une culture de série pérenne n'a pas de mode, donc pas de ligne ;
 * 3. une part variétale sans variété de référence n'entre pas dans la table ;
 * 4. la préparation d'un passage interrompu est jetée sans toucher à la version en ligne.
 *
 * Les méthodes éprouvées sont protégées : la projection entière réécrit les deux tables en
 * quarante secondes, et ces règles se lisent chacune sur une structure ou une culture. Elles sont
 * donc appelées par réflexion, sur des lignes réelles de la base de dev.
 *
 * ⚠️ Ce que ce découpage ne voit pas : la purge est éprouvée sur les deux deleteOffline() que
 * project() appelle, pas sur project() lui-même. Un project() qui cesserait de purger laisserait
 * ce test vert ; le voir demanderait de rejouer la projection entière dans la transaction.
 *
 * Isolation : une transaction par méthode, annulée en tearDown(). Les deux tables de la projection
 * partagent la connexion de la base commune, une seule transaction les couvre.
 *
 * Lancement : dev/test package=aggregate file=CultivationProjectionAi
 */
class CultivationProjectionAiTest extends \Test {

	public function setUp(): void {
		Cultivation::model()->beginTransaction();
	}

	public function tearDown(): void {
		Cultivation::model()->rollBack();
	}

	private function invoke(string $method, mixed ...$arguments): mixed {
		return new \ReflectionMethod(CultivationProjectionAiLib::class, $method)->invoke(NULL, ...$arguments);
	}

	private function getPlantReference(): \plant\Plant {

		return \plant\Plant::model()
			->select(['id', 'fqn'])
			->where('structure IS NULL')
			->where('fqn IS NOT NULL')
			->get();

	}

	/**
	 * Une ligne de faits hors ligne, telle que la projection la dépose avant publication. Seuls
	 * l'espèce, la culture source et la structure comptent pour les méthodes éprouvées ; le reste
	 * remplit les colonnes non nullables.
	 */
	private function createOffline(\plant\Plant $ePlant, \series\Cultivation $eCultivationSource): void {

		CultivationAiLib::createByPlant(new \Collection([
			new Cultivation([
				'plant' => $ePlant,
				'seriesCultivation' => $eCultivationSource['id'],
				'structure' => $eCultivationSource['structure'],
				'season' => (int)date('Y'),
				'startAction' => Cultivation::PLANTING,
				'seedling' => NULL,
				'mode' => Cultivation::OPEN_FIELD,
				// `use` n'a pas de défaut et ne peut pas en avoir : une culture est en
				// planche ou en bloc, et la seconde n'a ni largeur ni rangs. Une fixture
				// qui l'omettait faisait échouer l'insertion, pas l'assertion.
				'use' => Cultivation::BED,
				'startWeek' => 20,
				'month' => 5,
				'seedlingIndex' => NULL,
				'harvestPeriod' => Cultivation::WEEK,
				'harvestFrom' => NULL,
				'harvestTo' => NULL,
				'harvestCount' => NULL,
				'harvestWeeks' => [],
				'altitude' => NULL,
				'structureSeries' => 0,
				'online' => FALSE,
			])
		]));

	}

	public function testStructureClosedNotConsentingOrNotInstalledIsLeftOut(): void {

		$structures = $this->invoke('getStructures');

		// `getInstalledSql()` écarte le décor des tests (StructureSetting::FIXTURE_STRUCTURES) quel
		// que soit son état : chaque structure choisie ici est prise hors du décor, sinon elle serait
		// écartée pour une autre raison que celle que le test éprouve.
		$eStructureContributing = \structure\Structure::model()
			->select(['id'])
			->whereId('NOT IN', \structure\StructureSetting::FIXTURE_STRUCTURES)
			->whereStatus(\structure\Structure::ACTIVE)
			->whereConsentData(TRUE)
			->whereType(\structure\Structure::PRODUCER)
			->whereProducerType(\structure\Structure::REAL)
			->get();

		// `IS NOT TRUE` couvre le refus et l'absence de réponse : consentData est nullable, et un
		// `whereConsentData(FALSE)` ne trouverait aucune des structures qui n'ont jamais répondu.
		$eStructureNotConsenting = \structure\Structure::model()
			->select(['id'])
			->whereId('NOT IN', \structure\StructureSetting::FIXTURE_STRUCTURES)
			->whereStatus(\structure\Structure::ACTIVE)
			->where('consentData IS NOT TRUE')
			->get();

		// Fermée mais consentante : le seul critère qui l'écarte est la fermeture.
		$eStructureClosed = \structure\Structure::model()
			->select(['id'])
			->whereId('NOT IN', \structure\StructureSetting::FIXTURE_STRUCTURES)
			->whereStatus(\structure\Structure::CLOSED)
			->whereConsentData(TRUE)
			->get();

		// Active et consentante mais pas installée : le seul critère qui l'écarte est la catégorie
		// (chantier fermes-reelles, lot L4).
		$eStructureNotInstalled = \structure\Structure::model()
			->select(['id'])
			->whereId('NOT IN', \structure\StructureSetting::FIXTURE_STRUCTURES)
			->whereStatus(\structure\Structure::ACTIVE)
			->whereConsentData(TRUE)
			->whereType(\structure\Structure::PRODUCER)
			->whereProducerType(\structure\Structure::TEST)
			->get();

		if($eStructureContributing->empty() or $eStructureNotConsenting->empty() or $eStructureClosed->empty() or $eStructureNotInstalled->empty()) {
			$this->assertFail('la base de dev n\'a pas les quatre structures du décor (active consentante installée, active non consentante, fermée consentante, active consentante de test)');
			return;
		}

		$contributing = \structure\Structure::model()
			->whereId('NOT IN', \structure\StructureSetting::FIXTURE_STRUCTURES)
			->whereStatus(\structure\Structure::ACTIVE)
			->whereConsentData(TRUE)
			->whereType(\structure\Structure::PRODUCER)
			->whereProducerType(\structure\Structure::REAL)
			->count();

		$this->assertEquals($contributing, count($structures), 'une structure retenue sur trois critères doit l\'être sur les trois seulement');
		$this->assertTrue(isset($structures[$eStructureContributing['id']]), 'la structure active et consentante '.$eStructureContributing['id'].' doit contribuer');
		$this->assertFalse(isset($structures[$eStructureNotConsenting['id']]), 'la structure non consentante '.$eStructureNotConsenting['id'].' ne doit pas contribuer');
		$this->assertFalse(isset($structures[$eStructureClosed['id']]), 'la structure fermée '.$eStructureClosed['id'].' ne doit pas contribuer');
		$this->assertFalse(isset($structures[$eStructureNotInstalled['id']]), 'la ferme de test '.$eStructureNotInstalled['id'].' ne doit pas contribuer');

	}

	public function testPerennialSeriesIsLeftOut(): void {

		$eCultivationSourcePerennial = $this->getCultivationSourceByCycle(\series\Series::PERENNIAL);
		$eCultivationSourceAnnual = $this->getCultivationSourceByCycle(\series\Series::ANNUAL);

		if($eCultivationSourcePerennial->empty() or $eCultivationSourceAnnual->empty()) {
			$this->assertFail('la base de dev n\'a pas de culture sur une série pérenne et sur une série annuelle');
			return;
		}

		$seriesByCultivation = $this->invoke('getSeriesByCultivation', new \Collection([
			$eCultivationSourcePerennial['id'] => $eCultivationSourcePerennial,
			$eCultivationSourceAnnual['id'] => $eCultivationSourceAnnual,
		]));

		$eSeriesAnnual = $seriesByCultivation[$eCultivationSourceAnnual['id']] ?? new \series\Series();

		$this->assertEquals($eCultivationSourceAnnual['series']['mode'], $eSeriesAnnual['mode'] ?? NULL, 'la culture annuelle '.$eCultivationSourceAnnual['id'].' doit porter le mode de sa série');
		$this->assertEquals($eCultivationSourceAnnual['series']['bedWidth'], $eSeriesAnnual['bedWidth'] ?? NULL, 'la culture annuelle '.$eCultivationSourceAnnual['id'].' doit porter la largeur de planche de sa série');
		$this->assertFalse(isset($seriesByCultivation[$eCultivationSourcePerennial['id']]), 'la culture pérenne '.$eCultivationSourcePerennial['id'].' ne doit porter aucune série');

	}

	private function getCultivationSourceByCycle(string $cycle): \series\Cultivation {

		$cSeries = \series\Series::model()
			->select(['id'])
			->whereCycle($cycle)
			->getCollection(0, 50);

		return \series\Cultivation::model()
			->select(['id', 'series' => ['mode', 'bedWidth']])
			->whereSeries('IN', $cSeries)
			->get();

	}

	public function testVarietyWithoutReferenceIsLeftOut(): void {

		$ePlant = $this->getPlantReference();

		// Une variété propre à une structure et jamais rattachée au référentiel : c'est le cas que
		// la projection écarte, et non une variété du référentiel partagé.
		$eSliceUnreferenced = $this->getSlice('reference IS NULL AND structure IS NOT NULL');
		$eSliceReferenced = $this->getSlice('reference IS NOT NULL');

		if($ePlant->empty() or $eSliceUnreferenced->empty() or $eSliceReferenced->empty()) {
			$this->assertFail('la base de dev n\'a pas de part variétale avec et sans variété de référence');
			return;
		}

		$cCultivationSource = \series\Cultivation::model()
			->select(['id', 'structure'])
			->whereId('IN', [$eSliceUnreferenced['cultivation']['id'], $eSliceReferenced['cultivation']['id']])
			->getCollection(index: 'id');

		foreach($cCultivationSource as $eCultivationSource) {
			$this->createOffline($ePlant, $eCultivationSource);
		}

		// Ce que la projection doit rendre, lu d'ailleurs que par elle : chaque part des deux
		// cultures dont la variété a une référence.
		$cSlice = \series\Slice::model()
			->select(['variety' => ['reference']])
			->whereCultivation('IN', $cCultivationSource)
			->whereVariety('NOT IN', [\plant\PlantSetting::VARIETY_UNKNOWN, \plant\PlantSetting::VARIETY_MIX])
			->getCollection();

		$referenced = [];

		foreach($cSlice as $eSlice) {
			if($eSlice['variety']['reference']->notEmpty()) {
				$referenced[] = $eSlice['variety']['reference']['id'];
			}
		}

		$cCultivationVariety = $this->invoke('getVariety', $ePlant, $cCultivationSource);

		$found = [];

		foreach($cCultivationVariety as $eCultivationVariety) {
			$found[] = $eCultivationVariety['variety']['id'];
		}

		sort($referenced);
		sort($found);

		$this->assertTrue($cSlice->count() > count($referenced), 'le décor doit porter au moins une part sans référence');
		$this->assertTrue($found !== [], 'les parts rattachées au référentiel doivent être projetées');
		$this->assertEquals($referenced, $found, 'seules les parts rattachées au référentiel sont projetées');

	}

	private function getSlice(string $condition): \series\Slice {

		$cVariety = \plant\Variety::model()
			->select(['id'])
			->where($condition)
			->whereId('NOT IN', [\plant\PlantSetting::VARIETY_UNKNOWN, \plant\PlantSetting::VARIETY_MIX])
			->getCollection(0, 200);

		return \series\Slice::model()
			->select(['cultivation'])
			->whereVariety('IN', $cVariety)
			->get();

	}

	/**
	 * Le rendement attendu voyage de la culture source à la ligne de faits.
	 *
	 * C'est la seconde source de quantité de la composition d'un panier, pour les espèces que les
	 * compositions partagées ne couvrent pas. Rien à l'écran ne dirait qu'elle est vide : la
	 * première source prendrait toute la place et la règle proposerait moins d'espèces, sans
	 * erreur.
	 *
	 * L'espèce est choisie sur ce qu'elle doit être — assez de cultures pour que les deux cas
	 * existent, assez peu pour que la projection tienne dans le test — et non sur un identifiant
	 * qui changerait au prochain rechargement de la base de dev.
	 *
	 * ⚠️ Les deux colonnes n'ont pas le même type : `float32` à la source, `decimal(8, 2)` ici.
	 * La valeur arrive donc arrondie au centième, ce que la comparaison reproduit.
	 */
	public function testExpectedYieldIsCarriedFromTheSourceCultivation(): void {

		$ePlant = $this->getPlantWithAndWithoutExpectedYield();

		if($ePlant->empty()) {
			$this->assertFail('la base de dev n\'a aucune espèce de référence annuelle dont les cultures portent à la fois un rendement attendu et pas de rendement attendu');
			return;
		}

		$this->assertEquals(0, Cultivation::model()->wherePlant($ePlant)->whereOnline(FALSE)->count(), 'l\'espèce '.$ePlant['fqn'].' ne doit rien avoir hors ligne avant la projection');

		$seedlingActions = array_flip(
			\work\Action::model()
				->select(['id'])
				->where('fqn', \ACTION_SEMIS_PEPINIERE)
				->getColumn('id')
		);

		$this->invoke('projectPlant', $ePlant, $seedlingActions, $this->invoke('getStructures'));

		$cCultivation = Cultivation::model()
			->select(['seriesCultivation', 'yieldExpected'])
			->wherePlant($ePlant)
			->whereOnline(FALSE)
			->getCollection();

		if($cCultivation->empty()) {
			$this->assertFail('la projection de '.$ePlant['fqn'].' n\'a déposé aucune ligne : le contrôle ne verrait rien');
			return;
		}

		$cCultivationSource = \series\Cultivation::model()
			->select(['id', 'yieldExpected'])
			->whereId('IN', $cCultivation->getColumn('seriesCultivation'))
			->getCollection(index: 'id');

		$diverging = [];
		$withYield = 0;
		$withoutYield = 0;

		foreach($cCultivation as $eCultivation) {

			$expected = $cCultivationSource[$eCultivation['seriesCultivation']]['yieldExpected'];
			$expected = ($expected === NULL) ? NULL : round($expected, 2);

			if($expected === NULL) {
				$withoutYield++;
			} else {
				$withYield++;
			}

			if($eCultivation['yieldExpected'] !== $expected) {
				$diverging[] = $eCultivation['seriesCultivation'].' : '.($expected ?? 'NULL').' à la source, '.($eCultivation['yieldExpected'] ?? 'NULL').' projeté';
			}

		}

		$this->assertTrue($withYield > 0, 'la projection de '.$ePlant['fqn'].' doit porter au moins une culture avec rendement, sinon le contrôle ne prouve rien');
		$this->assertTrue($withoutYield > 0, 'la projection de '.$ePlant['fqn'].' doit porter au moins une culture sans rendement, sinon le contrôle ne distingue pas la recopie d\'un défaut');
		$this->assertEquals([], $diverging, 'rendements attendus divergents entre la culture source et la ligne de faits : '.implode(' ; ', $diverging));

	}

	/**
	 * Une espèce du référentiel que la projection retient, dont les cultures portent les deux cas.
	 *
	 * Les critères sont ceux de getPlantReference(), plus un plafond de cultures : la projection
	 * d'une espèce courante en écrit plusieurs milliers, ce qui ne prouverait rien de plus. La
	 * lecture s'arrête une culture au-dessus du plafond, de quoi savoir qu'il est dépassé sans
	 * charger les milliers de lignes d'une espèce courante.
	 */
	private function getPlantWithAndWithoutExpectedYield(): \plant\Plant {

		$cPlantReference = \plant\Plant::model()
			->select(['id', 'fqn'])
			->where('structure IS NULL')
			->where('fqn IS NOT NULL')
			->whereUsageVegetable(TRUE)
			->whereCycle(\plant\Plant::ANNUAL)
			->whereStatus(\plant\Plant::ACTIVE)
			->getCollection();

		foreach($cPlantReference as $ePlantReference) {

			$plantIds = \plant\Plant::model()
				->select(['id'])
				->where('fqn', $ePlantReference['fqn'])
				->getColumn('id');

			$cCultivationSource = \series\Cultivation::model()
				->select(['id', 'yieldExpected'])
				->wherePlant('IN', $plantIds)
				->getCollection(0, 61);

			if($cCultivationSource->count() > 60) {
				continue;
			}

			$withYield = $cCultivationSource->find(fn($eCultivationSource) => $eCultivationSource['yieldExpected'] !== NULL)->count();

			if($withYield > 0 and $withYield < $cCultivationSource->count()) {
				return $ePlantReference;
			}

		}

		return new \plant\Plant();

	}

	public function testInterruptedRunIsPurgedWithoutTouchingOnline(): void {

		$onlineCultivation = Cultivation::model()->whereOnline(TRUE)->count();
		$onlineVariety = CultivationVariety::model()->whereOnline(TRUE)->count();

		$ePlant = $this->getPlantReference();

		$eCultivationSource = \series\Cultivation::model()
			->select(['id', 'structure'])
			->get();

		$eVariety = \plant\Variety::model()
			->select(['id', 'name'])
			->where('reference IS NULL AND structure IS NULL')
			->whereId('NOT IN', [\plant\PlantSetting::VARIETY_UNKNOWN, \plant\PlantSetting::VARIETY_MIX])
			->get();

		if($onlineCultivation === 0 or $onlineVariety === 0) {
			$this->assertFail('aucune ligne en ligne : la projection n\'a jamais tourné sur la base de dev, le contrôle ne verrait rien');
			return;
		}

		if($ePlant->empty() or $eCultivationSource->empty() or $eVariety->empty()) {
			$this->assertFail('la base de dev n\'a pas d\'espèce de référence, de culture ou de variété du référentiel pour écrire le reliquat');
			return;
		}

		// Le reliquat d'un passage interrompu : une ligne de faits et sa part variétale, hors ligne.
		$this->createOffline($ePlant, $eCultivationSource);

		CultivationVarietyAiLib::createByPlant(new \Collection([
			new CultivationVariety([
				'cultivation' => CultivationAiLib::getOfflineByPlant($ePlant)->first(),
				'plant' => $ePlant,
				'structure' => $eCultivationSource['structure'],
				'variety' => $eVariety,
				'name' => $eVariety['name'],
				'searchKey' => CultivationVarietyAiLib::getSearchKey($eVariety['name']),
				'online' => FALSE,
			])
		]));

		$this->assertEquals(1, Cultivation::model()->whereOnline(FALSE)->count(), 'le reliquat doit être écrit avant la purge');
		$this->assertEquals(1, CultivationVariety::model()->whereOnline(FALSE)->count(), 'la part du reliquat doit être écrite avant la purge');

		// L'ordre de project() : les parts d'abord, les faits qu'elles désignent ensuite.
		CultivationVarietyAiLib::deleteOffline();
		CultivationAiLib::deleteOffline();

		$this->assertEquals(0, Cultivation::model()->whereOnline(FALSE)->count(), 'le reliquat doit être jeté');
		$this->assertEquals(0, CultivationVariety::model()->whereOnline(FALSE)->count(), 'la part du reliquat doit être jetée');
		$this->assertEquals($onlineCultivation, Cultivation::model()->whereOnline(TRUE)->count(), 'la version en ligne ne doit pas bouger');
		$this->assertEquals($onlineVariety, CultivationVariety::model()->whereOnline(TRUE)->count(), 'les parts en ligne ne doivent pas bouger');

	}

}
?>
