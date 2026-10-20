<?php

declare(strict_types=1);

namespace App\Http\Actions;

use App\Http\OpenApi\OpenApiDefinition;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ApiDocsAction
{
    public function __construct(private readonly bool $demo)
    {
    }

    public function specification(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write(json_encode(OpenApiDefinition::build($this->demo),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withHeader('Cache-Control', 'no-store');
    }

    public function ui(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $mode = $this->demo ? 'SIMULAÇÃO · SEM CHAMADAS DE IA' : 'API PRINCIPAL · DADOS LOCAIS';
        $instructions = $this->demo
            ? 'O exemplo gera uma prova fixa de frações para o 6º ano usando currículo sintético. As notas e o texto de referência ficam registrados, mas o fake não os interpreta. O histórico demo é descartado ao parar o MySQL demo.'
            : 'Esta interface usa o banco local e o provedor Gemini. Gerar provas exige currículo revisado e liberado e uma chave configurada. Importação, revisão e publicação alteram os dados deste ambiente.';
        $response->getBody()->write(<<<HTML
            <!doctype html>
            <html lang="pt-BR">
            <head>
              <meta charset="utf-8">
              <meta name="viewport" content="width=device-width,initial-scale=1">
              <title>Gerador de Provas — Swagger</title>
              <link rel="icon" href="data:,">
              <link rel="stylesheet" href="/swagger-ui/swagger-ui.css">
              <link rel="stylesheet" href="/swagger-ui/app.css">
              <script defer src="/swagger-ui/swagger-ui-bundle.js"></script>
              <script defer src="/swagger-ui/init.js"></script>
            </head>
            <body>
              <header class="guide">
                <div class="guide-inner">
                  <span class="mode">{$mode}</span>
                  <h1>Experimente a API</h1>
                  <p>{$instructions}</p>
                  <ol>
                    <li>Em <strong>Autenticação</strong>, cadastre uma conta ou faça login. A API envia um cookie HttpOnly; o Swagger busca o token CSRF automaticamente nas alterações. Importação, revisão e liberação curricular exigem papel administrativo.</li>
                    <li>Em <strong>Professores → POST /teachers</strong>, clique em <strong>Try it out</strong>, preencha o nome e execute. Copie o <strong>id</strong> retornado.</li>
                    <li>Use esse id em <strong>PATCH /teachers/{id}/settings</strong> para salvar escola e fonte. Em <strong>Catálogos</strong>, consulte matérias e anos escolares.</li>
                    <li>Em <strong>Provas → POST /exams/generate</strong>, clique em <strong>Try it out</strong>. Acrescente <strong>teacher_id</strong> com o id copiado para associar o professor. A geração também aceita pedidos sem professor cadastrado.</li>
                    <li>Use <strong>exam_header</strong> para turma, aluno e data e <strong>formatting</strong> para a fonte desta prova. Omita os campos de nome/escola e o bloco formatting para herdar o cadastro. Clique em <strong>Execute</strong>. Na simulação, varie as alternativas (2–6) e as afirmações (1–10).</li>
                    <li>Copie <strong>exam.id</strong> e <strong>generation.id</strong> da resposta para consultar a prova, o gabarito e o histórico.</li>
                  </ol>
                  <p>O cabeçalho e a fonte são preenchidos pelo backend e ficam salvos com a prova. Alterar o cadastro depois preserva as provas já geradas.</p>
                  <p class="guide-foot">Os pedidos são enviados para este mesmo endereço. <a href="/openapi.json">Abrir contrato OpenAPI em JSON</a></p>
                </div>
              </header>
              <main id="swagger-ui"></main>
              <noscript>Ative o JavaScript para usar os formulários do Swagger.</noscript>
            </body>
            </html>
            HTML);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
    }
}
