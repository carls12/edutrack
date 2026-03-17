CREATE DATABASE IF NOT EXISTS edutrack CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE edutrack;

DROP TABLE IF EXISTS timetable_entries;
DROP TABLE IF EXISTS teacher_availability;
DROP TABLE IF EXISTS teacher_assignments;
DROP TABLE IF EXISTS attendance;
DROP TABLE IF EXISTS prefect_password_audit;
DROP TABLE IF EXISTS periods;
DROP TABLE IF EXISTS subjects;
DROP TABLE IF EXISTS classes;
DROP TABLE IF EXISTS teachers;
DROP TABLE IF EXISTS school_settings;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','principal','prefect','teacher') NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE school_settings (
  id INT PRIMARY KEY DEFAULT 1,
  school_name VARCHAR(190) NOT NULL DEFAULT 'EduTrack School',
  logo_path VARCHAR(255) DEFAULT NULL,
  watermark_path VARCHAR(255) DEFAULT NULL,
  currency VARCHAR(10) NOT NULL DEFAULT 'XAF',
  timezone VARCHAR(60) NOT NULL DEFAULT 'Africa/Douala',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
INSERT INTO school_settings (id, school_name) VALUES (1, 'EduTrack School')
  ON DUPLICATE KEY UPDATE school_name=VALUES(school_name);

CREATE TABLE teachers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  salary_type ENUM('hourly','fixed') NOT NULL DEFAULT 'hourly',
  hourly_rate DECIMAL(10,2) DEFAULT NULL,
  fixed_salary DECIMAL(10,2) DEFAULT NULL,
  phone VARCHAR(40) DEFAULT NULL,
  stamp_code VARCHAR(20) DEFAULT NULL UNIQUE,
  stamp_secret VARCHAR(80) DEFAULT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE classes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  grade_level VARCHAR(40) DEFAULT NULL,
  room_number VARCHAR(40) DEFAULT NULL,
  class_master_user_id INT DEFAULT NULL,
  prefect_user_id INT DEFAULT NULL,
  FOREIGN KEY (class_master_user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (prefect_user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE subjects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  hod_user_id INT DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (hod_user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE periods (
  id INT AUTO_INCREMENT PRIMARY KEY,
  label VARCHAR(50) NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  sort_order INT NOT NULL,
  is_teaching_period TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE teacher_assignments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  teacher_user_id INT NOT NULL,
  subject_id INT NOT NULL,
  class_id INT NOT NULL,
  hours_per_week INT NOT NULL DEFAULT 2,
  UNIQUE KEY uniq_assignment (teacher_user_id, subject_id, class_id),
  FOREIGN KEY (teacher_user_id) REFERENCES users(id),
  FOREIGN KEY (subject_id) REFERENCES subjects(id),
  FOREIGN KEY (class_id) REFERENCES classes(id)
);

CREATE TABLE teacher_availability (
  id INT AUTO_INCREMENT PRIMARY KEY,
  teacher_user_id INT NOT NULL,
  day_of_week TINYINT NOT NULL, -- 1=Mon ... 7=Sun
  period_id INT NOT NULL,
  is_available TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uniq_avail (teacher_user_id, day_of_week, period_id),
  FOREIGN KEY (teacher_user_id) REFERENCES users(id),
  FOREIGN KEY (period_id) REFERENCES periods(id)
);

CREATE TABLE timetable_entries (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  class_id INT NOT NULL,
  subject_id INT NOT NULL,
  teacher_user_id INT NOT NULL,
  day_of_week TINYINT NOT NULL, -- 1=Mon ... 7=Sun
  period_id INT NOT NULL,
  source ENUM('auto','manual') NOT NULL DEFAULT 'auto',
  is_locked TINYINT(1) NOT NULL DEFAULT 0,
  room_override VARCHAR(40) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_slot (class_id, day_of_week, period_id),
  KEY idx_tt_class_day_period (class_id, day_of_week, period_id),
  KEY idx_tt_teacher_day_period (teacher_user_id, day_of_week, period_id),
  FOREIGN KEY (class_id) REFERENCES classes(id),
  FOREIGN KEY (subject_id) REFERENCES subjects(id),
  FOREIGN KEY (teacher_user_id) REFERENCES users(id),
  FOREIGN KEY (period_id) REFERENCES periods(id)
);

CREATE TABLE attendance (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  teacher_user_id INT NOT NULL,
  class_id INT DEFAULT NULL,
  status ENUM('arrived','departed','absent') NOT NULL,
  reason VARCHAR(255) DEFAULT NULL,
  event_time DATETIME NOT NULL,
  worked_minutes INT NOT NULL DEFAULT 0,
  source ENUM('manual','prefect_card','teacher_stamp') NOT NULL DEFAULT 'manual',
  created_by_user_id INT NOT NULL,
  validation_status ENUM('pending','validated','rejected') NOT NULL DEFAULT 'pending',
  validated_by_user_id INT DEFAULT NULL,
  validated_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (teacher_user_id) REFERENCES users(id),
  FOREIGN KEY (class_id) REFERENCES classes(id),
  FOREIGN KEY (created_by_user_id) REFERENCES users(id),
  FOREIGN KEY (validated_by_user_id) REFERENCES users(id)
);

CREATE TABLE prefect_password_audit (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  prefect_user_id INT NOT NULL,
  class_id INT NOT NULL,
  email VARCHAR(190) NOT NULL,
  plain_password VARCHAR(120) NOT NULL,
  created_by_user_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (prefect_user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Default users (passwords: Admin@2026! / Principal@2026! / Teacher@2026! / Prefect@2026!)
-- NOTE: Change these immediately in production.
INSERT INTO users(full_name,email,password_hash,role) VALUES
('Carlson Tantoh','admin@edutrack.local',
 '$2y$10$KxMNlopLaBv2f4/KtjnEU.TCz8Wl1.vbp7Gqju0CrNJmZHSK6CW/.','admin'),
('School Principal','principal@edutrack.local',
 '$2y$10$kKULALgKDU4RQ3ZR.v0LiuQqGKeRgRNtccbE4diN.mdAhSEfPxLE6','principal'),
('Demo Teacher','teacher@edutrack.local',
 '$2y$10$Rm2LtmOfjWASRAO/RdISRujzAvUlOwchdDq/JkKW19bkEmf6.a6re','teacher'),
('Class Prefect','prefect@edutrack.local',
 '$2y$10$/X9MrtxXtrNP/jQjVU.JMOJiVE6hZxIeUE2mbKdjzGQcClIvG69Pe','prefect');

INSERT INTO teachers(user_id,salary_type,hourly_rate,stamp_code,stamp_secret) 
SELECT id,'hourly',1500,'TR000001','demo-teacher-secret' FROM users WHERE email='teacher@edutrack.local';

-- Example classes
INSERT INTO classes(name,grade_level,room_number) VALUES
('Form 1A','Form 1','A1'),
('Form 2B','Form 2','B2');

-- Example subjects
INSERT INTO subjects(code,name) VALUES
('MATH','Mathematics'),
('ENG','English'),
('PHY','Physics');

-- Example periods (Mon-Fri assumed in app)
INSERT INTO periods(label,start_time,end_time,sort_order,is_teaching_period) VALUES
('P1','08:00','08:50',1,1),
('P2','08:55','09:45',2,1),
('P3','09:50','10:40',3,1),
('BREAK','10:40','11:00',4,0),
('P4','11:00','11:50',5,1),
('P5','11:55','12:45',6,1);

-- Example assignment: teacher teaches math in Form 1A for 3 periods/week
INSERT INTO teacher_assignments(teacher_user_id,subject_id,class_id,hours_per_week)
SELECT u.id, s.id, c.id, 3
FROM users u, subjects s, classes c
WHERE u.email='teacher@edutrack.local' AND s.code='MATH' AND c.name='Form 1A';
