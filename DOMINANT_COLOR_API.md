# Endpoint de recherche par couleur dominante

## URL
`POST /wp-json/photo-library/v1/pictures/by_dominant_color`

## Description
Recherche des photos ayant une couleur dominante similaire à la couleur cliquée. Le résultat est optimisé pour l'affichage UI :
- La première photo est retournée séparément pour être affichée en arrière-plan
- Les autres photos sont retournées dans un tableau pour être affichées en miniatures

## Requête

### Méthode
`POST`

### Headers
```
Content-Type: application/json
```

### Body JSON
```json
{
  "rgb": [120, 150, 200],
  "limit": 10
}
```

#### Paramètres
- **rgb** (requis): Tableau de 3 entiers [R, G, B] dans la plage 0-255
- **limit** (optionnel): Nombre de résultats à retourner (défaut: 10, minimum: 1)

## Réponse réussie (200)

```json
{
  "query_color": [120, 150, 200],
  "total_count": 10,
  "background_photo": {
    "id": 123,
    "title": "Photo principale",
    "url": "https://example.com/photo.jpg",
    "thumbnail": "https://example.com/photo-thumb.jpg",
    "color_score": 0.98,
    "color_match": [118, 152, 198],
    ...
  },
  "thumbnail_photos": [
    {
      "id": 124,
      "title": "Photo 2",
      "url": "https://example.com/photo2.jpg",
      "thumbnail": "https://example.com/photo2-thumb.jpg",
      "color_score": 0.95,
      "color_match": [125, 145, 205],
      ...
    },
    ...
  ]
}
```

### Champs de réponse
- **query_color**: La couleur RGB recherchée
- **total_count**: Nombre total de photos retournées
- **background_photo**: Première photo (meilleure correspondance) à afficher en arrière-plan
  - Contient tous les champs de la photo + `color_score` et `color_match`
  - `null` si aucun résultat
- **thumbnail_photos**: Tableau des autres photos à afficher en miniatures
  - Chaque photo contient les mêmes champs que background_photo

## Erreurs

### 400 - Requête invalide
```json
{
  "error": "Invalid RGB values. Expected array of 3 numbers [R, G, B] in 0-255 range.",
  "example": {
    "rgb": [120, 150, 200],
    "limit": 10
  }
}
```

### 500 - Erreur serveur
```json
{
  "error": "Dominant color search failed",
  "message": "Pinecone API error: ..."
}
```

## Exemples d'utilisation

### JavaScript (Fetch API)
```javascript
const searchByColor = async (rgb, limit = 10) => {
  const response = await fetch('/wp-json/photo-library/v1/pictures/by_dominant_color', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ rgb, limit })
  });
  
  const data = await response.json();
  
  // Afficher la photo principale en arrière-plan
  if (data.background_photo) {
    document.body.style.backgroundImage = `url(${data.background_photo.url})`;
  }
  
  // Afficher les miniatures
  data.thumbnail_photos.forEach(photo => {
    const img = document.createElement('img');
    img.src = photo.thumbnail;
    img.alt = photo.title;
    document.getElementById('thumbnails').appendChild(img);
  });
};

// Rechercher des photos avec une couleur bleue claire
searchByColor([120, 150, 200], 15);
```

### cURL
```bash
curl -X POST \
  https://example.com/wp-json/photo-library/v1/pictures/by_dominant_color \
  -H 'Content-Type: application/json' \
  -d '{
    "rgb": [120, 150, 200],
    "limit": 10
  }'
```

### React Example
```jsx
const ColorSearch = ({ clickedColor }) => {
  const [backgroundPhoto, setBackgroundPhoto] = useState(null);
  const [thumbnails, setThumbnails] = useState([]);
  
  useEffect(() => {
    const searchPhotos = async () => {
      const response = await fetch('/wp-json/photo-library/v1/pictures/by_dominant_color', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
          rgb: clickedColor,
          limit: 12 
        })
      });
      
      const data = await response.json();
      setBackgroundPhoto(data.background_photo);
      setThumbnails(data.thumbnail_photos);
    };
    
    if (clickedColor) {
      searchPhotos();
    }
  }, [clickedColor]);
  
  return (
    <div>
      {backgroundPhoto && (
        <div 
          className="background-image"
          style={{ backgroundImage: `url(${backgroundPhoto.url})` }}
        />
      )}
      <div className="thumbnails-grid">
        {thumbnails.map(photo => (
          <img 
            key={photo.id}
            src={photo.thumbnail}
            alt={photo.title}
          />
        ))}
      </div>
    </div>
  );
};
```

## Notes techniques

- Utilise **Pinecone** pour la recherche vectorielle de similarité de couleurs
- Les valeurs RGB sont normalisées en 0-1 pour un calcul de distance optimal
- Le score de similarité (0-1) est inclus dans chaque résultat
- L'ordre des résultats est du plus similaire au moins similaire
- La variable d'environnement `PINECONE_API_KEY` doit être configurée
