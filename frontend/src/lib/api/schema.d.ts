// Gerado pelo contrato OpenAPI do backend. Execute npm run api:types para atualizar.
export interface paths {
    "/teachers": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /**
         * Listar os professores
         * @description Mais recentes primeiro. Use next_before_id como before_id na próxima página.
         */
        get: operations["listTeachers"];
        put?: never;
        /**
         * Cadastrar um professor
         * @description Cria o professor e suas preferências padrão juntos: escola em branco, Arial, tamanho 12. Copie o id retornado para as próximas operações. Não há autenticação. Corpo máximo: 64 KiB. O e-mail é opcional e único; use null ou omita quando não precisar cadastrá-lo.
         */
        post: operations["createTeacher"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/teachers/{id}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /**
         * Consultar professor e preferências
         * @description Use o id do professor cadastrado neste ambiente. Inclui as preferências atuais.
         */
        get: operations["getTeacher"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        /**
         * Editar os dados do professor
         * @description Altera somente os campos enviados. Informe name ou email; null remove o e-mail. O nome não aceita null nem texto vazio. Alterações no cadastro não modificam provas já salvas. Corpo máximo: 64 KiB.
         */
        patch: operations["updateTeacher"];
        trace?: never;
    };
    "/teachers/{id}/settings": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /**
         * Consultar as preferências do professor
         * @description Escola e fonte padrão usadas nas próximas provas associadas a este professor.
         */
        get: operations["getTeacherSettings"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        /**
         * Editar escola e fonte padrão
         * @description Atualização parcial: campos omitidos são preservados. null remove somente school_name. Fontes devem ser textos em uma linha; tamanho de 8 a 36. As preferências são metadados para apresentação futura. Provas já salvas preservam os valores usados na geração. Corpo máximo: 64 KiB.
         */
        patch: operations["updateTeacherSettings"];
        trace?: never;
    };
    "/subjects": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /**
         * Consultar as matérias cadastradas
         * @description Use o code retornado no campo subject da geração. O catálogo não indica por si só que há currículo liberado para a matéria.
         */
        get: operations["listSubjects"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/school-grades": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /**
         * Consultar os anos escolares
         * @description Retorna o 1º ao 9º ano em ordem. Use year_number no campo grade da geração.
         */
        get: operations["listSchoolGrades"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/exams/generate": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /**
         * Gerar e salvar uma prova
         * @description Valida o pedido, busca o contexto BNCC, chama o provedor e salva prova/gabarito. Retorna exam.id e generation.id. teacher_id é opcional: use o id retornado por POST /teachers para herdar nome, escola e fonte. exam_header e formatting enviados prevalecem sobre o cadastro; null deixa um campo do cabeçalho em branco. Matéria e ano são preenchidos a partir do contexto BNCC. O backend salva uma cópia do cabeçalho e da formatação, sem enviá-los à IA. Corpo máximo: 64 KiB. Erros após o registro incluem error.generation_id. Exige currículo liberado para a matéria e o ano e GEMINI_API_KEY configurada no servidor. A liberação pode ser global ou por matéria/ano. A chave nunca é enviada neste formulário.
         */
        post: operations["generateExam"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/exams/{id}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /**
         * Consultar a prova salva
         * @description Use exam.id. Retorna apenas o conteúdo do aluno, sem gabarito ou prompt.
         */
        get: operations["getExam"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/exams/{id}/answer-key": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /**
         * Consultar o gabarito
         * @description Use o mesmo exam.id da prova. Respostas ficam em um bloco separado.
         */
        get: operations["getAnswerKey"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/generations": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /**
         * Listar as tentativas recentes
         * @description Mais recentes primeiro. Passe next_before_id como before_id na página seguinte. A lista não inclui prompts ou respostas.
         */
        get: operations["listGenerations"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/generations/{id}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /**
         * Inspecionar uma tentativa
         * @description Use generation.id (não confundir com exam.id). Inclui pedido, prompt, contexto, resultado ou erro. teacher_id é declarado, não autenticado. Uma interrupção pode deixar status running.
         */
        get: operations["getGeneration"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/bncc/context": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /**
         * Buscar habilidades por matéria, ano e tópicos
         * @description Consulta sem escrita. Usa apenas uma revisão liberada para a matéria e o ano solicitados, globalmente ou por escopo. Corpo máximo: 8 KiB. Se algum tópico não tiver correspondência lexical, retorna 422 topics_without_matches; currículo não liberado retorna 409.
         */
        post: operations["findBnccContext"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/bncc/documents": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /**
         * Listar os documentos importados
         * @description Retorna os metadados de todas as revisões, mais recentes primeiro, sem texto das páginas ou caminho interno. Um documento draft pode ter matérias e anos já liberados; consulte releases para conhecer a disponibilidade.
         */
        get: operations["listDocuments"];
        put?: never;
        /**
         * Importar PDF e extrair páginas
         * @description **Grava dados.** Selecione o arquivo no campo pdf. Não preencha manualmente o Content-Type: o navegador cria o boundary. Salva uma revisão draft; extrair páginas não aprova o currículo. Um mesmo PDF já importado retorna 409. A demonstração começa com currículo sintético e não contém o PDF oficial.
         */
        post: operations["importDocument"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/bncc/documents/{id}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /**
         * Consultar o documento
         * @description Metadados de extração e situação curricular, sem caminho interno do arquivo.
         */
        get: operations["getDocument"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/bncc/documents/{id}/pages": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /**
         * Consultar texto por página
         * @description Use next_page como from_page na próxima consulta. Páginas são as do PDF, não os números impressos. O currículo demo não possui um PDF e sua lista de páginas é vazia.
         */
        get: operations["getDocumentPages"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/bncc/documents/{id}/reprocess": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /**
         * Reprocessar em nova revisão
         * @description **Grava dados.** Reutiliza o PDF salvo e cria outra revisão draft. A revisão anterior permanece. Sem corpo. Não se aplica ao currículo demo, que não possui PDF.
         */
        post: operations["reprocessDocument"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/bncc/documents/{id}/structure": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /**
         * Ver estrutura e contagens de revisão
         * @description Consulta seções, relatório do parser e contagens pending/approved/rejected.
         */
        get: operations["getStructure"];
        put?: never;
        /**
         * Estruturar habilidades e competências
         * @description **Grava dados.** Interpreta o perfil BNCC 2018 EI/EF em uma revisão draft ainda não estruturada. Os itens entram pendentes de revisão. Não libera automaticamente o documento. Sem corpo.
         */
        post: operations["structureDocument"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/bncc/documents/{id}/skills": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /**
         * Listar habilidades
         * @description Paginação por offset; avance pelo número de itens retornados até total. Os filtros subject e grade restringem habilidades da matéria e do ano.
         */
        get: operations["listSkills"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/bncc/documents/{id}/skills/{code}": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /**
         * Inspecionar habilidade e origens
         * @description Inclui anos associados, contextos, trechos e páginas para conferência com a fonte.
         */
        get: operations["getSkill"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/bncc/documents/{id}/competencies": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /**
         * Listar competências
         * @description Inclui origens e estado de revisão. subject seleciona somente as competências gerais, da área e do componente aplicáveis à matéria. As competências são compartilhadas entre os anos; grade é aceito de 1 a 9 sem reduzir essa lista. Esta lista não usa paginação.
         */
        get: operations["listCompetencies"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/bncc/documents/{id}/skills/{code}/review": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        /**
         * Registrar revisão de habilidade
         * @description **Altera o item no banco.** Use approved somente após conferir a fonte. note é obrigatório; description opcional permite apenas recompor espaços/hífens. Revisões ready e itens já usados em uma liberação parcial são imutáveis (409). Outros itens de um documento draft continuam editáveis. O currículo demo já está ready. Corpo máximo: 8 KiB.
         */
        patch: operations["reviewSkill"];
        trace?: never;
    };
    "/bncc/documents/{id}/competencies/{competencyId}/review": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        /**
         * Registrar revisão de competência
         * @description **Altera o item no banco.** Use approved somente após conferir a fonte. note é obrigatório; description opcional permite apenas recompor espaços/hífens. Revisões ready e itens já usados em uma liberação parcial são imutáveis (409). Outros itens de um documento draft continuam editáveis. O currículo demo já está ready. Corpo máximo: 8 KiB.
         */
        patch: operations["reviewCompetency"];
        trace?: never;
    };
    "/bncc/documents/{id}/releases": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /**
         * Consultar liberação por matéria e ano
         * @description Lista os pares matéria/ano presentes nesta revisão e suas contagens de revisão. status ready indica liberação para geração; pending ainda exige conferência ou publicação. Competências gerais e de área podem ser compartilhadas por vários pares.
         */
        get: operations["listCurriculumReleases"];
        put?: never;
        /**
         * Liberar uma matéria e um ano revisados
         * @description **Grava a liberação do par informado.** Exige todas as habilidades da matéria/ano e todas as competências aplicáveis aprovadas e com origens válidas. Os demais pares podem continuar pendentes. O documento permanece draft, e os itens usados por esta liberação ficam imutáveis. Esta operação não aprova itens automaticamente nem substitui sua conferência com o PDF. Corpo máximo: 8 KiB.
         */
        post: operations["publishCurriculumScope"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/bncc/documents/{id}/publish": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        get?: never;
        put?: never;
        /**
         * Liberar uma revisão aprovada
         * @description **Altera draft para ready.** Exige o perfil oficial, todas as 1304 habilidades e 94 competências aprovadas e origens completas. Sem corpo. Um documento ready não pode mais ser editado. Esta operação não substitui a revisão pedagógica.
         */
        post: operations["publishDocument"];
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/health": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /**
         * Verificar a API
         * @description Não consulta o banco.
         */
        get: operations["health"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
    "/health/database": {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        /**
         * Verificar o MySQL
         * @description Executa SELECT 1.
         */
        get: operations["databaseHealth"];
        put?: never;
        post?: never;
        delete?: never;
        options?: never;
        head?: never;
        patch?: never;
        trace?: never;
    };
}
export type webhooks = Record<string, never>;
export interface components {
    schemas: {
        Health: {
            /** @enum {string} */
            status: "ok" | "unavailable";
        };
        Error: {
            error: {
                code: string;
                message: string;
                /** @description Presente quando a tentativa foi registrada no histórico. */
                generation_id?: number;
                retryable?: boolean;
                retry_after_seconds?: number | null;
                unmatched_topics?: string[];
            } & {
                [key: string]: unknown;
            };
        };
        ContextRequest: {
            /**
             * @description Código da matéria cadastrada. Ex.: matematica, lingua_portuguesa, ciencias.
             * @example matematica
             */
            subject: string;
            grade: number;
            /** @description Tópicos não vazios; a busca normaliza palavras e acentos, sem interpretar sinônimos. */
            topics: string[];
            document_id?: number;
            /**
             * @description Pelo menos o número de tópicos distintos.
             * @default 12
             */
            limit?: number;
        };
        ExamRequest: {
            /**
             * @description Código da matéria cadastrada. Ex.: matematica, lingua_portuguesa, ciencias.
             * @example matematica
             */
            subject: string;
            grade: number;
            /** @description Tópicos não vazios; a busca normaliza palavras e acentos, sem interpretar sinônimos. */
            topics: string[];
            /** @description Opcional. Revisão específica liberada para a matéria e o ano solicitados. Omitir seleciona a revisão liberada mais recente para esse par. Não envie null. */
            bncc_document_id?: number;
            /**
             * @description Deve ser pelo menos o número de tópicos distintos.
             * @default 12
             */
            context_limit?: number;
            question_count: number;
            /**
             * @default automatic
             * @enum {string}
             */
            question_mode?: "automatic" | "manual";
            /** @description Obrigatório no modo manual; a soma deve ser question_count. No automático, omita este campo por completo. */
            question_distribution?: {
                multiple_choice: number;
                open_ended: number;
                true_false: number;
            };
            /** @default 4 */
            alternatives_count?: number;
            /** @default 1 */
            true_false_statements?: number;
            /**
             * @description Solicita descrições de imagens; esta API ainda não gera arquivos de imagem.
             * @default none
             * @enum {string}
             */
            image_mode?: "none" | "required" | "auto";
            /**
             * @description Instruções em linguagem natural. Até 8192 bytes UTF-8; permite tabulação e quebras de linha.
             * @default
             */
            teacher_notes?: string;
            /**
             * @description Referência de estilo, linguagem e extensão dos enunciados. Até 16384 bytes UTF-8; permite tabulação e quebras de linha.
             * @default
             */
            reference_text?: string;
            /** @description Opcional. Deve existir em teachers. Preenche nome, escola e fontes a partir do cadastro. Associação declarada, sem autenticação. */
            teacher_id?: number | null;
            /** @description Opcional. Os campos enviados prevalecem sobre o cadastro; null deixa o campo em branco. Matéria e ano vêm da consulta curricular. Os dados do cabeçalho são adicionados pelo backend e não são enviados à IA. */
            exam_header?: {
                school_name?: string | null;
                teacher_name?: string | null;
                student_name?: string | null;
                class_name?: string | null;
                date?: string | null;
            };
            /** @description Opcional. Valores enviados prevalecem sobre as preferências do professor. Sem professor, os padrões são Arial e 12. Metadados para futura apresentação; não gera PDF. */
            formatting?: {
                /** @description Texto UTF-8 em uma linha; espaços nas extremidades são removidos. */
                font_family?: string;
                font_size?: number;
            };
        } & ({
            /** @constant */
            question_mode: "manual";
        } | {
            /** @constant */
            question_mode?: "automatic";
        });
        ReviewRequest: {
            /** @enum {string} */
            status: "pending" | "approved" | "rejected";
            note: string;
            /** @description Opcional: somente ajustes de espaços/hífens, preservando o conteúdo da fonte. */
            description?: string;
        };
        ReviewResult: {
            id: number;
            description: string;
            /** @enum {string} */
            review_status: "pending" | "approved" | "rejected";
            reviewed_at: string | null;
            review_note: string | null;
        };
        Subject: {
            id: number;
            code: string;
            name: string;
        };
        Grade: {
            id: number;
            year_number: number;
            name: string;
            education_stage: string;
        };
        SubjectList: {
            items: components["schemas"]["Subject"][];
        };
        SchoolGrade: {
            id: number;
            code: string;
            name: string;
            education_stage: string;
            year_number: number;
        };
        SchoolGradeList: {
            items: components["schemas"]["SchoolGrade"][];
        };
        TeacherCreate: {
            /** @description Texto UTF-8 em uma linha; espaços nas extremidades são removidos. */
            name: string;
            email?: string | null;
        };
        TeacherUpdate: {
            /** @description Texto UTF-8 em uma linha; espaços nas extremidades são removidos. */
            name?: string;
            email?: string | null;
        };
        TeacherSettings: {
            school_name: string | null;
            /** @description Texto UTF-8 em uma linha; espaços nas extremidades são removidos. */
            default_font_family: string;
            /** @description Valor preservado no banco ou herdado do cadastro. Dados legados podem ficar fora de 8 a 36; novos valores enviados pela API devem respeitar essa faixa. */
            default_font_size: number;
        };
        TeacherSettingsUpdate: {
            school_name?: string | null;
            /** @description Texto UTF-8 em uma linha; espaços nas extremidades são removidos. */
            default_font_family?: string;
            default_font_size?: number;
        };
        Teacher: {
            id: number;
            /** @description Texto UTF-8 em uma linha; espaços nas extremidades são removidos. */
            name: string;
            email: string | null;
            settings: components["schemas"]["TeacherSettings"];
            /** Format: date-time */
            created_at: string;
            /** Format: date-time */
            updated_at: string;
        };
        TeacherList: {
            items: components["schemas"]["Teacher"][];
            next_before_id: number | null;
        };
        ExamHeaderInput: {
            school_name?: string | null;
            teacher_name?: string | null;
            student_name?: string | null;
            class_name?: string | null;
            date?: string | null;
        };
        ExamHeader: {
            school_name: string | null;
            teacher_name: string | null;
            student_name: string | null;
            class_name: string | null;
            date: string | null;
            subject: string;
            grade: string;
        };
        ExamFormatting: {
            /** @description Texto UTF-8 em uma linha; espaços nas extremidades são removidos. */
            font_family: string;
            /** @description Valor preservado no banco ou herdado do cadastro. Dados legados podem ficar fora de 8 a 36; novos valores enviados pela API devem respeitar essa faixa. */
            font_size: number;
        };
        Cost: {
            /** @description Decimal como texto; null significa desconhecido. */
            amount: string | null;
            /** @constant */
            currency: "USD";
            /** @enum {string} */
            basis: "simulated" | "usage_unavailable" | "pricing_not_configured" | "configured_token_rates";
            rates?: {
                input_usd_per_million: number | string;
                output_usd_per_million: number | string;
            };
            provider?: string;
            model?: string;
        };
        ExamContent: {
            title: string;
            instructions: string;
            questions: ({
                number: number;
                statement: string;
                bncc_skills: string[];
                image_description: string | null;
                /** @enum {string} */
                type: "multiple_choice";
                alternatives: {
                    /** @enum {string} */
                    key: "A" | "B" | "C" | "D" | "E" | "F";
                    text: string;
                }[];
            } | {
                number: number;
                statement: string;
                bncc_skills: string[];
                image_description: string | null;
                /** @enum {string} */
                type: "open_ended";
            } | {
                number: number;
                statement: string;
                bncc_skills: string[];
                image_description: string | null;
                /** @enum {string} */
                type: "true_false";
                statements: {
                    number: number;
                    text: string;
                }[];
            })[];
            subject: components["schemas"]["Subject"];
            grade: components["schemas"]["Grade"];
            /** @description Tópicos não vazios; a busca normaliza palavras e acentos, sem interpretar sinônimos. */
            topics: string[];
            header?: components["schemas"]["ExamHeader"];
            formatting?: components["schemas"]["ExamFormatting"];
        };
        SavedExam: {
            title: string;
            instructions: string;
            questions: ({
                number: number;
                statement: string;
                bncc_skills: string[];
                image_description: string | null;
                /** @enum {string} */
                type: "multiple_choice";
                alternatives: {
                    /** @enum {string} */
                    key: "A" | "B" | "C" | "D" | "E" | "F";
                    text: string;
                }[];
            } | {
                number: number;
                statement: string;
                bncc_skills: string[];
                image_description: string | null;
                /** @enum {string} */
                type: "open_ended";
            } | {
                number: number;
                statement: string;
                bncc_skills: string[];
                image_description: string | null;
                /** @enum {string} */
                type: "true_false";
                statements: {
                    number: number;
                    text: string;
                }[];
            })[];
            subject: components["schemas"]["Subject"];
            grade: components["schemas"]["Grade"];
            /** @description Tópicos não vazios; a busca normaliza palavras e acentos, sem interpretar sinônimos. */
            topics: string[];
            header?: components["schemas"]["ExamHeader"];
            formatting?: components["schemas"]["ExamFormatting"];
            id: number;
        };
        AnswerKey: ({
            question_number: number;
            /** @enum {string} */
            type: "multiple_choice";
            /** @enum {string} */
            correct_alternative: "A" | "B" | "C" | "D" | "E" | "F";
        } | {
            question_number: number;
            /** @enum {string} */
            type: "open_ended";
            expected_answer: string;
            grading_criteria: string[];
        } | {
            question_number: number;
            /** @enum {string} */
            type: "true_false";
            answers: {
                statement_number: number;
                correct: boolean;
            }[];
        })[];
        ContextDocument: {
            id: number;
            title: string;
            version_label: string;
            revision: number;
            sha256: string;
            page_count: number;
            /** @enum {string} */
            status: "draft" | "ready" | "archived";
            ready_at: string | null;
            structured_at: string | null;
            structure_profile: string | null;
            release?: {
                id: number;
                /**
                 * @description Código da matéria cadastrada. Ex.: matematica, lingua_portuguesa, ciencias.
                 * @example matematica
                 */
                subject: string;
                grade: number;
                /** @description Data UTC no formato YYYY-MM-DD HH:MM:SS. */
                released_at: string;
            };
        };
        Document: {
            id: number;
            title: string;
            version_label: string;
            revision: number;
            sha256: string;
            page_count: number;
            /** @enum {string} */
            status: "draft" | "ready" | "archived";
            ready_at: string | null;
            structured_at: string | null;
            structure_profile: string | null;
            original_filename: string;
            /** @enum {string} */
            curriculum_status: "ready" | "awaiting_review" | "awaiting_structuring_and_review";
            /** @description Data UTC no formato YYYY-MM-DD HH:MM:SS. */
            created_at: string;
            extracted_at: string | null;
            extractor_version: string | null;
            extraction_warnings: (Record<string, never> | unknown[]) | null;
            structure_report: (Record<string, never> | unknown[]) | null;
        } & {
            [key: string]: unknown;
        };
        BnccDocumentList: {
            items: components["schemas"]["Document"][];
        };
        Pages: {
            document_id: number;
            total_pages: number;
            pages: {
                page_number: number;
                source_text: string;
                detected_skill_codes: string[];
            }[];
            next_page: number | null;
        };
        Source: {
            id: number;
            section_id: number;
            page_start: number;
            page_end: number;
            printed_page_label: string | null;
            source_excerpt: string;
            thematic_unit: string | null;
            knowledge_objects: string | null;
            description_page: number | null;
            language_practice: string | null;
            field_of_activity: string | null;
        };
        ContextCompetency: {
            id: number;
            /** @enum {string} */
            scope: "general" | "area" | "component";
            area_id: number | null;
            component_id: number | null;
            number: number;
            description: string;
            source_section_id: number;
            page_start: number;
            page_end: number;
            source_excerpt: string;
        };
        BnccContext: {
            /** @constant */
            schema_version: "bncc-context-v1";
            document: components["schemas"]["ContextDocument"];
            subject: components["schemas"]["Subject"];
            grade: components["schemas"]["Grade"];
            area: {
                id: number;
                code: string;
                name: string;
            };
            /** @description Tópicos não vazios; a busca normaliza palavras e acentos, sem interpretar sinônimos. */
            topics: string[];
            skills: {
                id: number;
                /** @example EF06MA07 */
                code: string;
                description: string;
                grades: number[];
                sources: components["schemas"]["Source"][];
                matches: {
                    topic: string;
                    fields: string[];
                }[];
            }[];
            competencies: components["schemas"]["ContextCompetency"][];
            selection: {
                /** @constant */
                method: "lexical-v1";
                limit: number;
                total_matches: number;
                returned_skills: number;
                truncated: boolean;
                /** @constant */
                all_topics_covered: true;
            };
        };
        Skills: {
            document_id: number;
            total: number;
            offset: number;
            limit: number;
            skills: {
                id: number;
                description: string;
                /** @enum {string} */
                review_status: "pending" | "approved" | "rejected";
                reviewed_at: string | null;
                review_note: string | null;
                /** @example EF06MA07 */
                code: string;
                /** @description true quando o item está imutável: documento ready/archived ou conteúdo compartilhado por alguma matéria/ano já liberados. */
                locked: boolean;
            }[];
        };
        Skill: {
            id: number;
            description: string;
            /** @enum {string} */
            review_status: "pending" | "approved" | "rejected";
            reviewed_at: string | null;
            review_note: string | null;
            /** @example EF06MA07 */
            code: string;
            /** @description true quando o item está imutável: documento ready/archived ou conteúdo compartilhado por alguma matéria/ano já liberados. */
            locked: boolean;
            document_id: number;
            component_id: number;
            grades: number[];
            subject: {
                code: string;
                name: string;
            };
            contexts: ({
                id: number;
                document_id: number;
                skill_id: number;
                section_id: number;
                page_start: number;
                page_end: number;
                source_excerpt: string;
                context_metadata: (Record<string, never> | unknown[]) | null;
            } & {
                [key: string]: unknown;
            })[];
        } & {
            [key: string]: unknown;
        };
        Competencies: {
            document_id: number;
            competencies: ({
                id: number;
                /** @enum {string} */
                scope: "general" | "area" | "component";
                area_id: number | null;
                component_id: number | null;
                number: number;
                description: string;
                source_section_id: number;
                page_start: number;
                page_end: number;
                source_excerpt: string;
                /** @enum {string} */
                review_status: "pending" | "approved" | "rejected";
                reviewed_at: string | null;
                review_note: string | null;
                document_id: number;
                source_metadata: (Record<string, never> | unknown[]) | null;
                /** @description true quando o item está imutável: documento ready/archived ou conteúdo compartilhado por alguma matéria/ano já liberados. */
                locked: boolean;
            } & {
                [key: string]: unknown;
            })[];
        };
        BnccReleaseSummary: {
            subject: components["schemas"]["Subject"];
            grade: components["schemas"]["Grade"];
            /** @enum {string} */
            status: "pending" | "ready";
            released_at: string | null;
            review: {
                skills: {
                    pending: number;
                    approved: number;
                    rejected: number;
                    total: number;
                };
                competencies: {
                    pending: number;
                    approved: number;
                    rejected: number;
                    total: number;
                };
            };
        };
        BnccRelease: {
            document_id: number;
            subject: components["schemas"]["Subject"];
            grade: components["schemas"]["Grade"];
            /** @enum {string} */
            status: "pending" | "ready";
            released_at: string | null;
            review: {
                skills: {
                    pending: number;
                    approved: number;
                    rejected: number;
                    total: number;
                };
                competencies: {
                    pending: number;
                    approved: number;
                    rejected: number;
                    total: number;
                };
            };
        };
        BnccReleaseList: {
            document_id: number;
            /** @enum {string} */
            status: "draft" | "ready" | "archived";
            items: components["schemas"]["BnccReleaseSummary"][];
        };
        BnccReleaseRequest: {
            /**
             * @description Código da matéria cadastrada. Ex.: matematica, lingua_portuguesa, ciencias.
             * @example matematica
             */
            subject: string;
            grade: number;
        };
        Structure: {
            document_id: number;
            /** @enum {string} */
            status: "draft" | "ready" | "archived";
            structure_profile: string | null;
            structured_at: string | null;
            report: (Record<string, never> | unknown[]) | null;
            review: {
                skills: {
                    pending: number;
                    approved: number;
                    rejected: number;
                    total: number;
                };
                competencies: {
                    pending: number;
                    approved: number;
                    rejected: number;
                    total: number;
                };
            };
            sections: {
                id: number;
                parent_id: number | null;
                section_key: string;
                title: string;
                page_start: number;
                page_end: number;
            }[];
        };
        PublishedDocument: {
            document_id: number;
            /** @constant */
            status: "ready";
        };
        BnccValidation: {
            /** @constant */
            status: "passed";
            /** @constant */
            database_rechecked: true;
            version: string;
            document_id: number;
            document_revision: number;
            document_sha256: string;
            context_sha256: string;
            questions: {
                question_number: number;
                skill_ids: number[];
            }[];
            skills: {
                id: number;
                /** @example EF06MA07 */
                code: string;
                question_numbers: number[];
                source_ids: number[];
            }[];
            topic_references: {
                topic: string;
                skill_codes: string[];
                question_numbers: number[];
            }[];
            unused_context_skill_codes: string[];
            /** @constant */
            semantic_review: "not_performed";
            /** @constant */
            teacher_review_required: true;
            curriculum_release?: {
                id: number;
                /**
                 * @description Código da matéria cadastrada. Ex.: matematica, lingua_portuguesa, ciencias.
                 * @example matematica
                 */
                subject: string;
                grade: number;
                /** @description Data UTC no formato YYYY-MM-DD HH:MM:SS. */
                released_at: string;
            };
        };
        NormalizedRequest: {
            /**
             * @description Código da matéria cadastrada. Ex.: matematica, lingua_portuguesa, ciencias.
             * @example matematica
             */
            subject: string;
            grade: number;
            /** @description Tópicos não vazios; a busca normaliza palavras e acentos, sem interpretar sinônimos. */
            topics: string[];
            question_count: number;
            /**
             * @default automatic
             * @enum {string}
             */
            question_mode: "automatic" | "manual";
            question_distribution: {
                multiple_choice: number;
                open_ended: number;
                true_false: number;
            } | null;
            /** @default 4 */
            alternatives_count: number;
            /** @default 1 */
            true_false_statements: number;
            /**
             * @description Solicita descrições de imagens; esta API ainda não gera arquivos de imagem.
             * @default none
             * @enum {string}
             */
            image_mode: "none" | "required" | "auto";
            /**
             * @description Instruções em linguagem natural. Até 8192 bytes UTF-8; permite tabulação e quebras de linha.
             * @default
             */
            teacher_notes: string;
            /**
             * @description Referência de estilo, linguagem e extensão dos enunciados. Até 16384 bytes UTF-8; permite tabulação e quebras de linha.
             * @default
             */
            reference_text: string;
            /** @description Opcional. Os campos enviados prevalecem sobre o cadastro; null deixa o campo em branco. Matéria e ano vêm da consulta curricular. Os dados do cabeçalho são adicionados pelo backend e não são enviados à IA. */
            exam_header?: {
                school_name?: string | null;
                teacher_name?: string | null;
                student_name?: string | null;
                class_name?: string | null;
                date?: string | null;
            };
            /** @description Opcional. Valores enviados prevalecem sobre as preferências do professor. Sem professor, os padrões são Arial e 12. Metadados para futura apresentação; não gera PDF. */
            formatting?: {
                /** @description Texto UTF-8 em uma linha; espaços nas extremidades são removidos. */
                font_family?: string;
                font_size?: number;
            };
        };
        GenerationResult: {
            exam: components["schemas"]["SavedExam"];
            answer_key: components["schemas"]["AnswerKey"];
            bncc_context: components["schemas"]["BnccContext"];
            generation: {
                schema_name: string;
                prompt_version: string;
                provider: string;
                model: string;
                duration_ms: number;
                input_tokens: number | null;
                output_tokens: number | null;
                provider_request_id: string | null;
                request: components["schemas"]["NormalizedRequest"];
                bncc_validation: components["schemas"]["BnccValidation"];
                estimated_cost: components["schemas"]["Cost"];
                id: number;
                exam_id: number;
                total_duration_ms: number;
                /** @constant */
                status: "succeeded";
            };
        };
        ExamLookup: {
            exam: components["schemas"]["SavedExam"];
            generation_id: number;
            /** Format: date-time */
            created_at: string;
        };
        AnswerKeyLookup: {
            exam_id: number;
            answer_key: components["schemas"]["AnswerKey"];
        };
        GenerationSummary: {
            id: number;
            teacher_id: number | null;
            requester_name: string | null;
            /** @constant */
            requester_verified: false;
            /** @enum {string} */
            status: "running" | "succeeded" | "failed";
            stage: string;
            /** Format: date-time */
            started_at: string;
            finished_at: string | null;
            subject: string;
            grade: number;
            question_count: number;
            /** @description Tópicos não vazios; a busca normaliza palavras e acentos, sem interpretar sinônimos. */
            topics: string[];
            provider: string | null;
            requested_model: string | null;
            model: string | null;
            bncc_document_id: number | null;
            bncc_revision: number | null;
            input_tokens: number | null;
            output_tokens: number | null;
            provider_duration_ms: number | null;
            total_duration_ms: number | null;
            estimated_cost: components["schemas"]["Cost"] | null;
            error_code: string | null;
            exam_id: number | null;
        };
        GenerationList: {
            items: components["schemas"]["GenerationSummary"][];
            next_before_id: number | null;
        };
        GenerationDetail: {
            id: number;
            teacher_id: number | null;
            requester_name: string | null;
            /** @constant */
            requester_verified: false;
            /** @enum {string} */
            status: "running" | "succeeded" | "failed";
            stage: string;
            /** Format: date-time */
            started_at: string;
            finished_at: string | null;
            subject: string;
            grade: number;
            question_count: number;
            provider: string | null;
            requested_model: string | null;
            model: string | null;
            bncc_document_id: number | null;
            bncc_revision: number | null;
            input_tokens: number | null;
            output_tokens: number | null;
            provider_duration_ms: number | null;
            total_duration_ms: number | null;
            estimated_cost: components["schemas"]["Cost"] | null;
            error_code: string | null;
            exam_id: number | null;
            error_message: string | null;
            response_sha256: string | null;
            provider_request_id: string | null;
            request: {
                /**
                 * @description Código da matéria cadastrada. Ex.: matematica, lingua_portuguesa, ciencias.
                 * @example matematica
                 */
                subject: string;
                grade: number;
                /** @description Tópicos não vazios; a busca normaliza palavras e acentos, sem interpretar sinônimos. */
                topics: string[];
                question_count: number;
                /**
                 * @default automatic
                 * @enum {string}
                 */
                question_mode: "automatic" | "manual";
                question_distribution: {
                    multiple_choice: number;
                    open_ended: number;
                    true_false: number;
                } | null;
                /** @default 4 */
                alternatives_count: number;
                /** @default 1 */
                true_false_statements: number;
                /**
                 * @description Solicita descrições de imagens; esta API ainda não gera arquivos de imagem.
                 * @default none
                 * @enum {string}
                 */
                image_mode: "none" | "required" | "auto";
                /**
                 * @description Instruções em linguagem natural. Até 8192 bytes UTF-8; permite tabulação e quebras de linha.
                 * @default
                 */
                teacher_notes: string;
                /**
                 * @description Referência de estilo, linguagem e extensão dos enunciados. Até 16384 bytes UTF-8; permite tabulação e quebras de linha.
                 * @default
                 */
                reference_text: string;
                /** @description Opcional. Os campos enviados prevalecem sobre o cadastro; null deixa o campo em branco. Matéria e ano vêm da consulta curricular. Os dados do cabeçalho são adicionados pelo backend e não são enviados à IA. */
                exam_header?: {
                    school_name?: string | null;
                    teacher_name?: string | null;
                    student_name?: string | null;
                    class_name?: string | null;
                    date?: string | null;
                };
                /** @description Opcional. Valores enviados prevalecem sobre as preferências do professor. Sem professor, os padrões são Arial e 12. Metadados para futura apresentação; não gera PDF. */
                formatting?: {
                    /** @description Texto UTF-8 em uma linha; espaços nas extremidades são removidos. */
                    font_family?: string;
                    font_size?: number;
                };
                teacher_id: number | null;
                bncc_document_id: number | null;
                context_limit: number;
            };
            ai_request: {
                instructions: string;
                input_json: string;
                output_schema_json: string;
                schema_name: string;
                max_output_tokens: number;
                timeout_seconds: number;
            } | null;
            result_metadata: (Record<string, never> | unknown[]) | null;
            /** @enum {string} */
            response_format: "unavailable" | "validated" | "unvalidated_json";
            /** @description Objeto validado no sucesso; string JSON não validada em certas falhas; null se indisponível. */
            response: {
                exam: components["schemas"]["ExamContent"];
                answer_key: components["schemas"]["AnswerKey"];
            } | string | null;
        };
    };
    responses: never;
    parameters: never;
    requestBodies: never;
    headers: never;
    pathItems: never;
}
export type $defs = Record<string, never>;
export interface operations {
    listTeachers: {
        parameters: {
            query?: {
                limit?: number;
                before_id?: number;
            };
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Operação concluída. */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["TeacherList"];
                };
            };
            /** @description Entrada inválida, tópico sem correspondência ou recusa do provedor. */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Falha interna controlada. */
            500: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Banco/provedor indisponível ou IA sem configuração. */
            503: {
                headers: {
                    /** @description Segundos, quando informados pelo provedor. */
                    "Retry-After"?: number;
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
        };
    };
    createTeacher: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["TeacherCreate"];
            };
        };
        responses: {
            /** @description Registro criado. */
            201: {
                headers: {
                    /** @description Caminho do registro criado neste ambiente. */
                    Location?: string;
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Teacher"];
                };
            };
            /** @description Conflito, e-mail já cadastrado ou currículo ainda não liberado. */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Envio maior que o limite. */
            413: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Content-Type incompatível. */
            415: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Entrada inválida, tópico sem correspondência ou recusa do provedor. */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Falha interna controlada. */
            500: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Banco/provedor indisponível ou IA sem configuração. */
            503: {
                headers: {
                    /** @description Segundos, quando informados pelo provedor. */
                    "Retry-After"?: number;
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
        };
    };
    getTeacher: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                /**
                 * @description ID neste ambiente; use o retornado pela operação anterior.
                 * @example 1
                 */
                id: number;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Operação concluída. */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Teacher"];
                };
            };
            /** @description Registro não encontrado neste ambiente. */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Entrada inválida, tópico sem correspondência ou recusa do provedor. */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Falha interna controlada. */
            500: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Banco/provedor indisponível ou IA sem configuração. */
            503: {
                headers: {
                    /** @description Segundos, quando informados pelo provedor. */
                    "Retry-After"?: number;
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
        };
    };
    updateTeacher: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                /**
                 * @description ID neste ambiente; use o retornado pela operação anterior.
                 * @example 1
                 */
                id: number;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["TeacherUpdate"];
            };
        };
        responses: {
            /** @description Operação concluída. */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Teacher"];
                };
            };
            /** @description Registro não encontrado neste ambiente. */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Conflito, e-mail já cadastrado ou currículo ainda não liberado. */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Envio maior que o limite. */
            413: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Content-Type incompatível. */
            415: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Entrada inválida, tópico sem correspondência ou recusa do provedor. */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Falha interna controlada. */
            500: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Banco/provedor indisponível ou IA sem configuração. */
            503: {
                headers: {
                    /** @description Segundos, quando informados pelo provedor. */
                    "Retry-After"?: number;
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
        };
    };
    getTeacherSettings: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                /**
                 * @description ID neste ambiente; use o retornado pela operação anterior.
                 * @example 1
                 */
                id: number;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Operação concluída. */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["TeacherSettings"];
                };
            };
            /** @description Registro não encontrado neste ambiente. */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Entrada inválida, tópico sem correspondência ou recusa do provedor. */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Falha interna controlada. */
            500: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Banco/provedor indisponível ou IA sem configuração. */
            503: {
                headers: {
                    /** @description Segundos, quando informados pelo provedor. */
                    "Retry-After"?: number;
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
        };
    };
    updateTeacherSettings: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                /**
                 * @description ID neste ambiente; use o retornado pela operação anterior.
                 * @example 1
                 */
                id: number;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["TeacherSettingsUpdate"];
            };
        };
        responses: {
            /** @description Operação concluída. */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["TeacherSettings"];
                };
            };
            /** @description Registro não encontrado neste ambiente. */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Envio maior que o limite. */
            413: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Content-Type incompatível. */
            415: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Entrada inválida, tópico sem correspondência ou recusa do provedor. */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Falha interna controlada. */
            500: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Banco/provedor indisponível ou IA sem configuração. */
            503: {
                headers: {
                    /** @description Segundos, quando informados pelo provedor. */
                    "Retry-After"?: number;
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
        };
    };
    listSubjects: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Operação concluída. */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["SubjectList"];
                };
            };
            /** @description Entrada inválida, tópico sem correspondência ou recusa do provedor. */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Falha interna controlada. */
            500: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Banco/provedor indisponível ou IA sem configuração. */
            503: {
                headers: {
                    /** @description Segundos, quando informados pelo provedor. */
                    "Retry-After"?: number;
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
        };
    };
    listSchoolGrades: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Operação concluída. */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["SchoolGradeList"];
                };
            };
            /** @description Entrada inválida, tópico sem correspondência ou recusa do provedor. */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Falha interna controlada. */
            500: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Banco/provedor indisponível ou IA sem configuração. */
            503: {
                headers: {
                    /** @description Segundos, quando informados pelo provedor. */
                    "Retry-After"?: number;
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
        };
    };
    generateExam: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["ExamRequest"];
            };
        };
        responses: {
            /** @description Operação concluída. */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["GenerationResult"];
                };
            };
            /** @description Registro não encontrado neste ambiente. */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Conflito, e-mail já cadastrado ou currículo ainda não liberado. */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Envio maior que o limite. */
            413: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Content-Type incompatível. */
            415: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Entrada inválida, tópico sem correspondência ou recusa do provedor. */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Limite do provedor atingido. */
            429: {
                headers: {
                    /** @description Segundos, quando informados pelo provedor. */
                    "Retry-After"?: number;
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Falha interna controlada. */
            500: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Falha do provedor ou resposta de prova inválida. */
            502: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Banco/provedor indisponível ou IA sem configuração. */
            503: {
                headers: {
                    /** @description Segundos, quando informados pelo provedor. */
                    "Retry-After"?: number;
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Tempo limite excedido. */
            504: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
        };
    };
    getExam: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                /**
                 * @description ID neste ambiente; use o retornado pela operação anterior.
                 * @example 1
                 */
                id: number;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Operação concluída. */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["ExamLookup"];
                };
            };
            /** @description Registro não encontrado neste ambiente. */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Entrada inválida, tópico sem correspondência ou recusa do provedor. */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Falha interna controlada. */
            500: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Banco/provedor indisponível ou IA sem configuração. */
            503: {
                headers: {
                    /** @description Segundos, quando informados pelo provedor. */
                    "Retry-After"?: number;
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
        };
    };
    getAnswerKey: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                /**
                 * @description ID neste ambiente; use o retornado pela operação anterior.
                 * @example 1
                 */
                id: number;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Operação concluída. */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["AnswerKeyLookup"];
                };
            };
            /** @description Registro não encontrado neste ambiente. */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Entrada inválida, tópico sem correspondência ou recusa do provedor. */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Falha interna controlada. */
            500: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Banco/provedor indisponível ou IA sem configuração. */
            503: {
                headers: {
                    /** @description Segundos, quando informados pelo provedor. */
                    "Retry-After"?: number;
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
        };
    };
    listGenerations: {
        parameters: {
            query?: {
                limit?: number;
                before_id?: number;
                status?: "running" | "succeeded" | "failed";
                teacher_id?: number;
            };
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Operação concluída. */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["GenerationList"];
                };
            };
            /** @description Entrada inválida, tópico sem correspondência ou recusa do provedor. */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Falha interna controlada. */
            500: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Banco/provedor indisponível ou IA sem configuração. */
            503: {
                headers: {
                    /** @description Segundos, quando informados pelo provedor. */
                    "Retry-After"?: number;
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
        };
    };
    getGeneration: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                /**
                 * @description ID neste ambiente; use o retornado pela operação anterior.
                 * @example 1
                 */
                id: number;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Operação concluída. */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["GenerationDetail"];
                };
            };
            /** @description Registro não encontrado neste ambiente. */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Entrada inválida, tópico sem correspondência ou recusa do provedor. */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Falha interna controlada. */
            500: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Banco/provedor indisponível ou IA sem configuração. */
            503: {
                headers: {
                    /** @description Segundos, quando informados pelo provedor. */
                    "Retry-After"?: number;
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
        };
    };
    findBnccContext: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["ContextRequest"];
            };
        };
        responses: {
            /** @description Operação concluída. */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["BnccContext"];
                };
            };
            /** @description Registro não encontrado neste ambiente. */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Conflito, e-mail já cadastrado ou currículo ainda não liberado. */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Content-Type incompatível. */
            415: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Entrada inválida, tópico sem correspondência ou recusa do provedor. */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Falha interna controlada. */
            500: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Banco/provedor indisponível ou IA sem configuração. */
            503: {
                headers: {
                    /** @description Segundos, quando informados pelo provedor. */
                    "Retry-After"?: number;
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
        };
    };
    listDocuments: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Operação concluída. */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["BnccDocumentList"];
                };
            };
            /** @description Falha interna controlada. */
            500: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Banco/provedor indisponível ou IA sem configuração. */
            503: {
                headers: {
                    /** @description Segundos, quando informados pelo provedor. */
                    "Retry-After"?: number;
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
        };
    };
    importDocument: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody: {
            content: {
                "multipart/form-data": {
                    /**
                     * Format: binary
                     * @description PDF com texto extraível, até 50 MiB.
                     */
                    pdf: string;
                    /** @default BNCC — Ensino Fundamental */
                    title: string;
                    /** @default 2018 */
                    version_label: string;
                };
            };
        };
        responses: {
            /** @description Registro criado. */
            201: {
                headers: {
                    /** @description Caminho do registro criado neste ambiente. */
                    Location?: string;
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Document"];
                };
            };
            /** @description Conflito, e-mail já cadastrado ou currículo ainda não liberado. */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Envio maior que o limite. */
            413: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Content-Type incompatível. */
            415: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Entrada inválida, tópico sem correspondência ou recusa do provedor. */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Falha interna controlada. */
            500: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Banco/provedor indisponível ou IA sem configuração. */
            503: {
                headers: {
                    /** @description Segundos, quando informados pelo provedor. */
                    "Retry-After"?: number;
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Tempo limite excedido. */
            504: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
        };
    };
    getDocument: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                /**
                 * @description ID neste ambiente; use o retornado pela operação anterior.
                 * @example 1
                 */
                id: number;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Operação concluída. */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Document"];
                };
            };
            /** @description Registro não encontrado neste ambiente. */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Entrada inválida, tópico sem correspondência ou recusa do provedor. */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Falha interna controlada. */
            500: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Banco/provedor indisponível ou IA sem configuração. */
            503: {
                headers: {
                    /** @description Segundos, quando informados pelo provedor. */
                    "Retry-After"?: number;
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
        };
    };
    getDocumentPages: {
        parameters: {
            query?: {
                from_page?: number;
                limit?: number;
            };
            header?: never;
            path: {
                /**
                 * @description ID neste ambiente; use o retornado pela operação anterior.
                 * @example 1
                 */
                id: number;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Operação concluída. */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Pages"];
                };
            };
            /** @description Registro não encontrado neste ambiente. */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Entrada inválida, tópico sem correspondência ou recusa do provedor. */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Falha interna controlada. */
            500: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Banco/provedor indisponível ou IA sem configuração. */
            503: {
                headers: {
                    /** @description Segundos, quando informados pelo provedor. */
                    "Retry-After"?: number;
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
        };
    };
    reprocessDocument: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                /**
                 * @description ID neste ambiente; use o retornado pela operação anterior.
                 * @example 1
                 */
                id: number;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Registro criado. */
            201: {
                headers: {
                    /** @description Caminho do registro criado neste ambiente. */
                    Location?: string;
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Document"];
                };
            };
            /** @description Registro não encontrado neste ambiente. */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Conflito, e-mail já cadastrado ou currículo ainda não liberado. */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Entrada inválida, tópico sem correspondência ou recusa do provedor. */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Falha interna controlada. */
            500: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Banco/provedor indisponível ou IA sem configuração. */
            503: {
                headers: {
                    /** @description Segundos, quando informados pelo provedor. */
                    "Retry-After"?: number;
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Tempo limite excedido. */
            504: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
        };
    };
    getStructure: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                /**
                 * @description ID neste ambiente; use o retornado pela operação anterior.
                 * @example 1
                 */
                id: number;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Operação concluída. */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Structure"];
                };
            };
            /** @description Registro não encontrado neste ambiente. */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Entrada inválida, tópico sem correspondência ou recusa do provedor. */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Falha interna controlada. */
            500: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Banco/provedor indisponível ou IA sem configuração. */
            503: {
                headers: {
                    /** @description Segundos, quando informados pelo provedor. */
                    "Retry-After"?: number;
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
        };
    };
    structureDocument: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                /**
                 * @description ID neste ambiente; use o retornado pela operação anterior.
                 * @example 1
                 */
                id: number;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Operação concluída. */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Structure"];
                };
            };
            /** @description Registro não encontrado neste ambiente. */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Conflito, e-mail já cadastrado ou currículo ainda não liberado. */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Entrada inválida, tópico sem correspondência ou recusa do provedor. */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Falha interna controlada. */
            500: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Banco/provedor indisponível ou IA sem configuração. */
            503: {
                headers: {
                    /** @description Segundos, quando informados pelo provedor. */
                    "Retry-After"?: number;
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
        };
    };
    listSkills: {
        parameters: {
            query?: {
                offset?: number;
                limit?: number;
                /**
                 * @description Código da matéria para revisar somente seu conteúdo.
                 * @example matematica
                 */
                subject?: string;
                /**
                 * @description Ano escolar para a revisão selecionada.
                 * @example 6
                 */
                grade?: number;
            };
            header?: never;
            path: {
                /**
                 * @description ID neste ambiente; use o retornado pela operação anterior.
                 * @example 1
                 */
                id: number;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Operação concluída. */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Skills"];
                };
            };
            /** @description Registro não encontrado neste ambiente. */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Entrada inválida, tópico sem correspondência ou recusa do provedor. */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Falha interna controlada. */
            500: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Banco/provedor indisponível ou IA sem configuração. */
            503: {
                headers: {
                    /** @description Segundos, quando informados pelo provedor. */
                    "Retry-After"?: number;
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
        };
    };
    getSkill: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                /**
                 * @description ID neste ambiente; use o retornado pela operação anterior.
                 * @example 1
                 */
                id: number;
                /**
                 * @description Código da habilidade nesta revisão.
                 * @example EF06MA07
                 */
                code: string;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Operação concluída. */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Skill"];
                };
            };
            /** @description Registro não encontrado neste ambiente. */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Entrada inválida, tópico sem correspondência ou recusa do provedor. */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Falha interna controlada. */
            500: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Banco/provedor indisponível ou IA sem configuração. */
            503: {
                headers: {
                    /** @description Segundos, quando informados pelo provedor. */
                    "Retry-After"?: number;
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
        };
    };
    listCompetencies: {
        parameters: {
            query?: {
                /**
                 * @description Código da matéria para revisar somente seu conteúdo.
                 * @example matematica
                 */
                subject?: string;
                /**
                 * @description Ano escolar para a revisão selecionada.
                 * @example 6
                 */
                grade?: number;
            };
            header?: never;
            path: {
                /**
                 * @description ID neste ambiente; use o retornado pela operação anterior.
                 * @example 1
                 */
                id: number;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Operação concluída. */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Competencies"];
                };
            };
            /** @description Registro não encontrado neste ambiente. */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Entrada inválida, tópico sem correspondência ou recusa do provedor. */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Falha interna controlada. */
            500: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Banco/provedor indisponível ou IA sem configuração. */
            503: {
                headers: {
                    /** @description Segundos, quando informados pelo provedor. */
                    "Retry-After"?: number;
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
        };
    };
    reviewSkill: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                /**
                 * @description ID neste ambiente; use o retornado pela operação anterior.
                 * @example 1
                 */
                id: number;
                /**
                 * @description Código da habilidade nesta revisão.
                 * @example EF06MA07
                 */
                code: string;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["ReviewRequest"];
            };
        };
        responses: {
            /** @description Operação concluída. */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["ReviewResult"];
                };
            };
            /** @description Registro não encontrado neste ambiente. */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Conflito, e-mail já cadastrado ou currículo ainda não liberado. */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Envio maior que o limite. */
            413: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Content-Type incompatível. */
            415: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Entrada inválida, tópico sem correspondência ou recusa do provedor. */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Falha interna controlada. */
            500: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Banco/provedor indisponível ou IA sem configuração. */
            503: {
                headers: {
                    /** @description Segundos, quando informados pelo provedor. */
                    "Retry-After"?: number;
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
        };
    };
    reviewCompetency: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                /**
                 * @description ID neste ambiente; use o retornado pela operação anterior.
                 * @example 1
                 */
                id: number;
                /**
                 * @description ID da competência retornado na listagem.
                 * @example 1
                 */
                competencyId: number;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["ReviewRequest"];
            };
        };
        responses: {
            /** @description Operação concluída. */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["ReviewResult"];
                };
            };
            /** @description Registro não encontrado neste ambiente. */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Conflito, e-mail já cadastrado ou currículo ainda não liberado. */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Envio maior que o limite. */
            413: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Content-Type incompatível. */
            415: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Entrada inválida, tópico sem correspondência ou recusa do provedor. */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Falha interna controlada. */
            500: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Banco/provedor indisponível ou IA sem configuração. */
            503: {
                headers: {
                    /** @description Segundos, quando informados pelo provedor. */
                    "Retry-After"?: number;
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
        };
    };
    listCurriculumReleases: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                /**
                 * @description ID neste ambiente; use o retornado pela operação anterior.
                 * @example 1
                 */
                id: number;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Operação concluída. */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["BnccReleaseList"];
                };
            };
            /** @description Registro não encontrado neste ambiente. */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Entrada inválida, tópico sem correspondência ou recusa do provedor. */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Falha interna controlada. */
            500: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Banco/provedor indisponível ou IA sem configuração. */
            503: {
                headers: {
                    /** @description Segundos, quando informados pelo provedor. */
                    "Retry-After"?: number;
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
        };
    };
    publishCurriculumScope: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                /**
                 * @description ID neste ambiente; use o retornado pela operação anterior.
                 * @example 1
                 */
                id: number;
            };
            cookie?: never;
        };
        requestBody: {
            content: {
                "application/json": components["schemas"]["BnccReleaseRequest"];
            };
        };
        responses: {
            /** @description Operação concluída. */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["BnccRelease"];
                };
            };
            /** @description Registro não encontrado neste ambiente. */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Conflito, e-mail já cadastrado ou currículo ainda não liberado. */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Envio maior que o limite. */
            413: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Content-Type incompatível. */
            415: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Entrada inválida, tópico sem correspondência ou recusa do provedor. */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Falha interna controlada. */
            500: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Banco/provedor indisponível ou IA sem configuração. */
            503: {
                headers: {
                    /** @description Segundos, quando informados pelo provedor. */
                    "Retry-After"?: number;
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
        };
    };
    publishDocument: {
        parameters: {
            query?: never;
            header?: never;
            path: {
                /**
                 * @description ID neste ambiente; use o retornado pela operação anterior.
                 * @example 1
                 */
                id: number;
            };
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Operação concluída. */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["PublishedDocument"];
                };
            };
            /** @description Registro não encontrado neste ambiente. */
            404: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Conflito, e-mail já cadastrado ou currículo ainda não liberado. */
            409: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Entrada inválida, tópico sem correspondência ou recusa do provedor. */
            422: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Falha interna controlada. */
            500: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Banco/provedor indisponível ou IA sem configuração. */
            503: {
                headers: {
                    /** @description Segundos, quando informados pelo provedor. */
                    "Retry-After"?: number;
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
        };
    };
    health: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Operação concluída. */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Health"];
                };
            };
            /** @description Falha interna controlada. */
            500: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
        };
    };
    databaseHealth: {
        parameters: {
            query?: never;
            header?: never;
            path?: never;
            cookie?: never;
        };
        requestBody?: never;
        responses: {
            /** @description Operação concluída. */
            200: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Health"];
                };
            };
            /** @description Falha interna controlada. */
            500: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    "application/json": components["schemas"]["Error"];
                };
            };
            /** @description Banco indisponível. */
            503: {
                headers: {
                    [name: string]: unknown;
                };
                content: {
                    /**
                     * @example {
                     *       "status": "unavailable"
                     *     }
                     */
                    "application/json": components["schemas"]["Health"];
                };
            };
        };
    };
}
