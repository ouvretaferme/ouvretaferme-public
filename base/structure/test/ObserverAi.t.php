<?php
namespace structure;

/**
 * Un observateur ne se voit plus proposer deux gestes que l'action refuse.
 *
 * Le chantier de la ferme école a fait tomber cinq hypothèses de la même famille — « qui peut
 * lire peut écrire ». Deux des corrections vivent **hors du drapeau** et partent donc en
 * production sur toutes les fermes : le bouton « Assoler mes séries » et les liens d'ajout de
 * saison. Le cas qu'elles changent n'est ni l'administrateur ni la ferme école, mais celui du
 * milieu : un membre observateur d'une ferme ordinaire, que personne n'avait mesuré.
 *
 * ⚠️ Les deux contrôles se lisent par paire. Un contrôle qui ne trouve plus le geste ne prouve
 * rien tant qu'il n'a pas montré qu'il sait le trouver : chaque absence attendue est donc
 * précédée de sa présence chez l'administrateur, et le bouton PDF, repère voisin, prouve que la
 * branche de l'assolement a bien été rendue pour les deux.
 *
 * Le rôle est la seule variable : les deux membres regardent **la même ferme**, la même saison
 * et la même vue.
 *
 * Lancement : dev/test package=structure file=ObserverAi
 */
class ObserverAiTest extends \dev\TransactionTest {

	/** F1, du décor, qui porte un observateur et un administrateur ; sa zone est posée par setUp() */
	const STRUCTURE = FixtureAiLib::F1;

	const EMAIL_OBSERVER = 'decor.f1.observateur@example.org';

	const EMAIL_MANAGER = FixtureAiLib::AUTHOR;

	/** État à restaurer : connecter un lecteur écrit dans des caches de processus. */
	private \user\User $eUserInitial;

	public function init(): void {
		$this->eUserInitial = \user\ConnectionLib::getOnline();
	}

	public function finalize(): void {
		\user\ConnectionLib::setOnline($this->eUserInitial);
	}

	/**
	 * Sans zone, aucun des deux gestes de l'assolement ne se rend : la parcelle est posée dans la
	 * transaction de `TransactionTest`, sur le modèle de `series/test/SeriesFixtureAi.t.php`.
	 */
	public function setUp(): void {

		parent::setUp();

		// La zone porte son auteur, que `createdBy` lit sur le compte connecté.
		$this->connectAs(self::EMAIL_MANAGER);

		// Sans build() : il aurait vérifié le nom, la surface dans ses bornes, la première saison dans celles
		// de la structure et le polygone, que ce test fixe à des valeurs valides ou laisse vides. Seule compte
		// ici l'existence d'une parcelle.
		\map\ZoneLib::create(new \map\Zone([
			'name' => 'zzTest parcelle '.uniqid(),
			'structure' => $this->getStructure(),
			'area' => 10000,
			'coordinates' => NULL,
			'seasonFirst' => NULL,
			'seasonLast' => NULL,
		]));

	}

	protected function getTransactionModel(): \ModuleModel {
		return new \map\ZoneModel();
	}

	private function connectAs(string $email): void {
		\user\ConnectionLib::setOnline(\user\UserLib::getByEmail($email));
	}

	private function getStructure(): Structure {

		return Structure::model()
			->select(Structure::getSelection())
			->whereId(self::STRUCTURE)
			->get();

	}

	/**
	 * Les deux membres lisent la ferme, et l'un seul l'administre.
	 *
	 * Sans ce préalable, une absence de geste s'expliquerait aussi bien par un accès fermé que
	 * par la garde qu'on veut prouver.
	 */
	public function testBothReadTheSameFarm(): void {

		$this->connectAs(self::EMAIL_OBSERVER);
		$eStructureObserver = $this->getStructure();

		$this->assertTrue($eStructureObserver->canRead());
		$this->assertFalse($eStructureObserver->canManage());

		$this->connectAs(self::EMAIL_MANAGER);
		$eStructureManager = $this->getStructure();

		$this->assertTrue($eStructureManager->canRead());
		$this->assertTrue($eStructureManager->canManage());

	}

	/**
	 * « Assoler mes séries » suit ce que l'action exige, c'est-à-dire l'écriture d'une série.
	 *
	 * Le geste n'est pas gardé par un droit sur la structure mais par `Series::canWrite()`, sur
	 * l'objet que l'assolement écrit réellement. Le bouton PDF, rendu dans la même branche et
	 * ouvert à tous, sert de témoin : sa présence chez les deux prouve que la vue a bien été
	 * peinte et que l'écart ne tient qu'à la garde.
	 */
	public function testSoilGestureFollowsSeriesWrite(): void {

		$season = (int)date('Y');

		$this->connectAs(self::EMAIL_MANAGER);
		$eStructureManager = $this->getStructure();
		$manager = \series\HeaderUi::getSoilTitle($eStructureManager, $season, Member::PLAN, \map\ZoneLib::getByStructure($eStructureManager));

		$this->assertTrue(str_contains($manager, 'SeriesPlace.toggleEditing()'));
		$this->assertTrue(str_contains($manager, 'data-ajax-download'));

		$this->connectAs(self::EMAIL_OBSERVER);
		$eStructureObserver = $this->getStructure();
		$observer = \series\HeaderUi::getSoilTitle($eStructureObserver, $season, Member::PLAN, \map\ZoneLib::getByStructure($eStructureObserver));

		$this->assertFalse(str_contains($observer, 'SeriesPlace.toggleEditing()'));
		$this->assertTrue(str_contains($observer, 'data-ajax-download'));

	}

	/**
	 * Ajouter une saison écrit la configuration, que seul l'administrateur ouvre.
	 *
	 * Seuls les deux liens d'écriture manquent à l'observateur.
	 *
	 * La liste est celle du jeton de saison de la bande (`getSeason()`), le seul appelant de
	 * `getSeasonsMore()` depuis que le sélecteur du gabarit (`getSeasonsTabs()`) est tombé avec les
	 * têtes de page de la production (B3 L6b).
	 */
	public function testSeasonGesturesReservedToManager(): void {

		$url = fn(int $season) => '/production/série?season='.$season;

		$this->connectAs(self::EMAIL_MANAGER);
		$eStructureManager = $this->getStructure();
		$season = $eStructureManager->conf(\map\Configuration::class)['seasonLast'];
		$manager = new StructureUi()->getSeason($eStructureManager, $url, $season);

		$this->assertTrue(str_contains($manager, 'configuration:doSeasonLast'), 'l\'administrateur ajoute la saison suivante');
		$this->assertTrue(str_contains($manager, 'configuration:doSeasonFirst'), 'l\'administrateur ajoute la saison précédente');

		$this->connectAs(self::EMAIL_OBSERVER);
		$eStructureObserver = $this->getStructure();
		$observer = new StructureUi()->getSeason($eStructureObserver, $url, $season);

		$this->assertFalse(str_contains($observer, 'configuration:doSeasonLast'), 'l\'observateur n\'ajoute pas la saison suivante');
		$this->assertFalse(str_contains($observer, 'configuration:doSeasonFirst'), 'l\'observateur n\'ajoute pas la saison précédente');

	}

}
?>
