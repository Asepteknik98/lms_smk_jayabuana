<?php
// Upgrade only connection-local test fixtures to model real account checks.
function test_auth_accounts(PDO $db,array $accounts=[]): void {
    $db->exec('CREATE TEMPORARY TABLE IF NOT EXISTS roles(id INT PRIMARY KEY,nama_role VARCHAR(50))');
    $db->exec("INSERT IGNORE INTO roles VALUES(1,'Admin'),(2,'Guru'),(3,'Siswa')");
    $db->exec('CREATE TEMPORARY TABLE IF NOT EXISTS users(id INT PRIMARY KEY,role_id INT)');
    $db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS username VARCHAR(50) NOT NULL DEFAULT '',ADD COLUMN IF NOT EXISTS password VARCHAR(255) NOT NULL DEFAULT '',ADD COLUMN IF NOT EXISTS is_active INT NOT NULL DEFAULT 1");
    foreach($accounts as $id=>$role){
        $q=$db->prepare('SELECT id FROM users WHERE id=?');$q->execute([$id]);
        if(!$q->fetchColumn())$db->prepare('INSERT INTO users(id,role_id) VALUES(?,?)')->execute([$id,$role]);
    }
    $db->exec("UPDATE users SET username=CONCAT('Fixture',id) WHERE username=''");
}
