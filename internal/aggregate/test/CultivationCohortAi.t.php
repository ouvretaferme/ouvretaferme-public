<?php
namespace aggregate;

/**
 * Les cohortes du calendrier : les cultures implantées une même semaine, et quand elles
 * récoltent.
 *
 * Deux contrats. Le premier est de cohérence : au survol, la cohorte se dessine par-dessus la
 * courbe des récoltes de l'espèce, et toutes les cohortes réunies doivent redonner exactement
 * cette courbe, récoltes déclarées au mois comprises, sinon la cohorte et la courbe qu'elle grise
 * ne parlent pas du même jeu. Le second protège l'anonymat : une cohorte d'une ou deux structures
 * serait leur calendrier de récolte, semaine par semaine, et le baisser ne casse rien de visible.
 * D'où le cas à un de part et d'autre du plancher.
 *
 * Aucune base : les méthodes éprouvées travaillent sur des tableaux de dates déjà calculés, posés
 * ici à la main.
 *
 * Lancement : dev/test package=aggregate file=CultivationCohortAi
 */
class CultivationCohortAiTest extends \Test {

	private function invoke(string $method, mixed ...$arguments): mixed {
		return new \ReflectionMethod(CultivationCropPlanAiLib::class, $method)->invoke(NULL, ...$arguments);
	}

	/**
	 * Le délai suit la projection : de l'implantation à la première semaine de récolte,
	 * compté sur l'axe continu, donc au-delà de 52 quand la récolte passe l'année.
	 */
	private function getTiming(int $structureId, int $startWeek, array $harvestWeeks): array {

		return [
			'structureId' => $structureId,
			'startWeek' => $startWeek,
			'harvestWeeks' => $harvestWeeks,
			'delay' => ($harvestWeeks === []) ? NULL : ($harvestWeeks[0] - $startWeek + 52) % 52,
		];

	}

	/**
	 * Des cultures de structures toutes différentes, implantées la même semaine et récoltées
	 * les mêmes semaines.
	 */
	private function getTimings(int $number, int $startWeek, array $harvestWeeks, int $firstStructureId = 1): array {

		$timings = [];

		for($structureId = $firstStructureId; $structureId < $firstStructureId + $number; $structureId++) {
			$timings[] = $this->getTiming($structureId, $startWeek, $harvestWeeks);
		}

		return $timings;

	}

	/**
	 * Les cohortes d'un créneau, additionnées, redonnent sa courbe des récoltes. Le créneau
	 * mêle deux semaines d'implantation et des récoltes déclarées au mois : celles-ci se
	 * répartissent sur le profil du créneau entier, pas sur celui de leur seule cohorte, qui
	 * n'a aucune récolte datée à la semaine.
	 */
	public function testCohortsAddUpToTheHarvestCurve(): void {

		$harvestTimings = array_merge(
			$this->getTimings(3, 14, [24, 25, 26]),
			$this->getTimings(2, 15, [26, 27, 28, 29], 10)
		);

		$monthTimings = $this->getTimings(2, 16, [27, 28, 29, 30], 20);

		$reference = $this->invoke('countHarvestWeeks', $harvestTimings);
		$harvest = $this->invoke('addMonthHarvests', $reference, $monthTimings, $reference);

		$cohorts = $this->invoke('addCohorts', [], array_merge($harvestTimings, $monthTimings), $harvestTimings, $monthTimings);

		$this->assertEquals([14, 15, 16], array_keys($cohorts));

		$sum = array_fill(1, 52, 0);

		foreach($cohorts as $cohort) {
			foreach($cohort['counts'] as $week => $count) {
				$sum[$week] += $count;
			}
		}

		foreach($harvest as $week => $count) {
			$this->assertTrue(abs($count - $sum[$week]) < 0.0001, 'semaine '.$week.' : '.$count.' attendu, '.$sum[$week].' obtenu');
		}

		// Sur le profil du créneau, S30 où personne ne récolte à la semaine ne reçoit rien.
		$this->assertEquals(0, $cohorts[16]['counts'][30]);
		$this->assertEquals(2, $cohorts[16]['cultivations']);

	}

	/**
	 * Une semaine d'implantation partagée par deux créneaux, la S14 à mars et à avril, réunit
	 * les cultures des deux.
	 */
	public function testCohortAddsUpAcrossSlots(): void {

		$march = $this->getTimings(3, 14, [24, 25]);
		$april = $this->getTimings(2, 14, [25, 26], 10);

		$cohorts = $this->invoke('addCohorts', [], $march, $march, []);
		$cohorts = $this->invoke('addCohorts', $cohorts, $april, $april, []);

		$this->assertEquals(5, $cohorts[14]['cultivations']);
		$this->assertEquals([24 => 3, 25 => 5, 26 => 2], array_filter($cohorts[14]['counts']));
		$this->assertEquals(5, count($cohorts[14]['structureIds']));

	}

	/**
	 * Le plancher compte les structures, pas les cultures : quatre structures qui échelonnent
	 * dix séries ne font toujours pas une cohorte.
	 */
	public function testCohortNeedsFiveStructures(): void {

		$timings = $this->getTimings(5, 14, [24, 25, 26]);
		$atFloor = $this->invoke('addCohorts', [], $timings, $timings, []);
		$this->assertEquals(CultivationCropPlanAiLib::COHORT_SHOWN, $this->invoke('getCohorts', $atFloor)[14]['status']);

		$timings = [];

		foreach([1, 2, 3, 4] as $structureId) {
			$timings[] = $this->getTiming($structureId, 14, [24, 25, 26]);
			$timings[] = $this->getTiming($structureId, 14, [24, 25, 26]);
		}

		$below = $this->invoke('addCohorts', [], $timings, $timings, []);

		$this->assertEquals(8, $below[14]['cultivations']);
		$this->assertEquals(['status' => CultivationCropPlanAiLib::COHORT_FEW], $this->invoke('getCohorts', $below)[14], 'sous le seuil, aucun chiffre ne sort');

	}

	/**
	 * Une récolte de janvier après un semis d'automne se dessine en janvier, à gauche, comme
	 * sur la courbe de l'espèce.
	 */
	public function testCohortWrapsAroundTheYear(): void {

		$timings = $this->getTimings(5, 40, [51, 52, 1, 2]);
		$cohorts = $this->invoke('addCohorts', [], $timings, $timings, []);
		$cohort = $this->invoke('getCohorts', $cohorts)[40];

		$this->assertEquals(5, $cohort['cultivations']);
		$this->assertEquals(11, $cohort['delay']);
		$this->assertTrue($cohort['curve'][1] > 0);
		$this->assertTrue($cohort['curve'][2] > 0);
		$this->assertTrue($cohort['curve'][52] > 0);
		$this->assertEquals(0.0, $cohort['curve'][26]);
		$this->assertEquals(max($cohort['curve']), $cohort['max']);

	}

	/**
	 * Le délai jusqu'à la première récolte est la médiane des cultures récoltées à la
	 * semaine. Une récolte déclarée au mois compte dans la courbe mais pas dans le
	 * délai : elle commencerait au premier du mois, quel que soit le jour réel.
	 */
	public function testCohortDelayIsTheMedianOfWeeklyHarvests(): void {

		$harvestTimings = array_merge(
			$this->getTimings(3, 14, [24, 25]),
			$this->getTimings(2, 14, [30, 31], 10)
		);

		$monthTimings = $this->getTimings(4, 14, [18, 19, 20, 21], 20);

		$cohorts = $this->invoke('addCohorts', [], array_merge($harvestTimings, $monthTimings), $harvestTimings, $monthTimings);

		$this->assertEquals(10, $this->invoke('getCohorts', $cohorts)[14]['delay']);

		$cohorts = $this->invoke('addCohorts', [], $monthTimings, [], $this->getTimings(5, 14, [18, 19], 30));

		$this->assertEquals(NULL, $this->invoke('getCohorts', $cohorts)[14]['delay'], 'sans récolte à la semaine, pas de délai');

	}

	/**
	 * Une semaine où des structures implantent sans qu'aucune ne récolte est sous le
	 * seuil : l'écran la distingue d'une semaine où personne n'a rien implanté. Les 52
	 * semaines ont chacune leur statut.
	 */
	public function testWeekWithoutHarvestIsBelowTheFloor(): void {

		$timings = $this->getTimings(6, 20, []);

		$cohorts = $this->invoke('getCohorts', $this->invoke('addCohorts', [], $timings, [], []));

		$this->assertEquals(['status' => CultivationCropPlanAiLib::COHORT_FEW], $cohorts[20]);
		$this->assertEquals(['status' => CultivationCropPlanAiLib::COHORT_EMPTY], $cohorts[21]);
		$this->assertEquals(range(1, 52), array_keys($cohorts));

	}

}
