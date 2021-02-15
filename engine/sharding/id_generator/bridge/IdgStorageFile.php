<?php
/**
 * Author: Drunk  (idrunk.net drunkce.com)
 * Date: 2018-9-19 2:53
 */

namespace dce\sharding\id_generator\bridge;

class IdgStorageFile extends IdgStorage {
    private string $dataDir;

    public function __construct(string $dataDir) {
        $this->dataDir = $dataDir;
        if (! file_exists($this->dataDir)) {
            mkdir($this->dataDir, 0777, true);
        }
    }

    /** @inheritDoc */
    protected function genKey(string $tag, string $prefix = ''): string {
        return "{$prefix}{$this->dataDir}{$tag}.php";
    }

    /** @inheritDoc */
    public function load(string $tag): IdgBatch|null {
        $content = @file_get_contents($this->genKey($tag));
        if ($content) {
            $batch = unserialize($content);
            if ($batch instanceof IdgBatch) {
                return $batch;
            }
        }
        return null;
    }

    /** @inheritDoc */
    public function save(string $tag, IdgBatch $batch): void {
        $content = serialize($batch);
        file_put_contents($this->genKey($tag), $content);
    }
}
