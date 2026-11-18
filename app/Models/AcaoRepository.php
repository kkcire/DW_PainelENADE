<?php
/**
 * =====================================================================
 * AcaoRepository.php - Repositório CRUD de Ações de Acompanhamento
 * =====================================================================
 *
 * Camada Models (Pattern Repository):
 * Isola todas as operações de banco (SELECT, INSERT, UPDATE, DELETE)
 * referentes à tabela `acao` e à view `vw_acao`.
 *
 * Regras de integridade do banco respeitadas aqui:
 * 1. `situacao` deve ser 'Planejada', 'Em andamento' ou 'Concluída'.
 * 2. Constraint `ck_anexo_completo`: `anexo_nome_original` e `anexo_arquivo`
 *    devem ser AMBOS preenchidos ou AMBOS nulos (NULL).
 */

class AcaoRepository
{
    /**
     * @param PDO $pdo Conexão PDO ativa obtida via conectar().
     */
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Lista as ações de acompanhamento utilizando a view `vw_acao`,
     * que já inclui o rótulo completo da oferta (`oferta_rotulo`) e a
     * coluna booleana `tem_anexo`.
     *
     * @param int|null    $idOferta Filtro opcional pelo ID da oferta.
     * @param string|null $situacao Filtro opcional pela situação ('Planejada', etc.).
     * @return array<int, array<string, mixed>> Lista de ações ordenadas por prazo.
     */
    public function listar(?int $idOferta = null, ?string $situacao = null): array
    {
        $condicoes = [];
        $params    = [];

        if ($idOferta !== null && $idOferta > 0) {
            $condicoes[]          = 'id_oferta = :id_oferta';
            $params[':id_oferta'] = $idOferta;
        }

        if ($situacao !== null && $situacao !== '') {
            $condicoes[]         = 'situacao = :situacao';
            $params[':situacao'] = $situacao;
        }

        $where = count($condicoes) > 0 ? 'WHERE ' . implode(' AND ', $condicoes) : '';

        $sql = "SELECT id_acao, id_oferta, oferta_rotulo, titulo, descricao,
                       situacao, responsavel, prazo,
                       anexo_nome_original, anexo_arquivo, tem_anexo
                FROM vw_acao
                {$where}
                ORDER BY prazo ASC, id_acao DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Busca os dados completos de uma única ação pelo seu ID (`id_acao`) na view `vw_acao`.
     *
     * @param int $idAcao Chave primária da ação.
     * @return array<string, mixed>|null Dados da ação ou null se não existir.
     */
    public function buscarPorId(int $idAcao): ?array
    {
        $sql = 'SELECT id_acao, id_oferta, oferta_rotulo, titulo, descricao,
                       situacao, responsavel, prazo,
                       anexo_nome_original, anexo_arquivo, tem_anexo
                FROM vw_acao
                WHERE id_acao = :id_acao
                LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id_acao' => $idAcao]);
        $acao = $stmt->fetch();
        return $acao ?: null;
    }

    /**
     * Insere uma nova ação de acompanhamento na tabela `acao` e retorna o `id_acao`
     * gerado pelo PostgreSQL (`RETURNING id_acao`).
     *
     * @param array<string, mixed> $dados Dados validados da ação.
     * @return int ID da nova ação recém-criada.
     */
    public function criar(array $dados): int
    {
        // Garante a constraint ck_anexo_completo (ambos preenchidos ou ambos NULL)
        $temAnexo    = !empty($dados['anexo_nome_original']) && !empty($dados['anexo_arquivo']);
        $nomeOrig    = $temAnexo ? $dados['anexo_nome_original'] : null;
        $nomeArquivo = $temAnexo ? $dados['anexo_arquivo'] : null;

        $sql = 'INSERT INTO acao (
                    id_oferta, titulo, descricao, situacao,
                    responsavel, prazo, anexo_nome_original, anexo_arquivo
                ) VALUES (
                    :id_oferta, :titulo, :descricao, :situacao,
                    :responsavel, :prazo, :anexo_nome_original, :anexo_arquivo
                ) RETURNING id_acao';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id_oferta'           => (int) $dados['id_oferta'],
            ':titulo'              => trim($dados['titulo']),
            ':descricao'           => trim($dados['descricao']),
            ':situacao'            => $dados['situacao'],
            ':responsavel'         => trim($dados['responsavel']),
            ':prazo'               => $dados['prazo'],
            ':anexo_nome_original' => $nomeOrig,
            ':anexo_arquivo'       => $nomeArquivo,
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Atualiza todos os campos de uma ação existente na tabela `acao`.
     *
     * @param int                  $idAcao ID da ação a ser atualizada.
     * @param array<string, mixed> $dados  Novos valores dos campos e anexo.
     * @return bool True em caso de sucesso na execução do UPDATE.
     */
    public function atualizar(int $idAcao, array $dados): bool
    {
        $temAnexo    = !empty($dados['anexo_nome_original']) && !empty($dados['anexo_arquivo']);
        $nomeOrig    = $temAnexo ? $dados['anexo_nome_original'] : null;
        $nomeArquivo = $temAnexo ? $dados['anexo_arquivo'] : null;

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
        return $stmt->execute([
            ':id_oferta'           => (int) $dados['id_oferta'],
            ':titulo'              => trim($dados['titulo']),
            ':descricao'           => trim($dados['descricao']),
            ':situacao'            => $dados['situacao'],
            ':responsavel'         => trim($dados['responsavel']),
            ':prazo'               => $dados['prazo'],
            ':anexo_nome_original' => $nomeOrig,
            ':anexo_arquivo'       => $nomeArquivo,
            ':id_acao'             => $idAcao,
        ]);
    }

    /**
     * Remove permanentemente uma ação da tabela `acao`.
     *
     * @param int $idAcao ID da ação a excluir.
     * @return bool True se o comando DELETE foi executado com sucesso.
     */
    public function excluir(int $idAcao): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM acao WHERE id_acao = :id_acao');
        return $stmt->execute([':id_acao' => $idAcao]);
    }

    /**
     * Contabiliza a quantidade de ações por situação ('Planejada', 'Em andamento', 'Concluída')
     * e o total geral, podendo filtrar por uma oferta específica.
     *
     * @param int|null $idOferta ID da oferta para filtrar ou null para considerar todas.
     * @return array{total: int, Planejada: int, 'Em andamento': int, Concluída: int}
     */
    public function contarPorSituacao(?int $idOferta = null): array
    {
        $resumo = [
            'total'        => 0,
            'Planejada'    => 0,
            'Em andamento' => 0,
            'Concluída'    => 0,
        ];

        $where  = '';
        $params = [];
        if ($idOferta !== null && $idOferta > 0) {
            $where                = 'WHERE id_oferta = :id_oferta';
            $params[':id_oferta'] = $idOferta;
        }

        $sql  = "SELECT situacao, COUNT(*) AS qtd FROM acao {$where} GROUP BY situacao";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        foreach ($stmt->fetchAll() as $linha) {
            $sit = $linha['situacao'];
            $qtd = (int) $linha['qtd'];
            if (isset($resumo[$sit])) {
                $resumo[$sit] = $qtd;
            }
            $resumo['total'] += $qtd;
        }

        return $resumo;
    }
}
