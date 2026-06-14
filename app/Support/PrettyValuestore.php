<?php

namespace App\Support;

use Spatie\Valuestore\Valuestore;

class PrettyValuestore extends Valuestore
{
    /**
     * Persist the store as human-readable JSON. Spatie's Valuestore writes
     * minified JSON; we keep the credentials file pretty-printed so it stays
     * easy to inspect and edit by hand.
     */
    protected function setContent(array $values): static
    {
        file_put_contents(
            $this->fileName,
            json_encode($values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL,
        );

        if (! count($values)) {
            unlink($this->fileName);
        }

        return $this;
    }
}
