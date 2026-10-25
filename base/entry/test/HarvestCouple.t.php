<?php
namespace entry;

/**
 * La variété et le calibre qu'une récolte lit après son espèce (`HarvestIntentionLib::readQualifiers()`), tapés ou
 * posés par une puce : ils désignent l'intervention du couple, où la récolte s'écrit, au menu comme dans la case
 * de la série. La case d'une part fixe le sien.
 */
class HarvestCoupleTest extends EntryFixtureTest {

	/**
	 * Une variété au nom choisi, que la phrase redit : celles du décor portent un `uniqid()`.
	 */
	private function createVarietyNamed(string $name, \series\Cultivation $eCultivation): \plant\Variety {

		$eVariety = new \plant\Variety([
			'name' => $name.' '.$this->getSuffix(),
			'structure' => $this->eStructure,
			'plant' => $eCultivation['plant'],
			'fqn' => NULL,
			'reference' => new \plant\Variety(),
			'supplierSeed' => new \work\Supplier(),
			'supplierPlant' => new \work\Supplier(),
		]);

		// Sans `build()` : il vérifierait le nom et les fournisseurs postés par le formulaire de variété, alors que
		// le décor fixe un nom que la phrase redit, chiffre compris.
		\plant\VarietyLib::create($eVariety);

		$this->createSlice($eCultivation, $eVariety);

		return $eVariety;

	}

	private function createSizeNamed(string $name, \plant\Plant $ePlant): \plant\Size {

		$eSize = new \plant\Size([
			'name' => $name.' '.$this->getSuffix(),
			'plant' => $ePlant,
			'structure' => $this->eStructure,
			'comment' => NULL,
			'yield' => TRUE,
		]);

		// Sans `build()` : il vérifierait le nom et le commentaire postés par le formulaire des calibres, alors que
		// le décor fixe un nom que la phrase redit, chiffre compris.
		\plant\SizeLib::create($eSize);

		return $eSize;

	}

	/**
	 * Des lettres seules, que le champ ne coupe pas, pour qu'aucun nom du décor n'en commence un autre.
	 */
	private function getSuffix(): string {
		return substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 8);
	}

	/**
	 * La case comme la page la poste ; `$part` pour celle d'une part, dont le couple est fixé même vide.
	 */
	private function getCell(\series\Cultivation $eCultivation, \plant\Variety $eVariety = new \plant\Variety(), \plant\Size $eSize = new \plant\Size(), bool $part = FALSE): array {

		return EntryLib::getCell($this->eStructure, [
			'cultivation' => (string)$eCultivation['id'],
			'date' => \Clock::date(),
			'variety' => $eVariety->empty() ? '' : (string)$eVariety['id'],
			'harvestSize' => $eSize->empty() ? '' : (string)$eSize['id'],
		] + ($part ? ['part' => '1'] : []));

	}

	private function getTasks(\series\Cultivation $eCultivation): \Collection {

		return \series\Task::model()
			->select(['harvest', 'harvestUnit', 'variety', 'harvestSize'])
			->whereCultivation($eCultivation)
			->whereAction($this->getAction(ACTION_RECOLTE))
			->getCollection();

	}

	public function testVarietyAlone(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$eVariety = $this->createVarietyNamed('Cœur de bœuf', $eCultivation);
		$this->createVarietyNamed('Noire', $eCultivation);

		$reading = $this->read($this->getText($eVariety['name'].' 12 kg '));

		$this->assertEquals(EntryLib::WRITE, $reading['outcome'], 'la variété dite s\'écrit');
		$this->assertEquals(['variety'], $reading['couple']['said']);
		$this->assertEquals($eVariety['id'], $reading['confirmation']['eVariety']['id']);
		$this->assertTrue($reading['confirmation']['eSize']->empty(), 'aucun calibre n\'est dit');
		$this->assertEquals(12.0, $reading['confirmation']['quantity']);

		EntryLib::write($this->eStructure, $reading);

		$cTask = $this->getTasks($eCultivation);

		$this->assertEquals(1, $cTask->count());
		$this->assertEquals($eVariety['id'], $cTask->first()['variety']['id'], 'la récolte va sur l\'intervention de la variété');
		$this->assertTrue($cTask->first()['harvestSize']->empty());

	}

	public function testSizeAlone(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$eSize = $this->createSizeNamed('Gros', $eCultivation['plant']);

		$reading = $this->read($this->getText('12 kg '.$eSize['name'].' '));

		$this->assertEquals(EntryLib::WRITE, $reading['outcome'], 'le calibre dit après la quantité s\'écrit aussi');
		$this->assertEquals(['harvestSize'], $reading['couple']['said']);
		$this->assertTrue($reading['confirmation']['eVariety']->empty());
		$this->assertEquals($eSize['id'], $reading['confirmation']['eSize']['id']);

		EntryLib::write($this->eStructure, $reading);

		$this->assertEquals($eSize['id'], $this->getTasks($eCultivation)->first()['harvestSize']['id']);

	}

	/**
	 * Le couple entier, des noms qui portent un nombre : ils se lisent avant la quantité, qui prendrait leur
	 * chiffre. Le même couple une seconde fois complète son intervention, dont la confirmation dit le total.
	 */
	public function testCouple(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$eVariety = $this->createVarietyNamed('Détroit 2', $eCultivation);
		$eSize = $this->createSizeNamed('Godet 8', $eCultivation['plant']);

		$reading = $this->read($this->getText($eVariety['name'].' '.$eSize['name'].' 12 kg '));

		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);
		$this->assertEquals(12.0, $reading['confirmation']['quantity'], 'les chiffres des noms ne font pas la quantité');
		$this->assertEquals($eVariety['id'], $reading['confirmation']['eVariety']['id']);
		$this->assertEquals($eSize['id'], $reading['confirmation']['eSize']['id']);
		$this->assertTrue($reading['confirmation']['created']);

		EntryLib::write($this->eStructure, $reading);

		$reading = $this->read($this->getText('8 kg '.$eVariety['name'].' '.$eSize['name'].' '));

		$this->assertFalse($reading['confirmation']['created'], 'la seconde saisie complète l\'intervention du couple');
		$this->assertEquals(20.0, $reading['confirmation']['today'], 'le total est celui du couple');

		EntryLib::write($this->eStructure, $reading);

		$cTask = $this->getTasks($eCultivation);

		$this->assertEquals(1, $cTask->count());
		$this->assertEquals(20.0, (float)$cTask->first()['harvest']);

	}

	/**
	 * Une série nommée par la saison dite, absente des objets du jour : la phrase se relit une fois l'objet
	 * nommé, et le nom à chiffre de la variété garde son nombre, que la quantité ne prend pas.
	 */
	public function testCoupleOfSeasonSaid(): void {

		$this->setClockInSeason();

		$eCultivation = $this->createCultivationPreviousSeason('Série de la saison d\'avant');
		$eVariety = $this->createVarietyNamed('Détroit 2', $eCultivation);

		$reading = $this->read($this->getText($eVariety['name'].' 12 kg '.($this->getSeason() - 1).' '));

		$this->assertEquals($eCultivation['id'], $reading['cultivation']['id'], 'la saison dite nomme la série');
		$this->assertEquals(['variety'], $reading['couple']['said']);
		$this->assertEquals($eVariety['id'], $reading['confirmation']['eVariety']['id']);
		$this->assertEquals(12.0, $reading['confirmation']['quantity'], 'le chiffre du nom ne fait pas la quantité');

	}

	/**
	 * « Non spécifiée », le libellé de la puce, dit la part sans variété : la famille est dite, la récolte va sur
	 * l'intervention sans couple, et le total du jour est le sien, sans les autres parts.
	 */
	public function testUnspecified(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$eVariety = $this->createVarietyNamed('Noire', $eCultivation);
		$this->createSizeNamed('Gros', $eCultivation['plant']);

		$reading = $this->read($this->getText($eVariety['name'].' 5 kg '));

		EntryLib::write($this->eStructure, $reading);

		$reading = $this->read($this->getText('Non spécifiée 12 kg '));

		$this->assertEquals(12.0, $reading['confirmation']['today'], 'le total est celui de l\'intervention sans couple');

		EntryLib::write($this->eStructure, $reading);

		$reading = $this->read($this->getText('3 kg '));

		$this->assertEquals(20.0, $reading['confirmation']['today'], 'sans couple dit, le menu dit le total de la culture');

		$reading = $this->read($this->getText('Non spécifiée Non spécifié 12 kg '));

		$this->assertEquals(EntryLib::WRITE, $reading['outcome']);
		$this->assertEquals(['variety', 'harvestSize'], $reading['couple']['said']);
		$this->assertTrue($reading['confirmation']['eVariety']->empty());
		$this->assertTrue($reading['confirmation']['eSize']->empty());
		$this->assertEquals(HarvestIntentionLib::UNSPECIFIED, array_map(fn(array $chip) => $chip['selected'], $reading['couple']['chips']), 'les deux familles dites restent, « Non spécifiée » retenue');

	}

	/**
	 * Une variété que la série n'a pas, ou d'une autre espèce, et un calibre d'une autre espèce ne se lisent pas :
	 * leurs mots restent, et la phrase est refusée.
	 */
	public function testUnknown(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$this->createVarietyNamed('Noire', $eCultivation);

		$ePlantOther = $this->createPlant('Autre');
		$eSizeOther = $this->createSizeNamed('Gros', $ePlantOther);

		foreach([
			'une variété inconnue' => 'Blanche '.$this->getSuffix(),
			'un calibre d\'une autre espèce' => $eSizeOther['name'],
		] as $case => $name) {

			$reading = $this->read($this->getText($name.' 12 kg '));

			$this->assertEquals(EntryLib::REFUSE, $reading['outcome'], $case.' est refusé');
			$this->assertEquals('unclaimed', $reading['refusal']['reason'] ?? NULL, $case.' reste un mot que personne ne lit');

		}

	}

	/**
	 * La variété d'une autre série de l'espèce se lit, puis se refuse sur la série choisie, qui ne la cultive pas.
	 */
	public function testVarietyOfAnotherSeries(): void {

		$eCultivationA = $this->createCultivationInPhase(TRUE, 'Série du décor A');
		$eCultivationB = $this->createCultivationInPhase(TRUE, 'Série du décor B');
		$eVariety = $this->createVarietyNamed('Noire', $eCultivationA);

		$reading = $this->read($this->getText($eVariety['name'].' 12 kg '), ['cultivation' => $eCultivationB['id']]);

		$this->assertEquals(EntryLib::REFUSE, $reading['outcome']);
		$this->assertEquals('variety', $reading['refusal']['reason'] ?? NULL, 'la série B ne cultive pas la variété');

		$reading = $this->read($this->getText($eVariety['name'].' 12 kg '), ['cultivation' => $eCultivationA['id']]);

		$this->assertEquals(EntryLib::WRITE, $reading['outcome'], 'la série A la cultive');

	}

	/**
	 * La case d'une part fixe son couple : la phrase peut le redire, pas en dire un autre. La case de la série
	 * prend celui que la phrase dit.
	 */
	public function testCell(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$eVariety = $this->createVarietyNamed('Noire', $eCultivation);
		$eVarietyOther = $this->createVarietyNamed('Blanche', $eCultivation);

		$cell = $this->getCell($eCultivation, $eVariety, part: TRUE);

		$reading = $this->read($eVarietyOther['name'].' 12 kg ', ['cell' => $cell]);

		$this->assertEquals('cellCouple', $reading['refusal']['reason'] ?? NULL, 'une autre variété que celle de la part est refusée');

		$reading = $this->read('Non spécifiée 12 kg ', ['cell' => $cell]);

		$this->assertEquals('cellCouple', $reading['refusal']['reason'] ?? NULL, '« Non spécifiée » contredit la part');

		$reading = $this->read($eVariety['name'].' 12 kg ', ['cell' => $cell]);

		$this->assertEquals(EntryLib::WRITE, $reading['outcome'], 'la variété de la part se redit');
		$this->assertEquals([], $reading['couple']['chips'], 'la case d\'une part ne propose pas de puces');

		$reading = $this->read($eVarietyOther['name'].' 12 kg ', ['cell' => $this->getCell($eCultivation)]);

		$this->assertEquals(EntryLib::WRITE, $reading['outcome'], 'la case de la série prend la variété dite');
		$this->assertTrue($reading['confirmation']['cell'] !== NULL, 'la ligne qui écrit est celle de la case');

		EntryLib::write($this->eStructure, $reading);

		$this->assertEquals($eVarietyOther['id'], $this->getTasks($eCultivation)->first()['variety']['id']);

	}

	/**
	 * La part « Non spécifiée » poste le même couple vide que la case de la série : son drapeau seul la fixe. Une
	 * variété dite la contredit, « 12 kg » écrit sur l'intervention sans couple, et la case de la série, sans
	 * drapeau, reste libre de prendre la variété dite.
	 */
	public function testCellUnspecified(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$eVariety = $this->createVarietyNamed('Gold Rush', $eCultivation);

		$cellPart = $this->getCell($eCultivation, part: TRUE);

		$this->assertTrue($cellPart['part']);

		$reading = $this->read($eVariety['name'].' 12 kg ', ['cell' => $cellPart]);

		$this->assertEquals('cellCouple', $reading['refusal']['reason'] ?? NULL, 'une variété contredit la part « Non spécifiée »');

		$reading = $this->read('12 kg ', ['cell' => $cellPart]);

		$this->assertEquals(EntryLib::WRITE, $reading['outcome'], 'la part « Non spécifiée » écrit sans rien dire du couple');
		$this->assertEquals([], $reading['couple']['chips'], 'la part ne propose pas de puces');

		EntryLib::write($this->eStructure, $reading);

		$cTask = $this->getTasks($eCultivation);

		$this->assertEquals(1, $cTask->count());
		$this->assertTrue($cTask->first()['variety']->empty(), 'la récolte va sur l\'intervention sans couple');
		$this->assertTrue($cTask->first()['harvestSize']->empty());

		$cellSeries = $this->getCell($eCultivation);

		$this->assertFalse($cellSeries['part']);

		$reading = $this->read('12 kg ', ['cell' => $cellSeries]);

		$this->assertTrue(isset($reading['couple']['chips']['variety']), 'la case de la série propose les variétés');

		$reading = $this->read($eVariety['name'].' 12 kg ', ['cell' => $cellSeries]);

		$this->assertEquals(EntryLib::WRITE, $reading['outcome'], 'la case de la série prend la variété dite');
		$this->assertEquals($eVariety['id'], $reading['confirmation']['eVariety']['id']);

	}

	/**
	 * Le refus d'un couple tombe avant l'intervention qui dit l'unité, la série étant déjà retenue : la réponse
	 * de la lecture se rend quand même, et porte le refus. Cas vu sur la part « Non spécifiée »,
	 * où la variété de l'autre part était tapée.
	 */
	public function testCellCoupleResponse(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$eVariety = $this->createVarietyNamed('Musquée', $eCultivation);

		$reading = $this->read($eVariety['name'].' 1 kg ', ['cell' => $this->getCell($eCultivation, part: TRUE)]);

		$this->assertEquals('cellCouple', $reading['refusal']['reason'] ?? NULL);

		$this->assertTrue(EntryUi::getEntry($reading)['refusal'] !== NULL, 'la réponse porte le refus');

	}

	/**
	 * Les puces des deux familles, chacune avec « Non spécifiée » ; une famille dite ne se propose plus, et une
	 * série sans variété n'en propose pas.
	 */
	public function testChips(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$eVariety = $this->createVarietyNamed('Noire', $eCultivation);
		$eSize = $this->createSizeNamed('Gros', $eCultivation['plant']);

		$reading = $this->read($this->getText('12 kg '));

		$this->assertEquals([$eVariety['id']], $reading['couple']['chips']['variety']['cChoice']->getIds());
		$this->assertTrue($reading['couple']['chips']['variety']['unspecified']);
		$this->assertEquals([$eSize['id']], $reading['couple']['chips']['harvestSize']['cChoice']->getIds());

		// La puce de la part sans couple pose le mot-clé que la lecture compare, et non son libellé : c'est ce mot
		// qui revient dans la phrase (`EntryField.getPrefixWord()`).
		$values = array_column(HarvestIntentionUi::getResults($this->eStructure, $reading, new \Collection(), new \Collection()), 'value');

		foreach(HarvestIntentionLib::UNSPECIFIED as $family => $word) {

			$this->assertTrue(in_array($family.':'.$word, $values, TRUE), $family.' : la puce porte le mot-clé');

			$reading = $this->read($this->getText($word.' 12 kg '));

			$this->assertEquals([$family], $reading['couple']['said'], $family.' : le mot-clé se relit');

		}

		$reading = $this->read($this->getText($eVariety['name'].' 12 kg '));

		$this->assertEquals(['variety', 'harvestSize'], array_keys($reading['couple']['chips']), 'la variété dite reste en puces');
		$this->assertEquals($eVariety['name'], $reading['couple']['chips']['variety']['selected'], 'retenue');
		$this->assertEquals(NULL, $reading['couple']['chips']['harvestSize']['selected']);

		$selected = array_column(array_filter(HarvestIntentionUi::getResults($this->eStructure, $reading, new \Collection(), new \Collection()), fn(array $result) => $result['selected'] ?? FALSE), 'value');

		$this->assertEquals(['variety:'.$eVariety['name'], 'unit:kg'], $selected, 'seule la puce de la variété dite est marquée, avec l\'unité qui s\'écrira');

		// Une puce choisie remplace les mots qui disent sa famille : la réponse les donne au champ, liaisons comprises.
		$eVarietyLinked = $this->createVarietyNamed('Cœur de bœuf', $eCultivation);

		$reading = $this->read($this->getText($eVarietyLinked['name'].' 12 kg '));

		$this->assertEquals($eVarietyLinked['id'], $reading['couple']['eVariety']['id']);
		$this->assertEquals(['variety' => implode(' ', EntryLib::split($eVarietyLinked['name']))], EntryUi::getEntry($reading)['couple']['named'], 'les mots de la variété, liaison comprise');

		$this->createSlice($eCultivation, $this->createVariety($eCultivation['plant']));

		$eCultivationBare = $this->createCultivationInPhase(TRUE, 'Série du décor sans variété');

		$reading = $this->read($this->getText('12 kg '), ['cultivation' => $eCultivationBare['id']]);

		$this->assertEquals(['harvestSize'], array_keys($reading['couple']['chips']), 'une série sans variété n\'en propose pas');

	}

	/**
	 * Un début de nom au mot en frappe cherche sa puce : pas de refus, rien ne s'écrit, et seules les puces qu'il
	 * commence restent. Un mot qui ne commence aucun nom est refusé.
	 */
	public function testSearching(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$eVariety = $this->createVarietyNamed('Cœur de bœuf', $eCultivation);
		$this->createVarietyNamed('Noire', $eCultivation);
		$this->createSizeNamed('Gros', $eCultivation['plant']);

		$reading = $this->read($this->getText('12 kg cœu'));

		$this->assertEquals(NULL, $reading['refusal'], 'le début de nom n\'est pas refusé');
		$this->assertEquals(EntryLib::ASK, $reading['outcome'], 'rien ne s\'écrit pendant la recherche');
		$this->assertEquals(['variety'], array_keys($reading['couple']['chips']), 'seule la famille qu\'il commence reste');
		$this->assertEquals([$eVariety['id']], $reading['couple']['chips']['variety']['cChoice']->getIds());
		$this->assertFalse($reading['couple']['chips']['variety']['unspecified']);

		$reading = $this->read($this->getText('12 kg non spé'));

		$this->assertTrue($reading['couple']['chips']['variety']['unspecified'], '« non spé » commence « Non spécifiée »');
		$this->assertTrue($reading['couple']['chips']['harvestSize']['unspecified'], 'et « Non spécifié »');

		$reading = $this->read($this->getText('12 kg zzz'));

		$this->assertEquals('unclaimed', $reading['refusal']['reason'] ?? NULL, 'un mot qui ne commence aucun nom est refusé');

	}

	/**
	 * Une autre unité se dit avec un couple comme sans, au menu comme dans la case : elle va sur
	 * l'intervention du couple dans cette unité (`HarvestWeekLib::getTaskForEntry()`).
	 */
	public function testCoupleUnit(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$eVariety = $this->createVarietyNamed('Noire', $eCultivation);

		$reading = $this->read($this->getText($eVariety['name'].' 40 pièces '));

		$this->assertEquals(EntryLib::WRITE, $reading['outcome'], 'avec un couple, l\'autre unité s\'écrit');
		$this->assertEquals([\series\Task::UNIT, $eVariety['id']], [$reading['confirmation']['unit'], $reading['confirmation']['eVariety']['id']]);

		EntryLib::write($this->eStructure, $reading);

		$eTask = $this->getTasks($eCultivation)->first();

		$this->assertEquals([40.0, \series\Task::UNIT, $eVariety['id']], [(float)$eTask['harvest'], $eTask['harvestUnit'], $eTask['variety']['id']], 'la récolte va sur l\'intervention de la variété, en pièces');

		$reading = $this->read($this->getText('40 pièces '));

		$this->assertEquals(EntryLib::WRITE, $reading['outcome'], 'sans couple, l\'autre unité s\'écrit');

	}

	/**
	 * Sur un jour en partie réparti par variété, un retrait se dit sur sa part : sur le jour entier, l'écriture le
	 * refuserait (`Task::harvestMore.ventilated`), la lecture aussi.
	 */
	public function testRemoveVentilated(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$eVariety = $this->createVarietyNamed('Cœur de bœuf', $eCultivation);

		$reading = $this->read($this->getText($eVariety['name'].' 12 kg '));
		EntryLib::write($this->eStructure, $reading);

		$reading = $this->read($this->getText('-2 kg '));

		$this->assertEquals(['reason' => 'removeVentilated'], $reading['refusal']);
		$this->assertEquals(['variety'], array_keys($reading['couple']['chips']), 'le refus garde la famille qui le corrige');
		$this->assertEquals(['variety:'.$eVariety['name']], array_slice(array_column(HarvestIntentionUi::getRefusalResults($this->eStructure, $reading), 'value'), 0, 1), 'la variété se choisit sous le refus');

		// La puce choisie après le nombre, comme on la choisit sous le refus.
		$reading = $this->read($this->getText('-2 kg '.$eVariety['name'].' '));

		$this->assertEquals(EntryLib::WRITE, $reading['outcome'], 'le retrait dit sur sa part s\'écrit');
		$this->assertEquals(10.0, $reading['confirmation']['today']);

	}

	/**
	 * Dans la case de la série, un retrait sans variété vise la part « Non spécifiée » et se refuse au-delà de ce
	 * qu'elle porte ; la variété choisie ensuite sous le refus le relit sur sa part.
	 */
	public function testRemoveMoreThenVariety(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);
		$eVariety = $this->createVarietyNamed('Butternut', $eCultivation);
		$cell = $this->getCell($eCultivation);

		foreach([$eVariety['name'].' 40 kg ', '1 kg '] as $text) {
			$reading = $this->read($text, ['cell' => $cell]);
			EntryLib::write($this->eStructure, $reading);
		}

		$reading = $this->read('-12 kg ', ['cell' => $cell]);

		$this->assertEquals('removeMore', $reading['refusal']['reason'] ?? NULL);
		$this->assertEquals(1.0, $reading['refusal']['today'], 'le refus compte la part « Non spécifiée »');
		$this->assertTrue(in_array('variety:'.$eVariety['name'], array_column(HarvestIntentionUi::getRefusalResults($this->eStructure, $reading), 'value'), TRUE), 'la variété se choisit sous le refus');

		$reading = $this->read('-12 kg '.$eVariety['name'].' ', ['cell' => $cell]);

		$this->assertEquals(EntryLib::WRITE, $reading['outcome'], 'la variété choisie relit le retrait sur sa part');
		$this->assertEquals(28.0, $reading['confirmation']['today']);

	}

}
?>
