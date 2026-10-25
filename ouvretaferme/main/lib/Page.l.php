<?php
namespace main;

class PageLib {

	use \Notifiable;

	/**
	 * Un visiteur arrive sur la présentation du site, qui n'est pas publiée : sans elle, l'accueil
	 * est la connexion.
	 */
	public static function displayAnonymousHome(\stdClass $data): void {

		if(\structure\StructureSetting::hasPresentationPackage()) {
			self::notify('displayAnonymousHome', $data);
		}

		throw new \RedirectAction('/user/log:form');

	}

	/**
	 * La newsletter de l'accueil vit dans base/mail, que la version publique n'installe pas, et son
	 * formulaire ne se propose pas à qui y est déjà inscrit.
	 */
	public static function hasNewsletterForm(\user\User $eUser): bool {

		if(\structure\StructureSetting::hasMailPackage()) {
			return (self::notify('hasNewsletter', $eUser)[0] === FALSE);
		} else {
			return FALSE;
		}

	}

	/**
	 * Une page qui sert à tous les comptes d'un appareil, comme la coquille de la caisse, n'ouvre pas de session. La
	 * caisse appartient au package selling, que l'export public retire.
	 */
	protected static function isWithoutSession(): bool {

		if(\structure\StructureSetting::hasCommercialisationApp()) {

			// notify() prend ses arguments par référence
			$page = LIME_PAGE_REQUESTED;

			return in_array(TRUE, self::notify('isWithoutSession', $page), TRUE);

		} else {
			return FALSE;
		}

	}

	public static function common(\stdClass $data): void {

		$data->tos = NULL;
		$data->isTab = FALSE;
		$data->isTabEnabled = FALSE;

		if(\Route::getRequestedWith() === 'cli') {
			return;
		}

		$data->browserObsolete = \util\HttpLib::isObsolete();

		if(LIME_REQUEST_PATH !== '/maintenance' and MainSetting::MAINTENANCE === TRUE) {
			throw new \RedirectAction('/maintenance');
		}

		if(
			str_starts_with(LIME_PAGE_REQUESTED, 'public/') or
			self::isWithoutSession()
		) {
			$data->eUserOnline = new \user\User();
			$data->userDeletedAt = NULL;
			$data->isLogged = FALSE;
			return;
		}

		// Demo
		if(OTF_DEMO) {

			\structure\DemoLib::activate();

			if(\structure\DemoLib::isRebuilding()) {

				if(LIME_PAGE_REQUESTED !== 'maintenance:demo') {
					throw new \RedirectAction(\Lime::getUrl().'/maintenance:demo');
				}

				$data->eUserOnline = new \user\User();
				$data->userDeletedAt = NULL;
				$data->isLogged = FALSE;

				return;

			}

			// Open session
			\session\SessionLib::init();

			if(\user\ConnectionLib::getOnline()->empty()) {
				\user\ConnectionLib::logInUser(new \user\User(['id' => \structure\DemoLib::USER]));
			}

		} else {

			// Open session
			\session\SessionLib::setCache(new \RedisCache());
			\session\SessionLib::init();

		}

		$data->isTab = \session\SessionLib::isTab();
		$data->isTabEnabled = \session\SessionLib::isTabEnabled();

		// Add user to page
		$data->eUserOnline = \user\ConnectionLib::getOnline();

		// Add log info
		$data->isLogged = \user\ConnectionLib::isLogged();

		if($data->isLogged) {

			$data->userDeletedAt = \session\SessionLib::get('userDeletedAt');
			$data->ccStructureUser = \structure\StructureLib::getOnline();
			$data->nStructureUser = $data->ccStructureUser->countByDepth();
			$data->hasLearning = \learning\CourseLib::isOpenByStructures($data->ccStructureUser);

			$data->hasAvatar = \map\SceneLib::isOpenByStructures($data->ccStructureUser);

			// L'avatar accompagne l'utilisateur connecté comme sa photo : un écran qui l'affiche n'a pas à le recharger
			$data->eUserOnline['eUserAvatar'] = \user\UserAvatarLib::getByUser($data->eUserOnline);

			$data->notificationsUnseen = \structure\MemberLib::getUnseenByStructure();

			// Les constructeurs passent avant la page : sur « J'accepte » ou le refus, la tacite devancerait le geste
			if(in_array(LIME_PAGE_REQUESTED, ['user/tosAi:doAccept', 'user/tosAi:doLater'], TRUE)) {
				$data->tos = \user\TosAiLib::getPending($data->eUserOnline);
			} else {
				$data->tos = \user\TosAiLib::acceptOnUse($data->eUserOnline);
			}

		} else {

			$data->userDeletedAt = NULL;
			$data->ccStructureUser = new \Collection();
			$data->nStructureUser = 0;
			$data->hasLearning = FALSE;
			$data->hasAvatar = FALSE;
			$data->notificationsUnseen = [];

		}

		$data->logInExternal = \user\ConnectionLib::checkLoginExternal();

		if($data->logInExternal !== NULL) {
			\user\AdminLogAiLib::recordExternal($data->logInExternal[1], $data->logInExternal[0]);
		}
/*
		$package = $data->__page['package'];
		$app = \Package::getApp($package);

		if(
			($app === 'accounting' or $package === 'securing') and
			str_starts_with(LIME_REQUEST_PATH, '/public') === FALSE and
			str_starts_with(LIME_REQUEST_PATH, '/articles') === FALSE and
			str_starts_with(LIME_REQUEST_PATH, '/invoicing/admin') === FALSE
		) {

			\company\CompanyLib::load($data);

		}
*/

		// In some specific cases of redirections after network login we need to load datas before displaying a message
		$error = GET('error');
		if(
			in_array($error, ['user:connectionBanned', 'user:signUpBanned']) and
			\session\SessionLib::exists('activeBanForUser')
		) {
			$data->errorOptions = \session\SessionLib::get('activeBanForUser');
			\session\SessionLib::delete('activeBanForUser');
		}

		$data->tip = NULL;

		\structure\StructureSentinelAiLib::resolve($data);

	}

}
?>
