# Changelog

## 1.0.0
- Upgrade PhotoSwipe 4 → 5.4.4 (UMD core + lightbox, no more skin/DOM template files)
- Responsive images: `srcset`/`sizes` on thumbnails and `data-pswp-srcset`/`data-pswp-sizes` in the lightbox view
- New params: thumbnail/full bounding box, srcset width lists, sizes attributes, images per row, thumbnail max height
- Base stylesheet (`plugin_gallery/gallery.css`) driven by CSS custom properties
- en-GB + de-DE language files
- Media root folder read from `com_media` `image_path` param (fallback `images`)
- Update server for com_installer

## 0.9.7
- Joomla 5/6 compatibility (SubscriberInterface)
