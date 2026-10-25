<?php
namespace learning;

/**
 * Ce que le moteur des blocs demande à la formation : les usages qu'elle déclare, les types qu'elle
 * accepte, l'objet qui porte les blocs et ses droits. La classe est résolue par son nom depuis la
 * valeur stockée (`learning-lesson`, `\block\BlockSetting::resolve()`) : elle ne porte pas le
 * suffixe `Ai`, et `block/test/BlockTypeSentinel.t.php` en garde le contrat.
 */
class BlockOwnerLib {

	use \Notifiable;

	const LESSON = 'learning-lesson';

	public static function getOwners(): array {
		return [self::LESSON];
	}

	/**
	 * Les types d'une leçon, dans l'ordre des tuiles du premier bloc, du menu du « + » et du « / ».
	 */
	public static function getTypes(string $owner): array {

		return match($owner) {
			self::LESSON => [
				\block\BlockTypeLib::TEXT,
				\block\BlockTypeLib::SECTION,
				\block\BlockTypeLib::VIDEO,
				\block\BlockTypeLib::BUTTON,
				\block\BlockTypeLib::CALLOUT,
				\block\BlockTypeLib::STEPS,
				\block\BlockTypeLib::ANSWERS,
				\block\BlockTypeLib::ACCORDION,
				\block\BlockTypeLib::TABS,
				\block\BlockTypeLib::FIGURE,
				\block\BlockTypeLib::QUOTE,
				\block\BlockTypeLib::TAGS,
				\block\BlockTypeLib::TABLE,
			],
		};

	}

	/**
	 * Les types qu'une leçon propose au premier niveau : ceux de l'usage (`getTypes()`), puis ceux qu'un
	 * package fermé ajoute à cette leçon. `learning` ne nomme pas ce package : il émet l'événement, et
	 * seule l'application comptable l'écoute, comme pour `LessonLib::fillContent()`. La page lit cette
	 * liste une fois et la passe à `BlockLib::getNew()` et `fillEditor()`, et `Block::type.allowed` juge
	 * un bloc posté contre elle.
	 */
	public static function getTypesByOwner(string $owner, Lesson $eLesson): array {

		$types = self::getTypes($owner);

		if(\structure\StructureSetting::hasAccountingApp()) {
			$types = array_merge($types, ...self::notify('getBlockTypes', $eLesson));
		}

		return $types;

	}

	/**
	 * La leçon relue avec sa formation, dont ses droits dépendent. Vide si elle n'existe plus : le
	 * moteur refuse alors tout geste sur ses blocs.
	 */
	public static function getOwner(string $owner, int $reference): Lesson {

		return match($owner) {
			self::LESSON => LessonLib::getById($reference),
		};

	}

	/**
	 * Les blocs d'une leçon se lisent comme la leçon à sa page, par `Lesson::assertRead()` : son auteur
	 * les relit en brouillon, un lecteur une fois la formation publiée, et personne quand la structure
	 * est fermée aux formations. C'est le droit de lecture que le moteur de blocs demande à chaque
	 * propriétaire (`Block::canRead()`) : quelle que soit la route qui le posera, elle doit répondre
	 * comme la page de la leçon. `canRead()` seul ouvrirait le brouillon d'une formation que tous
	 * lisent, retenue au catalogue ou tutoriel.
	 */
	public static function canRead(Lesson $eLesson): bool {
		return $eLesson->assertRead();
	}

	/**
	 * Écrire un bloc, c'est écrire la leçon : mêmes droits et même état que l'écran d'auteur
	 * (`canUpdate`, `acceptUpdate`), qui ouvre l'édition, et fermé avec les formations comme la
	 * lecture.
	 */
	public static function canWrite(Lesson $eLesson): bool {

		return (
			$eLesson['course']->acceptLearning() and
			$eLesson->canUpdate()
		);

	}

	public static function acceptWrite(Lesson $eLesson): bool {
		return $eLesson->acceptUpdate();
	}

	/**
	 * Les options d'éditeur du texte d'un bloc d'une leçon, seule source pour les deux chemins qui
	 * l'écrivent : le bloc (`Block::content`) et le contenu d'API (`Lesson::content`, qui s'écrit dans
	 * un bloc texte). Le bloc texte accepte une photo entre deux paragraphes (`figureOnlyImage`), et
	 * garde sa légende (`figureCaption`), que le mode photo seule retire sans ce réglage. Une vidéo a
	 * son propre bloc, et est retirée à l'écriture. L'écran ne pose qu'une photo par figure, mais rien
	 * à l'écriture n'en plafonne le nombre : un POST ou l'API peuvent en mettre plusieurs, lues en
	 * mosaïque (`Block.t.php`, `testBuildFigure()`). Le texte des autres types (encadré, citation, étape,
	 * volet, onglet, chiffre, réponse) ne porte aucune figure, leur forme n'ayant pas la largeur d'une
	 * image.
	 */
	public static function getEditorOptions(string $owner, string $type): array {

		return match($owner) {
			self::LESSON => match($type) {
				\block\BlockTypeLib::TEXT => ['acceptFigure' => TRUE, 'figureOnlyImage' => TRUE, 'figureCaption' => TRUE],
				\block\BlockTypeLib::CALLOUT,
				\block\BlockTypeLib::QUOTE,
				\block\BlockTypeLib::STEPS,
				\block\BlockTypeLib::ACCORDION,
				\block\BlockTypeLib::TABS,
				\block\BlockTypeLib::FIGURE,
				\block\BlockTypeLib::TABLE,
				\block\BlockTypeLib::VIDEO,
				\block\BlockTypeLib::BUTTON,
				\block\BlockTypeLib::SECTION,
				\block\BlockTypeLib::ANSWERS,
				\block\BlockTypeLib::TAGS,
				\block\BlockTypeLib::ITEM,
				\block\BlockTypeLib::ANSWER => ['acceptFigure' => FALSE],
			},
		};

	}

	/**
	 * Un bloc écrit, déplacé ou retiré change la leçon, donc sa formation : la fiche date sa dernière
	 * mise à jour de là, comme pour un contenu écrit avant les blocs. Il peut aussi l'écrire ou la
	 * vider (`LessonLib::synchronizeWritten()`).
	 */
	public static function touch(string $owner, int $reference): void {

		$eLesson = self::getOwner($owner, $reference);

		LessonLib::update($eLesson, []);
		LessonLib::synchronizeWritten($eLesson);

	}

}
?>
