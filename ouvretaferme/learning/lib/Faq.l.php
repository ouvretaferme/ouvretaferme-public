<?php
namespace learning;

/**
 * Les questions de la FAQ d'une structure. La structure se pose à la création et ne se construit
 * jamais : une question ne change pas de FAQ.
 */
class FaqLib extends FaqCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return self::getPropertiesUpdate($origin);
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['question', 'answer', 'symptoms', 'steps', 'notes', 'demoPage', 'demoPath', 'keywords', 'screen', 'verdict', 'status', 'chapter', 'discordUrl'];
	}

	/**
	 * La FAQ publique d'une structure, rangée par écran, les questions sans écran en premier : une
	 * collection par écran, indexée par sa valeur, et par la chaîne vide pour les questions sans écran.
	 *
	 * Lue par un visiteur sans compte : la sélection ne porte que ce que la page affiche. Le module
	 * lié y vient avec ce que `Course::acceptPublic()` lit de sa formation, que `fillChapterPublic()`
	 * juge avant que rien ne s'en affiche : elle peut être un brouillon.
	 */
	public static function getPublishedByCommunity(\structure\Structure $eStructure): \Collection {

		return Faq::model()
			->select([
				'id',
				'question', 'answer', 'symptoms', 'steps', 'notes', 'demoPage', 'demoPath', 'keywords',
				'screen', 'verdict',
				'chapter' => [
					'name',
					'course' => ['name', 'status', 'type', 'community' => ['type', 'communityType']]
				]
			])
			->whereCommunity($eStructure)
			->whereStatus(Faq::PUBLISHED)
			->sort(['screen' => SORT_ASC, 'id' => SORT_ASC])
			->getCollection(index: ['screen', 'id']);

	}

	/**
	 * Le nombre de questions que `getPublishedByCommunity()` rend, sur le même filtre : le lien qui
	 * mène à la page n'annonce jamais une FAQ que la page rendrait vide.
	 */
	public static function countPublishedByCommunity(\structure\Structure $eStructure): int {

		return Faq::model()
			->whereCommunity($eStructure)
			->whereStatus(Faq::PUBLISHED)
			->count();

	}

	/**
	 * Le module où mène chaque question (`eChapterLinked`), vide quand sa formation ne se lit pas
	 * sur la page où le lien arrive. Cette page est publique et la même pour tous : le programme
	 * de `/tutoriels` ou la fiche d'une formation de réseau, qui ne montrent l'un comme l'autre que
	 * ce que `Course::acceptPublic()` admet. La règle ne regarde donc jamais le visiteur : l'auteur
	 * d'un brouillon n'y trouverait qu'une ancre absente.
	 *
	 * Le module porte aussi son numéro (`number`), celui du programme où le lien arrive : un rang
	 * dans la formation et non une colonne, il se calcule par `ChapterLib::getByCourse()`, groupé par
	 * phases sur le sommaire des tutoriels comme `/tutoriels` le fait.
	 */
	public static function fillChapterPublic(\Collection $ccFaq): void {

		$courses = [];

		foreach($ccFaq as $cFaq) {

			foreach($cFaq as $eFaq) {

				$eFaq['eChapterLinked'] = (
					$eFaq['chapter']->notEmpty() and
					$eFaq['chapter']['course']->acceptPublic()
				) ? $eFaq['chapter'] : new Chapter();

				if($eFaq['eChapterLinked']->notEmpty()) {
					$courses[$eFaq['eChapterLinked']['course']['id']] = $eFaq['eChapterLinked']['course'];
				}

			}

		}

		$numbers = [];

		foreach($courses as $eCourse) {
			foreach(ChapterLib::getByCourse($eCourse, byPhase: $eCourse->acceptTutorial()) as $eChapter) {
				$numbers[$eChapter['id']] = $eChapter['number'];
			}
		}

		foreach($ccFaq as $cFaq) {

			foreach($cFaq as $eFaq) {

				if($eFaq['eChapterLinked']->notEmpty()) {
					$eFaq['eChapterLinked']['number'] = $numbers[$eFaq['eChapterLinked']['id']];
				}

			}

		}

	}

	/**
	 * Les questions de `fillChapterPublic()` rangées par formation, puis par module, puis par
	 * question, pour la page d'un réseau qui les filtre par module. `number` n'est qu'un rang dans
	 * sa formation : les formations se suivent d'abord, dans l'ordre où le réseau les liste
	 * (`CourseLib::getPublicByCommunity()`, par identifiant), puis leurs modules par rang. Une
	 * question sans module lisible se range sous la clé vide aux deux niveaux : en dernier quand
	 * une seule formation est liée, en tête quand il y en a plusieurs, le rail posant alors
	 * l'intitulé de chaque formation au-dessus de ses modules et les questions générales n'en ayant
	 * aucune.
	 */
	public static function groupByChapter(\Collection $ccFaq): \Collection {

		$faqs = [];
		$courses = [];

		foreach($ccFaq as $cFaq) {
			foreach($cFaq as $eFaq) {

				$faqs[] = $eFaq;

				if($eFaq['eChapterLinked']->notEmpty()) {
					$courses[$eFaq['eChapterLinked']['course']['id']] = TRUE;
				}

			}
		}

		$generalFirst = (count($courses) > 1);

		usort($faqs, fn(Faq $eFaqFirst, Faq $eFaqSecond) => self::getChapterRank($eFaqFirst, $generalFirst) <=> self::getChapterRank($eFaqSecond, $generalFirst));

		$cccFaq = new \Collection()->setDepth(3);

		foreach($faqs as $eFaq) {

			$eChapter = $eFaq['eChapterLinked'];

			if($eChapter->empty()) {
				$cccFaq->push(['', '', $eFaq['id']], $eFaq);
			} else {
				$cccFaq->push([$eChapter['course']['id'], $eChapter['id'], $eFaq['id']], $eFaq);
			}

		}

		return $cccFaq;

	}

	private static function getChapterRank(Faq $eFaq, bool $generalFirst): array {

		$eChapter = $eFaq['eChapterLinked'];

		if($eChapter->empty()) {
			return [$generalFirst ? -1 : 1, 0, 0, $eFaq['id']];
		} else {
			return [0, $eChapter['course']['id'], $eChapter['number'], $eFaq['id']];
		}

	}

	/**
	 * Les formations où mènent les questions de `groupByChapter()`, sans la clé vide des questions
	 * sans module.
	 */
	public static function countCourses(\Collection $cccFaq): int {
		return count(array_filter($cccFaq->getKeys(), fn(int|string $course) => $course !== ''));
	}

	/**
	 * Le nombre de questions publiées de chaque module, indexé par module, sans les modules qui
	 * n'en ont pas. Même filtre que `getPublishedByCommunity()`, dont la page est la cible du
	 * lien : un module n'annonce jamais une question que la FAQ ne montre pas.
	 */
	public static function countPublishedByChapter(\structure\Structure $eStructure, array $chapters): array {

		if($chapters === []) {
			return [];
		}

		$cFaq = Faq::model()
			->select([
				'chapter',
				'faqs' => new \Sql('COUNT(*)', 'int')
			])
			->whereCommunity($eStructure)
			->whereStatus(Faq::PUBLISHED)
			->whereChapter('IN', $chapters)
			->group('chapter')
			->getCollection();

		$faqsByChapter = [];

		foreach($cFaq as $eFaq) {
			$faqsByChapter[$eFaq['chapter']['id']] = $eFaq['faqs'];
		}

		return $faqsByChapter;

	}

	/**
	 * Les questions qui renvoyaient au module restent, sans renvoi. L'écriture de masse garde un
	 * tableau de valeurs : un élément porteur d'un id la bornerait à une ligne.
	 */
	public static function removeChapter(Chapter $eChapter): void {

		Faq::model()
			->whereChapter($eChapter)
			->update([
				'chapter' => NULL
			]);

	}

}
?>
