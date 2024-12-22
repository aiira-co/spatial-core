<?php
declare(strict_types=1);
namespace Spatial\Common\Helper\Response;

use function json_encode;
class ServerResponse
{
    public bool $success = true;
    public string $message;
    public mixed $data;

    public ?ErrorResponse $error;
    public Meta $meta;

    public function __construct()
    {
        $this->meta = new Meta();
    }

    public function logError(string $code, mixed $detail): void
    {
        $this->success = false;
        $this->error = new ErrorResponse();
        $this->error->code = $code;
        $this->error->details = $detail;
    }

    public  function  paginate(int $currentPage, int $pageSize, int $totalPages, int $totalItems): void
    {
        $this->meta->pagination = new Pagination();
        $this->meta->pagination->currentPage = $currentPage;
        $this->meta->pagination->pageSize = $pageSize;
        $this->meta->pagination->totalPages = $totalPages;
        $this->meta->pagination->totalItems = $totalItems;
    }

    public  function getResponseStatus():int
    {
        if($this->success) return 200;
        return (int)$this->error->code ?? 500;
    }

    public function __toString():string{
       return $this->toString();
    }


    public function toString():string{

        try {
            $jsonString =  json_encode($this, JSON_THROW_ON_ERROR, 512);
        } catch (\JsonException $e) {
            $jsonString ='';
        }

        return $jsonString;
    }

}