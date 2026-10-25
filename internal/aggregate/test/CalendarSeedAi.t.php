<?php
namespace aggregate;

/**
 * Le calendrier type se sème depuis un fichier figé (`lib/calendarDataAi.php`), et non d'un calcul :
 * ce que la table porte après le semis est ce que le fichier dit, ligne pour ligne.
 *
 * La production ne calcule jamais le calendrier (02/10/2026). Un semis qui sauterait une
 * espèce, en doublerait une ou laisserait derrière lui les lignes d'un calcul précédent ne se
 * verrait nulle part : la table est servie sans relecture, et une espèce sans ligne part au départ
 * des paniers sans saison. Chaque règle est éprouvée avec son pendant :
 *
 * 1. après le semis, la table et le fichier se valent dans les deux sens, et une ligne d'un calcul
 *    précédent que le fichier ne porte pas a disparu ;
 * 2. un second semis ne double rien ;
 * 3. un fqn que le référentiel partagé ne porte pas est nommé, et rien n'est écrit.
 *
 * Isolation : la transaction de `TransactionTest`, annulée après chaque méthode. Le semis ouvre sa propre
 * transaction, imbriquée dans celle du test : son commit ne part pas.
 *
 * Lancement : dev/test package=aggregate file=CalendarSeedAi
 */
class CalendarSeedAiTest extends \dev\TransactionTest {

	protected function getTransactionModel(): \ModuleModel {
		return Calendar::model();
	}

	public function testSeed(): void {

		$frozen = CalendarAiLib::getFrozen();

		$this->assertTrue($frozen !== [], 'Le fichier figé porte des espèces');

		// Une ligne que le fichier ne porte pas, comme un calcul précédent en aurait laissé : le
		// semis réécrit la table, il ne la complète pas.
		$ePlantForeign = $this->getPlantForeign($frozen);

		// `build()` aurait vérifié des valeurs postées : la table n'a aucun formulaire, et ce décor
		// passe par l'écriture de lot du module, comme une projection.
		CalendarAiLib::createCollection(new \Collection([new Calendar([
			'climate' => Calendar::OCEANIC,
			'plant' => $ePlantForeign,
			'harvestWeeks' => [1],
			'weeks' => [1],
			'structures' => 30,
			'fallback' => FALSE,
			'online' => TRUE,
		])]));

		$counts = CalendarAiLib::seed($frozen);

		$expected = $this->getFlat($frozen);
		$written = $this->getWritten();

		$this->assertEquals([], $counts['missing'], 'Espèces du fichier absentes du référentiel partagé : '.implode(', ', $counts['missing']));
		$this->assertEquals(count($expected), $counts['calendars'], 'Le semis compte une ligne par climat de chaque espèce du fichier');

		$absent = array_diff(array_keys($expected), array_keys($written));
		$surplus = array_diff(array_keys($written), array_keys($expected));

		$this->assertEquals([], $absent, 'Lignes du fichier absentes de la table après le semis : '.implode(', ', $absent));
		$this->assertEquals([], $surplus, 'Lignes de la table que le fichier ne porte pas : '.implode(', ', $surplus));

		$different = [];

		foreach($expected as $key => $calendar) {

			if(array_key_exists($key, $written) and $written[$key] !== $calendar) {
				$different[] = $key;
			}

		}

		$this->assertEquals([], $different, 'Lignes dont la table ne porte pas les valeurs du fichier : '.implode(', ', $different));

		$this->assertEquals(0, Calendar::model()->whereOnline(FALSE)->count(), 'Toute ligne semée est servie');

	}

	public function testSeedTwice(): void {

		$frozen = CalendarAiLib::getFrozen();

		CalendarAiLib::seed($frozen);
		$first = $this->getWritten();

		$counts = CalendarAiLib::seed($frozen);

		$this->assertEquals(count($first), $counts['calendars'], 'Un second semis écrit autant de lignes');
		$this->assertEquals($first, $this->getWritten(), 'Un second semis rend les mêmes lignes, sans en doubler aucune');
		$this->assertEquals(count($first), Calendar::model()->count(), 'La table ne porte que les lignes du fichier');

	}

	public function testMissing(): void {

		$frozen = CalendarAiLib::getFrozen();

		CalendarAiLib::seed($frozen);
		$before = $this->getWritten();

		$fqn = 'espece-absente-du-referentiel';

		$this->assertFalse(\plant\Plant::model()->whereStructure(NULL)->whereFqn($fqn)->exists(), 'Le fqn du test n\'est pas au référentiel');

		// Un fichier réduit à deux espèces, dont une absente : s'il était semé, la table n'en
		// porterait plus qu'une.
		$fqnKnown = array_key_first($frozen);

		$counts = CalendarAiLib::seed([
			$fqnKnown => $frozen[$fqnKnown],
			$fqn => $frozen[$fqnKnown],
		]);

		$this->assertEquals([$fqn], $counts['missing'], 'Le fqn absent est nommé');
		$this->assertEquals(0, $counts['calendars'], 'Rien n\'est compté comme écrit');
		$this->assertEquals($before, $this->getWritten(), 'La table reste telle qu\'avant le semis refusé');

	}

	/**
	 * Le fichier à plat, `['fqn climat' => [harvestWeeks, weeks, structures, fallback]]`.
	 */
	private function getFlat(array $frozen): array {

		$flat = [];

		foreach($frozen as $fqn => $climates) {

			foreach($climates as $climate => $calendar) {
				$flat[$fqn.' '.$climate] = [$calendar['harvestWeeks'], $calendar['weeks'], $calendar['structures'], $calendar['fallback']];
			}

		}

		return $flat;

	}

	/**
	 * La table à plat, dans la même forme que `getFlat()`.
	 */
	private function getWritten(): array {

		$cCalendar = Calendar::model()
			->select(['id', 'climate', 'plant' => ['fqn'], 'harvestWeeks', 'weeks', 'structures', 'fallback'])
			->getCollection();

		$flat = [];

		foreach($cCalendar as $eCalendar) {
			$flat[$eCalendar['plant']['fqn'].' '.$eCalendar['climate']] = [$eCalendar['harvestWeeks'], $eCalendar['weeks'], $eCalendar['structures'], $eCalendar['fallback']];
		}

		return $flat;

	}

	/**
	 * Une espèce du référentiel partagé que le fichier ne porte pas.
	 */
	private function getPlantForeign(array $frozen): \plant\Plant {

		$ePlant = \plant\Plant::model()
			->select(['id', 'fqn'])
			->whereStructure(NULL)
			->whereFqn('NOT IN', array_keys($frozen))
			->sort(['id' => SORT_ASC])
			->get();

		$this->assertTrue($ePlant->notEmpty(), 'Le référentiel porte une espèce hors du fichier');

		// La ligne du décor ne doit pas buter sur l'UNIQUE (climate, plant) d'un calcul resté en table.
		Calendar::model()
			->wherePlant($ePlant)
			->delete();

		return $ePlant;

	}

}
?>
