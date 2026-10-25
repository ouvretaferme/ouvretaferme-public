<?php
namespace learning;

/**
 * Commencer une formation. Un compte à plusieurs structures choisit dans un panneau celle au nom de
 * laquelle il la suit.
 */
class EnrollmentUi {

	public function __construct() {
		\Asset::js('learning', 'learning.js');
	}

	/**
	 * Le bouton qui commence la formation, en grand sur la fiche et à la taille des autres gestes
	 * sous une leçon. À plusieurs structures, ou quand l'inscription peut partir en demande
	 * (`onlineConfirm`, posé sur la formation par la page), il ouvre le panneau, où se lisent la note
	 * du réseau et la question de la prise en charge ; sinon il poste la seule structure du compte, ou
	 * aucune, et l'inscription passe par le même `build()` que le panneau. Quand le réseau confirme
	 * chaque inscription, le bouton dit d'emblée qu'il envoie une demande.
	 *
	 * Sous une leçon, `$eLessonOrigin` est la leçon lue : elle voyage jusqu'à `doCreate`, qui y
	 * ramène le lecteur au lieu de la première leçon. Depuis la fiche, il n'y en a pas.
	 */
	public function getStart(Course $eCourse, \Collection $cStructure, bool $small = FALSE, Lesson $eLessonOrigin = new Lesson()): string {
		return $this->getButton($eCourse, $cStructure, s("Commencer la formation"), $small, $eLessonOrigin);
	}

	/**
	 * Le même geste sur la fiche d'une formation qui s'offre à l'inscription : la personne s'inscrit
	 * et revient à la fiche, d'où elle commence quand elle veut (`enrollment:doCreate`).
	 */
	public function getEnroll(Course $eCourse, \Collection $cStructure): string {
		return $this->getButton($eCourse, $cStructure, self::getEnrollLabel(), FALSE, new Lesson());
	}

	protected static function getEnrollLabel(): string {
		return s("S'inscrire");
	}

	protected static function getRequestLabel(): string {
		return s("Envoyer la demande");
	}

	/**
	 * Le panneau ouvert à l'arrivée sur la fiche, comme si la personne avait cliqué sur le bouton :
	 * c'est là que revient un compte créé sur une formation où l'inscription peut partir en demande
	 * (`account:doCreate`), et où mène la carte « S'inscrire » d'une formation proposée. Ouvert en
	 * navigation et non en simple requête, le panneau est un calque : sa croix laisse la fiche
	 * dessous et lui rend son adresse, sans `enroll`, et le titre de l'onglet
	 * (`LearningEnrollment.openOnArrival()`).
	 */
	public function getPanelOnArrival(Course $eCourse): string {
		return '<div onrender="LearningEnrollment.openOnArrival(\'/learning/enrollment:create?course='.$eCourse['id'].'\')"></div>';
	}

	protected function getButton(Course $eCourse, \Collection $cStructure, string $label, bool $small, Lesson $eLessonOrigin): string {

		$class = 'btn btn-primary'.($small ? '' : ' btn-lg');
		$origin = $eLessonOrigin->empty() ? '' : $eLessonOrigin['id'];

		if($eCourse['onlineConfirm'] === \structure\Community::ALWAYS) {
			$label = self::getRequestLabel();
		}

		if(
			$cStructure->count() > 1 or
			$eCourse->acceptEnrollmentRequest()
		) {
			return '<a href="/learning/enrollment:create?course='.$eCourse['id'].($origin === '' ? '' : '&origin='.$origin).'" class="'.$class.'">'.$label.'</a>';
		}

		$structure = $cStructure->empty() ? '' : $cStructure->first()['id'];

		return '<a data-ajax="/learning/enrollment:doCreate" post-course="'.$eCourse['id'].'" post-structure="'.$structure.'"'.($origin === '' ? '' : ' post-origin="'.$origin.'"').' class="'.$class.'" data-waiter>'.$label.'</a>';

	}

	/**
	 * Le panneau du choix de la structure et de la question de la prise en charge. Son titre est le mot
	 * de son bouton d'envoi : « S'inscrire » depuis la fiche d'une formation où l'on s'inscrit avant
	 * de lire (`$enrollFirst`, posé par la page), « Commencer la formation » ailleurs. Ce qui part en
	 * demande s'envoie par « Envoyer la demande », le bouton des dates (`DateEnrollmentUi::getSendTexts()`) :
	 * toujours quand le réseau confirme chaque inscription, sur le choix de la prise en charge sinon,
	 * où le titre change avec le bouton (`LearningEnrollment.changeFunding()`).
	 *
	 * « Au nom de » ne se demande qu'à un compte qui a plusieurs structures : la seule structure du
	 * compte, ou aucune, part en champ caché, comme pour une date (`DateEnrollmentUi::createInscription()`).
	 */
	public function create(Enrollment $eEnrollment, bool $enrollFirst): \Panel {

		$eCourse = $eEnrollment['course'];

		$label = $enrollFirst ? self::getEnrollLabel() : s("Commencer la formation");
		$send = self::getRequestLabel();
		$title = $label;

		$form = new \util\FormUi();

		$h = $form->openAjax('/learning/enrollment:doCreate');

			$h .= $form->hidden('course', $eCourse);
			$h .= $form->hidden('origin', GET('origin'));

			$h .= $form->group(
				s("Formation"),
				'<b>'.encode($eCourse['name']).'</b>'
			);

			if($eEnrollment['cStructure']->count() > 1) {
				$h .= $form->dynamicGroup($eEnrollment, 'structure');
			} else {
				$h .= $form->hidden('structure', $eEnrollment['cStructure']->empty() ? '' : $eEnrollment['cStructure']->first());
			}

			if($eEnrollment->hasFundingQuestion()) {

				$h .= $form->dynamicGroup($eEnrollment, 'fundingRequested');

				$h .= $form->group(
					content: $form->submit($label, ['data-label' => $label, 'data-label-requested' => $send])
				);

			} else if($eCourse['onlineConfirm'] === \structure\Community::ALWAYS) {

				$title = $send;

				$h .= $form->group(
					content: \util\FormUi::info(s("{value} confirme chaque inscription. Un e-mail vous le dira.", encode($eCourse['community']['name'])))
				);

				$h .= $form->group(
					content: $form->submit($send)
				);

			} else {

				$h .= $form->group(
					content: $form->submit($label)
				);

			}

		$h .= $form->close();

		return new \Panel(
			id: 'panel-learning-enrollment',
			title: $title,
			body: $h
		);

	}

	/**
	 * La marque de la fiche quand la demande attend le réseau, à la place de « S'inscrire » : les
	 * formes et les mots de la marque d'une date (`DateEnrollmentUi::getMineTexts()`), pour que l'état
	 * se reconnaisse d'une formation à l'autre.
	 */
	public function getRequest(Enrollment $eEnrollment): string {

		[$label, $class, $action, $confirm] = DateEnrollmentUi::getMineTexts(DateEnrollment::REQUESTED);

		$h = '<div>';
			$h .= '<a class="btn btn-badge badge '.$class.' dropdown-toggle" data-dropdown="bottom-end">'.$label.'</a>';
			$h .= '<div class="dropdown-list">';
				$h .= '<a data-ajax="/learning/enrollment:doCancel" post-id="'.$eEnrollment['id'].'" class="dropdown-item" data-confirm="'.s("Annuler votre demande d'inscription à cette formation ?").'" data-waiter>'.$action.'</a>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les demandes à confirmer, en tête du suivi d'une formation en ligne, que l'annulation redessine
	 * seules (`learning-enrollment-requests`). Le bloc reste dans la page sans demande, vide, pour
	 * que la réponse ciblée trouve toujours sa place.
	 */
	public function getRequests(Course $eCourse, \Collection $cEnrollment): string {

		$h = '<div id="learning-enrollment-requests">';

			if($cEnrollment->notEmpty()) {

				$h .= '<div class="util-title">';
					$h .= '<h3>'.s("À confirmer").' <span class="util-counter">'.$cEnrollment->count().'</span></h3>';
				$h .= '</div>';

				$h .= '<div class="util-table util-table-even learning-table-enrolled mb-2">';

					$h .= '<div class="util-table-head">';
						$h .= '<div>'.s("Stagiaire").'</div>';
						$h .= '<div class="hide-xs-down">'.s("Adresse e-mail").'</div>';
						$h .= '<div class="hide-xs-down">'.s("Reçue le").'</div>';
						$h .= '<div></div>';
					$h .= '</div>';

					foreach($cEnrollment as $eEnrollment) {
						$h .= $this->getRequestRow($eEnrollment);
					}

				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}

	/**
	 * La demande de prise en charge se marque près du nom : c'est elle qui fait attendre la demande
	 * quand le réseau ne confirme que celles-là.
	 */
	protected function getRequestRow(Enrollment $eEnrollment): string {

		$structure = $eEnrollment['structure']->empty() ? s("À titre personnel") : encode($eEnrollment['structure']['name']);

		$h = '<div class="util-table-row" id="learning-enrollment-'.$eEnrollment['id'].'">';

			$h .= '<div>';
				$h .= '<b>'.$eEnrollment['user']->getName().'</b>';
				if($eEnrollment['fundingRequested'] === TRUE) {
					$h .= ' <span class="badge badge-info">'.s("Prise en charge demandée").'</span>';
				}
				$h .= '<div class="util-annotation">'.$structure.'</div>';
			$h .= '</div>';
			$h .= '<div class="hide-xs-down">'.SupervisorUi::getEmail($eEnrollment['user']['email']).'</div>';
			$h .= '<div class="hide-xs-down">'.\util\DateUi::numeric($eEnrollment['createdAt'], \util\DateUi::DAY_MONTH).'</div>';
			$h .= '<div class="text-end">'.$this->getRequestActions($eEnrollment).'</div>';

		$h .= '</div>';

		return $h;

	}

	protected function getRequestActions(Enrollment $eEnrollment): string {

		$name = $eEnrollment['user']->getName();

		$h = '<a class="btn btn-color-primary btn-sm dropdown-toggle" data-dropdown="bottom-end">'.\Asset::icon('gear-fill').'</a>';
		$h .= '<div class="dropdown-list">';
			$h .= '<div class="dropdown-title">'.$name.'</div>';
			$h .= '<a data-ajax="/learning/enrollment:doConfirm" post-id="'.$eEnrollment['id'].'" class="dropdown-item" data-waiter>'.s("Confirmer l'inscription").'</a>';
			$h .= '<div class="dropdown-divider"></div>';
			$h .= '<a data-ajax="/learning/enrollment:doCancelByCommunity" post-id="'.$eEnrollment['id'].'" class="dropdown-item" data-confirm="'.s("Annuler la demande de {value} ? Un e-mail le lui dira.", $name).'" data-waiter>'.s("Annuler la demande").'</a>';
		$h .= '</div>';

		return $h;

	}

	protected static function getCourseUrl(Enrollment $eEnrollment): string {
		return \Lime::getUrl().CourseUi::url($eEnrollment['course']);
	}

	/**
	 * À la confirmation par le réseau. La personne commence depuis la fiche.
	 */
	public function getConfirmedMail(Enrollment $eEnrollment): array {

		$eCourse = $eEnrollment['course'];

		return \main\MailSoftwareAiUi::format(
			s("Votre inscription à la formation « {value} » est confirmée", $eCourse['name']),
			heroTitle: s("Inscription confirmée"),
			heroText: s("{community} confirme votre inscription à la formation « {course} ».", ['community' => encode($eCourse['community']['name']), 'course' => encode($eCourse['name'])]),
			buttonLabel: s("Voir la formation"),
			buttonUrl: self::getCourseUrl($eEnrollment)
		);

	}

	/**
	 * À la personne, quand le réseau annule sa demande. L'adresse d'envoi ne reçoit pas de réponse :
	 * c'est le contact (`DateEnrollmentUi::getContact()`) qui permet d'en savoir plus.
	 */
	public function getCanceledByCommunityMail(Enrollment $eEnrollment, Supervisor $eSupervisorContact): array {

		$eCourse = $eEnrollment['course'];

		return \main\MailSoftwareAiUi::format(
			s("Votre demande d'inscription à la formation « {value} » est annulée", $eCourse['name']),
			'<p>'.DateEnrollmentUi::getContact($eCourse['community'], $eSupervisorContact).' '.s("Vous pouvez vous inscrire de nouveau depuis la fiche de la formation.").'</p>',
			heroTitle: s("Demande annulée"),
			heroText: s("{community} annule votre demande d'inscription à la formation « {course} ».", ['community' => encode($eCourse['community']['name']), 'course' => encode($eCourse['name'])]),
			buttonLabel: s("Voir la formation"),
			buttonUrl: self::getCourseUrl($eEnrollment)
		);

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Enrollment::model()->describer($property, [
			'structure' => s("Au nom de"),
			'fundingRequested' => s("Prise en charge"),
		]);

		switch($property) {

			case 'structure' :
				$d->field = 'radio';
				$d->values = function(Enrollment $e) {

					$e->expects(['cStructure']);

					$values = [];

					foreach($e['cStructure'] as $eStructure) {
						$values[$eStructure['id']] = encode($eStructure['name']);
					}

					// FormUi::inputRadio() compare la valeur à (string) NULL, soit '' : sans `checked`, ce
					// choix serait coché dès qu'aucune ferme ne l'est.
					$values[] = ['value' => '', 'label' => s("Aucune, à titre personnel"), 'attributes' => ['checked' => FALSE]];

					return $values;

				};
				$d->required = TRUE;
				break;

			// Aucun choix coché d'avance (`Enrollment::properties()`) : chacun dit ce qui le suit.
			case 'fundingRequested' :
				$d->field = 'radio';
				$d->values = function(Enrollment $e) {

					return [
						0 => '<div class="field-radio-group-label">'.s("Sans prise en charge").'</div><div class="field-radio-group-reason">'.s("Vous êtes inscrit dès l'envoi et vous pouvez commencer.").'</div>',
						1 => '<div class="field-radio-group-label">'.s("Je demande une prise en charge").'</div><div class="field-radio-group-reason">'.s("{value} confirme votre inscription. Un e-mail vous le dira.", encode($e['course']['community']['name'])).'</div>',
					];

				};
				$d->attributes = ['onchange' => 'LearningEnrollment.changeFunding(this)'];
				$d->required = TRUE;
				break;

		}

		return $d;

	}

}
?>
