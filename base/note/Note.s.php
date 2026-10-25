<?php
namespace note;

class NoteSetting extends \Settings {

	// Le nombre de cartes d'une page de la liste. Une structure tient jusqu'à 363 notes de série
	// (relevé du 16/10, `unification.md` du chantier) : la liste se pagine, et 48 cartes remplissent
	// des rangs entiers de deux, trois ou quatre colonnes.
	const LIST_MAX = 48;

	// Le début du texte que porte une carte : la carte en coupe trois lignes, et une note entière
	// descendrait sinon jusqu'à l'écran pour être aussitôt masquée.
	const EXTRACT_LIMIT = 300;

	// Les objets auxquels une note se rattache. Une note parle d'une espèce, d'une intervention, d'une
	// planche : dix rattachements passent ce qu'on écrit d'un même sujet, et tiennent en trois rangs de
	// badges à la largeur d'un téléphone. Même borne que les étiquettes d'un bloc (`BlockSetting::TAGS_MAX`).
	const TARGETS_MAX = 10;

	// Ce qu'une famille de l'omnibox reçoit pour une recherche. Le déroulant en montre huit et compte le
	// reste (`UtilOmnibox.groupLimit`) : au-delà de cinquante, ce compte s'arrête à 42 et invite à
	// préciser, plutôt que d'envoyer au navigateur les mille planches d'une grande structure.
	const SEARCH_LIMIT = 50;

	/**
	 * Les options de l'éditeur du contenu, seule source pour le champ et pour `build()`, qui doivent
	 * accepter la même chose. Une note prend des photos, une par figure, chacune avec sa légende : une
	 * photo prise au champ se comprend par ce qu'on en dit. La vidéo, la citation et la grille sont
	 * retirées à l'écriture, une note n'étant pas une page à mettre en forme.
	 */
	public static function getEditorOptions(): array {

		return [
			'acceptFigure' => TRUE,
			'figureOnlyImage' => TRUE,
			'figureCaption' => TRUE
		];

	}

	/**
	 * Le package d'un objet rattaché est le segment qui précède le premier tiret de son type, qui s'écrit
	 * `<package>-<quoi>`, comme pour un bloc (`BlockSetting::getPackage()`).
	 */
	public static function getPackage(string $targetType): string {
		return explode('-', $targetType)[0];
	}

	/**
	 * La classe de la paire qui décrit un type, `resolve($targetType, 'NoteTargetLib')` ou `'NoteTargetUi'`.
	 * Le nom est calculé à chaque appel, sans mémoïsation : rien ici ne dépend de la structure connectée.
	 */
	public static function resolve(string $targetType, string $class): string {
		return '\\'.self::getPackage($targetType).'\\'.$class;
	}

}
?>
