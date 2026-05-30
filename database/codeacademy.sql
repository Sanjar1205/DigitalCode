-- ═══════════════════════════════════════════════════════════
-- CodeAcademy - Online Dasturlash O'qitish Platformasi
-- Database: MySQL 8.0+ (InnoDB)
-- Charset: utf8mb4_unicode_ci
-- ═══════════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS `codeacademy` 
    DEFAULT CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

USE `codeacademy`;

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

-- ─────────────────────────────────────────────────────────
-- 1. USERS - Foydalanuvchilar (Admin/Teacher/Student)
-- ─────────────────────────────────────────────────────────
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(150) NOT NULL,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'teacher', 'student') NOT NULL DEFAULT 'student',
    `avatar` VARCHAR(255) DEFAULT 'default.png',
    `phone` VARCHAR(20) DEFAULT NULL,
    `status` ENUM('active', 'blocked', 'pending') NOT NULL DEFAULT 'active',
    `language` ENUM('uz', 'ru', 'en') NOT NULL DEFAULT 'uz',
    `last_login` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_role` (`role`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────
-- 2. SUBJECTS - Fanlar
-- ─────────────────────────────────────────────────────────
DROP TABLE IF EXISTS `subjects`;
CREATE TABLE `subjects` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT,
    `programming_language` ENUM('cpp', 'java', 'python', 'javascript', 'php', 'csharp') NOT NULL,
    `image` VARCHAR(255) DEFAULT 'default-subject.jpg',
    `created_by` INT UNSIGNED NOT NULL,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_language` (`programming_language`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────
-- 3. SUBJECT_TEACHERS - Fan-O'qituvchi biriktirish
-- ─────────────────────────────────────────────────────────
DROP TABLE IF EXISTS `subject_teachers`;
CREATE TABLE `subject_teachers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `subject_id` INT UNSIGNED NOT NULL,
    `teacher_id` INT UNSIGNED NOT NULL,
    `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_assignment` (`subject_id`, `teacher_id`),
    FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────
-- 4. SUBJECT_STUDENTS - Fan-Talaba biriktirish
-- ─────────────────────────────────────────────────────────
DROP TABLE IF EXISTS `subject_students`;
CREATE TABLE `subject_students` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `subject_id` INT UNSIGNED NOT NULL,
    `student_id` INT UNSIGNED NOT NULL,
    `enrolled_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `status` ENUM('active', 'completed', 'dropped') DEFAULT 'active',
    UNIQUE KEY `unique_enrollment` (`subject_id`, `student_id`),
    FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────
-- 5. TOPICS - Mavzular
-- ─────────────────────────────────────────────────────────
DROP TABLE IF EXISTS `topics`;
CREATE TABLE `topics` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `subject_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `content` LONGTEXT,
    `order_number` INT NOT NULL DEFAULT 1,
    `video_url` VARCHAR(500) DEFAULT NULL,
    `video_duration` INT DEFAULT 0 COMMENT 'Sekundlarda',
    `passing_score` INT DEFAULT 60 COMMENT 'O''tish bali (%)',
    `status` ENUM('draft', 'published') DEFAULT 'draft',
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_subject_order` (`subject_id`, `order_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────
-- 6. TOPIC_FILES - Mavzu fayllari (PDF, rasmlar)
-- ─────────────────────────────────────────────────────────
DROP TABLE IF EXISTS `topic_files`;
CREATE TABLE `topic_files` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `topic_id` INT UNSIGNED NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `file_type` ENUM('image', 'pdf', 'video', 'document', 'other') NOT NULL,
    `file_size` INT DEFAULT 0,
    `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`topic_id`) REFERENCES `topics`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────
-- 7. QUESTIONS - Test savollari
-- ─────────────────────────────────────────────────────────
DROP TABLE IF EXISTS `questions`;
CREATE TABLE `questions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `topic_id` INT UNSIGNED NOT NULL,
    `question_text` TEXT NOT NULL,
    `question_type` ENUM('single', 'multiple', 'true_false') NOT NULL DEFAULT 'single',
    `points` INT DEFAULT 1,
    `image` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`topic_id`) REFERENCES `topics`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────
-- 8. ANSWERS - Test javoblari
-- ─────────────────────────────────────────────────────────
DROP TABLE IF EXISTS `answers`;
CREATE TABLE `answers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `question_id` INT UNSIGNED NOT NULL,
    `answer_text` TEXT NOT NULL,
    `is_correct` BOOLEAN DEFAULT FALSE,
    `order_number` INT DEFAULT 0,
    FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────
-- 9. TASKS - Amaliy masalalar
-- ─────────────────────────────────────────────────────────
DROP TABLE IF EXISTS `tasks`;
CREATE TABLE `tasks` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `topic_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` LONGTEXT NOT NULL,
    `input_example` TEXT,
    `output_example` TEXT,
    `time_limit` INT DEFAULT 1000 COMMENT 'Millisekundlarda',
    `memory_limit` INT DEFAULT 256 COMMENT 'MB',
    `difficulty` ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`topic_id`) REFERENCES `topics`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────
-- 10. TEST_CASES - Yashirin test caselar
-- ─────────────────────────────────────────────────────────
DROP TABLE IF EXISTS `test_cases`;
CREATE TABLE `test_cases` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `task_id` INT UNSIGNED NOT NULL,
    `input_data` TEXT NOT NULL,
    `expected_output` TEXT NOT NULL,
    `is_hidden` BOOLEAN DEFAULT TRUE,
    `order_number` INT DEFAULT 0,
    FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────
-- 11. STUDENT_PROGRESS - Talaba progressi (CHEKLOV TIZIMI)
-- ─────────────────────────────────────────────────────────
DROP TABLE IF EXISTS `student_progress`;
CREATE TABLE `student_progress` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT UNSIGNED NOT NULL,
    `topic_id` INT UNSIGNED NOT NULL,
    `content_read` BOOLEAN DEFAULT FALSE COMMENT 'Matn 100% o''qildi',
    `content_scroll_percent` INT DEFAULT 0,
    `video_watched` BOOLEAN DEFAULT FALSE COMMENT 'Video 90%+ ko''rildi',
    `video_watch_percent` INT DEFAULT 0,
    `test_passed` BOOLEAN DEFAULT FALSE COMMENT '60%+ to''g''ri',
    `test_score` DECIMAL(5,2) DEFAULT 0.00,
    `task_completed` BOOLEAN DEFAULT FALSE COMMENT '"3" baho yoki yuqori',
    `task_grade` INT DEFAULT 0 COMMENT '2,3,4,5',
    `unlocked` BOOLEAN DEFAULT FALSE COMMENT 'Mavzu ochilganmi',
    `time_spent` INT DEFAULT 0 COMMENT 'Sekundlarda',
    `started_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `completed_at` TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY `unique_progress` (`student_id`, `topic_id`),
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`topic_id`) REFERENCES `topics`(`id`) ON DELETE CASCADE,
    INDEX `idx_student_topic` (`student_id`, `topic_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────
-- 12. TEST_RESULTS - Test natijalari
-- ─────────────────────────────────────────────────────────
DROP TABLE IF EXISTS `test_results`;
CREATE TABLE `test_results` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT UNSIGNED NOT NULL,
    `topic_id` INT UNSIGNED NOT NULL,
    `question_id` INT UNSIGNED NOT NULL,
    `selected_answers` VARCHAR(255) COMMENT 'Vergul bilan ajratilgan',
    `is_correct` BOOLEAN DEFAULT FALSE,
    `points_earned` DECIMAL(5,2) DEFAULT 0.00,
    `attempt_number` INT DEFAULT 1,
    `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`topic_id`) REFERENCES `topics`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`) ON DELETE CASCADE,
    INDEX `idx_student_attempt` (`student_id`, `topic_id`, `attempt_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────
-- 13. TASK_SUBMISSIONS - Kod topshiriqlari
-- ─────────────────────────────────────────────────────────
DROP TABLE IF EXISTS `task_submissions`;
CREATE TABLE `task_submissions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT UNSIGNED NOT NULL,
    `task_id` INT UNSIGNED NOT NULL,
    `code` LONGTEXT NOT NULL,
    `language` ENUM('cpp', 'java', 'python', 'javascript', 'php', 'csharp') NOT NULL,
    `passed_tests` INT DEFAULT 0,
    `total_tests` INT DEFAULT 0,
    `score_percent` DECIMAL(5,2) DEFAULT 0.00,
    `grade` TINYINT DEFAULT 0 COMMENT '2,3,4,5',
    `execution_time` INT DEFAULT 0 COMMENT 'ms',
    `memory_used` INT DEFAULT 0 COMMENT 'KB',
    `status` ENUM('pending', 'accepted', 'wrong_answer', 'time_limit', 'memory_limit', 'compile_error', 'runtime_error') DEFAULT 'pending',
    `compiler_output` TEXT,
    `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE,
    INDEX `idx_student_task` (`student_id`, `task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────
-- 14. GRADES - Baholar (umumiy gradebook)
-- ─────────────────────────────────────────────────────────
DROP TABLE IF EXISTS `grades`;
CREATE TABLE `grades` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT UNSIGNED NOT NULL,
    `subject_id` INT UNSIGNED NOT NULL,
    `topic_id` INT UNSIGNED DEFAULT NULL,
    `grade` TINYINT NOT NULL COMMENT '2,3,4,5',
    `type` ENUM('test', 'task', 'manual', 'final') NOT NULL,
    `comment` TEXT,
    `given_by` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`subject_id`) REFERENCES `subjects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`topic_id`) REFERENCES `topics`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`given_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_student_subject` (`student_id`, `subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────
-- 15. AI_CHAT_HISTORY - AI yordamchi tarixi
-- ─────────────────────────────────────────────────────────
DROP TABLE IF EXISTS `ai_chat_history`;
CREATE TABLE `ai_chat_history` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT UNSIGNED NOT NULL,
    `message` TEXT NOT NULL,
    `response` LONGTEXT,
    `tokens_used` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_student_date` (`student_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────
-- 16. ACTIVITY_LOGS - Audit log
-- ─────────────────────────────────────────────────────────
DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `details` TEXT,
    `ip_address` VARCHAR(45),
    `user_agent` VARCHAR(255),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_user_action` (`user_id`, `action`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────
-- 17. SETTINGS - Tizim sozlamalari
-- ─────────────────────────────────────────────────────────
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT,
    `setting_group` VARCHAR(50) DEFAULT 'general',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ═══════════════════════════════════════════════════════════
-- BOSHLANG'ICH MA'LUMOTLAR
-- ═══════════════════════════════════════════════════════════

-- Test foydalanuvchilar (parol: barchasiga "password123")
-- Hash: $2y$10$ generated via password_hash('password123', PASSWORD_BCRYPT)
INSERT INTO `users` (`full_name`, `username`, `email`, `password`, `role`, `status`) VALUES
('Administrator', 'admin', 'admin@codeacademy.uz', '$2y$12$PP.iJ3j0giiVofPQILtE3.5XMIXD5QSEzmBxsVZus0wq6jFNRHDGi', 'admin', 'active'),
('Aliyev Akmal', 'teacher1', 'teacher@codeacademy.uz', '$2y$12$PP.iJ3j0giiVofPQILtE3.5XMIXD5QSEzmBxsVZus0wq6jFNRHDGi', 'teacher', 'active'),
('Karimov Doniyor', 'teacher2', 'teacher2@codeacademy.uz', '$2y$12$PP.iJ3j0giiVofPQILtE3.5XMIXD5QSEzmBxsVZus0wq6jFNRHDGi', 'teacher', 'active'),
('Toshmatov Sardor', 'student1', 'student1@codeacademy.uz', '$2y$12$PP.iJ3j0giiVofPQILtE3.5XMIXD5QSEzmBxsVZus0wq6jFNRHDGi', 'student', 'active'),
('Yusupova Madina', 'student2', 'student2@codeacademy.uz', '$2y$12$PP.iJ3j0giiVofPQILtE3.5XMIXD5QSEzmBxsVZus0wq6jFNRHDGi', 'student', 'active'),
('Rahimov Bekzod', 'student3', 'student3@codeacademy.uz', '$2y$12$PP.iJ3j0giiVofPQILtE3.5XMIXD5QSEzmBxsVZus0wq6jFNRHDGi', 'student', 'active');

-- ✅ Yuqoridagi parollar real bcrypt hash. Barcha demo userlar uchun parol: password123
-- Production muhitda parollarni albatta almashtiring (INSTALL.md ga qarang)

-- Demo fanlar
INSERT INTO `subjects` (`name`, `description`, `programming_language`, `created_by`) VALUES
('C++ Asoslari', 'C++ dasturlash tilining boshlang''ich kursi. Sintaksis, ma''lumot turlari, tsikllar va funksiyalar.', 'cpp', 1),
('Python Dasturlash', 'Python tilida dasturlash. Boshlang''ichdan murakkab loyihalargacha.', 'python', 1),
('Java Asoslari', 'Java OOP, Spring Framework va Web dasturlash.', 'java', 1);

-- O'qituvchilarni fanlarga biriktirish
INSERT INTO `subject_teachers` (`subject_id`, `teacher_id`) VALUES
(1, 2), (2, 2), (3, 3);

-- Talabalarni fanlarga biriktirish
INSERT INTO `subject_students` (`subject_id`, `student_id`) VALUES
(1, 4), (1, 5), (1, 6),
(2, 4), (2, 5),
(3, 6);

-- Demo mavzular (C++ Asoslari uchun)
INSERT INTO `topics` (`subject_id`, `title`, `content`, `order_number`, `video_url`, `passing_score`, `status`, `created_by`) VALUES
(1, '1-Mavzu: C++ ga kirish', '<h2>C++ tili haqida</h2><p>C++ — bu yuqori darajadagi, umumiy maqsadli dasturlash tilidir. U 1979-yilda Bjarne Stroustrup tomonidan yaratilgan.</p><h3>Birinchi dastur</h3><pre><code>#include &lt;iostream&gt;\nusing namespace std;\n\nint main() {\n    cout &lt;&lt; "Salom, Dunyo!" &lt;&lt; endl;\n    return 0;\n}</code></pre><p>Bu dastur ekranga "Salom, Dunyo!" yozuvini chiqaradi.</p>', 1, 'https://www.youtube.com/embed/vLnPwxZdW4Y', 60, 'published', 2),
(1, '2-Mavzu: O''zgaruvchilar va ma''lumot turlari', '<h2>O''zgaruvchilar</h2><p>O''zgaruvchi — bu xotirada ma''lumot saqlash uchun nomlangan joy.</p><h3>Asosiy turlar:</h3><ul><li><b>int</b> — butun sonlar</li><li><b>float, double</b> — kasr sonlar</li><li><b>char</b> — bir belgi</li><li><b>string</b> — matn</li><li><b>bool</b> — true/false</li></ul>', 2, 'https://www.youtube.com/embed/zB9RI8_wExo', 60, 'published', 2),
(1, '3-Mavzu: Shartli operatorlar (if-else)', '<h2>If-Else operatori</h2><p>Shartga qarab dasturning turli qismlarini bajarish.</p><pre><code>if (a > b) {\n    cout &lt;&lt; "a katta";\n} else {\n    cout &lt;&lt; "b katta yoki teng";\n}</code></pre>', 3, 'https://www.youtube.com/embed/example3', 60, 'published', 2);

-- Demo savollar (1-mavzu uchun)
INSERT INTO `questions` (`topic_id`, `question_text`, `question_type`, `points`) VALUES
(1, 'C++ tilini kim yaratgan?', 'single', 1),
(1, 'C++ qachon yaratilgan?', 'single', 1),
(1, 'main() funksiyasi qaysi qaytaradi?', 'single', 1),
(1, 'Quyidagilardan qaysi biri C++ kalit so''zi?', 'multiple', 2),
(1, 'C++ obyektga yo''naltirilgan tilmi?', 'true_false', 1);

-- Demo javoblar
INSERT INTO `answers` (`question_id`, `answer_text`, `is_correct`, `order_number`) VALUES
-- 1-savol
(1, 'Bjarne Stroustrup', TRUE, 1),
(1, 'Dennis Ritchie', FALSE, 2),
(1, 'James Gosling', FALSE, 3),
(1, 'Guido van Rossum', FALSE, 4),
-- 2-savol
(2, '1972', FALSE, 1),
(2, '1979', TRUE, 2),
(2, '1989', FALSE, 3),
(2, '1995', FALSE, 4),
-- 3-savol
(3, 'void', FALSE, 1),
(3, 'int', TRUE, 2),
(3, 'string', FALSE, 3),
(3, 'bool', FALSE, 4),
-- 4-savol (multiple)
(4, 'class', TRUE, 1),
(4, 'function', FALSE, 2),
(4, 'public', TRUE, 3),
(4, 'method', FALSE, 4),
-- 5-savol (true/false)
(5, 'Ha (True)', TRUE, 1),
(5, 'Yo''q (False)', FALSE, 2);

-- Demo amaliy masala
INSERT INTO `tasks` (`topic_id`, `title`, `description`, `input_example`, `output_example`, `time_limit`, `memory_limit`, `difficulty`) VALUES
(1, 'Ikki sonning yig''indisi', 'Ikkita butun son qabul qilib, ularning yig''indisini chiqaring.', '5 3', '8', 1000, 256, 'easy');

-- Demo test caselar
INSERT INTO `test_cases` (`task_id`, `input_data`, `expected_output`, `is_hidden`, `order_number`) VALUES
(1, '5 3', '8', FALSE, 1),
(1, '10 20', '30', FALSE, 2),
(1, '0 0', '0', TRUE, 3),
(1, '-5 5', '0', TRUE, 4),
(1, '100 200', '300', TRUE, 5),
(1, '999 1', '1000', TRUE, 6),
(1, '-100 -200', '-300', TRUE, 7);

-- Tizim sozlamalari
INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_group`) VALUES
('site_name', 'CodeAcademy', 'general'),
('site_logo', 'logo.png', 'general'),
('primary_color', '#4F46E5', 'theme'),
('secondary_color', '#10B981', 'theme'),
('site_language', 'uz', 'general'),
('judge0_api_key', '', 'api'),
('judge0_api_url', 'https://judge0-ce.p.rapidapi.com', 'api'),
('openai_api_key', '', 'api'),
('claude_api_key', '', 'api'),
('ai_model', 'claude-3-5-sonnet-20241022', 'api'),
('max_login_attempts', '5', 'security'),
('session_timeout', '3600', 'security'),
('enable_ai_assistant', '1', 'features'),
('default_passing_score', '60', 'features');

-- ═══════════════════════════════════════════════════════════
-- TUGADI
-- ═══════════════════════════════════════════════════════════
