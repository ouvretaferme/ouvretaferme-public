class StructureMember {

	static watchSections(wrapper) {

		const form = wrapper.closest('form');

		if(form === null) {
			return;
		}

		const update = () => {

			const role = form.querySelector('input[name="role"]:checked');
			const staff = (role !== null && role.dataset.staff === '1');

			// L'encadrement n'entre dans aucun sous-groupe : les cases partent, et leur coche avec
			// elles, sans quoi un rangement fait avant de changer de rôle partirait quand même.
			wrapper.classList.toggle('hide', staff);

			if(staff) {
				wrapper.querySelectorAll('input[name="sections[]"]').forEach(checkbox => checkbox.checked = false);
			}

		};

		form.addEventListener('change', e => {

			if(e.target.name === 'role') {
				update();
			}

		});

		update();

	}

}
