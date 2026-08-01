INSERT INTO admin_users (name, email, password_hash, role, must_change_password, active, created_at, password_changed_at, last_login_at)
VALUES ('Newpath Super Admin', 'admin@newpathchaplaincy.com', '$2y$12$QDGG6faQsWpyZF7O1nQnSO6L6MqbYY9QlCheYqmY9Nyt1Zm/44z3K', 'Super Admin', 0, 1, NOW(), NOW(), NULL)
ON DUPLICATE KEY UPDATE email = email;

INSERT INTO admin_password_history (admin_user_id, password_hash, created_at)
SELECT id, password_hash, NOW() FROM admin_users WHERE email = 'admin@newpathchaplaincy.com'
  AND NOT EXISTS (SELECT 1 FROM admin_password_history WHERE admin_user_id = admin_users.id);

INSERT INTO training_modules (id, sort_order, step_number, title, description, updated_at) VALUES
(1, 1, 1, 'Foundations of Chaplaincy', 'Calling, ethics, confidentiality, presence, listening, and pastoral identity.', NOW()),
(2, 2, 2, 'Crisis & Trauma Care', 'Emergency response, grief support, psychological first aid, and referral protocols.', NOW()),
(3, 3, 3, 'Specialized Chaplaincy', 'Healthcare, military/veterans, corrections, community, workplace, and first-responder care.', NOW()),
(4, 4, 4, 'Digital Chaplaincy', 'Online care, virtual ministry, secure records, and ethical technology use.', NOW())
ON DUPLICATE KEY UPDATE title = VALUES(title), description = VALUES(description), updated_at = NOW();

INSERT INTO site_settings (setting_key, setting_value, updated_at) VALUES
('contact_email', 'info@newpathchaplaincy.com', NOW()),
('contact_phone', '(000) 000-0000', NOW())
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW();

INSERT INTO audit_log (admin_email, action, details, ip_address, created_at)
SELECT 'system', 'DATABASE_SEEDED', 'Initial Newpath admin, training modules, and settings created.', 'local', NOW()
WHERE NOT EXISTS (SELECT 1 FROM audit_log WHERE action = 'DATABASE_SEEDED');
