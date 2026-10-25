<?php
namespace learning;

/**
 * La feuille d'émargement des séances en présentiel d'une date (lot L8e-1, planche L8e bloc A ;
 * lot date-mixte), à la charte des PDF
 * (`main\PdfUi`), au nom du réseau : les personnes confirmées, rangées par nom, une case par
 * demi-journée, puis trois lignes vides pour qui vient sans être inscrit et les encadrants. Chacun
 * des trois groupes porte un titre de même forme, que la table dessine autrement qu'un nom (retour de
 * recette du 01/10 : « segmentation Stagiaire Sur place Encadrants pas claire »).
 *
 * La feuille se signe à la main et revient déposée par le réseau (lot L8e-2) : elle ne porte ni
 * adresse ni téléphone, seulement ce qui identifie la personne qui signe.
 *
 * Le pied ne porte pas la page : elle se numérote sur chaque page par le gabarit de Puppeteer
 * (`PdfUi::getPagingFooter()`), comme le relevé.
 */
class SheetUi {

	public static function getFilename(Date $eDate): string {
		return s("feuille-emargement-{value}", $eDate['id']).'.pdf';
	}

	/**
	 * `$halfDays` vient de `DateLib::getHalfDays()`, les personnes de
	 * `DateEnrollmentLib::getConfirmedByDate()`, déjà rangées.
	 *
	 * @param array<string, string[]> $halfDays
	 */
	public function get(Date $eDate, \Collection $cDateEnrollment, \Collection $cStaff, \structure\Structure $eStructureCommunity, array $halfDays, string $issuedAt): string {

		$eCourse = $eDate['course'];

		$columns = array_sum(array_map('count', $halfDays));

		// Les marges de l'attestation et du relevé : `pdf.js` ne réserve que le bas de la page, pour
		// le pied paginé, et la règle `@page` l'emporte sur Puppeteer (`preferCSSPageSize`).
		$h = \main\PdfUi::getPage('15mm 18mm');

		$h .= \main\PdfUi::getHeader();
		$h .= \main\PdfUi::getStructure($eStructureCommunity, s("Feuille d'émargement"), encode($eCourse['name']));

		// Seules les séances en présentiel se signent (lot date-mixte) : la feuille ne dit qu'elles,
		// ses colonnes venant de `DateLib::getHalfDays()`, qui ne compte qu'elles aussi.
		$days = [];
		$hours = [];
		$places = [];

		foreach($eDate['cDateSequence'] as $eDateSequence) {

			if($eDateSequence['mode'] !== DateSequence::ONSITE) {
				continue;
			}

			$days[$eDateSequence->getDay()] = TRUE;
			$hours[DateUi::getHours($eDateSequence)] = TRUE;
			// Une séance reprise d'une date d'avant le lieu obligatoire peut ne pas en porter.
			if($eDateSequence['place'] !== NULL) {
				$places[encode($eDateSequence['place'])] = TRUE;
			}

		}

		$days = array_keys($days);

		$figures = [
			[$this->getDays($days), (count($days) > 1) ? s("Dates") : s("Date")],
			[implode(s(", puis "), array_keys($hours)), s("Horaires")],
		];

		if($places !== []) {
			$figures[] = [implode(s(", puis "), array_keys($places)), (count($places) > 1) ? s("Lieux") : s("Lieu")];
		}

		$h .= \main\PdfUi::getFigures($figures);

		if($cStaff->notEmpty()) {
			$h .= '<p class="pdf-lede">'.$this->getStaffLede($cStaff).'</p>';
		}

		$h .= '<table class="pdf-sheet">';

			$h .= '<colgroup>';
				$h .= '<col>';
				$h .= str_repeat('<col class="pdf-sheet-sign">', $columns);
			$h .= '</colgroup>';

			$h .= $this->getHead($halfDays, $columns);

			// Le premier groupe n'est pas gardé d'un seul tenant : une liste de trente stagiaires ne tient
			// pas sur une page. Son titre suit l'en-tête du tableau, il ne peut donc pas finir seul en bas.
			$h .= '<tbody>';
				$h .= $this->getGroup(s("Stagiaires"), $columns);
				foreach($cDateEnrollment as $eDateEnrollment) {
					$h .= $this->getLine('<b>'.$eDateEnrollment->getName().'</b><div class="util-annotation">'.DateEnrollmentUi::getStructure($eDateEnrollment).'</div>', $columns);
				}
			$h .= '</tbody>';

			// Un groupe que l'impression garde d'un seul tenant : son titre ne finit pas seul en bas de page.
			$h .= '<tbody class="util-print-break-inside-avoid">';
				$h .= $this->getGroup(s("Non inscrits"), $columns);
				$h .= str_repeat($this->getLine('', $columns), 3);
			$h .= '</tbody>';

			if($cStaff->notEmpty()) {

				$h .= '<tbody class="util-print-break-inside-avoid">';
					$h .= $this->getGroup(s("Encadrants"), $columns);
					foreach($cStaff as $eStaff) {
						$h .= $this->getLine('<b>'.encode($eStaff['supervisor']['name']).'</b><div class="util-annotation">'.encode(mb_ucfirst($eStaff['role'])).'</div>', $columns);
					}
				$h .= '</tbody>';

			}

		$h .= '</table>';

		$h .= \main\PdfUi::getFooter(
			$eStructureCommunity,
			note: s("Feuille éditée le {date}.", ['date' => \util\DateUi::numeric($issuedAt, \util\DateUi::DATE)])
		);

		return $h;

	}

	/**
	 * Un jour par colonne groupée, puis ses demi-journées. Une date d'une seule demi-journée tient sur
	 * une ligne d'en-tête, le jour au-dessus de la demi-journée dans la même case.
	 */
	protected function getHead(array $halfDays, int $columns): string {

		$h = '<thead>';

			if($columns === 1) {

				$day = array_key_first($halfDays);

				$h .= '<tr>';
					$h .= '<th>'.s("Nom").'</th>';
					$h .= '<th class="pdf-sheet-sign">'.$this->getDay($day).'<br>'.$this->getHalf($halfDays[$day][0]).'</th>';
				$h .= '</tr>';

			} else {

				$h .= '<tr>';
					$h .= '<th rowspan="2">'.s("Nom").'</th>';
					foreach($halfDays as $day => $halves) {
						$h .= '<th colspan="'.count($halves).'" class="pdf-sheet-sign">'.$this->getDay($day).'</th>';
					}
				$h .= '</tr>';

				$h .= '<tr>';
					foreach($halfDays as $halves) {
						foreach($halves as $half) {
							$h .= '<th class="pdf-sheet-sign">'.$this->getHalf($half).'</th>';
						}
					}
				$h .= '</tr>';

			}

		$h .= '</thead>';

		return $h;

	}

	protected function getLine(string $name, int $columns): string {
		return '<tr><td>'.$name.'</td>'.str_repeat('<td class="pdf-sheet-sign"></td>', $columns).'</tr>';
	}

	/**
	 * Le titre d'un groupe, sur toute la largeur : la colonne des noms est nommée par l'en-tête, le
	 * groupe dit de qui il s'agit.
	 */
	protected function getGroup(string $title, int $columns): string {
		return '<tr class="pdf-sheet-group"><td colspan="'.($columns + 1).'">'.$title.'</td></tr>';
	}

	/**
	 * « 7 et 8 novembre 2026 », « Du 7 au 9 novembre 2026 », « 17 novembre 2026 » : sans le nom des
	 * jours, que les colonnes de la table portent, pour que la figure tienne sur une ligne et laisse
	 * à la page la hauteur des cases à signer.
	 *
	 * @param string[] $days les jours des séances en présentiel, `AAAA-MM-JJ`, dans l'ordre
	 */
	protected function getDays(array $days): string {

		$start = $days[0];
		$end = $days[count($days) - 1];

		$to = \util\DateUi::textual($end, \util\DateUi::DATE);

		if($start === $end) {
			return $to;
		}

		if(substr($start, 0, 7) === substr($end, 0, 7)) {
			$startNumber = (int)substr($start, 8, 2);
			$from = ($startNumber === 1) ? s("1er") : (string)$startNumber;
		} else if(substr($start, 0, 4) === substr($end, 0, 4)) {
			$from = \util\DateUi::textual($start, \util\DateUi::DAY_MONTH);
		} else {
			$from = \util\DateUi::textual($start, \util\DateUi::DATE);
		}

		return match(count($days)) {
			2 => s("{from} et {to}", ['from' => $from, 'to' => $to]),
			default => s("Du {from} au {to}", ['from' => $from, 'to' => $to]),
		};

	}

	/**
	 * « Samedi 7 novembre » : l'année est déjà dans les figures.
	 */
	protected function getDay(string $day): string {
		return DateUi::getDayName($day).' '.\util\DateUi::textual($day, \util\DateUi::DAY_MONTH);
	}

	protected function getHalf(string $half): string {

		return match($half) {
			DateLib::MORNING => s("Matin"),
			DateLib::AFTERNOON => s("Après-midi"),
		};

	}

	/**
	 * « Encadrée par Julie Fabre, formatrice et Claire Moulin, conseillère bio. » Le rôle tel que le
	 * réseau l'a saisi.
	 */
	protected function getStaffLede(\Collection $cStaff): string {

		$staff = $cStaff->makeArray(fn(Staff $eStaff) => s("{name}, {role}", ['name' => encode($eStaff['supervisor']['name']), 'role' => encode($eStaff['role'])]));

		$last = array_pop($staff);

		return s("Encadrée par {value}.", ($staff === []) ? $last : s("{list} et {last}", ['list' => implode(', ', $staff), 'last' => $last]));

	}

}
?>
