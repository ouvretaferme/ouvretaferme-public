<?php
namespace note;

class AlertUi {

	public static function getError(string $fqn): mixed {

		return match($fqn) {

			'Note::title.check' => s("Donnez un titre à la note, de 100 caractères au plus"),
			'Note::content.check' => s("Le contenu de la note est trop long"),
			'Note::readers.check' => s("Choisissez qui peut lire la note"),
			'Note::cTarget.cast' => s("Choisissez les rattachements dans la liste"),
			'Note::cTarget.max' => s("Rattachez la note à {value} objets au plus", NoteSetting::TARGETS_MAX),
			'Note::notebook.check' => s("Choisissez un carnet de la liste"),
			'Note::notebook.structure' => s("Choisissez un carnet de la liste"),

			'Notebook::name.check' => s("Donnez un nom au carnet, de 50 caractères au plus"),
			'Notebook::name.duplicate' => s("Un carnet porte déjà ce nom"),

			default => NULL

		};

	}

	public static function getSuccess(string $fqn): ?string {

		return match($fqn) {

			'Note::created' => s("La note a bien été enregistrée."),
			'Note::updated' => s("La note a bien été mise à jour."),
			'Note::deleted' => s("La note a bien été supprimée."),
			'Note::pinned' => s("La note est épinglée en tête des notes."),
			'Note::unpinned' => s("La note n'est plus épinglée."),

			'Notebook::created' => s("Le carnet a bien été créé."),
			'Notebook::updated' => s("Le carnet a bien été renommé."),
			'Notebook::deleted' => s("Le carnet a bien été supprimé, ses notes sont dans Non classé."),

			default => NULL

		};

	}

}
?>
