<?php declare(strict_types=1);

namespace Reconmap\Repositories;

use Reconmap\Repositories\QueryBuilders\SelectQueryBuilder;
use Reconmap\Repositories\QueryBuilders\UpdateQueryBuilder;

class CommandRepository extends MysqlRepository
{
    public const UPDATABLE_COLUMNS_TYPES = [
        'short_name' => 's',
        'description' => 's',
        'docker_image' => 's',
        'executable_type' => 's',
        'executable_path' => 's',
        'arguments' => 's',
        'configuration' => 's',
        'output_filename' => 's'
    ];

    public function findById(int $id): ?array
    {
        $sql = <<<SQL
SELECT
       c.*,
       u.full_name AS creator_full_name
FROM
    command c
    INNER JOIN user u ON (u.id = c.creator_uid)
WHERE c.id = ?
SQL;

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $rs = $stmt->get_result();
        $command = $rs->fetch_assoc();
        $stmt->close();

        return $command;
    }

    public function findAll(int $limit = 20): array
    {
        $selectQueryBuilder = $this->getBaseSelectQueryBuilder();
        $selectQueryBuilder->setLimit($limit);
        $sql = $selectQueryBuilder->toSql();

        $rs = $this->db->query($sql);
        return $rs->fetch_all(MYSQLI_ASSOC);
    }

    public function findByKeywords(string $keywords, int $limit = 20): array
    {
        $selectQueryBuilder = $this->getBaseSelectQueryBuilder();
        $selectQueryBuilder->setLimit($limit);
        $selectQueryBuilder->setWhere('c.short_name LIKE ? OR c.description LIKE ?');
        $sql = $selectQueryBuilder->toSql();

        $keywordsLike = "%$keywords%";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('ss', $keywordsLike, $keywordsLike);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    private function getBaseSelectQueryBuilder(): SelectQueryBuilder
    {
        $queryBuilder = new SelectQueryBuilder('command c');
        return $queryBuilder;
    }

    public function deleteById(int $id): bool
    {
        return $this->deleteByTableId('command', $id);
    }

    public function insert(object $command): int
    {
        $stmt = $this->db->prepare('INSERT INTO command (creator_uid, short_name, description, docker_image, arguments, executable_type, executable_path, output_filename) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('isssssss', $command->creator_uid, $command->short_name, $command->description, $command->docker_image, $command->arguments, $command->executable_type, $command->executable_path, $command->output_filename);
        return $this->executeInsertStatement($stmt);
    }

    public function updateById(int $id, array $newColumnValues): bool
    {
        $updateQueryBuilder = new UpdateQueryBuilder('command');
        $updateQueryBuilder->setColumnValues(array_map(fn() => '?', $newColumnValues));
        $updateQueryBuilder->setWhereConditions('id = ?');

        $stmt = $this->db->prepare($updateQueryBuilder->toSql());
        call_user_func_array([$stmt, 'bind_param'], [$this->generateParamTypes(array_keys($newColumnValues)) . 'i', ...$this->refValues($newColumnValues), &$id]);
        $result = $stmt->execute();
        $success = $result && 1 === $stmt->affected_rows;
        $stmt->close();

        return $success;
    }
}
