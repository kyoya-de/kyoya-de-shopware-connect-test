<?php

namespace MakairaConnect\Client;

use Makaira\Connect\Exception as ConnectException;
use Makaira\Connect\Exceptions\UnexpectedValueException;
use Makaira\Query;

interface ApiInterface
{
    /**
     * @param Query  $query
     * @param string $debug
     *
     * @return array
     */
    public function search(Query $query, string $debug = ''): array;
}
