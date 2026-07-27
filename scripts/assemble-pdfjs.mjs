#!/usr/bin/env node

/**
 * Assembles the PDF.js viewer into javascripts/vendor/pdfjs-dist/.
 *
 * Why this script exists: the npm `pdfjs-dist` package provides the PDF engine
 * but NOT the viewer application (`web/viewer.html`, `viewer.mjs`, `viewer.css`
 * and `web/locale/`). Those are published only in the GitHub release archives.
 *
 * The version installed by yarn is authoritative: we download the archive of the
 * matching tag, which makes a viewer out of sync with its engine impossible.
 * Updating is therefore just `yarn upgrade pdfjs-dist --latest`.
 */

import { cpSync, existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { unzipSync } from 'fflate'

const extensionRoot = dirname(dirname(fileURLToPath(import.meta.url)))
const sourceRoot = join(extensionRoot, 'node_modules', 'pdfjs-dist')
const targetRoot = join(extensionRoot, 'javascripts', 'vendor', 'pdfjs-dist')
const revisionFile = join(targetRoot, 'revision.json')

/**
 * The only files missing from the npm package. Everything else comes from
 * node_modules, where it is byte-identical to the archive.
 */
const VIEWER_ONLY_FILES = ['web/viewer.html', 'web/viewer.mjs', 'web/viewer.css']

/**
 * The viewer resolves its assets relatively to web/ (`../web/cmaps/` and so on),
 * whereas npm puts them at the package root: hence the move under web/.
 * We take the `legacy` build, which adds core-js polyfills for browsers lacking
 * the most recent JavaScript APIs.
 */
const COPIES_FROM_NPM = [
  ['LICENSE', 'LICENSE'],
  ['legacy/build/pdf.mjs', 'build/pdf.mjs'],
  ['legacy/build/pdf.worker.mjs', 'build/pdf.worker.mjs'],
  ['legacy/build/pdf.sandbox.mjs', 'build/pdf.sandbox.mjs'],
  ['web/images', 'web/images'],
  ['cmaps', 'web/cmaps'],
  ['standard_fonts', 'web/standard_fonts'],
  ['wasm', 'web/wasm'],
  ['iccs', 'web/iccs']
]

/** Apache serves .mjs as octet-stream by default, which ES modules reject. */
const HTACCESS = `<IfModule mod_mime.c>
  AddType text/javascript .mjs
</IfModule>
`

function readInstalledVersion() {
  const manifest = join(sourceRoot, 'package.json')
  if (!existsSync(manifest)) {
    throw new Error(
      `pdfjs-dist was not found in ${sourceRoot}.\n`
        + 'Run "yarn install" from tools/pdfviewer/.'
    )
  }

  return JSON.parse(readFileSync(manifest, 'utf8')).version
}

function readAssembledVersion() {
  if (!existsSync(revisionFile)) {
    return null
  }
  try {
    return JSON.parse(readFileSync(revisionFile, 'utf8')).version ?? null
  } catch {
    // Unreadable revision.json: treat the assembly as needing a rebuild.
    return null
  }
}

async function downloadViewerArchive(version) {
  const url = `https://github.com/mozilla/pdf.js/releases/download/v${version}`
    + `/pdfjs-${version}-legacy-dist.zip`

  const response = await fetch(url, { redirect: 'follow' })
  if (!response.ok) {
    throw new Error(`Could not download ${url}: HTTP ${response.status}.`)
  }

  return new Uint8Array(await response.arrayBuffer())
}

function isViewerOnly(path) {
  return VIEWER_ONLY_FILES.includes(path) || path.startsWith('web/locale/')
}

function extractViewer(archive) {
  const entries = unzipSync(archive, { filter: (file) => isViewerOnly(file.name) })

  const written = Object.entries(entries).filter(([path]) => !path.endsWith('/'))
  if (written.length === 0) {
    throw new Error('The archive contains no viewer file; unexpected layout.')
  }

  for (const [path, content] of written) {
    const destination = join(targetRoot, path)
    mkdirSync(dirname(destination), { recursive: true })
    writeFileSync(destination, content)
  }

  return written.length
}

function copyEngineFromNpm() {
  for (const [from, to] of COPIES_FROM_NPM) {
    const source = join(sourceRoot, from)
    if (!existsSync(source)) {
      throw new Error(`${from} is missing from pdfjs-dist; unexpected package layout.`)
    }
    const destination = join(targetRoot, to)
    mkdirSync(dirname(destination), { recursive: true })
    cpSync(source, destination, { recursive: true })
  }
}

/**
 * Installs the files this extension owns, as opposed to those coming from pdf.js.
 * They are rewritten on every run, so editing a source file takes effect without
 * having to wipe the assembled directory first.
 *
 * The shim must sit next to viewer.html: the latter's paths are relative to the
 * document, so moving it one level away would break every one of them.
 */
function installOwnFiles() {
  cpSync(join(extensionRoot, 'libs', 'pdf-viewer.php'), join(targetRoot, 'web', 'pdf-viewer.php'))
  writeFileSync(join(targetRoot, '.htaccess'), HTACCESS)
}

async function assemble() {
  const version = readInstalledVersion()

  if (readAssembledVersion() === version) {
    // Upstream files are already in place, so only the ones we own need refreshing.
    installOwnFiles()
    console.log(`pdf.js ${version} already assembled.`)
    return
  }

  console.log(`Assembling pdf.js ${version}…`)

  // Full rebuild: prevents files from an earlier version from lingering.
  rmSync(targetRoot, { recursive: true, force: true })
  mkdirSync(targetRoot, { recursive: true })

  const extracted = extractViewer(await downloadViewerArchive(version))
  copyEngineFromNpm()
  installOwnFiles()

  writeFileSync(revisionFile, `${JSON.stringify({ version }, null, 2)}\n`)

  console.log(`pdf.js ${version} assembled (${extracted} files from the archive).`)
}

assemble().catch((error) => {
  console.error(`\nCould not assemble pdf.js: ${error.message}\n`)
  process.exit(1)
})
