# Art

Marketing assets for the [Filament plugin directory](https://filamentphp.com/plugins).

| File | Size | Used for |
| --- | --- | --- |
| `banner.jpg` | 2560 x 1440 | Main image (16:9, at least 2560 x 1440) |
| `thumbnail.jpg` | 1920 x 1080 | Tighter crop for the plugins list (16:9, at least 1280 x 720) |

Both are rendered from the matching `.html` files, so they can be regenerated
after a design or wording change:

```bash
chrome="/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"

"$chrome" --headless=new --disable-gpu --hide-scrollbars \
    --force-device-scale-factor=2 --window-size=1280,720 \
    --screenshot=banner.png "file://$PWD/banner.html"

"$chrome" --headless=new --disable-gpu --hide-scrollbars \
    --force-device-scale-factor=1.5 --window-size=1280,720 \
    --screenshot=thumbnail.png "file://$PWD/thumbnail.html"

sips -s format jpeg -s formatOptions 90 banner.png --out banner.jpg
sips -s format jpeg -s formatOptions 90 thumbnail.png --out thumbnail.jpg
```

The pages are laid out at 1280 x 720 CSS pixels; the device scale factor is
what brings them up to the required resolution.

Headline font: [Satoshi](https://www.fontshare.com/fonts/satoshi). The weekly
grid mock-up mirrors the real page (hour cells, filled range segments, minute
badges, the inline range editor) with Filament's default amber as the accent,
which is also the accent used in the headline. Same layout system as the
`filament-logs-explorer` banner so the two plugins read as a family.

This folder is `export-ignore`d, so it is not shipped in the Composer package.
