# Guide de Test - Recherche par Couleur avec Pinecone

## 🎯 Endpoint à tester

**URL:** `POST /wp-json/photo-library/v1/pictures/by_color`

## 📋 Prérequis

### 1. Configuration Pinecone

Créer un fichier `.env` dans `src/pinecone/` avec vos vraies valeurs:

```bash
# Copier le fichier exemple
cp src/pinecone/.env.example src/pinecone/.env

# Éditer avec vos vraies valeurs Pinecone
nano src/pinecone/.env
```

Valeurs nécessaires:
- `PINECONE_API_KEY`: Votre clé API Pinecone (obtenir sur console.pinecone.io)
- `PINECONE_HOST`: L'URL de votre index (format: `your-index-xxxxx.svc.region.pinecone.io`)
- `PINECONE_INDEX_NAME`: Nom de votre index
- `PINECONE_ENVIRONMENT`: Région AWS (ex: `us-east-1-aws`)

### 2. Créer l'index Pinecone

Sur [console.pinecone.io](https://console.pinecone.io):
1. Créer un nouvel index
2. **Dimensions:** 3 (pour RGB)
3. **Metric:** cosine
4. **Nom:** `phototheque-color-search` (ou autre)

### 3. Indexer des photos

Synchroniser les couleurs dominantes vers Pinecone:

```bash
# Via le script de synchronisation
php src/pinecone/sync-colors-to-pinecone.php
```

Ou via WP-CLI:
```bash
wp eval-file src/pinecone/sync-colors-to-pinecone.php
```

## 🧪 Tests avec curl

### Test 1: Vérifier la configuration

```bash
curl -s "https://phototheque-wp.ddev.site/wp-json/photo-library/v1/config" | jq
```

### Test 2: Recherche basique (couleur bleue)

```bash
curl -X POST "https://phototheque-wp.ddev.site/wp-json/photo-library/v1/pictures/by_color" \
  -H "Content-Type: application/json" \
  -d '{
    "rgb": [120, 150, 200],
    "top_k": 5
  }' | jq
```

### Test 3: Recherche rouge

```bash
curl -X POST "https://phototheque-wp.ddev.site/wp-json/photo-library/v1/pictures/by_color" \
  -H "Content-Type: application/json" \
  -d '{
    "rgb": [255, 0, 0],
    "top_k": 10
  }' | jq
```

### Test 4: Recherche verte avec filtres

```bash
curl -X POST "https://phototheque-wp.ddev.site/wp-json/photo-library/v1/pictures/by_color" \
  -H "Content-Type: application/json" \
  -d '{
    "rgb": [0, 255, 0],
    "top_k": 15,
    "filter": {
      "category": "nature"
    }
  }' | jq
```

### Test 5: Recherche locale forcée (sans Pinecone)

```bash
curl -X POST "https://phototheque-wp.ddev.site/wp-json/photo-library/v1/pictures/by_color" \
  -H "Content-Type: application/json" \
  -d '{
    "rgb": [120, 150, 200],
    "top_k": 5,
    "force_local": true
  }' | jq
```

## 🔍 Test direct de l'API Pinecone

### Obtenir les statistiques de l'index

```bash
PINECONE_API_KEY="votre-cle-api"
PINECONE_HOST="votre-index-xxxxx.svc.region.pinecone.io"

curl -X POST "https://${PINECONE_HOST}/describe_index_stats" \
  -H "Api-Key: ${PINECONE_API_KEY}" \
  -H "Content-Type: application/json" | jq
```

Réponse attendue:
```json
{
  "namespaces": {
    "photos": {
      "vectorCount": 150
    }
  },
  "dimension": 3,
  "indexFullness": 0.0,
  "totalVectorCount": 150
}
```

### Recherche directe par vecteur

```bash
# Couleur bleue normalisée: [120/255, 150/255, 200/255] = [0.47, 0.59, 0.78]
curl -X POST "https://${PINECONE_HOST}/query" \
  -H "Api-Key: ${PINECONE_API_KEY}" \
  -H "Content-Type: application/json" \
  -d '{
    "vector": [0.47, 0.59, 0.78],
    "topK": 5,
    "includeMetadata": true,
    "namespace": "photos"
  }' | jq
```

Réponse attendue:
```json
{
  "matches": [
    {
      "id": "12345",
      "score": 0.95,
      "values": [],
      "metadata": {
        "photo_id": 12345,
        "rgb": [118, 152, 198],
        "uploaded_at": "2024-11-23 10:30:00"
      }
    }
  ],
  "namespace": "photos"
}
```

## 📊 Configuration Postman

### Créer une collection Postman

1. **Nom:** PhotoLibrary Color Search
2. **Base URL:** `{{base_url}}` = `https://phototheque-wp.ddev.site/wp-json/photo-library/v1`

### Requête: Search by Color

- **Method:** POST
- **URL:** `{{base_url}}/pictures/by_color`
- **Headers:**
  ```
  Content-Type: application/json
  ```
- **Body (raw JSON):**
  ```json
  {
    "rgb": [120, 150, 200],
    "top_k": 10
  }
  ```

### Tests Postman (onglet Tests)

```javascript
pm.test("Status code is 200", function () {
    pm.response.to.have.status(200);
});

pm.test("Response has query_color", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData).to.have.property('query_color');
});

pm.test("Response has results", function () {
    var jsonData = pm.response.json();
    pm.expect(jsonData).to.have.property('results_count');
    pm.expect(jsonData).to.have.property('pictures');
});
```

## 🐛 Dépannage

### Erreur: "PINECONE_API_KEY environment variable is required"

Solution: Créer le fichier `.env` dans `src/pinecone/`

### Erreur: "No similar colors found"

Causes possibles:
1. Aucune photo indexée dans Pinecone → Lancer le script de sync
2. Index vide → Vérifier avec `describe_index_stats`
3. Mauvais namespace → Vérifier `PINECONE_NAMESPACE=photos`

### Erreur: "Could not resolve host"

Solution: Vérifier l'URL de votre index Pinecone (obtenir depuis la console)

### Erreur: "Connection timeout"

Causes possibles:
1. Clé API invalide
2. Firewall bloquant les connexions sortantes
3. Index Pinecone supprimé ou suspendu

## 📝 Réponses attendues

### Succès avec résultats

```json
{
  "query_color": [120, 150, 200],
  "results_count": 8,
  "pictures": [
    {
      "id": 12345,
      "title": "Blue Sky Sunset",
      "src": {
        "thumbnail": [...],
        "medium": [...],
        "large": [...],
        "full": [...]
      },
      "keywords": ["sky", "blue", "sunset"],
      "color_score": 0.953,
      "color_match": [118, 152, 198],
      "metadata": {...}
    }
  ],
  "search_method": "pinecone",
  "total_matches": 8,
  "timestamp": "2024-11-23 14:30:00"
}
```

### Succès sans résultats

```json
{
  "query_color": [120, 150, 200],
  "results_count": 0,
  "pictures": [],
  "message": "No similar colors found"
}
```

### Erreur de paramètres

```json
{
  "error": "Invalid RGB format. Expected array [r, g, b]"
}
```

## 🚀 Scripts utiles

### Script de test complet

Créer `test-color-search.sh`:

```bash
#!/bin/bash

BASE_URL="https://phototheque-wp.ddev.site/wp-json/photo-library/v1"

echo "=== Test Color Search Endpoint ==="
echo ""

colors=(
  "[120,150,200]" "Blue Sky"
  "[255,0,0]" "Red"
  "[0,255,0]" "Green"
  "[255,255,0]" "Yellow"
  "[128,128,128]" "Gray"
)

for ((i=0; i<${#colors[@]}; i+=2)); do
  rgb="${colors[i]}"
  name="${colors[i+1]}"
  
  echo "Testing: $name $rgb"
  curl -s -X POST "$BASE_URL/pictures/by_color" \
    -H "Content-Type: application/json" \
    -d "{\"rgb\": $rgb, \"top_k\": 3}" \
    | jq -r '.results_count // "Error"'
  echo ""
done
```

Exécuter:
```bash
chmod +x test-color-search.sh
./test-color-search.sh
```
