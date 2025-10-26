# PhotoLibrary REST API Plugin

A comprehensive WordPress REST API plugin for photo library management with WP/LR Sync integration.

## Features

- RESTful API for photo management
- WP/LR Sync integration for Lightroom synchronization
- Hierarchical keyword/tag management
- Advanced search capabilities
- CORS support for external applications

## Installation

1. Upload the plugin to `/wp-content/plugins/photo_library_wp_api_rest_plugin/`
2. Activate the plugin through the WordPress admin
3. Optionally install WP/LR Sync for enhanced features

## Quick Start

### Test the API
```bash
curl https://your-site.com/wp-json/photo-library/v1/test
```

### Get all keywords
```bash
curl https://your-site.com/wp-json/photo-library/v1/pictures/keywords
```

### Search by keywords
```bash
curl -X POST https://your-site.com/wp-json/photo-library/v1/pictures/by_keywords \
  -H "Content-Type: application/json" \
  -d '{"search": ["beach", "sunset"]}'
```

## API Endpoints

- `GET /test` - API health check
- `GET /pictures/all` - Get all pictures
- `GET /pictures/{id}` - Get picture by ID
- `GET /pictures/random/{count}` - Get random pictures
- `POST /pictures/by_keywords` - Search by keywords
- `GET /pictures/keywords` - Get all keywords (flat)
- `GET /pictures/hierarchy` - Get keyword hierarchy

## Documentation

See [DEVELOPER_GUIDE.md](DEVELOPER_GUIDE.md) for complete documentation, examples, and integration guides.

## Requirements

- WordPress 5.0+
- PHP 7.4+
- WP/LR Sync Plugin (optional, for enhanced features)

## License

GPL v2 or later

## Author

Alex Baron
