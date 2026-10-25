<?php
namespace main;

class HomeUi {

	use \Notifiable;

	public function __construct() {

		\Asset::css('main', 'home.css');

	}

	/**
	 * Les cartes que le code fermé ajoute à l'espace client, préparées par `CustomerSpaceAiLib::load()`.
	 */
	public function getCustomerSpace(array $customerSpace): string {
		return implode('', self::notify('getCustomerSpace', $customerSpace));
	}

	public function getCustomerSpaceEmpty(array $customerSpace): string {
		return implode('', self::notify('getCustomerSpaceEmpty', $customerSpace));
	}

	/**
	 * Les invitations en attente, celles des structures et, dans la même grille, celles que le code
	 * fermé y ajoute à partir de ce que `CustomerSpaceAiLib::load()` a préparé.
	 */
	public function getInvites(\Collection $cInvite, array $customerSpace): string {

		$decks = implode('', self::notify('getInvites', $customerSpace));

		if($cInvite->empty() and $decks === '') {
			return '';
		}

		return new \structure\InviteUi()->getPending($cInvite, $decks);

	}

	public function getStructures(\user\User $eUser, \Collection $ccStructure, \structure\Structure $eStructureCreate): string {

		$h = '';

		if($ccStructure->empty()) {

			if(new \structure\Structure()->canCreate()) {
				$h .= new \structure\MemberUi()->getNoStructures($eUser, $eStructureCreate);
			} else {
				$h .= '';
			}

		} else {

			$h .= new \structure\MemberUi()->getMyStructures($ccStructure);

		}

		return $h;

	}

	public function getLearning(): string {

		return '<p><a href="/formations">'.\Asset::icon('mortarboard').' '.s("Se former").'</a></p>';

	}

	public function getTraining(bool $hide = FALSE): string {

		if(\Clock::date() > MainSetting::LIMIT_TRAINING) {
			return '';
		}

		if(\user\ConnectionLib::isLogged() and $hide) {

			$eUser = \user\ConnectionLib::getOnline();

			$key = 'pub-2025001-'.$eUser['id'];
			$expires = strtotime(MainSetting::LIMIT_TRAINING.' + 7 DAYS');

			if(get_exists('training')) {
				\Cache::redis()->set($key, 5);
				return '';
			}

			if(\Cache::redis()->add($key, 0, $expires) === FALSE) {

				$newValue = \Cache::redis()->get($key) + 1;

				if($newValue > 3) {
					return '';
				}

				\Cache::redis()->set($key, $newValue, $expires);

			}

		}

		$h = '<div class="home-blog bg-training util-block stick-xs">';
			$h .= '<div>';
				$h .= \Asset::image('main', 'favicon.png').'';
			$h .= '</div>';
			$h .= '<div>';
				$h .= '<h4 class="mb-0 color-secondary">'.s("29 janvier 2025 dans le Puy-de-Dôme (63)").'</h4>';
				$h .= '<h2>';
					$h .= s("Formation sur {siteName} !");
				$h .= '</h2>';
				$h .= '<div>';
					$h .= '<p>'.s("Une formation à la journée finançable VIVEA est organisée pour {siteName}. Une occasion idéale pour prendre en main ou se perfectionner sur {siteName}, discuter des évolutions possibles sur le logiciel et échanger sur vos problématiques !").'</p>';
					$h .= '<a href="/presentation/formations" target="_blank" class="btn btn-secondary" style="margin-bottom: 0.25rem">'.\Asset::icon('chevron-right').' '.s("En savoir plus").'</a>';
					if(\user\ConnectionLib::isLogged() and $hide) {
						$h .= ' <a href="'.\util\HttpUi::setArgument(LIME_REQUEST, 'training', 1).'" class="btn btn-secondary" style="margin-bottom: 0.25rem">'.\Asset::icon('x-lg').' '.s("Ok, cacher ce message").'</a>';
					}
				$h .= '</div>';
			$h .= '</div>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Les formations de la FRAB AuRA et leurs prochaines dates, sur l'accueil des producteurs. Planche :
	 * http://127.0.0.1:8750/formations-frab-accueil/ (direction A, couleur de la marque, validée le
	 * 16/10/2026).
	 */
	public function getTrainingCourses(\structure\Community $eCommunity, \Collection $cCourse): string {

		$h = '<div class="home-training">';

			$h .= '<div class="home-training-friend home-training-abeille">'.\Asset::svg('main', 'friends/abeille.svg').'</div>';
			$h .= '<div class="home-training-friend home-training-papillon">'.\Asset::svg('main', 'friends/papillon-jaune.svg').'</div>';

			$h .= '<div class="home-training-header">';
				$h .= '<div class="home-training-logo">'.\Asset::svg('main', 'logo-frab-aura.svg').'</div>';
				$h .= '<div>';
					$h .= '<h2>'.s("Se former à {siteName}").'</h2>';
					$h .= '<p>'.s("Avec la FRAB AuRA du réseau bio · 2 h en visio, en petit groupe · prise en charge VIVEA").'</p>';
				$h .= '</div>';
			$h .= '</div>';

			$h .= '<div class="util-cards util-cards-3">';

				foreach($cCourse as $eCourse) {

					$h .= '<div class="util-card home-training-course">';
						$h .= '<h4>'.encode($eCourse['name']).'</h4>';

						foreach($eCourse['cDate'] as $eDate) {

							$date = substr($eDate['startAt'], 0, 10);

							$h .= '<div class="home-training-date">';
								$h .= \util\DateUi::calendar($date);
								$h .= '<span>'.s("{day}, {hours}", [
									'day' => \learning\DateUi::getDayName($date),
									'hours' => TrainingAiUi::getHours(substr($eDate['startAt'], 11, 5), substr($eDate['endAt'], 11, 5)),
								]).'</span>';
								$h .= TrainingAiUi::getEnroll($eCommunity, $eDate, 'btn btn-sm btn-terracota');
							$h .= '</div>';

						}

					$h .= '</div>';

				}

			$h .= '</div>';

			$h .= '<a href="/presentation/formations" class="home-training-all">'.s("Toutes les formations").' '.\Asset::icon('chevron-right').'</a>';

		$h .= '</div>';

		return $h;

	}

	// Le formulaire et son action vivent avec la newsletter dans base/mail, la page ne le demande que
	// si PageLib::hasNewsletterForm() l'a ouvert.
	public function getNewsletter(\user\User $eUser): string {
		return implode('', self::notify('getNewsletter', $eUser));
	}

	public function getBlog(\Collection $cNews): string {

		if($cNews->empty()) {

			$h = '<h2>'.s("Quoi de neuf sur {siteName} ?").'</h2>';

			$h .= '<div class="mb-2 bg-info util-block">';
				$h .= '<p style="font-weight: normal">'.s("Suivez le blog de {siteName} pour retrouver les annonces de nouvelles fonctionnalités, la feuille de route avec les priorités de développement pour les mois à venir  et des ressources pour faciliter la prise en main du site !").'</p>';
				$h .= '<a href="https://blog.ouvretaferme.org/" target="_blank" class="btn btn-secondary">'.\Asset::icon('chevron-right').' '.s("Découvrir le blog").'</a>';
			$h .= '</div>';

		} else {

			$h = '<h2>'.s("Du nouveau sur {siteName}").'</h2>';

				$h .= '<table class="table-unstyled tr-bordered">';
					$h .= '<tbody>';

						foreach($cNews as $position => $eNews) {
							$h .= '<tr '.($position === 0 ? 'style="font-weight: bold; font-size: 1.1rem"' : '').'>';
								$h .= '<td class="'.($position === 0 ? '' : 'color-muted').' text-end td-min-content">'.\util\DateUi::textual($eNews['publishedAt'], \util\DateUi::DATE).'</td> ';
								$h .= '<td>';
									$h .=  '<a href="https://blog.ouvretaferme.org/#news-'.$eNews['id'].'">'.encode($eNews['title']).'</a>';
								$h .= '</td> ';
							$h .= '</tr>';
						}
					$h .= '</tbody>';
				$h .= '</table>';
				$h .= '<div class="mb-2">';
					$h .= '<a href="https://blog.ouvretaferme.org/" target="_blank" class="btn btn-secondary">'.\Asset::icon('chevron-right').' '.s("Toutes les actualités").'</a>';
				$h .= '</div>';

		}

		return $h;

	}

	public function getCustomer(\user\User $eUser, \structure\Invite $eInvite): string {

		$class = ($eUser['roleCustomer'] ? 'selected' : '');

		$h = '<a href="/inscription/client'.$this->getInviteKey($eInvite).'" class="home-user-type home-user-type-'.$class.'">';
			$h .= '<div class="home-user-type-icon">👨‍🍳</div>';
			$h .= '<div class="home-user-type-label">'.s("Je suis client / cliente").'</div>';
		$h .= '</a>';

		return $h;

	}

	public function getProducer(\user\User $eUser, \structure\Invite $eInvite): string {

		$class = ($eUser['roleProducer'] ? 'selected' : '');

		$h = '<a href="/inscription/producteur'.$this->getInviteKey($eInvite).'" class="home-user-type home-user-type-'.$class.'">';
			$h .= '<div class="home-user-type-icon">'.\user\UserSetting::EMOJI['producer'].'</div>';
			$h .= '<div class="home-user-type-label">'.s("Je suis producteur / productrice").'</div>';
		$h .= '</a>';

		return $h;

	}

	public function getTeacher(\user\User $eUser, \structure\Invite $eInvite): string {

		$class = ($eUser['roleTeacher'] ? 'selected' : '');

		$h = '<a href="/inscription/formation'.$this->getInviteKey($eInvite).'" class="home-user-type home-user-type-'.$class.'">';
			$h .= '<div class="home-user-type-icon">'.\user\UserSetting::EMOJI['teacher'].'</div>';
			$h .= '<div class="home-user-type-label">'.s("Je suis un organisme de formation").'</div>';
		$h .= '</a>';

		return $h;

	}

	public function getConsultant(\user\User $eUser, \structure\Invite $eInvite): string {

		$class = ($eUser['roleConsultant'] ? 'selected' : '');

		$h = '<a href="/inscription/accompagnement'.$this->getInviteKey($eInvite).'" class="home-user-type home-user-type-'.$class.'">';
			$h .= '<div class="home-user-type-icon">'.\user\UserSetting::EMOJI['consultant'].'</div>';
			$h .= '<div class="home-user-type-label" style="font-size: 1rem">'.s("Je suis une structure qui accompagne les producteurs").'</div>';
		$h .= '</a>';

		return $h;

	}

	public function getInviteKey(\structure\Invite $eInvite): string {
		return $eInvite->notEmpty() ? '?invite='.$eInvite['key'] : '';
	}

}
?>
