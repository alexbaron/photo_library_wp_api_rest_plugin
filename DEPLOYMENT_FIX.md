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

## Fix CORS et redirection 301 (29 décembre 2024 - 23h05)

### Problème supplémentaire
Erreurs CORS sur les appels API :
```
Access to XMLHttpRequest at 'https://photographie.stephanewagner.com/wp-json/...' 
from origin 'https://www.photographie.stephanewagner.com' has been blocked by CORS policy
GET https://photographie.stephanewagner.com/... net::ERR_FAILED 301 (Moved Permanently)
```

### Cause
Le serveur redirige automatiquement `photographie.stephanewagner.com` vers `www.photographie.stephanewagner.com` (301).
L'app React utilisait l'URL sans `www`, causant :
1. Une redirection 301 qui empêche les headers CORS
2. Un problème de cross-origin entre `www` et sans `www`

### Solution
Modification de l'URL de l'API dans le fichier buildé :
```bash
# Backup et correction
cp public/assets/index-CBt_eoBb.js public/assets/index-CBt_eoBb.js.backup
sed -i '' 's|https://photographie\.stephanewagner\.com/wp-json|https://www.photographie.stephanewagner.com/wp-json|g' public/assets/index-CBt_eoBb.js

# Créer une version avec nouveau nom pour contourner le cache serveur (30 jours)
cp public/assets/index-CBt_eoBb.js public/assets/index-CBt_eoBb-v2.js
```

Mise à jour de `index.html` pour pointer vers le nouveau fichier :
```html
<script type="module" crossorigin src="./assets/index-CBt_eoBb-v2.js"></script>
```

**Note :** Pour corriger à la source, modifier l'URL de l'API dans le projet React source avant le build.

## Déploiement
Fichiers uploadés via SSH :
```bash
# Fix des chemins relatifs
scp public/index.html dreamhost-phototheque:./photographie.stephanewagner.com/wp-content/plugins/photo_library_wp_api_rest_plugin/public/index.html

# Fix de l'URL API (www) - version avec cache-busting
scp public/assets/index-CBt_eoBb-v2.js dreamhost-phototheque:./photographie.stephanewagner.com/wp-content/plugins/photo_library_wp_api_rest_plugin/public/assets/index-CBt_eoBb-v2.js

# Mise à jour de index.html pour pointer vers v2
scp public/index.html dreamhost-phototheque:./photographie.stephanewagner.com/wp-content/plugins/photo_library_wp_api_rest_plugin/public/index.html
```
