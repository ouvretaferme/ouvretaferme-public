<?php
namespace dev;

/**
 * Inventaire des classes API que la documentation publique ne publie pas, `const DOCUMENTED = FALSE`
 * sur leur classe API versionnée les retirant de openapi.json et de Postman sans toucher à leur
 * routage. C'est le seul contrôle qui attrape une constante mal orthographiée : `defined()` la tient
 * alors pour absente, la classe redevient documentée en silence, et l'inventaire la réclame ici.
 */
class ApiUndocumentedSentinelTest extends \Test {

	/**
	 * Une entrée par classe, avec sa raison.
	 */
	const UNDOCUMENTED = [];

	public function testInventory(): void {

		$found = array_values(array_diff(ApiDocLib::getExposedModules(), ApiDocLib::getDocumentedModules()));

		$missing = array_values(array_diff(self::UNDOCUMENTED, $found));
		$extra = array_values(array_diff($found, self::UNDOCUMENTED));

		$this->assertEquals([], $missing, 'déclarées ici mais toujours documentées : '.implode(', ', $missing));
		$this->assertEquals([], $extra, 'non documentées (const DOCUMENTED = FALSE) mais non déclarées ici : '.implode(', ', $extra));

	}

}
?>
