-- Runs once, when the data volume is created. The test suite uses its own database.
CREATE DATABASE IF NOT EXISTS notes_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON notes_test.* TO 'notes'@'%';
