<?php
namespace structure;

/**
 * Le décor des tests de groupes : trois groupes, les fermes que deux comptes y apportent, une ferme
 * projet et une ferme fermée, lus dans le décor des tests (`FixtureAiLib`, G4 à G6, F6 à F11 et P1).
 *
 * Ce fichier ne pose plus rien. Il semait autrefois ses groupes et ses fermes à l'ouverture, hors de
 * toute transaction, et empruntait trois fermes réelles de la copie de production (7, 62 et 98) et
 * l'utilisateur 1 : un rechargement de la dev ou une saisie dans ces fermes changeait ce que les
 * tests mesuraient. Le décor se monte désormais par `structure/configure/fixtureAi`, en dev comme
 * en production, et `init()` vérifie seulement qu'il est en place : un écart fait échouer chaque
 * contrôle en le nommant, par `assertFixtures()`.
 *
 * Ce que le décor ne porte pas, la production d'une ferme (séries, cultures, surfaces, ateliers),
 * chaque test le pose dans sa transaction : une saison figée dans le décor vieillirait d'une année
 * à l'autre.
 */
abstract class CommunityFixtureAiTest extends \Test {

	/** Administrateur des trois groupes et des fermes qu'il y apporte */
	const EMAIL_USER = 'decor.c.admin@example.org';

	/** Le second lecteur : membre des deux groupes partageants et de la ferme d'un autre associé, de rien d'autre */
	const EMAIL_USER_OTHER = 'decor.c.lecteur@example.org';

	/** Ferme apportée par le lecteur principal aux deux groupes partageants */
	const STRUCTURE_SHARED = FixtureAiLib::F6;

	/** Ferme apportée par le lecteur principal au seul groupe silencieux */
	const STRUCTURE_SILENT = FixtureAiLib::F8;

	/** Ferme du seul groupe partageant, à 53,6 km de STRUCTURE_SHARED : c'est elle qui porte la distance */
	const STRUCTURE_SHARING_ONLY = FixtureAiLib::F7;

	/** La tomate du référentiel, que chaque ferme reçoit à sa création */
	const PLANT_REFERENCE = 136;

	const NAME_COMMUNITY_SHARING = FixtureAiLib::DECOR[FixtureAiLib::G4]['name'];
	const NAME_COMMUNITY_SILENT = FixtureAiLib::DECOR[FixtureAiLib::G5]['name'];
	const NAME_COMMUNITY_SHARING_BIS = FixtureAiLib::DECOR[FixtureAiLib::G6]['name'];
	const NAME_STRUCTURE_PROJECT = FixtureAiLib::DECOR[FixtureAiLib::P1]['name'];
	const NAME_STRUCTURE_SHARED_WITHOUT_PRODUCTION = FixtureAiLib::DECOR[FixtureAiLib::F9]['name'];
	const NAME_STRUCTURE_CLOSED = FixtureAiLib::DECOR[FixtureAiLib::F10]['name'];
	const NAME_STRUCTURE_OTHER_ASSOCIATE = FixtureAiLib::DECOR[FixtureAiLib::F11]['name'];

	/** Le lecteur principal, retrouvé par son courriel : son identifiant change d'une base à l'autre */
	protected int $user;

	/** Le second lecteur, retrouvé de même */
	protected int $userOther;

	protected int $communitySharing = FixtureAiLib::G4;
	protected int $communitySilent = FixtureAiLib::G5;
	protected int $communitySharingBis = FixtureAiLib::G6;
	protected int $structureProject = FixtureAiLib::P1;
	protected int $structureSharedWithoutProduction = FixtureAiLib::F9;
	protected int $structureClosed = FixtureAiLib::F10;
	protected int $structureOtherAssociate = FixtureAiLib::F11;

	/** Les écarts du décor, quand il n'est pas en place */
	private ?string $decorFailure = NULL;

	/** État à restaurer : les tests connectent des utilisateurs dans des caches de processus. */
	private \user\User $eUserInitial;

	public function init(): void {

		$this->eUserInitial = \user\ConnectionLib::getOnline();

		// ⚠️ Une exception qui sort de init() interrompt le fichier sans afficher de bilan, et le
		// pre-commit, qui ne trouve alors aucun « Passed: », prend le fichier pour non exécuté et
		// laisse passer le commit. Le refus se garde donc, pour que chaque contrôle le rende en échec
		// nommé par assertFixtures().
		try {
			$this->assertDecor();
		} catch(\Throwable $e) {
			$this->decorFailure = $e->getMessage().' ('.$e->getFile().':'.$e->getLine().')';
		}

		$this->forgetMemoized();

		\user\ConnectionLib::setOnline($this->eUserInitial);

	}

	public function finalize(): void {
		\user\ConnectionLib::setOnline($this->eUserInitial);
	}

	/**
	 * ⚠️ Le relevé du décor remplit des mémoïsations de processus que les tests liraient ensuite comme
	 * si elles venaient de la base : `Structure::community()` garde un groupe tel qu'il l'a vu, et un
	 * test qui change le partage de ce groupe lirait celui d'avant sa propre écriture. Les tests qui
	 * changent un groupe ou une adhésion en cours de méthode l'appellent aussi.
	 *
	 * Aucune de ces classes n'offre de purge : chaque statique qu'elles déclarent reprend donc sa
	 * valeur de départ, ce qui couvre aussi les mémoïsations qu'on leur ajoutera.
	 */
	protected function forgetMemoized(): void {

		foreach([Structure::class, ConfigurationLib::class, FeatureLib::class, MemberLib::class, AffiliationAiLib::class] as $class) {

			foreach(new \ReflectionClass($class)->getProperties(\ReflectionProperty::IS_STATIC) as $property) {

				if($property->getDeclaringClass()->getName() === $class and $property->hasDefaultValue()) {
					$property->setValue(NULL, $property->getDefaultValue());
				}

			}

		}

	}

	/** Pour un `setUp()` qui pose de la production : l'échec se nomme une fois, dans le corps du test */
	protected function hasDecor(): bool {
		return ($this->decorFailure === NULL);
	}

	protected function assertFixtures(): bool {

		if($this->decorFailure !== NULL) {
			$this->assertFail('décor des groupes absent ou dérivé : '.$this->decorFailure);
			return FALSE;
		}

		return TRUE;

	}

	protected function getStructure(int $id): Structure {

		return Structure::model()
			->select(Structure::getSelection())
			->whereId($id)
			->get();

	}

	/**
	 * Une série et une mise en culture de la saison en cours, pour les tests qui lisent la production
	 * d'une ferme du décor. L'appelant les pose dans sa transaction, qui les emporte.
	 *
	 * L'espèce est celle que la ferme a reçue à sa création pour la référence donnée : deux fermes ne
	 * cultivent la même espèce, aux yeux d'un groupe, que par leur référence commune.
	 */
	protected function createCultivation(int $structure, int $reference = self::PLANT_REFERENCE): \series\Cultivation {

		$eStructure = $this->getStructure($structure);

		$ePlant = \plant\Plant::model()
			->select(['id', 'name'])
			->whereStructure($eStructure)
			->whereReference($reference)
			->get();

		if($ePlant->empty()) {
			throw new \Exception('la ferme '.$structure.' n\'a pas d\'espèce de référence '.$reference);
		}

		$eUserOnline = \user\ConnectionLib::getOnline();

		// `series\Series.createdBy` est un `SPECIAL(user)` non nul : la série exige un lecteur connecté.
		\user\ConnectionLib::setOnline(\user\UserLib::getById($this->user, \user\User::getSelection()));

		$season = \Clock::year();
		$weeks = [$season.'-W27', $season.'-W28', $season.'-W29'];

		// Sans `build()` : l'écran ne crée une série qu'avec ses cultures et son itinéraire
		// (`SeriesLib::prepareCreate()`), et ces tests ne lisent qu'une culture d'une espèce du
		// référentiel, sur une saison et des semaines de récolte qu'ils choisissent.
		$eSeries = new \series\Series([
			'structure' => $eStructure,
			'sequence' => new \sequence\Sequence(),
			'name' => $ePlant['name'],
			'cycle' => \series\Series::ANNUAL,
			'use' => \series\Series::BLOCK,
			'mode' => \series\Series::OPEN_FIELD,
			'season' => $season,
			'plants' => 1,
			'areaTarget' => 100,
			'perennialFirst' => new \series\Series(),
			'duplicateOf' => new \series\Series(),
		]);

		\series\SeriesLib::create($eSeries);

		// Des semaines de récolte posées, comme l'écran les pose quand la récolte se prévoit à la
		// semaine : la frise de comparaison les dessine, et une culture sans elles n'y montrerait rien.
		$eCultivation = new \series\Cultivation([
			'structure' => $eStructure,
			'season' => $season,
			'series' => $eSeries,
			'sequence' => new \sequence\Sequence(),
			'crop' => new \sequence\Crop(),
			'plant' => $ePlant,
			'distance' => \series\Cultivation::SPACING,
			'rows' => NULL,
			'rowSpacing' => NULL,
			'plantSpacing' => NULL,
			'density' => NULL,
			'varietiesUnit' => \series\Cultivation::PERCENT,
			'varietiesTool' => new \work\Tool(),
			'mainUnit' => \series\Cultivation::KG,
			'harvestPeriodExpected' => \series\Cultivation::WEEK,
			'harvestWeeksExpected' => $weeks,
			'harvestMonthsExpected' => \util\DateLib::convertWeeksToMonths($weeks),
			'cSlice' => new \Collection(),
		]);

		\series\CultivationLib::create($eCultivation);

		\user\ConnectionLib::setOnline($eUserOnline);

		return $eCultivation;

	}

	/**
	 * Le décor des groupes est-il en place : les comptes, les trois groupes et les sept fermes. Les
	 * autres structures du décor ne sont pas relevées, un écart chez elles ne dit rien de ces tests.
	 */
	private function assertDecor(): void {

		$gaps = FixtureAiLib::check([
			FixtureAiLib::G4, FixtureAiLib::G5, FixtureAiLib::G6,
			FixtureAiLib::F6, FixtureAiLib::F7, FixtureAiLib::F8, FixtureAiLib::P1, FixtureAiLib::F9, FixtureAiLib::F10, FixtureAiLib::F11,
		]);

		if($gaps !== []) {
			throw new \Exception(implode(' ; ', $gaps).'. Le décor se monte par structure/configure/fixtureAi write=1');
		}

		$cUser = \user\User::model()
			->select(['id', 'email'])
			->whereEmail('IN', [self::EMAIL_USER, self::EMAIL_USER_OTHER])
			->getCollection(index: 'email');

		$this->user = $cUser[self::EMAIL_USER]['id'];
		$this->userOther = $cUser[self::EMAIL_USER_OTHER]['id'];

	}

}
?>
