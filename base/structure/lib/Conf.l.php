<?php
namespace structure;

/**
 * La lecture des réglages d'une structure, commune à toutes les tables qui en portent une ligne par
 * structure (`structure\Configuration`, `structure\Feature`, puis celles des autres packages) : le
 * Lib qui l'emploie se nomme d'après son module (`ConfigurationLib` pour `Configuration`), et
 * `Structure::conf()` le retrouve par ce nom.
 *
 * `getByStructure()` rend une instance partagée, lue une fois par structure et par processus : un
 * écran la consulte des dizaines de fois. Elle se lit, elle ne s'édite pas, un `build()` posé dessus
 * se verrait de tous les autres lecteurs du processus, valeur refusée comprise. Un formulaire, ou une
 * garde relue après un verrou, part de `readByStructure()` : le mémo rendrait ce qui a été lu avant
 * le verrou.
 *
 * ⚠️ Le mémo s'oublie à chaque écriture, et seul le Lib sait qu'il écrit : `create()`, `update()`
 * et `delete()` l'oublient ici, toute autre écriture du Lib appelle `self::forget()` elle-même
 * (contrôle : `ConfForgetSentinel`). Une boucle sur le parc (`StructureLib::runAll()`) lit chaque
 * structure une fois, d'où l'index par identifiant de structure et non un élément unique.
 */
trait ConfLib {

	private static array $cachedByStructure = [];

	public static function getByStructure(Structure $eStructure): \Element {

		if($eStructure->exists() === FALSE) {
			return self::getModule()::model()->getNewElement();
		}

		return self::$cachedByStructure[$eStructure['id']] ??= static::readByStructure($eStructure);

	}

	/**
	 * Lit d'une requête la ligne de chaque structure d'une liste et la range dans le mémo : une page
	 * qui lit `conf()` sur chacune ne fait pas une requête par structure. Une structure sans ligne
	 * n'est pas rangée, `getByStructure()` lui rendra ses défauts.
	 */
	public static function memorizeByStructures(\Collection $cStructure): void {

		if($cStructure->empty()) {
			return;
		}

		$module = self::getModule();
		$property = static::getConfProperty();

		$c = $module::model()
			->select($module::getSelection())
			->where($property, 'IN', $cStructure)
			->getCollection(index: $property);

		foreach($cStructure as $eStructure) {

			if($c->offsetExists($eStructure['id'])) {
				$e = $c[$eStructure['id']];
				$e[$property] = $eStructure;
				self::$cachedByStructure[$eStructure['id']] = $e;
			}

		}

	}

	/**
	 * Une ligne absente rend les défauts du schéma, sans identifiant : ce sont les valeurs qu'aurait
	 * la ligne créée avec la structure.
	 */
	public static function readByStructure(Structure $eStructure): \Element {

		$module = self::getModule();
		$property = static::getConfProperty();

		$e = $module::model()->getNewElement();

		if($module::model()
			->select($module::getSelection())
			->where($property, $eStructure)
			->get($e) === FALSE) {

			foreach($module::model()->getProperties() as $propertyDefault) {
				$e[$propertyDefault] = $module::model()->getDefaultValue($propertyDefault);
			}

		}

		// L'appelant tient déjà la structure : la relire ne lui apprendrait rien
		$e[$property] = $eStructure;

		return $e;

	}

	/**
	 * La colonne qui désigne la structure. Une table qui la nomme autrement (`amap\Configuration.amap`)
	 * surcharge cette méthode.
	 */
	protected static function getConfProperty(): string {
		return 'structure';
	}

	/**
	 * La structure dont une ligne qu'on écrit porte les réglages. Une table d'une base de structure
	 * (`account\Configuration`), qui n'a pas de colonne pour la désigner, surcharge cette méthode.
	 */
	protected static function getStructure(\Element $e): Structure {

		$e->expects([static::getConfProperty()]);

		return $e[static::getConfProperty()];

	}

	public static function forget(Structure $eStructure): void {
		unset(self::$cachedByStructure[$eStructure['id']]);
	}

	public static function forgetAll(): void {
		self::$cachedByStructure = [];
	}

	public static function create(\Element $e): void {

		$eStructure = static::getStructure($e);

		parent::create($e);

		static::forget($eStructure);

	}

	public static function update(\Element $e, array $properties): void {

		$eStructure = static::getStructure($e);

		parent::update($e, $properties);

		static::forget($eStructure);

	}

	// Le Crud désigne les lignes par leur seul id (`whereId('IN', $c)`) : les structures touchées ne se lisent pas
	public static function updateCollection(\Collection $c, \Element $e, array $properties): void {

		parent::updateCollection($c, $e, $properties);

		static::forgetAll();

	}

	public static function delete(\Element $e): void {

		$eStructure = static::getStructure($e);

		parent::delete($e);

		static::forget($eStructure);

	}

	/**
	 * @return class-string<\Element>
	 */
	private static function getModule(): string {
		return substr(static::class, 0, -strlen('Lib'));
	}

}
?>
