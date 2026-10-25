<?php
namespace structure;

class SurveyUi {

	public function __construct() {
		\Asset::css('structure', 'survey.css');
		\Asset::js('structure', 'survey.js');
	}

	public static function getAnnouncement(bool $hasStructureToAnswer): string {

		if($hasStructureToAnswer === FALSE) {
			return '';
		}

		$h = '<div class="mb-2" style="position: relative; overflow: hidden; padding: 1.5rem 0; background-color: var(--color-brand); color: white; text-align: center;">';

			$h .= '<div class="hide-sm-down" style="position: absolute; left: 4rem; top: 50%; transform: translateY(-50%); width: 5rem">'.\Asset::svg('main', 'friends/abeille.svg').'</div>';
			$h .= '<div class="hide-sm-down" style="position: absolute; right: 4rem; top: 50%; transform: translateY(-50%); width: 4rem">'.\Asset::svg('main', 'friends/oiseau.svg').'</div>';

			$h .= '<div style="position: relative; max-width: 42rem; margin: 0 auto; padding: 0 1rem">';
				$h .= '<h3 style="margin-bottom: 0.5rem">'.s("Quel usage faites-vous de {siteName} ?").'</h3>';
				$h .= '<div>'.s("En tant qu'adhérent·e, dites-nous ce que vous utilisez dans le logiciel et ce qui vous manque. Nous utiliserons vos réponses pour choisir les prochains développements.").'</div>';
				$h .= '<a href="/structure/survey" class="btn btn-neon" style="margin-top: 1rem">'.s("Répondre à l'enquête").'</a>';
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	public static function getIntroduction(): string {

		return new \main\DesignUi()->getStart(
			\Asset::icon('chat-square-heart'),
			s("Votre bilan de {siteName}"),
			'<p>'.s("<b>Cette enquête est réservée aux adhérents de l'association.</b> Elle nous sert à savoir ce que vous utilisez dans le logiciel, univers par univers, ce qui vous convient et ce qui vous manque.").'</p><p>'.s("Les questions ouvertes sont celles qui nous apprennent le plus. Que ce soit un geste trop long, une erreur qui revient, un tableur que vous tenez encore à côté, tout nous intéresse !").'</p>',
			'<div style="width: 6rem; margin-left: auto">'.\Asset::svg('main', 'friends/abeille.svg').'</div>'
		);

	}

	public function create(Survey $eSurvey, Structure $eStructure): string {

		$isUpdate = $eSurvey->notEmpty();

		$form = new \util\FormUi();

		$h = $form->openAjax('/structure/survey:'.($isUpdate ? 'doUpdate' : 'doCreate'));

			$h .= \util\FormUi::asteriskInfo();

			if($isUpdate) {
				$h .= $form->hidden('id', $eSurvey['id']);
			} else {
				$h .= $form->hidden('structure', $eStructure);
			}

			$h .= $form->group(
				s("Votre structure"),
				StructureUi::getLogo($eStructure, '3rem').'  <b>'.encode($eStructure['name']).'</b>'
			);

			foreach(Survey::UNIVERSES as $universe) {

				// Pas d'avis à donner sur un univers jamais ouvert : le bloc reste fermé tant qu'aucune
				// fréquence n'est choisie, et un fieldset désactivé n'envoie ni ne contrôle ses champs.
				$isClosed = ($isUpdate === FALSE or $eSurvey[$universe] === Survey::NEVER);

				$h .= $form->fieldset(
					self::getUniverseIcon($universe).'<span>'.self::getUniverseName($universe).'</span>',
					$form->dynamicGroup($eSurvey, $universe.'*').
					'<fieldset id="survey-'.$universe.'"'.($isClosed ? ' class="hide" disabled' : '').'>'.
						$form->dynamicGroups($eSurvey, [$universe.'Missing']).
					'</fieldset>',
					['class' => 'structure-survey-universe structure-survey-universe-'.$universe]
				);

			}

			$h .= $form->fieldset(
				s("Le logiciel dans son ensemble"),
				$form->dynamicGroups($eSurvey, ['useful', 'missing*', 'otherSoftware', 'comment'])
			);

			$h .= '<template id="structure-survey-missing-template">'.self::getMissingLine($form, NULL).'</template>';

			$h .= $form->group(
				content: $form->submit($isUpdate ? s("Enregistrer les modifications") : s("Envoyer"))
			);

		$h .= $form->close();

		return $h;

	}

	public function getPanel(Survey $eSurvey): \Panel {

		$h = '';

		foreach(Survey::model()->getProperties() as $property) {

			if(in_array($property, ['id', 'structure', 'createdAt'])) {
				continue;
			}

			$d = self::p($property);

			$h .= '<h5>'.$d->label.'</h5>';
			$h .= '<p>'.self::getValue($eSurvey, $property, $d).'</p>';

		}

		return new \Panel(
			id: 'panel-survey',
			title: StructureUi::getLogo($eSurvey['structure'], '2rem').' '.encode($eSurvey['structure']['name']),
			subTitle: s("Réponse reçue le {date}", ['date' => \util\DateUi::numeric($eSurvey['createdAt'], \util\DateUi::DATE)]),
			body: $h,
		);

	}

	protected static function getValue(Survey $eSurvey, string $property, \PropertyDescriber $d): string {

		$value = $eSurvey[$property];

		if($value === NULL) {
			return '<i>'.s("Sans réponse").'</i>';
		}

		if($d->enum !== NULL) {
			return $d->values[$value];
		}

		if($property === 'missing') {
			return implode('<br/>', array_map(fn(string $line) => encode($line), $value));
		}

		return nl2br(encode($value));

	}

	public static function getUniverseName(string $universe): string {

		return match($universe) {
			'production' => s("Production"),
			'commercialisation' => s("Vente"),
			'accounting' => s("Comptabilité")
		};

	}

	protected static function getUniverseIcon(string $universe): string {

		return match($universe) {
			'production' => \Asset::icon('leaf'),
			'commercialisation' => \Asset::icon('basket3'),
			'accounting' => \Asset::icon('piggy-bank')
		};

	}

	/**
	 * Les réponses fermées forment des échelles : chacune se lit d'un coup d'œil en icônes, d'où
	 * des vignettes de FormUi::choices() à la place de boutons radio. `$icons` donne pour chaque
	 * valeur son icône et la couleur qui en porte le sens. Publique parce que `$d->field` l'appelle
	 * depuis une closure que le describer lie à lui-même.
	 */
	public static function getChoices(\util\FormUi $form, Survey $eSurvey, string $property, array $values, array $icons, array $attributes = []): string {

		$choices = [];

		foreach($values as $value => $label) {

			[$icon, $color] = $icons[$value];

			$choices[$value] = [
				'label' => $label,
				'image' => '<span class="util-choice-icon '.$color.'">'.\Asset::icon($icon).'</span>',
				'attributes' => ['required' => TRUE],
			];

		}

		return $form->choices($property, $choices, $eSurvey->empty() ? NULL : $eSurvey[$property], $attributes);

	}

	/**
	 * Trois lignes vides pour une première réponse : une seule laisserait croire qu'on n'attend
	 * qu'un besoin. Publique pour la même raison que `getChoices()`.
	 */
	public static function getMissing(\util\FormUi $form, array $lines): string {

		if($lines === []) {
			$lines = [NULL, NULL, NULL];
		}

		$h = '<div id="structure-survey-missing" class="util-table util-table-bordered mb-1" data-max="'.Survey::MISSING_MAX.'">';

			foreach($lines as $line) {
				$h .= self::getMissingLine($form, $line);
			}

		$h .= '</div>';

		$h .= '<a id="structure-survey-missing-add" onclick="StructureSurvey.addMissing(this)" class="btn btn-outline-primary btn-sm'.(count($lines) >= Survey::MISSING_MAX ? ' hide' : '').'">'.\Asset::icon('plus-circle').' '.s("Ajouter une ligne").'</a>';

		return $h;

	}

	protected static function getMissingLine(\util\FormUi $form, ?string $line): string {

		$h = '<div class="util-table-row" data-missing>';
			$h .= '<div class="structure-survey-missing-rank"><span class="badge badge-solid bg-primary">'.s("Priorité").'</span></div>';
			$h .= '<div>'.$form->text('missing[]', $line, ['maxlength' => Survey::MISSING_LENGTH, 'placeholder' => s("Un besoin")]).'</div>';
			$h .= '<div class="structure-survey-missing-actions">';
				$h .= '<a onclick="StructureSurvey.upMissing(this)" class="btn btn-outline-primary structure-survey-missing-up">'.\Asset::icon('arrow-up').' '.s("Monter").'</a>';
				$h .= '<a onclick="StructureSurvey.removeMissing(this)" class="btn btn-outline-secondary">'.\Asset::icon('trash').' '.s("Retirer").'</a>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	protected static function getUniverseContent(string $universe): string {

		return match($universe) {
			'production' => s("Planning, plan de culture, assolement, semences et plants, récoltes, temps de travail."),
			'commercialisation' => s("Ventes, clients, produits, factures, boutiques en ligne, caisse enregistreuse, stocks."),
			'accounting' => s("Facturation électronique, précomptabilité, banque, cahiers de caisse, justificatifs, comptabilité.")
		};

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Survey::model()->describer($property, [
			'production' => s("À quelle fréquence utilisez-vous la production ?"),
			'productionMissing' => s("Qu'est-ce qui vous manque ou vous gêne dans la production ?"),
			'commercialisation' => s("À quelle fréquence utilisez-vous la vente ?"),
			'commercialisationMissing' => s("Qu'est-ce qui vous manque ou vous gêne dans la vente ?"),
			'accounting' => s("À quelle fréquence utilisez-vous la comptabilité ?"),
			'accountingMissing' => s("Qu'est-ce qui vous manque ou vous gêne dans la comptabilité ?"),
			'useful' => s("Qu'est-ce qui vous sert le plus ?"),
			'missing' => s("Qu'est-ce qui manque le plus au logiciel aujourd'hui ?"),
			'otherSoftware' => s("Quels outils utilisez-vous en dehors de {siteName}, et pour quoi faire ?"),
			'comment' => s("Avez-vous autre chose à nous dire ?"),
		]);

		switch($property) {

			case 'production' :
			case 'commercialisation' :
			case 'accounting' :
				$d->values = [
					Survey::DAILY => s("Tous les jours"),
					Survey::WEEKLY => s("Toutes les semaines"),
					Survey::OCCASIONAL => s("De temps en temps"),
					Survey::STOPPED => s("J'ai arrêté"),
					Survey::NEVER => s("Jamais"),
				];
				$d->labelAfter = \util\FormUi::info(self::getUniverseContent($property));
				$d->field = fn(\util\FormUi $form, Survey $e) => SurveyUi::getChoices($form, $e, $property, $d->values, [
					Survey::DAILY => ['reception-4', 'color-primary'],
					Survey::WEEKLY => ['reception-3', 'color-primary'],
					Survey::OCCASIONAL => ['reception-1', 'color-primary'],
					Survey::STOPPED => ['pause-circle', 'color-muted'],
					Survey::NEVER => ['x-circle', 'color-muted'],
				], [
					'onchange' => 'const block = document.getElementById("survey-'.$property.'"); block.disabled = (event.target.value === '.json_encode(Survey::NEVER).'); block.classList.toggle("hide", block.disabled);',
				]);
				break;

			case 'productionMissing' :
			case 'commercialisationMissing' :
			case 'accountingMissing' :
				$d->labelAfter = \util\FormUi::info(s("Une fonctionnalité absente, un geste trop long, une erreur qui revient. Si vous avez arrêté, dites-nous pourquoi."));
				break;

			case 'missing' :
				$d->labelAfter = \util\FormUi::info(s("Un besoin par ligne. Placez en premier celui que nous devrions développer avant tous les autres."));
				$d->field = fn(\util\FormUi $form, Survey $e) => SurveyUi::getMissing($form, $e->empty() ? [] : $e['missing']);
				break;

			case 'otherSoftware' :
				$d->labelAfter = \util\FormUi::info(s("Tableur, carnet, logiciel de caisse, de comptabilité ou de vente en ligne."));
				break;

		}

		if($d->type === 'text16') {
			[, $max] = Survey::model()->getPropertyRange($property);
			$d->attributes['maxlength'] = $max;
		}

		$d->required = (
			$d->type === 'enum' or
			$property === 'missing'
		);

		return $d;

	}

}
?>