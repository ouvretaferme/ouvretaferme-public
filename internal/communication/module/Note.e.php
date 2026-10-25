<?php
namespace communication;

class Note extends NoteElement {

	// Une note de l'équipe ne se lit et ne s'écrit que depuis l'administration du site.
	public function canRead(): bool {

		return \user\ConnectionLib::getOnline()->isAdmin();

	}

	public static function properties(\PropertyList $l): void {

		// Les cases cochées arrivent en liste : la clé que le navigateur leur donne ne dit rien, et une
		// valeur postée deux fois ne vise pas deux fois la même structure. La liste se range avant le
		// contrôle, qui juge alors ce que la note stockera.
		$list = function(array &$values): bool {

			$values = array_values(array_unique($values));

			return TRUE;

		};

		// La règle couvre ce que le schéma vérifie (un tableau), et refuse en plus une liste vide, qui
		// n'enverrait la note à personne.
		$l->property('structureTypes')
			->addAfterCast('list', $list)
			->replaceCheck(fn(array $structureTypes): bool => (
				$structureTypes !== [] and
				array_diff($structureTypes, array_keys(CommunicationSetting::NOTE_STRUCTURE_TYPES)) === []
			));

		// Aucune fonctionnalité cochée est un choix : la note part alors à tous les types retenus.
		$l->property('features')
			->addAfterCast('list', $list)
			->replaceCheck(fn(array $features): bool => array_diff($features, CommunicationSetting::NOTE_FEATURES) === []);

		// Un libellé sans page, ou une page sans libellé, ferait un bouton qui ne mène nulle part ou
		// qu'on ne peut pas lire. Chaque refus se pose sur le champ resté vide, pour qu'on voie lequel
		// remplir. Le libellé se construit avant la page : il lit donc ce qui a été posté pour elle,
		// et la page lit le libellé construit.
		$l->property('buttonLabel')
			->addAfterCheck('missing', fn(?string $buttonLabel, \PropertyList $l): bool => ($buttonLabel !== NULL or ($l->input['buttonPath'] ?? '') === ''));

		$l->property('buttonPath')
			->addAfterCheck('path', fn(?string $buttonPath): bool => NoteLib::isPath($buttonPath))
			->addAfterCheck('missing', fn(?string $buttonPath, \PropertyList $l): bool => ($buttonPath !== NULL or $l->isBuilt('buttonLabel') === FALSE or $this['buttonLabel'] === NULL));

	}

}
?>
