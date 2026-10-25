<?php
namespace planning;

/**
 * Test bout-en-bout de l'API publique planning\Reminder (production/planning/api/Reminder_0_1beta.a.php),
 * via \ApiRoute::run() (cf \dev\ApiTest pour le principe : bypass OAuth + mock php://input).
 *
 * Les rappels se posent dans des semaines lointaines, que rien du décor ne porte, pour que la liste
 * filtrée par semaine ne rende que ce que le test pose.
 *
 * Isolation : la transaction de `SeriesFixtureAiTest`, annulée en tearDown().
 *
 * Lancement : dev/test package=planning file=ReminderApiAi
 */
class ReminderApiAiTest extends \series\SeriesFixtureAiTest {

	const string WEEK = '2099-W10';
	const string WEEK_OTHER = '2099-W11';

	public function testList(): void {

		$eReminder = $this->createReminder(self::WEEK, 'Commander les plants');
		$eReminderChecked = $this->createReminder(self::WEEK, 'Réparer la bâche');
		$this->createReminder(self::WEEK_OTHER, 'Appeler le voisin');

		ReminderLib::check($eReminderChecked);

		$response = $this->callApi('GET', 'reminderList', get: ['start' => 1, 'limit' => 20, 'week' => self::WEEK]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals([$eReminder['id'], $eReminderChecked['id']], array_column($response['body']['list'], 'id'), 'la semaine filtrée rend ses deux rappels et pas celui de la semaine suivante');

		$response = $this->callApi('GET', 'reminderList', get: ['start' => 1, 'limit' => 20, 'week' => self::WEEK, 'status' => Reminder::DONE]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals([$eReminderChecked['id']], array_column($response['body']['list'], 'id'), 'le statut filtre le rappel coché');

	}

	public function testCreateReadUpdateDelete(): void {

		$date = week_date_day(self::WEEK, 2);

		$create = $this->callApi('POST', 'reminder', body: [
			'week' => self::WEEK,
			'date' => $date,
			'text' => 'Rappel de test API'
		]);

		$this->assertEquals(201, $create['status']);
		$this->assertEquals('Rappel de test API', $create['body']['text'] ?? NULL);
		$this->assertEquals($date, $create['body']['date'] ?? NULL);
		$this->assertEquals(Reminder::TODO, $create['body']['status'] ?? NULL);

		$id = $create['body']['id'];

		$eReminderCreated = Reminder::model()->select(['structure'])->whereId($id)->get();

		$this->assertEquals($this->eStructure['id'], $eReminderCreated['structure']['id'], 'le rappel est posé sur la structure de la clé');

		$read = $this->callApi('GET', 'reminder/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $read['status']);
		$this->assertEquals(self::WEEK, $read['body']['week'] ?? NULL);
		$this->assertEquals(NULL, $read['body']['updatedAt']);

		$update = $this->callApi('PUT', 'reminder/{id}', body: [
			'date' => NULL,
			'text' => 'Rappel modifié'
		], get: ['id' => $id]);

		$this->assertEquals(200, $update['status']);
		$this->assertEquals('Rappel modifié', $update['body']['text'] ?? NULL);
		$this->assertEquals(NULL, $update['body']['date']);
		$this->assertTrue($update['body']['updatedAt'] !== NULL);

		$delete = $this->callApi('DELETE', 'reminder/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $delete['status']);

		$readAfterDelete = $this->callApi('GET', 'reminder/{id}', get: ['id' => $id]);

		$this->assertEquals(404, $readAfterDelete['status']);

	}

	/**
	 * La coche pose ensemble l'état, son heure et son auteur, et ne se rejoue pas sur un rappel déjà
	 * dans l'état demandé.
	 */
	public function testCheckAndUncheck(): void {

		$eReminder = $this->createReminder(self::WEEK, 'Commander les plants');

		$check = $this->callApi('PATCH', 'reminder/{id}/check', get: ['id' => $eReminder['id']]);

		$this->assertEquals(200, $check['status']);
		$this->assertEquals(Reminder::DONE, $check['body']['status'] ?? NULL);
		$this->assertTrue($check['body']['doneAt'] !== NULL, 'la coche est datée');
		$this->assertTrue($check['body']['doneBy'] !== NULL, 'la coche est signée');

		$checkAgain = $this->callApi('PATCH', 'reminder/{id}/check', get: ['id' => $eReminder['id']]);

		$this->assertEquals(412, $checkAgain['status']);
		$this->assertEquals('accept_check', $checkAgain['body'][0]['code'] ?? NULL);

		$uncheck = $this->callApi('PATCH', 'reminder/{id}/uncheck', get: ['id' => $eReminder['id']]);

		$this->assertEquals(200, $uncheck['status']);
		$this->assertEquals(Reminder::TODO, $uncheck['body']['status'] ?? NULL);
		$this->assertEquals(NULL, $uncheck['body']['doneAt']);
		$this->assertEquals(NULL, $uncheck['body']['doneBy']);

		$uncheckAgain = $this->callApi('PATCH', 'reminder/{id}/uncheck', get: ['id' => $eReminder['id']]);

		$this->assertEquals(412, $uncheckAgain['status']);
		$this->assertEquals('accept_uncheck', $uncheckAgain['body'][0]['code'] ?? NULL);

	}

	/**
	 * Les règles de l'écran tiennent sous la clé : une semaine passée ne prend pas de rappel, et le jour
	 * tombe dans la semaine du rappel.
	 */
	public function testCreateIsValidated(): void {

		$past = $this->callApi('POST', 'reminder', body: [
			'week' => $this->getWeekPast(),
			'date' => NULL,
			'text' => 'Trop tard'
		]);

		$this->assertEquals(400, $past['status']);
		$this->assertEquals('resource_error', $past['body'][0]['type'] ?? NULL);

		$outside = $this->callApi('POST', 'reminder', body: [
			'week' => self::WEEK,
			'date' => week_date_day(self::WEEK_OTHER, 2),
			'text' => 'Mauvais jour'
		]);

		$this->assertEquals(400, $outside['status']);
		$this->assertEquals('resource_error', $outside['body'][0]['type'] ?? NULL);

		$empty = $this->callApi('POST', 'reminder', body: [
			'week' => self::WEEK,
			'date' => NULL,
			'text' => ''
		]);

		$this->assertEquals(400, $empty['status']);

	}

	/**
	 * Un rappel coché dans une semaine passée ne vit plus que dans cette semaine, qui ne s'écrit pas :
	 * ni correction, ni effacement.
	 */
	public function testCheckedInPastWeekIsReadOnly(): void {

		$week = $this->getWeekPast();

		$eReminder = $this->createReminder($week, 'Réparer la bâche');

		ReminderLib::check($eReminder);

		// Sans `ReminderLib` : la coche se date de maintenant (`NOW()`), et le cas éprouvé est une coche
		// d'une semaine passée, qu'aucun geste ne pose.
		Reminder::model()->update($eReminder, ['doneAt' => week_date_starts($week).' 10:00:00']);

		$update = $this->callApi('PUT', 'reminder/{id}', body: [
			'date' => NULL,
			'text' => 'Corrigé trop tard'
		], get: ['id' => $eReminder['id']]);

		$this->assertEquals(412, $update['status']);
		$this->assertEquals('accept_update', $update['body'][0]['code'] ?? NULL);

		$delete = $this->callApi('DELETE', 'reminder/{id}', get: ['id' => $eReminder['id']]);

		$this->assertEquals(412, $delete['status']);
		$this->assertEquals('accept_delete', $delete['body'][0]['code'] ?? NULL);

	}

	public function testForeignReminderIsNotFound(): void {

		if($this->requireStructureForeign() === FALSE) {
			return;
		}

		$eReminderForeign = $this->createReminder(self::WEEK, 'Rappel d\'une autre structure', $this->getStructureForeign());

		foreach([
			['GET', 'reminder/{id}'],
			['PATCH', 'reminder/{id}/check'],
			['DELETE', 'reminder/{id}'],
		] as [$method, $route]) {

			$response = $this->callApi($method, $route, get: ['id' => $eReminderForeign['id']]);

			$this->assertEquals(404, $response['status'], $method.' '.$route.' ne voit pas le rappel d\'une autre structure');

		}

		$response = $this->callApi('GET', 'reminderList', get: ['start' => 1, 'limit' => 20, 'week' => self::WEEK]);

		$this->assertFalse(in_array($eReminderForeign['id'], array_column($response['body']['list'], 'id')), 'la liste ne rend pas le rappel d\'une autre structure');

	}

	private function getWeekPast(): string {
		return toWeek(date('Y-m-d', strtotime(\Clock::date().' - 14 days')));
	}

	private function createReminder(string $week, string $text, \structure\Structure $eStructure = new \structure\Structure()): Reminder {

		// Sans `build()` : il refuserait une semaine passée, que `testCheckedInPastWeekIsReadOnly()` pose
		// exprès, et vérifierait un texte que le décor écrit lui-même.
		$eReminder = new Reminder([
			'structure' => $eStructure->empty() ? $this->eStructure : $eStructure,
			'week' => $week,
			'date' => NULL,
			'text' => $text,
		]);

		ReminderLib::create($eReminder);

		return $eReminder;

	}

}
?>
