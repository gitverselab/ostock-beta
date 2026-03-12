<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include 'db.php';
include 'navbar.php';

// DEBUG: show PHP errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set('Asia/Manila');

// load only your four chilled variants
$flavors = [];
$flavorRes = $conn->query("
    SELECT id, name
      FROM items
     WHERE name IN (
       'LECHE FLAN - MI - CHILLED',
       'LECHE FLAN - RETAIL - CHILLED',
       'UBE HALAYA - MI - CHILLED',
       'UBE HALAYA - RETAIL - CHILLED'
     )
     ORDER BY name
");
while ($row = $flavorRes->fetch_assoc()) {
    $flavors[] = $row;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (empty($_POST['inventory_ids'])) {
        echo "<script>alert('No pallets selected!');history.back();</script>";
        exit;
    }

    $warehouse_id  = intval($_POST['warehouse_id']);
    $from_item_id  = intval($_POST['from_item_id']);
    $to_item_id    = intval($_POST['to_item_id']);
    $inventory_ids = $_POST['inventory_ids'];

    $now       = date('Y-m-d H:i:s');
    $processed = $_SESSION['username'] ?? 'unknown';

    $conn->begin_transaction();
    $error = false;
    $msg   = "";

    foreach ($inventory_ids as $inv_id) {
        // 1) fetch the “from” record
        $st = $conn->prepare("SELECT * FROM inventory WHERE id=? AND warehouse_id=?");
        $st->bind_param("ii", $inv_id, $warehouse_id);
        $st->execute();
        $res = $st->get_result();
        if ($res->num_rows === 0) {
            $error = true;
            $msg = "Record ID $inv_id not found.";
            $st->close();
            break;
        }
        $item = $res->fetch_assoc();
        $st->close();

        // preserve fields
        $qty    = $item['quantity'];
        $ippc   = $item['items_per_pc'];
        $pallet = $item['pallet_id'];
        $prod   = $item['production_date'];
        $exp    = $item['expiry_date'];
        $uom    = $item['uom'];

        // 2) outbound_inventory
        $so = $conn->prepare("
            INSERT INTO outbound_inventory 
              (item_id, pallet_id, quantity_removed, warehouse_id, items_per_pc,
               outbound_type, date_removed, transfer_id, processed_by, production_date, expiry_date)
            VALUES (?,?,?,?,?, 'Reboxing Outbound', ?, NULL, ?, ?, ?)
        ");
        $so->bind_param(
            "isiiissss",
            $from_item_id,
            $pallet,
            $qty,
            $warehouse_id,
            $ippc,
            $now,
            $processed,
            $prod,
            $exp
        );
        if (!$so->execute()) {
            $error = true;
            $msg = "Outbound failed for record $inv_id: " . $so->error;
            $so->close();
            break;
        }
        $out_id = $conn->insert_id;
        $so->close();

        // 3) delete original
        $sd = $conn->prepare("DELETE FROM inventory WHERE id=?");
        $sd->bind_param("i", $inv_id);
        if (!$sd->execute()) {
            $error = true;
            $msg = "Delete failed for record $inv_id: " . $sd->error;
            $sd->close();
            break;
        }
        $sd->close();

        // 4) insert inbound (“to” variant)
        $si = $conn->prepare("
            INSERT INTO inventory 
              (item_id, quantity, uom, expiry_date, production_date,
               pallet_id, warehouse_id, items_per_pc, date_received, transfer_id, processed_by)
            VALUES (?,?,?,?,?,?,?,?,?, NULL, ?)
        ");
        $si->bind_param(
            //  1    2    3    4    5     6      7       8           9         10
            "iisssiisss",
            $to_item_id,   // item_id
            $qty,          // quantity
            $uom,          // uom
            $exp,          // expiry_date
            $prod,         // production_date
            $pallet,       // pallet_id (if VARCHAR change this to "s")
            $warehouse_id, // warehouse_id
            $ippc,         // items_per_pc
            $now,          // date_received
            $processed     // processed_by
        );
        if (!$si->execute()) {
            $error = true;
            $msg = "Inbound failed for pallet $pallet: " . $si->error;
            $si->close();
            break;
        }
        $in_id = $conn->insert_id;
        $si->close();

        // 5) log in reboxing_transactions
        $sr = $conn->prepare("
            INSERT INTO reboxing_transactions
              (warehouse_id, from_item_id, to_item_id, inventory_id,
               pallet_id, quantity, items_per_pc, production_date, expiry_date, date_reboxed, processed_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)
        ");
        $sr->bind_param(
            "iiiisiissss",
            $warehouse_id,
            $from_item_id,
            $to_item_id,
            $inv_id,
            $pallet,
            $qty,
            $ippc,
            $prod,
            $exp,
            $now,
            $processed
        );
        if (!$sr->execute()) {
            $error = true;
            $msg = "Logging failed for pallet $pallet: " . $sr->error;
            $sr->close();
            break;
        }
        $sr->close();

        // (Optional) history entries…
    }

    if ($error) {
        $conn->rollback();
        echo "<script>alert('Reboxing failed: " . addslashes($msg) . "');window.location='reboxing.php';</script>";
    } else {
        $conn->commit();
        echo "<script>alert('Reboxing completed successfully!');window.location='reboxing.php';</script>";
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reboxing</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- jQuery & Debug -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
    $(document).ready(function(){
      console.log("Reboxing page ready");
      $('#warehouse_id, #from_item_id').on('change', function(){
        const w = $('#warehouse_id').val(),
              f = $('#from_item_id').val();
        console.log("Loading pallets for:", { warehouse: w, fromItem: f });
        if (w && f) {
          $.post('get_original_inventory.php', { warehouse_id: w, item_id: f })
           .done(html => $('#inventoryList').html(html))
           .fail((xhr,status,err) => {
             console.error("AJAX error:", status, err);
             $('#inventoryList').html("<p class='text-danger'>Error loading pallets.</p>");
           });
        } else {
          $('#inventoryList').html("<p class='text-muted'>Choose both warehouse & “From Variant” …</p>");
        }
      });
      $('form').on('submit', function(){
        console.log("Submitting form, selected pallets:", $('input[name=\"inventory_ids[]\"]:checked').map(function(){return this.value;}).get());
      });
    });
  </script>
  <style>
  body {
    padding-top: 70px; /* adjust if your navbar height differs */
  }
</style>
</head>
<body>
<div class="container mt-4">
  <h2>Reboxing</h2>
  <form method="POST" action="reboxing.php">
    <div class="row mb-3">
      <div class="col-md-4">
        <label>Warehouse</label>
        <select id="warehouse_id" name="warehouse_id" class="form-control" required>
          <option value="">– select –</option>
          <?php
            $wQ = $conn->query("SELECT id,name FROM warehouses ORDER BY name");
            while($w = $wQ->fetch_assoc()) {
              echo "<option value='{$w['id']}'>".htmlspecialchars($w['name'])."</option>";
            }
          ?>
        </select>
      </div>
      <div class="col-md-4">
        <label>From Variant</label>
        <select id="from_item_id" name="from_item_id" class="form-control" required>
          <option value="">– select –</option>
          <?php foreach($flavors as $f): ?>
            <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label>To Variant</label>
        <select id="to_item_id" name="to_item_id" class="form-control" required>
          <option value="">– select –</option>
          <?php foreach($flavors as $f): ?>
            <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="mb-3" id="inventoryList">
      <label>Select Pallets to Rebox</label>
      <p class="text-muted">Choose warehouse & “From Variant” to load pallets…</p>
    </div>

    <button type="submit" class="btn btn-primary">Process Reboxing</button>
  </form>

  <hr>
  <h3>Reboxing History</h3>
  <table class="table table-striped table-bordered">
    <thead class="table-dark text-center">
      <tr>
        <th>#</th><th>From</th><th>To</th><th>Pallet</th><th>Qty</th>
        <th>Per PC</th><th>Prod Date</th><th>Expiry</th><th>Date</th><th>By</th><th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php
        $hist = $conn->query("SELECT * FROM reboxing_transactions ORDER BY date_reboxed DESC");
        if ($hist && $hist->num_rows) {
          while ($r = $hist->fetch_assoc()) {
            echo "<tr class='text-center'>
                    <td>{$r['id']}</td>
                    <td>{$r['from_item_id']}</td>
                    <td>{$r['to_item_id']}</td>
                    <td>{$r['pallet_id']}</td>
                    <td>{$r['quantity']}</td>
                    <td>{$r['items_per_pc']}</td>
                    <td>{$r['production_date']}</td>
                    <td>{$r['expiry_date']}</td>
                    <td>{$r['date_reboxed']}</td>
                    <td>{$r['processed_by']}</td>
                    <td>
                      <a href='edit_reboxing.php?id={$r['id']}' class='btn btn-sm btn-warning'>Edit</a>
                      <a href='delete_reboxing.php?id={$r['id']}' class='btn btn-sm btn-danger' onclick=\"return confirm('Are you sure?')\">Del</a>
                    </td>
                  </tr>";
          }
        } else {
          echo "<tr><td colspan='11' class='text-center'>No records found.</td></tr>";
        }
      ?>
    </tbody>
  </table>
</div>
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
