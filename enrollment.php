<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

$action = $_GET['action'] ?? 'list';

// Delete
if ($action === 'delete' && isset($_GET['id'])) {
    $EnrollmentID = intval($_GET['id']);

    $res = $conn->query("SELECT StudentId FROM enrollment WHERE EnrollmentID = $EnrollmentID");
    if ($res && $row = $res->fetch_assoc()) {
        $StudentId = intval($row['StudentId']);
        $conn->query("DELETE FROM enrollment WHERE EnrollmentID = $EnrollmentID");
        $conn->query("DELETE FROM student WHERE StudentId = $StudentId");
    }
    header("Location: enrollment.php?msg=deleted");
    exit;
}

// Create
$errors   = [];
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'list') {

    // Grab raw POST values
    $FirstName      = trim($_POST['FirstName']      ?? '');
    $LastName       = trim($_POST['LastName']        ?? '');
    $Email          = trim($_POST['Email']           ?? '');
    $CourseName     = trim($_POST['CourseName']      ?? '');
    $EnrollmentDate = trim($_POST['EnrollmentDate']  ?? '');

    // Keep for repopulating form on error
    $formData = compact('FirstName','LastName','Email','CourseName','EnrollmentDate');

    // Validate
    if ($FirstName === '')      $errors[] = "First name is required.";
    if ($LastName === '')       $errors[] = "Last name is required.";
    if ($Email === '')          $errors[] = "Email is required.";
    if ($CourseName === '')     $errors[] = "Course name is required.";
    if ($EnrollmentDate === '') $errors[] = "Enrollment date is required.";

    if (empty($errors)) {
        // Escape safely
        $fn  = $conn->real_escape_string($FirstName);
        $ln  = $conn->real_escape_string($LastName);
        $em  = $conn->real_escape_string($Email);
        $cn  = $conn->real_escape_string($CourseName);
        $ed  = $conn->real_escape_string($EnrollmentDate);

        // Insert student
        $ins1 = $conn->query("INSERT INTO student (FirstName, LastName, Email)
                               VALUES ('$fn', '$ln', '$em')");

        if (!$ins1) {
            $errors[] = "DB Error (student): " . $conn->error;
        } else {
            $StudentId = $conn->insert_id;

            if ($StudentId == 0) {
                $errors[] = "Could not get new StudentId after insert.";
            } else {
                // Insert enrollment
                $ins2 = $conn->query("INSERT INTO enrollment (StudentId, CourseName, EnrollmentDate)
                                       VALUES ($StudentId, '$cn', '$ed')");
                if (!$ins2) {
                    $errors[] = "DB Error (enrollment): " . $conn->error;
                    // Roll back student row so we don't leave orphan
                    $conn->query("DELETE FROM student WHERE StudentId = $StudentId");
                } else {
                    header("Location: enrollment.php?msg=added");
                    exit;
                }
            }
        }
    }
}

// Update
$rec = [];
if ($action === 'edit') {
    $EnrollmentID = intval($_GET['id'] ?? 0);
    if ($EnrollmentID <= 0) { header("Location: enrollment.php"); exit; }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $FirstName      = trim($_POST['FirstName']     ?? '');
        $LastName       = trim($_POST['LastName']      ?? '');
        $Email          = trim($_POST['Email']         ?? '');
        $CourseName     = trim($_POST['CourseName']    ?? '');
        $EnrollmentDate = trim($_POST['EnrollmentDate']?? '');

        if ($FirstName === '')  $errors[] = "First name is required.";
        if ($LastName === '')   $errors[] = "Last name is required.";
        if ($Email === '')      $errors[] = "Email is required.";
        if ($CourseName === '') $errors[] = "Course name is required.";

        if (empty($errors)) {
            $fn = $conn->real_escape_string($FirstName);
            $ln = $conn->real_escape_string($LastName);
            $em = $conn->real_escape_string($Email);
            $cn = $conn->real_escape_string($CourseName);
            $ed = $conn->real_escape_string($EnrollmentDate);

            $sidRes = $conn->query("SELECT StudentId FROM enrollment WHERE EnrollmentID = $EnrollmentID");
            if ($sidRes && $sidRow = $sidRes->fetch_assoc()) {
                $sid = intval($sidRow['StudentId']);
                $conn->query("UPDATE student SET FirstName='$fn', LastName='$ln', Email='$em' WHERE StudentId = $sid");
                $conn->query("UPDATE enrollment SET CourseName='$cn', EnrollmentDate='$ed' WHERE EnrollmentID = $EnrollmentID");
                header("Location: enrollment.php?msg=updated");
                exit;
            } else {
                $errors[] = "Enrollment record not found.";
            }
        }
        $rec = $_POST;
        $rec['EnrollmentID'] = $EnrollmentID;

    } else {
        $res = $conn->query("
            SELECT e.EnrollmentID, e.CourseName, e.EnrollmentDate,
                   s.StudentId, s.FirstName, s.LastName, s.Email
            FROM enrollment e
            JOIN student s ON e.StudentId = s.StudentId
            WHERE e.EnrollmentID = $EnrollmentID
        ");
        if (!$res || $res->num_rows === 0) { header("Location: enrollment.php"); exit; }
        $rec = $res->fetch_assoc();
    }
}

// Flash Messages
$flashMsg = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'added')   $flashMsg = '<div class="alert success">Student enrolled successfully!</div>';
    if ($_GET['msg'] === 'updated') $flashMsg = '<div class="alert success">Record updated successfully!</div>';
    if ($_GET['msg'] === 'deleted') $flashMsg = '<div class="alert danger">Record deleted.</div>';
}

// Fetch Records for Table Listing
$listResult = null;
if ($action === 'list') {
    $listResult = $conn->query("
        SELECT e.EnrollmentID, e.CourseName, e.EnrollmentDate,
               s.StudentId, s.FirstName, s.LastName, s.Email
        FROM enrollment e
        JOIN student s ON e.StudentId = s.StudentId
        ORDER BY e.EnrollmentID DESC
    ");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Course Enrollment System</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>

<?php if ($action === 'list'): ?>
<!-- MAIN PAGE  —  Form (left) + Table (right) -->
 
<div class="page-wrapper">

  <?= $flashMsg ?>

  <div class="layout">

    <!-- LEFT: FORM -->
    <div class="form-panel">
      <div class="form-title">
        Enroll Student Form
      </div>

      <?php if (!empty($errors)): ?>
      <div class="error-box">
        <ul><?php foreach ($errors as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?></ul>
      </div>
      <?php endif; ?>

      <!-- POST to enrollment.php (no query string = action stays 'list') -->
      <form method="POST" action="enrollment.php">
        <div class="form-group">
          <label for="FirstName">First Name</label>
          <input type="text" id="FirstName" name="FirstName"
                 placeholder="John"
                 value="<?= htmlspecialchars($formData['FirstName'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="LastName">Last Name</label>
          <input type="text" id="LastName" name="LastName"
                 placeholder="Doe"
                 value="<?= htmlspecialchars($formData['LastName'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="Email">Email Address</label>
          <input type="email" id="Email" name="Email"
                 placeholder="name@example.com"
                 value="<?= htmlspecialchars($formData['Email'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="CourseName">Course Name</label>
          <input type="text" id="CourseName" name="CourseName"
                 placeholder="e.g. Computer Science"
                 value="<?= htmlspecialchars($formData['CourseName'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="EnrollmentDate">Enrollment Date</label>
          <input type="date" id="EnrollmentDate" name="EnrollmentDate"
                 value="<?= htmlspecialchars($formData['EnrollmentDate'] ?? '') ?>">
        </div>
        <button type="submit" class="btn-submit">Complete Enrollment</button>
      </form>
    </div><!-- end form-panel -->

    <!-- RIGHT: TABLE -->
    <div class="table-panel">
      <div class="tbl-head">
        <span>Name</span>
        <span>Course</span>
        <span>Date</span>
        <span>Actions</span>
      </div>

      <?php if ($listResult && $listResult->num_rows > 0):
            while ($row = $listResult->fetch_assoc()): ?>
      <div class="tbl-row">
        <div>
          <div class="student-name">
            <?= htmlspecialchars(strtoupper($row['FirstName'] . ' ' . $row['LastName'])) ?>
          </div>
          <div class="student-email"><?= htmlspecialchars($row['Email']) ?></div>
        </div>
        <div>
          <span class="course-badge" title="<?= htmlspecialchars($row['CourseName']) ?>">
            <?= htmlspecialchars($row['CourseName']) ?>
          </span>
        </div>
        <div class="enroll-date">
          <?= date('M d, Y', strtotime($row['EnrollmentDate'])) ?>
        </div>
        <div class="row-actions">
          <a href="enrollment.php?action=edit&id=<?= $row['EnrollmentID'] ?>" class="link-edit">Edit</a>
          <span class="sep">·</span>
          <a href="enrollment.php?action=delete&id=<?= $row['EnrollmentID'] ?>"
             class="link-delete"
             onclick="return confirm('Delete this record?')">Delete</a>
        </div>
      </div>
      <?php endwhile; else: ?>
      <div class="empty-state">No enrollment records yet.</div>
      <?php endif; ?>

    </div><!-- end table-panel -->

  </div><!-- end layout -->
</div><!-- end page-wrapper -->


<?php elseif ($action === 'edit'): ?>
<!-- ══════════════════════════════════════════
     EDIT PAGE
══════════════════════════════════════════ -->
<div class="page-wrapper">
  <div class="edit-wrapper">
    <a href="enrollment.php" class="btn-back">&larr; Back</a>
    <div class="edit-badge">Editing ID: <?= $rec['EnrollmentID'] ?></div>
    <div class="page-title">Edit Enrollment</div>
    <div class="page-sub">Update student and enrollment information</div>

    <?php if (!empty($errors)): ?>
    <div class="error-box">
      <ul><?php foreach ($errors as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?></ul>
    </div>
    <?php endif; ?>

    <div class="form-panel">
      <form method="POST" action="enrollment.php?action=edit&id=<?= $rec['EnrollmentID'] ?>">
        <div class="form-group">
          <label>First Name</label>
          <input type="text" name="FirstName"
                 value="<?= htmlspecialchars($rec['FirstName'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label>Last Name</label>
          <input type="text" name="LastName"
                 value="<?= htmlspecialchars($rec['LastName'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label>Email Address</label>
          <input type="email" name="Email"
                 value="<?= htmlspecialchars($rec['Email'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label>Course Name</label>
          <input type="text" name="CourseName"
                 value="<?= htmlspecialchars($rec['CourseName'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label>Enrollment Date</label>
          <input type="date" name="EnrollmentDate"
                 value="<?= htmlspecialchars($rec['EnrollmentDate'] ?? '') ?>" required>
        </div>
        <button type="submit" class="btn-update">Save Changes</button>
      </form>
    </div>
  </div>
</div>

<?php endif; ?>

<footer>Online Course Enrollment System &mdash; CST5L Lab Exam 2</footer>
</body>
</html>