<?php
namespace main;

/**
 * Le gabarit des e-mails que le produit envoie à un utilisateur.
 *
 * À ne pas confondre avec \main\MailStructureUi, qui habille les e-mails qu'une ferme
 * envoie à ses clients : celui-là pose la bannière et le pied de la structure, et son
 * texte est personnalisable dans les réglages. Ici c'est le site qui parle, depuis
 * ne-pas-repondre@, et aucune structure n'est en jeu — le mail d'inscription part
 * même avant qu'il en existe une.
 *
 * Onze blocs. Un appelant passe ceux dont il a besoin en arguments nommés et laisse
 * les autres à NULL. Seule la barre de marque est systématique.
 *
 * Le pied porte la documentation et, quand le site de présentation est installé, la foire
 * aux questions, qui s'adressent aux utilisateurs du logiciel. Un e-mail destiné au client d'une ferme passe donc
 * footer: FALSE : ces liens ne le concernent pas et l'enverraient au mauvais endroit.
 *
 * Contraintes du canal, qui expliquent la forme du HTML produit :
 * - Outlook Windows rend par Word : mise en page en tableaux, jamais en flex ni grid.
 * - Les variables CSS ne sont pas lues : toutes les couleurs sont en hexadécimal.
 * - Les <svg> ne sont pas rendus par Gmail : les icônes sont des PNG en URL absolue,
 *   servis en double résolution avec width et height posés en dur, sans quoi Outlook
 *   les affiche à leur taille native.
 * - Les fonds sont posés explicitement, sinon Gmail et Apple Mail les inversent en
 *   thème sombre.
 */
class MailSoftwareAiUi extends MailBlockAiUi {

	use \Notifiable;

	const ACCENT = '#1d4b53';
	const HERO = '#e1f7e8';
	const TINT_RULE = '#b0ccc3';
	const FOOT_TEXT = '#b7d2cc';
	const FOOT_LINK = '#e1f7e8';

	const URL_DOC = '/doc/';

	/**
	 * L'adresse de la foire aux questions, que tient le site de présentation, NULL quand il n'est
	 * pas installé.
	 */
	public static function getFaqUrl(): ?string {

		if(\structure\StructureSetting::hasPresentationPackage() === FALSE) {
			return NULL;
		}

		return self::notify('getFaqUrl')[0];

	}

	/**
	 * L'URL absolue d'une image du gabarit.
	 *
	 * Absolue parce qu'un client de messagerie ne connaît pas le site, et prise dans le
	 * package mail parce que ces images appartiennent au gabarit : les chercher dans un
	 * package applicatif ferait dépendre base de l'application.
	 */
	public static function image(string $file): string {
		return \Lime::getUrl().\Asset::getPath('main', 'software/'.$file, 'image');
	}

	/**
	 * Assemble un e-mail du produit et rend le triplet attendu par MailLib::setContent().
	 *
	 * Le corps texte est fabriqué ici et non par \main\MailStructureUi::format() : celui-ci le tire
	 * du HTML par strip_tags(), ce qui réduirait un bouton à son libellé et ferait
	 * disparaître son URL. Le gabarit, lui, connaît ses blocs et sait écrire le lien.
	 *
	 * $chips attend une liste de ['image' => URL absolue, 'label' => …, 'url' => …].
	 * $cards et $tiles sont décrits sur getCards() et getTiles().
	 *
	 * @return [string, string, string] sujet, corps texte, corps HTML
	 */
	public static function format(
		string $title,
		string $content = '',
		?string $heroTitle = NULL,
		?string $heroText = NULL,
		?string $heroImage = NULL,
		?string $valueLabel = NULL,
		?string $value = NULL,
		?array $facts = NULL,
		?string $quote = NULL,
		?string $buttonLabel = NULL,
		?string $buttonUrl = NULL,
		?string $buttonNote = NULL,
		?array $chips = NULL,
		?string $cardsTitle = NULL,
		?array $cards = NULL,
		?string $contentAfter = NULL,
		?string $tilesTitle = NULL,
		?array $tiles = NULL,
		?string $attachment = NULL,
		?string $asideTitle = NULL,
		?string $asideText = NULL,
		bool $footer = TRUE
	): array {

		$rows = self::getBrand();
		$parts = [];

		if($heroTitle !== NULL) {

			$rows .= self::getHero($heroTitle, $heroText, $heroImage);

			$part = self::getTextBlock($heroTitle);

			if($heroText !== NULL) {
				$part .= "\n".self::getTextBlock($heroText);
			}

			$parts[] = $part;

		}

		if($content !== '') {
			$rows .= self::getContent($content);
			$parts[] = self::getTextBlock($content);
		}

		if($value !== NULL) {
			$rows .= self::getValue($valueLabel, $value);
			$parts[] = self::getTextLabel($valueLabel)."\n".self::getTextLabel($value);
		}

		if($facts !== NULL) {
			$rows .= self::getFacts($facts);
			$parts[] = join("\n", array_map(fn($label, $value) => '- '.self::getTextLabel($label).' : '.self::getTextLabel($value), array_keys($facts), $facts));
		}

		if($quote !== NULL) {
			$rows .= self::getQuote($quote);
			$parts[] = self::getTextBlock($quote);
		}

		if($buttonLabel !== NULL) {

			$rows .= self::getButton($buttonLabel, $buttonUrl, $buttonNote, ($content !== '' or $value !== NULL or $facts !== NULL or $quote !== NULL));

			$part = self::getTextLabel($buttonLabel)."\n".$buttonUrl;

			if($buttonNote !== NULL) {
				$part .= "\n".self::getTextBlock($buttonNote);
			}

			$parts[] = $part;

		}

		if($chips !== NULL) {
			$rows .= self::getChips($chips);
			$parts[] = join("\n", array_map(fn(array $chip) => '- '.self::getTextLabel($chip['label']).' : '.$chip['url'], $chips));
		}

		if($cards !== NULL) {
			$rows .= self::getCards($cardsTitle, $cards);
			$parts[] = self::getTextCards($cardsTitle, $cards);
		}

		if($contentAfter !== NULL) {
			$rows .= self::getContent($contentAfter);
			$parts[] = self::getTextBlock($contentAfter);
		}

		if($tiles !== NULL) {
			$rows .= self::getTiles($tilesTitle, $tiles);
			$parts[] = self::getTextTiles($tilesTitle, $tiles);
		}

		if($attachment !== NULL) {
			$rows .= self::getAttachment($attachment);
			$parts[] = s("En pièce jointe : {value}", self::getTextLabel($attachment));
		}

		if($asideTitle !== NULL) {

			$rows .= self::getAside($asideTitle, $asideText);

			$part = self::getTextLabel($asideTitle);

			if($asideText !== NULL) {
				$part .= "\n".self::getTextBlock($asideText);
			}

			$parts[] = $part;

		}

		if($footer) {

			$rows .= self::getFooter();

			$urlFaq = self::getFaqUrl();

			if($urlFaq === NULL) {
				$part = s("Une question ? La documentation répond à la plupart d'entre elles.")
					."\n".\Lime::getUrl().self::URL_DOC;
			} else {
				$part = s("Une question ? La documentation et la foire aux questions répondent à la plupart d'entre elles.")
					."\n".\Lime::getUrl().self::URL_DOC
					."\n".\Lime::getUrl().$urlFaq;
			}

			$parts[] = $part."\n\n".s("Ce message part d'une adresse qui ne reçoit pas de réponse.");

		}

		// Un bloc peut n'avoir aucun équivalent texte, une illustration par exemple : le
		// garder laisserait une ligne vide de plus dans le corps texte.
		return [$title, join("\n\n", array_filter($parts, fn(string $part) => $part !== '')), self::getHtml($rows)];

	}

	protected static function getBrand(): string {

		$logo = \Lime::getUrl().\Asset::getPath('main', 'logo.png', 'image');
		$name = \L::getVariable('siteName');

		$h = '<tr><td style="padding: 16px 24px; border-bottom: 1px solid '.self::RULE.';">';

			// La police et la couleur sont posées sur l'<img> lui-même : quand le
			// destinataire bloque les images, ou quand elles ne chargent pas, c'est le texte
			// de remplacement qui s'affiche et il hérite de ce style. Sans cela, la barre de
			// marque tombe en Times noir.
			$h .= '<img src="'.encode($logo).'" width="174" height="24" alt="'.encode($name).'" style="display: block; border: 0; width: 174px; height: 24px; font-family: '.self::FONT.'; font-size: 17px; line-height: 24px; font-weight: bold; color: '.self::ACCENT.'; text-decoration: none;"/>';

		$h .= '</td></tr>';

		return $h;

	}

	protected static function getFooter(): string {

		$link = fn(string $url, string $label) => '<a href="'.encode(\Lime::getUrl().$url).'" style="color: '.self::FOOT_LINK.';">'.$label.'</a>';

		$h = '<tr><td style="padding: 18px 24px 22px 24px; background-color: '.self::ACCENT.'; border-radius: 0 0 8px 8px; font-family: '.self::FONT.'; font-size: 12px; line-height: 19px; color: '.self::FOOT_TEXT.';">';
			$urlFaq = self::getFaqUrl();

			if($urlFaq === NULL) {
				$h .= s("Une question ? La {documentation} répond à la plupart d'entre elles.", [
					'documentation' => $link(self::URL_DOC, s("documentation")),
				]);
			} else {
				$h .= s("Une question ? La {documentation} et la {faq} répondent à la plupart d'entre elles.", [
					'documentation' => $link(self::URL_DOC, s("documentation")),
					'faq' => $link($urlFaq, s("foire aux questions")),
				]);
			}
			$h .= '<br/>';
			$h .= s("Ce message part d'une adresse qui ne reçoit pas de réponse.");
		$h .= '</td></tr>';

		return $h;

	}

	/**
	 * Le corps texte, bloc par bloc.
	 *
	 * Le libellé d'un bouton est suivi de son URL sur la ligne suivante : c'est le seul
	 * endroit où le lien reste atteignable pour un lecteur qui n'affiche pas le HTML.
	 */
}
?>
