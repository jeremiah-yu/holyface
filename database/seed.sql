-- Fictional seed data only — not real persons
USE holyface_amgs;

INSERT INTO roles (id, code, name) VALUES
  (1, 'admin', 'Admin / Registrar'),
  (2, 'teacher', 'Teacher'),
  (3, 'student', 'Student');

INSERT INTO strands (id, code, name, description) VALUES
  (1, 'ABM', 'Accountancy, Business & Management', 'Business and finance track'),
  (2, 'HUMSS', 'Humanities & Social Science', 'Social sciences and humanities'),
  (3, 'ICT', 'Information & Communication Technology', 'Technology and computing'),
  (4, 'HE', 'Home Economics', 'Hospitality and livelihood'),
  (5, 'STEM', 'Science, Technology, Engineering & Mathematics', 'STEM track');

INSERT INTO school_years (id, label, is_current, starts_on, ends_on) VALUES
  (1, '2025-2026', 1, '2025-06-02', '2026-04-15');

INSERT INTO subject_categories (id, code, name) VALUES
  (1, 'core', 'Core Subjects'),
  (2, 'applied', 'Applied Subjects'),
  (3, 'specialized', 'Specialized Subjects');

-- Password for all demo users: HolyFace@2026
-- Argon2id hash generated with PHP password_hash(..., PASSWORD_ARGON2ID)
SET @pwd = '$argon2id$v=19$m=65536,t=4,p=1$M2RmN2tNa2U4RDFxQVY5Uw$qTQ0H8W5xfZhMiRZj+UMi/SnfjTE98jiFhqN52XT0Oo';

INSERT INTO users (id, role_id, email, username, password_hash, full_name) VALUES
  (1, 1, 'registrar@holyface.local', 'registrar', @pwd, 'Maria Clara Santos'),
  (2, 2, 'teacher.reyes@holyface.local', 'mreyes', @pwd, 'Mark Anthony Reyes'),
  (3, 2, 'teacher.cruz@holyface.local', 'lcruz', @pwd, 'Lea Francine Cruz'),
  (4, 3, 'student001@holyface.local', 's001', @pwd, 'Juan Miguel Dela Rosa'),
  (5, 3, 'student002@holyface.local', 's002', @pwd, 'Ana Sofia Mendoza'),
  (6, 3, 'student003@holyface.local', 's003', @pwd, 'Rafael Torres'),
  (7, 3, 'student004@holyface.local', 's004', @pwd, 'Isabelle Navarro'),
  (8, 3, 'student005@holyface.local', 's005', @pwd, 'Ethan Villanueva');

INSERT INTO teachers (id, user_id, employee_no, department) VALUES
  (1, 2, 'T-2025-001', 'Mathematics & STEM'),
  (2, 3, 'T-2025-002', 'Languages & HUMSS');

INSERT INTO students (id, user_id, lrn, strand_id, grade_level, section_code, school_year_id, photo_path, guardian_name, guardian_contact, date_of_birth) VALUES
  (1, 4, '123456789012', 5, 11, 'STEM-A', 1, '/uploads/students/placeholder.png', 'Rosa Dela Rosa', '09171234501', '2009-03-14'),
  (2, 5, '123456789013', 1, 11, 'ABM-A', 1, '/uploads/students/placeholder.png', 'Carlos Mendoza', '09171234502', '2009-07-22'),
  (3, 6, '123456789014', 3, 12, 'ICT-B', 1, '/uploads/students/placeholder.png', 'Teresa Torres', '09171234503', '2008-11-05'),
  (4, 7, '123456789015', 2, 12, 'HUMSS-A', 1, '/uploads/students/placeholder.png', 'Pedro Navarro', '09171234504', '2008-01-18'),
  (5, 8, '123456789016', 4, 11, 'HE-A', 1, '/uploads/students/placeholder.png', 'Liza Villanueva', '09171234505', '2009-09-30');

INSERT INTO subjects (id, subject_code, title, category_id, strand_id, grade_level) VALUES
  (1, 'G11-MATH', 'General Mathematics', 1, NULL, 11),
  (2, 'G11-ENG', 'English for Academic Purposes', 1, NULL, 11),
  (3, 'G11-STEM-CHEM', 'General Chemistry', 3, 5, 11),
  (4, 'G12-ICT-PROG', 'Programming (Java)', 3, 3, 12),
  (5, 'G11-ABM-BUS', 'Business Mathematics', 3, 1, 11);

INSERT INTO class_sections (id, school_year_id, strand_id, grade_level, section_code, adviser_teacher_id) VALUES
  (1, 1, 5, 11, 'STEM-A', 1),
  (2, 1, 1, 11, 'ABM-A', 2),
  (3, 1, 3, 12, 'ICT-B', 1),
  (4, 1, 2, 12, 'HUMSS-A', 2),
  (5, 1, 4, 11, 'HE-A', 2);

INSERT INTO class_subject_assignments (id, class_section_id, subject_id, teacher_id) VALUES
  (1, 1, 1, 1),
  (2, 1, 3, 1),
  (3, 2, 1, 2),
  (4, 2, 5, 2),
  (5, 3, 4, 1);

INSERT INTO grade_component_weights (class_subject_assignment_id, written_work_pct, performance_task_pct, quarterly_assessment_pct) VALUES
  (1, 40, 40, 20),
  (2, 40, 40, 20),
  (3, 40, 40, 20),
  (4, 40, 40, 20),
  (5, 40, 40, 20);

INSERT INTO enrollments (id, student_id, class_section_id, status, enrolled_at) VALUES
  (1, 1, 1, 'enrolled', '2025-06-02'),
  (2, 2, 2, 'enrolled', '2025-06-02'),
  (3, 3, 3, 'enrolled', '2025-06-02'),
  (4, 4, 4, 'enrolled', '2025-06-02'),
  (5, 5, 5, 'enrolled', '2025-06-02');

INSERT INTO announcements (title, body, audience, created_by) VALUES
  ('Welcome to School Year 2025-2026', 'Classes begin June 2. Please complete enrollment requirements at the Registrar.', 'public', 1),
  ('Quarter 1 Grade Encoding', 'Teachers: please encode Q1 grades by October 30 via the AMGS Teacher Desktop app.', 'teachers', 1),
  ('Student ID Digital Card', 'Download the Holy Face Student app to access your official digital ID.', 'students', 1);

INSERT INTO study_materials (subject_id, title, content, tags, uploaded_by) VALUES
  (1, 'Linear Equations Review', 'A linear equation has the form ax + b = c. Solve by isolating x. Practice: 2x + 5 = 15 → x = 5.', 'algebra,review', 2),
  (3, 'Mole Concept Intro', 'The mole links atomic mass to grams. One mole contains 6.022×10^23 particles (Avogadro constant).', 'chemistry,mole', 1),
  (4, 'Java Variables', 'int count = 0; String name = "Holy Face"; Use camelCase for variable names in Java.', 'java,basics', 1);

INSERT INTO consent_logs (user_id, consent_type, version, granted, ip_address) VALUES
  (4, 'data_privacy', '1.0', 1, '127.0.0.1'),
  (5, 'data_privacy', '1.0', 1, '127.0.0.1');
