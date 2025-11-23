# API Examples

## Health Check

### Request
```
GET /wp-json/photo-library/v1/test
```

### Response
```json
{
  "message": "PhotoLibrary REST API is working!"
}
```

## Get Keywords

### Request
```
GET /wp-json/photo-library/v1/pictures/keywords
```

### Response
```json
{
  "message": "get_keywords called",
  "data": {
    "123": "beach",
    "124": "sunset",
    "125": "nature"
  },
  "source": "wplr_sync"
}
```

## Search by Keywords

### Request
```
POST /wp-json/photo-library/v1/pictures/by_keywords
Content-Type: application/json

{
  "search": ["beach", "sunset"]
}
```

### Response
```json
{
  "keywords_searched": ["beach", "sunset"],
  "keywords_found": ["beach", "sunset"],
  "total_found": 15,
  "media": [
    {
      "id": 123,
      "title": "Beach Sunset",
      "url": "https://site.com/wp-content/uploads/beach.jpg",
      "path": "2024/01/beach.jpg",
      "tags": ["beach", "sunset", "ocean"]
    }
  ]
}
```

## Get Hierarchy

### Request
```
GET /wp-json/photo-library/v1/pictures/hierarchy
```

### Response
```json
{
  "message": "get_hierarchy called",
  "data": [
    {
      "id": 1,
      "name": "Travel",
      "type": "folder",
      "children": [
        {
          "id": 2,
          "name": "Europe",
          "type": "collection",
          "children": []
        }
      ]
    }
  ]
}
```

## JavaScript Examples

### Basic Fetch
```javascript
// Get keywords
const response = await fetch('/wp-json/photo-library/v1/pictures/keywords');
const data = await response.json();
console.log(data.data);

// Search photos
const searchResponse = await fetch('/wp-json/photo-library/v1/pictures/by_keywords', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ search: ['beach'] })
});
const searchData = await searchResponse.json();
console.log(searchData.media);
```

### PHP Examples
```php
// Get keywords
$response = wp_remote_get(home_url('/wp-json/photo-library/v1/pictures/keywords'));
$data = json_decode(wp_remote_retrieve_body($response), true);
$keywords = $data['data'];

// Search photos
$search_response = wp_remote_post(home_url('/wp-json/photo-library/v1/pictures/by_keywords'), [
    'body' => json_encode(['search' => ['beach']]),
    'headers' => ['Content-Type' => 'application/json']
]);
$search_data = json_decode(wp_remote_retrieve_body($search_response), true);
$photos = $search_data['media'];
```
