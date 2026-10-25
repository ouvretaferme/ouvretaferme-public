<?php
namespace map;

/**
 * Ce qu'une note montre d'une planche à laquelle elle est rattachée (`NoteTargetLib`).
 */
class NoteTargetUi {

	public static function getGroup(): string {
		return s("Emplacement");
	}

	/**
	 * La parcelle devant la planche, comme `BedUi` les enchaîne (`Bed.u.php`) : une structure nomme
	 * souvent « 1 » une planche de chaque parcelle, et la puce d'une note doit les départager comme la
	 * suggestion qui l'a posée. Une parcelle qui tient lieu de jardin entier (`zoneFill`) n'a pas de nom
	 * à elle : c'est le jardin qui la nomme. Le libellé s'écrit en texte (`omnibox.js`) ou passe par
	 * `encode()`, d'où le `>` sans entité.
	 */
	public static function getLabel(Bed $eBed): string {

		if($eBed['plot']['zoneFill']) {
			return $eBed['zone']['name'].' > '.$eBed['name'];
		}

		return $eBed['plot']['name'].' > '.$eBed['name'];

	}

	public static function getIcon(Bed $eBed): ?string {
		return NULL;
	}

	/**
	 * Le jardin de la parcelle, que le libellé ne nomme pas déjà.
	 */
	public static function getDetail(Bed $eBed): ?string {

		if($eBed['plot']['zoneFill']) {
			return NULL;
		}

		return $eBed['zone']['name'];

	}

}
?>
