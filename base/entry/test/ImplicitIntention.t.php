<?php
namespace entry;

/**
 * Le champ du menu n'a pas de jeton de verbe : « courgette 12 kg » se lit comme une récolte (lot S9,
 * planche G3, « LISTE_SANS_VERBE »). Le verbe reste deviné, jamais imposé : un début de verbe le
 * propose comme avant, et des mots qui ne nomment l'objet d'aucune intention ne lisent rien.
 */
class ImplicitIntentionTest extends EntryFixtureTest {

	/**
	 * Sans verbe, la phrase rend la lecture de la même phrase précédée de « récolte » : même culture,
	 * même écriture, mêmes mots qui ont nommé l'espèce.
	 */
	public function testPhraseWithoutVerbIsHarvest(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$name = $this->getPlant()['name'];

		$readingImplicit = $this->read($name.' 12 kg');
		$readingExplicit = $this->read('récolte '.$name.' 12 kg');

		$this->assertEquals('harvest', $readingImplicit['intention'], 'sans verbe, la phrase se lit en récolte');
		$this->assertEquals(EntryLib::WRITE, $readingImplicit['outcome'], 'et elle écrit');
		$this->assertEquals($eCultivation['id'], $readingImplicit['cultivation']['id']);
		$this->assertEquals($readingExplicit['confirmation']['quantity'], $readingImplicit['confirmation']['quantity']);
		$this->assertEquals($readingExplicit['named'], $readingImplicit['named']);

	}

	/**
	 * Le début d'un nom d'espèce, en cours de frappe, propose les espèces de la récolte.
	 */
	public function testObjectBeingTyped(): void {

		$this->createCultivationInPhase(TRUE);

		$ePlant = $this->getPlant();
		$start = mb_substr(EntryLib::split($ePlant['name'])[0], 0, 4);

		$cElement = new \Collection();
		$reading = EntryLib::read($this->eStructure, $start, cElement: $cElement);

		$this->assertEquals('harvest', $reading['intention'], '« '.$start.' »');
		$this->assertTrue(EntryLib::findObjects($cElement, $reading['pending'])->offsetExists($ePlant['id']), 'l\'espèce du décor est proposée');

	}

	/**
	 * Ce qui ne nomme aucune espèce reste sans intention : le champ propose les verbes, ou rien.
	 */
	public function testWordsNamingNothing(): void {

		$this->createCultivationInPhase(TRUE);

		$reading = $this->read('zzzqqq');

		$this->assertEquals(NULL, $reading['intention'], 'un mot inconnu ne devine aucun verbe');
		$this->assertEquals(EntryLib::ASK, $reading['outcome']);
		$this->assertEquals('zzzqqq', $reading['pending']);

		$reading = $this->read('');

		$this->assertEquals(NULL, $reading['intention'], 'un champ vide ne lit rien');
		$this->assertEquals(NULL, $reading['pending']);

	}

	/**
	 * La ligne qui écrit se range sous le verbe que la phrase a pris, qu'elle l'ait dit ou non : le
	 * champ du menu n'a pas d'autre endroit où le montrer.
	 */
	public function testWriteLineUnderItsVerb(): void {

		$this->createCultivationInPhase(TRUE);

		foreach([$this->getPlant()['name'].' 12 kg', $this->getText('12 kg')] as $text) {

			$cElement = new \Collection();
			$reading = EntryLib::read($this->eStructure, $text, cElement: $cElement);
			$results = EntryUi::getResults($this->eStructure, $reading, EntryLib::findObjects($cElement, $reading['pending']), EntryLib::findIntentions($this->eStructure, $reading['pending']));

			$this->assertEquals('write', $results[0]['value'], '« '.$text.' »');
			$this->assertEquals(HarvestIntentionUi::getLabel(), $results[0]['group'], '« '.$text.' »');

		}

	}

	/**
	 * Un verbe obligatoire peut commencer le nom d'une action : « vente directe 1 h » reste un temps sur
	 * l'action Vente directe, la vente n'ayant aucun client à ce nom (CL-07).
	 */
	public function testVerbInsideActionName(): void {

		$eCategory = \work\CategoryLib::getByStructure($this->eStructure, fqn: CATEGORIE_COMMERCIALISATION);

		if($this->assertDecorPresent($eCategory->notEmpty(), 'la structure a une catégorie Commercialisation') === FALSE) {
			return;
		}

		$eAction = new \work\Action([
			'name' => 'Vente directe '.substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 10),
			'structure' => $this->eStructure,
			'categories' => [$eCategory['id']],
		]);

		// Sans `build()` : le décor fixe le nom et la catégorie, que le formulaire d'action vérifierait.
		\work\ActionLib::create($eAction);

		$reading = $this->read($eAction['name'].' 1 h ');

		$this->assertEquals('time', $reading['intention'], 'la phrase se lit en temps');
		$this->assertEquals($eAction['name'], $reading['object']['name'] ?? NULL, 'sur l\'action qu\'elle nomme');

	}

}
?>
