<?php
namespace aggregate;

/**
 * Ce que la projection du calendrier type retient d'un profil de récolte, comment elle range une
 * structure dans un climat, et ce qu'un second passage change.
 *
 * Le calendrier donne sa saison à chaque espèce d'un plan de paniers parti sans plan de culture :
 * une règle qui glisse d'une semaine ou un climat déduit hors de France ne se verraient nulle part,
 * la table étant servie sans relecture. Chaque règle est éprouvée avec son pendant, pour qu'un
 * contrôle qui ne trouve rien ne passe pas pour réussi :
 *
 * 1. la saison d'un profil : seuil relatif et plancher, trous bouchés, fenêtres courtes retirées,
 *    deux fenêtres au plus, année lue en cercle, aucun morceau de moins de trois semaines lu de
 *    janvier à décembre, conservation sur la dernière saison seulement ;
 * 2. le climat : déclaré d'abord, déduit en France seulement, jamais montagnard sans altitude ;
 * 3. les lignes : une par climat pour une espèce assez cultivée, en repli sur tous les climats
 *    quand le climat compte trop peu de structures ;
 * 4. un second passage réécrit la table sans la doubler, et les lecteurs ne servent que les lignes
 *    au seuil.
 *
 * Isolation : la transaction de `TransactionTest`, annulée après chaque méthode. La réécriture de project() ouvre
 * sa propre transaction, imbriquée dans celle du test : son commit ne part pas, et le rollBack
 * final rend la table telle qu'avant.
 *
 * Lancement : dev/test package=aggregate file=CalendarProjectionAi
 */
class CalendarProjectionAiTest extends \dev\TransactionTest {

	protected function getTransactionModel(): \ModuleModel {
		return Calendar::model();
	}

	private function invoke(string $method, mixed ...$arguments): mixed {
		return new \ReflectionMethod(CalendarProjectionAiLib::class, $method)->invoke(NULL, ...$arguments);
	}

	/**
	 * Un profil de 52 semaines, à zéro sauf les semaines données : `[[première, dernière, compte]]`.
	 */
	private function getProfile(array $ranges): array {

		$counts = array_fill(1, 52, 0);

		foreach($ranges as [$first, $last, $count]) {

			foreach(range($first, $last) as $week) {
				$counts[$week] = $count;
			}

		}

		return $counts;

	}

	public function testWindowsThreshold(): void {

		// Cent structures, la meilleure semaine à 50 % : le seuil est à 20 %.
		$windows = CalendarProjectionAiLib::getWindows($this->getProfile([[20, 30, 50], [31, 31, 20], [32, 32, 19]]), 100, 0);

		$this->assertEquals(range(20, 31), $windows['harvestWeeks'], 'une semaine à 40 % de la meilleure est retenue, une semaine juste dessous ne l\'est pas');
		$this->assertEquals($windows['harvestWeeks'], $windows['weeks'], 'sans conservation, l\'espèce se livre quand elle se récolte');

		// La meilleure semaine à 20 % : 40 % de la meilleure vaudrait 8 %, le plancher de 15 % tient.
		$windows = CalendarProjectionAiLib::getWindows($this->getProfile([[20, 25, 20], [26, 26, 15], [27, 27, 14]]), 100, 0);

		$this->assertEquals(range(20, 26), $windows['harvestWeeks'], 'sous 15 % des structures, une semaine n\'est pas retenue');

	}

	public function testWindowsShape(): void {

		$windows = CalendarProjectionAiLib::getWindows($this->getProfile([[20, 25, 50], [28, 32, 50]]), 100, 0);

		$this->assertEquals(range(20, 32), $windows['harvestWeeks'], 'un trou de deux semaines est bouché');

		$windows = CalendarProjectionAiLib::getWindows($this->getProfile([[20, 25, 50], [29, 32, 50]]), 100, 0);

		$this->assertEquals(array_merge(range(20, 25), range(29, 32)), $windows['harvestWeeks'], 'un trou de trois semaines reste');

		$windows = CalendarProjectionAiLib::getWindows($this->getProfile([[10, 12, 50], [20, 30, 50]]), 100, 0);

		$this->assertEquals(range(20, 30), $windows['harvestWeeks'], 'une fenêtre de trois semaines est retirée');

		$windows = CalendarProjectionAiLib::getWindows($this->getProfile([[10, 13, 50], [20, 30, 50]]), 100, 0);

		$this->assertEquals(array_merge(range(10, 13), range(20, 30)), $windows['harvestWeeks'], 'une fenêtre de quatre semaines reste');

		// Trois fenêtres : la moins fournie tombe, par la somme de ses parts et non par sa place.
		$windows = CalendarProjectionAiLib::getWindows($this->getProfile([[5, 9, 60], [18, 21, 40], [30, 40, 50]]), 100, 0);

		$this->assertEquals(array_merge(range(5, 9), range(30, 40)), $windows['harvestWeeks'], 'deux fenêtres au plus, les plus fournies');

		// L'année est un cercle : une saison qui passe le nouvel an est une seule fenêtre.
		$windows = CalendarProjectionAiLib::getWindows($this->getProfile([[50, 52, 50], [1, 3, 50]]), 100, 0);

		$this->assertEquals([1, 2, 3, 50, 51, 52], $windows['harvestWeeks'], 'six semaines à cheval sur le nouvel an font une fenêtre, trois de chaque côté');

		// Mais le plan la lit de janvier à décembre, où elle fait deux morceaux : aucun ne garde moins de
		// trois semaines (r54, « vire les semaines isolées »).
		$windows = CalendarProjectionAiLib::getWindows($this->getProfile([[10, 52, 50], [1, 1, 50]]), 100, 0);

		$this->assertEquals(range(10, 52), $windows['harvestWeeks'], 'le débord d\'une semaine sur janvier est retiré');

		$windows = CalendarProjectionAiLib::getWindows($this->getProfile([[40, 52, 50], [1, 2, 50]]), 100, 0);

		$this->assertEquals(range(40, 52), $windows['harvestWeeks'], 'celui de deux semaines aussi');

		$windows = CalendarProjectionAiLib::getWindows($this->getProfile([[51, 52, 50], [1, 10, 50]]), 100, 0);

		$this->assertEquals(range(1, 10), $windows['harvestWeeks'], 'comme celui de deux semaines sur décembre');

		$windows = CalendarProjectionAiLib::getWindows($this->getProfile([[1, 52, 50]]), 100, 4);

		$this->assertEquals([range(1, 52), range(1, 52)], [$windows['harvestWeeks'], $windows['weeks']], 'une espèce récoltée toute l\'année');

	}

	public function testWindowsStorage(): void {

		$windows = CalendarProjectionAiLib::getWindows($this->getProfile([[20, 30, 50]]), 100, 4);

		$this->assertEquals([range(20, 30), range(20, 34)], [$windows['harvestWeeks'], $windows['weeks']], 'la conservation prolonge la livraison, pas la récolte');

		$windows = CalendarProjectionAiLib::getWindows($this->getProfile([[45, 52, 50]]), 100, 3);

		$this->assertEquals(array_merge(range(1, 3), range(45, 52)), $windows['weeks'], 'la conservation passe le nouvel an');

		$windows = CalendarProjectionAiLib::getWindows($this->getProfile([[45, 52, 50]]), 100, 2);

		$this->assertEquals(range(45, 52), $windows['weeks'], 'mais ne laisse pas en janvier un morceau de moins de trois semaines');

		$windows = CalendarProjectionAiLib::getWindows($this->getProfile([[10, 15, 50], [30, 40, 50]]), 100, 4);

		$this->assertEquals(array_merge(range(10, 15), range(30, 44)), $windows['weeks'], 'avec deux saisons, seule la dernière de l\'année se conserve');

		// La conservation qui s'arrête à deux semaines de la saison suivante la rejoint.
		$windows = CalendarProjectionAiLib::getWindows($this->getProfile([[10, 15, 50], [30, 40, 50]]), 100, 20);

		$this->assertEquals(array_merge(range(1, 15), range(30, 52)), $windows['weeks'], 'la conservation fait le tour de l\'année jusqu\'à la saison suivante');

	}

	public function testWeek(): void {

		$this->assertEquals([14, 52, 52], [$this->invoke('getWeek', '2026-W14'), $this->invoke('getWeek', '2026-W52'), $this->invoke('getWeek', '2026-W53')], 'la semaine sans son année, la 53 rangée en 52');

	}

	public function testClimateDeduced(): void {

		$this->assertEquals(Calendar::OCEANIC, CalendarAiLib::deduceClimate(48.11, -1.68, NULL), 'Rennes');
		$this->assertEquals(Calendar::OCEANIC, CalendarAiLib::deduceClimate(50.63, 3.06, NULL), 'Lille, au nord du continental');
		$this->assertEquals(Calendar::CONTINENTAL, CalendarAiLib::deduceClimate(48.58, 7.75, 140), 'Strasbourg');
		$this->assertEquals(Calendar::MEDITERRANEAN, CalendarAiLib::deduceClimate(43.61, 3.88, 30), 'Montpellier');
		$this->assertEquals(Calendar::MEDITERRANEAN, CalendarAiLib::deduceClimate(42.70, 2.90, 30), 'Perpignan');
		$this->assertEquals(Calendar::OCEANIC, CalendarAiLib::deduceClimate(43.60, 1.44, 150), 'Toulouse, à l\'ouest du pourtour');
		$this->assertEquals(Calendar::MOUNTAIN, CalendarAiLib::deduceClimate(43.61, 3.88, 600), 'à 600 m, l\'altitude passe avant la position');
		$this->assertEquals(Calendar::MEDITERRANEAN, CalendarAiLib::deduceClimate(43.61, 3.88, 599), 'à 599 m, pas encore');
		$this->assertEquals(NULL, CalendarAiLib::deduceClimate(-21.11, 55.53, 100), 'La Réunion, hors du cadre');

	}

	public function testClimateOfStructure(): void {

		$getStructure = fn(?int $country, ?string $postcode) => new \structure\Structure([
			'id' => 1,
			'legalCountry' => ($country === NULL) ? new \user\Country() : new \user\Country(['id' => $country]),
			'legalPostcode' => $postcode,
		]);

		$getSystem = fn(?string $climate, ?array $lngLat, ?int $altitude) => new \structure\System([
			'id' => 1,
			'climate' => $climate,
			'placeLngLat' => $lngLat,
			'altitude' => $altitude,
		]);

		$france = 75;
		$elsewhere = 76;

		$this->assertEquals(Calendar::MEDITERRANEAN, CalendarAiLib::getClimateBySystem($getStructure($france, '35000'), $getSystem(NULL, [3.88, 43.61], NULL)), 'en France, le site placé passe avant l\'adresse');
		$this->assertEquals(Calendar::OCEANIC, CalendarAiLib::getClimateBySystem($getStructure($france, '35000'), $getSystem(NULL, NULL, NULL)), 'en France sans site placé, le centre du département');
		$this->assertEquals(Calendar::OCEANIC, CalendarAiLib::getClimateBySystem($getStructure($france, '35000'), new \structure\System()), 'en France sans système, le centre du département');
		$this->assertEquals(Calendar::MOUNTAIN, CalendarAiLib::getClimateBySystem($getStructure($france, '35000'), $getSystem(NULL, NULL, 900)), 'l\'altitude du système range en montagne');
		$this->assertEquals(NULL, CalendarAiLib::getClimateBySystem($getStructure($france, NULL), $getSystem(NULL, NULL, NULL)), 'en France sans site ni code postal, aucun climat');
		$this->assertEquals(NULL, CalendarAiLib::getClimateBySystem($getStructure($france, '99999'), new \structure\System()), 'un code postal sans département, aucun climat');

		// Hors de France, le même point que plus haut ne donne rien : le climat ne s'y déduit jamais.
		$this->assertEquals(NULL, CalendarAiLib::getClimateBySystem($getStructure($elsewhere, '35000'), $getSystem(NULL, [3.88, 43.61], NULL)), 'hors de France, aucun climat déduit');
		$this->assertEquals(NULL, CalendarAiLib::getClimateBySystem($getStructure(NULL, '35000'), $getSystem(NULL, [3.88, 43.61], NULL)), 'sans pays, aucun climat déduit');
		$this->assertEquals(Calendar::CONTINENTAL, CalendarAiLib::getClimateBySystem($getStructure($elsewhere, NULL), $getSystem(Calendar::CONTINENTAL, NULL, NULL)), 'hors de France, le climat déclaré');
		$this->assertEquals(Calendar::CONTINENTAL, CalendarAiLib::getClimateBySystem($getStructure($france, '35000'), $getSystem(Calendar::CONTINENTAL, [3.88, 43.61], 900)), 'le climat déclaré passe avant tout ce qui se déduit');

	}

	/**
	 * Une espèce cultivée par 30 structures, dont 26 en océanique et 4 en continental : l'océanique
	 * dessine sa saison, les trois autres climats prennent celle de tous. Une espèce à 24 structures
	 * n'entre pas.
	 */
	public function testCalendar(): void {

		$weeks = [];
		$climates = [];

		foreach(range(1, 30) as $structureId) {

			$oceanic = ($structureId <= 26);

			if($oceanic) {
				$climates[$structureId] = Calendar::OCEANIC;
			} else if($structureId <= 29) {
				$climates[$structureId] = Calendar::CONTINENTAL;
			} else {
				// Une structure sans climat compte dans le profil de tous les climats.
				$climates[$structureId] = NULL;
			}

			$weeks[$structureId][101] = array_fill_keys($oceanic ? range(20, 30) : range(34, 44), TRUE);

			if($structureId <= 24) {
				$weeks[$structureId][102] = array_fill_keys(range(20, 30), TRUE);
			}

		}

		$counts = $this->invoke('getCounts', $weeks, $climates);

		$this->assertEquals([30, 26, 3], [$counts[''][101]['structures'], $counts[Calendar::OCEANIC][101]['structures'], $counts[Calendar::CONTINENTAL][101]['structures']], 'les structures par climat, celle sans climat dans le seul total');
		$this->assertEquals([26, 4], [$counts[''][101]['weeks'][25], $counts[''][101]['weeks'][40]], 'les structures qui récoltent une semaine');

		$cPlant = new \Collection([
			new \plant\Plant(['id' => 101, 'fqn' => 'espece-cultivee', 'storage' => 2]),
			new \plant\Plant(['id' => 102, 'fqn' => 'espece-rare', 'storage' => 0]),
		]);

		$cCalendar = $this->invoke('getCalendar', $counts, $cPlant);

		$rows = [];

		foreach($cCalendar as $eCalendar) {

			$this->assertEquals(101, $eCalendar['plant']['id'], 'une espèce à 24 structures n\'entre pas au calendrier');

			$rows[$eCalendar['climate']] = $eCalendar->extracts(['harvestWeeks', 'weeks', 'structures', 'fallback', 'online']);

		}

		$this->assertEquals([Calendar::OCEANIC, Calendar::CONTINENTAL, Calendar::MEDITERRANEAN, Calendar::MOUNTAIN], array_keys($rows), 'une ligne par climat');

		$this->assertEquals(
			['harvestWeeks' => range(20, 30), 'weeks' => range(20, 32), 'structures' => 26, 'fallback' => FALSE, 'online' => TRUE],
			$rows[Calendar::OCEANIC],
			'un climat à 26 structures dessine sa saison'
		);

		foreach([Calendar::CONTINENTAL, Calendar::MEDITERRANEAN, Calendar::MOUNTAIN] as $climate) {

			$this->assertEquals(
				['harvestWeeks' => range(20, 30), 'weeks' => range(20, 32), 'structures' => 30, 'fallback' => TRUE, 'online' => TRUE],
				$rows[$climate],
				$climate.' : sous 25 structures, la saison de tous les climats'
			);

		}

	}

	/**
	 * Deux passages sur la base de dev : la table est refaite sans être doublée, le relevé est le
	 * même, aucune ligne ne désigne l'espèce propre d'une structure, et un lecteur ne sert qu'une
	 * ligne au seuil.
	 */
	public function testProjectTwice(): void {

		$read = fn() => Calendar::model()
			->select(['climate', 'plant', 'harvestWeeks', 'weeks', 'structures', 'fallback', 'online'])
			->sort(['climate' => SORT_ASC, 'plant' => SORT_ASC])
			->getCollection()
			->makeArray(fn(Calendar $eCalendar) => [$eCalendar['climate'], $eCalendar['plant']['id'], $eCalendar['harvestWeeks'], $eCalendar['weeks'], $eCalendar['structures'], $eCalendar['fallback'], $eCalendar['online']]);

		$statsFirst = CalendarProjectionAiLib::project();
		$rowsFirst = $read();

		$statsSecond = CalendarProjectionAiLib::project();
		$rowsSecond = $read();

		$this->assertTrue($statsFirst['calendars'] > 0, 'la base de dev donne des lignes : sinon le contrôle ne verrait rien');
		$this->assertEquals($statsFirst, $statsSecond, 'le second passage rend le même relevé');
		$this->assertEquals($statsFirst['calendars'], count($rowsSecond), 'la table n\'est pas doublée');
		$this->assertTrue($rowsFirst === $rowsSecond, 'et porte les mêmes lignes');

		$plantIds = array_values(array_unique(array_column($rowsSecond, 1)));

		$own = \plant\Plant::model()
			->whereId('IN', $plantIds)
			->whereStructure('!=', NULL)
			->count();

		$this->assertEquals(0, $own, 'aucune ligne ne désigne l\'espèce propre d\'une structure');

		$wrong = array_filter($rowsSecond, fn(array $row) => (
			array_diff($row[2], $row[3]) !== [] or
			$row[2] === [] or
			$row[4] < CalendarProjectionAiLib::STRUCTURES_MIN
		));

		$this->assertEquals(0, count($wrong), 'chaque ligne se livre au moins quand elle se récolte, et compte assez de structures');

		$cCalendar = CalendarAiLib::getByClimate(Calendar::OCEANIC);

		$this->assertTrue($cCalendar->count() > 0, 'le lecteur sert l\'océanique');

		$eCalendar = $cCalendar->first();
		$plantId = $eCalendar['plant']['id'];

		// Écrit sans build() ni Lib : le test fabrique la ligne sous le seuil que la projection
		// n'écrit jamais aujourd'hui, pour voir le lecteur l'écarter ; il n'y a aucune saisie à valider.
		Calendar::model()->update($eCalendar, ['online' => FALSE]);

		$this->assertFalse(CalendarAiLib::getByClimate(Calendar::OCEANIC)->offsetExists($plantId), 'une ligne sous le seuil n\'est pas servie');

	}

}
?>
