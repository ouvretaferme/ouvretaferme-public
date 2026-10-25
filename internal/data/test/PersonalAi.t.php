<?php
namespace data;

/**
 * Une valeur masquée par `PersonalAiLib` ne laisse passer que son empreinte et les mots de la liste
 * fermée, et deux valeurs égales se reconnaissent encore. Aucune base n'est lue.
 *
 * Lancement : dev/test package=data file=PersonalAi
 */
class PersonalAiTest extends \Test {

	public function testHideKeepsOnlyFingerprintAndTokens(): void {

		$hidden = PersonalAiLib::hide('VIR SEPA SUMUP PAYMENTS Jean Dupont');

		$this->assertEquals(1, preg_match('/^#[0-9a-f]{8} VIR SEPA SUMUP$/', $hidden), 'empreinte suivie des seuls jetons connus : '.$hidden);
		$this->assertEquals(FALSE, str_contains(strtoupper($hidden), 'DUPONT'), 'le nom ne sort pas');

		$this->assertEquals(PersonalAiLib::hide('VIR SEPA SUMUP PAYMENTS Jean Dupont'), $hidden, 'même valeur, même empreinte');
		$this->assertDiffer(PersonalAiLib::hide('VIR SEPA SUMUP PAYMENTS Jean Durand'), $hidden, 'autre valeur, autre empreinte');

		$this->assertEquals(1, preg_match('/^#[0-9a-f]{8}$/', PersonalAiLib::hide('Jacques Cbrown')), 'un mot qui contient un jeton ne le donne pas');
		$this->assertEquals(['PRELEVEMENT'], PersonalAiLib::getTokens('Prélèvement MSA'), 'un jeton accentué est reconnu');
		$this->assertEquals('-', PersonalAiLib::hide(NULL), 'NULL');
		$this->assertEquals('-', PersonalAiLib::hide(''), 'chaîne vide');

	}

	public function testHideAccountKeepsLeadingDigits(): void {

		$this->assertEquals('51200000', PersonalAiLib::hideAccount('51200000'), 'un compte fait de chiffres sort inchangé');
		$this->assertEquals('401~'.PersonalAiLib::getFingerprint('DUPONT'), PersonalAiLib::hideAccount('401DUPONT'), 'la suite passe en empreinte');
		$this->assertEquals(1, preg_match('/^~#[0-9a-f]{8}$/', PersonalAiLib::hideAccount('DUPONT')), 'sans chiffre de tête, tout passe en empreinte');
		$this->assertEquals('-', PersonalAiLib::hideAccount(NULL), 'NULL');

	}

	public function testHideJsonKeepsOnlyDeclaredKeys(): void {

		$body = [
			'status' => 'AUTHORIZED',
			'http_status_code' => 400,
			'success' => FALSE,
			'accounts' => [
				['uid' => 'a1b2', 'name' => 'M. Jean Dupont', 'iban' => 'FR7612345678901234567890123'],
			],
			'error' => ['message' => 'Unknown recipient Dupont'],
		];

		$hidden = PersonalAiLib::hideJson($body, ['status', 'uid']);

		$this->assertEquals('AUTHORIZED', $hidden['status'], 'clé gardée');
		$this->assertEquals('a1b2', $hidden['accounts'][0]['uid'], 'clé gardée en profondeur');
		$this->assertEquals(400, $hidden['http_status_code'], 'un nombre reste');
		$this->assertEquals(FALSE, $hidden['success'], 'un booléen reste');

		$encoded = json_encode($hidden);

		foreach(['Dupont', 'FR76', 'Unknown'] as $word) {
			$this->assertEquals(FALSE, str_contains($encoded, $word), $word.' ne sort pas');
		}

		$this->assertEquals(['list' => ['a', 'b']], PersonalAiLib::hideJson(['list' => ['a', 'b']], ['list']), 'une liste sous une clé gardée garde ses valeurs');

	}

}
?>
