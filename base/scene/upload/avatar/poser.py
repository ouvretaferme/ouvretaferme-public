"""Met la scène Blender dans une pose des règles, comme le site anime le modèle.

Par le MCP de Blender, dans le code exécuté :

    exec(open('<dossier du skill>/poser.py').read())
    poser('assis', '<dossier du skill>')     # une pose de `poses` d'avatar.json
    poser('repos', '<dossier du skill>')     # remet chaque objet où il était

Pour voir la pose sans Blender, `node rendre.mjs <modele>.glb <dossier> --pose=<nom>` la rend comme l'éditeur du site.

Chaque objet suit l'os qui le nomme (`tete.020` suit `tete`). Le calcul est celui du site :
- les angles sont en radians, dans les axes glTF (Y en haut, regard vers +Z), en Euler XYZ comme three.js,
  c'est-à-dire la matrice Rx · Ry · Rz ;
- chaque os tourne autour de son pivot des règles, dans le repère de son parent (`parents`) ;
- puis tout le corps descend jusqu'à ce que le point le plus bas des objets `tibiaG` et `tibiaD` touche le sol.

Un angle X positif penche vers l'avant ce qui est au-dessus du pivot (le buste en `recolte`) et ramène vers l'arrière ce
qui pend dessous (la jambe arrière en `marche`). Ne sauvegarde pas une scène posée : le modèle s'exporte au repos.
"""
import json
import pathlib

import bpy
from mathutils import Matrix, Vector

# glTF (x, y, z) vaut (x, -z, y) dans Blender
GLTF_VERS_BLENDER = Matrix(((1, 0, 0, 0), (0, 0, -1, 0), (0, 1, 0, 0), (0, 0, 0, 1)))
BLENDER_VERS_GLTF = GLTF_VERS_BLENDER.inverted()


def regles(dossier):
	return json.loads((pathlib.Path(dossier) / 'avatar.json').read_text(encoding='utf-8'))


def objets():
	return [o for o in bpy.context.scene.objects if o.type == 'MESH' and o.visible_get()]


def os_de(objet):
	return objet.name.split('.')[0]


def rotation(angles):
	x, y, z = angles
	return Matrix.Rotation(x, 4, 'X') @ Matrix.Rotation(y, 4, 'Y') @ Matrix.Rotation(z, 4, 'Z')


def matrices_des_os(r, angles):
	"""Pour chaque os, la matrice qui mène un point du corps au repos à sa place dans la pose, en axes glTF."""
	mondes = {}

	def monde(os):
		if os not in mondes:
			parent = r['parents'].get(os)
			depart = Vector(r['pivots'][parent]) if parent is not None else Vector((0, 0, 0))
			locale = Matrix.Translation(Vector(r['pivots'][os]) - depart) @ rotation(angles.get(os, (0, 0, 0)))
			mondes[os] = locale if parent is None else monde(parent) @ locale
		return mondes[os]

	return {os: monde(os) @ Matrix.Translation(-Vector(r['pivots'][os])) for os in r['bones']}


def poser(nom, dossier=None):
	"""Pose les objets visibles. `repos` les remet à leur place. Rend la descente du corps, en mètres."""
	dossier = pathlib.Path(dossier) if dossier is not None else pathlib.Path(__file__).parent
	r = regles(dossier)
	maillages = [o for o in objets() if os_de(o) in r['bones']]

	# La place au repos se garde sur l'objet la première fois, pour revenir au repos ou passer d'une pose à l'autre
	for o in maillages:
		if 'repos' not in o:
			o['repos'] = [v for ligne in o.matrix_world for v in ligne]
		o.matrix_world = Matrix([o['repos'][i:i + 4] for i in range(0, 16, 4)])

	if nom == 'repos':
		for o in maillages:
			del o['repos']
		return 0.0

	if nom not in r['poses']:
		raise ValueError(f"Pose inconnue « {nom} » : {', '.join(['repos', *r['poses']])}.")

	os_poses = matrices_des_os(r, r['poses'][nom])
	vers = {os: GLTF_VERS_BLENDER @ m @ BLENDER_VERS_GLTF for os, m in os_poses.items()}

	for o in maillages:
		o.matrix_world = vers[os_de(o)] @ o.matrix_world

	bpy.context.view_layer.update()

	# Le site descend le corps de ce qu'il faut pour poser au sol le point le plus bas des tibias
	depsgraph = bpy.context.evaluated_depsgraph_get()
	bas = []
	for o in maillages:
		if os_de(o) in ('tibiaG', 'tibiaD'):
			evalue = o.evaluated_get(depsgraph)
			bas.extend((evalue.matrix_world @ v.co).z for v in evalue.data.vertices)
	descente = min(bas) if bas else 0.0

	for o in maillages:
		o.matrix_world = Matrix.Translation((0, 0, -descente)) @ o.matrix_world

	return descente
