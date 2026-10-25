<?php
namespace structure;

/**
 * Exécuter une route de groupe dans un test : ce que `CommunityVegetableAi` et `CommunityMapAi`
 * partagent, sur le décor de `CommunityFixtureAiTest`.
 * Chaque méthode tourne dans une transaction annulée en `tearDown()`.
 */
abstract class CommunityRouteAiTest extends CommunityFixtureAiTest {

	public function setUp(): void {
		Feature::model()->beginTransaction();
		$this->forgetStatics();
	}

	public function tearDown(): void {
		Feature::model()->rollBack();
		$this->forgetStatics();
	}

	/**
	 * La fonctionnalité et le membre en ligne sont mémoïsés par processus : sans cette purge, une
	 * écriture de la transaction resterait invisible, ou survivrait à son annulation.
	 */
	protected function forgetStatics(): void {

		foreach([Structure::class, ConfigurationLib::class, FeatureLib::class, MemberLib::class] as $class) {

			foreach(new \ReflectionClass($class)->getProperties(\ReflectionProperty::IS_STATIC) as $property) {

				if($property->getDeclaringClass()->getName() === $class and $property->hasDefaultValue()) {
					$property->setValue(NULL, $property->getDefaultValue());
				}

			}

		}

	}

	/**
	 * Ce que la route rend pour ce lecteur : les données passées à la vue, ou la classe de l'action
	 * qui a interrompu la page. Un chemin vide est l'accueil du groupe.
	 */
	protected function run(string $path, int $user, array $get = []): \stdClass|string {

		\user\ConnectionLib::setOnline(\user\UserLib::getById($user));

		$method = new \ReflectionProperty(\Route::class, 'requestMethod');
		$methodBefore = $method->getValue();
		$method->setValue(NULL, 'GET');

		if(defined('LIME_URL') === FALSE) {
			define('LIME_URL', \Lime::getUrl());
		}

		$getBefore = $_GET;
		$_GET = ['id' => (string)$this->communitySharing] + $get;

		// Ce que le démarrage de l'application pose avant toute page (`ouvretaferme/Lime.s.php`),
		// et que `Page::execute()` ne rejoue pas.
		$data = new \stdClass();
		$data->eUserOnline = \user\ConnectionLib::getOnline();

		$view = new class(NULL, fn() => NULL) extends \View {

			public ?\stdClass $data = NULL;

			public function getContentType(): string {
				return 'text/html';
			}

			public function render(\stdClass $data, ?\Template $template = NULL): void {
				$this->data = $data;
			}

		};

		try {
			\Page::execute(\Route::getCompatiblePages(rtrim('groupe/'.$this->communitySharing.'/'.$path, '/')), $data);
			$result = 'aucune action';
		} catch(\CommunityAction $action) {
			$action->render($view);
			$result = $view->data ?? new \stdClass();
		} catch(\Action $action) {
			$result = get_class($action);
		} finally {
			$_GET = $getBefore;
			$method->setValue(NULL, $methodBefore);
			\user\ConnectionLib::setOnline(new \user\User());
		}

		return $result;

	}

}
?>
