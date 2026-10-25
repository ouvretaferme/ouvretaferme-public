<?php
namespace data;

/**
 * L'anonymisation d'une copie des sauvegardes de production, pour une développeuse qui n'a le droit
 * de lire en clair que la structure 7 (décidé le 2026-09-27, chantier `anonymisation`).
 *
 * Le contrat tient dans `AnonymizeAiSetting` et dans le `Data.o.php` de chaque package fermé
 * (`getRules()`) : chaque colonne d'un type qui peut porter une donnée personnelle ou un secret y a
 * sa règle, et `AnonymizeSentinelAi` fait échouer toute colonne ajoutée sans règle. Ce Lib ne connaît aucune colonne par son nom : il lit la déclaration et le schéma.
 */
class AnonymizeAiLib {

	use \Notifiable;

	/**
	 * Les types de propriété qui se déclarent : ce sont ceux qui peuvent porter un texte libre, une
	 * adresse, un jeton, une position ou une adresse de page (`fqn`, que les boutiques et les groupes
	 * forment sur leur nom). Les autres (identifiants, délégués, dates, montants, enums, booléens) ne
	 * disent en général rien d'une personne et se gardent, sauf quand une règle de `getRules()` en
	 * nomme une, comme une date de naissance.
	 */
	const TYPES_DECLARED = ['text', 'editor', 'json', 'email', 'url', 'ipv', 'binary', 'sid', 'point', 'polygon', 'fqn'];

	/**
	 * Les modules d'une des deux familles de bases, par classe.
	 *
	 * La famille se lit dans `StructureSetting::getPackages()` : un package qui y figure vit dans
	 * `structure_<id>`, tous les autres dans la base commune. Un module sans `model()` (élément sans
	 * table) n'a rien à anonymiser.
	 *
	 * @return array<string, \ModuleModel>
	 */
	public static function getModels(bool $structure): array {

		$packagesStructure = \structure\StructureSetting::getPackages();

		$models = [];

		foreach(array_keys(\Package::getList()) as $package) {

			if(in_array($package, $packagesStructure) !== $structure) {
				continue;
			}

			foreach(glob(\Package::getPath($package).'/module/*.m.php') as $file) {

				$class = $package.'\\'.basename($file, '.m.php');

				if(class_exists($class) === FALSE or method_exists($class, 'model') === FALSE) {
					continue;
				}

				$models[$class] = $class::model();

			}

		}

		ksort($models);

		return $models;

	}

	/**
	 * La règle de chaque colonne, par module puis par propriété : celles de `AnonymizeAiSetting::RULES`,
	 * et celles que chaque package fermé déclare dans son `Data.o.php`, absent de la version publique.
	 *
	 * @return array<string, array<string, array>>
	 */
	public static function getRules(): array {

		$rules = array_merge(AnonymizeAiSetting::RULES, ...self::notify('getAnonymizeRules'));
		ksort($rules);

		return $rules;

	}

	/**
	 * Les modules dont les lignes partent, sous la forme `[portée, raison]`, déclarés comme les règles.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function getModulesTruncate(): array {

		$modules = array_merge(AnonymizeAiSetting::MODULES_TRUNCATE, ...self::notify('getAnonymizeTruncate'));
		ksort($modules);

		return $modules;

	}

	public static function isDeclaredType(string $type): bool {

		foreach(self::TYPES_DECLARED as $prefix) {

			if(str_starts_with($type, $prefix)) {
				return TRUE;
			}

		}

		return FALSE;

	}

	/**
	 * Les propriétés d'un module qui doivent porter une règle.
	 *
	 * @return string[]
	 */
	public static function getPropertiesDeclared(\ModuleModel $model): array {

		return array_values(array_filter(
			$model->getProperties(),
			fn(string $property) => self::isDeclaredType($model->getPropertyType($property))
		));

	}

}
?>
