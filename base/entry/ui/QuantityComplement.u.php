<?php
namespace entry;

class QuantityComplementUi {

	/**
	 * La question du champ tant que la quantité manque, dans l'unité qui sera écrite.
	 */
	public static function getPlaceholder(string $unit): string {

		return match($unit) {
			\series\Task::KG => s("Combien de kilos ?"),
			\series\Task::UNIT => s("Combien de pièces ?"),
			\series\Task::BUNCH => s("Combien de bottes ?"),
			UnitComplementLib::CRATE => s("Combien de caisses ?"),
		};

	}

	/**
	 * La question tant que la phrase n'a encore rien dit : sans unité, parce qu'un mot de plus peut toujours
	 * en choisir une autre que celle qui s'écrirait.
	 */
	public static function getBlankPlaceholder(): string {
		return s("Combien ?");
	}

}
?>
