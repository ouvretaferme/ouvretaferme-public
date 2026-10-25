<?php
namespace structure;

/**
 * Les cases de l'onglet Fermes d'un groupe, qui ouvrent le bandeau « Pour les fermes sélectionnées ».
 *
 * Une case ne se pose que sur une ferme de membre : une ferme projet appartient au groupe et ne lui
 * partage rien, il n'y a rien à y comparer ni à dupliquer. C'est la raison pour laquelle la case est
 * passée en `aside` par l'appelant et non posée dans `getGroupPanel()`, que les deux natures
 * partagent : descendre la case dans le panneau commun la donnerait aussi aux fermes projet, et ce
 * test le dit.
 *
 * La page est exécutée pour de vrai (`Page::execute()`), puis la vue rendue avec ce que la page a
 * préparé : c'est la préparation de la page qui pose `sharing`, et une UI appelée avec
 * une donnée montée à la main prouverait seulement que le test sait la monter.
 *
 * Chaque absence attendue est précédée de sa présence : la ferme projet est bien préparée par la
 * page, et la ferme de membre porte bien sa case.
 *
 * Décor : le groupe partageant G4, sa ferme projet P1, F6 qui lui partage son plan de culture et F9
 * qui ne le partage pas. Aucune écriture.
 *
 * Lancement : dev/test package=structure file=FarmsBatchAi
 */
class FarmsBatchAiTest extends CommunityFixtureAiTest {

	/**
	 * Le rendu de l'onglet Fermes du groupe partageant, lu par le lecteur principal : les cases,
	 * indexées par ferme, avec leurs marques `data-batch`.
	 */
	private function getBatch(): array {

		$data = $this->runFarmsRoute();

		$html = new StructureCommunityUi()->getStructures(
			$data->e,
			$data->eCommunity,
			$data->cStructureMine,
			$data->cStructureProject,
			$data->cStructureMember,
			$data->e->canManage(),
			$data->hasShareable,
			$data->hasProducer,
			$data->hasDuplicate,
			$data->eSection
		);

		$document = new \DOMDocument();
		// Le HTML5 du produit (`svg`, `use`, `&` nus dans les adresses) fait parler libxml, qui ne connaît
		// que HTML 4 : ses remarques sont écartées, un vrai avertissement restant visible ailleurs.
		$errors = libxml_use_internal_errors(TRUE);
		$document->loadHTML('<?xml encoding="utf-8"?><body>'.$html.'</body>');
		libxml_clear_errors();
		libxml_use_internal_errors($errors);

		$boxes = [];

		foreach($document->getElementsByTagName('input') as $node) {
			if($node->getAttribute('name') === 'batch[]') {
				$boxes[(int)$node->getAttribute('value')] = explode(' ', $node->getAttribute('data-batch'));
			}
		}

		return [
			'data' => $data,
			'boxes' => $boxes,
			'menu' => ($document->getElementById('batch-farm') !== NULL)
		];

	}

	private function runFarmsRoute(): \stdClass {

		\user\ConnectionLib::setOnline(\user\UserLib::getById($this->user));

		$method = new \ReflectionProperty(\Route::class, 'requestMethod');
		$methodBefore = $method->getValue();
		$method->setValue(NULL, 'GET');

		if(defined('LIME_URL') === FALSE) {
			define('LIME_URL', \Lime::getUrl());
		}

		$getBefore = $_GET;
		$_GET = ['id' => (string)$this->communitySharing];
		$data = new \stdClass();

		// Ce que le démarrage de l'application pose avant toute page (`ouvretaferme/Lime.s.php`),
		// et que `Page::execute()` ne rejoue pas : le lecteur, et aucun sous-groupe choisi.
		$data->eUserOnline = \user\ConnectionLib::getOnline();
		$data->eSection = new Section();

		try {
			\Page::execute(\Route::getCompatiblePages('groupe/'.$this->communitySharing.'/fermes'), $data);
			$this->assertFail('l\'onglet Fermes n\'a levé aucune action');
		} catch(\ViewAction) {
			return $data;
		} finally {
			$_GET = $getBefore;
			$method->setValue(NULL, $methodBefore);
		}

		return $data;

	}

	public function testProjectFarmHasNoBox(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		['data' => $data, 'boxes' => $boxes] = $this->getBatch();

		$this->assertTrue(in_array($this->structureProject, $data->cStructureProject->getIds()), 'la ferme projet est préparée par la page');

		$this->assertTrue(isset($boxes[self::STRUCTURE_SHARED]), 'une ferme de membre porte sa case');
		$this->assertFalse(isset($boxes[$this->structureProject]), 'la ferme projet ne porte pas de case');

	}

	public function testBoxCarriesSharing(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		['boxes' => $boxes, 'menu' => $menu] = $this->getBatch();

		$this->assertTrue($menu, 'le bandeau des fermes cochées est rendu');

		$this->assertTrue(isset($boxes[$this->structureSharedWithoutProduction]), 'la ferme qui ne partage pas son plan de culture porte aussi sa case');

		$this->assertTrue(in_array('accept-cultures', $boxes[self::STRUCTURE_SHARED] ?? [], TRUE), 'la ferme qui partage son plan de culture accepte la comparaison des cultures');
		$this->assertFalse(in_array('accept-cultures', $boxes[$this->structureSharedWithoutProduction] ?? [], TRUE), 'la ferme qui ne le partage pas la refuse');

	}

}
