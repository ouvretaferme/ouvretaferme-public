<?php
namespace learning;

/**
 * Les séances d'une date dans son formulaire (lot date-mixte) : une ligne par séance, sur le
 * balisage des lignes du parcours de création (`CourseStartUi::getLessonRow()`), et les trois
 * boutons qui en ajoutent une, préréglée. Le panneau d'une date et l'étape « La première date » les
 * partagent par `DateUi::getFields()`.
 */
class DateSequenceUi {

	public function __construct() {
		\Asset::css('main', 'onboarding.css');
		\Asset::css('learning', 'learning.css');
		\Asset::js('learning', 'learning.js');
	}

	/**
	 * Le champ `sequences` de la date : les lignes, les boutons d'ajout hors de la liste, pour prendre
	 * l'espacement d'un geste sous un bloc, puis le gabarit d'une ligne neuve, que
	 * `LearningDate.add()` recopie sous l'index suivant (`data-index`, jamais le nombre de lignes :
	 * après un retrait, deux lignes porteraient le même index).
	 */
	public function getField(\util\FormUi $form, Date $eDate): string {

		$eDate->expects(['cDateSequence']);

		$index = 0;

		$rows = '';

		foreach($eDate['cDateSequence'] as $eDateSequence) {
			$rows .= $this->getRow($form, $eDateSequence, (string)$index);
			$index++;
		}

		$h = '<div class="onboarding-rows learning-date-sequences" data-index="'.$index.'">'.$rows.'</div>';

		$h .= '<div class="mt-1">';
			foreach([
				'day' => s("Journée"),
				'morning' => s("Matin"),
				'afternoon' => s("Après-midi"),
			] as $preset => $label) {
				$h .= '<a class="btn btn-outline-primary" onclick="LearningDate.add(this, \''.$preset.'\')">'.\Asset::icon('plus-circle').' '.$label.'</a> ';
			}
		$h .= '</div>';

		$h .= '<template>'.$this->getRow($form, $eDate['course']->getDefaultSequence(), '__i__').'</template>';

		return $h;

	}

	/**
	 * Une séance, sous les noms que `Date::properties()` construit par `buildIndex()` : chaque refus
	 * retombe sur son champ, dans sa ligne (`startAt[<index>]`). Les ids sont posés d'après l'index :
	 * celui que `FormUi` tire au hasard serait rendu une fois dans le gabarit, puis recopié à
	 * l'identique sur chaque ligne ajoutée. Le lieu reste dans la ligne en visio, masqué : la ligne
	 * qui repasse en présentiel le retrouve, et le Lib ne l'écrit pas en visio. Une séance sans jour
	 * (celle d'une date neuve, le gabarit) propose la journée, de 09:30 à 17:00.
	 */
	public function getRow(\util\FormUi $form, DateSequence $eDateSequence, string $index): string {

		$schedule = ['oninput' => 'LearningDate.updateSchedule(this)'];

		if($eDateSequence['startAt'] === NULL) {
			$day = NULL;
			$start = '09:30';
			$end = '17:00';
		} else {
			$day = $eDateSequence->getDay();
			$start = substr($eDateSequence['startAt'], 11, 5);
			$end = substr($eDateSequence['endAt'], 11, 5);
		}

		$h = '<div class="util-card onboarding-row">';

			$h .= $form->hidden('sequences[]', $index, ['id' => 'learning-date-sequence-'.$index]);

			$h .= '<div class="onboarding-row-main">';
				$h .= '<div class="onboarding-field onboarding-field-name" data-wrapper="startAt['.$index.']">';
					$h .= $form->date('startAt['.$index.'][date]', $day, ['id' => 'learning-date-sequence-day-'.$index] + $schedule);
				$h .= '</div>';
				$h .= '<a class="onboarding-row-remove" onclick="LearningDate.remove(this)" title="'.s("Retirer").'">'.\Asset::icon('x-lg').'</a>';
			$h .= '</div>';

			$h .= '<div class="onboarding-row-details">';

				$h .= '<div class="onboarding-field" data-wrapper="endAt['.$index.']">';
					$h .= '<label class="onboarding-field-label" for="learning-date-sequence-start-'.$index.'">'.s("Horaires").'</label>';
					$h .= $form->inputGroup(
						$form->time('startAt['.$index.'][time]', $start, ['id' => 'learning-date-sequence-start-'.$index] + $schedule).
						$form->addon(s("à")).
						$form->time('endAt['.$index.'][time]', $end, ['id' => 'learning-date-sequence-end-'.$index] + $schedule)
					);
				$h .= '</div>';

				$h .= '<div class="onboarding-field" data-wrapper="mode['.$index.']">';
					$h .= '<label class="onboarding-field-label" for="learning-date-sequence-mode-'.$index.'">'.self::p('mode')->label.'</label>';
					$h .= $form->dynamicField($eDateSequence, 'mode', function(\PropertyDescriber $d) use($index) {
						$d->name = 'mode['.$index.']';
						$d->attributes = ['id' => 'learning-date-sequence-mode-'.$index, 'onchange' => 'LearningDate.changeMode(this)'] + $d->attributes;
					});
				$h .= '</div>';

				$h .= '<div class="onboarding-field'.($eDateSequence['mode'] === DateSequence::ONSITE ? '' : ' hide').'" data-wrapper="place['.$index.']">';
					$h .= '<label class="onboarding-field-label" for="learning-date-sequence-place-'.$index.'">'.self::p('place')->label.'</label>';
					$h .= $form->dynamicField($eDateSequence, 'place', function(\PropertyDescriber $d) use($index) {
						$d->name = 'place['.$index.']';
						$d->attributes = ['id' => 'learning-date-sequence-place-'.$index] + $d->attributes;
					});
				$h .= '</div>';

			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	public static function p(string $property): \PropertyDescriber {

		$d = DateSequence::model()->describer($property, [
			'startAt' => s("Jour"),
			'endAt' => s("Horaires"),
			'mode' => s("Mode"),
			'place' => s("Lieu"),
		]);

		switch($property) {

			case 'mode' :
				$d->values = [
					DateSequence::ONSITE => s("Présentiel"),
					DateSequence::REMOTE => s("Visio"),
				];
				$d->field = 'select';
				$d->attributes = ['required' => TRUE];
				break;

			case 'place' :
				$d->placeholder = s("Ex. : salle des fêtes, Crest");
				break;

		}

		return $d;

	}

}
?>
