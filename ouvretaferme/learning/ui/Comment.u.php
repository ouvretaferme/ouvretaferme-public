<?php
namespace learning;

/**
 * Le fil d'une leçon, sous ses boutons de suivi : le titre et « Nouveau commentaire », puis un
 * message par ligne, du plus ancien au plus récent. Une ligne a la forme de celles de la page
 * Commentaires d'un groupe (util-deck-row-top) : le nom de l'auteur, que les stagiaires d'une
 * promotion ne connaissent pas tous, la marque des formateurs et le texte dessous, la date
 * et la roue à droite. Les réponses à un commentaire se lisent en filet sous son texte, dans sa
 * ligne, comme la réponse à un avis (`.learning-review-answer`).
 */
class CommentUi {

	public function __construct() {
		\Asset::css('learning', 'learning.css');
	}

	public function getThread(Lesson $eLesson): string {

		$eLesson->expects(['cComment', 'comments', 'eCommentNew']);

		if($eLesson['eCommentNew']->assertRead() === FALSE) {
			return '';
		}

		$cComment = $eLesson['cComment'];

		$h = '<div class="util-title mt-2" id="comments">';
			$h .= '<h3>'.s("Commentaires").self::getCounter($eLesson['comments']).'</h3>';
			if($eLesson['eCommentNew']->assertCreate()) {
				$h .= '<div>';
					$h .= '<a href="/learning/comment:create?lesson='.$eLesson['id'].'" class="btn btn-outline-primary">'.\Asset::icon('plus-circle').' '.s("Nouveau commentaire").'</a>';
				$h .= '</div>';
			}
		$h .= '</div>';

		if($cComment->empty()) {
			return $h;
		}

		$h .= '<div class="util-cards util-cards-1 util-thread">';
			$h .= '<div class="util-deck util-deck-open">';
				$h .= '<div class="util-deck-fold">';
					foreach($cComment as $eComment) {
						$h .= $this->getThreadRow($eComment);
					}
				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	private function getThreadRow(Comment $eComment): string {

		$h = '<div class="util-deck-row util-deck-row-top">';

			$h .= \user\UserUi::getVignette($eComment['createdBy'], '1.75rem');

			$h .= '<div class="util-deck-row-name">';
				$h .= $this->getRowMessage($eComment);
				$h .= $this->getRowAnswers($eComment);
			$h .= '</div>';

			$actions = [];

			if($eComment->assertAnswer()) {
				$actions[] = '<a href="/learning/comment:create?lesson='.$eComment['lesson']['id'].'&parent='.$eComment['id'].'" class="btn btn-outline-primary">'.s("Répondre").'</a>';
			}

			$wheel = $this->getThreadActions($eComment);

			if($wheel !== '') {
				$actions[] = $wheel;
			}

			$h .= '<div class="util-thread-side">';
				$h .= $this->getRowDate($eComment);
				$h .= $this->getRowButtons($actions);
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Les gestes d'une ligne, sous sa date : deux gestes se rangent côte à côte, un seul se pose tel
	 * quel.
	 */
	private function getRowButtons(array $actions): string {

		if(count($actions) > 1) {
			return '<div class="flex-buttons">'.implode('', $actions).'</div>';
		}

		return implode('', $actions);

	}

	/**
	 * Le nom de l'auteur, sa marque d'équipe et le texte : le même message dans le fil d'une leçon et
	 * sur la page Commentaires du groupe.
	 */
	private function getRowMessage(Comment $eComment): string {

		$h = '<b>'.\user\UserUi::name($eComment['createdBy']).'</b>';
		if($eComment['isTeam']) {
			$h .= ' <span class="badge badge-outline">'.s("Formateur").'</span>';
		}
		$h .= '<p>'.\util\TextUi::tiny(nl2br(encode($eComment['text'])), TRUE).'</p>';

		return $h;

	}

	/**
	 * Les réponses rangées sous un commentaire, chacune en filet : son auteur, sa date et son texte,
	 * sans vignette ni geste.
	 */
	private function getRowAnswers(Comment $eComment): string {

		$eComment->expects(['cCommentAnswer']);

		$h = '';

		foreach($eComment['cCommentAnswer'] as $eCommentAnswer) {

			$h .= '<div class="learning-review-answer">';
				$h .= '<b>'.\user\UserUi::name($eCommentAnswer['createdBy']).'</b>';
				if($eCommentAnswer['isTeam']) {
					$h .= ' <span class="badge badge-outline">'.s("Formateur").'</span>';
				}
				$h .= ' '.$this->getRowDate($eCommentAnswer);
				$h .= '<p class="mb-0">'.\util\TextUi::tiny(nl2br(encode($eCommentAnswer['text'])), TRUE).'</p>';
			$h .= '</div>';

		}

		return $h;

	}

	private function getRowDate(Comment $eComment): string {
		return '<span class="util-annotation">'.\util\DateUi::numeric($eComment['createdAt'], \util\DateUi::DAY_MONTH).'</span>';
	}

	/**
	 * La roue d'un message : « Modifier » pour son auteur, « Supprimer » pour lui et pour l'équipe.
	 * Un stagiaire devant le message d'un autre n'a aucun geste, donc pas de roue.
	 */
	private function getThreadActions(Comment $eComment): string {

		$update = $eComment->assertUpdate();
		$delete = $eComment->assertDelete();

		if($update === FALSE and $delete === FALSE) {
			return '';
		}

		$items = '';

		if($update) {
			$items .= '<a href="/learning/comment:update?id='.$eComment['id'].'" class="dropdown-item">'.s("Modifier le commentaire").'</a>';
		}
		if($update and $delete) {
			$items .= '<div class="dropdown-divider"></div>';
		}
		if($delete) {
			$confirm = $eComment['cCommentAnswer']->empty() ? s("Supprimer ce commentaire ?") : s("Supprimer ce commentaire et ses réponses ?");
			$items .= '<a data-ajax="/learning/comment:doDelete" post-id="'.$eComment['id'].'" class="dropdown-item" data-confirm="'.$confirm.'" data-waiter>'.s("Supprimer le commentaire").'</a>';
		}

		return \main\DesignUi::getGear($items, s("Commentaire de {value}", ['value' => \user\UserUi::name($eComment['createdBy'])]));

	}

	public static function urlInbox(\structure\Structure $eStructure): string {
		return '/groupe/'.$eStructure['id'].'/formations/commentaires';
	}

	/**
	 * L'entrée de la page, à droite du titre des formations du groupe.
	 */
	public static function getInboxButton(\structure\Structure $eStructure, int $commentsNew): string {
		return '<a href="'.self::urlInbox($eStructure).'" class="btn btn-outline-primary">'.\Asset::icon('chat-left-text').' '.s("Commentaires").self::getCounter($commentsNew).'</a>';
	}

	/**
	 * Le même en-tête que la boite des avis, sa voisine dans la rangée de boutons
	 * (`ReviewUi::getInboxTitle()`). Le compteur et « Tout marquer comme lu » se désignent : une
	 * lecture ne redessine qu'eux (`getInboxCounter()`), la bande du groupe ayant rangé le geste
	 * hors de la tête qui le porte.
	 */
	public function getInboxTitle(\structure\Structure $eStructure, int $commentsNew): string {

		$tools = ($commentsNew > 0)
			? '<a id="learning-inbox-read-all" data-ajax="/learning/community:doReadAll" post-id="'.$eStructure['id'].'" class="btn btn-outline-secondary">'.\Asset::icon('check2-all').' '.s("Tout marquer comme lu").'</a>'
			: '';

		return \main\DesignUi::getPageHeader(
			s("Commentaires").self::getInboxCounter($commentsNew),
			back: '/groupe/'.$eStructure['id'].'/formations',
			tools: $tools,
			backLabel: s("Formations")
		);

	}

	public static function getInboxCounter(int $commentsNew): string {
		return '<span id="learning-inbox-counter">'.self::getCounter($commentsNew).'</span>';
	}

	/**
	 * Un seul fil : les nouveaux en tête, puis les lus, chacun du plus récent au plus ancien.
	 */
	public function getInbox(\structure\Structure $eStructure, \Collection $cCommentNew, \Collection $cCommentRead, bool $older): string {

		if(
			$cCommentNew->empty() and
			$cCommentRead->empty()
		) {

			$h = '<div class="util-block util-block-empty">';
				$h .= '<h3>'.s("Aucun commentaire pour le moment").'</h3>';
				$h .= '<p>'.match($eStructure['communityType']) {
					\structure\Structure::NETWORK => s("Les commentaires que les stagiaires écrivent sous les leçons des formations du réseau paraitront ici."),
					\structure\Structure::EDUCATION => s("Les commentaires que les stagiaires écrivent sous les leçons des formations du groupe paraitront ici."),
				}.'</p>';
			$h .= '</div>';

			return $h;

		}

		$h = '<div class="util-cards util-cards-1 util-thread">';
			$h .= '<div class="util-deck util-deck-open">';
				$h .= '<div class="util-deck-fold">';

					foreach($cCommentNew as $eComment) {
						$h .= $this->getInboxRow($eComment);
					}

					foreach($cCommentRead as $eComment) {
						$h .= $this->getInboxRow($eComment);
					}

				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		if($older) {
			$h .= '<div class="text-center mt-1">';
				$h .= '<a href="'.self::urlInbox($eStructure).'?before='.$cCommentRead->last()['id'].'" class="btn btn-outline-primary">'.s("Commentaires plus anciens").'</a>';
			$h .= '</div>';
		}

		return $h;

	}

	/**
	 * Une ligne, que « Marquer comme lu » et la réponse de l'équipe redessinent seule. La réponse d'un
	 * stagiaire est une ligne à lire qui dit à qui elle répond ; celles de l'équipe se lisent en filet
	 * sous leur commentaire.
	 */
	public function getInboxRow(Comment $eComment): string {

		$new = ($eComment['readAt'] === NULL);

		$h = '<div class="util-deck-row '.($new ? 'util-deck-row-unread' : 'util-deck-row-read').' util-deck-row-top" id="learning-comment-'.$eComment['id'].'">';

			$h .= \user\UserUi::getVignette($eComment['createdBy'], '1.75rem');

			$h .= '<div class="util-deck-row-name">';
				$h .= $this->getRowMessage($eComment);
				$h .= '<div class="util-annotation">';
					if($eComment['parent']->notEmpty()) {
						$h .= s("En réponse à {value}", ['value' => \user\UserUi::name($eComment['parent']['createdBy'])]).' · ';
					}
					$h .= encode($eComment['lesson']['course']['name']).' · ';
					$h .= '<a href="'.LessonUi::url($eComment['lesson']).'#comments">'.encode($eComment['lesson']['number'].'. '.$eComment['lesson']['name']).'</a>';
				$h .= '</div>';
				$h .= $this->getRowAnswers($eComment);
			$h .= '</div>';

			$h .= '<div class="util-thread-side">';
				$h .= $this->getRowDate($eComment);
				if($new) {
					$actions = [];
					if($eComment->assertAnswer()) {
						$actions[] = '<a href="/learning/comment:create?lesson='.$eComment['lesson']['id'].'&parent='.$eComment['id'].'&origin=inbox" class="btn btn-outline-primary">'.s("Répondre").'</a>';
					}
					$actions[] = '<a data-ajax="/learning/comment:doRead" post-id="'.$eComment['id'].'" class="btn btn-outline-primary" title="'.s("Marquer comme lu").'">'.\Asset::icon('check2').' <span class="hide-xs-down">'.s("Marquer comme lu").'</span></a>';
					$h .= $this->getRowButtons($actions);
				} else if(
					// Un message de l'équipe est lu par son auteur dès sa création, et le filet d'une réponse nomme déjà qui
					// a répondu : « Lu par » redirait dans les deux cas un nom que la ligne porte.
					$eComment['readBy']->is($eComment['createdBy']) === FALSE and
					$eComment['cCommentAnswer']->empty()
				) {
					$h .= '<span class="util-annotation">'.s("Lu par {value}", ['value' => \user\UserUi::name($eComment['readBy'])]).'</span>';
				}
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/** Un compteur à zéro s'afficherait : il n'est pas posé. */
	private static function getCounter(int $number): string {
		return ($number > 0) ? ' <span class="util-counter">'.$number.'</span>' : '';
	}

	/**
	 * Le panneau d'un commentaire, et celui d'une réponse quand `eCommentParent` est posé : le
	 * commentaire cité en tête, comme l'avis dans `ReviewUi::answer()`. Dans les deux, un encart dit
	 * avant l'envoi qui lira et sous quel nom.
	 */
	public function create(Comment $eComment, string $origin): \Panel {

		$eComment->expects(['eCommentParent', 'createdBy']);

		$eCommentParent = $eComment['eCommentParent'];
		$eCourse = $eComment['lesson']['course'];

		$values = [
			'name' => \user\UserUi::name($eComment['createdBy']),
			'community' => $eCourse->isTeam() ? 'Ouvretaferme' : encode($eCourse['community']['name'])
		];

		$form = new \util\FormUi();

		$h = '';

		if($eCommentParent->notEmpty()) {
			$h .= '<div class="util-block mb-2">';
				$h .= '<b>'.\user\UserUi::name($eCommentParent['createdBy']).'</b>';
				$h .= '<p class="mb-0 learning-review-text text-break">'.nl2br(encode($eCommentParent['text'])).'</p>';
			$h .= '</div>';
		}

		$h .= '<div class="util-block-info mb-2">';
			$h .= \Asset::icon('info-circle', ['class' => 'util-block-icon']);
			$h .= '<div class="util-block-info-text">';
				if($eCommentParent->empty()) {
					$h .= '<div class="util-block-info-title">'.s("Tous les stagiaires de cette leçon liront votre commentaire").'</div>';
					$h .= '<div class="util-block-info-help">'.s("Il parait sous votre nom, {name}. L'équipe de {community} le lit aussi.", $values).'</div>';
				} else {
					$h .= '<div class="util-block-info-title">'.s("Tous les stagiaires de cette leçon liront votre réponse").'</div>';
					$h .= '<div class="util-block-info-help">'.s("Elle parait sous votre nom, {name}. L'équipe de {community} la lit aussi.", $values).'</div>';
				}
			$h .= '</div>';
		$h .= '</div>';

		$h .= $form->openAjax('/learning/comment:doCreate');

			$h .= $form->hidden('lesson', $eComment['lesson']);
			$h .= $form->hidden('parent', $eCommentParent);
			$h .= $form->hidden('origin', $origin);

			if($eCommentParent->empty()) {
				$h .= $form->dynamicGroup($eComment, 'text');
			} else {
				$h .= $form->dynamicGroup($eComment, 'text', function(\PropertyDescriber $d) {
					$d->label = s("Réponse");
				});
			}

			$h .= $form->group(
				content: $form->submit($eCommentParent->empty() ? s("Envoyer") : s("Envoyer la réponse"))
			);

		$h .= $form->close();

		// Depuis la page des commentaires du groupe, la leçon se nomme comme sur la ligne quittée.
		$subTitle = ($origin === 'inbox')
			? encode($eCourse['name']).' · '.encode($eComment['lesson']['number'].'. '.$eComment['lesson']['name'])
			: encode($eComment['lesson']['name']);

		if($eCommentParent->empty()) {

			return new \Panel(
				id: 'panel-learning-comment-create',
				title: s("Commenter"),
				subTitle: $subTitle,
				body: $h
			);

		}

		return new \Panel(
			id: 'panel-learning-comment-answer',
			title: s("Répondre au commentaire"),
			subTitle: $subTitle,
			body: $h
		);

	}

	public function update(Comment $eComment): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/learning/comment:doUpdate');

			$h .= $form->hidden('id', $eComment);
			$h .= $form->dynamicGroup($eComment, 'text');

			$h .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-learning-comment-update',
			title: s("Modifier le commentaire"),
			subTitle: encode($eComment['lesson']['name']),
			body: $h
		);

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Comment::model()->describer($property, [
			'text' => s("Commentaire"),
		]);

		switch($property) {

			case 'text' :
				$d->field = 'textarea';
				$d->attributes = [
					'data-limit' => Comment::model()->getPropertyRange('text')[1],
					'onrender' => 'this.focus();'
				];
				break;

		}

		return $d;

	}

}
?>
