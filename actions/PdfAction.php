<?php

/**
 * Action to display a pdf in an embedded reader.
 *
 * Overrides the `pdf` action shipped by the `attach` extension: the Performer walks
 * extensions in alphabetical order and keeps the last match it finds, and
 * "pdfviewer" sorts after "attach".
 *
 * @param url  required The url of the pdf file. The url has to be from the same origin than the wiki (same schema, same host & same port)
 * @param ratio shape for the container : possible values empty (default), 'portrait' - 'paysage' - 'carre'
 * @param largeurmax  the maximum wanted width ; number without "px"
 * @param hauteurmax  the maximum wanted heigth ; number without "px"
 * @param class class add class to the container : use "pull-right" and "pull-left" for position
 *
 * @category YesWiki
 *
 * @author   Adrien Cheype <adrien.cheype@gmail.com>
 * @author   Jérémy Dufraisse <jeremy.dufraisse@orange.fr>
 * @license  https://www.gnu.org/licenses/agpl-3.0.en.html AGPL 3.0
 *
 * @see     https://yeswiki.net
 */

namespace YesWiki\Pdfviewer;

use YesWiki\Core\YesWikiAction;

class PdfAction extends YesWikiAction
{
    public const VENDOR_PATH = 'tools/pdfviewer/javascripts/vendor/pdfjs-dist';
    public const STYLE_PATH = 'tools/pdfviewer/styles/pdfviewer.css';

    public function formatArguments($arg)
    {
        return [
            'url' => $arg['url'] ?? '',
            'ratio' => $arg['ratio'] ?? '',
            'largeurmax' => $arg['largeurmax'] ?? '',
            'hauteurmax' => $arg['hauteurmax'] ?? '',
            'class' => str_replace('attached_file', '', ($arg['class'] ?? '')), // to prevent errors
        ];
    }

    public function run()
    {
        $viewerPath = self::VENDOR_PATH . '/web/pdf-viewer.php';

        // The viewer is assembled by yarn and is not versioned: without this check,
        // an incomplete install would render an empty, silent iframe.
        if (!file_exists($viewerPath)) {
            return $this->render('@templates/alert-message.twig', [
                'type' => 'danger',
                'message' => _t('PDFVIEWER_ASSETS_MISSING'),
            ]);
        }

        if (!$this->isSameOrigin($this->arguments['url'])) {
            return $this->render('@templates/alert-message.twig', [
                'type' => 'danger',
                'message' => _t('PDFVIEWER_ACTION_PDF_PARAM_URL_ERROR'),
            ]);
        }

        switch ($this->arguments['ratio']) {
            case 'paysage':
                $shape = 'pdfviewer-paysage';
                $ratio = 0.75;
                break;
            case 'carre':
                $shape = 'pdfviewer-carre';
                $ratio = 1;
                break;
            case 'portrait':
            default:
                $shape = 'pdfviewer-portrait';
                $ratio = 1.38;
        }

        //size
        $maxWidth = $this->arguments['largeurmax'];
        $maxHeight = $this->arguments['hauteurmax'];
        $manageSize = false;
        if (!empty($maxWidth) && is_numeric($maxWidth)) {
            $manageSize = true;
            if (empty($maxHeight) || !(is_numeric($maxHeight))) {
                $maxHeight = $maxWidth * $ratio;
            } else {
                // calculte the minimum between width and height
                $newMaxHeight = min($maxWidth * $ratio, $maxHeight);
                $newMaxWidth = min($maxHeight / $ratio, $maxWidth);
                $maxHeight = $newMaxHeight;
                $maxWidth = $newMaxWidth;
            }
        } elseif (!empty($maxHeight) && is_numeric($maxHeight)) {
            $manageSize = true;
            if (empty($maxWidth) || !(is_numeric($maxWidth))) {
                $maxWidth = $maxHeight / $ratio;
            }
        }

        $this->wiki->AddCSSFile(self::STYLE_PATH);

        return $this->render('@pdfviewer/actions/pdf.twig', [
            'url' => $this->arguments['url'],
            'viewerUrl' => $viewerPath,
            'class' => $this->arguments['class'],
            'manageSize' => $manageSize,
            'shape' => $shape,
            'maxWidth' => $maxWidth,
            'maxHeight' => $maxHeight,
        ]);
    }

    /**
     * pdf.js already rejects a cross-origin `?file=` (validateFileURL), but we reject it
     * here too so as to render an explicit message rather than a viewer stuck on an error.
     */
    private function isSameOrigin(string $url): bool
    {
        if (empty($url)) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (!in_array($host, [$_SERVER['SERVER_NAME'], 'www.' . $_SERVER['SERVER_NAME']])) {
            return false;
        }

        $port = parse_url($url, PHP_URL_PORT);
        $serverPort = $_SERVER['SERVER_PORT'] ?? '';
        if (empty($port)) {
            // A url without a port is only compatible with a server on a default port.
            if (!in_array($serverPort, ['', '80', '443'], true)) {
                return false;
            }
        } elseif ($port != $serverPort) {
            return false;
        }

        if (
            !empty($_SERVER['HTTP_REFERER'])
            && parse_url($url, PHP_URL_SCHEME) != parse_url($_SERVER['HTTP_REFERER'], PHP_URL_SCHEME)
        ) {
            return false;
        }

        return true;
    }
}
