<?php
namespace dev;

/**
 * Point d'entrée générique, sans contenu métier : collecte les libellés/genres/descriptions
 * fournis par chaque package concerné via son DevObserverUi::apiLabels() (ui/Dev.o.php), cf
 * Notifiable/Package::getObservers().
 */
class ApiUi {

	use \Notifiable;

	public static function getLabels(): array {
		return array_merge(...self::notify('apiLabels'));
	}

	/**
	 * Le schéma des champs qu'aucune colonne ne porte : compteurs, collections embarquées, valeurs
	 * recalculées à la volée.
	 *
	 * Indexé par module exposé puis par champ, la valeur étant un fragment de schéma OpenAPI
	 * ('type', 'format', 'items', '$ref', 'description'…), écrit là où vivent déjà les libellés
	 * plutôt que dans le *.a.php : c'est une description destinée au lecteur de la documentation.
	 *
	 * Un champ que la ressource lit dans le corps sans en faire une propriété s'y déclare avec
	 * 'writeOnly' => TRUE : il sort alors dans ses schémas d'écriture, et jamais en lecture.
	 * 'gestures' => ['create'] ou ['update'] le borne aux corps de ce geste, les deux par défaut.
	 */
	public static function getFields(): array {
		return array_merge([], ...self::notify('apiFields'));
	}

	/**
	 * Les scopes qu'un package déclare en plus de ceux que dev\ApiDocLib::SCOPE_LABELS range, chacun
	 * une section de la référence. Indexé par scope ('learning'), avec 'title' et 'description',
	 * l'accroche de la page de la section.
	 */
	public static function getScopes(): array {
		return array_merge([], ...self::notify('apiScopes'));
	}

	/**
	 * Le résumé des routes que leur motif ne suffit pas à décrire — les actions et les sous-listes,
	 * cf dev\ApiDocLib. Indexé 'MÉTHODE route' ('PATCH sale/{id}/draft'), une même route pouvant en
	 * porter deux (marketSale/{id}/payment, posé en PATCH et retiré en DELETE).
	 *
	 * Ici plutôt que dans le *.a.php qui déclare la route : une phrase française relève de
	 * l'interface, seule couche où s() la donne traduite.
	 */
	public static function getRoutes(): array {
		return array_merge([], ...self::notify('apiRoutes'));
	}

	/**
	 * Ce que le describer du module dit d'une colonne : son libellé et, pour une énumération, le
	 * libellé de chaque valeur. La référence s'en sert quand apiFields() ne dit rien du champ, ce qui
	 * est le cas de la plupart : l'écran nomme déjà chaque colonne, l'intégrateur lisait un nom nu.
	 *
	 * Une icône précède parfois le libellé d'une valeur (la caisse) : seul le texte est repris.
	 */
	public static function getDescriber(string $module, string $property): array {

		$ui = $module.'Ui';

		if(class_exists($ui) === FALSE or method_exists($ui, 'p') === FALSE) {
			return ['label' => NULL, 'values' => []];
		}

		// Un describer de la comptabilité prépare son champ de saisie pour la structure connectée, et
		// la documentation se génère sans structure : ces colonnes restent décrites par leur seul nom
		try {
			$d = $ui::p($property);
		} catch(\Exception) {
			return ['label' => NULL, 'values' => []];
		}

		$values = [];

		foreach(is_array($d->values) ? $d->values : [] as $value => $label) {

			if(is_string($label)) {
				$values[$value] = trim(strip_tags($label));
			}

		}

		return [
			'label' => is_string($d->label) ? trim(strip_tags($d->label)) : NULL,
			'values' => $values
		];

	}

}
?>
