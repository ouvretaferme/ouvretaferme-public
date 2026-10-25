<?php
namespace dev;

/**
 * Un fail levé hors HTTP sans FailWatch ouvert se signale, sauf dans les pages cli du package dev.
 *
 * Les cas sans FailWatch passent d'abord : le lanceur n'en ouvre aucun autour des méthodes, et un
 * FailWatch ouvert plus haut ferait passer chaque cas pour surveillé.
 */
class FailUnwatchedTest extends \Test {

	public function testUnwatched(): void {

		$this->assertTrue(\Fail::isUnwatched(['type' => 'cron', 'package' => 'selling', 'name' => 'sale']), 'un cron métier sans FailWatch doit se signaler');
		$this->assertTrue(\Fail::isUnwatched(['type' => 'cron', 'package' => 'dev', 'name' => 'error']), 'un cron du package dev sans FailWatch doit se signaler');
		$this->assertTrue(\Fail::isUnwatched(['type' => 'cli', 'package' => 'selling', 'name' => 'configure']), 'une page cli métier sans FailWatch doit se signaler');

		$this->assertFalse(\Fail::isUnwatched(['type' => 'cli', 'package' => 'dev', 'name' => 'test']), 'une page cli du package dev ne doit pas se signaler, le lanceur de tests compterait chaque fail en échec');
		$this->assertFalse(\Fail::isUnwatched(['type' => 'get', 'package' => 'selling', 'name' => 'sale']), 'une page HTTP ne doit pas se signaler, sa réponse porte déjà le fail');
		$this->assertFalse(\Fail::isUnwatched(NULL), 'hors de toute page, le fail ne doit pas se signaler');

	}

	public function testWatched(): void {

		$fw = new \FailWatch();

		$this->assertFalse(\Fail::isUnwatched(['type' => 'cron', 'package' => 'selling', 'name' => 'sale']), 'un cron sous un FailWatch ouvert ne doit pas se signaler');

		$fw->validate();

	}

}
?>
