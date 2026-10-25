<?php
namespace learning;

/**
 * Les avis sur les formations du catalogue public (lot L15c) : la note de la carte et de la fiche,
 * la section « Avis » de la fiche, les panneaux de l'auteur, du réseau et de l'équipe, l'onglet
 * d'administration, la page de l'avis par le lien d'une inscription, l'e-mail de masquage et la
 * rubrique qui dit comment les avis sont publiés (D111-17).
 *
 * Le fil suit la forme de celui des commentaires (`CommentUi`) : vignette, nom et texte, puis la date
 * et les gestes à droite. Le nom est celui que l'avis signe partout, prénom et initiale
 * (`Review::getAuthorName()`) : la vignette le reprend, son titre ne dit pas le nom entier.
 */
class ReviewUi {

	public function __construct() {
		\Asset::css('learning', 'learning.css');
	}

	public static function urlCourse(Course $eCourse): string {
		return CourseUi::url($eCourse).'#avis';
	}

	public static function urlPublic(\structure\Community $eCommunity, string $token): string {
		return DatePublicUi::urlEnrollment($eCommunity, $token).'/avis';
	}

	public static function urlRules(): string {
		return '/formations/avis';
	}

	public static function urlAdmin(): string {
		return '/learning/admin/avis';
	}

	/**
	 * Sous le titre de la carte, à partir du seuil : une étoile, la moyenne et le nombre d'avis.
	 */
	public static function getCardRating(Course $eCourse): string {

		$eCourse->expects(['reviews', 'rating']);

		if($eCourse['rating'] === NULL) {
			return '';
		}

		return '<p>'.\main\DesignUi::getRating($eCourse['rating'], $eCourse['reviews'], compact: TRUE).'</p>';

	}

	/**
	 * Sous le titre de la fiche, à partir du seuil : les cinq étoiles, qui mènent à la section.
	 */
	public static function getFicheRating(Course $eCourse): string {

		$eCourse->expects(['reviews', 'rating']);

		if($eCourse['rating'] === NULL) {
			return '';
		}

		// Une ancre nue se résout contre `<base href>`, que le gabarit pose sur l'hôte : `#avis` mènerait à
		// l'accueil. L'adresse de la fiche passe par la navigation ajax, qui défile jusqu'à l'ancre.
		return '<p>'.\main\DesignUi::getRating($eCourse['rating'], $eCourse['reviews'], url: self::urlCourse($eCourse)).'</p>';

	}

	/**
	 * Le bouton de la personne, à côté de « Lire la première leçon » ou de l'attestation : donner son
	 * avis quand elle a suivi la formation, le modifier, ou le relire quand il est masqué.
	 */
	public static function getStartButton(Course $eCourse): string {

		$eCourse->expects(['eReview']);

		$eReview = $eCourse['eReview'];

		if($eReview->empty()) {
			return '';
		}

		if($eReview->exists() === FALSE) {

			if($eReview->assertCreate() === FALSE) {
				return '';
			}

			return '<a href="/learning/review:create?course='.$eCourse['id'].'" class="btn btn-outline-primary btn-lg">'.\Asset::icon('star').' '.s("Donner mon avis").'</a>';

		}

		if($eReview->assertUpdate()) {
			return '<a href="/learning/review:update?id='.$eReview['id'].'" class="btn btn-outline-primary btn-lg">'.\Asset::icon('star').' '.s("Modifier mon avis").'</a>';
		}

		if($eReview->assertDelete()) {
			return '<a href="/learning/review:update?id='.$eReview['id'].'" class="btn btn-outline-primary btn-lg">'.\Asset::icon('star').' '.s("Voir mon avis").'</a>';
		}

		return '';

	}

	/**
	 * La section « Avis » de la fiche, seulement pour une formation au catalogue public (CL-23) et
	 * qui peut encore en recevoir (`ReviewLib::fillCourse()`) : la grande note et la répartition à
	 * partir du seuil, la mention L111-7-2, puis le fil.
	 */
	public function getSection(Course $eCourse): string {

		$eCourse->expects(['hasReviewSection', 'cReview', 'reviews', 'rating', 'reviewsListed', 'reviewDistribution']);

		if($eCourse['hasReviewSection'] === FALSE) {
			return '';
		}

		$cReview = $eCourse['cReview'];

		$h = '<div id="avis" class="learning-reviews">';

			$h .= '<div class="util-title mt-2">';
				$h .= '<h2>'.s("Avis").'</h2>';
			$h .= '</div>';

			if($cReview->empty()) {

				$h .= '<div class="util-block-empty">';
					$h .= '<p>'.s("Aucun avis pour l'instant.").'</p>';
				$h .= '</div>';

				$h .= $this->getMention();

			} else {

				if($eCourse['rating'] !== NULL) {
					$h .= $this->getSummary($eCourse);
				} else if($eCourse['reviews'] > 0) {
					$h .= '<p class="util-annotation">'.p("{value} avis.", "{value} avis.", $eCourse['reviews']).' '.p("La note moyenne paraît à partir de {value} avis.", "La note moyenne paraît à partir de {value} avis.", LearningSetting::REVIEWS_THRESHOLD).'</p>';
				}

				$h .= $this->getMention();

				$h .= '<div class="util-cards util-cards-1 util-thread">';
					$h .= '<div class="util-deck util-deck-open">';
						$h .= '<div class="util-deck-fold" id="learning-reviews-thread">';
							$h .= $this->getRows($eCourse, $cReview);
						$h .= '</div>';
					$h .= '</div>';
				$h .= '</div>';

				$h .= $this->getMore($eCourse, $cReview->count(), $eCourse['reviewsListed'] - $cReview->count());

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le bouton qui charge la page suivante du fil, et que la réponse remplace par le suivant : vide
	 * quand le fil est complet, ce qui retire le bouton.
	 */
	public function getMore(Course $eCourse, int $offset, int $more): string {

		if($more <= 0) {
			return '';
		}

		$url = '/learning/review:more?course='.$eCourse['id'].'&offset='.$offset;

		$h = '<div class="text-center mt-1" id="learning-reviews-more">';
			$h .= '<a href="'.$url.'" data-ajax="'.$url.'" data-ajax-method="get" class="btn btn-outline-primary">'.p("Voir l'autre avis", "Voir les {value} autres avis", $more).'</a>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les avis du fil, sur la fiche comme dans la suite que charge « Voir les autres avis ».
	 */
	public function getRows(Course $eCourse, \Collection $cReview): string {

		$h = '';

		foreach($cReview as $eReview) {
			$h .= $this->getRow($eCourse, $eReview);
		}

		return $h;

	}

	private function getSummary(Course $eCourse): string {

		$h = '<div class="learning-reviews-head">';

			$h .= '<div>';
				$h .= \main\DesignUi::getRating($eCourse['rating'], large: TRUE);
				$h .= '<div class="util-annotation mt-1">'.p("{value} avis", "{value} avis", $eCourse['reviews'], ['value' => \util\TextUi::number($eCourse['reviews'])]).'</div>';
			$h .= '</div>';

			$h .= self::getDistribution($eCourse['reviewDistribution']);

		$h .= '</div>';

		return $h;

	}

	/**
	 * La répartition des avis par note, de 5 à 1 (`ReviewLib::getDistribution()`), sur la fiche et dans
	 * le Bilan de l'éditeur.
	 */
	public static function getDistribution(array $distribution): string {

		$total = array_sum($distribution);

		$h = '<div class="util-rating-distribution">';

			foreach($distribution as $rating => $count) {

				$width = ($total === 0) ? 0 : round($count / $total * 100, 1);

				$h .= '<span class="util-annotation">'.$rating.' '.\Asset::icon('star-fill').'</span>';
				$h .= '<div class="util-progress">';
					$h .= '<div class="util-progress-part bg-secondary" style="width: '.$width.'%"></div>';
				$h .= '</div>';
				$h .= '<span class="util-annotation">'.$count.'</span>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * L111-7-2 : dire si les avis sont contrôlés, et comment ; D111-17 : le lien vers la rubrique.
	 */
	private function getMention(): string {

		$h = '<p class="util-annotation">';
			$h .= s("Ouvretaferme vérifie que chaque auteur a suivi la formation, en ligne jusqu'à la dernière leçon, en présentiel ou en visio à une date où sa place était confirmée. Les avis paraissent sans contrepartie, du plus récent au plus ancien, sans tri par note.");
			$h .= ' <a href="'.self::urlRules().'">'.s("Comment les avis sont publiés").'</a>';
		$h .= '</p>';

		return $h;

	}

	public function getRow(Course $eCourse, Review $eReview): string {

		$h = '<div class="util-deck-row util-deck-row-top'.(($eReview['status'] === Review::HIDDEN) ? ' learning-review-hidden' : '').'" id="learning-review-'.$eReview['id'].'">';

			$h .= self::getVignette($eReview);
			$h .= $this->getRowName($eCourse, $eReview, self::getExperienced($eCourse, $eReview));

			$h .= '<div class="util-thread-side">';
				$h .= '<span class="util-annotation">'.self::getPublished($eReview).'</span>';
				$h .= $this->getActions($eReview);
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le corps d'une ligne, sur la fiche comme dans la boite : l'auteur et sa note, son texte, ce que
	 * dit `$annotation`, l'état que voit qui écrit la formation, puis la réponse.
	 */
	private function getRowName(Course $eCourse, Review $eReview, string $annotation): string {

		$h = '<div class="util-deck-row-name">';

			$h .= '<b>'.encode($eReview->getAuthorName()).'</b> '.\main\DesignUi::getRating($eReview['rating']);

			if($eReview['status'] === Review::HIDDEN) {
				$h .= ' <span class="badge badge-neutral">'.s("Masqué").'</span>';
			}

			if($eReview['text'] !== NULL) {
				$h .= '<p class="learning-review-text text-break">'.nl2br(encode($eReview['text'])).'</p>';
			}

			$h .= '<div class="util-annotation">'.$annotation.'</div>';

			$h .= self::getState($eReview);

			if($eReview['answer'] !== NULL) {
				$h .= '<div class="learning-review-answer">';
					$h .= '<b>'.s("Réponse de {value}", self::getCourseAuthor($eCourse)).'</b> ';
					$h .= '<span class="util-annotation">'.\util\DateUi::textual($eReview['answeredAt'], \util\DateUi::DATE).'</span>';
					$h .= '<p class="mb-0 learning-review-text text-break">'.nl2br(encode($eReview['answer'])).'</p>';
				$h .= '</div>';
			}

		$h .= '</div>';

		return $h;

	}

	public static function urlInbox(\structure\Structure $eStructure): string {
		return '/groupe/'.$eStructure['id'].'/formations/avis';
	}

	/**
	 * L'entrée de la boite, à droite du titre des formations du groupe, après celle des commentaires.
	 */
	public static function getInboxButton(\structure\Structure $eStructure, int $reviewsNew): string {
		return '<a href="'.self::urlInbox($eStructure).'" class="btn btn-outline-primary">'.\Asset::icon('star').' '.s("Avis").self::getCounter($reviewsNew).'</a>';
	}

	/**
	 * L'en-tête de la boite. Le compteur et « Tout marquer comme lu » se désignent : `doRead` ne
	 * redessine qu'eux (`getInboxCounter()`), la bande du groupe ayant rangé le geste hors de la tête
	 * qui le porte.
	 */
	public function getInboxTitle(\structure\Structure $eStructure, int $reviewsNew): string {

		$tools = ($reviewsNew > 0)
			? '<a id="learning-review-inbox-read-all" data-ajax="/learning/community:doReadAllReviews" post-id="'.$eStructure['id'].'" class="btn btn-outline-secondary">'.\Asset::icon('check2-all').' '.s("Tout marquer comme lu").'</a>'
			: '';

		return \main\DesignUi::getPageHeader(
			s("Avis").self::getInboxCounter($reviewsNew),
			back: '/groupe/'.$eStructure['id'].'/formations',
			tools: $tools,
			backLabel: s("Formations")
		);

	}

	public static function getInboxCounter(int $reviewsNew): string {
		return '<span id="learning-review-inbox-counter">'.self::getCounter($reviewsNew).'</span>';
	}

	/**
	 * Un seul fil, comme celui des commentaires : les nouveaux en tête, puis les lus, chacun du plus
	 * récent au plus ancien.
	 */
	public function getInbox(\structure\Structure $eStructure, \Collection $cReviewNew, \Collection $cReviewRead, bool $older): string {

		if(
			$cReviewNew->empty() and
			$cReviewRead->empty()
		) {

			$h = '<div class="util-block util-block-empty">';
				$h .= '<h3>'.s("Aucun avis pour le moment").'</h3>';
				$h .= '<p>'.s("Les avis que les stagiaires donnent sur les formations du groupe seront lus ici.").'</p>';
			$h .= '</div>';

			return $h;

		}

		$h = '<div class="util-cards util-cards-1 util-thread">';
			$h .= '<div class="util-deck util-deck-open">';
				$h .= '<div class="util-deck-fold">';

					foreach($cReviewNew as $eReview) {
						$h .= $this->getInboxRow($eReview);
					}

					foreach($cReviewRead as $eReview) {
						$h .= $this->getInboxRow($eReview);
					}

				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		if($older) {
			$h .= '<div class="text-center mt-1">';
				$h .= '<a href="'.self::urlInbox($eStructure).'?before='.$cReviewRead->last()['id'].'" class="btn btn-outline-primary">'.s("Avis plus anciens").'</a>';
			$h .= '</div>';
		}

		return $h;

	}

	/**
	 * Une ligne de la boite, que « Marquer comme lu » redessine seule : celle de la fiche, avec le nom
	 * de la formation devant la date suivie, et la roue de la fiche après l'état lu.
	 */
	public function getInboxRow(Review $eReview): string {

		$eCourse = $eReview['course'];
		$new = ($eReview['readAt'] === NULL);

		$h = '<div class="util-deck-row '.($new ? 'util-deck-row-unread' : 'util-deck-row-read').' util-deck-row-top" id="learning-review-'.$eReview['id'].'">';

			$h .= self::getVignette($eReview);
			$h .= $this->getRowName($eCourse, $eReview, '<a href="'.CourseUi::url($eCourse).'">'.encode($eCourse['name']).'</a> · '.self::getExperienced($eCourse, $eReview));

			$h .= '<div class="util-thread-side">';
				$h .= '<span class="util-annotation">'.self::getPublished($eReview).'</span>';
				if($new) {
					$h .= '<a data-ajax="/learning/review:doRead" post-id="'.$eReview['id'].'" class="btn btn-outline-primary">'.\Asset::icon('check2').' '.s("Marquer comme lu").'</a>';
				} else {
					$h .= '<span class="util-annotation">'.s("Lu par {value}", ['value' => \user\UserUi::name($eReview['readBy'])]).'</span>';
				}
				$h .= $this->getActions($eReview);
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * La vignette de l'auteur, sous le nom qu'il signe : la photo d'un compte public, les initiales
	 * sinon. Le titre de la vignette donnerait le nom entier.
	 */
	private static function getVignette(Review $eReview): string {

		$eUser = $eReview['user'];

		// « Léa R. » se coupe au dernier espace : `getDefault()` tire les initiales du prénom et du nom,
		// et le titre les rejoint, soit la signature exacte de l'avis.
		$name = $eReview->getAuthorName();
		$space = mb_strrpos($name, ' ');

		$eUserShown = new \user\User([
			'id' => $eUser->notEmpty() ? $eUser['id'] : $eReview['dateEnrollment']['id'],
			'vignette' => ($eUser->notEmpty() and $eUser->isPublic()) ? $eUser['vignette'] : NULL,
			'firstName' => ($space === FALSE) ? NULL : mb_substr($name, 0, $space),
			'lastName' => ($space === FALSE) ? $name : mb_substr($name, $space + 1)
		]);

		return \user\UserUi::getVignette($eUserShown, '1.75rem');

	}

	private static function getExperienced(Course $eCourse, Review $eReview): string {

		$date = \util\DateUi::textual($eReview['experiencedAt'], \util\DateUi::DATE);

		if($eCourse->isDated()) {
			return s("Formation suivie le {value}", $date);
		}

		return s("Formation terminée le {value}", $date);

	}

	/**
	 * La date de publication, et celle de la dernière modification par l'auteur (L111-7-2).
	 */
	private static function getPublished(Review $eReview): string {

		$h = \util\DateUi::textual($eReview['createdAt'], \util\DateUi::DATE);

		if($eReview['updatedAt'] !== NULL) {
			$h .= '<br/>'.s("modifié le {value}", \util\DateUi::textual($eReview['updatedAt'], \util\DateUi::DATE));
		}

		return $h;

	}

	/**
	 * Ce que seul voit qui écrit la formation : le signalement en cours, la décision de l'équipe.
	 * L'état d'un avis publié n'a rien à dire.
	 */
	private static function getState(Review $eReview): string {

		if($eReview->canAnswer() === FALSE and $eReview->canModerate() === FALSE) {
			return '';
		}

		return match($eReview['status']) {
			Review::PUBLISHED => '',
			Review::REPORTED => '<div class="util-annotation">'.s("Signalé le {value}", \util\DateUi::textual($eReview['reportedAt'], \util\DateUi::DATE)).'</div>',
			Review::KEPT => '<div class="util-annotation">'.s("Signalement examiné, avis maintenu").'</div>',
			Review::HIDDEN => '<div class="util-annotation">'.s("Masqué par Ouvretaferme le {date}, pour le motif suivant.", ['date' => \util\DateUi::textual($eReview['moderatedAt'], \util\DateUi::DATE)]).'</div><p>'.nl2br(encode($eReview['hideReason'])).'</p>'
		};

	}

	/**
	 * La roue d'un avis, comme celle d'un commentaire (`util-thread`) : répondre et signaler pour qui
	 * écrit la formation, masquer et réafficher pour l'équipe, séparés puisqu'ils ne viennent pas des
	 * mêmes personnes. Qui n'a aucun geste n'a pas de roue.
	 */
	private function getActions(Review $eReview): string {

		$items = [];

		if($eReview->assertAnswer()) {
			$items[] = '<a href="/learning/review:answer?id='.$eReview['id'].'" class="dropdown-item">'.($eReview['answer'] === NULL ? s("Répondre à l'avis") : s("Modifier la réponse")).'</a>';
		}

		if($eReview->assertReport()) {
			$items[] = '<a href="/learning/review:report?id='.$eReview['id'].'" class="dropdown-item">'.s("Signaler l'avis").'</a>';
		}

		$moderate = [];

		if($eReview->assertHide()) {
			$moderate[] = '<a href="/learning/admin/review:hide?id='.$eReview['id'].'" class="dropdown-item">'.s("Masquer l'avis").'</a>';
		}

		if($eReview->assertShow()) {
			$moderate[] = '<a data-ajax="/learning/admin/review:doShow" post-id="'.$eReview['id'].'" class="dropdown-item" data-waiter>'.s("Réafficher l'avis").'</a>';
		}

		if($items === [] and $moderate === []) {
			return '';
		}

		$menu = implode('', $items);
		if($items !== [] and $moderate !== []) {
			$menu .= '<div class="dropdown-divider"></div>';
		}
		$menu .= implode('', $moderate);

		return \main\DesignUi::getGear($menu, s("Avis de {value}", encode($eReview->getAuthorName())));

	}

	/**
	 * Le réseau qui répond, ou l'équipe pour une formation de l'équipe.
	 */
	private static function getCourseAuthor(Course $eCourse): string {

		if($eCourse->isTeam()) {
			return s("Ouvretaferme");
		}

		return encode($eCourse['community']['name']);

	}

	/**
	 * Donner son avis depuis la fiche.
	 */
	public function create(Review $eReview): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/learning/review:doCreate', ['class' => 'form-columns form-columns-33 form-columns-xs']);

			$h .= $form->hidden('course', $eReview['course']);
			$h .= $this->getFields($form, $eReview);

			$h .= $form->group(
				content: $form->submit($eReview->isCatalog() ? s("Publier l'avis") : s("Envoyer l'avis"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-learning-review',
			title: s("Donner votre avis"),
			subTitle: $this->getPanelSubTitle($eReview),
			body: $h
		);

	}

	/**
	 * Modifier son avis, ou le relire en lecture seule quand l'équipe l'a masqué : seul reste alors le
	 * retrait (Q4).
	 */
	public function update(Review $eReview): \Panel {

		$h = '';

		if($eReview['status'] === Review::HIDDEN) {

			$h .= '<div class="util-block-warning mb-2">';
				$h .= '<p>'.s("L'équipe de Ouvretaferme a masqué votre avis le {date}, pour le motif suivant.", ['date' => \util\DateUi::textual($eReview['moderatedAt'], \util\DateUi::DATE)]).'</p>';
				$h .= '<p class="mb-0">'.nl2br(encode($eReview['hideReason'])).'</p>';
			$h .= '</div>';

			$h .= '<div class="util-block mb-2">';
				$h .= \main\DesignUi::getRating($eReview['rating']);
				if($eReview['text'] !== NULL) {
					$h .= '<p class="mb-0 learning-review-text text-break">'.nl2br(encode($eReview['text'])).'</p>';
				}
			$h .= '</div>';

		} else {

			if($eReview->isCatalog()) {
				$h .= '<p class="util-annotation">'.s("Publié le {value}.", \util\DateUi::textual($eReview['createdAt'], \util\DateUi::DATE)).'</p>';
			} else {
				$h .= '<p class="util-annotation">'.s("Donné le {date}, lu par {network}.", ['date' => \util\DateUi::textual($eReview['createdAt'], \util\DateUi::DATE), 'network' => self::getCourseAuthor($eReview['course'])]).'</p>';
			}

			$form = new \util\FormUi();

			$h .= $form->openAjax('/learning/review:doUpdate', ['class' => 'form-columns form-columns-33 form-columns-xs']);

				$h .= $form->hidden('id', $eReview);
				$h .= $this->getFields($form, $eReview);

				$h .= $form->group(
					content: $form->submit(s("Enregistrer"))
				);

			$h .= $form->close();

		}

		if($eReview['answer'] !== NULL and $eReview->isCatalog() === FALSE) {
			$h .= '<div class="util-block mb-2">';
				$h .= '<b>'.s("Réponse de {value}", self::getCourseAuthor($eReview['course'])).'</b> ';
				$h .= '<span class="util-annotation">'.\util\DateUi::textual($eReview['answeredAt'], \util\DateUi::DATE).'</span>';
				$h .= '<p class="mb-0 learning-review-text text-break">'.nl2br(encode($eReview['answer'])).'</p>';
			$h .= '</div>';
		}

		$confirm = $eReview->isCatalog()
			? s("Retirer votre avis ? Votre note et votre commentaire seront supprimés de la fiche de la formation.")
			: s("Retirer votre avis ? Votre note et votre commentaire seront supprimés.");

		$h .= '<a data-ajax="/learning/review:doDelete" post-id="'.$eReview['id'].'" class="btn btn-sm btn-outline-primary mt-1" data-confirm="'.$confirm.'" data-waiter>'.s("Retirer mon avis").'</a>';

		return new \Panel(
			id: 'panel-learning-review',
			title: ($eReview['status'] === Review::HIDDEN) ? s("Votre avis") : s("Modifier votre avis"),
			subTitle: $this->getPanelSubTitle($eReview),
			body: $h
		);

	}

	/**
	 * La note et le texte, dans le panneau comme sur la page par lien.
	 */
	public function getFields(\util\FormUi $form, Review $eReview): string {

		$h = $form->dynamicGroup($eReview, 'rating*');
		$h .= $form->dynamicGroup($eReview, 'text', function(\PropertyDescriber $d) use($eReview) {
			$d->after = \util\FormUi::info($eReview->isCatalog()
				? s("Facultatif. Publié sous « {value} », avec sa date.", encode($eReview->getAuthorName()))
				: s("Facultatif. Lu par {network}, et publié sous « {author} » avec sa date si la formation entre au catalogue public.", ['network' => self::getCourseAuthor($eReview['course']), 'author' => encode($eReview->getAuthorName())])
			);
		});

		return $h;

	}

	private function getPanelSubTitle(Review $eReview): string {

		$h = encode($eReview['course']['name']);

		if($eReview['experiencedAt'] !== NULL) {
			$h .= ' · '.\util\DateUi::textual($eReview['experiencedAt'], \util\DateUi::DAY_MONTH);
		}

		return $h;

	}

	public function answer(Review $eReview): \Panel {

		$form = new \util\FormUi();

		$h = '<div class="util-block mb-2">'.$this->getQuote($eReview).'</div>';

		$h .= $form->openAjax('/learning/review:doAnswer', ['class' => 'form-columns form-columns-33 form-columns-xs']);

			$h .= $form->hidden('id', $eReview);

			if($eReview->isCatalog()) {
				$h .= $form->dynamicGroup($eReview, 'answer');
			} else {
				// Un avis donné par le lien d'une inscription sans compte n'a personne pour lire la réponse
				// hors catalogue : seul son avenir au catalogue se dit.
				$h .= $form->dynamicGroup($eReview, 'answer', function(\PropertyDescriber $d) use($eReview) {
					$d->after = \util\FormUi::info($eReview['user']->notEmpty()
						? s("Lue par l'auteur de l'avis seul. Elle sera publiée sous l'avis si la formation entre au catalogue public.")
						: s("Elle sera publiée sous l'avis si la formation entre au catalogue public.")
					);
				});
			}

			$h .= $form->group(
				content: $form->submit($eReview->isCatalog() ? s("Publier la réponse") : s("Enregistrer la réponse"))
			);

		$h .= $form->close();

		if($eReview['answer'] !== NULL) {
			$h .= '<a data-ajax="/learning/review:doDeleteAnswer" post-id="'.$eReview['id'].'" class="btn btn-sm btn-outline-primary mt-1" data-confirm="'.s("Retirer la réponse ? Son texte sera supprimé, l'avis restera tel quel.").'" data-waiter>'.s("Retirer la réponse").'</a>';
		}

		return new \Panel(
			id: 'panel-learning-review-answer',
			title: ($eReview['answer'] === NULL) ? s("Répondre à l'avis") : s("Modifier la réponse"),
			subTitle: encode($eReview['course']['name']),
			body: $h
		);

	}

	public function report(Review $eReview): \Panel {

		$form = new \util\FormUi();

		$h = '<div class="util-block mb-2">'.$this->getQuote($eReview).'</div>';

		$h .= $form->openAjax('/learning/review:doReport', ['class' => 'form-columns form-columns-33 form-columns-xs']);

			$h .= $form->hidden('id', $eReview);
			$h .= $form->dynamicGroup($eReview, 'reportReason', function(\PropertyDescriber $d) {
				$d->after = \util\FormUi::info(s("L'équipe de Ouvretaferme lit ce motif, garde l'avis ou le masque."));
			});

			$h .= $form->group(
				content: $form->submit(s("Signaler l'avis"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-learning-review-report',
			title: s("Signaler l'avis"),
			subTitle: encode($eReview['course']['name']),
			body: $h
		);

	}

	public function hide(Review $eReview): \Panel {

		$form = new \util\FormUi();

		$h = '<div class="util-block mb-2">'.$this->getQuote($eReview).'</div>';

		$h .= $form->openAjax('/learning/admin/review:doHide', ['class' => 'form-columns form-columns-33 form-columns-xs']);

			$h .= $form->hidden('id', $eReview);
			$h .= $form->dynamicGroup($eReview, 'hideReason', function(\PropertyDescriber $d) use($eReview) {
				$d->after = \util\FormUi::info($this->getHideInfo($eReview));
			});

			$h .= $form->group(
				content: $form->submit(s("Masquer l'avis"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-learning-review-hide',
			title: s("Masquer l'avis"),
			subTitle: encode($eReview['course']['name']).($eReview['course']->isTeam() ? '' : ' · '.encode($eReview['course']['community']['name'])),
			body: $h
		);

	}

	private function getHideInfo(Review $eReview): string {

		$author = encode($eReview->getAuthorName());

		if($eReview->getAuthorEmail() === NULL) {
			$h = s("{value} n'a plus d'adresse e-mail et ne sera pas prévenu.", $author);
		} else {
			$h = s("{value} le reçoit par e-mail.", $author);
		}

		if($eReview['status'] === Review::REPORTED) {
			$h .= ' '.s("{value} le lit sous son signalement.", encode($eReview['course']['community']['name']));
		}

		return $h;

	}

	private function getQuote(Review $eReview): string {

		$h = \main\DesignUi::getRating($eReview['rating']).' <b>'.encode($eReview->getAuthorName()).'</b>';

		if($eReview['text'] !== NULL) {
			$h .= '<p class="mb-0 learning-review-text text-break">'.nl2br(encode($eReview['text'])).'</p>';
		}

		return $h;

	}

	/**
	 * L'onglet de l'équipe : les avis signalés à juger, puis les avis masqués, qu'une roue réaffiche.
	 */
	public function getAdmin(\Collection $cReviewReported, \Collection $cReviewHidden): string {

		$h = CourseUi::getAdminTabs('reviews');

		$h .= '<div class="util-title">';
			$h .= '<h3>'.s("Signalés").self::getCounter($cReviewReported->count()).'</h3>';
		$h .= '</div>';

		if($cReviewReported->empty()) {
			$h .= '<div class="util-block-empty mb-2"><p>'.s("Aucun avis signalé à examiner.").'</p></div>';
		} else {

			$h .= '<div class="util-table util-table-hover learning-table-reviews mb-2">';

				$h .= '<div class="util-table-head">';
					$h .= '<div>'.s("Avis").'</div>';
					$h .= '<div class="hide-xs-down">'.s("Signalement").'</div>';
					$h .= '<div class="hide-xs-down">'.s("Formation").'</div>';
					$h .= '<div></div>';
				$h .= '</div>';

				foreach($cReviewReported as $eReview) {

					$community = encode($eReview['course']['community']['name']);
					$reason = nl2br(encode($eReview['reportReason']));

					$h .= '<div class="util-table-row">';
						$h .= '<div>';
							$h .= $this->getAdminReview($eReview);
							$h .= '<div class="hide-sm-up mt-1"><div class="util-label">'.s("Signalement de {value}", $community).'</div>'.$reason.'</div>';
						$h .= '</div>';
						$h .= '<div class="hide-xs-down"><div class="util-label">'.$community.'</div>'.$reason.'</div>';
						$h .= '<div class="hide-xs-down">'.$this->getAdminCourse($eReview).'</div>';
						$h .= '<div class="learning-row-actions">';
							$h .= '<a href="/learning/admin/review:hide?id='.$eReview['id'].'" class="btn btn-outline-primary">'.s("Masquer").'</a>';
							$h .= '<a data-ajax="/learning/admin/review:doKeep" post-id="'.$eReview['id'].'" class="btn btn-outline-primary" data-waiter>'.s("Garder").'</a>';
						$h .= '</div>';
					$h .= '</div>';

				}

			$h .= '</div>';

		}

		$h .= '<div class="util-title mt-2">';
			$h .= '<h3>'.s("Masqués").self::getCounter($cReviewHidden->count()).'</h3>';
		$h .= '</div>';

		if($cReviewHidden->empty()) {
			$h .= '<div class="util-block-empty"><p>'.s("Aucun avis masqué.").'</p></div>';
			return $h;
		}

		$h .= '<div class="util-table util-table-hover learning-table-reviews">';

			$h .= '<div class="util-table-head">';
				$h .= '<div>'.s("Avis").'</div>';
				$h .= '<div class="hide-xs-down">'.s("Motif").'</div>';
				$h .= '<div class="hide-xs-down">'.s("Formation").'</div>';
				$h .= '<div></div>';
			$h .= '</div>';

			foreach($cReviewHidden as $eReview) {

				$reason = nl2br(encode($eReview['hideReason']));

				$h .= '<div class="util-table-row">';
					$h .= '<div>';
						$h .= $this->getAdminReview($eReview);
						$h .= '<div class="hide-sm-up mt-1"><div class="util-label">'.s("Motif").'</div>'.$reason.'</div>';
					$h .= '</div>';
					$h .= '<div class="hide-xs-down">'.$reason.'</div>';
					$h .= '<div class="hide-xs-down">'.$this->getAdminCourse($eReview).'</div>';
					$h .= '<div class="learning-row-actions">';
						$items = '<a data-ajax="/learning/admin/review:doShow" post-id="'.$eReview['id'].'" class="dropdown-item" data-waiter>'.s("Réafficher l'avis").'</a>';
						$h .= \main\DesignUi::getGear($items, s("Avis de {value}", encode($eReview->getAuthorName())));
					$h .= '</div>';
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	private function getAdminReview(Review $eReview): string {

		$h = \main\DesignUi::getRating($eReview['rating']).' <b>'.encode($eReview->getAuthorName()).'</b>';

		if($eReview['text'] !== NULL) {
			$h .= '<div class="learning-review-text text-break">'.nl2br(encode($eReview['text'])).'</div>';
		}

		$h .= '<div class="util-annotation">'.\util\DateUi::textual($eReview['createdAt'], \util\DateUi::DATE).'</div>';

		return $h;

	}

	private function getAdminCourse(Review $eReview): string {
		return '<a href="'.self::urlCourse($eReview['course']).'">'.encode($eReview['course']['name']).'</a>';
	}

	/** Un compteur à zéro s'afficherait : il n'est pas posé. */
	private static function getCounter(int $number): string {
		return ($number > 0) ? ' <span class="util-counter">'.$number.'</span>' : '';
	}

	/**
	 * Pourquoi la ligne n'ouvre pas l'avis, dans l'ordre où la personne le comprend : la formation
	 * n'est plus au catalogue, l'avis est masqué, le compte de la place écrit la formation, la date
	 * n'a pas été suivie. `NULL` quand elle l'ouvre. Le refus remplace le formulaire : la page n'en
	 * propose aucun qui échouerait à l'envoi.
	 */
	public static function getRefusal(DateEnrollment $eDateEnrollment, Review $eReview): ?string {

		if($eReview->exists()) {

			if($eReview->acceptUpdate()) {
				return NULL;
			}

			return s("L'équipe de Ouvretaferme a masqué votre avis le {date}, pour le motif suivant : {reason}", ['date' => \util\DateUi::textual($eReview['moderatedAt'], \util\DateUi::DATE), 'reason' => encode($eReview['hideReason'])]);

		}

		if($eReview->assertCreateByLink()) {
			return NULL;
		}

		if($eReview->canCreateByLink() === FALSE) {
			return s("Les avis viennent des personnes qui suivent la formation, pas de celles qui l'écrivent.");
		}

		if($eDateEnrollment['status'] !== DateEnrollment::CONFIRMED) {
			return s("Un avis se donne sur une date où votre place était confirmée.");
		}

		if($eDateEnrollment['date']['status'] === Date::CANCELED) {
			return s("Cette date a été annulée : il n'y a pas d'avis à donner.");
		}

		return s("Vous pourrez donner votre avis une fois la formation passée.");

	}

	/**
	 * À l'auteur, quand l'équipe masque son avis : le motif, et ce qui lui reste possible.
	 */
	public function getHiddenMail(Review $eReview): array {

		$eCourse = $eReview['course'];

		return \main\MailSoftwareAiUi::format(
			s("Votre avis sur la formation {value} n'est plus affiché", $eCourse['name']),
			heroTitle: s("Votre avis n'est plus affiché"),
			heroText: s("L'équipe de Ouvretaferme a masqué votre avis du {date} sur la formation « {course} », pour la raison suivante.", ['date' => \util\DateUi::textual($eReview['createdAt'], \util\DateUi::DATE), 'course' => encode($eCourse['name'])]),
			contentAfter: '<blockquote>'.nl2br(encode($eReview['hideReason'])).'</blockquote><p>'.s("Vous pouvez le retirer depuis la fiche de la formation.").'</p>',
			buttonLabel: s("Voir la formation"),
			buttonUrl: \Lime::getUrl().CourseUi::url($eCourse),
			footer: $eReview['user']->notEmpty()
		);

	}

	/**
	 * Le titre de la rubrique, avec le retour au catalogue des pages voisines : on y arrive depuis une
	 * fiche, qui se retrouve par le catalogue.
	 */
	public function getRulesTitle(): string {
		return '<h1><a href="/formations" class="h-back">'.\Asset::icon('arrow-left').'</a>'.s("Comment les avis sont publiés").'</h1>';
	}

	/**
	 * La rubrique D111-17, publique, liée depuis la mention de chaque fiche.
	 */
	public function getRules(): string {

		$h = '<div class="util-block">';
			$h .= '<p>'.s("Seule une personne qui a suivi la formation peut la noter : en ligne, après la dernière leçon ; en présentiel ou en visio, à une date où sa place était confirmée, une fois la date passée. Ouvretaferme le vérifie avant d'ouvrir l'avis.").'</p>';
			$h .= '<p>'.s("Aucun avis n'est rémunéré ni échangé contre un avantage.").'</p>';
			$h .= '<p>'.p("Un avis paraît dès qu'il est envoyé, sans relecture. Les avis se lisent du plus récent au plus ancien, sans tri par note. La note moyenne paraît à partir de {value} avis.", "Un avis paraît dès qu'il est envoyé, sans relecture. Les avis se lisent du plus récent au plus ancien, sans tri par note. La note moyenne paraît à partir de {value} avis.", LearningSetting::REVIEWS_THRESHOLD).'</p>';
			$h .= '<p>'.s("L'organisme qui propose la formation peut répondre à un avis et le signaler. L'équipe de Ouvretaferme examine chaque signalement : elle garde l'avis ou le masque, et envoie alors le motif à son auteur.").'</p>';
			$h .= '<p class="mb-0">'.s("Un avis reste publié tant que la formation est au catalogue public. Son auteur le modifie ou le retire quand il veut. Il est conservé tant que la formation existe.").'</p>';
		$h .= '</div>';

		return $h;

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Review::model()->describer($property, [
			'rating' => s("Note"),
			'text' => s("Avis"),
			'answer' => s("Réponse"),
			'reportReason' => s("Motif"),
			'hideReason' => s("Motif"),
		]);

		switch($property) {

			case 'rating' :
				$d->field = fn(\util\FormUi $form, Review $e) => \main\DesignUi::getRatingField($form, 'rating', $e['rating']);
				break;

			case 'text' :
			case 'answer' :
			case 'reportReason' :
			case 'hideReason' :
				$d->field = 'textarea';
				$d->attributes = [
					'data-limit' => Review::model()->getPropertyRange($property)[1],
					'rows' => 5
				];
				break;

		}

		return $d;

	}

}
?>
