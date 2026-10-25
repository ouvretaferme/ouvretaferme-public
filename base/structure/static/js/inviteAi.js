class StructureInvite {

	static watch(select) {

		const form = select.closest('form');

		if(form === null) {
			return;
		}

		const empty = select.querySelector('option[value=""]');
		const wrapper = form.querySelector('[data-wrapper="memberSection"]');

		const update = () => {

			const role = form.querySelector('input[name="memberRole"]:checked');
			const bounded = (role !== null && role.dataset.sections === 'own');
			const staff = (role !== null && role.dataset.staff === '1');

			// L'encadrement n'entre dans aucun sous-groupe : le champ part, et sa valeur avec lui,
			// sans quoi un choix fait avant de changer de rôle partirait quand même au serveur.
			wrapper.classList.toggle('hide', staff);

			if(staff) {
				select.value = '';
			}

			empty.hidden = bounded;
			empty.disabled = bounded;
			select.required = bounded;

			if(bounded && select.value === '') {
				select.selectedIndex = select.options.length > 1 ? 1 : 0;
			}

		};

		form.addEventListener('change', update);

		update();

	}

	static watchProducerType(node) {

		const form = node.closest('form');

		if(form === null) {
			return;
		}

		const question = form.querySelector('[data-wrapper="producerType"]');

		if(question === null) {
			return;
		}

		const update = () => {

			const structure = form.querySelector('input[name="structureProducer"]:checked');
			const producerType = (structure === null) ? null : (structure.dataset.producerType ?? null);

			question.classList.toggle('hide', producerType === null);

			form.querySelectorAll('input[name="producerType"]').forEach(input => input.checked = (producerType !== null && input.value === producerType));

		};

		// Sur le seul changement de ferme, et non sur tout le formulaire : la question qu'on remet
		// d'aplomb porte des boutons dont un `change` général effacerait la réponse en cours.
		form.addEventListener('change', e => {

			if(e.target.name === 'structureProducer') {
				update();
			}

		});

		update();

	}

	static watchPlace(node) {

		const form = node.closest('form');

		if(form === null) {
			return;
		}

		const question = form.querySelector('[data-place-question]');

		if(question === null) {
			return;
		}

		const update = () => {

			const structure = form.querySelector('input[name="structureProducer"]:checked');

			question.classList.toggle('hide', structure === null || structure.dataset.placeMissing === undefined);

		};

		form.addEventListener('change', e => {

			if(e.target.name === 'structureProducer') {
				update();
			}

		});

		update();

	}

	static watchRights(node) {

		const form = node.closest('form');

		if(form === null) {
			return;
		}

		const question = form.querySelector('[data-rights-question]');

		if(question === null) {
			return;
		}

		const update = () => {

			const structure = form.querySelector('input[name="structureProducer"]:checked');

			question.classList.toggle('hide', structure !== null && structure.value === '0');

		};

		form.addEventListener('change', e => {

			if(e.target.name === 'structureProducer') {
				update();
			}

		});

		update();

	}

}
