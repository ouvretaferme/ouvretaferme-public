<?php
namespace structure;

/**
 * Le contenu des interstitiels.
 *
 * Un panel du framework et non une modale maison : fermeture, fond, pile et
 * navigation arrière viennent avec, et l'écran ressemble au reste du produit.
 *
 * Chaque interstitiel est une méthode, et `get()` fait l'aiguillage. Le `match`
 * n'a pas de `default` : un nom inconnu n'arrive jamais jusqu'ici, la liste des
 * noms servis étant tenue par `InterstitialLib::getNames()`.
 */
class InterstitialUi {

	public function __construct() {
		\Asset::css('structure', 'interstitial.css');
	}

	public function get(string $name, Structure $eStructure): \Panel {

		return match($name) {
			InterstitialLib::SIGNUP_QUOTATION => $this->getSignUpQuotation($eStructure),
		};

	}

	/**
	 * L'arrivée par les relevés de prix.
	 *
	 * Cette personne n'a pas cherché un logiciel : elle a cherché un prix. Le
	 * message ne vend donc rien et ne demande rien — il énumère ce qui est déjà là,
	 * en quelques mots, et laisse le bouton de sortie mener aux relevés de prix plutôt
	 * qu'à une visite guidée dont elle n'a pas envie maintenant.
	 */
	private function getSignUpQuotation(Structure $eStructure): \Panel {

		$body = \main\DesignUi::getWelcome(
			s("Votre ferme est créée sur {siteName}, vos prix sont enregistrés."),
			'<p>'.s("{siteName} ne s'arrête pas aux relevés de prix.<br/>Tout ce qui suit est compris pour votre ferme !").'</p>',
			\Asset::svg('main', 'friends/abeille.svg')
		);

		$body .= '<div class="util-cards util-cards-2 interstitial-features">';

			foreach($this->getFeatures($eStructure) as ['icon' => $icon, 'domain' => $domain, 'title' => $title, 'text' => $text, 'url' => $url]) {
				// L'interstitiel se referme de lui-même en suivant le lien, la navigation dépilant la
				// couche.
				$body .= \main\DesignUi::getDeckEntry($url, $icon, $domain, $title, $text);
			}

		$body .= '</div>';

		$body .= '<div class="interstitial-buttons">';
			$body .= '<a onclick="Lime.Panel.closeLast()" class="btn btn-xl btn-primary">'.s("Voir les relevés de prix").'</a>';
		$body .= '</div>';

		return new \Panel(
			id: 'panel-interstitial-signup-quotation',
			title: s("Bienvenue sur {siteName} !"),
			body: $body
		);

	}

	/**
	 * Ce qu'on cite, et dans cet ordre : le plus proche du geste qui vient d'être
	 * fait d'abord. Quatre entrées et pas douze — une liste exhaustive se lit comme
	 * une brochure et ne se retient pas.
	 *
	 * La comptabilité sort de la liste sans accounting/, et non à l'affichage : `urlOverview()` lit
	 * l'exercice de la comptabilité.
	 */
	private function getFeatures(Structure $eStructure): array {

		return [
			[
				'icon' => 'shop',
				'domain' => 'commercialisation',
				'title' => s("Vendez en ligne"),
				'text' => s("Ouvrez une boutique pour vos clients particuliers ou professionnels, c'est gratuit et illimité."),
				'url' => StructureUi::urlSellingShop($eStructure),
			],
			[
				'icon' => 'receipt',
				'domain' => 'commercialisation',
				'title' => s("Facturez"),
				'text' => s("Devis, bons de livraison, factures électroniques, c'est gratuit et illimité."),
				'url' => StructureUi::urlSellingSales($eStructure),
			],
			...(StructureSetting::hasAccountingApp() ? [
				[
					'icon' => 'piggy-bank',
					'domain' => 'accounting',
					'title' => s("Tenez votre comptabilité"),
					'text' => s("Import bancaire, rapprochement, TVA et bilan, jusqu'au compte de résultat."),
					// Sans condition sur la ferme : une ferme dont la comptabilité n'est pas ouverte est
					// redirigée par le produit lui-même vers « Découvrir la comptabilité », et
					// l'URL tient sans exercice comptable — `urlFinancialYear()` n'ajoute le
					// segment que s'il en existe un.
					'url' => StructureUi::urlOverview($eStructure),
				],
			] : []),
			[
				'icon' => 'calendar3',
				'domain' => 'production',
				'title' => s("Planifiez vos cultures"),
				'text' => s("Plan de culture, assolement, planning de production et suivi du temps de travail."),
				'url' => StructureUi::urlCultivationSeries($eStructure),
			],
		];

	}

}
?>
