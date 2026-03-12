<?php

use App\Support\Session;

$success = Session::getFlash('success');
$error = Session::getFlash('error');
?>

<?php if (!empty($success)): ?>
    <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
        <?= htmlspecialchars((string) $success) ?>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <?= htmlspecialchars((string) $error) ?>
    </div>
<?php endif; ?>