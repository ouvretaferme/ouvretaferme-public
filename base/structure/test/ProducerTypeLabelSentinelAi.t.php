<?php
namespace structure;

/**
 * Chaque catégorie de ferme a-t-elle encore de quoi s'écrire à l'écran ?
 *
 * **De quoi c'est l'inventaire.** Les catégories qu'une ferme peut porter, c'est-à-dire toutes les valeurs
 * de `Structure.producerType` dans `structure.yml`, et pour chacune ce qu'un écran écrit quand il la
 * montre : un libellé (`displayValues` du describer) et un signe dans une liste (`displayIcons`). Ce n'est
 * **pas** l'inventaire des catégories qu'on propose : celles-là sont les options du bouton radio,
 * `$d->values` du même describer, qui ne porte volontairement ni la ferme projet d'un groupe ni la ferme
 * école, parce qu'aucune ferme ne se déclare ainsi. Une sentinelle qui comparerait l'enum au bouton radio
 * se réparerait en offrant ces deux catégories à l'utilisateur : un défaut produit obtenu en faisant verdir
 * un test.
 *
 * **Pourquoi une sentinelle.** Une valeur ajoutée au `.yml` sans libellé s'afficherait dans l'écran
 * d'administration des structures comme un bloc sans titre et une cellule d'usages vide, sans que rien ne
 * lève. Une valeur est annoncée au 2026-09-16 (`REGISTERED`, ferme enregistrée par un réseau, chantier
 * mes-producteurs) : elle s'écrit en deux lignes, une dans chaque table, sans toucher au bouton radio.
 *
 * Elle compare dans les deux sens, pour chacune des deux tables : une catégorie du schéma sans entrée, et
 * une entrée qui ne désigne plus aucune catégorie. Une catégorie sans signe se déclare dans
 * `WITHOUT_ICON`, avec sa raison, et la déclaration se compare elle aussi dans les deux sens.
 *
 * Lecture seule, rien n'est écrit en base.
 *
 * Lancement : dev/test package=structure file=ProducerTypeLabelSentinelAi
 */
class ProducerTypeLabelSentinelAiTest extends \Test {

	/**
	 * Les catégories qu'une liste ne marque d'aucun signe, et pourquoi.
	 */
	const WITHOUT_ICON = [
		// Décision sur la planche du lot L3, le 2026-09-16 : « Afficher la catégorie sous forme
		// de picto dans usages uniquement si c'est pas une forme de production ». La ferme installée est le
		// cas courant, et un signe posé sur presque toutes les lignes ne distinguerait plus rien.
		Structure::REAL => 'la ferme installée est le cas courant',
	];

	public function testEveryCategoryHasADisplayLabel(): void {

		$this->assertInventory('displayValues', StructureUi::p('producerType')->displayValues);

		foreach(StructureUi::p('producerType')->displayValues as $producerType => $label) {
			$this->assertTrue(is_string($label) and $label !== '', 'libellé vide pour la catégorie '.$producerType);
		}

	}

	public function testEveryCategoryHasADisplayIcon(): void {

		$displayIcons = StructureUi::p('producerType')->displayIcons;

		$this->assertInventory('displayIcons', $displayIcons);

		$withoutIcon = array_keys(array_filter($displayIcons, fn(?string $icon) => $icon === NULL));

		$undeclared = array_values(array_diff($withoutIcon, array_keys(self::WITHOUT_ICON)));
		$this->assertEquals([], $undeclared, 'catégories sans signe non déclarées dans WITHOUT_ICON : '.implode(', ', $undeclared));

		$stale = array_values(array_diff(array_keys(self::WITHOUT_ICON), $withoutIcon));
		$this->assertEquals([], $stale, 'catégories déclarées sans signe qui en portent un ou n\'existent plus : '.implode(', ', $stale));

	}

	/**
	 * Le bouton radio reste une partie de la table d'affichage : chaque choix offert s'écrit à l'écran
	 * avec le même mot qu'au moment où on le choisit.
	 */
	public function testChoicesAreWrittenWithTheSameWord(): void {

		$describer = StructureUi::p('producerType');

		foreach($describer->values as $producerType => $label) {
			$this->assertEquals($label, $describer->displayValues[$producerType] ?? NULL, 'le choix '.$producerType.' ne s\'écrit pas comme il se propose');
		}

	}

	private function assertInventory(string $table, ?array $entries): void {

		$this->assertTrue(is_array($entries), 'le describer de producerType ne porte pas de table '.$table);

		$entries ??= [];
		$enum = StructureUi::p('producerType')->enum;

		$this->assertTrue(is_array($enum) and $enum !== [], 'l\'enum de producerType est introuvable : la sentinelle ne peut rien comparer');

		$missing = array_values(array_diff($enum, array_keys($entries)));
		$this->assertEquals([], $missing, 'catégories du schéma sans entrée dans '.$table.' : '.implode(', ', $missing));

		$unknown = array_values(array_diff(array_keys($entries), $enum));
		$this->assertEquals([], $unknown, 'entrées de '.$table.' qui ne désignent aucune catégorie du schéma : '.implode(', ', $unknown));

	}

}
?>
