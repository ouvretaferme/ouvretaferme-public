<?php
namespace communication;

/**
 * Les notes de l'équipe Ouvretaferme, côté administration : la rédaction (R4, N19 à N24) et la liste
 * des envois (R3-B, une carte par envoi).
 *
 * Sans suffixe Ai : le cadriciel cherche le describer d'une entité dans `<Entité>Ui::p()`.
 */
class NoteUi {

	public function __construct() {

		\Asset::css('communication', 'noteAi.css');
		\Asset::js('communication', 'noteAi.js');

	}

	public function getList(\Collection $cNote): string {

		$h = '<div class="util-title">';
			$h .= '<h1>'.s("Notifications envoyées").'</h1>';
			$h .= '<a href="/communication/admin/noteAi:create" class="btn btn-outline-primary">'.\Asset::icon('plus-circle').' '.s("Rédiger une notification").'</a>';
		$h .= '</div>';

		if($cNote->empty()) {

			$h .= '<div class="util-block util-block-empty">';
				$h .= '<h3>'.s("Aucune notification envoyée").'</h3>';
				$h .= '<p>'.s("Une notification rédigée ici part chez les administrateurs des structures que vous choisissez.").'</p>';
			$h .= '</div>';

		} else {

			foreach($cNote as $eNote) {
				$h .= $this->getCard($eNote);
			}

		}

		$h .= '<div class="util-info">'.s("Une notification reste {value} jours chez ses destinataires. Cette liste garde tous les envois.", \notification\NotificationSetting::DAYS).'</div>';

		return $h;

	}

	private function getCard(Note $eNote): string {

		$h = '<div class="util-deck communication-note-card mb-1">';
			$h .= '<div class="util-deck-head">';
				$h .= '<span class="util-icon util-icon-brand">'.\Asset::svg('main', 'svg/logo-otf.svg').'</span>';
				$h .= '<div class="util-deck-body">';
					$h .= '<div class="util-deck-title">'.encode($eNote['title']).'</div>';
					$h .= '<div class="communication-note-content">'.new \editor\EditorUi()->value($eNote['content']).'</div>';
					$h .= '<div class="util-deck-figures">';
						$h .= $this->getFigure(s("Envoyée le"), \util\DateUi::numeric($eNote['createdAt'], \util\DateUi::DATE));
						$h .= $this->getFigure(s("Structures"), \util\TextUi::number($eNote['structures']));
						$h .= $this->getFigure(s("Administrateurs"), \util\TextUi::number($eNote['users']));
						// Une note sans bouton est lue dès qu'elle est vue : ses deux nombres sont égaux
						if($eNote['buttonLabel'] !== NULL and $eNote['views'] !== NULL) {
							$h .= $this->getFigure(s("Vue par"), \util\TextUi::number($eNote['views']));
						}
						if($eNote['reads'] !== NULL) {
							$h .= $this->getFigure(s("Lue par"), \util\TextUi::number($eNote['reads']));
						}
						$h .= $this->getFigure(s("Chez les membres jusqu'au"), \util\DateUi::numeric($eNote['until'], \util\DateUi::DATE));
					$h .= '</div>';
				$h .= '</div>';
			$h .= '</div>';
			$h .= '<div class="util-deck-band">'.$this->getCriteria($eNote).'</div>';
		$h .= '</div>';

		return $h;

	}

	private function getFigure(string $key, string $value): string {
		return '<div class="util-deck-figure"><span class="util-deck-key">'.$key.'</span><span class="util-deck-value">'.$value.'</span></div>';
	}

	/**
	 * Les critères de l'envoi en une ligne : les types cochés, puis les fonctionnalités, qu'une
	 * structure devait toutes utiliser (D10-A).
	 */
	private function getCriteria(Note $eNote): string {

		$labelsType = self::p('structureTypes')->values;
		$labelsFeature = self::p('features')->values;

		$h = implode(', ', array_map(fn(string $structureType) => $labelsType[$structureType], $eNote['structureTypes']));

		if($eNote['features'] !== []) {
			$h .= ' · '.s("utilise {features}", ['features' => $this->enumerate(array_map(fn(string $feature) => $labelsFeature[$feature], $eNote['features']))]);
		}

		return $h;

	}

	private function enumerate(array $labels): string {

		$last = array_pop($labels);

		if($labels === []) {
			return $last;
		}

		return s("{list} et {last}", ['list' => implode(', ', $labels), 'last' => $last]);

	}

	/**
	 * Le formulaire de rédaction, en trois notions (N19 à N24) : à qui, le message, et l'aperçu tel que
	 * les destinataires le liront. L'aperçu et les chiffres se recalculent à la saisie
	 * (`CommunicationNoteAi`), « Envoyer » ouvre la confirmation chiffrée.
	 */
	public function create(Note $eNote, \notification\Notification $eNotificationPreview, array $recipients): string {

		$form = new \util\FormUi();

		$h = '<div class="util-title">';
			$h .= '<h1>'.s("Rédiger une notification").'</h1>';
		$h .= '</div>';

		$h .= $form->openAjax('/communication/admin/noteAi:send', [
			'id' => 'communication-note-create',
			'data-preview' => '/communication/admin/noteAi:preview',
			'onrender' => 'CommunicationNoteAi.init(this)'
		]);

			$h .= $form->fieldset(
				s("À qui"),
				$form->dynamicGroup($eNote, 'structureTypes*').
				$form->dynamicGroup($eNote, 'features').
				$form->group(s("Destinataires"), $this->getRecipients($recipients)),
				['class' => 'form-fieldset']
			);

			$h .= $form->fieldset(
				s("Le message"),
				$form->dynamicGroups($eNote, ['title*', 'content*', 'buttonLabel', 'buttonPath']),
				['class' => 'form-fieldset']
			);

			$h .= $form->fieldset(
				s("Aperçu"),
				'<div id="communication-note-preview">'.$this->getPreview($eNotificationPreview).'</div>',
				['class' => 'form-fieldset']
			);

			$h .= $form->group(content: $form->submit(s("Envoyer")));

		$h .= $form->close();

		return $h;

	}

	/**
	 * Les administrateurs sont comptés une fois, même s'ils administrent plusieurs structures : c'est
	 * le nombre de personnes qui liront la note (N21).
	 */
	public function getRecipients(array $recipients): string {

		$h = '<div id="communication-note-recipients" class="util-figures communication-note-recipients">';
			$h .= '<div class="util-figure">';
				$h .= '<div class="util-figure-value">'.\util\TextUi::number($recipients['structures']).'</div>';
				$h .= '<div class="util-figure-label">'.p("Structure", "Structures", $recipients['structures']).'</div>';
			$h .= '</div>';
			$h .= '<div class="util-figure">';
				$h .= '<div class="util-figure-value">'.\util\TextUi::number($recipients['users']).'</div>';
				$h .= '<div class="util-figure-label">'.p("Administrateur", "Administrateurs", $recipients['users']).'</div>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	public function getPreview(\notification\Notification $eNotification): string {
		return new \notification\NotificationUi()->getCommonPreview($eNotification);
	}

	/**
	 * La confirmation chiffrée (N24). Le bouton reprend le formulaire resté sous le panneau
	 * (`CommunicationNoteAi.send()`) : la note n'existe pas encore, il n'y a rien d'autre à poster.
	 */
	public function send(array $recipients, string $until): \Panel {

		return \main\DesignUi::getConfirmPanel(
			'panel-communication-note-send',
			s("Envoyer la notification"),
			p("Aux administrateurs d'une structure", "Aux administrateurs de {value} structures", $recipients['structures']),
			p("La notification partira chez un administrateur", "La notification partira chez les {value} administrateurs", $recipients['users']).' '.p("d'une structure.", "de {value} structures.", $recipients['structures']).' '.s("Elle restera {days} jours chez eux, jusqu'au {date}.", ['days' => \notification\NotificationSetting::DAYS, 'date' => \util\DateUi::textual($until, \util\DateUi::DATE)]),
			'<a onclick="CommunicationNoteAi.send(this)" class="btn btn-primary" data-waiter="'.s("Envoi en cours...").'">'.s("Envoyer la notification").'</a>'
		);

	}

	/**
	 * Les types et leurs familles, dans l'ordre de `CommunicationSetting::NOTE_STRUCTURE_TYPES`, qui
	 * tient la correspondance avec les colonnes de `Structure`.
	 */
	public static function getStructureTypeFamilies(): array {

		return [
			s("Production") => ['producer-real', 'producer-installation', 'producer-test', 'producer-school', 'producer-learner', 'producer-project'],
			s("Commercialisation") => ['shop', 'amap'],
			s("Groupes") => ['community-producer', 'community-education', 'community-network'],
		];

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Note::model()->describer($property, [
			'title' => s("Titre"),
			'content' => s("Texte"),
			'buttonLabel' => s("Libellé du bouton"),
			'buttonPath' => s("Page du bouton"),
			'structureTypes' => s("Types de structure"),
			'features' => s("Utilise toutes ces fonctionnalités"),
		]);

		switch($property) {

			case 'buttonLabel' :
				$d->labelAfter = ' <span class="util-annotation">'.s("facultatif").'</span>';
				$d->placeholder = s("Connecter ma banque");
				break;

			case 'buttonPath' :
				$d->labelAfter = ' <span class="util-annotation">'.s("facultatif").'</span>';
				$d->placeholder = '/x/banque/operations';
				break;

			// Les libellés sont ceux du describer de `Structure`, pour qu'une catégorie se nomme ici
			// comme sur l'écran de la structure.
			case 'structureTypes' :

				$d->values = [];

				foreach(CommunicationSetting::NOTE_STRUCTURE_TYPES as $key => [$type, $column, $value]) {

					$d->values[$key] = match($column) {
						'producerType' => \structure\StructureUi::p('producerType')->displayValues[$value],
						'communityType' => \structure\StructureUi::p('communityType')->values[$value],
						NULL => match($type) {
							\structure\Structure::SHOP => s("Boutique"),
							\structure\Structure::AMAP => s("AMAP")
						}
					};

				}

				// Le nombre de structures actives de chaque type, posé par la page (N19).
				$d->field = function(\util\FormUi $form, Note $e) use($d) {

					$h = '';

					foreach(NoteUi::getStructureTypeFamilies() as $family => $structureTypes) {

						$values = [];

						foreach($structureTypes as $structureType) {
							$values[$structureType] = $d->values[$structureType].'<span class="util-annotation"> '.\util\TextUi::number($e['structureTypeCounts'][$structureType]).'</span>';
						}

						$h .= '<div class="communication-note-family">';
							$h .= '<div class="util-label">'.$family.'</div>';
							$h .= $form->checkboxes('structureTypes[]', $values, $e['structureTypes'], ['columns' => 2, 'all' => FALSE]);
						$h .= '</div>';

					}

					return $h;

				};

				break;

			case 'features' :

				$d->values = [
					'hasCultivations' => s("Cultures"),
					'hasSales' => s("Ventes"),
					'hasShops' => s("Boutique en ligne"),
					'hasInvoices' => s("Facturation"),
					'hasStock' => s("Stocks"),
					'hasWallet' => s("Cagnotte"),
					'hasQuotation' => s("Mercuriale"),
					'hasPurchases' => s("Achats"),
					'hasAccounting' => s("Comptabilité"),
					'hasFinancialYears' => s("Exercices comptables"),
					'hasPdp' => s("E-facturation"),
					'hasEnableBanking' => s("Connexion bancaire"),
				];

				$d->field = fn(\util\FormUi $form, Note $e) => $form->checkboxes('features[]', $d->values, $e['features'], ['columns' => 3, 'all' => FALSE]);

				break;

		}

		return $d;

	}

}
?>
