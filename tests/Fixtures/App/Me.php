<?php

declare(strict_types=1);

namespace Tests\Fixtures\App;

use Coagus\PhpApiBuilder\Resource\Service;

/**
 * Canonical self-service resource used to exercise the nested-action URL
 * shape `/api/v1/me/sessions/{id}`. The handler echoes back the id and
 * action it observed so the feature test can assert that the router
 * preserved segment 3 (see UI-005).
 */
class Me extends Service
{
    public function deleteSessions(): void
    {
        $this->success([
            'action' => $this->action,
            'resourceId' => $this->resourceId,
            'closed' => true,
        ], 200);
    }

    public function getSessions(): void
    {
        $this->success([
            'action' => $this->action,
            'resourceId' => $this->resourceId,
        ]);
    }
}
