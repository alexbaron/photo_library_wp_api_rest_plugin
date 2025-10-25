# Photo Library WordPress API REST Plugin - Documentation des Endpoints

## 📋 Vue d'ensemble

Ce plugin WordPress expose une API REST pour gérer une photothèque. Tous les endpoints sont accessibles via le namespace `photo-library/v1`.

**Base URL**: `https://phototheque-wp.ddev.site/wp-json/photo-library/v1/`

## 🛠️ Endpoints disponibles

### 1. Test de l'API
**Route**: `/test`  
**Méthode**: `GET`  
**URL complète**: `https://phototheque-wp.ddev.site/wp-json/photo-library/v1/test`

**Description**: Route de test pour vérifier que l'API fonctionne correctement.

**Paramètres**: Aucun

**Réponse exemple**:
```json
{
  "message": "Bienvenue sur notre photo library API ",
  "status": "success"
}
```

**Status**: ✅ Testé et fonctionnel

---

### 2. Récupérer toutes les images
**Route**: `/pictures/all`  
**Méthode**: `GET`  
**URL complète**: `https://phototheque-wp.ddev.site/wp-json/photo-library/v1/pictures/all`

**Description**: Récupère toutes les images disponibles dans la photothèque.

**Paramètres**: Aucun

**Réponse**: Array JSON d'objets image

**Callback**: `PL_REST_DB::getPictures()`

**Status**: ⚠️ À tester

---

### 3. Récupérer une image aléatoire
**Route**: `/pictures/random/{id}`  
**Méthode**: `GET`  
**URL complète**: `https://phototheque-wp.ddev.site/wp-json/photo-library/v1/pictures/random/0`

**Description**: Récupère une image aléatoire. Passer `id = 0` pour obtenir une image vraiment aléatoire.

**Paramètres**:
- `id` (integer, requis): ID de l'image ou 0 pour une sélection aléatoire

**Callback**: `PL_REST_DB::getRandomPicture($id)`

**Status**: ⚠️ À tester

---

### 4. Récupérer une image par ID
**Route**: `/pictures/{id}`  
**Méthode**: `GET`  
**URL complète**: `https://phototheque-wp.ddev.site/wp-json/photo-library/v1/pictures/123`

**Description**: Récupère une image spécifique par son ID unique.

**Paramètres**:
- `id` (integer, requis): ID unique de l'image

**Callback**: `PL_REST_DB::getPicturesById($id)`

**Status**: ⚠️ À tester

---

### 5. Rechercher par mots-clés
**Route**: `/pictures/by_keywords`  
**Méthode**: `POST`  
**URL complète**: `https://phototheque-wp.ddev.site/wp-json/photo-library/v1/pictures/by_keywords`

**Description**: Récupère les images correspondant à des mots-clés spécifiés.

**Paramètres**:
- Body (JSON): Objet contenant les paramètres de recherche avec les mots-clés

**Exemple de requête**:
```bash
curl -X POST \
  -H "Content-Type: application/json" \
  -d '{"keywords": ["nature", "paysage"]}' \
  https://phototheque-wp.ddev.site/wp-json/photo-library/v1/pictures/by_keywords
```

**Callback**: `PL_REST_DB::getPicturesByKeywords($params)`

**Status**: ⚠️ À tester

---

### 6. Récupérer tous les mots-clés
**Route**: `/pictures/keywords`  
**Méthode**: `GET`  
**URL complète**: `https://phototheque-wp.ddev.site/wp-json/photo-library/v1/pictures/keywords`

**Description**: Récupère la liste de tous les mots-clés disponibles dans la photothèque.

**Paramètres**: Aucun

**Réponse exemple**:
```json
{
  "keywords": [
    "Réinitialiser",
    "nature",
    "paysage",
    "portrait",
    "urbain"
  ]
}
```

**Callback**: `PL_REST_DB::getKeywords()`

**Status**: ⚠️ À tester

---

## 🔒 Sécurité

Tous les endpoints sont actuellement configurés avec `permission_callback => '__return_true'`, ce qui les rend publics. 

**⚠️ Note de sécurité**: Pour un environnement de production, il est recommandé d'implémenter des contrôles d'accès appropriés.

## 🛠️ Configuration CORS

Le plugin inclut une gestion CORS pour les domaines autorisés :
- `phototheque.stephanewagner.com`
- `localhost:3000`
- `*` (tous les domaines)

## 📊 Dépendances de base de données

L'API utilise les classes suivantes pour l'accès aux données :
- `PL_REST_DB::getPictures()`
- `PL_REST_DB::getRandomPicture($id)`
- `PL_REST_DB::getPicturesById($id)`
- `PL_REST_DB::getPicturesByKeywords($params)`
- `PL_REST_DB::getKeywords()`

## 🔧 Développement

Pour tester les endpoints :

1. **Test simple** :
   ```bash
   curl https://phototheque-wp.ddev.site/wp-json/photo-library/v1/test
   ```

2. **Avec authentification** (si nécessaire) :
   ```bash
   curl -H "Authorization: Bearer YOUR_TOKEN" \
        https://phototheque-wp.ddev.site/wp-json/photo-library/v1/pictures/all
   ```

## 📝 Notes importantes

1. Le plugin modifie le préfixe REST de `/wp-json/` vers `/api/`, mais cette fonctionnalité semble avoir des problèmes dans l'environnement DDEV actuel.
2. Utiliser `/wp-json/` comme préfixe pour l'instant.
3. Tous les endpoints gèrent les erreurs avec des blocs try-catch et retournent des messages d'erreur appropriés.