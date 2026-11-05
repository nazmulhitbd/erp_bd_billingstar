-- Audit trail: logins, data changes, activity and attacks, for every kind of user. Safe to run again.
-- Written by addon/inc/audit.php. The pages only read these tables, apart from Audit Settings and the blocked list.
CREATE TABLE IF NOT EXISTS audit_log (
  audit_id BIGINT AUTO_INCREMENT PRIMARY KEY,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  category VARCHAR(12) NOT NULL,
  action VARCHAR(40) NOT NULL,
  severity VARCHAR(8) NOT NULL DEFAULT 'info',
  status VARCHAR(8) NOT NULL DEFAULT 'ok',
  actor_type VARCHAR(12) NOT NULL DEFAULT 'anonymous',
  actor_id INT NOT NULL DEFAULT 0,
  actor_name VARCHAR(120) NOT NULL DEFAULT '',
  branch_id INT NOT NULL DEFAULT 0,
  ip VARCHAR(45) NOT NULL DEFAULT '',
  country VARCHAR(3) NOT NULL DEFAULT '',
  browser VARCHAR(30) NOT NULL DEFAULT '',
  os VARCHAR(30) NOT NULL DEFAULT '',
  device VARCHAR(10) NOT NULL DEFAULT '',
  user_agent VARCHAR(255) NOT NULL DEFAULT '',
  method VARCHAR(8) NOT NULL DEFAULT '',
  page VARCHAR(190) NOT NULL DEFAULT '',
  target_table VARCHAR(64) NOT NULL DEFAULT '',
  target_id VARCHAR(40) NOT NULL DEFAULT '',
  summary VARCHAR(255) NOT NULL DEFAULT '',
  detail MEDIUMTEXT NULL,
  session_ref CHAR(12) NOT NULL DEFAULT '',
  request_id CHAR(16) NOT NULL DEFAULT '',
  KEY idx_created (created_at),
  KEY idx_category (category, created_at),
  KEY idx_severity (severity, created_at),
  KEY idx_ip (ip, created_at),
  KEY idx_actor (actor_type, actor_id, created_at),
  KEY idx_target (target_table, target_id),
  KEY idx_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Country of each address, kept so an address is looked up only once.
CREATE TABLE IF NOT EXISTS audit_ip_country (
  ip VARCHAR(45) PRIMARY KEY,
  country VARCHAR(3) NOT NULL DEFAULT '',
  checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Addresses refused before any page runs. An empty blocked_until means until someone unblocks it.
CREATE TABLE IF NOT EXISTS audit_blocked_ip (
  ip VARCHAR(45) PRIMARY KEY,
  reason VARCHAR(190) NOT NULL DEFAULT '',
  blocked_until DATETIME NULL,
  created_by VARCHAR(120) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_until (blocked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_settings (
  setting_key VARCHAR(40) PRIMARY KEY,
  setting_value VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Counters behind the sign-in limits and automatic blocking.
CREATE TABLE IF NOT EXISTS security_throttle (
  bucket CHAR(64) PRIMARY KEY,
  hits INT NOT NULL DEFAULT 0,
  window_end DATETIME NOT NULL
) ENGINE=InnoDB;
