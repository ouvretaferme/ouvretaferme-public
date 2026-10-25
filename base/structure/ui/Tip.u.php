<?php
namespace structure;

class TipUi {

	/**
	 * Une astuce qui présente une fonctionnalité se pose en bande util-start ; une aide posée sur un écran
	 * déjà rempli, en util-block-info, que le design system réserve à ce cas. L'identifiant porte le nom
	 * de l'astuce : un panneau ouvert sur une page qui a déjà la sienne en montre deux, et « Masquer »
	 * retire celle qu'on a cliquée.
	 */
	public function getTip(Structure $eStructure, string $tip): string {

		$content = $this->getContent($eStructure, $tip);

		$buttons = '<div class="flex-buttons">';
			$buttons .= $content['buttons'];
			$buttons .= $this->getHelp($tip) ?? '';
			$buttons .= '<a data-ajax="/structure/tip:close?id='.$tip.'" data-ajax-method="get" class="btn btn-outline-primary">'.\Asset::icon('x-lg').' '.s("Masquer").'</a>';
		$buttons .= '</div>';

		if($content['info']) {

			$h = '<div id="tip-'.$tip.'" class="util-block-info">';
				$h .= '<h4>'.$content['title'].'</h4>';
				$h .= $content['content'];
				$h .= $buttons;
			$h .= '</div>';

		} else {

			$h = '<div id="tip-'.$tip.'" class="util-start-wrapper">';
				$h .= new \main\DesignUi()->getStart(
					icon: $content['icon'],
					title: $content['title'],
					content: $content['content'],
					image: $content['image'] ? \Asset::image('structure', 'tip/'.$tip.'.png') : NULL,
					buttons: $buttons
				);
			$h .= '</div>';

		}

		return $h;

	}

	/**
	 * Le bouton qui mène à l'aide ou à la ressource de l'astuce, par `/structure/tip:click`, qui compte le
	 * clic puis redirige vers `TipLib::getLink()`. Une astuce a ce bouton si et seulement si elle a ce
	 * lien : `TipSentinel` compare les deux.
	 */
	public function getHelp(string $tip): ?string {

		$label = match($tip) {
			'selling-photo' => s("Voir les photos"),
			'selling-market-start', 'selling-wallet', 'hr-start' => s("Lire la documentation"),
			'accounting-pre-accounting-fec', 'accounting-pre-accounting-summary', 'accounting-cashflow-attach', 'accounting-operation-attach' => \Asset::icon('person-raised-hand').' '.s("Lire l'aide"),
			'accounting-cash-internal-transfer' => \Asset::icon('person-raised-hand').' '.s("Lire l'aide sur le compte {value}", \account\AccountSetting::FINANCIAL_INTERNAL_TRANSFER_CLASS),
			default => NULL
		};

		if($label === NULL) {
			return NULL;
		}

		// Les photos sont le geste de leur astuce, les aides en accompagnent un autre.
		$class = ($tip === 'selling-photo') ? 'btn-primary' : 'btn-outline-primary';

		return '<a href="/structure/tip:click?id='.$tip.'" data-ajax-navigation="never" target="_blank" class="btn '.$class.'">'.$label.'</a>';

	}

	private function getContent(Structure $eStructure, string $fqn): array {

		$default = [
			'icon' => NULL,
			'image' => FALSE,
			'buttons' => '',
			'info' => FALSE,
		];

		switch($fqn) {

			// Posée sur l'écran déjà rempli de l'équipe, dès deux personnes, RH éteinte.
			case 'hr-start' :

				return [
					'title' => s("Suivez le temps de travail et les absences de votre équipe"),
					'content' => '<p>'.s("Renseignez le contrat de chaque personne, en heures par semaine ou annualisé. Vos salariés demandent leurs congés payés et leurs autres absences, que vous validez et qui s'affichent au planning. Le temps de chaque journée se saisit même sans suivre celui des interventions, les heures supplémentaires se calculent sur le contrat, et la synthèse mensuelle de chaque personne s'exporte pour la paie.").'</p>',
					'buttons' => '<a data-ajax="/structure/feature:doUpdateHr" post-id="'.$eStructure->conf(Feature::class)['id'].'" post-value="1" class="btn btn-primary">'.s("Activer le suivi RH").'</a>',
					'info' => TRUE,
				] + $default;

			case 'selling-photo' :

				return [
					'icon' => \Asset::icon('image'),
					'title' => s("Des photos libres de droit pour vos produits"),
					'content' => '<p>'.s("Illustrez vos produits avec les photos des principaux légumes que nous mettons à votre disposition.").'</p>',
				] + $default;

			case 'selling-market-start' :

				return [
					'icon' => \Asset::icon('cash-register'),
					'title' => s("La caisse enregistreuse"),
					'content' => '<p>'.s("Enregistrez les ventes de vos marchés sur une tablette ou un téléphone, à plusieurs vendeurs en même temps. La caisse respecte les obligations d'inaltérabilité, de sécurisation, de conservation et d'archivage des données, et alimente votre comptabilité si vous la tenez sur {siteName}.").'</p>',
					'image' => TRUE,
				] + $default;

			case 'selling-purchase' :

				$h = '<p>'.s("Notez les produits que vous achetez pour les revendre. Ils viennent en déduction des ventes dans les analyses, et les exports CSV les distinguent.").'</p>';
				$h .= '<p>'.s("Ils n'entrent pas dans votre comptabilité, où ce sont les factures de vos fournisseurs qui comptent.").'</p>';

				return [
					'icon' => \Asset::icon('cart2'),
					'title' => s("Les achats"),
					'content' => $h,
				] + $default;

			case 'selling-catalog-start' :

				return [
					'icon' => \Asset::icon('book'),
					'title' => s("Les catalogues de produits"),
					'content' => '<p>'.s("Tenez vos disponibilités à un seul endroit, et partagez la même gamme et les mêmes stocks entre plusieurs boutiques. Chaque produit d'un catalogue a son stock, sa mise en avant et ses restrictions de commande.").'</p>',
					'image' => TRUE,
				] + $default;

			case 'selling-wallet' :

				$h = '<p>'.s("Enregistrez les avances de vos clients particuliers, qu'ils dépensent ensuite dans vos boutiques. Les adhérents d'une AMAP peuvent ainsi régler en début de saison. Chaque cagnotte s'active client par client, garde l'historique de ses opérations et s'intègre à votre comptabilité si vous la tenez sur {siteName}.").'</p>';
				$h .= '<p>'.s("Un client professionnel règle une avance par une facture d'acompte.").'</p>';

				return [
					'icon' => \Asset::icon('wallet'),
					'title' => s("Les cagnottes"),
					'content' => $h,
					'image' => TRUE,
					'buttons' => '<a href="/selling/configuration:updateWallet?id='.$eStructure['id'].'" class="btn btn-primary">'.s("Paramétrer les cagnottes").'</a>',
				] + $default;

			case 'sequence-weeks' :

				return [
					'icon' => \Asset::icon('list-task'),
					'title' => s("Un seul itinéraire technique par espèce"),
					'content' => '<p>'.s("Une série créée depuis cet itinéraire peut démarrer une autre semaine : toutes ses interventions se décalent d'autant. Un seul itinéraire de radis suffit pour des séries semées à des semaines différentes.").'</p>',
					'image' => TRUE,
				] + $default;

			case 'mailing-campaign-help' :

				$limit = $eStructure->getCampaignLimit();

				$h = '<p>'.($eStructure->isAmap() ?
					s("Envoyez un e-mail groupé à une partie de vos contacts, aux adhérents d'un contrat pour les prévenir d'un changement à la prochaine distribution par exemple.") :
					s("Envoyez un e-mail groupé à une partie de vos contacts, aux clients d'une boutique pour annoncer la prochaine vente par exemple.")
				).'</p>';
				$h .= '<p>'.p("Chaque envoi a un coût pour l'association {siteName}, d'où une limite de {value} envoi par semaine. Les contacts pour qui vous avez désactivé l'envoi et ceux qui ont refusé vos communications n'en reçoivent pas.", "Chaque envoi a un coût pour l'association {siteName}, d'où une limite de {value} envois par semaine. Les contacts pour qui vous avez désactivé l'envoi et ceux qui ont refusé vos communications n'en reçoivent pas.", $limit, ['value' => \util\TextUi::number($limit)]).'</p>';
				$h .= '<p>'.s("Un envoi non sollicité, ou un désabonnement refusé, engage votre responsabilité et vous expose à un bannissement à vie de {siteName}.").'</p>';

				return [
					'icon' => \Asset::icon('envelope'),
					'title' => s("Les campagnes d'e-mailing"),
					'content' => $h,
				] + $default;

			case 'series-forecast-help' :

				return [
					'icon' => \Asset::icon('graph-up-arrow'),
					'title' => s("Le prévisionnel financier"),
					'content' => '<p>'.s("Les séries de la saison y sont déjà : saisissez vos prix de vente pour obtenir vos prévisions. Vous pouvez aussi partir du prévisionnel avant de planifier, en y ajoutant les espèces que vous voulez cultiver.").'</p>',
				] + $default;

			case 'accounting-invoice-cashflow' :

				return [
					'title' => s("Rapprochez factures et opérations bancaires"),
					'content' => '<p>'.s("{siteName} propose les rapprochements entre vos factures et vos opérations bancaires importées, avec un indice de confiance calculé sur le client, le numéro de facture, le mode de règlement, le montant et la date. Validés, ils marquent la facture payée, avec son mode et sa date de paiement.").'</p>',
					'info' => TRUE,
				] + $default;

			case 'accounting-pre-accounting-fec' :

				return [
					'icon' => \Asset::icon('file-spreadsheet'),
					'title' => s("La précomptabilité"),
					'content' => '<p>'.s("Préparez vos ventes, factures et opérations de caisse pour une comptabilité au Plan Comptable Agricole, jusqu'au fichier FEC à importer dans votre logiciel comptable.").'</p>',
				] + $default;

			case 'accounting-pre-accounting-summary' :

				return [
					'icon' => \Asset::icon('magic'),
					'title' => s("L'import des ventes en comptabilité"),
					'content' => '<p>'.s("{siteName} écrit pour vous dans le livre journal les écritures de vos factures rapprochées et de vos opérations de caisse. Une fois vos numéros de compte paramétrés, l'import se fait en un clic chaque mois.").'</p>',
				] + $default;

			case 'accounting-cash-internal-transfer' :

				$account = \account\AccountSetting::FINANCIAL_INTERNAL_TRANSFER_CLASS;

				return [
					'title' => s("Des versements vers un compte qui n'est pas synchronisé ?"),
					'content' => '<p>'.s("Choisissez le compte {account} en contrepartie de vos versements dans le cahier. Quand les fonds arrivent sur votre compte synchronisé, importez l'opération avec le même compte {value}, qui revient à zéro.", ['account' => '<b>'.$account.' '.s("Virements internes").'</b>', 'value' => $account]).'</p>',
					'info' => TRUE,
				] + $default;

			case 'accounting-custom-account' :

				return [
					'title' => s("Personnalisez votre plan comptable"),
					'content' => '<p>'.s("Créez vos propres numéros de compte, un compte courant par associé par exemple, et choisissez le journal par défaut de chaque compte. Tous ceux qui saisissent la comptabilité suivent alors les mêmes règles.").'</p>',
					'info' => TRUE,
				] + $default;

			case 'accounting-cashflow-attach' :

				$h = '<p>'.s("Les écritures proposées n'ont pas encore de contrepartie en compte {value}. Le rattachement crée seulement cette contrepartie, du montant de l'opération.", '<b>'.\account\AccountSetting::BANK_ACCOUNT_CLASS.'</b>').'</p>';
				$h .= '<p>'.s("Les écritures du tiers choisi viennent en premier, mais vous pouvez en prendre d'autres, pour un virement Stripe qui regroupe plusieurs clients par exemple.").'</p>';

				return [
					'title' => s("Rattacher à des écritures déjà saisies"),
					'content' => $h,
					'info' => TRUE,
				] + $default;

			case 'accounting-operation-attach' :

				return [
					'title' => s("Rattacher à plusieurs opérations bancaires"),
					'content' => '<p>'.s("Une écriture peut se rattacher à plusieurs opérations, des frais du mois réglés en plusieurs prélèvements par exemple. Chaque opération choisie reçoit sa contrepartie en compte {value}. Seules les opérations de l'exercice pas encore rattachées sont proposées.", '<b>'.\account\AccountSetting::BANK_ACCOUNT_CLASS.'</b>').'</p>',
					'info' => TRUE,
				] + $default;

			case 'invoicing-addresses' :

				return [
					'title' => s("Vos adresses de facturation électronique"),
					'content' => '<p>'.s("Une seule adresse suffit à la plupart des structures. Vous pouvez en ajouter pour envoyer ou recevoir des factures, et une adresse déjà utilisée ne se supprime pas.").'</p>',
					'info' => TRUE,
				] + $default;

			default:
				throw new \Exception('Invalid tip \''.$fqn.'\'');

		}

	}

}
?>
