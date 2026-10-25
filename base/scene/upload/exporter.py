"""Contrôle la scène Blender puis l'exporte en modèle 3D pour Ouvretaferme (.glb), avatar ou objet du décor.

Par le MCP de Blender, dans le code exécuté :

    exec(open('<dossier du skill>/exporter.py').read())
    exporter('<sortie>.glb', '<dossier du skill>')

En ligne de commande :

    blender -b --factory-startup <modele>.blend --python <dossier du skill>/exporter.py -- <sortie>.glb

Seuls les objets visibles partent. Le script lit les règles à côté de lui, dans le seul fichier de règles du dossier
(avatar.json ou decor.json), dont `profile` dit ce que la scène doit porter. Un objet du décor se juge sous les règles de sa
famille, `famille` quand les règles en portent plusieurs. Le script dit ce qui ne va pas avant d'exporter, puis fait juger
le fichier écrit par upload.mjs si node est installé : c'est ce jugement qui fait foi, le même que celui du site.

Avant d'exporter, il contrôle sur la scène les images, l'armature, les formes clés, l'animation, les triangles, le nombre
d'objets et leur imbrication, les primitives (une par objet et par matériau), puis selon le profil :
- avatar : les noms d'os, chaque os pourvu, l'emprise sur les trois axes, et le sol (le bas des tibias à 0, aucune pièce
  dessous) ;
- décor : les noms de pièces, les pièces obligatoires, les couleurs, l'emprise centrée sur l'origine, le sol (le point le
  plus bas à 0) et les ancres.
Après l'export, il lit le fichier : poids et extensions glTF.
Il ne contrôle pas ce que seul upload.mjs sait juger sur le fichier écrit : sa structure binaire et les longueurs
de ses tampons. Il ne voit pas non plus ce que le validateur ne juge pas : une pièce qui en traverse une autre
dans une pose (poser.py, ou rendre.mjs sans Blender), une face tournée vers l'intérieur (rendre.mjs, qui ne dessine que
l'avant des faces, la montre comme un trou).
"""
import json
import pathlib
import shutil
import struct
import subprocess
import sys

import bpy


def fichier_regles(dossier):
	"""Le fichier de règles du dossier : le seul de ses .json qui nomme un profil."""
	fichiers = [f for f in sorted(pathlib.Path(dossier).glob('*.json')) if 'profile' in json.loads(f.read_text(encoding='utf-8'))]
	if len(fichiers) != 1:
		raise RuntimeError(f"Un seul fichier de règles attendu dans {dossier}, trouvés : {', '.join(f.name for f in fichiers) or 'aucun'}.")
	return fichiers[0]


def regles(dossier, famille=None):
	"""Les règles du profil, et pour le décor le tronc commun complété du bloc de sa famille, comme upload.mjs les lit."""
	r = json.loads(fichier_regles(dossier).read_text(encoding='utf-8'))
	if 'families' not in r:
		return r
	familles = r.pop('families')
	if famille is None and len(familles) == 1:
		famille = next(iter(familles))
	if famille not in familles:
		raise RuntimeError(f"Famille {famille} inconnue, les familles sont {', '.join(familles)}.")
	return {**r, **familles[famille], 'family': famille}


def objets():
	return [o for o in bpy.context.scene.objects if o.type == 'MESH' and o.visible_get()]


def nom_de(objet):
	"""L'os ou la pièce d'un objet : son nom jusqu'au premier point, `tete.003` vaut `tete`."""
	return objet.name.split('.')[0]


def profondeur(objet):
	n = 1
	while objet.parent is not None:
		objet, n = objet.parent, n + 1
	return n


def couleur(materiau):
	"""La couleur de base d'un matériau, telle que l'export l'écrit, arrondie comme deux teintes que l'œil confond."""
	bsdf = next((n for n in materiau.node_tree.nodes if n.type == 'BSDF_PRINCIPLED'), None) if materiau.use_nodes else None
	valeur = bsdf.inputs['Base Color'].default_value if bsdf is not None else materiau.diffuse_color
	return tuple(round(c, 3) for c in valeur[:3])


def verifier(r):
	"""Les problèmes de la scène, un par ligne. Une liste vide ne garantit rien : upload.mjs reste le juge."""
	problemes = []
	avatar = r['profile'] == 'avatar'
	noms_admis = r['bones'] if avatar else r['pieces']
	maillages = objets()
	couleurs = set()
	depsgraph = bpy.context.evaluated_depsgraph_get()
	triangles = 0
	primitives = 0
	# Les règles sont en axes glTF : x de glTF est x de Blender, y de glTF est z de Blender, z de glTF est -y de Blender
	bas_pieds, bas_tout = [], []
	mini, maxi = [float('inf')] * 3, [float('-inf')] * 3

	for o in maillages:

		if nom_de(o) not in noms_admis:
			if avatar:
				problemes.append(f"« {o.name} » ne porte le nom d'aucun os ({', '.join(noms_admis)}).")
			else:
				problemes.append(f"« {o.name} » ne porte le nom d'aucune pièce ({', '.join(noms_admis)}).")

		materiaux = {slot.material for slot in o.material_slots if slot.material is not None}
		couleurs |= {couleur(m) for m in materiaux}

		for m in materiaux:
			if m.use_nodes and any(n.type == 'TEX_IMAGE' for n in m.node_tree.nodes):
				problemes.append(f"Le matériau « {m.name} » de « {o.name} » utilise une image : une couleur unie seulement.")

		if o.find_armature() is not None or any(mod.type == 'ARMATURE' for mod in o.modifiers):
			if avatar:
				problemes.append(f"« {o.name} » est lié à une armature : retirez-la, chaque objet suit son os par son nom.")
			else:
				problemes.append(f"« {o.name} » est lié à une armature : retirez-la, un objet du décor est fait de pièces fixes.")

		if o.data.shape_keys is not None:
			problemes.append(f"« {o.name} » a des formes clés : appliquez-les ou retirez-les.")

		if o.animation_data is not None and o.animation_data.action is not None:
			problemes.append(f"« {o.name} » est animé : retirez son animation.")

		evalue = o.evaluated_get(depsgraph)
		maillage = evalue.to_mesh()
		maillage.calc_loop_triangles()
		triangles += len(maillage.loop_triangles)
		# L'export écrit une primitive par matériau que portent les faces de l'objet
		primitives += max(1, len({p.material_index for p in maillage.polygons}))
		points = [evalue.matrix_world @ v.co for v in maillage.vertices]
		evalue.to_mesh_clear()

		if points:
			for axe, valeurs in enumerate(([p.x for p in points], [p.z for p in points], [-p.y for p in points])):
				mini[axe] = min(mini[axe], min(valeurs))
				maxi[axe] = max(maxi[axe], max(valeurs))
			bas_tout.append((min(p.z for p in points), o.name))
			if nom_de(o) in ('tibiaG', 'tibiaD'):
				bas_pieds.append(min(p.z for p in points))

	presents = {nom_de(o) for o in maillages}
	if avatar:
		manquants = [b for b in r['bones'] if b not in presents]
		if manquants:
			problemes.append(f"Aucun objet pour l'os {', '.join(manquants)} : chaque os porte au moins un objet.")
	else:
		manquants = [p for p in r['required'] if p not in presents]
		if manquants:
			problemes.append(f"Aucun objet pour la pièce {', '.join(manquants)}, qui est obligatoire.")
		if len(couleurs) > r['maxColors']:
			problemes.append(f"{len(couleurs)} couleurs, pour {r['maxColors']} au plus : donnez la même couleur aux matériaux proches.")

	if triangles > r['maxTriangles']:
		problemes.append(f"{triangles} triangles, pour {r['maxTriangles']} au plus.")

	meme = "d'un même os" if avatar else "d'une même pièce"

	exportes = [o for o in bpy.context.scene.objects if o.visible_get() and o.type in ('MESH', 'EMPTY')]
	if len(exportes) > r['maxNodes']:
		problemes.append(f"{len(exportes)} objets, pour {r['maxNodes']} au plus : fusionnez les objets {meme}.")
	if exportes and max(profondeur(o) for o in exportes) > r['maxDepth']:
		problemes.append(f"Des objets imbriqués sur plus de {r['maxDepth']} niveaux : aplatissez la hiérarchie.")

	if primitives > r['maxPrimitives']:
		problemes.append(f"{primitives} primitives (une par objet et par matériau), pour {r['maxPrimitives']} au plus dans tout le modèle : fusionnez les objets de même couleur {meme}, puis leurs matériaux de même couleur.")

	if not maillages:
		return problemes

	if avatar:

		bas, haut = r['bounds']
		noms = ('x (largeur, gauche en +x)', 'hauteur', 'profondeur (devant en -y de Blender)')
		for axe in range(3):
			if maxi[axe] > haut[axe] or (axe != 1 and mini[axe] < bas[axe]):
				problemes.append(f"Le modèle sort de l'emprise en {noms[axe]} : de {mini[axe]:.2f} à {maxi[axe]:.2f} m, pour {bas[axe]} à {haut[axe]} m. Vérifiez l'échelle, un mètre de Blender vaut un mètre.")

		if bas_pieds and abs(min(bas_pieds)) > r['ground']:
			problemes.append(f"Le bas des pieds (objets tibiaG et tibiaD) est à {min(bas_pieds):.3f} m : placez-le à 0.")

		sous_le_sol = [nom for z, nom in bas_tout if z < -r['ground']]
		if sous_le_sol:
			problemes.append(f"Sous le sol, à plus de {r['ground']} m : {', '.join(sous_le_sol)}. Rien ne passe sous les pieds.")

	else:

		emprise = r['bounds']
		if max(abs(mini[0]), abs(maxi[0])) > emprise['width'] / 2 or max(abs(mini[2]), abs(maxi[2])) > emprise['length'] / 2 or maxi[1] > emprise['height']:
			problemes.append(f"Le modèle mesure de {mini[0]:.2f} à {maxi[0]:.2f} m en x, de {mini[2]:.2f} à {maxi[2]:.2f} m en profondeur et {maxi[1]:.2f} m de haut, pour {emprise['width']} m de large et {emprise['length']} m de long centrés sur l'origine, et {emprise['height']} m de haut. Vérifiez l'échelle, un mètre de Blender vaut un mètre.")

		if abs(mini[1]) > r['ground']:
			problemes.append(f"Le point le plus bas est à {mini[1]:.3f} m : posez le modèle à 0, rien dessous.")

		problemes += verifier_ancres(r)

	return problemes


def verifier_ancres(r):
	"""Les ancres d'un objet du décor : des objets vides nommés d'après elles, une seule par nom, dans l'emprise."""
	problemes = []
	emprise = r['bounds']
	vues = set()
	for o in bpy.context.scene.objects:
		if o.type != 'EMPTY' or not o.visible_get() or nom_de(o) not in r['anchors']:
			continue
		if nom_de(o) in vues:
			problemes.append(f"L'ancre {nom_de(o)} est posée deux fois : gardez un seul objet vide de ce nom.")
		vues.add(nom_de(o))
		x, y, z = o.matrix_world.translation.x, o.matrix_world.translation.z, -o.matrix_world.translation.y
		if abs(x) > emprise['width'] / 2 or abs(z) > emprise['length'] / 2 or y > emprise['height'] or y < -r['ground']:
			problemes.append(f"L'ancre {o.name} est hors de l'emprise du modèle.")
	return problemes


def lire_fichier(sortie, r):
	"""Ce que l'export a écrit et que la scène ne montrait pas : le poids et les extensions glTF."""
	donnees = pathlib.Path(sortie).read_bytes()
	problemes = []
	if len(donnees) > r['maxBytes']:
		problemes.append(f"Le fichier pèse {len(donnees)} octets, pour {r['maxBytes']} au plus.")
	longueur = struct.unpack_from('<I', donnees, 12)[0]
	entete = json.loads(donnees[20:20 + longueur])
	extensions = sorted(set(entete.get('extensionsUsed', [])) | set(entete.get('extensionsRequired', [])))
	if extensions:
		problemes.append(f"L'export a écrit les extensions glTF {', '.join(extensions)}, refusées par le site : dans le Principled BSDF, ne réglez que Base Color, Roughness et Metallic.")
	return problemes


def exporter(sortie, dossier=None, famille=None):
	"""Contrôle, exporte en .glb, puis fait juger le fichier par upload.mjs. Rend le jugement, ou None sans node."""
	dossier = pathlib.Path(dossier) if dossier is not None else pathlib.Path(__file__).parent
	r = regles(dossier, famille)

	problemes = verifier(r)
	if problemes:
		print('À corriger avant d\'exporter :')
		for p in problemes:
			print('- ' + p)
		return None

	# Les options changent de nom d'une version de Blender à l'autre : seules celles que l'opérateur connaît lui sont passées
	voulues = dict(
		filepath=str(sortie), export_format='GLB', use_visible=True, export_yup=True, export_apply=True,
		export_materials='EXPORT', export_texcoord=False, export_normals=False, export_skins=False,
		export_animations=False, export_morph=False, export_lights=False, export_cameras=False, export_extras=False,
	)
	connues = {p.identifier for p in bpy.ops.export_scene.gltf.get_rna_type().properties}
	bpy.ops.export_scene.gltf(**{cle: valeur for cle, valeur in voulues.items() if cle in connues})

	problemes = lire_fichier(sortie, r)
	if problemes:
		print(f'Exporté vers {sortie}, mais à corriger :')
		for p in problemes:
			print('- ' + p)

	node = shutil.which('node')
	if node is None:
		print(f'Exporté vers {sortie}. node est introuvable : jugez le fichier avec upload.mjs avant de l\'envoyer.')
		return None

	commande = [node, str(dossier / 'upload.mjs'), '--check', '--rules=' + str(fichier_regles(dossier)), '--in=' + str(sortie)]
	if 'family' in r:
		commande.append('--family=' + r['family'])

	jugement = json.loads(subprocess.run(commande, capture_output=True, text=True, check=True).stdout)
	print(f'Exporté vers {sortie} : {json.dumps(jugement, ensure_ascii=False)}')
	return jugement


# Lu par exec(), le script prend le __file__ de qui l'exécute, ou aucun : seul le lancement par --python exporte de lui-même
if '--' in sys.argv and pathlib.Path(globals().get('__file__', '')).name == 'exporter.py':
	exporter(sys.argv[sys.argv.index('--') + 1])
