<?php
namespace learning;

/**
 * Un fichier à télécharger, posé sur une leçon : il se lit comme sa leçon et s'écrit comme sa
 * formation.
 */
class Attachment extends AttachmentElement {

	/**
	 * Au-delà, une leçon n'est plus une leçon mais un dépôt de fichiers.
	 */
	const MAX_BY_LESSON = 50;

	public static function getSelection(): array {

		return parent::getSelection() + [
			'lesson' => Lesson::getSelection(),
			'course' => Course::getSelectionWithCommunity(),
		];

	}

	public function canRead(): bool {

		$this->expects(['lesson']);

		return $this['lesson']->canRead();

	}

	public function canWrite(): bool {

		$this->expects(['course']);

		return $this['course']->canWrite();

	}

	public function acceptRead(): bool {

		$this->expects(['lesson']);

		return $this['lesson']->acceptRead();

	}

	public function acceptWrite(): bool {

		$this->expects(['course']);

		return $this['course']->acceptWrite();

	}

	/**
	 * Celle de la leçon, qui laisse l'auteur lire son brouillon : c'est elle que le téléchargement
	 * valide, comme la lecture de la leçon (`page/lesson.p.php`).
	 */
	public function assertRead(): bool {

		$this->expects(['lesson']);

		return $this['lesson']->assertRead();

	}

}
?>
