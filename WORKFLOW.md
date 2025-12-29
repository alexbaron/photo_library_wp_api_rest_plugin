# Workflow de développement et déploiement

## Architecture

### Projets
- **React Source** : `/Users/alexandrebaron/Documents/dev/perso/phototheque`
- **Plugin WordPress** : `/Users/alexandrebaron/Documents/dev/perso/phototheque-wp/wp-content/plugins/photo_library_wp_api_rest_plugin`
- **Production** : `dreamhost-phototheque:/home/wagess/photographie.stephanewagner.com/wp-content/plugins/photo_library_wp_api_rest_plugin`

### URLs
- **Dev** : `https://phototheque-wp.ddev.site/phototeque-react`
- **Production** : `https://www.photographie.stephanewagner.com/phototeque-react/`

## Développement

### 1. Modifications du code React
```bash
cd /Users/alexandrebaron/Documents/dev/perso/phototheque

# Développement avec hot-reload
npm run dev

# Build local
npm run build
```

### 2. Déploiement local (WordPress)
```bash
cd /Users/alexandrebaron/Documents/dev/perso/phototheque

# Build + déploiement automatique vers WordPress local
./deploy-react-to-wp.sh
```

### 3. Test local
- Ouvrir : `https://phototheque-wp.ddev.site/phototeque-react`

## Déploiement Production

### Méthode automatisée (recommandée)
```bash
cd /Users/alexandrebaron/Documents/dev/perso/phototheque

# Build + déploiement automatique vers production
./deploy-to-production.sh
```

Le script effectue :
1. ✅ Git pull du code PHP sur le serveur
2. ✅ Build React en local avec config production
3. ✅ Nettoyage du dossier public distant
4. ✅ Upload des fichiers via SCP
5. ✅ Configuration des permissions

### Méthode manuelle
```bash
# 1. Build React en mode production
cd /Users/alexandrebaron/Documents/dev/perso/phototheque
npm run build

# 2. Upload vers le serveur
scp -r dist/* dreamhost-phototheque:./photographie.stephanewagner.com/wp-content/plugins/photo_library_wp_api_rest_plugin/public/

# 3. Update code PHP (si nécessaire)
ssh dreamhost-phototheque
cd photographie.stephanewagner.com/wp-content/plugins/photo_library_wp_api_rest_plugin
git pull origin main
```

## Configuration importante

### React (.env.production)
```env
VITE_BASE_URL=https://www.photographie.stephanewagner.com/wp-json/photo-library/v1
VITE_WP_URL=https://www.photographie.stephanewagner.com
```

⚠️ **Important** : Toujours utiliser `www.` pour éviter les redirections 301 et problèmes CORS

### Vite (vite.config.ts)
```typescript
export default defineConfig({
  base: './', // Chemins relatifs pour déploiement en sous-répertoire
  build: {
    outDir: 'dist',
    sourcemap: true
  }
})
```

## Versionning

### Plugin WordPress
```bash
cd /Users/alexandrebaron/Documents/dev/perso/phototheque-wp/wp-content/plugins/photo_library_wp_api_rest_plugin

# Commit et push des modifications PHP
git add .
git commit -m "fix: description"
git push origin main
```

### Projet React
```bash
cd /Users/alexandrebaron/Documents/dev/perso/phototheque

# Commit des modifications React
git add .
git commit -m "feat: description"
git push origin 20251212-search-by-color
```

## Troubleshooting

### Erreur CORS
- ✅ Vérifier que l'URL utilise `www.photographie.stephanewagner.com`
- ✅ Vérifier la config dans `.env.production`
- ✅ Vider le cache du navigateur (Ctrl+Shift+R)

### Assets 404
- ✅ Vérifier que `base: './'` est dans `vite.config.ts`
- ✅ Vérifier que `index.html` utilise des chemins relatifs (`./assets/`)
- ✅ Rebuilder avec `npm run build`

### Cache serveur
- Le serveur a un cache de 30 jours sur les assets
- Si changement d'URL, changer le nom du fichier JS/CSS
- Exemple : `index-CBt_eoBb.js` → `index-CBt_eoBb-v2.js`

## Références

- **Documentation fixes** : `DEPLOYMENT_FIX.md`
- **API Endpoints** : `https://www.photographie.stephanewagner.com/wp-json/photo-library/v1/`
- **SSH Config** : `~/.ssh/config` (alias `dreamhost-phototheque`)
