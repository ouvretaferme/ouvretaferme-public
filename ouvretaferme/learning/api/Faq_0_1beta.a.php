<?php
namespace learning;

/**
 * La FAQ du groupe de la clé. Le groupe est gardé par `Course_0_1betaApi::validateStructure()`, le
 * module auquel une question renvoie par l'étape `Faq::chapter.owned`, qui le cherche parmi les modules
 * des formations du groupe.
 */
class Faq_0_1betaApi extends \ModuleApi {

	public string $scopeName = 'learning';

	public function __construct() {

		$this->list(
			'faqList',
			[
				'?status' => Faq::getValidator('status'),
				'?screen' => Faq::getValidator('screen')
			]
		);

		$this->create('faq');
		$this->read('faq/{id}');
		$this->update('faq/{id}');
		$this->delete('faq/{id}');

		$this->element(
			'learning\Faq',
			fn() => [
				'id', 'community',
				'question', 'answer', 'symptoms', 'steps', 'notes', 'demoPage', 'demoPath', 'keywords',
				'screen', 'verdict', 'status',
				'chapter', 'discordUrl'
			]
		);

	}

	public function getList(\ApiData $data): FaqModel {

		return $this->getNewModel($data)
			->whereStatus(fn() => $data->get('status'), if: $data->exists('status'))
			->whereScreen(fn() => $data->get('screen'), if: $data->exists('screen'));

	}

	public function getNewModel(\ApiData $data): FaqModel {

		Course_0_1betaApi::validateStructure($data);

		return new FaqModel()
			->whereCommunity($data->eStructure);

	}

	public function getNewElement(\ApiData $data): Faq {

		Course_0_1betaApi::validateStructure($data);

		return new Faq([
			'community' => $data->eStructure,
			'cChapter' => ChapterLib::getByCommunity($data->eStructure)
		]);

	}

	/**
	 * L'emplacement dans le menu ne se lit qu'à côté du chemin de la page qu'il décrit : un `PUT` qui
	 * nomme ses propriétés les change ensemble, et l'étape `Faq::demoPath.withPage` juge les deux.
	 */
	public function getStandalonePropertiesUpdate(\ApiData $data): array {

		return [
			'group' => [
				['demoPage', 'demoPath'],
			],
		] + parent::getStandalonePropertiesUpdate($data);

	}

	/**
	 * `cChapter` est la liste où l'étape `Faq::chapter.owned` cherche le module posté par un `PUT`.
	 */
	public function getById(\ApiData $data): Faq {

		$eFaq = parent::getById($data);

		if(\Route::getRequestMethod() === 'PUT') {
			$eFaq['cChapter'] = ChapterLib::getByCommunity($data->eStructure);
		}

		return $eFaq;

	}

}
?>
