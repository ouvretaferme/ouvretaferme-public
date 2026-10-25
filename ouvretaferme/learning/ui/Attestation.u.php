<?php
namespace learning;

/**
 * L'attestation de suivi d'une formation (F69, scène L4g), à la charte des PDF (`main\PdfUi`).
 *
 * Deux formes, que choisit `CourseLib::getIssuer()` :
 * - A, au nom du groupe auteur, quand un partage, une inscription confirmée à une date ou
 *   l'inscription à la formation en ligne d'un réseau le lie à la personne : le bloc de la
 *   structure en tête, son nom en pied ;
 * - B, délivrée par Ouvretaferme seule : le titre du document à la place du bloc, et un pied sans
 *   structure. C'est la forme d'une formation de l'équipe, et jamais celle d'une formation en
 *   ligne de réseau.
 *
 * Le pied ne porte pas la page : elle se numérote sur chaque page par le gabarit de Puppeteer
 * (`PdfUi::getPagingFooter()`, porte L8 question 5), et un numéro écrit dans le flux se répéterait.
 */
class AttestationUi {

	public static function getFilename(Course $eCourse): string {
		return s("attestation-de-suivi-{id}", ['id' => $eCourse['id']]).'.pdf';
	}

	public function get(Course $eCourse, \Collection $cChapter, \user\User $eUser, \structure\Structure $eStructure, \structure\Structure $eStructureIssuer, string $issuedAt): string {

		$eUser->expects(['track', 'detail', 'validated', 'minutesValidated', 'firstDoneAt', 'lastDoneAt', 'checked', 'cDateEnrollmentAttended', 'minutesAttended', 'attendedModes', 'minutesRetained', 'followedFrom', 'followedTo']);

		$title = s("Attestation de suivi");
		$lede = $this->getLede($eCourse, $eUser, $eStructure, $eStructureIssuer);

		// Les marges de la planche L4g. `pdf.js` ne réserve que le bas de la page, pour le pied
		// paginé : chaque document pose les siennes, et la règle `@page` l'emporte sur Puppeteer
		// (`preferCSSPageSize`). Le bas de 15 mm garde la place du numéro de page.
		$h = \main\PdfUi::getPage('15mm 18mm');

		$h .= \main\PdfUi::getHeader();

		if($eStructureIssuer->empty()) {
			$h .= \main\PdfUi::getTitle($title, $lede);
		} else {
			$h .= \main\PdfUi::getStructure($eStructureIssuer, $title, s("Délivrée le {date}", ['date' => \util\DateUi::textual($issuedAt, \util\DateUi::DATE)]));
			$h .= '<p class="pdf-lede">'.$lede.'</p>';
		}

		$h .= \main\PdfUi::getFigures([
			[$eUser['validated'], s("Leçons suivies")],
			[CourseUi::getDuration($eUser['minutesValidated']), s("De leçons")],
			[\util\DateUi::numeric($eUser['lastDoneAt'], \util\DateUi::DATE), s("Terminée le")],
		]);

		$h .= $this->getProgram($eCourse, $cChapter, $eUser);
		$h .= '<p class="pdf-note">'.$this->getNote($eCourse, $eUser).'</p>';

		$h .= \main\PdfUi::getFooter(
			$eStructureIssuer->empty() ? NULL : $eStructureIssuer,
			note: encode(\Lime::getDomain().CourseUi::url($eCourse))
		);

		return $h;

	}

	/**
	 * Qui, de quelle structure, quelle formation et quand. La ferme n'est nommée que si le compte
	 * suit la formation en son nom. Elle s'appelle ferme quand le groupe auteur délivre l'attestation
	 * (forme A), c'est-à-dire quand un partage ou une date la lie à lui : l'arbitrage du 26/09
	 * (`WordingSentinel`) réserve le mot à la ferme membre d'un réseau ou d'un groupe. Quand
	 * Ouvretaferme délivre seule (forme B), c'est une structure.
	 */
	protected function getLede(Course $eCourse, \user\User $eUser, \structure\Structure $eStructure, \structure\Structure $eStructureIssuer): string {

		$values = [
			'user' => '<b>'.\user\UserUi::name($eUser).'</b>',
			'course' => '<b>'.encode($eCourse['name']).'</b>',
			'period' => $this->getPeriod($eUser['followedFrom'], $eUser['followedTo']),
		];

		if($eStructure->notEmpty()) {
			$values['structure'] = encode($eStructure['name']);
			$values['user'] = $eStructureIssuer->empty()
				? s("{user}, de la structure {structure},", $values)
				: s("{user}, de la ferme {structure},", $values);
		}

		if($eCourse->isTeam()) {
			return s("{user} a suivi en entier la formation {course} de Ouvretaferme, {period}.", $values);
		}

		$values['community'] = encode($eCourse['community']['name']);

		return $eCourse['community']->isNetwork()
			? s("{user} a suivi en entier la formation {course} écrite par le réseau {community}, {period}.", $values)
			: s("{user} a suivi en entier la formation {course} écrite par le groupe {community}, {period}.", $values);

	}

	/**
	 * « le 18 septembre 2026 », ou « entre le 12 et le 18 septembre 2026 » : le mois et l'année ne
	 * se répètent que lorsqu'ils changent.
	 */
	protected function getPeriod(string $from, string $to): string {

		$first = substr($from, 0, 10);
		$last = substr($to, 0, 10);

		if($first === $last) {
			return s("le {date}", ['date' => \util\DateUi::textual($last, \util\DateUi::DATE)]);
		}

		if(substr($first, 0, 7) === substr($last, 0, 7)) {
			$from = (int)substr($first, 8, 2);
		} else if(substr($first, 0, 4) === substr($last, 0, 4)) {
			$from = \util\DateUi::textual($first, \util\DateUi::DAY_MONTH);
		} else {
			$from = \util\DateUi::textual($first, \util\DateUi::DATE);
		}

		return s("entre le {from} et le {to}", [
			'from' => $from,
			'to' => \util\DateUi::textual($last, \util\DateUi::DATE),
		]);

	}

	/**
	 * Le programme leçon par leçon, un titre par module (`pdf-tr-title`), la date à laquelle le stagiaire
	 * a suivi chaque leçon. Une leçon seulement constatée par l'équipe n'a pas de date à elle.
	 *
	 * Quand l'équipe a pointé sa présence à une date (lot L8e-3), le groupe des dates vient après les
	 * modules, puis trois totaux : les leçons en ligne, les heures pointées sous le nom de leurs
	 * modes, et le total (`minutesRetained`, posé par `DateEnrollmentLib::fillAttended()`). Les
	 * leçons se disent « en ligne » et non « à distance », qu'une ligne « Visio » rendrait ambigu.
	 */
	protected function getProgram(Course $eCourse, \Collection $cChapter, \user\User $eUser): string {

		$h = '<table class="pdf-table-bordered">';

			$h .= '<thead>';
				$h .= '<tr>';
					$h .= '<th>'.s("Programme").'</th>';
					$h .= '<th>'.s("Format").'</th>';
					$h .= '<th class="text-end">'.s("Durée").'</th>';
					$h .= '<th class="text-end">'.s("Suivie le").'</th>';
				$h .= '</tr>';
			$h .= '</thead>';

			$h .= '<tbody>';

				foreach($cChapter as $eChapter) {

					$h .= '<tr class="pdf-tr-title">';
						$h .= '<td colspan="4">'.CourseUi::getChapterName($eCourse, $eChapter).'</td>';
					$h .= '</tr>';

					foreach($eChapter['cLesson'] as $eLesson) {

						$doneAt = $eUser['detail'][$eLesson['id']]['doneAt'];

						$h .= '<tr class="pdf-tr-ml-1">';
							$h .= '<td>'.$eLesson['number'].'. '.encode($eLesson['name']).'</td>';
							if($eLesson['format'] === Lesson::EMPTY) {
								$h .= '<td><span class="util-annotation">-</span></td>';
							} else {
								$h .= '<td>'.$this->getFormat($eLesson).'</td>';
							}
							// Une leçon vide ne compte pas dans la durée suivie : sa ligne la tait, pour que la
							// somme des lignes fasse le total.
							if($eLesson['written']) {
								$h .= '<td class="text-end">'.CourseUi::getDuration($eLesson['minutes']).'</td>';
							} else {
								$h .= '<td class="text-end"><span class="util-annotation">-</span></td>';
							}
							$h .= '<td class="text-end">'.($doneAt === NULL ? '<span class="util-annotation">-</span>' : \util\DateUi::numeric($doneAt, \util\DateUi::DATE)).'</td>';
						$h .= '</tr>';

					}

				}

				if($eUser['cDateEnrollmentAttended']->notEmpty()) {
					$h .= $this->getAttendedGroup($eUser['cDateEnrollmentAttended'], $eUser['attendedModes']);
				}

			$h .= '</tbody>';

			if($eUser['cDateEnrollmentAttended']->notEmpty()) {

				$h .= '<tbody>';
					$h .= $this->getTotal(s("Leçons en ligne"), $eUser['minutesValidated']);
					$h .= $this->getTotal(self::getModesName($eUser['attendedModes']), $eUser['minutesAttended']);
					$h .= $this->getTotal(s("Total"), $eUser['minutesRetained']);
				$h .= '</tbody>';

			}

		$h .= '</table>';

		return $h;

	}

	/**
	 * Le groupe des dates pointées, aux colonnes du programme de l'attestation : un titre aux modes des
	 * dates, puis une ligne par date, ses jours et ses lieux, son mode, ses heures et son dernier jour.
	 * Le mode se dit une fois, dans la colonne du format : l'annotation des jours ne porte que les lieux.
	 * Une date mixte compte en entier. Chaque place porte `minutesAttended` et `attendedModes`
	 * (`DateEnrollmentLib::fillAttendance()`).
	 *
	 * @param string[] $modes les modes réunis des dates, que le titre nomme
	 */
	protected function getAttendedGroup(\Collection $cDateEnrollment, array $modes): string {

		$h = '<tr class="pdf-tr-title">';
			$h .= '<td colspan="4">'.self::getModesName($modes).'</td>';
		$h .= '</tr>';

		foreach($cDateEnrollment as $eDateEnrollment) {

			$eDate = $eDateEnrollment['date'];

			$h .= '<tr class="pdf-tr-ml-1">';
				$h .= '<td>'.self::getAttendedDate($eDate, [DateSequence::ONSITE]).'</td>';
				$h .= '<td>'.self::getModesName($eDateEnrollment['attendedModes']).'</td>';
				$h .= '<td class="text-end">'.CourseUi::getDuration($eDateEnrollment['minutesAttended']).'</td>';
				$h .= '<td class="text-end">'.\util\DateUi::numeric($eDate['endAt'], \util\DateUi::DATE).'</td>';
			$h .= '</tr>';

		}

		return $h;

	}

	protected function getTotal(string $label, int $minutes): string {

		$h = '<tr class="pdf-tr-title">';
			$h .= '<td colspan="2">'.$label.'</td>';
			$h .= '<td class="text-end">'.CourseUi::getDuration($minutes).'</td>';
			$h .= '<td></td>';
		$h .= '</tr>';

		return $h;

	}

	/**
	 * Le nom de modes de dates pointées, dans le titre de leur groupe et de leur total au relevé
	 * comme à l'attestation : « Présentiel », « Visio », ou « Présentiel et visio », les mots de
	 * `DateUi::getWhere()`.
	 *
	 * @param string[] $modes dans l'ordre de `DateSequence::mode`, sans doublon
	 */
	public static function getModesName(array $modes): string {

		if(count($modes) > 1) {
			return s("Présentiel et visio");
		}

		return DateSequenceUi::p('mode')->values[$modes[0]];

	}

	/**
	 * Les jours d'une date pointée, puis en annotation où se sont tenues ses séances des modes
	 * demandés, chaque endroit une fois sur sa ligne : le lieu d'une séance en présentiel, « En
	 * visio » (`DateUi::getSequenceWhere()`). L'attestation ne demande que le présentiel, sa colonne
	 * du format disant déjà le mode ; le relevé, qui n'en a pas, demande les deux. Une séance en
	 * présentiel sans lieu n'écrit rien. `Date.place` ne se lit plus.
	 *
	 * @param string[] $modes les modes de `DateSequence::mode` dont la ligne dit l'endroit
	 */
	public static function getAttendedDate(Date $eDate, array $modes): string {

		$cDateSequence = $eDate['cDateSequence']->find(fn(DateSequence $eDateSequence) => in_array($eDateSequence['mode'], $modes, TRUE));

		$wheres = array_unique(array_filter($cDateSequence->makeArray(DateUi::getSequenceWhere(...))));

		$h = DateUi::getDays($eDate);

		foreach($wheres as $where) {
			$h .= '<div class="util-annotation">'.$where.'</div>';
		}

		return $h;

	}

	protected function getFormat(Lesson $eLesson): string {

		$format = LessonUi::p('format')->values[$eLesson['format']];

		if($eLesson['validation'] === Lesson::QUIZ) {
			return s("{format}&nbsp;et quiz", ['format' => $format]);
		}

		return $format;

	}

	/**
	 * Ce que l'attestation constate, et ce qu'elle ne vaut pas. Le constat de l'équipe se dit quand
	 * il existe : une formation de l'équipe n'a pas de suivi, donc jamais de constat. Les heures
	 * pointées aussi : seul un réseau donne des dates à ses formations (`Date::acceptWrite()`), c'est
	 * donc toujours son équipe qui a pointé.
	 */
	protected function getNote(Course $eCourse, \user\User $eUser): string {

		$values = ['user' => \user\UserUi::name($eUser)];

		if($eUser['cDateEnrollmentAttended']->notEmpty()) {

			$values['modes'] = mb_strtolower(self::getModesName($eUser['attendedModes']));

			$text = ($eUser['checked'] === 0)
				? s("Cette attestation constate les leçons que {user} a suivies depuis son compte sur Ouvretaferme, et les heures de {modes} pointées par l'équipe du réseau.", $values)
				: p("Cette attestation constate les leçons que {user} a suivies depuis son compte sur Ouvretaferme, dont {value} constatée par l'équipe du réseau, et les heures de {modes} qu'elle a pointées.", "Cette attestation constate les leçons que {user} a suivies depuis son compte sur Ouvretaferme, dont {value} constatées par l'équipe du réseau, et les heures de {modes} qu'elle a pointées.", $eUser['checked'], $values);

		} else if($eUser['checked'] === 0) {
			$text = s("Cette attestation constate les leçons que {user} a suivies depuis son compte sur Ouvretaferme.", $values);
		} else if($eCourse['community']->isNetwork()) {
			$text = p("Cette attestation constate les leçons que {user} a suivies depuis son compte sur Ouvretaferme, dont {value} constatée par l'équipe du réseau.", "Cette attestation constate les leçons que {user} a suivies depuis son compte sur Ouvretaferme, dont {value} constatées par l'équipe du réseau.", $eUser['checked'], $values);
		} else {
			$text = p("Cette attestation constate les leçons que {user} a suivies depuis son compte sur Ouvretaferme, dont {value} constatée par l'équipe du groupe.", "Cette attestation constate les leçons que {user} a suivies depuis son compte sur Ouvretaferme, dont {value} constatées par l'équipe du groupe.", $eUser['checked'], $values);
		}

		return $text.' '.s("Elle ne vaut pas certification.");

	}

	public static function getPresenceFilename(DateEnrollment $eDateEnrollment): string {
		return s("attestation-de-presence-{id}", ['id' => $eDateEnrollment['id']]).'.pdf';
	}

	/**
	 * L'attestation de présence d'une place pointée à une date d'une formation sans leçon (lot L8f),
	 * toujours au nom du réseau : seul un réseau donne des dates à ses formations. Elle porte les
	 * quatre mentions de l'article L6353-1 du code du travail : les objectifs, la nature de l'action,
	 * sa durée (les heures pointées) et l'évaluation des acquis, qu'une formation sans leçon ni quiz
	 * n'a pas. Les heures sont celles de `DateEnrollment::getAttendedMinutes()`, rapportées à la durée
	 * de la date quand la présence est partielle.
	 */
	public function getPresence(DateEnrollment $eDateEnrollment, \structure\Structure $eStructureCommunity, string $issuedAt): string {

		$eDateEnrollment->expects(['attendedAt', 'date' => ['cDateSequence', 'course' => ['name', 'objectives']]]);

		$eDate = $eDateEnrollment['date'];
		$eCourse = $eDate['course'];

		$attended = $eDateEnrollment->getAttendedMinutes();
		$minutes = $eDate->getMinutes();
		$partial = ($attended < $minutes);

		// Les marges de la planche L4g, comme l'attestation de suivi (`get()`).
		$h = \main\PdfUi::getPage('15mm 18mm');

		$h .= \main\PdfUi::getHeader();
		$h .= \main\PdfUi::getStructure($eStructureCommunity, s("Attestation de présence"), s("Délivrée le {date}", ['date' => \util\DateUi::textual($issuedAt, \util\DateUi::DATE)]));
		$h .= '<p class="pdf-lede">'.$this->getPresenceLede($eDateEnrollment, $eStructureCommunity).'</p>';

		$days = $eDate->getDays();
		$place = $eDate->getPlace();

		// Le libellé d'un chiffre est du texte brut (`PdfUi::getFigures()`) : la durée y entre sans
		// ses entités.
		$h .= \main\PdfUi::getFigures([
			[CourseUi::getDuration($attended), $partial ? s("De présence, sur {value}", html_entity_decode(CourseUi::getDuration($minutes))) : s("De présence")],
			[$this->getPresenceDays($days), (count($days) > 1) ? s("Dates") : s("Date")],
			($eDate->getModes() === [DateSequence::ONSITE] and $place !== NULL)
				? [encode($place), s("Lieu")]
				: [self::getModesName($eDate->getModes()), s("Modalité")],
		]);

		$h .= $this->getSequences($eDate);
		$h .= $this->getMentions($eCourse);

		$note = s("La présence a été constatée par l'équipe du réseau {community} le {date}.", [
			'community' => encode($eStructureCommunity['name']),
			'date' => \util\DateUi::numeric($eDateEnrollment['attendedAt'], \util\DateUi::DATE),
		]);

		if($partial) {
			$note .= ' '.s("Elle porte sur {attended} des {minutes} de la formation.", ['attended' => CourseUi::getDuration($attended), 'minutes' => CourseUi::getDuration($minutes)]);
		}

		$h .= '<p class="pdf-note">'.$note.' '.s("Cette attestation ne vaut pas certification.").'</p>';

		$h .= \main\PdfUi::getFooter($eStructureCommunity, note: encode(\Lime::getDomain().CourseUi::url($eCourse)));

		return $h;

	}

	/**
	 * Qui, de quelle ferme, à quelle formation et quel jour. La ferme est celle choisie par un membre
	 * ou saisie sur la page publique, et n'est pas nommée à titre personnel. Elle s'appelle ferme : le
	 * réseau délivre, et l'arbitrage du 26/09 (`WordingSentinel`) réserve le mot à la ferme membre
	 * d'un réseau ou d'un groupe.
	 */
	protected function getPresenceLede(DateEnrollment $eDateEnrollment, \structure\Structure $eStructureCommunity): string {

		$eDate = $eDateEnrollment['date'];

		$days = mb_strtolower(DateUi::getDays($eDate));

		$values = [
			'user' => '<b>'.$eDateEnrollment->getName().'</b>',
			'course' => '<b>'.encode($eDate['course']['name']).'</b>',
			'community' => encode($eStructureCommunity['name']),
			'day' => ($eDate->countDays() === 1) ? s("le {value}", $days) : $days,
		];

		if($eDateEnrollment['structure']->notEmpty() or $eDateEnrollment['structureName'] !== NULL) {
			$values['structure'] = DateEnrollmentUi::getStructure($eDateEnrollment);
			$values['user'] = s("{user}, de la ferme {structure},", $values);
		}

		return s("{user} a assisté à la formation {course} organisée par le réseau {community}, {day}.", $values);

	}

	/**
	 * « 11/09/2026 », « 16 et 17/10/2026 », le mois et l'année dits une fois quand ils sont communs,
	 * chaque jour en entier sinon.
	 *
	 * @param string[] $days les jours des séances, `AAAA-MM-JJ`, dans l'ordre
	 */
	protected function getPresenceDays(array $days): string {

		$last = array_pop($days);

		if($days === []) {
			return \util\DateUi::numeric($last, \util\DateUi::DATE);
		}

		$sameMonth = array_filter($days, fn(string $day) => substr($day, 0, 7) !== substr($last, 0, 7)) === [];

		$others = array_map(
			fn(string $day) => $sameMonth ? substr($day, 8, 2) : \util\DateUi::numeric($day, \util\DateUi::DATE),
			$days
		);

		return s("{others} et {last}", ['others' => implode(', ', $others), 'last' => \util\DateUi::numeric($last, \util\DateUi::DATE)]);

	}

	/**
	 * Les séances de la date, une ligne chacune : le jour, les horaires, la modalité et le lieu, le
	 * tiret muet d'une séance en visio ou sans lieu.
	 */
	protected function getSequences(Date $eDate): string {

		$h = '<table class="pdf-table-bordered">';

			$h .= '<thead>';
				$h .= '<tr>';
					$h .= '<th>'.s("Séance").'</th>';
					$h .= '<th>'.s("Horaires").'</th>';
					$h .= '<th>'.s("Modalité").'</th>';
					$h .= '<th>'.s("Lieu").'</th>';
				$h .= '</tr>';
			$h .= '</thead>';

			$h .= '<tbody>';

				foreach($eDate['cDateSequence'] as $eDateSequence) {

					$place = ($eDateSequence['mode'] === DateSequence::ONSITE) ? $eDateSequence['place'] : NULL;

					$h .= '<tr>';
						$h .= '<td>'.DateUi::getDayName($eDateSequence->getDay()).' '.\util\DateUi::textual($eDateSequence->getDay(), \util\DateUi::DATE).'</td>';
						$h .= '<td>'.DateUi::getHours($eDateSequence).'</td>';
						$h .= '<td>'.DateSequenceUi::p('mode')->values[$eDateSequence['mode']].'</td>';
						$h .= '<td>'.(($place === NULL) ? '<span class="util-annotation">-</span>' : encode($place)).'</td>';
					$h .= '</tr>';

				}

			$h .= '</tbody>';

		$h .= '</table>';

		return $h;

	}

	/**
	 * Les mentions de l'article L6353-1 que les chiffres et les séances ne portent pas : les objectifs
	 * de la formation, sans lesquels l'attestation ne se délivre pas
	 * (`DateEnrollment::acceptPresenceAttestation()`), la nature de l'action et l'évaluation des
	 * acquis. Une formation sans leçon n'a pas de quiz, donc aucune évaluation n'était prévue.
	 */
	protected function getMentions(Course $eCourse): string {

		$sections = [];
		$sections[s("Objectifs de la formation")] = new \editor\EditorUi()->value($eCourse['objectives']);
		$sections[s("Nature de l'action")] = s("Action de formation, au sens de l'article L6313-1 du code du travail.");
		$sections[s("Évaluation des acquis")] = s("Aucune évaluation des acquis n'était prévue.");

		$h = '<table class="pdf-table-bordered">';
			$h .= '<tbody>';

				foreach($sections as $title => $content) {
					$h .= '<tr class="pdf-tr-title"><td>'.$title.'</td></tr>';
					$h .= '<tr class="pdf-tr-ml-1"><td>'.$content.'</td></tr>';
				}

			$h .= '</tbody>';
		$h .= '</table>';

		return $h;

	}

}
?>
