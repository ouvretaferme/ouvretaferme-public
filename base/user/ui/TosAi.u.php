<?php
namespace user;

/**
 * La fenêtre qui présente une nouvelle version des CGU : ce qui change, bloc par bloc, une ligne par
 * changement, dite telle qu'elle s'applique, et un cachet « Nouveau » sur ce que la version apporte.
 *
 * Le contenu décrit la version 2 (1er novembre 2026) : une version suivante réécrit `getBlocks()`.
 *
 * Deux états, que rend `TosAiLib::acceptOnUse()` :
 * - `ANNOUNCED`, une version pas encore en vigueur : la fenêtre demande l'acceptation et ne se ferme
 *   que par « J'accepte », jamais par une croix ni Échap ;
 * - `EFFECTIVE`, une version déjà acceptée en continuant : la fenêtre présente une fois ce qui a changé
 *   et se ferme par « J'ai compris », qui n'écrit rien.
 * Les deux disent comment refuser.
 */
class TosAiUi {

	public function getTrigger(): string {

		return '<div onrender="new Ajax.Query().method(\'get\').url(\'/user/tosAi\').fetch()"></div>';

	}

	public function getPanel(string $effectiveAt, string $state): \Panel {

		\Asset::css('user', 'tosAi.css');
		\Asset::js('main', 'pilesAi.js');

		$date = \util\DateUi::textual($effectiveAt, \util\DateUi::DAY_MONTH);

		$h = '<div class="user-tos-head">';
			$h .= '<h2>'.match($state) {
				TosAiLib::ANNOUNCED => s("Les conditions générales de {siteName} évoluent"),
				TosAiLib::EFFECTIVE => s("Les conditions générales de {siteName} ont évolué")
			}.'</h2>';
			$h .= '<p class="user-tos-intro">'.match($state) {
				TosAiLib::ANNOUNCED => s("Au {date}, la SAS OTF reprend le logiciel et de nouvelles offres arrivent.", ['date' => $date]),
				TosAiLib::EFFECTIVE => s("Depuis le {date}, la SAS OTF porte le logiciel et de nouvelles offres sont arrivées.", ['date' => $date])
			}.' '.s("Nous avions tout expliqué dans l'article <link>Où en sommes-nous avec Ouvretaferme&nbsp;?</link>", ['link' => '<a href="https://blog.ouvretaferme.org/#news-561" target="_blank">']).'</p>';
			$h .= '<p>'.match($state) {
				TosAiLib::ANNOUNCED => s("Voici un résumé de ce qui change au {date}", ['date' => $date]),
				TosAiLib::EFFECTIVE => s("Voici un résumé de ce qui a changé le {date}", ['date' => $date])
			}.'</p>';
		$h .= '</div>';

		$h .= '<div class="util-piles" data-pile-width="22" onrender="MainPiles.stack(this)">';

			foreach($this->getBlocks() as $block) {
				$h .= $this->getBlock($block);
			}

		$h .= '</div>';

		return new \Panel(
			id: 'user-tos',
			body: $h,
			footer: $this->getFooter($state),
			close: 'never',
			layer: FALSE
		);

	}

	protected function getBlock(array $block): string {

		$h = '<section class="util-card">';

			$h .= '<div class="util-title">';
				$h .= '<h4>'.$block['title'].' '.$block['emoji'].'</h4>';
			$h .= '</div>';

			foreach($block['changes'] as $change) {
				$h .= $this->getChange($change);
			}

		$h .= '</section>';

		return $h;

	}

	protected function getChange(array $change): string {

		$h = '<div class="user-tos-change">';

			$h .= '<div>';

				$h .= $change['text'];

				if($change['action'] !== NULL) {
					$h .= '<a href="'.$change['href'].'" class="user-tos-change-action">'.$change['action'].'</a>';
				}

			$h .= '</div>';

			if($change['new']) {
				$h .= '<span class="util-stamp util-stamp-new">'.s("Nouveau").'</span>';
			}

		$h .= '</div>';

		return $h;

	}

	protected function getFooter(string $state): string {

		$h = '<div class="user-tos-footer">';

			$h .= match($state) {
				TosAiLib::ANNOUNCED => '<a data-ajax="/user/tosAi:doAccept" class="btn btn-lg btn-primary">'.s("J'accepte").'</a>',
				TosAiLib::EFFECTIVE => '<a onclick="Lime.Panel.closeLast()" class="btn btn-lg btn-primary">'.s("J'ai compris").'</a>'
			};

			$h .= '<a href="'.\Asset::getPath('main', 'document/cgu.pdf').'" target="_blank" class="btn btn-lg btn-outline-primary">'.s("Lire le texte intégral").'</a>';

			if($state === TosAiLib::ANNOUNCED) {
				$h .= '<a data-ajax="/user/tosAi:doLater" class="user-tos-later">'.s("Plus tard").'</a>';
			}

			$link = ['link' => '<a data-ajax="/user/tosAi:doLater" post-close="1">'];

			$h .= '<p class="user-tos-refuse">'.match($state) {
				TosAiLib::ANNOUNCED => s("Vous ne souhaitez pas les accepter ? Vous pouvez <link>exporter vos données et supprimer votre compte</link>.", $link),
				TosAiLib::EFFECTIVE => s("Vous ne souhaitez pas continuer avec ces conditions ? Vous pouvez <link>exporter vos données et supprimer votre compte</link>.", $link)
			}.'</p>';

		$h .= '</div>';

		return $h;

	}

	protected function change(string $text, bool $new = FALSE, ?string $action = NULL, ?string $href = NULL): array {

		return [
			'text' => $text,
			'new' => $new,
			'action' => $action,
			'href' => $href
		];

	}

	/**
	 * `new` marque ce que la version apporte. Une règle déjà appliquée avant d'entrer au texte, comme le
	 * partage des données, n'en porte pas. Les liens mènent aux adresses en /ferme/x/, qui font choisir
	 * la structure à qui en a plusieurs.
	 */
	protected function getBlocks(): array {

		return [
			[
				'emoji' => '🏢',
				'title' => s("Qui édite {siteName}"),
				'changes' => [
					$this->change(s("Le logiciel {siteName} est maintenant porté par la SAS OTF.")),
				]
			],
			[
				'emoji' => '💶',
				'title' => s("Les offres"),
				'changes' => [
					$this->change(s("Les modules Production et Vente et l'entraide entre producteurs restent gratuits, dans l'offre Essentiel.")),
					$this->change(s("La comptabilité, la liaison bancaire et les cahiers de caisse passent dans l'offre Gestion, à 10&nbsp;€&nbsp;HT par mois soit 120&nbsp;€&nbsp;HT par an.")),
					$this->change(s("Une nouvelle offre Pilotage est proposée à 25&nbsp;€&nbsp;HT par mois, soit 300&nbsp;€&nbsp;HT par an et ajoute de nouvelles fonctionnalités (tableaux de bord, notifications...)."), new: TRUE),
					$this->change(s("Si votre structure a soutenu l'association en y adhérant en 2025 ou 2026, une remise de 20&nbsp;€&nbsp;HT sera automatiquement appliquée lors de la souscription d'une offre Gestion, Pilotage ou Intégrale."), new: TRUE),
					$this->change(s("L'offre Communauté, portée par la FRAB AuRA, ouvre un accompagnement personnalisé, des formations et des échanges thématiques entre producteurs, à 9&nbsp;€&nbsp;HT par mois, soit 108&nbsp;€&nbsp;HT par an."), new: TRUE, action: s("Voir les offres"), href: '/presentation/offres'),
				]
			],
			[
				'emoji' => '🤝',
				'title' => s("Les données que vous partagez"),
				'changes' => [
					$this->change(s("Vous choisissez une à une les fonctionnalités mises en commun (relevés de prix, bibliothèque de cultures, paniers et prix au détail). Tant que vous n'avez rien choisi, vos données n'y sont pas versées."), action: s("Choisir dans Partage des données"), href: '/ferme/x/partage-des-donnees'),
					$this->change(s("Vos saisies aident à suggérer des comptes et des catégories à tous, sous une forme agrégée. Vous pouvez le refuser à tout moment."), action: s("Choisir dans Partage des données"), href: '/ferme/x/partage-des-donnees'),
				]
			],
			[
				'emoji' => '📦',
				'title' => s("Si vous fermez votre compte"),
				'changes' => [
					$this->change(s("Vous avez 90 jours au lieu de 30 jours pour exporter vos données après la demande de suppression de votre compte."), new: TRUE),
				]
			],
			[
				'emoji' => '🤖',
				'title' => s("Les accès automatisés"),
				'changes' => [
					$this->change(s("Un robot, un script ou une extension qui agit avec vos identifiants est interdit. L'API restera le seul accès automatisé dès qu'elle sera disponible."), new: TRUE),
				]
			],
			[
				'emoji' => '📣',
				'title' => s("Les prochains changements"),
				'changes' => [
					$this->change(s("Le retrait d'une fonctionnalité de votre offre sera annoncé 60 jours avant le changement et vous pourrez demander un remboursement au prorata le cas échéant."), new: TRUE),
				]
			],
		];

	}

}
?>
