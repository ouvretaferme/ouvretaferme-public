<?php
namespace toolbox;

/**
 * « Ajouter une espèce » (r48c, planche r48), le panneau de l'onglet « Espèces » :
 *
 * - ce qui s'ajoute (`CompositionSpeciesLib::getAddable()`) : les espèces en activité de la
 *   structure que le plan ne livre pas, les siennes propres comprises, et jamais une espèce d'une
 *   autre structure, du référentiel partagé ou déjà livrée ;
 * - une espèce que la communauté ne compte pas : son unité, sa quantité au panier et son prix sont
 *   exigés, le climat se demande une fois quand le plan n'en porte pas, il s'écrit avec l'espèce, et
 *   l'espèce entre alors dans la source du plan, dans l'onglet et dans les paniers ;
 * - une espèce propre à la structure, qu'aucune source ne date : le climat ne se demande pas, sa
 *   saison se trace aux poignées du panneau et une semaine au moins est exigée, elle naît en
 *   fréquence « base » et les paniers la servent dans ces seules semaines, et sa fiche ne se
 *   supprime pas tant que les paniers la livrent ;
 * - la saison réglée aux poignées (r59), postée comme le panneau la poste : renvoyée telle quelle,
 *   elle reste celle de la source, tirée d'un cran, elle est réglée à la main ;
 * - le panneau, dans chacun de ses états, et « Régler une espèce » pour une telle espèce ;
 * - les réglages de « Régler une espèce », que le panneau pose à l'identique sous la saison (r60), et
 *   que l'envoi construit et écrit : fréquence, pas, minimum, quantité par défaut et produit vendu ;
 * - « Recommencer », dont le départ montre et renvoie les espèces ajoutées, et dessine la saison
 *   réglée à la main que le nouveau plan gardera ;
 * - la déclinaison : le panneau n'en crée plus (r75, retrait de r71), une copie que le plan livre
 *   déjà ne se propose pas et son envoi forgé n'écrit rien ;
 * - la variante (r76), une espèce du plan de plus sur la fiche de l'espèce qu'elle décline : elle lit
 *   la communauté sur cette fiche, se range sous son espèce nue, ne partage jamais un panier avec
 *   elle, et « Recommencer » la reconduit.
 *
 * La remise d'une espèce retirée, qui passe par le même envoi, est éprouvée par
 * `CompositionSpeciesTabTest::testExcludeAdd()`, et les droits par
 * `RightsTest::testCompositionSpeciesTab()`.
 *
 * Les gestes s'éprouvent sur un plan écrit par le départ pour une structure qui n'en a pas, parti de
 * la communauté et sans climat : c'est le plan auquel le panneau demande le climat.
 *
 * Isolation : la transaction de `TransactionTest`, annulée après chaque méthode.
 *
 * Lancement : dev/test package=toolbox file=CompositionSpeciesAdd
 */
class CompositionSpeciesAddTest extends \dev\TransactionTest {

	const SEASON = 2026;
	const CLIMATE = CompositionPlan::OCEANIC;

	private \user\User $eUserInitial;

	protected function getTransactionModel(): \ModuleModel {
		return CompositionPlan::model();
	}

	public function setUp(): void {

		parent::setUp();

		$this->eUserInitial = \user\ConnectionLib::getOnline();

	}

	public function tearDown(): void {

		\user\ConnectionLib::setOnline($this->eUserInitial);

		parent::tearDown();

	}

	/**
	 * Ce qui s'ajoute est toute espèce en activité de la structure dont le plan ne livre pas l'espèce
	 * qu'elle sert, une espèce propre comprise. L'envoi ne trouve ni la copie d'une espèce que le plan
	 * livre déjà (r75, retrait de r71), ni une espèce du référentiel partagé, ni celle d'une autre
	 * structure, ni une espèce désactivée.
	 */
	public function testAddable(): void {

		$eStructure = $this->getStructureWithoutPlan();
		$eCompositionPlan = $this->createStarted($eStructure);

		$ePlantOwn = $this->createPlantOwn($eStructure);

		$plantIdsDelivered = $this->getPlantIdsDelivered($eStructure);
		$plantIdsAddable = CompositionSpeciesLib::getAddable($eCompositionPlan);

		$cPlantStructure = \plant\Plant::model()
			->select(['id', 'reference'])
			->whereStructure($eStructure)
			->whereStatus(\plant\Plant::ACTIVE)
			->getCollection(index: 'id');

		$plantIdsServing = [];
		$plantIdsFree = [];

		foreach($cPlantStructure as $ePlantStructure) {

			$plantIdPlan = $ePlantStructure['reference']->empty() ? $ePlantStructure['id'] : $ePlantStructure['reference']['id'];

			if(in_array($plantIdPlan, $plantIdsDelivered, TRUE)) {
				$plantIdsServing[] = $ePlantStructure['id'];
			} else {
				$plantIdsFree[] = $ePlantStructure['id'];
			}

		}

		// Une espèce livrée se propose aussi, et s'ajoute en variante (r76 lot 2, piste B).
		$plantIdsExpected = array_merge($plantIdsFree, $plantIdsServing);

		$this->assertTrue(count($plantIdsServing) > 10, 'la structure porte plus de dix espèces que le plan livre, sans quoi le contrôle ne verrait rien');
		$this->assertTrue(count($plantIdsFree) > 10, 'et plus de dix qu\'il ne livre pas');

		sort($plantIdsAddable);
		sort($plantIdsExpected);

		$this->assertEquals($plantIdsExpected, $plantIdsAddable, 'ce qui s\'ajoute est toute espèce en activité de la structure, livrée ou non');
		$this->assertTrue(in_array($ePlantOwn['id'], $plantIdsAddable, TRUE), 'une espèce propre à la structure s\'ajoute');

		$plantIdShared = $this->getPlantsTyped($eCompositionPlan)[0];

		$plantIdOther = \plant\Plant::model()
			->whereStructure('!=', $eStructure)
			->whereStatus(\plant\Plant::ACTIVE)
			->sort(['id' => SORT_DESC])
			->getValue('id');

		$this->assertTrue($cPlantStructure[$plantIdsServing[0]]['reference']->notEmpty(), 'la première espèce livrée est une copie du référentiel, sans quoi le contrôle suivant ne verrait rien');
		$eCompositionSpeciesServing = CompositionSpeciesLib::getForAdd($eCompositionPlan, $plantIdsServing[0]);

		$this->assertTrue($eCompositionSpeciesServing->notEmpty() and $eCompositionSpeciesServing['eCompositionSpeciesBare']->notEmpty() and $eCompositionSpeciesServing->exists() === FALSE, 'l\'envoi trouve une copie que le plan livre déjà, et en fait une variante neuve');
		$this->assertFalse(CompositionSpeciesLib::getForAdd($eCompositionPlan, $this->getPlantStructureId($eStructure, $plantIdShared))->empty(), 'il trouve la copie d\'une espèce du référentiel');
		$this->assertTrue(CompositionSpeciesLib::getForAdd($eCompositionPlan, $plantIdShared)->empty(), 'pas l\'espèce du référentiel elle-même, qui n\'est pas à la structure');
		$this->assertTrue($plantIdOther !== NULL and CompositionSpeciesLib::getForAdd($eCompositionPlan, $plantIdOther)->empty(), 'ni l\'espèce d\'une autre structure');
		$this->assertFalse(CompositionSpeciesLib::getForAdd($eCompositionPlan, $ePlantOwn['id'])->empty(), 'il trouve l\'espèce propre');

		// `build()` aurait vérifié l'état posté par la fiche de l'espèce : c'est la constante du schéma.
		$ePlantOwn['status'] = \plant\Plant::INACTIVE;
		\plant\PlantLib::update($ePlantOwn, ['status']);

		$this->assertTrue(CompositionSpeciesLib::getForAdd($eCompositionPlan, $ePlantOwn['id'])->empty(), 'et plus une fois désactivée');
		$this->assertFalse(in_array($ePlantOwn['id'], CompositionSpeciesLib::getAddable($eCompositionPlan), TRUE), 'qui ne se propose plus');

	}

	/**
	 * Une espèce que la communauté ne compte pas, sur un plan parti de la communauté et sans climat :
	 * le climat se demande, et s'exige ; l'unité, la quantité au panier et le prix aussi, la quantité
	 * en pas entiers de son unité. Ajoutée, elle porte la saison du calendrier type, le plan garde sa
	 * référence et prend le climat, et l'espèce entre dans la source du plan, dans l'onglet et dans
	 * les paniers. Le climat ne se redemande pas pour la suivante.
	 */
	public function testTyped(): void {

		$eStructure = $this->getStructureWithoutPlan();
		$eCompositionPlan = $this->createStarted($eStructure);

		[$plantId, $plantIdNext] = $this->getPlantsTyped($eCompositionPlan);

		$eCompositionSpecies = CompositionSpeciesLib::getForAdd($eCompositionPlan, $this->getPlantStructureId($eStructure, $plantId));

		$this->assertEquals([FALSE, FALSE], [$eCompositionSpecies->exists(), $eCompositionSpecies->isCommunity()], 'une espèce neuve, que la communauté ne compte pas');
		$this->assertEquals([$plantId, $this->getPlantStructureId($eStructure, $plantId)], [$eCompositionSpecies['plant']['id'], $eCompositionSpecies['ePlantStructure']['id']], 'le plan écrit l\'espèce du référentiel, le panneau montre celle de la structure');
		$this->assertTrue(CompositionSpeciesLib::isClimateAsked($eCompositionSpecies), 'le plan n\'a pas de climat et rien d\'autre ne date l\'espèce : le climat se demande');
		$this->assertEquals(['status' => CompositionSpecies::CUSTOM, 'windowWeeks' => []], CompositionSpeciesLib::getSeasonRestored($eCompositionSpecies), 'sans climat, elle n\'aurait pas de saison');

		$this->assertEquals(['CompositionPlan::climate.required'], $this->buildClimate($eCompositionPlan, []), 'le climat demandé est exigé');
		$this->assertEquals(['CompositionPlan::climate.check'], $this->buildClimate($eCompositionPlan, ['climate' => 'lunaire']), 'et pris parmi ceux du schéma');
		$this->assertEquals([], $this->buildClimate($eCompositionPlan, ['climate' => self::CLIMATE]), 'un climat du schéma est admis');
		$this->assertEquals([CompositionPlan::COMMUNITY, self::CLIMATE], [$eCompositionPlan['reference'], $eCompositionPlan['climate']], 'sur un plan qui garde sa référence');

		$kgId = $this->getUnitId('kg');

		$this->assertEquals(
			['CompositionSpecies::unit.offered', 'CompositionSpecies::quantityBasket.required', 'CompositionSpecies::price.required'],
			$this->buildTyped($eCompositionSpecies, ['unit' => '', 'quantityBasket' => '', 'price' => '']),
			'l\'unité, la quantité au panier et le prix sont exigés'
		);
		$this->assertEquals(
			['CompositionSpecies::quantityBasket.multiple'],
			$this->buildTyped($eCompositionSpecies, ['unit' => (string)$kgId, 'quantityBasket' => '0.55', 'price' => '6']),
			'la quantité au panier se dit en pas entiers de son unité'
		);
		$this->assertEquals([], $this->buildTyped($eCompositionSpecies, ['unit' => (string)$kgId, 'quantityBasket' => '0.5', 'price' => '6']), 'un demi-kilo à 6 € est admis');

		$season = CompositionSpeciesLib::getSeasonRestored($eCompositionSpecies);

		$this->assertEquals(CompositionSpecies::CALENDAR, $season['status'], 'le climat choisi, la saison est celle du calendrier type');
		$this->assertTrue($season['windowWeeks'] !== [], 'et elle porte des semaines');

		$fw = new \FailWatch();

		$eCompositionSpecies->build(['status', 'windowWeeks'], $season);

		$this->assertEquals([], array_column($fw->get(), 1), 'la saison de la source est admise');

		CompositionSpeciesLib::add($eCompositionSpecies, TRUE);

		$eCompositionPlanWritten = CompositionPlanLib::getByStructure($eStructure);

		$this->assertEquals([$eCompositionPlan['id'], CompositionPlan::COMMUNITY, self::CLIMATE], [$eCompositionPlanWritten['id'], $eCompositionPlanWritten['reference'], $eCompositionPlanWritten['climate']], 'le même plan, sa référence gardée, le climat écrit');

		$eCompositionSpeciesWritten = CompositionSpecies::model()
			->select(['status', 'windowWeeks', 'unit', 'quantityBasket', 'price'])
			->wherePlan($eCompositionPlan)
			->wherePlant($plantId)
			->get();

		$this->assertEquals(
			[CompositionSpecies::CALENDAR, $season['windowWeeks'], $kgId, 0.5, 6.0],
			[$eCompositionSpeciesWritten['status'], $eCompositionSpeciesWritten['windowWeeks'], $eCompositionSpeciesWritten['unit']['id'], $eCompositionSpeciesWritten['quantityBasket'], $eCompositionSpeciesWritten['price']],
			'l\'espèce est écrite avec sa saison, son unité, sa quantité au panier et son prix'
		);

		$eCompositionPlanWritten['structure'] = $eStructure;
		$source = CompositionLib::getSourceByPlan($eCompositionPlanWritten);

		// La source du plan et l'onglet rangent l'espèce sous l'id de son espèce du plan (r76).
		$speciesId = $this->getSpecies($eCompositionPlanWritten, $plantId)['id'];

		$this->assertEquals([$kgId, 0.5], [$source['units'][$speciesId]['unit'], $source['units'][$speciesId]['quantity']], 'elle entre dans la source du plan, dans son unité et sa quantité');
		$this->assertFalse(array_key_exists($plantId, CompositionLib::getSource()['units']), 'et la source partagée n\'en voit rien');

		$cCompositionSpeciesTab = CompositionPlanLib::getForScreen($eStructure, NULL, tab: CompositionPlan::TAB_SPECIES)['cCompositionSpecies'];

		$this->assertTrue($cCompositionSpeciesTab->offsetExists($speciesId), 'l\'onglet la livre');
		$this->assertEquals(0.5, $cCompositionSpeciesTab[$speciesId]['basketQuantity'], 'avec sa quantité au panier');
		$this->assertTrue($this->getWeeksServed($eCompositionPlanWritten, $plantId) !== [], 'et les paniers la servent');

		$eCompositionSpeciesNext = CompositionSpeciesLib::getForAdd($eCompositionPlanWritten, $this->getPlantStructureId($eStructure, $plantIdNext));

		$this->assertFalse(CompositionSpeciesLib::isClimateAsked($eCompositionSpeciesNext), 'le climat ne se redemande pas pour l\'espèce suivante');
		$this->assertTrue(CompositionSpeciesLib::getForAdd($eCompositionPlanWritten, $this->getPlantStructureId($eStructure, $plantId))['eCompositionSpeciesBare']->notEmpty(), 'et l\'espèce ajoutée ne s\'ajoute plus qu\'en variante');

	}

	/**
	 * Le panneau dans ses états : sans espèce, il ne porte que le champ qui la choisit ; une espèce que
	 * la communauté ne compte pas demande le climat, puis sa saison se propose sur un rang de la
	 * grille, à régler aux poignées (r59), et les réglages de « Régler une espèce » s'ouvrent (r60),
	 * aucune unité cochée et aucun choix vide, le produit vendu masqué jusqu'à l'unité ; une espèce de la
	 * communauté qu'on avait retirée ne demande pas de climat, sa saison se propose de même, et ses
	 * réglages suivent la communauté.
	 */
	public function testPanel(): void {

		$eStructure = $this->getStructureWithoutPlan();
		$eCompositionPlan = $this->createStarted($eStructure);

		[$plantId] = $this->getPlantsTyped($eCompositionPlan);

		$plantStructureId = $this->getPlantStructureId($eStructure, $plantId);

		$eCompositionSpeciesEmpty = new CompositionSpecies([
			'plan' => $eCompositionPlan,
			'plant' => new \plant\Plant(),
			'ePlantStructure' => new \plant\Plant(),
			'plantIdsAddable' => CompositionSpeciesLib::getAddable($eCompositionPlan),
		]);

		$panel = $this->getPanel($eCompositionSpeciesEmpty);

		$this->assertEquals([1, 0], [$panel->query('//form')->length, $panel->query('//form[contains(@data-ajax-form, "doAddSpecies")]')->length], 'sans espèce, le panneau ne porte que le formulaire qui la choisit');
		$this->assertEquals(0, $panel->query('//select[@name="climate"]')->length, 'et ne demande pas de climat');

		$body = json_decode($panel->query('//*[@data-autocomplete-body]')->item(0)->getAttribute('data-autocomplete-body'), TRUE);

		$this->assertEquals([$eStructure['id'], $eCompositionSpeciesEmpty['plantIdsAddable']], [$body['structure'], $body['ids']], 'la recherche porte sur les espèces de la structure, bornée à celles qui s\'ajoutent');

		$panel = $this->getPanel(new CompositionSpecies([
			'plan' => $eCompositionPlan,
			'plant' => new \plant\Plant(),
			'ePlantStructure' => new \plant\Plant(),
			'plantIdsAddable' => [],
		]));

		$this->assertEquals(0, $panel->query('//*[@data-autocomplete-body]')->length, 'quand rien ne s\'ajoute, le panneau le dit et n\'ouvre pas de recherche');

		$eCompositionSpecies = CompositionSpeciesLib::getForAdd($eCompositionPlan, $plantStructureId);
		$eCompositionSpecies['plantIdsAddable'] = $eCompositionSpeciesEmpty['plantIdsAddable'];

		CompositionSpeciesLib::loadAdd($eCompositionSpecies, self::CLIMATE);

		$panel = $this->getPanel($eCompositionSpecies);

		$this->assertEquals([TRUE, TRUE], [$eCompositionSpecies['climateAsked'], $eCompositionSpecies['typed']], 'une espèce que la communauté ne compte pas, sur un plan sans climat');
		$this->assertEquals(self::CLIMATE, $panel->query('//form[contains(@data-ajax-form, "addSpecies")]//select[@name="climate"]/option[@selected]')->item(0)->getAttribute('value'), 'le formulaire du choix porte le climat envoyé');
		$this->assertEquals(self::CLIMATE, $panel->query('//form[contains(@data-ajax-form, "doAddSpecies")]//input[@name="climate"]')->item(0)->getAttribute('value'), 'que l\'envoi reprend');
		$this->assertEquals((string)$plantStructureId, $panel->query('//form[contains(@data-ajax-form, "doAddSpecies")]//input[@name="plant"]')->item(0)->getAttribute('value'), 'avec l\'espèce de la structure');
		$this->assertEquals(CompositionSpeciesLib::getSeasonRestored($eCompositionSpecies), $eCompositionSpecies['seasonAdded'], 'le panneau propose la saison de la source');
		$this->assertTrue($eCompositionSpecies['seasonAdded']['windowWeeks'] !== [], 'que le calendrier date');
		$this->assertEquals(
			$this->getSeasonField($eCompositionSpecies['seasonAdded']['windowWeeks']),
			$this->readSeasonField($panel),
			'et dessine sur un rang de la grille, à régler aux poignées, avec une case par semaine de la saison et les champs que l\'envoi relit'
		);
		$this->assertEquals(0, $panel->query('//input[@name="months"] | //*[@data-dropdown-id="toolbox-composition-add-months"]')->length, 'sans mois à cocher');

		$radios = $panel->query('//input[@type="radio"][@name="unit"]');
		$values = [];
		$checked = [];

		foreach($radios as $radio) {
			$values[] = $radio->getAttribute('value');
			if($radio->hasAttribute('checked')) {
				$checked[] = $radio->getAttribute('value');
			}
		}

		$this->assertEquals(array_map(strval(...), array_keys(CompositionLib::getSource()['offered'])), array_values(array_intersect(array_map(strval(...), array_keys(CompositionLib::getSource()['offered'])), $values)), 'chaque unité offerte se coche');
		$this->assertFalse(in_array('', $values, TRUE), 'sans choix vide, l\'espèce n\'ayant pas d\'unité à suivre');
		$this->assertEquals([], $checked, 'et aucune ne part cochée : une unité précochée s\'enregistrerait sans avoir été choisie');
		$this->assertEquals([1, 1], [$panel->query('//input[@name="quantityBasket"]')->length, $panel->query('//input[@name="price"]')->length], 'la quantité au panier et le prix se tapent');

		$product = $panel->query('//div[@data-follow="product"]')->item(0);

		$this->assertEquals(
			[TRUE, '/toolbox/composition:queryAddProduct'],
			[$product->hasAttribute('data-follow-unit'), $panel->query('.//*[@data-autocomplete-url]', $product)->item(0)->getAttribute('data-autocomplete-url')],
			'le produit vendu attend une unité, masqué, et se cherchera dans celle qu\'on coche'
		);

		$step = $panel->query('//div[@data-follow="step"]')->item(0);

		$this->assertEquals('disabled', $panel->query('.//input[@name="step"]', $step)->item(0)->getAttribute('disabled'), 'le pas attend une unité qui se coupe, masqué et sans rien poster');

		$plantIdCommunity = $this->getPlantDelivered($eCompositionPlan);

		$this->exclude($eCompositionPlan, $plantIdCommunity);

		$eCompositionSpeciesExcluded = CompositionSpeciesLib::getForAdd($eCompositionPlan, $this->getPlantStructureId($eStructure, $plantIdCommunity));
		$eCompositionSpeciesExcluded['plantIdsAddable'] = CompositionSpeciesLib::getAddable($eCompositionPlan);

		$eCompositionPlan['climate'] = NULL;

		CompositionSpeciesLib::loadAdd($eCompositionSpeciesExcluded, NULL);

		$panel = $this->getPanel($eCompositionSpeciesExcluded);

		$this->assertEquals([FALSE, FALSE], [$eCompositionSpeciesExcluded['climateAsked'], $eCompositionSpeciesExcluded['typed']], 'une espèce de la communauté qu\'on avait retirée ne demande pas de climat, et ne tape rien');
		$this->assertEquals([0, 0, 1], [
			$panel->query('//select[@name="climate"]')->length,
			$panel->query('//input[@name="quantityBasket"]')->length,
			$panel->query('//input[@name="price"]')->length,
		], 'son panneau ne demande pas le climat ni de quantité au panier, et son prix se règle');
		$this->assertEquals('', $panel->query('//input[@type="radio"][@name="unit"][@checked]')->item(0)->getAttribute('value'), 'dans l\'unité de la communauté, le choix vide');
		$this->assertEquals('', $panel->query('//input[@type="radio"][@name="frequency"][@checked]')->item(0)->getAttribute('value'), 'et selon la saison');
		$this->assertEquals([0, 0], [
			$panel->query('//a[contains(@data-ajax, "doExclude")]')->length,
			$panel->query('//a[contains(@href, "plant:update")]')->length,
		], 'sans « Retirer de mes cultures » ni lien vers la fiche de l\'espèce, qui rechargerait l\'écran');
		$this->assertEquals($this->getSeasonField($eCompositionSpeciesExcluded['seasonAdded']['windowWeeks']), $this->readSeasonField($panel), 'sa saison se propose aux poignées, comme celle d\'une espèce neuve');
		$this->assertEquals(1, $panel->query('//form[contains(@data-ajax-form, "doAddSpecies")]//button[@type="submit"]')->length, 'il ne reste qu\'à l\'ajouter');

	}

	/**
	 * « Régler une espèce », pour une espèce que la communauté ne compte pas : l'unité n'a pas de
	 * choix vide, la quantité au panier se règle, et elle reste exigée.
	 */
	public function testUpdatePanel(): void {

		$eStructure = $this->getStructureWithoutPlan();
		$eCompositionPlan = $this->createStarted($eStructure);

		[$plantId] = $this->getPlantsTyped($eCompositionPlan);

		$this->addTyped($eCompositionPlan, $plantId);

		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $plantId);

		CompositionSpeciesLib::loadPriced($eCompositionSpecies);
		CompositionSpeciesLib::loadUnits($eCompositionSpecies);
		CompositionSpeciesLib::loadProducts($eCompositionSpecies);
		CompositionSpeciesLib::loadSeasonAxis($eCompositionSpecies['plan']);
		CompositionSpeciesLib::loadFrequency($eCompositionSpecies, NULL);

		$panel = new CompositionSpeciesUi()->update($eCompositionSpecies, NULL, CompositionPlan::TAB_SPECIES);
		$xpath = new \DOMXPath($this->getDocument($panel->body));

		$values = [];

		foreach($xpath->query('//input[@type="radio"][@name="unit"]') as $radio) {
			$values[] = $radio->getAttribute('value');
		}

		$this->assertEquals(array_map(strval(...), $eCompositionSpecies['cUnitOffered']->getIds()), $values, 'les unités offertes, sans choix vide');
		$this->assertEquals('0.5', $xpath->query('//div[@data-follow="quantityBasket"]//input[@name="quantityBasket"]')->item(0)->getAttribute('value'), 'la quantité au panier se règle, et suit l\'unité');
		$this->assertTrue(in_array('quantityBasket', CompositionSpeciesLib::getPropertiesPanel($eCompositionSpecies), TRUE), 'et l\'enregistrement la construit');

		$fw = new \FailWatch();

		$eCompositionSpecies->build(['quantityBasket'], ['quantityBasket' => ''], new \PropertyList('update'));

		$this->assertEquals(['CompositionSpecies::quantityBasket.required'], array_column($fw->get(), 1), 'vidée, elle est refusée');

	}

	/**
	 * Les réglages du panneau sont ceux de « Régler une espèce » (r60, le 03/10/2026, « les
	 * champs devraient être raccord ») : sur la même espèce de la communauté, réglée puis retirée et
	 * remise, les deux panneaux portent les mêmes groupes dans le même ordre, avec les mêmes libellés,
	 * les mêmes suffixes et les mêmes champs. Seuls changent ce qui ne vaut pas avant l'ajout,
	 * « Retirer de mes cultures » et le lien vers la fiche de l'espèce, qui sont hors des groupes
	 * comparés ou se lisent à part.
	 */
	public function testSettingsLikeUpdate(): void {

		$eStructure = $this->getStructureWithoutPlan();
		$eCompositionPlan = $this->createStarted($eStructure);

		$plantId = $this->getPlantDelivered($eCompositionPlan);

		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $plantId);

		CompositionSpeciesLib::loadPriced($eCompositionSpecies);
		CompositionSpeciesLib::loadUnits($eCompositionSpecies);
		CompositionSpeciesLib::loadProducts($eCompositionSpecies);
		CompositionSpeciesLib::loadSeasonAxis($eCompositionSpecies['plan']);
		CompositionSpeciesLib::loadFrequency($eCompositionSpecies, NULL);

		$settingsUpdate = $this->readSettings(new \DOMXPath($this->getDocument(new CompositionSpeciesUi()->update($eCompositionSpecies, NULL, NULL)->body)), '');

		$this->exclude($eCompositionPlan, $plantId);

		$eCompositionSpeciesAdded = CompositionSpeciesLib::getForAdd($eCompositionPlan, $this->getPlantStructureId($eStructure, $plantId));
		$eCompositionSpeciesAdded['plantIdsAddable'] = CompositionSpeciesLib::getAddable($eCompositionPlan);

		CompositionSpeciesLib::loadAdd($eCompositionSpeciesAdded, NULL);

		$settingsAdded = $this->readSettings($this->getPanel($eCompositionSpeciesAdded), '//form[contains(@data-ajax-form, ":doAddSpecies")]');

		$this->assertEquals($settingsUpdate, $settingsAdded, 'et les mêmes suffixes et les mêmes champs');

	}

	/**
	 * La même comparaison pour une espèce que la communauté ne compte pas (r60, retour du chef de
	 * projet) : le panneau d'ajout part sans unité, et c'est une fois le kilo coché, comme
	 * `composition.js` le suit, qu'il porte les mêmes groupes visibles, les mêmes libellés, « Prix par
	 * kg » compris, les mêmes suffixes et les mêmes champs que « Régler une espèce » sur l'espèce
	 * ajoutée au kilo. La recherche du produit vendu se fait dans l'unité cochée, que `build()` contrôle.
	 */
	public function testSettingsTypedLikeUpdate(): void {

		$eStructure = $this->getStructureWithoutPlan();
		$eCompositionPlan = $this->createStarted($eStructure);

		[$plantId] = $this->getPlantsTyped($eCompositionPlan);

		$plantStructureId = $this->getPlantStructureId($eStructure, $plantId);
		$kgId = $this->getUnitId('kg');

		$eCompositionSpeciesAdded = CompositionSpeciesLib::getForAdd($eCompositionPlan, $plantStructureId);
		$eCompositionSpeciesAdded['plantIdsAddable'] = CompositionSpeciesLib::getAddable($eCompositionPlan);

		CompositionSpeciesLib::loadAdd($eCompositionSpeciesAdded, self::CLIMATE);

		$root = '//form[contains(@data-ajax-form, ":doAddSpecies")]';
		$panel = $this->getPanel($eCompositionSpeciesAdded);

		$settingsAdded = $this->readSettings($panel, $root);

		$this->runAsManager($eStructure, function() use($eStructure, $plantStructureId, $kgId) {

			$input = ['id' => $eStructure['id'], 'plant' => $plantStructureId, 'query' => ''];

			$action = $this->callRoute('POST', 'toolbox/composition:queryAddProduct', $input + ['unit' => (string)$kgId]);

			$this->assertEquals([\ViewAction::class, 'kg'], [$action::class, $this->getData($action)->e->getUnitFqn()], 'la recherche du produit vendu cherche dans l\'unité cochée');

			$fw = new \FailWatch();

			$action = $this->callRoute('POST', 'toolbox/composition:queryAddProduct', $input + ['unit' => '']);

			$this->assertEquals([['CompositionSpecies::unit.offered'], \FailAction::class], [array_column($fw->get(), 1), $action::class], 'et sans unité, une espèce typée n\'a rien où chercher');

		});

		$this->addTyped($eCompositionPlan, $plantId);

		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $plantId);

		CompositionSpeciesLib::loadPriced($eCompositionSpecies);
		CompositionSpeciesLib::loadUnits($eCompositionSpecies);
		CompositionSpeciesLib::loadProducts($eCompositionSpecies);
		CompositionSpeciesLib::loadSeasonAxis($eCompositionSpecies['plan']);
		CompositionSpeciesLib::loadFrequency($eCompositionSpecies, NULL);

		$settingsUpdate = $this->readSettings(new \DOMXPath($this->getDocument(new CompositionSpeciesUi()->update($eCompositionSpecies, NULL, NULL)->body)), '');

		$this->assertEquals($settingsUpdate, $settingsAdded, 'et les mêmes groupes visibles, les mêmes suffixes et les mêmes champs');

	}

	/**
	 * Une espèce de la communauté au kilo, retirée puis remise par `composition:doAddSpecies` avec ses
	 * réglages : un minimum hors du pas est refusé comme dans « Régler une espèce », puis la fréquence,
	 * le pas, le minimum, la quantité par défaut et le produit vendu arrivent en base. Le produit vendu
	 * se cherche par `composition:queryAddProduct`, qui le trouve parmi les candidats.
	 */
	public function testSettingsAdded(): void {

		$eStructure = $this->getStructureWithoutPlan();
		$eCompositionPlan = $this->createStarted($eStructure);

		$units = CompositionLib::getSource()['units'];
		$plantId = NULL;

		foreach($this->getPlantIdsDelivered($eStructure) as $plantIdDelivered) {

			if($units[$plantIdDelivered]['fqn'] === 'kg' and $this->getPlantStructureId($eStructure, $plantIdDelivered) !== NULL) {
				$plantId = $plantIdDelivered;
				break;
			}

		}

		$this->assertTrue($plantId !== NULL, 'le plan livre une espèce au kilo dont la structure porte la copie');

		$this->exclude($eCompositionPlan, $plantId);

		$plantStructureId = $this->getPlantStructureId($eStructure, $plantId);

		$eCompositionSpecies = CompositionSpeciesLib::getForAdd($eCompositionPlan, $plantStructureId);

		CompositionSpeciesLib::loadAdd($eCompositionSpecies, NULL);

		$this->assertTrue($eCompositionSpecies['ePlantCreate']->notEmpty() and $eCompositionSpecies['eUnitCreate']->notEmpty(), 'la structure porte l\'espèce et le kilo d\'un produit');

		$eProduct = new \product\Product([
			'structure' => $eStructure,
			'status' => \product\Product::ACTIVE,
			'profile' => \product\Product::PLANT,
			'name' => 'Produit du test des paniers',
			'plant' => $eCompositionSpecies['ePlantCreate'],
			'unit' => $eCompositionSpecies['eUnitCreate'],
			'duplicateOf' => new \product\Product(),
			'vat' => 2,
			'organic' => \product\Product::NO,
			'qualities' => new \Set([]),
			'private' => TRUE,
			'privatePrice' => 4.0,
			'pro' => FALSE,
			'proPrice' => NULL,
		]);

		// `build()` aurait vérifié la saisie du formulaire d'un produit : le décor pose un produit en
		// vente de l'espèce et de l'unité que le panneau propose, qu'aucun formulaire ne poste ici.
		\product\ProductLib::create($eProduct);

		$season = CompositionSpeciesLib::getSeasonRestored($eCompositionSpecies);

		$post = [
			'id' => $eStructure['id'],
			'plant' => $plantStructureId,
			'windowWeeks' => $this->getPosted($season['windowWeeks']),
			'unit' => '',
			'price' => '',
			'step' => '0.2',
			'minimum' => '0.4',
			'quantity' => '0.6',
			'frequency' => CompositionSpecies::MEDIUM,
			'product' => (string)$eProduct['id'],
		];

		$this->runAsManager($eStructure, function() use($eStructure, $plantStructureId, $eProduct, $post) {

			$action = $this->callRoute('POST', 'toolbox/composition:queryAddProduct', ['id' => $eStructure['id'], 'plant' => $plantStructureId, 'query' => 'test des paniers']);

			$this->assertEquals(\ViewAction::class, $action::class, 'la recherche du produit vendu répond');
			$this->assertEquals([$eProduct['id']], $this->getData($action)->cProduct->getIds(), 'et trouve le produit de l\'espèce, dans son unité');

			$fw = new \FailWatch();

			$action = $this->callRoute('POST', 'toolbox/composition:doAddSpecies', ['minimum' => '0.3'] + $post);

			$this->assertEquals([['CompositionSpecies::minimum.multiple'], \FailAction::class], [array_column($fw->get(), 1), $action::class], 'un minimum hors du pas est refusé, comme dans « Régler une espèce »');

			$fw = new \FailWatch();

			$action = $this->callRoute('POST', 'toolbox/composition:doAddSpecies', $post);

			$this->assertEquals([[], \ViewAction::class], [array_column($fw->get(), 1), $action::class], 'l\'ajout avec ses réglages est admis');

		});

		$eCompositionSpeciesWritten = $this->getSpecies($eCompositionPlan, $plantId);

		$this->assertEquals(
			[$season['status'], CompositionSpecies::MEDIUM, 0.2, 0.4, 0.6, $eProduct['id'], TRUE],
			[$eCompositionSpeciesWritten['status'], $eCompositionSpeciesWritten['frequency'], $eCompositionSpeciesWritten['step'], $eCompositionSpeciesWritten['minimum'], $eCompositionSpeciesWritten['quantity'], $eCompositionSpeciesWritten['product']['id'], $eCompositionSpeciesWritten['unit']->empty()],
			'l\'espèce arrive en base avec sa saison, sa fréquence, son pas, son minimum, sa quantité par défaut et son produit vendu, dans l\'unité de la communauté'
		);

	}

	/**
	 * « Ajouter une espèce » sur une espèce que le plan livre déjà ajoute une variante (r76 lot 2,
	 * le 04/10/2026, piste B), par la route du panneau :
	 *
	 * - la copie livrée se propose, et le « + » du rang de l'espèce nue (piste C, `species`) ouvre le
	 *   même panneau sur elle ;
	 * - le panneau demande le nom, ne coche aucune unité, et part du prix, du minimum et de la quantité
	 *   de l'espèce, que l'unité de l'espèce rend (`data-copied`) ;
	 * - l'envoi refuse une variante sans nom, puis sans unité cochée, sans rien écrire ;
	 * - la variante s'écrit sur la fiche de son espèce, avec la saison de l'espèce et le produit vendu
	 *   vide, sans toucher à l'espèce nue ni créer d'espèce de la structure ;
	 * - un second envoi sous le même nom est refusé, et une variante retirée revient sous son nom.
	 */
	public function testAddVariant(): void {

		$eStructure = $this->getStructureWithoutPlan();
		$eCompositionPlan = $this->createStarted($eStructure);

		$units = CompositionLib::getSource()['units'];
		$plantId = NULL;

		// Une espèce livrée à la botte : le kilo, qu'on coche pour la variante, en est une autre unité.
		foreach($this->getPlantIdsDelivered($eStructure) as $plantIdDelivered) {

			if($units[$plantIdDelivered]['fqn'] === 'bunch' and $this->getPlantStructureId($eStructure, $plantIdDelivered) !== NULL) {
				$plantId = $plantIdDelivered;
				break;
			}

		}

		$this->assertTrue($plantId !== NULL, 'le plan livre une espèce à la botte dont la structure porte la copie');

		$plantStructureId = $this->getPlantStructureId($eStructure, $plantId);
		$kgId = $this->getUnitId('kg');
		$name = 'Vrac du test des paniers';

		$eCompositionSpeciesBare = $this->getSpecies($eCompositionPlan, $plantId);
		$eCompositionSpeciesBare->merge(['price' => 2.5, 'minimum' => 2.0, 'quantity' => 3.0, 'frequency' => CompositionSpecies::MEDIUM]);

		// `build()` aurait vérifié ces réglages postés par le panneau de l'espèce : le décor les fixe,
		// pour que la variante les reprenne.
		CompositionSpeciesLib::update($eCompositionSpeciesBare, ['price', 'minimum', 'quantity', 'frequency']);

		$this->assertTrue(in_array($plantStructureId, CompositionSpeciesLib::getAddable($eCompositionPlan), TRUE), 'la copie livrée se propose');

		$post = [
			'id' => $eStructure['id'],
			'plant' => $plantStructureId,
			'windowWeeks' => $this->getPosted($eCompositionSpeciesBare['windowWeeks']),
			'price' => '',
			'step' => '',
			'minimum' => '',
			'quantity' => '',
			'frequency' => CompositionSpecies::MEDIUM,
			'product' => '',
		];

		$countPlants = fn() => \plant\Plant::model()->whereStructure($eStructure)->count();
		$countSpecies = fn() => CompositionSpecies::model()->wherePlan($eCompositionPlan)->count();

		[$plantsBefore, $speciesBefore] = [$countPlants(), $countSpecies()];

		$this->runAsManager($eStructure, function() use($eStructure, $eCompositionSpeciesBare, $plantStructureId, $kgId, $name, $post, $countSpecies, $speciesBefore) {

			$data = $this->getData($this->callRoute('GET', 'toolbox/composition:addSpecies', ['id' => $eStructure['id'], 'plant' => $plantStructureId]));
			$panel = new \DOMXPath($this->getDocument(new CompositionSpeciesUi()->add($data->eCompositionSpecies)->body));

			$this->assertEquals($eCompositionSpeciesBare['id'], $data->eCompositionSpecies['eCompositionSpeciesBare']['id'], 'le panneau qui choisit la copie livrée prépare une variante de son espèce nue');
			$this->assertEquals(1, $panel->query('//input[@name="name"]')->length, 'il demande son nom');
			$this->assertEquals(0, $panel->query('//input[@name="unit"][@checked]')->length, 'et ne coche aucune unité');
			$this->assertTrue($panel->query('//*[@data-wrapper="unit"]//input[@name="unit"]')->length > 0, 'le groupe de l\'unité porte son wrapper, sous lequel s\'affiche le refus `unit.chosen`');
			$this->assertEquals([TRUE, FALSE], [
				$panel->query('//input[@name="unit"][@value=""]')->item(0)->hasAttribute('data-copied'),
				$panel->query('//input[@name="unit"][@value="'.$kgId.'"]')->item(0)->hasAttribute('data-copied'),
			], 'l\'unité de l\'espèce, celle de la communauté, rend les valeurs reprises, et pas le kilo');
			$this->assertEquals(
				[2.5, 2.0, 3.0],
				array_map(fn(string $property) => (float)str_replace(',', '.', $panel->query('//input[@name="'.$property.'"]')->item(0)->getAttribute('value')), ['price', 'minimum', 'quantity']),
				'le prix, le minimum et la quantité de l\'espèce sont repris'
			);
			$this->assertEquals(CompositionSpecies::MEDIUM, $panel->query('//input[@name="frequency"][@checked]')->item(0)->getAttribute('value'), 'comme sa fréquence');

			$data = $this->getData($this->callRoute('GET', 'toolbox/composition:addSpecies', ['id' => $eStructure['id'], 'species' => $eCompositionSpeciesBare['id']]));

			$this->assertEquals([$plantStructureId, $eCompositionSpeciesBare['id']], [$data->eCompositionSpecies['ePlantStructure']['id'], $data->eCompositionSpecies['eCompositionSpeciesBare']['id']], 'le « + » du rang ouvre le même panneau, sur la copie de l\'espèce');

			$panelAdd = new CompositionSpeciesUi()->add($data->eCompositionSpecies, $data->variant);
			$panelVariant = new \DOMXPath($this->getDocument($panelAdd->body));

			$this->assertEquals(
				[TRUE, 0, 1],
				[$data->variant, $panelVariant->query('//*[@name="plant"][not(@type="hidden")]')->length, $panelVariant->query('//input[@name="name"]')->length],
				'il s\'intitule « Ajouter une variante », nomme l\'espèce en sous-titre et ne la fait pas choisir, mais demande le nom (planche r76, banc « c-onglet »)'
			);

			$fw = new \FailWatch();

			$action = $this->callRoute('POST', 'toolbox/composition:doAddSpecies', $post + ['unit' => '']);

			$this->assertEquals([['CompositionSpecies::name.required'], \FailAction::class], [array_column($fw->get(), 1), $action::class], 'une variante sans nom est refusée');

			$fw = new \FailWatch();

			$action = $this->callRoute('POST', 'toolbox/composition:doAddSpecies', $post + ['name' => $name]);

			$this->assertEquals([['CompositionSpecies::unit.chosen'], \FailAction::class], [array_column($fw->get(), 1), $action::class], 'puis sans unité cochée');
			$this->assertEquals($speciesBefore, $countSpecies(), 'et rien ne s\'est écrit');

			$this->assertEquals(0, $panel->query('//input[@name="frequency"][@value=""]')->length, 'le panneau ne propose pas « Selon la saison » à une variante');
			$this->assertTrue($panel->query('//*[@data-wrapper="frequency"]//input[@name="frequency"]')->length > 0, 'et le refus s\'affiche sous le champ de la fréquence, qui porte son wrapper');

			$fw = new \FailWatch();

			$action = $this->callRoute('POST', 'toolbox/composition:doAddSpecies', ['name' => $name, 'unit' => (string)$kgId, 'frequency' => ''] + $post);

			$this->assertEquals([['CompositionSpecies::frequency.seasonal'], \FailAction::class], [array_column($fw->get(), 1), $action::class], 'et son envoi la refuse');
			$this->assertEquals($speciesBefore, $countSpecies(), 'sans rien écrire');

			$fw = new \FailWatch();

			$action = $this->callRoute('POST', 'toolbox/composition:doAddSpecies', $post + ['name' => $name, 'unit' => (string)$kgId]);

			$this->assertEquals([[], \ViewAction::class], [array_column($fw->get(), 1), $action::class], 'la variante au kilo, nommée, est admise');

			$fw = new \FailWatch();

			$action = $this->callRoute('POST', 'toolbox/composition:doAddSpecies', $post + ['name' => $name, 'unit' => (string)$kgId]);

			$this->assertEquals([['CompositionSpecies::name.duplicate'], \FailAction::class], [array_column($fw->get(), 1), $action::class], 'une seconde variante du même nom est refusée');

		});

		$eCompositionSpeciesVariant = $this->getSpecies($eCompositionPlan, $plantId, $name);
		$eCompositionSpeciesBareAfter = $this->getSpecies($eCompositionPlan, $plantId);

		$this->assertEquals(
			[$kgId, $eCompositionSpeciesBare['status'], $eCompositionSpeciesBare['windowWeeks'], CompositionSpecies::MEDIUM, NULL, TRUE],
			[$eCompositionSpeciesVariant['unit']['id'], $eCompositionSpeciesVariant['status'], $eCompositionSpeciesVariant['windowWeeks'], $eCompositionSpeciesVariant['frequency'], $eCompositionSpeciesVariant['price'], $eCompositionSpeciesVariant['product']->empty()],
			'la variante s\'écrit au kilo, avec la saison de son espèce, sa fréquence, et le prix de la mercuriale au kilo, sans produit vendu'
		);
		$this->assertEquals($eCompositionSpeciesBare['plant']['name'].' · '.$name, $eCompositionSpeciesVariant->getLabel(), 'elle se dit sous son espèce');
		$this->assertEquals([2.5, TRUE], [$eCompositionSpeciesBareAfter['price'], $eCompositionSpeciesBareAfter['unit']->empty()], 'l\'espèce nue garde son prix et son unité');
		$this->assertEquals([$plantsBefore, $speciesBefore + 1], [$countPlants(), $countSpecies()], 'une espèce du plan de plus, et aucune espèce de la structure');

		// Le « + » du rang ajoute une variante à une espèce nue, jamais une variante à une variante.
		$this->runAsManager($eStructure, function() use($eStructure, $eCompositionSpeciesBare, $eCompositionSpeciesVariant) {

			$html = new CompositionUi()->getSpecies(CompositionPlanLib::getForScreen($eStructure, NULL, tab: CompositionPlan::TAB_SPECIES));

			$this->assertEquals(
				[1, 0],
				[substr_count($html, 'species='.$eCompositionSpeciesBare['id'].'"'), substr_count($html, 'species='.$eCompositionSpeciesVariant['id'].'"')],
				'l\'onglet porte le « + » sur le rang de l\'espèce, pas sur celui de sa variante'
			);

			// Un lien « + » qui ne désigne plus une espèce nue en activité (une variante, une espèce
			// d'une autre structure, un id forgé) rouvre l'ajout ordinaire.
			foreach([$eCompositionSpeciesVariant['id'], 0] as $speciesId) {

				$action = $this->callRoute('GET', 'toolbox/composition:addSpecies', ['id' => $eStructure['id'], 'species' => $speciesId]);

				$this->assertEquals([\ViewAction::class, FALSE, TRUE], [$action::class, $this->getData($action)->variant, $this->getData($action)->eCompositionSpecies['plant']->empty()], 'le « + » de l\'espèce #'.$speciesId.' rouvre « Ajouter une espèce », sans espèce choisie');

			}

		});

		$this->exclude($eCompositionPlan, $plantId, $name);

		$this->runAsManager($eStructure, function() use($plantStructureId, $kgId, $name, $post) {

			$fw = new \FailWatch();

			$action = $this->callRoute('POST', 'toolbox/composition:doAddSpecies', $post + ['name' => $name, 'unit' => (string)$kgId]);

			$this->assertEquals([[], \ViewAction::class], [array_column($fw->get(), 1), $action::class], 'la variante retirée s\'ajoute de nouveau sous son nom');

		});

		$eCompositionSpeciesBack = $this->getSpecies($eCompositionPlan, $plantId, $name);

		$this->assertEquals([$eCompositionSpeciesVariant['id'], TRUE], [$eCompositionSpeciesBack['id'], $eCompositionSpeciesBack['status'] !== CompositionSpecies::EXCLUDED], 'elle revient sous son id, livrée');
		$this->assertEquals($speciesBefore + 1, $countSpecies(), 'sans espèce du plan de plus');

	}

	/**
	 * Le nom d'une variante (r76 lot 2) se corrige dans son panneau (`compositionSpecies:doUpdate`) :
	 * un nom vide et le nom d'une autre variante de la même espèce sont refusés, un autre s'écrit.
	 */
	public function testRenameVariant(): void {

		$eStructure = $this->getStructureWithoutPlan();
		$eCompositionPlan = $this->createStarted($eStructure);

		$plantId = $this->getPlantDelivered($eCompositionPlan);

		$eCompositionSpeciesVariant = $this->createVariant($eCompositionPlan, $plantId, 'Vrac du test des paniers');
		$this->createVariant($eCompositionPlan, $plantId, 'Botte du test des paniers');

		$post = fn(string $name) => [
			'id' => $eCompositionSpeciesVariant['id'],
			'structure' => $eStructure['id'],
			'origin' => CompositionPlan::TAB_SPECIES,
			'name' => $name,
			'windowWeeks' => $this->getPosted($eCompositionSpeciesVariant['windowWeeks']),
			'unit' => '',
			'price' => '',
			'step' => '',
			'minimum' => '',
			'quantity' => '',
			'frequency' => CompositionSpecies::BASE,
			'product' => '',
		];

		$eCompositionFormula = CompositionPlanLib::getForScreen($eStructure, NULL)['eCompositionFormula'];

		$this->assertEquals(
			[FALSE, TRUE],
			[str_contains(CompositionSpeciesUi::getFrequencyList($this->getSpecies($eCompositionPlan, $plantId, 'Vrac du test des paniers')), 'Selon la saison'), str_contains(CompositionSpeciesUi::getFrequencyList($this->getSpecies($eCompositionPlan, $plantId)), 'Selon la saison')],
			'le menu de la fréquence ne propose « Selon la saison » qu\'à l\'espèce nue'
		);

		$this->runAsManager($eStructure, function() use($post, $eStructure, $eCompositionSpeciesVariant, $eCompositionFormula) {

			$fw = new \FailWatch();

			$action = $this->callRoute('POST', 'toolbox/compositionSpecies:doUpdate', $post(''));

			$this->assertEquals([['CompositionSpecies::name.required'], \FailAction::class], [array_column($fw->get(), 1), $action::class], 'un nom vide est refusé');

			$fw = new \FailWatch();

			$action = $this->callRoute('POST', 'toolbox/compositionSpecies:doUpdate', $post('Botte du test des paniers'));

			$this->assertEquals([['CompositionSpecies::name.duplicate'], \FailAction::class], [array_column($fw->get(), 1), $action::class], 'le nom d\'une autre variante de l\'espèce aussi');

			$fw = new \FailWatch();

			$action = $this->callRoute('POST', 'toolbox/compositionSpecies:doUpdate', ['frequency' => ''] + $post('Kilo du test des paniers'));

			$this->assertEquals([['CompositionSpecies::frequency.seasonal'], \FailAction::class], [array_column($fw->get(), 1), $action::class], 'une variante ne passe pas « selon la saison » dans son panneau');

			$fw = new \FailWatch();

			$action = $this->callRoute('POST', '@module/toolbox/CompositionSpecies/doQuick', ['id' => $eCompositionSpeciesVariant['id'], 'structure' => $eStructure['id'], 'formula' => $eCompositionFormula['id'], 'property' => 'frequency', 'frequency' => '']);

			$this->assertEquals([['CompositionSpecies::frequency.seasonal'], \FailAction::class], [array_column($fw->get(), 1), $action::class], 'ni par le menu de la grille');

			$fw = new \FailWatch();

			$action = $this->callRoute('POST', 'toolbox/compositionSpecies:doUpdate', $post('Kilo du test des paniers'));

			$this->assertEquals([[], \ViewAction::class], [array_column($fw->get(), 1), $action::class], 'un autre nom est admis');

		});

		$this->assertEquals('Kilo du test des paniers', $this->getSpecies($eCompositionPlan, $plantId, 'Kilo du test des paniers')['name'], 'et s\'écrit');
		$this->assertEquals(0, CompositionSpecies::model()->wherePlan($eCompositionPlan)->whereName('Vrac du test des paniers')->count(), 'à la place de l\'ancien');

	}

	/**
	 * Une variante (r76, modèle a de l'étude) : une espèce du plan de plus sur la fiche de l'espèce
	 * qu'elle décline, sous son nom (« Carotte vrac » à côté de la carotte), écrite en fréquence
	 * « base ». Elle lit tout ce qui se range par espèce du référentiel sous la même fiche que son
	 * espèce nue : l'unité, la saison de chaque origine et les prix de la mercuriale. La grille la range
	 * sous le même organe, l'onglet « Espèces » juste sous son espèce nue, et aucun panier ne sert les
	 * deux (arbitrage) : ni le tirage, ni le menu « Ajouter » d'une case, ni l'ajout à la
	 * main (`CompositionLine::species.twin`).
	 */
	public function testVariant(): void {

		$eStructure = $this->getStructureWithoutPlan();
		$eCompositionPlan = $this->createStarted($eStructure);

		// La deuxième espèce de l'onglet, que la première sépare d'un nom rangé avant tous les autres.
		$plantId = $this->getPlantDelivered($eCompositionPlan, [$this->getPlantDelivered($eCompositionPlan)]);

		// Un nom rangé avant celui de presque toutes les espèces : la variante doit tout de même tomber
		// sous son espèce nue dans l'onglet « Espèces », qui range d'abord par fiche.
		$name = 'Aa variante du test des paniers';

		// Une saison propre à la variante, plus courte que celle de son espèce nue : la grille, qui range
		// d'abord par longueur de saison, ne doit pas l'en éloigner.
		$eCompositionSpeciesVariant = $this->createVariant($eCompositionPlan, $plantId, $name, ['status' => CompositionSpecies::CUSTOM, 'windowWeeks' => $this->getWindowWeeks(6, 6)]);
		$eCompositionSpeciesBare = $this->getSpecies($eCompositionPlan, $plantId);

		$speciesId = $eCompositionSpeciesBare['id'];
		$variantId = $eCompositionSpeciesVariant['id'];

		$this->assertEquals(
			[TRUE, $plantId, $name, CompositionSpecies::BASE],
			[$eCompositionSpeciesVariant->notEmpty(), $eCompositionSpeciesVariant['plant']['id'], $eCompositionSpeciesVariant['name'], $eCompositionSpeciesVariant['frequency']],
			'le plan écrit la variante sur la fiche de son espèce, sous son nom, en fréquence « base »'
		);
		$this->assertTrue($eCompositionSpeciesBare['status'] !== CompositionSpecies::EXCLUDED, 'à côté de son espèce nue, toujours livrée');
		$this->assertEquals([$eCompositionSpeciesBare['plant']['name'].' · '.$name, $eCompositionSpeciesBare['plant']['name']], [$eCompositionSpeciesVariant->getLabel(), $eCompositionSpeciesBare->getLabel()], 'la variante se dit sous son espèce et par son nom (r76 lot 2), l\'espèce nue par le nom de sa fiche');

		$source = CompositionLib::getSourceByPlan($eCompositionPlan);

		$this->assertEquals([$plantId, $plantId], [$source['plants'][$speciesId], $source['plants'][$variantId]], 'la source du plan sert les deux sous leur propre id, et les lit sous la même fiche');
		$this->assertEquals($speciesId, CompositionLib::getSpeciesBare($plantId, $source), 'les cultures et le stock de la fiche se rangent sous l\'espèce nue');
		$this->assertEquals($source['units'][$speciesId]['unit'], $source['units'][$variantId]['unit'], 'la variante a l\'unité de son espèce');

		$plants = [$speciesId => $plantId, $variantId => $plantId];
		$seasons = CompositionSpeciesLib::getSeasonsDefault($eCompositionPlan, $plants, $source);

		$this->assertTrue(array_key_exists($speciesId, $seasons) and array_key_exists($variantId, $seasons), 'la communauté date les deux, sans quoi le contrôle ne verrait rien');
		$this->assertEquals($seasons[$speciesId], $seasons[$variantId], 'de la même saison');

		$eCompositionPlanCultivation = clone $eCompositionPlan;
		$eCompositionPlanCultivation['reference'] = CompositionPlan::CULTIVATION;

		$seasons = CompositionSpeciesLib::getSeasonsDefault($eCompositionPlanCultivation, $plants, $source);

		$this->assertEquals(array_key_exists($speciesId, $seasons), array_key_exists($variantId, $seasons), 'les cultures de la fiche datent les deux, ou aucune');

		if(array_key_exists($speciesId, $seasons)) {
			$this->assertEquals($seasons[$speciesId], $seasons[$variantId], 'et de la même saison');
		}

		$weeksPriced = array_keys(array_filter($source['prices'], fn(array $prices) => array_key_exists($speciesId, $prices)));
		$weeksPricedVariant = array_keys(array_filter($source['prices'], fn(array $prices) => array_key_exists($variantId, $prices)));

		$this->assertTrue($weeksPriced !== [], 'la mercuriale relève l\'espèce nue, sans quoi le contrôle ne verrait rien');
		$this->assertEquals($weeksPriced, $weeksPricedVariant, 'et la variante dans les mêmes semaines');
		$this->assertEquals($source['prices'][$weeksPriced[0]][$speciesId], $source['prices'][$weeksPriced[0]][$variantId], 'aux mêmes prix');

		$this->assertTrue($this->getWeeksServed($eCompositionPlan, $plantId) !== [], 'l\'espèce nue est servie, sans quoi le contrôle ne verrait rien');

		$eCompositionPlanScreen = CompositionPlanLib::getForScreen($eStructure, NULL);
		$eCompositionFormula = $eCompositionPlanScreen['eCompositionFormula'];
		$steps = CompositionSpeciesLib::getStepsByPlan($eCompositionPlanScreen);

		$organsGrid = [];
		$speciesIdsGrid = [];

		foreach($eCompositionFormula['grid'] as $group) {
			foreach($group['species'] as $row) {
				$organsGrid[$row['species']['id']] = $group['organ'];
				$speciesIdsGrid[] = $row['species']['id'];
			}
		}

		$this->assertTrue(array_key_exists($speciesId, $organsGrid) and array_key_exists($variantId, $organsGrid), 'les deux ont un rang dans la grille');
		$this->assertEquals($organsGrid[$speciesId], $organsGrid[$variantId], 'sous le même organe, celui de leur fiche');
		$this->assertTrue(count($eCompositionSpeciesVariant->getSeasonWeeks()) < count($eCompositionSpeciesBare->getSeasonWeeks()), 'la saison de la variante est plus courte que celle de son espèce nue, sans quoi le contrôle suivant ne verrait rien');
		$this->assertEquals($variantId, $speciesIdsGrid[array_search($speciesId, $speciesIdsGrid, TRUE) + 1], 'la grille range la variante juste sous son espèce nue, malgré sa saison plus courte');

		$labelsTab = [];

		foreach(CompositionPlanLib::getForScreen($eStructure, NULL, tab: CompositionPlan::TAB_SPECIES)['cCompositionSpecies'] as $speciesIdTab => $eCompositionSpeciesTab) {
			$labelsTab[$speciesIdTab] = $eCompositionSpeciesTab->getLabel();
		}

		$speciesIdsTab = array_keys($labelsTab);
		$labelBare = $labelsTab[$speciesId];

		$labelsBetween = array_filter($labelsTab, fn(string $label) => \L::getCollator()->compare($label, $name) > 0 and \L::getCollator()->compare($label, $labelBare) < 0);

		$this->assertTrue(\L::getCollator()->compare($name, $labelBare) < 0 and $labelsBetween !== [], 'la variante se nomme avant son espèce nue, et une espèce du plan tombe entre les deux dans l\'ordre des noms, sans quoi le contrôle ne verrait rien');
		$this->assertEquals($variantId, $speciesIdsTab[array_search($speciesId, $speciesIdsTab, TRUE) + 1], 'l\'onglet « Espèces » range la variante juste sous son espèce nue');

		$weeksBare = [];

		foreach($eCompositionFormula['cCompositionWeek'] as $eCompositionWeek) {

			$speciesIdsServed = $eCompositionWeek['cCompositionLine']->getColumnCollection('species')->getIds();

			$this->assertFalse(in_array($speciesId, $speciesIdsServed, TRUE) and in_array($variantId, $speciesIdsServed, TRUE), 'semaine '.$eCompositionWeek['week'].' : jamais les deux dans le panier');

			if(in_array($speciesId, $speciesIdsServed, TRUE)) {
				$weeksBare[] = $eCompositionWeek;
			}

		}

		$eCompositionWeek = $weeksBare[0];

		$grid = CompositionLib::applyAddableToGrid($eCompositionFormula['grid'], $eCompositionWeek['week'], $source, $steps);
		$speciesIdsAddable = [];

		foreach(array_merge(...array_column($grid, 'species')) as $row) {

			if($row['eCompositionLineAddable']->notEmpty()) {
				$speciesIdsAddable[] = $row['species']['id'];
			}

		}

		$this->assertTrue($speciesIdsAddable !== [], 'semaine '.$eCompositionWeek['week'].' : le menu propose des espèces, sans quoi le contrôle ne verrait rien');
		$this->assertFalse(in_array($variantId, $speciesIdsAddable, TRUE), 'semaine '.$eCompositionWeek['week'].' : le menu ne propose pas la variante d\'une espèce servie');

		$fw = new \FailWatch();

		new CompositionLine(['week' => $eCompositionWeek, 'origin' => CompositionLine::USER])->build(['species'], ['species' => $variantId], new \PropertyList('create'));

		$this->assertEquals(['CompositionLine::species.twin'], array_column($fw->get(), 1), 'et l\'ajout à la main est refusé');

	}

	/**
	 * La quantité au panier d'une espèce que la communauté ne compte pas se compte en pas de l'espèce
	 * (r68, cas de La Ferme du Coucou, tomate ancienne) : au kilo, sous un pas de vente réglé à
	 * 0,25 kg dans le même envoi, 0,75 kg est admise, là où le seul pas de l'unité, 0,1 kg, la
	 * refusait, et 0,8 kg, hors de ce pas, est refusée. La proposition l'arrondit sur le même pas
	 * (`CompositionLib::getStep()`) : 0,75 kg reste 0,75 kg.
	 */
	public function testQuantityBasketStep(): void {

		$eStructure = $this->getStructureWithoutPlan();
		$eCompositionPlan = $this->createStarted($eStructure);

		[$plantId] = $this->getPlantsTyped($eCompositionPlan);

		$post = [
			'id' => $eStructure['id'],
			'plant' => $this->getPlantStructureId($eStructure, $plantId),
			'climate' => self::CLIMATE,
			'windowWeeks' => $this->getPosted($this->getWindowWeeks(6, 9)),
			'unit' => (string)$this->getUnitId('kg'),
			'quantityBasket' => '0.75',
			'price' => '6',
			'step' => '0.25',
			'minimum' => '',
			'quantity' => '',
			'frequency' => CompositionSpecies::MEDIUM,
			'product' => '',
		];

		$this->runAsManager($eStructure, function() use($post) {

			$fw = new \FailWatch();

			$action = $this->callRoute('POST', 'toolbox/composition:doAddSpecies', ['quantityBasket' => '0.8'] + $post);

			$this->assertEquals([['CompositionSpecies::quantityBasket.multiple'], \FailAction::class], [array_column($fw->get(), 1), $action::class], '0,8 kg sous un pas de 0,25 kg est refusé');

			$fw = new \FailWatch();

			$action = $this->callRoute('POST', 'toolbox/composition:doAddSpecies', $post);

			$this->assertEquals([[], \ViewAction::class], [array_column($fw->get(), 1), $action::class], '0,75 kg sous un pas de 0,25 kg est admis');

		});

		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $plantId);

		$this->assertEquals([0.25, 0.75], [$eCompositionSpecies['step'], $eCompositionSpecies['quantityBasket']], 'l\'espèce est écrite avec son pas et sa quantité au panier');

		$eCompositionPlanWritten = CompositionPlanLib::getByStructure($eStructure);
		$eCompositionPlanWritten['structure'] = $eStructure;

		$step = CompositionLib::getStep($eCompositionSpecies['id'], CompositionLib::getSourceByPlan($eCompositionPlanWritten), CompositionSpeciesLib::getStepsByPlan($eCompositionPlanWritten));

		$this->assertEquals([0.25, 0.75], [$step['step'], CompositionLib::roundToStep(0.75, $step)], 'la proposition l\'arrondit sur le même pas, et la sert telle quelle');

	}

	/**
	 * La même espèce envoyée deux fois par « Ajouter une espèce » (point 4 de la relecture r76, tranché
	 * sous mandat par le coordinateur le 04/10/2026) : l'unicité `(plan, plant, name)` ne retient pas
	 * deux espèces nues, dont le nom est NULL. Le second envoi qui suit le premier ne trouve plus
	 * l'espèce à ajouter (`plant.addable`) ; deux envois qui l'ont trouvée tous deux avant que l'un
	 * n'écrive se départagent à la construction (`plant.duplicate`). Le plan n'écrit l'espèce qu'une
	 * fois.
	 */
	public function testAddTwice(): void {

		$eStructure = $this->getStructureWithoutPlan();
		$eCompositionPlan = $this->createStarted($eStructure);

		[$plantId] = $this->getPlantsTyped($eCompositionPlan);

		$plantStructureId = $this->getPlantStructureId($eStructure, $plantId);

		$post = [
			'id' => $eStructure['id'],
			'plant' => $plantStructureId,
			'climate' => self::CLIMATE,
			'windowWeeks' => $this->getPosted($this->getWindowWeeks(6, 9)),
			'unit' => (string)$this->getUnitId('kg'),
			'quantityBasket' => '0.5',
			'price' => '6',
			'step' => '',
			'minimum' => '',
			'quantity' => '',
			'frequency' => CompositionSpecies::MEDIUM,
			'product' => '',
		];

		// Les deux envois trouvent l'espèce avant que l'un d'eux ne l'écrive.
		$eCompositionSpeciesSecond = CompositionSpeciesLib::getForAdd($eCompositionPlan, $plantStructureId);

		$this->assertTrue($eCompositionSpeciesSecond->notEmpty() and $eCompositionSpeciesSecond->exists() === FALSE, 'l\'espèce est neuve et s\'ajoute, sans quoi le contrôle ne verrait rien');

		$this->runAsManager($eStructure, function() use($post) {

			$fw = new \FailWatch();

			$action = $this->callRoute('POST', 'toolbox/composition:doAddSpecies', $post);

			$this->assertEquals([[], \ViewAction::class], [array_column($fw->get(), 1), $action::class], 'le premier envoi est admis');

			$fw = new \FailWatch();

			$action = $this->callRoute('POST', 'toolbox/composition:doAddSpecies', $post);

			// L'espèce est livrée : le second envoi ajouterait une variante (r76 lot 2), qui demande un nom.
			$this->assertEquals([['CompositionSpecies::name.required'], \FailAction::class], [array_column($fw->get(), 1), $action::class], 'le second, qui le suit, ne trouve plus l\'espèce à ajouter, seulement une variante sans nom');

		});

		$fw = new \FailWatch();

		$eCompositionSpeciesSecond->build(['plant'], ['plant' => $eCompositionSpeciesSecond['plant']]);

		$this->assertEquals(['CompositionSpecies::plant.duplicate'], array_column($fw->get(), 1), 'le second, qui l\'avait trouvée avant l\'écriture du premier, est refusé à la construction');
		$this->assertTrue(AlertUi::getError('CompositionSpecies::plant.duplicate') !== NULL, 'avec son message');
		$this->assertEquals(1, CompositionSpecies::model()->wherePlan($eCompositionPlan)->wherePlant($plantId)->count(), 'le plan n\'écrit l\'espèce qu\'une fois');

	}

	/**
	 * Une espèce propre à la structure, qu'aucune source ne date, sur un plan sans climat : le climat
	 * ne se demande pas, sa saison part vide sur le rang du panneau, à tracer aux poignées, et une
	 * semaine au moins est exigée. Ajoutée, elle est écrite sous son propre id, réglée à la main sur
	 * les semaines tracées, et entre dans la source du plan, sa conservation, l'onglet et les paniers. Sa
	 * fiche ne se supprime pas tant que les paniers la livrent, et part avec son espèce de plan une
	 * fois retirée.
	 */
	public function testOwn(): void {

		$eStructure = $this->getStructureWithoutPlan();
		$eCompositionPlan = $this->createStarted($eStructure);

		$ePlantOwn = $this->createPlantOwn($eStructure);
		$plantId = $ePlantOwn['id'];

		$eCompositionSpecies = CompositionSpeciesLib::getForAdd($eCompositionPlan, $plantId);
		$eCompositionSpecies['plantIdsAddable'] = CompositionSpeciesLib::getAddable($eCompositionPlan);

		$this->assertEquals([$plantId, $plantId], [$eCompositionSpecies['plant']['id'], $eCompositionSpecies['ePlantStructure']['id']], 'le plan écrit l\'espèce propre sous son id');
		$this->assertEquals([NULL, FALSE], [$eCompositionPlan['climate'], CompositionSpeciesLib::isClimateAsked($eCompositionSpecies)], 'le plan n\'a pas de climat, et il ne se demande pas pour une espèce que le calendrier ne date pas');

		CompositionSpeciesLib::loadAdd($eCompositionSpecies, NULL);

		$this->assertEquals(
			[FALSE, ['status' => CompositionSpecies::CUSTOM, 'windowWeeks' => []], TRUE],
			[$eCompositionSpecies['climateAsked'], $eCompositionSpecies['seasonAdded'], $eCompositionSpecies['typed']],
			'sa saison part vide, et son unité, sa quantité et son prix se tapent'
		);

		$panel = $this->getPanel($eCompositionSpecies);

		$this->assertEquals(0, $panel->query('//select[@name="climate"]')->length, 'le panneau ne demande pas le climat');
		$this->assertEquals($this->getSeasonField([]), $this->readSeasonField($panel), 'il porte le rang de la saison, vide, à tracer aux poignées, et aucun champ de semaine');

		// Sans saison tracée, l'envoi construit une saison vide, que le schéma admet, et l'ajout la refuse.
		$eCompositionSpeciesEmpty = clone $eCompositionSpecies;

		$this->assertEquals(
			['status' => CompositionSpecies::CUSTOM, 'windowWeeks' => []],
			CompositionSpeciesLib::getSeasonAdded($eCompositionSpeciesEmpty, []),
			'sans saison tracée, la saison revient vide'
		);

		$fw = new \FailWatch();

		$eCompositionSpeciesEmpty->build(['unit', 'quantityBasket', 'price'], ['unit' => (string)$this->getUnitId('kg'), 'quantityBasket' => '0.5', 'price' => '6']);
		$eCompositionSpeciesEmpty->build(['status', 'windowWeeks'], CompositionSpeciesLib::getSeasonAdded($eCompositionSpeciesEmpty, []));

		$this->assertEquals([], array_column($fw->get(), 1), 'que le schéma admet');

		CompositionSpeciesLib::add($eCompositionSpeciesEmpty, FALSE);

		$this->assertEquals(['CompositionSpecies::windowWeeks.months'], array_column($fw->get(), 1), 'et que l\'ajout refuse, une semaine au moins étant exigée');
		$this->assertTrue(AlertUi::getError('CompositionSpecies::windowWeeks.months') !== NULL, 'avec son message');
		$this->assertFalse(CompositionSpecies::model()->wherePlan($eCompositionPlan)->wherePlant($plantId)->exists(), 'rien n\'est écrit');

		$windowWeeks = $this->getWindowWeeks(6, 7);
		$season = CompositionSpeciesLib::getSeasonAdded($eCompositionSpecies, $this->getPosted($windowWeeks));
		$weeks = CompositionLib::getWindowWeeksList($windowWeeks);

		$this->assertEquals(CompositionSpecies::CUSTOM, $season['status'], 'juin et juillet tracés aux poignées sont réglés à la main');

		$kgId = $this->getUnitId('kg');

		$this->assertEquals(
			['CompositionSpecies::unit.offered', 'CompositionSpecies::quantityBasket.required', 'CompositionSpecies::price.required'],
			$this->buildTyped($eCompositionSpecies, ['unit' => '', 'quantityBasket' => '', 'price' => '']),
			'l\'unité, la quantité au panier et le prix sont exigés'
		);
		$this->assertEquals([], $this->buildTyped($eCompositionSpecies, ['unit' => (string)$kgId, 'quantityBasket' => '0.5', 'price' => '6']), 'un demi-kilo à 6 € est admis');

		$fw = new \FailWatch();

		$eCompositionSpecies->build(['status', 'windowWeeks'], $season);

		$this->assertEquals([], array_column($fw->get(), 1), 'la saison tracée est admise');
		$this->assertEquals($weeks, $eCompositionSpecies->getSeasonWeeks(), 'sur les semaines tracées');

		CompositionSpeciesLib::add($eCompositionSpecies, FALSE);

		$eCompositionPlanWritten = CompositionPlanLib::getByStructure($eStructure);
		$eCompositionPlanWritten['structure'] = $eStructure;

		$this->assertEquals(NULL, $eCompositionPlanWritten['climate'], 'le plan reste sans climat');

		$source = CompositionLib::getSourceByPlan($eCompositionPlanWritten);
		$speciesId = $this->getSpecies($eCompositionPlanWritten, $plantId)['id'];

		$this->assertEquals([$kgId, 0.5], [$source['units'][$speciesId]['unit'], $source['units'][$speciesId]['quantity']], 'elle entre dans la source du plan');
		$this->assertEquals([0, TRUE, $plantId], [$source['storage'][$speciesId]['weeks'], $source['storage'][$speciesId]['typed'], $source['storage'][$speciesId]['plant']['id']], 'avec la conservation de sa fiche, aucune tant qu\'elle n\'en dit pas');

		$cCompositionSpeciesTab = CompositionPlanLib::getForScreen($eStructure, NULL, tab: CompositionPlan::TAB_SPECIES)['cCompositionSpecies'];

		$this->assertTrue($cCompositionSpeciesTab->offsetExists($speciesId), 'l\'onglet la livre');

		$this->assertTrue(in_array($plantId, CompositionSpeciesLib::getAddable($eCompositionPlanWritten), TRUE), 'et elle ne se propose plus que pour une variante (r76 lot 2)');

		// La communauté ne lui mesure aucune part : sans fréquence, elle n'entrerait dans un panier que
		// par le bruit du tirage, qui dépend de son id. Elle naît donc en « base », et les paniers la
		// servent sans autre réglage. Deux autres espèces propres le redisent sur d'autres ids.
		$eCompositionSpeciesWritten = $this->getSpecies($eCompositionPlanWritten, $plantId);

		$this->assertEquals(CompositionSpecies::BASE, $eCompositionSpeciesWritten['frequency'], 'elle naît en fréquence « base »');

		$weeksServed = $this->getWeeksServed($eCompositionPlanWritten, $plantId);

		$this->assertTrue($weeksServed !== [], 'les paniers la servent sans autre réglage');
		$this->assertEquals([], array_values(array_diff($weeksServed, $weeks)), 'dans les seules semaines tracées');

		foreach(['Mesclun du test des paniers', 'Tétragone du test des paniers'] as $name) {

			$ePlantOther = $this->createPlantOwn($eStructure, $name);

			$this->addOwn($eCompositionPlanWritten, $ePlantOther['id'], $windowWeeks);

			$weeksServedOther = $this->getWeeksServed($eCompositionPlanWritten, $ePlantOther['id']);

			$this->assertTrue($weeksServedOther !== [], $name.', les paniers la servent');
			$this->assertEquals([], array_values(array_diff($weeksServedOther, $weeks)), $name.', dans les seules semaines tracées');

		}

		CompositionSpeciesLib::loadProducts($eCompositionSpeciesWritten);

		$this->assertEquals($plantId, $eCompositionSpeciesWritten['ePlantCreate']['id'], 'la création de produit reçoit l\'espèce propre');

		$fw = new \FailWatch();

		\plant\PlantLib::delete($ePlantOwn);

		$this->assertEquals(['Plant::deleteComposition'], array_column($fw->get(), 1), 'sa fiche ne se supprime pas tant que les paniers la livrent');
		$this->assertTrue(\plant\Plant::model()->whereId($plantId)->exists(), 'elle est toujours là');

		$this->exclude($eCompositionPlanWritten, $plantId);

		$fw = new \FailWatch();

		\plant\PlantLib::delete($ePlantOwn);

		$this->assertEquals([], array_column($fw->get(), 1), 'retirée des paniers, elle se supprime');
		$this->assertEquals(
			[FALSE, FALSE],
			[\plant\Plant::model()->whereId($plantId)->exists(), CompositionSpecies::model()->wherePlan($eCompositionPlanWritten)->wherePlant($plantId)->exists()],
			'et son espèce de plan part avec elle'
		);

		CompositionPlanLib::getForScreen($eStructure, NULL, tab: CompositionPlan::TAB_SPECIES);

	}

	/**
	 * La saison réglée aux poignées du panneau (r59), postée par `composition:doAddSpecies` comme
	 * `ToolboxCompositionSeason.write()` la range, en chaînes. Deux espèces de la communauté retirées
	 * puis remises : celle que le panneau renvoie telle qu'il l'a proposée garde l'état et la saison de
	 * sa source, celle dont la fin a été tirée d'une semaine est réglée à la main sur les semaines
	 * postées, comme un réglage de la grille. Une espèce propre, qu'aucune source ne date, tracée aux
	 * poignées, arrive en base sur sa saison, et les paniers la servent dans ces semaines.
	 */
	public function testSeasonDragged(): void {

		$eStructure = $this->getStructureWithoutPlan();
		$eCompositionPlan = $this->createStarted($eStructure);

		$plantIdKept = $this->getPlantDelivered($eCompositionPlan);
		$plantIdDragged = $this->getPlantDelivered($eCompositionPlan, [$plantIdKept]);

		$this->exclude($eCompositionPlan, $plantIdKept);
		$this->exclude($eCompositionPlan, $plantIdDragged);

		$plantStructureIdKept = $this->getPlantStructureId($eStructure, $plantIdKept);
		$plantStructureIdDragged = $this->getPlantStructureId($eStructure, $plantIdDragged);

		$seasonKept = CompositionSpeciesLib::getSeasonRestored(CompositionSpeciesLib::getForAdd($eCompositionPlan, $plantStructureIdKept));
		$seasonDragged = CompositionSpeciesLib::getSeasonRestored(CompositionSpeciesLib::getForAdd($eCompositionPlan, $plantStructureIdDragged));

		$this->assertTrue($seasonKept['status'] !== CompositionSpecies::CUSTOM, 'la saison proposée est celle d\'une source, sans quoi rester à la source ne se distinguerait pas d\'un réglage');

		$windowWeeksDragged = $seasonDragged['windowWeeks'];
		$last = array_key_last($windowWeeksDragged);

		$this->assertTrue($last !== NULL and $windowWeeksDragged[$last][1] > $windowWeeksDragged[$last][0], 'la saison proposée finit sur un intervalle de deux semaines au moins, qu\'une poignée peut raccourcir');

		$windowWeeksDragged[$last][1]--;

		$ePlantOwn = $this->createPlantOwn($eStructure);
		$windowWeeksOwn = $this->getWindowWeeks(6, 7);

		$this->runAsManager($eStructure, function() use($eStructure, $plantStructureIdKept, $plantStructureIdDragged, $seasonKept, $windowWeeksDragged, $ePlantOwn, $windowWeeksOwn) {

			$posts = [
				'renvoyée telle quelle' => ['plant' => $plantStructureIdKept, 'windowWeeks' => $this->getPosted($seasonKept['windowWeeks'])],
				'tirée d\'une semaine' => ['plant' => $plantStructureIdDragged, 'windowWeeks' => $this->getPosted($windowWeeksDragged)],
				// La fréquence cochée au panneau d'une espèce que la communauté ne mesure pas (`getForAdd()`).
				'tracée sur une espèce propre' => ['plant' => $ePlantOwn['id'], 'windowWeeks' => $this->getPosted($windowWeeksOwn), 'unit' => (string)$this->getUnitId('kg'), 'quantityBasket' => '0.5', 'price' => '6', 'frequency' => CompositionSpecies::BASE],
			];

			foreach($posts as $name => $post) {

				$fw = new \FailWatch();

				$action = $this->callRoute('POST', 'toolbox/composition:doAddSpecies', ['id' => $eStructure['id']] + $post);

				$this->assertEquals([[], \ViewAction::class], [array_column($fw->get(), 1), $action::class], 'la saison '.$name.' : l\'ajout est admis');

			}

		});

		$eCompositionPlanWritten = CompositionPlanLib::getByStructure($eStructure);
		$eCompositionPlanWritten['structure'] = $eStructure;

		$eCompositionSpeciesKept = $this->getSpecies($eCompositionPlanWritten, $plantIdKept);
		$eCompositionSpeciesDragged = $this->getSpecies($eCompositionPlanWritten, $plantIdDragged);
		$eCompositionSpeciesOwn = $this->getSpecies($eCompositionPlanWritten, $ePlantOwn['id']);

		$this->assertEquals(
			[$seasonKept['status'], CompositionLib::getWindowWeeksList($seasonKept['windowWeeks'])],
			[$eCompositionSpeciesKept['status'], $eCompositionSpeciesKept->getSeasonWeeks()],
			'la saison renvoyée telle que le panneau l\'a proposée reste celle de la source'
		);
		$this->assertEquals(
			[CompositionSpecies::CUSTOM, CompositionLib::getWindowWeeksList($windowWeeksDragged)],
			[$eCompositionSpeciesDragged['status'], $eCompositionSpeciesDragged->getSeasonWeeks()],
			'la saison tirée d\'une semaine arrive en base, réglée à la main'
		);

		$weeksOwn = CompositionLib::getWindowWeeksList($windowWeeksOwn);

		$this->assertEquals([CompositionSpecies::CUSTOM, $weeksOwn], [$eCompositionSpeciesOwn['status'], $eCompositionSpeciesOwn->getSeasonWeeks()], 'l\'espèce propre arrive en base sur la saison tracée');

		$weeksServed = $this->getWeeksServed($eCompositionPlanWritten, $ePlantOwn['id']);

		$this->assertTrue($weeksServed !== [], 'et les paniers la servent');
		$this->assertEquals([], array_values(array_diff($weeksServed, $weeksOwn)), 'dans les seules semaines tracées');

	}

	/**
	 * Une espèce dont la communauté mesure la quantité au panier sans la dire disponible dans aucune
	 * semaine (le basilic, le persil, la ciboulette, le mesclun au 03/10/2026) n'est jamais proposée,
	 * mais entre au tirage dès qu'on l'ajoute avec une saison. `draw()` lit alors son organe, que la
	 * source ne posait que pour les espèces disponibles : « Undefined array key 138 » en production le
	 * 03/10/2026, sur le départ de la 594, qui avait ajouté le basilic.
	 *
	 * L'inventaire vaut pour toutes ces espèces ; l'ajout s'éprouve sur la première dont la structure
	 * porte la copie, en relevant les avertissements PHP, que la réponse ne montre pas.
	 */
	public function testWithoutAvailability(): void {

		$source = CompositionLib::getSource();

		$plantIdsAvailable = [];

		foreach($source['availability'] as $structuresByPlant) {
			$plantIdsAvailable += $structuresByPlant;
		}

		$plantIdsWithoutAvailability = array_keys(array_diff_key($source['units'], $plantIdsAvailable));

		if($plantIdsWithoutAvailability === []) {
			$this->assertFail('l\'agrégé ne porte aucune espèce mesurée sans disponibilité : le contrôle ne verrait rien');
			return;
		}

		$this->assertEquals([], array_keys(array_diff_key($source['units'], $source['organs'])), 'toute espèce qui a une unité a son organe dans la source, même sans disponibilité');

		$eStructure = $this->getStructureWithoutPlan();
		$eCompositionPlan = $this->createStarted($eStructure);

		$plantIdsAddable = array_values(array_filter($plantIdsWithoutAvailability, fn(int $plantId) => $this->getPlantStructureId($eStructure, $plantId) !== NULL));

		if($plantIdsAddable === []) {
			$this->assertFail('la structure #'.$eStructure['id'].' ne porte la copie d\'aucune espèce mesurée sans disponibilité : le contrôle ne verrait rien');
			return;
		}

		$plantId = $plantIdsAddable[0];
		$post = [
			'id' => $eStructure['id'],
			'plant' => $this->getPlantStructureId($eStructure, $plantId),
			'windowWeeks' => $this->getPosted($this->getWindowWeeks(5, 9)),
			// Le plan du départ n'a pas de climat, et le panneau le demande pour une espèce que la
			// communauté ne dit disponible dans aucune semaine (`climate.required` sans lui).
			'climate' => self::CLIMATE,
		];

		$this->runAsManager($eStructure, function() use($post, &$action, &$warnings) {

			$warnings = [];

			set_error_handler(function(int $number, string $message, string $file, int $line) use(&$warnings) {
				$warnings[] = $message.' ('.basename($file).':'.$line.')';
				return TRUE;
			});

			try {
				$action = $this->callRoute('POST', 'toolbox/composition:doAddSpecies', $post);
			} finally {
				restore_error_handler();
			}

		});

		$this->assertEquals(\ViewAction::class, $action::class, 'l\'ajout est admis');
		$this->assertEquals([], array_values(array_unique($warnings)), 'et les paniers se composent sans avertissement PHP');
		$this->assertTrue($this->getWeeksServed(CompositionPlanLib::getByStructure($eStructure)->merge(['structure' => $eStructure]), $plantId) !== [], 'l\'espèce ajoutée est servie');

	}

	/**
	 * « Recommencer » : le départ rouvert montre les espèces ajoutées, livrées ou retirées comme le
	 * plan les porte, et son envoi les construit. Le nouveau plan les garde avec leur unité, leur
	 * quantité au panier et leur prix, et une espèce ajoutée puis retirée reste retirée.
	 *
	 * Le départ dessine ce que le nouveau plan gardera : une saison réglée à la main, sur une espèce
	 * ajoutée comme sur une espèce de la communauté, se lit la même sous chaque origine, et le
	 * nouveau plan la porte.
	 */
	public function testRestart(): void {

		$eStructure = $this->getStructureWithoutPlan();
		$eCompositionPlan = $this->createStarted($eStructure);

		[$plantId, $plantIdExcluded] = $this->getPlantsTyped($eCompositionPlan);

		$this->addTyped($eCompositionPlan, $plantId);
		$this->addTyped($eCompositionPlan, $plantIdExcluded);
		$this->exclude($eCompositionPlan, $plantIdExcluded);

		$ePlantOwn = $this->createPlantOwn($eStructure);
		$plantIdOwn = $ePlantOwn['id'];

		$this->addOwn($eCompositionPlan, $plantIdOwn, $this->getWindowWeeks(6, 7));

		// Une espèce de la communauté, réglée à la main sur le seul mois de mars, comme la grille
		// l'écrit (`compositionSpecies:doUpdateSeason`).
		$plantIdCustom = $this->getPlantDelivered($eCompositionPlan);
		$seasonMonths = CompositionLib::getSeasonMonths(self::SEASON);

		$eCompositionSpeciesCustom = $this->getSpecies($eCompositionPlan, $plantIdCustom);

		$fw = new \FailWatch();

		$eCompositionSpeciesCustom->build(['status', 'windowWeeks'], [
			'status' => CompositionSpecies::CUSTOM,
			'windowWeeks' => $this->getWindowWeeks(3, 3),
		]);

		$this->assertEquals([], array_column($fw->get(), 1), 'la saison réglée à la main est admise');

		CompositionSpeciesLib::updateSeason($eCompositionSpeciesCustom);

		$eCompositionPlanStart = CompositionPlanLib::getForStart($eStructure, self::SEASON);
		$eCompositionPlanStart['weeks'] = range(10, 40);
		$eCompositionPlanStart['cCompositionFormula'] = new \Collection([
			new CompositionFormula(['plan' => $eCompositionPlanStart, 'name' => 'Le panier', 'price' => 18.0, 'baskets' => 10, 'gap' => 5, 'version' => 0]),
		]);

		$cCompositionSpeciesStart = $eCompositionPlanStart['cCompositionSpecies'];

		$this->assertEquals(
			[TRUE, TRUE, TRUE],
			[$cCompositionSpeciesStart->offsetExists($plantId), $cCompositionSpeciesStart->offsetExists($plantIdExcluded), $cCompositionSpeciesStart->offsetExists($plantIdOwn)],
			'le départ rouvert montre les espèces ajoutées'
		);
		$this->assertEquals(
			[CompositionSpecies::NATURAL, CompositionSpecies::EXCLUDED, CompositionSpecies::NATURAL],
			[$cCompositionSpeciesStart[$plantId]['status'], $cCompositionSpeciesStart[$plantIdExcluded]['status'], $cCompositionSpeciesStart[$plantIdOwn]['status']],
			'livrées ou retirées comme le plan les porte'
		);
		$this->assertEquals(
			[],
			array_values(array_diff([$plantId, $plantIdExcluded, $plantIdOwn], CompositionPlanLib::getStartPlantIds($eStructure, self::SEASON))),
			'et son envoi les construit'
		);
		$this->assertEquals(
			$cCompositionSpeciesStart->getKeys(),
			array_values(array_intersect($cCompositionSpeciesStart->getKeys(), CompositionPlanLib::getStartPlantIds($eStructure, self::SEASON))),
			'comme chaque espèce qu\'il montre'
		);
		$this->assertFalse(array_key_exists(CompositionPlan::COMMUNITY, $cCompositionSpeciesStart[$plantId]['seasons']), 'la communauté ne date pas une espèce ajoutée');
		$this->assertTrue(array_key_exists(self::CLIMATE, $cCompositionSpeciesStart[$plantId]['seasons']), 'que le calendrier date');

		$sources = array_merge([CompositionPlan::COMMUNITY, CompositionPlan::CULTIVATION], CompositionPlan::model()->getPropertyEnum('climate'));

		$monthsOwn = CompositionLib::getSeasonShares($seasonMonths, array_merge($seasonMonths[6], $seasonMonths[7]), []);
		$monthsCustom = CompositionLib::getSeasonShares($seasonMonths, $seasonMonths[3], []);

		$this->assertEquals(array_fill_keys($sources, $monthsOwn), $cCompositionSpeciesStart[$plantIdOwn]['seasons'], 'l\'espèce propre se lit en juin et juillet sous chaque origine');
		$this->assertEquals(array_fill_keys($sources, $monthsCustom), $cCompositionSpeciesStart[$plantIdCustom]['seasons'], 'et l\'espèce de la communauté réglée à la main en mars sous chaque origine');

		$plantIdNatural = $this->getPlantDelivered($eCompositionPlan, [$plantIdCustom]);

		$this->assertTrue($cCompositionSpeciesStart[$plantIdNatural]['seasons'][CompositionPlan::COMMUNITY] !== $monthsCustom, 'là où une espèce qui suit la communauté garde sa saison, sans quoi le contrôle ne verrait rien');

		CompositionPlanLib::restart($eCompositionPlanStart);

		$eCompositionPlanNew = CompositionPlanLib::getByStructure($eStructure);
		$eCompositionPlanNew['structure'] = $eStructure;

		$this->assertTrue($eCompositionPlanNew['id'] !== $eCompositionPlan['id'], 'un nouveau plan');

		$cCompositionSpecies = CompositionSpecies::model()
			->select(['id', 'plant', 'status', 'windowWeeks', 'unit', 'quantityBasket', 'price'])
			->wherePlan($eCompositionPlanNew)
			->wherePlant('IN', [$plantId, $plantIdExcluded, $plantIdOwn, $plantIdCustom])
			->getCollection(index: 'plant');

		$this->assertEquals(4, $cCompositionSpecies->count(), 'les espèces ajoutées sont reportées');

		$kgId = $this->getUnitId('kg');

		$this->assertEquals(
			[CompositionSpecies::CALENDAR, $kgId, 0.5, 6.0],
			[$cCompositionSpecies[$plantId]['status'], $cCompositionSpecies[$plantId]['unit']['id'], $cCompositionSpecies[$plantId]['quantityBasket'], $cCompositionSpecies[$plantId]['price']],
			'l\'espèce livrée garde sa saison du calendrier, son unité, sa quantité au panier et son prix'
		);
		$this->assertTrue($cCompositionSpecies[$plantId]['windowWeeks'] !== [], 'avec ses semaines');
		$this->assertEquals(
			[CompositionSpecies::EXCLUDED, NULL, $kgId, 0.5, 6.0],
			[$cCompositionSpecies[$plantIdExcluded]['status'], $cCompositionSpecies[$plantIdExcluded]['windowWeeks'], $cCompositionSpecies[$plantIdExcluded]['unit']['id'], $cCompositionSpecies[$plantIdExcluded]['quantityBasket'], $cCompositionSpecies[$plantIdExcluded]['price']],
			'l\'espèce retirée le reste, et garde ce qu\'on lui avait réglé'
		);
		$this->assertEquals(
			[CompositionSpecies::CUSTOM, array_merge($seasonMonths[6], $seasonMonths[7]), $kgId, 0.5, 6.0],
			[$cCompositionSpecies[$plantIdOwn]['status'], CompositionLib::getWindowWeeksList($cCompositionSpecies[$plantIdOwn]['windowWeeks']), $cCompositionSpecies[$plantIdOwn]['unit']['id'], $cCompositionSpecies[$plantIdOwn]['quantityBasket'], $cCompositionSpecies[$plantIdOwn]['price']],
			'l\'espèce propre garde juin et juillet, comme le départ les dessinait'
		);
		$this->assertEquals(
			[CompositionSpecies::CUSTOM, $seasonMonths[3]],
			[$cCompositionSpecies[$plantIdCustom]['status'], CompositionLib::getWindowWeeksList($cCompositionSpecies[$plantIdCustom]['windowWeeks'])],
			'et l\'espèce de la communauté garde mars'
		);
		// Le report garantit la candidature, pas le panier : la proposition pèse l'espèce contre toutes
		// celles que leur saison rend candidates la même semaine, et la sert ou non selon le prix
		// visé.
		$windows = CompositionSpeciesLib::getWindowsByPlan($eCompositionPlanNew);

		$this->assertTrue(array_intersect($windows[$cCompositionSpecies[$plantId]['id']] ?? [], $eCompositionPlanNew['weeks']) !== [], 'l\'espèce reportée est candidate dans des semaines du nouveau plan');
		$this->assertFalse(array_key_exists($cCompositionSpecies[$plantIdExcluded]['id'], $windows), 'celle qu\'on avait retirée ne l\'est pas');
		$this->assertEquals([], $this->getWeeksServed($eCompositionPlanNew, $plantIdExcluded), 'et pas celle qu\'on avait retirée');

	}

	/**
	 * « Ajouter une espèce » au départ (r48e, planche r48, verdict 2 B) : le même panneau que l'onglet,
	 * sans rien écrire avant « Composer mes paniers ». Une espèce propre, que rien ne date et que la
	 * communauté ne compte pas, y demande son unité, sa quantité au panier, son prix et sa saison,
	 * tracée aux poignées (r67, le 03/10/2026, « la saison se demande au départ »), refusée
	 * sans elle ; une espèce que la corbeille a retirée de la liste s'y remet sans rien demander. Le
	 * rang rendu porte tout ce que l'envoi du départ relit, saison tracée comprise, et l'envoi qui ne
	 * la porte pas est refusé au lieu d'écrire une saison vide. Le plan écrit garde l'espèce propre
	 * livrée, avec ce qu'on lui a tapé et sa saison, dans laquelle les paniers la servent ; des mois
	 * postés à la main ne changent la saison d'aucune espèce.
	 */
	public function testStart(): void {

		$eStructure = $this->getStructureWithoutPlan();

		if($eStructure->acceptComposition() === FALSE) {
			$this->assertFail('la structure #'.$eStructure['id'].' n\'ouvre pas les paniers : le contrôle ne verrait rien');
			return;
		}

		$plantIdOwn = $this->createPlantOwn($eStructure)['id'];

		$cCompositionSpeciesStart = CompositionPlanLib::getForStart($eStructure, self::SEASON)['cCompositionSpecies'];
		$plantIds = $cCompositionSpeciesStart->getKeys();

		$plantIdsCopied = array_values(array_filter($plantIds, fn(int $plantId) => $this->getPlantStructureId($eStructure, $plantId) !== NULL));

		$this->assertTrue(count($plantIdsCopied) >= 2, 'deux espèces du départ au moins dont la structure porte la copie, sans quoi le contrôle ne verrait rien');

		// La première est retirée par la corbeille et remise par le panneau, la seconde reçoit des mois
		// postés à la main, que le départ ignore.
		[$plantIdRemoved, $plantIdTuned] = $plantIdsCopied;

		$delivered = array_values(array_diff($plantIds, [$plantIdRemoved]));
		$kgId = $this->getUnitId('kg');
		$seasonMonths = CompositionLib::getSeasonMonths(self::SEASON);
		$windowWeeksOwn = $this->getWindowWeeks(6, 7);

		$start = [
			'id' => $eStructure['id'],
			'season' => self::SEASON,
			'reference' => CompositionPlan::CALENDAR,
			'climate' => self::CLIMATE,
			'delivered' => implode(',', $delivered),
		];

		$this->runAsManager($eStructure, function() use($eStructure, $start, $delivered, $plantIds, $plantIdOwn, $plantIdRemoved, $plantIdTuned, $kgId, $seasonMonths, $windowWeeksOwn) {

			// Le panneau, sans espèce puis sur l'espèce propre.
			$data = $this->getData($this->callRoute('GET', 'toolbox/composition:addStartSpecies', $start));

			$this->assertTrue(in_array($plantIdOwn, $data->eCompositionSpecies['plantIdsAddable'], TRUE), 'l\'espèce propre s\'ajoute');
			$this->assertTrue(in_array($this->getPlantStructureId($eStructure, $plantIdRemoved), $data->eCompositionSpecies['plantIdsAddable'], TRUE), 'comme l\'espèce que la corbeille a retirée de la liste');
			$this->assertFalse(in_array($this->getPlantStructureId($eStructure, $plantIdTuned), $data->eCompositionSpecies['plantIdsAddable'], TRUE), 'et pas une espèce que la liste livre');

			$data = $this->getData($this->callRoute('GET', 'toolbox/composition:addStartSpecies', $start + ['plant' => $plantIdOwn]));
			$eCompositionSpecies = $data->eCompositionSpecies;

			$this->assertEquals([FALSE, TRUE, FALSE, TRUE], [$eCompositionSpecies['climateAsked'], $eCompositionSpecies['typed'], $eCompositionSpecies['written'], $eCompositionSpecies['undated']], 'l\'espèce propre, qu\'aucune source ne date, demande son unité, sa quantité, son prix et sa saison, pas le climat');

			$panel = new \DOMXPath($this->getDocument(new CompositionSpeciesUi()->addStart($eCompositionSpecies, $data->delivered)->body));

			foreach(['addStartSpecies' => 'le formulaire du choix', 'doAddStartSpecies' => 'celui de l\'envoi'] as $route => $name) {

				$values = [];

				foreach(['id', 'season', 'reference', 'climate', 'delivered'] as $field) {
					$values[$field] = $panel->query('//form[contains(@data-ajax-form, ":'.$route.'")]//input[@type="hidden"][@name="'.$field.'"]')->item(0)?->getAttribute('value');
				}

				$this->assertEquals(array_map(strval(...), $start), $values, $name.' réémet ce que le départ montrait');

			}

			// La saison se demande au départ (r67, le 03/10/2026) : aucune source ne datant
			// l'espèce propre, le panneau porte le rang de l'onglet Espèces, vide, à tracer aux poignées.
			$this->assertEquals(
				[0, 1, 0, 0, 0, 0],
				[
					$panel->query('//input[@name="months"]')->length,
					$panel->query('//form[contains(@data-ajax-form, ":doAddStartSpecies")]//*[@data-wrapper="windowWeeks"]//*[@data-season-field]//tr[@data-species][@data-season=""]')->length,
					$panel->query('//input[starts-with(@name, "windowWeeks[")]')->length,
					$panel->query('//*[@data-dropdown-id="toolbox-composition-add-months"]')->length,
						$panel->query('//input[@type="radio"][@name="unit"][@checked]')->length,
					$panel->query('//select[@name="climate"]')->length,
				],
				'le panneau demande la saison sur un rang vide à régler aux poignées, ne coche aucune unité et ne demande pas le climat'
			);

			// L'envoi du panneau : refusé sur ses champs, puis admis.
			$post = $start + ['plant' => $plantIdOwn];

			$fw = new \FailWatch();

			$this->assertEquals(\FailAction::class, $this->callRoute('POST', 'toolbox/composition:doAddStartSpecies', $post)::class, 'sans rien de tapé, l\'envoi du panneau est refusé');
			$this->assertEquals(
				['CompositionSpecies::unit.offered', 'CompositionSpecies::quantityBasket.required', 'CompositionSpecies::price.required', 'CompositionSpecies::windowWeeks.months'],
				array_column($fw->get(), 1),
				'sur l\'unité, la quantité, le prix et la saison'
			);

			$fw = new \FailWatch();

			$this->assertEquals(\FailAction::class, $this->callRoute('POST', 'toolbox/composition:doAddStartSpecies', $start + ['plant' => $this->getPlantStructureId($eStructure, $plantIdTuned)])::class, 'une espèce que la liste livre ne s\'ajoute pas');
			$this->assertEquals(['CompositionSpecies::plant.addable'], array_column($fw->get(), 1), 'et le dit');

			$typed = ['unit' => (string)$kgId, 'quantityBasket' => '0.5', 'price' => '6'];

			$fw = new \FailWatch();

			$this->assertEquals(\FailAction::class, $this->callRoute('POST', 'toolbox/composition:doAddStartSpecies', $post + $typed)::class, 'un demi-kilo à 6 € sans saison : l\'envoi du panneau est refusé');
			$this->assertEquals(['CompositionSpecies::windowWeeks.months'], array_column($fw->get(), 1), 'sur la saison, avec le message de l\'onglet Espèces');

			$fw = new \FailWatch();

			$data = $this->getData($this->callRoute('POST', 'toolbox/composition:doAddStartSpecies', $post + $typed + ['windowWeeks' => $this->getPosted($windowWeeksOwn)]));

			$this->assertEquals([], array_column($fw->get(), 1), 'un demi-kilo à 6 € de juin à juillet : l\'envoi du panneau est admis');
			$this->assertFalse(CompositionPlanLib::getByStructure($eStructure)->notEmpty(), 'et rien n\'est écrit');

			$row = new \DOMXPath($this->getDocument(new CompositionStartUi()->getSpeciesRow(new \util\FormUi(), $data->eCompositionPlan, $data->eCompositionSpecies)));
			$value = fn(string $name): ?string => $row->query('//div[@data-plant="'.$plantIdOwn.'"]//input[@type="hidden"][@name="'.$name.'"][not(@disabled)]')->item(0)?->getAttribute('value');

			$this->assertEquals(
				[CompositionSpecies::NATURAL, (string)$plantIdOwn, (string)$kgId, '0.5', '6', 0, (string)$windowWeeksOwn[0][0], (string)$windowWeeksOwn[0][1], 2],
				[$value('status['.$plantIdOwn.']'), $value('added[]'), $value('unit['.$plantIdOwn.']'), $value('quantityBasket['.$plantIdOwn.']'), $value('price['.$plantIdOwn.']'), $row->query('//input[starts-with(@name, "months[")]')->length, $value('windowWeeks['.$plantIdOwn.'][0][0]'), $value('windowWeeks['.$plantIdOwn.'][0][1]'), $row->query('//input[starts-with(@name, "windowWeeks[")]')->length],
				'le rang ajouté porte son statut, l\'espèce de la structure, ce qu\'on lui a tapé et la saison tracée'
			);

			$this->assertEquals(0, $row->query('//*[@data-season-field or @data-dropdown]')->length, 'et dessine juin et juillet, sans source, sans rien à régler dans la liste');

			// Une espèce de la communauté retirée par la corbeille se remet par le panneau, sans rien
			// demander : sa saison est celle de la source.
			$data = $this->getData($this->callRoute('POST', 'toolbox/composition:doAddStartSpecies', $start + ['plant' => $this->getPlantStructureId($eStructure, $plantIdRemoved)]));

			$this->assertEquals([$plantIdRemoved, FALSE], [$data->eCompositionSpecies['plant']['id'], $data->eCompositionSpecies['typed']], 'l\'espèce retirée revient sous son id au référentiel, sans rien demander');

			$row = new \DOMXPath($this->getDocument(new CompositionStartUi()->getSpeciesRow(new \util\FormUi(), $data->eCompositionPlan, $data->eCompositionSpecies)));

			$this->assertEquals(
				[1, 0, 0],
				[
					$row->query('//input[@name="status['.$plantIdRemoved.']"][@value="'.CompositionSpecies::NATURAL.'"]')->length,
					$row->query('//input[starts-with(@name, "months[")]')->length,
					$row->query('//input[starts-with(@name, "unit[")]')->length,
				],
				'son rang la livre, sans mois réglés ni unité à poster'
			);

			// L'envoi du départ, tel que le formulaire le poste après ces deux ajouts, et des mois ajoutés
			// à la main, que plus aucun champ ne porte (r58) : ils ne doivent rien régler. La saison
			// tracée de l'espèce propre part sous son index (r67).
			$status = array_fill_keys($plantIds, CompositionSpecies::NATURAL) + [$plantIdOwn => CompositionSpecies::NATURAL];

			$input = [
				'id' => $eStructure['id'],
				'season' => self::SEASON,
				'reference' => CompositionPlan::CALENDAR,
				'climate' => self::CLIMATE,
				'weeks' => range(10, 40),
				'name' => ['Le panier'],
				'price' => ['18'],
				'baskets' => ['10'],
				'status' => $status,
				'added' => [$plantIdOwn, $this->getPlantStructureId($eStructure, $plantIdRemoved)],
				'unit' => [$plantIdOwn => (string)$kgId],
				'quantityBasket' => [$plantIdOwn => '0.5'],
				'months' => [$plantIdOwn => '6,7', $plantIdTuned => '3'],
			];

			// Le prix des tailles et celui de l'espèce propre partagent leur nom de champ, chacun sous
			// son index.
			$input['price'][$plantIdOwn] = '6';

			// Sans sa saison, l'espèce propre partirait sans qu'aucun panier la serve : le départ la
			// refuse en la nommant, au lieu de l'écrire avec une saison vide.
			$fw = new \FailWatch();

			$this->assertEquals(\FailAction::class, $this->callRoute('POST', 'toolbox/composition:doStart', $input)::class, 'sans la saison de l\'espèce propre, l\'envoi du départ est refusé');
			$this->assertEquals(['CompositionSpecies::windowWeeks.undated'], array_column($fw->get(), 1), 'sur cette saison');
			$this->assertFalse(CompositionPlanLib::getByStructure($eStructure)->notEmpty(), 'et rien n\'est écrit');

			// Une saison tracée avant les semaines livrées (de la 10 à la 40) ne croise aucune
			// d'elles : aucun panier ne servirait l'espèce, et le départ la refuse en la nommant (r69).
			$input['windowWeeks'] = [$plantIdOwn => $this->getPosted([[1, 5]])];

			$fw = new \FailWatch();

			$this->assertEquals(\FailAction::class, $this->callRoute('POST', 'toolbox/composition:doStart', $input)::class, 'une saison qui ne croise aucune semaine livrée est refusée');
			$this->assertEquals(['CompositionSpecies::windowWeeks.delivered'], array_column($fw->get(), 1), 'sur cette saison');
			$this->assertFalse(CompositionPlanLib::getByStructure($eStructure)->notEmpty(), 'et rien n\'est écrit');

			$input['windowWeeks'] = [$plantIdOwn => $this->getPosted($windowWeeksOwn)];

			$fw = new \FailWatch();

			$action = $this->callRoute('POST', 'toolbox/composition:doStart', $input);

			$this->assertEquals([[], \RedirectAction::class], [array_column($fw->get(), 1), $action::class], 'l\'envoi du départ est admis');

		});

		$eCompositionPlan = CompositionPlanLib::getByStructure($eStructure);

		$this->assertEquals([CompositionPlan::CALENDAR, self::CLIMATE], [$eCompositionPlan['reference'], $eCompositionPlan['climate']], 'le plan est écrit sous le calendrier');

		$cCompositionSpecies = CompositionSpecies::model()
			->select(['plant', 'status', 'windowWeeks', 'unit', 'quantityBasket', 'price', 'frequency'])
			->wherePlan($eCompositionPlan)
			->wherePlant('IN', [$plantIdOwn, $plantIdRemoved, $plantIdTuned])
			->getCollection(index: 'plant');

		$this->assertEquals(3, $cCompositionSpecies->count(), 'avec les trois espèces');
		$this->assertEquals(
			[CompositionSpecies::CUSTOM, $windowWeeksOwn, $kgId, 0.5, 6.0, CompositionSpecies::BASE],
			[$cCompositionSpecies[$plantIdOwn]['status'], $cCompositionSpecies[$plantIdOwn]['windowWeeks'], $cCompositionSpecies[$plantIdOwn]['unit']['id'], $cCompositionSpecies[$plantIdOwn]['quantityBasket'], $cCompositionSpecies[$plantIdOwn]['price'], $cCompositionSpecies[$plantIdOwn]['frequency']],
			'l\'espèce propre reste livrée, sur la saison tracée au panneau, au kilo, un demi-kilo à 6 €, en fréquence « base »'
		);
		$this->assertTrue(
			in_array($cCompositionSpecies[$plantIdTuned]['status'], [CompositionSpecies::CALENDAR, CompositionSpecies::NATURAL], TRUE) and
			CompositionLib::getWindowWeeksList($cCompositionSpecies[$plantIdTuned]['windowWeeks']) !== $seasonMonths[3],
			'les mois postés à la main ne règlent pas la saison d\'une espèce de la liste : '.$cCompositionSpecies[$plantIdTuned]['status']
		);
		$this->assertTrue(in_array($cCompositionSpecies[$plantIdRemoved]['status'], [CompositionSpecies::CALENDAR, CompositionSpecies::NATURAL], TRUE), 'et l\'espèce remise par le panneau, livrée sur la saison de sa source : '.$cCompositionSpecies[$plantIdRemoved]['status']);

		$eCompositionPlan['structure'] = $eStructure;

		$weeksServed = $this->getWeeksServed($eCompositionPlan, $plantIdOwn);
		$weeksTraced = range($windowWeeksOwn[0][0], $windowWeeksOwn[0][1]);

		$this->assertTrue($weeksServed !== [] and array_diff($weeksServed, $weeksTraced) === [], 'les paniers servent l\'espèce propre, dans les seules semaines tracées : '.implode(', ', $weeksServed));

	}

	/**
	 * « Recommencer » reconduit les variantes (r76, GO du coordinateur le 04/10/2026, qui remplace la
	 * reconduite d'une déclinaison de r75). Le départ ne montre que des espèces nues, une seule par
	 * fiche, et le nouveau plan garde chaque variante de l'ancien sous son nom, à côté de son espèce
	 * nue, avec ce qu'on lui avait réglé : une saison réglée à la main et sa fréquence. Une variante
	 * retirée le reste.
	 */
	public function testStartVariant(): void {

		$eStructure = $this->getStructureWithoutPlan();
		$eCompositionPlan = $this->createStarted($eStructure);

		$plantId = $this->getPlantDelivered($eCompositionPlan);
		$windowWeeks = $this->getWindowWeeks(6, 7);

		$nameKept = 'Vrac du test des paniers';
		$nameExcluded = 'Botte du test des paniers';

		$this->createVariant($eCompositionPlan, $plantId, $nameKept, ['status' => CompositionSpecies::CUSTOM, 'windowWeeks' => $windowWeeks]);
		$this->createVariant($eCompositionPlan, $plantId, $nameExcluded);
		$this->exclude($eCompositionPlan, $plantId, $nameExcluded);

		$plantIds = CompositionPlanLib::getStartPlantIds($eStructure, self::SEASON);

		$this->assertEquals([$plantId], array_values(array_filter($plantIds, fn(int $plantIdStart) => $plantIdStart === $plantId)), 'le départ montre la fiche une fois, sous son espèce nue');

		$this->runAsManager($eStructure, function() use($eStructure, $plantIds) {

			$fw = new \FailWatch();

			$action = $this->callRoute('POST', 'toolbox/composition:doStart', [
				'id' => $eStructure['id'],
				'season' => self::SEASON,
				'reference' => CompositionPlan::CALENDAR,
				'climate' => self::CLIMATE,
				'weeks' => range(10, 40),
				'name' => ['Le panier'],
				'price' => ['18'],
				'baskets' => ['10'],
				'status' => array_fill_keys($plantIds, CompositionSpecies::NATURAL),
			]);

			$this->assertEquals([[], \RedirectAction::class], [array_column($fw->get(), 1), $action::class], 'l\'envoi du départ est admis');

		});

		$eCompositionPlanNew = CompositionPlanLib::getByStructure($eStructure);

		$this->assertTrue($eCompositionPlanNew['id'] !== $eCompositionPlan['id'], 'un nouveau plan');

		$read = fn(?string $name) => CompositionSpecies::model()
			->select(['status', 'windowWeeks', 'frequency'])
			->wherePlan($eCompositionPlanNew)
			->wherePlant($plantId)
			->whereName($name)
			->get();

		$eCompositionSpeciesBare = $read(NULL);
		$eCompositionSpeciesKept = $read($nameKept);
		$eCompositionSpeciesExcluded = $read($nameExcluded);

		$this->assertEquals(3, CompositionSpecies::model()->wherePlan($eCompositionPlanNew)->wherePlant($plantId)->count(), 'le nouveau plan porte la fiche trois fois, l\'espèce nue et ses deux variantes');
		$this->assertTrue($eCompositionSpeciesBare->notEmpty() and $eCompositionSpeciesBare['status'] !== CompositionSpecies::EXCLUDED, 'il livre l\'espèce nue');
		$this->assertEquals(
			[TRUE, CompositionSpecies::CUSTOM, CompositionLib::getWindowWeeksList($windowWeeks), CompositionSpecies::BASE],
			[$eCompositionSpeciesKept->notEmpty(), $eCompositionSpeciesKept['status'], CompositionLib::getWindowWeeksList($eCompositionSpeciesKept['windowWeeks']), $eCompositionSpeciesKept['frequency']],
			'il reconduit la variante sous son nom, avec sa saison réglée à la main et sa fréquence'
		);
		$this->assertEquals([TRUE, CompositionSpecies::EXCLUDED], [$eCompositionSpeciesExcluded->notEmpty(), $eCompositionSpeciesExcluded['status']], 'et la variante retirée le reste');

	}

	/**
	 * La corbeille ne touche que le statut d'un rang (`compositionStart.js`) : une espèce propre
	 * ajoutée au départ puis retirée garde dans le formulaire la saison qu'on lui avait tracée, ici
	 * hors des semaines livrées. Elle ne part pas, et cette saison ne bloque pas l'envoi (r69).
	 */
	public function testStartExcludedTraced(): void {

		$eStructure = $this->getStructureWithoutPlan();

		if($eStructure->acceptComposition() === FALSE) {
			$this->assertFail('la structure #'.$eStructure['id'].' n\'ouvre pas les paniers : le contrôle ne verrait rien');
			return;
		}

		$plantIdOwn = $this->createPlantOwn($eStructure)['id'];
		$plantIds = CompositionPlanLib::getForStart($eStructure, self::SEASON)['cCompositionSpecies']->getKeys();
		$kgId = $this->getUnitId('kg');

		$this->runAsManager($eStructure, function() use($eStructure, $plantIds, $plantIdOwn, $kgId) {

			$fw = new \FailWatch();

			$action = $this->callRoute('POST', 'toolbox/composition:doStart', [
				'id' => $eStructure['id'],
				'season' => self::SEASON,
				'reference' => CompositionPlan::CALENDAR,
				'climate' => self::CLIMATE,
				'weeks' => range(10, 40),
				'name' => ['Le panier'],
				'price' => ['18', $plantIdOwn => '6'],
				'baskets' => ['10'],
				'status' => array_fill_keys($plantIds, CompositionSpecies::NATURAL) + [$plantIdOwn => CompositionSpecies::EXCLUDED],
				'added' => [$plantIdOwn],
				'unit' => [$plantIdOwn => (string)$kgId],
				'quantityBasket' => [$plantIdOwn => '0.5'],
				'windowWeeks' => [$plantIdOwn => $this->getPosted([[1, 5]])],
			]);

			$this->assertEquals([[], \RedirectAction::class], [array_column($fw->get(), 1), $action::class], 'retirée par la corbeille, sa saison tracée ne bloque pas l\'envoi');

		});

		$eCompositionSpeciesOwn = CompositionSpecies::model()
			->select(['status'])
			->wherePlan(CompositionPlanLib::getByStructure($eStructure))
			->wherePlant($plantIdOwn)
			->get();

		$this->assertTrue($eCompositionSpeciesOwn->notEmpty(), 'le plan garde l\'espèce');
		$this->assertEquals(CompositionSpecies::EXCLUDED, $eCompositionSpeciesOwn['status'], 'retirée, elle ne part pas');

	}

	/**
	 * Le climat construit comme `composition:doAddSpecies` le construit, pour une modification. Rend
	 * les refus.
	 */
	private function buildClimate(CompositionPlan $eCompositionPlan, array $input): array {

		$fw = new \FailWatch();

		$eCompositionPlan->build(['climate'], $input, new \PropertyList('update'));

		return array_column($fw->get(), 1);

	}

	/**
	 * L'unité, la quantité au panier et le prix d'une espèce neuve, construits comme
	 * `composition:doAddSpecies` les construit. Rend les refus.
	 */
	private function buildTyped(CompositionSpecies $eCompositionSpecies, array $input): array {

		$fw = new \FailWatch();

		$eCompositionSpecies->build(['unit', 'quantityBasket', 'price'], $input);

		return array_column($fw->get(), 1);

	}

	/**
	 * Une espèce que la communauté ne compte pas, ajoutée par les appels de `composition:doAddSpecies` :
	 * un demi-kilo à 6 €, sous le climat du test quand le plan n'en porte pas.
	 */
	private function addTyped(CompositionPlan $eCompositionPlan, int $plantId): void {

		$eCompositionSpecies = CompositionSpeciesLib::getForAdd($eCompositionPlan, $this->getPlantStructureId($eCompositionPlan['structure'], $plantId));

		$climate = CompositionSpeciesLib::isClimateAsked($eCompositionSpecies);

		$fw = new \FailWatch();

		if($climate) {
			$eCompositionPlan->build(['climate'], ['climate' => self::CLIMATE], new \PropertyList('update'));
		}

		$eCompositionSpecies->build(['unit', 'quantityBasket', 'price'], ['unit' => (string)$this->getUnitId('kg'), 'quantityBasket' => '0.5', 'price' => '6']);
		// Le panneau renvoie la saison qu'il propose, que personne n'a tirée aux poignées.
		$eCompositionSpecies->build(['status', 'windowWeeks'], CompositionSpeciesLib::getSeasonAdded($eCompositionSpecies, $this->getPosted(CompositionSpeciesLib::getSeasonRestored($eCompositionSpecies)['windowWeeks'])));

		$this->assertEquals([], array_column($fw->get(), 1), 'l\'ajout est admis');

		CompositionSpeciesLib::add($eCompositionSpecies, $climate);

	}

	/**
	 * Une espèce propre à la structure, ajoutée par les appels de `composition:doAddSpecies` : un
	 * demi-kilo à 6 €, sur la saison tracée aux poignées `$windowWeeks`.
	 */
	private function addOwn(CompositionPlan $eCompositionPlan, int $plantId, array $windowWeeks): void {

		$eCompositionSpecies = CompositionSpeciesLib::getForAdd($eCompositionPlan, $plantId);

		$fw = new \FailWatch();

		$eCompositionSpecies->build(['unit', 'quantityBasket', 'price'], ['unit' => (string)$this->getUnitId('kg'), 'quantityBasket' => '0.5', 'price' => '6']);
		$eCompositionSpecies->build(['status', 'windowWeeks'], CompositionSpeciesLib::getSeasonAdded($eCompositionSpecies, $this->getPosted($windowWeeks)));

		$this->assertEquals([], array_column($fw->get(), 1), 'l\'ajout de l\'espèce propre est admis');

		CompositionSpeciesLib::add($eCompositionSpecies, FALSE);

	}

	/**
	 * L'espèce en activité de la structure qui recopie l'espèce `$plantId` du référentiel partagé, la
	 * plus ancienne : celle que la recherche du panneau poste. `NULL` quand la structure n'en a pas.
	 */
	private function getPlantStructureId(\structure\Structure $eStructure, int $plantId): ?int {

		return \plant\Plant::model()
			->whereStructure($eStructure)
			->whereReference($plantId)
			->whereStatus(\plant\Plant::ACTIVE)
			->sort(['id' => SORT_ASC])
			->getValue('id');

	}

	/**
	 * Une espèce propre à la structure, sans référence, que ni le calendrier ni la communauté ne
	 * datent.
	 */
	private function createPlantOwn(\structure\Structure $eStructure, string $name = 'Pourpier du test des paniers'): \plant\Plant {

		$ePlant = new \plant\Plant([
			'structure' => $eStructure,
			'name' => $name,
			'cycle' => \plant\Plant::ANNUAL,
		]);

		// `build()` aurait vérifié le nom et le cycle postés par la fiche d'une espèce : le décor pose
		// ici un nom que la structure ne porte pas et une constante du schéma.
		\plant\PlantLib::create($ePlant);

		return $ePlant;

	}

	/**
	 * Une variante `$name` de l'espèce nue du plan qui lit l'espèce `$plantId` (r76) : une espèce du
	 * plan de plus sur la même fiche, avec la saison de son espèce nue, en fréquence « base »,
	 * `$values` réglant le reste. Le plan se recompose avec elle. Rend la variante telle que la page
	 * la charge.
	 */
	private function createVariant(CompositionPlan $eCompositionPlan, int $plantId, string $name, array $values = []): CompositionSpecies {

		$eCompositionSpeciesBare = $this->getSpecies($eCompositionPlan, $plantId);

		$eCompositionSpecies = new CompositionSpecies([
			'plan' => $eCompositionPlan,
			'plant' => $eCompositionSpeciesBare['plant'],
			'name' => $name,
			'status' => $eCompositionSpeciesBare['status'],
			'windowWeeks' => $eCompositionSpeciesBare['windowWeeks'],
			'unit' => new \product\Unit(),
			'quantityBasket' => NULL,
			'price' => NULL,
			'step' => NULL,
			'minimum' => NULL,
			'quantity' => NULL,
			'weight' => NULL,
			'frequency' => CompositionSpecies::BASE,
			'product' => new \product\Product(),
		]);

		$eCompositionSpecies->merge($values);

		// Sans build() : `build()` aurait vérifié le nom, l'unité cochée, la saison et les réglages
		// postés par « Ajouter une espèce », que `testAddVariant()` éprouve par la route (r76 lot 2). Le
		// décor veut une variante dont il fixe chaque valeur : la saison de l'espèce nue, déjà
		// construite quand le plan l'a écrite, ou celle que `$values` fixe.
		CompositionSpeciesLib::add($eCompositionSpecies, FALSE);

		return $this->getSpecies($eCompositionPlan, $plantId, $name);

	}

	/**
	 * Une espèce de la communauté que le plan livre et dont la structure porte la copie, par son id,
	 * la première hors de `$plantIdsSkipped`.
	 */
	private function getPlantDelivered(CompositionPlan $eCompositionPlan, array $plantIdsSkipped = []): int {

		foreach(array_diff($this->getPlantIdsDelivered($eCompositionPlan['structure']), $plantIdsSkipped) as $plantId) {

			if($this->getPlantStructureId($eCompositionPlan['structure'], $plantId) !== NULL) {
				return $plantId;
			}

		}

		throw new \Exception('Aucune espèce livrée dont la structure porte la copie');

	}

	/**
	 * L'id au référentiel des espèces nues que l'onglet « Espèces » montre, dans son ordre. L'onglet
	 * range ses rangs par espèce du plan (r76), dont une variante, qui lit la même espèce que l'espèce
	 * nue qu'elle décline : c'est l'espèce nue qui dit si le plan livre une espèce.
	 */
	private function getPlantIdsDelivered(\structure\Structure $eStructure): array {

		$plantIds = [];

		foreach(CompositionPlanLib::getForScreen($eStructure, NULL, tab: CompositionPlan::TAB_SPECIES)['cCompositionSpecies'] as $eCompositionSpecies) {

			if($eCompositionSpecies['name'] === NULL) {
				$plantIds[] = $eCompositionSpecies['plant']['id'];
			}

		}

		return $plantIds;

	}

	/**
	 * L'espèce retirée, par les appels de `compositionSpecies:doExclude` : l'espèce nue, ou sa variante
	 * `$name`.
	 */
	private function exclude(CompositionPlan $eCompositionPlan, int $plantId, ?string $name = NULL): void {

		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $plantId, $name);

		$fw = new \FailWatch();

		$eCompositionSpecies->build(['status'], ['status' => CompositionSpecies::EXCLUDED]);

		$this->assertEquals([], array_column($fw->get(), 1), 'le retrait est admis');

		CompositionSpeciesLib::exclude($eCompositionSpecies);

	}

	/**
	 * Deux espèces que la communauté ne compte pas, que le calendrier type date sur les semaines du
	 * plan sous le climat du test et dont la structure porte la copie, par leur id au référentiel : la
	 * première s'ajoute, la seconde dit ce qui se passe ensuite.
	 */
	private function getPlantsTyped(CompositionPlan $eCompositionPlan): array {

		$plantIds = array_values(array_diff(CompositionLib::getCalendarPlantIds(), array_keys(CompositionLib::getSource()['units'])));
		sort($plantIds);

		// Hors plan, chaque espèce se sert sous son propre id (`getCalendarWindows()` prend `[servi => lu]`).
		$windows = CompositionLib::getCalendarWindows(self::CLIMATE, array_combine($plantIds, $plantIds));
		$plantIdsDated = [];

		foreach($plantIds as $plantId) {

			$weeks = array_intersect(array_keys($windows[$plantId] ?? []), $eCompositionPlan['weeks']);

			if(count($weeks) >= 8 and $this->getPlantStructureId($eCompositionPlan['structure'], $plantId) !== NULL) {
				$plantIdsDated[] = $plantId;
			}

		}

		$this->assertTrue(count($plantIdsDated) >= 2, 'deux espèces hors communauté au moins sont datées sur le plan, sans quoi le contrôle ne verrait rien');

		return array_slice($plantIdsDated, 0, 2);

	}

	/**
	 * `$check` joué par l'administrateur de la structure, connecté le temps du contrôle : les routes
	 * du départ valident son droit.
	 */
	private function runAsManager(\structure\Structure $eStructure, \Closure $check): void {

		$eMember = \structure\Member::model()
			->select(['user' => new \Sql('m1.user', 'element')])
			->join(\structure\Role::model(), 'm1.role = m2.id')
			->where('m2.admin', TRUE)
			->where('m1.structure', $eStructure)
			->where('m1.status', \structure\Member::IN)
			->where('m1.structureStatus', \structure\Member::ACTIVE)
			->sort(['m1.id' => SORT_ASC])
			->get();

		\user\ConnectionLib::setOnline($eMember['user']);

		try {
			$check();
		} finally {
			\user\ConnectionLib::setOnline($this->eUserInitial);
		}

	}

	/**
	 * `runAsManager()` qui rend ce que `$check` rend.
	 */
	private function runAsManagerReturn(\structure\Structure $eStructure, \Closure $check): mixed {

		$value = NULL;

		$this->runAsManager($eStructure, function() use($check, &$value) {
			$value = $check();
		});

		return $value;

	}

	/**
	 * La route jouée par son adresse, comme `RightsTest::callRoute()` : l'action qu'elle lève.
	 */
	private function callRoute(string $method, string $path, array $input): \Action {

		if(defined('LIME_URL') === FALSE) {
			define('LIME_URL', \Lime::getUrl());
		}

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodInitial = $requestMethod->getValue();
		$requestMethod->setValue(NULL, $method);

		[$getInitial, $postInitial, $requestInitial] = [$_GET, $_POST, $_REQUEST];

		if($method === 'GET') {
			[$_GET, $_POST, $_REQUEST] = [$input, [], $input];
		} else {
			[$_GET, $_POST, $_REQUEST] = [[], $input, $input];
		}

		try {
			$pages = \Route::getCompatiblePages($path);
			$pages[201] = new \ReflectionMethod(\Page::class, 'getIndexPages')->invoke(NULL, $path);
			ksort($pages);

			\Page::execute($pages, new \stdClass());
		} catch(\Action $action) {
			return $action;
		} finally {
			[$_GET, $_POST, $_REQUEST] = [$getInitial, $postInitial, $requestInitial];
			$requestMethod->setValue(NULL, $requestMethodInitial);
		}

	}

	/**
	 * Les données que la vue d'une action reçoit.
	 */
	private function getData(\Action $action): \stdClass {

		return new \ReflectionProperty($action, 'data')->getValue($action);

	}

	private function getUnitId(string $fqn): int {

		return \product\Unit::model()
			->whereFqn($fqn)
			->whereStructure(NULL)
			->getValue('id');

	}

	/**
	 * La saison des mois `$monthFirst` à `$monthLast` de la saison du test, d'un seul tenant, en
	 * intervalles : ce que donne un tracé aux poignées de la première semaine du premier mois à la
	 * dernière du second.
	 */
	private function getWindowWeeks(int $monthFirst, int $monthLast): array {

		$seasonMonths = CompositionLib::getSeasonMonths(self::SEASON);

		return [[min($seasonMonths[$monthFirst]), max($seasonMonths[$monthLast])]];

	}

	/**
	 * Une saison en intervalles telle que le panneau la poste (`ToolboxCompositionSeason.write()`) :
	 * des chaînes, que `build()` n'a pas encore converties.
	 */
	private function getPosted(array $windowWeeks): array {

		return array_map(fn(array $interval) => array_map(strval(...), $interval), $windowWeeks);

	}

	/**
	 * Le champ de saison que le panneau doit porter pour la saison `$windowWeeks` : un seul rang de
	 * la grille, réglable aux poignées (`toolbox-composition-season-mode`), une case par semaine de la
	 * saison, la saison écrite comme `ToolboxCompositionSeason.read()` la lit (`data-season`,
	 * « 14-28,38-44 »), et deux champs par intervalle.
	 */
	private function getSeasonField(array $windowWeeks): array {

		$fields = [];

		foreach($windowWeeks as $position => [$start, $stop]) {
			$fields['windowWeeks['.$position.'][0]'] = (string)$start;
			$fields['windowWeeks['.$position.'][1]'] = (string)$stop;
		}

		return [
			'rows' => 1,
			'mode' => TRUE,
			'cells' => count(CompositionLib::getSeasonWeeks()),
			'season' => implode(',', array_map(fn(array $interval) => $interval[0].'-'.$interval[1], $windowWeeks)),
			'fields' => $fields,
		];

	}

	/**
	 * Le champ de saison que porte le formulaire d'envoi du panneau, lu comme `getSeasonField()` le
	 * décrit.
	 */
	private function readSeasonField(\DOMXPath $panel): array {

		$field = '//form[contains(@data-ajax-form, ":doAddSpecies")]//div[@data-wrapper="windowWeeks"]//div[@data-season-field]';
		$rows = $panel->query($field.'//tr[@data-species]');

		$fields = [];

		foreach($panel->query('//form[contains(@data-ajax-form, ":doAddSpecies")]//input[starts-with(@name, "windowWeeks[")]') as $input) {
			$fields[$input->getAttribute('name')] = $input->getAttribute('value');
		}

		return [
			'rows' => $rows->length,
			'mode' => $panel->query($field.'[contains(concat(" ", @class, " "), " toolbox-composition-season-mode ")]')->length === 1,
			'cells' => $panel->query($field.'//tr[@data-species]/td[@data-week]')->length,
			'season' => $rows->item(0)?->getAttribute('data-season'),
			'fields' => $fields,
		];

	}

	/**
	 * Le panneau « Ajouter une espèce », tel que la vue le rend.
	 */
	/**
	 * Les réglages que porte un panneau sous `$root`, dans l'ordre : chaque groupe à libellé hors de la
	 * saison, s'il se montre, avec son libellé, les suffixes de ses champs et les champs qu'il poste, un
	 * par valeur pour des cases. Les poids de l'unité, groupes dans le groupe de l'unité, comptent avec
	 * elle.
	 */
	private function readSettings(\DOMXPath $panel, string $root): array {

		$group = '[contains(concat(" ", normalize-space(@class), " "), " form-group ")]';

		$settings = [];

		foreach($panel->query($root.'//div'.$group.'[not(ancestor::div'.$group.')][label]') as $node) {

			if($node->getAttribute('data-wrapper') === 'windowWeeks') {
				continue;
			}

			$fields = [];

			// Le champ de recherche d'une autocomplétion porte un nom tiré à chaque rendu : il se dit par le
			// champ qu'il remplit.
			foreach($panel->query('.//input[@name] | .//select[@name]', $node) as $input) {
				if($input->hasAttribute('data-autocomplete-field')) {
					$fields[] = 'autocomplete:'.$input->getAttribute('data-autocomplete-field');
				} else if($input->getAttribute('type') === 'radio') {
					$fields[] = $input->getAttribute('name').'='.$input->getAttribute('value');
				} else {
					$fields[] = $input->getAttribute('name');
				}
			}

			$settings[] = array_values(array_unique($fields));

		}

		return $settings;

	}

	private function getPanel(CompositionSpecies $eCompositionSpecies): \DOMXPath {

		return new \DOMXPath($this->getDocument(new CompositionSpeciesUi()->add($eCompositionSpecies)->body));

	}

	private function getDocument(string $html): \DOMDocument {

		// L'analyseur HTML de libxml ne connait pas les balises SVG des vignettes, et le dit en
		// avertissements, que le cadriciel lève.
		$libxmlErrors = libxml_use_internal_errors(TRUE);

		$document = new \DOMDocument();
		$document->loadHTML('<?xml encoding="utf-8"?>'.$html);

		libxml_clear_errors();
		libxml_use_internal_errors($libxmlErrors);

		return $document;

	}

	/**
	 * Les semaines où une taille du plan sert l'espèce `$plantId` du référentiel, sous son espèce nue :
	 * une variante (r76) lit la même espèce et se compte à part.
	 */
	private function getWeeksServed(CompositionPlan $eCompositionPlan, int $plantId): array {

		$eCompositionPlanScreen = CompositionPlanLib::getForScreen($eCompositionPlan['structure'], NULL);

		$weeks = [];

		foreach($eCompositionPlanScreen['cCompositionFormula'] as $eCompositionFormula) {
			foreach($eCompositionFormula['cCompositionWeek'] as $eCompositionWeek) {
				foreach($eCompositionWeek['cCompositionLine'] as $eCompositionLine) {
					if($eCompositionLine['species']['plant']['id'] === $plantId and $eCompositionLine['species']['name'] === NULL) {
						$weeks[] = $eCompositionWeek['week'];
					}
				}
			}
		}

		$weeks = array_values(array_unique($weeks));
		sort($weeks);

		return $weeks;

	}

	/**
	 * L'espèce nue du plan qui lit l'espèce `$plantId`, telle que la page la charge : sa sélection, son
	 * plan et la structure de son plan. `$name` désigne une de ses variantes (r76).
	 */
	private function getSpecies(CompositionPlan $eCompositionPlan, int $plantId, ?string $name = NULL): CompositionSpecies {

		$eCompositionSpecies = CompositionSpecies::model()
			->select(CompositionSpecies::getSelection() + [
				'plan' => CompositionPlan::getSelection(),
				'product' => ['name', 'variety'],
			])
			->wherePlan($eCompositionPlan)
			->wherePlant($plantId)
			->whereName($name)
			->get();

		$eCompositionSpecies['plan']['structure'] = $eCompositionPlan['structure'];

		CompositionSpeciesLib::applySeasonsUnwritten($eCompositionSpecies['plan'], new \Collection([$eCompositionSpecies]));

		return $eCompositionSpecies;

	}

	/**
	 * Un plan écrit par le départ, sur la saison de la communauté et sans climat.
	 */
	private function createStarted(\structure\Structure $eStructure): CompositionPlan {

		$eCompositionPlan = CompositionPlanLib::getForStart($eStructure, self::SEASON);
		$eCompositionPlan['weeks'] = range(10, 40);
		$eCompositionPlan['reference'] = CompositionPlan::COMMUNITY;
		$eCompositionPlan['climate'] = NULL;
		$eCompositionPlan['cCompositionFormula']->first()->merge(['name' => 'Le panier', 'price' => 18.0, 'baskets' => 10]);

		CompositionPlanLib::create($eCompositionPlan);

		$eCompositionPlanWritten = CompositionPlanLib::getByStructure($eStructure);
		$eCompositionPlanWritten['structure'] = $eStructure;

		return $eCompositionPlanWritten;

	}

	/**
	 * La première structure active qui n'a pas de plan et qu'un administrateur en activité gère : le
	 * départ y écrit le plan du test.
	 */
	private function getStructureWithoutPlan(): \structure\Structure {

		$eMember = \structure\Member::model()
			->select(['structure' => new \Sql('m1.structure', 'int')])
			->join(\structure\Role::model(), 'm1.role = m2.id')
			->join(CompositionPlan::model(), 'm1.structure = m3.structure', 'LEFT')
			->join(\structure\Structure::model(), 'm1.structure = m4.id')
			->where('m2.admin', TRUE)
			->where('m1.status', \structure\Member::IN)
			->where('m1.structureStatus', \structure\Member::ACTIVE)
			->where('m4.status', \structure\Structure::ACTIVE)
			->where('m3.id IS NULL')
			->sort(['m1.structure' => SORT_ASC])
			->get();

		return \structure\StructureLib::getById($eMember['structure']);

	}

}
?>
