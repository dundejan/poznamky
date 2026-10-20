-- Runs once, when the data volume is created. The test suite uses its own database.
CREATE DATABASE IF NOT EXISTS notes_test;
GRANT ALL PRIVILEGES ON notes_test.* TO 'notes'@'%';
