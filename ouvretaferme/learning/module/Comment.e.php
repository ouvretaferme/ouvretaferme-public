<?php
namespace learning;

/**
 * Un message du fil d'une leçon. Il se lit par qui lit la leçon, s'écrit par son auteur, et se
 * retire aussi par l'équipe qui écrit la formation.
 *
 * Le fil est un état de la leçon publiée : `accept*` suivent la lecture de la leçon et non son
 * écriture, qui dit seulement que le propriétaire écrit des formations et laisserait commenter un
 * brouillon.
 *
 * L'équipe qui écrit la formation modère aussi le fil (`canModerate()`) : elle retire un message, et
 * le marque lu pour toute l'équipe (`readAt`, `readBy`), quel que soit l'état de la leçon.
 *
 * Le fil a un seul niveau : on répond à un commentaire (`parent`), jamais à une réponse.
 */
class Comment extends CommentElement {

	public static function getSelection(): array {

		return parent::getSelection() + [
			'lesson' => Lesson::getSelection(),
			'createdBy' => ['firstName', 'lastName', 'visibility', 'vignette'],
		];

	}

	/**
	 * Ce que lit la page des commentaires d'un groupe : l'auteur, le lecteur, l'auteur du commentaire
	 * auquel une réponse répond, et de la leçon ce que la ligne nomme et ce que pèsent `canModerate()`
	 * et `assertAnswer()`.
	 */
	public static function getInboxSelection(): array {

		return [
			'id', 'text', 'readAt', 'createdAt',
			'parent' => ['createdBy' => ['firstName', 'lastName']],
			'readBy' => ['firstName', 'lastName'],
			'createdBy' => ['firstName', 'lastName', 'visibility', 'vignette'],
			'lesson' => ['name', 'course' => ['name', 'type', 'status', 'open', 'catalog', 'community' => ['name', 'type', 'communityType']]],
		];

	}

	/**
	 * Ce que le filet d'une réponse montre sous son commentaire.
	 */
	public static function getAnswerSelection(): array {

		return [
			'id', 'text', 'createdAt', 'parent',
			'createdBy' => ['firstName', 'lastName'],
		];

	}

	/**
	 * Un compte est demandé même sur une leçon en lecture libre : le fil nomme ses auteurs, qu'un
	 * visiteur anonyme n'a pas à lire.
	 */
	public function canRead(): bool {

		$this->expects(['lesson']);

		return (
			\user\ConnectionLib::isLogged() and
			$this['lesson']->canRead()
		);

	}

	public function canCreate(): bool {
		return $this->canRead();
	}

	public function canUpdate(): bool {

		$this->expects(['createdBy']);

		return (
			$this->canRead() and
			$this['createdBy']->is(\user\ConnectionLib::getOnline())
		);

	}

	public function canDelete(): bool {

		return (
			$this->canUpdate() or
			$this->canModerate()
		);

	}

	public function canModerate(): bool {

		$this->expects(['lesson' => ['course']]);

		return $this['lesson']['course']->canWrite();

	}

	/**
	 * Un tutoriel n'a pas de fil : il se lit sans compte et n'a personne pour répondre. Les
	 * commentaires qu'il porterait ne se lisent plus, ni ne s'écrivent (`acceptWrite()`).
	 */
	public function acceptRead(): bool {

		$this->expects(['lesson' => ['course']]);

		return (
			$this['lesson']['course']->acceptTutorial() === FALSE and
			$this['lesson']->acceptRead()
		);

	}

	public function acceptWrite(): bool {
		return $this->acceptRead();
	}

	/**
	 * Répondre à ce commentaire : le droit est celui d'écrire dans le fil, l'état est d'être un
	 * commentaire de premier niveau sous une leçon dont le fil est ouvert.
	 */
	public function canAnswer(): bool {
		return $this->canCreate();
	}

	public function acceptAnswer(): bool {

		$this->expects(['parent']);

		return (
			$this['parent']->empty() and
			$this->acceptWrite()
		);

	}

	public function assertAnswer(): bool {
		return $this->acceptAnswer() and $this->canAnswer();
	}

	public static function properties(\PropertyList $l): void {

		$l->property('parent')
			// La page a lu le commentaire demandé dans la leçon, parmi ceux de premier niveau
			// (`CommentLib::getParentByLesson()`) : un parent posté qui n'est pas celui-là est d'une autre
			// leçon, une réponse, ou un commentaire retiré depuis l'ouverture du panneau.
			->addAfterCheck('thread', function(Comment &$eCommentParent): bool {

				$this->expects(['eCommentParent']);

				if($this['eCommentParent']->is($eCommentParent) === FALSE) {
					return FALSE;
				}

				$eCommentParent = $this['eCommentParent'];

				return TRUE;

			});

	}

}
?>
