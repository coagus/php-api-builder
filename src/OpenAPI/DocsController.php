<?php

declare(strict_types=1);

namespace Coagus\PhpApiBuilder\OpenAPI;

use Coagus\PhpApiBuilder\Http\Response;
use Coagus\PhpApiBuilder\Resource\Service;

class DocsController extends Service
{
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
        $specUrl = $this->request?->getPath() ?? '/api/v1/docs';
        $specUrl = str_replace('/swagger', '', $specUrl);

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

        $response = new Response(null, 200);
        $response->header('Content-Type', 'text/html; charset=utf-8');
        $response->setBody($html);

        return $response;
    }

    private function redocHtml(): Response
    {
        $specUrl = $this->request?->getPath() ?? '/api/v1/docs';
        $specUrl = str_replace('/redoc', '', $specUrl);

        $html = <<<HTML
        <!DOCTYPE html>
        <html><head>
        <title>API Docs - ReDoc</title>
        </head><body>
        <redoc spec-url="{$specUrl}"></redoc>
        <script src="https://cdn.jsdelivr.net/npm/redoc/bundles/redoc.standalone.js"></script>
        </body></html>
        HTML;

        $response = new Response(null, 200);
        $response->header('Content-Type', 'text/html; charset=utf-8');
        $response->setBody($html);

        return $response;
    }
}
