USE edutrack;

-- Recreate / reset default user accounts with fresh password hashes.
-- Passwords:
-- admin@edutrack.local     => Admin@2026!
-- principal@edutrack.local => Principal@2026!
-- teacher@edutrack.local   => Teacher@2026!
-- prefect@edutrack.local   => Prefect@2026!

INSERT INTO users (full_name, email, password_hash, role, is_active) VALUES
('Carlson Tantoh', 'admin@edutrack.local', '$2y$10$KxMNlopLaBv2f4/KtjnEU.TCz8Wl1.vbp7Gqju0CrNJmZHSK6CW/.', 'admin', 1),
('School Principal', 'principal@edutrack.local', '$2y$10$kKULALgKDU4RQ3ZR.v0LiuQqGKeRgRNtccbE4diN.mdAhSEfPxLE6', 'principal', 1),
('Demo Teacher', 'teacher@edutrack.local', '$2y$10$Rm2LtmOfjWASRAO/RdISRujzAvUlOwchdDq/JkKW19bkEmf6.a6re', 'teacher', 1),
('Class Prefect', 'prefect@edutrack.local', '$2y$10$/X9MrtxXtrNP/jQjVU.JMOJiVE6hZxIeUE2mbKdjzGQcClIvG69Pe', 'prefect', 1)
ON DUPLICATE KEY UPDATE
  full_name = VALUES(full_name),
  password_hash = VALUES(password_hash),
  role = VALUES(role),
  is_active = VALUES(is_active);

-- Ensure teacher profile exists for the default teacher account.
INSERT INTO teachers (user_id, salary_type, hourly_rate, fixed_salary, active)
SELECT id, 'hourly', 1500, NULL, 1
FROM users
WHERE email = 'teacher@edutrack.local'
ON DUPLICATE KEY UPDATE
  salary_type = VALUES(salary_type),
  hourly_rate = VALUES(hourly_rate),
  active = VALUES(active);
