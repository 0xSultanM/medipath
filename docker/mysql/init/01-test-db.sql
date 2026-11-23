CREATE DATABASE IF NOT EXISTS medipath_test
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE DATABASE IF NOT EXISTS medipath_dusk
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON medipath_test.* TO 'medipath'@'%';
GRANT ALL PRIVILEGES ON medipath_dusk.* TO 'medipath'@'%';
FLUSH PRIVILEGES;
