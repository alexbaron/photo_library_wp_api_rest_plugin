# 🔑 Configuration Pinecone - Production

## 📋 Problème
```
PINECONE_API_KEY environment variable not set
```

## ✅ Solutions pour la Production

### Option 1 : Fichier .env sur le serveur (Recommandé)

#### Étape 1 : Créer le fichier .env sur le serveur
```bash
ssh wagess@pdx1-shared-a1-34.dreamhost.com
cd /home/wagess/photographie.stephanewagner.com/wp-content/plugins/photo_library_wp_api_rest_plugin
nano .env
```

#### Étape 2 : Ajouter le contenu
```bash
# Environment
ENV=production

# Pinecone Configuration
PINECONE_API_KEY=pcsk_6GVQ1b_LSmxvJ9bEjhMgWPagpDvJCgtNEMmLyMjG7a78NkrppJKoA8vDRNtqfTKmp3LGYH
PINECONE_INDEX_NAME=phototheque-color-search
PINECONE_NAMESPACE=photos

# Debug (désactiver en production)
DEBUG_MODE=false
LOG_LEVEL=error
```

#### Étape 3 : Sauvegarder
- Ctrl+O (pour save)
- Enter
- Ctrl+X (pour quit)

#### Étape 4 : Permissions
```bash
chmod 600 .env
```

### Option 2 : Variables d'environnement PHP

Ajouter dans le fichier principal du plugin :
`photo_library_rest_api.php`

```php
// Configuration Pinecone pour production
if (!getenv('PINECONE_API_KEY')) {
    putenv('PINECONE_API_KEY=pcsk_6GVQ1b_LSmxvJ9bEjhMgWPagpDvJCgtNEMmLyMjG7a78NkrppJKoA8vDRNtqfTKmp3LGYH');
    putenv('PINECONE_INDEX_NAME=phototheque-color-search');
    putenv('PINECONE_NAMESPACE=photos');
}
```

### Option 3 : wp-config.php (Plus sécurisé)

Éditer `/home/wagess/photographie.stephanewagner.com/wp-config.php`

```bash
ssh wagess@pdx1-shared-a1-34.dreamhost.com
nano /home/wagess/photographie.stephanewagner.com/wp-config.php
```

Ajouter AVANT `/* That's all, stop editing! */` :

```php
/** Pinecone Configuration */
define('PINECONE_API_KEY', 'pcsk_6GVQ1b_LSmxvJ9bEjhMgWPagpDvJCgtNEMmLyMjG7a78NkrppJKoA8vDRNtqfTKmp3LGYH');
define('PINECONE_INDEX_NAME', 'phototheque-color-search');
define('PINECONE_NAMESPACE', 'photos');
```

Puis modifier `class.photo-library-pinecone.php` pour utiliser les constantes :

```php
public function __construct() {
    $this->api_key = defined('PINECONE_API_KEY') 
        ? PINECONE_API_KEY 
        : getenv('PINECONE_API_KEY');
    
    if (!$this->api_key) {
        throw new Exception('PINECONE_API_KEY not configured');
    }
    
    $this->index_name = defined('PINECONE_INDEX_NAME')
        ? PINECONE_INDEX_NAME
        : 'phototheque-color-search';
        
    $this->index_host = $this->get_index_host();
}
```

## 🎯 Méthode Recommandée : wp-config.php

**Avantages** :
✅ Plus sécurisé (en dehors du dossier du plugin)
✅ Pas de risque d'écraser le fichier lors du déploiement
✅ WordPress standard
✅ Lecture des constantes très rapide

## 📝 Script de Configuration Automatique

Créer un fichier `configure-production.sh` :

```bash
#!/bin/bash

# Configuration de la production
REMOTE_USER="wagess"
REMOTE_HOST="pdx1-shared-a1-34.dreamhost.com"
WP_CONFIG="/home/wagess/photographie.stephanewagner.com/wp-config.php"

echo "🔧 Configuration de Pinecone en production..."

# Vérifier si déjà configuré
CHECK=$(ssh ${REMOTE_USER}@${REMOTE_HOST} "grep -q 'PINECONE_API_KEY' ${WP_CONFIG} && echo 'EXISTS' || echo 'NOT_EXISTS'")

if [ "$CHECK" == "EXISTS" ]; then
    echo "✅ Pinecone est déjà configuré dans wp-config.php"
    exit 0
fi

# Ajouter la configuration
ssh ${REMOTE_USER}@${REMOTE_HOST} << 'ENDSSH'
cd /home/wagess/photographie.stephanewagner.com

# Backup
cp wp-config.php wp-config.php.backup

# Trouver la ligne "stop editing"
LINE_NUM=$(grep -n "That's all, stop editing" wp-config.php | cut -d: -f1)

# Insérer la configuration avant cette ligne
sed -i "${LINE_NUM}i /** Pinecone Configuration */\ndefine('PINECONE_API_KEY', 'pcsk_6GVQ1b_LSmxvJ9bEjhMgWPagpDvJCgtNEMmLyMjG7a78NkrppJKoA8vDRNtqfTKmp3LGYH');\ndefine('PINECONE_INDEX_NAME', 'phototheque-color-search');\ndefine('PINECONE_NAMESPACE', 'photos');\n" wp-config.php

echo "Configuration ajoutée à wp-config.php"
ENDSSH

echo "✅ Configuration Pinecone ajoutée avec succès!"
echo "🔄 Backup créé : wp-config.php.backup"
```

Rendre exécutable et lancer :
```bash
chmod +x configure-production.sh
./configure-production.sh
```

## 🧪 Vérification

### Tester sur le serveur
```bash
ssh wagess@pdx1-shared-a1-34.dreamhost.com
cd /home/wagess/photographie.stephanewagner.com/wp-content/plugins/photo_library_wp_api_rest_plugin
php -r "var_dump(getenv('PINECONE_API_KEY'));"
```

### Tester via l'API WordPress
Créer un endpoint de test temporaire ou vérifier les logs :

```bash
tail -f /home/wagess/photographie.stephanewagner.com/wp-content/debug.log
```

### Tester dans l'application
1. Ouvrir https://photographie.stephanewagner.com
2. Cliquer sur une couleur
3. Vérifier que la recherche fonctionne
4. Pas d'erreur "PINECONE_API_KEY not set"

## 🔐 Sécurité

### Permissions du fichier .env
```bash
chmod 600 .env
```

### Fichiers à protéger
Ajouter dans `.htaccess` du plugin :

```apache
<Files ".env">
    Order allow,deny
    Deny from all
</Files>
```

### Ne jamais commit
Ajouter au `.gitignore` :
```
.env
wp-config.php
```

## 📊 Comparaison des Méthodes

| Méthode | Sécurité | Facilité | Performance | Recommandé |
|---------|----------|----------|-------------|------------|
| `.env` fichier | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | Production OK |
| `wp-config.php` | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ✅ Meilleur |
| Code PHP | ⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ | Dev seulement |
| Variables serveur | ⭐⭐⭐⭐ | ⭐⭐ | ⭐⭐⭐⭐⭐ | Avancé |

## 🆘 Dépannage

### Erreur : "PINECONE_API_KEY not set"
1. Vérifier que le fichier `.env` existe
2. Vérifier les permissions (chmod 600)
3. Vérifier que PHP peut lire le fichier
4. Vérifier les logs WordPress

### L'API key ne fonctionne pas
1. Vérifier sur https://console.pinecone.io/
2. Régénérer une nouvelle clé si nécessaire
3. Vérifier que l'index existe
4. Vérifier le nom de l'index

### Variables d'environnement non chargées
Sur Dreamhost, vérifier que le plugin charge bien le `.env` :

```php
// Dans photo_library_rest_api.php
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
```

## 🎉 Checklist de Configuration

- [ ] SSH configuré
- [ ] Connexion au serveur OK
- [ ] Fichier wp-config.php accessible
- [ ] Backup de wp-config.php créé
- [ ] Configuration Pinecone ajoutée
- [ ] Permissions vérifiées (600)
- [ ] Test de l'API réussi
- [ ] Application fonctionne
- [ ] Recherche par couleur opérationnelle

## 📞 Support

Si problème persistant :
1. Vérifier les logs : `/wp-content/debug.log`
2. Activer WP_DEBUG dans wp-config.php
3. Contacter le support Dreamhost
4. Vérifier la console Pinecone
