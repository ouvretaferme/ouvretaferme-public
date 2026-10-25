<?php
namespace learning;

/**
 * Le Bilan d'une formation en ligne (`/formation/{id}/bilan`), sous les onglets de l'éditeur, dans la
 * forme des analyses des ventes (23/10) : cinq chiffres clés sur la période, dont le choisi
 * dessine sa série en barres, par semaine ou par mois, puis les leçons sur la même période. La
 * période se choisit à côté du titre de la page (`getSeason()`), parce qu'elle gouverne tout l'écran.
 * Tout vient de `UsageLib::get()`, que l'API rend aussi.
 *
 * Chaque chiffre, le pas et la période sont des liens qui rouvrent la page, comme les années de
 * l'analyse des ventes : chacun garde les deux autres choix, et un seul graphique se dessine, celui
 * qu'on regarde.
 */
class UsageUi {

	const INDICATORS = ['enrolled', 'readersActive', 'completed', 'reviews', 'comments'];
	const INDICATOR_DEFAULT = 'readersActive';

	public function __construct() {
		\Asset::css('learning', 'learning.css');
		\Asset::css('main', 'chart.css');
		\Asset::js('main', 'chart.js');
	}

	/**
	 * La puce de période de la tête de l'éditeur. Une formation sans lecteur n'en a pas : le Bilan ne
	 * montre alors rien que la période changerait.
	 */
	public function getSeason(Course $eCourse, array $usage, string $indicator): string {

		if($usage['readers'] === 0) {
			return '';
		}

		$choices = [];

		foreach(self::getPeriods() as $period => $label) {
			$choices[] = [
				'url' => self::url($eCourse, $period, $usage['step'], $indicator),
				'label' => $label,
				'selected' => ($period === $usage['period'])
			];
		}

		return new \main\DesignUi()->getSeason(self::getPeriods()[$usage['period']], $choices, title: s("Changer de période"));

	}

	public function get(Course $eCourse, \Collection $cChapter, array $usage, string $indicator, bool $withDiffusion): string {

		if($usage['readers'] === 0) {
			return $this->getEmpty($eCourse, $withDiffusion);
		}

		$h = '<div id="learning-usage">';
			$h .= $this->getFigures($eCourse, $usage, $indicator);
			$h .= $this->getIndicator($eCourse, $usage, $indicator);
			$h .= $this->getLessons($cChapter, $usage);
		$h .= '</div>';

		return $h;

	}

	/**
	 * Une formation qui n'a encore aucun lecteur : un seul constat, et le chemin vers la diffusion
	 * quand la formation en a une.
	 */
	protected function getEmpty(Course $eCourse, bool $withDiffusion): string {

		$h = '<div class="util-block-empty">';
			$h .= '<h3>'.s("Aucun lecteur inscrit").'</h3>';
			$h .= '<p>'.s("Les inscriptions, la progression des lecteurs et leurs avis viendront ici dès la première inscription.").'</p>';
			if($withDiffusion) {
				$h .= '<a href="'.CourseUi::urlEdition($eCourse).'?tab=diffusion" class="btn btn-outline-primary">'.s("Voir la diffusion").'</a>';
			}
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les cinq chiffres de la période. Les inscriptions, les lecteurs qui ont tout suivi et les
	 * commentaires sont le total de leurs barres, comme le nombre d'avis écrit sous leur note ; un
	 * lecteur actif plusieurs semaines ne compte qu'une fois dans le sien, qui peut donc rester sous la
	 * somme des barres. Le taux de complétion, qui vaut à ce jour, se lit sous le chiffre de la période
	 * qui le nourrit.
	 */
	protected function getFigures(Course $eCourse, array $usage, string $indicator): string {

		$reviews = $usage['reviews'];
		$comments = $usage['comments'];

		$h = '<div class="util-figures util-figures-overflow">';

			$h .= $this->getFigure($eCourse, $usage, 'enrolled', $indicator, \util\TextUi::number($usage['enrolled']));
			$h .= $this->getFigure($eCourse, $usage, 'readersActive', $indicator, \util\TextUi::number($usage['readersActive']));
			$h .= $this->getFigure($eCourse, $usage, 'completed', $indicator, \util\TextUi::number($usage['completed']), s("{value}&nbsp;% à ce jour", $usage['completionRate']));
			$h .= $this->getFigure($eCourse, $usage, 'reviews', $indicator, ($reviews['count'] === 0) ? '-' : '<span class="util-unit">'.\util\TextUi::number($reviews['rating'], 1).' <small>'.\Asset::icon('star-fill').'</small></span>', p("{value} avis", "{value} avis", $reviews['count'], ['value' => \util\TextUi::number($reviews['count'])]));
			$h .= $this->getFigure($eCourse, $usage, 'comments', $indicator, \util\TextUi::number($comments['count']), ($comments['count'] === 0) ? '' : s("dont {value} sans réponse", \util\TextUi::number($comments['unanswered'])));

		$h .= '</div>';

		return $h;

	}

	protected function getFigure(Course $eCourse, array $usage, string $name, string $indicator, string $value, string $context = ''): string {

		$h = '<a href="'.self::url($eCourse, $usage['period'], $usage['step'], $name).'" class="util-figure'.($name === $indicator ? ' util-figure-selected' : '').'">';
			$h .= '<div class="util-figure-label util-figure-label-top">'.self::getFigureLabels()[$name].'</div>';
			$h .= '<div class="util-figure-value">'.$value.'</div>';
			if($context !== '') {
				$h .= '<div class="util-figure-label">'.$context.'</div>';
			}
		$h .= '</a>';

		return $h;

	}

	/**
	 * L'indicateur choisi, sous son nom et la bascule du pas. Les avis se lisent d'abord en note et en
	 * répartition, comme sur la fiche publique, puis par pas. Un indicateur à zéro sur la période ne
	 * dessine pas de barres vides, ni la bascule du pas, qui n'y changerait rien.
	 */
	protected function getIndicator(Course $eCourse, array $usage, string $indicator): string {

		$count = match($indicator) {
			'enrolled', 'readersActive', 'completed' => $usage[$indicator],
			'reviews', 'comments' => $usage[$indicator]['count'],
		};

		$h = '<div class="util-title">';
			$h .= '<h2>'.self::getIndicators()[$indicator].'</h2>';
			if($count > 0) {
				$h .= $this->getStepSwitch($eCourse, $usage, $indicator);
			}
		$h .= '</div>';

		if($count === 0) {
			$h .= '<div class="util-empty">'.self::getIndicatorsEmpty()[$indicator].'</div>';
			return $h;
		}

		if($indicator === 'reviews') {
			$h .= '<div class="learning-reviews-head">';
				$h .= '<div>'.\main\DesignUi::getRating($usage['reviews']['rating'], large: TRUE).'</div>';
				$h .= ReviewUi::getDistribution($usage['reviews']['distribution']);
			$h .= '</div>';
		}

		\Asset::lib('main', 'chart-4.5.1.umd.min.js');

		$values = array_column($usage['series'], $indicator);
		$labels = self::getLabels($usage);

		$h .= '<div class="analyze-bar">';
			$h .= '<canvas '.attr('onrender', 'UtilChart.createBar(this, '.json_encode(self::getIndicators()[$indicator]).', '.json_encode($values).', '.json_encode($labels).', "", {legend: false, integer: true})').'></canvas>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Mois puis semaine, dans l'ordre du design system. Une période qui n'admet qu'un pas n'a pas de
	 * bascule : la page pose sur `$usage['steps']` ceux qu'elle admet (`UsageLib::getSteps()`).
	 */
	protected function getStepSwitch(Course $eCourse, array $usage, string $indicator): string {

		if(count($usage['steps']) === 1) {
			return '';
		}

		$h = '<span class="tabs-item tabs-sm">';
			foreach([UsageLib::MONTH => s("Mois"), UsageLib::WEEK => s("Semaine")] as $step => $label) {
				$h .= '<a href="'.self::url($eCourse, $usage['period'], $step, $indicator).'" class="tab-item'.($step === $usage['step'] ? ' selected' : '').'">'.$label.'</a>';
			}
		$h .= '</span>';

		return $h;

	}

	/**
	 * Le repère de chaque barre : le lundi de la semaine ou le mois. Au pas mois, le premier mois que
	 * la période entame porte le lundi où elle commence, puisqu'il ne compte qu'à partir de là
	 * (`UsageLib::getRange()`). L'année s'ajoute quand « Depuis l'ouverture » couvre plusieurs années,
	 * où un même mois revient.
	 */
	protected static function getLabels(array $usage): array {

		$withYear = ($usage['period'] === 'all' and substr($usage['from'], 0, 4) !== substr($usage['to'], 0, 4));

		$labels = [];

		foreach($usage['series'] as $line) {

			if($line['date'] < $usage['from']) {
				$label = \util\DateUi::dayMonthShort($usage['from']);
			} else {
				$label = match($usage['step']) {
					UsageLib::WEEK => \util\DateUi::dayMonthShort($line['date']),
					UsageLib::MONTH => \util\DateUi::getMonthName((int)substr($line['date'], 5, 2), type: 'short'),
				};
			}

			// Chart.js dessine ses libellés en texte dans le canvas : l'insécable des dates y resterait écrit en entité.
			$labels[] = html_entity_decode($label).($withYear ? ' '.substr($line['date'], 0, 4) : '');

		}

		return $labels;

	}

	protected static function url(Course $eCourse, string $period, string $step, string $indicator): string {
		return CourseUi::urlUsage($eCourse, ['period' => $period, 'step' => $step, 'indicator' => $indicator]);
	}

	/**
	 * Les leçons dans l'ordre du programme, sur la période : combien de lecteurs actifs ont suivi
	 * chacune, leur temps médian et ses commentaires ; une leçon validée par quiz dit sous son nom la
	 * part réussie au premier essai. Sans lecteur actif sur la période, le programme ne serait qu'une
	 * colonne de zéros : un constat le remplace, comme sous un chiffre clé à zéro.
	 */
	protected function getLessons(\Collection $cChapter, array $usage): string {

		$h = '<div class="util-title mt-2">';
			$h .= '<h2>'.s("Leçons");
			if($usage['readersActive'] > 0) {
				$h .= ' <span class="util-annotation">'.p("sur {value} lecteur actif", "sur {value} lecteurs actifs", $usage['readersActive']).'</span>';
			}
			$h .= '</h2>';
		$h .= '</div>';

		if($usage['readersActive'] === 0) {
			$h .= '<div class="util-empty">'.s("Aucune leçon suivie sur la période").'</div>';
			return $h;
		}

		$h .= '<div class="util-overflow">';
			$h .= '<table class="tr-even td-vertical-align-middle">';

				$h .= '<thead>';
					$h .= '<tr>';
						$h .= '<th class="td-min-content"></th>';
						$h .= '<th>'.s("Leçon").'</th>';
						$h .= '<th class="hide-xs-down"></th>';
						$h .= '<th class="text-end td-min-content">'.s("Suivie par").'</th>';
						$h .= '<th class="text-end td-min-content hide-xs-down">'.s("Temps médian").'</th>';
						$h .= '<th class="text-end td-min-content hide-xs-down">'.s("Commentaires").'</th>';
					$h .= '</tr>';
				$h .= '</thead>';

				foreach($cChapter as $eChapter) {

					$h .= '<tbody>';

						$h .= '<tr class="tr-title">';
							$h .= '<td class="td-min-content text-end learning-program-number"><span class="hide-xxs-down">'.s("Module").' </span>'.$eChapter['number'].'</td>';
							$h .= '<td colspan="5">'.encode($eChapter['name']).'</td>';
						$h .= '</tr>';

						foreach($eChapter['cLesson'] as $eLesson) {
							$h .= $this->getLesson($eLesson, $usage['lessons'][$eLesson['id']], $usage['readersActive']);
						}

					$h .= '</tbody>';

				}

			$h .= '</table>';
		$h .= '</div>';

		return $h;

	}

	protected function getLesson(Lesson $eLesson, array $lesson, int $readersActive): string {

		// La largeur de la barre, et non une donnée : la part des lecteurs actifs qui ont suivi la leçon.
		$width = round($lesson['readersDone'] / $readersActive * 100, 1);

		$h = '<tr>';
			$h .= '<td class="td-min-content text-end learning-program-number">'.$eLesson['number'].'</td>';
			$h .= '<td>';
				$h .= encode($eLesson['name']);
				if($lesson['quizFirstAttemptRate'] !== NULL) {
					$h .= '<div class="util-annotation">'.s("Quiz, {value}&nbsp;% réussi au premier essai", $lesson['quizFirstAttemptRate']).'</div>';
				}
			$h .= '</td>';
			$h .= '<td class="hide-xs-down learning-usage-progress">';
				$h .= '<div class="util-progress"><div class="util-progress-part bg-secondary" style="width: '.$width.'%"></div></div>';
			$h .= '</td>';
			$h .= '<td class="text-end td-min-content">'.\util\TextUi::number($lesson['readersDone']).'</td>';
			$h .= '<td class="text-end td-min-content hide-xs-down">'.self::getMedian($lesson['medianSeconds']).'</td>';
			$h .= '<td class="text-end td-min-content hide-xs-down">'.\util\TextUi::number($lesson['comments']).'</td>';
		$h .= '</tr>';

		return $h;

	}

	/**
	 * Le temps médian en minutes, arrondi à la minute supérieure : une leçon lue en quarante secondes
	 * se dit « 1 min », pas « 0 min ».
	 */
	protected static function getMedian(?int $seconds): string {

		if($seconds === NULL) {
			return '<span class="util-annotation">-</span>';
		}

		return CourseUi::getDuration((int)ceil($seconds / 60));

	}

	protected static function getIndicators(): array {

		return [
			'enrolled' => s("Inscriptions"),
			'readersActive' => s("Lecteurs actifs"),
			'completed' => s("Ont tout suivi"),
			'reviews' => s("Avis reçus"),
			'comments' => s("Commentaires")
		];

	}

	/**
	 * La tuile des avis dit en grand leur note moyenne sur la période, leur nombre en dessous (
	 * 24/10). Le graphique qu'elle ouvre garde le titre « Avis reçus » et compte les avis par pas : la
	 * note d'une semaine tient sur un ou deux avis et ne se lirait pas en barres, et la note de la
	 * période se lit déjà au-dessus, avec sa répartition.
	 */
	protected static function getFigureLabels(): array {

		return [
			'reviews' => s("Note moyenne")
		] + self::getIndicators();

	}

	protected static function getIndicatorsEmpty(): array {

		return [
			'enrolled' => s("Aucune inscription sur la période"),
			'readersActive' => s("Aucun lecteur actif sur la période"),
			'completed' => s("Personne n'a tout suivi sur la période"),
			'reviews' => s("Aucun avis sur la période"),
			'comments' => s("Aucun commentaire sur la période")
		];

	}

	public static function getPeriods(): array {

		return [
			'30d' => s("30 derniers jours"),
			'90d' => s("90 derniers jours"),
			'12m' => s("12 derniers mois"),
			'all' => s("Depuis l'ouverture")
		];

	}

}
?>
