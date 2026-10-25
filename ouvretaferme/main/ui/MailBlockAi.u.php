<?php
namespace main;

/**
 * Les blocs communs aux deux gabarits d'e-mail.
 *
 * \main\MailSoftwareAiUi habille les e-mails que le produit envoie à un utilisateur,
 * \main\MailStructureUi ceux qu'une ferme envoie à ses clients. Les deux posent les mêmes
 * blocs — un bandeau, un corps, une liste de faits, une valeur, une table, une
 * citation, un bouton, une pièce jointe, un encart — et ne diffèrent que par ce qui
 * les entoure : l'en-tête et le pied.
 *
 * La palette est lue en `static::` et non en `self::` : chaque gabarit redéfinit ses
 * couleurs et les blocs suivent, sans qu'aucun bloc ait à savoir qui l'appelle.
 *
 * Contraintes du canal, qui expliquent la forme du HTML produit :
 * - Outlook Windows rend par Word : mise en page en tableaux, jamais en flex ni grid.
 * - Les variables CSS ne sont pas lues : toutes les couleurs sont en hexadécimal.
 * - Les <svg> ne sont pas rendus par Gmail : les icônes sont des PNG en URL absolue,
 *   servis en double résolution avec width et height posés en dur.
 * - Les fonds sont posés explicitement, sinon Gmail et Apple Mail les inversent en
 *   thème sombre.
 */
abstract class MailBlockAiUi {

	const WIDTH = 600;

	const FONT = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif";
	const MONO = "'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace";

	const TEXT = '#333333';
	const MUTED = '#62766f';
	const RULE = '#e4ebe9';
	const SOFT = '#f7faf9';
	const BADGE = '#b6231a';

	/**
	 * La balise ouvrante d'un lien dans un corps ou un encart.
	 *
	 * La couleur y est posée à la main parce qu'un client de messagerie n'hérite pas
	 * celle du conteneur pour une balise <a>, qui garderait le bleu du navigateur.
	 * Elle vit ici et non chez l'appelant, pour que la teinte du gabarit reste au
	 * gabarit.
	 */
	public static function linkTag(string $url): string {
		return '<a href="'.encode($url).'" style="color: '.static::ACCENT.'; font-weight: bold;">';
	}

	/**
	 * Une capture du produit, à glisser dans $content ou $contentAfter.
	 *
	 * Les dimensions sont posées en dur pour Outlook, et la largeur en pourcentage pour
	 * que l'image suive le conteneur sur un téléphone. Rendue ici pour que le cadre et
	 * le rayon restent au gabarit plutôt que chez chaque appelant.
	 */
	public static function illustration(string $url, int $width, int $height, string $alt = '', ?string $link = NULL): string {

		$h = '<img src="'.encode($url).'" width="'.$width.'" height="'.$height.'" alt="'.encode($alt).'" style="display: block; border: 1px solid '.static::RULE.'; border-radius: 8px; width: 100%; max-width: '.$width.'px; height: auto;"/>';

		if($link !== NULL) {
			$h = '<a href="'.encode($link).'" style="text-decoration: none;">'.$h.'</a>';
		}

		return $h;

	}

	protected static function getHtml(string $rows): string {

		$h = '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f1f4f3; margin: 0; padding: 0;">';
			$h .= '<tr><td align="center" style="padding: 24px 12px;">';

				// Largeur fluide bornée à 600 px et non fixée à 600 px : sur un téléphone, une
				// table à largeur fixe déborde de l'écran et force un défilement horizontal.
				$h .= '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="width: 100%; max-width: '.static::WIDTH.'px; background-color: #ffffff; border-radius: 8px;">';
					$h .= $rows;
				$h .= '</table>';

			$h .= '</td></tr>';
		$h .= '</table>';

		return $h;

	}

	protected static function getHero(string $title, ?string $text, ?string $image): string {

		$h = '<tr><td style="padding: 26px 24px; background-color: '.static::HERO.'; border-bottom: 1px solid '.static::TINT_RULE.';">';

			$h .= '<table width="100%" cellpadding="0" cellspacing="0" border="0">';
				$h .= '<tr>';

					$h .= '<td valign="middle">';
						$h .= '<div style="font-family: '.static::FONT.'; font-size: 23px; line-height: 28px; font-weight: bold; color: '.static::ACCENT.';">'.$title.'</div>';
						if($text !== NULL) {
							$h .= '<div style="font-family: '.static::FONT.'; font-size: 14px; line-height: 21px; color: '.static::MUTED.'; padding-top: 7px;">'.$text.'</div>';
						}
					$h .= '</td>';

					if($image !== NULL) {
						$h .= '<td width="94" valign="middle" align="right" style="width: 94px;">';
							$h .= '<img src="'.encode($image).'" width="76" height="76" alt="" style="display: block; border: 0; width: 76px; height: 76px;"/>';
						$h .= '</td>';
					}

				$h .= '</tr>';
			$h .= '</table>';

		$h .= '</td></tr>';

		return $h;

	}

	protected static function getContent(string $content): string {

		$h = '<tr><td style="padding: 24px; font-family: '.static::FONT.'; font-size: 15px; line-height: 23px; color: '.static::TEXT.'; word-break: break-word;">';
			$h .= $content;
		$h .= '</td></tr>';

		return $h;

	}

	/**
	 * Un seul bouton plein par e-mail, et jamais deux : c'est la règle du gabarit. Les cartes
	 * (getCards()) portent le leur sur la première et des contours sur les suivantes, et l'e-mail
	 * qui les montre ne passe pas par ce bouton-ci (planche 24 de l'abonnement, G1).
	 *
	 * Le bouton est un tableau et non une simple balise <a> : Outlook ignore le
	 * remplissage d'un élément en ligne et collerait le libellé aux bords du fond.
	 */
	protected static function getButton(string $label, ?string $url, ?string $note, bool $afterContent): string {

		$top = $afterContent ? '0' : '24px';

		$h = '<tr><td align="center" style="padding: '.$top.' 24px 24px 24px;">';

			$h .= '<table cellpadding="0" cellspacing="0" border="0"><tr>';
				$h .= '<td align="center" bgcolor="'.static::ACCENT.'" style="background-color: '.static::ACCENT.'; border-radius: 8px;">';
					$h .= '<a href="'.encode($url).'" style="display: inline-block; padding: 14px 28px; font-family: '.static::FONT.'; font-size: 16px; line-height: 19px; font-weight: bold; color: #ffffff; text-decoration: none;">'.$label.'</a>';
				$h .= '</td>';
			$h .= '</tr></table>';

			if($note !== NULL) {
				$h .= '<div style="font-family: '.static::FONT.'; font-size: 13px; line-height: 20px; color: #7b908c; padding-top: 10px;">'.$note.'</div>';
			}

		$h .= '</td></tr>';

		return $h;

	}

	/**
	 * La bande de pastilles : trois par ligne, en tableau.
	 *
	 * Elle promet sans détailler, et n'a de sens que dans un e-mail qui présente le
	 * produit à quelqu'un qui ne l'a pas encore vu.
	 */
	protected static function getChips(array $chips): string {

		$h = '<tr><td style="padding: 22px 24px 8px 24px; background-color: '.static::SOFT.'; border-top: 1px solid '.static::RULE.'; border-bottom: 1px solid '.static::RULE.';">';

			$h .= '<table width="100%" cellpadding="0" cellspacing="0" border="0">';

				foreach(array_chunk($chips, 3) as $line) {

					$h .= '<tr>';

					foreach($line as $chip) {

						$h .= '<td width="33%" align="center" valign="top" style="width: 33%; padding-bottom: 16px;">';
							$h .= '<a href="'.encode($chip['url']).'" style="text-decoration: none;">';
								$h .= '<img src="'.encode($chip['image']).'" width="40" height="40" alt="" style="display: block; border: 0; width: 40px; height: 40px; margin: 0 auto 7px auto;"/>';
								$h .= '<span style="font-family: '.static::FONT.'; font-size: 12px; line-height: 15px; font-weight: bold; color: '.static::ACCENT.';">'.$chip['label'].'</span>';
							$h .= '</a>';
						$h .= '</td>';

					}

					// Une ligne incomplète se complète de cellules vides, sinon les pastilles
					// présentes s'étalent sur toute la largeur et ne s'alignent plus sur celles
					// de la ligne du dessus.
					for($i = count($line); $i < 3; $i++) {
						$h .= '<td width="33%" style="width: 33%;">&nbsp;</td>';
					}

					$h .= '</tr>';

				}

			$h .= '</table>';

		$h .= '</td></tr>';

		return $h;

	}

	/**
	 * La valeur que l'e-mail existe pour transmettre.
	 *
	 * Une adresse de facturation électronique, une référence à recopier : ce n'est pas
	 * une donnée qu'on lit au passage comme un fait, c'est une chaîne qu'on sélectionne
	 * pour la coller ailleurs. D'où le cadre qui la détache, la chasse fixe qui rend
	 * lisible un identifiant alphanumérique, et la coupure autorisée en tout point,
	 * sans quoi une chaîne longue déborde du cadre sur un téléphone.
	 */
	protected static function getValue(?string $label, string $value): string {

		$h = '<tr><td style="padding: 0 24px 20px 24px;">';

			$h .= '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="border: 1px solid '.static::TINT_RULE.'; border-radius: 8px; background-color: '.static::SOFT.';">';
				$h .= '<tr><td style="padding: 16px 18px;">';

					if($label !== NULL) {
						$h .= '<div style="font-family: '.static::FONT.'; font-size: 11px; line-height: 16px; font-weight: bold; letter-spacing: 0.08em; text-transform: uppercase; color: #7b908c; padding-bottom: 6px;">'.$label.'</div>';
					}

					$h .= '<div style="font-family: '.static::MONO.'; font-size: 16px; line-height: 24px; font-weight: bold; color: '.static::ACCENT.'; word-break: break-all;">'.$value.'</div>';

				$h .= '</td></tr>';
			$h .= '</table>';

		$h .= '</td></tr>';

		return $h;

	}

	/**
	 * La table libellé/valeur, sans son enveloppe de ligne.
	 *
	 * Elle diffère de getFacts() sur un point qui compte : elle prend une liste de paires
	 * et non un tableau associatif, donc deux lignes peuvent porter le même libellé — deux
	 * produits de même nom dans une commande, deux documents de même type dans une demande.
	 * L'enveloppe reste à l'appelant, chaque gabarit ayant son propre rythme vertical.
	 */
	public static function getItemsTable(array $items, ?string $label = NULL): string {

		$h = '';

		if($label !== NULL) {
			$h .= '<div style="font-family: '.static::FONT.'; font-size: 12px; line-height: 18px; font-weight: bold; letter-spacing: 0.08em; text-transform: uppercase; color: #7b908c; padding-bottom: 8px;">'.$label.'</div>';
		}

		$h .= '<table width="100%" cellpadding="0" cellspacing="0" border="0">';

			foreach($items as [$itemLabel, $value]) {
				$h .= '<tr>';
					$h .= '<td style="padding: 6px 0; border-bottom: 1px solid #eef3f1; font-family: '.static::FONT.'; font-size: 14px; line-height: 21px; color: #4c6461;">'.$itemLabel.'</td>';
					$h .= '<td align="right" style="padding: 6px 0; border-bottom: 1px solid #eef3f1; font-family: '.static::FONT.'; font-size: 14px; line-height: 21px; font-weight: bold; white-space: nowrap; color: '.static::ACCENT.';">'.$value.'</td>';
				$h .= '</tr>';
			}

		$h .= '</table>';

		return $h;

	}

	/**
	 * Les faits de l'e-mail : expéditeur, référence, montant, date.
	 *
	 * Sept e-mails du produit écrivent aujourd'hui ces lignes à la main, en tirets, dans
	 * sept mises en forme différentes. Le libellé à gauche, la valeur à droite en gras :
	 * c'est la valeur qu'on vient chercher, et l'alignement à droite la rend balayable
	 * quand il y en a plusieurs.
	 */
	protected static function getFacts(array $facts): string {

		$h = '<tr><td style="padding: 4px 24px 20px 24px;">';

			$h .= '<table width="100%" cellpadding="0" cellspacing="0" border="0">';

				foreach($facts as $label => $value) {
					$h .= '<tr>';
						$h .= '<td style="padding: 7px 0; border-bottom: 1px solid #eef3f1; font-family: '.static::FONT.'; font-size: 14px; line-height: 21px; color: #7b908c;">'.$label.'</td>';
						$h .= '<td align="right" style="padding: 7px 0; border-bottom: 1px solid #eef3f1; font-family: '.static::FONT.'; font-size: 14px; line-height: 21px; font-weight: bold; color: '.static::ACCENT.'; word-break: break-word;">'.$value.'</td>';
					$h .= '</tr>';
				}

			$h .= '</table>';

		$h .= '</td></tr>';

		return $h;

	}

	/**
	 * Les mots de quelqu'un d'autre, rapportés tels quels.
	 *
	 * Le message d'un visiteur, le commentaire d'un client : le filet et le retrait
	 * disent que ce n'est pas le site qui parle, et évitent de faire lire une phrase
	 * inconnue comme si elle venait de nous.
	 */
	protected static function getQuote(string $quote): string {

		$h = '<tr><td style="padding: 0 24px 20px 24px;">';
			$h .= '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: '.static::SOFT.'; border-left: 3px solid '.static::TINT_RULE.';">';
				$h .= '<tr><td style="padding: 12px 16px; font-family: '.static::FONT.'; font-size: 14px; line-height: 22px; font-style: italic; color: #4c6461; word-break: break-word;">';
					$h .= $quote;
				$h .= '</td></tr>';
			$h .= '</table>';
		$h .= '</td></tr>';

		return $h;

	}

	/**
	 * L'annonce d'une pièce jointe.
	 *
	 * Trois e-mails en portent une. Sans cette ligne, le lecteur ne sait qu'elle existe
	 * qu'en voyant le trombone de son client de messagerie, qui ne dit pas ce qu'il y a
	 * dedans.
	 */
	protected static function getAttachment(string $label): string {

		$h = '<tr><td style="padding: 0 24px 20px 24px;">';
			$h .= '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="border: 1px dashed '.static::TINT_RULE.'; border-radius: 8px; background-color: #fbfdfc;">';
				$h .= '<tr><td style="padding: 11px 14px; font-family: '.static::FONT.'; font-size: 13px; line-height: 20px; color: '.static::MUTED.';">';
					$h .= '&#128206;&nbsp;&nbsp;'.$label;
				$h .= '</td></tr>';
			$h .= '</table>';
		$h .= '</td></tr>';

		return $h;

	}

	/**
	 * Le second sujet de l'e-mail, quand il y en a un.
	 *
	 * Sur fond gris et sans bouton : il ne prend jamais la place de l'appel principal.
	 */
	protected static function getAside(string $title, ?string $text): string {

		$h = '<tr><td style="padding: 18px 24px; background-color: '.static::SOFT.'; border-top: 1px solid '.static::RULE.';">';
			$h .= '<div style="font-family: '.static::FONT.'; font-size: 14px; line-height: 21px; font-weight: bold; color: '.static::ACCENT.';">'.$title.'</div>';
			if($text !== NULL) {
				$h .= '<div style="font-family: '.static::FONT.'; font-size: 13px; line-height: 20px; color: '.static::MUTED.'; padding-top: 3px;">'.$text.'</div>';
			}
		$h .= '</td></tr>';

		return $h;

	}

	/**
	 * Une date annoncée, en pastille terracota comme sur la page des offres (bg-terracota).
	 *
	 * Le nowrap garde la date d'un seul tenant quand la ligne qui la porte se replie.
	 */
	public static function badge(string $text): string {
		return '<span style="display: inline-block; padding: 1px 7px; border-radius: 9px; background-color: '.static::BADGE.'; color: #ffffff; font-size: 11px; line-height: 16px; font-weight: bold; white-space: nowrap;">'.$text.'</span>';
	}

	/**
	 * L'intertitre d'un bloc de cartes ou de tuiles.
	 */
	protected static function getTitle(string $title): string {
		return '<div style="font-family: '.static::FONT.'; font-size: 18px; line-height: 24px; font-weight: bold; color: '.static::ACCENT.'; padding: 0 0 12px 0;">'.$title.'</div>';
	}

	/**
	 * Une rangée vide de hauteur fixe.
	 *
	 * Les espacements passent par une rangée ou le remplissage d'une cellule : Outlook pour
	 * Windows ignore la marge d'un tableau.
	 */
	protected static function getSpacer(int $height): string {
		return '<div style="height: '.$height.'px; line-height: '.$height.'px; font-size: 1px;">&nbsp;</div>';
	}

	/**
	 * Des gestes en cartes, chacune avec son bouton : le premier plein aux mesures de
	 * getButton(), sur fond HERO, les suivants en contour, sur fond blanc.
	 *
	 * Deux cartes par rang dans les 552 px du corps, une sur mobile : chaque carte est un
	 * bloc en ligne de 270 px au plus, sans media query, qu'Outlook ne lit pas. Le corps
	 * du conteneur est à 0 pour que l'espace entre deux blocs en ligne ne les décale pas.
	 *
	 * $cards attend une liste de ['title' => …, 'text' => …, 'label' => …, 'url' => URL absolue].
	 */
	protected static function getCards(?string $title, array $cards): string {

		$h = '<tr><td style="padding: 24px;">';

			if($title !== NULL) {
				$h .= self::getTitle($title);
			}

			$h .= '<div style="font-size: 0; line-height: 0;">';

				foreach(array_values($cards) as $index => $card) {

					$isFirst = ($index === 0);

					$h .= '<div style="display: inline-block; vertical-align: top; width: 100%; max-width: 270px; box-sizing: border-box; padding: 0 6px 10px 0;">';
						$h .= '<div style="background-color: '.($isFirst ? static::HERO : '#ffffff').'; border: 1px solid '.($isFirst ? static::TINT_RULE : static::RULE).'; border-radius: 10px; padding: 14px 16px 16px 16px;">';
							$h .= '<div style="font-family: '.static::FONT.'; font-size: 15px; line-height: 21px; font-weight: bold; color: '.static::ACCENT.';">'.$card['title'].'</div>';
							$h .= '<div style="font-family: '.static::FONT.'; font-size: 13px; line-height: 19px; color: '.($isFirst ? static::TEXT : static::MUTED).'; padding: 4px 0 12px 0;">'.$card['text'].'</div>';
							$h .= '<table cellpadding="0" cellspacing="0" border="0"><tr>';
								if($isFirst) {
									$h .= '<td align="center" bgcolor="'.static::ACCENT.'" style="background-color: '.static::ACCENT.'; border-radius: 8px;">';
										$h .= '<a href="'.encode($card['url']).'" style="display: inline-block; padding: 14px 28px; font-family: '.static::FONT.'; font-size: 16px; line-height: 19px; font-weight: bold; color: #ffffff; text-decoration: none;">'.$card['label'].'</a>';
									$h .= '</td>';
								} else {
									$h .= '<td style="border: 2px solid '.static::ACCENT.'; border-radius: 8px;">';
										$h .= '<a href="'.encode($card['url']).'" style="display: inline-block; padding: 8px 16px; font-family: '.static::FONT.'; font-size: 14px; line-height: 17px; font-weight: bold; color: '.static::ACCENT.'; text-decoration: none;">'.$card['label'].'</a>';
									$h .= '</td>';
								}
							$h .= '</tr></table>';
						$h .= '</div>';
					$h .= '</div>';

				}

			$h .= '</div>';

			$h .= self::getSpacer(14);

		$h .= '</td></tr>';

		return $h;

	}

	/**
	 * Ce que comprennent des offres : une ligne d'en-tête par offre (son image et son nom),
	 * puis ses groupes en tuiles, qui passent de deux colonnes à une comme les cartes.
	 *
	 * $tiles attend une liste de ['name' => …, 'image' => URL absolue, 'groups' => [['label' => …, 'items' => [ligne, …]], …], 'text' => les mêmes groupes, écrits pour le corps texte].
	 */
	protected static function getTiles(?string $title, array $tiles): string {

		$h = '<tr><td style="padding: 24px;">';

			if($title !== NULL) {
				$h .= self::getTitle($title);
			}

			foreach($tiles as $tile) {

				$h .= '<table width="100%" cellpadding="0" cellspacing="0" border="0"><tr>';
					$h .= '<td width="36" valign="middle" style="width: 36px;"><img src="'.encode($tile['image']).'" width="30" height="30" alt="" style="display: block; border: 0; width: 30px; height: 30px;"/></td>';
					$h .= '<td valign="middle" style="font-family: '.static::FONT.'; font-size: 16px; line-height: 22px; font-weight: bold; color: '.static::ACCENT.';">'.$tile['name'].'</td>';
				$h .= '</tr></table>';

				$h .= self::getSpacer(8);

				$h .= '<div style="font-size: 0; line-height: 0;">';

					foreach($tile['groups'] as $group) {

						$h .= '<div style="display: inline-block; vertical-align: top; width: 100%; max-width: 270px; box-sizing: border-box; padding: 0 6px 10px 0;">';
							$h .= '<div style="background-color: '.static::SOFT.'; border: 1px solid '.static::RULE.'; border-radius: 10px; padding: 12px 14px;">';
								$h .= '<div style="font-family: '.static::FONT.'; font-size: 14px; line-height: 20px; font-weight: bold; color: '.static::ACCENT.'; padding-bottom: 4px;">'.$group['label'].'</div>';
								foreach($group['items'] as $item) {
									$h .= '<div style="font-family: '.static::FONT.'; font-size: 13px; line-height: 19px; color: '.static::MUTED.'; padding-top: 2px;">'.$item.'</div>';
								}
							$h .= '</div>';
						$h .= '</div>';

					}

				$h .= '</div>';

				$h .= self::getSpacer(12);

			}

		$h .= '</td></tr>';

		return $h;

	}

	/**
	 * Les cartes en texte : le titre de la carte, puis son geste suivi de son adresse. Sans le
	 * titre, deux cartes au même geste (« Voir la formation ») ne se distinguent plus.
	 */
	protected static function getTextCards(?string $title, array $cards): string {

		$lines = array_map(fn(array $card) => self::getTextLabel($card['title'])."\n".'- '.self::getTextLabel($card['label']).' : '.$card['url'], $cards);

		return ($title === NULL ? '' : self::getTextLabel($title)."\n").join("\n", $lines);

	}

	/**
	 * Les tuiles en texte : le nom de l'offre, puis chaque groupe et ses lignes précédées
	 * d'un tiret, une ligne vide entre deux groupes.
	 *
	 * Le texte se prend dans 'text', que l'appelant écrit à part : une ligne qui porte une
	 * pastille (badge()) perdrait sinon, par strip_tags(), la séparation entre son libellé et
	 * sa date.
	 */
	protected static function getTextTiles(?string $title, array $tiles): string {

		$parts = [];

		if($title !== NULL) {
			$parts[] = self::getTextLabel($title);
		}

		foreach($tiles as $tile) {

			$parts[] = self::getTextLabel($tile['name']);

			foreach($tile['text'] as $group) {
				$parts[] = self::getTextLabel($group['label'])."\n".join("\n", array_map(fn(string $item) => '- '.self::getTextLabel($item), $group['items']));
			}

		}

		return join("\n\n", $parts);

	}

	/**
	 * Un bloc de HTML rédigé, converti en texte sans y perdre les liens.
	 *
	 * \main\MailStructureUi::toText() passe par strip_tags(), qui réduit <a href="url">Label</a> à
	 * « Label » et jette l'URL. Un lecteur qui n'affiche que le texte se retrouverait
	 * alors devant un « Vérifier mon adresse e-mail » sur lequel il n'y a rien à
	 * cliquer. On sort donc l'URL avant de retirer les balises.
	 */
	protected static function getTextBlock(string $html): string {

		// Un lien devient « libellé : url ». Quand il n'enveloppe qu'une image, il n'a pas
		// de libellé : le rendre laisserait un « : http://… » orphelin, alors que le même
		// lien est déjà porté par le bouton juste à côté.
		$html = preg_replace_callback(
			'#<a\s[^>]*href="([^"]*)"[^>]*>(.*?)</a>#is',
			function(array $match) {

				$label = trim(strip_tags($match[2]));

				return ($label === '') ? '' : $label.' : '.$match[1];

			},
			$html
		);

		// Un paragraphe se sépare du suivant par une ligne vide. toText() ne rend qu'un
		// saut de ligne sur </p>, ce qui collerait les paragraphes les uns aux autres.
		$html = str_replace('</p>', "</p>\n", $html);

		return trim(\main\MailStructureUi::toText($html));

	}

	/**
	 * Le libellé d'une pastille, tenu sur une ligne.
	 *
	 * Il porte un <br/> pour se répartir sur deux lignes dans la pastille, qui n'a
	 * qu'une quarantaine de pixels de large. En texte, la coupure séparerait le libellé
	 * de son URL.
	 */
	protected static function getTextLabel(string $html): string {
		return trim(preg_replace('/\s+/u', ' ', \main\MailStructureUi::toText($html)));
	}

}
?>
