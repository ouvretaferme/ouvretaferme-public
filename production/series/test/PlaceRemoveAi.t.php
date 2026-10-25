<?php
namespace series;

/**
 * Une planche qu'on vient de vider se réassole sans recharger le plan.
 *
 * Signalé par un utilisateur (Discord, 16/10) dans le panneau d'assolement : après avoir retiré une
 * série d'une surface libre, la ligne redessinée n'offrait plus de bouton Ajouter, et il fallait
 * recharger pour la reposer. `doTargetAi` redessinait la planche par `BedUi::displayBedFromPlot()`
 * sans les bornes de la série armée, que le plan entier lui donne (`PlotUi`), et
 * `BedUi::getBedPoseParts()` ne rend aucun bouton sans bornes.
 *
 * Le lien est le contrat : c'est lui que le geste « + Ajouter » clique, par son `data-bed`.
 *
 * Lancement : dev/test package=series file=PlaceRemoveAi
 */
class PlaceRemoveAiTest extends SeriesFixtureAiTest {

	protected function getTransactionModel(): \ModuleModel {
		return new PlaceModel();
	}

	public function testTheEmptiedBedOffersToPlaceAgain(): void {

		if($this->requireMember() === FALSE) {
			return;
		}

		$eSeries = $this->createSeriesBed('Série retirée du décor');

		// `build()` vérifierait des semaines saisies : ces deux colonnes sont calculées par le
		// produit depuis les cultures, et le cas ne veut qu'une série qui a sa place sur la frise.
		$eSeries['bedStartCalculated'] = 15;
		$eSeries['bedStopCalculated'] = 30;
		Series::model()->select(['bedStartCalculated', 'bedStopCalculated'])->update($eSeries);

		$eBed = $this->createBed();

		$this->createPlace($eSeries, $eBed, 10);

		// La vue Liste, celle du signalement : la vue Plan redessine le jardin entier et garde son bouton.
		$action = $this->runPage('series/place:doRemove', ['view' => \structure\Member::PLAN], [
			'series' => $eSeries['id'],
			'context' => $eSeries['id'],
			'beds' => [$eBed['id']],
			'gesture' => 'remove',
		]);

		$this->assertTrue($action instanceof \ViewAction, "le retrait passe : ".get_class($action));
		$this->assertEquals(0, Place::model()->whereSeries($eSeries)->count(), "la série n'est plus sur la planche");

		$instructions = $this->renderInstructions('series/place', 'doTargetAi', $action);

		$this->assertTrue(
			str_contains($instructions, 'class=\"place-pose place-pose-timeline\" data-bed=\"'.$eBed['id'].'\"'),
			"la planche redessinée offre de nouveau le bouton Ajouter"
		);

	}

	/**
	 * Le pied du panneau suit le geste (signalé par V-Dashboard le 24/10) : il disait « Sélectionné : 58 mL »
	 * sur une série qu'on venait de vider, la réponse ne le redessinant pas. Le sélecteur est le contrat, c'est
	 * lui qui atteint le pied dans le panneau ouvert.
	 */
	public function testTheFooterFollowsTheRemoval(): void {

		if($this->requireMember() === FALSE) {
			return;
		}

		$eSeries = $this->createSeriesBed('Série au pied du panneau');
		$eBed = $this->createBed();

		$this->createPlace($eSeries, $eBed, 10);

		$action = $this->runPage('series/place:doRemove', ['view' => \structure\Member::PLAN], [
			'series' => $eSeries['id'],
			'context' => $eSeries['id'],
			'beds' => [$eBed['id']],
			'gesture' => 'remove',
		]);

		$this->assertTrue($action instanceof \ViewAction, "le retrait passe : ".get_class($action));

		$instructions = $this->renderInstructions('series/place', 'doTargetAi', $action);

		$this->assertTrue(
			str_contains($instructions, '#panel-place-update .place-update-submit'),
			"la réponse redessine le pied du panneau"
		);

		$this->assertTrue(
			str_contains($instructions, 'id=\"place-update-value\">0<'),
			"le pied dit ce qui reste placé, rien"
		);

	}

	/** Les instructions d'une réponse ajax, rendues sur les données de l'action. Forme reprise de `dashboard/test/Preference.t.php`. */
	private function renderInstructions(string $request, string $name, \ViewAction $action): string {

		$view = $this->getView($request, $name);
		$template = new \AjaxTemplate();
		$template->setData(new \ReflectionProperty(\ViewAction::class, 'data')->getValue($action));

		new \ReflectionProperty(\View::class, 'callback')->getValue($view)->call($view, $template->getData(), $template);

		return json_encode($template->formatInstructions(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

	}

	/** Exécute la vraie page et rend ce qu'elle lève. Forme reprise de `payment/test/StripeExpressCardAi.t.php`. */
	private function runPage(string $page, array $get, array $post): \Throwable {

		$method = new \ReflectionProperty(\Route::class, 'requestMethod');
		$methodBefore = $method->getValue();
		$method->setValue(NULL, 'POST');

		$getPages = new \ReflectionMethod(\Page::class, 'getPages');

		$_GET = $get;
		$_POST = $post;
		$_REQUEST = $_GET + $_POST;

		try {
			\Page::execute($getPages->invoke(NULL, $page), new \stdClass());
		} catch(\Throwable $e) {
			return $e;
		} finally {
			$_GET = [];
			$_POST = [];
			$_REQUEST = [];
			$method->setValue(NULL, $methodBefore);
		}

		return new \LogicException('l\'action '.$page.' n\'a rien levé');

	}

}
?>
