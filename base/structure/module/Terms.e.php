<?php
namespace structure;

class Terms extends TermsElement {

	// Une acceptation est une preuve : aucune page ne la lit pour la modifier ou la retirer
	public function canRead(): bool {
		return FALSE;
	}

	public static function properties(\PropertyList $l): void {

		// La case cochée n'est pas une colonne : seule l'acceptation qu'elle déclenche est gardée. Sans colonne, aucune
		// conversion ne précède le contrôle, qui reçoit l'entrée brute : seule la valeur que poste la case vaut accord
		$l->property('terms')
			->replaceCheck(fn(mixed $terms): bool => ($terms === '1'))
			// L'acceptation est une preuve au nom de qui est connecté : sous une connexion « en tant que », elle prêterait
			// à l'administrateur un accord donné par quelqu'un d'autre. Même décision que les CGU (`user\TosAiLib::getPending()`)
			->addAfterCheck('external', fn(): bool => (\user\ConnectionLib::isLoggedExternal() === FALSE));

	}

}
?>
