<?php

declare(strict_types=1);

namespace App\Http\OpenApi;

final class OpenApiDefinition
{
    public static function examExample(): array
    {
        return ['subject' => 'matematica', 'grade' => 6, 'topics' => ['frações'],
            'question_count' => 3, 'question_mode' => 'manual',
            'question_distribution' => ['multiple_choice' => 1, 'open_ended' => 1, 'true_false' => 1],
            'alternatives_count' => 5, 'true_false_statements' => 3, 'image_mode' => 'none',
            'exam_header' => ['student_name' => null, 'class_name' => '6º A', 'date' => '2026-10-20'],
            'formatting' => ['font_family' => 'Arial', 'font_size' => 12],
            'teacher_notes' => 'Use linguagem clara, uma situação de partilha e uma comparação com justificativa.',
            'reference_text' => 'Frações equivalentes representam a mesma parte de um inteiro.'];
    }

    public static function build(bool $demo = false): array
    {
        $schemas = ApiSchemas::build();
        $id = self::parameter('id', ApiSchemas::integer(), 'path', 'ID neste ambiente; use o retornado pela operação anterior.', 1);
        $paths = [];
        $paths['/auth/register']['post'] = self::operation('registerUser', 'Autenticação', 'Criar uma conta', 'AuthSession',
            'Cadastra uma conta de professor, inicia uma sessão de 12 horas e envia cookie HttpOnly/SameSite Strict. '
            . 'Senha de 12 a 128 caracteres, até 512 bytes. Não aceita papel administrativo no JSON. '
            . 'Limite de cinco cadastros por IP a cada hora. Corpo máximo: 8 KiB.',
            body: self::body('AuthRegister', ['account' => ['summary' => 'Conta de exemplo — troque e-mail e senha',
                'value' => ['name' => 'Professor Exemplo', 'email' => 'professor@example.test', 'password' => 'TroqueEstaSenha123!', 'password_confirmation' => 'TroqueEstaSenha123!']]]),
            success: 201, errors: [403, 409, 413, 415, 422, 429, 503]);
        $paths['/auth/login']['post'] = self::operation('loginUser', 'Autenticação', 'Entrar na conta', 'AuthSession',
            'Valida as credenciais na API e substitui a sessão atual por um novo cookie aleatório. '
            . 'E-mail ou senha incorretos devolvem uma mensagem genérica. Até 10 tentativas por e-mail e 60 por IP em 15 minutos. '
            . 'O IP é o observado pelo servidor; atrás de proxy ele pode ser compartilhado. Corpo máximo: 8 KiB.',
            body: self::body('AuthLogin', ['account' => ['summary' => 'Use uma conta cadastrada',
                'value' => ['email' => 'professor@example.test', 'password' => 'TroqueEstaSenha123!']]]), errors: [401, 403, 413, 415, 422, 429, 503]);
        $paths['/auth/session']['get'] = self::operation('getAuthSession', 'Autenticação', 'Validar a sessão atual', 'AuthSession',
            'Retorna o usuário autenticado e o token CSRF. O cookie de autenticação nunca aparece no JSON. '
            . 'Envie X-CSRF-Token em todas as operações de escrita. O Swagger busca esse token automaticamente antes de enviar alterações.', errors: [401, 403, 503]);
        $paths['/auth/logout']['post'] = self::operation('logoutUser', 'Autenticação', 'Encerrar a sessão', 'AuthLogout',
            'Revoga a sessão no servidor e expira o cookie atual. Exige sessão e X-CSRF-Token.', errors: [401, 403, 503]);
        $paths['/teachers']['post'] = self::operation('createTeacher', 'Professores', 'Cadastrar um professor', 'Teacher',
            'Cria o professor e suas preferências padrão juntos: escola em branco, Arial, tamanho 12. '
            . 'Copie o id retornado para as próximas operações. A sessão define o proprietário. Corpo máximo: 64 KiB. '
            . 'O e-mail é opcional e único; use null ou omita quando não precisar cadastrá-lo.',
            body: self::body('TeacherCreate', ['teacher' => ['summary' => 'Professor de exemplo — dados fictícios',
                'value' => ['name' => 'Carolina Silva', 'email' => null]]]), success: 201, errors: [409, 413, 415, 422, 503]);
        $paths['/teachers']['get'] = self::operation('listTeachers', 'Professores', 'Listar os professores', 'TeacherList',
            'Mais recentes primeiro. Use next_before_id como before_id na próxima página.', [
                self::parameter('limit', [...ApiSchemas::integer(1, 100), 'default' => 20]),
                self::parameter('before_id', ApiSchemas::integer()),
            ], errors: [422, 503]);
        $paths['/teachers/{id}']['get'] = self::operation('getTeacher', 'Professores', 'Consultar professor e preferências', 'Teacher',
            'Use o id do professor cadastrado neste ambiente. Inclui as preferências atuais.', [$id], errors: [404, 422, 503]);
        $paths['/teachers/{id}']['patch'] = self::operation('updateTeacher', 'Professores', 'Editar os dados do professor', 'Teacher',
            'Altera somente os campos enviados. Informe name ou email; null remove o e-mail. '
            . 'O nome não aceita null nem texto vazio. Alterações no cadastro não modificam provas já salvas. Corpo máximo: 64 KiB.', [$id],
            body: self::body('TeacherUpdate', ['name' => ['summary' => 'Atualizar somente o nome', 'value' => ['name' => 'Carolina Santos']]]),
            errors: [404, 409, 413, 415, 422, 503]);
        $paths['/teachers/{id}/settings']['get'] = self::operation('getTeacherSettings', 'Professores', 'Consultar as preferências do professor', 'TeacherSettings',
            'Escola e fonte padrão usadas nas próximas provas associadas a este professor.', [$id], errors: [404, 422, 503]);
        $paths['/teachers/{id}/settings']['patch'] = self::operation('updateTeacherSettings', 'Professores', 'Editar escola e fonte padrão', 'TeacherSettings',
            'Atualização parcial: campos omitidos são preservados. null remove somente school_name. '
            . 'Fontes devem ser textos em uma linha; tamanho de 8 a 36. As preferências são metadados para apresentação futura. '
            . 'Provas já salvas preservam os valores usados na geração. Corpo máximo: 64 KiB.', [$id],
            body: self::body('TeacherSettingsUpdate', ['settings' => ['summary' => 'Escola e apresentação padrão — dados fictícios',
                'value' => ['school_name' => 'Escola Exemplo', 'default_font_family' => 'Arial', 'default_font_size' => 14]]]),
            errors: [404, 413, 415, 422, 503]);
        $paths['/subjects']['get'] = self::operation('listSubjects', 'Catálogos', 'Consultar as matérias cadastradas', 'SubjectList',
            'Use o code retornado no campo subject da geração. O catálogo não indica por si só que há currículo liberado para a matéria.', errors: [422, 503]);
        $paths['/school-grades']['get'] = self::operation('listSchoolGrades', 'Catálogos', 'Consultar os anos escolares', 'SchoolGradeList',
            'Retorna o 1º ao 9º ano em ordem. Use year_number no campo grade da geração.', errors: [422, 503]);
        $paths['/exams/generate']['post'] = self::operation('generateExam', 'Provas', 'Gerar e salvar uma prova', 'GenerationResult',
            'Valida o pedido, busca o contexto BNCC, chama o provedor e salva prova/gabarito. Retorna exam.id e generation.id. '
            . 'teacher_id é opcional: use o id retornado por POST /teachers para herdar nome, escola e fonte. '
            . 'exam_header e formatting enviados prevalecem sobre o cadastro; null deixa um campo do cabeçalho em branco. '
            . 'Matéria e ano são preenchidos a partir do contexto BNCC. O backend salva uma cópia do cabeçalho e da formatação, sem enviá-los à IA. '
            . 'Corpo máximo: 64 KiB. Erros após o registro incluem error.generation_id. '
            . ($demo ? '**SIMULAÇÃO:** use matematica, grade 6, topics [frações], 3 questões, uma de cada tipo, image_mode none. '
                . 'Alternativas (2–6) e afirmações (1–10) são configuráveis. As questões são fixas; textos livres não são interpretados. Nenhuma chamada de IA.'
                : 'Exige currículo liberado para a matéria e o ano e GEMINI_API_KEY configurada no servidor. A liberação pode ser global ou por matéria/ano. A chave nunca é enviada neste formulário.'),
            body: self::body('ExamRequest', [
                'manual' => ['summary' => 'Três questões: uma de cada tipo', 'value' => self::examExample()],
                'automatic' => ['summary' => 'Distribuição automática (sem question_distribution)',
                    'value' => array_replace(array_diff_key(self::examExample(), ['question_distribution' => true]), ['question_mode' => 'automatic'])],
            ]), errors: [404, 409, 413, 415, 422, 429, 502, 503, 504]);
        $paths['/exams/{id}']['get'] = self::operation('getExam', 'Provas', 'Consultar a prova salva', 'ExamLookup',
            'Use exam.id. Retorna apenas o conteúdo do aluno, sem gabarito ou prompt.', [$id], errors: [404, 422, 503]);
        $paths['/exams/{id}/answer-key']['get'] = self::operation('getAnswerKey', 'Provas', 'Consultar o gabarito', 'AnswerKeyLookup',
            'Use o mesmo exam.id da prova. Respostas ficam em um bloco separado.', [$id], errors: [404, 422, 503]);
        $paths['/generations']['get'] = self::operation('listGenerations', 'Histórico', 'Listar as tentativas recentes', 'GenerationList',
            'Mais recentes primeiro. Passe next_before_id como before_id na página seguinte. A lista não inclui prompts ou respostas.', [
                self::parameter('limit', [...ApiSchemas::integer(1, 100), 'default' => 20]),
                self::parameter('before_id', ApiSchemas::integer()),
                self::parameter('status', ['type' => 'string', 'enum' => ['running', 'succeeded', 'failed']]),
                self::parameter('teacher_id', ApiSchemas::integer()),
            ], errors: [422, 503]);
        $paths['/generations/{id}']['get'] = self::operation('getGeneration', 'Histórico', 'Inspecionar uma tentativa', 'GenerationDetail',
            'Use generation.id (não confundir com exam.id). Inclui pedido, prompt, contexto, resultado ou erro. '
            . 'Somente o proprietário autenticado tem acesso. Uma interrupção pode deixar status running.', [$id], errors: [404, 422, 503]);
        $paths['/bncc/context']['post'] = self::operation('findBnccContext', 'Contexto BNCC', 'Buscar habilidades por matéria, ano e tópicos', 'BnccContext',
            'Consulta sem escrita. Usa apenas uma revisão liberada para a matéria e o ano solicitados, globalmente ou por escopo. Corpo máximo: 8 KiB. '
            . 'Se algum tópico não tiver correspondência lexical, retorna 422 topics_without_matches; currículo não liberado retorna 409.',
            body: self::body('ContextRequest', ['fractions' => ['summary' => 'Frações, 6º ano', 'value' => [
                'subject' => 'matematica', 'grade' => 6, 'topics' => ['frações'], 'limit' => 12,
            ]]]), errors: [404, 409, 415, 422, 503]);
        $upload = ['required' => true, 'content' => ['multipart/form-data' => ['schema' => ApiSchemas::object([
            'pdf' => ['type' => 'string', 'format' => 'binary', 'description' => 'PDF com texto extraível, até 50 MiB.'],
            'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 180, 'default' => 'BNCC — Ensino Fundamental'],
            'version_label' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 80, 'default' => '2018'],
        ])]]];
        $paths['/bncc/documents']['post'] = self::operation('importDocument', 'Documentos BNCC', 'Importar PDF e extrair páginas', 'Document',
            '**Grava dados.** Selecione o arquivo no campo pdf. Não preencha manualmente o Content-Type: o navegador cria o boundary. '
            . 'Salva uma revisão draft; extrair páginas não aprova o currículo. Um mesmo PDF já importado retorna 409. '
            . 'A demonstração começa com currículo sintético e não contém o PDF oficial.', body: $upload, success: 201, errors: [409, 413, 415, 422, 503, 504]);
        $paths['/bncc/documents']['get'] = self::operation('listDocuments', 'Documentos BNCC', 'Listar os documentos importados', 'BnccDocumentList',
            'Retorna os metadados de todas as revisões, mais recentes primeiro, sem texto das páginas ou caminho interno. '
            . 'Um documento draft pode ter matérias e anos já liberados; consulte releases para conhecer a disponibilidade.', errors: [503]);
        $paths['/bncc/documents/{id}']['get'] = self::operation('getDocument', 'Documentos BNCC', 'Consultar o documento', 'Document',
            'Metadados de extração e situação curricular, sem caminho interno do arquivo.', [$id], errors: [404, 422, 503]);
        $paths['/bncc/documents/{id}/pages']['get'] = self::operation('getDocumentPages', 'Documentos BNCC', 'Consultar texto por página', 'Pages',
            'Use next_page como from_page na próxima consulta. Páginas são as do PDF, não os números impressos. '
            . 'O currículo demo não possui um PDF e sua lista de páginas é vazia.', [$id,
                self::parameter('from_page', [...ApiSchemas::integer(), 'default' => 1]),
                self::parameter('limit', [...ApiSchemas::integer(1, 20), 'default' => 5]),
            ], errors: [404, 422, 503]);
        $paths['/bncc/documents/{id}/reprocess']['post'] = self::operation('reprocessDocument', 'Documentos BNCC', 'Reprocessar em nova revisão', 'Document',
            '**Grava dados.** Reutiliza o PDF salvo e cria outra revisão draft. A revisão anterior permanece. Sem corpo. '
            . 'Não se aplica ao currículo demo, que não possui PDF.', [$id], success: 201, errors: [404, 409, 422, 503, 504]);
        $paths['/bncc/documents/{id}/structure']['get'] = self::operation('getStructure', 'Revisão BNCC', 'Ver estrutura e contagens de revisão', 'Structure',
            'Consulta seções, relatório do parser e contagens pending/approved/rejected.', [$id], errors: [404, 422, 503]);
        $paths['/bncc/documents/{id}/structure']['post'] = self::operation('structureDocument', 'Revisão BNCC', 'Estruturar habilidades e competências', 'Structure',
            '**Grava dados.** Interpreta o perfil BNCC 2018 EI/EF em uma revisão draft ainda não estruturada. '
            . 'Os itens entram pendentes de revisão. Não libera automaticamente o documento. Sem corpo.', [$id], errors: [404, 409, 422, 503]);
        $subjectFilter = self::parameter('subject', ['type' => 'string', 'pattern' => '^[a-z][a-z0-9_]{1,39}$'], 'query', 'Código da matéria para revisar somente seu conteúdo.', 'matematica');
        $gradeFilter = self::parameter('grade', ApiSchemas::integer(1, 9), 'query', 'Ano escolar para a revisão selecionada.', 6);
        $paths['/bncc/documents/{id}/skills']['get'] = self::operation('listSkills', 'Revisão BNCC', 'Listar habilidades', 'Skills',
            'Paginação por offset; avance pelo número de itens retornados até total. Os filtros subject e grade restringem habilidades da matéria e do ano.', [$id,
                self::parameter('offset', [...ApiSchemas::integer(0), 'default' => 0]),
                self::parameter('limit', [...ApiSchemas::integer(1, 50), 'default' => 20]),
                $subjectFilter, $gradeFilter,
            ], errors: [404, 422, 503]);
        $skillCode = self::parameter('code', ['type' => 'string', 'pattern' => '^EF[0-9]{2}[A-Z]{2}[0-9]{2}$'], 'path', 'Código da habilidade nesta revisão.', 'EF06MA07');
        $paths['/bncc/documents/{id}/skills/{code}']['get'] = self::operation('getSkill', 'Revisão BNCC', 'Inspecionar habilidade e origens', 'Skill',
            'Inclui anos associados, contextos, trechos e páginas para conferência com a fonte.', [$id, $skillCode], errors: [404, 422, 503]);
        $paths['/bncc/documents/{id}/competencies']['get'] = self::operation('listCompetencies', 'Revisão BNCC', 'Listar competências', 'Competencies',
            'Inclui origens e estado de revisão. subject seleciona somente as competências gerais, da área e do componente aplicáveis à matéria. '
            . 'As competências são compartilhadas entre os anos; grade é aceito de 1 a 9 sem reduzir essa lista. Esta lista não usa paginação.', [$id, $subjectFilter, $gradeFilter], errors: [404, 422, 503]);
        $reviewBody = self::body('ReviewRequest', ['pending' => ['summary' => 'Registrar uma revisão ainda pendente',
            'value' => ['status' => 'pending', 'note' => 'Aguardando conferência com a página de origem.']]]);
        $reviewDescription = '**Altera o item no banco.** Use approved somente após conferir a fonte. '
            . 'note é obrigatório; description opcional permite apenas recompor espaços/hífens. '
            . 'Revisões ready e itens já usados em uma liberação parcial são imutáveis (409). '
            . 'Outros itens de um documento draft continuam editáveis. O currículo demo já está ready. Corpo máximo: 8 KiB.';
        $paths['/bncc/documents/{id}/skills/{code}/review']['patch'] = self::operation('reviewSkill', 'Revisão BNCC', 'Registrar revisão de habilidade', 'ReviewResult',
            $reviewDescription, [$id, $skillCode], body: $reviewBody, errors: [404, 409, 413, 415, 422, 503]);
        $paths['/bncc/documents/{id}/competencies/{competencyId}/review']['patch'] = self::operation('reviewCompetency', 'Revisão BNCC', 'Registrar revisão de competência', 'ReviewResult',
            $reviewDescription, [$id, self::parameter('competencyId', ApiSchemas::integer(), 'path', 'ID da competência retornado na listagem.', 1)],
            body: $reviewBody, errors: [404, 409, 413, 415, 422, 503]);
        $paths['/bncc/documents/{id}/releases']['get'] = self::operation('listCurriculumReleases', 'Revisão BNCC', 'Consultar liberação por matéria e ano', 'BnccReleaseList',
            'Lista os pares matéria/ano presentes nesta revisão e suas contagens de revisão. '
            . 'status ready indica liberação para geração; pending ainda exige conferência ou publicação. '
            . 'Competências gerais e de área podem ser compartilhadas por vários pares.', [$id], errors: [404, 422, 503]);
        $paths['/bncc/documents/{id}/releases']['post'] = self::operation('publishCurriculumScope', 'Revisão BNCC', 'Liberar uma matéria e um ano revisados', 'BnccRelease',
            '**Grava a liberação do par informado.** Exige todas as habilidades da matéria/ano e todas as competências aplicáveis aprovadas e com origens válidas. '
            . 'Os demais pares podem continuar pendentes. O documento permanece draft, e os itens usados por esta liberação ficam imutáveis. '
            . 'Esta operação não aprova itens automaticamente nem substitui sua conferência com o PDF. Corpo máximo: 8 KiB.', [$id],
            body: self::body('BnccReleaseRequest', ['mathematics' => ['summary' => 'Matemática do 6º ano, após revisar os itens',
                'value' => ['subject' => 'matematica', 'grade' => 6]]]), errors: [404, 409, 413, 415, 422, 503]);
        $paths['/bncc/documents/{id}/publish']['post'] = self::operation('publishDocument', 'Revisão BNCC', 'Liberar uma revisão aprovada', 'PublishedDocument',
            '**Altera draft para ready.** Exige o perfil oficial, todas as 1304 habilidades e 94 competências aprovadas e origens completas. '
            . 'Sem corpo. Um documento ready não pode mais ser editado. Esta operação não substitui a revisão pedagógica.', [$id], errors: [404, 409, 422, 503]);
        $paths['/health']['get'] = self::operation('health', 'Saúde', 'Verificar a API', 'Health', 'Não consulta o banco.', errors: []);
        $paths['/health/database']['get'] = self::operation('databaseHealth', 'Saúde', 'Verificar o MySQL', 'Health', 'Executa SELECT 1.', errors: []);
        $paths['/health/database']['get']['responses']['503'] = ['description' => 'Banco indisponível.', 'content' => ['application/json' => [
            'schema' => ApiSchemas::ref('Health'), 'example' => ['status' => 'unavailable'],
        ]]];
        // Snapshot sintético estático: ajuda a ler o formato sem mostrar amostras aleatórias do Swagger.
        $example = json_decode(file_get_contents(__DIR__ . '/../../../config/openapi/simulated-response.json'), true, flags: JSON_THROW_ON_ERROR);
        $example['exam']['header'] = ['school_name' => null, 'teacher_name' => null,
            ...self::examExample()['exam_header'], 'subject' => $example['exam']['subject']['name'], 'grade' => $example['exam']['grade']['name']];
        $example['exam']['formatting'] = self::examExample()['formatting'];
        $example['generation']['request'] += array_intersect_key(self::examExample(), array_flip(['exam_header', 'formatting']));
        foreach ([
            ['/exams/generate', 'post', $example],
            ['/bncc/context', 'post', $example['bncc_context']],
            ['/exams/{id}', 'get', ['exam' => $example['exam'], 'generation_id' => $example['generation']['id'], 'created_at' => '2026-09-30T13:46:52Z']],
            ['/exams/{id}/answer-key', 'get', ['exam_id' => $example['exam']['id'], 'answer_key' => $example['answer_key']]],
            ['/health', 'get', ['status' => 'ok']],
            ['/health/database', 'get', ['status' => 'ok']],
        ] as [$path, $method, $value]) {
            $paths[$path][$method]['responses']['200']['content']['application/json']['examples'] = [
                'sample' => ['summary' => 'Exemplo de formato — dados sintéticos, IDs ilustrativos', 'value' => $value],
            ];
        }
        foreach ($paths as $path => &$operations) {
            foreach ($operations as $method => &$operation) {
                $public = in_array($path, ['/auth/register', '/auth/login', '/health', '/health/database'], true);
                $operation['security'] = $public ? [] : [['cookieAuth' => [], ...($method === 'get' ? [] : ['csrfToken' => []])]];
                if (!$public) {
                    foreach ([401 => 'Sessão ausente ou expirada.', 403 => 'CSRF, origem, Host ou papel administrativo incompatíveis.'] as $status => $description) {
                        $operation['responses'][(string) $status] = ['description' => $description, 'content' => ['application/json' => ['schema' => ApiSchemas::ref('Error')]]];
                    }
                }
                if (str_starts_with($path, '/bncc/documents') && $method !== 'get') {
                    $operation['description'] .= ' **Somente administrador autenticado.**';
                }
            }
            unset($operation);
        }
        unset($operations);
        return ['openapi' => '3.1.0', 'info' => ['title' => 'Gerador de Provas — ' . ($demo ? 'Simulação' : 'API principal'), 'version' => '1.2.0',
            'description' => ($demo ? 'Ambiente separado com FakeAIProvider e currículo sintético. Comece por **Provas → POST /exams/generate**.'
                : 'Backend do Ensino Fundamental. As operações usam os dados locais e Gemini quando o currículo está liberado e a chave configurada.')
                . "\n\nCadastre-se ou entre em Autenticação antes das operações de negócio. A API valida a sessão por cookie HttpOnly e exige CSRF nas escritas. Importação e revisão BNCC exigem administrador. Cada Execute faz uma chamada HTTP real. Horários são UTC. A validação BNCC não substitui a revisão do professor."],
            'servers' => [['url' => '/', 'description' => 'Mesmo host e porta desta página']],
            'tags' => array_map(static fn (string $name): array => ['name' => $name], ['Autenticação', 'Professores', 'Catálogos', 'Provas', 'Histórico', 'Contexto BNCC', 'Documentos BNCC', 'Revisão BNCC', 'Saúde']),
            'paths' => $paths, 'components' => ['schemas' => $schemas, 'securitySchemes' => [
                'cookieAuth' => ['type' => 'apiKey', 'in' => 'cookie', 'name' => $demo ? 'prova_clara_demo_session' : 'prova_clara_session', 'description' => 'Cookie HttpOnly emitido por login/cadastro. Não cole tokens manualmente.'],
                'csrfToken' => ['type' => 'apiKey', 'in' => 'header', 'name' => 'X-CSRF-Token', 'description' => 'Token de GET /auth/session; preenchido pelo inicializador local do Swagger.'],
            ]]];
    }

    private static function parameter(string $name, array $schema, string $in = 'query', string $description = '', mixed $example = null): array
    {
        return ['name' => $name, 'in' => $in, 'required' => $in === 'path', 'schema' => $schema, 'description' => $description]
            + ($example === null ? [] : ['example' => $example]);
    }

    private static function body(string $schema, array $examples): array
    {
        return ['required' => true, 'content' => ['application/json' => ['schema' => ApiSchemas::ref($schema), 'examples' => $examples]]];
    }

    private static function operation(string $id, string $tag, string $summary, string $schema, string $description,
        array $parameters = [], ?array $body = null, int $success = 200, array $errors = [422, 503]): array
    {
        $responses = [(string) $success => ['description' => $success === 201 ? 'Registro criado.' : 'Operação concluída.',
            'content' => ['application/json' => ['schema' => ApiSchemas::ref($schema)]]]];
        if ($success === 201) {
            $responses['201']['headers']['Location'] = ['description' => 'Caminho do registro criado neste ambiente.', 'schema' => ['type' => 'string']];
        }
        foreach ($errors as $status) {
            $responses[(string) $status] = ['description' => match ($status) {
                401 => 'Credenciais ou sessão inválidas.', 403 => 'Origem, Host, CSRF ou permissão incompatíveis.',
                404 => 'Registro não encontrado neste ambiente.', 409 => 'Conflito, e-mail já cadastrado ou currículo ainda não liberado.',
                413 => 'Envio maior que o limite.', 415 => 'Content-Type incompatível.', 422 => 'Entrada inválida, tópico sem correspondência ou recusa do provedor.',
                429 => 'Limite de acesso, geração ou fornecedor atingido.', 502 => 'Falha do provedor ou resposta de prova inválida.',
                503 => 'Banco/provedor indisponível ou IA sem configuração.', 504 => 'Tempo limite excedido.',
            }, 'content' => ['application/json' => ['schema' => ApiSchemas::ref('Error')]]];
            if (in_array($status, [429, 503], true)) {
                $responses[(string) $status]['headers']['Retry-After'] = ['description' => 'Segundos, quando informados pelo provedor.', 'schema' => ['type' => 'integer', 'minimum' => 0]];
            }
        }
        $responses['500'] = ['description' => 'Falha interna controlada.', 'content' => ['application/json' => ['schema' => ApiSchemas::ref('Error')]]];
        $operation = ['operationId' => $id, 'tags' => [$tag], 'summary' => $summary, 'description' => $description, 'responses' => $responses];
        if ($parameters !== []) {
            $operation['parameters'] = $parameters;
        }
        if ($body !== null) {
            $operation['requestBody'] = $body;
        }
        return $operation;
    }
}
