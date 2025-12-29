# Fix déploiement assets React (29 décembre 2024)

## Problème
Les assets JS et CSS ne se chargeaient pas en production sur :
https://www.photographie.stephanewagner.com/phototeque-react/

Erreurs 404 :
- GET /assets/index-CBt_eoBb.js (404)
- GET /assets/index-q01vHpm2.css (404)

## Cause
Les chemins dans `public/index.html` étaient absolus (`/assets/...`) au lieu de relatifs (`./assets/...`), ce qui ne fonctionne pas dans un sous-répertoire.

## Solution appliquée
Modification de `public/index.html` :
- `/assets/index-CBt_eoBb.js` → `./assets/index-CBt_eoBb.js`
- `/assets/index-q01vHpm2.css` → `./assets/index-q01vHpm2.css`
- `/vite.svg` → `./vite.svg`

## Pour corriger à la source (projet React)
Dans le projet React source (non présent dans ce repo), configurer Vite avec :

```js
// vite.config.js
export default {
  base: './', // Utiliser des chemins relatifs au lieu de /
  build: {
    outDir: '../public'
  }
}
```

## Déploiement
Fichier uploadé via SSH :
```bash
scp public/index.html dreamhost-phototheque:./photographie.stephanewagner.com/wp-content/plugins/photo_library_wp_api_rest_plugin/public/index.html
```
