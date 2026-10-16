# MedReview image wordmark

`medreview-wordmark.png` is the production branding asset: a 2850 × 412,
transparent PNG containing only **MedReview**, with no icon, box, or `.vn` suffix.
The renderer needs GD's PNG decoder, not installed fonts or an SVG library.

`medreview-wordmark.svg` is its editable, font-independent vector source. Every
glyph is an outlined path, not an SVG text element or remote font dependency.
Colors are `#142D50` for `Med` and `#2D63FF` for `Review`.

## Font and generation provenance

The outlines were created from this repository's bundled
`assets/fonts/inter/5.3.0/inter-latin-wght-normal.woff2`, with the variable weight
axis set to **800 (ExtraBold)**. Inter is by the Inter Project Authors and is
licensed under the SIL Open Font License 1.1; its existing complete license is
[`../fonts/inter/5.3.0/LICENSE.txt`](../fonts/inter/5.3.0/LICENSE.txt). The wordmark
is artwork produced using that font, not a redistributed modified font.

The initial outlines were rendered locally with HarfBuzz `hb-view`, using its
CoreText face loader/font functions to read the bundled WOFF2, at 256 units/em:

```sh
hb-view --face-loader=coretext --font-funcs=coretext --variations=wght=800 \
  --font-size=256 --margin=0 --background=FFFFFF00 --foreground=142D50 \
  --output-format=svg --output-file=/tmp/medreview-outline.svg \
  assets/fonts/inter/5.3.0/inter-latin-wght-normal.woff2 MedReview
```

The resulting SVG paths were retained, each glyph's horizontal offset was
reduced by `5 × its zero-based character index` for tight tracking, the final six
glyph instances were given the Review blue, and the view box was set to
`12 47 1425 206` for narrow transparent padding. The source includes accessible
title metadata and this provenance. No AI generation or substitute system font
was used.

The deployable PNG was generated from that outlined SVG with ImageMagick:

```sh
magick -background none -density 192 assets/brand/medreview-wordmark.svg \
  -strip assets/brand/medreview-wordmark.png
```

Those build tools are not required in production. Keep both assets and the
existing Inter license when deploying or modifying this branding feature.
