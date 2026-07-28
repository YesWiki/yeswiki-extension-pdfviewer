# PdfViewer

A YesWiki extension that displays a wiki PDF file in an embedded viewer built on
[PDF.js](https://github.com/mozilla/pdf.js) (Mozilla, Apache 2.0 licence).

## Why this extension exists

YesWiki core now hands PDF files over to the browser's own viewer. That works on a
modern desktop browser, but it makes the reading experience unpredictable — and on
some devices it does not work at all:

- **The rendering differs from one browser to the next.** Chrome, Firefox and Safari
  each ship a different viewer, with its own toolbar, keyboard shortcuts and zoom
  behaviour. The same wiki page therefore looks and behaves differently depending on
  who is reading it.
- **Mobile browsers rarely display PDFs inline.** Most of them download the file or
  hand it to a separate application, which pulls the reader out of the wiki entirely.
- **Older or lighter browsers have no built-in PDF viewer.** For those visitors the
  document simply cannot be read in place.

This extension embeds its own viewer instead of relying on the browser's. A PDF then
looks and behaves identically for every visitor — desktop or phone, recent browser or
not — and the reader stays on the wiki page.

## Relationship with the `attach` extension

This extension provides the `pdf` action and **overrides** the one shipped by
`attach`: the `Performer` walks extensions in alphabetical order and keeps the last
match it finds, and `pdfviewer` sorts after `attach`. Nothing in `attach` is modified,
so removing this extension restores the previous behaviour.

## Usage

```text
{{pdf url="https://my-wiki.org/files/document.pdf"}}
{{pdf url="…" ratio="paysage" largeurmax="600"}}
{{pdf url="…" class="pull-right" hauteurmax="400"}}
```

| Parameter | Description |
| --- | --- |
| `url` | **Required.** Url of the PDF, which must share the wiki's origin (same scheme, same host, same port). |
| `ratio` | Container shape: `portrait` (default), `paysage`, `carre`. |
| `largeurmax` | Maximum width, in pixels, without a unit. |
| `hauteurmax` | Maximum height, in pixels, without a unit. |
| `class` | Classes added to the container. `pull-left` and `pull-right` position the block. |

## Installation

```bash
cd tools/pdfviewer
yarn install --ignore-optional
```

`yarn install` installs `pdfjs-dist`, then runs `scripts/assemble-pdfjs.mjs`, which
builds `javascripts/vendor/pdfjs-dist/`. That directory is **not versioned**, so it
has to be regenerated on every environment, development and production alike.

`--ignore-optional` skips `@napi-rs/canvas`, an optional dependency of `pdfjs-dist`
that renders PDFs to images from Node. This extension only copies static files out of
the package and runs the viewer in the browser, so those 61 MB of native binaries are
never used. The flag has to be typed: yarn 1 cannot persist it in `.yarnrc`, where
boolean options are appended as a positional argument and abort the command.

If the assembled directory is missing, the action renders an explicit message rather
than an empty frame.

## Updating PDF.js

```bash
cd tools/pdfviewer
yarn upgrade pdfjs-dist --latest --ignore-optional
```

`yarn.lock` is the single source of truth: the script reads the version installed in
`node_modules/pdfjs-dist/` and downloads the archive of the matching GitHub tag. A
viewer out of sync with its engine is therefore impossible.

The script does nothing when `revision.json` already matches the installed version: it
is idempotent, and only reaches the network when the version changes.

## Why a GitHub archive on top of the npm package

The npm `pdfjs-dist` package provides the PDF engine but **not** the viewer
application (`web/viewer.html`, `viewer.mjs`, `viewer.css`, `web/locale/`), which is
published only in the GitHub release archives.

## Licence

AGPL-3.0. PDF.js is distributed under the Apache 2.0 licence; its licence file is kept
in the assembled directory.
