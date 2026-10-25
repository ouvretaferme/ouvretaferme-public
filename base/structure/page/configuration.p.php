<?php
new \structure\StructurePage()
	->update(function($data) {

		$data->eStructure = $data->e;
		$data->eStructure->conf()->validate('acceptUpdateVat');

		if($data->eStructure->hasAccounting()) {
			$data->eStructure['eFinancialYear'] = \financialYear\FinancialYearLib::getById(GET('financialYear'));
		}

		$data->ccConfigurationHistory = \structure\ConfigurationHistoryLib::getHistory($data->eStructure);

		throw new ViewAction($data);

	}, page: 'updateVat')
	->update(function($data) {

		$data->eStructure = $data->e;
		$data->eStructure->conf()->validate('acceptUpdateVat');

		if(in_array(GET('field'), ['hasVat', 'vatChargeability']) === FALSE) {
			throw new NotExistsAction();
		}

		$data->field = GET('field');

		$cConfigurationHistory = (\structure\ConfigurationHistoryLib::getHistory($data->eStructure)[$data->field] ?? new Collection());
		$data->periods = \structure\ConfigurationHistoryLib::getPeriods($data->eStructure->conf(), $data->field, $cConfigurationHistory);

		throw new ViewAction($data);

	}, page: 'updateVatHistory')
	/**
	 * Pèse un changement daté de l'onglet TVA avant son envoi : les choix de l'historique qu'il remplacera, et les
	 * déclarations de TVA touchées. Un écran resté ouvert peut peser une valeur déjà enregistrée, qui ne remplace rien.
	 */
	->update(function($data) {

		$data->eStructure = $data->e;

		$eConfiguration = $data->eStructure->conf();
		$eConfiguration->validate('canUpdate', 'acceptUpdateVat');

		$data->field = GET('field');

		$value = match($data->field) {
			'hasVat' => GET('value', 'bool'),
			'vatChargeability' => GET('value', \structure\Configuration::model()->getPropertyEnum('vatChargeability')) ?? throw new NotExistsAction(),
			default => throw new NotExistsAction(),
		};

		$effectiveAt = GET('effectiveAt');

		if(
			\structure\Configuration::isHistoryChange($data->field, $value, $eConfiguration[$data->field], TRUE) and
			\util\DateLib::isValid($effectiveAt)
		) {
			$data->cConfigurationHistory = \structure\ConfigurationHistoryLib::getReplaced($data->eStructure, $data->field, $effectiveAt);
		} else {
			$data->cConfigurationHistory = new Collection();
		}

		if($data->cConfigurationHistory->notEmpty()) {
			$data->cDeclaration = \structure\StructureLib::getVatDeclarationsFrom($data->eStructure, $effectiveAt, ($data->field === 'hasVat') ? $value : NULL);
		} else {
			$data->cDeclaration = new Collection();
		}

		throw new ViewAction($data);

	}, page: 'vatWeigh')
	->write('doDeleteVatHistory', function($data) {

		// Le droit de la structure dit « est membre » : celui d'écrire l'historique est celui de changer le réglage
		$data->e->conf()->validate('canUpdate', 'acceptUpdateVat');

		if(
			in_array(POST('field'), ['hasVat', 'vatChargeability']) === FALSE or
			\util\DateLib::isValid(POST('date')) === FALSE
		) {
			throw new NotExistsAction();
		}

		\structure\ConfigurationHistoryLib::cancel($data->e, POST('field'), POST('date'));

		throw new ReloadAction('structure', 'ConfigurationHistory::deleted');
	})
	->update(function($data) {

		$data->e->conf()->validate('acceptUpdateVat');

		$data->eStructure = $data->e;

		throw new ViewAction($data);

	}, page: 'updateVatNumber');

// Les libellés des e-mails lisent le type de la structure (`isAmap()`), qu'un délégué ne garantit pas au-delà de son id :
// la structure se relit.
new \structure\ConfigurationPage()
	->applyElement(function($data, \structure\Configuration $e) {
		$e['structure'] = \structure\StructureLib::getById($e['structure']);
	})
	->update(function($data) {

		$data->eStructure = $data->e['structure'];

		throw new DynamicAction($data);

	}, page: 'updateEmail')
	->write('doUpdateEmail', function($data) {

		$fw = new FailWatch();

		$data->e->build(['emailFooter'], $_POST, new \PropertyList('update'));
		$writes = \structure\StructureLib::buildEmail($data->e['structure'], $_POST);

		$fw->validate();

		\structure\ConfigurationLib::updateEmail($data->e, $writes);

		throw new ReloadAction('structure', 'Configuration::updatedEmail');

	}, validate: ['canUpdate', 'acceptUpdate']);

/**
 * Le premier jour du récapitulatif mensuel et la période de référence des contrats annualisés, réglés depuis le
 * récapitulatif (`hr\RecapUi`). `acceptUpdatePeriod()` lit les drapeaux de la structure, qu'un délégué ne garantit
 * pas au-delà de son id : la structure se relit.
 */
new \structure\ConfigurationPage()
	->applyElement(function($data, \structure\Configuration $e) {
		$e['structure'] = \structure\StructureLib::getById($e['structure']);
	})
	->update(page: 'updatePeriod', addValidate: ['acceptUpdatePeriod'])
	->doUpdateProperties('doUpdatePeriod', ['timePeriodDay', 'timeReferenceMonth'], fn() => throw new ReloadAction('structure', 'Configuration::updated'), addValidate: ['acceptUpdatePeriod']);

/**
 * L'interrupteur du temps de travail, sur la page Fonctionnalités. L'étape `time.production` lit le type et les
 * drapeaux de la structure, qu'un délégué ne garantit pas au-delà de son id : la structure se relit.
 */
new \structure\ConfigurationPage()
	->applyElement(function($data, \structure\Configuration $e) {
		$e['structure'] = \structure\StructureLib::getById($e['structure']);
	})
	->doUpdateProperties('doUpdateTime', ['time'], fn($data) => throw new ReloadAction('structure', $data->e['time'] ? 'Configuration::enabledTime' : 'Configuration::disabledTime'));

new \structure\ConfigurationPage()
	->applyElement(function($data, \structure\Configuration $eConfiguration) {

		$eConfiguration['structure'] = \structure\StructureLib::getById($eConfiguration['structure'])->validateVerified();

	})
	->doUpdateProperties('doUpdateVatNumber', ['vatNumber'], fn() => throw new ReloadAction('structure', 'Configuration::updated'), addValidate: ['acceptUpdateVat'])
	->doUpdateProperties('doUpdateVat', ['hasVat', 'vatNumber', 'vatChargeability', 'vatDebitsOption'], fn() => throw new ReloadAction('structure', 'Configuration::updated'), addValidate: ['acceptUpdateVat'])
	->doUpdateProperties('doUpdateVatDeclaration', ['vatChargeability'], fn() => throw new ReloadAction('structure', 'Configuration::updated'), addValidate: ['acceptUpdateVat']);
?>
