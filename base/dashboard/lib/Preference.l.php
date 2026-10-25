<?php
namespace dashboard;

/**
 * Ce qu'un membre a réglé de son accueil, et la seule porte d'écriture de `dashboardPreference`.
 *
 * Une ligne existe pour une pièce réglée, et pour elle seule : le défaut se calcule à la lecture
 * depuis la déclaration de chaque pièce, donc un membre qui n'a jamais rien touché n'a aucune ligne
 * et voit exactement ce que le produit lui montre aujourd'hui.
 *
 * Aucune mémoïsation de la carte : elle dépend du tenant **et** du membre, et un cache statique indexé
 * par la seule structure servirait à l'un ce que l'autre a réglé.
 *
 * Les deux lectures que fait un accueil sont donc assumées telles quelles. Ce qui les borne n'est pas
 * le nombre de lignes que la table porte aujourd'hui, qui changera dès qu'on ajoutera des univers :
 * c'est l'UNIQUE `(user, structure, name)`, dont elles attaquent le préfixe, et qui garantit que
 * chacune ne lise jamais que les lignes d'un seul membre dans une seule structure. Un cache devrait
 * être indexé sur ces deux mêmes clés pour être correct, c'est-à-dire refaire l'index qu'il
 * consulterait.
 */
class PreferenceLib extends PreferenceCrud {

	/**
	 * `user` et `structure` ne sont pas construits : ils ne viennent jamais du formulaire, c'est ce Lib
	 * qui les pose depuis la connexion en cours et la structure de la route. Ce qui n'est pas dans ces
	 * deux listes n'est jamais construit, donc `position` et `hidden` y sont tous deux, chacun écrit
	 * par son geste.
	 */
	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['name', 'hidden', 'position'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['hidden', 'position'];
	}

	/**
	 * La carte du membre connecté dans cette structure.
	 *
	 * @return array [nom => ['hidden' => bool, 'position' => ?int]]
	 */
	public static function getByMember(\structure\Structure $eStructure): array {

		$eUser = \user\ConnectionLib::getOnline();

		if($eUser->empty()) {
			return [];
		}

		$cPreference = Preference::model()
			->select(['name', 'hidden', 'position'])
			->whereUser($eUser)
			->whereStructure($eStructure)
			->getCollection();

		$preferences = [];

		foreach($cPreference as $ePreference) {

			$preferences[$ePreference['name']] = [
				'hidden' => $ePreference['hidden'],
				'position' => $ePreference['position']
			];

		}

		return $preferences;

	}

	/** La ligne d'une pièce pour le membre connecté, ou un élément vide quand il n'a rien réglé. */
	public static function getByName(\structure\Structure $eStructure, string $name): Preference {

		$eUser = \user\ConnectionLib::getOnline();

		if($eUser->empty()) {
			return new Preference();
		}

		return Preference::model()
			->select(Preference::getSelection())
			->whereUser($eUser)
			->whereStructure($eStructure)
			->whereName($name)
			->get();

	}

	/**
	 * Un seul interrupteur, celui d'une pièce.
	 *
	 * L'upsert se fait en deux temps, sur la forme du seul `upsert()` du dépôt
	 * (`framework/queue/lib/Task.l.php`), qui lit avant d'insérer.
	 */
	public static function updateHidden(\structure\Structure $eStructure, string $name, bool $hidden): void {

		// La ligne d'un univers ne porte que son rang : son état se déduit de ses blocs, et un `hidden`
		// écrit à son nom serait un second état que rien ne lirait. Le nom passe pourtant le `build()`,
		// qui l'accepte pour le rang ; c'est donc ici, seule porte d'écriture, que le refus se pose.
		if(in_array($name, DashboardSetting::SECTIONS, TRUE)) {
			Preference::fail('sectionHidden');
			return;
		}

		self::writeAll($eStructure, [$name => ['hidden' => $hidden]]);

	}

	/**
	 * L'interrupteur d'un univers : une ligne par bloc de la section, dans une transaction.
	 *
	 * La ligne au nom de la section n'est pas touchée : elle ne porte que son rang. L'état d'un univers
	 * se déduit de ses blocs, donc l'éteindre les éteint tous et le rallumer les rallume tous : c'est
	 * l'unique écriture, et la lecture n'a alors rien à réconcilier.
	 *
	 * `$names` vient du moteur, qui sait quels blocs la section range et lesquels ce membre peut voir :
	 * un nom qu'il n'aurait pas donné serait refusé par le `build()` de chaque ligne.
	 */
	public static function updateSection(\structure\Structure $eStructure, array $names, bool $hidden): void {

		$values = [];

		foreach($names as $name) {
			$values[$name] = ['hidden' => $hidden];
		}

		self::writeAll($eStructure, $values);

	}

	/**
	 * Le rangement d'une liste, après un glisser-déposer : la place de chaque nom est son rang dans
	 * `$names`, et `$names` est la liste entière de ce que l'étage porte, les blocs d'un univers ou
	 * les univers eux-mêmes.
	 *
	 * Le refus d'une liste périmée reprend celui de `BlockLib::updatePositions()` : un étage dont le
	 * contenu a changé sous les doigts (une pièce qu'un droit vient de retirer) écrirait des rangs qui
	 * ne désignent plus rien.
	 */
	public static function updatePositions(\structure\Structure $eStructure, array $choices, array $names): void {

		if(
			$names === []
			or count(array_unique($names)) !== count($names)
			or array_diff($names, $choices) !== []
			or array_diff($choices, $names) !== []
		) {
			Preference::fail('positionsIncomplete');
			return;
		}

		$values = [];

		foreach(array_values($names) as $position => $name) {
			$values[$name] = ['position' => $position];
		}

		self::writeAll($eStructure, $values);

	}

	/**
	 * La purge d'un membre qui quitte une structure, appelée par `MemberLib::deleteInternal()`.
	 *
	 * Les éléments sont lus avant la boucle plutôt que supprimés par critère : c'est le `delete()` du
	 * module qui porte les règles d'écriture, et une suppression de masse les contournerait.
	 */
	public static function deleteByUserAndStructure(\user\User $eUser, \structure\Structure $eStructure): void {

		$cPreference = Preference::model()
			->select('id')
			->whereUser($eUser)
			->whereStructure($eStructure)
			->getCollection();

		foreach($cPreference as $ePreference) {
			self::delete($ePreference);
		}

	}

	/**
	 * L'upsert d'un lot de lignes : tous les `build()` d'abord, la validation ensuite, les écritures
	 * en dernier, dans une transaction.
	 *
	 * ⚠️ **L'ordre est la correction, pas une élégance.** `build()` rend `void` et pose le fail sans
	 * lever (`framework/core/Data.php`) : écrire dans la foulée envoyait en `insert()` une ligne dont
	 * `name` n'avait pas été posé, qui échouait sur `Column 'name' cannot be null` au lieu de rendre le
	 * refus que l'écran sait dire (mesuré le 2026-10-01, `test/Preference.t.php`,
	 * `testUnknownNameIsRefused`).
	 *
	 * Le `FailWatch` se conclut par `validate()`, qui entre dans la chaîne d'erreur du cadriciel et la
	 * laisse rendre le message de `ui/Alert.u.php`. Il est donc **hors de la transaction** : la
	 * `FailAction` qu'il lève, posée entre `beginTransaction()` et `commit()`, laisserait le compteur à
	 * 1 et le COMMIT ne partirait jamais. C'est ce qui oblige à séparer les deux passes, et non une
	 * préférence de forme.
	 */
	private static function writeAll(\structure\Structure $eStructure, array $values): void {

		$fw = new \FailWatch();

		$cPreference = new \Collection();

		foreach($values as $name => $value) {

			$ePreference = self::getByName($eStructure, $name);

			if($ePreference->empty()) {

				$ePreference = new Preference([
					'user' => \user\ConnectionLib::getOnline(),
					'structure' => $eStructure
				]);

				$ePreference->build(self::getPropertiesCreate(), $value + ['name' => $name, 'hidden' => FALSE, 'position' => NULL]);

			} else {
				$ePreference->build(array_keys($value), $value);
			}

			$cPreference[] = $ePreference;

		}

		$fw->validate();

		Preference::model()->beginTransaction();

			foreach($cPreference as $position => $ePreference) {

				$properties = array_keys(array_values($values)[$position]);

				if($ePreference->offsetExists('id')) {

					// `updatedAt` est posé à l'insertion par le `SPECIAL(now)` du schéma, qui ne rejoue
					// pas sur un UPDATE : sans cette ligne, une préférence basculée dix fois garde l'heure
					// de sa création (mesuré le 2026-10-01, quatre lignes créées à 09:28:58 et réécrites
					// jusqu'à 09:52 la portaient encore). Une date qui ne bouge pas ne manque pas une
					// information, elle en affirme une fausse. Forme du dépôt : `quotation/lib/Suggestion.l.php`.
					$ePreference['updatedAt'] = new \Sql('NOW()');

					self::update($ePreference, [...$properties, 'updatedAt']);

				} else {
					self::create($ePreference);
				}

			}

		Preference::model()->commit();

	}

}
?>
