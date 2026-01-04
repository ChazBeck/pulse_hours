# Favicon Setup Instructions

## Files Added
- `includes/head.php` - Common HTML head include with favicon links
- Favicon link added to:
  - `apps/admin/reports/client-hours.php`
  - `apps/admin/reports/pulse-workload.php`
  - `apps/admin/hours-log.php`

## Required Favicon Images

You need to place the Veerless logo (orange asterisk) as favicons in the following locations:

### Required Files:
1. **`assets/images/favicon-32x32.png`** - 32x32 pixel PNG
2. **`assets/images/favicon-16x16.png`** - 16x16 pixel PNG  
3. **`assets/images/apple-touch-icon.png`** - 180x180 pixel PNG (for iOS devices)

## How to Create Favicons

### Option 1: Use Online Converter (Easiest)
1. Visit https://favicon.io/favicon-converter/
2. Upload the Veerless logo (orange asterisk image)
3. Download the generated favicon package
4. Upload the PNG files to `assets/images/` directory

### Option 2: Use Image Editor
1. Open the Veerless logo in an image editor (Photoshop, GIMP, etc.)
2. Resize to 32x32 pixels, save as `favicon-32x32.png`
3. Resize to 16x16 pixels, save as `favicon-16x16.png`
4. Resize to 180x180 pixels, save as `apple-touch-icon.png`
5. Upload all three files to `assets/images/`

### Option 3: Using ImageMagick (Command Line)
If you have ImageMagick installed:
```bash
# Assuming the logo is named veerless-asterisk.png
convert veerless-asterisk.png -resize 32x32 assets/images/favicon-32x32.png
convert veerless-asterisk.png -resize 16x16 assets/images/favicon-16x16.png
convert veerless-asterisk.png -resize 180x180 assets/images/apple-touch-icon.png
```

## Adding to Other Pages

To add the favicon to other pages, include this in the `<head>` section:

```php
<?php include __DIR__ . '/../../includes/head.php'; ?>
```

Adjust the path (`../`) based on the page's location relative to the `includes` folder.

## Testing

After uploading the favicon files:
1. Clear your browser cache
2. Visit any page with the favicon link
3. Check the browser tab - you should see the orange Veerless asterisk

## Current Status

✅ Code changes committed to Git
❌ Favicon image files NOT YET UPLOADED - you need to create and upload them manually

The favicon will appear once you upload the three PNG files to the `assets/images/` directory.
