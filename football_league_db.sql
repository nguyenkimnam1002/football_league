-- Database: football_league

-- Bảng cầu thủ
CREATE TABLE players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    main_position ENUM('Thủ môn', 'Trung vệ', 'Hậu vệ cánh', 'Tiền vệ', 'Tiền đạo') NOT NULL,
    secondary_position ENUM('Thủ môn', 'Trung vệ', 'Hậu vệ cánh', 'Tiền vệ', 'Tiền đạo'),
    main_skill ENUM('Tốt', 'Trung bình', 'Yếu') NOT NULL,
    secondary_skill ENUM('Tốt', 'Trung bình', 'Yếu'),
    total_points INT DEFAULT 0,
    total_matches INT DEFAULT 0,
    total_wins INT DEFAULT 0,
    total_goals INT DEFAULT 0,
    total_assists INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;;

-- Bảng trận đấu hàng ngày
CREATE TABLE daily_matches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_date DATE NOT NULL UNIQUE,
    team_a_formation JSON NOT NULL, -- Lưu đội hình A
    team_b_formation JSON NOT NULL, -- Lưu đội hình B
    team_a_score INT DEFAULT NULL,
    team_b_score INT DEFAULT NULL,
    status ENUM('scheduled', 'locked', 'completed') DEFAULT 'scheduled',
    locked_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;;

-- Bảng tham gia trận đấu
CREATE TABLE match_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NOT NULL,
    player_id INT NOT NULL,
    team ENUM('A', 'B') NOT NULL,
    assigned_position ENUM('Thủ môn', 'Trung vệ', 'Hậu vệ cánh', 'Tiền vệ', 'Tiền đạo') NOT NULL,
    position_type ENUM('Sở trường', 'Sở đoản', 'Không quen') NOT NULL,
    skill_level ENUM('Tốt', 'Trung bình', 'Yếu') NOT NULL,
    goals INT DEFAULT 0,
    assists INT DEFAULT 0,
    points_earned INT DEFAULT 0, -- 3 điểm nếu thắng, 0 nếu thua
    FOREIGN KEY (match_id) REFERENCES daily_matches(id) ON DELETE CASCADE,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    UNIQUE KEY unique_player_match (match_id, player_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;;

-- Bảng đăng ký tham gia hàng ngày
CREATE TABLE daily_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    registration_date DATE NOT NULL,
    player_id INT NOT NULL,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    UNIQUE KEY unique_player_date (registration_date, player_id),
    INDEX idx_date (registration_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;;

-- Bảng thống kê tổng hợp
CREATE TABLE player_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    player_id INT NOT NULL,
    month DATE NOT NULL, -- lưu định dạng đầy đủ YYYY-MM-DD
    matches_played INT DEFAULT 0,
    wins INT DEFAULT 0,
    goals INT DEFAULT 0,
    assists INT DEFAULT 0,
    points INT DEFAULT 0,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    UNIQUE KEY unique_player_month (player_id, month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;;

-- Bảng cấu hình hệ thống
CREATE TABLE system_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(50) NOT NULL UNIQUE,
    config_value TEXT NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;;

-- Chèn dữ liệu mẫu cầu thủ
INSERT INTO players (name, main_position, secondary_position, main_skill, secondary_skill) VALUES
('Nguyễn Văn Nam', 'Hậu vệ cánh', 'Tiền vệ', 'Tốt', 'Trung bình'),
('Nguyễn Văn Tuấn', 'Trung vệ', 'Hậu vệ cánh', 'Tốt', 'Trung bình'),
('Đào Văn Đăng', 'Tiền đạo', 'Hậu vệ cánh', 'Trung bình', 'Yếu'),
('Đàm Minh Thư', 'Hậu vệ cánh', 'Tiền đạo', 'Trung bình', 'Trung bình'),
('Hà Văn Nam', 'Trung vệ', 'Hậu vệ cánh', 'Tốt', 'Trung bình'),
('Đỗ Minh Hoàng', 'Hậu vệ cánh', 'Thủ môn', 'Trung bình', 'Yếu'),
('Nguyễn Anh Việt', 'Tiền đạo', 'Hậu vệ cánh', 'Tốt', 'Yếu'),
('Lê Hoàng Minh', 'Trung vệ', 'Hậu vệ cánh', 'Trung bình', 'Trung bình'),
('Nguyễn Xuân Trường', 'Hậu vệ cánh', 'Tiền đạo', 'Trung bình', 'Trung bình'),
('Chu Văn Trường', 'Tiền vệ', 'Hậu vệ cánh', 'Tốt', 'Trung bình'),
('Trần Anh', 'Hậu vệ cánh', 'Tiền đạo', 'Trung bình', 'Trung bình'),
('Ngô Quyền', 'Thủ môn', 'Tiền vệ', 'Trung bình', 'Trung bình'),
('Trần Quyền', 'Tiền vệ', 'Trung vệ', 'Tốt', 'Trung bình'),
('Văn Đăng Tuấn', 'Tiền vệ', 'Hậu vệ cánh', 'Tốt', 'Trung bình'),
('Nguyễn Văn Linh', 'Tiền vệ', 'Hậu vệ cánh', 'Tốt', 'Trung bình'),
('Trần Minh Tú', 'Hậu vệ cánh', 'Thủ môn', 'Trung bình', 'Yếu'),
('Đỗ Văn Tính (C)', 'Tiền đạo', 'Tiền vệ', 'Tốt', 'Trung bình'),
('Hoàng Văn Hồng', 'Hậu vệ cánh', 'Tiền đạo', 'Trung bình', 'Trung bình'),
('Hoàng Văn Chung', 'Trung vệ', 'Hậu vệ cánh', 'Tốt', 'Trung bình'),
('Nguyễn Quang Tuấn', 'Thủ môn', 'Hậu vệ cánh', 'Tốt', 'Trung bình'),
('Nguyễn Văn Quân', 'Tiền đạo', 'Thủ môn', 'Tốt', 'Trung bình'),
('Trần Văn Khoa', 'Tiền vệ', 'Hậu vệ cánh', 'Tốt', 'Trung bình'),
('Nguyễn Kim Nam', 'Tiền vệ', 'Tiền đạo', 'Tốt', 'Trung bình'),
('Phạm Đình Đạt', 'Hậu vệ cánh', 'Tiền đạo', 'Trung bình', 'Trung bình'),
('Nguyễn Quang Linh Su', 'Hậu vệ cánh', 'Tiền đạo', 'Tốt', 'Trung bình'),
('Nguyễn Văn Trọng', 'Hậu vệ cánh', 'Thủ môn', 'Trung bình', 'Trung bình'),
('Đỗ Văn Hà', 'Hậu vệ cánh', 'Tiền đạo', 'Trung bình', 'Yếu'),
('Nguyễn Chí Đạt Lốp', 'Tiền vệ', 'Trung vệ', 'Tốt', 'Trung bình'),
('Trần Hải Nam', 'Tiền đạo', 'Thủ môn', 'Trung bình', 'Trung bình'),
('Đỗ Việt Anh', 'Hậu vệ cánh', 'Tiền vệ', 'Tốt', 'Trung bình'),
('Trần Văn Tuấn', 'Tiền đạo', 'Tiền vệ', 'Tốt', 'Tốt'),
('Lê Hùng Quảng Cáo', 'Thủ môn', 'Tiền đạo', 'Tốt', 'Trung bình');

-- Cấu hình hệ thống
INSERT INTO system_config (config_key, config_value, description) VALUES
('lock_time', '22:30:00', 'Thời gian khóa đăng ký hàng ngày'),
('match_start_time', '07:00:00', 'Thời gian bắt đầu trận đấu'),
('min_players', '14', 'Số cầu thủ tối thiểu để chia đội'),
('points_win', '3', 'Điểm thưởng khi thắng'),
('points_lose', '0', 'Điểm khi thua');

-- Thêm cột total_draws vào bảng players
ALTER TABLE players ADD COLUMN total_draws INT DEFAULT 0 AFTER total_wins;

-- Cập nhật dữ liệu existing (tính toán số trận hòa)
-- total_draws = total_matches - total_wins - total_losses
-- Giả sử total_losses = total_matches - total_wins - total_draws
-- Tạm thời set total_draws = 0 cho dữ liệu hiện tại
UPDATE players SET total_draws = 0 WHERE total_draws IS NULL;

-- Thêm trường is_special_player vào bảng players
ALTER TABLE players 
ADD COLUMN is_special_player TINYINT(1) DEFAULT 0 AFTER secondary_skill;

-- Thêm comment để rõ ràng
ALTER TABLE players 
MODIFY COLUMN is_special_player TINYINT(1) DEFAULT 0 COMMENT '1 = Cầu thủ đặc biệt (x1.5 điểm), 0 = Cầu thủ thường';

-- Cập nhật một số cầu thủ mẫu thành cầu thủ đặc biệt (tùy chọn)
-- UPDATE players SET is_special_player = 1 WHERE name IN ('Đỗ Văn Tính (C)', 'Nguyễn Quang Tuấn', 'Trần Văn Tuấn');

-- Kiểm tra kết quả
-- SELECT id, name, main_position, main_skill, is_special_player 
-- FROM players 
-- WHERE is_special_player = 1;

-- Fix database để hỗ trợ điểm thập phân cho cầu thủ đặc biệt

-- Thay đổi kiểu dữ liệu trong bảng players
ALTER TABLE players 
MODIFY COLUMN total_points DECIMAL(8,2) DEFAULT 0.00;

-- Thay đổi kiểu dữ liệu trong bảng match_participants  
ALTER TABLE match_participants 
MODIFY COLUMN points_earned DECIMAL(5,2) DEFAULT 0.00;

-- Thay đổi kiểu dữ liệu trong bảng player_stats
ALTER TABLE player_stats 
MODIFY COLUMN points DECIMAL(8,2) DEFAULT 0.00;

-- Kiểm tra lại cấu trúc bảng
DESCRIBE players;
DESCRIBE match_participants;
DESCRIBE player_stats;