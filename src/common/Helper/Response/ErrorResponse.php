<?php
declare(strict_types=1);
namespace Common\Helper\Response;

class ErrorResponse
{
    public string $code;
    public mixed $details;
}