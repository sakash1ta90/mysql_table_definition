<?php

declare(strict_types=1);

new class('database', '127.0.0.1:3316', 'docker', 'docker') {
    /**
     *  constructor.
     * @param string $database
     * @param string $host
     * @param string $user
     * @param string $password
     */
    public function __construct(string $database, string $host, string $user, string $password)
    {
        $dsn = sprintf('mysql:dbname=%s;host=%s;charset=utf8', $database, $host);
        $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
        try {
            $pdo = new PDO($dsn, $user, $password, $options);
        } catch (PDOException $e) {
            exit($e->getMessage() . PHP_EOL);
        }

        $this->custom($pdo);
    }


    /**
     * @param PDO $pdo
     */
    private function custom(PDO $pdo): void
    {
        $sth = $pdo->prepare('SHOW TABLES', [PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY]);
        $sth->execute();
        $tableList = array_column($sth->fetchAll(), 'Tables_in_database');

        // 表示するshow full columnsの結果
        $outputColumns = ['Field', 'Type', 'Length', 'DefaultValue', 'PK', 'Null', 'AI'];

        ob_start();
        foreach ($tableList as $tableName) {
            $columnList = implode('|', $outputColumns);
            $tableLine = str_repeat(':--|', count($outputColumns));
            echo sprintf("## %s\n\n|%s|\n|%s", $tableName, $columnList, $tableLine), PHP_EOL;

            // カラムの定義出力
            try {
                $stmt = $pdo->query("SHOW FULL COLUMNS FROM {$tableName}");
                $stmt->execute();
            } catch (PDOException $e) {
                ob_clean();
                exit($e->getMessage() . PHP_EOL);
            }

            while (['Field' => $field, 'Type' => $type, 'Null' => $null, 'Default' => $default, 'Extra' => $extra, 'Key' => $key] = $stmt->fetch()) {
                preg_match('/(.*)\(([0-9]+)/', $type, $splinted);
                $line = [
                    'field' => $field,
                    'type' => $splinted[1] ?? $type,
                    'length' => $splinted[2] ?? '',
                    'default_value' => $default,
                    'pk' => 'PRI' === $key ? '○' : '',
                    'null' => 'YES' === $null ? '○' : '',
                    'ai' => 'auto_increment' === $extra ? '○' : '',//str_contains($extra, 'auto_increment') ? '○' : '',
                ];
                echo '|', implode('|', $line), '|', PHP_EOL;
            }
            echo PHP_EOL;
        }
    }

    /**
     * @param PDO $pdo
     */
    private function markdown(PDO $pdo): void
    {
        $sth = $pdo->prepare('SHOW TABLES', [PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY]);
        $sth->execute();
        $tableList = array_column($sth->fetchAll(), 'Tables_in_database');

        // 表示するshow full columnsの結果
        $outputColumns = ['Field', 'Type', 'Null', 'Key', 'Default', 'Extra', 'Comment'];

        ob_start();

        $tableStmt = $pdo->prepare("SHOW TABLE STATUS LIKE :table_name");
        foreach ($tableList as $tableName) {
            // テーブルコメント出力
            $tableStmt->bindValue(':table_name', $tableName);
            $tableStmt->execute();
            while ($row = $tableStmt->fetch()) {
                echo sprintf("## %s\n%s\n", $tableName, $row['Comment'] ?: 'no comment');
            }

            echo sprintf("\n|%s|\n|%s", implode('|', $outputColumns), str_repeat(':--|', count($outputColumns))), PHP_EOL;

            // カラムの定義出力
            try {
                $stmt = $pdo->query("SHOW FULL COLUMNS FROM {$tableName}");
                $stmt->execute();
            } catch (PDOException $e) {
                ob_clean();
                exit($e->getMessage() . PHP_EOL);
            }

            while ($row = $stmt->fetch()) {
                echo '|', implode('|', $row), '|', PHP_EOL;
            }
            echo PHP_EOL;
        }
    }
};
