<?php
namespace main;

/**
 * Deux écrans communs lus par un utilisateur au profil client.
 *
 * 1. L'accueil d'un client ne lui ouvre que ses AMAP. Le référent d'une AMAP n'a pas d'autre porte
 *    vers elle sur l'accueil, et y trouve sa carte. Un client membre d'une ferme n'y gagne rien :
 *    ni la carte de la ferme, ni le bloc de démonstration qui l'accompagne. Membre des deux, il ne
 *    voit que l'AMAP. Le témoin est un client sans structure, à qui l'accueil n'en montre aucune.
 * 2. « Mon compte » dit « Oui » sur la ligne Client à un client, et « Non » à un producteur qui ne
 *    l'est pas : la ligne lisait `roleProducer`, donc disait l'inverse.
 *
 * Les deux vues se rendent sur ce que leur page a préparé, la page étant jouée pour les comptes du
 * décor (`structure\FixtureAiLib`), qui existe en dev comme en production. Le gabarit n'est pas
 * construit : il lit ce que la route HTTP pose avant la page, que le lanceur de tests n'a pas.
 *
 * Isolation : l'adhésion à la ferme F1 qu'un cas pose est annulée avec la transaction de la méthode.
 *
 * Lancement : dev/test package=main file=CustomerProfileAi
 */
class CustomerProfileAiTest extends \dev\TransactionTest {

	/** Référent de l'AMAP A1 du décor, au profil client seul */
	const EMAIL_AMAP_REFERENT = 'decor.a1.referent@example.org';

	/** Client du décor qui n'adhère à aucune structure */
	const EMAIL_WITHOUT = 'decor.sans.structure@example.org';

	/** Administrateur de la ferme F1 du décor, au profil producteur seul */
	const EMAIL_PRODUCER = 'decor.f1.admin@example.org';

	private \user\User $eUserInitial;

	protected function getTransactionModel(): \ModuleModel {
		return \structure\Member::model();
	}

	public function init(): void {
		$this->eUserInitial = \user\ConnectionLib::getOnline();
	}

	public function finalize(): void {
		\user\ConnectionLib::setOnline($this->eUserInitial);
	}

	public function testHomeOpensAmapOfCustomer(): void {

		$eUser = $this->getCustomer(self::EMAIL_AMAP_REFERENT);

		if($eUser->empty()) {
			return;
		}

		$data = $this->renderHome($eUser);

		$eStructureAmap = $this->findStructure($data->ccStructureUser, \structure\FixtureAiLib::A1);

		if($this->assertDecorPresent($eStructureAmap->notEmpty(), 'l\'AMAP A1 parmi les structures de '.self::EMAIL_AMAP_REFERENT) === FALSE) {
			return;
		}

		$this->assertTrue($this->findStructure($data->ccStructureHome, \structure\FixtureAiLib::A1)->notEmpty(), 'un client membre d\'une AMAP trouve sa carte sur l\'accueil');

		$this->assertFalse($this->renderHome($this->getCustomer(self::EMAIL_WITHOUT))->hasHomeStructures, 'un client sans structure n\'en voit aucune');

	}

	public function testHomeIgnoresFarmOfCustomer(): void {

		$eUserFarm = $this->getCustomer(self::EMAIL_WITHOUT);
		$eUserBoth = $this->getCustomer(self::EMAIL_AMAP_REFERENT);

		if($eUserFarm->empty() or $eUserBoth->empty()) {
			return;
		}

		$eStructureFarm = \structure\Structure::model()
			->select(\structure\Structure::getSelection())
			->whereId(\structure\FixtureAiLib::F1)
			->get();

		if($this->assertDecorPresent($eStructureFarm->isProducer(), 'la ferme F1') === FALSE) {
			return;
		}

		foreach([$eUserFarm, $eUserBoth] as $eUser) {

			\structure\MemberLib::create(new \structure\Member([
				'user' => $eUser,
				'structure' => $eStructureFarm,
				'role' => \structure\RoleLib::getOne($eStructureFarm, 'admin'),
				'status' => \structure\Member::IN
			]));

		}

		$data = $this->renderHome($eUserFarm);

		if($this->assertDecorPresent($this->findStructure($data->ccStructureUser, \structure\FixtureAiLib::F1)->notEmpty(), 'F1 parmi les structures de '.self::EMAIL_WITHOUT) === FALSE) {
			return;
		}

		$this->assertFalse($data->hasHomeStructures, 'un client membre d\'une ferme ne voit pas ses structures sur l\'accueil, ni le bloc de démonstration');

		$data = $this->renderHome($eUserBoth);

		$this->assertTrue($this->findStructure($data->ccStructureHome, \structure\FixtureAiLib::A1)->notEmpty(), 'membre d\'une AMAP et d\'une ferme, le client trouve la carte de l\'AMAP');
		$this->assertFalse($data->ccStructureHome->offsetExists(\structure\Structure::PRODUCER), 'et pas celle de la ferme, ni le bloc de démonstration qui accompagne les fermes');

	}

	public function testAccountCustomerLine(): void {

		$eCustomer = $this->getUser(self::EMAIL_WITHOUT);
		$eProducer = $this->getUser(self::EMAIL_PRODUCER);

		if($this->assertDecorPresent(
			$eCustomer->notEmpty() and $eCustomer['roleCustomer'] and $eCustomer['roleProducer'] === FALSE and
			$eProducer->notEmpty() and $eProducer['roleProducer'] and $eProducer['roleCustomer'] === FALSE,
			self::EMAIL_WITHOUT.' au profil client seul, '.self::EMAIL_PRODUCER.' au profil producteur seul'
		) === FALSE) {
			return;
		}

		$this->assertEquals(s("Oui"), $this->getCustomerLine($this->renderAccount($eCustomer)), 'un client se lit client');
		$this->assertEquals(s("Non"), $this->getCustomerLine($this->renderAccount($eProducer)), 'un producteur qui n\'est pas client ne se lit pas client');

	}

	/** Un client du décor au profil client seul, ou un utilisateur vide après un échec nommé */
	private function getCustomer(string $email): \user\User {

		$eUser = $this->getUser($email);

		if($this->assertDecorPresent(
			$eUser->notEmpty() and $eUser['roleCustomer'] and $eUser['roleProducer'] === FALSE and $eUser['roleConsultant'] === FALSE and $eUser['roleTeacher'] === FALSE,
			$email.', au profil client seul'
		) === FALSE) {
			return new \user\User();
		}

		return $eUser;

	}

	private function findStructure(\Collection $ccStructure, int $structure): \structure\Structure {
		return $ccStructure->find(fn(\structure\Structure $eStructure) => $eStructure['id'] === $structure, depth: 2, limit: 1, default: new \structure\Structure());
	}

	private function getUser(string $email): \user\User {

		return \user\User::model()
			->select(\user\User::getSelection())
			->whereEmail($email)
			->get();

	}

	private function getCustomerLine(string $html): ?string {

		return preg_match('/<td>'.preg_quote(s("Client"), '/').'<\/td>.*?<b>([^<]+)<\/b>/s', $html, $match)
			? $match[1]
			: NULL;

	}

	/**
	 * Ce que prépare l'accueil tel que l'ouvre un membre dont aucune structure n'est l'accueil : le
	 * tableau de bord n'est l'accueil d'une structure qu'en dev, où il est expérimental.
	 */
	private function renderHome(\user\User $eUser): \stdClass {

		$data = $this->getData($eUser);

		$this->render('main/index', 'logged', $data);

		return $data;

	}

	/**
	 * Les comptes du décor n'ont aucun moyen de connexion, ce qu'aucun compte réel n'atteint, et
	 * l'entrée du mot de passe lit le premier : elle se retire, la ligne Client n'en dépendant pas.
	 */
	private function renderAccount(\user\User $eUser): string {
		return $this->render('main/account', 'index', $this->getData($eUser), function(\stdClass $data) {
			$data->canUpdate['password'] = FALSE;
		});
	}

	/** Ce que `main\PageLib` pose avant toute page d'un connecté, pour ce que ces deux pages en lisent */
	private function getData(\user\User $eUser): \stdClass {

		\user\ConnectionLib::setOnline($eUser);

		$data = new \stdClass();
		$data->eUserOnline = $eUser;
		$data->isLogged = TRUE;
		$data->userDeletedAt = NULL;
		$data->ccStructureUser = \structure\StructureLib::getByUser($eUser, withFeature: TRUE, index: ['type', 'id']);
		$data->eUserOnline['eUserAvatar'] = \user\UserAvatarLib::getByUser($data->eUserOnline);

		return $data;

	}

	private function render(string $request, string $name, \stdClass $data, ?\Closure $prepare = NULL): string {

		if(defined('LIME_URL') === FALSE) {
			define('LIME_URL', \Lime::getUrl());
		}

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodInitial = $requestMethod->getValue();
		$requestMethod->setValue(NULL, 'GET');

		try {
			\Page::execute(new \ReflectionMethod(\Page::class, 'getPages')->invoke(NULL, $request), $data);
			throw new \Exception('la page '.$request.' n\'a levé aucune vue');
		} catch(\ViewAction $action) {
		} finally {
			$requestMethod->setValue(NULL, $requestMethodInitial);
		}

		if($prepare !== NULL) {
			$prepare($data);
		}

		require_once \Package::getFileFromUri($request, 'view');

		$view = \View::get($name, $action);
		$template = new \ReflectionMethod(\View::class, 'getTemplate')->invoke($view);
		$template->setData($data);

		ob_start();
		new \ReflectionProperty(\View::class, 'callback')->getValue($view)->call($view, $data, $template);

		return $template->header.ob_get_clean();

	}

}
?>
