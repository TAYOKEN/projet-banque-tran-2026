<?php
$host = 'localhost';
$dbname = 'qu-dev-1-2';
$username = 'root';
$password = 'root'; // Mettez votre mot de passe local si nécessaire (ex: '' ou 'root')

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}
?>