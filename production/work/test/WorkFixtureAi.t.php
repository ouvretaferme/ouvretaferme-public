<?php
namespace work;

/**
 * Le décor des tests du paquet work, monté par le test et non emprunté à la base.
 *
 * Les tests du paquet lisaient le dernier outil actif de la structure 7, un outil de n'importe
 * quelle autre structure, et n'affirmaient sur les listes que la présence des clés : chaque
 * rechargement de la base depuis la production et chaque saisie du jour dans le produit changeaient
 * ce qu'ils mesuraient. Ils posent désormais leur action, leur méthode, leur fournisseur et leur
 * outil ici, dans la transaction de la méthode, que le `tearDown()` emporte.
 *
 * Ce qui reste lu est le décor de la structure hôte, et il se lit par ce qui l'identifie, jamais par
 * un identifiant : ses catégories (`fqn` `culture`, `production`) et ses actions (`fqn` `plantation`,
 * `recolte`...), que `CategoryLib::duplicateForStructure()` et `ActionLib::duplicateForStructure()`
 * donnent à toute structure à sa création. Une pièce absente se déclare par `requireXxx()`, et le
 * cas s'arrête là.
 *
 * Toutes les tables du paquet vivent dans la base commune : une seule transaction, sur n'importe
 * quel modèle de cette connexion, couvre la catégorie, l'action, la méthode, le fournisseur et
 * l'outil.
 */
abstract class WorkFixtureAiTest extends \dev\ApiTest {

	/** Les couches du décor de la méthode en cours, vidées à chaque cas : `\Test` garde l'objet d'une méthode à l'autre. */
	private array $fixtures = [];

	protected function getTransactionModel(): \ModuleModel {
		return new ActionModel();
	}

	public function setUp(): void {

		parent::setUp();

		$this->fixtures = [];

	}

	/** Monte une couche au premier appel et la rend telle quelle ensuite : les cas qui la lisent plusieurs fois parlent de la même. */
	protected function once(string $key, \Closure $build): mixed {

		return $this->fixtures[$key] ??= $build();

	}

	/** Une image carrée valide, pour les routes de vignette. */
	protected function getImage(): string {

		$image = new \Imagick();
		$image->newImage(600, 600, new \ImagickPixel('#4a7c2f'));
		$image->setImageFormat('jpeg');

		$content = base64_encode($image->getImageBlob());

		$image->clear();

		return $content;

	}

	/** L0 · Une catégorie de la structure, par son `fqn` : un décor de la structure, jamais un identifiant. */
	protected function getCategory(string $fqn): Category {

		return $this->once('category-'.$fqn, fn() => CategoryLib::getByStructure($this->eStructure, fqn: $fqn));

	}

	protected function requireCategory(string $fqn): bool {

		return $this->assertTrue($this->getCategory($fqn)->notEmpty(), 'la structure '.static::STRUCTURE.' a une catégorie « '.$fqn.' »');

	}

	/**
	 * La place pour une catégorie de plus : `CategoryLib::create()` refuse la création au-delà de
	 * `WorkSetting::CATEGORIES_LIMIT`, et le nombre de catégories de la structure dépend de ce que ses
	 * membres y ont ajouté. Un cas qui en crée une le déclare.
	 */
	protected function requireCategorySlot(): bool {

		$categories = Category::model()
			->whereStructure($this->eStructure)
			->count();

		return $this->assertTrue($categories < WorkSetting::CATEGORIES_LIMIT, 'la structure '.static::STRUCTURE.' a moins de '.WorkSetting::CATEGORIES_LIMIT.' catégories, pour en créer une');

	}

	/** L0 bis · Une action de la structure, par son `fqn`. */
	protected function getAction(string $fqn): Action {

		return $this->once('action-'.$fqn, fn() => ActionLib::getByStructure($this->eStructure, fqn: $fqn));

	}

	protected function requireAction(string $fqn): bool {

		return $this->assertTrue($this->getAction($fqn)->notEmpty(), 'la structure '.static::STRUCTURE.' a une action « '.$fqn.' » active');

	}

	/**
	 * L1 · Une action libre de la structure, sans `fqn`, dans la catégorie de production : hors de la
	 * catégorie de culture, `Action::build()` ne force ni l'allure ni le sol.
	 */
	protected function createAction(Category $eCategory = new Category()): Action {

		$eAction = new Action([
			'name' => 'Action du décor '.uniqid(),
			'structure' => $this->eStructure,
			'categories' => [($eCategory->notEmpty() ? $eCategory : $this->getCategory(CATEGORIE_PRODUCTION))['id']],
		]);

		ActionLib::create($eAction);

		return $eAction;

	}

	protected function getActionFree(): Action {

		return $this->once('actionFree', fn() => $this->createAction());

	}

	/** L2 · Une méthode d'une action de la structure. */
	protected function createMethod(Action $eAction = new Action()): Method {

		$eMethod = new Method([
			'name' => 'Méthode du décor '.uniqid(),
			'structure' => $this->eStructure,
			'action' => $eAction->notEmpty() ? $eAction : $this->getActionFree(),
		]);

		MethodLib::create($eMethod);

		return $eMethod;

	}

	protected function getMethod(): Method {

		return $this->once('method', fn() => $this->createMethod());

	}

	/** L3 · Un fournisseur de la structure. */
	protected function createSupplier(): Supplier {

		$eSupplier = new Supplier([
			'name' => 'Fournisseur du décor '.uniqid(),
			'structure' => $this->eStructure,
		]);

		SupplierLib::create($eSupplier);

		return $eSupplier;

	}

	protected function getSupplier(): Supplier {

		return $this->once('supplier', fn() => $this->createSupplier());

	}

	/** L4 · Un outil libre de la structure, rattaché à aucune action ni routine. */
	protected function createTool(): Tool {

		$eTool = new Tool([
			'name' => 'Outil du décor '.uniqid(),
			'structure' => $this->eStructure,
			'action' => new Action(),
			'stock' => NULL,
			'comment' => NULL,
			'routineName' => NULL,
			'routineValue' => NULL,
		]);

		ToolLib::create($eTool);

		return $eTool;

	}

	protected function getTool(): Tool {

		return $this->once('tool', fn() => $this->createTool());

	}

	/**
	 * Une autre structure, n'importe laquelle : seule son identité compte, pour un témoin que la borne
	 * de structure doit écarter. Le cas se déclare si la base n'en porte aucune.
	 */
	protected function getStructureForeign(): \structure\Structure {

		return $this->once('structureForeign', fn() => \structure\Structure::model()
			->select(['id'])
			->whereId('!=', static::STRUCTURE)
			->sort(['id' => SORT_ASC])
			->get());

	}

	protected function requireStructureForeign(): bool {

		return $this->assertTrue($this->getStructureForeign()->notEmpty(), 'la base porte une autre structure que la '.static::STRUCTURE.', pour y poser un témoin');

	}

	/**
	 * Le jumeau de l'outil du décor chez une autre structure, posé par le modèle : un témoin n'a besoin
	 * que d'une ligne portant une autre structure, et la table vit dans la base commune.
	 */
	protected function createToolForeign(): Tool {

		$eTool = new Tool([
			'name' => 'Outil témoin '.uniqid(),
			'structure' => $this->getStructureForeign(),
			'action' => new Action(),
		]);

		Tool::model()->insert($eTool);

		return $eTool;

	}

}
?>
