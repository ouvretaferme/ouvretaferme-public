<?php
namespace learning;

/**
 * Le journal de lecture d'un stagiaire (lot L8c, planche L8 bloc 4, forme A) : le panneau que le nom
 * ouvre au suivi de l'équipe, et « Mon suivi » du stagiaire. Le même corps pour les deux, seuls le
 * titre et les phrases de l'état vide changent.
 */
class ReadingUi {

	public function __construct() {
		\Asset::css('learning', 'learning.css');
	}

	public static function urlJournal(Course $eCourse, \user\User $eUser): string {
		return '/learning/progress:journal?id='.$eCourse['id'].'&user='.$eUser['id'];
	}

	public static function urlMine(Course $eCourse): string {
		return '/learning/progress:mine?id='.$eCourse['id'];
	}

	/**
	 * `$team` dit qui lit : l'équipe, qui voit le nom du stagiaire en titre, ou le stagiaire lui-même.
	 * Le stagiaire est passé par `ProgressLib::fillSummary()`, `DateEnrollmentLib::fillAttended()` et
	 * `ReadingLib::fillMeasured()`, le
	 * journal vient de `ReadingLib::getJournal()`. Le pied télécharge le relevé PDF de ce même
	 * stagiaire (lot L8d), par la route de l'équipe ou par celle du stagiaire.
	 */
	public function getPanel(Course $eCourse, \Collection $cChapter, \user\User $eUser, array $journal, bool $team): \Panel {

		$eEnrollment = $eUser['eEnrollment'];

		$subTitle = encode($eCourse['name']);

		if(
			$eEnrollment->notEmpty() and
			$eEnrollment['structure']->notEmpty()
		) {
			$subTitle .= ' · '.encode($eEnrollment['structure']['name']);
		}

		$h = $this->getSummary($eCourse, $eUser);

		$h .= '<div class="util-title">';
			$h .= '<h3>'.s("Journal").'</h3>';
		$h .= '</div>';

		$h .= $this->getJournal($journal, $team);

		$url = $team ? ReportUi::url($eCourse, $eUser) : ReportUi::urlMine($eCourse);

		return new \Panel(
			id: 'panel-learning-reading',
			title: $team ? \user\UserUi::name($eUser) : s("Mon suivi"),
			subTitle: $subTitle,
			body: $h,
			footer: '<a href="'.$url.'" class="btn btn-outline-primary" data-ajax-download data-waiter>'.\Asset::icon('download').' '.s("Relevé en PDF").'</a>'
		);

	}

	/**
	 * Trois lignes, chacune nommée d'après ce qu'elle compte : la durée retenue est la durée estimée
	 * des leçons validées (arbitrage « Heures »), plus les heures pointées aux dates (lot L8e-3), le
	 * temps mesuré celui de toutes les lectures, sur toutes les leçons, qu'elles soient validées ou
	 * non (planche L8, reprise 1).
	 */
	public function getSummary(Course $eCourse, \user\User $eUser): string {

		$eUser->expects(['validated', 'readings', 'minutesMeasuredAll', 'secondsMeasuredAll', 'cDateEnrollmentAttended', 'attendedModes', 'minutesRetained']);

		$measured = ($eUser['readings'] === 0)
			? s("Aucune lecture")
			: p("{duration} en {value} lecture, toutes leçons confondues", "{duration} en {value} lectures, toutes leçons confondues", $eUser['readings'], ['duration' => self::getMeasured($eUser['secondsMeasuredAll'], $eUser['minutesMeasuredAll'])]);

		return CourseUi::getFicheTable([
			s("Leçons validées") => s("{validated} sur {lessons}", ['validated' => $eUser['validated'], 'lessons' => $eCourse['lessons']]),
			s("Durée retenue") => $eUser['cDateEnrollmentAttended']->empty()
				? s("{duration}, la durée estimée des leçons validées", ['duration' => CourseUi::getDuration($eUser['minutesRetained'])])
				: s("{duration}, la durée estimée des leçons validées et les heures de {modes} pointées", ['duration' => CourseUi::getDuration($eUser['minutesRetained']), 'modes' => mb_strtolower(AttestationUi::getModesName($eUser['attendedModes']))]),
			s("Temps mesuré") => $measured,
		], 'mb-2');

	}

	/**
	 * Un groupe par jour, du plus ancien au plus récent, et dans le jour une ligne par séance ou par
	 * événement qu'aucune séance n'encadre, sans durée. Le statut a sa colonne à droite de la leçon
	 * (retour du 28/09) : sous le nom, il se lisait comme un sous-titre de la leçon. Une
	 * séance sans événement laisse la cellule vide, comme l'annexe du relevé.
	 */
	public function getJournal(array $journal, bool $team): string {

		if($journal === []) {

			$h = '<div class="util-block util-block-empty">';
				$h .= '<h3>'.s("Aucune leçon ouverte").'</h3>';
				$h .= '<p>'.($team ? s("Chaque leçon que ce stagiaire ouvre s'inscrira ici, avec son heure et sa durée.") : s("Chaque leçon que vous ouvrez s'inscrira ici, avec son heure et sa durée.")).'</p>';
			$h .= '</div>';

			return $h;

		}

		$progressUi = new ProgressUi();

		$h = '<table class="tr-bordered">';

			$h .= '<thead>';
				$h .= '<tr>';
					$h .= '<th>'.s("Heure").'</th>';
					$h .= '<th>'.s("Leçon").'</th>';
					$h .= '<th>'.s("Statut").'</th>';
					$h .= '<th class="text-end">'.s("Lecture").'</th>';
				$h .= '</tr>';
			$h .= '</thead>';

			foreach($journal as $day => $rows) {

				$h .= '<tbody class="tbody-group">';

					$h .= '<tr class="tr-title">';
						$h .= '<td colspan="4">'.self::getDay($day).'</td>';
					$h .= '</tr>';

					foreach($rows as $row) {

						$h .= '<tr>';
							$h .= '<td>'.\util\DateUi::numeric($row['at'], \util\DateUi::TIME_HOUR_MINUTE).'</td>';
							$h .= '<td>'.encode($row['eLesson']['number'].'. '.$row['eLesson']['name']).'</td>';
							$h .= '<td>';
								foreach($row['events'] as $event) {
									$h .= '<div>'.$this->getEvent($progressUi, $event).'</div>';
								}
							$h .= '</td>';
							$h .= '<td class="text-end">'.self::getLength($row).'</td>';
						$h .= '</tr>';

					}

				$h .= '</tbody>';

			}

		$h .= '</table>';

		return $h;

	}

	/** « Samedi 12 septembre 2026 », le titre d'un jour du journal, dans le panneau et dans le relevé. */
	public static function getDay(string $day): string {
		return \util\DateUi::getDayName((int)date('N', strtotime($day))).' '.\util\DateUi::textual($day, \util\DateUi::DATE);
	}

	/**
	 * La durée d'une ligne : un constat ou un événement hors séance n'a pas de durée. Le relevé la
	 * reprend pour son annexe.
	 */
	public static function getLength(array $row): string {
		return self::getMeasured($row['seconds'], $row['minutes']);
	}

	/**
	 * Le seul formateur d'un temps mesuré, séance, leçon ou total, dans le panneau comme dans le relevé
	 * (`ReportUi`) : un tiret quand aucune séance n'y entre, « moins d'1 min » sous 60 s plutôt que
	 * « 0 min », qui se lirait comme une absence de lecture, et sinon les minutes arrondies par le Lib.
	 * Le seuil se lit sur les secondes : sur les minutes arrondies, 45 s feraient « 1 min » à un endroit
	 * et « moins d'1 min » à un autre. Seul « 1 min » est insécable : la colonne Lecture du panneau peut
	 * ainsi se replier sur deux lignes à 360 px, au lieu de réduire la colonne Statut au point de couper
	 * les badges en deux.
	 */
	public static function getMeasured(?int $seconds, ?int $minutes): string {

		if($seconds === NULL) {
			return CourseUi::getDuration(NULL);
		}

		if($seconds < 60) {
			return s("moins d'1&nbsp;min");
		}

		return CourseUi::getDuration($minutes);

	}

	/**
	 * Un événement de la séance, avec le badge et le score du suivi de l'équipe. Le constat nomme son
	 * auteur sans heure, c'est sa ligne qui la porte, et se tait sur un auteur dont le compte a été
	 * retiré depuis (`checkedBy` est nullable).
	 */
	private function getEvent(ProgressUi $progressUi, array $event): string {

		$time = \util\DateUi::numeric($event['at'], \util\DateUi::TIME_HOUR_MINUTE);

		return match($event['type']) {
			ReadingLib::EVENT_CHECKED => $event['eUser']->empty() ? s("Constatée") : s("Constatée par {value}", \user\UserUi::name($event['eUser'])),
			ProgressLib::TRACK_DECLARED => $progressUi->getTrackState(['state' => $event['type']]).' '.s("à {value}", $time),
			ProgressLib::TRACK_PASSED, ProgressLib::TRACK_FAILED => $progressUi->getTrackState(['state' => $event['type']]).' '.s("au quiz, {score}, à {time}", ['score' => $progressUi->getTrackScore($event), 'time' => $time]),
		};

	}

}
?>
