<?php

declare(strict_types=1);

namespace Modular\Router\Response;

class ProblemDetailsPayloadFactory
{
    /**
     * @return array<string, int|string>
     */
    public function createPayload(int $statusCode, string $title, string $type = 'about:blank'): array
    {
        return [
            'type' => $type,
            'title' => $title,
            'status' => $statusCode,
        ];
    }
}
