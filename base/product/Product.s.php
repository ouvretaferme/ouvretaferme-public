<?php
namespace product;

class ProductSetting extends \Settings {

	const UNIT_DEFAULT_ID = 1;

	const STOCK_FACTOR = 3;
	const STOCK_LOW = 10;

	// Nombre maximal de paliers de remise sur volume, sur un produit comme sur une offre.
	// Trois lignes sous un prix est ce qu'une carte de boutique porte sans devenir un tableau, et
	// au-delà ce n'est plus une dégressivité mais une grille de prix, que le logiciel sait déjà
	// tenir.
	const PRICE_STEPS_MAX = 3;

	// Place que la description occupe sur une carte de boutique en grille, en
	// caractères. Une description qui y tient est affichée en entier ; au-delà,
	// c'est la longueur que se partagent l'extrait et « Lire la suite ». Compter
	// en caractères plutôt qu'en lignes fait varier la hauteur avec la largeur de
	// la carte — six à sept lignes dans une colonne de 235px.
	const DESCRIPTION_EXCERPT_LENGTH = 200;

	// Ce que « Lire la suite » prend sur cette place, à la fin de l'extrait.
	const DESCRIPTION_EXCERPT_LINK_LENGTH = 20;

	const VAT_UNKNOWN = 999;

	public static function getVatAllowed(): array {
		return [\user\UserSetting::FR, \user\UserSetting::BE];
	}

	public static function getStandardVat(\structure\Structure $eStructure): float {

		$eStructure->expects(['legalCountry']);

		$eCountry = $eStructure['legalCountry'];

		if($eCountry['id'] === \user\UserSetting::FR) {
			return 4;
		} else if($eCountry['id'] === \user\UserSetting::BE) {
			return 103;
		} else {
			return self::VAT_UNKNOWN;
		}

	}

	public static function getStandardVatRate(\structure\Structure $eStructure): float {

		return self::getVatRate($eStructure, self::getStandardVat($eStructure));

	}

	public static function getVatApi(): array {

		return [

			0 => 'FR_VAT_0',
			1 => 'FR_VAT_PARTICULIER',
			2 => 'FR_VAT_REDUIT',
			3 => 'FR_VAT_INTERMEDIAIRE',
			4 => 'FR_VAT_NORMAL',

			100 => 'BE_VAT_0',
			101 => 'BE_VAT_REDUIT',
			102 => 'BE_VAT_INTERMEDIAIRE',
			103 => 'BE_VAT_NORMAL',

			999 => 'UNKNOWN',

		];

	}

	public static function getVatRates(\structure\Structure $eStructure): array {

		$eStructure->expects(['legalCountry']);

		$eCountry = $eStructure['legalCountry'];

		if($eCountry['id'] === \user\UserSetting::FR) {

			return [
				0 => 0,
				1 => 2.1,
				2 => 5.5,
				3 => 10,
				4 => 20,
				self::VAT_UNKNOWN => NULL,
			];

		} else if($eCountry['id'] === \user\UserSetting::BE) {

			return [
				100 => 0,
				101 => 6,
				102 => 12,
				103 => 21,
				self::VAT_UNKNOWN => NULL,
			];

		} else {

			return [
				self::VAT_UNKNOWN => 0
			];

		}

	}

	/**
	 * Le plus haut taux légal du pays, au-delà duquel un rapport TVA / HT ne décrit plus une TVA : une
	 * déduction partielle descend sous le taux légal, elle ne le dépasse jamais. NULL pour un pays dont
	 * la grille n'est pas connue, qui n'a donc aucun plafond à opposer.
	 */
	public static function getVatRateMax(\structure\Structure $eStructure): ?float {

		$vatRates = array_filter(self::getVatRates($eStructure));

		return ($vatRates === []) ? NULL : (float)max($vatRates);

	}

	public static function getVatRate(\structure\Structure $eStructure, int $rate): ?float {

		if($eStructure->conf()['hasVat'] === FALSE) {
			return 0.0;
		}

		$rates = self::getVatRates($eStructure);

		if(array_key_exists($rate, $rates) === FALSE) {
			throw new \Exception('Unknown rate '.$rate.' for structure '.$eStructure['id']);
		}

		return $rates[$rate];

	}

	public static function getStartVat(\user\Country $eCountry): ?int {

		if($eCountry->empty()) {
			return NULL;
		} else if($eCountry['id'] === \user\UserSetting::FR) {
			return 2;
		} else if($eCountry['id'] === \user\UserSetting::BE) {
			return 101;
		} else {
			return self::VAT_UNKNOWN;
		}

	}

}
?>
