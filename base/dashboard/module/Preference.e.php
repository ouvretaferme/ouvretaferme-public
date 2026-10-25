<?php
namespace dashboard;

/**
 * Ce qu'un membre a réglé de son accueil dans une structure : une ligne par pièce réglée, et rien
 * pour celles qu'il n'a jamais touchées.
 *
 * `name` porte indifféremment un bloc ou une contribution, les deux formes de déclaration que le
 * moteur connaît : c'est le mot que le moteur emploie lui-même (`DashboardLib::getEntry()`), et la
 * table en range les deux. Il porte aussi le nom d'un univers, pour son seul rang (L39) : l'état d'un
 * univers se déduit toujours de ses blocs, l'éteindre les éteint tous, et la ligne à son nom ne dit
 * que sa place. Son `hidden` n'est jamais lu, et `PreferenceLib::updateHidden()` refuse de l'écrire.
 *
 * Une préférence n'est lisible que par son propre utilisateur, et écrivable par lui dans une
 * structure qu'il peut ouvrir : deux droits, donc `canRead()` et `canWrite()` séparés, et
 * `canCreate`/`canUpdate`/`canDelete` qui retombent sur le second (`framework/core/Data.php`).
 */
class Preference extends PreferenceElement {

	public function canRead(): bool {

		$this->expects(['user']);

		$eUserOnline = \user\ConnectionLib::getOnline();

		if($eUserOnline->empty()) {
			return FALSE;
		}

		return ($this['user']['id'] === $eUserOnline['id']);

	}

	/**
	 * Écrire son réglage demande plus que le lire : la ligne est la sienne, **et** la structure est
	 * une structure qu'il peut ouvrir. Sans le second, le membre d'une structure pourrait régler son
	 * accueil dans n'importe quelle autre, l'identifiant de structure venant du formulaire.
	 *
	 * `canRead()` ne pose pas cette question : une ligne déjà écrite est la sienne par construction,
	 * `PreferenceLib` ne lisant jamais que `whereUser(getOnline())`.
	 */
	public function canWrite(): bool {

		$this->expects(['user', 'structure']);

		if($this->canRead() === FALSE) {
			return FALSE;
		}

		return $this['structure']->canRead();

	}

	/**
	 * Ce que l'élément tolère, et non qui le demande : une structure dont l'accueil n'est pas ouvert
	 * n'a aucun réglage à recevoir. La règle est rangée ici et non dans `canWrite()` parce que
	 * `ModuleApi` n'appelle que les `accept*` (`framework/core/Api.php`), et qu'une règle d'état posée
	 * dans un `can*` serait invisible depuis l'API.
	 */
	public function acceptWrite(): bool {

		$this->expects(['structure']);

		return DashboardSetting::isEnabled($this['structure']);

	}

	public static function properties(\PropertyList $l): void {

		$l->property('name')
			// replaceCheck : la règle couvre ce que le schéma vérifie et davantage. Le schéma borne la
			// longueur d'un texte libre ; ici la liste est fermée, et c'est l'inventaire des packages
			// qui la donne.
			->replaceCheck(fn(string $name): bool => in_array($name, self::getNames(), TRUE));

	}

	/**
	 * Les noms qu'une ligne peut porter. Un nom qu'aucun package ne déclare n'a aucune pièce à
	 * éteindre, et une contribution d'un emplacement que le membre ne règle pas n'a pas d'interrupteur.
	 *
	 * Les univers y sont pour leur rang. Leur nom ne peut pas croiser celui d'une pièce, qui porte
	 * toujours le tiret de `<package>-<quoi>` (`test/DashboardBlockSentinel.t.php`).
	 */
	public static function getNames(): array {

		$names = [
			...DashboardSetting::SECTIONS,
			...array_keys(DashboardLib::getBlocks())
		];

		foreach(DashboardLib::getContributions() as $name => $contribution) {
			if(in_array($contribution['slot'], DashboardSetting::SLOTS_FIXED, TRUE) === FALSE) {
				$names[] = $name;
			}
		}

		return $names;

	}

}
?>
