<?php
namespace toolbox;

/**
 * Le panneau « Régler une espèce » (lot r22e, planche r22, question 1), qui remplace le menu de
 * l'espèce : ce qu'il montre, ses tuiles d'abord puis le formulaire, et ce que son unique
 * « Enregistrer » écrit.
 *
 * - le rendu : les tuiles et leurs libellés sans le nom de l'espèce, qui est dans l'en-tête ; aucune
 *   médiane nationale ni mercuriale générale (verdict) ; la fréquence dite par son rythme,
 *   « Selon la saison » en tête ; la conservation en lecture, avec le lien vers la fiche de la copie
 *   de l'espèce quand elle existe ; « Enregistrer » et « Retirer de mes cultures » dans le corps, sous
 *   le dernier champ, et l'origine réémise ; une espèce retirée, sans
 *   fréquence, sans conservation et sans retrait ;
 * - l'enregistrement, par les mêmes appels que la page (`compositionSpecies.p.php`, `doUpdate`) : le
 *   panneau poste tous ses champs, et seul ce qui a changé s'écrit (`getPropertiesChanged()`). Rien
 *   de changé n'écrit rien et ne recompose rien ; un prix seul se reporte sur les lignes sans
 *   recomposer ; un prix et une fréquence recomposent, une seule fois ; une unité changée efface ce
 *   que le panneau laisse vide et garde ce qu'on y retape, dans la nouvelle unité
 *   (`getPropertiesPanel()`) ;
 * - la saison aux poignées (r61), en tête comme à l'ajout : renvoyée telle qu'elle s'est ouverte,
 *   elle ne change ni de semaines ni d'état ; raccourcie, elle passe à `CUSTOM` et refait les
 *   semaines qu'elle quitte ; allongée, elle ne refait rien, comme le geste sur la grille
 *   (`CompositionSpeciesLib::updatePanel()`).
 *
 * Le geste s'éprouve sur un plan écrit par le départ sur la saison 2099, qu'aucune structure
 * n'emploie, comme `CompositionUnitTest`.
 *
 * Isolation : la transaction de `TransactionTest`, annulée après chaque méthode.
 *
 * Lancement : dev/test package=toolbox file=CompositionSpeciesPanel
 */
class CompositionSpeciesPanelTest extends \dev\TransactionTest {

	const SEASON = 2099;

	const PRICE = 16.0;

	private \user\User $eUserInitial;

	/**
	 * Les semaines que la saison du dernier envoi a refaites (`CompositionSpeciesLib::updatePanel()`).
	 */
	private array $weeksSeasoned = [];

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
	 * Le panneau d'une espèce cultivée, au kilo par la communauté et pesée à la pièce : ses tuiles dans
	 * l'ordre, des libellés sans le nom de l'espèce, aucune médiane, les fréquences dites par leur
	 * rythme, la conservation et son lien, le retrait au pied. Retirée, l'espèce ne dit plus ni sa
	 * fréquence ni sa conservation, et ne se retire pas une seconde fois.
	 */
	/**
	 * Le panneau ouvert en page complète, par son adresse, n'a pas l'écran des paniers derrière lui
	 * pour avoir chargé composition.js : c'est son UI qui le demande, sinon la confirmation avant
	 * qu'un changement d'unité remplace les valeurs posées ne joue pas (lot r22g).
	 */
	public function testScript(): void {

		\Asset::getJs();

		new \toolbox\CompositionSpeciesUi();

		$this->assertTrue(array_key_exists(\Asset::getJsPath('toolbox', 'composition.js'), \Asset::getJs()), 'le panneau charge composition.js');

	}

	public function testRender(): void {

		[$eCompositionPlan, $speciesId] = $this->createPlan();

		if($eCompositionPlan->empty()) {
			return;
		}

		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesId);
		$this->loadPanel($eCompositionSpecies);

		$eCompositionSpecies['storage']['plant'] = new \plant\Plant(['id' => 123]);

		$html = $this->getPanelHtml($eCompositionSpecies);
		$xpath = new \DOMXPath($this->getDocument($html));

		// La saison se règle aux poignées, sur le rang de l'ajout (r61), et l'espèce porte sa saison dans
		// les champs qu'il poste.
		$this->assertEquals(1, $xpath->query('//div[@data-wrapper="windowWeeks"]//div[@data-season-field]')->length, 'le rang de saison de l\'ajout, dans le groupe que l\'erreur de la saison vise');

		$windowWeeksPosted = [];

		foreach($xpath->query('//div[@data-season-field]//input[@type="hidden"]') as $input) {
			$windowWeeksPosted[] = [$input->getAttribute('name'), (int)$input->getAttribute('value')];
		}

		$windowWeeksExpected = [];

		foreach($eCompositionSpecies['windowWeeks'] as $position => [$start, $stop]) {
			$windowWeeksExpected[] = ['windowWeeks['.$position.'][0]', $start];
			$windowWeeksExpected[] = ['windowWeeks['.$position.'][1]', $stop];
		}

		$this->assertEquals($windowWeeksExpected, $windowWeeksPosted, 'les champs postent la saison de l\'espèce');

		// Le prix, le pas, le minimum et la quantité par défaut suivent l'unité cochée (`composition.js`) :
		// chaque unité porte ses mots et son pas, chaque groupe se marque, chaque mot qui nomme l'unité
		// aussi, y compris le prix figé d'un produit lié, qu'un produit choisi dans une autre unité
		// remplit à l'ajout (r60).
		$this->assertEquals(['product', 'price', 'step', 'minimum', 'quantity'], array_map(fn(\DOMNode $node) => $node->getAttribute('data-follow'), iterator_to_array($xpath->query('//*[@data-follow]'))), 'les groupes qui suivent l\'unité');
		$this->assertEquals(['singular', 'singular', 'by', 'plural', 'plural', 'plural'], array_map(fn(\DOMNode $node) => $node->getAttribute('data-follow-text'), iterator_to_array($xpath->query('//*[@data-follow-text]'))), 'les mots qui la nomment');

		$one = $xpath->query('//input[@name="unit"][@value="'.$this->getUnitId('one').'"]')->item(0);

		$this->assertEquals(['pièce', 'pièces', '1', '0'], [$one->getAttribute('data-singular'), $one->getAttribute('data-plural'), $one->getAttribute('data-step'), $one->getAttribute('data-divisible')], 'la pièce, ses mots et son pas entier');
		$this->assertEquals('€ / pièce', str_replace("\u{a0}", ' ', strip_tags($one->getAttribute('data-by'))), 'et son suffixe de prix');

		$kilo = $xpath->query('//input[@name="unit"][@value=""]')->item(0);

		$this->assertEquals('1', $kilo->getAttribute('data-divisible'), 'le kilo se coupe');

		// Le minimum en attente suit l'unité cochée (lot r25) : celui de la communauté dans son unité,
		// un pas dans une autre.
		$unitShared = CompositionLib::getSource()['units'][$eCompositionSpecies['plant']['id']];
		$minimumShared = CompositionLib::getMinimumCommunity($unitShared['minimum'], $unitShared['step']);

		$this->assertTrue($minimumShared > $unitShared['step'], 'la communauté donne à l\'espèce un minimum de plus d\'un pas');
		$this->assertEquals(\util\TextUi::number($minimumShared, NULL), $kilo->getAttribute('data-minimum'), 'le kilo, unité de la communauté, propose son minimum');
		$this->assertEquals('1', $one->getAttribute('data-minimum'), 'la pièce, une pièce');

		$this->assertEquals(NULL, $eCompositionSpecies['minimum'], 'l\'espèce n\'a pas de minimum réglé');

		$this->assertEquals(1, $xpath->query('//a[@href="/plant/plant:update?id=123&origin=composition"]')->length, 'le lien vers la fiche de la copie de l\'espèce');
		$this->assertEquals(0, $xpath->query('//input[@name="storage"]')->length, 'la conservation ne se règle pas ici');

		$exclude = $xpath->query('//a[@data-ajax="/toolbox/compositionSpecies:doExclude"]')->item(0);

		$this->assertTrue($exclude !== NULL and $exclude->hasAttribute('data-confirm'), 'le retrait, avec sa confirmation');
		$this->assertEquals('', $xpath->query('//input[@name="origin"]')->item(0)->getAttribute('value'), 'depuis la grille, aucune origine');

		// Ouvert depuis l'onglet « Espèces » (r29, #84, r48a), le panneau réémet son origine, à
		// l'enregistrement comme au retrait : la réponse rend le rang de l'espèce dans son tableau.
		$xpathSpecies = new \DOMXPath($this->getDocument($this->getPanelHtml($eCompositionSpecies, CompositionPlan::TAB_SPECIES)));

		$this->assertEquals(1, $xpathSpecies->query('//input[@type="hidden"][@name="origin"][@value="species"]')->length, 'l\'enregistrement porte l\'origine');
		$this->assertEquals('species', $xpathSpecies->query('//a[@data-ajax="/toolbox/compositionSpecies:doExclude"]')->item(0)->getAttribute('post-origin'), 'le retrait aussi');

		$eCompositionSpecies['status'] = CompositionSpecies::EXCLUDED;

		$xpath = new \DOMXPath($this->getDocument($this->getPanelHtml($eCompositionSpecies)));

		$this->assertEquals(0, $xpath->query('//input[@name="frequency"]')->length, 'une espèce retirée ne règle pas sa fréquence');
		$this->assertEquals(0, $xpath->query('//div[@data-season-field]')->length, 'ni sa saison');

	}

	/**
	 * Un prix se dit au centime, quand la cascade en porte quatre décimales. Les repères du prix d'un
	 * panier se disent d'après les paniers partagés, sous le prix d'une taille qu'on ajoute comme
	 * sous celui de la tête, sans médiane nationale (verdict sur la planche r22).
	 */
	public function testPriceText(): void {

		$prices = str_replace("\u{a0}", ' ', CompositionUi::getPrices(['low' => 15, 'median' => 20, 'high' => 25]));

		$this->assertEquals('la moitié des paniers partagés coûtent entre 15 € et 25 €', $prices, 'les repères du prix d\'un panier');

	}

	/**
	 * Enregistrer le panneau tel qu'il s'ouvre n'écrit rien et ne recompose rien : il poste tous ses
	 * champs, vides pour ce que la cascade décide.
	 */
	public function testSaveUnchanged(): void {

		[$eCompositionPlan, $speciesId] = $this->createPlan();

		if($eCompositionPlan->empty()) {
			return;
		}

		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesId);
		$eCompositionSpecies['price'] = 14.0;
		$eCompositionSpecies['minimum'] = 0.5;
		CompositionSpeciesLib::update($eCompositionSpecies, ['price', 'minimum']);

		$linesBefore = $this->getLines($eCompositionPlan);

		$saved = $this->save($this->getSpecies($eCompositionPlan, $speciesId), ['unit' => '', 'price' => '14', 'step' => '', 'minimum' => '0.5', 'frequency' => '']);

		$this->assertEquals(['errors' => [], 'changed' => [], 'recomposed' => FALSE], $saved, 'rien de changé');
		$this->assertEquals($linesBefore, $this->getLines($eCompositionPlan), 'les paniers intacts');

	}

	/**
	 * Un prix seul se reporte sur les lignes de l'espèce sans recomposer ; un prix et une fréquence
	 * dans le même envoi s'écrivent tous les deux, et recomposent.
	 */
	public function testSavePriceAndFrequency(): void {

		[$eCompositionPlan, $speciesId] = $this->createPlan();

		if($eCompositionPlan->empty()) {
			return;
		}

		$linesBefore = $this->getLines($eCompositionPlan);

		$saved = $this->save($this->getSpecies($eCompositionPlan, $speciesId), ['unit' => '', 'price' => '21', 'step' => '', 'minimum' => '', 'frequency' => '']);

		$this->assertEquals(['errors' => [], 'changed' => ['price'], 'recomposed' => FALSE], $saved, 'le prix seul');

		$linesAfter = $this->getLines($eCompositionPlan);

		$this->assertEquals(array_column($linesBefore, 'quantity'), array_column($linesAfter, 'quantity'), 'les paniers gardent leurs espèces');

		$prices = array_unique(array_column(array_filter($linesAfter, fn(array $line) => $line['species'] === $speciesId), 'unitPrice'));

		$this->assertEquals([21.0], array_values($prices), 'les lignes de l\'espèce prennent le prix');

		$saved = $this->save($this->getSpecies($eCompositionPlan, $speciesId), ['unit' => '', 'price' => '22', 'step' => '', 'minimum' => '', 'frequency' => CompositionSpecies::BASE]);

		$this->assertEquals(['errors' => [], 'changed' => ['price', 'frequency'], 'recomposed' => TRUE], $saved, 'le prix et la fréquence');

		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesId);

		$this->assertEquals([22.0, CompositionSpecies::BASE], [$eCompositionSpecies['price'], $eCompositionSpecies['frequency']], 'écrits tous les deux');
		$this->assertTrue($this->getLines($eCompositionPlan) !== $linesAfter, 'les paniers recomposés');

	}

	/**
	 * Un prix et une fréquence dans le même envoi ne recomposent le plan qu'une fois : chaque
	 * recomposition efface les lignes des semaines non corrigées et les réécrit
	 * (`CompositionLib::recomposeWeeks()`), et les identifiants consommés comptent donc les lignes
	 * écrites. Une recomposition en écrit autant que le plan en porte ensuite, deux le double. Le
	 * compte se prouve sur la fréquence seule, qui recompose une fois.
	 */
	public function testSaveRecomposesOnce(): void {

		[$eCompositionPlan, $speciesId] = $this->createPlan();

		if($eCompositionPlan->empty()) {
			return;
		}

		$idBefore = $this->getLineIdLast();

		$this->save($this->getSpecies($eCompositionPlan, $speciesId), ['unit' => '', 'price' => '', 'step' => '', 'minimum' => '', 'frequency' => CompositionSpecies::LOW]);

		$this->assertEquals(count($this->getLines($eCompositionPlan)), $this->getLineIdLast() - $idBefore, 'la fréquence seule, une recomposition');

		$idBefore = $this->getLineIdLast();

		$saved = $this->save($this->getSpecies($eCompositionPlan, $speciesId), ['unit' => '', 'price' => '23', 'step' => '', 'minimum' => '', 'frequency' => CompositionSpecies::BASE]);

		$this->assertEquals(['price', 'frequency'], $saved['changed'], 'le prix et la fréquence partent ensemble');
		$this->assertEquals(count($this->getLines($eCompositionPlan)), $this->getLineIdLast() - $idBefore, 'une seule recomposition pour les deux');

	}

	/**
	 * La saison aux poignées (r61). Renvoyée telle qu'elle s'est ouverte, elle ne change rien, ni
	 * l'état que la communauté a écrit. Raccourcie, elle passe à `CUSTOM`, et l'espèce sort des
	 * semaines non corrigées qu'elle quitte, elles seules se refaisant ; avec un prix dans le même
	 * envoi, les deux s'écrivent. Allongée de nouveau, elle reste `CUSTOM` et ne refait rien : les
	 * paniers ne bougent pas, comme sur la grille (`CompositionSpeciesLib::updateSeason()`).
	 */
	public function testSaveSeason(): void {

		[$eCompositionPlan, $speciesId] = $this->createPlan();

		if($eCompositionPlan->empty()) {
			return;
		}

		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesId);
		$windowWeeks = $eCompositionSpecies['windowWeeks'];

		$this->assertEquals(CompositionSpecies::NATURAL, $eCompositionSpecies['status'], 'la saison part de la communauté');

		$saved = $this->save($this->getSpecies($eCompositionPlan, $speciesId), ['unit' => '', 'price' => '', 'step' => '', 'minimum' => '', 'frequency' => '', 'windowWeeks' => array_map(fn(array $window) => array_map('strval', $window), $windowWeeks)]);

		$this->assertEquals(['errors' => [], 'changed' => [], 'recomposed' => FALSE], $saved, 'la saison renvoyée telle quelle, en chaînes comme le navigateur la poste, ne change rien');
		$this->assertEquals([CompositionSpecies::NATURAL, $windowWeeks], $this->getSeason($eCompositionPlan, $speciesId), 'ni son état');

		$weeksServed = $this->getWeeksServed($eCompositionPlan, $speciesId);

		$this->assertTrue(count($weeksServed) >= 2, 'l\'espèce est servie au moins deux semaines, sans quoi raccourcir ne verrait rien');

		$weekLast = $weeksServed[intdiv(count($weeksServed), 2) - 1];
		$weeksKept = array_values(array_filter(CompositionLib::getWindowWeeksList($windowWeeks), fn(int $week) => $week <= $weekLast));
		$windowWeeksShort = CompositionLib::getBands($weeksKept);

		$linesBefore = $this->getLines($eCompositionPlan);

		$saved = $this->save($this->getSpecies($eCompositionPlan, $speciesId), ['unit' => '', 'price' => '19', 'step' => '', 'minimum' => '', 'frequency' => '', 'windowWeeks' => array_map(fn(array $window) => array_map('strval', $window), $windowWeeksShort)]);

		$this->assertEquals(['errors' => [], 'changed' => ['season', 'price'], 'recomposed' => FALSE], $saved, 'la saison raccourcie et le prix partent ensemble');
		$this->assertEquals([CompositionSpecies::CUSTOM, $windowWeeksShort], $this->getSeason($eCompositionPlan, $speciesId), 'la saison raccourcie est réglée à la main');
		$this->assertEquals(19.0, $this->getSpecies($eCompositionPlan, $speciesId)['price'], 'le prix est écrit');
		$this->assertEquals([], array_values(array_filter($this->getWeeksServed($eCompositionPlan, $speciesId), fn(int $week) => $week > $weekLast)), 'l\'espèce sort des semaines quittées');
		$this->assertEquals([], array_values(array_filter($this->weeksSeasoned, fn(int $week) => $week <= $weekLast)), 'seules les semaines quittées se refont');
		$this->assertTrue($this->weeksSeasoned !== [], 'et elles se refont');
		$this->assertTrue($linesBefore !== $this->getLines($eCompositionPlan), 'les paniers ont changé');

		$linesShort = $this->getLines($eCompositionPlan);

		$saved = $this->save($this->getSpecies($eCompositionPlan, $speciesId), ['unit' => '', 'price' => '19', 'step' => '', 'minimum' => '', 'frequency' => '', 'windowWeeks' => array_map(fn(array $window) => array_map('strval', $window), $windowWeeks)]);

		$this->assertEquals(['errors' => [], 'changed' => ['season'], 'recomposed' => FALSE], $saved, 'la saison allongée seule');
		$this->assertEquals([CompositionSpecies::CUSTOM, $windowWeeks], $this->getSeason($eCompositionPlan, $speciesId), 'reste réglée à la main, même rendue à la saison de la communauté');
		$this->assertEquals([], $this->weeksSeasoned, 'allonger ne refait aucune semaine');
		$this->assertEquals($linesShort, $this->getLines($eCompositionPlan), 'et les paniers ne bougent pas');

	}

	/**
	 * Une unité changée se construit avec son poids, et avec le prix, le pas et le minimum que le
	 * panneau a vidés au changement (`composition.js`) : ce qui reste vide s'efface, ce qu'on retape
	 * se garde, dans la nouvelle unité, et s'y contrôle (arbitrage du chef de projet sur r22e). Un
	 * prix retapé à l'identique se garde aussi : il se dit dans une autre unité. Le pas ne se construit
	 * que vers une unité qui se coupe.
	 */
	public function testSaveUnit(): void {

		[$eCompositionPlan, $speciesId] = $this->createPlan();

		if($eCompositionPlan->empty()) {
			return;
		}

		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesId);
		$eCompositionSpecies['price'] = 14.0;
		$eCompositionSpecies['minimum'] = 0.5;
		CompositionSpeciesLib::update($eCompositionSpecies, ['price', 'minimum']);

		$oneId = $this->getUnitId('one');

		$this->assertEquals(['unit', 'weight', 'price', 'minimum', 'quantity', 'product', 'frequency'], $this->getPropertiesPanel($eCompositionPlan, $speciesId, (string)$oneId), 'vers la pièce, sans pas');

		// Le prix laissé vide s'efface, le minimum retapé en pièces se garde.
		$saved = $this->save($this->getSpecies($eCompositionPlan, $speciesId), ['unit' => (string)$oneId, 'weight' => '0.1', 'price' => '', 'minimum' => '2', 'frequency' => '']);

		$this->assertEquals(['errors' => [], 'changed' => ['unit', 'weight', 'price', 'minimum', 'quantity', 'product', 'frequency'], 'recomposed' => TRUE], $saved, 'à la pièce');
		$this->assertEquals([$oneId, 0.1, NULL, NULL, 2.0], $this->getWritten($eCompositionPlan, $speciesId), 'le prix au kilo effacé, le minimum retapé gardé');

		$this->assertEquals(['weight', 'price', 'minimum', 'quantity', 'product', 'frequency'], $this->getPropertiesPanel($eCompositionPlan, $speciesId, (string)$oneId), 'l\'unité gardée, à la pièce : ni unité ni pas');
		$this->assertEquals(['unit', 'weight', 'price', 'step', 'minimum', 'quantity', 'product', 'frequency'], $this->getPropertiesPanel($eCompositionPlan, $speciesId, ''), 'vers le kilo, avec son pas');

		// Retour au kilo : le pas retapé se contrôle au kilo, où 0,5 se coupe.
		$saved = $this->save($this->getSpecies($eCompositionPlan, $speciesId), ['unit' => '', 'price' => '9', 'step' => '0.5', 'minimum' => '', 'frequency' => '']);

		$this->assertEquals([], $saved['errors'], 'le pas retapé au kilo admis');
		$this->assertEquals([NULL, NULL, 9.0, 0.5, NULL], $this->getWritten($eCompositionPlan, $speciesId), 'au kilo, le prix et le pas retapés, le minimum en pièces effacé');

		// Le même prix retapé vers la pièce se garde : 9 € la pièce n'est pas 9 € le kilo.
		$saved = $this->save($this->getSpecies($eCompositionPlan, $speciesId), ['unit' => (string)$oneId, 'weight' => '0.1', 'price' => '9', 'minimum' => '', 'frequency' => '']);

		$this->assertEquals([], $saved['errors'], 'à la pièce de nouveau');
		$this->assertEquals([$oneId, 0.1, 9.0, NULL, NULL], $this->getWritten($eCompositionPlan, $speciesId), 'le prix retapé à l\'identique gardé, le pas au kilo effacé');

		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesId);
		$eCompositionSpecies['status'] = CompositionSpecies::EXCLUDED;

		$this->assertEquals(['weight', 'price', 'minimum', 'quantity', 'product'], CompositionSpeciesLib::getPropertiesPanel($eCompositionSpecies), 'une espèce retirée ne règle pas sa fréquence');

	}

	/**
	 * Ce que le panneau construit quand l'unité `$unit` est postée.
	 */
	private function getPropertiesPanel(CompositionPlan $eCompositionPlan, int $speciesId, string $unit): array {

		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesId);
		$eCompositionSpecies->build(['unit'], ['unit' => $unit]);

		return CompositionSpeciesLib::getPropertiesPanel($eCompositionSpecies);

	}

	/**
	 * L'unité, le poids, le prix, le pas et le minimum écrits sur l'espèce.
	 */
	private function getWritten(CompositionPlan $eCompositionPlan, int $speciesId): array {

		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesId);

		return [
			$eCompositionSpecies['unit']->empty() ? NULL : $eCompositionSpecies['unit']['id'],
			$eCompositionSpecies['weight'],
			$eCompositionSpecies['price'],
			$eCompositionSpecies['step'],
			$eCompositionSpecies['minimum'],
		];

	}

	/**
	 * Ce qui recompose : l'unité, la fréquence, et le poids d'une unité choisie, qui convertit la
	 * quantité ; pas le prix, le pas ni le minimum, ni le poids sous l'unité de la communauté.
	 */
	public function testIsRecomposing(): void {

		$eCompositionSpecies = new CompositionSpecies(['unit' => new \product\Unit()]);

		$this->assertFalse(CompositionSpeciesLib::isRecomposing($eCompositionSpecies, ['price', 'step', 'minimum', 'weight']), 'sous l\'unité de la communauté');
		$this->assertTrue(CompositionSpeciesLib::isRecomposing($eCompositionSpecies, ['unit']), 'l\'unité');
		$this->assertTrue(CompositionSpeciesLib::isRecomposing($eCompositionSpecies, ['price', 'frequency']), 'la fréquence');

		$eCompositionSpecies['unit'] = new \product\Unit(['id' => 1]);

		$this->assertTrue(CompositionSpeciesLib::isRecomposing($eCompositionSpecies, ['weight']), 'le poids d\'une unité choisie');
		$this->assertFalse(CompositionSpeciesLib::isRecomposing($eCompositionSpecies, ['price']), 'le prix d\'une unité choisie');

	}

	/**
	 * L'enregistrement de la page (`compositionSpecies.p.php`, `doUpdate`), sans la réponse : l'unité
	 * construite seule, puis ce qu'elle laisse construire, la saison à part (r61), puis l'écriture de
	 * ce qui a changé. Un envoi qui ne porte pas de saison la laisse telle quelle, comme
	 * `POST('windowWeeks', '?array')`.
	 */
	private function save(CompositionSpecies $eCompositionSpecies, array $post): array {

		$fw = new \FailWatch();

		CompositionSpeciesLib::loadPriced($eCompositionSpecies);

		$eCompositionSpecies->build(['unit'], $post);

		CompositionSpeciesLib::loadProducts($eCompositionSpecies);

		$properties = CompositionSpeciesLib::getPropertiesPanel($eCompositionSpecies);

		$eCompositionSpecies->build($properties, $post);

		$cultivated = ($eCompositionSpecies['status'] !== CompositionSpecies::EXCLUDED);

		if($cultivated) {
			$eCompositionSpecies->build(['status', 'windowWeeks'], CompositionSpeciesLib::getSeasonUpdated($eCompositionSpecies, $post['windowWeeks'] ?? NULL));
		}

		$errors = array_column($fw->get(), 1);

		if($errors !== []) {
			return ['errors' => $errors, 'changed' => [], 'recomposed' => FALSE];
		}

		$propertiesChanged = CompositionSpeciesLib::getPropertiesChanged($eCompositionSpecies, array_unique(array_merge(['unit'], $properties)));

		$seasoned = ($cultivated and CompositionSpeciesLib::getPropertiesChanged($eCompositionSpecies, ['status', 'windowWeeks']) !== []);

		$this->weeksSeasoned = CompositionSpeciesLib::updatePanel($eCompositionSpecies, $propertiesChanged, $seasoned);

		return ['errors' => [], 'changed' => $seasoned ? array_merge(['season'], $propertiesChanged) : $propertiesChanged, 'recomposed' => CompositionSpeciesLib::isRecomposing($eCompositionSpecies, $propertiesChanged)];

	}

	/**
	 * L'espèce chargée comme la page la charge pour son panneau.
	 */
	private function loadPanel(CompositionSpecies $eCompositionSpecies): void {

		CompositionSpeciesLib::loadPriced($eCompositionSpecies);
		CompositionSpeciesLib::loadUnits($eCompositionSpecies);
		CompositionSpeciesLib::loadProducts($eCompositionSpecies);
		CompositionSpeciesLib::loadSeasonAxis($eCompositionSpecies['plan']);
		CompositionSpeciesLib::loadFrequency($eCompositionSpecies, NULL);

	}

	private function getPanelHtml(CompositionSpecies $eCompositionSpecies, ?string $origin = NULL): string {

		$panel = new CompositionSpeciesUi()->update($eCompositionSpecies, NULL, $origin);

		return $panel->body.$panel->footer;

	}

	private function getLineIdLast(): int {

		return CompositionLine::model()
			->select('id')
			->sort(['id' => SORT_DESC])
			->get()['id'];

	}

	private function getUnitId(string $fqn): int {
		return array_column(CompositionLib::getSource()['offered'], 'unit', 'fqn')[$fqn];
	}

	private function getSpecies(CompositionPlan $eCompositionPlan, int $speciesId): CompositionSpecies {

		$eCompositionSpecies = CompositionSpecies::model()
			->select(CompositionSpecies::getSelection() + [
				'plan' => CompositionPlan::getSelection(),
				'product' => ['name', 'variety'],
			])
			->wherePlan($eCompositionPlan)
			->whereId($speciesId)
			->get();

		$eCompositionSpecies['plan']['structure'] = $eCompositionPlan['structure'];

		return $eCompositionSpecies;

	}

	private function getSeason(CompositionPlan $eCompositionPlan, int $speciesId): array {

		$eCompositionSpecies = $this->getSpecies($eCompositionPlan, $speciesId);

		return [$eCompositionSpecies['status'], $eCompositionSpecies['windowWeeks']];

	}

	/**
	 * Les semaines où une taille au moins sert l'espèce, dans l'ordre.
	 */
	private function getWeeksServed(CompositionPlan $eCompositionPlan, int $speciesId): array {

		$weeks = [];

		foreach(CompositionFormulaLib::getByPlan($eCompositionPlan) as $eCompositionFormula) {
			foreach(CompositionWeekLib::getByFormulaWithLines($eCompositionFormula, CompositionSpeciesLib::getByPlan($eCompositionPlan)) as $eCompositionWeek) {
				foreach($eCompositionWeek['cCompositionLine'] as $eCompositionLine) {
					if($eCompositionLine['species']['id'] === $speciesId) {
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
	 * Les lignes de toutes les tailles du plan, dans l'ordre des tailles et des semaines.
	 */
	private function getLines(CompositionPlan $eCompositionPlan): array {

		$lines = [];

		foreach(CompositionFormulaLib::getByPlan($eCompositionPlan) as $eCompositionFormula) {

			foreach(CompositionWeekLib::getByFormulaWithLines($eCompositionFormula, CompositionSpeciesLib::getByPlan($eCompositionPlan)) as $eCompositionWeek) {

				foreach($eCompositionWeek['cCompositionLine'] as $eCompositionLine) {

					$lines[] = [
						'week' => $eCompositionWeek['id'],
						'species' => $eCompositionLine['species']['id'],
						'quantity' => $eCompositionLine['quantity'],
						'unitPrice' => $eCompositionLine['unitPrice'],
					];

				}

			}

		}

		return $lines;

	}

	/**
	 * Un plan écrit par le départ sur une structure qui n'en a pas, en 2099, toute l'année, toutes
	 * les espèces proposables cochées, et l'espèce qu'on y règle : la première que la communauté sert
	 * au kilo, qui a un poids à la pièce et que la première taille sert. La structure est celle d'un
	 * administrateur, que le test connecte : le panneau ne se rend qu'à qui gère la structure
	 * (`assertUpdate`). Vide, avec un échec qui le dit, quand la base de dev n'a personne à connecter
	 * ou pas d'espèce qui convienne.
	 */
	private function createPlan(): array {

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
			return [new CompositionPlan(), 0];
		}

		\user\ConnectionLib::setOnline($eMember['user']);

		$eStructure = \structure\StructureLib::getById($eMember['structure']['id']);

		$eCompositionPlan = CompositionPlanLib::getForStart($eStructure, self::SEASON);
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

		// La communauté se parcourt dans l'ordre de ses espèces, et l'espèce retenue est l'espèce nue du
		// plan qui la lit (`speciesBare`).
		$source = CompositionLib::getSource();
		$speciesBare = CompositionLib::getSourceByPlan($eCompositionPlan)['speciesBare'];
		$served = array_unique(array_column($this->getLines($eCompositionPlan), 'species'));

		foreach($source['units'] as $plantId => $unit) {

			if(
				$unit['fqn'] === 'kg' and
				array_key_exists('one', $source['weights'][$plantId] ?? []) and
				array_key_exists($plantId, $speciesBare) and
				in_array($speciesBare[$plantId], $served, TRUE)
			) {
				return [$eCompositionPlan, $speciesBare[$plantId]];
			}

		}

		$this->assertFail('le relevé n\'a pas d\'espèce au kilo pesée à la pièce que les paniers servent');

		return [new CompositionPlan(), 0];

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

}
?>
