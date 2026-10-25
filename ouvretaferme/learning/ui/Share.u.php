<?php
namespace learning;

/**
 * L'ouverture d'une formation de groupe, aux promotions d'un groupe de formation ou aux membres d'un
 * réseau : le panneau où l'équipe la règle, ce que la fenêtre de lecture d'une promotion dit au
 * stagiaire sur la carte de la formation, et l'e-mail qui l'annonce.
 */
class ShareUi {

	public function __construct() {

		\Asset::css('learning', 'learning.css');
		\Asset::js('learning', 'learning.js');

	}

	/**
	 * Un groupe par promotion proposée : son nom en libellé, la case qui la partage, puis
	 * l'ouverture et l'échéance, qui ne se saisissent que case cochée. Une case décochée laisse ses
	 * dates vides, et la cocher reprend l'ouverture du jour que porte le partage à créer
	 * (`data-default`).
	 */
	public function share(Course $eCourse, \Collection $cChapter, \Collection $cSection): \Panel {

		$form = new \util\FormUi();

		$h = '<p>'.s("Les stagiaires des promotions cochées trouvent la formation dans « Mes formations ». Les membres de l'équipe la voient toujours.").'</p>';

		$h .= $form->hidden('id', $eCourse);

		if($cSection->empty()) {

			$h .= '<div class="util-block util-block-empty">';
				$h .= '<h3>'.s("Aucune promotion ouverte").'</h3>';
				$h .= '<p>'.s("Les promotions closes ne reçoivent plus de nouvelle formation.").'</p>';
			$h .= '</div>';

			return $this->getPanel($form, $eCourse, $cChapter, $h, shareable: FALSE);

		} else {

			foreach($cSection as $eSection) {
				$h .= $this->getSection($form, $eSection);
			}

			$h .= '<p class="util-annotation">'.s("Sans échéance, elle reste ouverte.").'</p>';

		}

		return $this->getPanel($form, $eCourse, $cChapter, $h);

	}

	/**
	 * Le panneau d'un réseau : les membres, ou personne. L'ouverture ne donne aucune lecture, elle
	 * prévient les membres et pose la formation dans l'onglet du réseau, sans période. `$eShare` est
	 * l'ouverture qu'il reprend, cochée d'avance (`ShareLib::getForNetworkPanel()`), et la formation
	 * porte le nombre de membres qu'elle atteindrait (`members`). Un réseau sans membre hors de
	 * l'équipe n'a rien à proposer, sauf à retirer une ouverture qui existe encore.
	 */
	public function shareNetwork(Course $eCourse, \Collection $cChapter, Share $eShare): \Panel {

		$eCourse->expects(['members']);

		$form = new \util\FormUi();

		$h = $form->hidden('id', $eCourse);

		if(
			$eCourse['members'] === 0 and
			$eShare->exists() === FALSE
		) {

			$h .= '<div class="util-block util-block-empty">';
				$h .= '<h3>'.s("Aucun membre").'</h3>';
				$h .= '<p>'.s("Le réseau ne compte encore aucun membre hors de son équipe.").'</p>';
			$h .= '</div>';

			return $this->getPanel($form, $eCourse, $cChapter, $h, shareable: FALSE);

		}

		$h .= $form->dynamicGroup($eShare, 'audience');

		return $this->getPanel($form, $eCourse, $cChapter, $h);

	}

	/**
	 * Le panneau est court : « Enregistrer » suit le dernier champ, dans le corps, et la croix du
	 * panneau suffit à le fermer sans enregistrer. Sans promotion ni membre à proposer, il n'a rien
	 * à enregistrer et ne porte aucun bouton : un envoi ne porterait aucune audience et se ferait
	 * refuser sur un champ que l'écran ne montre pas.
	 */
	private function getPanel(\util\FormUi $form, Course $eCourse, \Collection $cChapter, string $body, bool $shareable = TRUE): \Panel {

		if($shareable) {
			$body .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);
		}

		return new \Panel(
			id: 'panel-learning-course-share',
			title: $eCourse->isNetwork() ? s("Ouvrir la formation aux membres") : s("Ouvrir à une promotion"),
			subTitle: CourseUi::getPublicationSubTitle($eCourse, $cChapter),
			dialogOpen: $form->openAjax('/learning/course:doShare', ['class' => 'panel-dialog']),
			dialogClose: $form->close(),
			body: $body
		);

	}

	protected function getSection(\util\FormUi $form, \structure\Section $eSection): string {

		$eShare = $eSection['eShare'];
		$checked = $eShare->exists();
		$id = $eSection['id'];

		$label = encode($eSection['name']).' <span class="util-counter" title="'.s("Stagiaires").'">'.$eSection['learners'].'</span>';

		if($eSection->isClosed()) {
			$label .= ' <span class="util-annotation">'.s("Promotion close").'</span>';
		}

		$content = $form->checkbox('section[]', $id, [
			'checked' => $checked,
			'onclick' => 'LearningShare.toggle(this)',
			'callbackLabel' => fn($input) => $input.' '.s("Ouvrir la formation à cette promotion")
		]);

		$content .= $form->inputGroup(
			$form->addon(s("Du")).
			$form->date('opensAt['.$id.']', $checked ? $eShare['opensAt'] : NULL, [
				'disabled' => $checked ? NULL : TRUE,
				'data-default' => $eShare['opensAt']
			]).
			$form->addon(s("au")).
			$form->date('dueAt['.$id.']', $checked ? $eShare['dueAt'] : NULL, [
				'disabled' => $checked ? NULL : TRUE
			]),
			['class' => 'mt-1']
		);

		// Les deux dates dans un seul wrapper : un refus de l'une ou de l'autre s'affiche sous la
		// promotion, comme la période d'une distribution (`amap/ui/Distribution.u.php`).
		return $form->group($label, $content, ['for' => FALSE, 'data-wrapper' => 'opensAt['.$id.'] dueAt['.$id.']']);

	}

	/**
	 * La bande de la carte d'une formation que la promotion n'a pas encore ouverte : le jour où elle
	 * s'ouvre, à la place de la progression.
	 */
	public static function getOpensBand(Share $eShare): string {
		return '<div class="util-deck-band"><span>'.s("Ouvre le {value}", \util\DateUi::numeric($eShare['opensAt'], \util\DateUi::DATE)).'</span></div>';
	}

	/**
	 * L'échéance de la promotion, que la bande de progression de la carte porte à la suite (planche
	 * carte-hierarchie du 02/10, bandes C) : jusqu'à quand la suivre, ou que l'échéance est passée.
	 * Rien hors promotion, sans échéance, ou une fois la formation suivie en entier.
	 */
	public static function getCardDue(Course $eCourse, Share $eShare): ?string {

		if(
			$eShare->empty() or
			$eShare['dueAt'] === NULL or
			$eCourse->isDone()
		) {
			return NULL;
		}

		return $eShare->acceptProgress() ? self::getDue($eShare) : self::getDuePast($eShare);

	}

	/**
	 * L'échéance d'une formation ouverte, sur la carte et sous le titre de la fiche.
	 */
	public static function getDue(Share $eShare): string {

		return s("À suivre avant le {value}", \util\DateUi::numeric($eShare['dueAt'], \util\DateUi::DATE));

	}

	/**
	 * Ce que lit le stagiaire à la place du geste de suivi, sur une formation figée.
	 */
	public static function getDuePast(Share $eShare): string {

		return s("Échéance passée le {value}", \util\DateUi::numeric($eShare['dueAt'], \util\DateUi::DATE));

	}

	/**
	 * L'e-mail d'annonce d'une formation ouverte (F35), un par compte que les partages atteignent
	 * (`ShareLib::claimAnnounce()`) : ce qui est ouvert, par qui, et pour une promotion à quel titre
	 * et jusqu'à quand. Le compte d'une promotion porte ses promotions (`cSection`) et son échéance
	 * (`dueAt`) ; la formation ses leçons, leur durée, sa prochaine date et son réseau
	 * (`ShareLib::fillAnnounce()`).
	 *
	 * Un réseau propose la formation à ses membres, qui s'y inscrivent : l'e-mail ne dit pas
	 * qu'elle leur est ouverte, et ne porte pas d'échéance. Le bouton d'une formation datée mène à
	 * sa page publique, où l'on s'inscrit à une date, celui d'une formation en ligne à sa fiche.
	 *
	 * Une formation datée montre sa prochaine date, la durée et le prix de cette date, repris de la
	 * formation quand la date n'en porte pas ; sans date à venir, ni date ni prix, la fiche dira
	 * quand une date s'ouvre. Une formation en ligne montre ses leçons et leur durée.
	 */
	public function getAnnounceMail(Course $eCourse, \user\User $eUser): array {

		$eCourse->expects(['lessons', 'minutes', 'eDateNext', 'eCommunity']);

		$community = encode($eCourse['community']['name']);

		if($eCourse['community']->isNetwork()) {

			$title = s("{value} vous propose une formation", $eCourse['community']['name']);

			$heroText = $eCourse->isDated()
				? s("Le réseau {community} vous propose cette formation. Vous vous y inscrivez en votre nom, à la date de votre choix.", ['community' => $community])
				: s("Le réseau {community} vous propose cette formation en ligne. Vous vous y inscrivez en votre nom.", ['community' => $community]);

			$facts = [s("Réseau") => $community];

			$buttonLabel = s("Voir la formation et s'inscrire");

			if($eCourse->isDated() === FALSE) {
				$buttonUrl = \Lime::getUrl().CourseUi::url($eCourse);
			} else if($eCourse['eDateNext']->notEmpty()) {
				$buttonUrl = DatePublicUi::urlDate($eCourse['eCommunity'], $eCourse['eDateNext']);
			} else {
				$buttonUrl = DatePublicUi::urlList($eCourse['eCommunity']);
			}

			$buttonNote = NULL;

		} else {

			$eUser->expects(['cSection', 'dueAt']);

			$sections = self::getNames($eUser['cSection'], quoted: TRUE);
			$several = ($eUser['cSection']->count() > 1);

			$title = $several
				? s("Une nouvelle formation pour vos promotions")
				: s("Une nouvelle formation pour votre promotion");

			$heroText = $several
				? s("Le groupe {community} ouvre cette formation à vos promotions {sections}.", ['community' => $community, 'sections' => $sections])
				: s("Le groupe {community} ouvre cette formation à votre promotion {sections}.", ['community' => $community, 'sections' => $sections]);

			$facts = [s("Groupe") => $community];

			$buttonLabel = s("Lire la formation");
			$buttonUrl = \Lime::getUrl().CourseUi::url($eCourse);
			$buttonNote = $several
				? s("Vous la retrouverez aussi dans «&nbsp;Mes formations&nbsp;», sous le nom de vos promotions.")
				: s("Vous la retrouverez aussi dans «&nbsp;Mes formations&nbsp;», sous le nom de votre promotion.");

		}

		if($eCourse['level']->notEmpty()) {
			$facts[s("Niveau")] = encode($eCourse['level']['name']);
		}

		$eDateNext = $eCourse['eDateNext'];

		if($eDateNext->notEmpty()) {

			$facts[s("Prochaine date")] = DateUi::getDays($eDateNext);
			$facts[s("Durée")] = CourseUi::getDuration($eDateNext->getMinutes());

			$price = CourseUi::isPaidOnline($eCourse, $eDateNext)
				? CourseUi::getAmount($eDateNext->getPrice())
				: CourseUi::getPriceAmounts($eDateNext);

			if($price !== '') {
				$facts[s("Prix")] = $price;
			}

		} else {

			$volume = CourseUi::getVolume($eCourse);

			if($volume !== NULL) {
				$facts[s("Durée")] = $volume;
			}

		}

		if($eCourse['community']->isNetwork() === FALSE) {
			$facts[s("Échéance")] = ($eUser['dueAt'] === NULL)
				? s("Aucune, la formation reste ouverte")
				: \util\DateUi::textual($eUser['dueAt'], \util\DateUi::DATE);
		}

		return \main\MailSoftwareAiUi::format(
			$title,
			content: ($eCourse['summary'] === NULL) ? '' : '<p>'.encode($eCourse['summary']).'</p>',
			heroTitle: encode($eCourse['name']),
			heroText: $heroText,
			facts: $facts,
			buttonLabel: $buttonLabel,
			buttonUrl: $buttonUrl,
			buttonNote: $buttonNote
		);

	}

	/**
	 * « A », « A et B », « A, B et C », chaque nom encodé, et entre guillemets quand `$quoted` le
	 * demande : un nom de promotion se lit mal sans eux au fil de la phrase (« à votre promotion
	 * Promotion 2026 »).
	 */
	private static function getNames(\Collection $c, bool $quoted = FALSE): string {

		$names = [];

		foreach($c as $e) {
			$names[] = $quoted ? '«&nbsp;'.encode($e['name']).'&nbsp;»' : encode($e['name']);
		}

		$last = array_pop($names);

		return ($names === []) ? $last : s("{list} et {last}", ['list' => implode(', ', $names), 'last' => $last]);

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Share::model()->describer($property, [
			'audience' => s("Ouvrir à"),
			'section' => s("Promotion"),
			'opensAt' => s("Ouverture"),
			'dueAt' => s("Échéance"),
		]);

		switch($property) {

			case 'audience' :
				$d->values = [
					Share::MEMBERS => s("Tous les membres du réseau"),
				];
				$d->field = fn(\util\FormUi $form, Share $e) => $form->radios('audience', [
					Share::MEMBERS => '<div class="field-radio-group-label">'.$d->values[Share::MEMBERS].' <span class="util-annotation">'.p("{value} membre", "{value} membres", $e['course']['members']).'</span></div><div class="field-radio-group-reason">'.s("Chaque membre est prévenu par e-mail, qu'il ait rattaché une structure ou non.").'</div>',
					'' => '<div class="field-radio-group-label">'.s("Personne pour l'instant").'</div><div class="field-radio-group-reason">'.s("Aucun e-mail ne part et la formation quitte l'onglet du réseau. Elle reste sur la page publique du réseau.").'</div>',
				], $e['audience'], [
					'required' => TRUE
				]);
				break;

		}

		return $d;

	}

}
?>
