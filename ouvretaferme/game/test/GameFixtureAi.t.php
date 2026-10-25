<?php
namespace game;

/**
 * Le décor des tests du jeu : deux comptes fictifs, en example.org où rien n'est jamais délivré,
 * semés une fois dans `init()` et retrouvés par leur adresse. Ils ne jouent pas : chaque méthode
 * inscrit ses joueurs dans sa transaction, annulée en `tearDown()`, et aucune ligne de la partie en
 * base n'est lue ni écrite.
 *
 * Le joueur premium est le seul emprunt au décor commun : `decor.f1.admin`, administrateur de F1,
 * dont l'adhésion est posée dans la transaction du cas qui le demande.
 */
abstract class GameFixtureAiTest extends \dev\TransactionTest {

	const EMAIL_USER = 'zztest.jeu.joueur@example.org';
	const EMAIL_USER_FRIEND = 'zztest.jeu.ami@example.org';
	const EMAIL_USER_PREMIUM = 'decor.f1.admin@example.org';

	/** La raison du refus, quand le semis n'a pas pu aller au bout */
	private ?string $seedFailure = NULL;

	private \user\User $eUserInitial;

	public function init(): void {

		$this->eUserInitial = \user\ConnectionLib::getOnline();

		// Une exception qui sort de init() interrompt le fichier sans bilan : elle se garde, et
		// chaque contrôle la rend en échec nommé.
		try {
			$this->seedUser(self::EMAIL_USER, 'Joueur');
			$this->seedUser(self::EMAIL_USER_FRIEND, 'Ami');
		} catch(\Throwable $e) {
			$this->seedFailure = $e->getMessage().' ('.$e->getFile().':'.$e->getLine().')';
		}

	}

	public function setUp(): void {

		// Le joueur connecté et le premium se mémoïsent par utilisateur, et chaque méthode réinscrit
		// ses joueurs : la mémoire de la méthode précédente porterait un joueur annulé.
		$this->forgetOnline();
		new \ReflectionProperty(Player::class, 'premium')->setValue(NULL, []);

		parent::setUp();

	}

	public function tearDown(): void {

		parent::tearDown();

		\user\ConnectionLib::setOnline($this->eUserInitial);

	}

	protected function getTransactionModel(): \ModuleModel {
		return Player::model();
	}

	public function finalize(): void {
		\user\ConnectionLib::setOnline($this->eUserInitial);
	}

	protected function assertFixtures(): bool {
		return $this->assertDecorPresent($this->seedFailure === NULL, 'les deux comptes fictifs du jeu ('.($this->seedFailure ?? '').')');
	}

	protected function getUser(string $email): \user\User {

		return \user\User::model()
			->select(\user\User::getSelection())
			->whereEmail($email)
			->get();

	}

	/**
	 * Inscrit le joueur de ce compte, comme le formulaire de `/game/:new`, et le rend tel que
	 * `PlayerLib::getOnline()` le rend : connecté, l'utilisateur complet sur l'élément.
	 */
	protected function createPlayer(string $email, string $name): Player {

		$eUser = $this->getUser($email);

		\user\ConnectionLib::setOnline($eUser);

		$fw = new \FailWatch();

		$ePlayer = new Player(['user' => $eUser]);
		$ePlayer->build(['name'], ['name' => $name]);

		PlayerLib::create($ePlayer);

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('inscription refusée : '.json_encode($fw->get())));

		return $this->getPlayer($eUser);

	}

	protected function getPlayer(\user\User $eUser): Player {

		$ePlayer = PlayerLib::getByUser($eUser);
		$ePlayer['user'] = $eUser;

		return $ePlayer;

	}

	protected function forgetOnline(): void {
		new \ReflectionProperty(PlayerLib::class, 'ePlayerOnline')->setValue(NULL, []);
	}

	/**
	 * Rend au joueur toute sa journée, pour enchaîner des gestes qui en demandent plus qu'une.
	 */
	protected function rest(Player $ePlayer): void {

		$ePlayer['time'] = 0;

		PlayerLib::update($ePlayer, ['time']);

	}

	protected function getTime(Player $ePlayer): float {

		return (float)Player::model()
			->whereId($ePlayer)
			->getValue('time');

	}

	protected function getTile(Player $ePlayer, int $board, int $tile): Tile {
		return TileLib::getByBoard($ePlayer, $board)[$tile];
	}

	protected function getStock(Player $ePlayer, Growing $eGrowing): int {

		return (int)Food::model()
			->whereUser($ePlayer['user'])
			->whereGrowing($eGrowing)
			->getValue('current');

	}

	protected function getGrowing(string $fqn): Growing {

		return Growing::model()
			->select(Growing::getSelection())
			->whereFqn($fqn)
			->get();

	}

	private function seedUser(string $email, string $lastName): void {

		if($this->getUser($email)->notEmpty()) {
			return;
		}

		$eUser = new \user\User([
			'firstName' => 'zzTest',
			'lastName' => $lastName,
			'email' => $email,
			'visibility' => \user\User::PRIVATE,
			'roleCustomer' => FALSE,
			'roleProducer' => TRUE,
			'roleConsultant' => FALSE,
			'roleTeacher' => FALSE
		]);

		$fw = new \FailWatch();

		// Sans build() : celui de l'inscription (SignUpLib::match()) exige le type de compte et l'adresse
		// de livraison, qu'un compte fictif semé pour le décor n'a pas à fournir.
		\user\UserLib::create($eUser);

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('création du compte fictif refusée : '.json_encode($fw->get())));

	}

}
?>
