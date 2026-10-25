<?php
namespace learning;

/**
 * La FAQ publiée d'une structure, sur deux pages : les questions d'utilisation de Ouvretaferme
 * (`/faq`, réseau des tutoriels) et les questions fréquentes d'un réseau (`/reseau/{fqn}/faq`).
 *
 * Les deux s'ouvrent sur leur bande d'accueil (`getHero()`, `getNetworkHero()`), puis la liste
 * des questions à côté d'un rail qui la filtre : par écran sur `/faq` (`getBody()`), par module
 * sur un réseau (`getNetworkBody()`), dont les questions se rangent par formation. Elles
 * partagent la question et sa réponse (`getItem()`), la feuille des pages publiques
 * (`template-presentation.css`) et `faq.js`.
 *
 * La recherche est l'omnibox du produit en mode local : `faq.js` y propose les questions déjà
 * dans la page, trouvées aussi par leur réponse courte, leurs symptômes et leurs mots-clés
 * (`terms`). Une question liée à un module mène à lui, et l'ancre `#module-{id}` venue du sommaire
 * des tutoriels pose ce module en jeton sur `/faq`, ou choisit son option du rail sur un réseau.
 */
class FaqUi {

	public function __construct() {
		\Asset::js('learning', 'faq.js');
	}

	public function getHero(int $faqs, int $screens): string {

		return \main\DesignUi::getPageHero(
			s("Que se passe-t-il à l'écran ?"),
			label: s("Ressources pédagogiques · Questions d'utilisation"),
			icon: 'question-circle',
			back: '',
			lead: s("Décrivez ce que vous voyez avec vos mots, sans chercher le nom de la fonction. Chaque réponse dit pourquoi, et comment faire autrement."),
			search: $this->getSearch($faqs),
			aside: ($faqs === 0) ? '' : $this->getFigures([
				[$faqs, p("question", "questions", $faqs)],
				[$screens, p("écran", "écrans", $screens)]
			])
		);

	}

	/**
	 * La bande d'un réseau se passe de surtitre : le bandeau au-dessus dit déjà « Formations ». Elle
	 * prend la couleur du réseau sous `util-brand-scope`, que pose `CustomizeUi::getPublic()`.
	 */
	public function getNetworkHero(int $faqs, string $back): string {

		return \main\DesignUi::getPageHero(
			s("Questions fréquentes"),
			icon: 'question-circle',
			back: $back,
			lead: s("Les réponses de l'équipe du réseau aux questions qui reviennent. Décrivez ce que vous voyez avec vos mots."),
			search: $this->getSearch($faqs),
			aside: ($faqs === 0) ? '' : $this->getFigures([
				[$faqs, p("question", "questions", $faqs)]
			])
		);

	}

	protected function getSearch(int $faqs): string {

		if($faqs === 0) {
			return '';
		}

		return \main\OmniboxUi::get(
			placeholder: s("Ce que vous voyez à l'écran..."),
			id: 'learning-faq-search',
			class: 'util-omnibox-hero mt-2',
			singleByGroup: TRUE
		);

	}

	protected function getFigures(array $figures): string {

		$h = '<div class="util-figures-line util-figures-line-card">';
			foreach($figures as [$value, $label]) {
				$h .= '<div class="util-figures-line-item"><div class="util-figure-value">'.$value.'</div><div class="util-figure-label">'.$label.'</div></div>';
			}
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les questions de `/faq` à côté du rail des écrans, puis l'appel au Discord. Le rail filtre la
	 * liste sans la recharger (`faq.js`) : chaque option porte l'écran qu'elle garde, « Tous les
	 * écrans » aucun. Sous le jeton d'un module, « Tous » ne compte que les écrans du module.
	 */
	public function getBody(\Collection $ccFaq, int $faqs): string {

		if($faqs === 0) {
			return \main\DesignUi::getBand($this->getEmpty().$this->getAsk());
		}

		$options = '<a class="util-filter-option is-selected" data-label-all="'.encode(s("Tous les écrans")).'" data-label-chapter="'.encode(s("Tous les écrans du module")).'"><span data-faq-all>'.s("Tous les écrans").'</span> <span class="util-counter">'.$faqs.'</span></a>';
		$items = '';

		foreach($ccFaq as $screen => $cFaq) {

			$label = encode(self::getScreenLabel($screen));

			$options .= $this->getOption('screen', $screen, $label, $cFaq->count());

			foreach($cFaq as $eFaq) {
				$items .= $this->getItem($eFaq, $screen, $label);
			}

		}

		return \main\DesignUi::getBand($this->getLayout('screen', s("Où cela se passe-t-il ?"), $options, $faqs, $items).$this->getAsk());

	}

	/**
	 * Les questions d'un réseau à côté du rail de ses modules, rangées par `FaqLib::groupByChapter()`,
	 * sans appel au Discord, qui est celui de Ouvretaferme. Le module qui chapeaute une question
	 * porte sa formation quand le réseau en lie plusieurs (`$withCourse`) : deux formations ont
	 * chacune leur « Module 1 ». L'option du rail garde le nom du module seul, sous l'intitulé de
	 * sa formation, et le rail perd alors son libellé : les intitulés disent déjà ce qu'il filtre.
	 */
	public function getNetworkBody(\Collection $cccFaq, int $faqs, bool $withCourse): string {

		if($faqs === 0) {
			return \main\DesignUi::getBand($this->getEmpty());
		}

		$options = '<a class="util-filter-option is-selected" data-label-all="'.encode(s("Tous les modules")).'"><span data-faq-all>'.s("Tous les modules").'</span> <span class="util-counter">'.$faqs.'</span></a>';
		$items = '';

		foreach($cccFaq as $course => $ccFaq) {

			if($withCourse and $course !== '') {
				$options .= '<div class="util-filter-options-title">'.encode($ccFaq->first()->first()['eChapterLinked']['course']['name']).'</div>';
			}

			foreach($ccFaq as $chapter => $cFaq) {

				if($chapter === '') {
					$label = s("Questions générales");
					$kicker = $label;
				} else {
					$eChapter = $cFaq->first()['eChapterLinked'];
					$label = CourseUi::getChapterName($eChapter['course'], $eChapter);
					$kicker = $withCourse ? s("{course}&nbsp;· {chapter}", ['course' => encode($eChapter['course']['name']), 'chapter' => $label]) : $label;
				}

				$options .= $this->getOption('chapter', (string)$chapter, $label, $cFaq->count());

				foreach($cFaq as $eFaq) {
					$items .= $this->getItem($eFaq, $eFaq['screen'] ?? '', $kicker);
				}

			}

		}

		return \main\DesignUi::getBand($this->getLayout('chapter', $withCourse ? '' : s("Quel module ?"), $options, $faqs, $items));

	}

	/**
	 * Le rail filtre une seule dimension par page, que `faq.js` lit sur la racine (`data-filter`) :
	 * l'écran ou le module, porté par chaque option et chaque question.
	 */
	protected function getLayout(string $filter, string $title, string $options, int $faqs, string $items): string {

		$h = '<div '.$this->getListAttributes($filter).' class="util-layout-aside-start" style="--aside-width: 17rem">';

			$h .= '<nav class="util-layout-aside-side">';
				if($title !== '') {
					$h .= '<div class="util-filter-label">'.$title.'</div>';
				}
				$h .= '<div class="util-filter-options util-filter-options-column util-filter-options-nav">';
					$h .= $options;
				$h .= '</div>';
			$h .= '</nav>';

			$h .= '<div>';
				$h .= '<p class="util-annotation" data-faq-count>'.p("{value} question", "{value} questions", $faqs).'</p>';
				$h .= '<div class="util-stack util-stack-xs">';
					$h .= $items;
				$h .= '</div>';
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * `$label` est déjà échappé : un nom de module vient de `CourseUi::getChapterName()`, qui
	 * l'échappe lui-même.
	 */
	protected function getOption(string $filter, string $value, string $label, int $faqs): string {
		return '<a class="util-filter-option" data-'.$filter.'="'.encode($value).'"><span data-faq-label>'.$label.'</span> <span class="util-counter">'.$faqs.'</span></a>';
	}

	public function getEmpty(): string {

		$h = '<div class="util-block util-block-empty">';
			$h .= '<h3>'.s("Aucune question publiée pour le moment").'</h3>';
			$h .= '<p>'.s("Les réponses paraîtront ici dès leur publication.").'</p>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * La racine de la liste porte ce que `faq.js` écrit dans l'omnibox, qui n'a pas de traduction :
	 * le nom des familles de suggestions et de celle du module venu par l'ancre, et le décompte au
	 * singulier et au pluriel.
	 */
	protected function getListAttributes(string $filter): string {

		$h = 'id="learning-faq-list"';
		$h .= ' data-filter="'.$filter.'"';
		$h .= ' data-group-questions="'.encode(s("Questions")).'"';
		$h .= ' data-group-chapters="'.encode(s("Modules")).'"';
		$h .= ' data-count-one="'.encode(s("{value} question", '{value}')).'"';
		$h .= ' data-count-many="'.encode(s("{value} questions", '{value}')).'"';

		return $h;

	}

	protected static function getScreenLabel(string $screen): string {
		return ($screen === '') ? s("Questions générales") : self::p('screen')->values[$screen];
	}

	/**
	 * Une question repliée sur sa réponse, son verdict et ce qui la range au-dessus, l'écran ou le
	 * module, déjà échappé par l'appelant. L'écran est la clé de sa collection sur `/faq`, la chaîne
	 * vide pour les questions générales, et le module la clé vide quand il n'y en a pas : le rail
	 * filtre sur l'un ou l'autre. La réponse est dans la page même fermée : la recherche la lit, et
	 * un moteur l'indexe.
	 */
	protected function getItem(Faq $eFaq, string $screen, string $kicker): string {

		$eChapter = $eFaq['eChapterLinked'];

		$h = '<div class="util-deck util-deck-round presentation-faq-item" id="faq-'.$eFaq['id'].'" data-screen="'.encode($screen).'" data-chapter="'.($eChapter->empty() ? '' : $eChapter['id']).'"';
		if($eFaq['keywords'] !== NULL) {
			$h .= ' data-keywords="'.encode($eFaq['keywords']).'"';
		}
		if($eChapter->notEmpty()) {
			$h .= ' data-chapter-name="'.encode($eChapter['name']).'"';
		}
		$h .= '>';

			$h .= '<a class="util-deck-head" '.attr('onclick', 'this.closest(".util-deck").classList.toggle("util-deck-open")').'>';
				$h .= '<div class="util-deck-body">';
					$h .= '<div class="util-deck-kicker">'.$this->getVerdict($eFaq).'<span>'.$kicker.'</span></div>';
					$h .= '<div class="util-deck-title util-deck-title-heading presentation-faq-question">'.encode($eFaq['question']).'</div>';
				$h .= '</div>';
				$h .= '<span class="util-deck-chevron">'.\Asset::icon('chevron-down').'</span>';
			$h .= '</a>';

			$h .= '<div class="util-deck-fold">';
				$h .= $this->getAnswer($eFaq);
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * La réponse en champs : la réponse courte, les symptômes, « Comment faire » et « À savoir » côte
	 * à côte, puis la page de la démo où corriger et le module où apprendre. Une réponse qui n'a ni
	 * l'un ni l'autre des deux derniers champs tient sur une colonne, sans titre vide.
	 */
	protected function getAnswer(Faq $eFaq): string {

		$eChapter = $eFaq['eChapterLinked'];

		$h = '<div class="util-deck-content util-stack">';

			$h .= '<div class="presentation-faq-lead">'.new \editor\EditorUi()->value($eFaq['answer']).'</div>';

			if($eFaq['symptoms'] !== NULL) {
				$h .= '<div class="util-block-inside presentation-faq-symptoms">';
					$h .= '<div class="util-label">'.self::p('symptoms')->label.'</div>';
					$h .= new \editor\EditorUi()->value($eFaq['symptoms']);
				$h .= '</div>';
			}

			$columns = '';

			foreach(['steps', 'notes'] as $property) {
				if($eFaq[$property] !== NULL) {
					$columns .= '<div>';
						$columns .= '<div class="util-label">'.self::p($property)->label.'</div>';
						$columns .= '<div class="presentation-faq-list-text">'.new \editor\EditorUi()->value($eFaq[$property]).'</div>';
					$columns .= '</div>';
				}
			}

			if($columns !== '') {
				$h .= '<div class="util-split">'.$columns.'</div>';
			}

			$links = '';

			// `demoPage` n'admet qu'un chemin qui commence par un seul `/` (`Faq::demoPage.relative`) :
			// collé derrière l'hôte de la démo, il ne peut pas mener ailleurs.
			if($eFaq['demoPage'] !== NULL) {
				$links .= '<a href="'.encode($eFaq->getDemoUrl()).'" target="_blank" rel="noopener" class="util-link-path">';
					$links .= \Asset::icon('box-arrow-up-right');
					$links .= '<span>'.s("Corriger dans la démo");
						if($eFaq['demoPath'] !== NULL) {
							$links .= '<small>'.encode($eFaq['demoPath']).'</small>';
						}
					$links .= '</span>';
				$links .= '</a>';
			}

			if($eChapter->notEmpty()) {
				$links .= '<a href="'.$this->urlChapter($eChapter).'" class="util-link-path">';
					$links .= \Asset::icon('play-btn');
					$links .= '<span>'.($eChapter['course']->acceptTutorial() ? s("Tutoriel") : s("Formation"));
						$links .= '<small>'.CourseUi::getChapterName($eChapter['course'], $eChapter).'</small>';
					$links .= '</span>';
				$links .= '</a>';
			}

			if($links !== '') {
				$h .= '<div class="presentation-faq-links">'.$links.'</div>';
			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le module dans le programme public de sa formation : le sommaire des tutoriels, ou la fiche
	 * d'une formation de réseau, qui portent tous deux l'ancre du module (`CourseUi::getProgram()`).
	 */
	protected function urlChapter(Chapter $eChapter): string {

		$eCourse = $eChapter['course'];

		$url = $eCourse->acceptTutorial() ? '/tutoriels' : CourseUi::url($eCourse);

		return $url.'#module-'.$eChapter['id'];

	}

	/**
	 * Un verdict classe la question, il n'alerte pas : un état qu'on lit, en point, sans l'orange
	 * ni le rouge des alertes.
	 */
	protected function getVerdict(Faq $eFaq): string {

		$class = match($eFaq['verdict']) {
			Faq::POSSIBLE => 'badge-success',
			Faq::PARTIAL => 'badge-info',
			Faq::INTENDED => 'badge-neutral',
			Faq::IMPOSSIBLE => 'badge-off',
		};

		return '<span class="badge badge-dot '.$class.'">'.self::p('verdict')->values[$eFaq['verdict']].'</span>';

	}

	/**
	 * Une question qui manque se pose sur le Discord, par l'invitation : le salon lui-même ne
	 * s'ouvre qu'à ceux qui y sont déjà. Sur `/faq` seulement : le Discord est celui de
	 * Ouvretaferme, pas celui d'un réseau.
	 */
	protected function getAsk(): string {

		$h = '<div class="util-block util-block-dark util-block-round bg-primary util-action presentation-start mt-2">';
			$h .= '<div>';
				$h .= '<h2>'.s("Votre question n'y est pas ?").'</h2>';
				$h .= '<p>'.s("Posez-la sur le Discord de Ouvretaferme, où l'équipe et les utilisateurs répondent. Les questions qui reviennent rejoignent cette page.").'</p>';
			$h .= '</div>';
			$h .= '<a href="'.\main\MainSetting::URL_DISCORD_INVITE.'" target="_blank" rel="noopener" class="btn btn-light">'.\Asset::icon('discord').' '.s("Rejoindre le Discord").'</a>';
		$h .= '</div>';

		return $h;

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Faq::model()->describer($property, [
			'question' => s("Question"),
			'answer' => s("Réponse"),
			'symptoms' => s("Vous constatez peut-être"),
			'steps' => s("Comment faire"),
			'notes' => s("À savoir"),
			'demoPage' => s("Page de la démo où corriger"),
			'demoPath' => s("Emplacement de cette page dans le menu"),
			'keywords' => s("Mots-clés de la recherche"),
			'screen' => s("Écran"),
			'verdict' => s("Ce que permet le logiciel"),
			'status' => s("Publication"),
			'chapter' => s("Module lié"),
			'discordUrl' => s("Discussion sur le Discord"),
		]);

		switch($property) {

			case 'screen' :
				$d->values = [
					Faq::SERIES => s("Plan de culture"),
					Faq::SOIL => s("Plan d'assolement"),
					Faq::SEQUENCE => s("Itinéraires techniques"),
					Faq::PLANNING => s("Planning"),
					Faq::EXPORT => s("Exports"),
					Faq::SEEDLING => s("Semences et plants"),
					Faq::FORECAST => s("Prévisionnel financier"),
				];
				break;

			case 'verdict' :
				$d->values = [
					Faq::POSSIBLE => s("Possible"),
					Faq::PARTIAL => s("Possible en partie ou contournement"),
					Faq::INTENDED => s("C'est voulu"),
					Faq::IMPOSSIBLE => s("Pas possible aujourd'hui"),
				];
				break;

			case 'status' :
				$d->values = [
					Faq::DRAFT => s("Brouillon"),
					Faq::PUBLISHED => s("Publiée"),
				];
				break;

		}

		return $d;

	}

}
?>
