# PhotoLibrary REST API - Developer Guide

## 📚 Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [API Endpoints](#api-endpoints)
4. [Integration Examples](#integration-examples)
5. [WP/LR Sync Integration](#wplr-sync-integration)
6. [Error Handling](#error-handling)
7. [Performance Considerations](#performance-considerations)
8. [Development Setup](#development-setup)

## 🎯 Overview

The PhotoLibrary REST API provides a comprehensive interface for managing and accessing photo libraries in WordPress with optional integration with WP/LR Sync for advanced Lightroom synchronization.

### Key Features

- ✅ RESTful API following WordPress standards
- ✅ WP/LR Sync integration with graceful fallbacks
- ✅ Hierarchical keyword/tag management
- ✅ Advanced search capabilities
- ✅ Metadata extraction and management
- ✅ CORS support for external applications

## 🏗️ Architecture

### Class Structure

```
PhotoLibrary_Route (WP_REST_Controller)
├── Constructor & Initialization
├── WP/LR Sync Integration
├── Route Registration
├── Endpoint Handlers
└── Utility Methods

PL_REST_DB
├── Database Operations
├── Media Retrieval
├── Keyword Management
└── WP/LR Sync Queries
```

### Dependencies

- **WordPress REST API** (Core)
- **WP/LR Sync Plugin** (Optional)
- **Custom Database Schema** (wp_lrsync_* tables)

## 🛠️ API Endpoints

### Base URL
```
https://your-site.com/wp-json/photo-library/v1/
```

### 1. Health Check
```http
GET /test
```

**Response:**
```json
{
  "message": "PhotoLibrary REST API is working!"
}
```

### 2. Get All Keywords (Flat)
```http
GET /pictures/keywords
```

**Response:**
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

### 3. Get Keyword Hierarchy
```http
GET /pictures/hierarchy
```

**Response:**
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

### 4. Search by Keywords
```http
POST /pictures/by_keywords
Content-Type: application/json

{
  "search": ["beach", "sunset"]
}
```

**Response:**
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

### 5. Get All Pictures
```http
GET /pictures/all
```

### 6. Get Picture by ID
```http
GET /pictures/{id}
```

### 7. Get Random Pictures
```http
GET /pictures/random/{count}
```

## 💻 Integration Examples

### JavaScript/Fetch API

```javascript
// Get all keywords
async function getKeywords() {
  const response = await fetch('/wp-json/photo-library/v1/pictures/keywords');
  const data = await response.json();
  return data.data;
}

// Search by keywords
async function searchByKeywords(keywords) {
  const response = await fetch('/wp-json/photo-library/v1/pictures/by_keywords', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ search: keywords })
  });
  return await response.json();
}

// Usage
const keywords = await getKeywords();
const searchResults = await searchByKeywords(['beach', 'sunset']);
```

### PHP Client

```php
// WordPress environment
function get_photolibrary_keywords() {
    $response = wp_remote_get(home_url('/wp-json/photo-library/v1/pictures/keywords'));

    if (is_wp_error($response)) {
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    return $data['data'] ?? [];
}

// Search photos
function search_photos_by_keywords($keywords) {
    $response = wp_remote_post(home_url('/wp-json/photo-library/v1/pictures/by_keywords'), [
        'body' => json_encode(['search' => $keywords]),
        'headers' => ['Content-Type' => 'application/json']
    ]);

    if (is_wp_error($response)) {
        return false;
    }

    return json_decode(wp_remote_retrieve_body($response), true);
}
```

### React Hook

```jsx
import { useState, useEffect } from 'react';

function usePhotoLibrary() {
  const [keywords, setKeywords] = useState({});
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetch('/wp-json/photo-library/v1/pictures/keywords')
      .then(response => response.json())
      .then(data => {
        setKeywords(data.data);
        setLoading(false);
      });
  }, []);

  const searchPhotos = async (keywordList) => {
    const response = await fetch('/wp-json/photo-library/v1/pictures/by_keywords', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ search: keywordList })
    });
    return response.json();
  };

  return { keywords, loading, searchPhotos };
}

// Usage in component
function PhotoGallery() {
  const { keywords, searchPhotos } = usePhotoLibrary();

  const handleSearch = async () => {
    const results = await searchPhotos(['beach', 'sunset']);
    console.log(results.media);
  };

  return (
    <div>
      <button onClick={handleSearch}>Search Beach Sunsets</button>
    </div>
  );
}
```

## 🔗 WP/LR Sync Integration

### Integration Benefits

When WP/LR Sync is available, the API provides:

- **Hierarchical Keywords**: Full Lightroom keyword hierarchy
- **Collection Management**: Access to Lightroom collections and folders
- **Automatic Synchronization**: Real-time updates from Lightroom
- **Advanced Metadata**: Rich EXIF and Lightroom metadata

### Fallback Behavior

When WP/LR Sync is not available:

- **Graceful Degradation**: API continues to function
- **Database Queries**: Falls back to direct WordPress database queries
- **Basic Functionality**: Core features remain accessible
- **Error Logging**: Issues logged for debugging

### Checking Integration Status

```php
// In PhotoLibrary_Route class
if ($this->is_wplr_available()) {
    // Use WP/LR Sync features
    $hierarchy = $this->wplrSync->get_keywords_hierarchy();
} else {
    // Use fallback methods
    $keywords = PL_REST_DB::getKeywords();
}
```

## ⚠️ Error Handling

### Error Response Format

```json
{
  "error": "Error description",
  "code": "error_code",
  "data": {
    "status": 400
  }
}
```

### Common Error Scenarios

1. **WP/LR Sync Unavailable**
   ```json
   {
     "error": "WP/LR Sync keywords unavailable",
     "source": "fallback_db"
   }
   ```

2. **Invalid Parameters**
   ```json
   {
     "error": "No keywords provided",
     "code": "invalid_params"
   }
   ```

3. **Database Error**
   ```json
   {
     "error": "Database connection failed",
     "code": "db_error"
   }
   ```

### Error Handling Best Practices

```javascript
async function safeApiCall(url, options = {}) {
  try {
    const response = await fetch(url, options);

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`);
    }

    const data = await response.json();

    if (data.error) {
      throw new Error(data.error);
    }

    return data;
  } catch (error) {
    console.error('PhotoLibrary API Error:', error);
    throw error;
  }
}
```

## ⚡ Performance Considerations

### Caching Strategies

1. **Client-Side Caching**
   ```javascript
   // Cache keywords for 1 hour
   const CACHE_DURATION = 3600000;
   let keywordCache = {
     data: null,
     timestamp: 0
   };

   async function getCachedKeywords() {
     const now = Date.now();
     if (keywordCache.data && (now - keywordCache.timestamp) < CACHE_DURATION) {
       return keywordCache.data;
     }

     const keywords = await getKeywords();
     keywordCache = { data: keywords, timestamp: now };
     return keywords;
   }
   ```

2. **WordPress Object Cache**
   ```php
   function get_cached_photolibrary_keywords() {
       $cache_key = 'photolibrary_keywords';
       $keywords = wp_cache_get($cache_key);

       if (false === $keywords) {
           $keywords = PL_REST_DB::getKeywords();
           wp_cache_set($cache_key, $keywords, '', 3600); // 1 hour
       }

       return $keywords;
   }
   ```

### Database Optimization

- Use proper indexes on lrsync_meta table
- Limit result sets with pagination
- Use prepared statements for security

### Request Optimization

- Batch multiple API calls when possible
- Use appropriate HTTP caching headers
- Implement request debouncing for search

## 🚀 Development Setup

### Prerequisites

- WordPress 5.0+
- PHP 7.4+
- WP/LR Sync Plugin (optional)

### Installation

1. **Clone the plugin**
   ```bash
   git clone <repository> wp-content/plugins/photo_library_wp_api_rest_plugin
   ```

2. **Activate the plugin**
   ```bash
   wp plugin activate photo_library_wp_api_rest_plugin
   ```

3. **Install WP/LR Sync (optional)**
   ```bash
   wp plugin install wplr-sync --activate
   ```

### Testing

```bash
# Test API health
curl https://your-site.com/wp-json/photo-library/v1/test

# Test keywords endpoint
curl https://your-site.com/wp-json/photo-library/v1/pictures/keywords

# Test search
curl -X POST https://your-site.com/wp-json/photo-library/v1/pictures/by_keywords \
  -H "Content-Type: application/json" \
  -d '{"search": ["beach"]}'
```

### Debug Mode

Enable WordPress debug logging to monitor API behavior:

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Check logs
tail -f wp-content/debug.log
```

## 📝 Contributing

### Code Standards

- Follow WordPress coding standards
- Use PHPDoc for all public methods
- Include error handling in all endpoints
- Write unit tests for new functionality

### Extending the API

To add new endpoints:

1. Add route registration in `register_routes()`
2. Create corresponding handler method
3. Add proper PHPDoc documentation
4. Update this guide with examples

---

## 📞 Support

For issues, feature requests, or questions:

- Check the WordPress debug log
- Verify WP/LR Sync integration status
- Test with minimal plugins active
- Review API response format for errors
