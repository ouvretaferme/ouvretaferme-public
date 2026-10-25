<?php
namespace planning;

/**
 * La saisie rapide de l'écran Récoltes (R-Retours-1210, V35) : une préférence du membre, éteinte par défaut,
 * qui pose le champ de saisie dans chaque case vide d'un jour qu'on peut saisir. Elle se retient sur le
 * membre, vaut pour toutes les semaines, et ouvre le jour proposé d'une semaine passée.
 *
 * Le décor est monté dans la transaction de la méthode, sur la structure du décor `series`.
 *
 * Lancement : dev/test package=planning file=HarvestView
 */
class HarvestViewTest extends HarvestFixtureTest {

	/**
	 * Éteinte, la ligne s'ouvre au toucher et ne porte aucun champ ; allumée, chaque case vide du jour en
	 * porte un et la ligne ne s'ouvre plus. La semaine précédente reste dans les deux formes, et la liste dit
	 * la sienne aux réponses qui la visent (`data-quick`).
	 */
	public function testQuick(): void {

		$eCultivation = $this->createCultivationExpected([\Clock::week()]);

		foreach([FALSE, TRUE] as $quick) {

			$case = $quick ? 'allumée' : 'éteinte';

			[$document, $data] = $this->render($quick);

			$row = $document->querySelector('#planning-harvest-'.$eCultivation['id']);

			if($this->assertTrue($row !== NULL, 'saisie rapide '.$case.', la culture n\'a pas de ligne') === FALSE) {
				return;
			}

			$this->assertEquals($quick, $data->quick, 'saisie rapide '.$case.', l\'écran ne lit pas la préférence du membre');
			$this->assertEquals($quick ? '1' : '0', $document->querySelector('#planning-harvest-list')?->getAttribute('data-quick'), 'saisie rapide '.$case.', la liste ne dit pas sa forme à ses requêtes');
			$this->assertEquals($quick, $row->querySelectorAll('.planning-harvest-cell[data-date="'.\Clock::date().'"] .entry-cell')->length > 0, 'saisie rapide '.$case.', le champ du jour ne suit pas la préférence');
			$this->assertEquals($quick === FALSE, $row->hasAttribute('data-planning-harvest-open'), 'saisie rapide '.$case.', l\'ouverture de la ligne au toucher ne suit pas la préférence');
			$this->assertEquals(1, $document->querySelectorAll('#planning-harvest-'.$eCultivation['id'].' > .planning-harvest-previous')->length, 'saisie rapide '.$case.', la ligne perd la semaine précédente');

		}

	}

	/**
	 * L'interrupteur écrit la préférence du membre connecté, que l'écran rechargé relit. Un membre n'écrit
	 * pas celle d'un autre.
	 */
	public function testQuickKept(): void {

		$this->createCultivationExpected([\Clock::week()]);

		[$document, $data] = $this->render();

		$switch = $document->querySelector('.planning-harvest-quick .field-switch');

		if($this->assertTrue($switch !== NULL, 'l\'écran ne porte pas l\'interrupteur de la saisie rapide') === FALSE) {
			return;
		}

		$this->assertEquals('/structure/member:doUpdatePlanningHarvestQuick', $switch->getAttribute('data-ajax'), 'l\'interrupteur n\'écrit pas la préférence');
		$this->assertEquals('1', $switch->getAttribute('post-view-planning-harvest-quick'), 'l\'interrupteur éteint ne propose pas de s\'allumer');
		$this->assertFalse($data->quick, 'la saisie rapide n\'est pas éteinte par défaut');

		$eMember = \structure\StructureLib::getById($this->eStructure['id'])->getMember();

		$action = $this->executePage('structure/member:doUpdatePlanningHarvestQuick', ['id' => $eMember['id'], 'viewPlanningHarvestQuick' => '1'], 'POST');
		$this->assertTrue($action instanceof \ReloadAction, 'le membre n\'écrit pas sa préférence : '.implode(', ', $this->getFails($action)));
		$this->assertTrue($this->getQuickStored($eMember), 'la saisie rapide allumée ne s\'écrit pas sur le membre');

		\structure\MemberLib::clearCache();

		$action = $this->executePage('ferme/'.$this->eStructure['id'].'/planning-v2/recoltes/'.\Clock::week(), [], 'GET');
		[$data] = $this->runView('harvestWeek', $action);

		$this->assertTrue($data->quick, 'l\'écran rechargé ne relit pas la préférence');

		$eMemberOther = \structure\MemberLib::getByStructure($this->eStructure, index: NULL)->find(fn($eMemberStructure) => $eMemberStructure['user']->is($this->getMember()) === FALSE)->first();

		if($this->assertDecorPresent($eMemberOther !== NULL, 'la structure '.static::STRUCTURE.' a un second membre') === FALSE) {
			return;
		}

		$quickOther = $this->getQuickStored($eMemberOther);

		$action = $this->executePage('structure/member:doUpdatePlanningHarvestQuick', ['id' => $eMemberOther['id'], 'viewPlanningHarvestQuick' => $quickOther ? '0' : '1'], 'POST');

		$this->assertTrue(($action instanceof \ReloadAction) === FALSE, 'un membre écrit la préférence d\'un autre');
		$this->assertEquals($quickOther, $this->getQuickStored($eMemberOther), 'la préférence d\'un autre membre a changé en base');

	}

	/**
	 * Une semaine à venir ne se saisit pas : allumée, la saisie rapide n'y pose aucun champ, et l'interrupteur
	 * y reste pour l'éteindre.
	 */
	public function testWeekToCome(): void {

		$weekNext = $this->getSeason().'-W40';

		$this->createCultivationExpected([\Clock::week(), $weekNext]);

		[$document, $data] = $this->render(TRUE, $weekNext);

		$this->assertTrue($data->quick, 'une semaine à venir ne lit pas la préférence du membre');
		$this->assertEquals(0, $document->querySelectorAll('#planning-harvest-list .entry-cell')->length, 'une semaine à venir porte un champ de saisie');
		$this->assertEquals(1, $document->querySelectorAll('.planning-harvest-quick .field-switch')->length, 'une semaine à venir perd l\'interrupteur de la saisie rapide');

	}

	/**
	 * Une semaine passée s'ouvre sans jour pour qu'on la lise entière (HD) ; en saisie rapide, elle ouvre le
	 * jour proposé, où le champ attend.
	 */
	public function testPastWeek(): void {

		$weekPrevious = toWeek(week_date_day(\Clock::week(), 1).' - 7 days');

		$this->createCultivationExpected([$weekPrevious]);

		$open = fn(array $days): ?string => array_find_key($days, fn($day) => $day['open']);

		[, $data] = $this->render(FALSE, $weekPrevious);
		$this->assertEquals(NULL, $open($data->days), 'éteinte, une semaine passée s\'ouvre sur un jour');

		[, $data] = $this->render(TRUE, $weekPrevious);
		$this->assertTrue($open($data->days) !== NULL, 'allumée, une semaine passée n\'ouvre aucun jour');
		$this->assertEquals(array_find_key($data->days, fn($day) => $day['proposed']), $open($data->days), 'allumée, une semaine passée n\'ouvre pas le jour proposé');

	}

	/**
	 * Une culture de la série du décor prévue sur `$weeks`.
	 */
	private function createCultivationExpected(array $weeks): \series\Cultivation {

		$eCultivation = $this->createCultivation();

		$eCultivation['harvestPeriodExpected'] = \series\Cultivation::WEEK;
		$eCultivation->build(['harvestWeeksExpected'], ['harvestWeeksExpected' => $weeks]);

		\series\CultivationLib::update($eCultivation, ['harvestPeriodExpected', 'harvestWeeksExpected']);

		return $eCultivation;

	}

	/**
	 * La saisie rapide telle que la base la porte pour `$eMember`.
	 */
	private function getQuickStored(\structure\Member $eMember): bool {

		return (bool)\structure\Member::model()
			->select('viewPlanningHarvestQuick')
			->whereId($eMember['id'])
			->getValue('viewPlanningHarvestQuick');

	}

}
?>
