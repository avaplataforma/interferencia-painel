<?php

declare(strict_types=1);

use Interferencia\Kernel\Config\Config;
use Interferencia\Kernel\Environment\Environment;
use Interferencia\Kernel\Http\Request;
use Interferencia\Kernel\Http\Response;
use Interferencia\Kernel\Http\Router;
use Interferencia\Kernel\Log\JsonLogger;

$rootPath = dirname(__DIR__);
$autoload = $rootPath . '/vendor/autoload.php';

if (!is_file($autoload)) {
    fwrite(STDERR, "Autoload ausente. Execute: composer dump-autoload\n");
    exit(1);
}

require $autoload;

$tests = [];

$tests['carrega ambiente sem sobrescrever valores existentes'] = static function (): void {
    $suffix = strtoupper(bin2hex(random_bytes(4)));
    $first = 'TEST_FIRST_' . $suffix;
    $second = 'TEST_SECOND_' . $suffix;
    $path = tempnam(sys_get_temp_dir(), 'env_');

    assertTrue($path !== false, 'Não foi possível criar arquivo temporário.');
    file_put_contents($path, sprintf("%s=arquivo\n%s=\"valor com espaço\"\n", $first, $second));
    putenv($first . '=sistema');

    try {
        Environment::load($path);
        assertSame('sistema', getenv($first));
        assertSame('valor com espaço', getenv($second));
    } finally {
        @unlink($path);
        putenv($first);
        putenv($second);
        unset($_ENV[$first], $_ENV[$second], $_SERVER[$first], $_SERVER[$second]);
    }
};

$tests['consulta configuração com notação por pontos'] = static function (): void {
    $config = new Config(['app' => ['name' => 'Painel', 'debug' => false]]);

    assertSame('Painel', $config->string('app.name'));
    assertSame(false, $config->bool('app.debug'));
    assertSame('padrão', $config->get('app.ausente', 'padrão'));
};

$tests['grava JSON e remove segredos do contexto'] = static function (): void {
    $path = tempnam(sys_get_temp_dir(), 'log_');
    assertTrue($path !== false, 'Não foi possível criar arquivo temporário.');

    try {
        $logger = new JsonLogger($path, 'debug');
        $logger->log('info', 'Teste', [
            'user' => 'teste',
            'password' => 'não-pode-aparecer',
            'nested' => ['access_token' => 'também-não'],
        ]);

        $record = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        assertSame('Teste', $record['message'] ?? null);
        assertSame('teste', $record['context']['user'] ?? null);
        assertSame('[REDACTED]', $record['context']['password'] ?? null);
        assertSame('[REDACTED]', $record['context']['nested']['access_token'] ?? null);
    } finally {
        @unlink($path);
    }
};

$tests['roteia dentro do prefixo e captura parâmetros restritos'] = static function (): void {
    $router = new Router('/painel');
    $router->get(
        '/unidades/{id:\d+}',
        static fn (Request $request, array $parameters): Response => Response::text($parameters['id']),
    );

    $response = $router->dispatch(new Request('GET', '/painel/unidades/42/'));
    assertSame(200, $response->status());
    assertSame('42', $response->body());

    assertSame(404, $router->dispatch(new Request('GET', '/painel/unidades/abc'))->status());
    assertSame(404, $router->dispatch(new Request('GET', '/outro/unidades/42'))->status());
};

$tests['diferencia método não permitido de rota inexistente'] = static function (): void {
    $router = new Router('/painel');
    $router->get('/status', static fn (): Response => Response::text('ok'));

    $notAllowed = $router->dispatch(new Request('POST', '/painel/status'));
    assertSame(405, $notAllowed->status());
    assertSame('GET, HEAD', $notAllowed->header('Allow'));
    assertSame(404, $router->dispatch(new Request('GET', '/painel/ausente'))->status());
};

$tests['aceita HEAD em rota GET'] = static function (): void {
    $router = new Router('/painel');
    $router->get('/status', static fn (): Response => Response::text('ok'));

    assertSame(200, $router->dispatch(new Request('HEAD', '/painel/status'))->status());
};

$failures = 0;

foreach ($tests as $name => $test) {
    try {
        $test();
        fwrite(STDOUT, "✓ {$name}\n");
    } catch (Throwable $exception) {
        $failures++;
        fwrite(STDERR, "✗ {$name}: {$exception->getMessage()}\n");
    }
}

fwrite(STDOUT, sprintf("\n%d teste(s), %d falha(s).\n", count($tests), $failures));
exit($failures === 0 ? 0 : 1);

function assertSame(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(sprintf(
            'Esperado %s, recebido %s.',
            var_export($expected, true),
            var_export($actual, true),
        ));
    }
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
