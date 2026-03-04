<? php

// Add type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_type'])) {
    $name = sanitize($_POST['type_name'] ?? '');
    $desc = sanitize($_POST['description'] ?? '');

    if (!$name) {
        $err = 'Type name is required.';
    } else {
        $stmt = $conn->prepare("INSERT INTO incident_types (name) VALUES (?)");
        $stmt->bind_param("s", $name);
        if ($stmt->execute()) {
            $msg = "Incident type \"$name\" added.";
        } else {
            $err = 'Failed to add type.';
        }
    }
}


?>