-- Kuyu General Hospital Bed Management System
-- Database Schema v1.0
-- Run via install.php

CREATE TABLE IF NOT EXISTS roles (
  role_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_name VARCHAR(50) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
  user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id INT UNSIGNED NOT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NULL UNIQUE,
  phone VARCHAR(20) NULL,
  profile_photo VARCHAR(255) NULL,
  status ENUM('ACTIVE','INACTIVE','LOCKED') NOT NULL DEFAULT 'ACTIVE',
  failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  last_login DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(role_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS departments (
  department_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  code VARCHAR(20) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS wards (
  ward_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  department_id INT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  ward_type VARCHAR(50) NOT NULL DEFAULT 'GENERAL',
  capacity SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ward (department_id, name),
  CONSTRAINT fk_wards_dept FOREIGN KEY (department_id) REFERENCES departments(department_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rooms (
  room_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ward_id INT UNSIGNED NOT NULL,
  room_number VARCHAR(20) NOT NULL,
  room_type ENUM('GENERAL','PRIVATE','SEMI_PRIVATE','ICU','ISOLATION') NOT NULL DEFAULT 'GENERAL',
  capacity TINYINT UNSIGNED NOT NULL DEFAULT 1,
  status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  UNIQUE KEY uq_room (ward_id, room_number),
  CONSTRAINT fk_rooms_ward FOREIGN KEY (ward_id) REFERENCES wards(ward_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS beds (
  bed_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id INT UNSIGNED NOT NULL,
  bed_number VARCHAR(20) NOT NULL,
  bed_type ENUM('STANDARD','ICU','PEDIATRIC','MATERNITY','ELECTRIC') NOT NULL DEFAULT 'STANDARD',
  current_status ENUM('AVAILABLE','OCCUPIED','RESERVED','CLEANING','MAINTENANCE','BLOCKED','OUT_OF_SERVICE') NOT NULL DEFAULT 'AVAILABLE',
  notes VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bed (room_id, bed_number),
  KEY idx_bed_status (current_status),
  CONSTRAINT fk_beds_room FOREIGN KEY (room_id) REFERENCES rooms(room_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS patients (
  patient_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  patient_number VARCHAR(20) NOT NULL UNIQUE,
  full_name VARCHAR(150) NOT NULL,
  gender ENUM('MALE','FEMALE','OTHER') NOT NULL,
  date_of_birth DATE NOT NULL,
  phone VARCHAR(20) NULL,
  address VARCHAR(255) NULL,
  emergency_contact_name VARCHAR(100) NULL,
  emergency_contact_phone VARCHAR(20) NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_patient_name (full_name),
  KEY idx_patient_phone (phone),
  CONSTRAINT fk_patients_user FOREIGN KEY (created_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admissions (
  admission_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  patient_id INT UNSIGNED NOT NULL,
  department_id INT UNSIGNED NOT NULL,
  admitted_by INT UNSIGNED NOT NULL,
  attending_doctor_id INT UNSIGNED NULL,
  admission_type ENUM('EMERGENCY','PLANNED','TRANSFER_IN','OBSERVATION') NOT NULL,
  admission_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reason TEXT NULL,
  status ENUM('ACTIVE','DISCHARGED','CANCELLED') NOT NULL DEFAULT 'ACTIVE',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_adm_patient (patient_id, status),
  KEY idx_adm_status (status, admission_date),
  CONSTRAINT fk_adm_patient FOREIGN KEY (patient_id) REFERENCES patients(patient_id),
  CONSTRAINT fk_adm_dept FOREIGN KEY (department_id) REFERENCES departments(department_id),
  CONSTRAINT fk_adm_by FOREIGN KEY (admitted_by) REFERENCES users(user_id),
  CONSTRAINT fk_adm_doc FOREIGN KEY (attending_doctor_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admission_nurse_assignments (
  admission_id INT UNSIGNED NOT NULL,
  nurse_user_id INT UNSIGNED NOT NULL,
  assigned_by INT UNSIGNED NOT NULL,
  assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (admission_id, nurse_user_id),
  KEY idx_nurse_assignments_user (nurse_user_id),
  CONSTRAINT fk_nurse_assignment_admission FOREIGN KEY (admission_id) REFERENCES admissions(admission_id),
  CONSTRAINT fk_nurse_assignment_nurse FOREIGN KEY (nurse_user_id) REFERENCES users(user_id),
  CONSTRAINT fk_nurse_assignment_assigned_by FOREIGN KEY (assigned_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bed_allocations (
  allocation_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admission_id INT UNSIGNED NOT NULL,
  bed_id INT UNSIGNED NOT NULL,
  allocated_by INT UNSIGNED NOT NULL,
  allocated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  released_at DATETIME NULL,
  release_reason VARCHAR(50) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  active_bed_key INT UNSIGNED AS (IF(is_active = 1, bed_id, NULL)) VIRTUAL,
  active_admission_key INT UNSIGNED AS (IF(is_active = 1, admission_id, NULL)) VIRTUAL,
  UNIQUE KEY uq_active_bed (active_bed_key),
  UNIQUE KEY uq_active_admission (active_admission_key),
  KEY idx_alloc_bed (bed_id, is_active),
  KEY idx_alloc_adm (admission_id, is_active),
  CONSTRAINT fk_alloc_adm FOREIGN KEY (admission_id) REFERENCES admissions(admission_id),
  CONSTRAINT fk_alloc_bed FOREIGN KEY (bed_id) REFERENCES beds(bed_id),
  CONSTRAINT fk_alloc_user FOREIGN KEY (allocated_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bed_transfers (
  transfer_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admission_id INT UNSIGNED NOT NULL,
  patient_id INT UNSIGNED NOT NULL,
  from_bed_id INT UNSIGNED NOT NULL,
  to_bed_id INT UNSIGNED NOT NULL,
  transferred_by INT UNSIGNED NOT NULL,
  transfer_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reason VARCHAR(255) NOT NULL,
  CONSTRAINT fk_tr_adm FOREIGN KEY (admission_id) REFERENCES admissions(admission_id),
  CONSTRAINT fk_tr_pat FOREIGN KEY (patient_id) REFERENCES patients(patient_id),
  CONSTRAINT fk_tr_from FOREIGN KEY (from_bed_id) REFERENCES beds(bed_id),
  CONSTRAINT fk_tr_to FOREIGN KEY (to_bed_id) REFERENCES beds(bed_id),
  CONSTRAINT fk_tr_user FOREIGN KEY (transferred_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bed_reservations (
  reservation_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  patient_id INT UNSIGNED NOT NULL,
  bed_id INT UNSIGNED NOT NULL,
  reserved_by INT UNSIGNED NOT NULL,
  reserved_from DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reserved_until DATETIME NOT NULL,
  status ENUM('ACTIVE','FULFILLED','CANCELLED','EXPIRED') NOT NULL DEFAULT 'ACTIVE',
  notes VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_res_bed (bed_id, status, reserved_until),
  CONSTRAINT fk_res_pat FOREIGN KEY (patient_id) REFERENCES patients(patient_id),
  CONSTRAINT fk_res_bed FOREIGN KEY (bed_id) REFERENCES beds(bed_id),
  CONSTRAINT fk_res_user FOREIGN KEY (reserved_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS discharges (
  discharge_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admission_id INT UNSIGNED NOT NULL UNIQUE,
  patient_id INT UNSIGNED NOT NULL,
  discharged_by INT UNSIGNED NOT NULL,
  discharge_type ENUM('RECOVERED','REFERRED','LEFT_AGAINST_ADVICE','TRANSFERRED_OUT','DECEASED','OTHER') NOT NULL,
  discharge_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  summary TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_dis_adm FOREIGN KEY (admission_id) REFERENCES admissions(admission_id),
  CONSTRAINT fk_dis_pat FOREIGN KEY (patient_id) REFERENCES patients(patient_id),
  CONSTRAINT fk_dis_user FOREIGN KEY (discharged_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bed_status_history (
  history_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bed_id INT UNSIGNED NOT NULL,
  old_status VARCHAR(20) NULL,
  new_status VARCHAR(20) NOT NULL,
  changed_by INT UNSIGNED NOT NULL,
  changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reason VARCHAR(255) NULL,
  KEY idx_hist_bed (bed_id, changed_at),
  CONSTRAINT fk_hist_bed FOREIGN KEY (bed_id) REFERENCES beds(bed_id),
  CONSTRAINT fk_hist_user FOREIGN KEY (changed_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
  notification_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  title VARCHAR(100) NOT NULL,
  message VARCHAR(255) NOT NULL,
  type VARCHAR(30) NOT NULL DEFAULT 'INFO',
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_notif_user (user_id, is_read),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
  audit_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(50) NOT NULL,
  table_name VARCHAR(50) NULL,
  record_id INT UNSIGNED NULL,
  details TEXT NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_user (user_id, created_at),
  KEY idx_audit_action (action, created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Seed roles
INSERT IGNORE INTO roles (role_name, description) VALUES
  ('ADMINISTRATOR', 'System administrator with full access'),
  ('RECEPTION',     'Reception and admission staff'),
  ('NURSE',         'Ward nurse'),
  ('DOCTOR',        'Attending doctor'),
  ('MANAGER',       'Hospital manager (read-only reports)');

-- Default admin user (password updated by installer to 'Admin@1234')
INSERT IGNORE INTO users (role_id, username, password_hash, full_name, email, status)
  SELECT role_id, 'admin', 'PLACEHOLDER', 'System Administrator', 'admin@kuyuhospital.et', 'ACTIVE'
  FROM roles WHERE role_name = 'ADMINISTRATOR' LIMIT 1;

-- Sample departments
INSERT IGNORE INTO departments (name, code, description) VALUES
  ('Medicine',                 'MED',  'General medical ward'),
  ('Surgery',                  'SURG', 'Surgical services'),
  ('Pediatrics',               'PED',  'Children ward'),
  ('Gynecology & Obstetrics',  'GYN',  'Maternal and child health'),
  ('Emergency',                'EMRG', 'Emergency department'),
  ('Intensive Care Unit',      'ICU',  'Critical care');

-- Sample wards
INSERT IGNORE INTO wards (department_id, name, ward_type, capacity)
SELECT d.department_id, 'Medical Ward A', 'GENERAL', 20 FROM departments d WHERE d.code='MED' LIMIT 1;
INSERT IGNORE INTO wards (department_id, name, ward_type, capacity)
SELECT d.department_id, 'Medical Ward B', 'GENERAL', 20 FROM departments d WHERE d.code='MED' LIMIT 1;
INSERT IGNORE INTO wards (department_id, name, ward_type, capacity)
SELECT d.department_id, 'Surgical Ward', 'GENERAL', 24 FROM departments d WHERE d.code='SURG' LIMIT 1;
INSERT IGNORE INTO wards (department_id, name, ward_type, capacity)
SELECT d.department_id, 'Pediatric Ward', 'PEDIATRIC', 16 FROM departments d WHERE d.code='PED' LIMIT 1;
INSERT IGNORE INTO wards (department_id, name, ward_type, capacity)
SELECT d.department_id, 'Maternity Ward', 'MATERNITY', 20 FROM departments d WHERE d.code='GYN' LIMIT 1;
INSERT IGNORE INTO wards (department_id, name, ward_type, capacity)
SELECT d.department_id, 'Emergency Ward', 'GENERAL', 10 FROM departments d WHERE d.code='EMRG' LIMIT 1;
INSERT IGNORE INTO wards (department_id, name, ward_type, capacity)
SELECT d.department_id, 'ICU', 'ICU', 8 FROM departments d WHERE d.code='ICU' LIMIT 1;

-- Sample rooms for Medical Ward A
INSERT IGNORE INTO rooms (ward_id, room_number, room_type, capacity)
SELECT w.ward_id, 'MA-101', 'GENERAL', 4 FROM wards w WHERE w.name='Medical Ward A' LIMIT 1;
INSERT IGNORE INTO rooms (ward_id, room_number, room_type, capacity)
SELECT w.ward_id, 'MA-102', 'GENERAL', 4 FROM wards w WHERE w.name='Medical Ward A' LIMIT 1;
INSERT IGNORE INTO rooms (ward_id, room_number, room_type, capacity)
SELECT w.ward_id, 'MA-103', 'PRIVATE', 1 FROM wards w WHERE w.name='Medical Ward A' LIMIT 1;

-- Sample beds for MA-101
INSERT IGNORE INTO beds (room_id, bed_number, bed_type)
SELECT r.room_id, 'MA-101-A', 'STANDARD' FROM rooms r WHERE r.room_number='MA-101' LIMIT 1;
INSERT IGNORE INTO beds (room_id, bed_number, bed_type)
SELECT r.room_id, 'MA-101-B', 'STANDARD' FROM rooms r WHERE r.room_number='MA-101' LIMIT 1;
INSERT IGNORE INTO beds (room_id, bed_number, bed_type)
SELECT r.room_id, 'MA-101-C', 'STANDARD' FROM rooms r WHERE r.room_number='MA-101' LIMIT 1;
INSERT IGNORE INTO beds (room_id, bed_number, bed_type)
SELECT r.room_id, 'MA-101-D', 'ELECTRIC' FROM rooms r WHERE r.room_number='MA-101' LIMIT 1;

-- Sample beds for MA-102
INSERT IGNORE INTO beds (room_id, bed_number, bed_type)
SELECT r.room_id, 'MA-102-A', 'STANDARD' FROM rooms r WHERE r.room_number='MA-102' LIMIT 1;
INSERT IGNORE INTO beds (room_id, bed_number, bed_type)
SELECT r.room_id, 'MA-102-B', 'STANDARD' FROM rooms r WHERE r.room_number='MA-102' LIMIT 1;

-- Insert history for seed beds
INSERT IGNORE INTO bed_status_history (bed_id, old_status, new_status, changed_by, reason)
SELECT b.bed_id, NULL, 'AVAILABLE', 1, 'Initial registration'
FROM beds b
WHERE NOT EXISTS (SELECT 1 FROM bed_status_history h WHERE h.bed_id = b.bed_id)
