<?php
namespace dev;

/**
 * L'écriture de l'API s'ouvre après la lecture, par ApiSetting::IS_API_WRITE_ACTIVE. La constante
 * vaut TRUE en dev pour que les tests d'écriture tournent : l'état fermé ne s'éprouve donc que par le
 * second argument de isScopeOpen(), que lisent le refus à l'appel et les scopes proposés à une clé.
 *
 * Lancement : dev/test package=dev file=ApiWriteScope
 */
class ApiWriteScopeTest extends \Test {

	public function testClosedWriteRefusesOnlyWrite(): void {

		foreach(\api\ApiSetting::PERSONAL_SCOPE_NAMES as $scope) {
			$this->assertEquals(str_ends_with($scope, ':read'), \api\ApiSetting::isScopeOpen($scope, FALSE), $scope.' fermé');
			$this->assertEquals(TRUE, \api\ApiSetting::isScopeOpen($scope, TRUE), $scope.' ouvert');
		}

	}

	public function testDevKeepsWriteOpen(): void {

		$this->assertEquals(TRUE, \api\ApiSetting::IS_API_WRITE_ACTIVE);

	}

	public function testRefusalHasMessage(): void {

		$this->assertEquals("L'accès production:write n'est pas encore ouvert", \api\AlertUi::getError('Api::scope_not_open', ['scope' => 'production:write']));

	}

}
?>
