<?php
/**
 * Database Setup Script
 * Run this file once to create workspace tables
 * Access via: http://localhost/roots/setup_database.php
 */

require_once __DIR__ . '/connect.php';

echo "<h1>Roots ERP - Database Setup</h1>";
echo "<p>Setting up workspace tables...</p>";

try {
    // Create workspaces table
    $sql = "CREATE TABLE IF NOT EXISTS `workspaces` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `name` VARCHAR(255) NOT NULL,
      `logo` VARCHAR(500) DEFAULT NULL,
      `industry` VARCHAR(100) DEFAULT 'retail_ecommerce',
      `company_size` VARCHAR(50) DEFAULT '1-10',
      `created_at` DATETIME NOT NULL,
      `setup_completed_at` DATETIME DEFAULT NULL,
      PRIMARY KEY (`id`),
      INDEX `idx_setup_completed` (`setup_completed_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    $pdo->exec($sql);
    echo "<p style='color: green;'>✓ Table 'workspaces' created successfully</p>";

    // Create workspace_members table
    $sql = "CREATE TABLE IF NOT EXISTS `workspace_members` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `workspace_id` INT(11) NOT NULL,
      `user_id` INT(11) NOT NULL,
      `role` ENUM('owner', 'admin', 'member') DEFAULT 'member',
      `joined_at` DATETIME NOT NULL,
      PRIMARY KEY (`id`),
      UNIQUE KEY `unique_workspace_user` (`workspace_id`, `user_id`),
      INDEX `idx_workspace` (`workspace_id`),
      INDEX `idx_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    $pdo->exec($sql);
    echo "<p style='color: green;'>✓ Table 'workspace_members' created successfully</p>";

    // Add foreign keys if not exist
    try {
        $sql = "ALTER TABLE `workspace_members`
                ADD CONSTRAINT `fk_workspace_members_workspace` 
                FOREIGN KEY (`workspace_id`) REFERENCES `workspaces` (`id`) 
                ON DELETE CASCADE ON UPDATE CASCADE";
        $pdo->exec($sql);
        echo "<p style='color: green;'>✓ Foreign key 'fk_workspace_members_workspace' added</p>";
    } catch (Exception $e) {
        echo "<p style='color: orange;'>⚠ Foreign key 'fk_workspace_members_workspace' already exists</p>";
    }

    try {
        $sql = "ALTER TABLE `workspace_members`
                ADD CONSTRAINT `fk_workspace_members_user` 
                FOREIGN KEY (`user_id`) REFERENCES `users` (`id_user`) 
                ON DELETE CASCADE ON UPDATE CASCADE";
        $pdo->exec($sql);
        echo "<p style='color: green;'>✓ Foreign key 'fk_workspace_members_user' added</p>";
    } catch (Exception $e) {
        echo "<p style='color: orange;'>⚠ Foreign key 'fk_workspace_members_user' already exists</p>";
    }

    // Create uploads directory
    $uploadDir = __DIR__ . '/uploads/workspaces';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
        echo "<p style='color: green;'>✓ Directory 'uploads/workspaces' created</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Directory 'uploads/workspaces' already exists</p>";
    }

    echo "<hr>";
    echo "<h2 style='color: green;'>✓ Setup completed successfully!</h2>";
    echo "<p><a href='dashboard.php'>Go to Dashboard</a></p>";
    echo "<p><strong>Note:</strong> You can delete this file (setup_database.php) after setup is complete.</p>";

} catch (Exception $e) {
    echo "<h2 style='color: red;'>✗ Error during setup</h2>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
    echo "<p>Please check your database connection and try again.</p>";
}
?>
