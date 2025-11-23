# Pinecone Color Search Integration

## Overview
Integration de la recherche par similarité de couleurs RGB avec Pinecone dans le plugin PhotoLibrary WordPress.

## Configuration

### 1. Variable d'environnement
Ajouter dans `phototheque-wp/.ddev/config.yaml` ou dans WordPress `wp-config.php` :

```php
// In wp-config.php
putenv('PINECONE_API_KEY=your-pinecone-api-key-here');
```

Ou dans DDEV :
```yaml
# .ddev/config.yaml
web_environment:
  - PINECONE_API_KEY=your-pinecone-api-key-here
```

### 2. Index Pinecone
Index déjà créé : `phototheque-color-search`
- Dimensions : 3 (RGB)
- Métrique : euclidean
- Région : AWS us-east-1

## Nouveaux fichiers

### 1. `src/class.photo-library-pinecone.php`
Classe `PL_Color_Search_Index` pour interagir avec Pinecone :
- `upsert_photo_color()` - Ajouter une photo
- `batch_upsert_photos()` - Batch insert
- `search_by_color()` - Rechercher par couleur
- `get_index_stats()` - Statistiques

### 2. Nouveau endpoint REST API

**POST** `/wp-json/photo-library/v1/pictures/by_color`

**Request Body:**
```json
{
  "rgb": [120, 150, 200],
  "top_k": 10,
  "filter": {
    "category": "landscape"
  }
}
```

**Response:**
```json
{
  "query_color": [120, 150, 200],
  "results_count": 10,
  "pictures": [
    {
      "id": 123,
      "title": "Photo Title",
      "src": {...},
      "keywords": [...],
      "color_score": 0.95,
      "color_match": [118, 152, 198]
    }
  ]
}
```

## Utilisation

### 1. Peupler l'index Pinecone

**Option A : Script automatisé (recommandé)**

```bash
cd phototheque-wp/wp-content/plugins/photo_library_wp_api_rest_plugin/
export PINECONE_API_KEY="your-api-key"
./quick-sync-pinecone.sh
```

Le script propose 3 options :
1. Synchroniser palettes existantes → Pinecone
2. Extraire palettes manquantes + synchroniser
3. Extraire palettes uniquement (sans sync)

**Option B : Script WP-CLI direct**

```bash
cd phototheque-wp/
export PINECONE_API_KEY="your-key"
ddev exec "wp eval-file wp-content/plugins/photo_library_wp_api_rest_plugin/sync-colors-to-pinecone.php"
```

Le script :
- ✅ Récupère toutes les photos avec palette (`_pl_palette`)
- ✅ Extrait la couleur dominante (premier RGB de la palette)
- ✅ Upload par batch (1000 max) vers Pinecone
- ✅ Affiche statistiques et résultats
- ✅ Peut extraire palettes manquantes avant sync

### 2. Tester l'endpoint

```bash
curl -X POST https://phototheque-wp.ddev.site/wp-json/photo-library/v1/pictures/by_color \
  -H "Content-Type: application/json" \
  -d '{"rgb": [120, 150, 200], "top_k": 5}'
```

### 3. Intégration Frontend (React)

Dans `src/lib/picture-api.ts` :

```typescript
async searchByColor(rgb: number[], topK: number = 10): Promise<Picture[]> {
  const response = await fetch(`${this.baseUrl}/pictures/by_color`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ rgb, top_k: topK })
  });
  
  const data = await response.json();
  return data.pictures.map(this.transformToPicture);
}
```

## Prochaines étapes

1. **Extraire couleur moyenne** : Si pas déjà fait, ajouter extraction RGB moyenne dans WordPress
2. **Synchronisation auto** : Hook WordPress pour sync automatique quand photo uploadée
3. **UI Color Picker** : Ajouter sélecteur de couleur dans l'interface React
4. **Batch sync script** : Script WP-CLI pour synchroniser toutes les photos existantes

## Dépannage

### Erreur : PINECONE_API_KEY not set
- Vérifier que la variable d'environnement est définie
- Redémarrer DDEV : `ddev restart`

### Erreur : Failed to get index info
- Vérifier que l'index existe : `pc index describe -n phototheque-color-search`
- Vérifier la clé API : `pc index list`

### Aucun résultat
- Vérifier que des photos sont indexées : `GET /cache/stats`
- Tester avec une couleur connue dans l'index

## Architecture

```
React Frontend
    ↓ POST /pictures/by_color
WordPress REST API (PhotoLibrary_Route)
    ↓
PL_Color_Search_Index
    ↓ HTTP API
Pinecone Vector Database (phototheque-color-search)
    ↓ Returns photo IDs + scores
WordPress Media Library (get full photo data)
    ↓
React Frontend (display results)
```
