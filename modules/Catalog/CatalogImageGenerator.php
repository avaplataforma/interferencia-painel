<?php

declare(strict_types=1);

namespace Interferencia\Modules\Catalog;

/**
 * Contrato neutro para um futuro gerador externo de capas.
 *
 * A implementação poderá usar qualquer fornecedor sem acoplar a curadoria,
 * o site público ou o armazenamento do catálogo ao serviço escolhido.
 */
interface CatalogImageGenerator
{
    /**
     * @param array<string,mixed> $context
     * @return array{contents:string,mime_type:string,provider:string,prompt:string}
     */
    public function generate(string $prompt, array $context = []): array;
}
