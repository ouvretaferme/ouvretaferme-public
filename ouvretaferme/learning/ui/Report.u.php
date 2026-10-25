<?php
namespace learning;

/**
 * Le relevé de suivi d'un stagiaire (lot L8d, planche L8 bloc 6 A), à la charte des PDF
 * (`main\PdfUi`) : ce qu'il a validé, la durée retenue, le temps mesuré, leçon par leçon, puis en
 * annexe le journal de ses lectures.
 *
 * Trois temps s'y lisent, chacun nommé d'après ce qu'il compte (porte L8, reprise 1) :
 * - la durée retenue, la durée estimée des leçons validées, plus les heures pointées aux dates (lot
 *   L8e-3), le total de l'attestation ;
 * - le temps mesuré, celui de toutes les lectures, validées ou non, le même que le panneau du
 *   journal : la figure, la ligne « Toutes les leçons » et la note le lisent toutes les trois ;
 * - la ligne « Leçons validées », qui somme les deux colonnes sur les seules leçons validées.
 *
 * Le relevé n'existe que pour une formation de groupe ou de réseau, les seules qui tiennent un
 * journal (`Course::acceptReading()`) : il est toujours au nom du groupe auteur.
 *
 * Le pied ne porte pas la page : elle se numérote sur chaque page par le gabarit de Puppeteer
 * (`PdfUi::getPagingFooter()`, porte L8 question 5). Un programme de 25 leçons passe sur deux pages,
 * et un numéro écrit dans le flux s'y répéterait.
 */
class ReportUi {

	public static function url(Course $eCourse, \user\User $eUser): string {
		return '/learning/progress:report?id='.$eCourse['id'].'&user='.$eUser['id'];
	}

	public static function urlMine(Course $eCourse): string {
		return '/learning/progress:reportMine?id='.$eCourse['id'];
	}

	public static function getFilename(Course $eCourse, \user\User $eUser): string {
		return s("releve-de-suivi-{course}-{user}", ['course' => $eCourse['id'], 'user' => $eUser['id']]).'.pdf';
	}

	/**
	 * Le stagiaire est passé par `ProgressLib::fillTrack()`, `fillDetail()`, `fillSummary()`,
	 * `DateEnrollmentLib::fillAttended()` et `ReadingLib::fillMeasured()`, le journal vient de `ReadingLib::getJournal()`, et la formation
	 * porte son nombre de leçons (`lessons`).
	 */
	public function get(Course $eCourse, \Collection $cChapter, \user\User $eUser, \structure\Structure $eStructureCommunity, array $journal, string $issuedAt): string {

		$eUser->expects(['track', 'eEnrollment', 'validated', 'minutesValidated', 'minutesAll', 'validatedAt', 'readings', 'measured', 'measuredSeconds', 'minutesMeasuredAll', 'minutesMeasuredValidated', 'secondsMeasuredAll', 'secondsMeasuredValidated', 'cDateEnrollmentAttended', 'minutesAttended', 'attendedModes', 'minutesRetained']);

		$issued = ['date' => \util\DateUi::numeric($issuedAt, \util\DateUi::DATE)];

		$footer = \main\PdfUi::getFooter(
			$eStructureCommunity,
			note: $eUser['cDateEnrollmentAttended']->empty()
				? s("Relevé établi le {date} à partir du journal de lecture de la formation.", $issued)
				: s("Relevé établi le {date} à partir du journal de lecture de la formation et des présences pointées.", $issued)
		);

		// Les marges de l'attestation, la même charte (planche L8 bloc 6). `pdf.js` ne réserve que le
		// bas de la page, pour le pied paginé : chaque document pose les siennes, et la règle `@page`
		// l'emporte sur Puppeteer (`preferCSSPageSize`).
		$h = \main\PdfUi::getPage('15mm 18mm');

		// L'annexe commence sur une page à elle, et seulement s'il y a un journal à y mettre : un
		// stagiaire qui n'a rien ouvert a un relevé d'une page.
		$h .= '<div'.($journal === [] ? '' : ' class="util-print-break-after"').'>';

			$h .= \main\PdfUi::getHeader();
			$h .= \main\PdfUi::getStructure($eStructureCommunity, s("Relevé de suivi"), $this->getMeta($eCourse, $eUser));

			$h .= \main\PdfUi::getFigures([
				[s("{validated} sur {lessons}", ['validated' => $eUser['validated'], 'lessons' => $eCourse['lessons']]), s("Leçons validées")],
				[CourseUi::getDuration($eUser['minutesRetained']), s("Durée retenue")],
				[($eUser['readings'] === 0) ? s("Aucune lecture") : ReadingUi::getMeasured($eUser['secondsMeasuredAll'], $eUser['minutesMeasuredAll']), s("Temps mesuré")],
			]);

			$h .= $this->getProgram($eCourse, $cChapter, $eUser);
			$h .= '<p class="pdf-note">'.$this->getNote($eCourse, $eUser).'</p>';

			$h .= $footer;

		$h .= '</div>';

		if($journal !== []) {
			$h .= '<h2>'.s("Journal des lectures").'</h2>';
			$h .= $this->getJournal($journal);
			$h .= $footer;
		}

		return $h;

	}

	/**
	 * La formation, puis le stagiaire et la structure au nom de laquelle il s'est inscrit, quand il
	 * s'est inscrit au nom d'une structure : « Réussir sa certification bio · Manon Petit, Ferme des
	 * Trois Chênes ». Du HTML, comme le veut `PdfUi::getStructure()`.
	 */
	protected function getMeta(Course $eCourse, \user\User $eUser): string {

		$eEnrollment = $eUser['eEnrollment'];

		$person = \user\UserUi::name($eUser);

		if(
			$eEnrollment->notEmpty() and
			$eEnrollment['structure']->notEmpty()
		) {
			$person = s("{user}, {structure}", ['user' => $person, 'structure' => encode($eEnrollment['structure']['name'])]);
		}

		return encode($eCourse['name']).' · '.$person;

	}

	/**
	 * Le programme leçon par leçon sous un titre par module, puis deux lignes de total : les leçons
	 * validées, et toutes les leçons. Un `<tbody>` par module, que l'impression garde d'un seul tenant :
	 * une leçon ne commence pas une page sans son module. L'alternance de `tr-even` repart à chaque
	 * module, `nth-child` comptant dans le `<tbody>`.
	 *
	 * Quand l'équipe a pointé sa présence à une date (lot L8e-3), un groupe de plus sous le nom des
	 * modes des dates, une ligne par date, puis deux totaux de plus : ces heures, et la durée retenue
	 * qui les ajoute aux leçons validées (`minutesRetained`). Une date n'a pas de temps mesuré.
	 */
	protected function getProgram(Course $eCourse, \Collection $cChapter, \user\User $eUser): string {

		$h = '<table class="tr-even">';

			$h .= '<thead>';
				$h .= '<tr>';
					$h .= '<th>'.s("Programme").'</th>';
					$h .= '<th>'.s("Validation").'</th>';
					$h .= '<th class="text-end">'.s("Validée le").'</th>';
					$h .= '<th class="text-end">'.s("Durée estimée").'</th>';
					$h .= '<th class="text-end">'.s("Temps mesuré").'</th>';
				$h .= '</tr>';
			$h .= '</thead>';

			foreach($cChapter as $eChapter) {

				$h .= '<tbody class="util-print-break-inside-avoid">';

					$h .= '<tr class="tr-title">';
						$h .= '<td colspan="5"><b>'.CourseUi::getChapterName($eCourse, $eChapter).'</b></td>';
					$h .= '</tr>';

					foreach($eChapter['cLesson'] as $eLesson) {

						$track = $eUser['track'][$eLesson['id']];
						$validatedAt = $eUser['validatedAt'][$eLesson['id']];

						$h .= '<tr class="pdf-tr-ml-1">';
							$h .= '<td>'.$eLesson['number'].'. '.encode($eLesson['name']).'</td>';
							$h .= '<td>'.$this->getValidation($track).'</td>';
							$h .= '<td class="text-end">'.($validatedAt === NULL ? '' : \util\DateUi::numeric($validatedAt, \util\DateUi::DATE)).'</td>';
							// Comme sur l'attestation : une leçon vide ne compte pas dans les durées estimées.
							$h .= '<td class="text-end">'.($eLesson['written'] ? CourseUi::getDuration($eLesson['minutes']) : '<span class="util-annotation">-</span>').'</td>';
							$h .= '<td class="text-end">'.ReadingUi::getMeasured($eUser['measuredSeconds'][$eLesson['id']], $eUser['measured'][$eLesson['id']]).'</td>';
						$h .= '</tr>';

					}

				$h .= '</tbody>';

			}

			$attended = $eUser['cDateEnrollmentAttended']->notEmpty();

			if($attended) {
				$h .= $this->getAttendedGroup($eUser['cDateEnrollmentAttended'], $eUser['attendedModes']);
			}

			$h .= '<tbody>';
				$h .= $this->getTotal(s("Leçons validées"), $eUser['minutesValidated'], ReadingUi::getMeasured($eUser['secondsMeasuredValidated'], $eUser['minutesMeasuredValidated']));
				$h .= $this->getTotal(s("Toutes les leçons"), $eUser['minutesAll'], ReadingUi::getMeasured($eUser['secondsMeasuredAll'], $eUser['minutesMeasuredAll']));
				if($attended) {
					$h .= $this->getTotal(AttestationUi::getModesName($eUser['attendedModes']), $eUser['minutesAttended'], CourseUi::getDuration(NULL));
					$h .= $this->getTotal(s("Durée retenue"), $eUser['minutesRetained'], '');
				}
			$h .= '</tbody>';

		$h .= '</table>';

		return $h;

	}

	/**
	 * Le groupe des dates pointées, aux colonnes du relevé : leurs jours et où se sont tenues leurs
	 * séances (`AttestationUi::getAttendedDate()`), le pointage et son jour, les heures retenues pour
	 * la date. Le même `<tbody>` d'un seul tenant qu'un module.
	 *
	 * Le relevé n'a pas de colonne du format : sous un titre de deux modes, « En visio » dit seul
	 * qu'une séance s'est tenue à distance. Sous un titre d'un seul mode, le titre le dit déjà, et la
	 * ligne ne porte que les lieux.
	 */
	protected function getAttendedGroup(\Collection $cDateEnrollment, array $modes): string {

		$modesWhere = (count($modes) > 1) ? [DateSequence::ONSITE, DateSequence::REMOTE] : [DateSequence::ONSITE];

		$h = '<tbody class="util-print-break-inside-avoid">';

			$h .= '<tr class="tr-title">';
				$h .= '<td colspan="5"><b>'.AttestationUi::getModesName($modes).'</b></td>';
			$h .= '</tr>';

			foreach($cDateEnrollment as $eDateEnrollment) {

				$h .= '<tr class="pdf-tr-ml-1">';
					$h .= '<td>'.AttestationUi::getAttendedDate($eDateEnrollment['date'], $modesWhere).'</td>';
					$h .= '<td>'.s("Présente, pointée").'</td>';
					$h .= '<td class="text-end">'.\util\DateUi::numeric($eDateEnrollment['attendedAt'], \util\DateUi::DATE).'</td>';
					$h .= '<td class="text-end">'.CourseUi::getDuration($eDateEnrollment['minutesAttended']).'</td>';
					$h .= '<td class="text-end">'.CourseUi::getDuration(NULL).'</td>';
				$h .= '</tr>';

			}

		$h .= '</tbody>';

		return $h;

	}

	protected function getTotal(string $label, int $minutesEstimated, string $measured): string {

		$h = '<tr class="tr-title">';
			$h .= '<td colspan="3"><b>'.$label.'</b></td>';
			$h .= '<td class="text-end"><b>'.CourseUi::getDuration($minutesEstimated).'</b></td>';
			$h .= '<td class="text-end"><b>'.$measured.'</b></td>';
		$h .= '</tr>';

		return $h;

	}

	/**
	 * Comment la leçon a été validée, en toutes lettres : « Déclarée », « Réussie au quiz, 4 sur 5 »,
	 * « Quiz à refaire, 2 sur 5 », suivis de « constatée » quand l'équipe l'a constatée. Rien pour
	 * une leçon que le stagiaire n'a ni déclarée ni tentée et que l'équipe n'a pas constatée.
	 */
	protected function getValidation(array $track): string {

		$score = fn() => new ProgressUi()->getTrackScore($track);

		$state = match($track['state']) {
			ProgressLib::TRACK_NONE => NULL,
			ProgressLib::TRACK_DECLARED => s("Déclarée"),
			ProgressLib::TRACK_PASSED => s("Réussie au quiz, {value}", $score()),
			ProgressLib::TRACK_FAILED => s("Quiz à refaire, {value}", $score()),
		};

		if($track['checked'] === FALSE) {
			return $state ?? '';
		}

		return ($state === NULL) ? s("Constatée") : s("{state}, constatée", ['state' => $state]);

	}

	/**
	 * Ce que chaque temps compte, et qui déclare ou constate une leçon. Les heures pointées le sont
	 * toujours par l'équipe d'un réseau, seul à donner des dates à ses formations.
	 */
	protected function getNote(Course $eCourse, \user\User $eUser): string {

		$text = $eUser['cDateEnrollmentAttended']->empty()
			? s("La durée retenue est la durée estimée des leçons validées. Le temps mesuré compte les minutes où une leçon était ouverte à l'écran, toutes leçons confondues, pour information.")
			: s("La durée retenue est la durée estimée des leçons validées, plus les heures de {modes} pointées par l'équipe du réseau. Le temps mesuré compte les minutes où une leçon était ouverte à l'écran, toutes leçons confondues, pour information.", ['modes' => mb_strtolower(AttestationUi::getModesName($eUser['attendedModes']))]);

		return $text.' '.($eCourse['community']->isNetwork()
			? s("Une leçon déclarée l'est par le stagiaire, une leçon constatée l'est par l'équipe du réseau.")
			: s("Une leçon déclarée l'est par le stagiaire, une leçon constatée l'est par l'équipe du groupe."));

	}

	/**
	 * L'annexe : le journal du panneau, un titre par jour, une ligne par séance ou par événement
	 * qu'aucune séance n'encadre, et ce qui s'y est passé en toutes lettres à la place des badges,
	 * un événement par ligne comme dans le panneau : chacun porte déjà sa virgule (« Quiz à
	 * refaire, 2 sur 5 »), et joints par une autre ils se liraient mal et élargiraient la colonne
	 * au détriment de celle des leçons. Un `<tbody>` par jour, que l'impression garde d'un seul
	 * tenant : devant un auditeur, une séance en haut de page sans son jour ne dit plus quand elle a
	 * eu lieu.
	 */
	protected function getJournal(array $journal): string {

		$h = '<table class="tr-even">';

			$h .= '<thead>';
				$h .= '<tr>';
					$h .= '<th>'.s("Heure").'</th>';
					$h .= '<th>'.s("Leçon").'</th>';
					$h .= '<th>'.s("Statut").'</th>';
					$h .= '<th class="text-end">'.s("Lecture").'</th>';
				$h .= '</tr>';
			$h .= '</thead>';

			foreach($journal as $day => $rows) {

				$h .= '<tbody class="util-print-break-inside-avoid">';

					$h .= '<tr class="tr-title">';
						$h .= '<td colspan="4"><b>'.ReadingUi::getDay($day).'</b></td>';
					$h .= '</tr>';

					foreach($rows as $row) {

						$h .= '<tr>';
							$h .= '<td>'.\util\DateUi::numeric($row['at'], \util\DateUi::TIME_HOUR_MINUTE).'</td>';
							$h .= '<td>'.encode($row['eLesson']['number'].'. '.$row['eLesson']['name']).'</td>';
							$h .= '<td>';
								foreach($row['events'] as $event) {
									$h .= '<div>'.$this->getEvent($event).'</div>';
								}
							$h .= '</td>';
							$h .= '<td class="text-end">'.ReadingUi::getLength($row).'</td>';
						$h .= '</tr>';

					}

				$h .= '</tbody>';

			}

		$h .= '</table>';

		return $h;

	}

	/**
	 * Un événement de la séance, avec son heure. Le constat nomme son auteur, sans heure : sa ligne
	 * la porte.
	 */
	protected function getEvent(array $event): string {

		$time = \util\DateUi::numeric($event['at'], \util\DateUi::TIME_HOUR_MINUTE);

		return match($event['type']) {
			ReadingLib::EVENT_CHECKED => $event['eUser']->empty() ? s("Constatée") : s("Constatée par {value}", \user\UserUi::name($event['eUser'])),
			ProgressLib::TRACK_DECLARED => s("Déclarée à {value}", $time),
			ProgressLib::TRACK_PASSED => s("Réussie au quiz, {score} à {time}", ['score' => new ProgressUi()->getTrackScore($event), 'time' => $time]),
			ProgressLib::TRACK_FAILED => s("Quiz à refaire, {score} à {time}", ['score' => new ProgressUi()->getTrackScore($event), 'time' => $time]),
		};

	}

}
?>
