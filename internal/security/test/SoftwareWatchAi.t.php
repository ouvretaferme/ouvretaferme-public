<?php
namespace security;

/**
 * La hiérarchie de la veille technique, sur des fiches écrites en ligne : gravité d'une faille,
 * version qui la corrige, ce qu'un constat retient, et ce que chaque message porte.
 *
 * Aucun appel réseau : les sources se prouvent à part, par
 * `php framework/lime.php -a ouvretaferme -e dev security/softwareAi:witness`.
 *
 * Lancement : dev/test package=security file=SoftwareWatchAi
 */
class SoftwareWatchAiTest extends \Test {

	public function testCvss3Score(): void {

		// Valeurs de référence du calculateur du FIRST.
		$this->assertEquals(9.8, OsvAiLib::getCvss3Score('CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H'));
		$this->assertEquals(6.1, OsvAiLib::getCvss3Score('CVSS:3.1/AV:N/AC:L/PR:N/UI:R/S:C/C:L/I:L/A:N'));
		$this->assertEquals(10.0, OsvAiLib::getCvss3Score('CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:C/C:H/I:H/A:H'));
		$this->assertEquals(0.0, OsvAiLib::getCvss3Score('CVSS:3.1/AV:L/AC:L/PR:L/UI:N/S:U/C:N/I:N/A:N'));

	}

	public function testLevel(): void {

		// La priorité de la distribution l'emporte sur un score CVSS plus alarmant.
		$this->assertEquals(OsvAiLib::LOW, OsvAiLib::getLevel(['severity' => [
			['type' => 'CVSS_V3', 'score' => 'CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H'],
			['type' => 'Ubuntu', 'score' => 'negligible'],
		]]));

		$this->assertEquals(OsvAiLib::MEDIUM, OsvAiLib::getLevel(['database_specific' => ['severity' => 'MODERATE']]));
		$this->assertEquals(OsvAiLib::CRITICAL, OsvAiLib::getLevel(['severity' => [['type' => 'CVSS_V3', 'score' => 'CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H']]]));

		// Rien ne dit la gravité : moyenne, plutôt que tue.
		$this->assertEquals(OsvAiLib::MEDIUM, OsvAiLib::getLevel([]));

	}

	public function testDescribeFixed(): void {

		$vulnerability = [
			'id' => 'GHSA-78xj-cgh5-2h22',
			'published' => '2024-02-08T00:00:00Z',
			'affected' => [
				['package' => ['ecosystem' => 'npm', 'name' => 'ip'], 'ranges' => [['events' => [['introduced' => '0'], ['fixed' => '1.1.9']]]]],
				['package' => ['ecosystem' => 'npm', 'name' => 'ip'], 'ranges' => [['events' => [['introduced' => '2.0.0'], ['fixed' => '2.0.1']]]]],
				['package' => ['ecosystem' => 'npm', 'name' => 'other'], 'ranges' => [['events' => [['introduced' => '0'], ['fixed' => '9.0.0']]]]],
			],
		];

		// La correction de la branche installée, jamais celle d'une branche plus ancienne.
		$this->assertEquals('1.1.9', OsvAiLib::describe($vulnerability, 'npm', 'ip', '1.1.8')['fixed']);
		$this->assertEquals('2.0.1', OsvAiLib::describe($vulnerability, 'npm', 'ip', '2.0.0')['fixed']);
		$this->assertEquals(NULL, OsvAiLib::describe($vulnerability, 'npm', 'ip', '2.0.1')['fixed']);

		$this->assertEquals('2024-02-08', OsvAiLib::describe($vulnerability, 'npm', 'ip', '2.0.0')['published']);

	}

	public function testBuildVulnerable(): void {

		$query = ['ecosystem' => 'npm', 'package' => 'ws', 'version' => '8.14.2', 'exposure' => SoftwareAiLib::EXPOSED, 'source' => 'test'];

		$high = self::vulnerability('A', OsvAiLib::HIGH, '8.17.1', '2026-06-15');
		$medium = self::vulnerability('B', OsvAiLib::MEDIUM, '8.21.0', '2026-05-01');
		$criticalUnfixed = self::vulnerability('C', OsvAiLib::CRITICAL, NULL, '2026-04-01');
		$highUnfixed = self::vulnerability('D', OsvAiLib::HIGH, NULL, '2026-07-01');

		$finding = SoftwareWatchAiLib::buildVulnerable($query, [$high, $medium, $highUnfixed], SoftwareWatchAiLib::LIBRARY);

		// La faille sans correctif et non critique ne compte pas : rien ne la ferme de notre côté.
		$this->assertEquals(OsvAiLib::HIGH, $finding['level']);
		$this->assertEquals('8.14.2 installée · 1 élevée, 1 moyenne, corrigées en 8.21.0', $finding['text']);
		$this->assertEquals('2026-06-15', $finding['date']);
		$this->assertEquals(['A', 'B'], array_column($finding['vulnerabilities'], 'id'));

		// Une faille critique se garde même sans correctif.
		$this->assertEquals(OsvAiLib::CRITICAL, SoftwareWatchAiLib::buildVulnerable($query, [$criticalUnfixed], SoftwareWatchAiLib::LIBRARY)['level']);

		$this->assertEquals(NULL, SoftwareWatchAiLib::buildVulnerable($query, [$highUnfixed], SoftwareWatchAiLib::LIBRARY));

		// Une bibliothèque interne compte un cran de moins, et une faille critique qui descend en
		// élevée sans correctif ne se garde plus.
		$internal = ['exposure' => SoftwareAiLib::INTERNAL] + $query;

		$this->assertEquals(OsvAiLib::MEDIUM, SoftwareWatchAiLib::buildVulnerable($internal, [$high], SoftwareWatchAiLib::LIBRARY)['level']);
		$this->assertEquals(NULL, SoftwareWatchAiLib::buildVulnerable($internal, [$criticalUnfixed], SoftwareWatchAiLib::LIBRARY));

		$this->assertEquals('8.14.2 installée · 1 élevée, corrigée par une mise à jour du système', SoftwareWatchAiLib::buildVulnerable($query, [$high], SoftwareWatchAiLib::SYSTEM)['text']);

	}

	public function testGetNew(): void {

		$findings = [
			self::finding('critical-new', OsvAiLib::CRITICAL, '2026-10-21'),
			self::finding('high-old', OsvAiLib::HIGH, '2026-09-01'),
			self::finding('high-undated', OsvAiLib::HIGH, NULL),
			self::finding('medium-new', OsvAiLib::MEDIUM, '2026-10-21'),
		];

		$this->assertEquals(['critical-new'], array_column(SoftwareWatchAiLib::getNew($findings, '2026-10-20'), 'key'));

	}

	public function testEmbeds(): void {

		$findings = [
			self::finding('openssl', OsvAiLib::CRITICAL, '2026-10-21', SoftwareWatchAiLib::SYSTEM),
			self::finding('expat', OsvAiLib::MEDIUM, '2026-10-01', SoftwareWatchAiLib::SYSTEM),
			self::finding('pcre2', OsvAiLib::MEDIUM, '2026-10-01', SoftwareWatchAiLib::SYSTEM),
			self::finding('Redis', OsvAiLib::LOW, '2026-09-17', SoftwareWatchAiLib::SOFTWARE),
		];

		$unread = ['SumUp' => 'le flux a répondu 503'];

		// Le message du jour ne porte que le critique et l'élevé.
		$daily = SoftwareWatchAiLib::getEmbeds($findings, $unread, 0, FALSE);
		$this->assertEquals(['🔴 Critique'], array_column($daily, 'title'));

		$weekly = SoftwareWatchAiLib::getEmbeds($findings, $unread, 2, TRUE);
		$this->assertEquals(['🔴 Critique', '🟡 À planifier', '⚪ Quand l\'occasion se présente', 'Ce que la veille n\'a pas vu'], array_column($weekly, 'title'));

		// Les paquets du système se résument en une ligne, un logiciel garde la sienne.
		$this->assertEquals(1, substr_count($weekly[1]['description'], "\n") + 1);
		$this->assertTrue(str_contains($weekly[1]['description'], '2 à mettre à jour (expat, pcre2)'));

		$this->assertTrue(str_contains($weekly[3]['description'], 'SumUp non lu, le flux a répondu 503'));
		$this->assertTrue(str_contains($weekly[3]['description'], '2 éléments écartés'));

	}

	public function testEmbedsEscapeRemoteText(): void {

		// Un titre de flux compromis ne pose ni lien masqué ni mise en forme dans le salon.
		$finding = SoftwareWatchAiLib::finding('service:feed', SoftwareWatchAiLib::SERVICE, SoftwareWatchAiLib::EOL, 'Flux', OsvAiLib::CRITICAL, '**Urgent** [ouvrir](https://evil.test)', 'http://evil.test', '2026-10-21');

		$description = SoftwareWatchAiLib::getEmbeds([$finding], [], 0, FALSE)[0]['description'];

		$this->assertEquals('• **Flux** · \*\*Urgent\*\* \[ouvrir\]\(https://evil.test\)', $description);

	}

	public function testRecommendedCycle(): void {

		$cycles = [
			['name' => '26.7', 'isLts' => FALSE, 'isEol' => FALSE],
			['name' => '9.7', 'isLts' => TRUE, 'isEol' => FALSE],
			['name' => '8.4', 'isLts' => TRUE, 'isEol' => FALSE],
			['name' => '8.0', 'isLts' => FALSE, 'isEol' => TRUE],
		];

		$this->assertEquals('9.7', ReleaseAiLib::getRecommendedCycle($cycles)['name']);

		// Un produit sans branche suivie à long terme monte à la plus récente.
		$this->assertEquals('156', ReleaseAiLib::getRecommendedCycle([['name' => '156', 'isLts' => FALSE, 'isEol' => FALSE], ['name' => '155', 'isLts' => FALSE, 'isEol' => TRUE]])['name']);

	}

	public function testFindCycle(): void {

		$cycles = [['name' => '8'], ['name' => '8.4'], ['name' => '8.0']];

		$this->assertEquals('8.4', ReleaseAiLib::findCycle($cycles, '8.4.26')['name']);
		$this->assertEquals(NULL, ReleaseAiLib::findCycle($cycles, '7.4.33'));

	}

	private static function vulnerability(string $id, int $level, ?string $fixed, string $published): array {
		return ['id' => $id, 'summary' => '', 'published' => $published, 'level' => $level, 'fixed' => $fixed, 'url' => 'https://osv.dev/vulnerability/'.$id];
	}

	private static function finding(string $name, int $level, ?string $date, string $group = SoftwareWatchAiLib::SOFTWARE): array {
		return SoftwareWatchAiLib::finding($name, $group, SoftwareWatchAiLib::VULNERABLE, $name, $level, 'texte', '', $date);
	}

}
?>
