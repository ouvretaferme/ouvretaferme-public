<?php
namespace toolbox;

/**
 * Ajouter un compteur d'eau, le régler, le supprimer.
 */
class WaterMeterUi {

	public static function getName(WaterMeter $eWaterMeter): string {
		return encode($eWaterMeter['name']);
	}

	public static function getCreate(\structure\Structure $eStructure, string $label, string $class): string {
		return '<a href="/toolbox/waterMeter:create?structure='.$eStructure['id'].'" class="'.$class.'">'.\Asset::icon('plus-circle').' '.$label.'</a>';
	}

	/**
	 * La roue crantée d'un compteur, qui ferme sa ligne de saisie comme celle d'un pluviomètre
	 * (`GaugeUi::getActions()`). La suppression, qui emporte les relevés, passe sous la zone de danger.
	 * « Nouveau compteur installé » remplace l'appareil et garde le compteur de l'écran, son onglet et
	 * ses relevés (`WaterReadingUi::createChange()`). Libellés du 02/10.
	 */
	public function getActions(WaterMeter $eWaterMeter): string {

		$h = '<a class="btn btn-color-primary dropdown-toggle" data-dropdown="bottom-end" title="'.s("Réglages du compteur").'">'.\Asset::icon('gear-fill').'</a>';
		$h .= '<div class="dropdown-list">';
			$h .= '<div class="dropdown-title">'.self::getName($eWaterMeter).'</div>';
			$h .= '<a href="/toolbox/waterMeter:update?id='.$eWaterMeter['id'].'" class="dropdown-item">'.s("Paramétrer le compteur").'</a>';
			$h .= '<a href="/toolbox/waterMeter:change?id='.$eWaterMeter['id'].'" class="dropdown-item">'.s("Nouveau compteur installé").'</a>';
			$h .= '<div class="dropdown-divider"></div>';
			$h .= '<div class="dropdown-subtitle">'.\Asset::icon('exclamation-circle').'  '.s("Zone de danger").'  '.\Asset::icon('exclamation-circle').'</div>';
			$h .= '<a data-ajax="/toolbox/waterMeter:doUpdateStatus" post-id="'.$eWaterMeter['id'].'" post-status="'.WaterMeter::INACTIVE.'" class="dropdown-item" data-confirm="'.s("Supprimer ce compteur et ses relevés ?").'">'.s("Supprimer le compteur").'</a>';
		$h .= '</div>';

		return $h;

	}

	public function create(WaterMeter $eWaterMeter): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/toolbox/waterMeter:doCreate', ['id' => 'water-meter-create']);

			$h .= $form->hidden('structure', $eWaterMeter['structure']);
			$h .= $form->dynamicGroups($eWaterMeter, ['name', 'digits']);

			$h .= $form->group(
				content: $form->submit(s("Ajouter le compteur"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-water-meter-create',
			title: s("Nouveau compteur"),
			body: $h
		);

	}

	public function update(WaterMeter $eWaterMeter): \Panel {

		$form = new \util\FormUi();

		$h = $form->openAjax('/toolbox/waterMeter:doUpdate', ['id' => 'water-meter-update']);

			$h .= $form->hidden('id', $eWaterMeter);
			$h .= $form->dynamicGroups($eWaterMeter, ['name', 'digits']);

			$h .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-water-meter-update',
			title: s("Paramétrer le compteur"),
			body: $h
		);

	}

	public static function p(string $property): \PropertyDescriber {

		$d = WaterMeter::model()->describer($property, [
			'name' => s("Nom du compteur"),
			'digits' => s("Nombre de chiffres du compteur"),
		]);

		switch($property) {

			case 'name' :
				$d->attributes = [
					'placeholder' => s("Forage, puits, réseau...")
				];
				break;

			case 'digits' :
				$d->after = \util\FormUi::info(s("Ceux des m³ entiers, sans les décimales."));
				break;

		}

		return $d;

	}

}
?>
