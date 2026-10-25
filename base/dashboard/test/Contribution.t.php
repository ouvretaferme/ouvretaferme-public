<?php
namespace dashboard;

/**
 * Le rangement des pièces du poste : par emplacement, puis par ce que l'emplacement promet.
 *
 * Ce sont des règles de composition, invisibles jusqu'à ce que deux packages se retrouvent dans le même
 * emplacement, et aucune ne se voit sur un écran où une seule pièce paraît. Elles sont donc éprouvées
 * ici, sur des déclarations fabriquées à la main : aucune base ouverte, aucune structure, aucun package
 * réel, le moteur ne nommant de toute façon personne.
 *
 * Les tris sont privés et le restent : les éprouver par réflexion coûte moins que de les rendre publics
 * pour un test, et `loadContributions()`, qui les appelle, demande une structure et les droits qui vont
 * avec.
 *
 * Lancement : dev/test package=dashboard file=Contribution
 */
class ContributionTest extends \Test {

	/**
	 * Les emplacements se suivent dans l'ordre de `SLOTS` et non dans celui où les packages ont été
	 * balayés, et un emplacement sans occupant est **absent** plutôt que vide : le socle en déduit qu'il
	 * n'a rien à dessiner, et ne pose pas un cadre sur du vide.
	 */
	public function testSlotsAreRankedInTheOrderOfTheScreen(): void {

		$sorted = self::sortBySlot([
			DashboardSetting::QUEUE => ['x-one' => self::getEntry()],
			DashboardSetting::FIGURE => ['x-two' => self::getEntry()],
			DashboardSetting::BILAN => ['x-three' => self::getEntry()]
		]);

		$this->assertEquals([DashboardSetting::BILAN, DashboardSetting::FIGURE, DashboardSetting::QUEUE], array_keys($sorted), "les emplacements se suivent dans l'ordre de SLOTS");

		$sorted = self::sortBySlot([
			DashboardSetting::QUEUE => ['x-one' => self::getEntry()]
		]);

		$this->assertEquals(FALSE, array_key_exists(DashboardSetting::FIGURE, $sorted), "un emplacement sans occupant n'est pas une clé vide");

	}

	/**
	 * Dans un emplacement ordinaire, le rang est celui que le package a posé, et deux rangs égaux gardent
	 * l'ordre de déclaration, qui est celui de `\Package::getList()`. C'est ce qui rend l'ordre déterminé
	 * sans qu'un package ait à connaître les autres.
	 */
	public function testFiguresAreRankedByPosition(): void {

		$sorted = self::sortBySlot([
			DashboardSetting::FIGURE => [
				'x-third' => self::getEntry(position: 20),
				'x-first' => self::getEntry(position: 10),
				'x-second' => self::getEntry(position: 10)
			]
		]);

		$this->assertEquals(['x-first', 'x-second', 'x-third'], array_keys($sorted[DashboardSetting::FIGURE]), "le rang range la rangée, et l'égalité garde l'ordre de déclaration");

	}

	/**
	 * La file se range par ce qu'elle attend : l'échéance la plus proche en tête, puis la plus longue
	 * attente, puis le rang. Jamais l'univers, et jamais l'ordre des packages tant qu'une des trois
	 * mesures les sépare.
	 *
	 * Une clé que le package ne porte pas est posée à `NULL` par `loadContributions()` : « absente » et
	 * « à NULL » sont donc le même cas, et il range la ligne en dernier.
	 */
	public function testQueueIsRankedByWhatItWaitsFor(): void {

		$sorted = self::sortBySlot([
			DashboardSetting::QUEUE => [
				'x-nothing' => self::getEntry(),
				'x-waiting' => self::getEntry(waitingDays: 3),
				'x-waiting-longer' => self::getEntry(waitingDays: 12),
				'x-tomorrow' => self::getEntry(dueAt: '2026-09-30'),
				'x-today' => self::getEntry(dueAt: '2026-09-29')
			]
		]);

		$this->assertEquals(
			['x-today', 'x-tomorrow', 'x-waiting-longer', 'x-waiting', 'x-nothing'],
			array_keys($sorted[DashboardSetting::QUEUE]),
			"l'échéance d'abord, la plus proche en tête, puis l'attente la plus longue, puis ce qui n'attend rien de daté"
		);

		$sorted = self::sortBySlot([
			DashboardSetting::QUEUE => [
				'x-late' => self::getEntry(dueAt: '2026-09-29', position: 20),
				'x-early' => self::getEntry(dueAt: '2026-09-29', position: 10)
			]
		]);

		$this->assertEquals(['x-early', 'x-late'], array_keys($sorted[DashboardSetting::QUEUE]), "à échéance et attente égales, le rang tranche");

	}

	/** Une contribution du bilan dit la section dont sa bande prend la couleur : le socle refuse de la deviner. */
	public function testBilanContributionsDeclareTheirSection(): void {

		foreach(DashboardLib::getContributions() as $contribution => $declaration) {

			if($declaration['slot'] === DashboardSetting::BILAN) {
				$this->assertTrue(in_array($declaration['section'], DashboardSetting::SECTIONS, TRUE), $contribution.' déclare une section connue');
			}

		}

		$this->assertEquals('commercialisation', DashboardLib::getContributions()['selling-bilan']['section'], 'le bilan des ventes en couleur de Vente');

	}

	/**
	 * Les défauts d'une contribution, clé par clé.
	 *
	 * `max` n'y est pas, et c'est le seul écart avec les défauts d'un bloc : une contribution ne rend pas
	 * de liste, un plafond d'items n'y aurait pas de lecteur. `cost` y est, à `light`, parce que le
	 * contrat est figé, mais le poste est rendu dans la page : la sentinelle refuse aujourd'hui une
	 * contribution lourde. `section` n'a de lecteur que dans l'emplacement du bilan, qui l'exige.
	 * `choose` y est à `FALSE` parce que la cellule du bilan est rendue par `DashboardUi::getBlock()`,
	 * qui la lit : sans elle, l'accueil du lundi levait « Undefined array key "choose" ». `shape` y est
	 * à la tuile pour la même raison, la tuile du bilan passant par la même méthode.
	 */
	public function testContributionDefaultsSayWhatTheContractCarries(): void {

		$method = new \ReflectionMethod(DashboardLib::class, 'getContributionDefaults');
		$method->setAccessible(TRUE);

		$this->assertEquals(
			[
				'section' => NULL,
				'feature' => NULL,
				'right' => 'canRead',
				'accept' => [],
				'default' => TRUE,
				'cost' => DashboardSetting::LIGHT,
				'cache' => 0,
				'position' => 0,
				'shape' => DashboardSetting::TILE,
				'choose' => FALSE
			],
			$method->invoke(NULL),
			"les défauts d'une contribution sont ceux du contrat, sans plafond d'items"
		);

	}

	private static function sortBySlot(array $contributions): array {

		$method = new \ReflectionMethod(DashboardLib::class, 'sortBySlot');
		$method->setAccessible(TRUE);

		return $method->invoke(NULL, $contributions);

	}

	/** Une pièce telle que `getEntry()` la rend, réduite à ce que les tris lisent. */
	private static function getEntry(?string $dueAt = NULL, ?int $waitingDays = NULL, int $position = 0): array {

		return [
			'declaration' => ['position' => $position],
			'url' => '/',
			'data' => ['dueAt' => $dueAt, 'waitingDays' => $waitingDays]
		];

	}

}
?>
