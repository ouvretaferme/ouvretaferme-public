<?php
namespace learning;

require_once __DIR__.'/LearningFixture.t.php';

/**
 * Le panneau d'un module montre en préfixe de son nom le numéro que l'écran lui donnera partout
 * ailleurs (« Module 2 · Préparer sa saison ») : l'auteur voit ce qui sera ajouté et ne le tape
 * plus lui-même. Le numéro ne se stocke pas, il suit la place du module dans le programme.
 *
 * Chaque test joue la route comme le lien de l'éditeur l'ouvre, puis rend le panneau comme la vue
 * le rend : c'est la page qui pose le numéro, et c'est donc elle qui doit être traversée.
 */
class ChapterTest extends LearningFixtureTest {

	/**
	 * Un module ajouté prend la dernière place : « Module 1 » sur une formation vide,
	 * « Module 2 » sur une formation qui en a déjà un.
	 */
	public function testCreateShowsNextNumber(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::ACCEPTED);

		$this->assertEquals(1, $this->getNumber('learning/chapter:create', ['course' => (string)$eCourse['id']], 'create'), 'une formation sans module');

		$this->createChapter($eCourse, 'zzTest premier module');

		$this->assertEquals(2, $this->getNumber('learning/chapter:create', ['course' => (string)$eCourse['id']], 'create'), 'une formation à un module');

	}

	/**
	 * Les modules restent rangés sans trou : après le premier supprimé sur trois, les deux autres
	 * remontent d'un rang, et le module créé ensuite prend la dernière place. Le préfixe l'annonçait
	 * « Module 3 », et c'est bien le numéro qu'il prend dans le programme.
	 */
	public function testCreateAfterDelete(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::ACCEPTED);

		$eChapterFirst = $this->createChapter($eCourse, 'zzTest premier module');
		$this->createChapter($eCourse, 'zzTest deuxième module');
		$this->createChapter($eCourse, 'zzTest troisième module');

		ChapterLib::delete($this->getChapter($eChapterFirst['id']));

		$this->assertEquals([0, 1], $this->getPositions($eCourse), 'les suivants remontent d\'un rang');
		$this->assertEquals(3, $this->getNumber('learning/chapter:create', ['course' => (string)$eCourse['id']], 'create'), 'le préfixe annoncé');

		$eChapterNew = $this->createChapter($eCourse, 'zzTest module ajouté');

		$this->assertEquals([0, 1, 2], $this->getPositions($eCourse), 'le module créé en dernier, sans égalité');
		$this->assertEquals(3, ChapterLib::getByCourse($eCourse)[$eChapterNew['id']]['number'], 'la place que le module créé prend dans le programme');

	}

	/**
	 * Supprimer un module du milieu ne touche qu'aux suivants, et jamais aux modules d'une autre
	 * formation.
	 */
	public function testDeleteMiddle(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::ACCEPTED);
		$eCourseOther = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::ACCEPTED);

		$eChapterFirst = $this->createChapter($eCourse, 'zzTest premier module');
		$eChapterSecond = $this->createChapter($eCourse, 'zzTest deuxième module');
		$eChapterThird = $this->createChapter($eCourse, 'zzTest troisième module');

		$this->createChapter($eCourseOther, 'zzTest autre premier');
		$this->createChapter($eCourseOther, 'zzTest autre deuxième');
		$this->createChapter($eCourseOther, 'zzTest autre troisième');

		ChapterLib::delete($this->getChapter($eChapterSecond['id']));

		$this->assertEquals([$eChapterFirst['id'], $eChapterThird['id']], ChapterLib::getByCourse($eCourse)->getIds(), 'le troisième remonte derrière le premier');
		$this->assertEquals([0, 1], $this->getPositions($eCourse), 'sans trou');
		$this->assertEquals([0, 1, 2], $this->getPositions($eCourseOther), 'l\'autre formation est intacte');

	}

	private function getPositions(Course $eCourse): array {

		return Chapter::model()
			->whereCourse($eCourse)
			->sort(['position' => SORT_ASC])
			->getColumn('position');

	}

	/**
	 * Un module renommé garde sa place, lue dans l'ordre du programme et non dans l'ordre de
	 * création : le deuxième module créé, remonté en tête, est le « Module 1 ».
	 */
	public function testUpdateShowsCurrentNumber(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse(new \structure\Structure(), Course::DRAFT, Course::ACCEPTED);

		$eChapterFirst = $this->createChapter($eCourse, 'zzTest premier module');
		$eChapterSecond = $this->createChapter($eCourse, 'zzTest second module');

		$this->assertEquals(2, $this->getNumber('learning/chapter:update', ['id' => (string)$eChapterSecond['id']], 'update'), 'dans l\'ordre de création');

		$fw = new \FailWatch();

		ChapterLib::updatePositions($eCourse, [$eChapterSecond['id'], $eChapterFirst['id']]);

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('déplacement refusé : '.json_encode($this->getFails($fw))));

		$this->assertEquals(1, $this->getNumber('learning/chapter:update', ['id' => (string)$eChapterSecond['id']], 'update'), 'le second module remonté en tête');
		$this->assertEquals(2, $this->getNumber('learning/chapter:update', ['id' => (string)$eChapterFirst['id']], 'update'), 'le premier module descendu');

	}

	/**
	 * Le numéro que la route pose sur le module pour le préfixe du champ « Nom du module », après
	 * avoir rendu le panneau qu'elle ouvre. Rend la classe de l'action levée quand la route ne va
	 * pas jusqu'à la vue, pour que l'échec la nomme.
	 */
	private function getNumber(string $path, array $get, string $panel): int|string {

		$data = $this->executeView($path, $get);

		if(is_string($data)) {
			return $path.' a levé '.$data;
		}

		$body = match($panel) {
			'create' => new ChapterUi()->create($data->e)->body,
			'update' => new ChapterUi()->update($data->e)->body
		};

		$this->assertTrue($body !== '', $path.' rend son panneau');

		return $data->e['number'];

	}

}
?>
