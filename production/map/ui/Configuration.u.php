<?php
namespace map;

class ConfigurationUi {

	public function updateCrop(Configuration $eConfiguration): string {

		\Asset::js('map', 'configuration.js');

		$form = new \util\FormUi();

		$h = $form->openAjax('/map/configuration:doUpdateCrop');

			$h .= $form->hidden('id', $eConfiguration);

			if($eConfiguration['structure']->hasWorkingTime()) {
				$h .= $form->dynamicGroup($eConfiguration['eConfigurationStructure'], 'timeRange');
			}

			$input = $form->inputGroup(
				$form->addon(s("Début en année n - 1 :")).
				$form->select('calendarMonthStart', array_slice(\util\DateUi::months(), 6, preserve_keys: TRUE), $eConfiguration['calendarMonthStart'], ['placeholder' => s("année non affichée"), 'onchange' => 'MapConfiguration.changeCalendarMonth('.$eConfiguration['id'].', this)']),
				['class' => 'mb-1']
			);
			$input .= $form->inputGroup(
				$form->addon(s("Fin en année n + 1 :")).
				$form->select('calendarMonthStop', array_slice(\util\DateUi::months(), 0, -6, preserve_keys: TRUE), $eConfiguration['calendarMonthStop'], ['placeholder' => s("année non affichée"), 'onchange' => 'MapConfiguration.changeCalendarMonth('.$eConfiguration['id'].', this)']),
				['class' => 'mb-1']
			);

			$input .= '<div id="map-configuration-calendar-month">';
				$input .= new \series\CultivationUi()->getListSeason($eConfiguration, \Clock::year());
			$input .= '</div>';

			$h .= $form->group(s("Période affichée sur les diagrammes"), $input, ['data-wrapper' => 'calendarMonthStart calendarMonthStop']);

			$h .= $form->section('<h3>'.s("Planches de culture").'</h3>');
			$h .= $form->dynamicGroups($eConfiguration, ['defaultBedLength', 'defaultBedWidth', 'defaultAlleyWidth']);

			$h .= $form->section('<h3>'.s("Rotations").'</h3>');
			$h .= $form->dynamicGroups($eConfiguration, ['rotationYears', 'rotationExclude']);

			$h .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);

		$h .= $form->close();

		return $h;

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Configuration::model()->describer($property, [
			'decorGrass' => s("Herbe"),
			'decorSoil' => s("Sol"),
			'decorMeadow' => s("Pré"),
			'rotationYears' => s("Temps de suivi des rotations"),
			'rotationExclude' => s("Espèces cultivées exclues du suivi des rotations"),
			'defaultBedLength' => s("Longueur des planches par défaut"),
			'defaultBedWidth' => s("Largeur travaillée des planches par défaut"),
			'defaultAlleyWidth' => s("Largeur de passe-pied entre les planches par défaut"),
		]);

		switch($property) {

			case 'decorGrass' :
				$d->values = [
					Configuration::TENDER => s("Verte tendre"),
					Configuration::PALE => s("Verte pâle"),
					Configuration::CLEAR => s("Presque blanche"),
					Configuration::DRY => s("Sèche d'été"),
					Configuration::LUSH => s("Verte vive"),
					Configuration::GRAZED => s("Verte lumineuse"),
				];
				break;

			case 'decorSoil' :
				$d->values = [
					Configuration::LOAM => s("Limon"),
					Configuration::CLAY => s("Argile"),
					Configuration::SAND => s("Sable"),
					Configuration::HUMUS => s("Terre noire"),
					Configuration::CHALK => s("Calcaire"),
					Configuration::OCHRE => s("Ocre"),
					Configuration::SANDSTONE => s("Grès rose"),
					Configuration::SCHIST => s("Schiste"),
					Configuration::BROWN => s("Terre brune"),
				];
				break;

			case 'decorMeadow' :
				$d->values = [
					Configuration::MIXED => s("Mixte"),
					Configuration::FLOWERY => s("Prairie fleurie"),
					Configuration::TUFTED => s("Herbe à touffes"),
				];
				break;

			case 'rotationYears' :
				$d->append = s("saisons");
				break;

			case 'rotationExclude' :
				$d->after = \util\FormUi::info(s("Vous pouvez exclure des espèces cultivées de la gestion des rotations, cela peut par exemple être utile pour les cultures très courtes."));
				$d->autocompleteDefault = fn(Configuration $e) => $e['cPlantRotationExclude'] ?? $e->expects(['cPlantRotationExclude']);
				$d->autocompleteBody = function(\util\FormUi $form, Configuration $e) {
					return [
						'structure' => $e['structure']['id']
					];
				};
				new \plant\PlantUi()->query($d, TRUE);
				$d->wrapper = 'rotationExclude';
				break;

			case 'defaultBedLength' :
				$d->append = s("m");
				$d->after = \util\FormUi::info(s("Si vous travaillez sur planches, indiquez ici la longueur habituelle de vos planches."));
				break;

			case 'defaultBedWidth' :
				$d->append = s("cm");
				$d->after = \util\FormUi::info(s("Si vous travaillez sur planches, indiquez ici la largeur travaillée habituelle de vos planches."));
				break;

			case 'defaultAlleyWidth' :
				$d->append = s("cm");
				$d->labelAfter = \structure\StructureUi::getAlleyWarning();
				$d->after = \util\FormUi::info(s("Si vous travaillez sur planches, indiquez ici la largeur habituelle des passe-pieds entre vos planches"));
				break;

		}

		return $d;

	}

}
?>
