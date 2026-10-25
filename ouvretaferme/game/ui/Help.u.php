<?php
namespace game;

class HelpUi {

	public function __construct() {

		\Asset::css('game', 'game.css');

	}

	public function getHome(Player $ePlayer): string {

		if(
			$ePlayer->empty() and
			\Clock::date() > GameSetting::PROMOTION_LIMIT
		) {
			return '';
		}

		$h = \game\DeskUi::getFonts();

		$h .= '<div class="game-home">';

			if($ePlayer->notEmpty()) {

				$h .= '<h4 class="mb-0">'.s("🎅 Des légumes pour les rennes 🦌").'</h4>';
				$h .= '<div>';
					$h .= '<a href="/jouer" data-ajax-navigation="never" class="btn btn-xl game-home-button">'.s("Continuer ma partie").'</a>';
				$h .= '</div>';

			} else {

				$h .= '<div>';
					$h .= '<h4 class="mb-2">'.s("Vous pensiez pouvoir partir tranquillement en vacances ?").'</h4>';
					$h .= '<h2>'.s("Détrompez-vous, le père Noël 🎅 a une ultime mission pour vous à accomplir en décembre avant de profiter d'un moment de repos bien mérité !").'</h2>';
					$h .= '<p>'.s("L'association qui édite {siteName} organise un jeu pour toutes celles et ceux qui utilisent le logiciel au quotidien pour gérer leur production ou acheter leurs produits locaux préférés.").'</p>';
					$h .= '<p class="mb-2">'.s("L'objectif est de lever des fonds pour financer et pérenniser l'association sur 2026 !").'</p>';
					$h .= '<div class="game-home-links">';
						$h .= '<a href="'.\Lime::getUrl().'/jouer" data-ajax-navigation="never" class="btn btn-xl game-home-button">'.s("Commencer à jouer").'</a> ';
						$h .= '<a href="'.\main\MainSetting::URL_ASSOCIATION.'" data-ajax-navigation="never" class="btn btn-xl game-home-button">'.s("En savoir plus").'</a> ';
					$h .= '</div>';
				$h .= '</div>';
				$h .= \Asset::image('game', 'board-3.jpg');

			}
		$h .= '</div>';
		
		return $h;
		
	}

	/**
	 * L'histoire du jeu, à l'accueil de la cour, sous ses deux phrases d'accroche en titres.
	 */
	public function getStory(): string {

		[$question, $mission] = $this->getStoryHook();

		$h = '<div class="game-intro">';
			$h .= '<h3>'.$question.'</h3>';
			$h .= '<h2>'.$mission.'</h2>';
			$h .= $this->getStoryBody();
		$h .= '</div>';

		return $h;

	}

	/**
	 * La même histoire dans la feuille des règles, où l'accroche se lit à la taille du texte, sous le titre de la feuille.
	 */
	public function getSynopsis(): string {

		[$question, $mission] = $this->getStoryHook();

		$h = '<p class="game-sheet-lead">'.$question.' '.$mission.'</p>';
		$h .= $this->getStoryBody();

		return $h;

	}

	protected function getStoryHook(): array {

		return [
			s("La fin d'année approche et vous pensiez pouvoir partir tranquillement en vacances ?"),
			s("Détrompez-vous, il vous reste une ultime mission à accomplir en décembre avant de profiter d'un moment de repos bien mérité.")
		];

	}

	protected function getStoryBody(): string {

		$h = '<div class="util-block">';
			$h .= '<p>'.s("Le père Noël 🎅 et les lutins sont bien en train de confectionner les 247 millions de cadeaux 🎁 commandés par les petits enfants français, bien entendus fabriqués en bois et emballés avec un papier recyclable. Par contre, petit problème, le père Noël a encore oublié de s'occuper de la logistique pour nourrir ses rennes, qui ne mangent que des légumes biologiques 🙄.").'</p>';
			$h .= '<p>'.s("C'est donc à vous que revient cette lourde mission. En lien avec les autres fermes, il vous reste seulement quelques semaines pour cultiver 🥕 et autres 🫛 pour qu'ils puissent se ravitailler chez vous dans la nuit du 24 décembre et distribuer sereinement les cadeaux aux enfants.").'</p>';
			$h .= '<p class="text-center">'.s("<b>Pas de légumes pour les rennes, pas de cadeaux 😞<br/>À vous de jouer !</b>").'</p>';
		$h .= '</div>';

		return $h;

	}

	public function getEnd(int $points): string {

		$h = '<div class="game-intro">';
			$h .= '<h3>'.s("Vous avez collectivement préparé de quoi nourrir {value} rennes.", $points).'</h3>';
			$h .= '<div class="util-block">';
				$h .= '<p>'.s(" Avec 247 millions de cadeaux 🎁 à distribuer, cela fait donc {value} cadeaux à tirer par chaque renne. Tout devrait bien se passer cette fois ci, il n'y a plus qu'à espérer que le traineau du père Noël soit suffisamment grand !", round(247000000 / $points)).'</p>';
				$h .= '<p class="text-center">'.s("<b>L'année prochaine et pour éviter de vous solliciter à nouveau, le Père Noël s'est engagé à utiliser ses crédits Vivea pour se former à la logistique et mieux gérer son pic d'activité saisonnier.</b>").'</p>';
			$h .= '</div>';
			$h .= '<div class="game-intro-disclaimer">';
				$h .= '<h4>'.s("Mince ! ").'</h4>';
				$h .= '<p>'.s("La formation <u>Apprendre à gérer la logistique de son entreprise dans le cadre d'une activité de distribution de 🎁 en circuits courts</u> est prévue le 31 septembre 2026. On nous informe déjà que Vivea aura épuisé tous ses fonds à cette date...").'</p>';
			$h .= '</div>';

		$h .= '</div>';

		return $h;

	}

	public function getRules(Player $ePlayer, bool $new = FALSE): string {

		$h = '<div class="util-label">'.s("Les règles du jeu").'</div>';
		$h .= '<div class="util-block">';
			$h .= '<p>'.s("Vous démarrez avec un plateau de 16 parcelles prêtes à être cultivées !").'</p>';
			$h .= '<p>'.s("Vous disposez chaque jour de {value} heures de temps de travail que vous pouvez répartir sur les différentes actions :", GameSetting::TIME_DAY).'</p>';
			$h .= '<ul>';
				$h .= '<li>'.GameSetting::EMOJI_SEEDLING.'  '.s("<b>SEMER</b> une nouvelle culture sur une de vos parcelles coûte {value}", PlayerUi::getTime(GameSetting::TIME_SEEDLING)).'</i></li>';
				$h .= '<li>'.GameSetting::EMOJI_HARVEST.'  '.s("<b>RÉCOLTER</b> les légumes à la fin d'une de vos cultures coûte {value}", PlayerUi::getTime(GameSetting::TIME_HARVESTING)).'</li>';
				$h .= '<li>'.GameSetting::EMOJI_WEED.'  '.s("<b>DÉSHERBER</b> une de vos cultures avance sa date de récolte de {value} jours et coûte {time}", ['value' => GameSetting::BONUS_WEED, 'time' => PlayerUi::getTime(GameSetting::TIME_WEED)]).'</li>';
				$h .= '<li>'.GameSetting::EMOJI_WATERING.'  '.s("<b>ARROSER</b> une de vos cultures augmente sa productivité et coûte {value}", PlayerUi::getTime(GameSetting::TIME_WATERING)).'</li>';
			$h .= '</ul>';
			$h .= '<p>'.s("Votre compteur de temps de travail est remis à zéro chaque nuit à minuit. Les actions décomptent votre temps de travail, mais vous n'avez pas à attendre, elles sont réalisées immédiatement !").'</p>';
			$h .= '<p>'.s("Une fois qu'une parcelle est semée, elle est semée et vous ne pouvez pas revenir en arrière.").'</p>';
			$h .= '<p>'.s("Le 30 novembre et les 4 et 10 décembre, vous débloquez des plateaux supplémentaires.").'</p>';
		$h .= '</div>';
		$h .= '<div class="util-label">'.s("Comment gagner ?").'</div>';
		$h .= '<div class="util-block">';
			$h .= '<p>'.s("Le {date} à {time}, les rennes du Père Noël viendront manger les légumes que vous aurez récoltés pour eux. Votre objectif est de voir passer un maximum de rennes sur votre partie, et pour cela vous devez :", ['date' => \util\DateUi::textual(GameSetting::END, \util\DateUi::DAY_MONTH), 'time' => GameSetting::END_TIME]).'</p>';
			$h .= '<ul>';
				$h .= '<li>'.s("<b>PRODUIRE</b> le plus de légumes possibles <i>(1 légume attirera 1 renne)</i>").'</li>';
				$h .= '<li>'.s("<b>CUISINER</b> des soupes de légumes <i>(1 soupe attirera 10 rennes)</i>").'</li>';
			$h .= '</ul>';
			$h .= '<p>'.s("Une soupe de légumes se cuisine en utilisant 1 légume de chacune des 5 espèces proposées dans le jeu.").'</p>';
		$h .= '</div>';
		$h .= '<div class="util-label">'.s("Ce qu'il y a à gagner").'</div>';
		$h .= '<div class="util-block">';
			$h .= '<p>'.s("Personne ne le sait vraiment, c'est le Père Noël qui décidera le 24 décembre, mais encore faut-il que les rennes aient été attirés en masse sur le jeu... Qui tirera le traineau sinon ?").'</p>';
		$h .= '</div>';
		$h .= '<div class="util-label">'.s("Les bonus").'</div>';
		$h .= '<div class="util-block">';
			$h .= '<p>'.s("Une fois par jour, un ami peut vous remotiver pour vous faire gagner {value} de travail, et vous-même pouvez remotiver un ami.", PlayerUi::getTime(GameSetting::BONUS_MOTIVATION)).'</p>';
			$h .= '<p>'.s("Si vous êtes membre de l'équipe d'une ferme qui a adhéré à l'association {siteName}, vous débloquez les deux bonus suivants :").'</p>';
			$h .= '<ul class="mb-1">';
				$h .= '<li>'.s("{premium} de travail par jour au lieu de {value}", ['value' => PlayerUi::getTime(GameSetting::TIME_DAY), 'premium' => PlayerUi::getTime(GameSetting::TIME_DAY_PREMIUM)]).'</li>';
				$h .= '<li>'.s("Manger une soupe que vous avez cuisinée vous permet d'obtenir {value} de temps de travail en plus", PlayerUi::getTime(GameSetting::BONUS_SOUP)).'</li>';
			$h .= '</ul>';
		$h .= '</div>';

		if($new) {
			$h .= '<div class="game-intro-disclaimer">';
				$h .= '<div class="util-label">'.s("Pour commencer").'</div>';
				$h .= '<p>'.s("Choisissez une parcelle sur votre terrain et semez une première culture !").'</p>';
			$h .= '</div>';
		} else {
			$h .= '<div class="util-label">'.s("Recommencer").'</div>';
			$h .= '<div class="util-block">';
				$h .= '<p class="text-center">'.s("Votre début de partie est une catastrophe ?").' '.s("Vous pouvez recommencer de zéro, ça passe pour cette fois...").'</p>';
				$h .= '<p class="text-center">';
					$h .= '<a data-ajax="/game/action:doRestart" class="btn btn-game" data-confirm="'.s("Tout ce que vous avez réalisé jusqu'à présent sera réinitialisé. Voulez-vous continuer ?").'">'.s("Recommencer ma partie").'</a>';
				$h .= '</p>';
			$h .= '</div>';
		}

		return $h;

	}

	/**
	 * Le tableau des cultures, puis celui des plantes compagnes, qui ne se récoltent pas et dont l'avantage se dit en
	 * toutes lettres.
	 */
	public function getCrops(\Collection $cGrowing): string {

		$h = '<div class="util-table" style="grid-template-columns: minmax(0, 1fr) auto auto auto">';

			$h .= '<div class="util-table-head">';
				$h .= '<div>'.s("Culture").'</div>';
				$h .= '<div class="text-end">'.s("Durée").'</div>';
				$h .= '<div class="text-end">'.s("Rendement").'</div>';
				$h .= '<div class="text-end">'.s("Arrosage").'</div>';
			$h .= '</div>';

			foreach($cGrowing as $eGrowing) {

				if($eGrowing['harvest'] === NULL) {
					continue;
				}

				$h .= '<div class="util-table-row">';
					$h .= '<div class="flex-align-center">'.GrowingUi::getVignette($eGrowing, '1.5rem').'  <b>'.$eGrowing['name'].'</b></div>';
					$h .= '<div class="text-end">'.($eGrowing['days'] === NULL ? s("Pérenne") : p("{value} jour", "{value} jours", $eGrowing['days'])).'</div>';
					$h .= '<div class="text-end">'.$eGrowing['harvest'].'</div>';
					$h .= '<div class="text-end">'.($eGrowing['bonusWatering'] === NULL ? '-' : s("+ {value}", $eGrowing['bonusWatering'])).'</div>';
				$h .= '</div>';

			}

		$h .= '</div>';

		$h .= '<div class="util-label">'.s("Plantes compagnes").'</div>';
		$h .= '<p class="util-annotation">'.s("Les avantages des plantes compagnes sont cumulables.").'</p>';

		$h .= '<div class="util-table" style="grid-template-columns: auto auto minmax(0, 1fr)">';

			$h .= '<div class="util-table-head">';
				$h .= '<div>'.s("Plante").'</div>';
				$h .= '<div class="text-end">'.s("Durée").'</div>';
				$h .= '<div>'.s("Avantage").'</div>';
			$h .= '</div>';

			foreach($cGrowing as $eGrowing) {

				if($eGrowing['harvest'] !== NULL) {
					continue;
				}

				$h .= '<div class="util-table-row">';
					$h .= '<div class="flex-align-center">'.GrowingUi::getVignette($eGrowing, '1.5rem').'  <b>'.$eGrowing['name'].'</b></div>';
					$h .= '<div class="text-end">'.($eGrowing['days'] === NULL ? s("Pérenne") : p("{value} jour", "{value} jours", $eGrowing['days'])).'</div>';
					$h .= '<div>';
						$h .= match($eGrowing['fqn']) {
							'luzerne' => s("<b>+ {value}</b> de rendement sur les cultures des parcelles adjacentes", GameSetting::BONUS_LUZERNE),
							'pivoine' => s("<b>- {value}<small>min</small></b> de temps de récolte sur toutes les autres cultures du plateau", GameSetting::BONUS_PIVOINE).'<br/>'.s("- maximum 6 pivoines par plateau")
						};
					$h .= '</div>';
				$h .= '</div>';

			}

		$h .= '</div>';

		return $h;

	}
}
?>
