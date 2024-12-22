<?php
declare(strict_types=1);
namespace Common\Helper\Response;

use Ramsey\Uuid\Uuid;

class Meta
{
    public string $requestId;
    public int $timestamp;
    public string $version = 'v2';
    public ?Pagination $pagination;

    public function __construct()
    {
        $this->requestId = Uuid::uuid1()->toString();
        $this->timestamp = time();

    }
}