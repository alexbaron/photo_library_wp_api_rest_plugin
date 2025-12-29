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
// vite.config.ts
export default defineConfig({
  base: './', // Utiliser des chemins relatifs au lieu de /
  build: {
    outDir: 'dist'
  }
})
```

Et mettre à jour les URLs de l'API :

```bash
# .env.production
VITE_BASE_URL=https://www.photographie.stephanewagner.com/wp-json/photo-library/v1
VITE_WP_URL=https://www.photographie.stephanewagner.com

# src/config/config.ts
# Mettre à jour les fallback URLs pour utiliser www.photographie.stephanewagner.com
```

✅ **Appliqué dans le projet source** : `/Users/alexandrebaron/Documents/dev/perso/phototheque`
- Commit: `8794433` - "fix: use www subdomain for API URL and relative paths"

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

## Fix random picture cache (29 décembre 2024 - 23h18)

### Problème
L'endpoint `/pictures/random/0` retourne toujours la même image côté client, même si le serveur renvoie bien des IDs différents à chaque requête.

### Cause
Cache HTTP du navigateur ou du proxy sur les requêtes GET. Axios/navigateur met en cache les réponses des requêtes identiques.

### Solution
Ajout de cache-busting dans `picture-api.ts` :
```typescript
// Ajout d'un timestamp unique pour chaque requête
const timestamp = Date.now();
axios.get(`${BASE_URL}/pictures/random/${id}?_t=${timestamp}`, {
  headers: {
    'Cache-Control': 'no-cache',
    'Pragma': 'no-cache'
  }
})
```

### Déploiement
```bash
cd /Users/alexandrebaron/Documents/dev/perso/phototheque
npm run build
scp -r dist/* dreamhost-phototheque:./photographie.stephanewagner.com/wp-content/plugins/photo_library_wp_api_rest_plugin/public/
```

Nouveau fichier généré : `index-CG7SJabw.js` (le hash change automatiquement à chaque build)

✅ **Commit React** : `fa52d94` - "fix: add cache-busting to random picture endpoint"

## Fix KeywordAPIResponse TypeError (29 décembre 2024 - 23h27)

### Problème
```
Error fetching keywords, using props keywords: TypeError: Cannot read properties of undefined (reading 'map')
    at w (SearchBox.tsx:34:49)
```

### Cause
Incompatibilité entre la structure de la réponse API et l'interface TypeScript :
- **API retourne** : `{ message: string, data: string[] }`
- **Interface attendait** : `{ keywords: string[] }`

### Solution
1. **Mise à jour de l'interface** (`interfaces.ts`) :
```typescript
interface KeywordAPIResponse {
  message?: string;
  data: string[];
  cached?: boolean;
  cache_time?: string;
}
```

2. **Transformation dans l'API client** (`picture-api.ts`) :
```typescript
static fetchKeywords(): Promise<{ keywords: string[] }> {
  return axios.get(`${BASE_URL}/pictures/keywords`)
    .then((response: AxiosResponse<KeywordAPIResponse>) => {
      // Transform API response to expected format
      return { keywords: response.data.data };
    });
}
```

3. **Null checks défensifs** (`SearchBox.tsx`) :
```typescript
const keywordsOptions = (response.keywords || []).map(keyword => ({ title: keyword }))
const keywordsFromProps = (keywords || []).map(keyword => ({ title: keyword }))
```

### Déploiement
```bash
cd /Users/alexandrebaron/Documents/dev/perso/phototheque
npm run build  # Génère index-C0jkVbdf.js
scp -r dist/* dreamhost-phototheque:./photographie.stephanewagner.com/wp-content/plugins/photo_library_wp_api_rest_plugin/public/
```

✅ **Commit React** : `14926c5` - "fix: correct KeywordAPIResponse structure and add null checks"

## Fix vite.svg 404 (29 décembre 2024 - 23h31)

### Problème
```
GET https://www.photographie.stephanewagner.com/phototeque-react/vite.svg 404 (Not Found)
```

Le fichier favicon `vite.svg` référencé dans `index.html` n'existait pas.

### Solution
Création d'un favicon SVG simple avec gradient et lettre "P" :

```svg
<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32">
  <defs>
    <linearGradient id="grad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:#41d1ff;stop-opacity:1" />
      <stop offset="100%" style="stop-color:#bd34fe;stop-opacity:1" />
    </linearGradient>
  </defs>
  <rect width="32" height="32" rx="4" fill="url(#grad)"/>
  <text x="16" y="22" font-family="Arial" font-size="18" font-weight="bold" text-anchor="middle" fill="white">P</text>
</svg>
```

### Déploiement
```bash
cd /Users/alexandrebaron/Documents/dev/perso/phototheque
# Créer public/vite.svg
npm run build  # Copié automatiquement dans dist/
scp dist/vite.svg dreamhost-phototheque:./photographie.stephanewagner.com/wp-content/plugins/photo_library_wp_api_rest_plugin/public/
```

✅ **Commit React** : `6589297` - "fix: add missing vite.svg favicon"
✅ **Vérification** : https://www.photographie.stephanewagner.com/phototeque-react/vite.svg → 200 OK

## Fix Color Search Empty Results (29 décembre 2024 - 23h37)

### Problème
```
POST /wp-json/photo-library/v1/pictures/by_dominant_color
{"rgb": [227, 227, 227], "limit": 10}
→ {"results_count": 0, "pictures": []}
```

La recherche par couleur retournait toujours un tableau vide même si des photos correspondantes existaient dans la base de données.

### Cause
Le code retournait immédiatement quand Pinecone ne trouvait aucun résultat, sans utiliser le fallback local. 

**Séquence du bug :**
1. Pinecone interrogé → retourne `[]` (vide)
2. Code: `if (!empty($pinecone_results))` → false
3. ❌ Retour immédiat avec `pictures: []`
4. Le fallback local n'était jamais exécuté

### Solution
Modification de la logique dans `class.photo-library-route.php` :

```php
// AVANT
if (!empty($pinecone_results)) {
    $pictures_data = [];
    foreach ($pinecone_results as $match) {
        // ... populate pictures_data
    }
    return new WP_REST_Response([
        'pictures' => $pictures_data
    ]);
}
// Fallback local...

// APRÈS
if (!empty($pinecone_results)) {
    $pictures_data = [];
    foreach ($pinecone_results as $match) {
        // ... populate pictures_data
    }
    // ✅ Only return if we have actual pictures
    if (!empty($pictures_data)) {
        return new WP_REST_Response([
            'pictures' => $pictures_data
        ]);
    }
    // ✅ Fall through to local search if no valid pictures
    error_log('Pinecone results contained no valid pictures, falling back to local search');
}
// Fallback local est maintenant atteint
```

### Tests
```bash
# Test avec RGB [227,227,227]
curl -X POST ".../pictures/by_dominant_color" \
  -d '{"rgb": [227, 227, 227], "limit": 3}'

# ✅ AVANT le fix: results_count: 0
# ✅ APRÈS le fix: results_count: 3
```

**Résultats obtenus :**
- ID 93486 - "Usure et texture monochrome" - distance: 0 (exact match!)
- ID 93206 - "Vendeur cubain pensif" - distance: 0
- ID 93009 - "IMGS8396.jpg" - distance: 0

### Déploiement
```bash
scp src/routing/class.photo-library-route.php dreamhost-phototheque:./photographie.stephanewagner.com/wp-content/plugins/photo_library_wp_api_rest_plugin/src/routing/
```

✅ **Commit WordPress** : `b57eef3` - "fix: fallback to local search when Pinecone returns empty results"
✅ **Vérification** : La recherche par couleur retourne maintenant des résultats via le fallback local

## Fix Pinecone ID Parsing (29 décembre 2024 - 23h42)

### Problème racine découvert
Après investigation, Pinecone **fonctionnait correctement** et retournait bien des résultats, mais le code PHP ne pouvait pas les exploiter.

#### Test direct Pinecone
```bash
php test_pinecone.php
# Résultats:
# 1. ID: img_92812, Score: 0.0000, photo_id: 92812
# 2. ID: img_93009, Score: 0.0000, photo_id: 93009
# 3. ID: img_93206, Score: 0.0000, photo_id: 93206
```

✅ Pinecone retourne bien 5 résultats avec les IDs préfixés `img_XXXXX`

### Cause réelle
```php
// ❌ AVANT - Ligne 1582
$photo_id = (int) $match['id'];
// Conversion de 'img_92812' en int → 0

// ✅ APRÈS  
$metadata = $match['metadata'] ?? [];
$photo_id = isset($metadata['photo_id']) ? (int) $metadata['photo_id'] : 0;
// Utilise metadata['photo_id'] qui contient '92812' → 92812
```

**Problème :** Les IDs Pinecone sont sous forme `img_92812`, convertir ça en `int` donne `0`.

**Solution :** Utiliser `metadata['photo_id']` qui contient l'ID WordPress réel.

### Améliorations ajoutées

1. **Extraction correcte de l'ID** depuis les métadonnées
2. **Parse RGB string** : Gère le format `"227,227,227"` depuis les métadonnées
3. **Validation** : Skip les matches sans `photo_id` valide
4. **Logs** : Alerte si un match n'a pas de `photo_id`

### Tests après fix

```bash
curl -X POST ".../pictures/by_dominant_color" \
  -d '{"rgb": [227, 227, 227], "limit": 5}'
```

**Résultats :**
- ✅ `results_count: 5`
- ✅ `search_source: pinecone` (avant: "euclidean" = fallback local)
- ✅ Scores: 0.0000 (match exact!)
- ✅ Photos retournées :
  - ID 93206 - Vendeur cubain pensif
  - ID 92812 - IMG_6912.jpg
  - ID 93486 - Usure et texture monochrome
  - ID 93009 - IMGS8396.jpg
  - ID 91492 - Wireframe placeholder

### Déploiement
```bash
scp src/routing/class.photo-library-route.php dreamhost-phototheque:.../src/routing/
```

✅ **Commit WordPress** : `468aacd` - "fix: extract photo_id from Pinecone metadata instead of match id"
✅ **Status** : Pinecone fonctionne maintenant parfaitement avec scores exacts!

### Conclusion
**Pinecone était correctement configuré et indexé** (612 vecteurs). Le seul problème était l'extraction de l'ID depuis les résultats. Fix appliqué et testé ✅

## Ajout des thumbnails aux résultats de recherche par couleur (29 décembre 2024 - 23h49)

### Objectif
Afficher des miniatures des photos dans le composant `PictureList` pour les résultats de recherche par couleur.

### Changements Backend (PHP)

#### Problème
L'API retournait uniquement l'URL complète de l'image dans le champ `url`, pas les différentes tailles (thumbnail, medium, large).

#### Solution
Ajout d'un objet `src` dans les résultats avec toutes les tailles disponibles :

```php
// Récupération des différentes tailles
$thumbnail_url = wp_get_attachment_image_url($photo_id, 'thumbnail');
$medium_url = wp_get_attachment_image_url($photo_id, 'medium');
$large_url = wp_get_attachment_image_url($photo_id, 'large');

// Ajout dans les résultats
'src' => [
    'thumbnail' => $thumbnail_url ?: $attachment_url,
    'medium' => $medium_url ?: $attachment_url,
    'large' => $large_url ?: $attachment_url,
    'full' => $attachment_url
]
```

**Appliqué à :**
- Résultats Pinecone (ligne ~1298)
- Fallback local (ligne ~1442)

#### Exemple de réponse
```json
{
  "id": 92812,
  "title": "IMG_6912.jpg",
  "url": "https://.../lightroom-6912.webp",
  "src": {
    "thumbnail": "https://.../lightroom-6912-375x150.webp",
    "medium": "https://.../lightroom-6912-200x300.webp",
    "large": "https://.../lightroom-6912-683x1024.webp",
    "full": "https://.../lightroom-6912.webp"
  }
}
```

### Changements Frontend (React)

#### Problème
`PictureItem` s'attendait à un format complexe :
```typescript
src.thumbnail: {
  file: string;
  width: number;
  // ...
}
```

Mais l'API color search retourne maintenant :
```typescript
src.thumbnail: string  // URL directe
```

#### Solution

**1. Interface TypeScript mise à jour** (`interfaces.ts`) :
```typescript
src: {
  thumbnail?: string | { file: string; width: number; ... };
  medium?: string | { file: string; width: number; ... };
  large?: string | { file: string; width: number; ... };
  full?: string;
  // ... legacy fields
}
```

**2. Fonction `getThumbnailUrl` améliorée** (`PictureItem.tsx`) :
```typescript
const getThumbnailUrl = (picture: Picture): string | null => {
  // New format: direct URL string
  if (typeof picture.src?.thumbnail === 'string') {
    return picture.src.thumbnail;
  }
  
  // Old format: object with file property
  if (picture.src?.thumbnail && typeof picture.src.thumbnail === 'object') {
    const url = new URL(picture.baseUrl + picture.src.thumbnail.file);
    return url.toString();
  }
  
  // Fallback
  return picture.url || null;
}
```

### Tests

#### Test API
```bash
curl -X POST ".../pictures/by_dominant_color" -d '{"rgb": [227,227,227], "limit": 1}'
```

✅ Retourne `src.thumbnail` avec URL complète

#### Test Frontend
✅ PictureList affiche les miniatures des résultats de recherche par couleur
✅ Compatible avec l'ancien format (recherche par mots-clés)
✅ Fallback gracieux si thumbnail non disponible

### Déploiement

**Backend :**
```bash
scp src/routing/class.photo-library-route.php dreamhost-phototheque:.../src/routing/
```

**Frontend :**
```bash
npm run build  # Génère index-KXd_Sf2G.js
scp -r dist/* dreamhost-phototheque:.../public/
```

### Commits
- ✅ **PHP** : `9c9bfd2` - "feat: add image size variations to color search results"
- ✅ **React** : `79aa627` - "feat: support both URL string and object formats for image src"

### Résultat
Les résultats de recherche par couleur affichent maintenant des **miniatures optimisées** (375x150px au lieu de l'image complète), améliorant significativement les performances et l'UX.
