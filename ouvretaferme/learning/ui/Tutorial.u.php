<?php
namespace learning;

/**
 * Le sommaire des tutoriels (`/tutoriels`), page publique sous le gabarit `presentation` : la bande
 * d'en-tête avec sa recherche et ses chiffres, puis une bande par phase, à la teinte de son rang
 * (`PhaseLib::fillChapters()`), et l'appel à commencer par le premier module. Les leçons se
 * numérotent et disent leur état comme sur la fiche d'une formation (`CourseUi::getLessonNumber()`,
 * `getLessonState()`).
 *
 * La recherche est l'omnibox du produit en mode navigation : ses suggestions sont les modules et les
 * leçons du sommaire, déjà dans la page, et en choisir une ouvre la leçon.
 */
class TutorialUi {

	public function getHero(\Collection $cCourse, int $chapters, int $lessons, ?int $minutes): string {

		$search = '';
		$aside = '';

		if($lessons > 0) {

			$search = $this->getSearch($cCourse);

			$aside = '<div class="util-figures-line util-figures-line-card">';
				$aside .= '<div class="util-figures-line-item"><div class="util-figure-value">'.$chapters.'</div><div class="util-figure-label">'.p("module", "modules", $chapters).'</div></div>';
				$aside .= '<div class="util-figures-line-item"><div class="util-figure-value">'.$lessons.'</div><div class="util-figure-label">'.p("leçon", "leçons", $lessons).'</div></div>';
				if($minutes !== NULL) {
					$aside .= '<div class="util-figures-line-item"><div class="util-figure-value">'.CourseUi::getDuration($minutes).'</div><div class="util-figure-label">'.s("au total").'</div></div>';
				}
			$aside .= '</div>';

		}

		return \main\DesignUi::getPageHero(
			s("Prendre en main Ouvretaferme, pas&nbsp;à&nbsp;pas"),
			label: s("Ressources pédagogiques · Tutoriels"),
			icon: 'play-btn',
			back: '',
			lead: s("Du compte à l'analyse des résultats, en leçons de quelques minutes. Suivez les modules dans l'ordre, à votre rythme."),
			search: $search,
			aside: $aside
		);

	}

	/**
	 * Un module mène à sa première leçon écrite : il n'a pas d'adresse à lui hors de la fiche, et
	 * c'est là qu'on commence à le suivre (`eLessonFirst`). Un module sans leçon écrite n'a rien à
	 * ouvrir et ne se propose pas, ni une leçon vide (`Lesson::isLinked()`).
	 */
	protected function getSearch(\Collection $cCourse): string {

		$suggestions = [];

		foreach($cCourse as $eCourse) {

			foreach($eCourse['cChapter'] as $eChapter) {

				if($eChapter['eLessonFirst']->empty()) {
					continue;
				}

				$suggestions[] = [
					'group' => s("Modules"),
					'label' => $eChapter['name'],
					'detail' => s("Module {value}", $eChapter['number']),
					'url' => LessonUi::url($eChapter['eLessonFirst'])
				];

			}

		}

		foreach($cCourse as $eCourse) {

			foreach($eCourse['cChapter'] as $eChapter) {

				foreach($eChapter['cLesson'] as $eLesson) {

					if($eLesson->isLinked($eCourse) === FALSE) {
						continue;
					}

					$suggestions[] = [
						'group' => s("Leçons"),
						'label' => $eLesson['name'],
						'detail' => $eChapter['name'],
						'url' => LessonUi::url($eLesson)
					];

				}

			}

		}

		return \main\OmniboxUi::get(
			suggestions: $suggestions,
			placeholder: s("Un module, une leçon..."),
			class: 'util-omnibox-hero mt-2'
		);

	}

	public function getEmpty(): string {

		$h = '<div class="util-band">';
			$h .= '<div class="container">';
				$h .= '<div class="util-block util-block-empty">';
					$h .= '<h3>'.s("Aucun tutoriel publié pour le moment").'</h3>';
					$h .= '<p>'.s("Les tutoriels de Ouvretaferme paraîtront ici dès leur publication.").'</p>';
				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les formations du réseau, phase par phase. Une seule paraît d'habitude, et elle n'a pas de titre
	 * à elle : la bande d'en-tête la nomme. Plusieurs formations s'annoncent chacune par son nom.
	 */
	public function getSummary(\Collection $cCourse, array $faqsByChapter): string {

		$h = '';

		foreach($cCourse as $eCourse) {

			if($cCourse->count() > 1) {
				$h .= '<div class="util-band">';
					$h .= '<div class="container">';
						$h .= '<h2>'.encode($eCourse['name']).'</h2>';
						if($eCourse['summary'] !== NULL) {
							$h .= '<p>'.encode($eCourse['summary']).'</p>';
						}
					$h .= '</div>';
				$h .= '</div>';
			}

			$h .= $this->getCourse($eCourse, $faqsByChapter);

		}

		return $h;

	}

	/**
	 * Une formation et son programme. Les phases sans module ne se montrent pas : leurs modules
	 * restent à écrire. Les modules sans phase viennent à la suite, sous « Autres modules » quand la
	 * formation a des phases, et seuls sinon.
	 */
	protected function getCourse(Course $eCourse, array $faqsByChapter): string {

		$eCourse->expects(['lessons', 'cPhase', 'cChapterWithoutPhase']);

		if($eCourse['lessons'] === 0) {

			$h = '<div class="util-band">';
				$h .= '<div class="container">';
					$h .= new CourseUi()->getProgram($eCourse, new \Collection(), new \Collection());
				$h .= '</div>';
			$h .= '</div>';

			return $h;

		}

		$h = '';

		// Une bande sur deux est grisée, dans l'ordre où elles se suivent, « Autres modules » compris.
		$alternate = FALSE;

		foreach($eCourse['cPhase'] as $ePhase) {

			if($ePhase['cChapter']->notEmpty()) {
				$h .= $this->getPhase($eCourse, $ePhase, $faqsByChapter, $alternate);
				$alternate = !$alternate;
			}

		}

		if($eCourse['cChapterWithoutPhase']->notEmpty()) {

			$h .= '<section class="util-band'.($alternate ? ' util-band-alt' : '').'">';
				$h .= '<div class="container util-layout-aside-start" style="--aside-width: 19rem">';
					$h .= '<div class="util-layout-aside-side">';
						if($eCourse['cPhase']->notEmpty()) {
							$h .= '<h2>'.s("Autres modules").'</h2>';
						}
					$h .= '</div>';
					$h .= $this->getChapters($eCourse, $eCourse['cChapterWithoutPhase'], $faqsByChapter);
				$h .= '</div>';
			$h .= '</section>';

		}

		return $h;

	}

	/**
	 * Une phase en bande, à la teinte de son rang : à gauche sa pastille, son nom, son résumé et ce
	 * qu'elle contient, à droite ses modules.
	 */
	protected function getPhase(Course $eCourse, Phase $ePhase, array $faqsByChapter, bool $alternate): string {

		$ePhase->expects(['number', 'tint', 'chapterFrom', 'chapterTo', 'lessons', 'minutes', 'cChapter']);

		$caption = [
			($ePhase['chapterFrom'] === $ePhase['chapterTo'])
				? s("Module {value}", $ePhase['chapterFrom'])
				: s("Modules {from} à {to}", ['from' => $ePhase['chapterFrom'], 'to' => $ePhase['chapterTo']]),
			p("{value} leçon", "{value} leçons", $ePhase['lessons'])
		];

		if($ePhase['minutes'] !== NULL) {
			$caption[] = CourseUi::getDuration($ePhase['minutes']);
		}

		$h = '<section class="util-band'.($alternate ? ' util-band-alt' : '').' util-tint-phase-'.$ePhase['tint'].'">';
			$h .= '<div class="container util-layout-aside-start" style="--aside-width: 19rem">';

				$h .= '<div class="util-layout-aside-side">';
					$h .= '<span class="badge badge-solid badge-tint">'.s("Phase {value}", $ePhase['number']).'</span>';
					$h .= '<h2>'.encode($ePhase['name']).'</h2>';
					if($ePhase['summary'] !== NULL) {
						$h .= '<p>'.encode($ePhase['summary']).'</p>';
					}
					$h .= '<span class="util-rule"></span>';
					// Un nombre ne se sépare pas de son mot en fin de ligne.
					$h .= '<p class="util-rule-caption">'.implode(' · ', array_map(fn(string $part) => str_replace(' ', '&nbsp;', $part), $caption)).'</p>';
				$h .= '</div>';

				$h .= $this->getChapters($eCourse, $ePhase['cChapter'], $faqsByChapter);

			$h .= '</div>';
		$h .= '</section>';

		return $h;

	}

	/**
	 * Les modules en étapes numérotées « 01 », « 02 »… La numérotation reprend celle du programme
	 * d'une phase à l'autre, d'où le premier numéro posé sur la liste.
	 */
	protected function getChapters(Course $eCourse, \Collection $cChapter, array $faqsByChapter): string {

		$h = '<ol class="util-procedure util-procedure-tint" style="--marker-size: 2.75rem; --marker-counter: decimal-leading-zero; --marker-start: '.$cChapter->first()['number'].'">';

			foreach($cChapter as $eChapter) {
				$h .= '<li class="util-procedure-step">'.$this->getChapter($eCourse, $eChapter, $faqsByChapter).'</li>';
			}

		$h .= '</ol>';

		return $h;

	}

	/**
	 * Un module : son nom et sa question, ce qu'il contient à droite, puis ses leçons. Il porte
	 * l'ancre `#module-{id}` des liens de la FAQ.
	 */
	protected function getChapter(Course $eCourse, Chapter $eChapter, array $faqsByChapter): string {

		$h = '<div class="util-deck util-deck-round util-deck-top util-deck-open presentation-tutorial-module" id="module-'.$eChapter['id'].'">';

			$h .= '<div class="util-deck-head">';
				$h .= '<div class="util-deck-body">';
					$h .= '<h3 class="util-deck-title">'.encode($eChapter['name']).'</h3>';
					if($eChapter['summary'] !== NULL) {
						$h .= '<p class="presentation-tutorial-question">'.encode($eChapter['summary']).'</p>';
					}
				$h .= '</div>';
				$h .= '<ul class="util-deck-aside-list">';
					if($eChapter['minutes'] !== NULL) {
						$h .= '<li class="util-deck-aside-total">'.\Asset::icon('clock').' '.CourseUi::getDuration($eChapter['minutes']).'</li>';
					}
					if($eChapter['cLesson']->notEmpty()) {
						$h .= '<li>'.\Asset::icon('play-btn').' '.p("{value} leçon", "{value} leçons", $eChapter['cLesson']->count()).'</li>';
					}
					if(array_key_exists($eChapter['id'], $faqsByChapter)) {
						$h .= '<li><a href="/faq#module-'.$eChapter['id'].'">'.\Asset::icon('chat-square-text').' '.p("{value} question de la FAQ", "{value} questions de la FAQ", $faqsByChapter[$eChapter['id']]).'</a></li>';
					}
				$h .= '</ul>';
			$h .= '</div>';

			if($eChapter['cLesson']->notEmpty()) {

				$h .= '<div class="util-deck-fold">';

					foreach($eChapter['cLesson'] as $eLesson) {

						$linked = $eLesson->isLinked($eCourse);

						$h .= $linked
							? '<a href="'.LessonUi::url($eLesson).'" class="util-deck-row util-deck-row-numbered">'
							: '<div class="util-deck-row util-deck-row-numbered">';
							$h .= '<span class="util-deck-row-index">'.CourseUi::getLessonNumber($eCourse, $eLesson).'</span>';
							$h .= '<span class="util-deck-row-name">'.encode($eLesson['name']).'</span>';
							$h .= CourseUi::getLessonState($eCourse, $eLesson, new \Collection());
						$h .= $linked ? '</a>' : '</div>';

					}

				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * L'appel en fin de page : le premier module qui a une leçon écrite, et le geste qui ouvre cette
	 * leçon. Le module y compte toutes ses leçons, comme sa carte.
	 */
	public function getStart(Chapter $eChapter): string {

		if($eChapter->empty()) {
			return '';
		}

		$h = '<div class="util-band">';
			$h .= '<div class="container">';
				$h .= '<div class="util-block util-block-dark util-block-round bg-primary util-action presentation-start">';
					$h .= '<div>';
						$h .= '<div class="util-label">'.s("Votre point de départ").'</div>';
						$h .= '<h2>'.CourseUi::getChapterName($eChapter['course'], $eChapter).'</h2>';
						$h .= '<p>'.implode(', ', array_filter([
							p("{value} leçon", "{value} leçons", $eChapter['cLesson']->count()),
							$eChapter['minutes'] === NULL ? NULL : CourseUi::getDuration($eChapter['minutes'])
						])).'.</p>';
					$h .= '</div>';
					$h .= '<a href="'.LessonUi::url($eChapter['eLessonFirst']).'" class="btn btn-light">'.s("Commencer le module {value}", $eChapter['number']).'</a>';
				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

}
?>
