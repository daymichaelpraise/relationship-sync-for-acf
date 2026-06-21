# wordpress.org assets

Drop the plugin directory assets here. The deploy workflow uploads them to the
SVN `assets/` directory (they are NOT shipped inside the plugin zip).

Expected files:

- `icon-128x128.png` and `icon-256x256.png` — plugin icon
- `banner-772x250.png` and `banner-1544x500.png` — header banner
- `screenshot-1.png`, `screenshot-2.png`, `screenshot-3.png` — must match the
  order of the `== Screenshots ==` section in `readme.txt`

PNG (or JPG) only. Remove this README before adding real assets if you prefer a
clean folder; the deploy action ignores non-image files anyway.
