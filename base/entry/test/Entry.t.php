<?php
namespace entry;

/**
 * Les deux routes du champ, jouées comme des requêtes : la lecture, l'écriture qui relit la phrase
 * côté serveur, et le refus hors drapeau.
 */
class EntryTest extends EntryFixtureTest {

	private function getInput(string $query): array {

		return [
			'structure' => $this->eStructure['id'],
			'prefix' => 'Récolte '.$this->getPlant()['name'],
			'query' => $query,
		];

	}

	public function testRead(): void {

		$this->createCultivationInPhase(TRUE);

		$action = $this->executePageAction('entry/entry:read', $this->getInput('12 kg'));

		$this->assertTrue($action instanceof \ViewAction, 'la lecture rend sa vue');

	}

	/**
	 * La lecture dit si le mot en cours de frappe n'est pris que pour un début de mot, que le champ laisse
	 * alors sans espace derrière lui quand un choix réécrit la phrase (`EntryLib::isTypingPrefix()`).
	 */
	public function testTypingPrefix(): void {

		$this->createCultivationInPhase(TRUE);

		$cases = [
			'4 p' => TRUE,
			'4 kg hi' => TRUE,
			'4 kg' => FALSE,
			'4 pièces' => FALSE,
			'4' => FALSE,
			'4 p ' => FALSE,
			'4 kg 1 h' => FALSE,
			'4 kg 1 he' => TRUE,
			'4 kg 3' => TRUE,
		];

		foreach($cases as $rest => $prefix) {
			$this->assertEquals($prefix, $this->read($this->getText($rest))['typingPrefix'], '« '.$rest.' »');
		}

		$this->assertFalse($this->read('récolte b')['typingPrefix'], 'avant l\'espèce, « b » en cherche une');

	}

	public function testWrite(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$action = $this->executePageAction('entry/entry:doWrite', $this->getInput('12 kg'));

		$this->assertTrue($action instanceof \ViewAction, 'l\'écriture rend ses instructions sans recharger la page');
		$this->assertEquals(12.0, (float)$this->getHarvest($eCultivation)->first()['harvest']);

	}

	/**
	 * Une phrase incomplète ne s'écrit pas, quoi que le client envoie.
	 */
	public function testWriteIncomplete(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$action = $this->executePageAction('entry/entry:doWrite', $this->getInput(''));

		$this->assertTrue($action instanceof \FailAction, 'une phrase sans quantité est refusée');
		$this->assertEquals(0, $this->getHarvest($eCultivation)->count());

	}

	/**
	 * Les intentions que les mots en attente commencent, parmi celles que le membre peut écrire.
	 */
	public function testIntentions(): void {

		$this->assertEquals(['harvest', 'time', 'done', 'intervention', 'sale'], EntryLib::findIntentions($this->eStructure, NULL));
		$this->assertEquals(['intervention'], EntryLib::findIntentions($this->eStructure, 'inter'));
		$this->assertEquals(['sale'], EntryLib::findIntentions($this->eStructure, 'ven'));
		$this->assertEquals(['harvest'], EntryLib::findIntentions($this->eStructure, 'rec'));
		$this->assertEquals(['done'], EntryLib::findIntentions($this->eStructure, 'fin'));
		$this->assertEquals(['harvest'], EntryLib::findIntentions($this->eStructure, 'Réc'));
		$this->assertEquals(['time'], EntryLib::findIntentions($this->eStructure, 'tem'));
		$this->assertEquals([], EntryLib::findIntentions($this->eStructure, 'semis'));

	}

	/**
	 * Hors drapeau, le champ du menu ne lit ni n'écrit.
	 */
	public function testFlag(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$enabled = EntrySetting::$enabled;
		EntrySetting::$enabled = fn(\structure\Structure $eStructure) => FALSE;

		try {

			$this->assertFalse(EntrySetting::isEnabled($this->eStructure));

			foreach(['entry/entry:read', 'entry/entry:doWrite'] as $path) {
				$action = $this->executePageAction($path, $this->getInput('12 kg'));
				$this->assertTrue($action instanceof \NotExistsAction, $path.' hors drapeau');
			}

			$this->assertEquals(0, $this->getHarvest($eCultivation)->count());

		} finally {
			EntrySetting::$enabled = $enabled;
		}

	}

	/**
	 * Ce que poste la case d'un jour de récolte (`EntryUi::getHarvestCell()`), sans préfixe : la case ne lit que
	 * la récolte.
	 */
	private function getCellInput(\series\Cultivation $eCultivation, string $query): array {

		return [
			'structure' => $this->eStructure['id'],
			'cell' => [
				'cultivation' => (string)$eCultivation['id'],
				'date' => \Clock::date(),
				'variety' => '',
				'harvestSize' => '',
			],
			'query' => $query,
		];

	}

	/**
	 * Hors drapeau, la case d'un jour de récolte lit, écrit, et son « Annuler » défait l'écriture : elle est la
	 * saisie rapide du planning, ouverte à toutes les structures, et non le champ du menu (V49, production le
	 * 23/10, « Data does not exist » sur chaque frappe hors de la 7).
	 */
	public function testFlagLeavesHarvestCell(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$input = $this->getCellInput($eCultivation, '12 kg');

		$enabled = EntrySetting::$enabled;
		EntrySetting::$enabled = fn(\structure\Structure $eStructure) => FALSE;

		try {

			$action = $this->executePageAction('entry/entry:read', $input);
			$this->assertTrue($action instanceof \ViewAction, 'la case ne lit pas hors drapeau');

			$action = $this->executePageAction('entry/entry:doWrite', $input);
			$this->assertTrue($action instanceof \ViewAction, 'la case n\'écrit pas hors drapeau');
			$this->assertEquals(12.0, (float)$this->getHarvest($eCultivation)->first()['harvest'], 'la récolte de la case n\'est pas écrite');

			// La vue rend le ticket dans la pastille, et l'action ne l'expose pas autrement.
			$undo = new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action)->reading['undo'];

			$action = $this->executePageAction('entry/entry:doCancel', [
				'structure' => $this->eStructure['id'],
				'intention' => $undo['intention'],
				'ticket' => $undo['ticket'],
			]);

			$this->assertTrue($action instanceof \ViewAction, 'l\'annulation de la case est refusée hors drapeau');
			$this->assertEquals(0, $this->getHarvest($eCultivation)->count(), 'l\'annulation ne défait pas la récolte de la case');

		} finally {
			EntrySetting::$enabled = $enabled;
		}

	}

	/**
	 * Un temps seul tapé dans la case s'annule par son propre ticket, celui de l'intention du temps : l'annulation
	 * hors drapeau ne se borne donc pas à la récolte.
	 */
	public function testFlagLeavesHarvestCellTime(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$enabled = EntrySetting::$enabled;
		EntrySetting::$enabled = fn(\structure\Structure $eStructure) => FALSE;

		try {

			$action = $this->executePageAction('entry/entry:doWrite', $this->getCellInput($eCultivation, '30 min'));
			$this->assertTrue($action instanceof \ViewAction, 'le temps seul de la case ne s\'écrit pas hors drapeau');
			$this->assertEquals(0.5, $this->getTime($eCultivation), 'la demi-heure de la case n\'est pas écrite');

			// La vue rend le ticket dans la pastille, et l'action ne l'expose pas autrement.
			$undo = new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action)->reading['undo'];

			$this->assertEquals('time', $undo['intention'], 'le temps seul ne s\'annule pas par l\'intention du temps');

			$action = $this->executePageAction('entry/entry:doCancel', [
				'structure' => $this->eStructure['id'],
				'intention' => $undo['intention'],
				'ticket' => $undo['ticket'],
			]);

			$this->assertTrue($action instanceof \ViewAction, 'l\'annulation du temps de la case est refusée hors drapeau');
			$this->assertEquals(0.0, $this->getTime($eCultivation), 'l\'annulation ne défait pas le temps de la case');

		} finally {
			EntrySetting::$enabled = $enabled;
		}

	}

	private function getTime(\series\Cultivation $eCultivation): float {

		return (float)\series\Timesheet::model()
			->select(['time'])
			->whereCultivation($eCultivation)
			->getCollection()
			->sum('time');

	}

	/**
	 * Une structure hors de la liste du drapeau, comme toute autre que la 7 en production : le champ du menu
	 * ne lit ni n'écrit, et la page du planning ne prépare pas la semaine qu'elle dit à l'écriture. La même structure, inscrite
	 * dans la liste, les reçoit : sans ce témoin, un `entry` jamais préparé passerait aussi.
	 */
	public function testOutsideList(): void {

		$eCultivation = $this->createCultivationInPhase(TRUE);

		$enabled = EntrySetting::$enabled;

		try {

			EntrySetting::$enabled = fn(\structure\Structure $eStructure) => EntrySetting::accepts($eStructure, [$this->eStructure['id'] + 1]);

			foreach(['entry/entry:read', 'entry/entry:doWrite'] as $path) {
				$action = $this->executePageAction($path, $this->getInput('12 kg'));
				$this->assertTrue($action instanceof \NotExistsAction, $path.' hors liste');
			}

			$this->assertEquals(0, $this->getHarvest($eCultivation)->count());
			$this->assertFalse($this->getPlanningEntry(), 'le planning hors liste ne prépare pas le champ');

			EntrySetting::$enabled = fn(\structure\Structure $eStructure) => EntrySetting::accepts($eStructure, [$this->eStructure['id']]);

			$this->assertTrue($this->getPlanningEntry(), 'le planning de la structure inscrite prépare le champ');

		} finally {
			EntrySetting::$enabled = $enabled;
		}

	}

	/**
	 * Ce que la page du planning de la semaine pose dans `$data->entry`, qui décide de la semaine qu'elle dit à l'écriture (`EntryUi::getContext()`).
	 */
	private function getPlanningEntry(): bool {

		$path = 'ferme/'.$this->eStructure['id'].'/planning/'.\structure\Member::WEEKLY.'/'.\Clock::week();

		$action = $this->executePageAction($path, [], 'GET');

		$this->assertTrue($action instanceof \ViewAction, 'le planning se rend');

		return new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action)->entry;

	}

	/**
	 * Un membre sans le planning complet ne lit rien : la lecture montre ce que seul le planning montre.
	 */
	public function testRight(): void {

		$this->createCultivationInPhase(TRUE);

		$eUser = new \user\User();

		foreach(\structure\MemberLib::getByStructure($this->eStructure, index: NULL) as $eMember) {

			if($eMember['role']->notEmpty() and $eMember->isRole('planning', \structure\Role::FULL) === FALSE) {
				$eUser = $eMember['user'];
				break;
			}

		}

		if($this->assertTrue($eUser->notEmpty(), 'la structure '.static::STRUCTURE.' a un membre sans le planning complet') === FALSE) {
			return;
		}

		\user\ConnectionLib::setOnline($eUser);

		try {

			foreach(['entry/entry:read', 'entry/entry:doWrite', 'entry/entry:doCancel'] as $path) {
				$action = $this->executePageAction($path, $this->getInput('12 kg'));
				$this->assertTrue($action instanceof \NotAllowedAction, $path.' sans le planning complet');
			}

		} finally {
			\user\ConnectionLib::setOnline($this->getMember());
		}

	}

	public function testAccepts(): void {

		$this->assertTrue(EntrySetting::accepts($this->eStructure, TRUE));
		$this->assertFalse(EntrySetting::accepts($this->eStructure, FALSE));
		$this->assertTrue(EntrySetting::accepts($this->eStructure, [$this->eStructure['id']]));
		$this->assertFalse(EntrySetting::accepts($this->eStructure, [$this->eStructure['id'] + 1]));

	}

}
?>
