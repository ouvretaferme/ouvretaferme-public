<?php
namespace aid;

/**
 * L'onglet « Couverture » de l'admin des aides : la part des lignes attendues des listes officielles que
 * le catalogue résout, sur la carte des régions pour une famille à la fois, puis le détail d'une région.
 */
class ExpectedUi {

	public function __construct() {
		\Asset::css('aid', 'admin.css');
	}

	/**
	 * Les trois chiffres de tête. La réserve n'entre pas dans le taux : ce sont des brouillons, qui ne
	 * résolvent aucune ligne. Ses deux nombres ouvrent chacun sa liste, le filtre `verification` de la
	 * liste ne prenant qu'une valeur (décidé par V-Sub).
	 */
	public function getFigures(array $total, array $reserve, int $reserveTotal): string {

		$h = '<div class="util-figures util-figures-3 mb-2">';

			$h .= '<div class="util-figure">';
				$h .= '<div class="util-figure-value">'.$this->getRate($total).'</div>';
				$h .= '<div class="util-figure-label">'.p("Taux de couverture, {resolved} ligne résolue sur {value} attendue", "Taux de couverture, {resolved} lignes résolues sur {value} attendues", $total['expected'], ['resolved' => $total['resolved']]).'</div>';
			$h .= '</div>';

			$h .= '<div class="util-figure">';
				$h .= '<div class="util-figure-value">'.$total[Expected::OPEN].'</div>';
				$h .= '<div class="util-figure-label">'.p("Ligne attendue encore ouverte", "Lignes attendues encore ouvertes", $total[Expected::OPEN]).'</div>';
			$h .= '</div>';

			$h .= '<div class="util-figure">';
				$h .= '<div class="util-figure-value">'.$reserveTotal.'</div>';
				$h .= '<div class="util-figure-label">';
					$h .= s("Brouillons hors couverture, {relay} et {conflict}", [
						'relay' => '<a href="/aid/admin/?status='.Aid::DRAFT.'&verification='.Aid::RELAY.'">'.p("{value} lu sur un relais", "{value} lus sur un relais", $reserve['relay']).'</a>',
						'conflict' => '<a href="/aid/admin/?status='.Aid::DRAFT.'&verification='.Aid::CONFLICT.'">'.p("{value} divergent", "{value} divergents", $reserve['conflict']).'</a>',
					]);
				$h .= '</div>';
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * La carte des régions pour la famille choisie, l'outre-mer dessous, faute de tracé, et à côté le
	 * national puis les régions les moins couvertes. Une région sans ligne dans la famille reste en
	 * aplat neutre : elle n'a pas de taux, et 0 % dirait un trou. Les lignes de département se
	 * teignent par département, leur taux au survol (décidé par V-Sub).
	 */
	public function getCoverage(array $coverage, ?string $family, array $familiesSearched, array $published): string {

		$h = '<div class="util-title"><h2>'.s("Taux de couverture").'</h2></div>';
		$h .= $this->getFamilies($family);

		if($family !== NULL and in_array($family, $familiesSearched, TRUE) === FALSE) {
			return $h.'<div class="util-empty">'.s("Pas encore recherché").'</div>';
		}

		if($coverage['isEmpty']) {
			return $h.'<div class="util-empty">'.s("Aucune ligne attendue").'</div>';
		}

		$query = ($family === NULL) ? '' : '&family='.$family;
		$members = [];

		foreach(AidSetting::REGIONS as $region => $name) {
			$members[$region] = AidSetting::getDepartmentsByRegion((string)$region);
		}

		$centres = \main\FranceUi::getRegionCentres($members);

		$fills = [];
		$labels = [];
		$titles = [];
		$overseas = [];

		foreach($coverage['regions'] as $region => $tally) {

			$region = (string)$region;

			if(in_array($region, AidSetting::REGIONS_OVERSEAS, TRUE)) {
				$overseas[] = $region;
				continue;
			}

			foreach($members[$region] as $department) {

				if($family === Expected::DEPARTMENT) {

					$rate = $coverage['departments'][$department]['rate'];

					$fills[$department] = $this->getFill($rate, $coverage['scale']);

					if($rate !== NULL) {
						$titles[$department] = s("{department}, {rate}", ['department' => AidSetting::DEPARTMENTS[$department][0], 'rate' => $this->getRateText($rate)]);
					}

				} else {
					$fills[$department] = $this->getFill($tally['rate'], $coverage['scale']);
				}

			}

			// Une région sans taux n'a rien à écrire sur la carte.
			if($tally['rate'] !== NULL) {
				$labels[] = [
					'x' => $centres[$region][0],
					'y' => $centres[$region][1],
					'value' => $this->getRateText($tally['rate']),
				];
			}

		}

		// Une métropole sans taux n'a pas d'échelle, et la carte reste en aplat neutre.
		if($coverage['scale'] === NULL) {
			$legend = '';
		} else {

			[$low, $high] = $coverage['scale'];

			$legend = '<div class="util-map-legend">';
				$legend .= '<span>'.$this->getRateText($low).'</span>';
				$legend .= '<span class="util-map-scale aid-map-scale"></span>';
				$legend .= '<span>'.$this->getRateText($high).'</span>';
			$legend .= '</div>';

		}

		$h .= '<div class="util-layout-aside">';

			$h .= '<div>';
				$h .= \main\FranceUi::getMap($fills, $labels, $legend, ['region' => ($family !== Expected::DEPARTMENT), 'links' => self::getMapLinks($family), 'titles' => $titles]) ?? '';
				$h .= '<h3>'.s("Outre-mer").'</h3>';
				$h .= '<div class="util-rows">';
					foreach($overseas as $region) {
						$h .= $this->getRegionRow($region, $coverage['regions'][$region], $published[$region], $query);
					}
				$h .= '</div>';
				$h .= $this->getProgressLegend();
			$h .= '</div>';

			$h .= '<div class="util-layout-aside-side">';
				$h .= '<h3 class="mt-2">'.s("National").'</h3>';
				$h .= '<div class="util-rows">';
					$h .= '<div class="util-row">';
						if(in_array(Expected::NATIONAL, $familiesSearched, TRUE) === FALSE) {
							$h .= '<span class="color-muted">'.s("Pas encore recherché").'</span>';
						} else {
							$h .= '<div>';
								$h .= '<div class="util-annotation">'.$this->getExpected($coverage['national']).'</div>';
								$h .= $this->getProgress($coverage['national']);
							$h .= '</div>';
							$h .= '<b>'.$this->getRate($coverage['national']).'</b>';
						}
					$h .= '</div>';
				$h .= '</div>';
				// Sans région de métropole à classer, le bloc disparaît : l'outre-mer est juste à côté (décidé par V-Sub).
				if($coverage['lowest'] !== []) {
					$h .= '<h3 class="mt-2">'.s("Les moins couvertes").'</h3>';
					$h .= '<div class="util-rows">';
						foreach($coverage['lowest'] as $region) {
							$h .= $this->getRegionRow((string)$region, $coverage['regions'][$region], $published[$region], $query);
						}
					$h .= '</div>';
					$h .= $this->getProgressLegend();
				}
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	/**
	 * Le lien de chaque département du tracé vers le détail de sa région, la famille choisie gardée :
	 * toute la région se clique, y compris sans taux, et l'étiquette posée dessus ne reçoit pas le clic.
	 */
	public static function getMapLinks(?string $family): array {

		$links = [];

		foreach(AidSetting::DEPARTMENTS as $department => [, $region]) {
			$links[$department] = '/aid/admin/:coverage?region='.$region.(($family === NULL) ? '' : '&family='.$family);
		}

		return $links;

	}

	// Les aides que le Département accorde, et non toutes celles qui touchent son territoire (décidé par V-Sub).
	public static function getDepartmentUrl(string $department): string {
		return '/aid/admin/?department='.$department.'&level='.Aid::DEPARTMENT;
	}

	// La clé des trois parts de la barre, chaque marque dans la teinte de la part qu'elle nomme.
	private function getProgressLegend(): string {

		$h = '<div class="util-legend mt-1">';
			$h .= '<span class="util-legend-item"><i class="util-legend-mark bg-success"></i>'.s("Trouvée").'</span>';
			$h .= '<span class="util-legend-item"><i class="util-legend-mark aid-justified"></i>'.s("Absente, justifiée").'</span>';
			$h .= '<span class="util-legend-item"><i class="util-legend-mark aid-open"></i>'.s("Ouverte").'</span>';
		$h .= '</div>';

		return $h;

	}

	// La bascule d'une famille à l'autre, sous le titre : dans le titre, elle déborderait d'un téléphone.
	private function getFamilies(?string $family): string {

		$h = '<div class="tabs-item tabs-sm mb-1">';

			$h .= '<a href="/aid/admin/:coverage" class="tab-item'.($family === NULL ? ' selected' : '').'">'.s("Toutes").'</a>';

			foreach(ExpectedLib::FAMILIES_REGION as $value) {
				$h .= '<a href="/aid/admin/:coverage?family='.$value.'" class="tab-item'.($family === $value ? ' selected' : '').'">'.self::p('family')->values[$value].'</a>';
			}

		$h .= '</div>';

		return $h;

	}

	// La région ouvre son détail, et son nombre d'aides publiées la liste qui le donne, closes comprises.
	private function getRegionRow(string $region, array $tally, int $published, string $query): string {

		$h = '<div class="util-row">';
			$h .= '<div>';
				$h .= '<a href="/aid/admin/:coverage?region='.$region.$query.'">'.encode(AidSetting::REGIONS[$region]).'</a>';
				$h .= '<div class="util-annotation"><a href="/aid/admin/?region='.$region.'&status='.Aid::PUBLISHED.'">'.p("{value} aide publiée", "{value} aides publiées", $published).'</a></div>';
				$h .= $this->getProgress($tally);
			$h .= '</div>';
			$h .= '<b>'.$this->getRate($tally).'</b>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le détail d'une région : un chiffre par famille, puis ses lignes attendues groupées par famille,
	 * chacune avec son état et ce qui le prouve. Une famille sans ligne garde son groupe, qui dit ce qui
	 * manque à la méthode : pas encore recherchée nulle part, ou rien d'attendu ici (décidé par V-Sub).
	 * Arrivé avec une famille, elle seule est dépliée, et elle vient en premier.
	 */
	public function getRegion(string $region, \Collection $ccExpected, array $tallies, array $familiesSearched, ?string $family): string {

		$h = '<div class="util-title">';
			$h .= '<h2>'.encode(AidSetting::REGIONS[$region]).'</h2>';
			$h .= '<div><a href="/aid/admin/:coverage'.($family === NULL ? '' : '?family='.$family).'" class="btn btn-outline-primary">'.s("Toute la couverture").'</a></div>';
		$h .= '</div>';

		$h .= '<div class="util-figures mb-2">';
			foreach($tallies as $value => $tally) {
				$h .= '<div class="util-figure">';
					$h .= '<div class="util-figure-value">'.$this->getRate($tally).'</div>';
					$h .= '<div class="util-figure-label">'.self::p('family')->values[$value].'<br/>'.$this->getExpected($tally, in_array($value, $familiesSearched, TRUE)).'</div>';
				$h .= '</div>';
			}
		$h .= '</div>';

		// Deux colonnes sur un téléphone, l'état passant sous la ligne : la troisième ferait défiler le tableau.
		$h .= '<table class="tbody-even">';

			$h .= '<thead>';
				$h .= '<tr>';
					$h .= '<th>'.s("Ligne attendue").'</th>';
					$h .= '<th class="hide-xs-down">'.s("État").'</th>';
					$h .= '<th>'.s("Aide ou justification").'</th>';
				$h .= '</tr>';
			$h .= '</thead>';

			foreach($ccExpected as $value => $cExpectedFamily) {

				$h .= '<tbody class="tbody-group tbody-group-fold'.(($family === NULL or $family === $value) ? ' tbody-group-open' : '').'">';
					$h .= '<tr class="tr-title">';
						$h .= '<td colspan="3">';
							$h .= '<a class="tbody-group-toggle" onclick="this.closest(\'tbody\').classList.toggle(\'tbody-group-open\')">';
								$h .= self::p('family')->values[$value].' <span class="util-counter">'.$cExpectedFamily->count().'</span>';
								$h .= '<span class="util-fold-chevron">'.\Asset::icon('chevron-down').'</span>';
							$h .= '</a>';
						$h .= '</td>';
					$h .= '</tr>';
				$h .= '</tbody>';

				$h .= '<tbody class="tbody-group-sub">';

					if($cExpectedFamily->empty()) {
						$h .= '<tr class="tr-sub"><td colspan="3" class="color-muted">'.(in_array($value, $familiesSearched, TRUE) ? s("Aucune ligne attendue") : s("Pas encore recherché")).'</td></tr>';
					}

					foreach($cExpectedFamily as $eExpected) {
						$h .= $this->getLine($eExpected);
					}

				$h .= '</tbody>';

			}

		$h .= '</table>';

		return $h;

	}

	private function getLine(Expected $eExpected): string {

		$state = $eExpected->getState();

		$badge = match($state) {
			Expected::FOUND => '<span class="badge badge-success">'.s("Trouvée").'</span>',
			Expected::JUSTIFIED => '<span class="badge aid-justified">'.s("Absente, justifiée").'</span>',
			Expected::OPEN => '<span class="badge badge-outline">'.s("Ouverte").'</span>',
		};

		$h = '<tr class="tr-sub">';

			$h .= '<td>';
				if($eExpected['department'] !== NULL) {
					$h .= '<b>'.encode($eExpected['department']).'</b> ';
				} else if($eExpected['number'] !== NULL) {
					$h .= '<b>'.encode($eExpected['number']).'</b> ';
				}
				$h .= encode($eExpected['name']);
				$h .= '<div class="hide-sm-up">'.$badge.'</div>';
			$h .= '</td>';

			$h .= '<td class="hide-xs-down">'.$badge.'</td>';

			$h .= '<td>'.$this->getProof($eExpected, $state).'</td>';

		$h .= '</tr>';

		return $h;

	}

	/**
	 * Ce qui prouve l'état : l'aide qui se montre, la phrase qui justifie l'absence avec son adresse, ou
	 * l'aide close dont la fiche donne la campagne passée. Une ligne ouverte nomme l'aide ou l'organisme
	 * qu'elle attend, quand le semis l'a trouvé, l'aide marquée quand elle attend d'être publiée ; celle
	 * d'un département mène aux aides de ce département, où chercher ce qui manque (décidé par V-Sub).
	 */
	private function getProof(Expected $eExpected, string $state): string {

		if($eExpected['justification'] !== NULL and $state === Expected::JUSTIFIED) {

			if($eExpected['justificationUrl'] === NULL) {
				return encode($eExpected['justification']);
			}

			return '<a href="'.encode($eExpected['justificationUrl']).'" target="_blank">'.encode($eExpected['justification']).' '.\Asset::icon('box-arrow-up-right').'</a>';

		}

		if($state === Expected::JUSTIFIED) {
			$eAid = $eExpected->getAidClosed();
			return '<a href="/aid/admin/aid?id='.$eAid['id'].'">'.encode($eAid['name']).'</a><div class="util-annotation">'.s("Dernière édition close").'</div>';
		}

		// Une ligne d'organisme se compte sur les aides de cet organisme-là : elle ouvre leurs fiches, les
		// mêmes que celles qui la résolvent.
		$h = '';

		if($eExpected['organization']->notEmpty()) {

			$h .= encode($eExpected['organization']['name']);

			foreach($eExpected->getAidsFound() as $eAid) {
				$h .= '<div><a href="/aid/admin/aid?id='.$eAid['id'].'">'.encode($eAid['name']).'</a></div>';
			}

		} else if($eExpected['aid']->notEmpty()) {

			$h .= '<a href="/aid/admin/aid?id='.$eExpected['aid']['id'].'">'.encode($eExpected['aid']['name']).'</a>';

			if($eExpected->getAidDraft()->notEmpty()) {
				$h .= ' <span class="badge badge-draft">'.s("Brouillon").'</span>';
			}

		}

		if($state === Expected::OPEN and $eExpected['family'] === Expected::DEPARTMENT) {
			$h .= '<div><a href="'.self::getDepartmentUrl($eExpected['department']).'">'.s("Aides du département").'</a></div>';
		}

		return $h;

	}

	// Trouvées en vert, absentes justifiées en vert clair : le vide de la piste est ce qui reste ouvert.
	private function getProgress(array $tally): string {

		if($tally['expected'] === 0) {
			return '';
		}

		$h = '<div class="util-progress">';
			$h .= '<div class="util-progress-part bg-success" style="width: '.round(100 * $tally[Expected::FOUND] / $tally['expected'], 1).'%"></div>';
			$h .= '<div class="util-progress-part aid-justified" style="width: '.round(100 * $tally[Expected::JUSTIFIED] / $tally['expected'], 1).'%"></div>';
		$h .= '</div>';

		return $h;

	}

	private function getRate(array $tally): string {

		if($tally['rate'] === NULL) {
			return '<span class="color-muted">-</span>';
		}

		return $this->getRateText($tally['rate']);

	}

	private function getRateText(float $rate): string {
		return s("{value} %", round(100 * $rate));
	}

	// Le décompte sous un taux, d'une seule forme (décidé par V-Sub). Sans ligne, ce qui manque à la méthode.
	private function getExpected(array $tally, bool $searched = TRUE): string {

		if($tally['expected'] === 0) {
			return $searched ? s("Aucune ligne attendue") : s("Pas encore recherché");
		}

		return p("{value} ouverte sur {expected}", "{value} ouvertes sur {expected}", $tally[Expected::OPEN], ['expected' => $tally['expected']]);

	}

	// Du plus bas au plus haut taux reçu : de 0 à 100 %, des taux proches se confondraient en une teinte.
	private function getFill(?float $rate, ?array $scale): string {

		if($rate === NULL) {
			return 'var(--aid-track)';
		}

		[$low, $high] = $scale;

		return 'color-mix(in srgb, var(--production-light) '.round(10 + 90 * ($rate - $low) / max($high - $low, 0.01)).'%, white)';

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Expected::model()->describer($property, [
			'family' => s("Famille"),
		]);

		switch($property) {

			case 'family' :
				$d->values = [
					Expected::NATIONAL => s("National"),
					Expected::FEADER => s("FEADER régional"),
					Expected::DEPARTMENT => s("Départements"),
					Expected::GAL => s("GAL"),
					Expected::PARK => s("Parcs naturels régionaux"),
					Expected::EPCI => s("Intercommunalités"),
				];
				break;

		}

		return $d;

	}

}
?>
