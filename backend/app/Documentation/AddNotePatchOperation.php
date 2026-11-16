<?php

namespace App\Documentation;

use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;

class AddNotePatchOperation implements DocumentTransformer
{
    public function handle(OpenApi $document, OpenApiContext $context): void
    {
        foreach ($document->paths as $path) {
            if ($path->path !== 'notes/{note}' || ! isset($path->operations['put'])) {
                continue;
            }

            if (isset($path->operations['patch'])) {
                return;
            }

            $patchOperation = clone $path->operations['put'];
            $patchOperation->setMethod('patch');
            $patchOperation->setOperationId('notes.update.patch');
            $patchOperation->summary('Partially update a note (PATCH)');
            $patchOperation->description(
                'Patch alias for the same partial-update behavior as PUT. At least one valid field is required; the note embedding is regenerated from the resulting title and content. This route allows 60 requests per minute per client IP.'
            );

            $path->addOperation($patchOperation);

            return;
        }
    }
}
