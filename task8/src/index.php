<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/config.php';
$dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $config['host'], $config['port'], $config['dbname']);
$user = $config['user'];
$pass = $config['pass'];

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

$pdo = new PDO($dsn, $user, $pass, $options);

$faker = Faker\Factory::create();

$pdo->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS test_customers (
        id SERIAL PRIMARY KEY,
        customer_name VARCHAR(255),
        contact_name VARCHAR(255),
        address TEXT,
        city VARCHAR(100),
        postal_code VARCHAR(20),
        country VARCHAR(100)
    );
    TRUNCATE TABLE test_customers;
    SQL
);
//TODO изучить типы строк + printf
$insert = $pdo->prepare(
    'INSERT INTO test_customers 
    (customer_name, contact_name, address, city, postal_code, country) 
    VALUES (?, ?, ?, ?, ?, ?)'
);
$batchSize = 1000;
$total = 1000000;
for ($i = 1; $i <= $total; $i++) {
    $insert->execute([
        $faker->company,
        $faker->name,
        $faker->address,
        $faker->city,
        $faker->postcode,
        $faker->country
    ]);
    if ($i % $batchSize === 0) {
        echo "Inserted $i records\n";
    }
}

$pdo->exec(sprintf('DROP INDEX IF EXISTS %s;', 'idx_city_btree'));
$pdo->exec(sprintf('DROP INDEX IF EXISTS %s;', 'idx_country_hash'));
$pdo->exec(sprintf('DROP INDEX IF EXISTS %s;', 'idx_city_postalcode_compound'));

$pdo->exec(sprintf("CREATE INDEX %s ON test_customers USING btree (%s);", 'idx_city_btree', 'city'));

$pdo->exec(sprintf("CREATE INDEX %s ON test_customers USING hash (%s);", 'idx_country_hash', 'country'));

$pdo->exec(sprintf("CREATE INDEX %s ON test_customers USING btree (%s, %s);", 'idx_city_postalcode_compound', 'city', 'postal_code'));

function explain(PDO $pdo, string $sql, array $params = []): void
{
    $stmt = $pdo->prepare("EXPLAIN ANALYZE $sql");
    $stmt->execute($params);
    $result = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo implode(PHP_EOL , $result) . PHP_EOL . PHP_EOL;
}

echo "B-tree (city, диапазон):" . PHP_EOL;
explain($pdo, "SELECT * FROM test_customers WHERE city BETWEEN ? AND ?", ['A', 'M']);

echo "Hash (country, точное):" . PHP_EOL;
explain($pdo, "SELECT * FROM test_customers WHERE country = ?", ['Germany']);

echo "Compound (city+postal_code):" . PHP_EOL;
explain($pdo, "SELECT * FROM test_customers WHERE city = ? AND postal_code = ?", ['Berlin', '10115']);