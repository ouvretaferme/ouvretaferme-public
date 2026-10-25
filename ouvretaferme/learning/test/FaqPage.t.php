<?php
namespace learning;

require_once __DIR__.'/LearningTutorialFixture.t.php';

/**
 * Les questions d'utilisation, `/faq` (lot T6b), et les questions fréquentes d'un réseau,
 * `/reseau/{fqn}/faq` (lot T6c) : ce que les pages préparent pour le visiteur sans compte, avec
 * le module où mène chaque question (lot T7).
 *
 * Le réseau des tutoriels est celui du décor (`LearningTutorialFixtureTest`), l'autre réseau est G3
 * du décor commun, ouvert aux formations en dev (`LearningSetting::STRUCTURE_BETA`), et les
 * questions s'écrivent dans la transaction du test.
 *
 * Lancement : dev/test package=learning file=FaqPage
 */
class FaqPageTest extends LearningTutorialFixtureTest {

	const COMMUNITY_NETWORK = \structure\FixtureAiLib::G3;

	/**
	 * Seules les questions publiées du réseau des tutoriels paraissent : ni son brouillon, ni la
	 * question publiée d'un autre réseau. Elles se rangent par écran, dans l'ordre des écrans du
	 * logiciel et non dans celui de leur création, les questions sans écran en tête, et le compte
	 * les reprend toutes.
	 */
	public function testList(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		// Créées dans un autre ordre que celui des écrans : l'ordre de création les rangerait mal.
		$eFaqPlanning = $this->createFaq($this->eStructureTutorial, Faq::PLANNING, Faq::PUBLISHED);
		$eFaqSeries = $this->createFaq($this->eStructureTutorial, Faq::SERIES, Faq::PUBLISHED);
		$eFaqSeriesOther = $this->createFaq($this->eStructureTutorial, Faq::SERIES, Faq::PUBLISHED);
		$eFaqWithout = $this->createFaq($this->eStructureTutorial, NULL, Faq::PUBLISHED);
		$eFaqDraft = $this->createFaq($this->eStructureTutorial, Faq::SERIES, Faq::DRAFT);
		$eFaqOther = $this->createFaq($this->getCommunity(self::COMMUNITY_EDUCATION), Faq::SERIES, Faq::PUBLISHED);

		$this->actAs(new \user\User());

		$data = $this->executeView('faq', []);

		$this->assertTrue($data instanceof \stdClass, 'la page répond au visiteur sans compte');

		$this->assertEquals(['', Faq::SERIES, Faq::PLANNING], array_keys($data->ccFaq->getArrayCopy()), 'les questions sans écran en tête, puis les écrans dans leur ordre');
		$this->assertEquals([$eFaqWithout['id']], $data->ccFaq['']->getIds(), 'la question sans écran');
		$this->assertEquals([$eFaqSeries['id'], $eFaqSeriesOther['id']], $data->ccFaq[Faq::SERIES]->getIds(), 'les questions publiées du plan de culture, sans le brouillon '.$eFaqDraft['id'].' ni la question '.$eFaqOther['id'].' d\'un autre réseau');
		$this->assertEquals([$eFaqPlanning['id']], $data->ccFaq[Faq::PLANNING]->getIds(), 'la question du planning');
		$this->assertEquals(4, $data->faqs, 'quatre questions publiées');
		$this->assertEquals(2, $data->screens, 'deux écrans pour les chiffres de la bande, les questions générales n\'en sont pas un');

		$this->assertTrue($data->ccFaq[Faq::SERIES][$eFaqSeries['id']]['eChapterLinked']->empty(), 'une question sans module ne mène à aucun module');

	}

	/**
	 * Une question mène à son module quand sa formation paraît sur la page où le lien arrive : le
	 * sommaire des tutoriels, la fiche d'une formation de réseau. Ni le brouillon ni la formation
	 * datée n'y paraissent, et la règle ne regarde pas le lecteur : l'auteur du brouillon, qui le
	 * lit, n'y est pas mené non plus.
	 */
	public function testChapter(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eChapterPublished = $this->createChapter($this->createCourse($this->eStructureTutorial, Course::PUBLISHED, Course::PRIVATE), 'zzTest module publié');
		$eChapterDraft = $this->createChapter($this->createCourse($this->eStructureTutorial, Course::DRAFT, Course::PRIVATE), 'zzTest module en brouillon');
		$eChapterDated = $this->createChapter($this->createCourse($this->eStructureTutorial, Course::PUBLISHED, Course::PRIVATE, type: Course::ONSITE), 'zzTest module en présentiel');

		$eFaqPublished = $this->createFaq($this->eStructureTutorial, Faq::SERIES, Faq::PUBLISHED, $eChapterPublished);
		$eFaqDraft = $this->createFaq($this->eStructureTutorial, Faq::SERIES, Faq::PUBLISHED, $eChapterDraft);
		$eFaqDated = $this->createFaq($this->eStructureTutorial, Faq::SERIES, Faq::PUBLISHED, $eChapterDated);

		foreach([new \user\User(), $this->eUserAdmin] as $eUser) {

			$this->actAs($eUser);

			$cFaq = $this->executeView('faq', [])->ccFaq[Faq::SERIES];
			$reader = $eUser->empty() ? 'au visiteur' : 'à l\'auteur';

			$this->assertEquals($eChapterPublished['id'], $cFaq[$eFaqPublished['id']]['eChapterLinked']['id'], 'la question mène '.$reader.' au module de la formation publiée');
			$this->assertEquals('zzTest module publié', $cFaq[$eFaqPublished['id']]['eChapterLinked']['name'], 'avec son nom');
			$this->assertTrue($cFaq[$eFaqDraft['id']]['eChapterLinked']->empty(), 'ni '.$reader.' au module d\'un brouillon');
			$this->assertTrue($cFaq[$eFaqDated['id']]['eChapterLinked']->empty(), 'ni '.$reader.' au module d\'une formation datée');

		}

		$eStructureNetwork = $this->getCommunity(self::COMMUNITY_NETWORK);

		$this->actAs($this->eUserAdmin);

		$eCourseNetwork = $this->createCourse($eStructureNetwork, Course::PUBLISHED, Course::PRIVATE);
		$this->createChapter($eCourseNetwork, 'zzTest module d\'avant');
		$eChapterNetwork = $this->createChapter($eCourseNetwork, 'zzTest module du réseau');
		$eFaqNetwork = $this->createFaq($eStructureNetwork, Faq::SERIES, Faq::PUBLISHED, $eChapterNetwork);

		$this->actAs(new \user\User());

		[, , $data] = $this->call('reseau/'.\structure\CommunityLib::getByStructure($eStructureNetwork)['fqn'].'/faq');

		$this->assertEquals($eChapterNetwork['id'], $data->ccFaq[Faq::SERIES][$eFaqNetwork['id']]['eChapterLinked']['id'], 'sur un réseau, la question mène au module de sa formation publique en ligne');
		$this->assertEquals(2, $data->ccFaq[Faq::SERIES][$eFaqNetwork['id']]['eChapterLinked']['number'], 'sous son numéro dans le programme de la fiche');

	}

	/**
	 * Le filtre par module de `faq.js` lit `data-chapter` sur chaque question et non sur son écran :
	 * un écran partagé entre deux modules ne garde que les questions du module choisi. L'attribut est
	 * le contrat dont dépend ce filtre, d'où sa lecture dans le rendu.
	 */
	public function testChapterOnItem(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureTutorial, Course::PUBLISHED, Course::PRIVATE);
		$eChapterFirst = $this->createChapter($eCourse, 'zzTest premier module');
		$eChapterSecond = $this->createChapter($eCourse, 'zzTest second module');

		$eFaqFirst = $this->createFaq($this->eStructureTutorial, Faq::SERIES, Faq::PUBLISHED, $eChapterFirst);
		$eFaqSecond = $this->createFaq($this->eStructureTutorial, Faq::SERIES, Faq::PUBLISHED, $eChapterSecond);
		$eFaqAlone = $this->createFaq($this->eStructureTutorial, Faq::SERIES, Faq::PUBLISHED);

		$this->actAs(new \user\User());

		// libxml ne connaît pas les balises SVG des icônes et le dirait en avertissement.
		$document = new \DOMDocument();
		$errors = libxml_use_internal_errors(TRUE);
		$data = $this->executeView('faq', []);
		$document->loadHTML('<?xml encoding="utf-8"?><body>'.new FaqUi()->getBody($data->ccFaq, $data->faqs).'</body>');
		libxml_clear_errors();
		libxml_use_internal_errors($errors);

		$xpath = new \DOMXPath($document);
		$getChapter = fn(Faq $eFaq) => $xpath->query('//*[@id="faq-'.$eFaq['id'].'"]')->item(0)->getAttribute('data-chapter');

		$this->assertEquals((string)$eChapterFirst['id'], $getChapter($eFaqFirst), 'la question du premier module porte son module');
		$this->assertEquals((string)$eChapterSecond['id'], $getChapter($eFaqSecond), 'sur le même écran, celle du second porte le sien');
		$this->assertEquals('', $getChapter($eFaqAlone), 'une question sans module n\'en porte aucun');
		$this->assertFalse($xpath->query('//a[contains(@class, "util-filter-option") and @data-screen="'.Faq::SERIES.'"]')->item(0)->hasAttribute('data-chapter'), 'l\'option de l\'écran n\'en porte pas');

	}

	/**
	 * Le lien de la démo et le rail des écrans portent des contrats que l'œil ne voit pas : le lien
	 * mène à la démo de l'environnement, dans un nouvel onglet sans accès à la page, et le libellé
	 * du menu, écrit par l'équipe, sort échappé. Une question sans page de démo n'a pas de lien,
	 * et chaque question porte l'écran que le rail de `faq.js` filtre.
	 */
	public function testBody(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eFaqDemo = $this->createFaq($this->eStructureTutorial, Faq::SERIES, Faq::PUBLISHED, values: [
			'demoPage' => '/ferme/1/series?view=area',
			'demoPath' => 'Production <script>alert(1)</script>',
		]);
		$eFaqAlone = $this->createFaq($this->eStructureTutorial, NULL, Faq::PUBLISHED);

		$this->actAs(new \user\User());

		$data = $this->executeView('faq', []);

		$html = new FaqUi()->getBody($data->ccFaq, $data->faqs);

		// libxml ne connaît pas les balises SVG des icônes et le dirait en avertissement.
		$document = new \DOMDocument();
		$errors = libxml_use_internal_errors(TRUE);
		$document->loadHTML('<?xml encoding="utf-8"?><body>'.$html.'</body>');
		libxml_clear_errors();
		libxml_use_internal_errors($errors);

		$xpath = new \DOMXPath($document);
		$getLinks = fn(Faq $eFaq) => $xpath->query('//*[@id="faq-'.$eFaq['id'].'"]//a[@target="_blank"]');

		$links = $getLinks($eFaqDemo);

		$this->assertEquals(1, $links->length, 'la question qui a une page de démo y mène');
		$this->assertEquals(OTF_DEMO_URL.'/ferme/1/series?view=area', $links->item(0)->getAttribute('href'), 'le lien mène à la démo de l\'environnement');
		$this->assertEquals('noopener', $links->item(0)->getAttribute('rel'), 'la démo s\'ouvre sans accès à la page');
		$this->assertFalse(str_contains($html, '<script>alert(1)</script>'), 'le libellé du menu ne sort pas brut');
		$this->assertTrue(str_contains($html, 'Production &lt;script&gt;alert(1)&lt;/script&gt;'), 'le libellé du menu sort échappé');
		$this->assertEquals(0, $getLinks($eFaqAlone)->length, 'une question sans page de démo n\'a pas de lien');

		$getScreen = fn(Faq $eFaq) => $xpath->query('//*[@id="faq-'.$eFaq['id'].'"]')->item(0)->getAttribute('data-screen');

		$this->assertEquals(Faq::SERIES, $getScreen($eFaqDemo), 'la question porte son écran');
		$this->assertEquals('', $getScreen($eFaqAlone), 'une question générale porte l\'écran vide, celui de son option');
		$this->assertEquals(1, $xpath->query('//a[contains(@class, "util-filter-option") and @data-screen=""]')->length, 'le rail a l\'option des questions générales');

	}

	/**
	 * Sans réseau des tutoriels, ou sur un réseau fermé aux formations, l'adresse n'existe pas.
	 */
	public function testNotExists(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs(new \user\User());

		LearningSetting::$structureTutorial = NULL;

		$this->assertEquals(\NotExistsAction::class, $this->executeView('faq', []), 'sans réseau des tutoriels, la page répond 404');

		// L'école du décor n'est pas de `STRUCTURE_BETA` : en production simulée, elle n'a pas les
		// formations.
		$active = LearningSetting::$active;

		LearningSetting::$active = FALSE;
		LearningSetting::$structureTutorial = self::COMMUNITY_EDUCATION;

		$action = $this->executeView('faq', []);

		LearningSetting::$active = $active;

		$this->assertEquals(\NotExistsAction::class, $action, 'sur une structure fermée aux formations, la page répond 404');

	}

	/**
	 * Sur l'adresse d'un réseau, seules ses questions publiées paraissent : ni son brouillon, ni
	 * celles du réseau des tutoriels ou d'un groupe de formation. Le décompte qui mène à la page, sur
	 * les formations du réseau, compte les mêmes, et ne part pas dans l'intégration, qui ne montre pas
	 * l'accès.
	 */
	public function testNetwork(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eStructureNetwork = $this->getCommunity(self::COMMUNITY_NETWORK);
		$path = 'reseau/'.\structure\CommunityLib::getByStructure($eStructureNetwork)['fqn'];

		$this->actAs($this->eUserAdmin);

		if($this->assertDecorPresent(FaqLib::countPublishedByCommunity($eStructureNetwork) === 0, 'le réseau '.self::COMMUNITY_NETWORK.' sans question publiée en base') === FALSE) {
			return;
		}

		$eFaqSeries = $this->createFaq($eStructureNetwork, Faq::SERIES, Faq::PUBLISHED);
		$eFaqWithout = $this->createFaq($eStructureNetwork, NULL, Faq::PUBLISHED);
		$eFaqDraft = $this->createFaq($eStructureNetwork, Faq::SERIES, Faq::DRAFT);
		$eFaqTutorial = $this->createFaq($this->eStructureTutorial, Faq::SERIES, Faq::PUBLISHED);
		$eFaqEducation = $this->createFaq($this->getCommunity(self::COMMUNITY_EDUCATION), Faq::SERIES, Faq::PUBLISHED);

		$this->actAs(new \user\User());

		[$action, $view, $data] = $this->call($path.'/faq');

		$this->assertEquals([\ViewAction::class, NULL], [$action, $view], 'la page répond au visiteur sans compte');
		$this->assertEquals(['', Faq::SERIES], array_keys($data->ccFaq->getArrayCopy()), 'les questions sans écran en tête, puis le plan de culture');
		$this->assertEquals([$eFaqSeries['id']], $data->ccFaq[Faq::SERIES]->getIds(), 'la question publiée du plan de culture, sans le brouillon '.$eFaqDraft['id'].', ni la question '.$eFaqTutorial['id'].' du réseau des tutoriels, ni la question '.$eFaqEducation['id'].' d\'un groupe de formation');
		$this->assertEquals([$eFaqWithout['id']], $data->ccFaq['']->getIds(), 'la question sans écran');
		$this->assertEquals(2, $data->faqs, 'deux questions publiées');

		$this->assertEquals(2, FaqLib::countPublishedByCommunity($eStructureNetwork), 'le décompte compte les mêmes questions que la page');

		[, , $data] = $this->call($path.'/formations');
		$this->assertEquals(2, $data->faqs, 'les formations du réseau portent le décompte');

		[, , $data] = $this->call($path.'/formations', ['embed' => '1']);
		$this->assertEquals(0, $data->faqs, 'pas dans l\'intégration');

	}

	/**
	 * Le rail d'un réseau filtre par module, et `number` n'est qu'un rang dans sa formation : les
	 * formations se suivent d'abord, puis leurs modules, et deux « Module 1 » ne se mêlent pas. La
	 * formation ne se nomme que lorsque le réseau en lie plusieurs : devant le module dans le
	 * sur-titre, et en intitulé au-dessus de ses options dans le rail, les questions sans module
	 * passant alors en tête. Les options portent le module que `faq.js` filtre, dans cet ordre.
	 */
	public function testNetworkByChapter(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eStructureNetwork = $this->getCommunity(self::COMMUNITY_NETWORK);
		$path = 'reseau/'.\structure\CommunityLib::getByStructure($eStructureNetwork)['fqn'].'/faq';

		$this->actAs($this->eUserAdmin);

		if($this->assertDecorPresent(FaqLib::countPublishedByCommunity($eStructureNetwork) === 0, 'le réseau '.self::COMMUNITY_NETWORK.' sans question publiée en base') === FALSE) {
			return;
		}

		$eCourseFirst = $this->createCourse($eStructureNetwork, Course::PUBLISHED, Course::PRIVATE);
		$eChapterFirstOne = $this->createChapter($eCourseFirst, 'zzTest premier module de la première');
		$eChapterFirstTwo = $this->createChapter($eCourseFirst, 'zzTest second module de la première');

		$eCourseSecond = $this->createCourse($eStructureNetwork, Course::PUBLISHED, Course::PRIVATE);
		$eChapterSecondOne = $this->createChapter($eCourseSecond, 'zzTest premier module de la seconde');

		$eFaqGeneral = $this->createFaq($eStructureNetwork, NULL, Faq::PUBLISHED);
		$eFaqFirstTwo = $this->createFaq($eStructureNetwork, Faq::SERIES, Faq::PUBLISHED, $eChapterFirstTwo);
		$eFaqFirstOne = $this->createFaq($eStructureNetwork, Faq::PLANNING, Faq::PUBLISHED, $eChapterFirstOne);

		$this->actAs(new \user\User());

		[, , $data] = $this->call($path);

		$this->assertFalse($data->withCourse, 'une seule formation liée : le module se nomme seul');
		$this->assertEquals([$eCourseFirst['id'], ''], array_keys($data->cccFaq->getArrayCopy()), 'une seule formation liée : les questions sans module en dernier');

		$this->actAs($this->eUserAdmin);

		$eFaqSecondOne = $this->createFaq($eStructureNetwork, Faq::SERIES, Faq::PUBLISHED, $eChapterSecondOne);

		$this->actAs(new \user\User());

		[, , $data] = $this->call($path);

		$this->assertTrue($data->withCourse, 'deux formations liées : la formation se nomme devant le module');
		$this->assertEquals(['', $eCourseFirst['id'], $eCourseSecond['id']], array_keys($data->cccFaq->getArrayCopy()), 'les questions sans module en tête, puis les formations dans leur ordre');
		$this->assertEquals([$eChapterFirstOne['id'], $eChapterFirstTwo['id']], array_keys($data->cccFaq[$eCourseFirst['id']]->getArrayCopy()), 'dans la première, ses modules par rang, et non par question');
		$this->assertEquals([$eFaqSecondOne['id']], $data->cccFaq[$eCourseSecond['id']][$eChapterSecondOne['id']]->getIds(), 'le module 1 de la seconde reste sous sa formation');
		$this->assertEquals([$eFaqGeneral['id']], $data->cccFaq['']['']->getIds(), 'la question sans module sous la clé vide');

		// libxml ne connaît pas les balises SVG des icônes et le dirait en avertissement.
		$document = new \DOMDocument();
		$errors = libxml_use_internal_errors(TRUE);
		$document->loadHTML('<?xml encoding="utf-8"?><body>'.new FaqUi()->getNetworkBody($data->cccFaq, $data->faqs, $data->withCourse).'</body>');
		libxml_clear_errors();
		libxml_use_internal_errors($errors);

		$xpath = new \DOMXPath($document);

		$this->assertEquals('chapter', $xpath->query('//*[@id="learning-faq-list"]')->item(0)->getAttribute('data-filter'), 'le rail filtre par module');

		$options = [];
		foreach($xpath->query('//a[contains(@class, "util-filter-option") and @data-chapter]') as $node) {
			$options[] = $node->getAttribute('data-chapter');
		}

		$this->assertEquals(['', (string)$eChapterFirstOne['id'], (string)$eChapterFirstTwo['id'], (string)$eChapterSecondOne['id']], $options, 'l\'option des questions générales, puis une option par module dans l\'ordre des données');

		$this->assertEquals((string)$eChapterFirstOne['id'], $xpath->query('//*[@id="faq-'.$eFaqFirstOne['id'].'"]')->item(0)->getAttribute('data-chapter'), 'la question porte le module que filtre le rail');
		$this->assertEquals(0, $xpath->query('//a[contains(@href, "discord")]')->length, 'pas d\'appel au Discord de Ouvretaferme sur un réseau');

	}

	/**
	 * Un réseau qui n'a que des brouillons n'a rien à annoncer, et sa page rend l'état vide.
	 */
	public function testNetworkDraftOnly(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eStructureNetwork = $this->getCommunity(self::COMMUNITY_NETWORK);
		$path = 'reseau/'.\structure\CommunityLib::getByStructure($eStructureNetwork)['fqn'];

		$this->actAs($this->eUserAdmin);

		$publishedInitial = FaqLib::countPublishedByCommunity($eStructureNetwork);

		if($this->assertDecorPresent($publishedInitial === 0, 'le réseau '.self::COMMUNITY_NETWORK.' sans question publiée en base') === FALSE) {
			return;
		}

		$this->createFaq($eStructureNetwork, Faq::SERIES, Faq::DRAFT);

		$this->actAs(new \user\User());

		$this->assertEquals(0, FaqLib::countPublishedByCommunity($eStructureNetwork), 'un brouillon ne se compte pas');

		[$action, $view, $data] = $this->call($path.'/faq');

		$this->assertEquals([\ViewAction::class, NULL], [$action, $view], 'la page répond');
		$this->assertEquals(0, $data->faqs, 'et rend son état vide');

	}

	/**
	 * Un groupe de formation, le réseau des tutoriels et un réseau fermé aux formations n'ont pas
	 * de FAQ sur leur adresse, et la réponse part avant toute lecture de la FAQ. Une ancienne
	 * adresse du réseau mène à la page sous l'adresse actuelle.
	 */
	public function testNetworkNotExists(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs(new \user\User());

		$eStructureEducation = $this->getCommunity(self::COMMUNITY_EDUCATION);
		$eCommunityEducation = \structure\CommunityLib::getByStructure($eStructureEducation);

		if($this->assertDecorPresent($eCommunityEducation->notEmpty(), 'une adresse publique pour le groupe de formation '.self::COMMUNITY_EDUCATION) === FALSE) {
			return;
		}

		[$action, $view, $data] = $this->call('reseau/'.$eCommunityEducation['fqn'].'/faq');
		$this->assertEquals([\ViewAction::class, '/error:404'], [$action, $view], 'un groupe de formation rend la 404');
		$this->assertFalse(property_exists($data, 'ccFaq'), 'sans lire la FAQ');

		[$action, $view] = $this->call('reseau/'.\structure\CommunityLib::getByStructure($this->eStructureTutorial)['fqn'].'/faq');
		$this->assertEquals([\ViewAction::class, '/error:404'], [$action, $view], 'le réseau des tutoriels aussi, sa FAQ est sur /faq');

		$eStructureNetwork = $this->getCommunity(self::COMMUNITY_NETWORK);
		$eCommunityNetwork = \structure\CommunityLib::getByStructure($eStructureNetwork);

		// Sans `build()` : l'ancienne adresse n'a pas de formulaire, `CommunityLib::update()` la pose elle aussi
		// à la main quand le fqn change, et le test veut ce fqn précis.
		\structure\CommunityRedirectLib::create(new \structure\CommunityRedirect([
			'fqn' => 'zztest-ancienne-adresse-faq',
			'structure' => $eStructureNetwork,
		]));

		[$action, $url] = $this->call('reseau/zztest-ancienne-adresse-faq/faq');
		$this->assertEquals([\RedirectAction::class, DatePublicUi::urlFaq($eCommunityNetwork)], [$action, $url], 'depuis une ancienne adresse, vers l\'adresse actuelle');

		$eFeature = \structure\FeatureLib::readByStructure($eStructureNetwork);
		// Sans `build()` : un booléen posé sans saisie, que le schéma ne borne pas autrement.
		$eFeature['hasCommunityLearning'] = FALSE;
		\structure\FeatureLib::update($eFeature, ['hasCommunityLearning']);

		$this->forgetMemoization();

		[$action, , $data] = $this->call('reseau/'.$eCommunityNetwork['fqn'].'/faq');
		$this->assertEquals(\NotExistsAction::class, $action, 'un réseau fermé aux formations répond comme une adresse inconnue');
		$this->assertFalse(property_exists($data, 'ccFaq'), 'sans lire la FAQ');

	}

	/**
	 * La racine du réseau des tutoriels se sert dès qu'il a une date à venir, mais n'annonce pas sa
	 * FAQ, qui se lit sur `/faq` : son adresse de réseau répond 404, la carte y mènerait.
	 */
	public function testTutorialRoot(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs($this->eUserAdmin);

		$eCourse = $this->createCourse($this->eStructureTutorial, Course::PUBLISHED, Course::PRIVATE, type: Course::ONSITE);

		$eDate = new Date(['course' => $eCourse]);

		$fw = new \FailWatch();

		$eDate->build(DateLib::getPropertiesCreate(), $this->postSequences([
			[$this->day(10), '09:00', '17:00', $eCourse->getDefaultSequence()['mode'], 'Salle des fêtes, Crest'],
		]), new \PropertyList('create'));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('date refusée : '.json_encode($fw->get())));

		DateLib::create($eDate);

		$this->createFaq($this->eStructureTutorial, Faq::SERIES, Faq::PUBLISHED);

		$this->assertTrue(FaqLib::countPublishedByCommunity($this->eStructureTutorial) > 0, 'le réseau des tutoriels a une question publiée');

		$this->actAs(new \user\User());

		$eCommunityTutorial = \structure\CommunityLib::getByStructure($this->eStructureTutorial);

		[$action, $view, $data] = $this->call('reseau/'.$eCommunityTutorial['fqn']);

		$this->assertEquals([\ViewAction::class, NULL], [$action, $view], 'sa racine se sert, avec sa date');
		$this->assertEquals(0, $data->faqs, 'sans décompte de questions');
		$this->assertEquals('', new DatePublicUi($this->eStructureTutorial, $eCommunityTutorial, embed: FALSE)->getFaqAccess($data->faqs), 'donc sans carte');

	}

	/**
	 * La route jouée par son adresse : la classe de l'action, la vue d'une `ViewAction` ou l'adresse
	 * d'une redirection, et les données préparées, que la réponse parte ou non avant la vue.
	 *
	 * @return array{0: string, 1: ?string, 2: \stdClass}
	 */
	private function call(string $path, array $input = []): array {

		if(defined('LIME_URL') === FALSE) {
			define('LIME_URL', \Lime::getUrl());
		}

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodInitial = $requestMethod->getValue();
		$requestMethod->setValue(NULL, 'GET');

		[$getInitial, $requestInitial] = [$_GET, $_REQUEST];
		[$_GET, $_REQUEST] = [$input, $input];

		$data = new \stdClass();

		try {
			\Page::execute(\Route::getCompatiblePages($path), $data);
			return ['aucune action', NULL, $data];
		} catch(\ViewAction $action) {
			$view = new \ReflectionProperty(\ViewAction::class, 'path')->getValue($action);
			return [\ViewAction::class, $view, $data];
		} catch(\RedirectAction $action) {
			return [\RedirectAction::class, new \ReflectionProperty($action, 'url')->getValue($action), $data];
		} catch(\Action $action) {
			return [get_class($action), NULL, $data];
		} finally {
			[$_GET, $_REQUEST] = [$getInitial, $requestInitial];
			$requestMethod->setValue(NULL, $requestMethodInitial);
		}

	}

	/**
	 * La structure se pose à la création comme le fait l'API, qui la tient de la clé
	 * (`Faq_0_1betaApi::getNewElement()`), avec les modules où `Faq::chapter.owned` cherche le sien.
	 */
	private function createFaq(\structure\Structure $eStructure, ?string $screen, string $status, Chapter $eChapter = new Chapter(), array $values = []): Faq {

		$eFaq = new Faq([
			'community' => $eStructure,
			'cChapter' => ChapterLib::getByCommunity($eStructure)
		]);

		$fw = new \FailWatch();

		$eFaq->build(array_merge(['question', 'answer', 'screen', 'verdict', 'status', 'chapter'], array_keys($values)), [
			'question' => 'zzTest question '.uniqid(),
			'answer' => '<p>zzTest réponse</p>',
			'screen' => $screen,
			'verdict' => Faq::POSSIBLE,
			'status' => $status,
			'chapter' => $eChapter->empty() ? NULL : $eChapter['id'],
		] + $values, new \PropertyList('create'));

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('question refusée : '.json_encode($fw->get())));

		FaqLib::create($eFaq);

		return $eFaq;

	}

}
?>
