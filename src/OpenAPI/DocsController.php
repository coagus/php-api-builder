<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\OpenAPI;

use Coagus\PhpApiBuilder\Http\Response;
use Coagus\PhpApiBuilder\Resource\Service;

class DocsController extends Service
{
    private const DEFAULT_SPEC_PATH = '/api/v1/docs';
    private const SAFE_PATH_PATTERN = '/^[A-Za-z0-9\/_\-\.]+$/';

    private SpecBuilder $specBuilder;

    public function __construct(SpecBuilder $specBuilder)
    {
        $this->specBuilder = $specBuilder;
    }

    public function get(): void
    {
        $action = $this->action;

        if ($action === 'swagger') {
            $this->response = $this->swaggerHtml();
            return;
        }

        if ($action === 'redoc') {
            $this->response = $this->redocHtml();
            return;
        }

        // Default: JSON spec
        $this->response = new Response($this->specBuilder->build(), 200);
    }

    private function swaggerHtml(): Response
    {
        $specUrl = $this->resolveSpecUrl('/swagger');

        $html = <<<HTML
        <!DOCTYPE html>
        <html><head>
        <title>API Docs - Swagger UI</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist/swagger-ui.css">
        </head><body>
        <div id="swagger-ui"></div>
        <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist/swagger-ui-bundle.js"></script>
        <script>SwaggerUIBundle({url: "{$specUrl}", dom_id: '#swagger-ui'})</script>
        </body></html>
        HTML;

        return $this->htmlResponse($html);
    }

    private function redocHtml(): Response
    {
        $specUrl = $this->resolveSpecUrl('/redoc');

        $html = <<<HTML
        <!DOCTYPE html>
        <html><head>
        <title>API Docs - ReDoc</title>
        </head><body>
        <redoc spec-url="{$specUrl}"></redoc>
        <script src="https://cdn.jsdelivr.net/npm/redoc/bundles/redoc.standalone.js"></script>
        </body></html>
        HTML;

        return $this->htmlResponse($html);
    }

    /**
     * Derive the spec URL from the current request path, then HTML-escape it.
     * Falls back to the default spec path if the derived value contains
     * anything beyond the safe character set.
     */
    private function resolveSpecUrl(string $actionSegment): string
    {
        $path = $this->request?->getPath() ?? self::DEFAULT_SPEC_PATH;
        $candidate = str_replace($actionSegment, '', $path);

        if ($candidate === '' || !preg_match(self::SAFE_PATH_PATTERN, $candidate)) {
            $candidate = self::DEFAULT_SPEC_PATH;
        }

        return htmlspecialchars($candidate, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function htmlResponse(string $html): Response
    {
        $response = new Response(null, 200);
        $response->header('Content-Type', 'text/html; charset=utf-8');
        $response->setBody($html);

        return $response;
    }
}
