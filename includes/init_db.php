<?php
require_once __DIR__ . '/database.php';

function initializeDatabase() {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id SERIAL PRIMARY KEY,
                email VARCHAR(255) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                role VARCHAR(20) NOT NULL CHECK (role IN ('student', 'professor', 'admin')),
                student_id VARCHAR(50) UNIQUE,
                phone VARCHAR(20),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
            
            CREATE TABLE IF NOT EXISTS groups (
                id SERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                year VARCHAR(50),
                specialization VARCHAR(100),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
            
            CREATE TABLE IF NOT EXISTS courses (
                id SERIAL PRIMARY KEY,
                code VARCHAR(20) UNIQUE NOT NULL,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                professor_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
                credits INTEGER DEFAULT 3,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
            
            CREATE TABLE IF NOT EXISTS course_groups (
                id SERIAL PRIMARY KEY,
                course_id INTEGER REFERENCES courses(id) ON DELETE CASCADE,
                group_id INTEGER REFERENCES groups(id) ON DELETE CASCADE,
                UNIQUE(course_id, group_id)
            );
            
            CREATE TABLE IF NOT EXISTS student_groups (
                id SERIAL PRIMARY KEY,
                student_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
                group_id INTEGER REFERENCES groups(id) ON DELETE CASCADE,
                UNIQUE(student_id, group_id)
            );
            
            CREATE TABLE IF NOT EXISTS attendance_sessions (
                id SERIAL PRIMARY KEY,
                course_id INTEGER REFERENCES courses(id) ON DELETE CASCADE,
                group_id INTEGER REFERENCES groups(id) ON DELETE CASCADE,
                session_date DATE NOT NULL,
                session_time TIME,
                session_type VARCHAR(50) DEFAULT 'lecture',
                status VARCHAR(20) DEFAULT 'open' CHECK (status IN ('open', 'closed')),
                notes TEXT,
                created_by INTEGER REFERENCES users(id),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
            
            CREATE TABLE IF NOT EXISTS attendance_records (
                id SERIAL PRIMARY KEY,
                session_id INTEGER REFERENCES attendance_sessions(id) ON DELETE CASCADE,
                student_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
                status VARCHAR(20) DEFAULT 'absent' CHECK (status IN ('present', 'absent', 'late', 'excused')),
                participation_score INTEGER DEFAULT 0 CHECK (participation_score >= 0 AND participation_score <= 10),
                behavior_note TEXT,
                marked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                marked_by INTEGER REFERENCES users(id),
                UNIQUE(session_id, student_id)
            );
            
            CREATE TABLE IF NOT EXISTS justifications (
                id SERIAL PRIMARY KEY,
                student_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
                session_id INTEGER REFERENCES attendance_sessions(id) ON DELETE CASCADE,
                reason TEXT NOT NULL,
                file_path VARCHAR(500),
                status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected')),
                admin_notes TEXT,
                reviewed_by INTEGER REFERENCES users(id),
                reviewed_at TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
            
            CREATE TABLE IF NOT EXISTS notifications (
                id SERIAL PRIMARY KEY,
                user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
                title VARCHAR(255) NOT NULL,
                message TEXT,
                is_read BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");
        
        $result = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")->fetch();
        if ($result['count'] == 0) {
            $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
            $profPassword = password_hash('prof123', PASSWORD_DEFAULT);
            $studentPassword = password_hash('student123', PASSWORD_DEFAULT);
            
            $pdo->exec("
                INSERT INTO users (email, password, first_name, last_name, role) VALUES
                ('admin@univ-alger.dz', '$adminPassword', 'Admin', 'System', 'admin'),
                ('prof@univ-alger.dz', '$profPassword', 'Mohamed', 'Boutaba', 'professor'),
                ('student1@univ-alger.dz', '$studentPassword', 'Ahmed', 'Benali', 'student'),
                ('student2@univ-alger.dz', '$studentPassword', 'Fatima', 'Cherif', 'student'),
                ('student3@univ-alger.dz', '$studentPassword', 'Karim', 'Hadj', 'student')
                ON CONFLICT (email) DO NOTHING;
            ");
            
            $pdo->exec("
                UPDATE users SET student_id = 'STU001' WHERE email = 'student1@univ-alger.dz';
                UPDATE users SET student_id = 'STU002' WHERE email = 'student2@univ-alger.dz';
                UPDATE users SET student_id = 'STU003' WHERE email = 'student3@univ-alger.dz';
            ");
            
            $pdo->exec("
                INSERT INTO groups (name, year, specialization) VALUES
                ('Group A', '2024-2025', 'Computer Science'),
                ('Group B', '2024-2025', 'Computer Science'),
                ('Group C', '2024-2025', 'Information Systems')
                ON CONFLICT DO NOTHING;
            ");
            
            $pdo->exec("
                INSERT INTO courses (code, name, description, professor_id, credits) 
                SELECT 'AWP101', 'Advanced Web Programming', 'Learn modern web development techniques', id, 4
                FROM users WHERE email = 'prof@univ-alger.dz'
                ON CONFLICT (code) DO NOTHING;
                
                INSERT INTO courses (code, name, description, professor_id, credits)
                SELECT 'DB201', 'Database Systems', 'Database design and management', id, 3
                FROM users WHERE email = 'prof@univ-alger.dz'
                ON CONFLICT (code) DO NOTHING;
                
                INSERT INTO courses (code, name, description, professor_id, credits)
                SELECT 'ALG301', 'Algorithms', 'Advanced algorithm design and analysis', id, 4
                FROM users WHERE email = 'prof@univ-alger.dz'
                ON CONFLICT (code) DO NOTHING;
            ");
            
            $pdo->exec("
                INSERT INTO course_groups (course_id, group_id)
                SELECT c.id, g.id FROM courses c, groups g 
                WHERE c.code = 'AWP101' AND g.name = 'Group A'
                ON CONFLICT DO NOTHING;
                
                INSERT INTO course_groups (course_id, group_id)
                SELECT c.id, g.id FROM courses c, groups g 
                WHERE c.code = 'AWP101' AND g.name = 'Group B'
                ON CONFLICT DO NOTHING;
                
                INSERT INTO course_groups (course_id, group_id)
                SELECT c.id, g.id FROM courses c, groups g 
                WHERE c.code = 'DB201' AND g.name = 'Group A'
                ON CONFLICT DO NOTHING;
            ");
            
            $pdo->exec("
                INSERT INTO student_groups (student_id, group_id)
                SELECT u.id, g.id FROM users u, groups g 
                WHERE u.email = 'student1@univ-alger.dz' AND g.name = 'Group A'
                ON CONFLICT DO NOTHING;
                
                INSERT INTO student_groups (student_id, group_id)
                SELECT u.id, g.id FROM users u, groups g 
                WHERE u.email = 'student2@univ-alger.dz' AND g.name = 'Group A'
                ON CONFLICT DO NOTHING;
                
                INSERT INTO student_groups (student_id, group_id)
                SELECT u.id, g.id FROM users u, groups g 
                WHERE u.email = 'student3@univ-alger.dz' AND g.name = 'Group B'
                ON CONFLICT DO NOTHING;
            ");
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Database initialization failed: " . $e->getMessage());
        return false;
    }
}
?>
