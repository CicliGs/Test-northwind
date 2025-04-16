<?php
require 'vendor/autoload.php';

use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$host = $_ENV['DB_HOST'];
$db   = $_ENV['DB_NAME'];
$user = $_ENV['DB_USER'];
$pass = $_ENV['DB_PASS'];
$port = $_ENV['DB_PORT'];

$dsn = "pgsql:host=$host;port=$port;dbname=$db";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
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
SQL);

$insert = $pdo->prepare("INSERT INTO test_customers (customer_name, contact_name, address, city, postal_code, country) VALUES (?, ?, ?, ?, ?, ?)");
$batchSize = 1000;
$total = 1000000;
for ($i = 0; $i < $total; $i++) {
    $insert->execute([
        $faker->company,
        $faker->name,
        $faker->address,
        $faker->city,
        $faker->postcode,
        $faker->country
    ]);
    if ($i % $batchSize == 0) {
        echo "Inserted $i records\n";
    }
}

$pdo->exec("DROP INDEX IF EXISTS idx_city_btree;");
$pdo->exec("CREATE INDEX idx_city_btree ON test_customers (city);");

$pdo->exec("DROP INDEX IF EXISTS idx_country_hash;");
$pdo->exec("CREATE INDEX idx_country_hash ON test_customers USING HASH (country);"); 

$pdo->exec("DROP INDEX IF EXISTS idx_city_postalcode_compound;");
$pdo->exec("CREATE INDEX idx_city_postalcode_compound ON test_customers (city, postal_code);"); 

function explain($pdo, $sql, $params = []) {
    $stmt = $pdo->prepare("EXPLAIN ANALYZE $sql");
    $stmt->execute($params);
    $result = $stmt->fetchAll();
    print_r($result);
}

echo "B-tree (city, диапазон):\n";
explain($pdo, "SELECT * FROM test_customers WHERE city BETWEEN ? AND ?", ['A', 'M']);

echo "Hash (country, точное):\n";
explain($pdo, "SELECT * FROM test_customers WHERE country = ?", ['Germany']);

echo "Compound (city+postal_code):\n";
explain($pdo, "SELECT * FROM test_customers WHERE city = ? AND postal_code = ?", ['Berlin', '10115']);