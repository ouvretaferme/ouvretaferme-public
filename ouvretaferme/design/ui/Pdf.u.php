<?php
namespace design;

/**
 * La charte des documents imprimés, montrée dans le design system.
 *
 * Les briques sont rendues par des iframes qui pointent sur le gabarit des PDF
 * (`/pdf/sample`) : ce qui s'affiche ici sort de `main\PdfUi` et de
 * `main/static/css/pdf.css`, pas d'une imitation écrite pour la démonstration.
 * Recopier la charte dans cette page l'aurait fait diverger au premier changement,
 * et une page de design system qui ment est pire que pas de page du tout.
 */
class PdfUi {

	public function __construct() {
		\Asset::css('design', 'designAi.css');
	}

	/**
	 * L'inventaire des documents produits par le site.
	 *
	 * Relevé sur le code : les vues qui déclarent un `PdfTemplate` et les appels à
	 * `PdfLib::build()`. La liste vit dans l'UI parce qu'elle décrit la charte et
	 * non des données : rien ici n'est en base.
	 */
	public static function getDocuments(): array {

		return [
			[s("Comptabilité"), [
				[s("Bilan de clôture"), 'overview/pdf/balanceSheet', 'structure', s("en regard")],
				[s("Bilan de clôture détaillé"), 'overview/pdf/balanceSheet', 'structure', s("empilé")],
				[s("Bilan de clôture provisoire"), 'overview/pdf/balanceSheet', 'structure', s("en regard")],
				[s("Bilan d'ouverture"), 'overview/pdf/balanceSheet', 'structure', s("empilé")],
				[s("Bilan d'ouverture détaillé"), 'overview/pdf/balanceSheet', 'structure', s("empilé")],
				[s("Compte de résultat"), 'overview/pdf/incomeStatement', 'structure', s("en regard")],
				[s("Compte de résultat détaillé"), 'overview/pdf/incomeStatement', 'structure', s("en regard")],
				[s("Soldes intermédiaires de gestion"), 'overview/pdf/sig', 'structure', ''],
				[s("Dossier de gestion"), 'overview/pdf/managementFileAi', 'structure', s("sommaire")],
				[s("Balance"), 'journal/pdf:balance', 'structure', ''],
				[s("Balance détaillée"), 'journal/pdf:balance', 'structure', ''],
				[s("Grand-livre"), 'journal/pdf/book', 'structure', ''],
				[s("Amortissements"), 'asset/pdf:amortization', 'structure', ''],
				[s("Acquisitions"), 'asset/pdf:acquisition', 'structure', ''],
				[s("Simulation des amortissements"), 'asset/pdf:projection', 'structure', ''],
				[s("Attestation de conformité FEC"), 'financialYear/pdf:attestation', 'structure', ''],
				[s("Facture électronique"), 'invoicing/pdf:get', 'none', s("norme")],
			]],
			[s("Ventes et relevés de prix"), [
				[s("Relevés de prix"), 'quotation/pdf', 'brand', ''],
				[s("Facture"), 'selling/pdf:getInvoice', 'none', s("document client")],
				[s("Justificatif de vente"), 'selling/pdf:getProof', 'none', s("document client")],
				[s("Devis, bon de commande"), 'selling/pdf:getDocument', 'none', s("document client")],
				[s("Bon de livraison"), 'selling/pdf:getDocument', 'none', s("document client")],
				[s("Étiquettes"), 'selling/pdf:getLabels', 'none', s("format libre")],
				[s("Ticket de caisse"), 'selling/market:pdf', 'none', s("format rouleau")],
				[s("Export de ventes"), 'selling/sale:getExport', 'nofooter', ''],
				[s("Récapitulatif de boutique"), 'shop/date:getSales', 'nofooter', ''],
			]],
			[s("Production et association"), [
				[s("Plan de culture"), 'series/series:getCultivation', 'structure', ''],
				[s("Plan d'assolement"), 'series/series:getSoil', 'structure', ''],
				[s("Plan de la ferme"), 'map/map:getPlan', 'structure', ''],
				[s("Planning de travail"), 'work/planning:getWeeklyPdf', 'structure', ''],
				[s("Reçu d'adhésion"), 'association/pdf', 'brandonly', ''],
			]],
		];

	}

	private static function getCaseLabel(string $case): string {

		return match($case) {
			'structure' => s("Document de structure"),
			'brand' => s("Document sans structure"),
			'brandonly' => s("En-tête seul"),
			'nofooter' => s("Récapitulatif interne"),
			'none' => s("Hors charte"),
		};

	}

	public function get(array $documents): string {

		$h = '<div class="design-pdf">';

		$h .= '<h2>'.s("Le châssis").'</h2>';

		$h .= '<p>'.s("Six briques, dans <code>main\PdfUi</code>. Un document les assemble, il n'en redessine aucune. Ce qui suit est rendu par le gabarit des PDF lui-même, dans une iframe : la page du design system ne réécrit pas la charte, elle l'affiche.").'</p>';

		$h .= $this->getBrick(
			'brand',
			s("En-tête de marque"),
			s("<code>getHeader()</code> : la marque en haut à gauche, identique partout. Le SVG suit <code>currentColor</code>, donc la même marque sert en couleur et en réserve sur un aplat."),
			'4.5rem'
		);

		$h .= $this->getBrick(
			'structure',
			s("Bloc de la structure"),
			s("<code>getStructure(\$eStructure, \$title, \$meta)</code> : à qui appartient le document, et lequel est-ce. Le logo s'affiche s'il existe, le titre et la période se lisent d'un seul regard. Le titre est du texte brut et se fait encoder ici ; la méta est du HTML, assemblée par l'appelant."),
			'13rem'
		);

		$h .= $this->getBrick(
			'title',
			s("Titre sans structure"),
			s("<code>getTitle(\$title, \$lede)</code> : pour un document qui n'appartient à aucune ferme. Il prend la place laissée libre par le bloc central, à gauche et en grand."),
			'11rem'
		);

		$h .= $this->getBrick(
			'figures',
			s("Bande de chiffres"),
			s("<code>getFigures([[valeur, libellé], …])</code> : ce qu'on retient sans lire. Trois entrées est le bon compte : deux ne font pas une bande, quatre ne se retiennent plus."),
			'8.5rem'
		);

		$h .= $this->getBrick(
			'toc',
			s("Sommaire"),
			s("<code>getToc([[titre, [libellé, …]], …])</code> : ce que contient un document de plusieurs feuilles, sous sa bande de chiffres. Une section par titre, et les feuilles numérotées dans l'ordre où on les tourne."),
			'16rem'
		);

		$h .= $this->getBrick(
			'footer',
			s("Pied de page"),
			s("<code>getFooter(\$eStructure, \$note, \$page)</code> : qui édite, de qui c'est le document, où l'on en est. La grille compte ses colonnes : une colonne vide laisserait un tiers de la largeur en attente de rien. Les trois variantes sont montrées dans cet ordre : trois colonnes, deux, une."),
			'17rem'
		);

		$h .= '<p>'.s("La page elle-même n'est pas une brique. <code>getPage(\$margin, \$landscape)</code> pose son format A4, en portrait ou en paysage, et ses marges, 1 cm si le document n'en demande pas d'autres. Le gabarit ne réserve que le bas de la page, pour le pied paginé, donc chaque document pose les siennes par <code>getPage()</code>.").'</p>';

		$h .= '<h2>'.s("Les cinq cas").'</h2>';

		$h .= '<p>'.s("Ce qui distingue les documents n'est pas leur habillage mais deux questions : appartiennent-ils à une structure, et sortent-ils de chez nous ?").'</p>';

		$h .= '<div class="util-cards util-cards-2">';

			$h .= $this->getCase(
				s("Document de structure"),
				s("En-tête, bloc de la structure, bande de chiffres si le document a trois chiffres à retenir, pied à deux colonnes. C'est le cas courant : toute la comptabilité, les plans de production, le planning."),
				'design-pdf-case-structure'
			);

			$h .= $this->getCase(
				s("Document sans structure"),
				s("En-tête, titre à gauche, pied à une colonne. Le document est publié par Ouvretaferme et n'appartient à aucune ferme, le pied perd donc sa colonne d'identité. Un seul document aujourd'hui : les relevés de prix."),
				'design-pdf-case-brand'
			);

			$h .= $this->getCase(
				s("En-tête seul"),
				s("La marque, et rien d'autre de la charte. Le reste du document porte déjà son émetteur et son destinataire, comme une facture. Le reçu d'adhésion à l'association."),
				'design-pdf-case-brandonly'
			);

			$h .= $this->getCase(
				s("Récapitulatif interne"),
				s("En-tête et bloc de la structure, sans pied. Ces documents se lisent en atelier et repartent à la poubelle le soir : ni mention d'édition, ni pagination. L'export de ventes et le récapitulatif de boutique."),
				'design-pdf-case-nofooter'
			);

			$h .= $this->getCase(
				s("Hors charte"),
				s("Aucune brique. Trois raisons, jamais l'oubli : le document part chez un client et ne porte donc pas notre marque (facture, devis, bon de livraison, justificatif de vente) ; sa mise en forme suit une norme (facture électronique) ; son format n'est pas une page (étiquettes, ticket de caisse en rouleau)."),
				'design-pdf-case-none'
			);

		$h .= '</div>';

		$h .= '<h2>'.s("Le tableau").'</h2>';

		$h .= $this->getBrick(
			'table',
			s("Tableau bordé"),
			s("<code>&lt;table class=\"pdf-table-bordered\"&gt;</code>, et <code>pdf-tr-title</code> sur la ligne qui ouvre un groupe. Un filet sépare les lignes, l'en-tête passe en capitales et un trait noir ferme le tableau. Les documents comptables et les attestations de formation le partagent, ses règles vivent donc dans la feuille des PDF et non dans celle d'un package."),
			'15rem'
		);

		$h .= '<h2>'.s("Deux dispositions").'</h2>';

		$h .= '<p>'.s("Un document fait de deux côtés qui s'équilibrent les met en regard ou les empile, et le choix se fait sur ce qu'on vient y chercher.").'</p>';

		$h .= '<div class="util-cards util-cards-2">';

			$h .= $this->getCase(
				s("En regard"),
				s("Un seul tableau, <code>&lt;table class=\"pdf-table-bordered pdf-facing-table\"&gt;</code>, dont chaque ligne porte ses deux moitiés, la seconde ouverte par une cellule <code>pdf-facing-split</code>. Deux tableaux côte à côte ne tombent jamais en face l'un de l'autre : les côtés n'ont pas le même nombre de lignes, et le total de l'un s'imprime au milieu du détail de l'autre alors que les deux portent le même montant. On complète de cellules vides le côté qui a le moins de postes. Chaque côté n'ayant qu'une demi-page, il ne garde que ses colonnes essentielles."),
				'design-pdf-case-structure'
			);

			$h .= $this->getCase(
				s("Empilé"),
				s("Les deux côtés à la suite, en pleine largeur. C'est la disposition des documents dont l'objet est le détail par compte : les libellés y sont longs et les colonnes réglementaires reviennent."),
				'design-pdf-case-structure'
			);

		$h .= '</div>';

		$h .= '<h2>'.s("Les règles").'</h2>';

		$h .= '<ul class="design-pdf-rules">';
			$h .= '<li>'.s("<b>Tout est en A4 portrait.</b> Une liasse se range et se feuillette d'un seul geste ; deux orientations obligent à tourner la pile à chaque changement de document.").'</li>';
			$h .= '<li>'.s("<b>La pagination ne s'écrit pas dans le flux.</b> Un numéro posé dans le document se répète à l'identique sur toutes les pages, puisque le HTML ignore où il sera coupé. Elle passe par le gabarit de pied du navigateur sans tête, que <code>main\PdfLib::build()</code> accepte, comme le constructeur des PDF de la comptabilité.").'</li>';
			$h .= '<li>'.s("<b>Le préfixe <code>pdf-</code> est partagé.</b> La charte occupe <code>pdf-brand</code>, <code>pdf-structure*</code>, <code>pdf-title*</code>, <code>pdf-figure*</code>, <code>pdf-footer*</code>, <code>pdf-facing*</code>, <code>pdf-table-bordered</code>, <code>pdf-tr-title</code>, <code>pdf-equality</code> et <code>pdf-note</code> ; les packages gardent <code>pdf-document-*</code> et leurs classes métier. Une charte écrasée dans la moitié des documents seulement se cherche longtemps.").'</li>';
			$h .= '<li>'.s("<b>Une taille de logo se donne en centimètres.</b> <code>MediaUi::convertToFormat()</code> connaît px, rem, cm et %, pas mm : un <code>17mm</code> y lève « Invalid size format », et seulement en production.").'</li>';
			$h .= '<li>'.s("<b>Un chiffre affiché doit se retrouver à l'écran.</b> Les documents et les pages partagent leurs bibliothèques mais plus leur rendu : c'est au tirage qu'on vérifie, pas à la lecture du code.").'</li>';
		$h .= '</ul>';

		$h .= '<h2>'.s("Les documents du site").'</h2>';

		$h .= $this->getDocumentsTable($documents);

		$h .= '</div>';

		return $h;

	}

	private function getBrick(string $case, string $title, string $description, string $height): string {

		$h = '<div class="design-pdf-brick">';

			$h .= '<h3>'.encode($title).'</h3>';
			$h .= '<p>'.$description.'</p>';

			$h .= '<iframe class="design-pdf-frame" src="/pdf:sample?case='.encode($case).'" style="height: '.$height.'" title="'.encode($title).'"></iframe>';

		$h .= '</div>';

		return $h;

	}

	private function getCase(string $title, string $description, string $class): string {

		$h = '<div class="util-card '.$class.'">';
			$h .= '<div class="util-card-title">'.encode($title).'</div>';
			$h .= '<p>'.$description.'</p>';
		$h .= '</div>';

		return $h;

	}

	private function getDocumentsTable(array $documents): string {

		$h = '<table class="tr-even">';

			$h .= '<thead>';
				$h .= '<tr>';
					$h .= '<th>'.s("Document").'</th>';
					$h .= '<th>'.s("Route").'</th>';
					$h .= '<th>'.s("Cas").'</th>';
					$h .= '<th>'.s("Note").'</th>';
				$h .= '</tr>';
			$h .= '</thead>';

			$h .= '<tbody>';

				foreach($documents as [$group, $lines]) {

					$h .= '<tr>';
						$h .= '<th colspan="4">'.encode($group).'</th>';
					$h .= '</tr>';

					foreach($lines as [$label, $route, $case, $note]) {

						$h .= '<tr>';
							$h .= '<td>'.encode($label).'</td>';
							$h .= '<td><code>'.encode($route).'</code></td>';
							$h .= '<td><span class="design-pdf-tag design-pdf-tag-'.$case.'">'.encode(self::getCaseLabel($case)).'</span></td>';
							$h .= '<td>'.encode($note).'</td>';
						$h .= '</tr>';

					}

				}

			$h .= '</tbody>';

		$h .= '</table>';

		return $h;

	}

	/**
	 * Le rendu d'une brique dans le gabarit des PDF.
	 *
	 * La structure est celle du décor des tests, les montants ceux d'un document réel,
	 * écrits ici plutôt que lus en base. Une page de design system qui
	 * interroge la comptabilité d'une ferme pour se dessiner elle-même ne s'affiche
	 * plus le jour où cette ferme n'existe pas.
	 */
	public function getSample(string $case): string {

		$eStructure = new \structure\Structure([
			'legalName' => 'EARL des Quatre Saisons',
			'name' => 'Ferme des Quatre Saisons',
			'logo' => NULL,
		]);

		return match($case) {

			'brand' => \main\PdfUi::getHeader(),

			'structure' => \main\PdfUi::getStructure(
				$eStructure,
				s("Bilan de clôture"),
				s("Exercice 2025 du 01/01/2025 au 31/12/2025 · SIRET 00000003400017")
			),

			'title' => \main\PdfUi::getTitle(
				s("Les prix du moment, juillet 2026"),
				s("vente directe, prix TTC · France · tous modes de production")
			),

			'figures' => \main\PdfUi::getFigures([
				['185 037 €', s("Total du bilan")],
				['21 842 €', s("Capitaux propres")],
				['17 101 €', s("Trésorerie")],
			]),

			'toc' => \main\PdfUi::getToc([
				[s("Sommaire"), [s("L'année en chiffres"), s("Où est passé l'argent"), s("Ce que l'exploitant a gagné")]],
				[s("Annexes"), [s("Bilan"), s("Compte de résultat")]],
			]),

			'table' => '<table class="pdf-table-bordered">'
				.'<thead><tr><th>'.s("Compte").'</th><th>'.s("Libellé").'</th><th class="text-end">'.s("Débit").'</th><th class="text-end">'.s("Crédit").'</th></tr></thead>'
				.'<tbody>'
				.'<tr class="pdf-tr-title"><td colspan="4">'.s("Charges").'</td></tr>'
				.'<tr><td>601</td><td>'.s("Achats de semences et plants").'</td><td class="text-end">4 812 €</td><td></td></tr>'
				.'<tr><td>606</td><td>'.s("Achats non stockés").'</td><td class="text-end">1 245 €</td><td></td></tr>'
				.'<tr class="pdf-tr-title"><td colspan="4">'.s("Produits").'</td></tr>'
				.'<tr><td>701</td><td>'.s("Ventes de produits finis").'</td><td></td><td class="text-end">38 410 €</td></tr>'
				.'</tbody>'
				.'</table>',

			'footer' => \main\PdfUi::getFooter($eStructure, note: s("Document établi le 20/08/2026, non attesté par un expert-comptable."), page: s("Page 1"))
				.\main\PdfUi::getFooter($eStructure, note: s("Document établi le 20/08/2026, non attesté par un expert-comptable."))
				.\main\PdfUi::getFooter(note: s("Prix arrêtés au 31/07/2026, relevés déclaratifs sans valeur contractuelle.")),

		};

	}

}
?>
