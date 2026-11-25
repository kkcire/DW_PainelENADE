<?php
/**
 * AcaoRepository.php - Camada Models (Repository Pattern)
 *
 * Responsabilidade:
 * Isolar TODAS as operações de banco de dados (CRUD) da entidade `acao` e da view `vw_acao`:
 * - SELECT (listar com filtros, buscar por ID, resumir contagem por situação)
 * - INSERT (criar nova ação e retornar o id_acao gerado pelo PostgreSQL)
 * - UPDATE (atualizar dados e informações de anexo de uma ação existente)
 * - DELETE (excluir uma ação pelo ID)
 *
 * Regras do banco (ver banco/estrutura.sql):
 * - situacao só aceita: 'Planejada', 'Em andamento' ou 'Concluída'
 * - anexo_nome_original e anexo_arquivo andam sempre juntos (ambos NULL ou ambos preenchidos)
 * - id_oferta referencia a tabela oferta (ON DELETE RESTRICT)
 */

class AcaoRepository
{
    /**
     * Lista fechada de situações aceitas pela constraint CHECK da tabela `acao`.
     * Deixamos como constante pública para que controllers e validações usem sempre a mesma fonte da verdade.
     */
    public const SITUACOES_VALIDAS = [
        'Planejada',
        'Em andamento',
        'Concluída',
    ];

    /**
     * Conexão PDO com o PostgreSQL injetada pelo controller.
     */
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Lista ações de acompanhamento a partir da view `vw_acao`, com filtros opcionais.
     *
     * Por que consultar `vw_acao`?
     * Porque a view já faz o JOIN com `oferta` e devolve `oferta_rotulo`
     * (ex.: "Pedagogia · Licenciatura · Educação a Distância") e o booleano `tem_anexo`.
     *
     * @param int|null    $idOferta Filtra por uma oferta específica (ou null para todas)
     * @param string|null $situacao Filtra por situação ('Planejada', 'Em andamento', 'Concluída' ou null)
     * @return array<int, array<string, mixed>>
     */
    public function listar(?int $idOferta = null, ?string $situacao = null): array
    {
        // Começamos com um array de condições dinâmicas e um array de parâmetros nomeados.
        // Assim, podemos combinar nenhum, um ou ambos os filtros de forma limpa e 100% segura.
        $condicoes  = [];
        $parametros = [];

        if ($idOferta !== null) {
            $condicoes[]              = 'id_oferta = :id_oferta';
            $parametros[':id_oferta'] = $idOferta;
        }

        if ($situacao !== null && $situacao !== '') {
            $condicoes[]             = 'situacao = :situacao';
            $parametros[':situacao'] = $situacao;
        }

        $sql = 'SELECT id_acao,
                       id_oferta,
                       oferta_rotulo,
                       titulo,
                       descricao,
                       situacao,
                       responsavel,
                       prazo,
                       anexo_nome_original,
                       anexo_arquivo,
                       tem_anexo
                FROM vw_acao';

        // Só adiciona a cláusula WHERE se pelo menos um filtro foi informado
        if (!empty($condicoes)) {
            $sql .= ' WHERE ' . implode(' AND ', $condicoes);
        }

        // Ordena pelo prazo mais próximo primeiro; em caso de empate, mostra a mais recente
        $sql .= ' ORDER BY prazo ASC, id_acao DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parametros);

        return array_map([$this, 'normalizarAcao'], $stmt->fetchAll());
    }

    /**
     * Busca uma ação específica pelo ID na view `vw_acao`.
     * Retorna null caso nenhuma ação seja encontrada.
     */
    public function buscarPorId(int $idAcao): ?array
    {
        $sql = 'SELECT id_acao,
                       id_oferta,
                       oferta_rotulo,
                       titulo,
                       descricao,
                       situacao,
                       responsavel,
                       prazo,
                       anexo_nome_original,
                       anexo_arquivo,
                       tem_anexo
                FROM vw_acao
                WHERE id_acao = :id_acao
                LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id_acao' => $idAcao]);

        $linha = $stmt->fetch();
        if (!$linha) {
            return null;
        }

        return $this->normalizarAcao($linha);
    }

    /**
     * Conta quantas ações existem em cada situação ('Planejada', 'Em andamento', 'Concluída').
     * Pode ser filtrado por oferta (usado no painel.php) ou geral (usado em acoes/listar.php).
     *
     * Exemplo de retorno:
     * [
     *   'total' => 5,
     *   'planejada' => 2,
     *   'em_andamento' => 2,
     *   'concluida' => 1,
     *   'por_situacao' => ['Planejada' => 2, 'Em andamento' => 2, 'Concluída' => 1]
     * ]
     */
    public function resumirPorSituacao(?int $idOferta = null): array
    {
        $sql = 'SELECT situacao, COUNT(*) AS total FROM acao';
        $parametros = [];

        if ($idOferta !== null) {
            $sql .= ' WHERE id_oferta = :id_oferta';
            $parametros[':id_oferta'] = $idOferta;
        }

        $sql .= ' GROUP BY situacao';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parametros);

        // Inicializa todas as situações com zero para o frontend nunca receber chave undefined
        $porSituacao = [
            'Planejada'    => 0,
            'Em andamento' => 0,
            'Concluída'    => 0,
        ];

        $total = 0;
        foreach ($stmt->fetchAll() as $linha) {
            $sit = $linha['situacao'];
            $qtd = (int) $linha['total'];
            if (array_key_exists($sit, $porSituacao)) {
                $porSituacao[$sit] = $qtd;
            }
            $total += $qtd;
        }

        return [
            'total'        => $total,
            'planejada'    => $porSituacao['Planejada'],
            'em_andamento' => $porSituacao['Em andamento'],
            'concluida'    => $porSituacao['Concluída'],
            'por_situacao' => $porSituacao,
        ];
    }

    /**
     * Verifica se o id_oferta informado existe realmente na tabela `oferta`.
     * Usado antes de criar/editar uma ação para devolver um erro 400 amigável
     * em vez de deixar estourar uma exceção de chave estrangeira no banco.
     */
    public function ofertaExiste(int $idOferta): bool
    {
        $sql = 'SELECT 1 FROM oferta WHERE id_oferta = :id_oferta LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id_oferta' => $idOferta]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Insere uma nova ação na tabela `acao` e retorna o `id_acao` gerado pelo banco.
     *
     * Detalhe do PostgreSQL:
     * Usamos `RETURNING id_acao` no final do INSERT, que é a forma mais segura e
     * idiomática de recuperar o ID gerado por uma coluna SERIAL no PostgreSQL.
     */
    public function criar(array $dados): int
    {
        $sql = 'INSERT INTO acao (
                    id_oferta,
                    titulo,
                    descricao,
                    situacao,
                    responsavel,
                    prazo,
                    anexo_nome_original,
                    anexo_arquivo
                ) VALUES (
                    :id_oferta,
                    :titulo,
                    :descricao,
                    :situacao,
                    :responsavel,
                    :prazo,
                    :anexo_nome_original,
                    :anexo_arquivo
                )
                RETURNING id_acao';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id_oferta'           => (int) $dados['id_oferta'],
            ':titulo'              => $dados['titulo'],
            ':descricao'           => $dados['descricao'],
            ':situacao'            => $dados['situacao'],
            ':responsavel'         => $dados['responsavel'],
            ':prazo'               => $dados['prazo'],
            ':anexo_nome_original' => $dados['anexo_nome_original'] ?? null,
            ':anexo_arquivo'       => $dados['anexo_arquivo'] ?? null,
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Atualiza os dados (e opcionalmente o anexo) de uma ação existente.
     * Retorna true se encontrou e atualizou o registro, ou false se o ID não existia.
     */
    public function atualizar(int $idAcao, array $dados): bool
    {
        $sql = 'UPDATE acao
                SET id_oferta           = :id_oferta,
                    titulo              = :titulo,
                    descricao           = :descricao,
                    situacao            = :situacao,
                    responsavel         = :responsavel,
                    prazo               = :prazo,
                    anexo_nome_original = :anexo_nome_original,
                    anexo_arquivo       = :anexo_arquivo
                WHERE id_acao = :id_acao';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id_acao'             => $idAcao,
            ':id_oferta'           => (int) $dados['id_oferta'],
            ':titulo'              => $dados['titulo'],
            ':descricao'           => $dados['descricao'],
            ':situacao'            => $dados['situacao'],
            ':responsavel'         => $dados['responsavel'],
            ':prazo'               => $dados['prazo'],
            ':anexo_nome_original' => $dados['anexo_nome_original'] ?? null,
            ':anexo_arquivo'       => $dados['anexo_arquivo'] ?? null,
        ]);

        // rowCount() indica quantas linhas foram afetadas pelo UPDATE
        return $stmt->rowCount() > 0;
    }

    /**
     * Exclui uma ação da tabela `acao` pelo ID.
     * IMPORTANTE: a remoção do arquivo físico em app/uploads/ fica sob responsabilidade
     * do UploadEvidenciaService (acionado pelo controller acoes/excluir.php).
     */
    public function excluir(int $idAcao): bool
    {
        $sql = 'DELETE FROM acao WHERE id_acao = :id_acao';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id_acao' => $idAcao]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Valida e higieniza os dados de entrada de uma ação (usado tanto no criar.php quanto no editar.php).
     *
     * Por que centralizar aqui?
     * Para não repetir as mesmas regras de validação em dois controllers diferentes
     * e garantir que tudo respeite exatamente os limites e CHECKs da tabela `acao`.
     *
     * @param array<string, mixed> $entrada Dados brutos vindos de $_POST ou JSON
     * @return array{valido: bool, erros: array<int, string>, dados: array<string, mixed>}
     */
    public function validarEntrada(array $entrada): array
    {
        $erros = [];

        // 1. Oferta vinculada (inteiro positivo e existente no banco)
        $idOferta = filter_var($entrada['id_oferta'] ?? null, FILTER_VALIDATE_INT);
        if ($idOferta === false || $idOferta <= 0) {
            $erros[] = 'Selecione uma oferta válida.';
        } elseif (!$this->ofertaExiste($idOferta)) {
            $erros[] = 'A oferta selecionada não existe no banco de dados.';
        }

        // 2. Título (obrigatório, máximo de 150 caracteres conforme VARCHAR(150))
        $titulo = trim((string) ($entrada['titulo'] ?? ''));
        if ($titulo === '') {
            $erros[] = 'O título da ação é obrigatório.';
        } elseif ($this->contarCaracteres($titulo) > 150) {
            $erros[] = 'O título da ação deve ter no máximo 150 caracteres.';
        }

        // 3. Descrição (obrigatória)
        $descricao = trim((string) ($entrada['descricao'] ?? ''));
        if ($descricao === '') {
            $erros[] = 'A descrição da ação é obrigatória.';
        }

        // 4. Situação (deve corresponder exatamente ao CHECK do banco)
        $situacao = trim((string) ($entrada['situacao'] ?? ''));
        if (!in_array($situacao, self::SITUACOES_VALIDAS, true)) {
            $erros[] = 'Situação inválida. Valores permitidos: ' . implode(', ', self::SITUACOES_VALIDAS) . '.';
        }

        // 5. Responsável (obrigatório, máximo de 100 caracteres conforme VARCHAR(100))
        $responsavel = trim((string) ($entrada['responsavel'] ?? ''));
        if ($responsavel === '') {
            $erros[] = 'O responsável pela ação é obrigatório.';
        } elseif ($this->contarCaracteres($responsavel) > 100) {
            $erros[] = 'O nome do responsável deve ter no máximo 100 caracteres.';
        }

        // 6. Prazo (data real no formato ISO YYYY-MM-DD, padrão do <input type="date"> e do PostgreSQL)
        $prazo = trim((string) ($entrada['prazo'] ?? ''));
        $dataValida = DateTimeImmutable::createFromFormat('Y-m-d', $prazo);
        if ($prazo === '' || !$dataValida || $dataValida->format('Y-m-d') !== $prazo) {
            $erros[] = 'Informe um prazo válido no formato AAAA-MM-DD.';
        }

        return [
            'valido' => empty($erros),
            'erros'  => $erros,
            'dados'  => [
                'id_oferta'   => $idOferta !== false ? (int) $idOferta : 0,
                'titulo'      => $titulo,
                'descricao'   => $descricao,
                'situacao'    => $situacao,
                'responsavel' => $responsavel,
                'prazo'       => $prazo,
            ],
        ];
    }

    /**
     * Conta a quantidade de caracteres UTF-8 sem depender obrigatoriamente da extensão `mbstring`.
     * Se `mbstring` estiver habilitada, usa `mb_strlen`; caso contrário, conta caracteres UTF-8 via regex PCRE.
     */
    private function contarCaracteres(string $texto): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($texto, 'UTF-8');
        }

        $total = preg_match_all('/./us', $texto);
        return $total !== false ? $total : strlen($texto);
    }

    /**
     * Normaliza os tipos de uma linha vinda da view `vw_acao`.
     * Garante inteiros em id_acao/id_oferta e booleano real em tem_anexo.
     */
    private function normalizarAcao(array $linha): array
    {
        return [
            'id_acao'             => (int) $linha['id_acao'],
            'id_oferta'           => (int) $linha['id_oferta'],
            'oferta_rotulo'       => $linha['oferta_rotulo'],
            'titulo'              => $linha['titulo'],
            'descricao'           => $linha['descricao'],
            'situacao'            => $linha['situacao'],
            'responsavel'         => $linha['responsavel'],
            'prazo'               => $linha['prazo'],
            'anexo_nome_original' => $linha['anexo_nome_original'],
            'anexo_arquivo'       => $linha['anexo_arquivo'],
            // No PostgreSQL, boolean pode vir como bool true/false ou 't'/'f' dependendo da versão do driver
            'tem_anexo'           => filter_var($linha['tem_anexo'], FILTER_VALIDATE_BOOLEAN),
        ];
    }
}
