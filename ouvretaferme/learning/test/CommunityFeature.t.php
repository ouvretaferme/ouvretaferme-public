<?php
namespace learning;

require_once __DIR__.'/LearningReviewFixture.t.php';

/**
 * L'interrupteur « Formations » d'un groupe (`structure\Feature::hasCommunityLearning`) : ce qu'il
 * ferme, ce qu'il laisse ouvert, et le refus de l'éteindre tant que des personnes attendent une date.
 *
 * Le réseau du décor est `COMMUNITY_NETWORK`, ouvert en production par `LearningSetting::STRUCTURE_BETA`
 * : c'est le seul groupe sur lequel les deux réglages se croisent, celui d'OTF et celui du groupe.
 * Tout s'écrit dans la transaction du test. Aucun cron n'est lancé : les crons se jugent par la
 * liste qu'ils liraient, bornée aux lignes du test quand le Lib le permet.
 *
 * Lancement : dev/test package=learning file=CommunityFeature
 */
class CommunityFeatureTest extends LearningReviewFixtureTest {

	/**
	 * Éteint, le groupe n'a plus de formations nulle part : l'onglet, la liste, la page d'intégration,
	 * la page publique, le script d'intégration, la lecture d'une formation et l'API.
	 */
	public function testClosed(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seed(Course::ONSITE);
		$this->setOwner();

		$id = $this->eStructureNetwork['id'];
		$path = 'reseau/'.\structure\CommunityLib::getByStructure($this->eStructureNetwork)['fqn'];
		$embed = ['id' => (string)$id, 'target' => 'otf-learning-'.$id];

		$this->setLearning(TRUE);

		$this->assertTrue($this->getNetwork()->acceptLearning(), 'allumé, le réseau a ses formations');
		$this->assertTrue($this->getCourse($eCourse['id'])->acceptLearning(), 'et sa formation se lit');
		$this->assertTrue($this->executeView('groupe/'.$id.'/formations', []) instanceof \stdClass, 'la liste s\'ouvre');
		$this->assertTrue($this->executeView('groupe/'.$id.'/formations/integrer', []) instanceof \stdClass, 'la page d\'intégration s\'ouvre');
		$this->assertTrue($this->executeView($path.'/formations', []) instanceof \stdClass, 'la page publique s\'ouvre');
		$this->assertDiffer([\NotExistsAction::class, NULL], $this->executePage('reseau/embed-learning.js', $embed, 'GET'), 'le script d\'intégration est servi');
		$this->assertTrue(str_contains($this->getTabs(), '/groupe/'.$id.'/formations'), 'l\'onglet Formations');

		$this->setLearning(FALSE);

		$this->assertFalse($this->getNetwork()->acceptLearning(), 'éteint, le réseau n\'a plus de formations');
		$this->assertFalse($this->getCourse($eCourse['id'])->acceptLearning(), 'ni sa formation');
		$this->assertEquals(\NotExistsAction::class, $this->executeView('groupe/'.$id.'/formations', []), 'la liste répond comme une adresse inconnue');
		$this->assertEquals(\NotExistsAction::class, $this->executeView('groupe/'.$id.'/formations/integrer', []), 'la page d\'intégration aussi');
		$this->assertEquals(\NotExistsAction::class, $this->executeView('groupe/'.$id.'/formations/commentaires', []), 'les commentaires aussi');
		$this->assertEquals(\NotExistsAction::class, $this->executeView($path.'/formations', []), 'la page publique aussi');
		$this->assertEquals([\NotExistsAction::class, NULL], $this->executePage('reseau/embed-learning.js', $embed, 'GET'), 'le script d\'intégration aussi');
		$this->assertFalse(str_contains($this->getTabs(), '/groupe/'.$id.'/formations'), 'l\'onglet Formations disparaît');

	}

	/**
	 * Une formation au catalogue public en sort quand son groupe éteint ses formations, et y revient
	 * au rallumage sans nouvelle demande : sa place au catalogue n'a pas bougé.
	 */
	public function testCatalog(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCatalog(Course::ONLINE);

		$this->setLearning(TRUE);
		$this->assertTrue(in_array($eCourse['id'], CourseLib::getCatalog()->getIds()), 'allumé, la formation est au catalogue');

		$this->setLearning(FALSE);
		$this->assertFalse(in_array($eCourse['id'], CourseLib::getCatalog()->getIds()), 'éteint, elle en sort');
		$this->assertEquals(Course::ACCEPTED, $this->getCourse($eCourse['id'])['catalog'], 'sans perdre sa place');

		$this->setLearning(TRUE);
		$this->assertTrue(in_array($eCourse['id'], CourseLib::getCatalog()->getIds()), 'rallumé, elle y revient sans nouvelle demande');

	}

	/**
	 * Le catalogue suit la règle de `acceptLearning()` jusque dans le type du groupe : un groupe qui
	 * passe à un type sans formations en sort même interrupteur allumé, sa colonne restant en base
	 * telle qu'il l'avait laissée. La règle SQL et la règle PHP sont lues côte à côte à chaque type.
	 */
	public function testCatalogType(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCatalog(Course::ONLINE);

		$this->setLearning(TRUE);

		$inCatalog = fn() => in_array($eCourse['id'], CourseLib::getCatalog()->getIds());

		foreach([
			\structure\Structure::NETWORK => TRUE,
			\structure\Structure::EDUCATION => TRUE,
			\structure\Structure::PRODUCER => FALSE,
			\structure\Structure::ACCOUNTING => FALSE,
		] as $communityType => $expected) {

			$this->setCommunityType($communityType);

			$this->assertEquals($expected, $this->getNetwork()->acceptLearning(), $communityType.' : acceptLearning()');
			$this->assertEquals($expected, $inCatalog(), $communityType.' : le catalogue rend la même réponse');

		}

		$this->setCommunityType(\structure\Structure::NETWORK);
		$this->assertTrue($inCatalog(), 'revenu réseau, la formation retrouve le catalogue');

	}

	/**
	 * « Mes formations » au menu du compte, en production : un réseau ouvert par OTF qui a éteint ses
	 * formations ne les ouvre plus à ses membres ni à ses fermes.
	 */
	public function testMenu(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$ccStructure = new \Collection([
			new \Collection([$this->eStructureNetwork['id'] => $this->getNetwork()])
		]);

		$active = LearningSetting::$active;
		LearningSetting::$active = FALSE;

		try {

			$this->setLearning(TRUE);
			$this->assertTrue(CourseLib::isOpenByStructures($ccStructure), 'allumé, le réseau ouvert par OTF ouvre le menu');

			$this->setLearning(FALSE);
			$this->assertFalse(CourseLib::isOpenByStructures($ccStructure), 'éteint, il ne l\'ouvre plus');

		} finally {
			LearningSetting::$active = $active;
		}

	}

	/**
	 * Le rappel d'avant la date ne part plus pour un groupe éteint, que les formations soient ouvertes
	 * à tous ou par la liste d'OTF.
	 */
	public function testRemindable(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);

		$eDateEnrollment = $this->age($this->enrollConfirmed($this->createDate($eCourse, 1), $this->createPerson()));

		$active = LearningSetting::$active;

		try {

			foreach([TRUE => 'ouvertes à tous', FALSE => 'ouvertes par la liste d\'OTF'] as $value => $label) {

				LearningSetting::$active = (bool)$value;

				$this->setLearning(TRUE);
				$this->assertTrue(in_array($eDateEnrollment['id'], DateEnrollmentLib::getRemindable()->getIds()), $label.', allumé : le rappel part');

				$this->setLearning(FALSE);
				$this->assertFalse(in_array($eDateEnrollment['id'], DateEnrollmentLib::getRemindable()->getIds()), $label.', éteint : le rappel ne part pas');

			}

		} finally {
			LearningSetting::$active = $active;
		}

	}

	/**
	 * L'invitation à donner son avis ne part plus pour un groupe éteint, que les formations soient
	 * ouvertes à tous ou par la liste d'OTF.
	 */
	public function testReviewable(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCatalog();

		$eDateEnrollment = $this->followDate($eCourse, $this->createPerson(), -1);
		$cDateEnrollment = new \Collection([$eDateEnrollment['id'] => $eDateEnrollment]);

		$active = LearningSetting::$active;

		try {

			foreach([TRUE => 'ouvertes à tous', FALSE => 'ouvertes par la liste d\'OTF'] as $value => $label) {

				LearningSetting::$active = (bool)$value;

				$this->setLearning(TRUE);
				$this->assertEquals([$eDateEnrollment['id']], DateEnrollmentLib::getReviewableAmong($cDateEnrollment)->getIds(), $label.', allumé : l\'invitation part');

				$this->setLearning(FALSE);
				$this->assertEquals([], DateEnrollmentLib::getReviewableAmong($cDateEnrollment)->getIds(), $label.', éteint : l\'invitation ne part pas');

			}

		} finally {
			LearningSetting::$active = $active;
		}

	}

	/**
	 * Une inscription en attente de réponse, confirmée ou en liste d'attente à une date à venir
	 * empêche d'éteindre, et le refus nomme la formation et le jour. Une inscription annulée, une date
	 * passée ou une date annulée n'empêchent rien. Allumer n'est jamais refusé.
	 */
	public function testRefused(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$mail = $this->getLastMail();

		// Le réseau du décor porte en dev des dates à venir où des personnes sont inscrites. Annulées
		// dans la transaction du test, sans `DateLib`, qui annulerait aussi les places et écrirait aux
		// inscrits : l'étape ne voit plus que les dates que le test pose. Écriture de masse, donc un
		// tableau de valeurs.
		Date::model()
			->whereCourse('IN', Course::model()->select('id')->whereCommunity($this->eStructureNetwork)->getCollection())
			->whereStatus(Date::PLANNED)
			->update(['status' => Date::CANCELED]);

		[$eCourse] = $this->seedCourse(quiz: FALSE);

		$this->setLearning(TRUE);

		$this->assertEquals([], $this->buildLearning(FALSE), 'sans date, éteindre est permis');

		$eDate = $this->createDate($eCourse, 5);
		$eDateEnrollment = $this->enrollRequested($eDate, $this->createPerson());

		$this->assertEquals(['Feature::hasCommunityLearning.enrolled'], $this->buildLearning(FALSE), 'une demande en attente de réponse empêche d\'éteindre');
		$this->assertEquals([], $this->buildLearning(TRUE), 'allumer n\'est jamais refusé');

		$cDate = DateLib::getEnrolledByCommunity($this->eStructureNetwork);
		$message = \structure\AlertUi::getError('Feature::hasCommunityLearning.enrolled', []);

		$this->assertEquals([$eDate['id']], $cDate->getIds(), 'la date bloquante');
		$this->assertTrue($message instanceof \Closure, 'le refus a son message');

		$text = $message($cDate);

		$this->assertTrue(str_contains($text, encode($eCourse['name'])), 'le message nomme la formation : '.$text);
		$this->assertTrue(str_contains($text, \util\DateUi::textual($eDate['startAt'], \util\DateUi::DAY_MONTH)), 'et le jour : '.$text);

		DateEnrollmentLib::confirm($eDateEnrollment);
		$this->assertEquals(['Feature::hasCommunityLearning.enrolled'], $this->buildLearning(FALSE), 'une inscription confirmée aussi');

		// La liste d'attente posée sans remplir la date : une date pleine porterait déjà une place
		// qui bloque, et c'est l'état seul qu'on éprouve.
		DateEnrollment::model()
			->whereId($eDateEnrollment['id'])
			->update(['status' => DateEnrollment::WAITING]);

		$this->assertEquals(['Feature::hasCommunityLearning.enrolled'], $this->buildLearning(FALSE), 'une place en liste d\'attente aussi');

		DateEnrollmentLib::cancel($this->getLine($eDateEnrollment['id']), DateEnrollment::USER);
		$this->assertEquals([], $this->buildLearning(FALSE), 'une inscription annulée n\'empêche rien');

		$eDatePast = $this->createDate($eCourse, 10);
		$this->enrollConfirmed($eDatePast, $this->createPerson());
		$this->moveDate($eDatePast, -2);
		$this->assertEquals([], $this->buildLearning(FALSE), 'une date passée n\'empêche rien');

		// La date annulée par son statut seul : `DateLib::cancel()` annulerait aussi la place, et c'est
		// la condition sur la date qu'on éprouve ici.
		$eDateCanceled = $this->createDate($eCourse, 7);
		$this->enrollConfirmed($eDateCanceled, $this->createPerson());
		$eDateCanceled['status'] = Date::CANCELED;
		DateLib::update($eDateCanceled, ['status']);
		$this->assertEquals([], $this->buildLearning(FALSE), 'une date annulée n\'empêche rien');

		$this->assertEquals($mail, $this->getLastMail(), 'aucun e-mail écrit en base');

	}

	/**
	 * Le refus par le geste de la carte : le bandeau reçoit le nom du refus, la colonne ne bouge pas.
	 * Les autres cartes passent par le même `build()` sans changer de comportement.
	 */
	public function testPage(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		[$eCourse] = $this->seedCourse(quiz: FALSE);
		$this->enrollRequested($this->createDate($eCourse, 5), $this->createPerson());

		$this->setOwner();
		$this->setLearning(TRUE);

		$input = ['id' => (string)$this->eStructureNetwork['id'], 'feature' => 'hasCommunityLearning', 'value' => '0'];

		$this->assertEquals([\FailAction::class, 'Feature::hasCommunityLearning.enrolled'], $this->executePage('structure/community:doUpdateFeature', $input), 'éteindre est refusé par le bandeau');
		$this->assertTrue(\structure\FeatureLib::readByStructure($this->eStructureNetwork)['hasCommunityLearning'], 'les formations restent allumées');

		$eFeature = \structure\FeatureLib::readByStructure($this->eStructureNetwork);
		$sections = $eFeature['hasCommunitySections'];

		$input = ['id' => (string)$this->eStructureNetwork['id'], 'feature' => 'hasCommunitySections', 'value' => $sections ? '0' : '1'];

		$this->assertEquals([\ReloadAction::class, NULL], $this->executePage('structure/community:doUpdateFeature', $input), 'une autre carte se bascule');
		$this->assertEquals(!$sections, \structure\FeatureLib::readByStructure($this->eStructureNetwork)['hasCommunitySections'], 'et sa colonne suit');

		$input = ['id' => (string)$this->eStructureNetwork['id'], 'feature' => 'hasCommunitySchools', 'value' => '1'];

		$this->assertEquals([\NotExpectedAction::class, NULL], $this->executePage('structure/community:doUpdateFeature', $input), 'une carte que le type n\'a pas reste refusée');

	}

	/**
	 * La carte n'existe que là où OTF ouvre les formations. Un groupe neuf trouve ses formations allumées.
	 */
	public function testCard(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eStructureEducation = $this->getCommunity(self::COMMUNITY_EDUCATION);

		$this->setLearning(TRUE);

		$this->assertTrue($this->getNetwork()->acceptCommunityFeatureSetting('hasCommunityLearning'), 'le réseau a la carte');
		$this->assertTrue($eStructureEducation->acceptCommunityFeatureSetting('hasCommunityLearning'), 'le groupe de formation aussi');

		$active = LearningSetting::$active;
		LearningSetting::$active = FALSE;

		try {
			$this->assertTrue($this->getNetwork()->acceptCommunityFeatureSetting('hasCommunityLearning'), 'en production, le réseau ouvert par OTF garde la carte');
			$this->assertFalse($eStructureEducation->acceptCommunityFeatureSetting('hasCommunityLearning'), 'le groupe que la liste d\'OTF ne porte pas n\'a pas de carte');
			$this->assertFalse(in_array('hasCommunityLearning', $eStructureEducation->getCommunityFeatures()), 'ni d\'entrée parmi ses fonctionnalités');
		} finally {
			LearningSetting::$active = $active;
		}

		$this->setLearning(FALSE);

		$cards = $this->getCards($this->getNetwork());

		$this->assertTrue(str_contains($cards, 'post-feature="hasCommunityLearning"'), 'éteint, la carte reste');

		foreach([\structure\Structure::NETWORK => TRUE, \structure\Structure::EDUCATION => TRUE, \structure\Structure::PRODUCER => FALSE, \structure\Structure::ACCOUNTING => FALSE] as $type => $expected) {
			$eStructure = new \structure\Structure(['type' => \structure\Structure::COMMUNITY, 'communityType' => $type]);
			$this->assertEquals($expected, in_array('hasCommunityLearning', \structure\CommunityFeatureAiLib::getDefaults($eStructure)), $type.' : formations allumées à la création ou non');
		}

	}

	/**
	 * La page d'intégration, à qui gère un réseau : l'adresse publique et le code se copient même sans
	 * date à venir, l'aperçu exécute le script quand il y a quelque chose à voir. Le panneau des
	 * réglages ne porte plus que les réglages.
	 */
	public function testIntegration(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->setLearning(TRUE);

		$id = $this->eStructureNetwork['id'];

		$this->actAs($this->eUserAdmin);
		$this->assertEquals(\NotAllowedAction::class, $this->executeView('groupe/'.$id.'/formations/integrer', []), 'un conseiller n\'y entre pas');
		$this->assertTrue(str_contains(CourseUi::getCommunityTitle($this->getNetwork(), 0, 0), 'href="/learning/course:create?community='.$id.'"'), 'il garde la création');
		$this->assertFalse(str_contains(CourseUi::getCommunityTitle($this->getNetwork(), 0, 0), '/integrer'), 'sans l\'intégration dans sa roue');
		$this->assertTrue(str_contains(CourseUi::getCommunityTitle($this->getNetwork(), 0, 0), 'href="/groupe/'.$id.'/formations/encadrants"'), 'qui porte les encadrants');
		$this->assertFalse(str_contains(CourseUi::getCommunityTitle($this->getNetwork(), 0, 0), 'community:updateEnrollment'), 'et pas les réglages');

		$this->assertEquals(\NotExpectedAction::class, $this->executeView('groupe/'.self::COMMUNITY_EDUCATION.'/formations/integrer', []), 'un groupe de formation n\'a pas de page publique');

		$this->setOwner();

		$data = $this->executeView('groupe/'.$id.'/formations/integrer', []);

		$this->assertTrue($data instanceof \stdClass, 'qui gère le réseau y entre');
		$this->assertEquals(
			DateLib::existsPublicByCommunity($this->eStructureNetwork) or CourseLib::existsPublicOnlineByCommunity($this->eStructureNetwork),
			$data->withPublic,
			'l\'aperçu suit les dates publiques et les formations en ligne publiées'
		);

		$eCommunity = $this->getNetwork()->community();

		$html = new DateEnrollmentUi()->getIntegration($data->eStructure, $eCommunity, TRUE);

		$this->assertTrue(str_contains($html, 'id="learning-public-url">'.encode(DatePublicUi::urlList($eCommunity)).'</div>'), 'l\'adresse publique à copier');
		$this->assertTrue(str_contains($html, 'id="learning-public-code">'.encode(DatePublicUi::getEmbedScript($data->eStructure)).'</div>'), 'le code à copier');
		$this->assertTrue(str_contains($html, DatePublicUi::getEmbedScript($data->eStructure)), 'l\'aperçu exécute le script');

		$html = new DateEnrollmentUi()->getIntegration($data->eStructure, $eCommunity, FALSE);

		$this->assertTrue(str_contains($html, 'id="learning-public-code"'), 'sans date à venir, le code reste à copier');
		$this->assertFalse(str_contains($html, DatePublicUi::getEmbedScript($data->eStructure)), 'sans aperçu');

		$actions = CourseUi::getCommunityTitle($this->getNetwork(), 0, 0);

		$this->assertTrue(str_contains($actions, 'href="/groupe/'.$id.'/formations/encadrants"') and str_contains($actions, 'href="/learning/community:updateEnrollment?id='.$id.'"'), 'la roue mène aux encadrants et aux réglages : '.$actions);
		$this->assertTrue(str_contains($actions, 'href="/groupe/'.$id.'/formations/integrer"'), 'et à l\'intégration');

		$data = $this->executeView('learning/community:updateEnrollment', ['id' => $id]);
		$settings = new DateEnrollmentUi()->updateSettings($data->eStructure, $data->eCommunity)->body;

		$this->assertFalse(str_contains($settings, 'learning-public'), 'le panneau des réglages n\'a plus rien à copier');

	}

	/**
	 * Le décor allume ou éteint par le Lib, sans `build()` : l'étape `enrolled` refuserait d'éteindre
	 * un réseau qui porte des inscriptions, et c'est parfois cet état qu'un test monte.
	 */
	private function setLearning(bool $value, \structure\Structure $eStructure = new \structure\Structure()): void {

		$eFeature = \structure\FeatureLib::readByStructure($eStructure->empty() ? $this->eStructureNetwork : $eStructure);
		$eFeature['hasCommunityLearning'] = $value;

		\structure\FeatureLib::update($eFeature, ['hasCommunityLearning']);

		$this->forgetMemoization();

	}

	/**
	 * Change le type du réseau du décor dans la transaction du test. Hors du Lib et sans `build()` :
	 * le type d'un groupe ne se change pas par un formulaire du produit, et le test veut précisément
	 * un réseau devenu groupe de producteurs ou de comptabilité, que rien d'autre ne fabrique.
	 */
	private function setCommunityType(string $communityType): void {

		\structure\Structure::model()->update($this->eStructureNetwork, ['communityType' => $communityType]);

		$this->forgetMemoization();

	}

	/**
	 * Les noms des refus de `build()` sur la colonne, comme le geste de la carte la poste.
	 */
	private function buildLearning(bool $value): array {

		$eFeature = \structure\FeatureLib::readByStructure($this->eStructureNetwork);

		$fw = new \FailWatch();

		$eFeature->build(['hasCommunityLearning'], ['hasCommunityLearning' => $value ? '1' : '0']);

		return array_column($this->getFails($fw), 0);

	}

	/**
	 * L'utilisateur 1 administrateur du réseau, dont il n'est que conseiller dans le
	 * décor : le réglage et l'intégration sont à `canManage()`. Écrit par le modèle, le produit
	 * ne changeant un rôle que par l'écran des membres.
	 */
	private function setOwner(): void {

		\structure\Member::model()
			->whereStructure($this->eStructureNetwork)
			->whereUser($this->eUserAdmin)
			->update(['role' => \structure\RoleLib::getOne($this->eStructureNetwork, 'admin')]);

		$this->actAs($this->eUserAdmin);

	}

	private function getNetwork(): \structure\Structure {
		return $this->getCommunity($this->eStructureNetwork['id']);
	}

	private function getTabs(): string {
		return new \structure\StructureCommunityUi()->getTabs($this->getNetwork(), 0, TRUE, FALSE, 'settings');
	}

	private function getCards(\structure\Structure $eStructure): string {
		return new \structure\StructureCommunityUi()->getFeatures($eStructure);
	}

	/**
	 * La confirmation reculée de deux jours : le rappel ne suit pas une confirmation de moins de 24
	 * heures, et le décor ne peut pas attendre. Écrite hors du Lib, qui n'a pas de raison de dater une
	 * confirmation dans le passé.
	 */
	private function age(DateEnrollment $eDateEnrollment): DateEnrollment {

		DateEnrollment::model()
			->whereId($eDateEnrollment['id'])
			->update(['confirmedAt' => new \Sql('NOW() - INTERVAL 2 DAY')]);

		return $this->getLine($eDateEnrollment['id']);

	}

}
?>
