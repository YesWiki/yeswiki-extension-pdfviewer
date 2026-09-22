# Extension pdfviewer

Affiche un fichier PDF du wiki dans un lecteur intégré construit sur
[PDF.js](https://github.com/mozilla/pdf.js) (Mozilla, licence Apache 2.0).

## Pourquoi cette extension existe

Le cœur de YesWiki confie maintenant les fichiers PDF au lecteur du navigateur. Ça
marche sur un navigateur de bureau récent, mais ça rend la lecture imprévisible, et sur
certains appareils ça ne marche pas du tout.

- **Le rendu change d'un navigateur à l'autre.** Chrome, Firefox et Safari embarquent
  chacun un lecteur différent, avec sa barre d'outils, ses raccourcis clavier et son
  comportement de zoom. La même page du wiki se présente donc différemment selon qui
  la lit.
- **Les navigateurs mobiles affichent rarement les PDF en ligne.** La plupart
  téléchargent le fichier ou le passent à une autre application, ce qui sort le lecteur
  du wiki.
- **Les navigateurs anciens ou légers n'ont pas de lecteur PDF.** Pour ces visiteurs, le
  document est simplement illisible sur place.

Cette extension embarque son propre lecteur au lieu de s'en remettre à celui du
navigateur. Un PDF se présente alors de la même façon pour tout le monde, sur ordinateur
comme sur téléphone, sur navigateur récent ou non, et le lecteur reste sur la page du
wiki.

## Relation avec l'extension `attach`

Cette extension fournit l'action `pdf` et **remplace** celle livrée par `attach` : le
`Performer` parcourt les extensions par ordre alphabétique et garde la dernière
correspondance trouvée, or `pdfviewer` passe après `attach`. Rien n'est modifié dans
`attach`, donc désinstaller cette extension rétablit le comportement précédent.

## Utilisation

```text
{{pdf url="https://mon-wiki.org/files/document.pdf"}}
{{pdf url="…" ratio="paysage" largeurmax="600"}}
{{pdf url="…" class="pull-right" hauteurmax="400"}}
```

| Paramètre | Description |
| --- | --- |
| `url` | **Obligatoire.** Url du PDF, qui doit partager l'origine du wiki : même schéma, même hôte, même port. |
| `ratio` | Forme du conteneur : `portrait` par défaut, `paysage`, `carre`. |
| `largeurmax` | Largeur maximale, en pixels, sans unité. |
| `hauteurmax` | Hauteur maximale, en pixels, sans unité. |
| `class` | Classes ajoutées au conteneur. `pull-left` et `pull-right` positionnent le bloc. |

## Installation

```bash
cd tools/pdfviewer
yarn install --ignore-optional
```

`yarn install` installe `pdfjs-dist`, puis lance `scripts/assemble-pdfjs.mjs`, qui
construit `javascripts/vendor/pdfjs-dist/`. Ce dossier **n'est pas versionné**, il doit
donc être régénéré sur chaque environnement, développement comme production.

`--ignore-optional` écarte `@napi-rs/canvas`, une dépendance optionnelle de `pdfjs-dist`
qui rend les PDF en images depuis Node. Cette extension ne fait que copier des fichiers
statiques du paquet et exécute le lecteur dans le navigateur, donc ces 61 Mo de binaires
natifs ne servent jamais. Le drapeau doit être tapé à chaque fois : yarn 1 ne sait pas
le conserver dans `.yarnrc`, où les options booléennes sont ajoutées comme argument
positionnel et font échouer la commande.

Si le dossier assemblé manque, l'action affiche un message explicite plutôt qu'un cadre
vide.

## Mettre à jour PDF.js

```bash
cd tools/pdfviewer
yarn upgrade pdfjs-dist --latest --ignore-optional
```

`yarn.lock` fait foi : le script lit la version installée dans
`node_modules/pdfjs-dist/` et télécharge l'archive du tag GitHub correspondant. Un
lecteur désynchronisé de son moteur est donc impossible.

Le script ne fait rien quand `revision.json` correspond déjà à la version installée : il
est idempotent, et ne sollicite le réseau que si la version change.

Attention, `yarn upgrade` ne rejoue pas le `postinstall` du paquet racine. Après une
montée de version, lancer le script à la main :

```bash
node scripts/assemble-pdfjs.mjs
```

## Pourquoi une archive GitHub en plus du paquet npm

Le paquet npm `pdfjs-dist` fournit le moteur PDF mais **pas** l'application de lecture
(`web/viewer.html`, `viewer.mjs`, `viewer.css`, `web/locale/`), qui n'est publiée que
dans les archives de release GitHub.

## Licence

AGPL-3.0. PDF.js est distribué sous licence Apache 2.0 ; son fichier de licence est
conservé dans le dossier assemblé.
