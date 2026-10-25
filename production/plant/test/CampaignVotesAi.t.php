<?php
namespace plant;

/**
 * La barre des votes de la campagne des variétés préférées (demande du 02/10/2026).
 *
 * Un vote est une variété sur laquelle une structure s'est prononcée (`Variety.ratingStatus = RATED`),
 * compté toutes structures réunies par `VarietyLib::countVotes()`. Le compte est gardé en cache,
 * la table n'ayant pas d'index sur `ratingStatus` : ce que le test éprouve d'abord est donc que le
 * cache s'oublie à chaque écriture du statut, par les deux chemins qui l'écrivent, sans quoi la
 * barre resterait figée une heure après chaque vote.
 *
 * Le rendu : la largeur de la barre suit le rapport à `PlantSetting::CAMPAIGN_VOTES_GOAL`, et
 * s'arrête à 100 %.
 *
 * Le décor est la ferme F1 : la variété y est créée dans la transaction de la méthode. Le cache
 * n'est pas dans la transaction, il est donc oublié en `tearDown()`, après l'annulation.
 *
 * Lancement : dev/test package=plant file=CampaignVotesAi
 */
class CampaignVotesAiTest extends PlantFixtureAiTest {

	public function setUp(): void {

		parent::setUp();

		\Cache::redis()->delete(VarietyLib::VOTES_CACHE);

	}

	public function tearDown(): void {

		parent::tearDown();

		\Cache::redis()->delete(VarietyLib::VOTES_CACHE);

	}

	public function testVoteCountsOnceRated(): void {

		$before = VarietyLib::countVotes();

		$eVariety = $this->createVariety();

		$this->assertEquals($before, VarietyLib::countVotes(), 'une variété que personne n\'a notée n\'est pas un vote');

		VarietyLib::updateRatingStatus($eVariety, Variety::RATED);

		$this->assertEquals($before + 1, VarietyLib::countVotes(), 'la variété notée compte, sans attendre la fin du cache');

		VarietyLib::updateRatingStatus($eVariety, Variety::WAITING);

		$this->assertEquals($before, VarietyLib::countVotes(), 'une note retirée ne compte plus');

	}

	public function testSkippingARatedVarietyForgetsTheCount(): void {

		$eVariety = $this->createVariety();

		VarietyLib::updateRatingStatus($eVariety, Variety::RATED);

		$rated = VarietyLib::countVotes();

		// L'écran écarte une variété par doUpdateProperties, donc par update() et non par updateRatingStatus()
		$eVariety['ratingStatus'] = Variety::SKIPPED;
		VarietyLib::update($eVariety, ['ratingStatus']);

		$this->assertEquals($rated - 1, VarietyLib::countVotes(), 'une variété écartée après avoir été notée ne compte plus');

	}

	public function testBarFollowsTheGoal(): void {

		$goal = PlantSetting::CAMPAIGN_VOTES_GOAL;

		$ui = new CommunityAiUi();

		$this->assertTrue(str_contains($ui->getIntro($this->eStructure, intdiv($goal, 4)), 'width: 25%'), 'au quart de l\'objectif, la barre est au quart');
		$this->assertTrue(str_contains($ui->getIntro($this->eStructure, $goal + 134), 'width: 100%'), 'l\'objectif dépassé, la barre reste pleine et ne déborde pas');

	}

}
?>
