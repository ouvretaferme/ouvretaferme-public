<?php
namespace learning;

/**
 * Une séance de lecture d'une leçon par un stagiaire (lot L8c). Elle ne s'écrit que par
 * `ReadingLib::see()` et ne se lit qu'en journal, par la page du suivi, qui borne elle-même qui le
 * relit : l'équipe pour les stagiaires qu'elle suit, le stagiaire pour lui-même. Le droit propre de
 * la ligne est donc celui de son stagiaire, comme `Progress`.
 */
class Reading extends ReadingElement {

	public function canRead(): bool {

		$this->expects(['user']);

		return $this['user']->isOnline();

	}

}
?>
