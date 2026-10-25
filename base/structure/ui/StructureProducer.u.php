<?php
namespace structure;

class StructureProducerUi implements StructureCommonUi {

	public function getCreateForm(\util\FormUi $form, Structure $eStructure): string {

		$h = '<div data-step="producer-type">';
			$h .= \main\DesignUi::getWelcome(
				s("Qui êtes-vous ?"),
				'<p>'.s("Choisissez ce qui décrit le mieux votre activité pour que le logiciel vous propose les fonctionnalités les plus adaptées.").'</p>',
				\Asset::svg('main', 'friends/'.self::getWelcomeMascot().'.svg')
			);
			$h .= StructureUi::getProducerTypeDecks($eStructure['isFirst'], $eStructure['producerType'], $eStructure['canCreateAmap']);
		$h .= '</div>';

		// Les deux vocabulaires sont écrits ensemble, `Structure.chooseProducerType()` montre celui
		// que porte le deck : cette étape est rendue avant que la catégorie soit choisie.
		$wording = fn(string $farm, string $structure) => '<span data-wording="farm" class="hide">'.$farm.'</span><span data-wording="structure">'.$structure.'</span>';

		$h .= '<div data-step="fields" class="hide">';

			$h .= $form->asteriskInfo();
			$h .= $form->hidden('isLegal', TRUE);
			$h .= $form->hidden('type', Structure::PRODUCER);
			$h .= $form->hidden('producerType');

			if($eStructure['inviteKey'] !== NULL) {
				$h .= $form->hidden('inviteKey', $eStructure['inviteKey']);
			}

			$labels = '';

			foreach([Structure::TEST, Structure::LEARNER, Structure::INSTALLATION] as $producerType) {
				$labels .= '<b data-producer-type="'.$producerType.'" class="hide">'.StructureUi::p('producerType')->values[$producerType].'</b>';
			}

			$h .= $form->group($wording(s("Catégorie de la ferme"), s("Catégorie de la structure")), $labels, ['data-wrapper' => 'producerType', 'class' => 'hide']);

			$h .= $form->dynamicGroup($eStructure, 'name*', function($d) use($wording) {
				$d->label = $wording(s("Nom de la ferme"), s("Nom de la structure"));
			});

			$h .= $form->dynamicGroups($eStructure, ['startedAt', 'legalEmail*', 'legalCountry'], [
				'legalEmail*' => function($d) use($wording) {
					$d->label = $wording(s("Adresse e-mail de la ferme"), s("Adresse e-mail de la structure"));
				}
			]);

			$h .= new StructureUi()->getMarksFields($form, $eStructure);

			$h .= $form->group(
				content: $form->submit($wording(s("Créer ma ferme"), s("Créer ma structure")), ['data-waiter' => s("Création en cours")])
			);

		$h .= '</div>';

		return $h;

	}

	public function getCreateTitle(Structure $eStructure): string {

		return s("Créer ma structure");

	}

	public static function getWelcomeMascot(): string {
		return 'abeille';
	}

	public static function getText(Structure $eStructure, string $text, mixed $value = 1): string {

		return match($text) {

			'of' => s("de la ferme"),
			'one' => s("une ferme"),
			'the' => s("la ferme"),
			'this' => s("cette ferme"),
			'your' => s("votre ferme"),

			'my' => p("Ma structure", "Mes structures", $value),

			'propertyLogo' => s("Logo de la structure"),
			'propertyBanner' => s("Bandeau de la structure"),
			'propertyName' => s("Nom de la structure"),
			'propertyIsLegal' => throw new \UnsupportedException(),
			'propertyLegalEmail' => s("Adresse e-mail de la structure"),
			'propertyPresentation' => s("Présentation de la structure"),

			'settingsGeneral' => s("Administratif"),

			'delete' => s("Supprimer la structure"),

			// Mesuré le 12/09/2026 : deux fermes actives passent la garde de `getLegalForm()`
			// (`isLegal() === FALSE and acceptLegal()`), et cette clé manquante y levait un
			// `UnhandledMatchError` — le panneau entier tombait.
			'updateLegal' => s("Votre ferme est désormais portée par une structure juridique ?")

		};

	}

}
?>
