<?php
declare(strict_types=1);

require_once __DIR__ . '/translit.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $cfg = (require __DIR__ . '/config.php')['db'];

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $cfg['host'], $cfg['name'], $cfg['charset']
    );

    $pdo = new PDO($dsn, $cfg['user'], $cfg['password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+00:00'",
    ]);

    db_migrate($pdo);
    return $pdo;
}

function db_migrate(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS source (
            id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
            url          VARCHAR(500) NOT NULL,
            kind         VARCHAR(32)  NOT NULL,
            fetched_at   INT UNSIGNED NOT NULL,
            http_status  INT          NULL,
            bytes        INT UNSIGNED NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_source_url (url)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS teacher (
            id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name       VARCHAR(255) NOT NULL,
            searchable VARCHAR(800) NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_teacher_name (name),
            KEY idx_teacher_searchable (searchable(200))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS subject (
            id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code       VARCHAR(64)  NULL,
            name       VARCHAR(500) NOT NULL,
            searchable VARCHAR(1500) NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_subject_code_name (code, name),
            KEY idx_subject_searchable (searchable(200))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    add_column_if_missing($pdo, 'teacher',            'searchable',         'VARCHAR(800) NULL');
    add_column_if_missing($pdo, 'subject',            'searchable',         'VARCHAR(1500) NULL');
    add_column_if_missing($pdo, 'additional_lecture', 'teacher_searchable', 'VARCHAR(800) NULL');
    add_column_if_missing($pdo, 'additional_lecture', 'subject_searchable', 'VARCHAR(1500) NULL');
    add_index_if_missing($pdo,  'teacher',            'idx_teacher_searchable',  'searchable(200)');
    add_index_if_missing($pdo,  'subject',            'idx_subject_searchable',  'searchable(200)');
    add_index_if_missing($pdo,  'additional_lecture', 'idx_addlect_t_search',    'teacher_searchable(200)');
    add_index_if_missing($pdo,  'additional_lecture', 'idx_addlect_s_search',    'subject_searchable(200)');

    // Provenance columns: which homepage section a source came from, and a
    // short label we can display in search results / detail views.
    add_column_if_missing($pdo, 'source', 'section_title', 'VARCHAR(255) NULL');
    add_column_if_missing($pdo, 'source', 'display_label', 'VARCHAR(255) NULL');
    add_column_if_missing($pdo, 'source', 'anchor_text',   'VARCHAR(255) NULL');

    // pdf_doc: switch from UNIQUE(faculty_slug) to UNIQUE(kind, faculty_slug) so
    // the same faculty (e.g. ims) can have BOTH an "additional" and a "midterm"
    // PDF row without colliding. Use the new kind names directly so legacy rows
    // (default 'additional') get rewritten to 'additional_pdf' before the new
    // unique key is enforced.
    if (!column_exists($pdo, 'pdf_doc', 'kind')) {
        $pdo->exec("ALTER TABLE pdf_doc ADD COLUMN kind VARCHAR(32) NOT NULL DEFAULT 'additional_pdf'");
    }
    $pdo->exec("UPDATE pdf_doc SET kind = 'additional_pdf' WHERE kind IN ('additional', '')");
    drop_index_if_present($pdo,  'pdf_doc', 'uq_pdf_faculty');
    add_unique_if_missing($pdo,  'pdf_doc', 'uq_pdf_kind_faculty', 'kind, faculty_slug');

    // lecture: include source_id in the unique key so the same lecture appearing
    // in multiple teachers HTMLs doesn't collide — each gets its own row. Only
    // touch the index if it doesn't already have source_id as the first column;
    // otherwise the FK on source_id would block the drop.
    if (!unique_has_columns($pdo, 'lecture', 'uq_lecture',
        ['source_id', 'teacher_id', 'weekday', 'start_slot', 'group_code', 'subject_id', 'room'])
    ) {
        drop_index_if_present($pdo, 'lecture', 'uq_lecture');
        add_unique_if_missing($pdo, 'lecture', 'uq_lecture',
            'source_id, teacher_id, weekday, start_slot, group_code, subject_id, room');
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pdf_doc (
            id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
            faculty_slug  VARCHAR(64)  NOT NULL,
            faculty_name  VARCHAR(255) NOT NULL,
            source_id     INT UNSIGNED NOT NULL,
            page_count    INT UNSIGNED NULL,
            raw_text      LONGTEXT     NOT NULL,
            fetched_at    INT UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_pdf_faculty (faculty_slug),
            KEY idx_pdf_source (source_id),
            CONSTRAINT fk_pdf_source FOREIGN KEY (source_id) REFERENCES source(id),
            FULLTEXT KEY ft_pdf_text (raw_text)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS additional_lecture (
            id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
            pdf_doc_id         INT UNSIGNED NOT NULL,
            faculty_slug       VARCHAR(64)  NOT NULL,
            page_num           SMALLINT UNSIGNED NULL,
            row_num            SMALLINT UNSIGNED NULL,
            teacher_name       VARCHAR(255) NULL,
            teacher_searchable VARCHAR(800) NULL,
            subject_name       VARCHAR(500) NULL,
            subject_searchable VARCHAR(1500) NULL,
            lesson_type        VARCHAR(128) NULL,
            weekday            TINYINT UNSIGNED NULL,
            day_label          VARCHAR(64)  NULL,
            times_csv          VARCHAR(255) NULL,
            rooms_csv          VARCHAR(255) NULL,
            raw_row            TEXT         NULL,
            parse_quality      TINYINT UNSIGNED NOT NULL,
            last_seen_at       INT UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            KEY idx_addlect_faculty (faculty_slug),
            KEY idx_addlect_pdf     (pdf_doc_id),
            KEY idx_addlect_teacher (teacher_name),
            KEY idx_addlect_weekday (weekday),
            KEY idx_addlect_t_search (teacher_searchable(200)),
            KEY idx_addlect_s_search (subject_searchable(200)),
            CONSTRAINT fk_addlect_pdf FOREIGN KEY (pdf_doc_id) REFERENCES pdf_doc(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS lecture (
            id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_id      INT UNSIGNED NOT NULL,
            teacher_id     INT UNSIGNED NOT NULL,
            subject_id     INT UNSIGNED NULL,
            group_code     VARCHAR(128) NULL,
            lesson_type    VARCHAR(128) NULL,
            room           VARCHAR(128) NULL,
            weekday        TINYINT UNSIGNED NOT NULL,
            start_slot     TINYINT UNSIGNED NOT NULL,
            end_slot       TINYINT UNSIGNED NOT NULL,
            start_time     CHAR(5)      NOT NULL,
            end_time       CHAR(5)      NOT NULL,
            raw_cell       TEXT         NULL,
            first_seen_at  INT UNSIGNED NOT NULL,
            last_seen_at   INT UNSIGNED NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_lecture (teacher_id, weekday, start_slot, group_code, subject_id, room),
            KEY idx_lecture_teacher (teacher_id),
            KEY idx_lecture_subject (subject_id),
            KEY idx_lecture_weekday (weekday, start_slot),
            CONSTRAINT fk_lecture_source  FOREIGN KEY (source_id)  REFERENCES source(id),
            CONSTRAINT fk_lecture_teacher FOREIGN KEY (teacher_id) REFERENCES teacher(id),
            CONSTRAINT fk_lecture_subject FOREIGN KEY (subject_id) REFERENCES subject(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
    );
    $stmt->execute([$table, $column]);
    if ((int)$stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

function add_index_if_missing(PDO $pdo, string $table, string $index, string $columns): void {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.statistics
         WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?'
    );
    $stmt->execute([$table, $index]);
    if ((int)$stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `$table` ADD INDEX `$index` ($columns)");
    }
}

function add_unique_if_missing(PDO $pdo, string $table, string $index, string $columns): void {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.statistics
         WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?'
    );
    $stmt->execute([$table, $index]);
    if ((int)$stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `$table` ADD UNIQUE KEY `$index` ($columns)");
    }
}

function drop_index_if_present(PDO $pdo, string $table, string $index): void {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.statistics
         WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?'
    );
    $stmt->execute([$table, $index]);
    if ((int)$stmt->fetchColumn() > 0) {
        $pdo->exec("ALTER TABLE `$table` DROP INDEX `$index`");
    }
}

function column_exists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
    );
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function unique_has_columns(PDO $pdo, string $table, string $index, array $expectedCols): bool {
    $stmt = $pdo->prepare(
        'SELECT column_name FROM information_schema.statistics
         WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?
         ORDER BY seq_in_index'
    );
    $stmt->execute([$table, $index]);
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    return $cols === $expectedCols;
}

function upsert_teacher(PDO $pdo, string $name): int {
    $name = trim($name);
    $stmt = $pdo->prepare(
        'INSERT INTO teacher(name, searchable) VALUES(?, ?)
         ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), searchable = VALUES(searchable)'
    );
    $stmt->execute([$name, searchable_text($name)]);
    return (int)$pdo->lastInsertId();
}

function upsert_subject(PDO $pdo, ?string $code, string $name): int {
    $name = trim($name);
    $code = $code !== null ? trim($code) : null;
    $searchable = searchable_text($name);

    // MySQL treats NULLs as distinct in UNIQUE keys, so when code is NULL we must
    // SELECT-then-INSERT to avoid creating duplicate rows for the same name.
    if ($code === null) {
        $sel = $pdo->prepare('SELECT id FROM subject WHERE code IS NULL AND name = ? LIMIT 1');
        $sel->execute([$name]);
        $existing = $sel->fetchColumn();
        if ($existing !== false) {
            // refresh searchable in case the function changed
            $upd = $pdo->prepare('UPDATE subject SET searchable = ? WHERE id = ?');
            $upd->execute([$searchable, (int)$existing]);
            return (int)$existing;
        }
    }

    $stmt = $pdo->prepare(
        'INSERT INTO subject(code, name, searchable) VALUES(?, ?, ?)
         ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), searchable = VALUES(searchable)'
    );
    $stmt->execute([$code, $name, $searchable]);
    return (int)$pdo->lastInsertId();
}

function upsert_source(
    PDO $pdo,
    string $url,
    string $kind,
    int $status,
    int $bytes,
    ?string $sectionTitle = null,
    ?string $displayLabel = null,
    ?string $anchorText   = null
): int {
    $now = time();
    $stmt = $pdo->prepare(
        'INSERT INTO source(url, kind, fetched_at, http_status, bytes, section_title, display_label, anchor_text)
         VALUES(?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
             id            = LAST_INSERT_ID(id),
             kind          = VALUES(kind),
             fetched_at    = VALUES(fetched_at),
             http_status   = VALUES(http_status),
             bytes         = VALUES(bytes),
             section_title = VALUES(section_title),
             display_label = VALUES(display_label),
             anchor_text   = VALUES(anchor_text)'
    );
    $stmt->execute([$url, $kind, $now, $status, $bytes, $sectionTitle, $displayLabel, $anchorText]);
    return (int)$pdo->lastInsertId();
}
