CREATE TABLE IF NOT EXISTS citizens (
    citizen_id INT PRIMARY KEY AUTO_INCREMENT,
    last_name VARCHAR(100) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100),
    birth_date DATE NOT NULL,
    sex ENUM('М','Ж') NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(255),
    reg_address VARCHAR(255) NOT NULL,
    reg_status ENUM('активна','прекращена') NOT NULL,
    reg_start_date DATE NOT NULL,
    reg_end_date DATE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS passports (
    passport_id INT PRIMARY KEY AUTO_INCREMENT,
    citizen_id INT NOT NULL,
    series CHAR(4) NOT NULL,
    number CHAR(6) NOT NULL,
    issue_date DATE NOT NULL,
    expiry_date DATE,
    issuer VARCHAR(255) NOT NULL,
    status ENUM('черновик','действует','заменён','аннулирован') NOT NULL,
    reason VARCHAR(255),
    UNIQUE KEY uq_series_number (series, number),
    CONSTRAINT fk_passports_citizen FOREIGN KEY (citizen_id) REFERENCES citizens(citizen_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    login VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('администратор','оператор','пользователь') NOT NULL,
    mfa_secret VARCHAR(255) NOT NULL,
    last_password_change DATE NOT NULL,
    failed_attempts TINYINT NOT NULL DEFAULT 0,
    is_blocked TINYINT(1) NOT NULL DEFAULT 0,
    locked_until DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS applications (
    application_id INT PRIMARY KEY AUTO_INCREMENT,
    citizen_id INT NOT NULL,
    operator_id INT NOT NULL,
    service_type ENUM('Регистрация гражданина','Выдача паспорта','Замена паспорта','Снятие с регистрационного учёта','Справка о регистрации','Отчёт') NOT NULL,
    status ENUM('подано','в работе','завершено','отклонено') NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME,
    CONSTRAINT fk_applications_citizen FOREIGN KEY (citizen_id) REFERENCES citizens(citizen_id) ON DELETE CASCADE,
    CONSTRAINT fk_applications_operator FOREIGN KEY (operator_id) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS action_log (
    log_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    action_time DATETIME NOT NULL,
    user_id INT NOT NULL,
    entity_name ENUM('citizens','passports','applications','users','action_log') NOT NULL,
    entity_id INT NOT NULL,
    action_type ENUM('create','read','update','delete','login','logout','export','import','restore') NOT NULL,
    before_json JSON,
    after_json JSON,
    CONSTRAINT fk_action_log_user FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;