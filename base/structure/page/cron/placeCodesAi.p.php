<?php
/**
 * Rattrapage de la nuit : la commune, le département et l'intercommunalité des lieux de production
 * qui ont un point mais pas de département. L'enregistrement ne rappelle geo.api.gouv.fr que pour un
 * point déplacé (`SystemLib::prepareCodes()`), donc un appel qui a échoué ne se retente qu'ici.
 *
 *   php framework/lime.php -a ouvretaferme -e dev structure/cron/placeCodesAi
 *   … structure=<id> pour une seule structure, limit=<n> pour en reprendre plus ou moins que 500
 *
 * 500 lieux par passage : un appel toutes les 100 ms, le passage dure deux à trois minutes, et une
 * cinquantaine de minutes quand geo.api ne répond plus et que chaque appel attend ses 6 s. C'est
 * plusieurs fois ce qu'une journée peut laisser sans département, en plus des points qu'aucune
 * commune ne couvre (en mer, de l'autre côté d'une frontière), retentés chaque nuit. La première
 * reprise des lieux déjà posés passe `limit` pour tout prendre en une fois
 * (`migration/2026-10-22-structure-lieu-codes.md`).
 */
new Page()
	->cron('index', function($data) {

		$eStructure = \structure\StructureLib::getById(GET('structure', '?int'));
		$limit = GET('limit', '?int') ?? 500;

		$cSystem = \structure\SystemLib::getWithoutCodes($eStructure, $limit);

		$located = 0;

		foreach($cSystem as $eSystem) {

			\structure\SystemLib::updateCodes($eSystem);

			if($eSystem['placeDepartment'] !== NULL) {
				$located++;
			}

			// geo.api.gouv.fr admet 50 appels par seconde et par adresse : dix laissent la place aux
			// enregistrements du produit, qui partent de la même adresse.
			usleep(100000);

		}

		echo $cSystem->count().' lieu(x) traité(s), '.$located.' situé(s), '.($cSystem->count() - $located).' sans commune ou en échec'."\n";

	}, interval: '45 3 * * *');
?>
