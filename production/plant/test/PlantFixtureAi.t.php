<?php
namespace plant;

/**
 * Le décor des tests du paquet plant, monté par le test et non emprunté à la base.
 *
 * Les tests du paquet lisaient la première famille par identifiant, la dernière espèce de la
 * structure 7, la première étiquette, une espèce du référentiel prise au hasard : chaque
 * rechargement de la base depuis la production et chaque saisie du jour dans le produit changeaient
 * ce qu'ils mesuraient. Ils posent désormais leur famille, leur espèce, leur variété, leur taille et
 * leur étiquette ici, dans la transaction de la méthode, que le `tearDown()` emporte.
 *
 * Le référentiel partagé (`structure IS NULL`) est un décor de la base : ce qu'un cas y lit, il le
 * pose lui-même dans la transaction (`createPlantGlobal()`, `createVarietyGlobal()`), et les deux
 * variétés que le produit désigne par une constante (`PlantSetting::VARIETY_UNKNOWN`) se déclarent
 * par `requireXxx()`.
 *
 * Toutes les tables du paquet vivent dans la base commune : une seule transaction, sur n'importe
 * quel modèle de cette connexion, couvre la famille, l'espèce, la variété, la taille et l'étiquette.
 */
abstract class PlantFixtureAiTest extends \dev\ApiTest {

	/** Les couches du décor de la méthode en cours, vidées à chaque cas : `\Test` garde l'objet d'une méthode à l'autre. */
	private array $fixtures = [];

	protected function getTransactionModel(): \ModuleModel {
		return new PlantModel();
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

	/** L1 · Une famille du référentiel, posée dans la transaction : le `fqn` est unique, il porte donc un aléa. */
	protected function createFamily(): Family {

		$eFamily = new Family([
			'name' => 'Famille du décor '.uniqid(),
			'fqn' => 'famille-decor-'.uniqid(),
		]);

		FamilyLib::create($eFamily);

		return $eFamily;

	}

	protected function getFamily(): Family {

		return $this->once('family', fn() => $this->createFamily());

	}

	/** L2 · Une espèce propre à la structure, pour que rien du référentiel partagé ne pèse sur les cas. */
	protected function createPlant(): Plant {

		$ePlant = new Plant([
			'name' => 'Espèce du décor '.uniqid(),
			'structure' => $this->eStructure,
			'cycle' => Plant::ANNUAL,
		]);

		PlantLib::create($ePlant);

		return $ePlant;

	}

	protected function getPlant(): Plant {

		return $this->once('plant', fn() => $this->createPlant());

	}

	/** L2 bis · Une espèce du référentiel partagé, sans structure : ce que toute structure lit et qu'aucune ne possède. */
	protected function createPlantGlobal(): Plant {

		$ePlant = new Plant([
			'name' => 'Espèce commune du décor '.uniqid(),
			'structure' => new \structure\Structure(),
			'cycle' => Plant::ANNUAL,
		]);

		PlantLib::create($ePlant);

		return $ePlant;

	}

	protected function getPlantGlobal(): Plant {

		return $this->once('plantGlobal', fn() => $this->createPlantGlobal());

	}

	/** L3 · Une variété de la structure sur une espèce, sans fournisseur. */
	protected function createVariety(Plant $ePlant = new Plant()): Variety {

		$eVariety = new Variety([
			'name' => 'Variété du décor '.uniqid(),
			'structure' => $this->eStructure,
			'plant' => $ePlant->notEmpty() ? $ePlant : $this->getPlant(),
			'supplierSeed' => new \work\Supplier(),
			'supplierPlant' => new \work\Supplier(),
		]);

		VarietyLib::create($eVariety);

		return $eVariety;

	}

	protected function getVariety(): Variety {

		return $this->once('variety', fn() => $this->createVariety());

	}

	/** L3 bis · Une variété du référentiel partagé, sur une espèce du référentiel. */
	protected function createVarietyGlobal(Plant $ePlant = new Plant()): Variety {

		$eVariety = new Variety([
			'name' => 'Variété commune du décor '.uniqid(),
			'structure' => new \structure\Structure(),
			'plant' => $ePlant->notEmpty() ? $ePlant : $this->getPlantGlobal(),
			'supplierSeed' => new \work\Supplier(),
			'supplierPlant' => new \work\Supplier(),
		]);

		VarietyLib::create($eVariety);

		return $eVariety;

	}

	protected function getVarietyGlobal(): Variety {

		return $this->once('varietyGlobal', fn() => $this->createVarietyGlobal());

	}

	/** L4 · Une taille de récolte de la structure sur une espèce. */
	protected function createSize(Plant $ePlant = new Plant()): Size {

		$eSize = new Size([
			'name' => 'Taille du décor '.uniqid(),
			'structure' => $this->eStructure,
			'plant' => $ePlant->notEmpty() ? $ePlant : $this->getPlant(),
			'comment' => NULL,
			'yield' => TRUE,
		]);

		SizeLib::create($eSize);

		return $eSize;

	}

	protected function getSize(): Size {

		return $this->once('size', fn() => $this->createSize());

	}

	/** L5 · Une étiquette du vocabulaire commun, posée dans la transaction. */
	protected function createTag(): Tag {

		$eTag = new Tag([
			'fqn' => 'etiquette-decor-'.uniqid(),
			'usage' => Tag::VEGETABLE,
			'name' => 'Étiquette du décor '.uniqid(),
		]);

		TagLib::create($eTag);

		return $eTag;

	}

	protected function getTag(): Tag {

		return $this->once('tag', fn() => $this->createTag());

	}

	/** La variété « non renseignée » que le produit désigne par sa constante : une pièce unique de la base, qui se déclare. */
	protected function requireVarietyUnknown(): bool {

		return $this->assertTrue(Variety::model()->whereId(PlantSetting::VARIETY_UNKNOWN)->exists(), 'la base porte la variété non renseignée (PlantSetting::VARIETY_UNKNOWN)');

	}

}
?>
