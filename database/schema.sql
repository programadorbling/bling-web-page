-- Bling Network — Membership Schema
-- Run tables in this order to satisfy foreign key constraints.
-- Engine: InnoDB, Charset: utf8mb4_unicode_ci throughout.

-- ─────────────────────────────────────────────────────────────
-- 1. Parent / header record
-- ─────────────────────────────────────────────────────────────
CREATE TABLE membership_requests (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  privacy_accepted TINYINT(1)   NOT NULL DEFAULT 0,

  status          ENUM('pending','reviewing','approved','rejected')
                                NOT NULL DEFAULT 'pending',
  ip_address      VARCHAR(45)   NOT NULL,
  user_agent      VARCHAR(500)  DEFAULT NULL,
  recaptcha_score DECIMAL(3,2)  DEFAULT NULL,
  submitted_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_status       (status),
  INDEX idx_submitted_at (submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 2. Company information  (1:1)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE membership_company (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id           INT UNSIGNED NOT NULL,

  company_name         VARCHAR(255)  NOT NULL,
  country              VARCHAR(100)  NOT NULL,
  city                 VARCHAR(100)  NOT NULL,

  address              VARCHAR(500)  DEFAULT NULL,
  company_phone        VARCHAR(50)   DEFAULT NULL,
  zip_code             VARCHAR(20)   DEFAULT NULL,
  website              VARCHAR(255)  DEFAULT NULL,
  number_of_employees  VARCHAR(20)   DEFAULT NULL,
  established_date     DATE          DEFAULT NULL,
  other_networks       TEXT          DEFAULT NULL,

  CONSTRAINT fk_company_request
    FOREIGN KEY (request_id) REFERENCES membership_requests(id)
    ON DELETE CASCADE,
  UNIQUE KEY uq_company_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 3. Services offered + IATA + principal market  (1:1)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE membership_services (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id            INT UNSIGNED NOT NULL,

  svc_air_freight       TINYINT(1) NOT NULL DEFAULT 0,
  svc_ocean_freight     TINYINT(1) NOT NULL DEFAULT 0,
  svc_road_freight      TINYINT(1) NOT NULL DEFAULT 0,
  svc_rail_freight      TINYINT(1) NOT NULL DEFAULT 0,
  svc_customs_clearance TINYINT(1) NOT NULL DEFAULT 0,
  svc_warehousing       TINYINT(1) NOT NULL DEFAULT 0,
  svc_project_cargo     TINYINT(1) NOT NULL DEFAULT 0,
  svc_multimodal        TINYINT(1) NOT NULL DEFAULT 0,
  svc_courier_express   TINYINT(1) NOT NULL DEFAULT 0,
  svc_dangerous_goods   TINYINT(1) NOT NULL DEFAULT 0,

  iata_member           TINYINT(1)   NOT NULL DEFAULT 0,
  principal_market      VARCHAR(100) DEFAULT NULL,

  CONSTRAINT fk_services_request
    FOREIGN KEY (request_id) REFERENCES membership_requests(id)
    ON DELETE CASCADE,
  UNIQUE KEY uq_services_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 4. Key contacts  (1:2 — contact_order 1 required, 2 optional)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE membership_contacts (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id      INT UNSIGNED NOT NULL,
  contact_order   TINYINT UNSIGNED NOT NULL,

  prefix          ENUM('Mr','Ms','Mrs') DEFAULT NULL,
  full_name       VARCHAR(200)  NOT NULL,
  role            VARCHAR(100)  DEFAULT NULL,
  email           VARCHAR(255)  NOT NULL,
  phone           VARCHAR(50)   NOT NULL,

  CONSTRAINT fk_contacts_request
    FOREIGN KEY (request_id) REFERENCES membership_requests(id)
    ON DELETE CASCADE,
  UNIQUE KEY uq_contact_order (request_id, contact_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 5. Ownership / principal  (1:1)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE membership_ownership (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id      INT UNSIGNED NOT NULL,

  full_name       VARCHAR(200) NOT NULL,
  role            VARCHAR(100) DEFAULT NULL,
  mobile_phone    VARCHAR(50)  NOT NULL,

  CONSTRAINT fk_ownership_request
    FOREIGN KEY (request_id) REFERENCES membership_requests(id)
    ON DELETE CASCADE,
  UNIQUE KEY uq_ownership_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 6. Reference contacts  (1:2 — both optional)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE membership_references (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id       INT UNSIGNED NOT NULL,
  reference_order  TINYINT UNSIGNED NOT NULL,

  company_name     VARCHAR(255) NOT NULL,
  contact_name     VARCHAR(200) NOT NULL,
  contact_role     VARCHAR(100) DEFAULT NULL,
  phone            VARCHAR(50)  NOT NULL,
  email            VARCHAR(255) NOT NULL,

  CONSTRAINT fk_references_request
    FOREIGN KEY (request_id) REFERENCES membership_requests(id)
    ON DELETE CASCADE,
  UNIQUE KEY uq_reference_order (request_id, reference_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- 7. Rate-limit log  (no FK — standalone audit table)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE rate_limit_log (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip_address   VARCHAR(45)  NOT NULL,
  endpoint     VARCHAR(100) NOT NULL DEFAULT 'membership',
  attempted_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  INDEX idx_ip_endpoint  (ip_address, endpoint),
  INDEX idx_attempted_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
