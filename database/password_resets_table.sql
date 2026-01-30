-- Create password_resets table for storing reset codes
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    is_used BOOLEAN DEFAULT 0,
    used_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id_used (user_id, is_used),
    INDEX idx_expires_at (expires_at)
);

-- If you want to clean up old expired codes, you can run this query periodically:
-- DELETE FROM password_resets WHERE is_used = 0 AND expires_at < NOW();
