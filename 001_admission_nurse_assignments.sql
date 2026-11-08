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
