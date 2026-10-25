<?php
namespace series;

/**
 * Test bout-en-bout de l'API publique series\Comment (production/series/api/Comment_0_1beta.a.php),
 * via \ApiRoute::run() (cf \dev\ApiTest pour le principe : bypass OAuth + mock php://input).
 *
 * L'intervention commentée est celle du décor (`SeriesFixtureAiTest`), posée dans la transaction de
 * la méthode : les cas lisaient l'intervention la plus récente de la structure, qui changeait avec
 * chaque saisie et chaque rechargement de la base.
 *
 * Lancement : dev/test package=series file=CommentApiAi
 */
class CommentApiAiTest extends SeriesFixtureAiTest {

	/** Ce que tout cas qui commente exige de la structure : une équipe, et l'action de l'intervention du décor. */
	private function requireDecor(): bool {

		return ($this->requireMember() and $this->requireAction(ACTION_PLANTATION));

	}

	public function testList(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eComment = new Comment([
			'task' => $this->getTask(),
			'text' => 'Commentaire du décor'
		]);

		CommentLib::create($eComment);

		$response = $this->callApi('GET', 'taskCommentList', get: ['start' => 1, 'limit' => 20, 'ids' => [$eComment['id']]]);

		$this->assertEquals(200, $response['status']);
		$this->assertTrue(array_key_exists('count', $response['body']));
		$this->assertTrue(array_key_exists('list', $response['body']));
		$this->assertEquals([$eComment['id']], array_column($response['body']['list'], 'id'), 'le commentaire du décor est dans la liste');

	}

	public function testCreateReadUpdateDelete(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eTask = $this->getTask();

		$create = $this->callApi('POST', 'seriesComment', body: [
			'task' => $eTask['id'],
			'text' => 'Commentaire de test API'
		]);

		$this->assertEquals(201, $create['status']);
		$this->assertEquals('Commentaire de test API', $create['body']['text'] ?? NULL);
		$this->assertEquals($eTask['id'], $create['body']['task'] ?? NULL);

		$id = $create['body']['id'];

		// CommentLib::create() recopie structure, série et mise en culture depuis la tâche : le
		// client n'a pas à les fournir.
		$eCreated = Comment::model()->select(['structure', 'series', 'cultivation'])->whereId($id)->get();

		$this->assertEquals($this->eStructure['id'], $eCreated['structure']['id']);
		$this->assertEquals($eTask['series']['id'], $eCreated['series']['id']);
		$this->assertEquals($eTask['cultivation']['id'], $eCreated['cultivation']['id']);

		$read = $this->callApi('GET', 'seriesComment/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $read['status']);
		$this->assertEquals('Commentaire de test API', $read['body']['text'] ?? NULL);
		$this->assertEquals(NULL, $read['body']['updatedAt']);
		$this->assertTrue($read['body']['createdAt'] !== NULL);

		$update = $this->callApi('PUT', 'seriesComment/{id}', body: [
			'text' => 'Commentaire modifié'
		], get: ['id' => $id]);

		$this->assertEquals(200, $update['status']);
		$this->assertEquals('Commentaire modifié', $update['body']['text'] ?? NULL);
		$this->assertTrue($update['body']['updatedAt'] !== NULL);

		$delete = $this->callApi('DELETE', 'seriesComment/{id}', get: ['id' => $id]);

		$this->assertEquals(200, $delete['status']);

		$readAfterDelete = $this->callApi('GET', 'seriesComment/{id}', get: ['id' => $id]);

		$this->assertEquals(404, $readAfterDelete['status']);

	}

	public function testListByTask(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$eTask = $this->getTask();

		$eComment = new Comment([
			'task' => $eTask,
			'text' => 'Commentaire filtré'
		]);

		CommentLib::create($eComment);

		// Un commentaire sur une autre intervention du décor ne doit pas passer le filtre
		$eCommentOther = new Comment([
			'task' => $this->createTask($this->getCultivation()),
			'text' => 'Commentaire d\'une autre intervention'
		]);

		CommentLib::create($eCommentOther);

		$response = $this->callApi('GET', 'taskCommentList', get: ['start' => 1, 'limit' => 500, 'task' => $eTask['id']]);

		$this->assertEquals(200, $response['status']);
		$this->assertEquals([$eComment['id']], array_column($response['body']['list'], 'id'), 'le filtre rend le seul commentaire de l\'intervention');

		foreach($response['body']['list'] as $comment) {
			$this->assertEquals($eTask['id'], $comment['task']);
		}

	}

	/**
	 * Comment::build() vérifie que la tâche appartient bien à la structure du jeton : une tâche
	 * d'une autre structure doit être refusée, pas silencieusement acceptée.
	 */
	public function testCreateOnForeignTaskIsRejected(): void {

		if($this->requireDecor() === FALSE or $this->requireStructureForeign() === FALSE) {
			return;
		}

		$eTaskForeign = $this->createTaskForeign();

		$response = $this->callApi('POST', 'seriesComment', body: [
			'task' => $eTaskForeign['id'],
			'text' => 'Commentaire de test API'
		]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('resource_error', $response['body'][0]['type'] ?? NULL);

	}

	public function testNotFound(): void {

		$response = $this->callApi('GET', 'seriesComment/{id}', get: ['id' => 999999999]);

		$this->assertEquals(404, $response['status']);
		$this->assertEquals('not_found', $response['body'][0]['code'] ?? NULL);

	}

	public function testValidationError(): void {

		if($this->requireDecor() === FALSE) {
			return;
		}

		$response = $this->callApi('POST', 'seriesComment', body: [
			'task' => $this->getTask()['id'],
			'text' => ''
		]);

		$this->assertEquals(400, $response['status']);
		$this->assertEquals('resource_error', $response['body'][0]['type'] ?? NULL);

	}

}
?>
