<?php
declare(strict_types=1);
namespace Common\Helper\Response;

class Pagination
{
    public int $currentPage;
    public int $pageSize;
    public int $totalPages;
    public int $totalItems;
}