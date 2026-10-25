<?php
namespace structure;

/**
 * La catégorie d'une ferme (`producerType`) à sa création : ce que l'étape `type` accepte, ce
 * qu'elle refuse, et la garde de `StructureLib::create()` (chantier fermes-reelles, lots L1 et L2).
 *
 * Les contrôles de `build()` et de la garde n'écrivent rien : l'étape tourne en mémoire, et la
 * garde lève avant d'ouvrir sa transaction, ce que le test vérifie en cherchant la ferme par son
 * nom après coup.
 *
 * Le repli « valeur non postée → REAL » que L1 avait posé est retiré depuis L2 : le formulaire de
 * `/demarrer/ferme` demande la catégorie, et une ferme de producteur qui n'en poste aucune est
 * refusée. Ce que L2 en a fait ici : `testUnpostedValueIsRefused()` remplace
 * `testUnpostedValueIsPresumedReal()`, `testRealChainPostsReal()` remplace
 * `testRealChainPresumesReal()`, et la ferme semée change de nom parce qu'elle prouve autre chose.
 *
 * La chaîne réelle de `ModulePage::doCreate()` (`framework/core/Module.php:5322-5327`) crée une
 * base `structure_<id>`, ce qu'aucune transaction n'annule : la ferme est semée une fois dans
 * `init()` par cette chaîne, puis retrouvée par son nom. Son propriétaire est fictif, en
 * example.org, pour qu'aucun compte réel ne reçoive une ferme de test.
 *
 * Lancement : dev/test package=structure file=ProducerTypeAi
 */
class ProducerTypeAiTest extends \Test {

	/**
	 * La ferme semée par la chaîne réelle. Un semis retrouvé ne se recrée pas : la chaîne n'est jouée
	 * qu'au lancement qui la sème, et les suivants relisent ce qu'elle a écrit alors. Le nom change
	 * donc avec le code qu'il prouve, et L2 l'a renommé en retirant le repli.
	 */
	const NAME_CHOSEN = 'zzTest catégorie choisie';
	const NAME_REFUSED = 'zzTest catégorie absente';

	/** Un domaine réservé, où rien n'est jamais délivré. */
	const EMAIL_OWNER = 'zztest.categorie@example.org';

	/** La raison du refus, quand le semis de la chaîne réelle n'a pas pu aller au bout */
	private ?string $seedFailure = NULL;

	private Structure $eStructureChosen;

	private \user\User $eUserInitial;

	public function init(): void {

		$this->eUserInitial = \user\ConnectionLib::getOnline();

		// Une exception qui sort de init() interrompt le fichier sans bilan : elle se garde, et le
		// contrôle qui dépend du semis la rend en échec nommé.
		try {
			$this->eStructureChosen = $this->seedChosen();
		} catch(\Throwable $e) {
			$this->seedFailure = $e->getMessage().' ('.$e->getFile().':'.$e->getLine().')';
		}

		\user\ConnectionLib::setOnline($this->eUserInitial);

	}

	public function finalize(): void {
		\user\ConnectionLib::setOnline($this->eUserInitial);
	}

	/** Les quatre catégories que l'on choisit passent telles quelles. */
	public function testChoosableValuesAreAccepted(): void {

		foreach([Structure::REAL, Structure::TEST, Structure::LEARNER, Structure::INSTALLATION] as $producerType) {

			[$eStructure, $fw] = $this->buildProducer(['producerType' => $producerType]);

			$this->assertTrue($fw->ok(), $producerType.' accepté ('.json_encode($fw->get()).')');
			$this->assertEquals($producerType, $eStructure['producerType'], $producerType.' gardé');

		}

	}

	/**
	 * La ferme projet d'un groupe, la ferme école et la ferme de formation ne naissent jamais par le
	 * formulaire, et une valeur hors de l'enum non plus : toutes tombent sur l'étape `type`, qui a son
	 * message.
	 */
	public function testOtherValuesAreRefused(): void {

		foreach([Structure::PROJECT, Structure::SCHOOL, Structure::TRAINING, 'zz-hors-enum'] as $producerType) {

			[, $fw] = $this->buildProducer(['producerType' => $producerType]);

			$this->assertTrue($fw->has('Structure::producerType.type'), $producerType.' refusé par l\'étape type ('.json_encode($fw->get()).')');

		}

		$this->assertTrue(AlertUi::getError('Structure::producerType.type', []) !== NULL, 'le refus a un message');
		$this->assertEquals(NULL, AlertUi::getError('Structure::producerType.inconnu', []), 'un fail sans entrée rend NULL');

	}

	/**
	 * Depuis L2, aucune catégorie n'est présumée : une ferme de producteur qui n'en poste pas est
	 * refusée par `chosen`, clé absente comme chaîne vide.
	 */
	public function testUnpostedValueIsRefused(): void {

		foreach(['non postée' => [], 'vide' => ['producerType' => '']] as $label => $input) {

			[$eStructure, $fw] = $this->buildProducer($input);

			$this->assertTrue($fw->has('Structure::producerType.chosen'), 'valeur '.$label.' refusée ('.json_encode($fw->get()).')');
			// L'étape refuse avant `set`, donc la propriété n'est pas seulement nulle : elle n'existe pas.
			$this->assertEquals(FALSE, $eStructure->offsetExists('producerType'), 'valeur '.$label.' : rien de posé');

		}

		$this->assertTrue(AlertUi::getError('Structure::producerType.chosen', []) !== NULL, 'le refus a un message');

	}

	/** Hors d'une ferme, la catégorie ne se pose pas, même postée. */
	public function testOtherTypesHaveNoCategory(): void {

		foreach([Structure::COMMUNITY, Structure::SHOP, Structure::AMAP] as $type) {

			$eStructure = new Structure(['type' => $type]);

			$fw = new \FailWatch();

			$eStructure->build(['producerType'], ['producerType' => Structure::TEST], new \PropertyList('create'));

			$this->assertTrue($fw->ok(), $type.' : rien de refusé ('.json_encode($fw->get()).')');
			$this->assertEquals(NULL, $eStructure['producerType'], $type.' : catégorie effacée');

		}

	}

	/**
	 * Une ferme construite par tableau, hors de `build()`, ne passe pas l'étape : c'est la garde de
	 * `create()` qui l'arrête, clé absente comme valeur nulle.
	 */
	public function testCreateRefusesProducerWithoutCategory(): void {

		foreach([
			'clé absente' => [],
			'valeur nulle' => ['producerType' => NULL],
		] as $label => $values) {

			$eStructure = new Structure([
				'type' => Structure::PRODUCER,
				'name' => self::NAME_REFUSED,
				'legalEmail' => self::EMAIL_OWNER,
				...$values
			]);

			try {
				StructureLib::create($eStructure);
				$this->assertFail($label.' : la création aurait dû lever');
			} catch(\Exception $e) {
				$this->assertEquals('A producer is created without producerType', $e->getMessage(), $label.' : refus de la garde');
			}

		}

		$this->assertEquals(0, Structure::model()->whereName(self::NAME_REFUSED)->count(), 'aucune ligne écrite avant le refus');

	}

	/**
	 * La chaîne du produit, contre le code écrit : le POST de `/demarrer/ferme` répond « oui » à la
	 * question de l'installation, la ferme naît REAL, et `StructureLib::create()` date la catégorie
	 * parce qu'elle a été choisie. Il ne prouve la chaîne qu'au lancement qui sème `NAME_CHOSEN`.
	 */
	public function testRealChainPostsReal(): void {

		if($this->seedFailure !== NULL) {
			$this->assertFail('semis de la ferme présumée refusé : '.$this->seedFailure);
			return;
		}

		$eStructure = Structure::model()
			->select(['producerType', 'creation', 'producerTypeUpdatedAt'])
			->whereId($this->eStructureChosen['id'])
			->get();

		$this->assertEquals(Structure::REAL, $eStructure['producerType'], 'catégorie choisie en base');
		$this->assertTrue($eStructure['producerTypeUpdatedAt'] !== NULL, 'choisie, donc datée');
		$this->assertEquals(NULL, $eStructure['creation'], 'création ordinaire');

	}

	private function buildProducer(array $input): array {

		$eStructure = new Structure(['type' => Structure::PRODUCER]);

		$fw = new \FailWatch();

		$eStructure->build(StructureLib::getPropertiesCreate()($eStructure), $this->getInput() + $input, new \PropertyList('create'));

		return [$eStructure, $fw];

	}

	/** Le POST de `/demarrer/ferme`, dont la catégorie n'est posée que par l'appelant qui la veut. */
	private function getInput(string $name = self::NAME_REFUSED): array {

		return [
			'name' => $name,
			'isLegal' => 0,
			'legalEmail' => self::EMAIL_OWNER,
			'legalCountry' => \user\UserSetting::FR,
			'startedAt' => date('Y'),
			'organic' => Structure::NO,
			'qualities' => [],
		];

	}

	/**
	 * Rejoue `ModulePage::doCreate()` : élément de `getCreateElement()`, `getPropertiesCreate()`,
	 * `build()`, `validate()`, `create()`. La ferme se retrouve ensuite par son nom.
	 */
	private function seedChosen(): Structure {

		$eStructure = Structure::model()
			->select(['id'])
			->whereName(self::NAME_CHOSEN)
			->get();

		if($eStructure->notEmpty()) {
			return $eStructure;
		}

		$eUser = $this->seedOwner();

		\user\ConnectionLib::setOnline($eUser);

		$eStructure = new Structure([
			'type' => Structure::PRODUCER,
			'owner' => $eUser,
		]);

		$fw = new \FailWatch();

		$eStructure->build(StructureLib::getPropertiesCreate()($eStructure), $this->getInput(self::NAME_CHOSEN) + ['producerType' => Structure::REAL], new \PropertyList('create'));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('construction refusée : '.json_encode($fw->get())));

		StructureLib::create($eStructure);

		return $eStructure;

	}

	private function seedOwner(): \user\User {

		$eUser = \user\User::model()
			->select(\user\User::getSelection())
			->whereEmail(self::EMAIL_OWNER)
			->get();

		if($eUser->notEmpty()) {
			return $eUser;
		}

		$eUser = new \user\User([
			'firstName' => 'zzTest',
			'lastName' => 'Catégorie',
			'email' => self::EMAIL_OWNER,
			'visibility' => \user\User::PRIVATE,
			'roleCustomer' => FALSE,
			'roleProducer' => TRUE,
			'roleConsultant' => FALSE,
			'roleTeacher' => FALSE
		]);

		$fw = new \FailWatch();

		\user\UserLib::create($eUser);

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('création du propriétaire fictif refusée : '.json_encode($fw->get())));

		return \user\UserLib::getById($eUser['id'], \user\User::getSelection());

	}

}
?>
