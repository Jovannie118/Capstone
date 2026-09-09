<?php
/**
 * Applications (admin only).
 *   GET applications.php                                  -> paginated list
 *          ?q=name|ref_code|email|phone
 *          &status=submitted|under_verification|ranked|approved|waitlisted|rejected
 *          &doc_status=all|pending|rejected|verified|incomplete
 *          &page=N&per_page=N (default 10, max 500)
 *   GET applications.php?id=3                -> one application (full hydration)
 *   GET applications.php?ref=SCH-..          -> one application by reference code
 *
 * The list is filtered, sorted and paginated in SQL so only the visible page
 * of records is loaded into the browser. Document metadata for those records
 * is fetched with a single grouped query (never the file blobs).
 */
require __DIR__ . '/config.php';
require_admin();

$pdo = db();

if (!empty($_GET['id']) || !empty($_GET['ref'])) {
    if (!empty($_GET['id'])) {
        $stmt = $pdo->prepare('SELECT * FROM applications WHERE id = ?');
        $stmt->execute([(int) $_GET['id']]);
    } else {
        $stmt = $pdo->prepare('SELECT * FROM applications WHERE ref_code = ?');
        $stmt->execute([trim((string) $_GET['ref'])]);
    }
    $app = $stmt->fetch();
    if (!$app) {
        json_out(['error' => 'Application not found.'], 404);
    }
    json_out(['application' => hydrate($app)]);
}

// ------------------------- list (search / filter / paginate) ----------------

$q         = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
$status    = $_GET['status'] ?? 'all';
$docStatus = $_GET['doc_status'] ?? 'all';
$page      = max(1, (int) ($_GET['page'] ?? 1));
$perPage   = min(500, max(1, (int) ($_GET['per_page'] ?? 10)));

$allowedStatus = ['submitted', 'under_verification', 'ranked', 'approved', 'waitlisted', 'rejected'];
$allowedDoc    = ['all', 'pending', 'rejected', 'verified', 'incomplete'];

$from = 'FROM (
    SELECT a.id, a.ref_code, a.full_name, a.email, a.phone, a.school, a.status,
           a.requested, a.submitted_at, a.gpa, a.household_income, a.extracurricular, a.essay_score,
           IFNULL(d.d, 0) AS doc_total, IFNULL(v.d, 0) AS doc_verified,
           IFNULL(p.d, 0) AS doc_pending, IFNULL(r.d, 0) AS doc_rejected
    FROM applications a
    LEFT JOIN (SELECT application_id, COUNT(*) AS d FROM documents GROUP BY application_id) d ON d.application_id = a.id
    LEFT JOIN (SELECT application_id, COUNT(*) AS d FROM documents WHERE status = \'verified\' GROUP BY application_id) v ON v.application_id = a.id
    LEFT JOIN (SELECT application_id, COUNT(*) AS d FROM documents WHERE status = \'pending\' GROUP BY application_id) p ON p.application_id = a.id
    LEFT JOIN (SELECT application_id, COUNT(*) AS d FROM documents WHERE status = \'rejected\' GROUP BY application_id) r ON r.application_id = a.id
) t';

$where  = [];
$params = [];

if ($q !== '') {
    $where[] = "(t.full_name LIKE ? OR t.ref_code LIKE ? OR t.email LIKE ?
                  OR REPLACE(t.phone, ' ', '') LIKE ?
                  OR REPLACE(REPLACE(t.phone, ' ', ''), '+63', '0') LIKE ?)";
    $like    = '%' . $q . '%';
    $qPhone  = str_replace(' ', '', $q);
    array_push($params, $like, $like, $like, '%' . $qPhone . '%', '%' . $qPhone . '%');
}
if (in_array($status, $allowedStatus, true)) {
    $where[] = 't.status = ?';
    $params[] = $status;
}
switch ($docStatus) {
    case 'pending':
        $where[] = 't.doc_pending > 0';
        break;
    case 'rejected':
        $where[] = 't.doc_rejected > 0';
        break;
    case 'verified':
        $where[] = 't.doc_total > 0 AND t.doc_verified = t.doc_total';
        break;
    case 'incomplete':
        $where[] = 't.doc_total > 0 AND t.doc_verified < t.doc_total';
        break;
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare('SELECT COUNT(*) ' . $from . $whereSql);
$countStmt->execute($params);
$total  = (int) $countStmt->fetchColumn();
$pages  = max(1, (int) ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

$listSql = 'SELECT t.id, t.ref_code, t.full_name, t.email, t.phone, t.school, t.status,
                   t.requested, t.submitted_at, t.gpa, t.household_income,
                   t.extracurricular, t.essay_score,
                   t.doc_total, t.doc_verified, t.doc_pending, t.doc_rejected '
    . $from . $whereSql . ' ORDER BY t.submitted_at DESC, t.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset;

$rows  = null;
$stmt  = $pdo->prepare($listSql);
$stmt->execute($params);
$rows  = $stmt->fetchAll();
$apps  = [];

if ($rows) {
    $ids     = array_column($rows, 'id');
    $in      = implode(',', array_fill(0, count($ids), '?'));
    $docStmt = $pdo->prepare(
        'SELECT id, application_id, doc_type, file_name, mime_type, file_size, uploaded_at, status, note
         FROM documents WHERE application_id IN (' . $in . ') ORDER BY id'
    );
    $docStmt->execute($ids);

    $byApp = [];
    foreach ($docStmt->fetchAll() as $d) {
        $byApp[(int) $d['application_id']][] = $d;
    }

    foreach ($rows as $row) {
        $docs                = $byApp[(int) $row['id']] ?? [];
        $row['documents']    = $docs;
        $row['score']        = score_of($row, $docs);
        $row['status_label'] = STATUS_LABEL[$row['status']] ?? $row['status'];
        $apps[]              = $row;
    }
}

$budget = (int) ($pdo->query("SELECT svalue FROM settings WHERE skey = 'budget'")->fetchColumn() ?: 0);

json_out([
    'applications' => $apps,
    'total'        => $total,
    'page'         => $page,
    'per_page'     => $perPage,
    'pages'        => $pages,
    'budget'       => $budget,
]);