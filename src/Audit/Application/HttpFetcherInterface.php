<?php

declare(strict_types=1);

namespace App\Audit\Application;

interface HttpFetcherInterface
{
    /** @return array{url:string,final_url:string,status:int,content_type:string,body:string,redirects:list<string>,error:?string,fetch_duration_ms:int} */
    public function fetch(string $url): array;
}
