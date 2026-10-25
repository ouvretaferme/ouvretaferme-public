<?php
namespace structure;

/**
 * La liste du sélecteur de structure, chargée à l'ouverture par `structure/selectorAi`
 * (7A, décidé le 2026-10-03).
 *
 * Cinq promesses :
 * - la route ne rend que les structures du compte : une structure hors du compte et un univers hors de
 *   la liste fermée sont refusés ;
 * - le lien vers l'accueil du compte ne s'offre qu'à partir de deux structures ;
 * - jusqu'à dix structures, pas de recherche ; au-delà, le champ que le script écoute, et la ligne qu'il
 *   montre sur une recherche vaine ;
 * - les récentes sont la courante, puis les dernières visitées, de la plus récente à la plus ancienne,
 *   sans bourrage par les jamais visitées ;
 * - la route ne coûte aucune requête de plus qu'une page vide, et n'ouvre aucune base de structure.
 *
 * Aucune écriture : les comptes viennent du décor (`FixtureAiLib`), et les dates de visite sont posées
 * sur les adhésions en mémoire (`MemberLib::getOnline()`), que la route lit sans requête. Le compte en
 * ligne et les mémoïsations sont rendus à la fin.
 *
 * Lancement : dev/test package=structure file=SelectorAi
 */
class SelectorAiTest extends \Test {

	/** Trois structures : la liste sans recherche. */
	const FEW = 'decor.f1.admin@example.org';

	/** Une seule structure : pas de lien vers l'accueil. */
	const ONE = 'decor.f1.planning@example.org';

	/** Deux structures, le seuil du lien vers l'accueil. */
	const TWO = 'decor.f4.admin@example.org';

	/** Treize structures : la liste avec recherche. */
	const MANY = 'decor.c.admin@example.org';

	private \user\User $eUserInitial;

	public function init(): void {
		$this->eUserInitial = \user\ConnectionLib::getOnline();
	}

	public function finalize(): void {

		$this->forgetConnection();
		\user\ConnectionLib::setOnline($this->eUserInitial);

	}

	public function testAStructureOutsideTheAccountIsRefused(): void {

		$data = $this->getData(self::FEW);
		$outside = \structure\Structure::model()
			->select('id')
			->whereId('NOT IN', $this->getIds($data->ccStructureUser))
			->whereType(Structure::PRODUCER)
			->get();

		$this->assertTrue($outside->notEmpty(), 'le décor porte une structure hors du compte');
		$this->assertTrue($this->run($data, ['structure' => $outside['id']]) instanceof \NotExpectedAction, 'une structure hors du compte est refusée');

	}

	public function testASectionOutsideTheListIsRefused(): void {

		$data = $this->getData(self::FEW);

		$this->assertTrue($this->run($data, ['structure' => $this->getOpen($data), 'section' => 'dashboard']) instanceof \NotExpectedAction, 'un univers hors de la liste fermée est refusé');
		$this->assertTrue($this->run($data, ['structure' => $this->getOpen($data), 'section' => 'production']) instanceof \ViewAction, 'un univers de la liste passe');
		$this->assertTrue($this->run($data, ['structure' => $this->getOpen($data)]) instanceof \ViewAction, 'sans univers, la route passe');

	}

	public function testHomeIsOfferedFromTwoStructures(): void {

		$data = $this->getData(self::ONE);
		$this->run($data, ['structure' => $this->getOpen($data)]);
		$this->assertEquals(FALSE, $data->home, 'une structure, pas de lien vers l\'accueil');

		$data = $this->getData(self::TWO);
		$this->run($data, ['structure' => $this->getOpen($data)]);
		$this->assertEquals(2, $data->cStructureOwn->count() + $data->cStructureCommunity->count(), 'le compte a deux structures');
		$this->assertEquals(TRUE, $data->home, 'deux structures, le lien vers l\'accueil');

	}

	public function testUpToTenTheListHasNoSearch(): void {

		$data = $this->getData(self::FEW);
		$this->run($data, ['structure' => $this->getOpen($data)]);

		$this->assertEquals(FALSE, $data->search, 'trois structures, pas de recherche');

	}

	public function testBeyondTenTheListCarriesSearchRecentAndGroups(): void {

		$data = $this->getData(self::MANY);
		$this->run($data, ['structure' => $this->getOpen($data)]);

		$this->assertEquals(TRUE, $data->nStructureUser > SelectorAiUi::SEARCH, 'le compte a plus de dix structures');
		$this->assertEquals(TRUE, $data->search, 'la recherche est due');

		$html = $this->render($data);

		$this->assertEquals(1, substr_count($html, 'class="structure-selector-search" data-dropdown-keep'), 'le champ est là, et un clic dedans ne referme pas la liste');
		$this->assertEquals(1, substr_count($html, 'structure-selector-empty'), 'la ligne d\'une recherche vaine est là');

	}

	public function testRecentAreTheVisitedOnesInOrder(): void {

		$data = $this->getData(self::MANY);
		$open = $this->getOpen($data);

		$ids = array_values(array_diff($this->getIds($data->ccStructureUser, communities: FALSE), [$open]));

		$this->assertEquals(TRUE, count($ids) >= 6, 'le compte a de quoi remplir les récentes et en laisser');

		// Les dates de visite se posent en mémoire, sur les adhésions que la route lit sans requête.
		$cMember = MemberLib::getOnline();

		foreach($cMember as $eMember) {
			$eMember['visitedAt'] = NULL;
		}

		$cMember[$ids[0]]['visitedAt'] = '2026-09-01 10:00:00';
		$cMember[$ids[1]]['visitedAt'] = '2026-09-03 10:00:00';
		$cMember[$ids[2]]['visitedAt'] = '2026-09-02 10:00:00';

		$this->run($data, ['structure' => $open]);

		$this->assertEquals([$open, $ids[1], $ids[2], $ids[0]], $data->cStructureRecent->getIds(), 'la courante, puis les visitées de la plus récente à la plus ancienne, sans bourrage');

		foreach([3, 4, 5, 0, 1, 2] as $position => $index) {
			$cMember[$ids[$index]]['visitedAt'] = '2026-09-'.sprintf('%02d', 10 + $position).' 10:00:00';
		}

		$this->run($data, ['structure' => $open]);

		$this->assertEquals(SelectorAiUi::RECENT, $data->cStructureRecent->count(), 'les récentes s\'arrêtent à '.SelectorAiUi::RECENT);
		$this->assertEquals($open, $data->cStructureRecent->first()['id'], 'la courante reste en tête');

	}

	public function testTheRouteCostsNoQueryAndOpensNoStructureDatabase(): void {

		$data = $this->getData(self::MANY);
		$open = $this->getOpen($data);

		// Le premier passage remplit les mémoïsations que la page lit, comme `Page.l.php` le fait avant elle.
		$this->run($data, ['structure' => $open, 'section' => 'production']);

		$connected = $this->getConnectedId();
		$before = $this->countSelects();

		$this->run($data, ['structure' => $open, 'section' => 'production']);
		$this->render($data);

		$this->assertEquals(0, $this->countSelects() - $before, 'la liste ne lit rien que la page n\'ait déjà en mémoire');
		$this->assertEquals($connected, $this->getConnectedId(), 'aucune base de structure n\'est ouverte');

	}

	/**
	 * Ce que `Page.l.php` charge pour le compte avant la page : le constructeur commun s'arrête en ligne de
	 * commande, la donnée est donc posée ici.
	 */
	private function getData(string $email): \stdClass {

		$this->forgetConnection();

		$eUser = \user\User::model()
			->select(\user\User::getSelection())
			->whereEmail($email)
			->get();

		if($eUser->empty()) {
			throw new \Exception('Compte '.$email.' introuvable : monte le décor par dev/test package=structure file=FixtureAi');
		}

		\user\ConnectionLib::setOnline($eUser);

		$data = new \stdClass();
		$data->isLogged = TRUE;
		$data->eUserOnline = $eUser;
		$data->ccStructureUser = StructureLib::getOnline();
		$data->nStructureUser = $data->ccStructureUser->countByDepth();

		return $data;

	}

	private function run(\stdClass $data, array $get): \Action {

		$method = new \ReflectionProperty(\Route::class, 'requestMethod');
		$methodBefore = $method->getValue();
		$method->setValue(NULL, 'GET');

		if(defined('LIME_URL') === FALSE) {
			define('LIME_URL', \Lime::getUrl());
		}

		$getBefore = $_GET;
		$_GET = $get;

		try {
			// La page suit la convention package/page et ne figure pas dans les routes déclarées :
			// getCompatiblePages() ne la trouve pas, getPages() la résout.
			\Page::execute(new \ReflectionMethod(\Page::class, 'getPages')->invoke(NULL, 'structure/selectorAi'), $data);
		} catch(\Action $action) {
			return $action;
		} finally {
			$_GET = $getBefore;
			$method->setValue(NULL, $methodBefore);
		}

		throw new \Exception('La route structure/selectorAi n\'a levé aucune action');

	}

	private function render(\stdClass $data): string {
		return new SelectorAiUi()->get($data->eStructure, $data->cStructureOwn, $data->cStructureCommunity, $data->cStructureRecent, $data->home, $data->search);
	}

	private function getOpen(\stdClass $data): int {

		$ids = $this->getIds($data->ccStructureUser, communities: FALSE);

		if(in_array(FixtureAiLib::F1, $ids, TRUE)) {
			return FixtureAiLib::F1;
		} else {
			return $ids[0];
		}

	}

	private function getIds(\Collection $ccStructure, bool $communities = TRUE): array {

		$ids = [];

		foreach($ccStructure as $cStructure) {
			foreach($cStructure as $eStructure) {
				if($communities or $eStructure->isCommunity() === FALSE) {
					$ids[] = $eStructure['id'];
				}
			}
		}

		return $ids;

	}

	private function countSelects(): int {
		return (int)Structure::model()->pdo()->query('SHOW SESSION STATUS LIKE "Com_select"')->fetch()['Value'];
	}

	/** L'identifiant de la structure dont la base est ouverte, ou NULL si aucune ne l'est. */
	private function getConnectedId(): ?int {

		try {
			return Structure::getConnected()['id'];
		} catch(\Exception) {
			return NULL;
		}

	}

	/** Les mémoïsations de membre et de structure d'un compte survivraient au suivant dans le même processus. */
	private function forgetConnection(): void {

		foreach([Structure::class, ConfigurationLib::class, FeatureLib::class, MemberLib::class, StructureLib::class] as $class) {

			foreach(new \ReflectionClass($class)->getProperties(\ReflectionProperty::IS_STATIC) as $property) {

				if($property->getDeclaringClass()->getName() === $class and $property->hasDefaultValue()) {
					$property->setValue(NULL, $property->getDefaultValue());
				}

			}

		}

	}

}
?>
