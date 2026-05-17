# DotEnvIt Collage Engine

A dynamic, memory-safe image collage generator for Laravel. This package evaluates image orientations on the fly to produce structural, clean masonry grids (such as top-banners or side-portrait pillars) layouts.

## Features

- **Adaptive 3-Image Engine**: Intelligently swaps between a *Landscape Banner* framework or a *Portrait Left Pillar* layout depending on the primary image's aspect ratio.
- **Dynamic Height Compression**: Automatically calculates and shrinks row elements proportionally using a configuration threshold boundary (`canvas_height`) so multi-image elements snap perfectly onto single-page PDF engines (like DomPDF or Snappy) without creating blank whitespace gaps.
- **Memory Optimization Pipeline**: Gathers layout image dimensions safely (`getimagesize`) without loading pixel matrices directly into memory, cleaning up processing allocations instantly after write sequences finish to protect server thresholds.
- **Fluent Chaining Interface**: Allows developers to adjust constraints on the fly and immediately pluck out absolute filesystem paths or asset URLs.

---

## Installation

You can install this package easily via Composer.

### 1. Run the Composer Require Command
Execute the following command in your terminal root to download and install the distribution:

```bash
composer require dot-env-it/collage

```

### 2. Publish Configuration Assets

Expose the custom layout variables to your root application configuration directory by running:

```bash
php artisan vendor:publish --tag=collage-config

```

---

## Configuration

Once published, you can fine-tune default compilation parameters globally inside `config/collage.php`:

```php
return [
    // Default canvas width constraint (Pixels)
    'canvas_width' => 1200,

    // Strict maximum vertical height ceiling to prevent page-overflow breaks
    'canvas_height'  => 1200,

    // Border gaps between bounding layout rows/columns
    'padding'      => 12,

    // Default Storage Disk driver target
    'disk'         => 'public',
];

```

---

## Usage

### Basic Usage

Pass an array of local absolute file paths to the Collage builder and export it to your destination path:

```php
use DotEnvIt\Collage\Facades\Collage;

$imagePaths = [
    '/storage/app/public/photos/img1.jpg',
    '/storage/app/public/photos/img2.jpg',
    '/storage/app/public/photos/img3.jpg',
];

// Returns instance of Collage after writing to disk
$collage = Collage::make()
    ->from($imagePaths)
    ->save('collages/event-101.jpg');

```

### Fluent Extraction Methods

You can easily extract the file targets inline directly out of your execution pipeline:

```php
// Extract the absolute local file path (perfect for PDF engines)
$absolutePath = Collage::make()
    ->from($imagePaths)
    ->save('collages/event-101.jpg')
    ->getPath(); 
// Returns: "/home/user/app/storage/app/public/collages/event-101.jpg"

// Extract the publicly accessible public asset URL string
$publicUrl = Collage::make()
    ->from($imagePaths)
    ->save('collages/event-101.jpg')
    ->getUrl();
// Returns: "[https://your-domain.com/storage/collages/event-101.jpg](https://your-domain.com/storage/collages/event-101.jpg)"

```

### Runtime Method Overrides

If a specific layout demands unique structural parameters that differ from your global `config/collage.php` file defaults, you can override settings fluently at runtime:

```php
$customCollagePath = Collage::make()
    ->from($imagePaths)
    ->width(1600)       // Enforce a crisp wider base profile
    ->height(1200)  // Expand vertical space threshold limit
    ->disk('local')
    ->save('collages/high-res-event.jpg')
    ->getPath();

```

---

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

